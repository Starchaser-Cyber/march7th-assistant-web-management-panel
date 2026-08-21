#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""三月七云游戏画面预览转发服务 v2: CDP screencast -> WebSocket 帧流(MJPEG备用)"""
import json, base64, hashlib, select, threading
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from urllib.request import urlopen
from urllib.parse import urlparse, parse_qs
import websocket

CDP_PORT = 9222
LISTEN_PORT = 9223
WS_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'
RES = {'720p': (1280, 720, 70), '480p': (854, 480, 60)}

class SC:
    def __init__(self):
        self.lock = threading.Lock()
        self.clients = {}
        self.params = None
        self.stop = True
        self.thread = None

    def page(self):
        ps = json.loads(urlopen('http://127.0.0.1:%d/json' % CDP_PORT, timeout=3).read())
        return next((t for t in ps if t.get('type') == 'page'), None)

    def run(self):
        ws = None
        try:
            pg = self.page()
            if not pg:
                raise RuntimeError('no page')
            ws = websocket.create_connection(pg['webSocketDebuggerUrl'], timeout=10)
            ws.settimeout(0.5)
            w, h, q = self.params
            ws.send(json.dumps({'id': 1, 'method': 'Page.startScreencast',
                                'params': {'format': 'jpeg', 'quality': q,
                                           'maxWidth': w, 'maxHeight': h, 'everyNthFrame': 1}}))
            n = 0
            while not self.stop:
                try:
                    m = json.loads(ws.recv())
                except websocket.WebSocketTimeoutException:
                    continue
                except Exception:
                    break
                if m.get('method') == 'Page.screencastFrame':
                    n += 1
                    d = base64.b64decode(m['params']['data'])
                    self.broadcast(d)
                    try:
                        ws.send(json.dumps({'id': 100 + n, 'method': 'Page.screencastFrameAck',
                                            'params': {'sessionId': m['params'].get('sessionId')}}))
                    except Exception:
                        break
        except Exception:
            pass
        finally:
            try:
                if ws:
                    ws.send(json.dumps({'id': 9999, 'method': 'Page.stopScreencast'}))
            except Exception:
                pass
            try:
                if ws:
                    ws.close()
            except Exception:
                pass

    def broadcast(self, data):
        dead = []
        with self.lock:
            for s in list(self.clients):
                try:
                    s.sendall(self.frame(data))
                except Exception:
                    dead.append(s)
            for s in dead:
                self.clients.pop(s, None)
                try:
                    s.close()
                except Exception:
                    pass

    @staticmethod
    def frame(p):
        h = bytearray([0x82])
        n = len(p)
        if n < 126:
            h.append(n)
        elif n < 65536:
            h.append(126); h += n.to_bytes(2, 'big')
        else:
            h.append(127); h += n.to_bytes(8, 'big')
        return bytes(h) + p

    def add(self, s, params):
        with self.lock:
            self.clients[s] = params
        self.refresh()

    def remove(self, s):
        with self.lock:
            self.clients.pop(s, None)
        self.refresh()

    def refresh(self):
        with self.lock:
            if self.clients:
                np = list(self.clients.values())[-1]
                if np == self.params and self.thread and self.thread.is_alive():
                    return
                self.params = np
            else:
                self.params = None
            self.stop = True
            old = self.thread
            self.thread = None
            if not self.params:
                return
        if old and old.is_alive():
            old.join(timeout=3)
        with self.lock:
            if self.clients and self.params:
                self.stop = False
                self.thread = threading.Thread(target=self.run, daemon=True)
                self.thread.start()

sc = SC()

class H(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *a):
        pass

    def do_GET(self):
        u = urlparse(self.path)
        if u.path == '/health':
            self.json({'ok': True, 'cdp': self.cdp()})
        elif u.path == '/ws':
            self.ws(parse_qs(u.query))
        elif u.path.startswith('/stream'):
            self.mjpeg(parse_qs(u.query))
        else:
            self.json({'error': 'not found'}, 404)

    def cdp(self):
        try:
            urlopen('http://127.0.0.1:%d/json' % CDP_PORT, timeout=2)
            return True
        except Exception:
            return False

    def json(self, o, code=200):
        b = json.dumps(o).encode()
        self.send_response(code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def params(self, qs):
        r = qs.get('res', ['720p'])[0]
        w, h, q = RES.get(r, RES['720p'])
        if 'quality' in qs:
            q = int(qs['quality'][0])
        if 'maxWidth' in qs and 'maxHeight' in qs:
            w, h = int(qs['maxWidth'][0]), int(qs['maxHeight'][0])
        return (w, h, q)

    def ws(self, qs):
        key = self.headers.get('Sec-WebSocket-Key')
        if not key:
            self.json({'error': 'need upgrade'}, 400)
            return
        acc = base64.b64encode(hashlib.sha1((key + WS_GUID).encode()).digest()).decode()
        self.send_response(101)
        self.send_header('Upgrade', 'websocket')
        self.send_header('Connection', 'Upgrade')
        self.send_header('Sec-WebSocket-Accept', acc)
        self.end_headers()
        s = self.connection
        s.settimeout(0.2)
        sc.add(s, self.params(qs))
        try:
            while True:
                if not self.poll(s):
                    break
        except Exception:
            pass
        finally:
            sc.remove(s)

    @staticmethod
    def poll(s):
        try:
            r, _, _ = select.select([s], [], [], 0.2)
            if not r:
                return True
            hdr = s.recv(2)
            if len(hdr) < 2:
                return False
            op = hdr[0] & 0x0F
            mk = hdr[1] & 0x80
            ln = hdr[1] & 0x7F
            if ln == 126:
                ln = int.from_bytes(s.recv(2), 'big')
            elif ln == 127:
                ln = int.from_bytes(s.recv(8), 'big')
            mk4 = s.recv(4) if mk else b''
            p = b''
            while len(p) < ln:
                c = s.recv(ln - len(p))
                if not c:
                    return False
                p += c
            if op == 0x9:
                if mk:
                    p = bytes(c ^ mk4[i % 4] for i, c in enumerate(p))
                s.sendall(bytes([0x8A, len(p)]) + p)
            elif op == 0x8:
                return False
            return True
        except Exception:
            return False

    def mjpeg(self, qs):
        try:
            pg = sc.page()
            if not pg:
                raise RuntimeError('no page')
        except Exception as e:
            self.json({'error': 'CDP unavailable: %s' % e}, 503)
            return
        w, h, q = self.params(qs)
        self.send_response(200)
        self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
        self.send_header('Cache-Control', 'no-cache')
        self.end_headers()
        ws = None
        try:
            ws = websocket.create_connection(pg['webSocketDebuggerUrl'], timeout=10)
            ws.send(json.dumps({'id': 1, 'method': 'Page.startScreencast',
                                'params': {'format': 'jpeg', 'quality': q,
                                           'maxWidth': w, 'maxHeight': h, 'everyNthFrame': 1}}))
            n = 0
            while True:
                m = json.loads(ws.recv())
                if m.get('method') == 'Page.screencastFrame':
                    n += 1
                    d = base64.b64decode(m['params']['data'])
                    self.wfile.write(b'--frame\r\nContent-Type: image/jpeg\r\nContent-Length: '
                                     + str(len(d)).encode() + b'\r\n\r\n' + d + b'\r\n')
                    self.wfile.flush()
                    ws.send(json.dumps({'id': 100 + n, 'method': 'Page.screencastFrameAck',
                                        'params': {'sessionId': m['params'].get('sessionId')}}))
        except Exception:
            pass
        finally:
            try:
                if ws:
                    ws.send(json.dumps({'id': 9999, 'method': 'Page.stopScreencast'}))
            except Exception:
                pass
            try:
                if ws:
                    ws.close()
            except Exception:
                pass

if __name__ == '__main__':
    print('preview v2 on 0.0.0.0:%d (/ws /stream /health)' % LISTEN_PORT)
    srv = ThreadingHTTPServer(('0.0.0.0', LISTEN_PORT), H)
    srv.daemon_threads = True
    srv.serve_forever()
