# March7th 小助手网页管理面板 v1.1.1（单文件版）

> 浏览器里管三月七，告别 SSH 命令行。

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4) ![单文件](https://img.shields.io/badge/单文件-仅一个PHP文件-ff69b4) ![License](https://img.shields.io/badge/License-GPLv3-blue) ![更新](https://img.shields.io/badge/更新-自动检查-4BC0C0)

## 项目介绍

> ⚠️ **本面板为第三方开发的配套管理工具，非官方出品，与米哈游（HoYoverse）无关。**

本面板是 [三月七小助手（March7thAssistant）](https://github.com/moesnow/March7thAssistant) 的配套网页管理工具。原项目是跑在电脑上的 GUI / 命令行程序，人不在电脑前就没法操作；本面板把它部署到服务器后，你用手机或电脑浏览器就能随时远程管理——启动日常任务、清体力、改配置、看日志，全程不需要命令行。

- ✅ 适配已部署 Docker 版小助手的玩家；还没部署的，先按下方 [完整部署教程](#完整部署教程从零开始) 从零搞定（约 20 分钟）
- ✅ 压缩包发布：下载 `m7a_panel_v1.1.1.zip` 解压后上传到网站目录即可使用，无数据库、无框架依赖（核心仅一个 `index.php`）
- ✅ 手机、电脑浏览器均可使用

## 更新记录

### v1.1.1（2026-08-14）
- 🔗 **多镜像下载**：官方源下载失败时，自动依次尝试 ghfast.top / gh-proxy.com / ghproxy.net / ghps.cc 四个国内加速镜像，无需任何额外配置
- ⚠️ **代理提示**：官方源与所有镜像均下载失败时，明确提示检查服务器网络或在服务器配置代理后重试
- 🔧 **更新源测试增强**：一键测试可同时检测官方源与全部加速镜像连通性，并显示可用镜像数量

### v1.1（2026-08-14）
- 💽 **配置备份/恢复**：一键下载 `config.yaml` 到本地；上传备份文件即可恢复（恢复前自动备份当前配置）
- 🔄 **自动更新体验优化**

### v1.0（2026-08-14）
- 🎉 首个正式版本，March7th 小助手 Docker 部署配套管理面板
- 📊 **实时状态**：容器运行状态自动刷新（10 秒）
- 🚀 **任务执行**：全量运行 / 日常任务 / 清体力 / 测试通知 / 差分宇宙 / 模拟宇宙
- 🔧 **容器操作**：重启、更新镜像（带确认弹窗）
- ⚙️ **图形化配置编辑**：70+ 常用配置项分组表单 + 高级 YAML 文本编辑，保存前自动备份
- 🔄 **自动更新 + 更新源测试**：支持 GitHub / Gitea 双更新源
- 🌙 **界面体验**：深色模式、星铁粉紫皮肤三态切换、实时日志
- 🔐 **安全**：密码保护（哈希存储）、CSRF 防护

## 功能

### 🚀 核心管理
- 📊 **实时状态**：容器运行状态徽章 + 自动刷新（10秒）
- 🚀 **执行任务**：全量运行 / 日常任务 / 清体力 / 测试通知 / 差分宇宙 / 模拟宇宙
- 🔧 **容器操作**：重启、更新镜像（带确认弹窗）
- 🔄 **自动更新**（v1.0+）：打开面板自动检查 GitHub/Gitea 新版本，有更新弹条提醒，一键更新（自动备份旧文件）
- 🔗 **多镜像下载**（v1.1.1+）：官方源下载失败时自动依次尝试多个国内加速镜像，无需任何额外配置
- 🔗 **更新源测试**：一键测试更新源 API / 文件下载 / 加速镜像连通性，快速定位发版或网络问题

### ⚙️ 配置编辑
- ⚙️ **图形化配置编辑**：覆盖日常、体力、模拟宇宙、通知推送等 70+ 常用配置项，分组表单，开关/下拉框/输入框
- 📝 **高级文本编辑**：完整 YAML 直接编辑（改队伍配置等复杂结构）
- 💾 **安全保存**：保存前自动备份 config.yaml.bak.时间戳，保留注释
- 💽 **配置备份/恢复**（v1.1+）：一键下载 config.yaml 到本地，或上传备份文件恢复（恢复前自动备份当前配置）

### 🌙 界面体验
- 🌙 **深色模式**：一键切换，记住选择
- 💗 **星铁粉紫皮肤**：三月七主题粉紫渐变 + 玻璃拟态，亮色/深色/粉紫三态循环切换
- 📜 **实时日志**：自动刷新（3/5/10/30秒可选），可开关

### 🛡️ 安全
- 🔐 **密码保护**：首次访问设置密码，哈希存于 .panel_pass.php
- 🛡️ **CSRF 防护**：防跨站请求伪造

## 完整部署教程（从零开始）

> 还没部署三月七小助手的玩家，从这一节开始；已经部署过的，直接跳到 [部署本面板](#部署本面板)。
>
> 小助手 Docker 部署方式源自[原项目官方 Docker 教程](https://github.com/moesnow/March7thAssistant/blob/main/assets/docs/Docker.md)，本教程针对宝塔面板环境做了步骤化整理。

### 前置要求
- 一台 Linux 服务器：**2核 CPU / 4GB 内存**以上（小助手容器运行约需 1GB+ 内存）
- 系统：Ubuntu 20.04+ / Debian 11+ / CentOS 7.9+ 均可
- 已安装宝塔面板（[bt.cn](https://www.bt.cn) 官网一键安装）

### 第一步：安装 Docker

**方式 A：宝塔图形化安装（推荐新手）**
1. 宝塔面板 → 软件商店 → 搜索「Docker」→ 安装「Docker管理器」插件
2. 安装完成后 Docker 自动启动

**方式 B：命令行安装（更通用）**
```bash
curl -fsSL https://get.docker.com | bash
systemctl enable --now docker
```

**配置国内镜像加速（可选，推荐）**
```bash
mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'EOF'
{
  "registry-mirrors": [
    "https://docker.mirrors.aliyun.com",
    "https://mirror.baidubce.com"
  ]
}
EOF
systemctl daemon-reload && systemctl restart docker
```

验证：`docker -v` 能看到版本号即可。

### 第二步：部署三月七小助手（Docker）

> 小助手 Docker 模式仅支持**云·星穹铁道**（云游戏），首次运行需用米游社 APP 扫码登录。

**1. 创建项目目录**
```bash
mkdir -p /home/march7thassistant && cd /home/march7thassistant
```

**2. 下载配置文件**
```bash
curl -o config.yaml https://m7a.top/assets/config/config.example.yaml
curl -o docker-compose.yml https://m7a.top/docker-compose.yml
```

**3. 修改 docker-compose.yml**
宝塔文件管理器打开 `/home/march7thassistant/docker-compose.yml`：
- 将 `build: .` 这行**注释掉**（行首加 `#`）
- 取消 `image:` 行的注释
- 中国大陆用户建议改用南京大学镜像源：
  ```yaml
  image: ghcr.nju.edu.cn/moesnow/march7thassistant:latest
  ```
- 确认存在 `shm_size: 1g`（没有就加上，避免浏览器崩溃）

**4. 启动容器**
```bash
cd /home/march7thassistant && docker compose up -d
```
首次启动会拉取镜像（约 1-2GB），耐心等待。

**5. 扫码登录（关键）**
首次运行自动进入二维码登录模式：
- 二维码图片保存在 `/home/march7thassistant/logs/qrcode_login.png`
- 宝塔文件管理器双击打开该图片，用手机**米游社 APP** 扫码登录
- 或用 `docker compose logs -f` 查看日志，日志里有二维码网址，可用在线工具（如[草料二维码](https://cli.im/)）生成二维码后扫码

**6. 验证**
```bash
docker compose ps          # 容器状态应为 Up
docker compose logs -f     # 看到"开始运行"相关日志即正常
```

部署完成后：
- 小助手默认**每天凌晨 4:00 自动执行完整任务**，任务完成后自动循环等待
- 手动执行任务（面板「执行任务」按钮调用的就是这些命令）：

| 任务 | 命令 |
|------|------|
| 全量运行 | `docker exec m7a python main.py main` |
| 仅日常任务 | `docker exec m7a python main.py daily` |
| 清体力 | `docker exec m7a python main.py power` |
| 差分宇宙 | `docker exec m7a python main.py divergent` |
| 测试通知推送 | `docker exec m7a python main.py notify` |

### 第三步：部署本面板

小助手跑起来后，继续按下方 [部署本面板](#部署本面板) 的步骤安装网页管理面板（约 10 分钟）。

### 小助手常见问题

| 现象 | 原因 | 解决 |
|------|------|------|
| 日志截断 / 容器被杀 | 内存不足（OOM） | `docker inspect m7a \| grep -i oom` 确认；增大内存或 swap |
| 报 tab crashed / shm 错误 | 共享内存不足 | docker-compose.yml 确保 `shm_size: 1g`，必要时 `2g` 后 `docker compose up -d` |
| 登录状态丢失 | 浏览器数据丢失 | 重启容器后重新扫码：`docker compose restart` 然后 `docker compose logs -f` |
| 卡在"启动浏览器中..." | 浏览器用户目录损坏 | `rm -rf /home/march7thassistant/3rdparty/WebBrowser/UserProfile` 后重启容器 |
| 如何升级小助手 | 镜像更新 | `docker compose pull && docker compose up -d` |

## 部署本面板

> 预计 10 分钟完成。开始前请确认：
> - 宝塔面板已安装，PHP 版本为 8.x
> - Docker 版三月七小助手已部署（默认路径 `/home/march7thassistant`；若小助手装在别的目录，需同步修改 `index.php` 顶部的 `PROJECT_DIR`）
> - 服务器防火墙已放行要使用的端口

### 1. 宝塔一键建站
- 宝塔面板 → 网站 → 添加站点 → 域名填 `你的服务器IP:端口`
- **PHP 版本：8.x**（不要选"纯静态"）
- 防火墙放行对应端口

### 2. 上传并解压文件
把下载的 `m7a_panel_v1.1.1.zip` 压缩包上传到服务器并解压，将解压出来的**所有文件**（`index.php`、`README.md`、`LICENSE`、`.gitignore`）放到该站点根目录（如 `/www/wwwroot/你的服务器IP_端口/`）。

**方法一：宝塔文件管理器（推荐）**
1. 宝塔面板 → 文件 → 进入 `/www/wwwroot/你的服务器IP_端口/`
2. 点击「上传」→ 选择本地的 `m7a_panel_v1.1.1.zip`（支持拖拽上传）
3. 上传完成后，右键该压缩包 → 点击「解压」
4. 解压后确认根目录下有 `index.php` 即为成功（`README.md` 是使用说明、`LICENSE` 是开源许可、`.gitignore` 是 Git 忽略规则，保留即可，不要删除）

**方法二：命令行解压**
```bash
cd /www/wwwroot/你的服务器IP_端口/
unzip m7a_panel_v1.1.1.zip
ls -l    # 确认 index.php 已在目录中
```

### 3. 解禁 PHP 函数
宝塔 → 软件商店 → PHP-8.x → 设置 → 禁用函数 → 移除 `exec`、`shell_exec`、`passthru`

### 4. 给 PHP 用户 docker 权限
```bash
usermod -aG docker www
/etc/init.d/php-fpm-82 restart
```

### 5. 放开 open_basedir
编辑 `/www/wwwroot/你的站点/.user.ini`，追加项目目录：
```bash
chattr -i /www/wwwroot/你的站点/.user.ini
echo 'open_basedir=/www/wwwroot/你的站点/:/tmp/:/home/march7thassistant/' > /www/wwwroot/你的站点/.user.ini
chattr +i /www/wwwroot/你的站点/.user.ini
/etc/init.d/php-fpm-82 restart
```

> ⚠️ 如果 `.user.ini` 原本已有其他配置，请保留原内容后追加本行，**不要整行覆盖**。

### 6. config.yaml 写权限（编辑配置时需要）
```bash
chmod 666 /home/march7thassistant/config.yaml
```

### 7. 访问
浏览器打开 `http://你的服务器IP:端口/`，首次访问设置密码即可使用。

## 测试更新源连接

概览页「更新源」卡片可一键测试与更新源的连通性：

- **API 连通**：调 Releases 接口，显示已发版 / 连通未发版 / HTTP 错误
- **文件下载**：调官方 raw 文件接口，确认新版 `index.php` 可正常下载
- **镜像加速**：依次测试各国内加速镜像的连通性，显示可用数量

> 刚建仓库、还没发版时测试会提示「尚未发版 / HTTP 404」，属于正常现象；完成首次发版后应全部变为 ✅。

## 自动更新（v1.0+）

面板每次打开会静默检查代码仓库的最新 Release，发现新版本时顶部显示提醒条，点击「一键更新」即可升级（更新前自动备份当前 index.php）。

### 配置更新源
编辑 `index.php` 顶部「自动更新配置」区块：

| 常量 | 说明 | 示例 |
|------|------|------|
| `PANEL_VERSION` | 当前版本号（发版时修改） | `'1.1.1'` |
| `UPDATE_ENABLED` | 是否启用自动检查 | `true` |
| `UPDATE_TYPE` | 更新源类型 | `'github'`（默认） / `'gitea'` |
| `UPDATE_HOST` | 实例地址（github 时忽略） | `'https://github.com'` |
| `UPDATE_OWNER` | 仓库所有者 | `'starchaser-cyber'` |
| `UPDATE_REPO` | 仓库名 | `'march7th-assistant-web-management-panel'` |
| `UPDATE_BRANCH` | 仓库分支 | `'main'` |

> 未配置仓库或没有 Release 时，面板静默跳过检查，不影响使用。

### 多镜像下载说明（v1.1.1+）

更新时按顺序尝试：官方源 → 加速镜像1 → 加速镜像2 → …，任一成功即完成更新，无需在镜像站注册或上传任何文件——加速镜像只是实时转发 GitHub 官方内容，你正常 push 到 GitHub 仓库即可。

镜像列表在 `index.php` 的 `update_raw_urls()` 函数中维护，可自行增删；全部失败时面板会提示网络原因并建议配置代理。

## 安全提醒
- 面板能触发游戏任务和修改配置，**务必设置强密码**
- 建议仅在可信网络访问；公网暴露时加宝塔「网站 → 访问控制 → Basic Auth」双重保护
- 配置编辑每次保存前自动备份，备份文件在项目目录下 `config.yaml.bak.YmdHis`

## 常见问题
| 现象 | 原因 | 解决 |
|------|------|------|
| 下载了 index.php 文件 | PHP 未启用 | 宝塔站点设置 → PHP版本 → 选 8.x |
| 容器状态空白 / exec 报错 | www 无 docker 权限 / exec 被禁 | 执行第3、4步 |
| 配置保存失败 | 文件无写权限 | chmod 666 config.yaml |
| config.yaml 显示未找到 | open_basedir 限制 | 执行第5步 |
| 页面功能少/没样式 | 旧版 index.php | 替换为新版 index.php |

## 免责声明

本项目仅供学习交流使用，请遵守游戏用户协议与相关法律法规，请勿将本项目用于违反游戏服务条款的用途。

- 本面板为第三方开发的配套管理工具，**非官方出品**，与米哈游（HoYoverse）及《崩坏：星穹铁道》官方无关
- 项目不包含任何游戏美术素材、立绘或官方资源
- 本项目基于 **GNU GPL v3** 许可证发布，遵循原项目 [March7thAssistant](https://github.com/moesnow/March7thAssistant) 的开源协议
