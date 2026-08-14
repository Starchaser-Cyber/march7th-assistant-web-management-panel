# March7th 小助手网页管理面板 v1.0（单文件版）

> 浏览器里管三月七，告别 SSH 命令行。

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4) ![单文件](https://img.shields.io/badge/单文件-仅一个PHP文件-ff69b4) ![License](https://img.shields.io/badge/License-MIT-green) ![更新](https://img.shields.io/badge/更新-自动检查-4BC0C0)

## 项目介绍

本面板是 [三月七小助手（March7thAssistant）](https://github.com/moesnow/March7thAssistant) 的配套网页管理工具。原项目是跑在电脑上的 GUI / 命令行程序，人不在电脑前就没法操作；本面板把它部署到服务器后，你用手机或电脑浏览器就能随时远程管理——启动日常任务、清体力、改配置、看日志，全程不需要命令行。

- ✅ 适配已部署 Docker 版小助手的玩家；还没部署的，按下方步骤 10 分钟搞定
- ✅ 单文件 `index.php`，上传即用，无数据库、无框架依赖
- ✅ 手机、电脑浏览器均可使用

## 功能

### 🚀 核心管理
- 📊 **实时状态**：容器运行状态徽章 + 自动刷新（10秒）
- 🚀 **执行任务**：全量运行 / 日常任务 / 清体力 / 测试通知 / 差分宇宙 / 模拟宇宙
- 🔧 **容器操作**：重启、更新镜像（带确认弹窗）
- 🔄 **自动更新**（v1.0+）：打开面板自动检查 GitHub/Gitea 新版本，有更新弹条提醒，一键更新（自动备份旧文件）
- 🔗 **更新源测试**：一键测试更新源 API / 文件下载连通性，快速定位发版或网络问题

### ⚙️ 配置编辑
- ⚙️ **图形化配置编辑**：覆盖日常、体力、模拟宇宙、通知推送等 70+ 常用配置项，分组表单，开关/下拉框/输入框
- 📝 **高级文本编辑**：完整 YAML 直接编辑（改队伍配置等复杂结构）
- 💾 **安全保存**：保存前自动备份 config.yaml.bak.时间戳，保留注释

### 🌙 界面体验
- 🌙 **深色模式**：一键切换，记住选择
- 💗 **星铁粉紫皮肤**：三月七主题粉紫渐变 + 玻璃拟态，亮色/深色/粉紫三态循环切换
- 📜 **实时日志**：自动刷新（3/5/10/30秒可选），可开关

### 🛡️ 安全
- 🔐 **密码保护**：首次访问设置密码，哈希存于 .panel_pass.php
- 🛡️ **CSRF 防护**：防跨站请求伪造

## 部署步骤

> 预计 10 分钟完成。开始前请确认：
> - 宝塔面板已安装，PHP 版本为 8.x
> - Docker 版三月七小助手已部署（默认路径 `/home/march7thassistant`）
> - 服务器防火墙已放行要使用的端口

### 1. 宝塔一键建站
- 宝塔面板 → 网站 → 添加站点 → 域名填 `你的服务器IP:端口`
- **PHP 版本：8.x**（不要选"纯静态"）
- 防火墙放行对应端口

### 2. 上传文件
把 `index.php` 上传到该站点根目录（如 `/www/wwwroot/你的服务器IP_端口/`）。

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
- **文件下载**：调 raw 文件接口，确认新版 `index.php` 可正常下载

> 刚建仓库、还没发版时测试会提示「尚未发版 / HTTP 404」，属于正常现象；完成首次发版后应全部变为 ✅。

## 自动更新（v1.0+）

面板每次打开会静默检查代码仓库的最新 Release，发现新版本时顶部显示提醒条，点击「一键更新」即可升级（更新前自动备份当前 index.php）。

### 配置更新源
编辑 `index.php` 顶部「自动更新配置」区块：

| 常量 | 说明 | 示例 |
|------|------|------|
| `PANEL_VERSION` | 当前版本号（发版时修改） | `'1.0'` |
| `UPDATE_ENABLED` | 是否启用自动检查 | `true` |
| `UPDATE_TYPE` | 更新源类型 | `'github'`（默认） / `'gitea'` |
| `UPDATE_HOST` | 实例地址（github 时忽略） | `'https://github.com'` |
| `UPDATE_OWNER` | 仓库所有者 | `'huangsongping183'` |
| `UPDATE_REPO` | 仓库名 | `'march7th-assistant-web-management-panel'` |
| `UPDATE_BRANCH` | 仓库分支 | `'main'` |

> 未配置仓库或没有 Release 时，面板静默跳过检查，不影响使用。

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
