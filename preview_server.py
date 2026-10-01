#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""三月七云游戏画面预览转发服务 v3.1: CDP screencast -> WebSocket 帧流
v3.1: 每客户端独立screencast会话(无共享竞态) + 连接即推首帧(captureScreenshot)
      + token鉴权(HMAC按天轮换) + Chrome140 origin修复(suppress_origin) + 断线自动重连
"""
import json, base64, hashlib, hmac, select, threading, time
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from urllib.request import urlopen
from urllib.parse import urlparse, parse_qs
import websocket

CDP_PORT = 9222
LISTEN_PORT = 9223
WS_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'
RES = {'720p': (1280, 720, 70), '480p': (854, 480, 60)}
SECRET_FILE = '/m7a/logs/preview_secret'

_client_lock = threading.Lock()
_client_count = [0]


def day_token(secret):
    return hmac.new(secret.encode(), time.strftime('%Y%m%d', time.gmtime()).encode(), hashlib.sha256).hexdigest()[:32]


def check_token(qs):
    try:
        with open(SECRET_FILE) as f:
            secret = f.read().strip()
        if not secret:
            return False
    except Exception:
        return False
    tok = qs.get('token', [''])[0]
    if not tok:
        return False
    return hmac.compare_digest(tok, day_token(secret))


def get_page():
    ps = json.loads(urlopen('http://127.0.0.1:%d/json' % CDP_PORT, timeout=3).read())
    return next((t for t in ps if t.get('type') == 'page'), None)


def ws_frame(p):
    h = bytearray([0x82])
    n = len(p)
    if n < 126:
        h.append(n)
    elif n < 65536:
        h.append(126); h += n.to_bytes(2, 'big')
    else:
        h.append(127); h += n.to_bytes(8, 'big')
    return bytes(h) + p


def client_feed(sock, params):
    """单个客户端的独立 screencast 会话：首帧快照 + 变化帧流，断线自动重连"""
    w, h, q = params
    while True:
        try:
            alive = True

            def send_frame(data):
                # 发送失败视为客户端断开，跳出重连循环
                nonlocal alive
                if not alive:
                    return False
                try:
                    sock.sendall(ws_frame(data))
                    return True
                except Exception:
                    alive = False
                    return False

            pg = get_page()
            if not pg:
                raise RuntimeError('no page')
            cws = websocket.create_connection(pg['webSocketDebuggerUrl'], timeout=10,
                                              suppress_origin=True)
            cws.settimeout(0.5)
            cws.send(json.dumps({'id': 1, 'method': 'Page.startScreencast',
                                 'params': {'format': 'jpeg', 'quality': q,
                                            'maxWidth': w, 'maxHeight': h, 'everyNthFrame': 1}}))
            # 首帧快照：无论画面是否静止，连接后立即有画面
            cws.send(json.dumps({'id': 2, 'method': 'Page.captureScreenshot',
                                 'params': {'format': 'jpeg', 'quality': q}}))
            n = 0
            while alive:
                try:
                    m = json.loads(cws.recv())
                except websocket.WebSocketTimeoutException:
                    continue
                except Exception:
                    break  # CDP 断开（浏览器重启等），重连
                if m.get('id') == 2 and 'result' in m:
                    try:
                        d = base64.b64decode(m['result'].get('data', ''))
                        if d and not send_frame(d):
                            break
                    except Exception:
                        pass
                    continue
                if m.get('method') == 'Page.screencastFrame':
                    n += 1
                    try:
                        d = base64.b64decode(m['params']['data'])
                        if not send_frame(d):
                            break
                        cws.send(json.dumps({'id': 100 + n, 'method': 'Page.screencastFrameAck',
                                             'params': {'sessionId': m['params'].get('sessionId')}}))
                    except Exception:
                        break
            try:
                cws.send(json.dumps({'id': 9999, 'method': 'Page.stopScreencast'}))
            except Exception:
                pass
            try:
                cws.close()
            except Exception:
                pass
        except Exception:
            pass
        # 客户端已断开则退出；否则 1 秒后重连 CDP（浏览器重启自愈）
        if not alive:
            break
        time.sleep(1.0)


class H(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *a):
        pass

    def do_GET(self):
        u = urlparse(self.path)
        if u.path == '/health':
            with _client_lock:
                cnt = _client_count[0]
            self.json({'ok': True, 'cdp': self.cdp(), 'clients': cnt})
        elif u.path in ('/ws', '/m7a-ws'):
            qs = parse_qs(u.query)
            if not check_token(qs):
                self.json({'error': 'forbidden'}, 403)
                return
            self.ws(qs)
        elif u.path.startswith('/stream'):
            qs = parse_qs(u.query)
            if not check_token(qs):
                self.json({'error': 'forbidden'}, 403)
                return
            self.mjpeg(qs)
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
        with _client_lock:
            _client_count[0] += 1
        threading.Thread(target=client_feed, args=(s, self.params(qs)), daemon=True).start()
        try:
            while True:
                if not self.poll(s):
                    break
        except Exception:
            pass
        finally:
            with _client_lock:
                _client_count[0] -= 1
            try:
                s.close()
            except Exception:
                pass

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
            pg = get_page()
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
        cws = None
        try:
            cws = websocket.create_connection(pg['webSocketDebuggerUrl'], timeout=10,
                                              suppress_origin=True)
            cws.send(json.dumps({'id': 1, 'method': 'Page.startScreencast',
                                 'params': {'format': 'jpeg', 'quality': q,
                                            'maxWidth': w, 'maxHeight': h, 'everyNthFrame': 1}}))
            cws.send(json.dumps({'id': 2, 'method': 'Page.captureScreenshot',
                                 'params': {'format': 'jpeg', 'quality': q}}))
            n = 0
            while True:
                m = json.loads(cws.recv())
                if m.get('id') == 2 and 'result' in m:
                    d = base64.b64decode(m['result'].get('data', ''))
                    if d:
                        self.wfile.write(b'--frame\r\nContent-Type: image/jpeg\r\nContent-Length: '
                                         + str(len(d)).encode() + b'\r\n\r\n' + d + b'\r\n')
                        self.wfile.flush()
                    continue
                if m.get('method') == 'Page.screencastFrame':
                    n += 1
                    d = base64.b64decode(m['params']['data'])
                    self.wfile.write(b'--frame\r\nContent-Type: image/jpeg\r\nContent-Length: '
                                     + str(len(d)).encode() + b'\r\n\r\n' + d + b'\r\n')
                    self.wfile.flush()
                    cws.send(json.dumps({'id': 100 + n, 'method': 'Page.screencastFrameAck',
                                         'params': {'sessionId': m['params'].get('sessionId')}}))
        except Exception:
            pass
        finally:
            try:
                if cws:
                    cws.send(json.dumps({'id': 9999, 'method': 'Page.stopScreencast'}))
            except Exception:
                pass
            try:
                if cws:
                    cws.close()
            except Exception:
                pass


if __name__ == '__main__':
    print('preview v3.1 on 0.0.0.0:%d (/ws /m7a-ws /stream /health, token auth)' % LISTEN_PORT, flush=True)
    srv = ThreadingHTTPServer(('0.0.0.0', LISTEN_PORT), H)
    srv.daemon_threads = True
    srv.serve_forever()
