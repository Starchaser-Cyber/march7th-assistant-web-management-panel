<?php
/**
 * March7th Assistant 网页管理面板 v1.1
 * 现代化 UI + 图形化配置编辑 + 实时状态 + 配置备份恢复
 * 纯原生 PHP 单文件 · 宝塔友好
 */
declare(strict_types=1);
session_start();

define('PROJECT_DIR', '/home/march7thassistant');
define('CONTAINER', 'm7a');
define('CONFIG_PATH', PROJECT_DIR . '/config.yaml');
define('PASS_FILE', __DIR__ . '/.panel_pass.php');
define('SKEY', 'm7a_panel_auth');
define('CSRF_KEY', 'm7a_panel_csrf');

/* ===== 自动更新配置 =====
 * 发版流程：改 PANEL_VERSION → git push → 在 Gitea/GitHub 打 tag（如 v1.0）并创建 Release
 * UPDATE_TYPE: gitea / github
 */
define('PANEL_VERSION', '1.1');              // 面板当前版本号（发版时手动修改）
define('UPDATE_ENABLED', true);              // 是否启用自动检查更新
define('UPDATE_TYPE', 'github');              // 更新源类型：gitea 或 github
define('UPDATE_HOST', 'https://github.com');  // Gitea 实例地址（UPDATE_TYPE=gitea 时生效）
define('UPDATE_OWNER', 'huangsongping183');          // 仓库所有者
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

function run_cmd($cmd) {
    $out = array(); $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return array('code' => $code, 'out' => implode("\n", $out));
}
function compose($args) {
    return run_cmd('cd ' . escapeshellarg(PROJECT_DIR) . ' && docker compose ' . $args);
}
function task_start($sub) {
    return run_cmd('docker exec -d ' . escapeshellarg(CONTAINER) . ' python main.py ' . escapeshellarg($sub));
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
    $files = glob(PROJECT_DIR . '/logs/*.log');
    if (!$files) return null;
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    return $files[0];
}
function tail_file($path, $lines = 200) {
    $out = array(); $code = 0;
    exec('tail -n ' . (int)$lines . ' ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    return implode("\n", $out);
}
function container_status() {
    $r = compose('ps');
    return $r['out'];
}
function container_is_running() {
    $r = run_cmd('docker inspect -f "{{.State.Running}}" ' . escapeshellarg(CONTAINER) . ' 2>&1');
    return trim($r['out']) === 'true';
}
function config_read_raw($maxBytes = 65536) {
    if (!is_file(CONFIG_PATH)) return null;
    $fp = fopen(CONFIG_PATH, 'rb');
    if ($fp === false) return null;
    $data = fread($fp, $maxBytes);
    fclose($fp);
    return $data;
}
function yaml_read_simple() {
    $vals = array();
    if (!is_file(CONFIG_PATH)) return $vals;
    $lines = file(CONFIG_PATH);
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
    if (!is_file(CONFIG_PATH)) return array('ok' => false, 'msg' => 'config.yaml 不存在');
    $lines = file(CONFIG_PATH);
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
    $r = @file_put_contents(CONFIG_PATH, implode('', $out));
    if ($r === false) return array('ok' => false, 'msg' => '写入失败，请检查文件权限：chmod 666 ' . CONFIG_PATH);
    return array('ok' => true, 'msg' => '已更新 ' . count($changed) . ' 项配置', 'changed' => $changed);
}
function config_save_text($content) {
    if (strpos($content, 'locales:') === false && strpos($content, 'power_enable:') === false) {
        return array('ok' => false, 'msg' => '内容异常，未找到有效配置键，已取消保存');
    }
    $r = @file_put_contents(CONFIG_PATH, $content);
    if ($r === false) return array('ok' => false, 'msg' => '写入失败，请检查文件权限：chmod 666 ' . CONFIG_PATH);
    return array('ok' => true, 'msg' => '配置已保存');
}
function config_backup() {
    if (!is_file(CONFIG_PATH)) return false;
    $bak = dirname(CONFIG_PATH) . '/config.yaml.bak.' . date('YmdHis');
    if (@copy(CONFIG_PATH, $bak)) return $bak;
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


function test_update_source() {
    $api = update_api_url();
    $raw = update_raw_url();
    $ar  = http_get($api, 8);
    $rr  = http_get($raw, 8);

    $api_state = 'fail';
    if ($ar['code'] === 200 && stripos((string)$ar['body'], 'tag_name') !== false) {
        $api_state = 'ok_release';
    } elseif ($ar['code'] === 200) {
        $api_state = 'ok_no_release';
    }

    $raw_ok = ($rr['code'] === 200 && stripos(ltrim((string)$rr['body']), '<?php') === 0);

    return array(
        'ok'  => ($api_state !== 'fail') && $raw_ok,
        'api' => array('url' => $api, 'code' => $ar['code'], 'state' => $api_state),
        'raw' => array('url' => $raw, 'code' => $rr['code'], 'ok' => $raw_ok),
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
    $r = http_get(update_raw_url(), 30);
    if ($r['code'] !== 200 || empty($r['body'])) {
        return array('ok' => false, 'msg' => '下载新版失败（HTTP ' . $r['code'] . '）');
    }
    $content = $r['body'];
    if (stripos(ltrim($content), '<?php') !== 0) {
        return array('ok' => false, 'msg' => '下载内容校验失败（不是有效的 PHP 文件），已保留原文件');
    }
    $bak = __DIR__ . '/index.php.bak.' . date('YmdHis');
    if (!@copy(__FILE__, $bak)) {
        return array('ok' => false, 'msg' => '备份当前文件失败，已中止更新');
    }
    if (@file_put_contents(__FILE__, $content) === false) {
        @copy($bak, __FILE__);
        return array('ok' => false, 'msg' => '写入新版本失败，已回滚到备份');
    }
    return array('ok' => true, 'msg' => '更新完成，页面即将刷新', 'bak' => basename($bak));
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
        header('Content-Type: text/plain; charset=utf-8');
        $lp = latest_log_path();
        echo $lp ? tail_file($lp, 300) : '(暂无日志文件，任务运行后会生成)';
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
    if (!is_file(CONFIG_PATH)) {
        header('Location: index.php');
        exit;
    }
    header('Content-Type: application/octet-stream; charset=utf-8');
    header('Content-Disposition: attachment; filename="config.yaml.bak.' . date('YmdHis') . '.yaml"');
    header('Content-Length: ' . (string) filesize(CONFIG_PATH));
    readfile(CONFIG_PATH);
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
                        $r = @file_put_contents(CONFIG_PATH, $content);
                        if ($r === false) {
                            $err = '写入失败，请检查文件权限：chmod 666 ' . CONFIG_PATH;
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
<title>March7th 管理面板</title>
<style>
:root {
  --bg: #f0f2f5;
  --card: #ffffff;
  --card2: #f8fafc;
  --text: #1e293b;
  --muted: #64748b;
  --primary: #6366f1;
  --primary-light: #a5b4fc;
  --primary-bg: #eef2ff;
  --green: #10b981;
  --green-bg: #ecfdf5;
  --red: #ef4444;
  --red-bg: #fef2f2;
  --orange: #f59e0b;
  --orange-bg: #fffbeb;
  --border: #e2e8f0;
  --shadow: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
  --radius: 12px;
}
[data-theme="dark"] {
  --bg: #0f172a;
  --card: #1e293b;
  --card2: #334155;
  --text: #e2e8f0;
  --muted: #94a3b8;
  --primary: #818cf8;
  --primary-light: #6366f1;
  --primary-bg: #312e81;
  --green-bg: #064e3b;
  --red-bg: #7f1d1d;
  --orange-bg: #78350f;
  --border: #475569;
  --shadow: 0 1px 3px rgba(0,0,0,.3);
}
/* ===== 第二皮肤：星铁粉紫（三月七主题） ===== */
[data-theme="march7"] {
  --bg: #fdf2f8;
  --card: rgba(255,255,255,.72);
  --card2: rgba(255,255,255,.55);
  --text: #581c87;
  --muted: #9d6b9d;
  --primary: #ec4899;
  --primary-light: #f9a8d4;
  --primary-bg: #fce7f3;
  --green: #10b981;
  --green-bg: #ecfdf5;
  --red: #ef4444;
  --red-bg: #fef2f2;
  --orange: #f59e0b;
  --orange-bg: #fffbeb;
  --border: rgba(236,72,153,.18);
  --shadow: 0 8px 32px rgba(236,72,153,.12);
}
html[data-theme="march7"] body {
  background:
    radial-gradient(ellipse at 15% 0%, rgba(236,72,153,.10), transparent 50%),
    radial-gradient(ellipse at 85% 100%, rgba(168,85,247,.14), transparent 50%),
    linear-gradient(160deg, #fdf2f8 0%, #f5f3ff 50%, #fdf2f8 100%);
  background-attachment: fixed;
}
html[data-theme="march7"] .header { background:linear-gradient(135deg,#ec4899,#a855f7); box-shadow:0 2px 16px rgba(236,72,153,.35); }
html[data-theme="march7"] .card { backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); }
html[data-theme="march7"] .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
a { color:var(--primary); text-decoration:none; }

/* Header */
.header { background:linear-gradient(135deg,var(--primary),#8b5cf6); color:#fff; padding:16px 24px; position:sticky; top:0; z-index:100; box-shadow:0 2px 8px rgba(99,102,241,.3); }
.header-inner { max-width:1100px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
.header h1 { font-size:18px; font-weight:700; }
.header .sub { font-size:12px; opacity:.8; margin-top:2px; }
.header-actions { display:flex; align-items:center; gap:10px; }
.status-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; background:rgba(255,255,255,.2); }
.status-badge .dot { width:8px; height:8px; border-radius:50%; background:#fff; }
.status-badge.running .dot { background:#4ade80; animation:pulse 2s infinite; }
.status-badge.stopped .dot { background:#f87171; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.theme-btn { background:rgba(255,255,255,.2); border:none; color:#fff; padding:6px 10px; border-radius:8px; cursor:pointer; font-size:14px; }
.theme-btn:hover { background:rgba(255,255,255,.3); }

/* 更新提醒条 */
.update-banner { position:sticky; top:64px; z-index:99; max-width:1100px; margin:12px auto 0; padding:0 24px; }
.update-banner .update-inner { display:flex; align-items:center; gap:14px; background:linear-gradient(135deg,#ec4899,#a855f7); color:#fff; border-radius:12px; padding:12px 18px; box-shadow:0 4px 16px rgba(168,85,247,.3); flex-wrap:wrap; }
.update-banner .update-icon { font-size:24px; }
.update-banner .update-info { flex:1; min-width:200px; }
.update-banner .update-title { font-weight:700; font-size:14px; }
.update-banner .update-note { font-size:12px; opacity:.9; margin-top:3px; white-space:pre-wrap; max-height:72px; overflow-y:auto; }
.update-banner .update-actions { display:flex; gap:8px; align-items:center; }
.update-banner .btn.small { padding:6px 14px; font-size:12px; }
.update-banner .btn.gray { background:rgba(255,255,255,.25); color:#fff; border:none; }
.update-banner .btn.gray:hover { background:rgba(255,255,255,.35); }
.logout-btn { background:rgba(255,255,255,.15); border:none; color:#fff; padding:6px 14px; border-radius:8px; cursor:pointer; font-size:13px; }
.logout-btn:hover { background:rgba(255,255,255,.25); }

/* Tabs */
.tabs { max-width:1100px; margin:0 auto; display:flex; gap:0; padding:16px 24px 0; }
.tab-btn { padding:10px 20px; border:none; background:transparent; color:var(--muted); font-size:14px; font-weight:500; cursor:pointer; border-bottom:2px solid transparent; transition:all .15s; }
.tab-btn:hover { color:var(--text); }
.tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }

/* Main */
.main { max-width:1100px; margin:0 auto; padding:20px 24px 40px; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* Card */
.card { background:var(--card); border-radius:var(--radius); padding:20px 24px; margin-bottom:16px; box-shadow:var(--shadow); border:1px solid var(--border); }
.card h2 { font-size:15px; font-weight:600; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.card h2 .icon { font-size:18px; }

/* Messages */
.msg { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; display:flex; align-items:center; gap:8px; }
.msg.ok { background:var(--green-bg); color:var(--green); border:1px solid var(--green); }
.msg.err { background:var(--red-bg); color:var(--red); border:1px solid var(--red); }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:none; border-radius:10px; font-size:14px; font-weight:500; cursor:pointer; transition:all .15s; color:#fff; }
.btn:hover { opacity:.9; transform:translateY(-1px); }
.btn.primary { background:var(--primary); }
.btn.green { background:var(--green); }
.btn.orange { background:var(--orange); }
.btn.red { background:var(--red); }
.btn.gray { background:#64748b; }
.btn.small { padding:7px 14px; font-size:13px; }
.btn-group { display:flex; flex-wrap:wrap; gap:8px; }

/* Code box */
.codebox { background:var(--card2); border:1px solid var(--border); border-radius:8px; padding:14px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12.5px; line-height:1.7; white-space:pre-wrap; word-break:break-all; max-height:440px; overflow:auto; color:var(--text); }

/* Task cards */
.task-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; }
.task-card { background:var(--card2); border:1px solid var(--border); border-radius:10px; padding:14px; text-align:center; transition:all .15s; }
.task-card:hover { border-color:var(--primary); box-shadow:0 2px 8px rgba(99,102,241,.15); }
.task-card .icon { font-size:28px; margin-bottom:6px; }
.task-card .label { font-size:14px; font-weight:600; margin-bottom:4px; }
.task-card .desc { font-size:11px; color:var(--muted); line-height:1.4; }
.task-card form { margin-top:10px; }
.task-card .btn { width:100%; justify-content:center; }

/* Config form */
.cfg-group { margin-bottom:20px; }
.cfg-group h3 { font-size:14px; font-weight:600; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:6px; }
.cfg-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; gap:12px; }
.cfg-row + .cfg-row { border-top:1px solid var(--border); }
.cfg-label { font-size:13px; color:var(--text); flex:1; min-width:0; }
.cfg-input { flex:0 0 auto; }
/* Switch */
.switch { position:relative; display:inline-block; width:44px; height:24px; }
.switch input { opacity:0; width:0; height:0; }
.switch .slider { position:absolute; inset:0; background:#cbd5e1; border-radius:24px; transition:.2s; cursor:pointer; }
.switch .slider:before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.switch input:checked + .slider { background:var(--primary); }
.switch input:checked + .slider:before { transform:translateX(20px); }
/* Select / Input */
.cfg-select, .cfg-input-text, .cfg-input-num { padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--card); color:var(--text); min-width:160px; }
.cfg-input-text { min-width:200px; }
.cfg-input-num { min-width:100px; }
.cfg-select:focus, .cfg-input-text:focus, .cfg-input-num:focus { outline:none; border-color:var(--primary); }
.cfg-pass { min-width:200px; }
.cfg-tip { display:block; font-size:11px; color:var(--muted); margin-top:2px; font-weight:400; line-height:1.4; }
.cfg-textarea { width:100%; min-width:260px; padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-size:12px; background:var(--card); color:var(--text); font-family:Consolas,Monaco,monospace; resize:vertical; box-sizing:border-box; }
.cfg-textarea:focus { outline:none; border-color:var(--primary); }

/* Textarea */
.yaml-editor { width:100%; min-height:500px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12.5px; line-height:1.6; padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--card2); color:var(--text); resize:vertical; tab-size:2; }
.yaml-editor:focus { outline:none; border-color:var(--primary); }

/* Log */
.log-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
.auto-refresh { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--muted); }
.auto-refresh input { accent-color:var(--primary); }

/* Auth */
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.auth-card { background:var(--card); border-radius:16px; padding:36px 32px; max-width:400px; width:100%; box-shadow:var(--shadow); text-align:center; }
.auth-card h1 { font-size:22px; margin-bottom:4px; }
.auth-card .sub { color:var(--muted); font-size:13px; margin-bottom:24px; }
.auth-card input[type=password] { width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; margin-bottom:12px; background:var(--card2); color:var(--text); }
.auth-card input[type=password]:focus { outline:none; border-color:var(--primary); }
.auth-card .btn { width:100%; justify-content:center; }
.tip { font-size:12px; color:var(--muted); line-height:1.6; margin-top:10px; }

/* Overview info */
.info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; }
.info-item { background:var(--card2); border-radius:10px; padding:14px; border:1px solid var(--border); }
.info-item .label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.info-item .value { font-size:16px; font-weight:600; }

/* Responsive */
@media (max-width:640px) {
  .header { padding:12px 16px; }
  .tabs { padding:12px 16px 0; overflow-x:auto; }
  .main { padding:16px; }
  .task-grid { grid-template-columns:1fr 1fr; }
  .cfg-row { flex-direction:column; align-items:flex-start; }
  .cfg-input { align-self:flex-end; }
}

/* Config mode tabs */
.cfg-tabs { display:flex; gap:0; margin-bottom:16px; border-bottom:1px solid var(--border); }
.cfg-tab { padding:8px 16px; border:none; background:transparent; color:var(--muted); font-size:13px; cursor:pointer; border-bottom:2px solid transparent; }
.cfg-tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:500; }
.cfg-panel { display:none; }
.cfg-panel.active { display:block; }
</style>
</head>
<body>

<?php if (!$isAuth): ?>
<!-- ===== 登录页 ===== -->
<div class="auth-wrap">
  <div class="auth-card">
    <div style="font-size:48px;margin-bottom:8px;">🌟</div>
    <h1>March7th 管理面板</h1>
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
<!-- ===== 主界面 ===== -->

<!-- Header -->
<div class="header">
  <div class="header-inner">
    <div>
      <h1>🌟 March7th 管理面板</h1>
      <div class="sub">容器 <?php echo h(CONTAINER); ?> · <?php echo h(PROJECT_DIR); ?></div>
    </div>
    <div class="header-actions">
      <span class="status-badge" id="statusBadge"><span class="dot"></span><span id="statusText">检测中…</span></span>
      <button class="theme-btn" onclick="toggleTheme()" title="切换主题" id="themeBtn">🌙</button>
      <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="logout"><button type="submit" class="logout-btn">退出</button></form>
    </div>
  </div>
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
      <button class="btn green small" id="updBtn" onclick="doUpdate()">一键更新</button>
      <button class="btn gray small" onclick="hideUpdate()">稍后</button>
    </div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" data-tab="overview" onclick="switchTab('overview')">📊 概览</button>
  <button class="tab-btn" data-tab="tasks" onclick="switchTab('tasks')">🚀 任务</button>
  <button class="tab-btn" data-tab="config" onclick="switchTab('config')">⚙️ 配置</button>
  <button class="tab-btn" data-tab="log" onclick="switchTab('log')">📝 日志</button>
</div>

<!-- Main -->
<div class="main">

<?php if ($msg): ?><div class="msg ok">✅ <?php echo h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err">❌ <?php echo h($err); ?></div><?php endif; ?>

<!-- ===== 概览 ===== -->
<div class="tab-panel active" id="panel-overview">
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
    <h2><span class="icon">📊</span> 基本信息</h2>
    <div class="info-grid">
      <div class="info-item"><div class="label">项目目录</div><div class="value" style="font-size:13px;"><?php echo h(PROJECT_DIR); ?></div></div>
      <div class="info-item"><div class="label">容器名</div><div class="value"><?php echo h(CONTAINER); ?></div></div>
      <div class="info-item"><div class="label">Config</div><div class="value" style="font-size:13px;"><?php echo is_file(CONFIG_PATH) ? '✅ 存在' : '❌ 不存在'; ?></div></div>
      <div class="info-item"><div class="label">最近日志</div><div class="value" style="font-size:13px;"><?php $lp = latest_log_path(); echo $lp ? h(basename($lp)) : '暂无'; ?></div></div>
    </div>
  </div>

  <div class="card">
    <h2><span class="icon">💾</span> 配置备份</h2>
    <div class="btn-group">
      <a href="?download=config" class="btn primary">⬇️ 下载备份</a>
    </div>
    <form method="post" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:8px;align-items:center;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="restore_config">
      <input type="file" name="cfg_file" accept=".yaml,.yml" style="flex:1;font-size:13px;">
      <button type="submit" class="btn orange small" onclick="return confirm('恢复配置会用上传文件覆盖当前 config.yaml，确定？');">⬆️ 恢复配置</button>
    </form>
    <p class="tip" style="margin:10px 0 0;">下载备份可把配置导出到本地保存；恢复前会自动备份当前文件，恢复后需重启容器生效。</p>
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
    </div>
  </div>

<!-- ===== 任务 ===== -->
<div class="tab-panel" id="panel-tasks">
  <div class="card">
    <h2><span class="icon">🚀</span> 执行任务</h2>
    <p class="tip" style="margin-top:0;margin-bottom:14px;">任务在容器内后台执行，启动后可切到「日志」页查看进度。</p>
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
<div class="tab-panel" id="panel-config">
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
          <button type="button" class="btn" style="background:#64748b;" onclick="reloadYaml()">🔄 重新加载</button>
        </div>
      </form>
    </div>

    <div class="tip" style="margin-top:16px;">
      ⚠️ 保存配置后需<span style="font-weight:600;">重启容器</span>才生效。
      如遇写入权限错误，请执行：<code>chmod 666 <?php echo h(CONFIG_PATH); ?></code>
    </div>
  </div>
</div>

<!-- ===== 日志 ===== -->
<div class="tab-panel" id="panel-log">
  <div class="card">
    <div class="log-header">
      <h2 style="margin-bottom:0;"><span class="icon">📝</span> 运行日志</h2>
      <div class="auto-refresh">
        <label><input type="checkbox" id="autoRefresh" checked onchange="toggleAutoRefresh()"> 自动刷新</label>
        <select id="refreshInterval" onchange="updateRefreshInterval()" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--card);color:var(--text);">
          <option value="3000">3 秒</option>
          <option value="5000" selected>5 秒</option>
          <option value="10000">10 秒</option>
          <option value="30000">30 秒</option>
        </select>
        <button class="btn small gray" onclick="refreshLog()">立即刷新</button>
      </div>
    </div>
    <div class="codebox" id="logBox" style="max-height:600px;"><?php $lp = latest_log_path(); echo $lp ? h(tail_file($lp, 300)) : '(暂无日志文件，任务运行后会生成)'; ?></div>
  </div>
</div>

<?php endif; ?>

</div><!-- /main -->

<script>
/* ===== Theme (亮色 / 深色 / 星铁粉紫) ===== */
var THEMES = [
  { name: '',    icon: '☀️', label: '亮色' },
  { name: 'dark',  icon: '🌙', label: '深色' },
  { name: 'march7', icon: '💗', label: '星铁粉紫' }
];
function themeIdx(name) {
  for (var i = 0; i < THEMES.length; i++) if (THEMES[i].name === name) return i;
  return 0;
}
function applyTheme(name) {
  var idx = themeIdx(name), d = document.documentElement, btn = document.getElementById('themeBtn');
  d.setAttribute('data-theme', THEMES[idx].name);
  btn.textContent = THEMES[idx].icon;
  btn.title = '切换主题：' + THEMES[idx].label + ' → ' + THEMES[(idx + 1) % THEMES.length].label;
  try { localStorage.setItem('m7a_theme', THEMES[idx].name); } catch(e) {}
}
function toggleTheme() {
  var cur = document.documentElement.getAttribute('data-theme') || '';
  applyTheme(THEMES[(themeIdx(cur) + 1) % THEMES.length].name);
}
(function() {
  try {
    var t = localStorage.getItem('m7a_theme');
    if (t === 'dark' || t === 'march7') applyTheme(t); else applyTheme('');
  } catch(e) { applyTheme(''); }
})();

/* ===== Tabs ===== */
function switchTab(name) {
  document.querySelectorAll('.tab-panel').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.toggle('active', el.dataset.tab === name); });
  var panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');
  try { localStorage.setItem('m7a_tab', name); } catch(e) {}
  if (name === 'log') refreshLog();
  if (name === 'overview') refreshStatus();
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

/* ===== Config sub-tabs ===== */
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
  // 用户点过「稍后」后 1 小时内不再打扰
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
  apiEl.textContent = '测试中…'; rawEl.textContent = '测试中…';
  fetch('?ajax=test_update_source').then(function(r){ return r.json(); }).then(function(d){
    if (!d) { apiEl.textContent = '失败'; rawEl.textContent = '失败'; return; }
    if (d.api && d.api.state === 'ok_release')      apiEl.textContent = '✅ 已发版（HTTP ' + d.api.code + '）';
    else if (d.api && d.api.state === 'ok_no_release') apiEl.textContent = '✅ 连通，尚未发版（HTTP ' + d.api.code + '）';
    else apiEl.textContent = '❌ HTTP ' + (d.api ? d.api.code : '失败') + '（检查仓库名/发版）';
    rawEl.textContent = (d.raw && d.raw.ok) ? '✅ 可下载（HTTP ' + d.raw.code + '）' : '❌ HTTP ' + (d.raw ? d.raw.code : '失败') + '（文件未上传或路径不对）';
  }).catch(function(){ apiEl.textContent = '网络错误'; rawEl.textContent = '网络错误'; });
}

function hideUpdate() {
  document.getElementById('updateBanner').style.display = 'none';
  try { localStorage.setItem('m7a_upd_hide', Date.now()); } catch(e) {}
}

/* ===== AJAX refresh ===== */
var _logTimer = null;
var _statusTimer = null;

function refreshLog() {
  fetch('?ajax=log').then(function(r) { return r.text(); }).then(function(t) {
    var box = document.getElementById('logBox');
    if (box) { box.textContent = t; box.scrollTop = box.scrollHeight; }
  }).catch(function() {});
}

function refreshStatus() {
  fetch('?ajax=status').then(function(r) { return r.text(); }).then(function(t) {
    var box = document.getElementById('statusBox');
    if (box) box.textContent = t;
  }).catch(function() {});
  fetch('?ajax=running').then(function(r) { return r.json(); }).then(function(d) {
    var badge = document.getElementById('statusBadge');
    var text = document.getElementById('statusText');
    if (badge && text) {
      badge.className = 'status-badge ' + (d.running ? 'running' : 'stopped');
      text.textContent = d.running ? '运行中' : '已停止';
    }
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

// Init auto-refresh
refreshStatus();
startAutoRefresh();

/* ===== Save + Restart ===== */
function restartAfterSave() {
  document.getElementById('configForm').addEventListener('submit', function() {
    // After form submit, page will reload with msg; user can then restart
    // We add a hidden field to signal restart needed
  });
  // Submit form normally, then we'll add a restart step
  var form = document.getElementById('configForm');
  // Create a temporary form that saves then restarts
  // Actually, just submit normally and add restart action
  // Simple approach: set a flag
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
</script>
</body>
</html>
