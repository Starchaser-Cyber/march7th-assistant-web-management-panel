<?php
/**
 * March7th Assistant 网页管理面板 v1.5
 * 现代化 UI + 图形化配置编辑 + 实时状态 + 配置备份恢复 + 多镜像下载 + 日志高级查询 + 多实例切换
 * 纯原生 PHP 单文件 · 宝塔友好
 */
declare(strict_types=1);
session_start();

define('PASS_FILE', __DIR__ . '/.panel_pass.php');
define('INSTANCES_FILE', __DIR__ . '/.instances.php');
define('DEFAULT_DIR', '/home/march7thassistant');
define('DEFAULT_CONTAINER', 'm7a');
define('SKEY', 'm7a_panel_auth');
define('CSRF_KEY', 'm7a_panel_csrf');

/* ===== 自动更新配置 =====
 * 发版流程：改 PANEL_VERSION → git push → 在 Gitea/GitHub 打 tag（如 v1.0）并创建 Release
 * UPDATE_TYPE: gitea / github
 */
define('PANEL_VERSION', '1.5');            // 面板当前版本号（发版时手动修改）
define('UPDATE_ENABLED', true);              // 是否启用自动检查更新
define('UPDATE_TYPE', 'github');              // 更新源类型：gitea 或 github
define('UPDATE_HOST', 'https://github.com');  // Gitea 实例地址（UPDATE_TYPE=gitea 时生效）
define('UPDATE_OWNER', 'starchaser-cyber');          // 仓库所有者
define('UPDATE_REPO', 'march7th-assistant-web-management-panel');          // 仓库名
define('UPDATE_BRANCH', 'main');             // 仓库分支

/* ===== 任务白名单 ===== */
$TASKS = array(
    'main'          => array('label' => '全量运行',   'desc' => '日常+周常全部执行一遍', 'icon' => '🚀', 'long' => true),
    'daily'         => array('label' => '日常任务',   'desc' => '清体力/每日实训/领奖励', 'icon' => '📋', 'long' => true),
    'power'         => array('label' => '清体力',     'desc' => '仅清空开拓力', 'icon' => '⚡', 'long' => true),
    'notify'        => array('label' => '测试通知',   'desc' => '发送一条通知测试推送', 'icon' => '🔔', 'long' => false),
    'divergentloop' => array('label' => '差分宇宙',   'desc' => '差分宇宙周常', 'icon' => '🌌', 'long' => true),
    'universe'      => array('label' => '模拟宇宙',   'desc' => '模拟宇宙周常', 'icon' => '🔮', 'long' => true),
);
$OPS = array(
    'restart' => array('label' => '重启容器', 'desc' => 'docker compose restart', 'icon' => '🔄', 'confirm' => '确定重启容器？'),
    'update'  => array('label' => '更新镜像', 'desc' => '拉取最新镜像并重建容器', 'icon' => '⬆️', 'confirm' => '确定拉取最新镜像并重建容器？可能需要几分钟。'),
);

/* ===== 配置字段定义 ===== */
$CONFIG_GROUPS = array(
    'basic' => array('title' => '基础设置', 'icon' => '⚙️', 'fields' => array(
        'log_level'           => array('label' => '日志等级', 'type' => 'select', 'options' => array('INFO' => 'INFO（仅重要信息）', 'DEBUG' => 'DEBUG（详细调试）')),
        'log_retention_days'  => array('label' => '日志保留天数', 'type' => 'int'),
        'check_update'        => array('label' => '检查更新', 'type' => 'bool'),
        'pause_after_success' => array('label' => '成功后暂停', 'type' => 'bool'),
        'exit_after_failure'  => array('label' => '失败后退出', 'type' => 'bool'),
        'after_finish'        => array('label' => '完成后操作', 'type' => 'select', 'options' => array('None'=>'无操作','Exit'=>'退出','Loop'=>'循环','Shutdown'=>'关机','Sleep'=>'睡眠','Hibernate'=>'休眠','Restart'=>'重启','Logoff'=>'注销','TurnOffDisplay'=>'关显示器','RunScript'=>'运行脚本')),
        'play_audio'          => array('label' => '完成后播放提示音', 'type' => 'bool'),
        'debug_mode_enable'   => array('label' => '调试模式', 'type' => 'bool'),
    )),
    'instance' => array('title' => '副本与体力', 'icon' => '⚔️', 'fields' => array(
        'power_enable'        => array('label' => '清体力总开关', 'type' => 'bool'),
        'power_plan_keep'     => array('label' => '保留体力计划', 'type' => 'bool'),
        'instance_type'       => array('label' => '副本类型', 'type' => 'select', 'options' => array('拟造花萼（金）'=>'拟造花萼（金）','拟造花萼（赤）'=>'拟造花萼（赤）','凝滞虚影'=>'凝滞虚影','侵蚀隧洞'=>'侵蚀隧洞','饰品提取'=>'饰品提取')),
        'tp_before_instance'  => array('label' => '进副本前传送', 'type' => 'bool'),
        'build_target_enable' => array('label' => '培养目标', 'type' => 'bool'),
        'break_down_level_four_relicset' => array('label' => '分解四星遗器', 'type' => 'bool'),
        'use_reserved_trailblaze_power'  => array('label' => '使用后备开拓力', 'type' => 'bool'),
        'use_fuel'            => array('label' => '使用燃料', 'type' => 'bool'),
        'echo_of_war_enable'  => array('label' => '体力优先历战余响', 'type' => 'bool'),
        'merge_immersifier'   => array('label' => '优先合成沉浸器', 'type' => 'bool'),
        'borrow_enable'       => array('label' => '使用支援角色', 'type' => 'bool'),
    )),
    'daily' => array('title' => '日常与奖励', 'icon' => '🎁', 'fields' => array(
        'daily_enable'              => array('label' => '日常任务', 'type' => 'bool'),
        'daily_material_enable'     => array('label' => '合成材料完成日常', 'type' => 'bool'),
        'daily_himeko_try_enable'   => array('label' => '姬子试用完成日常', 'type' => 'bool'),
        'reward_enable'             => array('label' => '领取奖励总开关', 'type' => 'bool'),
        'reward_dispatch_enable'    => array('label' => '领取委托奖励', 'type' => 'bool'),
        'reward_mail_enable'        => array('label' => '领取邮件奖励', 'type' => 'bool'),
        'reward_assist_enable'      => array('label' => '领取支援奖励', 'type' => 'bool'),
        'reward_quest_enable'       => array('label' => '领取每日实训', 'type' => 'bool'),
        'reward_srpass_enable'      => array('label' => '领取无名勋礼', 'type' => 'bool'),
        'reward_redemption_code_enable' => array('label' => '领取兑换码', 'type' => 'bool'),
        'reward_achievement_enable' => array('label' => '领取成就奖励', 'type' => 'bool'),
    )),
    'activity' => array('title' => '活动设置', 'icon' => '🎪', 'fields' => array(
        'activity_enable'                     => array('label' => '活动功能', 'type' => 'bool'),
        'activity_dailycheckin_enable'        => array('label' => '每日签到', 'type' => 'bool'),
        'activity_gardenofplenty_enable'      => array('label' => '花藏繁生', 'type' => 'bool'),
        'activity_realmofthestrange_enable'   => array('label' => '异器盈界', 'type' => 'bool'),
        'activity_planarfissure_enable'       => array('label' => '位面分裂', 'type' => 'bool'),
        'activity_journey_highlights_notification_enable' => array('label' => '活动热点通知', 'type' => 'bool'),
    )),
    'schedule' => array('title' => '定时与循环', 'icon' => '⏰', 'fields' => array(
        'loop_mode'           => array('label' => '循环模式', 'type' => 'select', 'options' => array('scheduled'=>'定时任务','power'=>'根据开拓力')),
        'scheduled_time'      => array('label' => '运行时间', 'type' => 'str', 'placeholder' => '如 8:00 或 04:00'),
        'power_limit'         => array('label' => '开拓力下限', 'type' => 'int'),
        'refresh_hour'        => array('label' => '游戏刷新时间（时）', 'type' => 'int'),
        'scheduled_on_conflict' => array('label' => '任务冲突处理', 'type' => 'select', 'options' => array('skip'=>'跳过','stop'=>'停止当前再启动')),
        'scheduled_chain_continue_on_failure' => array('label' => '链式任务失败后继续', 'type' => 'bool'),
    )),
    'notify' => array('title' => '通知通用', 'icon' => '📣', 'fields' => array(
        'notification_enable' => array('label' => '通知总开关', 'type' => 'bool', 'tip' => '关闭后所有渠道推送均失效'),
        'notify_level'        => array('label' => '通知级别', 'type' => 'select', 'options' => array('all'=>'全部通知','error'=>'仅错误')),
        'notify_merge'        => array('label' => '合并通知', 'type' => 'bool', 'tip' => '开启后完整运行结束只发一条汇总'),
        'notify_send_images'  => array('label' => '推送图片', 'type' => 'bool', 'tip' => '推送消息时附带截图'),
        'notify_winotify_enable' => array('label' => 'Windows 原生通知', 'type' => 'bool', 'tip' => '仅 Windows 本机运行有效'),
    )),
    'notify_telegram' => array('title' => 'Telegram', 'icon' => '✈️', 'fields' => array(
        'notify_telegram_enable' => array('label' => '启用 Telegram', 'type' => 'bool', 'tip' => '依赖科学上网环境'),
        'notify_telegram_token'  => array('label' => 'Bot Token', 'type' => 'password', 'placeholder' => 'BotFather 获取'),
        'notify_telegram_userid' => array('label' => '接收用户/群组 ID', 'type' => 'str', 'placeholder' => '如 123456789'),
        'notify_telegram_api_url'=> array('label' => '自定义 API URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_telegram_proxies'=> array('label' => '代理地址', 'type' => 'str', 'placeholder' => '如 127.0.0.1:10808'),
        'notify_telegram_thread_id' => array('label' => 'Topics 线程 ID', 'type' => 'str', 'placeholder' => '可选'),
    )),
    'notify_matrix' => array('title' => 'Matrix', 'icon' => '🔗', 'fields' => array(
        'notify_matrix_enable' => array('label' => '启用 Matrix', 'type' => 'bool'),
        'notify_matrix_homeserver' => array('label' => '服务器地址', 'type' => 'str', 'placeholder' => '如 https://matrix.org'),
        'notify_matrix_device_id' => array('label' => '设备 ID', 'type' => 'str', 'placeholder' => '10位大写字母/数字'),
        'notify_matrix_user_id' => array('label' => '用户 ID', 'type' => 'str', 'placeholder' => '如 @user:matrix.org'),
        'notify_matrix_access_token' => array('label' => 'Access Token', 'type' => 'password', 'placeholder' => '登录后由服务器分发'),
        'notify_matrix_room_id' => array('label' => '房间 ID', 'type' => 'str', 'placeholder' => '如 !abc:matrix.org'),
        'notify_matrix_proxy' => array('label' => '代理', 'type' => 'str', 'placeholder' => '可选'),
        'notify_matrix_separately_text_media' => array('label' => '文字与图片分开发送', 'type' => 'bool'),
    )),
    'notify_serverchan' => array('title' => 'Server酱', 'icon' => '🛎️', 'fields' => array(
        'notify_serverchanturbo_enable' => array('label' => '启用 Server酱·Turbo', 'type' => 'bool', 'tip' => '微信推送，免费版每天5条 sct.ftqq.com'),
        'notify_serverchanturbo_sctkey' => array('label' => 'SendKey', 'type' => 'password', 'placeholder' => 'sct 开头的密钥'),
        'notify_serverchanturbo_channel' => array('label' => '推送渠道', 'type' => 'str', 'placeholder' => '可选'),
        'notify_serverchanturbo_openid' => array('label' => 'OpenID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_serverchan3_enable' => array('label' => '启用 Server酱·3', 'type' => 'bool', 'tip' => 'APP 推送 sc3.ft07.com'),
        'notify_serverchan3_sendkey' => array('label' => 'SendKey', 'type' => 'password', 'placeholder' => 'sct 开头的密钥'),
    )),
    'notify_bark' => array('title' => 'Bark (iOS)', 'icon' => '🐶', 'fields' => array(
        'notify_bark_enable' => array('label' => '启用 Bark', 'type' => 'bool', 'tip' => 'iOS 用户，App Store 安装'),
        'notify_bark_key' => array('label' => '推送 Key', 'type' => 'str', 'placeholder' => 'Bark 设备 key'),
        'notify_bark_group' => array('label' => '分组名', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_icon' => array('label' => '图标 URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_isarchive' => array('label' => '是否归档', 'type' => 'str', 'placeholder' => '可选，1 或 0'),
        'notify_bark_sound' => array('label' => '提示音', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_url' => array('label' => '点击跳转 URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_base_url' => array('label' => '自定义服务 URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_copy' => array('label' => '复制文本', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_autocopy' => array('label' => '自动复制', 'type' => 'str', 'placeholder' => '可选'),
        'notify_bark_cipherkey' => array('label' => '加密密钥', 'type' => 'password', 'placeholder' => '需与 APP 一致'),
        'notify_bark_ciphermethod' => array('label' => '加密算法', 'type' => 'str', 'placeholder' => 'cbc 或 ecb'),
    )),
    'notify_smtp' => array('title' => 'SMTP 邮箱', 'icon' => '📧', 'fields' => array(
        'notify_smtp_enable' => array('label' => '启用 SMTP', 'type' => 'bool', 'tip' => '支持发送截图，QQ 邮箱填授权码'),
        'notify_smtp_host' => array('label' => 'SMTP 服务器', 'type' => 'str', 'placeholder' => '如 smtp.qq.com'),
        'notify_smtp_user' => array('label' => '用户名/邮箱', 'type' => 'str'),
        'notify_smtp_password' => array('label' => '密码/授权码', 'type' => 'password', 'placeholder' => '留空则不修改'),
        'notify_smtp_From' => array('label' => '发件人', 'type' => 'str'),
        'notify_smtp_To' => array('label' => '收件人', 'type' => 'str'),
        'notify_smtp_port' => array('label' => '端口', 'type' => 'str', 'placeholder' => '默认 465'),
        'notify_smtp_ssl' => array('label' => 'SSL 连接', 'type' => 'bool'),
        'notify_smtp_starttls' => array('label' => 'STARTTLS', 'type' => 'bool'),
        'notify_smtp_ssl_unverified' => array('label' => '不验证 SSL 证书', 'type' => 'bool', 'tip' => '自签名邮箱才推荐开启'),
    )),
    'notify_qqbot' => array('title' => 'QQ 机器人', 'icon' => '💬', 'fields' => array(
        'notify_onebot_enable' => array('label' => '启用 OneBot', 'type' => 'bool', 'tip' => '支持 NapCatQQ / OpenShamrock'),
        'notify_onebot_endpoint' => array('label' => '服务端点 URL', 'type' => 'str', 'placeholder' => '如 http://127.0.0.1:3000'),
        'notify_onebot_token' => array('label' => 'Access Token', 'type' => 'password', 'placeholder' => '可选'),
        'notify_onebot_user_id' => array('label' => '接收用户 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_onebot_group_id' => array('label' => '接收群组 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_gocqhttp_enable' => array('label' => '启用 Go-cqhttp', 'type' => 'bool', 'tip' => '已停止维护，旧用户可用'),
        'notify_gocqhttp_endpoint' => array('label' => '服务端点 URL', 'type' => 'str'),
        'notify_gocqhttp_message_type' => array('label' => '消息类型', 'type' => 'select', 'options' => array(''=>'默认','private'=>'私聊','group'=>'群消息')),
        'notify_gocqhttp_token' => array('label' => 'Access Token', 'type' => 'password', 'placeholder' => '可选'),
        'notify_gocqhttp_user_id' => array('label' => '接收用户 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_gocqhttp_group_id' => array('label' => '接收群组 ID', 'type' => 'str', 'placeholder' => '可选'),
    )),
    'notify_dingtalk' => array('title' => '钉钉 / PushPlus', 'icon' => '📌', 'fields' => array(
        'notify_dingtalk_enable' => array('label' => '启用钉钉', 'type' => 'bool'),
        'notify_dingtalk_token' => array('label' => '机器人 Access Token', 'type' => 'password'),
        'notify_dingtalk_secret' => array('label' => '加签密钥', 'type' => 'password', 'placeholder' => '可选'),
        'notify_pushplus_enable' => array('label' => '启用 PushPlus', 'type' => 'bool'),
        'notify_pushplus_token' => array('label' => 'Token', 'type' => 'password'),
        'notify_pushplus_channel' => array('label' => '通知渠道', 'type' => 'str', 'placeholder' => '可选'),
        'notify_pushplus_webhook' => array('label' => 'Webhook URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_pushplus_callbackUrl' => array('label' => '回调 URL', 'type' => 'str', 'placeholder' => '可选'),
    )),
    'notify_wechat' => array('title' => '企业微信', 'icon' => '💼', 'fields' => array(
        'notify_wechatworkapp_enable' => array('label' => '启用应用通知', 'type' => 'bool', 'tip' => '支持发送截图'),
        'notify_wechatworkapp_corpid' => array('label' => '企业 ID', 'type' => 'str'),
        'notify_wechatworkapp_corpsecret' => array('label' => '应用密钥', 'type' => 'password'),
        'notify_wechatworkapp_agentid' => array('label' => 'AgentId', 'type' => 'str'),
        'notify_wechatworkapp_touser' => array('label' => '目标用户', 'type' => 'str', 'placeholder' => '@all 或 用户ID'),
        'notify_wechatworkapp_base_url' => array('label' => '自定义 API 地址', 'type' => 'str', 'placeholder' => '可选'),
        'notify_wechatworkbot_enable' => array('label' => '启用机器人通知', 'type' => 'bool'),
        'notify_wechatworkbot_key' => array('label' => '机器人 Key', 'type' => 'password'),
        'notify_wechatworkbot_webhook_url' => array('label' => 'Webhook URL', 'type' => 'str', 'placeholder' => '可选'),
    )),
    'notify_other' => array('title' => 'Gotify / Discord / PushDeer', 'icon' => '🔔', 'fields' => array(
        'notify_gotify_enable' => array('label' => '启用 Gotify', 'type' => 'bool'),
        'notify_gotify_url' => array('label' => '服务器 URL', 'type' => 'str'),
        'notify_gotify_token' => array('label' => 'Access Token', 'type' => 'password'),
        'notify_gotify_priority' => array('label' => '优先级(1-10)', 'type' => 'int'),
        'notify_discord_enable' => array('label' => '启用 Discord', 'type' => 'bool'),
        'notify_discord_webhook' => array('label' => 'Webhook URL', 'type' => 'str'),
        'notify_discord_username' => array('label' => '自定义用户名', 'type' => 'str', 'placeholder' => '可选'),
        'notify_discord_avatar_url' => array('label' => '自定义头像 URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_discord_color' => array('label' => '嵌入消息颜色', 'type' => 'str', 'placeholder' => '可选'),
        'notify_pushdeer_enable' => array('label' => '启用 PushDeer', 'type' => 'bool'),
        'notify_pushdeer_token' => array('label' => 'Token', 'type' => 'password'),
        'notify_pushdeer_url' => array('label' => '自定义服务 URL', 'type' => 'str', 'placeholder' => '可选'),
    )),
    'notify_lark' => array('title' => '飞书', 'icon' => '🪶', 'fields' => array(
        'notify_lark_enable' => array('label' => '启用飞书', 'type' => 'bool'),
        'notify_lark_webhook' => array('label' => 'Webhook URL', 'type' => 'str'),
        'notify_lark_content' => array('label' => '消息内容', 'type' => 'str', 'placeholder' => '可选'),
        'notify_lark_keyword' => array('label' => '安全关键词', 'type' => 'str', 'placeholder' => '无则留空'),
        'notify_lark_sign' => array('label' => '签名密钥', 'type' => 'password', 'placeholder' => '可选'),
        'notify_lark_imageenable' => array('label' => '图片消息', 'type' => 'bool', 'tip' => '开启需自建飞书应用'),
        'notify_lark_appid' => array('label' => '应用 AppID', 'type' => 'str', 'placeholder' => '图片消息必填'),
        'notify_lark_secret' => array('label' => '应用 Secret', 'type' => 'password', 'placeholder' => '图片消息必填'),
    )),
    'notify_kook' => array('title' => 'KOOK / MeoW', 'icon' => '🎧', 'fields' => array(
        'notify_kook_enable' => array('label' => '启用 KOOK', 'type' => 'bool', 'tip' => '支持发送截图'),
        'notify_kook_token' => array('label' => '机器人 Token', 'type' => 'password'),
        'notify_kook_target_id' => array('label' => '目标 ID', 'type' => 'str', 'placeholder' => '用户ID或频道ID'),
        'notify_kook_chat_type' => array('label' => '消息类型', 'type' => 'str', 'placeholder' => '1 私聊 / 9 频道'),
        'notify_meow_enable' => array('label' => '启用 MeoW', 'type' => 'bool'),
        'notify_meow_nickname' => array('label' => '昵称', 'type' => 'str'),
    )),
    'notify_webhook' => array('title' => 'Webhook / 自定义', 'icon' => '🕸️', 'fields' => array(
        'notify_webhook_enable' => array('label' => '启用 Webhook', 'type' => 'bool', 'tip' => '支持自定义请求方法/Headers/Body'),
        'notify_webhook_url' => array('label' => '接收地址', 'type' => 'str', 'placeholder' => '如 http://localhost:8080/notify'),
        'notify_webhook_method' => array('label' => '请求方法', 'type' => 'select', 'options' => array(''=>'默认 POST','GET'=>'GET','POST'=>'POST','PUT'=>'PUT','DELETE'=>'DELETE')),
        'notify_webhook_headers' => array('label' => '自定义 Headers', 'type' => 'textarea', 'placeholder' => 'JSON 格式，如 {"Authorization": "Bearer token"}'),
        'notify_webhook_body' => array('label' => '请求体模板', 'type' => 'textarea', 'placeholder' => 'JSON 或字符串，支持 {title} {content} {image}'),
        'notify_custom_enable' => array('label' => '启用自定义通知', 'type' => 'bool', 'tip' => '支持发送截图'),
        'notify_custom_url' => array('label' => '请求 URL', 'type' => 'str', 'placeholder' => '如 http://localhost:3000/send_msg'),
        'notify_custom_method' => array('label' => '请求类型', 'type' => 'str', 'placeholder' => 'get / post'),
        'notify_custom_datatype' => array('label' => '数据类型', 'type' => 'str', 'placeholder' => 'data / json'),
        'notify_custom_image' => array('label' => '图片模板', 'type' => 'str', 'placeholder' => '可选，onebot 参考格式'),
        'notify_custom_data' => array('label' => '请求体', 'type' => 'textarea', 'placeholder' => 'onebot 参考 {user_id: 114514, message: [...]}'),
    )),

    'other' => array('title' => '其他设置', 'icon' => '🔧', 'fields' => array(
        'telemetry_enable'          => array('label' => '匿名遥测', 'type' => 'bool'),
        'auto_update'               => array('label' => '自动更新', 'type' => 'bool'),
        'update_source'             => array('label' => '更新源', 'type' => 'select', 'options' => array('GitHub'=>'GitHub','MirrorChyan'=>'Mirror酱')),
        'auto_set_resolution_enable'=> array('label' => '自动修改分辨率', 'type' => 'bool'),
        'auto_battle_detect_enable' => array('label' => '自动战斗检测', 'type' => 'bool'),
        'ocr_gpu_acceleration'      => array('label' => 'OCR 加速', 'type' => 'select', 'options' => array('auto'=>'自动','gpu'=>'GPU','onnx_dml'=>'ONNX DML','cpu'=>'CPU','openvino_cpu'=>'OpenVINO CPU','onnx_cpu'=>'ONNX CPU')),
        'use_background_screenshot' => array('label' => '后台截图', 'type' => 'bool'),
        'cloud_game_enable'         => array('label' => '云游戏', 'type' => 'bool'),
        'browser_headless_enable'   => array('label' => '浏览器无窗口模式', 'type' => 'bool'),
        'autoplot_skip_enable'      => array('label' => '自动跳过对话', 'type' => 'bool'),
        'autoplot_click_enable'     => array('label' => '自动选择对话选项', 'type' => 'bool'),
    )),
);

/* ===== 工具函数 ===== */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function instances_load() {
    static $list = null;
    if ($list !== null) return $list;
    $fallback = array(array('id' => DEFAULT_CONTAINER, 'name' => '主号', 'container' => DEFAULT_CONTAINER, 'dir' => DEFAULT_DIR, 'default' => true));
    if (!is_file(INSTANCES_FILE)) return $list = $fallback;
    $loaded = @include INSTANCES_FILE;
    if (!is_array($loaded) || !$loaded) return $list = $fallback;
    $valid = array();
    foreach ($loaded as $i => $item) {
        if (!is_array($item)) continue;
        $container = trim((string)($item['container'] ?? ''));
        $dir = rtrim(trim((string)($item['dir'] ?? '')), '/');
        if ($container === '' || $dir === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $container)) continue;
        $valid[] = array(
            'id' => (string)($item['id'] ?? $container),
            'name' => trim((string)($item['name'] ?? $container)) ?: $container,
            'container' => $container,
            'dir' => $dir,
            'default' => !empty($item['default']),
        );
    }
    return $list = $valid ?: $fallback;
}
function instances_write($list) {
    $out = array("<?php", "// March7th 管理面板多实例配置，由面板维护。", "return array(");
    foreach ($list as $item) {
        $out[] = '    array(' .
            "'id' => " . var_export((string)$item['id'], true) . ', ' .
            "'name' => " . var_export((string)$item['name'], true) . ', ' .
            "'container' => " . var_export((string)$item['container'], true) . ', ' .
            "'dir' => " . var_export((string)$item['dir'], true) . ', ' .
            "'default' => " . (!empty($item['default']) ? 'true' : 'false') . '),';
    }
    $out[] = ');';
    $ok = @file_put_contents(INSTANCES_FILE, implode("\n", $out) . "\n", LOCK_EX) !== false;
    if ($ok) @chmod(INSTANCES_FILE, 0600);
    return $ok;
}
function instance_current() {
    $wanted = (string)($_SESSION['m7a_instance'] ?? '');
    $list = instances_load();
    foreach ($list as $item) {
        if ($wanted !== '' && ($item['id'] === $wanted || $item['container'] === $wanted)) return $item;
    }
    foreach ($list as $item) if (!empty($item['default'])) return $item;
    return $list[0];
}
function instance_dir() { return instance_current()['dir']; }
function instance_container() { return instance_current()['container']; }
function instance_config() { return instance_dir() . '/config.yaml'; }
function instance_name() { return instance_current()['name']; }
function instance_switch_to($id) {
    foreach (instances_load() as $item) {
        if ($item['id'] === $id || $item['container'] === $id) {
            $_SESSION['m7a_instance'] = $item['id'];
            return true;
        }
    }
    return false;
}

function run_cmd($cmd) {
    $out = array(); $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return array('code' => $code, 'out' => implode("\n", $out));
}
function compose($args) {
    return run_cmd('cd ' . escapeshellarg(instance_dir()) . ' && docker compose ' . $args);
}
function task_start($sub) {
    return run_cmd('docker exec -d ' . escapeshellarg(instance_container()) . ' python main.py ' . escapeshellarg($sub));
}
function is_auth() { return !empty($_SESSION[SKEY]); }

function csrf_token() {
    if (empty($_SESSION[CSRF_KEY])) $_SESSION[CSRF_KEY] = bin2hex(random_bytes(16));
    return $_SESSION[CSRF_KEY];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check() {
    return isset($_POST['csrf']) && hash_equals($_SESSION[CSRF_KEY] ?? '', $_POST['csrf']);
}

function check_pass($input) {
    if (!is_file(PASS_FILE)) return false;
    $hash = include PASS_FILE;
    return is_string($hash) && password_verify($input, $hash);
}
function set_pass($input) {
    $content = "<?php return '" . password_hash($input, PASSWORD_DEFAULT) . "';";
    if (@file_put_contents(PASS_FILE, $content) === false) return false;
    @chmod(PASS_FILE, 0600);
    return true;
}
function latest_log_path() {
    $files = glob(instance_dir() . '/logs/*.log');
    if (!$files) return null;
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    return $files[0];
}
function tail_file($path, $lines = 200) {
    $out = array(); $code = 0;
    exec('tail -n ' . (int)$lines . ' ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    return implode("\n", $out);
}
function log_files() {
    $files = glob(instance_dir() . '/logs/*.log');
    if (!$files) return array();
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    return array_values($files);
}
function filter_log($path, $opts = array()) {
    $lines = array(); $code = 0;
    exec('tail -n ' . (int)($opts['lines'] ?? 2000) . ' ' . escapeshellarg($path) . ' 2>&1', $lines, $code);
    $kw    = isset($opts['keyword']) ? trim((string)$opts['keyword']) : '';
    $level = isset($opts['level']) ? strtoupper(trim((string)$opts['level'])) : '';
    $hours = isset($opts['hours']) ? (int)$opts['hours'] : 0;
    $out = array();
    foreach ($lines as $line) {
        if ($kw !== '' && stripos($line, $kw) === false) continue;
        if ($level !== '' && $level !== 'ALL') {
            if (!preg_match('/\|\s*' . preg_quote($level, '/') . '\s*\|/i', $line)) continue;
        }
        if ($hours > 0) {
            $ts = strtotime(substr($line, 0, 19));
            if ($ts !== false && $ts < time() - $hours * 3600) continue;
        }
        $out[] = $line;
    }
    return array('lines' => $out, 'total' => count($out));
}
function container_status() {
    $r = compose('ps');
    return $r['out'];
}
function container_is_running() {
    $r = run_cmd('docker inspect -f "{{.State.Running}}" ' . escapeshellarg(instance_container()) . ' 2>&1');
    return trim($r['out']) === 'true';
}
function config_read_raw($maxBytes = 65536) {
    if (!is_file(instance_config())) return null;
    $fp = fopen(instance_config(), 'rb');
    if ($fp === false) return null;
    $data = fread($fp, $maxBytes);
    fclose($fp);
    return $data;
}
function yaml_read_simple() {
    $vals = array();
    if (!is_file(instance_config())) return $vals;
    $lines = file(instance_config());
    if ($lines === false) return $vals;
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        if (preg_match('/^([a-zA-Z_][\w]*):\s*(.*?)\s*$/', $line, $m)) {
            $key = $m[1];
            $raw = $m[2];
            // 多行值：key 后为空时，取后续第一个非空缩进行
            if ($raw === '') {
                for ($j = $i + 1; $j < $n; $j++) {
                    $nl = $lines[$j];
                    if (preg_match('/^\s+/', $nl)) {
                        if (preg_match('/^\s*([^#\s].*?)\s*(#.*)?$/', $nl, $mm)) {
                            $raw = trim($mm[1]);
                            break;
                        }
                    } else {
                        break;
                    }
                }
            }
            $val = $raw;
            // 去行尾注释
            if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*(#.*)?$/', $raw, $mm)) {
                $val = stripslashes($mm[1]);
            } elseif (preg_match("/^'((?:[^'\\\\]|\\\\.)*)'\s*(#.*)?$/", $raw, $mm)) {
                $val = stripslashes($mm[1]);
            } else {
                // 裸值：去掉尾部 # 注释
                if (preg_match('/^(.*?)\s+#/', $raw, $mm)) {
                    $val = trim($mm[1]);
                } else {
                    $val = trim($raw);
                }
            }
            $vals[$key] = $val;
        }
    }
    return $vals;
}
function yaml_format_val($val, $type) {
    if ($type === 'bool') return ($val === 'true' || $val === '1') ? 'true' : 'false';
    if ($type === 'int') return (string)(int)$val;
    if ($type === 'float') return (string)(float)$val;
    if ($type === 'password') {
        if ($val === '') return null; // 不修改
        return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $val) . '"';
    }
    // str, select
    return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $val) . '"';
}
function config_save_form($updates) {
    if (!is_file(instance_config())) return array('ok' => false, 'msg' => 'config.yaml 不存在');
    $lines = file(instance_config());
    if ($lines === false) return array('ok' => false, 'msg' => '无法读取 config.yaml');
    $out = array();
    $changed = array();
    $found = array();
    foreach ($lines as $line) {
        $matched = false;
        if (preg_match('/^([a-zA-Z_][\w]*):\s*/', $line, $m)) {
            $key = $m[1];
            if (array_key_exists($key, $updates) && $updates[$key] !== null) {
                $found[$key] = true;
                // 保留注释
                $rest = substr($line, strlen($m[0]));
                $comment = '';
                if (preg_match('/#\s*.*$/', $rest, $cm)) {
                    $comment = ' ' . $cm[0];
                }
                $out[] = $key . ': ' . $updates[$key] . $comment . "\n";
                $changed[] = $key;
                $matched = true;
            }
        }
        if (!$matched) $out[] = $line;
    }
    $r = @file_put_contents(instance_config(), implode('', $out));
    if ($r === false) return array('ok' => false, 'msg' => '写入失败，请检查文件权限：chmod 666 ' . instance_config());
    return array('ok' => true, 'msg' => '已更新 ' . count($changed) . ' 项配置', 'changed' => $changed);
}
function config_save_text($content) {
    if (strpos($content, 'locales:') === false && strpos($content, 'power_enable:') === false) {
        return array('ok' => false, 'msg' => '内容异常，未找到有效配置键，已取消保存');
    }
    $r = @file_put_contents(instance_config(), $content);
    if ($r === false) return array('ok' => false, 'msg' => '写入失败，请检查文件权限：chmod 666 ' . instance_config());
    return array('ok' => true, 'msg' => '配置已保存');
}
function config_backup() {
    if (!is_file(instance_config())) return false;
    $bak = dirname(instance_config()) . '/config.yaml.bak.' . date('YmdHis');
    if (@copy(instance_config(), $bak)) return $bak;
    return false;
}

/* ===== 自动更新 ===== */
function http_get($url, $timeout = 8) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'M7A-Panel/' . PANEL_VERSION,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('code' => $code, 'body' => $body);
    }
    $ctx = stream_context_create(array('http' => array(
        'timeout'     => $timeout,
        'ignore_errors' => true,
        'user_agent'  => 'M7A-Panel/' . PANEL_VERSION,
    )));
    $body = @file_get_contents($url, false, $ctx);
    $code = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return array('code' => $code, 'body' => $body);
}

function update_api_url() {
    if (UPDATE_TYPE === 'github') {
        return 'https://api.github.com/repos/' . rawurlencode(UPDATE_OWNER) . '/' . rawurlencode(UPDATE_REPO) . '/releases/latest';
    }
    return rtrim(UPDATE_HOST, '/') . '/api/v1/repos/' . rawurlencode(UPDATE_OWNER) . '/' . rawurlencode(UPDATE_REPO) . '/releases/latest';
}

function update_raw_url() {
    if (UPDATE_TYPE === 'github') {
        return 'https://raw.githubusercontent.com/' . rawurlencode(UPDATE_OWNER) . '/' . rawurlencode(UPDATE_REPO) . '/' . UPDATE_BRANCH . '/index.php';
    }
    return rtrim(UPDATE_HOST, '/') . '/' . rawurlencode(UPDATE_OWNER) . '/' . rawurlencode(UPDATE_REPO) . '/raw/branch/' . UPDATE_BRANCH . '/index.php';
}

/**
 * 多镜像下载地址列表（v1.1.1+）
 * 官方源失败时自动依次尝试加速镜像；时间戳参数用于绕过镜像缓存。
 */
function update_raw_urls() {
    if (UPDATE_TYPE !== 'github') {
        return array(update_raw_url());
    }
    $official = update_raw_url();
    $mirrors = array(
        $official,                                   // 0 官方源
        'https://ghfast.top/' . $official,           // 1 加速镜像
        'https://gh-proxy.com/' . $official,         // 2 加速镜像
        'https://ghproxy.net/' . $official,          // 3 加速镜像
        'https://ghps.cc/' . $official,              // 4 加速镜像
    );
    $ts = '?t=' . time();
    foreach ($mirrors as $i => $u) {
        $mirrors[$i] = $u . $ts;
    }
    return $mirrors;
}


function test_update_source() {
    $api = update_api_url();
    $ar  = http_get($api, 8);

    $api_state = 'fail';
    if ($ar['code'] === 200 && stripos((string)$ar['body'], 'tag_name') !== false) {
        $api_state = 'ok_release';
    } elseif ($ar['code'] === 200) {
        $api_state = 'ok_no_release';
    }

    $mirrors = array();
    $raw_ok  = false;
    $raw_code = 0;
    foreach (update_raw_urls() as $i => $url) {
        $rr  = http_get($url, 8);
        $ok  = ($rr['code'] === 200 && stripos(ltrim((string)$rr['body']), '<?php') === 0);
        $mirrors[] = array(
            'name' => $i === 0 ? '官方源' : '镜像' . $i,
            'url'  => $url,
            'code' => $rr['code'],
            'ok'   => $ok,
        );
        if ($i === 0) {
            $raw_ok  = $ok;
            $raw_code = $rr['code'];
        }
    }

    return array(
        'ok'      => ($api_state !== 'fail') && $raw_ok,
        'api'     => array('url' => $api, 'code' => $ar['code'], 'state' => $api_state),
        'raw'     => array('url' => update_raw_url(), 'code' => $raw_code, 'ok' => $raw_ok),
        'mirrors' => $mirrors,
    );
}

function check_update() {
    if (!UPDATE_ENABLED) return array('ok' => true, 'enabled' => false);
    $r = http_get(update_api_url(), 8);
    if ($r['code'] !== 200 || empty($r['body'])) {
        return array('ok' => false, 'err' => '更新源连接失败（HTTP ' . $r['code'] . '）');
    }
    $j = json_decode($r['body'], true);
    if (!is_array($j) || empty($j['tag_name'])) {
        return array('ok' => false, 'err' => '更新源返回数据异常');
    }
    $latest = ltrim((string) $j['tag_name'], 'vV');
    $note   = isset($j['body']) ? trim((string) $j['body']) : '';
    return array(
        'ok'         => true,
        'enabled'    => true,
        'has_update' => version_compare($latest, PANEL_VERSION, '>'),
        'latest'     => $latest,
        'current'    => PANEL_VERSION,
        'note'       => $note,
    );
}

function do_update() {
    if (!UPDATE_ENABLED) return array('ok' => false, 'msg' => '未启用自动更新');
    $errors = array();
    foreach (update_raw_urls() as $i => $url) {
        $src = $i === 0 ? '官方源' : '加速镜像' . $i;
        $r = http_get($url, 15);
        if ($r['code'] !== 200 || empty($r['body'])) {
            $errors[] = $src . ' HTTP ' . $r['code'];
            continue;
        }
        $content = $r['body'];
        if (stripos(ltrim($content), '<?php') !== 0) {
            $errors[] = $src . '内容校验失败';
            continue;
        }
        $bak = __DIR__ . '/index.php.bak.' . date('YmdHis');
        if (!@copy(__FILE__, $bak)) {
            return array('ok' => false, 'msg' => '备份当前文件失败，已中止更新');
        }
        if (@file_put_contents(__FILE__, $content) === false) {
            @copy($bak, __FILE__);
            return array('ok' => false, 'msg' => '写入新版本失败，已回滚到备份');
        }
        return array('ok' => true, 'msg' => '更新完成（来源：' . $src . '），页面即将刷新', 'bak' => basename($bak));
    }
    $detail = implode('；', $errors);
    return array('ok' => false, 'msg' => '所有更新源下载失败（' . $detail . '）。请检查服务器网络，或在服务器配置代理后重试');
}

/* ===== AJAX 请求 ===== */
if (isset($_GET['ajax']) && is_auth()) {
    $ajax = $_GET['ajax'];
    if ($ajax === 'status') {
        header('Content-Type: text/plain; charset=utf-8');
        echo container_status();
        exit;
    }
    if ($ajax === 'log') {
        header('Content-Type: application/json; charset=utf-8');
        $files = log_files();
        $baseNames = array_map('basename', $files);
        $file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
        $idx = $file === '' ? 0 : array_search($file, $baseNames);
        if ($idx === false) $idx = 0;
        $path = $files[$idx] ?? null;
        $opts = array(
            'keyword' => (string)($_GET['keyword'] ?? ''),
            'level'   => (string)($_GET['level'] ?? ''),
            'hours'   => (int)($_GET['hours'] ?? 0),
            'lines'   => max(100, min(5000, (int)($_GET['lines'] ?? 2000))),
        );
        if ($path) {
            $r = filter_log($path, $opts);
            echo json_encode(array(
                'ok'    => true,
                'file'  => basename($path),
                'files' => $baseNames,
                'total' => $r['total'],
                'lines' => $r['lines'],
            ), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array('ok' => true, 'file' => null, 'files' => array(), 'total' => 0, 'lines' => '(暂无日志文件，任务运行后会生成)'), JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    if ($ajax === 'running') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('running' => container_is_running()));
        exit;
    }
    if ($ajax === 'config_raw') {
        header('Content-Type: text/plain; charset=utf-8');
        $raw = config_read_raw();
        echo $raw !== null ? $raw : '(无法读取 config.yaml)';
        exit;
    }
    if ($ajax === 'test_update_source') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(test_update_source(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ajax === 'check_update') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(check_update(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit;
}

/* ===== 下载配置备份 ===== */
if (isset($_GET['download']) && $_GET['download'] === 'config' && is_auth()) {
    if (!is_file(instance_config())) {
        header('Location: index.php');
        exit;
    }
    header('Content-Type: application/octet-stream; charset=utf-8');
    header('Content-Disposition: attachment; filename="config.yaml.bak.' . date('YmdHis') . '.yaml"');
    header('Content-Length: ' . (string) filesize(instance_config()));
    readfile(instance_config());
    exit;
}

/* ===== 导出日志（应用当前过滤条件） ===== */
if (isset($_GET['export_log']) && $_GET['export_log'] === '1' && is_auth()) {
    $files = log_files();
    $baseNames = array_map('basename', $files);
    $file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
    $idx = $file === '' ? 0 : array_search($file, $baseNames);
    if ($idx === false) $idx = 0;
    $path = $files[$idx] ?? null;
    if ($path) {
        $opts = array(
            'keyword' => (string)($_GET['keyword'] ?? ''),
            'level'   => (string)($_GET['level'] ?? ''),
            'hours'   => (int)($_GET['hours'] ?? 0),
            'lines'   => 5000,
        );
        $r = filter_log($path, $opts);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="m7a_log_' . date('Ymd_His') . '.log"');
        echo implode("\n", $r['lines']);
    }
    exit;
}

/* ===== POST 请求处理 ===== */
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 设置密码（无需 csrf，首次无 session token）
    if ($action === 'setup_pass' && !is_file(PASS_FILE)) {
        $p1 = $_POST['pass1'] ?? '';
        $p2 = $_POST['pass2'] ?? '';
        if (strlen($p1) < 6) { $err = '密码至少 6 位'; }
        elseif ($p1 !== $p2) { $err = '两次输入的密码不一致'; }
        elseif (set_pass($p1)) { $_SESSION[SKEY] = true; header('Location: index.php'); exit; }
        else { $err = '密码文件写入失败，请检查面板目录权限'; }
    }
    // 登录
    elseif ($action === 'login') {
        $p = $_POST['pass'] ?? '';
        if (check_pass($p)) { $_SESSION[SKEY] = true; header('Location: index.php'); exit; }
        else { $err = '密码错误'; }
    }
    // 以下需认证 + CSRF
    elseif (is_auth() && csrf_check()) {
        if ($action === 'logout') {
            unset($_SESSION[SKEY]); header('Location: index.php'); exit;
        }
        // 多实例：切换当前实例
        elseif ($action === 'instance_switch') {
            $target = trim((string)($_POST['instance_id'] ?? ''));
            if ($target !== '' && instance_switch_to($target)) {
                $msg = '已切换到实例：' . h(instance_name()) . '（' . h(instance_container()) . '）';
            } else {
                $err = '切换失败：实例不存在';
            }
        }
        // 多实例：新增 / 编辑
        elseif ($action === 'instance_save') {
            $id = trim((string)($_POST['inst_id'] ?? ''));
            $name = trim((string)($_POST['inst_name'] ?? ''));
            $container = trim((string)($_POST['inst_container'] ?? ''));
            $dir = rtrim(trim((string)($_POST['inst_dir'] ?? '')), '/');
            $isDefault = !empty($_POST['inst_default']);
            if ($name === '' || $container === '' || $dir === '') {
                $err = '名称、容器名、项目目录均不能为空';
            } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $container)) {
                $err = '容器名只能包含字母、数字、下划线、短横线';
            } else {
                $list = instances_load();
                $isNew = true;
                $used = array();
                foreach ($list as $k => $item) {
                    if (($item['id'] === $id && $id !== '') || ($id === '' && $item['container'] === $container)) {
                        $list[$k]['name'] = $name;
                        $list[$k]['container'] = $container;
                        $list[$k]['dir'] = $dir;
                        $list[$k]['default'] = $isDefault;
                        $isNew = false;
                    } elseif ($item['container'] === $container) {
                        $used[] = $item['name'];
                    }
                }
                if ($used) {
                    $err = '容器名已被实例「' . implode('、', $used) . '」使用';
                } elseif ($isNew) {
                    $list[] = array('id' => $container, 'name' => $name, 'container' => $container, 'dir' => $dir, 'default' => $isDefault);
                }
                if ($err === '') {
                    // 保证只有一个默认实例
                    $anyDefault = false;
                    foreach ($list as $k => $item) {
                        if ($isDefault && $item['id'] === ($isNew ? $container : $id)) { $list[$k]['default'] = true; $anyDefault = true; }
                        elseif ($isDefault && $item['default']) { $list[$k]['default'] = false; }
                        elseif (!$isDefault && $item['default']) $anyDefault = true;
                    }
                    if (!$anyDefault && $list) $list[0]['default'] = true;
                    if (instances_write($list)) {
                        if ($isNew) instance_switch_to($container);
                        $msg = $isNew ? '实例「' . h($name) . '」已添加并切换' : '实例「' . h($name) . '」已更新';
                    } else {
                        $err = '写入 ' . h(basename(INSTANCES_FILE)) . ' 失败，请检查面板目录权限';
                    }
                }
            }
        }
        // 多实例：删除
        elseif ($action === 'instance_delete') {
            $id = trim((string)($_POST['inst_id'] ?? ''));
            $confirm = trim((string)($_POST['inst_confirm'] ?? ''));
            $list = instances_load();
            $target = null;
            foreach ($list as $item) if ($item['id'] === $id) { $target = $item; break; }
            if (!$target) {
                $err = '实例不存在';
            } elseif ($confirm !== $target['name']) {
                $err = '确认失败：请输入实例名称「' . h($target['name']) . '」以删除';
            } elseif (count($list) <= 1) {
                $err = '至少保留一个实例，无法删除';
            } else {
                $newList = array();
                foreach ($list as $item) if ($item['id'] !== $id) $newList[] = $item;
                if (!instances_write($newList)) {
                    $err = '写入失败，实例未删除';
                } else {
                    if (instance_current()['id'] === $id) {
                        unset($_SESSION['m7a_instance']);
                        $nc = instance_current();
                        $_SESSION['m7a_instance'] = $nc['id'];
                    }
                    $msg = '实例「' . h($target['name']) . '」已删除';
                }
            }
        }
        // 任务
        elseif (isset($TASKS[$action])) {
            $r = task_start($action);
            $msg = $r['code'] === 0
                ? '任务「' . $TASKS[$action]['label'] . '」已后台启动'
                : '任务启动失败：' . $r['out'];
        }
        // 容器操作
        elseif ($action === 'restart') {
            $r = compose('restart');
            $msg = $r['code'] === 0 ? '容器已重启' : '重启失败：' . $r['out'];
        }
        elseif ($action === 'update') {
            $r1 = compose('pull');
            if ($r1['code'] !== 0) { $err = '拉取镜像失败：' . $r1['out']; }
            else {
                $r2 = compose('up -d');
                $msg = $r2['code'] === 0 ? '镜像已更新，容器已重建' : '更新失败：' . $r2['out'];
            }
        }
        // 配置保存 - 表单模式
        elseif ($action === 'save_config_form') {
            $bak = config_backup();
            $updates = array();
            foreach ($CONFIG_GROUPS as $g) {
                foreach ($g['fields'] as $key => $f) {
                    $postKey = 'cfg_' . $key;
                    if ($f['type'] === 'bool') {
                        $updates[$key] = isset($_POST[$postKey]) ? 'true' : 'false';
                    } elseif ($f['type'] === 'password') {
                        $v = trim($_POST[$postKey] ?? '');
                        if ($v !== '') {
                            $updates[$key] = '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $v) . '"';
                        }
                        // 空值不更新
                    } else {
                        $v = trim($_POST[$postKey] ?? '');
                        $updates[$key] = yaml_format_val($v, $f['type']);
                    }
                }
            }
            $r = config_save_form($updates);
            if ($r['ok']) {
                $msg = $r['msg'] . '（备份：' . ($bak ? basename($bak) : '无') . '）';
                if (!empty($_POST['then_restart'])) {
                    $rr = compose('restart');
                    $msg .= $rr['code'] === 0 ? '。容器已重启，配置已生效。' : '。但重启失败：' . $rr['out'];
                } else {
                    $msg .= '。需重启容器生效。';
                }
            } else {
                $err = $r['msg'];
            }
        }
        // 自动更新 - 执行更新
        elseif ($action === 'do_update') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(do_update(), JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 配置保存 - 文本模式
        elseif ($action === 'save_config_text') {
            $bak = config_backup();
            $content = $_POST['yaml_content'] ?? '';
            $r = config_save_text($content);
            if ($r['ok']) {
                $msg = $r['msg'] . '（备份：' . ($bak ? basename($bak) : '无') . '）。需重启容器生效。';
            } else {
                $err = $r['msg'];
            }
        }
        // 配置恢复 - 上传备份文件
        elseif ($action === 'restore_config') {
            if (empty($_FILES['cfg_file']) || $_FILES['cfg_file']['error'] !== UPLOAD_ERR_OK) {
                $err = '未收到上传文件或上传失败';
            } else {
                $fname = $_FILES['cfg_file']['name'];
                $fsize = (int) $_FILES['cfg_file']['size'];
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (!in_array($ext, array('yaml', 'yml'), true)) {
                    $err = '文件格式错误，请上传 .yaml / .yml 文件';
                } elseif ($fsize <= 0 || $fsize > 2 * 1024 * 1024) {
                    $err = '文件为空或过大（最大 2MB）';
                } else {
                    $content = @file_get_contents($_FILES['cfg_file']['tmp_name']);
                    if ($content === false || strlen(trim($content)) < 10 || strpos($content, ':') === false) {
                        $err = '文件内容异常，未识别到有效 YAML 配置';
                    } else {
                        $bak = config_backup();
                        $r = @file_put_contents(instance_config(), $content);
                        if ($r === false) {
                            $err = '写入失败，请检查文件权限：chmod 666 ' . instance_config();
                        } else {
                            $msg = '配置已恢复（' . h($fname) . '），原配置备份：' . ($bak ? basename($bak) : '无') . '。需重启容器生效。';
                        }
                    }
                }
            }
        }
    }
    elseif (is_auth() && !csrf_check()) {
        $err = '请求验证失败，请重试';
    }
}

/* ===== 页面输出 ===== */
header('Content-Type: text/html; charset=utf-8');
$isAuth = is_auth();
$needSetup = !is_file(PASS_FILE);
$cfgVals = $isAuth ? yaml_read_simple() : array();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>M7A WebUI · March7th 管理面板</title>
<style>
/* ============================================================
   M7A WebUI v1.3 · 三月七粉→浅蓝渐变 · 玻璃拟态侧边栏布局
   仅外观改造，功能与后端逻辑零改动
   ============================================================ */
:root {
  --bg: #fdf6fb;
  --card: rgba(255,255,255,.72);
  --card2: rgba(255,255,255,.55);
  --card-solid: #ffffff;
  --text: #581c87;
  --muted: #a78ba3;
  --primary: #ec4899;
  --primary-2: #38bdf8;
  --primary-soft: #fce7f3;
  --green: #10b981;
  --green-bg: #ecfdf5;
  --red: #ef4444;
  --red-bg: #fef2f2;
  --orange: #f59e0b;
  --orange-bg: #fffbeb;
  --border: rgba(236,72,153,.16);
  --shadow: 0 8px 32px rgba(236,72,153,.10);
  --radius: 14px;
  --grad: linear-gradient(135deg,#ec4899 0%,#7dd3fc 100%);
  --grad-soft: linear-gradient(135deg,rgba(236,72,153,.13),rgba(125,211,252,.13));
}
html[data-theme="light"] {
  --bg: #f0f2f5;
  --card: #ffffff;
  --card2: #f8fafc;
  --card-solid: #ffffff;
  --text: #1e293b;
  --muted: #64748b;
  --primary: #6366f1;
  --primary-2: #8b5cf6;
  --primary-soft: #eef2ff;
  --green-bg: #ecfdf5;
  --red-bg: #fef2f2;
  --orange-bg: #fffbeb;
  --border: #e2e8f0;
  --shadow: 0 1px 3px rgba(0,0,0,.08);
  --grad: linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
  --grad-soft: linear-gradient(135deg,rgba(99,102,241,.12),rgba(139,92,246,.12));
}
html[data-theme="dark"] {
  --bg: #171022;
  --card: rgba(38,24,48,.72);
  --card2: rgba(51,33,63,.55);
  --card-solid: #241632;
  --text: #f3e8ff;
  --muted: #a78bfa;
  --primary: #f472b6;
  --primary-2: #7dd3fc;
  --primary-soft: #3b1d33;
  --green-bg: #064e3b;
  --red-bg: #7f1d1d;
  --orange-bg: #78350f;
  --border: rgba(244,114,182,.2);
  --shadow: 0 8px 32px rgba(0,0,0,.35);
  --grad: linear-gradient(135deg,#f472b6 0%,#7dd3fc 100%);
  --grad-soft: linear-gradient(135deg,rgba(244,114,182,.16),rgba(125,211,252,.16));
}
/* 默认主题（march7 粉→浅蓝）背景 */
body {
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;
  color:var(--text);
  min-height:100vh;
  background:
    radial-gradient(ellipse at 12% 0%, rgba(236,72,153,.13), transparent 52%),
    radial-gradient(ellipse at 88% 100%, rgba(125,211,252,.20), transparent 52%),
    linear-gradient(160deg,#fdf2f8 0%,#eef7ff 52%,#fdf2f8 100%);
  background-attachment: fixed;
}
html[data-theme="light"] body {
  background:var(--bg);
}
html[data-theme="dark"] body {
  background:
    radial-gradient(ellipse at 12% 0%, rgba(236,72,153,.16), transparent 52%),
    radial-gradient(ellipse at 88% 100%, rgba(56,189,248,.13), transparent 52%),
    linear-gradient(160deg,#171022 0%,#0f1726 52%,#171022 100%);
  background-attachment: fixed;
}
a { color:var(--primary); text-decoration:none; }
* { margin:0; padding:0; box-sizing:border-box; }

/* 渐变文字 */
.grad-text { background:var(--grad); -webkit-background-clip:text; background-clip:text; color:transparent; }

/* ===== 布局 ===== */
.layout { display:flex; min-height:100vh; }
.content { flex:1; min-width:0; padding:24px 28px 56px; max-width:1180px; }

/* ===== 侧边栏 ===== */
.sidebar {
  width:16rem; flex-shrink:0;
  background:rgba(255,255,255,.66);
  backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
  border-right:1px solid var(--border);
  display:flex; flex-direction:column;
  position:sticky; top:0; height:100vh; z-index:200;
}
html[data-theme="dark"] .sidebar { background:rgba(30,18,40,.66); }
.sidebar-logo { padding:22px 18px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--border); }
.logo-badge {
  width:42px; height:42px; border-radius:12px; flex-shrink:0;
  background:var(--grad); color:#fff; font-weight:800; font-size:16px;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 6px 18px rgba(236,72,153,.35);
}
.logo-title { font-size:18px; font-weight:800; letter-spacing:.3px; }
.logo-sub { font-size:11px; color:var(--muted); margin-top:2px; }
.sidebar-nav { flex:1; padding:14px 12px; display:flex; flex-direction:column; gap:4px; overflow-y:auto; }
.nav-item {
  display:flex; align-items:center; gap:10px; width:100%; text-align:left;
  padding:11px 14px; border:none; border-radius:10px; cursor:pointer;
  background:transparent; color:var(--muted); font-size:14px; font-weight:500;
  transition:all .15s; font-family:inherit;
}
.nav-item:hover { background:rgba(236,72,153,.07); color:var(--text); }
.nav-item.active {
  background:var(--grad-soft); color:var(--primary); font-weight:600;
  box-shadow:inset 0 0 0 1px var(--border);
}
.nav-icon { font-size:16px; }
.sidebar-bottom { padding:12px; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:10px; }
.sidebar-bottom-row { display:flex; gap:8px; align-items:center; }
.sidebar-bottom-row .logout-btn { flex:1; justify-content:center; }

/* 图标按钮 */
.icon-btn {
  width:38px; height:38px; border-radius:10px; border:1px solid var(--border);
  background:var(--card2); color:var(--text); font-size:16px; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center; transition:all .15s;
}
.icon-btn:hover { border-color:var(--primary); background:var(--primary-soft); }

/* 侧边栏遮罩 & 移动端顶栏 */
.sidebar-overlay { position:fixed; inset:0; background:rgba(30,12,40,.42); backdrop-filter:blur(2px); z-index:190; display:none; }
.sidebar-overlay.show { display:block; }
.mobile-topbar {
  display:none; align-items:center; gap:10px; margin-bottom:16px;
  background:var(--card); backdrop-filter:blur(14px); border:1px solid var(--border);
  border-radius:12px; padding:10px 14px; box-shadow:var(--shadow);
}

/* ===== 状态徽章 ===== */
.status-badge {
  display:inline-flex; align-items:center; gap:6px; padding:6px 14px;
  border-radius:20px; font-size:12px; font-weight:600;
  background:rgba(236,72,153,.10); color:var(--primary);
  border:1px solid var(--border);
}
.status-badge .dot { width:8px; height:8px; border-radius:50%; background:var(--muted); }
.status-badge.running .dot { background:#4ade80; animation:pulse 2s infinite; box-shadow:0 0 0 3px rgba(74,222,128,.18); }
.status-badge.stopped .dot { background:#f87171; box-shadow:0 0 0 3px rgba(248,113,113,.15); }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.35} }

/* ===== 页面 ===== */
.page { display:none; }
.page.active { display:block; animation:fadeIn .25s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
.page-head { margin-bottom:18px; }
.page-title { font-size:22px; font-weight:800; display:flex; align-items:center; gap:8px; }
.page-title .pt-icon { font-size:22px; }
.page-desc { font-size:13px; color:var(--muted); margin-top:4px; }

/* ===== 卡片 ===== */
.card {
  background:var(--card); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
  border-radius:var(--radius); padding:20px 24px; margin-bottom:16px;
  border:1px solid var(--border); box-shadow:var(--shadow);
}
html[data-theme="light"] .card { background:var(--card); }
.card h2 { font-size:15px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.card h2 .icon { font-size:18px; }

/* ===== 消息 ===== */
.msg { padding:12px 16px; border-radius:12px; margin-bottom:16px; font-size:14px; display:flex; align-items:center; gap:8px; }
.msg.ok { background:var(--green-bg); color:var(--green); border:1px solid var(--green); }
.msg.err { background:var(--red-bg); color:var(--red); border:1px solid var(--red); }

/* ===== 按钮 ===== */
.btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; transition:all .15s; color:#fff; }
.btn:hover { opacity:.92; transform:translateY(-1px); }
.btn:active { transform:translateY(0); }
.btn.primary { background:var(--grad); box-shadow:0 4px 14px rgba(236,72,153,.3); }
.btn.green { background:var(--green); }
.btn.orange { background:var(--orange); }
.btn.red { background:var(--red); }
.btn.gray { background:#94a3b8; }
.btn.small { padding:7px 14px; font-size:12.5px; }
.btn-group { display:flex; flex-wrap:wrap; gap:8px; }
.logout-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border:none; border-radius:10px; cursor:pointer; font-size:13px; font-weight:600; color:#fff; background:var(--grad); }

/* ===== 代码框 ===== */
.codebox {
  background:var(--card2); border:1px solid var(--border); border-radius:12px; padding:14px;
  font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12.5px; line-height:1.7;
  white-space:pre-wrap; word-break:break-all; max-height:440px; overflow:auto; color:var(--text);
}

/* ===== 任务卡片 ===== */
.task-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
.task-card {
  background:var(--card2); border:1px solid var(--border); border-radius:12px; padding:16px;
  text-align:center; transition:all .18s; backdrop-filter:blur(8px);
}
.task-card:hover { border-color:var(--primary); box-shadow:0 8px 24px rgba(236,72,153,.15); transform:translateY(-2px); }
.task-card .icon { font-size:30px; margin-bottom:8px; }
.task-card .label { font-size:14px; font-weight:700; margin-bottom:4px; }
.task-card .desc { font-size:11px; color:var(--muted); line-height:1.4; }
.task-card form { margin-top:10px; }
.task-card .btn { width:100%; justify-content:center; }

/* ===== 配置表单 ===== */
.cfg-group { margin-bottom:20px; }
.cfg-group h3 { font-size:14px; font-weight:700; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:6px; }
.cfg-row { display:flex; align-items:center; justify-content:space-between; padding:9px 0; gap:12px; }
.cfg-row + .cfg-row { border-top:1px solid var(--border); }
.cfg-label { font-size:13px; color:var(--text); flex:1; min-width:0; }
.cfg-input { flex:0 0 auto; }
/* Switch */
.switch { position:relative; display:inline-block; width:44px; height:24px; }
.switch input { opacity:0; width:0; height:0; }
.switch .slider { position:absolute; inset:0; background:#d6c3d0; border-radius:24px; transition:.2s; cursor:pointer; }
.switch .slider:before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.switch input:checked + .slider { background:var(--grad); }
.switch input:checked + .slider:before { transform:translateX(20px); }
/* Select / Input */
.cfg-select, .cfg-input-text, .cfg-input-num {
  padding:7px 12px; border:1px solid var(--border); border-radius:9px; font-size:13px;
  background:var(--card-solid); color:var(--text); min-width:160px; font-family:inherit;
}
.cfg-input-text { min-width:200px; }
.cfg-input-num { min-width:100px; }
.cfg-select:focus, .cfg-input-text:focus, .cfg-input-num:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(236,72,153,.12); }
.cfg-pass { min-width:200px; }
.cfg-tip { display:block; font-size:11px; color:var(--muted); margin-top:2px; font-weight:400; line-height:1.4; }
.cfg-textarea {
  width:100%; min-width:260px; padding:7px 12px; border:1px solid var(--border); border-radius:9px;
  font-size:12px; background:var(--card-solid); color:var(--text);
  font-family:Consolas,Monaco,monospace; resize:vertical; box-sizing:border-box;
}
.cfg-textarea:focus { outline:none; border-color:var(--primary); }

/* YAML 编辑器 */
.yaml-editor {
  width:100%; min-height:500px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12.5px; line-height:1.6;
  padding:14px; border:1px solid var(--border); border-radius:12px; background:var(--card2);
  color:var(--text); resize:vertical; tab-size:2;
}
.yaml-editor:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(236,72,153,.12); }

/* 配置模式切换 */
.cfg-tabs { display:flex; gap:0; margin-bottom:16px; border-bottom:1px solid var(--border); }
.cfg-tab { padding:8px 16px; border:none; background:transparent; color:var(--muted); font-size:13px; cursor:pointer; border-bottom:2px solid transparent; }
.cfg-tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:600; }
.cfg-panel { display:none; }
.cfg-panel.active { display:block; }

/* ===== 日志 ===== */
.log-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
.auto-refresh { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--muted); }
.auto-refresh input { accent-color:var(--primary); }
.log-filter { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; }
.log-filter input[type=text] {
  flex:1; min-width:160px; padding:7px 12px; border:1px solid var(--border); border-radius:9px;
  font-size:13px; background:var(--card-solid); color:var(--text);
}
.log-filter input[type=text]:focus { outline:none; border-color:var(--primary); }
.log-filter select {
  padding:7px 10px; border:1px solid var(--border); border-radius:9px; font-size:13px;
  background:var(--card-solid); color:var(--text);
}
.log-count { font-size:12px; color:var(--muted); }
.hl { background:#fecdd3; color:#9f1239; border-radius:3px; padding:0 2px; }

/* ===== 登录页 ===== */
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.auth-card {
  background:var(--card); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  border-radius:20px; padding:40px 34px; max-width:400px; width:100%;
  border:1px solid var(--border); box-shadow:0 20px 60px rgba(236,72,153,.18); text-align:center;
}
.auth-logo {
  width:64px; height:64px; border-radius:18px; margin:0 auto 14px;
  background:var(--grad); color:#fff; font-size:24px; font-weight:800;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 10px 28px rgba(236,72,153,.4);
}
.auth-card h1 { font-size:22px; margin-bottom:4px; font-weight:800; }
.auth-card .sub { color:var(--muted); font-size:13px; margin-bottom:24px; }
.auth-card input[type=password] {
  width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:11px;
  font-size:15px; margin-bottom:12px; background:var(--card-solid); color:var(--text);
}
.auth-card input[type=password]:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(236,72,153,.14); }
.auth-card .btn { width:100%; justify-content:center; }
.tip { font-size:12px; color:var(--muted); line-height:1.6; margin-top:10px; }

/* ===== 信息网格 ===== */
.info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
.info-item {
  background:var(--card2); border-radius:12px; padding:14px; border:1px solid var(--border);
  backdrop-filter:blur(8px);
}
.info-item .label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.info-item .value { font-size:16px; font-weight:700; }

/* ===== 更新提醒 ===== */
.update-banner { margin-bottom:16px; }
.update-inner {
  display:flex; align-items:center; gap:14px; background:var(--grad); color:#fff;
  border-radius:14px; padding:14px 18px; box-shadow:0 8px 24px rgba(236,72,153,.32); flex-wrap:wrap;
}
.update-icon { font-size:24px; }
.update-info { flex:1; min-width:200px; }
.update-title { font-weight:700; font-size:14px; }
.update-note { font-size:12px; opacity:.92; margin-top:3px; white-space:pre-wrap; max-height:72px; overflow-y:auto; }
.update-actions { display:flex; gap:8px; align-items:center; }
.update-banner .btn.small { padding:6px 14px; font-size:12px; }
.update-banner .btn.gray { background:rgba(255,255,255,.28); color:#fff; border:none; }
.update-banner .btn.gray:hover { background:rgba(255,255,255,.4); }

/* ===== 响应式 ===== */
@media (max-width:899px) {
  .sidebar { position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform .3s ease; box-shadow:0 0 40px rgba(0,0,0,.2); }
  .sidebar.open { transform:translateX(0); }
  .mobile-topbar { display:flex; }
  .content { padding:16px; }
  .task-grid { grid-template-columns:1fr 1fr; }
  .cfg-row { flex-direction:column; align-items:flex-start; }
  .cfg-input { align-self:flex-end; }
}
@media (min-width:900px) {
  .sidebar { transform:none; }
  .sidebar-overlay { display:none !important; }
}

/* ===== 多实例切换器 ===== */
.inst-switcher {
  display:flex; gap:6px; align-items:center; padding:10px 14px 4px;
}
.inst-select {
  flex:1; min-width:0; padding:8px 10px; border:1px solid var(--border); border-radius:10px;
  font-size:13px; font-weight:600; color:var(--text); background:var(--card-solid);
}
.inst-select:focus { outline:none; border-color:var(--primary); }
/* ===== 实例管理弹窗 ===== */
.modal-overlay {
  position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.45);
  display:flex; align-items:flex-start; justify-content:center; padding:48px 16px; overflow-y:auto;
}
.modal {
  width:100%; max-width:640px; background:var(--card-solid); border:1px solid var(--border);
  border-radius:16px; box-shadow:var(--shadow); overflow:hidden;
}
.modal-head {
  display:flex; align-items:center; justify-content:space-between; padding:16px 20px;
  border-bottom:1px solid var(--border); background:var(--grad-soft);
}
.modal-title { font-size:16px; font-weight:800; }
.modal-body { padding:16px 20px 20px; }
.inst-list { display:flex; flex-direction:column; gap:8px; }
.inst-item {
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:10px 14px; border:1px solid var(--border); border-radius:12px; background:var(--card);
}
.inst-name { font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.inst-meta { font-size:12px; color:var(--muted); margin-top:2px; word-break:break-all; }
.inst-actions { display:flex; gap:6px; flex-shrink:0; }
.badge-default, .badge-cur {
  font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;
}
.badge-default { background:var(--primary-soft); color:var(--primary); }
.badge-cur { background:var(--green-bg); color:var(--green); }
.inst-form-wrap {
  margin-top:14px; padding:14px; border:1px dashed var(--primary); border-radius:12px; background:var(--card2);
}
.inst-form { display:flex; flex-direction:column; gap:10px; }
.inst-form-row { display:flex; align-items:center; gap:10px; font-size:13px; }
.inst-form-row label { width:70px; flex-shrink:0; color:var(--muted); font-weight:600; }
.inst-form-row input[type="text"] { flex:1; }

/* ===== v1.5 云游戏画面直播预览 ===== */
.live-stage {
  position:relative; margin-top:12px; background:#0b0b12; border-radius:12px;
  overflow:hidden; box-shadow:inset 0 0 0 1px rgba(255,255,255,.06), 0 4px 24px rgba(0,0,0,.35);
}
.live-statusbar {
  display:flex; align-items:center; gap:8px; padding:8px 14px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02));
  border-bottom:1px solid rgba(255,255,255,.06);
}
.live-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; background:#9ca3af; }
.live-dot.yellow { background:#fbbf24; box-shadow:0 0 8px rgba(251,191,36,.8); animation:lvpulse 1.2s infinite; }
.live-dot.green  { background:#22c55e; box-shadow:0 0 10px rgba(34,197,94,.9); animation:lvpulse 1.2s infinite; }
.live-dot.red    { background:#ef4444; box-shadow:0 0 8px rgba(239,68,68,.8); animation:lvpulse 1s infinite; }
@keyframes lvpulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
.live-status { color:#e5e7eb; font-size:12px; font-weight:600; letter-spacing:.3px; }
.live-fps { margin-left:auto; color:#9ca3af; font-size:12px; font-variant-numeric:tabular-nums; }
.live-fps b { color:#22c55e; font-size:13px; }
#liveCanvas { display:block; width:100%; aspect-ratio:16/9; background:#000; }
.live-placeholder {
  position:absolute; left:0; right:0; top:34px; bottom:0;
  display:flex; align-items:center; justify-content:center;
  color:#6b7280; font-size:14px; letter-spacing:1px; pointer-events:none;
}
.live-guide { padding:14px; border-top:1px solid rgba(239,68,68,.3); background:rgba(239,68,68,.07); }
.guide-head { color:#fca5a5; font-size:13px; font-weight:700; margin-bottom:8px; }
.live-guide pre {
  background:#160b0d; color:#f3d2d2; font-size:12px; line-height:1.6;
  padding:10px 12px; border-radius:8px; overflow-x:auto; white-space:pre-wrap; word-break:break-all;
  margin:0 0 8px;
}
.guide-item { color:#e5e7eb; font-size:12px; line-height:1.7; margin:4px 0; }
.guide-item code { background:rgba(255,255,255,.08); padding:1px 6px; border-radius:4px; font-size:11px; }
.live-badge {
  display:inline-flex; align-items:center; gap:5px; margin-left:8px; padding:2px 10px;
  border-radius:99px; font-size:11px; font-weight:700; vertical-align:2px;
}
.live-badge.gray   { background:#f1f5f9; color:#64748b; }
.live-badge.yellow { background:#fffbeb; color:#b45309; }
.live-badge.green  { background:#ecfdf5; color:#059669; }
.live-badge.red    { background:#fef2f2; color:#dc2626; }
html[data-theme="dark"] .live-badge.gray { background:#1e293b; color:#94a3b8; }
.live-res { margin-left:auto; display:inline-flex; gap:4px; }
.res-btn {
  padding:3px 12px; border-radius:8px; border:1px solid var(--border); background:var(--card2);
  color:var(--muted); font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; font-family:inherit;
}
.res-btn:hover { border-color:var(--primary); color:var(--primary); }
.res-btn.active {
  background:var(--grad); color:#fff; border-color:transparent;
  box-shadow:0 2px 10px rgba(236,72,153,.35);
}
</style>
</head>
<body>

<?php if (!$isAuth): ?>
<!-- ===== 登录页 ===== -->
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">M7A</div>
    <h1 class="grad-text">March7th 管理面板</h1>
    <p class="sub"><?php echo $needSetup ? '首次使用，请设置访问密码' : '请输入访问密码'; ?></p>
    <?php if ($err): ?><div class="msg err"><?php echo h($err); ?></div><?php endif; ?>
    <?php if ($needSetup): ?>
    <form method="post">
      <input type="hidden" name="action" value="setup_pass">
      <input type="password" name="pass1" placeholder="设置密码（至少 6 位）" autocomplete="new-password" required>
      <input type="password" name="pass2" placeholder="再次输入密码" autocomplete="new-password" required>
      <button type="submit" class="btn green">设置并进入</button>
    </form>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <input type="password" name="pass" placeholder="访问密码" autocomplete="current-password" required>
      <button type="submit" class="btn primary">登录</button>
    </form>
    <?php endif; ?>
    <div class="tip">密码仅保存在面板目录的 .panel_pass.php 文件中</div>
  </div>
</div>

<?php else: ?>
<!-- ===== 主界面：左侧边栏 + 内容区 ===== -->
<div class="layout">

  <!-- 侧边栏 -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">M7A</div>
      <div>
        <div class="logo-title grad-text">M7A WebUI</div>
        <div class="logo-sub">管理面板 v<?php echo h(PANEL_VERSION); ?></div>
      </div>
    </div>

    <!-- 实例切换器 -->
    <div class="inst-switcher">
      <form method="post" id="instForm" style="display:flex;gap:6px;align-items:center;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="instance_switch">
        <select name="instance_id" class="inst-select" onchange="document.getElementById('instForm').submit()" title="切换当前实例">
          <?php foreach (instances_load() as $it): $cur = instance_current(); ?>
          <option value="<?php echo h($it['id']); ?>" <?php echo $it['id'] === $cur['id'] ? 'selected' : ''; ?>><?php echo h($it['name']); ?> (<?php echo h($it['container']); ?>)</option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="button" class="icon-btn" onclick="openInstModal()" title="管理实例">⚙️</button>
    </div>

    <nav class="sidebar-nav">
      <button class="nav-item active" data-tab="overview" onclick="switchTab('overview')"><span class="nav-icon">📊</span>概览</button>
      <button class="nav-item" data-tab="tasks" onclick="switchTab('tasks')"><span class="nav-icon">🚀</span>任务</button>
      <button class="nav-item" data-tab="config" onclick="switchTab('config')"><span class="nav-icon">⚙️</span>配置</button>
      <button class="nav-item" data-tab="log" onclick="switchTab('log')"><span class="nav-icon">📝</span>日志</button>
    </nav>

    <div class="sidebar-bottom">
      <span class="status-badge" id="statusBadge" style="justify-content:center;"><span class="dot"></span><span class="status-text">检测中…</span></span>
      <div class="sidebar-bottom-row">
        <button class="icon-btn" id="themeBtn" onclick="toggleTheme()" title="切换主题">🌙</button>
        <form method="post" style="display:inline;flex:1;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="logout"><button type="submit" class="logout-btn">退出</button></form>
      </div>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- 内容区 -->
  <div class="content">

    <!-- 移动端顶栏 -->
    <div class="mobile-topbar">
      <button class="icon-btn" onclick="openSidebar()">☰</button>
      <span class="grad-text" style="font-weight:800;font-size:16px;">M7A WebUI</span>
      <span class="status-badge" id="statusBadgeM" style="margin-left:auto;"><span class="dot"></span><span class="status-text">检测中…</span></span>
    </div>

    <!-- 自动更新提醒 -->
    <div class="update-banner" id="updateBanner" style="display:none;">
      <div class="update-inner">
        <div class="update-icon">🆕</div>
        <div class="update-info">
          <div class="update-title">发现新版本 v<span id="updLatest"></span>（当前 v<span id="updCurrent"></span>）</div>
          <div class="update-note" id="updNote"></div>
        </div>
        <div class="update-actions">
          <button class="btn small" id="updBtn" onclick="doUpdate()">一键更新</button>
          <button class="btn gray small" onclick="hideUpdate()">稍后</button>
        </div>
      </div>
    </div>

    <?php if ($msg): ?><div class="msg ok">✅ <?php echo h($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err">❌ <?php echo h($err); ?></div><?php endif; ?>

    <!-- ===== 概览 ===== -->
    <div class="page active" id="panel-overview">
      <div class="page-head">
        <div class="page-title"><span class="pt-icon">📊</span>概览</div>
        <div class="page-desc">容器状态、快捷操作与基本信息</div>
      </div>

      <!-- ===== 云游戏画面直播预览（v1.5） ===== -->
      <div class="card">
        <h2><span class="icon">🖥️</span> 云游戏画面
          <span id="liveBadge" class="live-badge gray">未连接</span>
          <span class="live-res">
            <button type="button" class="res-btn active" data-res="720p" onclick="setRes('720p')">720P</button>
            <button type="button" class="res-btn" data-res="480p" onclick="setRes('480p')">480P</button>
          </span>
        </h2>
        <div class="live-stage">
          <div class="live-statusbar">
            <span id="liveDot" class="live-dot gray"></span>
            <span class="live-status" id="liveStatus">初始化…</span>
            <span class="live-fps" id="liveFps">0 fps</span>
          </div>
          <canvas id="liveCanvas" width="1280" height="720"></canvas>
          <div class="live-placeholder" id="livePlaceholder">🎮 等待云游戏画面…</div>
          <div class="live-guide" id="liveGuide" style="display:none;">
            <div class="guide-head">🔧 预览服务连接失败</div>
            <div class="guide-item" id="guideReason"></div>
            <div class="guide-item">部署者请按以下步骤检查（Nginx 反代 与 防火墙）：</div>
            <pre id="guideNginx"></pre>
            <div class="guide-item" id="guideFirewall"></div>
            <div class="guide-item">若以上均正常，检查容器内转发服务是否运行：<code>docker exec m7a python3 /m7a/preview_server.py</code>，并确认 <code>curl http://容器IP:9223/health</code> 返回 <code>{"ok":true,"cdp":true}</code>。</div>
          </div>
        </div>
        <p class="tip">画面仅在小助手执行任务时出现，空闲时显示等待状态；720P 约 5-10fps，480P 帧率更高。长时间无画面时，展开上方红色引导区查看部署配置方法。</p>
      </div>

      <div class="card">
        <h2><span class="icon">📦</span> 容器状态 <button class="btn small gray" onclick="refreshStatus()" style="margin-left:auto;">刷新</button></h2>
        <div class="codebox" id="statusBox"><?php $st = container_status(); echo $st === '' ? '(无法获取，请检查 www 用户 docker 权限)' : h($st); ?></div>
      </div>

      <div class="card">
        <h2><span class="icon">⚡</span> 快捷操作</h2>
        <div class="btn-group">
          <?php foreach ($TASKS as $key => $t): ?>
          <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo h($key); ?>"><button type="submit" class="btn primary"><?php echo h($t['icon'] . ' ' . $t['label']); ?></button></form>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h2><span class="icon">📌</span> 基本信息</h2>
        <div class="info-grid">
          <div class="info-item"><div class="label">当前实例</div><div class="value"><?php echo h(instance_name()); ?>（<?php echo h(instance_container()); ?>）</div></div>
          <div class="info-item"><div class="label">项目目录</div><div class="value" style="font-size:13px;"><?php echo h(instance_dir()); ?></div></div>
          <div class="info-item"><div class="label">容器名</div><div class="value"><?php echo h(instance_container()); ?></div></div>
          <div class="info-item"><div class="label">Config</div><div class="value" style="font-size:13px;"><?php echo is_file(instance_config()) ? '✅ 存在' : '❌ 不存在'; ?></div></div>
          <div class="info-item"><div class="label">最近日志</div><div class="value" style="font-size:13px;"><?php $lp = latest_log_path(); echo $lp ? h(basename($lp)) : '暂无'; ?></div></div>
        </div>
      </div>

      <div class="card">
        <h2><span class="icon">🔗</span> 更新源 <button class="btn small gray" onclick="testUpdateSource()" style="margin-left:auto;">测试连接</button></h2>
        <div class="info-grid">
          <div class="info-item"><div class="label">更新源类型</div><div class="value"><?php echo h(strtoupper(UPDATE_TYPE)); ?></div></div>
          <div class="info-item"><div class="label">当前版本</div><div class="value">v<?php echo h(PANEL_VERSION); ?></div></div>
          <div class="info-item"><div class="label">仓库</div><div class="value" style="font-size:13px;"><?php echo h(UPDATE_OWNER . '/' . UPDATE_REPO); ?></div></div>
          <div class="info-item"><div class="label">API 连通</div><div class="value" id="srcApiStatus">未测试</div></div>
          <div class="info-item"><div class="label">文件下载</div><div class="value" id="srcRawStatus">未测试</div></div>
          <div class="info-item"><div class="label">镜像加速</div><div class="value" id="srcMirrorStatus">未测试</div></div>
        </div>
      </div>

      <div class="card">
        <h2><span class="icon">💾</span> 配置备份</h2>
        <div class="btn-group">
          <a href="?download=config" class="btn primary">⬇️ 下载备份</a>
        </div>
        <form method="post" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="restore_config">
          <input type="file" name="cfg_file" accept=".yaml,.yml" style="flex:1;font-size:13px;min-width:200px;">
          <button type="submit" class="btn orange small" onclick="return confirm('恢复配置会用上传文件覆盖当前 config.yaml，确定？');">⬆️ 恢复配置</button>
        </form>
        <p class="tip" style="margin:10px 0 0;">下载备份可把配置导出到本地保存；恢复前会自动备份当前文件，恢复后需重启容器生效。</p>
      </div>
    </div>

    <!-- ===== 任务 ===== -->
    <div class="page" id="panel-tasks">
      <div class="page-head">
        <div class="page-title"><span class="pt-icon">🚀</span>任务</div>
        <div class="page-desc">在容器内后台执行，启动后可切到「日志」页查看进度</div>
      </div>

      <div class="card">
        <h2><span class="icon">🎮</span> 执行任务</h2>
        <div class="task-grid">
          <?php foreach ($TASKS as $key => $t): ?>
          <div class="task-card">
            <div class="icon"><?php echo h($t['icon']); ?></div>
            <div class="label"><?php echo h($t['label']); ?></div>
            <div class="desc"><?php echo h($t['desc']); ?></div>
            <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo h($key); ?>"><button type="submit" class="btn primary small">执行</button></form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h2><span class="icon">🔧</span> 容器操作</h2>
        <div class="btn-group">
          <?php foreach ($OPS as $key => $op): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo h($op['confirm']); ?>');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo h($key); ?>"><button type="submit" class="btn <?php echo $key === 'restart' ? 'orange' : 'red'; ?>"><?php echo h($op['icon'] . ' ' . $op['label']); ?></button></form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ===== 配置 ===== -->
    <div class="page" id="panel-config">
      <div class="page-head">
        <div class="page-title"><span class="pt-icon">⚙️</span>配置</div>
        <div class="page-desc">图形化或文本方式编辑 config.yaml</div>
      </div>

      <div class="card">
        <h2><span class="icon">⚙️</span> 配置管理</h2>

        <div class="cfg-tabs">
          <button class="cfg-tab active" onclick="switchCfgTab('form')">📋 图形化编辑</button>
          <button class="cfg-tab" onclick="switchCfgTab('text')">📝 文本编辑</button>
        </div>

        <!-- 图形化表单 -->
        <div class="cfg-panel active" id="cfgPanel-form">
          <form method="post" id="configForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_config_form">

            <?php foreach ($CONFIG_GROUPS as $gKey => $g): ?>
            <div class="cfg-group">
              <h3><?php echo h($g['icon'] . ' ' . $g['title']); ?></h3>
              <?php foreach ($g['fields'] as $fKey => $f):
                  $curVal = $cfgVals[$fKey] ?? '';
                  $curValClean = trim($curVal, "\"'");
              ?>
              <div class="cfg-row">
                <span class="cfg-label"><?php echo h($f['label']); ?><?php if (!empty($f['tip'])): ?><span class="cfg-tip"><?php echo h($f['tip']); ?></span><?php endif; ?></span>
                <div class="cfg-input">
                  <?php if ($f['type'] === 'bool'): ?>
                  <label class="switch">
                    <input type="checkbox" name="cfg_<?php echo h($fKey); ?>" value="1" <?php echo ($curVal === 'true') ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                  </label>

                  <?php elseif ($f['type'] === 'select'): ?>
                  <select name="cfg_<?php echo h($fKey); ?>" class="cfg-select">
                    <?php foreach ($f['options'] as $ov => $ol): ?>
                    <option value="<?php echo h($ov); ?>" <?php echo ($curValClean === $ov) ? 'selected' : ''; ?>><?php echo h($ol); ?></option>
                    <?php endforeach; ?>
                  </select>

                  <?php elseif ($f['type'] === 'int'): ?>
                  <input type="number" name="cfg_<?php echo h($fKey); ?>" value="<?php echo h($curValClean); ?>" class="cfg-input-num">

                  <?php elseif ($f['type'] === 'password'): ?>
                  <input type="password" name="cfg_<?php echo h($fKey); ?>" placeholder="留空则不修改" class="cfg-input-text cfg-pass" autocomplete="off">

                  <?php elseif ($f['type'] === 'textarea'): ?>
                  <textarea name="cfg_<?php echo h($fKey); ?>" class="cfg-textarea" rows="3" placeholder="<?php echo h($f['placeholder'] ?? ''); ?>"><?php echo h($curValClean); ?></textarea>

                  <?php else: ?>
                  <input type="text" name="cfg_<?php echo h($fKey); ?>" value="<?php echo h($curValClean); ?>" class="cfg-input-text" placeholder="<?php echo h($f['placeholder'] ?? ''); ?>">
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div class="btn-group" style="margin-top:16px;">
              <button type="submit" class="btn green">💾 保存配置</button>
              <button type="button" class="btn orange" onclick="restartAfterSave()">💾 保存并重启容器</button>
            </div>
          </form>
        </div>

        <!-- 文本编辑 -->
        <div class="cfg-panel" id="cfgPanel-text">
          <form method="post" id="configTextForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_config_text">
            <p class="tip" style="margin-bottom:10px;">直接编辑完整 YAML，保存前会自动备份。修改后需重启容器生效。</p>
            <textarea name="yaml_content" class="yaml-editor" id="yamlEditor" spellcheck="false"><?php $raw = config_read_raw(); echo $raw !== null ? h($raw) : ''; ?></textarea>
            <div class="btn-group" style="margin-top:12px;">
              <button type="submit" class="btn green">💾 保存</button>
              <button type="button" class="btn" style="background:#94a3b8;" onclick="reloadYaml()">🔄 重新加载</button>
            </div>
          </form>
        </div>

        <div class="tip" style="margin-top:16px;">
          ⚠️ 保存配置后需<span style="font-weight:600;">重启容器</span>才生效。
          如遇写入权限错误，请执行：<code>chmod 666 <?php echo h(instance_config()); ?></code>
        </div>
      </div>
    </div>

    <!-- ===== 日志 ===== -->
    <div class="page" id="panel-log">
      <div class="page-head">
        <div class="page-title"><span class="pt-icon">📝</span>日志</div>
        <div class="page-desc">关键词搜索 · 级别/时间过滤 · 一键导出</div>
      </div>

      <div class="card">
        <div class="log-header">
          <h2 style="margin-bottom:0;"><span class="icon">📝</span> 运行日志</h2>
          <div class="auto-refresh">
            <label><input type="checkbox" id="autoRefresh" checked onchange="toggleAutoRefresh()"> 自动刷新</label>
            <select id="refreshInterval" onchange="updateRefreshInterval()" style="padding:4px 8px;border:1px solid var(--border);border-radius:8px;font-size:12px;background:var(--card-solid);color:var(--text);">
              <option value="3000">3 秒</option>
              <option value="5000" selected>5 秒</option>
              <option value="10000">10 秒</option>
              <option value="30000">30 秒</option>
            </select>
            <button class="btn small gray" onclick="refreshLog()">立即刷新</button>
          </div>
        </div>
        <div class="log-filter">
          <input type="text" id="logKeyword" placeholder="🔍 搜索关键词（回车立即过滤）" onkeyup="logKeywordKeyup(event)">
          <select id="logLevel" onchange="refreshLog()">
            <option value="">全部级别</option>
            <option value="DEBUG">DEBUG</option>
            <option value="INFO">INFO</option>
            <option value="WARNING">WARNING</option>
            <option value="ERROR">ERROR</option>
          </select>
          <select id="logHours" onchange="refreshLog()">
            <option value="0">全部时间</option>
            <option value="1">最近 1 小时</option>
            <option value="6">最近 6 小时</option>
            <option value="24">最近 24 小时</option>
          </select>
          <select id="logFile" onchange="refreshLog()"><option value="">加载中…</option></select>
          <button class="btn small primary" id="exportLogBtn">⬇ 导出</button>
          <button class="btn small gray" onclick="resetLogFilter()">↻ 重置</button>
        </div>
        <div class="log-count" id="logCount" style="margin-bottom:8px;"></div>
        <div class="codebox" id="logBox" style="max-height:600px;"><div style="color:var(--muted);">日志加载中…</div></div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /layout -->

<!-- ===== 实例管理弹窗 ===== -->
<div class="modal-overlay" id="instModal" style="display:none;" onclick="if(event.target===this)closeInstModal()">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">📦 实例管理</div>
      <button type="button" class="icon-btn" onclick="closeInstModal()" style="font-size:18px;">✕</button>
    </div>
    <div class="modal-body">
      <div class="tip" style="margin-bottom:12px;">每个实例对应一个已部署的三月七小助手 Docker 容器。切换实例后，任务、配置、日志、备份均作用于当前实例。</div>

      <div class="inst-list">
        <?php foreach (instances_load() as $it): $cur = instance_current(); ?>
        <div class="inst-item">
          <div class="inst-info">
            <div class="inst-name"><?php echo h($it['name']); ?> <?php echo !empty($it['default']) ? '<span class="badge-default">默认</span>' : ''; ?> <?php echo $it['id'] === $cur['id'] ? '<span class="badge-cur">当前</span>' : ''; ?></div>
            <div class="inst-meta">容器：<?php echo h($it['container']); ?> · 目录：<?php echo h($it['dir']); ?></div>
          </div>
          <div class="inst-actions">
            <button type="button" class="btn small gray" onclick="editInst(<?php echo h(json_encode($it, JSON_UNESCAPED_UNICODE)); ?>)">编辑</button>
            <button type="button" class="btn small red" onclick="askDeleteInst(<?php echo h(json_encode($it, JSON_UNESCAPED_UNICODE)); ?>)">删除</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 新增/编辑表单 -->
      <div class="inst-form-wrap" id="instFormWrap" style="display:none;">
        <form method="post" class="inst-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="instance_save">
          <input type="hidden" name="inst_id" id="inst_id" value="">
          <div class="inst-form-row"><label>名称</label><input type="text" name="inst_name" id="inst_name" class="cfg-input-text" placeholder="如：主号 / 小号"></div>
          <div class="inst-form-row"><label>容器名</label><input type="text" name="inst_container" id="inst_container" class="cfg-input-text" placeholder="如：m7a（docker ps 里的 NAME）"></div>
          <div class="inst-form-row"><label>项目目录</label><input type="text" name="inst_dir" id="inst_dir" class="cfg-input-text" placeholder="如：/home/march7thassistant"></div>
          <div class="inst-form-row"><label><input type="checkbox" name="inst_default" id="inst_default" value="1"> 设为默认实例</label></div>
          <div class="btn-group">
            <button type="submit" class="btn green">💾 保存实例</button>
            <button type="button" class="btn gray" onclick="hideInstForm()">取消</button>
          </div>
        </form>
      </div>

      <button type="button" class="btn primary small" onclick="newInst()" style="margin-top:12px;">➕ 新增实例</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
/* ===== 主题（默认星铁粉蓝 三月七）===== */
var THEMES = [
  { name: 'march7', icon: '🎀', label: '星铁粉蓝' },
  { name: 'light',  icon: '☀️', label: '亮色' },
  { name: 'dark',   icon: '🌙', label: '深色' }
];
function themeIdx(name) {
  for (var i = 0; i < THEMES.length; i++) if (THEMES[i].name === name) return i;
  return 0;
}
function applyTheme(name) {
  var idx = themeIdx(name), d = document.documentElement, btn = document.getElementById('themeBtn');
  d.setAttribute('data-theme', THEMES[idx].name);
  if (btn) {
    btn.textContent = THEMES[idx].icon;
    btn.title = '切换主题：' + THEMES[idx].label + ' → ' + THEMES[(idx + 1) % THEMES.length].label;
  }
  try { localStorage.setItem('m7a_theme', THEMES[idx].name); } catch(e) {}
}
function toggleTheme() {
  var cur = document.documentElement.getAttribute('data-theme') || 'march7';
  applyTheme(THEMES[(themeIdx(cur) + 1) % THEMES.length].name);
}
(function() {
  var t = null;
  try { t = localStorage.getItem('m7a_theme'); } catch(e) {}
  if (t !== 'light' && t !== 'dark' && t !== 'march7') t = 'march7';
  applyTheme(t);
})();

/* ===== 侧边栏（移动端抽屉） ===== */
function openSidebar() {
  var s = document.getElementById('sidebar'), o = document.getElementById('sidebarOverlay');
  if (s) s.classList.add('open');
  if (o) o.classList.add('show');
}
function closeSidebar() {
  var s = document.getElementById('sidebar'), o = document.getElementById('sidebarOverlay');
  if (s) s.classList.remove('open');
  if (o) o.classList.remove('show');
}

/* ===== 页面切换 ===== */
function switchTab(name) {
  document.querySelectorAll('.page').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.nav-item').forEach(function(el) { el.classList.toggle('active', el.dataset.tab === name); });
  var panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');
  try { localStorage.setItem('m7a_tab', name); } catch(e) {}
  if (name === 'log') refreshLog();
  if (name === 'overview') refreshStatus();
  closeSidebar();
}
// Restore tab
(function() {
  try {
    var t = localStorage.getItem('m7a_tab');
    if (t) switchTab(t);
  } catch(e) {}
})();

// 延迟检查更新（等页面渲染完）
setTimeout(checkUpdate, 1500);

/* ===== 配置子页切换 ===== */
function switchCfgTab(name) {
  document.querySelectorAll('.cfg-panel').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.cfg-tab').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('cfgPanel-' + name).classList.add('active');
  event.target.classList.add('active');
}

/* ===== 自动更新检查 ===== */
var _updHiddenAt = 0;
try { _updHiddenAt = parseInt(localStorage.getItem('m7a_upd_hide') || '0', 10); } catch(e) {}

function checkUpdate() {
  if (Date.now() - _updHiddenAt < 3600000) return;
  fetch('?ajax=check_update').then(function(r){ return r.json(); }).then(function(d){
    if (d && d.ok && d.has_update) {
      document.getElementById('updLatest').textContent = d.latest;
      document.getElementById('updCurrent').textContent = d.current;
      var note = d.note || '';
      if (note.length > 300) note = note.substring(0, 300) + '…';
      document.getElementById('updNote').textContent = note;
      document.getElementById('updNote').style.display = note ? '' : 'none';
      document.getElementById('updateBanner').style.display = '';
    }
  }).catch(function(){});
}

function doUpdate() {
  var btn = document.getElementById('updBtn');
  if (btn.disabled) return;
  btn.disabled = true; btn.textContent = '更新中…';
  var fd = new FormData();
  fd.append('action', 'do_update');
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        location.reload();
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '更新失败'));
        btn.disabled = false; btn.textContent = '一键更新';
      }
    })
    .catch(function(){
      alert('❌ 网络错误，更新未完成');
      btn.disabled = false; btn.textContent = '一键更新';
    });
}

function testUpdateSource() {
  var apiEl = document.getElementById('srcApiStatus');
  var rawEl = document.getElementById('srcRawStatus');
  var mirEl = document.getElementById('srcMirrorStatus');
  apiEl.textContent = '测试中…'; rawEl.textContent = '测试中…'; mirEl.textContent = '测试中…';
  fetch('?ajax=test_update_source').then(function(r){ return r.json(); }).then(function(d){
    if (!d) { apiEl.textContent = '失败'; rawEl.textContent = '失败'; mirEl.textContent = '失败'; return; }
    if (d.api && d.api.state === 'ok_release')      apiEl.textContent = '✅ 已发版（HTTP ' + d.api.code + '）';
    else if (d.api && d.api.state === 'ok_no_release') apiEl.textContent = '✅ 连通，尚未发版（HTTP ' + d.api.code + '）';
    else apiEl.textContent = '❌ HTTP ' + (d.api ? d.api.code : '失败') + '（检查仓库名/发版）';
    rawEl.textContent = (d.raw && d.raw.ok) ? '✅ 可下载（HTTP ' + d.raw.code + '）' : '❌ HTTP ' + (d.raw ? d.raw.code : '失败') + '（官方源不通，将尝试镜像）';
    if (d.mirrors && d.mirrors.length > 1) {
      var okN = 0, list = [];
      d.mirrors.forEach(function(m, i){
        if (i === 0) return;
        list.push(m.name + (m.ok ? '✅' : '❌' + m.code));
        if (m.ok) okN++;
      });
      mirEl.textContent = okN > 0 ? '✅ ' + okN + ' 个可用（' + list.join(' ') + '）' : '❌ 全部不可用';
    } else {
      mirEl.textContent = '无';
    }
  }).catch(function(){ apiEl.textContent = '网络错误'; rawEl.textContent = '网络错误'; mirEl.textContent = '网络错误'; });
}

function hideUpdate() {
  document.getElementById('updateBanner').style.display = 'none';
  try { localStorage.setItem('m7a_upd_hide', Date.now()); } catch(e) {}
}

/* ===== AJAX 刷新 ===== */
var _logTimer = null;
var _statusTimer = null;
var _logKeywordTimer = null;

function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escapeRegExp(s) {
  return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
function renderLogLine(line, kw) {
  var esc = escapeHtml(line);
  if (kw) {
    var re = new RegExp(escapeRegExp(escapeHtml(kw)), 'gi');
    esc = esc.replace(re, '<span class="hl">$&</span>');
  }
  return esc;
}
function logKeywordKeyup(e) {
  if (e.key === 'Enter') { refreshLog(); return; }
  clearTimeout(_logKeywordTimer);
  _logKeywordTimer = setTimeout(refreshLog, 300);
}
function logFilterParams() {
  var kw = document.getElementById('logKeyword') ? document.getElementById('logKeyword').value.trim() : '';
  var lv = document.getElementById('logLevel') ? document.getElementById('logLevel').value : '';
  var hh = document.getElementById('logHours') ? document.getElementById('logHours').value : '0';
  var ff = document.getElementById('logFile') ? document.getElementById('logFile').value : '';
  return 'keyword=' + encodeURIComponent(kw) + '&level=' + encodeURIComponent(lv) + '&hours=' + encodeURIComponent(hh) + '&file=' + encodeURIComponent(ff);
}
function fillLogFiles(files, current) {
  var sel = document.getElementById('logFile');
  if (!sel) return;
  var html = '';
  for (var i = 0; i < files.length; i++) {
    html += '<option value="' + escapeHtml(files[i]) + '"' + (files[i] === current ? ' selected' : '') + '>' + escapeHtml(files[i]) + '</option>';
  }
  sel.innerHTML = html || '<option value="">（无日志文件）</option>';
}
function refreshLog() {
  fetch('?ajax=log&' + logFilterParams()).then(function(r) { return r.json(); }).then(function(d) {
    if (!d || !d.ok) return;
    fillLogFiles(d.files || [], d.file);
    var box = document.getElementById('logBox');
    var countEl = document.getElementById('logCount');
    if (typeof d.lines === 'string') {
      if (box) box.innerHTML = '<div style="color:var(--muted);">' + escapeHtml(d.lines) + '</div>';
      if (countEl) countEl.textContent = '';
      return;
    }
    var kw = document.getElementById('logKeyword') ? document.getElementById('logKeyword').value.trim() : '';
    var hasFilter = kw !== '' || (document.getElementById('logLevel') && document.getElementById('logLevel').value !== '') || (document.getElementById('logHours') && document.getElementById('logHours').value !== '0');
    var html = d.lines.map(function(line) { return renderLogLine(line, kw); }).join('\n');
    if (box) {
      var atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 40;
      box.innerHTML = html;
      if (!hasFilter && atBottom) box.scrollTop = box.scrollHeight;
    }
    if (countEl) countEl.textContent = '命中 ' + d.total + ' 条';
  }).catch(function() {});
}
function resetLogFilter() {
  if (document.getElementById('logKeyword')) document.getElementById('logKeyword').value = '';
  if (document.getElementById('logLevel')) document.getElementById('logLevel').value = '';
  if (document.getElementById('logHours')) document.getElementById('logHours').value = '0';
  refreshLog();
}
function initLogExport() {
  var btn = document.getElementById('exportLogBtn');
  if (btn) btn.onclick = function() {
    var url = '?export_log=1&' + logFilterParams();
    window.open(url, '_blank');
  };
}

function refreshStatus() {
  fetch('?ajax=status').then(function(r) { return r.text(); }).then(function(t) {
    var box = document.getElementById('statusBox');
    if (box) box.textContent = t;
  }).catch(function() {});
  fetch('?ajax=running').then(function(r) { return r.json(); }).then(function(d) {
    document.querySelectorAll('.status-badge').forEach(function(badge) {
      badge.className = 'status-badge ' + (d.running ? 'running' : 'stopped');
      var txt = badge.querySelector('.status-text');
      if (txt) txt.textContent = d.running ? '运行中' : '已停止';
    });
  }).catch(function() {});
}

function toggleAutoRefresh() {
  var on = document.getElementById('autoRefresh').checked;
  if (on) startAutoRefresh(); else stopAutoRefresh();
}
function updateRefreshInterval() {
  stopAutoRefresh();
  if (document.getElementById('autoRefresh').checked) startAutoRefresh();
}
function startAutoRefresh() {
  var ms = parseInt(document.getElementById('refreshInterval').value) || 5000;
  stopAutoRefresh();
  _logTimer = setInterval(refreshLog, ms);
  _statusTimer = setInterval(refreshStatus, 10000);
}
function stopAutoRefresh() {
  if (_logTimer) { clearInterval(_logTimer); _logTimer = null; }
  if (_statusTimer) { clearInterval(_statusTimer); _statusTimer = null; }
}

// Init
refreshStatus();
refreshLog();
startAutoRefresh();
initLogExport();

/* ===== 保存并重启 ===== */
function restartAfterSave() {
  var form = document.getElementById('configForm');
  var input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'then_restart';
  input.value = '1';
  form.appendChild(input);
  form.submit();
}

function reloadYaml() {
  fetch('?ajax=config_raw').then(function(r) { return r.text(); }).then(function(t) {
    document.getElementById('yamlEditor').value = t;
  }).catch(function() {});
}

/* ===== 实例管理 ===== */
function openInstModal() {
  document.getElementById('instModal').style.display = 'flex';
}
function closeInstModal() {
  document.getElementById('instModal').style.display = 'none';
}
function newInst() {
  document.getElementById('inst_id').value = '';
  document.getElementById('inst_name').value = '';
  document.getElementById('inst_container').value = '';
  document.getElementById('inst_dir').value = '';
  document.getElementById('inst_default').checked = false;
  document.getElementById('instFormWrap').style.display = '';
}
function hideInstForm() {
  document.getElementById('instFormWrap').style.display = 'none';
}
function editInst(it) {
  document.getElementById('inst_id').value = it.id || '';
  document.getElementById('inst_name').value = it.name || '';
  document.getElementById('inst_container').value = it.container || '';
  document.getElementById('inst_dir').value = it.dir || '';
  document.getElementById('inst_default').checked = !!(it.default);
  document.getElementById('instFormWrap').style.display = '';
}
function askDeleteInst(it) {
  var name = it.name || '';
  var typed = prompt('删除实例「' + name + '」？\n此操作不可撤销，且会从面板移除该实例配置。\n请输入实例名称以确认：', '');
  if (typed === null) return;
  if (typed.trim() !== name) { alert('确认失败：输入的名称与实例名不一致'); return; }
  var form = document.createElement('form');
  form.method = 'post';
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) { var c = document.createElement('input'); c.type = 'hidden'; c.name = 'csrf'; c.value = csrf.value; form.appendChild(c); }
  var a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'instance_delete'; form.appendChild(a);
  var b = document.createElement('input'); b.type = 'hidden'; b.name = 'inst_id'; b.value = it.id || ''; form.appendChild(b);
  var d = document.createElement('input'); d.type = 'hidden'; d.name = 'inst_confirm'; d.value = name; form.appendChild(d);
  document.body.appendChild(form); form.submit();
}

/* ===== 云游戏画面直播预览（v1.5） ===== */
var PV = {
  ws:null, res:'720p', canvas:null, ctx:null, dot:null, statusEl:null,
  fpsEl:null, badge:null, placeholder:null, guide:null, guideReason:null,
  guideNginx:null, guideFirewall:null, fps:0, fpsTimer:null,
  waitTimer:null, reconnectTimer:null, failCount:0, state:'init'
};
function pvInit() {
  PV.canvas = document.getElementById('liveCanvas');
  if (!PV.canvas) return;
  PV.ctx = PV.canvas.getContext('2d');
  PV.dot = document.getElementById('liveDot');
  PV.statusEl = document.getElementById('liveStatus');
  PV.fpsEl = document.getElementById('liveFps');
  PV.badge = document.getElementById('liveBadge');
  PV.placeholder = document.getElementById('livePlaceholder');
  PV.guide = document.getElementById('liveGuide');
  PV.guideReason = document.getElementById('guideReason');
  PV.guideNginx = document.getElementById('guideNginx');
  PV.guideFirewall = document.getElementById('guideFirewall');
  PV.fpsTimer = setInterval(function(){ PV.fpsEl.innerHTML = '<b>' + PV.fps + '</b> fps'; PV.fps = 0; }, 1000);
  pvConnect();
}
function pvWsUrl() {
  var proto = (location.protocol === 'https:') ? 'wss://' : 'ws://';
  return proto + location.host + '/m7a-preview/ws?res=' + PV.res;
}
function pvSetState(state, text) {
  PV.state = state;
  var dotColor = { init:'gray', connecting:'yellow', waiting:'yellow', live:'green', error:'red' }[state] || 'gray';
  var badgeText = { init:'未连接', connecting:'连接中', waiting:'等待画面', live:'直播中', error:'已断开' }[state] || '未连接';
  var label = { init:'初始化…', connecting:'连接中…', waiting:'等待云游戏画面', live:'直播中', error:'连接断开，3 秒后重连' }[state] || '';
  PV.dot.className = 'live-dot ' + dotColor;
  PV.badge.className = 'live-badge ' + dotColor;
  PV.badge.textContent = badgeText;
  PV.statusEl.textContent = text || label;
  PV.placeholder.style.display = (state === 'live') ? 'none' : 'flex';
}
function pvConnect() {
  if (PV.ws) { try { PV.ws.onclose = null; PV.ws.close(); } catch(e){} PV.ws = null; }
  pvSetState('connecting');
  try {
    PV.ws = new WebSocket(pvWsUrl());
  } catch(e) {
    pvFail('浏览器无法创建 WebSocket 连接：' + e.message);
    return;
  }
  PV.ws.binaryType = 'arraybuffer';
  PV.ws.onopen = function() {
    PV.failCount = 0;
    pvSetState('waiting');
    PV.lastFrameAt = Date.now();
    if (PV.waitTimer) clearTimeout(PV.waitTimer);
    PV.waitTimer = setTimeout(function(){
      if (PV.state === 'waiting') PV.statusEl.textContent = '等待云游戏画面（小助手空闲时无画面）';
    }, 4000);
  };
  PV.ws.onmessage = function(ev) {
    if (!(ev.data instanceof ArrayBuffer) && !(ev.data instanceof Blob)) return;
    try {
      createImageBitmap(new Blob([ev.data], {type:'image/jpeg'})).then(function(bmp){
        PV.canvas.width = bmp.width; PV.canvas.height = bmp.height;
        PV.ctx.drawImage(bmp, 0, 0);
        try { bmp.close(); } catch(e){}
        PV.fps++;
        PV.lastFrameAt = Date.now();
        if (PV.state !== 'live') pvSetState('live');
      }).catch(function(){});
    } catch(e) {}
  };
  PV.ws.onerror = function() { try { PV.ws.close(); } catch(e){} };
  PV.ws.onclose = function() {
    if (PV.waitTimer) { clearTimeout(PV.waitTimer); PV.waitTimer = null; }
    PV.failCount++;
    pvSetState('error');
    if (PV.failCount >= 3) pvShowGuide();
    pvReconnect();
  };
}
function pvReconnect() {
  if (PV.reconnectTimer) clearTimeout(PV.reconnectTimer);
  PV.reconnectTimer = setTimeout(function(){ pvConnect(); }, 3000);
}
function pvFail(reason) {
  PV.failCount++;
  pvSetState('error');
  PV.statusEl.textContent = '连接失败';
  if (PV.failCount >= 2) pvShowGuide(reason);
}
function pvShowGuide(reason) {
  if (!PV.guide) return;
  var url = pvWsUrl();
  PV.guideReason.textContent = '当前连接地址：' + url + (reason ? '（' + reason + '）' : '');
  PV.guideNginx.textContent =
    '# 1. 在站点 Nginx 配置的 server { } 内添加反代（WebSocket 版）\n' +
    'location /m7a-preview/ {\n' +
    '    proxy_pass http://容器IP:9223/;\n' +
    '    proxy_http_version 1.1;\n' +
    '    proxy_set_header Upgrade $http_upgrade;\n' +
    '    proxy_set_header Connection "upgrade";\n' +
    '    proxy_buffering off;\n' +
    '    proxy_read_timeout 3600s;\n' +
    '}';
  PV.guideFirewall.textContent = '2. 防火墙放行面板端口（如 80/443），并确认容器内 9223 端口已启动转发服务；用 docker inspect 容器名 | grep IPAddress 查容器 IP 后，把上方「容器IP」替换为实际值。';
  PV.guide.style.display = '';
}
function setRes(r) {
  if (PV.res === r) return;
  PV.res = r;
  var btns = document.querySelectorAll('.res-btn');
  for (var i = 0; i < btns.length; i++) {
    btns[i].className = 'res-btn' + (btns[i].getAttribute('data-res') === r ? ' active' : '');
  }
  pvConnect();
}
pvInit();
</script>
</body>
</html>
