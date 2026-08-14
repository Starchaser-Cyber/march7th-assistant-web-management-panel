# March7th 网页管理面板 v1

现代化 UI + 图形化配置编辑 + 实时状态，用于宝塔服务器上管理 Docker 版 March7th 小助手。

## 功能
- 🔐 密码保护（首次访问设置密码，哈希存于 .panel_pass.php）
- 📊 **实时状态**：容器运行状态徽章 + 自动刷新（10秒）
- 🚀 **执行任务**：全量运行 / 日常任务 / 清体力 / 测试通知 / 差分宇宙 / 模拟宇宙
- 🔧 **容器操作**：重启、更新镜像（带确认弹窗）
- ⚙️ **图形化配置编辑**：70+ 常用配置项，分组表单，开关/下拉框/输入框
- 📝 **高级文本编辑**：完整 YAML 直接编辑（改队伍配置等复杂结构）
- 💾 **安全保存**：保存前自动备份 config.yaml.bak.时间戳，保留注释
- 📝 **实时日志**：自动刷新（3/5/10/30秒可选），可开关
- 🌙 **深色模式**：一键切换，记住选择
- 💗 **星铁粉紫皮肤**：三月七主题第二皮肤，粉紫渐变 + 玻璃拟态，亮色/深色/粉紫三态循环切换
- 🛡️ **CSRF 防护**：防跨站请求伪造
- 🔄 **自动更新检查**（v1.0+）：打开面板自动检查 Gitea/GitHub 新版本，有更新弹条提醒，一键更新（自动备份旧文件）
- 🔗 **更新源测试**：概览页一键测试更新源 API / 文件下载连通性，快速定位发版或网络问题

## 部署步骤

### 1. 宝塔一键建站
- 宝塔面板 → 网站 → 添加站点 → 域名填 `IP:端口`（如 192.168.1.42:9999）
- **PHP 版本：8.x**（不要选"纯静态"）
- 防火墙放行对应端口

### 2. 上传文件
把 `index.php` 上传到该站点根目录（如 `/www/wwwroot/192.168.1.42_9999/`）。

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

### 6. config.yaml 写权限（编辑配置时需要）
```bash
chmod 666 /home/march7thassistant/config.yaml
```

### 7. 访问
浏览器打开 `http://IP:端口/`，首次访问设置密码即可使用。

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

### 发版流程（维护者）
1. 修改 `index.php` 顶部 `PANEL_VERSION`（如 `1.0` → `1.1`）
2. `git add . && git commit -m "v1.1 更新说明" && git push`
3. 在 Gitea/GitHub 仓库页面 → Releases → **Create a new release**：
   - Tag 填 `v1.1`（与 PANEL_VERSION 对应）
   - 标题写版本号，正文写更新日志
4. 用户打开面板即可收到更新提醒

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
