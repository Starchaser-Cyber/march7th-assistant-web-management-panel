<?php
/**
 * March7th Assistant 网页管理面板 v1.15
 * 现代化 UI + 图形化配置编辑 + 实时状态 + 配置备份恢复 + 多镜像下载 + 日志高级查询 + 多实例切换 + 货币战争 + 停止任务 + 停止循环 + 停止容器 + 镜像检查 + 更新模式 + 更新分类 + 通知自动消失 + 小助手镜像加速更新 + 镜像版本精准检查 + 资源监控 + 版本备份回滚 + 任务执行历史 + 消息推送可视化配置（一键测试推送）+ 计划任务
 * 纯原生 PHP 单文件 · 宝塔友好
 */
declare(strict_types=1);
session_start();

define('PASS_FILE', __DIR__ . '/.panel_pass.php');
define('INSTANCES_FILE', __DIR__ . '/.instances.php');
define('PANEL_CONFIG_FILE', __DIR__ . '/.panel_config.php');
define('DEFAULT_DIR', '/home/march7thassistant');
define('DEFAULT_CONTAINER', 'm7a');
define('SKEY', 'm7a_panel_auth');
define('CSRF_KEY', 'm7a_panel_csrf');

/* ===== 自动更新配置 =====
 * 发版流程：改 PANEL_VERSION → git push → 在 Gitea/GitHub 打 tag（如 v1.0）并创建 Release
 * UPDATE_TYPE: gitea / github
 */
define('PANEL_VERSION', '1.16');           // 面板当前版本号（发版时手动修改）
define('UPDATE_ENABLED', true);              // 是否启用自动检查更新
define('UPDATE_TYPE', 'github');              // 更新源类型：gitea 或 github
define('UPDATE_HOST', 'https://github.com');  // Gitea 实例地址（UPDATE_TYPE=gitea 时生效）
define('UPDATE_OWNER', 'starchaser-cyber');          // 仓库所有者
define('UPDATE_REPO', 'march7th-assistant-web-management-panel');          // 仓库名
define('UPDATE_BRANCH', 'main');             // 仓库分支

/* ===== 版本备份 / 回滚（v1.14+） =====
 * 面板自动更新前会把当前 index.php 备份到 BACKUP_DIR，只保留最近 BACKUP_KEEP 份，
 * 更新出问题可在面板上一键回滚。
 */
define('BACKUP_DIR', __DIR__ . '/backups');  // 版本备份目录（自动创建）
define('BACKUP_KEEP', 5);                    // 备份最多保留份数

/* ===== 任务执行历史（v1.14+） =====
 * 每次启动任务记一条 running 记录，靠日志文件的静默时间判定任务是否结束。
 */
define('HISTORY_IDLE_SECONDS', 90);          // 日志静默超过该秒数视为任务已结束
define('HISTORY_KEEP', 200);                 // 历史最多保留条数

/* ===== 计划任务（v1.15+） =====
 * 由宿主机（宝塔「计划任务」）每分钟请求 ?scheduler=1&key=xxx，面板判断是否命中时间点后启动任务。
 * 这样无需容器内程序常驻，Docker 按需起容器的场景也能定时跑。
 */
define('SCHEDULE_WINDOW_SECONDS', 1800);     // 补跑窗口：错过时间点 30 分钟内仍会补跑一次

/* ===== 任务白名单 ===== */
$TASKS = array(
    'main'          => array('label' => '全量运行',   'desc' => '日常+周常全部执行一遍', 'icon' => '🚀', 'long' => true),
    'daily'         => array('label' => '日常任务',   'desc' => '清体力/每日实训/领奖励', 'icon' => '📋', 'long' => true),
    'power'         => array('label' => '清体力',     'desc' => '仅清空开拓力', 'icon' => '⚡', 'long' => true),
    'notify'        => array('label' => '测试通知',   'desc' => '发送一条通知测试推送', 'icon' => '🔔', 'long' => false),
    'divergentloop'   => array('label' => '差分宇宙',   'desc' => '差分宇宙周常', 'icon' => '🌌', 'long' => true),
    'universe'        => array('label' => '模拟宇宙',   'desc' => '模拟宇宙周常', 'icon' => '🔮', 'long' => true),
    'currencywars'    => array('label' => '货币战争',   'desc' => '按所选模式运行一次（标准/超频）', 'icon' => '💰', 'long' => true),
    'currencywarsloop'=> array('label' => '货币战争循环', 'desc' => '货币战争循环执行', 'icon' => '♻️', 'long' => true),
);
$OPS = array(
    'restart' => array('label' => '重启容器', 'desc' => 'docker compose restart', 'icon' => '🔄', 'confirm' => '确定重启容器？', 'color' => 'orange'),
    'stop_task' => array('label' => '停止任务（重启容器）', 'desc' => '任务进程即容器主进程（PID 1），停止任务会重启容器', 'icon' => '⏹️', 'confirm' => '任务进程即容器主进程，停止任务将重启容器，确定继续？', 'color' => 'red'),
    'stop_loop' => array('label' => '停止循环（轻量）', 'desc' => '仅停止货币战争循环子进程，容器与主进程不动', 'icon' => '🛑', 'confirm' => '确定停止货币战争循环？（仅结束循环子进程，不重启容器）', 'color' => 'blue'),
    'stop' => array('label' => '停止容器', 'desc' => 'docker compose stop，容器进入停止状态（想再运行点「重启容器」即可恢复）', 'icon' => '⏸️', 'confirm' => '确定停止容器？任务将全部中断，想再运行请点「重启容器」恢复', 'color' => 'gray'),
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
        'currencywars_enable'       => array('label' => '货币战争', 'type' => 'bool', 'tip' => '启用后按所选模式自动完成积分奖励'),
        'currencywars_type'         => array('label' => '货币战争模式', 'type' => 'select', 'options' => array('normal' => '标准博弈（提升职级）', 'overclock' => '超频博弈（快速刷积分）'), 'tip' => '标准博弈收益更高；超频博弈时间更短'),
        'currencywars_bonus_enable' => array('label' => '积分后自动提取饰品', 'type' => 'bool', 'tip' => '领取积分奖励后自动消耗深度沉浸器快速提取位面饰品，可能提升运行速度'),
    )),
    'schedule' => array('title' => '定时与循环', 'icon' => '⏰', 'fields' => array(
        'loop_mode'           => array('label' => '循环模式', 'type' => 'select', 'options' => array('scheduled'=>'定时任务','power'=>'根据开拓力')),
        'scheduled_time'      => array('label' => '运行时间', 'type' => 'str', 'placeholder' => '如 8:00 或 04:00'),
        'power_limit'         => array('label' => '开拓力下限', 'type' => 'int'),
        'refresh_hour'        => array('label' => '游戏刷新时间（时）', 'type' => 'int'),
        'scheduled_on_conflict' => array('label' => '任务冲突处理', 'type' => 'select', 'options' => array('skip'=>'跳过','stop'=>'停止当前再启动')),
        'scheduled_chain_continue_on_failure' => array('label' => '链式任务失败后继续', 'type' => 'bool'),
    )),
    /* ===== 消息推送（v1.15+）=====
     * 小助手的全部推送渠道集中在这里可视化配置，键名均为 config.yaml 里真实存在的顶层平铺标量键，
     * 直接复用 config_save_form() 的逐行改写逻辑（保留注释、不动结构），无需额外的解析器。
     * 常用渠道平铺展示，冷门渠道收在折叠区里默认收起，不干扰日常使用。
     */
    'notify' => array('title' => '消息推送 · 通用', 'icon' => '🔔', 'note' => '小助手的推送渠道都集中在这一段（v1.15 起），常用渠道任选一个填好即可；填完点表单最下方的「🔔 保存并发送测试推送」能立刻验证是否配通。修改后需重启容器生效。', 'fields' => array(
        'notification_enable' => array('label' => '通知总开关', 'type' => 'bool', 'tip' => '关闭后所有渠道推送均失效；只想临时静音关这里即可'),
        'notify_level'        => array('label' => '通知级别', 'type' => 'select', 'options' => array('all'=>'全部通知','error'=>'仅错误'), 'tip' => '选「仅错误」时只有出错才推送，日常成功不打扰'),
        'notify_merge'        => array('label' => '合并通知', 'type' => 'bool', 'tip' => '开启后完整运行结束只发一条汇总'),
        'notify_send_images'  => array('label' => '推送图片', 'type' => 'bool', 'tip' => '推送消息时附带游戏截图（部分渠道不支持）'),
    )),
    'notify_serverchan' => array('title' => 'Server酱（微信）', 'icon' => '🛎️', 'note' => '微信推送最省事的方案：填一个 SendKey 就能用。修改后需重启容器生效。', 'fields' => array(
        'notify_serverchanturbo_enable' => array('label' => '启用 Server酱·Turbo', 'type' => 'bool', 'tip' => '免费版每天 5 条，注册地址 sct.ftqq.com'),
        'notify_serverchanturbo_sctkey' => array('label' => 'SendKey（Turbo）', 'type' => 'password', 'placeholder' => 'SCT 开头的密钥', 'tip' => '登录 sct.ftqq.com，首页即可复制'),
        'notify_serverchanturbo_channel' => array('label' => '推送渠道', 'type' => 'str', 'placeholder' => '可选', 'tip' => '留空使用默认渠道（微信服务号）'),
        'notify_serverchanturbo_openid' => array('label' => 'OpenID', 'type' => 'str', 'placeholder' => '可选', 'tip' => '指定接收人的 OpenID，留空发给本人'),
        'notify_serverchan3_enable' => array('label' => '启用 Server酱·3', 'type' => 'bool', 'tip' => '走 APP 推送，注册地址 sc3.ft07.com'),
        'notify_serverchan3_sendkey' => array('label' => 'SendKey（3）', 'type' => 'password', 'placeholder' => 'sctp 开头的密钥', 'tip' => 'Turbo 与 3 是两个服务，密钥不通用，按需只填一个'),
    )),
    'notify_bark' => array('title' => 'Bark（iOS）', 'icon' => '🐶', 'fields' => array(
        'notify_bark_enable' => array('label' => '启用 Bark', 'type' => 'bool', 'tip' => 'iOS 用户推荐，App Store 搜 Bark 安装，支持推送截图'),
        'notify_bark_key' => array('label' => '推送 Key', 'type' => 'password', 'placeholder' => '只填 Key，不要带 https://api.day.app/', 'tip' => 'Bark App 首页地址里最后一段就是 Key'),
        'notify_bark_base_url' => array('label' => '自定义服务 URL', 'type' => 'str', 'placeholder' => '如 https://api.day.app', 'tip' => '只有自建 Bark 服务才填，官方服务留空'),
        'notify_bark_group' => array('label' => '分组名', 'type' => 'str', 'placeholder' => '可选', 'tip' => 'Bark 里的消息分组，便于归类'),
        'notify_bark_icon' => array('label' => '图标 URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '消息左侧显示的小图标'),
        'notify_bark_sound' => array('label' => '提示音', 'type' => 'str', 'placeholder' => '可选', 'tip' => '如 birdsong、alarm，留空用默认'),
        'notify_bark_isarchive' => array('label' => '是否归档', 'type' => 'str', 'placeholder' => '可选，1 或 0', 'tip' => '填 1 表示消息存进 Bark 历史记录'),
        'notify_bark_url' => array('label' => '点击跳转 URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '点通知后打开的网页地址'),
        'notify_bark_copy' => array('label' => '复制文本', 'type' => 'str', 'placeholder' => '可选', 'tip' => '通知里附带一段可长按复制的文本'),
        'notify_bark_autocopy' => array('label' => '自动复制', 'type' => 'str', 'placeholder' => '可选', 'tip' => '填 1 表示收到通知自动复制上面那段文本'),
        'notify_bark_cipherkey' => array('label' => '加密密钥', 'type' => 'password', 'placeholder' => '需与 App 内一致', 'tip' => '自建加密服务才填'),
        'notify_bark_ciphermethod' => array('label' => '加密算法', 'type' => 'str', 'placeholder' => 'cbc 或 ecb', 'tip' => '与上方的加密密钥配套使用'),
    )),
    'notify_dingtalk' => array('title' => '钉钉机器人', 'icon' => '📌', 'fields' => array(
        'notify_dingtalk_enable' => array('label' => '启用钉钉', 'type' => 'bool', 'tip' => '群里加「自定义机器人」后把凭据填到这里'),
        'notify_dingtalk_token' => array('label' => '机器人 Access Token', 'type' => 'password', 'placeholder' => 'Webhook 里 access_token= 后面的部分', 'tip' => '只填 token，不用填完整 Webhook 地址'),
        'notify_dingtalk_secret' => array('label' => '加签密钥', 'type' => 'password', 'placeholder' => '可选', 'tip' => '机器人安全设置选了「加签」才需要填，选「自定义关键词」则留空'),
    )),
    'notify_pushplus' => array('title' => 'PushPlus', 'icon' => '📮', 'fields' => array(
        'notify_pushplus_enable' => array('label' => '启用 PushPlus', 'type' => 'bool', 'tip' => '微信推送，pushplus.plus 微信扫码登录后获取 Token'),
        'notify_pushplus_token' => array('label' => 'Token', 'type' => 'password', 'placeholder' => 'pushplus.plus 首页复制', 'tip' => '免费版每日条数有限'),
        'notify_pushplus_channel' => array('label' => '通知渠道', 'type' => 'str', 'placeholder' => '可选，如 wechat / webhook', 'tip' => '留空使用默认渠道'),
        'notify_pushplus_webhook' => array('label' => 'Webhook URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '渠道选 webhook 时填接收地址'),
        'notify_pushplus_callbackUrl' => array('label' => '回调 URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '推送后的回调地址，一般不需要'),
    )),
    'notify_wechat' => array('title' => '企业微信', 'icon' => '💼', 'fields' => array(
        'notify_wechatworkbot_enable' => array('label' => '启用机器人通知（简单）', 'type' => 'bool', 'tip' => '群里加「群机器人」即可，配置最简单，推荐先用这个'),
        'notify_wechatworkbot_key' => array('label' => '机器人 Key', 'type' => 'password', 'placeholder' => 'Webhook 里 key= 后面的部分', 'tip' => '只填 key，不用填完整 Webhook 地址'),
        'notify_wechatworkbot_webhook_url' => array('label' => 'Webhook URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '自建中转时才填'),
        'notify_wechatworkapp_enable' => array('label' => '启用应用通知（可发截图）', 'type' => 'bool', 'tip' => '需要在企业微信后台自建应用，但支持推送截图'),
        'notify_wechatworkapp_corpid' => array('label' => '企业 ID', 'type' => 'str', 'placeholder' => '我的企业 → 企业信息里查看', 'tip' => '以 ww 或 wx 开头'),
        'notify_wechatworkapp_corpsecret' => array('label' => '应用密钥', 'type' => 'password', 'placeholder' => '应用管理 → 你的应用 → Secret', 'tip' => '应用级的 Secret，不是通讯录密钥'),
        'notify_wechatworkapp_agentid' => array('label' => 'AgentId', 'type' => 'str', 'placeholder' => '应用详情页可看到（纯数字）', 'tip' => '企业微信应用详情里的 AgentId'),
        'notify_wechatworkapp_touser' => array('label' => '目标用户', 'type' => 'str', 'placeholder' => '@all 或 成员账号', 'tip' => '填 @all 发给全企业成员，也可填成员 UserID，多个用 | 分隔'),
        'notify_wechatworkapp_base_url' => array('label' => '自定义 API 地址', 'type' => 'str', 'placeholder' => '可选', 'tip' => '默认走官方 API，一般留空'),
    )),
    'notify_lark' => array('title' => '飞书', 'icon' => '🪶', 'fields' => array(
        'notify_lark_enable' => array('label' => '启用飞书', 'type' => 'bool', 'tip' => '群里加「自定义机器人」后把 Webhook 地址填到这里'),
        'notify_lark_webhook' => array('label' => 'Webhook 地址', 'type' => 'password', 'placeholder' => 'https://open.feishu.cn/open-apis/bot/v2/hook/xxxx', 'tip' => '完整 Webhook 地址，飞书群设置 → 群机器人里复制'),
        'notify_lark_keyword' => array('label' => '安全关键词', 'type' => 'str', 'placeholder' => '无则留空', 'tip' => '机器人安全设置选了「关键词」时必填，且消息内容需包含该关键词'),
        'notify_lark_sign' => array('label' => '签名密钥', 'type' => 'password', 'placeholder' => '可选', 'tip' => '机器人安全设置选了「签名校验」时才填'),
        'notify_lark_content' => array('label' => '消息内容', 'type' => 'str', 'placeholder' => '可选', 'tip' => '自定义消息模板，留空用默认内容'),
        'notify_lark_imageenable' => array('label' => '图片消息', 'type' => 'bool', 'tip' => '开启后可推送截图，需要自建飞书应用'),
        'notify_lark_appid' => array('label' => '应用 AppID', 'type' => 'str', 'placeholder' => '图片消息必填', 'tip' => '飞书开放平台自建应用的 AppID'),
        'notify_lark_secret' => array('label' => '应用 Secret', 'type' => 'password', 'placeholder' => '图片消息必填', 'tip' => '飞书开放平台自建应用的 App Secret'),
    )),
    'notify_telegram' => array('title' => 'Telegram', 'icon' => '✈️', 'fields' => array(
        'notify_telegram_enable' => array('label' => '启用 Telegram', 'type' => 'bool', 'tip' => '服务器需能访问 Telegram，国内服务器通常要配代理'),
        'notify_telegram_token'  => array('label' => 'Bot Token', 'type' => 'password', 'placeholder' => 'BotFather 获取', 'tip' => '在 Telegram 里找 @BotFather 创建机器人后复制 Token'),
        'notify_telegram_userid' => array('label' => '接收用户/群组 ID', 'type' => 'str', 'placeholder' => '如 123456789', 'tip' => '私聊 ID 找 @userinfobot 获取；群组填 -100 开头的 ID'),
        'notify_telegram_api_url'=> array('label' => '自定义 API URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '只有自建反向代理/中转服务器才填'),
        'notify_telegram_proxies'=> array('label' => '代理地址', 'type' => 'str', 'placeholder' => '如 127.0.0.1:10808', 'tip' => '容器内可访问的代理地址，一般留空'),
        'notify_telegram_thread_id' => array('label' => 'Topics 线程 ID', 'type' => 'str', 'placeholder' => '可选', 'tip' => '群组开了话题（Topics）时才需要'),
    )),
    'notify_gotify' => array('title' => 'Gotify（自建）', 'icon' => '🔧', 'fields' => array(
        'notify_gotify_enable' => array('label' => '启用 Gotify', 'type' => 'bool', 'tip' => '自建推送服务，适合不想依赖第三方推送的用户'),
        'notify_gotify_url' => array('label' => '服务器 URL', 'type' => 'str', 'placeholder' => '如 http://1.2.3.4:8080', 'tip' => 'Gotify 服务地址，注意容器内能否访问'),
        'notify_gotify_token' => array('label' => 'Access Token', 'type' => 'password', 'placeholder' => 'Apps 里创建应用后生成', 'tip' => 'Gotify 后台 → Apps → 创建应用 → Token'),
        'notify_gotify_priority' => array('label' => '优先级（1-10）', 'type' => 'int', 'tip' => '数字越大通知越「重要」，默认 5'),
    )),
    'notify_discord' => array('title' => 'Discord', 'icon' => '🎮', 'fields' => array(
        'notify_discord_enable' => array('label' => '启用 Discord', 'type' => 'bool', 'tip' => '服务器能访问 Discord 时可用'),
        'notify_discord_webhook' => array('label' => 'Webhook URL', 'type' => 'password', 'placeholder' => 'https://discord.com/api/webhooks/xxx', 'tip' => '频道设置 → 整合 → Webhook → 复制 Webhook 网址'),
        'notify_discord_username' => array('label' => '自定义用户名', 'type' => 'str', 'placeholder' => '可选', 'tip' => '消息显示的发件人名字'),
        'notify_discord_avatar_url' => array('label' => '自定义头像 URL', 'type' => 'str', 'placeholder' => '可选', 'tip' => '消息头像图片地址'),
        'notify_discord_color' => array('label' => '嵌入消息颜色', 'type' => 'str', 'placeholder' => '可选，如 #ec4899', 'tip' => '左侧色条颜色'),
    )),
    'notify_smtp' => array('title' => 'SMTP 邮箱', 'icon' => '📧', 'fields' => array(
        'notify_smtp_enable' => array('label' => '启用 SMTP', 'type' => 'bool', 'tip' => '邮件推送，支持发送截图；QQ 邮箱需填授权码而非登录密码'),
        'notify_smtp_host' => array('label' => 'SMTP 服务器', 'type' => 'str', 'placeholder' => '如 smtp.qq.com', 'tip' => 'QQ 邮箱 smtp.qq.com、163 邮箱 smtp.163.com'),
        'notify_smtp_user' => array('label' => '用户名/邮箱', 'type' => 'str', 'placeholder' => '如 123456@qq.com', 'tip' => '登录邮箱的完整地址'),
        'notify_smtp_password' => array('label' => '密码/授权码', 'type' => 'password', 'placeholder' => '留空则不修改', 'tip' => 'QQ / 163 邮箱在设置里开启 SMTP 后生成授权码'),
        'notify_smtp_From' => array('label' => '发件人', 'type' => 'str', 'placeholder' => '一般与用户名相同', 'tip' => '部分邮箱要求发件人必须是本邮箱地址'),
        'notify_smtp_To' => array('label' => '收件人', 'type' => 'str', 'placeholder' => '多个用逗号分隔', 'tip' => '可以填自己的邮箱收通知'),
        'notify_smtp_port' => array('label' => '端口', 'type' => 'str', 'placeholder' => '默认 465', 'tip' => 'SSL 通常是 465，STARTTLS 通常是 587'),
        'notify_smtp_ssl' => array('label' => 'SSL 连接', 'type' => 'bool', 'tip' => '端口 465 时开启'),
        'notify_smtp_starttls' => array('label' => 'STARTTLS', 'type' => 'bool', 'tip' => '端口 587 时开启'),
        'notify_smtp_ssl_unverified' => array('label' => '不验证 SSL 证书', 'type' => 'bool', 'tip' => '自签名证书的邮箱才推荐开启'),
    )),
    'notify_webhook' => array('title' => '通用 Webhook', 'icon' => '🕸️', 'fields' => array(
        'notify_webhook_enable' => array('label' => '启用 Webhook', 'type' => 'bool', 'tip' => '想接到自建服务 / 其他推送平台时用，支持自定义请求方法、Headers 与 Body'),
        'notify_webhook_url' => array('label' => '接收地址', 'type' => 'str', 'placeholder' => '如 http://localhost:8080/notify', 'tip' => '注意 Docker 容器内能否访问该地址（宿主机可用 172.17.0.1 或实际内网 IP）'),
        'notify_webhook_method' => array('label' => '请求方法', 'type' => 'select', 'options' => array(''=>'默认 POST','GET'=>'GET','POST'=>'POST','PUT'=>'PUT','DELETE'=>'DELETE'), 'tip' => '留空按小助手默认 POST'),
        'notify_webhook_headers' => array('label' => '自定义 Headers', 'type' => 'textarea', 'placeholder' => 'JSON 格式，如 {"Authorization": "Bearer token"}', 'tip' => 'JSON 格式，需要鉴权的平台通常要填'),
        'notify_webhook_body' => array('label' => '请求体模板', 'type' => 'textarea', 'placeholder' => 'JSON 或字符串，支持 {title} {content} {image}', 'tip' => '可用 {title} {content} {image} 占位符'),
    )),

    /* ---- 更多渠道：相对冷门的推送统一收在折叠区，点标题展开 ---- */
    'notify_matrix' => array('title' => '更多渠道 · Matrix / PushDeer / KOOK / QQ 机器人 / 自定义', 'icon' => '🧩', 'collapsed' => true, 'note' => '折叠区里是相对冷门的推送渠道与自定义通知，按需点开填写；改完同样需要重启容器生效。', 'fields' => array(
        'notify_matrix_enable' => array('label' => 'Matrix · 启用', 'type' => 'bool'),
        'notify_matrix_homeserver' => array('label' => 'Matrix · 服务器地址', 'type' => 'str', 'placeholder' => '如 https://matrix.org'),
        'notify_matrix_user_id' => array('label' => 'Matrix · 用户 ID', 'type' => 'str', 'placeholder' => '如 @user:matrix.org'),
        'notify_matrix_access_token' => array('label' => 'Matrix · Access Token', 'type' => 'password', 'placeholder' => '登录后由服务器分发'),
        'notify_matrix_room_id' => array('label' => 'Matrix · 房间 ID', 'type' => 'str', 'placeholder' => '如 !abc:matrix.org'),
        'notify_matrix_device_id' => array('label' => 'Matrix · 设备 ID', 'type' => 'str', 'placeholder' => '10 位大写字母/数字'),
        'notify_matrix_proxy' => array('label' => 'Matrix · 代理', 'type' => 'str', 'placeholder' => '可选'),
        'notify_matrix_separately_text_media' => array('label' => 'Matrix · 文字与图片分开发送', 'type' => 'bool'),
        'notify_pushdeer_enable' => array('label' => 'PushDeer · 启用', 'type' => 'bool', 'tip' => '自建或官方 PushDeer 服务'),
        'notify_pushdeer_token' => array('label' => 'PushDeer · Token', 'type' => 'password'),
        'notify_pushdeer_url' => array('label' => 'PushDeer · 自定义服务 URL', 'type' => 'str', 'placeholder' => '可选'),
        'notify_kook_enable' => array('label' => 'KOOK · 启用', 'type' => 'bool', 'tip' => 'KOOK 机器人，支持发送截图'),
        'notify_kook_token' => array('label' => 'KOOK · 机器人 Token', 'type' => 'password', 'placeholder' => '开发者中心机器人 Token'),
        'notify_kook_target_id' => array('label' => 'KOOK · 目标 ID', 'type' => 'str', 'placeholder' => '用户 ID 或频道 ID'),
        'notify_kook_chat_type' => array('label' => 'KOOK · 消息类型', 'type' => 'str', 'placeholder' => '1 私聊 / 9 频道'),
        'notify_meow_enable' => array('label' => 'MeoW · 启用', 'type' => 'bool', 'tip' => 'MeoW 推送（iOS 通知）'),
        'notify_meow_nickname' => array('label' => 'MeoW · 昵称', 'type' => 'str'),
        'notify_onebot_enable' => array('label' => 'OneBot · 启用', 'type' => 'bool', 'tip' => '支持 NapCatQQ / OpenShamrock 等 OneBot 实现'),
        'notify_onebot_endpoint' => array('label' => 'OneBot · 服务端点 URL', 'type' => 'str', 'placeholder' => '如 http://127.0.0.1:3000'),
        'notify_onebot_token' => array('label' => 'OneBot · Access Token', 'type' => 'password', 'placeholder' => '可选'),
        'notify_onebot_user_id' => array('label' => 'OneBot · 接收用户 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_onebot_group_id' => array('label' => 'OneBot · 接收群组 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_gocqhttp_enable' => array('label' => 'go-cqhttp · 启用', 'type' => 'bool', 'tip' => '已停止维护，老用户仍可用'),
        'notify_gocqhttp_endpoint' => array('label' => 'go-cqhttp · 服务端点 URL', 'type' => 'str'),
        'notify_gocqhttp_message_type' => array('label' => 'go-cqhttp · 消息类型', 'type' => 'select', 'options' => array(''=>'默认','private'=>'私聊','group'=>'群消息')),
        'notify_gocqhttp_token' => array('label' => 'go-cqhttp · Access Token', 'type' => 'password', 'placeholder' => '可选'),
        'notify_gocqhttp_user_id' => array('label' => 'go-cqhttp · 接收用户 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_gocqhttp_group_id' => array('label' => 'go-cqhttp · 接收群组 ID', 'type' => 'str', 'placeholder' => '可选'),
        'notify_custom_enable' => array('label' => '自定义通知 · 启用', 'type' => 'bool', 'tip' => '完全自定义请求地址与请求体，支持截图'),
        'notify_custom_url' => array('label' => '自定义通知 · 请求 URL', 'type' => 'str', 'placeholder' => '如 http://localhost:3000/send_msg'),
        'notify_custom_method' => array('label' => '自定义通知 · 请求类型', 'type' => 'str', 'placeholder' => 'get / post'),
        'notify_custom_datatype' => array('label' => '自定义通知 · 数据类型', 'type' => 'str', 'placeholder' => 'data / json'),
        'notify_custom_image' => array('label' => '自定义通知 · 图片模板', 'type' => 'textarea', 'placeholder' => '可选，OneBot 参考格式'),
        'notify_custom_data' => array('label' => '自定义通知 · 请求体', 'type' => 'textarea', 'placeholder' => 'OneBot 参考 {user_id: 114514, message: [...]}'),
        'notify_winotify_enable' => array('label' => 'Windows 原生通知', 'type' => 'bool', 'tip' => '仅在 Windows 本机直接运行小助手时有效，Docker 部署下不生效'),
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

/* ===== 推送渠道体检规则（v1.15+） =====
 * 每项含义：
 *   group    : 该渠道所属的 $CONFIG_GROUPS 分组键（用来取分组标题做渠道名兜底）
 *   enable   : 独立启用开关的**真实键名**；没有独立开关的渠道写 null，
 *              体检时改用「任一必填键非空即视为启用」
 *   required : 必填键名数组（展示用的可读名一律从 $CONFIG_GROUPS 取真实 label，不另编名字）
 * 仅服务于「🔍 保存并体检」的只读判断，不参与配置写入，也不改变任何已有功能行为。
 */
$NOTIFY_CHANNEL_RULES = array(
    array('group' => 'notify_serverchan', 'enable' => 'notify_serverchanturbo_enable', 'required' => array('notify_serverchanturbo_sctkey')),
    array('group' => 'notify_serverchan', 'enable' => 'notify_serverchan3_enable', 'required' => array('notify_serverchan3_sendkey')),
    array('group' => 'notify_bark', 'enable' => 'notify_bark_enable', 'required' => array('notify_bark_key')),
    array('group' => 'notify_dingtalk', 'enable' => 'notify_dingtalk_enable', 'required' => array('notify_dingtalk_token')),
    array('group' => 'notify_pushplus', 'enable' => 'notify_pushplus_enable', 'required' => array('notify_pushplus_token')),
    array('group' => 'notify_wechat', 'enable' => 'notify_wechatworkbot_enable', 'required' => array('notify_wechatworkbot_key')),
    array('group' => 'notify_wechat', 'enable' => 'notify_wechatworkapp_enable', 'required' => array('notify_wechatworkapp_corpid', 'notify_wechatworkapp_corpsecret', 'notify_wechatworkapp_agentid', 'notify_wechatworkapp_touser')),
    array('group' => 'notify_lark', 'enable' => 'notify_lark_enable', 'required' => array('notify_lark_webhook')),
    array('group' => 'notify_telegram', 'enable' => 'notify_telegram_enable', 'required' => array('notify_telegram_token', 'notify_telegram_userid')),
    array('group' => 'notify_gotify', 'enable' => 'notify_gotify_enable', 'required' => array('notify_gotify_url', 'notify_gotify_token')),
    array('group' => 'notify_discord', 'enable' => 'notify_discord_enable', 'required' => array('notify_discord_webhook')),
    array('group' => 'notify_smtp', 'enable' => 'notify_smtp_enable', 'required' => array('notify_smtp_host', 'notify_smtp_user', 'notify_smtp_password', 'notify_smtp_To')),
    array('group' => 'notify_webhook', 'enable' => 'notify_webhook_enable', 'required' => array('notify_webhook_url')),
    array('group' => 'notify_matrix', 'enable' => 'notify_matrix_enable', 'required' => array('notify_matrix_homeserver', 'notify_matrix_access_token', 'notify_matrix_room_id')),
    array('group' => 'notify_matrix', 'enable' => 'notify_pushdeer_enable', 'required' => array('notify_pushdeer_token')),
    array('group' => 'notify_matrix', 'enable' => 'notify_kook_enable', 'required' => array('notify_kook_token', 'notify_kook_target_id')),
    array('group' => 'notify_matrix', 'enable' => 'notify_meow_enable', 'required' => array('notify_meow_nickname')),
    array('group' => 'notify_matrix', 'enable' => 'notify_onebot_enable', 'required' => array('notify_onebot_endpoint')),
    array('group' => 'notify_matrix', 'enable' => 'notify_gocqhttp_enable', 'required' => array('notify_gocqhttp_endpoint')),
    array('group' => 'notify_matrix', 'enable' => 'notify_custom_enable', 'required' => array('notify_custom_url')),
    array('group' => 'notify_matrix', 'enable' => 'notify_winotify_enable', 'required' => array()),
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

/* ===== 面板自身配置（.panel_config.php，自动创建） ===== */
function panel_config_load() {
    $def = array('update_mode' => 'auto');
    if (!is_file(PANEL_CONFIG_FILE)) {
        $content = "<?php\n// 面板自身配置（自动生成，v1.6+）\nreturn array('update_mode' => 'auto');\n";
        @file_put_contents(PANEL_CONFIG_FILE, $content);
        @chmod(PANEL_CONFIG_FILE, 0600);
        return $def;
    }
    $cfg = include PANEL_CONFIG_FILE;
    return is_array($cfg) ? array_merge($def, $cfg) : $def;
}
function panel_config_save($updates) {
    $cfg = panel_config_load();
    foreach ($updates as $k => $v) $cfg[$k] = $v;
    $content = "<?php\n// 面板自身配置（自动生成，v1.6+）\nreturn " . var_export($cfg, true) . ";\n";
    if (@file_put_contents(PANEL_CONFIG_FILE, $content) === false) return false;
    @chmod(PANEL_CONFIG_FILE, 0600);
    return true;
}
function panel_update_mode() {
    $cfg = panel_config_load();
    return (isset($cfg['update_mode']) && $cfg['update_mode'] === 'manual') ? 'manual' : 'auto';
}
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

/* ===== 推送配置体检（v1.15+，纯只读） =====
 * 用途：「🔍 保存并体检」保存配置后，判断哪些渠道真正会发出去、哪些启用了却缺必填项。
 * 只读 $cfgVals（yaml_read_simple() 的结果），不写配置、不发消息。
 */
/** yaml 值是否算「空」：去掉引号、空格、制表符后为空串即算空 */
function notify_val_empty($v) {
    return trim((string)$v, "\"' \t") === '';
}
/** yaml 值是否为 true（大小写不敏感，兼容带引号写法） */
function notify_val_true($v) {
    return strtolower(trim((string)$v, "\"' \t")) === 'true';
}
/** 真实键名 => 字段定义索引（label / 所属分组），展示文案全部取自 $CONFIG_GROUPS */
function notify_field_index() {
    global $CONFIG_GROUPS;
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = array();
    foreach ($CONFIG_GROUPS as $gk => $g) {
        foreach ($g['fields'] as $fk => $f) {
            $idx[$fk] = array('label' => isset($f['label']) ? (string)$f['label'] : (string)$fk, 'group' => (string)$gk);
        }
    }
    return $idx;
}
/** 渠道展示名：取启用开关的真实 label（去掉「启用」「· 启用」）；没有开关时退回分组标题 */
function notify_channel_name($rule, $rules = null) {
    global $CONFIG_GROUPS, $NOTIFY_CHANNEL_RULES;
    $all = ($rules === null) ? $NOTIFY_CHANNEL_RULES : $rules;
    $idx = notify_field_index();
    $gk = isset($rule['group']) ? (string)$rule['group'] : '';
    $gtitle = isset($CONFIG_GROUPS[$gk]['title']) ? (string)$CONFIG_GROUPS[$gk]['title'] : '';
    $gshort = trim((string)preg_replace('/^更多渠道\s*·\s*/u', '', $gtitle));
    $ek = isset($rule['enable']) ? $rule['enable'] : null;
    $name = '';
    if ($ek !== null && $ek !== '' && isset($idx[$ek])) {
        $name = trim((string)preg_replace('/^启用\s*/u', '', $idx[$ek]['label']));
        $name = trim((string)preg_replace('/\s*·\s*启用$/u', '', $name));
    }
    if ($name === '') return $gshort !== '' ? $gshort : $gk;
    // 同一分组里放了多个渠道（如企业微信的机器人 / 应用）时，前置分组短名做区分
    $sameGroup = 0;
    foreach ($all as $r) {
        if ((isset($r['group']) ? (string)$r['group'] : '') === $gk) $sameGroup++;
    }
    if ($sameGroup > 1 && $gshort !== '' && strpos($gshort, '/') === false && strlen($gshort) <= 15 && strpos($name, $gshort) === false) {
        return $gshort . ' · ' . $name;
    }
    return $name;
}
/** 必填项的可读名：取真实 label，去掉折叠区字段自带的「渠道 · 」前缀 */
function notify_field_label($key, $channelName, $fields) {
    $label = isset($fields[$key]) ? $fields[$key]['label'] : (string)$key;
    $pos = strpos($label, ' · ');
    if ($pos !== false) {
        $head = substr($label, 0, $pos);
        if ($head === $channelName || ($head !== '' && strpos($channelName, $head) !== false)) {
            $label = substr($label, $pos + 3);
        }
    }
    return trim($label);
}
/**
 * 推送配置体检
 * @param array      $cfgVals yaml_read_simple() 解析出的数组
 * @param array|null $rules   渠道规则表（默认用全局 $NOTIFY_CHANNEL_RULES，仅测试时可注入）
 * @return array array('master'=>bool, 'ok'=>array(array('name'=>..,'missing'=>array()),..), 'warn'=>array(same), 'off'=>int)
 */
function notify_health_check($cfgVals, $rules = null) {
    global $NOTIFY_CHANNEL_RULES;
    if ($rules === null) $rules = $NOTIFY_CHANNEL_RULES;
    if (!is_array($cfgVals)) $cfgVals = array();
    if (!is_array($rules)) $rules = array();
    $fields = notify_field_index();
    $master = notify_val_true(isset($cfgVals['notification_enable']) ? $cfgVals['notification_enable'] : '');
    $ok = array();
    $warn = array();
    $off = 0;
    foreach ($rules as $rule) {
        if (!is_array($rule)) continue;
        $ek = isset($rule['enable']) ? $rule['enable'] : null;
        $required = (isset($rule['required']) && is_array($rule['required'])) ? $rule['required'] : array();
        $enabled = false;
        if ($ek !== null && $ek !== '') {
            // 有独立启用开关：以开关为准
            $enabled = notify_val_true(isset($cfgVals[$ek]) ? $cfgVals[$ek] : '');
        } else {
            // 没有独立启用开关：任一必填键填了内容即视为启用
            foreach ($required as $rk) {
                if (!notify_val_empty(isset($cfgVals[$rk]) ? $cfgVals[$rk] : '')) { $enabled = true; break; }
            }
        }
        if (!$enabled) { $off++; continue; }
        $name = notify_channel_name($rule, $rules);
        $missing = array();
        foreach ($required as $rk) {
            if (notify_val_empty(isset($cfgVals[$rk]) ? $cfgVals[$rk] : '')) $missing[] = notify_field_label($rk, $name, $fields);
        }
        if (!empty($missing)) $warn[] = array('name' => $name, 'missing' => $missing);
        else $ok[] = array('name' => $name, 'missing' => array());
    }
    return array('master' => $master, 'ok' => $ok, 'warn' => $warn, 'off' => $off);
}
/** 体检结果转成页面提示（渲染在「消息推送」分组上方；配色沿用主题变量） */
function notify_check_html($res) {
    if (!is_array($res)) return '';
    $master = !empty($res['master']);
    $oks = isset($res['ok']) ? $res['ok'] : array();
    $warns = isset($res['warn']) ? $res['warn'] : array();
    $names = array();
    foreach ($oks as $it) $names[] = $it['name'];
    $html = '<div class="nchk-wrap" id="notifyCheckBox">';
    $html .= '<div class="nchk-title">🔍 推送配置体检</div>';
    if (!$master) {
        $html .= '<div class="nchk nchk-red">⚠️ 推送总开关（notification_enable）未开启，下面所有渠道都不会生效。</div>';
    }
    if (!empty($warns)) {
        $html .= '<div class="nchk nchk-orange">⚠️ 有 ' . count($warns) . ' 个渠道已启用但缺少必填项：<ul>';
        foreach ($warns as $it) {
            $html .= '<li><b>' . h($it['name']) . '</b>：缺少「' . h(implode('」「', $it['missing'])) . '」</li>';
        }
        $html .= '</ul></div>';
    }
    if (!empty($oks)) {
        if (!$master) {
            $html .= '<div class="nchk nchk-green">✅ 已填齐全 ' . count($oks) . ' 个渠道：' . h(implode('、', $names)) . '（把总开关打开后即生效）</div>';
        } elseif (!empty($warns)) {
            $html .= '<div class="nchk nchk-green">✅ 当前生效 ' . count($oks) . ' 个渠道：' . h(implode('、', $names)) . '</div>';
        } else {
            $html .= '<div class="nchk nchk-green">✅ 体检通过，当前生效 ' . count($oks) . ' 个渠道：' . h(implode('、', $names)) . '</div>';
        }
    } elseif (empty($warns)) {
        $html .= '<div class="nchk nchk-gray">💤 当前没有启用任何推送渠道（共 ' . (int)$res['off'] . ' 个渠道，均未启用）。</div>';
    }
    $html .= '<p class="nchk-foot">说明：体检读取的是<b>已保存</b>的配置（刚保存的这份），只做检查、不会发送任何消息；改完记得点「💾 保存并重启容器」让配置生效。</p>';
    $html .= '</div>';
    return $html;
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
        'update_mode'=> panel_update_mode(),
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
        // v1.14：覆盖前先把当前版本备份到 backups/（保留最近 BACKUP_KEEP 份），出问题可一键回滚
        $bak = backup_current_index(PANEL_VERSION);
        if ($bak === '') {
            return array('ok' => false, 'msg' => '备份当前文件失败（请检查 backups 目录权限），已中止更新');
        }
        if (@file_put_contents(__FILE__, $content) === false) {
            @copy($bak, __FILE__);
            return array('ok' => false, 'msg' => '写入新版本失败，已回滚到备份');
        }
        return array('ok' => true, 'msg' => '更新完成（来源：' . $src . '），旧版本已备份为 ' . basename($bak) . '，页面即将刷新', 'bak' => basename($bak));
    }
    $detail = implode('；', $errors);
    return array('ok' => false, 'msg' => '所有更新源下载失败（' . $detail . '）。请检查服务器网络，或在服务器配置代理后重试');
}

/**
 * 检查三月七小助手镜像是否最新（v1.6+）
 * 对比本地镜像构建时间与 GitHub 最新提交时间，超过 1 小时视为有新版本。
 * 结果缓存 6 小时，避免触发 GitHub API 限流。
 */
/**
 * 获取小助手镜像的候选拉取地址（官方源 + 国内加速镜像，v1.8+）
 * 小助手镜像托管在 ghcr.io；官方源失败时依次尝试加速镜像，加速源拉取成功后 tag 回官方名。
 */
/**
 * 查询 GHCR 官方 latest 镜像的构建时间（v1.9+）
 * 流程：取 token → 查 latest manifest 拿平台 digest → 查 config digest → 拉 config blob 取 created。
 * 返回 ISO 时间字符串；失败返回 ''。
 */
function ghcr_latest_created($timeout = 10) {
    if (!function_exists('curl_init')) return '';
    $repo = 'moesnow/march7thassistant';
    $base = 'https://ghcr.io/v2/' . $repo;
    $hdr = array('Accept: application/json', 'User-Agent: M7A-Panel/' . PANEL_VERSION);
    // 1. token
    $ch = curl_init('https://ghcr.io/token?scope=repository:' . $repo . ':pull');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => $hdr,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $body = (string)curl_exec($ch);
    curl_close($ch);
    $j = json_decode($body, true);
    $token = $j['token'] ?? '';
    if ($token === '') return '';
    $auth = 'Authorization: Bearer ' . $token;
    // 2. latest manifest（可能是多平台 index 或单 manifest）
    $ch = curl_init($base . '/manifests/latest');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => array($auth,
            'Accept: application/vnd.oci.image.index.v1+json',
            'Accept: application/vnd.docker.distribution.manifest.list.v2+json',
            'Accept: application/vnd.oci.image.manifest.v1+json',
            'Accept: application/vnd.docker.distribution.manifest.v2+json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $body = (string)curl_exec($ch);
    curl_close($ch);
    $j = json_decode($body, true);
    if (!is_array($j)) return '';
    if (isset($j['config']['digest'])) {
        // 单平台 manifest：直接查 config blob
        return ghcr_config_created($auth, $base, $j['config']['digest'], $timeout);
    }
    $digest = $j['manifests'][0]['digest'] ?? '';
    if ($digest === '') return '';
    // 3. 平台 manifest → config digest
    $ch = curl_init($base . '/manifests/' . $digest);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => array($auth,
            'Accept: application/vnd.oci.image.manifest.v1+json',
            'Accept: application/vnd.docker.distribution.manifest.v2+json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $body = (string)curl_exec($ch);
    curl_close($ch);
    $j = json_decode($body, true);
    $cfg = $j['config']['digest'] ?? '';
    if ($cfg === '') return '';
    return ghcr_config_created($auth, $base, $cfg, $timeout);
}

/** GHCR config blob 取 created（blob 会 307 到对象存储，需跟随重定向） */
function ghcr_config_created($auth, $base, $cfg, $timeout) {
    $ch = curl_init($base . '/blobs/' . $cfg);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array($auth, 'Accept: application/vnd.oci.image.config.v1+json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $body = (string)curl_exec($ch);
    curl_close($ch);
    $j = json_decode($body, true);
    return is_array($j) ? ($j['created'] ?? '') : '';
}

function assistant_image_candidates($image) {
    // 已知 registry 前缀（官方源 + 国内加速），无论当前镜像用哪个前缀，都生成完整候选列表
    $prefs = array('ghcr.io/', 'ghcr.nju.edu.cn/', 'ghcr.m.daocloud.io/', 'ghcr.dockerproxy.com/');
    $rest = '';
    foreach ($prefs as $pre) {
        if (stripos($image, $pre) === 0) { $rest = substr($image, strlen($pre)); break; }
    }
    if ($rest === '') return array($image); // 未知 registry，保持原样
    $cands = array();
    foreach ($prefs as $pre) $cands[] = $pre . $rest;
    return array_values(array_unique($cands));
}

/**
 * 更新三月七小助手镜像（v1.8+）
 * 依次尝试官方源与加速镜像，成功拉取后 tag 回官方镜像名，再重建容器。
 */
function image_update() {
    $r1 = run_cmd('docker inspect --format "{{.Config.Image}}" ' . escapeshellarg(instance_container()));
    $image = trim($r1['out']);
    if ($r1['code'] !== 0 || $image === '' || stripos($image, 'Error') !== false || stripos($image, 'not found') !== false) {
        return array('ok' => false, 'err' => '无法获取当前容器镜像（容器未运行？）：' . trim($r1['out']));
    }
    $cands = assistant_image_candidates($image);
    $used = '';
    $detail = array();
    foreach ($cands as $i => $cand) {
        $r = run_cmd('docker pull ' . escapeshellarg($cand));
        if ($r['code'] === 0) { $used = $cand; break; }
        $detail[] = ($i === 0 ? '官方源' : '加速镜像' . $i) . '失败：' . trim($r['out']);
    }
    if ($used === '') {
        return array('ok' => false, 'err' => '所有镜像源拉取失败：' . implode(' | ', $detail));
    }
    // 加速源拉取成功：tag 回官方镜像名，保证 compose 能用原名找到
    if ($used !== $image) {
        run_cmd('docker tag ' . escapeshellarg($used) . ' ' . escapeshellarg($image));
    }
    $r2 = compose('up -d');
    if ($r2['code'] !== 0) {
        return array('ok' => false, 'err' => '镜像已拉取但容器重建失败：' . trim($r2['out']));
    }
    // 清缓存，让镜像检查下次强制刷新
    @unlink(__DIR__ . '/.image_check_cache.json');
    // 显示实际使用的源名
    $srcName = '镜像源';
    $prefs = array('ghcr.io/' => '官方源', 'ghcr.nju.edu.cn/' => '南大镜像', 'ghcr.m.daocloud.io/' => 'DaoCloud', 'ghcr.dockerproxy.com/' => 'dockerproxy');
    foreach ($prefs as $pre => $label) {
        if (stripos($used, $pre) === 0) { $srcName = $label; break; }
    }
    return array('ok' => true, 'src' => $srcName, 'msg' => '镜像已更新（' . $srcName . '），容器已重建');
}

function image_check($force = false) {
    $cacheFile = __DIR__ . '/.image_check_cache.json';
    if (!$force && is_file($cacheFile)) {
        $c = @json_decode(@file_get_contents($cacheFile), true);
        if (is_array($c) && isset($c['ts']) && time() - (int)$c['ts'] < 21600) return $c;
    }
    $r1 = run_cmd('docker inspect --format "{{.Config.Image}}" ' . escapeshellarg(instance_container()));
    $image = trim($r1['out']);
    if ($r1['code'] !== 0 || $image === '' || stripos($image, 'Error') !== false || stripos($image, 'not found') !== false) {
        $err = trim($r1['out']);
        $data = array('ok' => false, 'err' => '无法获取容器镜像' . ($err !== '' ? '：' . $err : '（容器未运行？）'), 'ts' => time());
        @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }
    $r2 = run_cmd('docker image inspect --format "{{.Created}}" ' . escapeshellarg($image));
    $local = trim($r2['out']);
    // v1.9：远程版本改为查 GHCR 官方 latest 镜像构建时间（代码提交时间会误报，官方镜像发布远慢于提交）
    $remote = ghcr_latest_created(10);
    $remote_unknown = ($remote === '');
    $has_update = false;
    $lt = strtotime($local);
    $rt = strtotime($remote);
    if ($lt !== false && $rt !== false && $rt > $lt + 3600) $has_update = true;
    $data = array(
        'ok' => true,
        'image' => $image,
        'local' => $local,
        'remote' => $remote,
        'remote_unknown' => $remote_unknown,
        'has_update' => $has_update,
        'ts' => time(),
    );
    @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    return $data;
}

/* ===== 资源监控（v1.13+） ===== */
function monitor_data_file() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/monitor_' . preg_replace('/[^A-Za-z0-9_-]/', '_', instance_container()) . '.json';
}
function monitor_read() {
    $f = monitor_data_file();
    if (!is_file($f)) return array('points' => array(), 'minutes' => array(), 'meta' => array('host' => null, 'hostTs' => 0, 'lastSample' => 0, 'lastMin' => 0, 'lastNet' => null));
    $d = @json_decode(@file_get_contents($f), true);
    if (!is_array($d) || !isset($d['points'])) return array('points' => array(), 'minutes' => array(), 'meta' => array('host' => null, 'hostTs' => 0, 'lastSample' => 0, 'lastMin' => 0, 'lastNet' => null));
    if (!isset($d['meta'])) $d['meta'] = array('host' => null, 'hostTs' => 0, 'lastSample' => 0, 'lastMin' => 0, 'lastNet' => null);
    if (!isset($d['minutes'])) $d['minutes'] = array();
    return $d;
}
function monitor_write($d) {
    $f = monitor_data_file();
    return @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)) !== false;
}
function monitor_parse_bytes($s) {
    $s = strtolower(trim($s));
    if ($s === '') return 0;
    if (preg_match('/^([\d.]+)\s*([kmgt]?i?b)?$/', $s, $m)) {
        $v = (float)$m[1];
        $u = isset($m[2]) ? $m[2] : '';
        if ($u === '' || $u === 'b') return $v;
        if ($u === 'kb' || $u === 'kib') return $v * 1024;
        if ($u === 'mb' || $u === 'mib') return $v * 1024 * 1024;
        if ($u === 'gb' || $u === 'gib') return $v * 1024 * 1024 * 1024;
        if ($u === 'tb' || $u === 'tib') return $v * 1024 * 1024 * 1024 * 1024;
    }
    return 0;
}
function monitor_docker_stats() {
    $r = run_cmd('docker stats --no-stream --format "{{.CPUPerc}}|{{.MemUsage}}|{{.MemPerc}}|{{.NetIO}}" ' . escapeshellarg(instance_container()) . ' 2>&1');
    if ($r['code'] !== 0) return null;
    $parts = explode('|', trim($r['out']));
    if (count($parts) < 4) return null;
    $mem = explode('/', $parts[1]);
    $net = explode('/', $parts[3]);
    return array(
        'cpu'      => (float)str_replace('%', '', $parts[0]),
        'memUsed'  => monitor_parse_bytes(isset($mem[0]) ? $mem[0] : '0'),
        'memTotal' => monitor_parse_bytes(isset($mem[1]) ? $mem[1] : '0'),
        'memPct'   => (float)str_replace('%', '', $parts[2]),
        'netIn'    => monitor_parse_bytes(isset($net[0]) ? $net[0] : '0'),
        'netOut'   => monitor_parse_bytes(isset($net[1]) ? $net[1] : '0'),
    );
}
function monitor_container_started() {
    $r = run_cmd('docker inspect -f "{{.State.StartedAt}}" ' . escapeshellarg(instance_container()) . ' 2>&1');
    $ts = strtotime(trim($r['out']));
    return ($ts !== false && $ts > 0) ? $ts : 0;
}
function monitor_host_info() {
    $d = monitor_read();
    if (!empty($d['meta']['host']) && time() - (int)$d['meta']['hostTs'] < 600) return $d['meta']['host'];
    $host = array('name' => php_uname('n'), 'os' => '', 'docker' => '', 'cores' => 0, 'memTotal' => 0, 'memAvail' => 0, 'diskTotal' => 0, 'diskUsed' => 0);
    $r = run_cmd('free -b');
    foreach (explode("\n", $r['out']) as $line) {
        if (preg_match('/^Mem:\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
            $host['memTotal'] = (int)$m[1];
            $host['memAvail'] = (int)$m[2];
        }
    }
    $r = run_cmd('df -B1 /');
    $lines = explode("\n", trim($r['out']));
    if (count($lines) >= 2 && preg_match('/^\S+\s+(\d+)\s+(\d+)\s+\d+\s+\d+%/', trim($lines[1]), $m)) {
        $host['diskTotal'] = (int)$m[1];
        $host['diskUsed']  = (int)$m[2];
    }
    $r = run_cmd('cat /etc/os-release');
    foreach (explode("\n", $r['out']) as $line) {
        if (preg_match('/^PRETTY_NAME="?(.*?)"?$/', trim($line), $m)) { $host['os'] = $m[1]; break; }
    }
    $r = run_cmd('docker version --format "{{.Server.Version}}" 2>&1');
    $host['docker'] = trim($r['out']);
    $r = run_cmd('nproc');
    $host['cores'] = (int)trim($r['out']);
    $d['meta']['host'] = $host;
    $d['meta']['hostTs'] = time();
    monitor_write($d);
    return $host;
}
function monitor_sample($interval = 1) {
    $d = monitor_read();
    $now = time();
    $iv = max(1, (int)$interval);
    if ($now - (int)$d['meta']['lastSample'] < $iv) {
        return array('sampled' => false, 'data' => $d, 'running' => container_is_running());
    }
    $running = container_is_running();
    $started = $running ? monitor_container_started() : 0;
    $p = array('t' => $now, 'cpu' => 0.0, 'mem' => 0.0, 'disk' => 0.0, 'netIn' => 0.0, 'netOut' => 0.0, 'load' => 0.0, 'uptime' => 0);
    if ($running) {
        $t0 = microtime(true);
        $s1 = monitor_docker_stats();
        if ($s1 !== null) usleep(400000);
        $s2 = monitor_docker_stats();
        $t1 = microtime(true);
        if ($s1 !== null && $s2 !== null) {
            // 瞬时 CPU：docker stats 单次输出是容器启动以来平均值，用两次快照差值换算
            $wall1 = $started > 0 ? $t0 - $started : 0;
            $wall2 = $started > 0 ? $t1 - $started : 0;
            if ($wall1 > 1 && $wall2 > $wall1) {
                $cpu1 = $s1['cpu'] / 100.0 * $wall1;
                $cpu2 = $s2['cpu'] / 100.0 * $wall2;
                $p['cpu'] = max(0.0, ($cpu2 - $cpu1) / ($wall2 - $wall1) * 100.0);
            } else {
                $p['cpu'] = $s2['cpu'];
            }
            $p['mem']  = $s2['memPct'];
            $p['uptime'] = $started > 0 ? max(0, (int)($t1 - $started)) : 0;
            $lastNet = isset($d['meta']['lastNet']) ? $d['meta']['lastNet'] : null;
            $dt = $now - (int)$d['meta']['lastSample'];
            if ($lastNet !== null && $dt > 0) {
                $p['netIn']  = max(0.0, ($s2['netIn']  - $lastNet['in'])  / $dt);
                $p['netOut'] = max(0.0, ($s2['netOut'] - $lastNet['out']) / $dt);
            }
            $d['meta']['lastNet'] = array('in' => $s2['netIn'], 'out' => $s2['netOut']);
        } else {
            $d['meta']['lastNet'] = null;
        }
    } else {
        $d['meta']['lastNet'] = null;
    }
    $la = @file_get_contents('/proc/loadavg');
    if ($la !== false && preg_match('/^([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $la, $m)) {
        $p['load'] = (float)$m[1];
    }
    $r = run_cmd('df -B1 /');
    $lines = explode("\n", trim($r['out']));
    if (count($lines) >= 2 && preg_match('/^\S+\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%/', trim($lines[1]), $m)) {
        $p['disk'] = (int)$m[4];
    }
    $d['points'][] = $p;
    if (count($d['points']) > 3600) $d['points'] = array_slice($d['points'], -3600);
    // 分钟级聚合：跨分钟时取最近 60 秒平均值，保留最近 1440 点（24 小时）
    $min = (int)floor($now / 60);
    if ($min != (int)$d['meta']['lastMin']) {
        $cut = $now - 60;
        $sCpu = 0.0; $sMem = 0.0; $sDisk = 0.0; $cnt = 0;
        foreach ($d['points'] as $pp) {
            if ($pp['t'] >= $cut) { $sCpu += $pp['cpu']; $sMem += $pp['mem']; $sDisk += $pp['disk']; $cnt++; }
        }
        if ($cnt > 0) {
            $d['minutes'][] = array(
                't'    => $min * 60,
                'cpu'  => round($sCpu / $cnt, 1),
                'mem'  => round($sMem / $cnt, 1),
                'disk' => round($sDisk / $cnt, 1),
            );
            if (count($d['minutes']) > 1440) $d['minutes'] = array_slice($d['minutes'], -1440);
        }
        $d['meta']['lastMin'] = $min;
    }
    $d['meta']['lastSample'] = $now;
    monitor_write($d);
    return array('sampled' => true, 'data' => $d, 'running' => $running);
}
function monitor_interval() {
    $cfg = panel_config_load();
    return isset($cfg['monitor_interval']) ? max(1, (int)$cfg['monitor_interval']) : 1;
}

/* ===== 通用格式化 ===== */
function format_size($bytes) {
    $b = (float)$bytes;
    if ($b >= 1024 * 1024 * 1024) return round($b / 1024 / 1024 / 1024, 1) . ' GB';
    if ($b >= 1024 * 1024) return round($b / 1024 / 1024, 1) . ' MB';
    if ($b >= 1024) return round($b / 1024, 1) . ' KB';
    return (int)$b . ' B';
}
function format_duration($seconds) {
    $s = max(0, (int)$seconds);
    if ($s >= 3600) return floor($s / 3600) . ' 小时 ' . floor($s % 3600 / 60) . ' 分';
    if ($s >= 60)   return floor($s / 60) . ' 分 ' . ($s % 60) . ' 秒';
    return $s . ' 秒';
}

/* ===== 版本备份 / 一键回滚（v1.14+） ===== */

/** 备份目录（不存在则创建，避免告警） */
function backups_dir() {
    $dir = BACKUP_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/** 扫描 backups/ 返回备份列表，按时间倒序；文件名格式 index_{版本}_{YmdHis}.php */
function backups_list() {
    $out = array();
    $dir = backups_dir();
    if (!is_dir($dir)) return $out;
    $files = glob($dir . '/index_*.php');
    if (!$files) return $out;
    foreach ($files as $f) {
        if (!is_file($f)) continue;
        $base = basename($f);
        if (!preg_match('/^index_(.*)_(\d{14})\.php$/', $base, $m)) continue;
        $ver = ($m[1] !== '' && $m[1] !== null) ? $m[1] : '未知';
        $ts = mktime(
            (int)substr($m[2], 8, 2), (int)substr($m[2], 10, 2), (int)substr($m[2], 12, 2),
            (int)substr($m[2], 4, 2), (int)substr($m[2], 6, 2), (int)substr($m[2], 0, 4)
        );
        if ($ts === false || $ts <= 0) $ts = (int)@filemtime($f);
        $out[] = array(
            'file'    => $base,
            'path'    => $f,
            'version' => $ver,
            'time'    => $ts,
            'timeStr' => $ts > 0 ? date('Y-m-d H:i:s', $ts) : '未知',
            'size'    => (int)@filesize($f),
        );
    }
    usort($out, function($a, $b) {
        if ($a['time'] === $b['time']) return strcmp($b['file'], $a['file']);
        return $b['time'] - $a['time'];
    });
    return $out;
}

/** 只保留最近 $keep 份备份（按时间倒序），返回删除份数 */
function backups_prune($keep = BACKUP_KEEP) {
    $keep = max(1, (int)$keep);
    $list = backups_list();
    if (count($list) <= $keep) return 0;
    $removed = 0;
    foreach (array_slice($list, $keep) as $item) {
        if (!empty($item['path']) && @unlink($item['path'])) $removed++;
    }
    return $removed;
}

/**
 * 备份当前 index.php 到 backups/index_{版本}_{YmdHis}.php，并清理旧备份。
 * 成功返回备份文件绝对路径，失败返回 ''。
 */
function backup_current_index($version = '') {
    $dir = backups_dir();
    if (!is_dir($dir)) return '';
    $ver = trim((string)$version);
    if ($ver === '') $ver = PANEL_VERSION;
    $ver = preg_replace('/[^A-Za-z0-9._-]/', '', $ver);
    if ($ver === '') $ver = 'unknown';
    $file = $dir . '/index_' . $ver . '_' . date('YmdHis') . '.php';
    $ok = @copy(__FILE__, $file);
    if (!$ok) {
        $content = @file_get_contents(__FILE__);
        if ($content === false || $content === '') return '';
        $ok = @file_put_contents($file, $content, LOCK_EX) !== false;
    }
    if (!$ok) return '';
    @chmod($file, 0644);
    backups_prune();
    return $file;
}

/**
 * 一键回滚：把指定备份复制为 index.php。
 * 安全校验：仅接受 backups/index_*.php 的文件名，并二次确认文件真实路径位于备份目录内（防路径穿越）；
 * 回滚前先把当前版本也备份一次（防手滑）。
 */
function backup_rollback($file) {
    $dir = backups_dir();
    $base = basename(trim((string)$file));
    if ($base === '' || !preg_match('/^index_.*\.php$/', $base)) {
        return array('ok' => false, 'msg' => '备份文件名不合法，已中止回滚');
    }
    $target = $dir . '/' . $base;
    if (!is_file($target)) {
        return array('ok' => false, 'msg' => '备份文件不存在，已中止回滚');
    }
    $realDir = realpath($dir);
    $realTarget = realpath($target);
    if ($realDir === false || $realTarget === false
        || strpos($realTarget, rtrim($realDir, '/\\') . DIRECTORY_SEPARATOR) !== 0) {
        return array('ok' => false, 'msg' => '备份文件路径非法，已中止回滚');
    }
    $content = @file_get_contents($target);
    if ($content === false || strlen($content) < 200 || stripos(ltrim($content), '<?php') !== 0) {
        return array('ok' => false, 'msg' => '备份内容异常（不是有效的面板文件），已中止回滚');
    }
    $safety = backup_current_index(PANEL_VERSION);   // 回滚前先备份当前版本
    if (@file_put_contents(__FILE__, $content, LOCK_EX) === false) {
        return array('ok' => false, 'msg' => '写入 index.php 失败，请检查面板目录权限');
    }
    return array(
        'ok'  => true,
        'msg' => '已回滚到备份 ' . $base . ($safety !== '' ? '（回滚前的版本已备份为 ' . basename($safety) . '）' : '') . '，页面即将刷新',
        'file' => $base,
    );
}

/* ===== 任务执行历史（v1.14+） ===== */

/** 历史数据文件：data/history_{容器}.json（按实例区分） */
function history_data_file() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/history_' . preg_replace('/[^A-Za-z0-9_-]/', '_', instance_container()) . '.json';
}
function history_read() {
    $f = history_data_file();
    if (!is_file($f)) return array('items' => array());
    $d = @json_decode(@file_get_contents($f), true);
    if (!is_array($d) || !isset($d['items']) || !is_array($d['items'])) return array('items' => array());
    return array('items' => array_values($d['items']));
}
function history_write($d) {
    return @file_put_contents(history_data_file(), json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

/** 追加一条 running 记录（环形保留最近 HISTORY_KEEP 条） */
function history_add($taskKey, $taskLabel) {
    $d = history_read();
    $maxId = 0;
    foreach ($d['items'] as $it) {
        $id = isset($it['id']) ? (int)$it['id'] : 0;
        if ($id > $maxId) $maxId = $id;
    }
    $d['items'][] = array(
        'id'         => $maxId + 1,
        'task_key'   => (string)$taskKey,
        'task_label' => (string)$taskLabel,
        'instance'   => instance_container(),
        'start_ts'   => time(),
        'end_ts'     => 0,
        'status'     => 'running',
    );
    if (count($d['items']) > HISTORY_KEEP) $d['items'] = array_slice($d['items'], -HISTORY_KEEP);
    return history_write($d);
}

/**
 * 结束判定：小助手不在日志里写「任务结束」标记，这里用日志静默时间推断。
 * - 取当前实例最新日志文件 mtime，静默超过 HISTORY_IDLE_SECONDS 视为任务已结束（done，end_ts=mtime）；
 * - 兜底：容器未运行且日志长时间未更新，视为中断（aborted）。
 * 惰性调用：渲染或查询历史前同步一次即可。
 */
function history_sync() {
    $d = history_read();
    $now = time();
    $logPath = latest_log_path();
    $mtime = ($logPath && is_file($logPath)) ? (int)@filemtime($logPath) : 0;
    $changed = false;
    $containerRunning = null;
    foreach ($d['items'] as $i => $it) {
        if (!isset($it['status']) || $it['status'] !== 'running') continue;
        $start = isset($it['start_ts']) ? (int)$it['start_ts'] : 0;
        if ($mtime === 0) {
            // 没有日志文件：超过两倍静默时间仍无日志，按中断处理，避免记录永远挂在「运行中」
            if ($now - $start > HISTORY_IDLE_SECONDS * 2) {
                $d['items'][$i]['end_ts'] = $now;
                $d['items'][$i]['status'] = 'aborted';
                $changed = true;
            }
            continue;
        }
        if ($now - $mtime <= HISTORY_IDLE_SECONDS) continue;         // 日志还在更新 → 任务仍在跑
        if ($now - $start <= HISTORY_IDLE_SECONDS) continue;         // 刚启动不足静默时长，先不判定
        if ($containerRunning === null) $containerRunning = container_is_running();
        $end = $mtime > $start ? $mtime : $start;                    // 兜底：避免耗时出现负数
        $d['items'][$i]['end_ts'] = $end;
        $d['items'][$i]['status'] = (!$containerRunning && ($now - $mtime) > HISTORY_IDLE_SECONDS * 3) ? 'aborted' : 'done';
        $changed = true;
    }
    if ($changed) history_write($d);
    return $d;
}

/** 今日统计：今日执行次数 / 成功次数 */
function history_today_stats($items) {
    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $count = 0; $ok = 0;
    foreach ($items as $it) {
        if ((int)($it['start_ts'] ?? 0) < $todayStart) continue;
        $count++;
        if (($it['status'] ?? '') === 'done') $ok++;
    }
    return array('count' => $count, 'ok' => $ok);
}

/** 历史记录 → 界面展示数据（最新在前），状态文案与颜色在各主题下通用 */
function history_view($items) {
    $now = time();
    $statusMap = array(
        'running' => array('label' => '运行中', 'color' => 'var(--blue,#3b82f6)'),
        'done'    => array('label' => '已完成', 'color' => 'var(--green,#10b981)'),
        'aborted' => array('label' => '已中断', 'color' => 'var(--red,#ef4444)'),
    );
    $out = array();
    foreach (array_reverse($items) as $it) {
        $start = isset($it['start_ts']) ? (int)$it['start_ts'] : 0;
        $end   = isset($it['end_ts']) ? (int)$it['end_ts'] : 0;
        $st    = isset($it['status']) ? (string)$it['status'] : 'running';
        if ($st === 'running') {
            $dur = '进行中 ' . format_duration($now - $start);
        } else {
            $dur = format_duration(($end > 0 ? $end : $start) - $start);
        }
        $s = isset($statusMap[$st]) ? $statusMap[$st] : array('label' => $st, 'color' => 'var(--muted,#9ca3af)');
        $out[] = array(
            'id'           => isset($it['id']) ? (int)$it['id'] : 0,
            'task_label'   => isset($it['task_label']) ? (string)$it['task_label'] : '',
            'start_str'    => $start > 0 ? date('m-d H:i:s', $start) : '--',
            'duration_str' => $dur,
            'status'       => $st,
            'status_label' => $s['label'],
            'status_color' => $s['color'],
        );
    }
    return $out;
}

/* ===== 计划任务（v1.15+）=====
 * 设计要点：
 * - 数据存面板自己的 data/schedule.json，不写小助手的 config.yaml（那里是程序常驻时才生效的
 *   scheduled_tasks，Docker 按需起容器的场景落不到实处）；
 * - 由宿主机（宝塔 → 计划任务）每分钟请求一次 ?scheduler=1&key=xxx，面板自己判断是否命中时间点；
 * - 命中判定带 30 分钟补跑窗口（兼容 cron 间隔大于 1 分钟、或服务器偶发卡顿的情况）；
 * - 用 last_run 记录已触发的「日期+时间点」，避免同一时间点被重复触发。
 */
function schedule_data_file() {
    $dir = dirname(history_data_file());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/schedule.json';
}

/** 默认结构；任何异常都回落到它，保证面板不会因为脏数据崩掉 */
function schedule_default() {
    return array('conflict' => 'skip', 'tasks' => array());
}

function schedule_load() {
    $file = schedule_data_file();
    if (!is_file($file)) return schedule_default();
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return schedule_default();
    $d = json_decode($raw, true);
    if (!is_array($d)) return schedule_default();
    $out = schedule_default();
    if (isset($d['conflict']) && $d['conflict'] === 'stop') $out['conflict'] = 'stop';
    if (isset($d['tasks']) && is_array($d['tasks'])) {
        foreach ($d['tasks'] as $t) {
            if (!is_array($t)) continue;
            $out['tasks'][] = array(
                'id'          => isset($t['id']) ? (string)$t['id'] : '',
                'name'        => isset($t['name']) ? (string)$t['name'] : '',
                'time'        => isset($t['time']) ? (string)$t['time'] : '',
                'days'        => (isset($t['days']) && is_array($t['days'])) ? array_values($t['days']) : array(),
                'args'        => isset($t['args']) ? (string)$t['args'] : '',
                'enabled'     => !empty($t['enabled']),
                'last_run'    => isset($t['last_run']) ? (string)$t['last_run'] : '',
                'last_result' => isset($t['last_result']) ? (string)$t['last_result'] : '',
            );
        }
    }
    return $out;
}

function schedule_save($data) {
    if (!is_array($data)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    return @file_put_contents(schedule_data_file(), $json, LOCK_EX) !== false;
}

/** 星期清洗：仅接受 1-7（ISO，1=周一）的字符串/数字，去重后升序；空数组表示每天 */
function schedule_normalize_days($days) {
    $out = array();
    if (is_array($days)) {
        foreach ($days as $d) {
            $n = (int)$d;
            if ($n >= 1 && $n <= 7 && !in_array($n, $out, true)) $out[] = $n;
        }
    }
    sort($out);
    return $out;
}

/**
 * 校验计划任务输入。
 * @return string 空字符串表示通过；否则为错误提示。$clean 输出规范化后的数据。
 */
function schedule_days_label($days) {
    $days = schedule_normalize_days($days);
    if (!$days) return '每天';
    $name = array(1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日');
    $out = array();
    foreach ($days as $n) $out[] = '周' . $name[$n];
    return implode('、', $out);
}
function schedule_validate($input, &$clean = null) {
    global $TASKS;
    $clean = null;
    $input = is_array($input) ? $input : array();

    $name = trim((string)(isset($input['name']) ? $input['name'] : ''));
    if ($name === '') return '任务名称不能为空';
    // 按字符数（非字节数）限制长度：优先 mbstring，未装则用正则按 UTF-8 字符计数
    if (function_exists('mb_strlen')) {
        $len = mb_strlen($name, 'UTF-8');
    } else {
        $len = preg_match_all('/./us', $name, $tmp) ? count($tmp[0]) : strlen($name);
    }
    if ($len > 40) return '任务名称过长（最多 40 个字）';

    $time = trim((string)(isset($input['time']) ? $input['time'] : ''));
    if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) return '时间格式不正确，请用 24 小时制（如 04:00）';
    $tp = explode(':', $time);
    $time = sprintf('%02d:%02d', (int)$tp[0], (int)$tp[1]);   // 规范化为 H:i

    $rawDays = isset($input['days']) ? $input['days'] : array();
    if (!is_array($rawDays)) $rawDays = array($rawDays);
    $days = array();
    foreach ($rawDays as $d) {
        if (is_array($d)) return '星期选择不合法（只能选周一至周日）';
        $s = trim((string)$d);
        if (!preg_match('/^[1-7]$/', $s)) return '星期选择不合法（只能选周一至周日）';
        $n = (int)$s;
        if (!in_array($n, $days, true)) $days[] = $n;
    }
    sort($days);   // 空数组 = 每天

    $args = trim((string)(isset($input['args']) ? $input['args'] : ''));
    if ($args === '') return '请选择要执行的任务';
    if (!isset($TASKS[$args])) return '任务「' . $args . '」不在可用任务列表中';

    $clean = array('name' => $name, 'time' => $time, 'days' => $days, 'args' => $args);
    return '';
}

/**
 * 判断某条计划任务此刻是否应当触发。
 * @param array $task 计划任务记录
 * @param int|null $now 当前时间戳
 * @param string $slot 命中时输出该时间点标识（Ymd-Hi），用于防重复
 * @return bool
 */
function schedule_due($task, $now = null, &$slot = '') {
    $slot = '';
    if (!is_array($task) || empty($task['enabled'])) return false;
    $time = trim((string)(isset($task['time']) ? $task['time'] : ''));
    if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) return false;
    $tp = explode(':', $time);
    $hh = (int)$tp[0];
    $mi = (int)$tp[1];

    if ($now === null) $now = time();
    $now = (int)$now;

    $days = schedule_normalize_days(isset($task['days']) ? $task['days'] : array());
    if ($days) {
        $w = (int)date('N', $now);        // 1=周一 … 7=周日
        if (!in_array($w, $days, true)) return false;
    }

    $target = mktime($hh, $mi, 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
    $diff = $now - $target;
    if ($diff < 0) return false;                          // 今天还没到点
    if ($diff > SCHEDULE_WINDOW_SECONDS) return false;     // 超出补跑窗口，今天不再触发

    $slot = date('Ymd-Hi', $target);
    if ((string)(isset($task['last_run']) ? $task['last_run'] : '') === $slot) return false;   // 已触发过
    return true;
}

/**
 * 执行一轮检查：命中即起任务。返回 JSON 友好的摘要，供 cron 接口与「立即运行一次」复用。
 */
function schedule_run_due($now = null) {
    $d = schedule_load();
    if ($now === null) $now = time();
    $now = (int)$now;
    $ran = array(); $skipped = array();
    $changed = false;

    $busy = null;   // 惰性判断：只在真的命中且容器在跑时才算一次
    foreach ($d['tasks'] as $i => $t) {
        $slot = '';
        if (!schedule_due($t, $now, $slot)) continue;

        $name = ($t['name'] !== '') ? $t['name'] : $t['id'];
        // 先标记该时间点已处理，避免容器没起来/冲突跳过时每分钟刷屏
        $d['tasks'][$i]['last_run'] = $slot;
        $changed = true;

        if (!container_is_running()) {
            $d['tasks'][$i]['last_result'] = '跳过（容器未运行）';
            $skipped[] = $name;
            continue;
        }

        if ($busy === null) {
            $busy = false;
            $hist = history_sync();
            foreach ($hist['items'] as $it) {
                if (isset($it['status']) && $it['status'] === 'running') { $busy = true; break; }
            }
        }
        if ($busy) {
            if ($d['conflict'] === 'stop') {
                $rr = compose('restart');
                if ($rr['code'] !== 0) {
                    $d['tasks'][$i]['last_result'] = '失败：停掉当前任务失败（' . trim($rr['out']) . '）';
                    continue;
                }
                $busy = false;
            } else {
                $d['tasks'][$i]['last_result'] = '跳过（有任务在跑）';
                $skipped[] = $name;
                continue;
            }
        }

        $args = (string)$t['args'];
        $label = isset($GLOBALS['TASKS'][$args]['label']) ? $GLOBALS['TASKS'][$args]['label'] : $args;
        $sr = task_start($args);
        if ($sr['code'] === 0) {
            history_add($args, $label);
            $d['tasks'][$i]['last_result'] = '已触发';
            $ran[] = $name;
            $busy = true;
        } else {
            $d['tasks'][$i]['last_result'] = '失败：' . trim($sr['out']);
        }
    }

    if ($changed) schedule_save($d);
    return array(
        'ok'      => true,
        'time'    => date('Y-m-d H:i:s', $now),
        'ran'     => $ran,
        'skipped' => $skipped,
    );
}

/**
 * 只读统计 config.yaml 里本体自带的 scheduled_tasks 条数（用于提示用户两处不要重复配）。
 * 只按顶层块扫描（块以顶格非该键的行或文件结尾为界），解析失败返回 0，不报错、不抛异常。
 */
function config_scheduled_tasks_count() {
    $raw = config_read_raw(262144);
    if ($raw === null || $raw === '') return 0;
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $inBlock = false;
    $count = 0;
    foreach ($lines as $line) {
        if (!$inBlock) {
            if (preg_match('/^scheduled_tasks\s*:/', $line)) $inBlock = true;
            continue;
        }
        if (trim($line) === '') continue;
        if (!preg_match('/^\s/', $line)) { $inBlock = false; continue; }   // 回到顶层，块结束
        if (preg_match('/^\s*-\s*id\s*:/', $line)) $count++;
    }
    return $count;
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
    if ($ajax === 'image_check') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(image_check(!empty($_GET['force'])), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ajax === 'monitor') {
        header('Content-Type: application/json; charset=utf-8');
        $iv = isset($_GET['iv']) ? max(1, (int)$_GET['iv']) : monitor_interval();
        $r = monitor_sample($iv);
        $d = $r['data'];
        echo json_encode(array(
            'ok'      => true,
            'sampled' => $r['sampled'],
            'running' => $r['running'],
            'points'  => isset($d['points']) ? $d['points'] : array(),
            'minutes' => isset($d['minutes']) ? $d['minutes'] : array(),
            'host'    => isset($d['meta']['host']) ? $d['meta']['host'] : monitor_host_info(),
            'lastSample' => isset($d['meta']['lastSample']) ? (int)$d['meta']['lastSample'] : 0,
            'interval'   => $iv,
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    // 任务执行历史（v1.14+）：返回前先做一次结束判定
    if ($ajax === 'history') {
        header('Content-Type: application/json; charset=utf-8');
        $d = history_sync();
        $stats = history_today_stats($d['items']);
        echo json_encode(array(
            'ok'          => true,
            'items'       => history_view($d['items']),
            'today_count' => $stats['count'],
            'today_ok'    => $stats['ok'],
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit;
}

/* ===== 资源监控：计划任务采样接口（宝塔 crontab 用，需带 key） ===== */
if (isset($_GET['monitor_sampler']) && $_GET['monitor_sampler'] === '1') {
    $cfg = panel_config_load();
    $key = isset($cfg['monitor_key']) ? $cfg['monitor_key'] : '';
    if ($key === '') {
        $key = bin2hex(random_bytes(8));
        panel_config_save(array('monitor_key' => $key));
    }
    header('Content-Type: application/json; charset=utf-8');
    if (($_GET['key'] ?? '') !== $key) {
        echo json_encode(array('ok' => false, 'msg' => 'invalid key'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $r = monitor_sample(60);
    echo json_encode(array('ok' => true, 'sampled' => $r['sampled'], 'running' => $r['running'], 'points' => count($r['data']['points'])), JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== 计划任务触发接口（v1.15+，宝塔 crontab 每分钟调用一次，必须带 key）=====
 * 无 key / key 不匹配一律 403，避免公网被人随意触发任务。
 */
if (isset($_GET['scheduler']) && $_GET['scheduler'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $schedCfg = panel_config_load();
    $schedKey = isset($schedCfg['scheduler_key']) ? (string)$schedCfg['scheduler_key'] : '';
    $reqKey   = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($schedKey === '' || $reqKey === '' || !hash_equals($schedKey, $reqKey)) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'msg' => 'forbidden'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(schedule_run_due(), JSON_UNESCAPED_UNICODE);
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
$notifyCheck = null;   // v1.15+：推送配置体检结果（仅「🔍 保存并体检」提交后填充，读的是已保存的配置）

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
            if ($r['code'] === 0) {
                // v1.14：任务启动成功记一条执行历史（状态 running，靠日志静默时间判定结束）
                history_add($action, $TASKS[$action]['label']);
                $msg = '任务「' . $TASKS[$action]['label'] . '」已后台启动';
            } else {
                $msg = '任务启动失败：' . $r['out'];
            }
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
        elseif ($action === 'update_image') {
            $r = image_update();
            echo json_encode($r, JSON_UNESCAPED_UNICODE);
            exit;
        }
        elseif ($action === 'stop_task') {
            // v1.10：小助手任务进程即容器主进程（PID 1），Linux 下 PID 1 默认忽略 SIGTERM，
            // 旧方案 pkill -15 无法生效；停止任务只能通过重启容器实现
            $r = compose('restart');
            if ($r['code'] === 0) {
                $msg = '⏹️ 任务已停止（容器已重启），如需继续请重新启动任务';
            } else {
                $err = '容器重启失败：' . $r['out'];
            }
        }
        elseif ($action === 'stop_loop') {
            // v1.11：货币战争循环是 docker exec 拉起的子进程（非 PID 1），
            // 可正常接收 SIGTERM，pkill -15 即可精准停止循环，无需重启容器
            $r = run_cmd('docker exec ' . escapeshellarg(instance_container()) . ' pkill -15 -f "main.py currencywarsloop"');
            if ($r['code'] === 0) {
                $msg = '🛑 货币战争循环已停止（仅结束循环子进程，容器未重启）';
            } else {
                $err = '循环停止失败（可能循环未在运行）：' . $r['out'];
            }
        }
        elseif ($action === 'stop') {
            // v1.12：停止容器（docker compose stop），容器进入停止状态不删除，
            // 想再运行点「重启容器」（restart 对已停止容器同样有效）即可恢复
            $r = compose('stop');
            if ($r['code'] === 0) {
                $msg = '⏸️ 容器已停止，想再运行请点「重启容器」恢复';
            } else {
                $err = '容器停止失败：' . $r['out'];
            }
        }
        elseif ($action === 'set_after_finish') {
            // v1.12：任务结束后快捷设置（after_finish: Exit / None）
            $val = ($_POST['value'] ?? '') === 'Exit' ? 'Exit' : 'None';
            $bak = config_backup();
            $r = config_save_form(array('after_finish' => yaml_format_val($val, 'str')));
            if ($r['ok']) {
                $tip = $val === 'Exit' ? '已开启「跑完自动退出游戏」，任务跑完自动退出，下次进入从主界面开始' : '已切换为「跑完保持界面」，任务跑完停在最后界面';
                $msg = $tip . '（备份：' . ($bak ? basename($bak) : '无') . '）。下次运行任务时生效。';
                echo json_encode(array('ok' => true, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('ok' => false, 'msg' => $r['msg']), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        elseif ($action === 'set_update_mode') {
            $mode = ($_POST['mode'] ?? '') === 'manual' ? 'manual' : 'auto';
            if (panel_config_save(array('update_mode' => $mode))) {
                echo json_encode(array('ok' => true, 'msg' => '更新模式已切换为「' . ($mode === 'manual' ? '手动更新' : '自动检查') . '」'), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('ok' => false, 'msg' => '写入面板配置失败，请检查 .panel_config.php 权限'), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        // 资源监控：采样间隔设置（v1.13+）
        elseif ($action === 'set_monitor_interval') {
            $iv = max(1, min(3600, (int)($_POST['interval'] ?? 1)));
            if (panel_config_save(array('monitor_interval' => $iv))) {
                echo json_encode(array('ok' => true, 'msg' => '采样间隔已设置为 ' . $iv . ' 秒，曲线按新频率刷新'), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('ok' => false, 'msg' => '写入面板配置失败，请检查 .panel_config.php 权限'), JSON_UNESCAPED_UNICODE);
            }
            exit;
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
                } elseif (!empty($_POST['then_notify_check'])) {
                    // v1.15+：保存后立即对推送配置做一次只读体检（不发消息、不改配置）
                    $notifyCheck = notify_health_check(yaml_read_simple());
                    $msg .= '。🔍 已完成推送配置体检，结果见「消息推送」分组上方的体检提示。';
                } elseif (!empty($_POST['then_notify_test'])) {
                    // v1.15+：保存后立即发一条测试推送，方便验证推送渠道
                    if (container_is_running()) {
                        $nr = task_start('notify');
                        if ($nr['code'] === 0) {
                            history_add('notify', $TASKS['notify']['label']);
                            $msg .= '。🔔 已发送测试推送，请在手机上确认（结果见任务历史/日志）。';
                        } else {
                            $msg .= '。但测试推送发送失败：' . trim($nr['out']);
                        }
                    } else {
                        $msg .= '。容器未运行，配置已保存但未发送测试。';
                    }
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
        // 版本回滚（v1.14+）：把指定备份恢复为 index.php
        elseif ($action === 'backup_rollback') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(backup_rollback((string)($_POST['file'] ?? '')), JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 任务历史清空（v1.14+）
        elseif ($action === 'history_clear') {
            header('Content-Type: application/json; charset=utf-8');
            if (history_write(array('items' => array()))) {
                echo json_encode(array('ok' => true, 'msg' => '任务执行历史已清空'), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('ok' => false, 'msg' => '清空失败，请检查 data 目录写权限'), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        // ===== 计划任务（v1.15+）：新增/编辑、删除、启停、冲突策略、立即运行一次 =====
        elseif ($action === 'schedule_add') {
            $clean = null;
            $verr = schedule_validate(array(
                'name' => isset($_POST['sched_name']) ? $_POST['sched_name'] : '',
                'time' => isset($_POST['sched_time']) ? $_POST['sched_time'] : '',
                'days' => isset($_POST['sched_days']) ? $_POST['sched_days'] : array(),
                'args' => isset($_POST['sched_args']) ? $_POST['sched_args'] : '',
            ), $clean);
            if ($verr !== '') {
                $err = '计划任务保存失败：' . $verr;
            } else {
                $d = schedule_load();
                $id = trim((string)(isset($_POST['sched_id']) ? $_POST['sched_id'] : ''));
                if ($id === '' || !preg_match('/^[A-Za-z0-9]{1,32}$/', $id)) $id = bin2hex(random_bytes(4));
                $found = false;
                foreach ($d['tasks'] as $i => $t) {
                    if ($t['id'] === $id) {
                        $d['tasks'][$i]['name'] = $clean['name'];
                        $d['tasks'][$i]['time'] = $clean['time'];
                        $d['tasks'][$i]['days'] = $clean['days'];
                        $d['tasks'][$i]['args'] = $clean['args'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $d['tasks'][] = array(
                        'id'          => $id,
                        'name'        => $clean['name'],
                        'time'        => $clean['time'],
                        'days'        => $clean['days'],
                        'args'        => $clean['args'],
                        'enabled'     => true,
                        'last_run'    => '',
                        'last_result' => '',
                    );
                }
                if (schedule_save($d)) {
                    $msg = '计划任务「' . $clean['name'] . '」已保存：'
                        . schedule_days_label($clean['days'])
                        . ' ' . $clean['time'] . ' 执行 '
                        . $TASKS[$clean['args']]['label']
                        . '（宿主机计划任务每分钟调用面板即可触发）';
                } else {
                    $err = '计划任务保存失败：无法写入 data/schedule.json，请检查 data 目录写权限';
                }
            }
        }
        elseif ($action === 'schedule_del') {
            $id = trim((string)(isset($_POST['sched_id']) ? $_POST['sched_id'] : ''));
            $d = schedule_load();
            $kept = array();
            $removed = '';
            foreach ($d['tasks'] as $t) {
                if ($id !== '' && $t['id'] === $id) { $removed = ($t['name'] !== '' ? $t['name'] : $t['id']); continue; }
                $kept[] = $t;
            }
            if ($removed === '') {
                $err = '删除失败：没有找到该计划任务';
            } else {
                $d['tasks'] = $kept;
                if (schedule_save($d)) $msg = '计划任务「' . $removed . '」已删除';
                else $err = '删除失败：无法写入 data/schedule.json';
            }
        }
        elseif ($action === 'schedule_toggle') {
            $id = trim((string)(isset($_POST['sched_id']) ? $_POST['sched_id'] : ''));
            $d = schedule_load();
            $target = '';
            foreach ($d['tasks'] as $i => $t) {
                if ($t['id'] === $id) {
                    $d['tasks'][$i]['enabled'] = empty($t['enabled']);
                    $target = ($t['name'] !== '' ? $t['name'] : $t['id']) . '（' . ($d['tasks'][$i]['enabled'] ? '已启用' : '已停用') . '）';
                    break;
                }
            }
            if ($target === '') {
                $err = '操作失败：没有找到该计划任务';
            } else {
                if (schedule_save($d)) $msg = '计划任务 ' . $target;
                else $err = '操作失败：无法写入 data/schedule.json';
            }
        }
        elseif ($action === 'schedule_conflict') {
            $mode = (isset($_POST['conflict']) && $_POST['conflict'] === 'stop') ? 'stop' : 'skip';
            $d = schedule_load();
            $d['conflict'] = $mode;
            if (schedule_save($d)) {
                $msg = '冲突策略已设为：' . ($mode === 'stop' ? '停掉当前任务再执行' : '跳过本次（保留当前任务）');
            } else {
                $err = '保存失败：无法写入 data/schedule.json';
            }
        }
        elseif ($action === 'schedule_now') {
            $r = schedule_run_due();
            $msg = '计划任务检查完成（' . $r['time'] . '）：触发 ' . count($r['ran']) . ' 个';
            if (!empty($r['ran'])) $msg .= '（' . implode('、', $r['ran']) . '）';
            if (!empty($r['skipped'])) $msg .= '，跳过 ' . count($r['skipped']) . ' 个（' . implode('、', $r['skipped']) . '）';
            if (empty($r['ran']) && empty($r['skipped'])) $msg .= '。没有命中任何到点的计划任务（到点后 30 分钟内仍会补跑）。';
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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
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
  --blue: #3b82f6;
  --border: rgba(236,72,153,.16);
  --shadow: 0 8px 32px rgba(236,72,153,.10);
  --radius: 14px;
  --grad: linear-gradient(135deg,#ec4899 0%,#7dd3fc 100%);
  --grad-soft: linear-gradient(135deg,rgba(236,72,153,.13),rgba(125,211,252,.13));
  /* v1.15+ 磨砂玻璃参数（三套主题各自覆盖） */
  --glass-bg: rgba(255,255,255,.58);
  --glass-bg-soft: rgba(255,255,255,.40);
  --glass-blur: blur(22px) saturate(180%);
  --glass-blur-sm: blur(20px) saturate(180%);
  --glass-border: rgba(255,255,255,.62);
  --glass-highlight: inset 0 1px 0 rgba(255,255,255,.55);
  --glass-shadow: 0 10px 34px rgba(236,72,153,.10), 0 2px 8px rgba(88,28,135,.05);
  --glass-glow: 0 16px 42px rgba(236,72,153,.18), 0 4px 12px rgba(88,28,135,.06);
  --glass-edge-hover: rgba(236,72,153,.42);
  --glass-sheen: rgba(255,255,255,.46);
  --glass-row-hover: rgba(236,72,153,.07);
  --glass-radius: 17px;
  --glass-radius-sm: 12px;
  /* 背景光斑（粉 / 天蓝 / 紫） */
  --blob-a: rgba(236,72,153,.60);
  --blob-b: rgba(56,189,248,.55);
  --blob-c: rgba(167,139,250,.45);
  --blob-opacity: .62;
  /* v1.16+ 动效 token：统一时长与缓动曲线 */
  --dur-1:.15s; --dur-2:.3s; --dur-3:.5s;
  --ease:cubic-bezier(.22,.7,.28,1);
  --ease-spring:cubic-bezier(.34,1.35,.64,1);
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
  /* v1.15+ 磨砂玻璃参数（亮色：更透白、更淡的光斑） */
  --glass-bg: rgba(255,255,255,.66);
  --glass-bg-soft: rgba(255,255,255,.52);
  --glass-blur-sm: blur(20px) saturate(180%);
  --glass-border: rgba(255,255,255,.80);
  --glass-highlight: inset 0 1px 0 rgba(255,255,255,.85);
  --glass-shadow: 0 8px 28px rgba(51,65,85,.10), 0 1px 3px rgba(51,65,85,.06);
  --glass-glow: 0 16px 40px rgba(99,102,241,.16), 0 2px 10px rgba(51,65,85,.06);
  --glass-edge-hover: rgba(99,102,241,.40);
  --glass-sheen: rgba(255,255,255,.55);
  --glass-row-hover: rgba(99,102,241,.07);
  --blob-a: rgba(99,102,241,.38);
  --blob-b: rgba(139,92,246,.34);
  --blob-c: rgba(56,189,248,.30);
  --blob-opacity: .38;
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
  /* v1.15+ 磨砂玻璃参数（深色：更明显的玻璃与光斑） */
  --glass-bg: rgba(44,28,58,.52);
  --glass-bg-soft: rgba(255,255,255,.06);
  --glass-border: rgba(255,255,255,.12);
  --glass-highlight: inset 0 1px 0 rgba(255,255,255,.10);
  --glass-shadow: 0 12px 36px rgba(0,0,0,.42);
  --glass-glow: 0 18px 44px rgba(0,0,0,.50), 0 0 0 1px rgba(244,114,182,.18);
  --glass-edge-hover: rgba(244,114,182,.45);
  --glass-sheen: rgba(255,255,255,.16);
  --glass-row-hover: rgba(244,114,182,.10);
  --blob-a: rgba(244,114,182,.55);
  --blob-b: rgba(56,189,248,.42);
  --blob-c: rgba(167,139,250,.46);
  --blob-opacity: .55;
}
/* v1.16+：移动端禁用下拉回弹 */
html, body { overscroll-behavior-y:none; }

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
  /* v1.15+：亮色主题也铺一层柔和渐变，让玻璃有内容可模糊 */
  background:
    radial-gradient(ellipse at 15% 0%, rgba(99,102,241,.10), transparent 55%),
    radial-gradient(ellipse at 85% 100%, rgba(139,92,246,.12), transparent 55%),
    linear-gradient(160deg,#f4f6fa 0%,#eef1f8 55%,#f4f6fa 100%);
  background-attachment: fixed;
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
.layout { display:flex; min-height:100vh; position:relative; z-index:1; /* v1.15+：抬到背景光斑之上 */ }
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
  transition:all var(--dur-1); font-family:inherit;
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
  display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-1);
}
.icon-btn:hover { border-color:var(--primary); background:var(--primary-soft); }

/* 侧边栏遮罩 & 移动端顶栏 */
.sidebar-overlay { position:fixed; inset:0; background:rgba(30,12,40,.42); backdrop-filter:blur(2px); z-index:190; display:none; }
.sidebar-overlay.show { display:block; }
/* v1.16+：移动端底部导航（基础态隐藏，≤640 由手机档显示） */
.mobile-tabbar { display:none; }
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
.page.active { display:block; animation:fadeIn var(--dur-2) var(--ease); }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px) scale(.995)} to{opacity:1;transform:none} }
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
.btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; transition:all var(--dur-1); color:#fff; }
.btn:hover { opacity:.92; transform:translateY(-1px); }
.btn:active { transform:translateY(0); }
.btn.primary { background:var(--grad); box-shadow:0 4px 14px rgba(236,72,153,.3); }
.btn.green { background:var(--green); }
.btn.orange { background:var(--orange); }
.btn.red { background:var(--red); }
.btn.blue { background:var(--blue); }
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

/* ===== 资源监控（v1.13+） ===== */
.mon-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:12px; }
.mon-range { display:inline-flex; gap:4px; margin-left:10px; }
.mon-range .btn { font-size:11px; padding:4px 9px; border-radius:8px; border:1px solid var(--border); background:var(--card2); color:var(--muted); cursor:pointer; }
.mon-range .btn.active { background:var(--grad); color:#fff; border-color:transparent; }
.mon-stat { background:var(--card2); border:1px solid var(--border); border-radius:12px; padding:14px 12px; text-align:center; transition:all .2s; }
.mon-stat .m-label { font-size:12px; color:var(--muted); margin-bottom:6px; }
.mon-stat .m-value { font-size:22px; font-weight:800; line-height:1.2; }
.mon-stat .m-sub { font-size:11px; color:var(--muted); margin-top:5px; }
.mon-stat .m-bar { height:5px; border-radius:3px; background:var(--card-solid); margin-top:9px; overflow:hidden; }
.mon-stat .m-bar > i { display:block; height:100%; border-radius:3px; background:var(--grad); transition:width .6s ease; }
.mon-stat.warn .m-value { color:var(--orange); }
.mon-stat.danger .m-value { color:var(--red); }
.mon-legend { display:flex; gap:14px; font-size:12px; color:var(--muted); margin-bottom:8px; flex-wrap:wrap; }
.mon-legend .lg { display:inline-flex; align-items:center; gap:5px; }
.mon-legend .dot { width:9px; height:9px; border-radius:3px; display:inline-block; }
.mon-chart { width:100%; height:260px; background:var(--card2); border:1px solid var(--border); border-radius:12px; padding:8px; margin-bottom:10px; box-sizing:border-box; }
.mon-host { display:flex; flex-wrap:wrap; gap:8px 18px; font-size:12px; color:var(--muted); padding:10px 2px 0; border-top:1px solid var(--border); }
.mon-host-row { display:flex; gap:6px; align-items:center; }
.mon-host b { color:var(--text); font-weight:600; }
#monChart code, .mon-tip code { background:var(--card2); border:1px solid var(--border); padding:2px 6px; border-radius:6px; font-size:11px; }

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
/* v1.15+：可折叠分组（默认收起） */
.cfg-group h3.cfg-toggle { cursor:pointer; user-select:none; }
.cfg-group h3.cfg-toggle:hover { color:var(--primary); }
.cfg-caret { font-size:11px; color:var(--muted); transition:transform .24s var(--ease-spring); display:inline-block; }
.cfg-group.collapsed .cfg-caret { transform:rotate(-90deg); }
.cfg-hint { margin-left:auto; font-size:11px; font-weight:400; color:var(--muted); }
.cfg-group.collapsed .cfg-hint { color:var(--primary); }
.cfg-group.collapsed .cfg-hint::after { content:'点击展开'; }
.cfg-group:not(.collapsed) .cfg-hint::after { content:'点击收起'; }
/* v1.15+：折叠分组用高度过渡代替 display:none 硬切（箭头旋转见 .cfg-caret） */
.cfg-body {
  overflow:hidden; max-height:6000px; opacity:1;
  transition:max-height .36s cubic-bezier(.4,0,.2,1), opacity .26s ease;
}
.cfg-group.collapsed .cfg-body { max-height:0; opacity:0; }
.cfg-note { font-size:11.5px; color:var(--muted); line-height:1.6; margin:-4px 0 10px; padding:8px 10px; background:rgba(236,72,153,.06); border-left:3px solid var(--primary); border-radius:6px; }
.cfg-footnote { font-size:11.5px; color:var(--muted); line-height:1.6; margin:10px 0 0; }
/* v1.15+：推送配置体检结果区（沿用主题变量，不引入新的配色体系） */
.nchk-wrap { margin:0 0 14px; padding:12px 14px; border:1px solid var(--border); border-radius:12px; background:var(--card2); }
.nchk-title { font-size:13px; font-weight:700; color:var(--text); margin-bottom:8px; }
.nchk { padding:9px 12px; margin-bottom:8px; border:1px solid var(--border); border-radius:10px; font-size:12.5px; line-height:1.7; }
.nchk:last-of-type { margin-bottom:0; }
.nchk-red { background:var(--red-bg); color:var(--red); border-color:var(--red); }
.nchk-orange { background:var(--orange-bg); color:var(--orange); border-color:var(--orange); }
.nchk-green { background:var(--green-bg); color:var(--green); border-color:var(--green); }
.nchk-gray { background:var(--card-solid); color:var(--muted); }
.nchk ul { margin:6px 0 0 18px; padding:0; }
.nchk li { margin:2px 0; }
.nchk-foot { font-size:11.5px; color:var(--muted); line-height:1.6; margin:8px 0 0; }
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
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; position:relative; z-index:1; /* v1.15+：抬到背景光斑之上 */ }
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

/* ===== 数据表格（v1.14：版本备份 / 任务历史，沿用现有主题变量） ===== */
.hist-table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:12px; background:var(--card2); }
.hist-table { width:100%; border-collapse:collapse; font-size:13px; }
.hist-table th {
  text-align:left; padding:9px 12px; font-size:11px; font-weight:600; color:var(--muted);
  text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; background:var(--card2);
}
.hist-table td { padding:10px 12px; border-top:1px solid var(--border); white-space:nowrap; }
.hist-table tr:hover td { background:var(--card); }
.hist-badge {
  display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px;
  font-size:11.5px; font-weight:600; color:#fff;
}
.hist-empty { color:var(--muted); font-size:13px; }

/* ===== 计划任务（v1.15+） ===== */
.sched-table { min-width:760px; }
.sched-table .sched-result { white-space:normal; font-size:12px; color:var(--muted); max-width:220px; }
.sched-form { margin-top:14px; padding:14px; border:1px dashed var(--border); border-radius:12px; background:var(--card2); }
.sched-form-row { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:13px; }
.sched-form-row + .sched-form-row { margin-top:10px; }
.sched-form-row > label { width:52px; flex-shrink:0; color:var(--muted); font-weight:600; }
.sched-form-row input[type="text"] {
  flex:1; min-width:180px; max-width:320px; padding:7px 12px; border:1px solid var(--border);
  border-radius:9px; font-size:13px; background:var(--card-solid); color:var(--text); font-family:inherit;
}
.sched-form-row input[type="time"] {
  padding:6px 10px; border:1px solid var(--border); border-radius:9px; font-size:13px;
  background:var(--card-solid); color:var(--text); font-family:inherit;
}
.sched-hint { font-size:11.5px; color:var(--muted); }
.sched-days { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.sched-day { display:inline-flex; align-items:center; gap:4px; font-size:12.5px; color:var(--text); cursor:pointer; }
.sched-day input { accent-color:var(--primary); cursor:pointer; }
.sched-cmd-row { display:flex; align-items:center; gap:8px; margin-top:10px; }
.sched-cmd {
  flex:1; min-width:0; background:var(--card2); border:1px solid var(--border); border-radius:10px;
  padding:10px 12px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px;
  white-space:pre-wrap; word-break:break-all; color:var(--text);
}

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

/* ===== 响应式（v1.16+：三档断点 ≤640 手机 / 641-1024 平板 / ≥1025 桌面） ===== */
@media (max-width:640px) {
  .sidebar { position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform var(--dur-2) var(--ease); box-shadow:0 0 40px rgba(0,0,0,.2); }
  .sidebar.open { transform:translateX(0); }
  .mobile-topbar { display:flex; }
  .content { padding:16px 16px calc(84px + env(safe-area-inset-bottom)); }
  body { padding-bottom:env(safe-area-inset-bottom); }
  .task-grid { grid-template-columns:1fr 1fr; }
}
@media (min-width:641px) and (max-width:1024px) {
  .sidebar { position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform var(--dur-2) var(--ease); box-shadow:0 0 40px rgba(0,0,0,.2); }
  .sidebar.open { transform:translateX(0); }
  .mobile-topbar { display:flex; }
  .content { padding:16px; }
  .task-grid { grid-template-columns:1fr 1fr; }
}
@media (min-width:1025px) {
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
/* ============================================================
   v1.15+ 视觉层收尾：磨砂玻璃质感 + 微动效
   只改外观：不动 PHP 业务逻辑、表单字段、接口、任务与计划任务逻辑
   ============================================================ */

/* ---------- 1. 背景光斑层（给玻璃提供可模糊的内容，纯装饰、不挡交互） ---------- */
.bg-layer {
  position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden;
  contain:layout paint;
}
/* v1.16+ 重构：去掉大半径 filter:blur（掉帧主因），改用偏心 radial-gradient 柔边大圆；
   动画只用 transform 旋转缩放 + opacity 微变（合成层友好），保持粉/紫双色柔光漂移观感 */
.blob {
  position:absolute; display:block; border-radius:50%;
  opacity:var(--blob-opacity);
  will-change:transform, opacity;
}
.blob-1 {
  width:78vmin; height:78vmin; left:-18vmin; top:-22vmin;
  background:radial-gradient(circle at 42% 42%, var(--blob-a), transparent 66%);
  animation:blobGlow1 80s ease-in-out infinite;
}
.blob-2 {
  width:86vmin; height:86vmin; right:-22vmin; bottom:-26vmin;
  background:radial-gradient(circle at 58% 58%, var(--blob-b), var(--blob-c) 55%, transparent 70%);
  animation:blobGlow2 100s ease-in-out infinite; animation-delay:-32s;
}
.blob-3 { display:none; }
/* 只动 transform/opacity，保证合成层动画、不触发重排重绘 */
@keyframes blobGlow1 {
  0%   { transform:rotate(0deg) scale(1);    opacity:var(--blob-opacity); }
  50%  { transform:rotate(180deg) scale(1.14); opacity:calc(var(--blob-opacity) * .78); }
  100% { transform:rotate(360deg) scale(1);  opacity:var(--blob-opacity); }
}
@keyframes blobGlow2 {
  0%   { transform:rotate(0deg) scale(1.08);   opacity:calc(var(--blob-opacity) * .82); }
  50%  { transform:rotate(-180deg) scale(1);   opacity:var(--blob-opacity); }
  100% { transform:rotate(-360deg) scale(1.08); opacity:calc(var(--blob-opacity) * .82); }
}

/* ---------- 2. 玻璃质感（统一由变量驱动） ---------- */
/* 2.1 面板 / 卡片类容器 */
.card,
.mobile-topbar,
.modal,
.codebox,
.task-card,
.mon-stat,
.mon-chart,
.hist-table-wrap,
.sched-form,
.sched-cmd,
.inst-item,
.inst-form-wrap {
  background:var(--glass-bg);
  -webkit-backdrop-filter:var(--glass-blur);
  backdrop-filter:var(--glass-blur);
  border:1px solid var(--glass-border);
  border-radius:var(--glass-radius);
  box-shadow:var(--glass-highlight), var(--glass-shadow);
}
/* 2.2 表单控件 / 内嵌小容器（更轻的模糊，减少嵌套开销） */
.cfg-select,
.cfg-input-text,
.cfg-input-num,
.cfg-textarea,
.yaml-editor,
.inst-select,
.log-filter input[type=text],
.log-filter select,
.sched-form-row input[type="text"],
.sched-form-row input[type="time"] {
  background:var(--glass-bg);
  -webkit-backdrop-filter:var(--glass-blur-sm);
  backdrop-filter:var(--glass-blur-sm);
  border:1px solid var(--glass-border);
  border-radius:var(--glass-radius-sm);
  box-shadow:var(--glass-highlight);
}
/* 下拉浮层用不透明底色，避免选项看不清 */
.cfg-select option, .inst-select option, .log-filter select option { background:var(--card-solid); color:var(--text); }
/* 2.3 侧边栏 / 顶部栏 / 登录卡 / 状态徽章（各自保留原有圆角特征） */
.sidebar {
  background:var(--glass-bg);
  -webkit-backdrop-filter:var(--glass-blur); backdrop-filter:var(--glass-blur);
  border-right:1px solid var(--glass-border);
  box-shadow:var(--glass-highlight), 1px 0 30px rgba(236,72,153,.06);
}
html[data-theme="dark"] .sidebar { background:var(--glass-bg); }
html[data-theme="light"] .card { background:var(--glass-bg); }
.auth-card {
  background:var(--glass-bg);
  -webkit-backdrop-filter:var(--glass-blur); backdrop-filter:var(--glass-blur);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-highlight), 0 20px 60px rgba(236,72,153,.16);
}
.status-badge {
  background:var(--glass-bg);
  -webkit-backdrop-filter:var(--glass-blur-sm); backdrop-filter:var(--glass-blur-sm);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-highlight);
}
.mobile-topbar { border-radius:var(--glass-radius); }
/* 表格行高亮与表头也用半透明色，避免玻璃里出现一块死白 */
.hist-table th { background:var(--glass-bg-soft); }
.hist-table tr:hover td { background:var(--glass-row-hover); }
/* 老浏览器兜底：不支持 backdrop-filter 时回退为不透明底，避免糊成一片 */
@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
  .card, .mobile-topbar, .modal, .codebox, .task-card, .mon-stat, .mon-chart,
  .hist-table-wrap, .sched-form, .sched-cmd, .inst-item, .inst-form-wrap,
  .sidebar, .auth-card, .status-badge,
  .cfg-select, .cfg-input-text, .cfg-input-num, .cfg-textarea, .yaml-editor,
  .inst-select, .log-filter input[type=text], .log-filter select,
  .sched-form-row input[type="text"], .sched-form-row input[type="time"] {
    background:var(--card-solid);
  }
  .hist-table th { background:var(--card-solid); }
}

/* ---------- 3. 动效 ---------- */
/* 3.1 卡片进场：淡入 + 上浮，依次错开（40ms/张，backwards 填充防闪跳） */
@keyframes cardIn { from{opacity:0; transform:translateY(16px)} to{opacity:1; transform:none} }
.page.active .card { animation:cardIn var(--dur-3) var(--ease) backwards; }
.page.active .card:nth-child(2) { animation-delay:.04s; }
.page.active .card:nth-child(3) { animation-delay:.08s; }
.page.active .card:nth-child(4) { animation-delay:.12s; }
.page.active .card:nth-child(5) { animation-delay:.16s; }
.page.active .card:nth-child(6) { animation-delay:.20s; }
.page.active .card:nth-child(7) { animation-delay:.24s; }
.page.active .card:nth-child(8) { animation-delay:.28s; }
.page.active .card:nth-child(n+9) { animation-delay:.32s; }
.auth-card { animation:cardIn var(--dur-3) var(--ease) backwards; }

/* 3.2 卡片 / 任务卡 hover：上浮 + 阴影增强 + 边框主题色微光 */
.card {
  position:relative; overflow:hidden;
  transition:transform .26s var(--ease), box-shadow .26s ease, border-color .26s ease;
}
.card:hover {
  transform:translateY(-3px);
  border-color:var(--glass-edge-hover);
  box-shadow:var(--glass-highlight), var(--glass-glow);
}
.task-card { position:relative; overflow:hidden; }
.task-card:hover {
  transform:translateY(-3px);
  border-color:var(--glass-edge-hover);
  box-shadow:var(--glass-highlight), var(--glass-glow);
}
.task-card:active { transform:scale(.98); }
.mon-stat:hover { transform:translateY(-2px); }

/* 3.3 卡片 hover 斜向高光掠过（只在 hover 时跑一次，不常驻） */
.card::after, .task-card::after {
  content:''; position:absolute; top:-12%; bottom:-12%; left:0; width:42%;
  background:linear-gradient(100deg, transparent 0%, var(--glass-sheen) 45%, rgba(255,255,255,.05) 68%, transparent 100%);
  transform:translateX(-140%) skewX(-14deg);
  opacity:0; pointer-events:none;
}
.card:hover::after, .task-card:hover::after {
  animation:cardSheen .72s cubic-bezier(.3,.55,.3,1) 1;
}
@keyframes cardSheen {
  0%   { opacity:0;   transform:translateX(-140%) skewX(-14deg); }
  18%  { opacity:.9;  transform:translateX(-70%)  skewX(-14deg); }
  70%  { opacity:.55; transform:translateX(70%)   skewX(-14deg); }
  100% { opacity:0;   transform:translateX(140%)  skewX(-14deg); }
}

/* 3.4 按钮：hover 轻提亮 / 上浮，active 回弹 */
.btn, .logout-btn, .icon-btn { position:relative; overflow:hidden; }
.btn {
  transition:transform .18s var(--ease-spring), box-shadow .22s ease, opacity .2s ease;
}
.btn::before {
  content:''; position:absolute; inset:0; pointer-events:none; opacity:0;
  background:linear-gradient(180deg, rgba(255,255,255,.28), rgba(255,255,255,.04));
  transition:opacity .2s ease;
}
.btn:hover { opacity:1; transform:translateY(-2px); box-shadow:0 10px 22px rgba(88,28,135,.18); }
.btn:hover::before { opacity:1; }
.btn:active { transform:scale(.97); box-shadow:none; }
.logout-btn { transition:transform .18s var(--ease-spring), box-shadow .2s ease; }
.logout-btn:hover { transform:translateY(-1px); box-shadow:0 8px 18px rgba(236,72,153,.28); }
.logout-btn:active { transform:scale(.97); }
.icon-btn { transition:transform .18s var(--ease-spring), background .2s ease, border-color .2s ease, box-shadow .2s ease; }
.icon-btn:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(236,72,153,.16); }
.icon-btn:active { transform:scale(.94); }
/* 复制成功 / 失败的短暂反馈（由 copyText 追加 class，不改变其原有行为） */
.btn.copy-ok, .btn.copy-ok:hover { background:var(--green); color:#fff; }
.btn.copy-ok { animation:copyPop .42s var(--ease-spring) 1; }
.btn.copy-fail, .btn.copy-fail:hover { background:var(--red); color:#fff; }
.btn.copy-fail { animation:copyPop .42s var(--ease-spring) 1; }
@keyframes copyPop { 0%{transform:scale(1)} 45%{transform:scale(1.06)} 100%{transform:scale(1)} }

/* 3.5 侧边栏导航：hover 微位移 + 左侧高亮条（scaleY 过渡） */
.nav-item {
  position:relative;
  transition:background .18s ease, color .18s ease, transform .2s var(--ease);
}
.nav-item::before {
  content:''; position:absolute; left:5px; top:50%; width:3px; height:20px; border-radius:3px;
  background:var(--grad); opacity:0;
  transform:translateY(-50%) scaleY(0); transform-origin:50% 50%;
  transition:transform .22s var(--ease-spring), opacity .2s ease;
}
.nav-item:hover { transform:translateX(3px); }
.nav-item:hover::before { opacity:.65; transform:translateY(-50%) scaleY(.6); }
.nav-item.active::before { opacity:1; transform:translateY(-50%) scaleY(1); }
.nav-item:active { transform:translateX(3px) scale(.99); }

/* 3.6 开关滑块过渡更顺滑 */
.switch .slider { transition:background .26s cubic-bezier(.4,0,.2,1); }
.switch .slider:before { transition:transform .26s var(--ease-spring); }

/* 3.7 资源监控卡片与任务卡片的轻量反馈 */
.mon-stat, .task-card { transition:transform .22s var(--ease), box-shadow .22s ease, border-color .22s ease; }
.codebox { transition:box-shadow .26s ease, border-color .26s ease; }
.card:hover .codebox { border-color:var(--glass-edge-hover); }

/* ---------- 5. 降低动态效果偏好：只保留即时状态变化 ---------- */
@media (prefers-reduced-motion: reduce) {
  .blob { animation:none !important; }
  .page.active, .page > .card, .auth-card, .card::after, .task-card::after,
  .status-badge.running .dot, .btn.copy-ok, .btn.copy-fail { animation:none !important; }
  .page > .card, .auth-card { opacity:1 !important; transform:none !important; }
  .card::after, .task-card::after { display:none !important; }
  *, *::before, *::after {
    transition-duration:.001ms !important;
    animation-duration:.001ms !important;
    animation-iteration-count:1 !important;
  }
}

/* ============================================================
   v1.16+ 移动端适配（≤640 手机档）
   ============================================================ */
@media (max-width:640px) {
  /* 表单单列（v1.15 前断点内的 cfg-row 规则迁入手机档） */
  .cfg-row { flex-direction:column; align-items:flex-start; }
  .cfg-input { align-self:flex-start; width:100%; }
  .cfg-select, .cfg-input-text, .cfg-input-num { min-width:0; width:100%; }
  .cfg-pass { min-width:0; width:100%; }
  .cfg-textarea { min-width:0; }

  /* 表格卡片化：隐藏表头，行变卡片，td::before 显示列名 */
  .hist-table-wrap { border:none; background:transparent; }
  .hist-table, .sched-table { min-width:0; }
  .hist-table thead { display:none; }
  .hist-table tr {
    display:block; border:1px solid var(--glass-border); border-radius:12px;
    margin-bottom:10px; background:var(--glass-bg); overflow:hidden;
    box-shadow:var(--glass-highlight);
  }
  .hist-table td {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    white-space:normal; word-break:break-all; text-align:right;
    border-top:none; border-bottom:1px solid var(--border); padding:9px 12px;
  }
  .hist-table td:last-child { border-bottom:none; }
  .hist-table td[data-label]::before {
    content:attr(data-label); flex-shrink:0; text-align:left;
    color:var(--muted); font-size:12px; font-weight:600;
  }

  /* 监控移动化 */
  .mon-grid { grid-template-columns:repeat(2,1fr); }
  .mon-chart { height:200px; }

  /* 触控目标 */
  .btn, .btn.small { min-height:44px; }
  .icon-btn { min-height:44px; min-width:44px; }

  /* 底部导航（复用 nav-item 配色体系） */
  .mobile-tabbar {
    display:flex; position:fixed; left:0; right:0; bottom:0; z-index:300;
    padding:6px 8px calc(6px + env(safe-area-inset-bottom));
    background:var(--glass-bg);
    border-top:1px solid var(--glass-border);
    box-shadow:0 -6px 24px rgba(236,72,153,.10);
  }
  .mobile-tabbar .tab-item {
    flex:1; display:flex; flex-direction:column; align-items:center; gap:2px;
    padding:6px 4px; border:none; border-radius:10px; background:transparent;
    color:var(--muted); font-size:11px; font-weight:600; cursor:pointer; font-family:inherit;
  }
  .mobile-tabbar .tab-item .tab-icon { font-size:18px; line-height:1; }
  .mobile-tabbar .tab-item.active {
    background:var(--grad-soft); color:var(--primary);
    box-shadow:inset 0 0 0 1px var(--border);
  }

  /* 性能降级：光斑静态化 + 去掉多层 backdrop-filter（保半透明纯色底） */
  .blob { animation:none; opacity:.6; }
  .card, .mobile-topbar, .modal, .codebox, .task-card, .mon-stat, .mon-chart,
  .hist-table-wrap, .sched-form, .sched-cmd, .inst-item, .inst-form-wrap,
  .sidebar, .auth-card, .status-badge,
  .cfg-select, .cfg-input-text, .cfg-input-num, .cfg-textarea, .yaml-editor,
  .inst-select, .log-filter input[type=text], .log-filter select,
  .sched-form-row input[type="text"], .sched-form-row input[type="time"] {
    -webkit-backdrop-filter:none; backdrop-filter:none;
    background:var(--glass-bg);
  }
}
</style>
</head>
<body>

<!-- v1.15+：磨砂玻璃背景光斑层（纯装饰，pointer-events:none，不参与交互） -->
<div class="bg-layer" aria-hidden="true">
  <span class="blob blob-1"></span>
  <span class="blob blob-2"></span>
  <span class="blob blob-3"></span>
</div>

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
          <button class="btn gray small" onclick="ignoreVersion()">忽略此版本</button>
        </div>
      </div>
    </div>

    <?php if ($msg): ?><div class="msg ok">✅ <?php echo h($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err">❌ <?php echo h($err); ?></div><?php endif; ?>

    <!-- ===== 概览 ===== -->
    <?php $_afCfg = yaml_read_simple(); $afVal = isset($_afCfg['after_finish']) ? trim($_afCfg['after_finish'], '"\'') : 'None'; if ($afVal === '') $afVal = 'None'; ?>
    <div class="page active" id="panel-overview">
      <div class="page-head">
        <div class="page-title"><span class="pt-icon">📊</span>概览</div>
        <div class="page-desc">容器状态、快捷操作与基本信息</div>
      </div>

      <div class="card">
        <h2><span class="icon">📦</span> 容器状态 <button class="btn small gray" onclick="refreshStatus()" style="margin-left:auto;">刷新</button></h2>
        <div class="codebox" id="statusBox"><?php $st = container_status(); echo $st === '' ? '(无法获取，请检查 www 用户 docker 权限)' : h($st); ?></div>
      </div>

      <div class="card">
        <?php
        $_monCfg = panel_config_load();
        if (empty($_monCfg['monitor_key'])) {
            $_monCfg['monitor_key'] = bin2hex(random_bytes(8));
            panel_config_save(array('monitor_key' => $_monCfg['monitor_key']));
        }
        $_monScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $_monBase = $_monScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        $_monCron = 'curl -s "' . $_monBase . '/index.php?monitor_sampler=1&key=' . $_monCfg['monitor_key'] . '" >/dev/null 2>&1';
        $_monIv = isset($_monCfg['monitor_interval']) ? max(1, (int)$_monCfg['monitor_interval']) : 1;
        ?>
        <h2><span class="icon">📈</span> 资源监控
          <span class="badge" id="monLiveBadge" style="background:var(--green,#22c55e);color:#fff;">实时</span>
          <span class="mon-range">
            <button type="button" class="btn small" data-range="1m" onclick="setMonRange('1m')">近1分钟</button>
            <button type="button" class="btn small active" data-range="1h" onclick="setMonRange('1h')">近1小时</button>
            <button type="button" class="btn small" data-range="1d" onclick="setMonRange('1d')">近1天</button>
          </span>
          <select id="monInterval" onchange="setMonitorInterval(this.value)" style="margin-left:auto;font-size:12px;padding:4px 8px;border-radius:8px;border:1px solid var(--border);background:var(--card2);color:var(--text);">
            <option value="1"<?php echo $_monIv === 1 ? ' selected' : ''; ?>>1秒</option>
            <option value="2"<?php echo $_monIv === 2 ? ' selected' : ''; ?>>2秒</option>
            <option value="5"<?php echo $_monIv === 5 ? ' selected' : ''; ?>>5秒</option>
            <option value="10"<?php echo $_monIv === 10 ? ' selected' : ''; ?>>10秒</option>
            <option value="30"<?php echo $_monIv === 30 ? ' selected' : ''; ?>>30秒</option>
            <option value="60"<?php echo $_monIv === 60 ? ' selected' : ''; ?>>60秒</option>
          </select>
          <button class="btn small gray" onclick="loadMonitor(true)" style="margin-left:8px;">立即刷新</button>
        </h2>
        <div class="mon-grid">
          <div class="mon-stat" id="monCpu"><div class="m-label">CPU 使用率</div><div class="m-value">--</div><div class="m-bar"><i style="width:0%"></i></div></div>
          <div class="mon-stat" id="monMem"><div class="m-label">内存使用率</div><div class="m-value">--</div><div class="m-bar"><i style="width:0%"></i></div></div>
          <div class="mon-stat" id="monDisk"><div class="m-label">磁盘使用率</div><div class="m-value">--</div><div class="m-bar"><i style="width:0%"></i></div></div>
          <div class="mon-stat" id="monLoad"><div class="m-label">系统负载</div><div class="m-value">--</div><div class="m-sub">1分钟均值</div></div>
          <div class="mon-stat" id="monNet"><div class="m-label">网络速率</div><div class="m-value" style="font-size:16px;">--</div><div class="m-sub" id="monNetSub">↓下载 ↑上传</div></div>
          <div class="mon-stat" id="monUp"><div class="m-label">运行时长</div><div class="m-value" style="font-size:16px;">--</div><div class="m-sub" id="monUpSub">容器状态：检测中…</div></div>
        </div>
        <div class="mon-legend"><span class="lg"><span class="dot" style="background:#ec4899;"></span>CPU</span><span class="lg"><span class="dot" style="background:#38bdf8;"></span>内存</span><span class="lg"><span class="dot" style="background:#f59e0b;"></span>磁盘</span></div>
        <div class="mon-chart" id="monChart"><div style="text-align:center;color:var(--muted);padding-top:110px;font-size:13px;">图表加载中…（首次采样约需 1 秒）</div></div>
        <div class="mon-host" id="monHost"></div>
        <p class="tip mon-tip" style="margin:10px 0 0;">实时采样：打开面板时每 <span id="monIvText"><?php echo $_monIv; ?></span> 秒自动采样；想不打开面板也有曲线，在宝塔「计划任务」添加 Shell 脚本每 1 分钟执行：<code id="monCronCmd"><?php echo h($_monCron); ?></code><br><span style="color:var(--muted);font-size:12px;">提示：命令中的 IP 地址会自动取你当前访问面板的地址；如果服务器实际 IP 与此不同（例如用域名访问、内网/外网 IP 不一致），请把命令里的 IP 改成你服务器的实际 IP。</span></p>
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
        <h2><span class="icon">🚪</span> 任务结束后设置 <span class="badge" id="afterFinishBadge" style="background:<?php echo $afVal === 'Exit' ? 'var(--green,#22c55e)' : 'var(--gray,#9ca3af)'; ?>;color:#fff;"><?php echo $afVal === 'Exit' ? '自动退出' : '保持界面'; ?></span></h2>
        <div class="btn-group">
          <button type="button" class="btn primary" onclick="setAfterFinish('Exit')">🚪 跑完自动退出游戏</button>
          <button type="button" class="btn gray" onclick="setAfterFinish('None')">🖥️ 跑完保持界面</button>
        </div>
        <p class="tip" style="margin:10px 0 0;">开启「自动退出」后任务全部跑完会自动退出游戏，下次进入从主界面开始，不再停在模拟宇宙界面。当前状态：<strong id="afterFinishVal"><?php echo $afVal === 'Exit' ? '跑完自动退出游戏' : '跑完保持界面'; ?></strong>，下次运行任务时生效。</p>
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
        <h2><span class="icon">🔗</span> 管理面板更新 <button class="btn small gray" onclick="testUpdateSource()" style="margin-left:auto;">测试连接</button></h2>
        <div class="info-grid">
          <div class="info-item"><div class="label">更新源类型</div><div class="value"><?php echo h(strtoupper(UPDATE_TYPE)); ?></div></div>
          <div class="info-item"><div class="label">当前版本</div><div class="value">v<?php echo h(PANEL_VERSION); ?></div></div>
          <div class="info-item"><div class="label">仓库</div><div class="value" style="font-size:13px;"><?php echo h(UPDATE_OWNER . '/' . UPDATE_REPO); ?></div></div>
          <div class="info-item"><div class="label">更新模式</div><div class="value"><select id="panelUpdateMode" onchange="setUpdateMode(this.value)" style="font-size:13px;"><option value="auto"<?php echo panel_update_mode() === 'auto' ? ' selected' : ''; ?>>自动检查</option><option value="manual"<?php echo panel_update_mode() === 'manual' ? ' selected' : ''; ?>>手动更新</option></select></div></div>
          <div class="info-item"><div class="label">检查更新</div><div class="value"><button class="btn small primary" onclick="checkUpdate(true)">立即检查</button></div></div>
          <div class="info-item"><div class="label">API 连通</div><div class="value" id="srcApiStatus">未测试</div></div>
          <div class="info-item"><div class="label">文件下载</div><div class="value" id="srcRawStatus">未测试</div></div>
          <div class="info-item"><div class="label">镜像加速</div><div class="value" id="srcMirrorStatus">未测试</div></div>
        </div>
        <p class="tip" style="margin:10px 0 0;">自动检查：页面加载时自动检测面板新版本并弹提示，可忽略该版本；手动更新：不自动提示，点「立即检查」查看。</p>
      </div>

      <div class="card">
        <?php $bkList = backups_list(); ?>
        <h2><span class="icon">🗂️</span> 版本备份 / 回滚
          <span class="badge" style="background:var(--primary-soft);color:var(--primary);">保留最近 <?php echo (int)BACKUP_KEEP; ?> 份</span>
        </h2>
        <?php if (!$bkList): ?>
        <p class="tip" style="margin:0;">暂无备份。面板「一键更新」覆盖 index.php 前会自动把当前版本备份到 <code>backups/</code> 目录，更新出问题时可从这里一键回滚。</p>
        <?php else: ?>
        <div class="hist-table-wrap">
          <table class="hist-table">
            <thead><tr><th>版本</th><th>备份时间</th><th>大小</th><th style="text-align:right;">操作</th></tr></thead>
            <tbody>
              <?php foreach ($bkList as $bk): ?>
              <tr>
                <td data-label="版本">v<?php echo h($bk['version']); ?></td>
                <td data-label="备份时间"><?php echo h($bk['timeStr']); ?></td>
                <td data-label="大小"><?php echo h(format_size($bk['size'])); ?></td>
                <td data-label="操作" style="text-align:right;"><button type="button" class="btn small orange" onclick="rollbackPanel('<?php echo h($bk['file']); ?>')">↩️ 回滚</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <p class="tip" style="margin:10px 0 0;">回滚会把面板 <code>index.php</code> 恢复为所选备份；回滚前会先把当前版本也备份一次（防手滑），回滚完成后请刷新页面确认版本号。</p>
      </div>

      <div class="card">
        <h2><span class="icon">🐳</span> 三月七小助手镜像更新</h2>
        <div class="info-grid">
          <div class="info-item"><div class="label">小助手镜像</div><div class="value" id="imgStatus">检测中…</div></div>
          <div class="info-item"><div class="label">镜像检查</div><div class="value"><button class="btn small gray" onclick="checkImage(true)">重新检查</button></div></div>
          <div class="info-item"><div class="label">镜像更新</div><div class="value"><button class="btn small orange" onclick="updateAssistantImage()">⬆️ 更新镜像</button></div></div>
        </div>
        <p class="tip" style="margin:10px 0 0;">对比本地镜像构建时间与 GitHub 最新提交，超过 1 小时提示更新；结果缓存 6 小时。更新镜像会自动依次尝试官方源与国内加速镜像（南大 / DaoCloud / dockerproxy），拉取成功后重建容器。</p>
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
        <?php
        $histData  = history_sync();
        $histItems = history_view($histData['items']);
        $histToday = history_today_stats($histData['items']);
        ?>
        <h2><span class="icon">🕒</span> 任务历史
          <span class="badge" id="histStat" style="background:var(--primary-soft);color:var(--primary);">今日执行 <?php echo (int)$histToday['count']; ?> 次 · 成功 <?php echo (int)$histToday['ok']; ?> 次</span>
          <button class="btn small gray" style="margin-left:auto;" onclick="clearHistory()">🗑️ 清空历史</button>
        </h2>
        <div class="hist-table-wrap">
          <table class="hist-table">
            <thead><tr><th>任务</th><th>开始时间</th><th>耗时</th><th>状态</th><th style="text-align:right;">操作</th></tr></thead>
            <tbody id="histBody">
              <?php if (!$histItems): ?>
              <tr><td colspan="5" class="hist-empty">暂无任务执行记录，点击上方任务按钮即可开始记录</td></tr>
              <?php else: foreach ($histItems as $hi): ?>
              <tr>
                <td data-label="任务"><?php echo h($hi['task_label']); ?></td>
                <td data-label="开始时间"><?php echo h($hi['start_str']); ?></td>
                <td data-label="耗时"><?php echo h($hi['duration_str']); ?></td>
                <td data-label="状态"><span class="hist-badge" style="background:<?php echo h($hi['status_color']); ?>;"><?php echo h($hi['status_label']); ?></span></td>
                <td data-label="操作" style="text-align:right;"><button type="button" class="btn small gray" onclick="histViewLog()">📝 查看日志</button></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <p class="tip" style="margin:10px 0 0;">面板记录每次任务的开始时间与耗时；小助手日志里没有明确的结束标记，运行中的任务以「日志静默超过 <?php echo (int)HISTORY_IDLE_SECONDS; ?> 秒」判定结束、以「容器未运行且日志长时间无更新」判定中断。最多保留最近 <?php echo (int)HISTORY_KEEP; ?> 条，按实例分别保存在 <code>data/history_容器名.json</code>。</p>
      </div>

      <!-- v1.15+：计划任务（由宿主机 cron 每分钟调用面板触发，不依赖程序常驻） -->
      <div class="card">
        <?php
        $schedData = schedule_load();
        $schedCfg  = panel_config_load();
        if (empty($schedCfg['scheduler_key'])) {
            $schedCfg['scheduler_key'] = bin2hex(random_bytes(8));
            panel_config_save(array('scheduler_key' => $schedCfg['scheduler_key']));
        }
        $_schedScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $_schedBase   = $_schedScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        $_schedCron   = 'curl -s "' . $_schedBase . '/index.php?scheduler=1&key=' . $schedCfg['scheduler_key'] . '" >/dev/null 2>&1';
        $_schedBuiltin = config_scheduled_tasks_count();
        $_schedDayName = array(1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日');
        ?>
        <h2><span class="icon">⏰</span> 计划任务
          <span class="badge" style="background:var(--primary-soft);color:var(--primary);">共 <?php echo count($schedData['tasks']); ?> 条 · 启用 <?php
            $schedEnabled = 0;
            foreach ($schedData['tasks'] as $st) { if (!empty($st['enabled'])) $schedEnabled++; }
            echo (int)$schedEnabled;
          ?> 条</span>
          <form method="post" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="schedule_conflict">
            <span style="font-size:12px;color:var(--muted);">有任务在跑时</span>
            <select name="conflict" class="cfg-select" style="min-width:170px;font-size:12px;" onchange="this.form.submit()">
              <option value="skip"<?php echo $schedData['conflict'] === 'skip' ? ' selected' : ''; ?>>跳过本次</option>
              <option value="stop"<?php echo $schedData['conflict'] === 'stop' ? ' selected' : ''; ?>>停掉当前任务再跑</option>
            </select>
          </form>
        </h2>

        <div class="hist-table-wrap">
          <table class="hist-table sched-table">
            <thead><tr><th>名称</th><th>时间</th><th>星期</th><th>任务</th><th>状态</th><th>上次结果</th><th style="text-align:right;">操作</th></tr></thead>
            <tbody>
              <?php if (!$schedData['tasks']): ?>
              <tr><td colspan="7" class="hist-empty">还没有计划任务，填写下面的表单即可添加（例如每天 04:00 跑一次「每日实训」）</td></tr>
              <?php else: foreach ($schedData['tasks'] as $st):
                  $stLabel = isset($TASKS[$st['args']]) ? $TASKS[$st['args']]['label'] : ($st['args'] . '（已失效）');
                  $stDays = schedule_normalize_days($st['days']);
                  $stDayText = schedule_days_label($stDays);
              ?>
              <tr>
                <td data-label="名称"><?php echo h($st['name'] !== '' ? $st['name'] : $st['id']); ?></td>
                <td data-label="时间"><?php echo h($st['time']); ?></td>
                <td data-label="星期"><?php echo h($stDayText); ?></td>
                <td data-label="任务"><?php echo h($stLabel); ?></td>
                <td data-label="状态">
                  <?php if (!empty($st['enabled'])): ?>
                  <span class="hist-badge" style="background:var(--green);">已启用</span>
                  <?php else: ?>
                  <span class="hist-badge" style="background:#94a3b8;">已停用</span>
                  <?php endif; ?>
                </td>
                <td data-label="上次结果" class="sched-result"><?php echo h($st['last_result'] !== '' ? $st['last_result'] : '--'); ?></td>
                <td data-label="操作" style="text-align:right;">
                  <form method="post" style="display:inline-flex;gap:6px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="sched_id" value="<?php echo h($st['id']); ?>">
                    <button type="submit" class="btn small <?php echo !empty($st['enabled']) ? 'gray' : 'green'; ?>" name="action" value="schedule_toggle"><?php echo !empty($st['enabled']) ? '⏸ 停用' : '▶ 启用'; ?></button>
                    <button type="submit" class="btn small red" name="action" value="schedule_del" onclick="return confirm('删除计划任务「<?php echo h($st['name'] !== '' ? $st['name'] : $st['id']); ?>」？');">🗑 删除</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <form method="post" class="sched-form">
          <?php echo csrf_field(); ?>
          <div class="sched-form-row">
            <label>名称</label>
            <input type="text" name="sched_name" maxlength="40" placeholder="如 每日全量" required>
          </div>
          <div class="sched-form-row">
            <label>时间</label>
            <input type="time" name="sched_time" value="04:00" required>
            <span class="sched-hint">24 小时制；以服务器时区为准</span>
          </div>
          <div class="sched-form-row">
            <label>星期</label>
            <div class="sched-days">
              <?php foreach ($_schedDayName as $dn => $dc): ?>
              <label class="sched-day"><input type="checkbox" name="sched_days[]" value="<?php echo (int)$dn; ?>"><span>周<?php echo h($dc); ?></span></label>
              <?php endforeach; ?>
              <span class="sched-hint">都不勾选 = 每天执行</span>
            </div>
          </div>
          <div class="sched-form-row">
            <label>任务</label>
            <select name="sched_args" class="cfg-select">
              <?php foreach ($TASKS as $key => $t): ?>
              <option value="<?php echo h($key); ?>"><?php echo h($t['icon'] . ' ' . $t['label']); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn primary small" name="action" value="schedule_add">➕ 添加计划任务</button>
            <button type="submit" class="btn orange small" name="action" value="schedule_now" formnovalidate>▶️ 立即运行一次</button>
          </div>
        </form>

        <p class="tip" style="margin:12px 0 0;">原理：宿主机（宝塔 → 计划任务 → 添加 Shell 脚本）<b>每分钟</b>调用一次下面的命令，面板自己判断是否到了设定的时间点并启动任务，所以<b>不依赖小助手常驻</b>。到点后 30 分钟内仍会补跑（避免 cron 间隔或服务器卡顿导致漏跑）；同一时间点只触发一次，重复调用不会重复开任务。
          <?php if ($_schedBuiltin > 0): ?>
          <br>检测到 config.yaml 里本体自带的 <code>scheduled_tasks</code> 有 <b><?php echo (int)$_schedBuiltin; ?> 条</b>：那套定时任务只在程序常驻运行时生效，Docker 按需起容器的场景建议改用这里的计划任务，避免两处重复触发。
          <?php endif; ?>
        </p>
        <div class="sched-cmd-row">
          <code id="schedCronCmd" class="sched-cmd"><?php echo h($_schedCron); ?></code>
          <button type="button" class="btn small gray" onclick="copyText('schedCronCmd', this)">📋 复制</button>
        </div>
        <p class="tip" style="margin:8px 0 0;"><span style="color:var(--muted);font-size:12px;">提示：命令里的地址会自动取你当前访问面板的地址；如果服务器实际 IP 与此不同（例如用域名访问、内网/外网 IP 不一致），请把命令里的地址改成实际能访问面板的地址。命令带 key 校验，没有 key 的请求会被直接拒绝，建议面板本身也放在内网或加访问密码保护。</span></p>
      </div>

      <div class="card">
        <h2><span class="icon">🔧</span> 容器操作</h2>
        <div class="btn-group">
          <?php foreach ($OPS as $key => $op): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo h($op['confirm']); ?>');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo h($key); ?>"><button type="submit" class="btn <?php echo h($op['color'] ?? 'primary'); ?>"><?php echo h($op['icon'] . ' ' . $op['label']); ?></button></form>
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

            <?php foreach ($CONFIG_GROUPS as $gKey => $g):
                /* v1.15+：分组支持 'collapsed' => true（默认收起，点标题展开）与 'note' => 分组说明；
                   未标记 collapsed 的分组渲染结果与旧版完全一致。 */
                $gCollapsed = !empty($g['collapsed']);
                /* v1.15+：「🔍 保存并体检」的结果渲染在「消息推送」分组上方的提示区 */
                if ($gKey === 'notify' && $notifyCheck !== null) echo notify_check_html($notifyCheck);
            ?>
            <div class="cfg-group<?php echo $gCollapsed ? ' collapsed' : ''; ?>" id="cfgGroup-<?php echo h($gKey); ?>">
              <?php if ($gCollapsed): ?>
              <h3 class="cfg-toggle" onclick="toggleCfgGroup('<?php echo h($gKey); ?>')">
                <span class="cfg-caret">▾</span>
                <?php echo h($g['icon'] . ' ' . $g['title']); ?>
                <span class="cfg-hint"></span>
              </h3>
              <?php else: ?>
              <h3><?php echo h($g['icon'] . ' ' . $g['title']); ?></h3>
              <?php endif; ?>
              <?php if (!empty($g['note'])): ?>
              <p class="cfg-note"><?php echo h($g['note']); ?></p>
              <?php endif; ?>
              <div class="cfg-body">
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
            </div>
            <?php endforeach; ?>

            <div class="btn-group" style="margin-top:16px;">
              <button type="submit" class="btn green">💾 保存配置</button>
              <button type="button" class="btn orange" onclick="restartAfterSave()">💾 保存并重启容器</button>
              <button type="submit" class="btn blue" name="then_notify_test" value="1">🔔 保存并发送测试推送</button>
              <button type="submit" class="btn green" name="then_notify_check" value="1">🔍 保存并体检</button>
            </div>
            <p class="cfg-footnote">提示：「🔔 保存并发送测试推送」会先保存本页配置，再向小助手发一条测试通知，用于验证推送渠道是否配通（结果看任务历史或日志）。「🔍 保存并体检」同样先保存本页配置，然后只做只读检查：告诉你哪些渠道真正会发出去、哪些启用了却缺必填项，结果直接显示在「消息推送」分组上方（不会发送任何消息）。</p>
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

<!-- v1.16+：移动端底部导航（≤640 显示，对应 4 个页面） -->
<nav class="mobile-tabbar" aria-label="页面导航">
  <button type="button" class="tab-item active" data-tab="overview" onclick="switchTab('overview')"><span class="tab-icon">📊</span><span class="tab-text">概览</span></button>
  <button type="button" class="tab-item" data-tab="tasks" onclick="switchTab('tasks')"><span class="tab-icon">🚀</span><span class="tab-text">任务</span></button>
  <button type="button" class="tab-item" data-tab="config" onclick="switchTab('config')"><span class="tab-icon">⚙️</span><span class="tab-text">配置</span></button>
  <button type="button" class="tab-item" data-tab="log" onclick="switchTab('log')"><span class="tab-icon">📝</span><span class="tab-text">日志</span></button>
</nav>
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
  document.querySelectorAll('.nav-item, .mobile-tabbar .tab-item').forEach(function(el) { el.classList.toggle('active', el.dataset.tab === name); });
  var panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');
  try { localStorage.setItem('m7a_tab', name); } catch(e) {}
  if (name === 'log') refreshLog();
  if (name === 'tasks') loadHistory();
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

// 操作结果通知 5 秒后自动消失（表单 POST 返回的 .msg 通知条）
setTimeout(function(){
  var msgs = document.querySelectorAll('.msg');
  for (var i = 0; i < msgs.length; i++) {
    (function(el){
      el.style.transition = 'opacity .6s ease';
      setTimeout(function(){ el.style.opacity = '0'; }, 5000);
      setTimeout(function(){ el.style.display = 'none'; }, 5600);
    })(msgs[i]);
  }
}, 300);

// 延迟检查更新（等页面渲染完）；手动更新模式下不自动检查
if (PANEL_UPDATE_MODE === 'auto') { setTimeout(function(){ checkUpdate(false); }, 1500); }
// 加载时展示镜像状态（读缓存，不强制联网）
setTimeout(function(){ checkImage(false); }, 2200);

/* ===== 配置子页切换 ===== */
function switchCfgTab(name) {
  document.querySelectorAll('.cfg-panel').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.cfg-tab').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('cfgPanel-' + name).classList.add('active');
  event.target.classList.add('active');
}

/* ===== v1.15+：折叠分组展开 / 收起 ===== */
function toggleCfgGroup(key) {
  var el = document.getElementById('cfgGroup-' + key);
  if (el) el.classList.toggle('collapsed');
}

/* ===== v1.15+：复制文本（clipboard 优先，失败回退 execCommand） ===== */
function copyText(inputId, btn) {
  var el = document.getElementById(inputId);
  if (!el) return;
  var text = (el.innerText !== undefined && el.innerText !== null && el.innerText !== '') ? el.innerText : el.textContent;
  text = (text || '').trim();
  var old = btn ? btn.textContent : '';
  var done = function(ok) {
    if (!btn) return;
    btn.textContent = ok ? '✅ 已复制' : '❌ 复制失败';
    /* v1.15+：追加视觉反馈（变绿 + 轻微弹一下），原有行为不变 */
    btn.classList.remove('copy-ok', 'copy-fail');
    btn.classList.add(ok ? 'copy-ok' : 'copy-fail');
    setTimeout(function() { btn.classList.remove('copy-ok', 'copy-fail'); }, 1200);
    setTimeout(function() { btn.textContent = old; }, 1800);
  };
  var fallback = function() {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      done(ok);
    } catch (e) { done(false); }
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function(){ done(true); }).catch(fallback);
  } else {
    fallback();
  }
}

/* ===== 自动更新检查 ===== */
var PANEL_UPDATE_MODE = <?php echo json_encode(panel_update_mode()); ?>;
var _updIgnoredV = '';
try { _updIgnoredV = localStorage.getItem('m7a_upd_ignore_v') || ''; } catch(e) {}

function checkUpdate(force) {
  if (!force && _updIgnoredV) return; // 忽略过该版本则不提示；手动检查可绕过
  fetch('?ajax=check_update').then(function(r){ return r.json(); }).then(function(d){
    if (d && d.ok && d.has_update) {
      if (!force && d.latest === _updIgnoredV) return;
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

function ignoreVersion() {
  var v = document.getElementById('updLatest').textContent;
  if (!v) return;
  try { localStorage.setItem('m7a_upd_ignore_v', v); } catch(e) {}
  document.getElementById('updateBanner').style.display = 'none';
}

function updateAssistantImage() {
  if (!confirm('确定更新三月七小助手镜像？将依次尝试官方源与加速镜像（南大/DaoCloud/dockerproxy），可能需要几分钟。')) return;
  var btn = event.target;
  var oldTxt = btn.textContent;
  btn.disabled = true; btn.textContent = '更新中…';
  var fd = new FormData();
  fd.append('action', 'update_image');
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        checkImage(true); // 更新后强制刷新镜像状态
      } else {
        alert('❌ ' + (d && d.err ? d.err : '更新失败'));
      }
      btn.disabled = false; btn.textContent = oldTxt;
    })
    .catch(function(){ alert('❌ 网络错误'); btn.disabled = false; btn.textContent = oldTxt; });
}

function checkImage(force) {
  var el = document.getElementById('imgStatus');
  if (!el) return;
  el.textContent = '检测中…';
  fetch('?ajax=image_check' + (force ? '&force=1' : '')).then(function(r){ return r.json(); }).then(function(d){
    if (!d) { el.textContent = '无响应'; return; }
    if (!d.ok) { el.textContent = '❌ ' + (d.err || '检测失败'); return; }
    if (d.has_update) {
      el.textContent = '⚠️ 镜像较旧，建议更新';
    } else if (d.remote_unknown) {
      el.textContent = '⚠️ 无法确认远程版本（GHCR 查询失败），本地：' + (d.local || '未知');
    } else if (d.local) {
      el.textContent = '✅ 已是最新镜像';
    } else {
      el.textContent = d.err || '检测完成';
    }
  }).catch(function(){ el.textContent = '网络错误'; });
}

function setUpdateMode(mode) {
  var fd = new FormData();
  fd.append('action', 'set_update_mode');
  fd.append('mode', mode);
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        if (mode === 'manual') document.getElementById('updateBanner').style.display = 'none';
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '切换失败'));
      }
    }).catch(function(){ alert('❌ 网络错误'); });
}

function setAfterFinish(v) {
  var fd = new FormData();
  fd.append('action', 'set_after_finish');
  fd.append('value', v);
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        var valEl = document.getElementById('afterFinishVal');
        var badge = document.getElementById('afterFinishBadge');
        if (v === 'Exit') {
          if (valEl) valEl.textContent = '跑完自动退出游戏';
          if (badge) { badge.textContent = '自动退出'; badge.style.background = 'var(--green,#22c55e)'; badge.style.color = '#fff'; }
        } else {
          if (valEl) valEl.textContent = '跑完保持界面';
          if (badge) { badge.textContent = '保持界面'; badge.style.background = 'var(--gray,#9ca3af)'; badge.style.color = '#fff'; }
        }
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '切换失败'));
      }
    }).catch(function(){ alert('❌ 网络错误'); });
}

/* ===== 版本备份 / 回滚（v1.14+） ===== */
function rollbackPanel(file) {
  if (!file) return;
  if (!confirm('确定回滚到备份 ' + file + '？\n当前版本会先自动备份一次，回滚后面板会刷新。')) return;
  var fd = new FormData();
  fd.append('action', 'backup_rollback');
  fd.append('file', file);
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        location.reload();
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '回滚失败'));
      }
    }).catch(function(){ alert('❌ 网络错误，回滚未完成'); });
}

/* ===== 任务执行历史（v1.14+） ===== */
function histViewLog() {
  switchTab('log');
  refreshLog();
}
function renderHistoryRows(items) {
  var tb = document.getElementById('histBody');
  if (!tb) return;
  if (!items || !items.length) {
    tb.innerHTML = '<tr><td colspan="5" class="hist-empty">暂无任务执行记录，点击上方任务按钮即可开始记录</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < items.length; i++) {
    var it = items[i];
    html += '<tr>'
      + '<td data-label="任务">' + escapeHtml(it.task_label || '') + '</td>'
      + '<td data-label="开始时间">' + escapeHtml(it.start_str || '') + '</td>'
      + '<td data-label="耗时">' + escapeHtml(it.duration_str || '') + '</td>'
      + '<td data-label="状态"><span class="hist-badge" style="background:' + escapeHtml(it.status_color || '') + ';">' + escapeHtml(it.status_label || '') + '</span></td>'
      + '<td data-label="操作" style="text-align:right;"><button type="button" class="btn small gray" onclick="histViewLog()">📝 查看日志</button></td>'
      + '</tr>';
  }
  tb.innerHTML = html;
}
function loadHistory() {
  fetch('?ajax=history').then(function(r){ return r.json(); }).then(function(d){
    if (!d || !d.ok) return;
    var st = document.getElementById('histStat');
    if (st) st.textContent = '今日执行 ' + d.today_count + ' 次 · 成功 ' + d.today_ok + ' 次';
    renderHistoryRows(d.items);
  }).catch(function(){});
}
function clearHistory() {
  if (!confirm('确定清空全部任务执行历史？此操作不可恢复。')) return;
  var fd = new FormData();
  fd.append('action', 'history_clear');
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        loadHistory();
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '清空失败'));
      }
    }).catch(function(){ alert('❌ 网络错误'); });
}

/* ===== 资源监控（v1.13+） ===== */
var _monChart = null;
var _monTimer = null;
var _monRange = '1h';
function loadECharts(cb) {
  if (window.echarts) { if (cb) cb(); return; }
  var urls = [
    'https://cdn.bootcdn.net/ajax/libs/echarts/5.4.3/echarts.min.js',
    'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js'
  ];
  var i = 0;
  function tryNext() {
    if (i >= urls.length) {
      var box = document.getElementById('monChart');
      if (box) box.innerHTML = '<div style="text-align:center;color:var(--muted);padding-top:110px;font-size:13px;">⚠️ 图表库加载失败（需外网 CDN），上方数字指标仍可用</div>';
      return;
    }
    var s = document.createElement('script');
    s.src = urls[i++];
    s.onload = function(){ if (cb) cb(); };
    s.onerror = tryNext;
    document.head.appendChild(s);
  }
  tryNext();
}
function fmtBytes(b) {
  if (b >= 1024*1024*1024) return (b/1024/1024/1024).toFixed(1) + 'G';
  if (b >= 1024*1024) return (b/1024/1024).toFixed(1) + 'M';
  if (b >= 1024) return (b/1024).toFixed(1) + 'K';
  return b.toFixed(0) + 'B';
}
function fmtSpeed(bps) { return fmtBytes(bps) + '/s'; }
function fmtUptime(sec) {
  if (!sec || sec <= 0) return '--';
  var d = Math.floor(sec/86400), h = Math.floor(sec%86400/3600), m = Math.floor(sec%3600/60);
  if (d > 0) return d + '天' + h + '小时';
  if (h > 0) return h + '小时' + m + '分';
  return m + '分钟';
}
/* v1.16+：数字滚动（requestAnimationFrame 300ms；解析目标文本中的数字段滚动，
   支持小数与 %/GB 等前后缀；当前为 -- 等无数字占位、或含多个数字的复合文本时直接落值不滚动） */
function tweenNum(el, target) {
  if (!el) return;
  var to = String(target);
  var cur = el.textContent || '';
  var mTo = to.match(/^(\D*?)(-?\d+(?:\.\d+)?)(\D*)$/);
  var mFrom = /^(\D*?)(-?\d+(?:\.\d+)?)(\D*)$/.exec(cur);
  if (!mTo || !mFrom) { el.textContent = to; return; }
  var from = parseFloat(mFrom[2]);
  var toV = parseFloat(mTo[2]);
  if (isNaN(from) || isNaN(toV)) { el.textContent = to; return; }
  var dec = (mTo[2].indexOf('.') >= 0) ? mTo[2].split('.')[1].length : 0;
  var token = (el._tweenTok = (el._tweenTok || 0) + 1);
  var t0 = null;
  function step(ts) {
    if (el._tweenTok !== token) return;
    if (t0 === null) t0 = ts;
    var p = Math.min(1, (ts - t0) / 300);
    var e = 1 - Math.pow(1 - p, 3);
    el.textContent = mTo[1] + (from + (toV - from) * e).toFixed(dec) + mTo[3];
    if (p < 1) window.requestAnimationFrame(step);
  }
  window.requestAnimationFrame(step);
}
function setMonStat(id, val, pct, cls) {
  var el = document.getElementById(id);
  if (!el) return;
  el.className = 'mon-stat' + (cls ? ' ' + cls : '');
  var v = el.querySelector('.m-value'); if (v) tweenNum(v, val);
  var bar = el.querySelector('.m-bar > i'); if (bar) bar.style.width = (pct || 0) + '%';
}
function renderMonitor(d) {
  var pts = d.points || [];
  var last = pts.length ? pts[pts.length-1] : null;
  if (last) {
    var cpuCls = last.cpu > 80 ? 'danger' : (last.cpu > 60 ? 'warn' : '');
    var memCls = last.mem > 80 ? 'danger' : (last.mem > 60 ? 'warn' : '');
    var diskCls = last.disk > 80 ? 'danger' : (last.disk > 60 ? 'warn' : '');
    setMonStat('monCpu', (last.cpu||0).toFixed(1) + '%', last.cpu, cpuCls);
    setMonStat('monMem', (last.mem||0).toFixed(1) + '%', last.mem, memCls);
    setMonStat('monDisk', (last.disk||0).toFixed(1) + '%', last.disk, diskCls);
    tweenNum(document.querySelector('#monLoad .m-value'), (last.load||0).toFixed(2));
    tweenNum(document.querySelector('#monNet .m-value'), '↓' + fmtSpeed(last.netIn||0));
    document.getElementById('monNetSub').textContent = '↑' + fmtSpeed(last.netOut||0);
    tweenNum(document.querySelector('#monUp .m-value'), fmtUptime(last.uptime||0));
    document.getElementById('monUpSub').textContent = d.running ? '容器：运行中' : '容器：已停止';
  }
  var h = d.host || {};
  var hostEl = document.getElementById('monHost');
  if (hostEl && (h.name || h.os)) {
    hostEl.innerHTML =
      '<span class="mon-host-row">💻 <b>' + escapeHtml(h.name || '') + '</b></span>' +
      '<span class="mon-host-row">系统 <b>' + escapeHtml(h.os || '--') + '</b></span>' +
      '<span class="mon-host-row">Docker <b>' + escapeHtml(h.docker || '--') + '</b></span>' +
      '<span class="mon-host-row">核心 <b>' + (h.cores || '--') + '</b></span>' +
      '<span class="mon-host-row">内存 <b>' + fmtBytes(h.memTotal||0) + '</b></span>' +
      '<span class="mon-host-row">磁盘 <b>' + fmtBytes(h.diskUsed||0) + ' / ' + fmtBytes(h.diskTotal||0) + '</b></span>';
  }
  if (window.echarts) {
    if (!_monChart) {
      var box = document.getElementById('monChart');
      if (box) { box.innerHTML = ''; _monChart = echarts.init(box); }
    }
    if (_monChart) {
      var now = Math.floor(Date.now()/1000);
      var pts2 = pts, timeFmt = {hour12:false};
      if (_monRange === '1m') { pts2 = pts.filter(function(p){ return now - p.t <= 60; }); }
      else if (_monRange === '1h') { pts2 = pts.filter(function(p){ return now - p.t <= 3600; }); }
      else if (_monRange === '1d') { pts2 = d.minutes || []; timeFmt = {hour12:false, hour:'2-digit', minute:'2-digit'}; }
      var times = pts2.map(function(p){ return new Date(p.t*1000).toLocaleTimeString('zh-CN', timeFmt); });
      _monChart.setOption({
        tooltip: { trigger: 'axis', confine: true },
        animationDuration: 800, animationEasing: 'cubicOut',
        legend: { data: ['CPU','内存','磁盘'], textStyle:{color:'#999'}, top:0 },
        grid: { left:42, right:16, top:34, bottom:26 },
        xAxis: { type:'category', data:times, boundaryGap:false, axisLine:{lineStyle:{color:'#999'}}, axisLabel:{color:'#999', fontSize:10} },
        yAxis: { type:'value', max:100, axisLabel:{formatter:'{value}%', color:'#999', fontSize:10}, splitLine:{lineStyle:{color:'rgba(128,128,128,.15)'}} },
        series: [
          { name:'CPU', type:'line', smooth:true, showSymbol:false, data:pts2.map(function(p){return +(p.cpu||0).toFixed(1);}), lineStyle:{width:2,color:'#ec4899'}, itemStyle:{color:'#ec4899'}, areaStyle:{opacity:.08} },
          { name:'内存', type:'line', smooth:true, showSymbol:false, data:pts2.map(function(p){return +(p.mem||0).toFixed(1);}), lineStyle:{width:2,color:'#38bdf8'}, itemStyle:{color:'#38bdf8'}, areaStyle:{opacity:.08} },
          { name:'磁盘', type:'line', smooth:true, showSymbol:false, data:pts2.map(function(p){return +(p.disk||0).toFixed(1);}), lineStyle:{width:2,color:'#f59e0b'}, itemStyle:{color:'#f59e0b'}, areaStyle:{opacity:.08} }
        ]
      });
    }
  }
}
function setMonRange(r) {
  _monRange = r;
  var btns = document.querySelectorAll('.mon-range .btn');
  for (var i = 0; i < btns.length; i++) {
    btns[i].className = 'btn small' + (btns[i].getAttribute('data-range') === r ? ' active' : '');
  }
  loadMonitor(true);
}
function loadMonitor() {
  var iv = parseInt(document.getElementById('monInterval').value) || 1;
  fetch('?ajax=monitor&iv=' + iv)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d || !d.ok) return;
      renderMonitor(d);
      var badge = document.getElementById('monLiveBadge');
      if (badge) {
        badge.textContent = d.running ? '实时' : '容器已停止';
        badge.style.background = d.running ? 'var(--green,#22c55e)' : 'var(--gray,#9ca3af)';
      }
    }).catch(function(){});
}
function startMonitor() {
  stopMonitor();
  loadMonitor();
  var iv = parseInt(document.getElementById('monInterval').value) || 1;
  /* v1.16+：手机端且用户未手动设置过刷新间隔时，默认放宽到 3 秒；手动选过则尊重用户选择 */
  if (!window._monIvTouched && window.innerWidth <= 640) iv = 3;
  _monTimer = setInterval(loadMonitor, Math.max(1, iv) * 1000);
}
function stopMonitor() {
  if (_monTimer) { clearInterval(_monTimer); _monTimer = null; }
}
function setMonitorInterval(iv) {
  window._monIvTouched = true; /* v1.16+：标记用户已手动设置过刷新间隔 */
  var fd = new FormData();
  fd.append('action', 'set_monitor_interval');
  fd.append('interval', iv);
  var csrf = document.querySelector('input[name="csrf"]');
  if (csrf) fd.append('csrf', csrf.value);
  fetch('index.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.ok) {
        alert('✅ ' + d.msg);
        var t = document.getElementById('monIvText');
        if (t) t.textContent = iv;
        startMonitor();
      } else {
        alert('❌ ' + (d && d.msg ? d.msg : '设置失败'));
      }
    }).catch(function(){ alert('❌ 网络错误'); });
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
loadECharts(function(){ loadMonitor(); });
startMonitor();

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

/* ===== v1.15+：折叠分组的高度过渡（独立视觉增强，不改变 toggleCfgGroup 行为） =====
   纯 CSS 已能用 max-height 过渡；这里在能测到真实高度时用实测值，窗口缩放时交回 CSS，避免内容被裁切。 */
(function initCfgCollapseMotion() {
  var bodies = document.querySelectorAll('.cfg-group .cfg-body');
  if (!bodies.length) return;
  var findGroup = function(el) {
    var g = el.parentNode;
    while (g && g.nodeType === 1 && !(g.classList && g.classList.contains('cfg-group'))) g = g.parentNode;
    return (g && g.nodeType === 1) ? g : null;
  };
  var sync = function(body, group) {
    if (group.classList.contains('collapsed')) { body.style.maxHeight = '0px'; return; }
    if (body.scrollHeight > 0) { body.style.maxHeight = (body.scrollHeight + 24) + 'px'; }
    else { body.style.maxHeight = ''; }
  };
  Array.prototype.forEach.call(bodies, function(body) {
    var group = findGroup(body);
    if (!group) return;
    sync(body, group);
    var head = group.querySelector('h3.cfg-toggle');
    if (!head) return;
    head.addEventListener('click', function() {
      window.requestAnimationFrame(function() { sync(body, group); });
    });
  });
  window.addEventListener('resize', function() {
    Array.prototype.forEach.call(bodies, function(body) {
      var group = findGroup(body);
      if (!group) return;
      if (group.classList.contains('collapsed')) body.style.maxHeight = '0px';
      else body.style.maxHeight = '';
    });
  });
})();
</script>
</body>
</html>
