<?php
flush();
ob_start();
ob_implicit_flush(1);

// Railway sits behind a reverse proxy, so Telegram's source IP must NOT be
// validated against the container's REMOTE_ADDR.

ini_set("expose_php", "Off");
ini_set("max_execution_time", "60");
ini_set("memory_limit", "128M");
ini_set("post_max_size", "1M");
ignore_user_abort(true);

error_reporting(E_ALL);
ini_set("log_errors", "1");
ini_set("display_errors", "0");

date_default_timezone_set('Asia/Tehran');

include 'config.php';
include('lib/jdf.php');
//==========================// bot //==========================//

function bot($method, $datas = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . API_KEY . '/' . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    return json_decode(curl_exec($ch));
}

define('API_SAFE', API_KEY);
define('SAFE_ID', explode(':', API_SAFE)[0]);

function KajSafe($method, $datas = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . API_SAFE . '/' . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    return json_decode(curl_exec($ch));
}
//========================== // update // ==============================
$update = json_decode(file_get_contents('php://input'));

// Empty / health / non-Telegram requests
if (!$update || (!isset($update->message) && !isset($update->callback_query) && !isset($update->inline_query))) {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    exit;
}

$from_id = null;
$chat_id = null;
$message_id = null;
$text = null;
$data = null;
$tc = null;


if (isset($update->message)) {
    $message = $update->message;
    $message_id = $message->message_id;
    $text = safe($message->text);
    $chat_id = $message->chat->id;
    $tc = $message->chat->type;
    $first_name = $message->from->first_name;
    $username = $message->from->username;
    $from_id = $message->from->id;
}

if (isset($update->callback_query)) {
    $callback_query = $update->callback_query;
    $callback_query_id = $callback_query->id;
    $data = $callback_query->data;
    $from_id = $callback_query->from->id;
    $tc = $callback_query->chat->type;
    $message_id = $callback_query->message->message_id;
    $chat_id = $callback_query->message->chat->id;
}

$creator = $admin;

//======// Database //======//
$from_id = $from_id ?? null;
$chat_id = $chat_id ?? null;
$user = $from_id ? mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '" . mysqli_real_escape_string($connect, (string)$from_id) . "' LIMIT 1")) : null;
$block = $from_id ? mysqli_query($connect, "SELECT * FROM `block` WHERE `id` = '" . mysqli_real_escape_string($connect, (string)$from_id) . "' LIMIT 1") : null;
$admin = $from_id ? mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `admin` WHERE `admin` = '" . mysqli_real_escape_string($connect, (string)$from_id) . "' LIMIT 1")) : null;
if (!$admin) { $admin = null; }
$settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1")) ?: [
    'botname' => 'VPN', 'can_reset' => 3, 'coin_need' => 3, 'coin_daily' => 1, 'coin_referral' => 1, 'bot_mode' => 'on'
];
$send = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `sendall` LIMIT 1"));

date_default_timezone_set('Asia/Tehran');
$timestamp = time();
$time = date('H:i');
$date = jalali_today();
$ToDay = jdate('l');

$botname = $settings['botname'] ?? 'VPN';
$can_reset = $settings['can_reset'] ?? 3;
$coin_need = $settings['coin_need'] ?? 3;
$coin_daily = $settings['coin_daily'] ?? 1;
$coin_referral = $settings['coin_referral'] ?? 1;
//==========================// function //==========================//
function curl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return json_decode(curl_exec($ch));
}

function Takhmin($fil)
{
    global $sendall_min;

    if ($fil <= $sendall_min) {
        return "1";
    } else {
        $besanie = $fil / $sendall_min;
        return ceil($besanie) + 1;
    }
}

function safe($text)
{
    global $connect;
    $text = $connect->real_escape_string($text);
    $array = ['$', ';', '"', "'", '<', '>'];
    return str_replace($array, '', $text);
}

function jalali_today()
{
    list($y, $m, $d) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
    $m = str_pad($m, 2, '0', STR_PAD_LEFT);
    $d = str_pad($d, 2, '0', STR_PAD_LEFT);
    return $y . '/' . $m . '/' . $d;
}

function get_setting_status($settingKey)
{
    global $connect;

    $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));

    if ($settings[$settingKey] == "on") {
        return "🟢";
    } else {
        return "🔴";
    }
}

function get_setting_value($settingKey)
{
    global $connect;

    $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));

    if (!$settings || $settings[$settingKey] == NULL) {
        return "تنظیم نشده";
    } else {
        return $settings[$settingKey];
    }
}

function is_bot_admin($uid)
{
    global $connect, $creator;
    if ($uid === null || $uid === '') {
        return false;
    }
    if ($creator !== '' && (string) $uid === (string) $creator) {
        return true;
    }
    if (!$connect) {
        return false;
    }
    $uid = mysqli_real_escape_string($connect, (string) $uid);
    $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT `admin` FROM `admin` WHERE `admin` = '$uid' LIMIT 1"));
    return (bool) $r;
}


//===============// Config Helpers //===============//

function normalizeConfig($config)
{
    if (!is_string($config)) return false;

    $config = trim($config, " \n\r\t\"'`");
    $config = rtrim($config, ",.()[]{}<>");

    if ($config === '') return false;

    return $config;
}

function parseConfig($config)
{
    $result = [
        'valid' => false,
        'type'  => null
    ];

    // detect protocol
    if (!preg_match('/^(vmess|vless|trojan|ss|socks):\/\//i', $config, $m)) {
        return $result;
    }

    $type = strtolower($m[1]);
    $result['type'] = $type;

    switch ($type) {

        case 'vmess':
            $result['valid'] = validateVmess($config);
            break;

        case 'vless':
        case 'trojan':
        case 'socks':
            $result['valid'] = validateUrlStyle($config);
            break;

        case 'ss':
            $result['valid'] = validateSS($config);
            break;
    }

    return $result;
}

function validateVmess($config)
{
    $base64 = substr($config, 8);
    $decoded = safeBase64Decode($base64);

    if ($decoded === false) return false;

    $json = json_decode($decoded, true);

    return (
        is_array($json) &&
        !empty($json['add']) &&
        isset($json['port']) &&
        is_numeric($json['port'])
    );
}

function validateUrlStyle($config)
{
    $url = parse_url($config);

    if (!$url) return false;

    if (empty($url['host']) || empty($url['port'])) {
        return false;
    }

    if (!is_numeric($url['port'])) return false;

    return true;
}

function validateSS($config)
{
    $body = substr($config, 5);

    // remove fragment
    $body = preg_replace('/#([^#]*)$/', '', $body);

    // plain
    if (strpos($body, '@') !== false) {
        $url = parse_url('ss://' . $body);
        return $url && !empty($url['host']) && !empty($url['port']);
    }

    // base64
    $decoded = safeBase64Decode($body);

    if ($decoded !== false && strpos($decoded, '@') !== false) {
        $url = parse_url('ss://' . $decoded);
        return $url && !empty($url['host']) && !empty($url['port']);
    }

    return false;
}

function safeBase64Decode($data)
{
    if (!is_string($data)) return false;

    $data = str_replace(['-', '_'], ['+', '/'], $data);

    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($data, true);

    if ($decoded === false) return false;

    if (!mb_check_encoding($decoded, 'UTF-8')) return false;

    return $decoded;
}

function SafeConfig($config)
{
    global $connect, $usernamebot;

    $code = rand(1000, 9999);
    $new_name = "🚀 ViP #$code - @$usernamebot";

    // normalize
    $config = trim($config, " \n\r\t\"'`");
    $config = rtrim($config, '",.()[]{}<>');

    // try global base64 decode (subscription support)
    $decoded = safeBase64Decode($config);
    if ($decoded !== false && containsProtocol($decoded)) {
        $config = $decoded;
    }

    // split multiple configs (subscription)
    $lines = preg_split('/\r\n|\r|\n/', $config);
    $output = [];

    foreach ($lines as $line) {

        $line = trim($line);
        if ($line === '') continue;

        // VMESS
        if (stripos($line, 'vmess://') === 0) {

            $base64 = substr($line, 8);
            $decoded = safeBase64Decode($base64);

            if ($decoded !== false) {
                $json = json_decode($decoded, true);

                if (is_array($json)) {
                    $json['ps'] = $new_name;

                    $line = 'vmess://' . base64_encode(
                        json_encode($json, JSON_UNESCAPED_UNICODE)
                    );
                }
            }
        }

        // SS (handle both formats)
        elseif (stripos($line, 'ss://') === 0) {

            // remove only last fragment
            $line = preg_replace('/#([^#]*)$/', '', $line);

            $body = substr($line, 5);

            // try decode internal base64
            $decoded = safeBase64Decode($body);

            if ($decoded !== false && strpos($decoded, '@') !== false) {
                $line = 'ss://' . base64_encode($decoded);
            }

            $line .= '#' . rawurlencode($new_name);
        }

        // OTHER protocols
        elseif (preg_match('/^(vless|trojan|socks|http|https):\/\//i', $line)) {

            $line = preg_replace('/#([^#]*)$/', '', $line);
            $line .= '#' . rawurlencode($new_name);
        }

        $output[] = $line;
    }

    $final = implode("\n", $output);

    return mysqli_real_escape_string($connect, $final);
}

function containsProtocol($str)
{
    return preg_match('/(vmess|vless|trojan|ss|socks):\/\//i', $str);
}
//================// Join Function //================//

//====// بررسی جوین های اجباری //====//
function check_join($id)
{
    global $connect;

    $in_ch = [];
    $chs = mysqli_query($connect, "SELECT `idoruser` FROM `channels`");
    $fil = mysqli_num_rows($chs);

    if ($fil == 0) {
        return true;
    }

    for ($i = 0; $i < $fil; $i++) {
        $okk = mysqli_fetch_assoc($chs)['idoruser'];
        $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `channels` WHERE `idoruser` = '$okk' LIMIT 1"));
        $link = $ch['link'];
        $ch_id = $ch['idoruser'];

        $type = KajSafe("getChatMember", ["chat_id" => "$ch_id", "user_id" => $id]);
        $status = null;
        if (is_object($type) && isset($type->result->status)) {
            $status = $type->result->status;
        } elseif (is_array($type) && isset($type['result']['status'])) {
            $status = $type['result']['status'];
        }
        if ($status == 'creator' || $status == 'administrator' || $status == 'member') {
            $in_ch[$ch_id] = $status;
        } else {
            return false;
        }
    }
    return true;
}

//====// بررسی لینک//====//
function check_link($from_id, $Channel)
{

    $forchaneel = KajSafe('getChatMember', [
        'chat_id' => $Channel,
        'user_id' => $from_id
    ]);

    if (!$forchaneel || empty($forchaneel->ok) || empty($forchaneel->result->status)) {
        return false;
    }
    $tch = $forchaneel->result->status;

    if ($tch != 'member' && $tch != 'creator' && $tch != 'administrator') {
        return false;
    }
    return true;
}


//====// ایجاد کیبورد چنل ها //====//
function join_keyboard($chat_id)
{
    global $connect;

    $from_id = $chat_id;
    $d4 = [];
    $ar = [];

    $chs = mysqli_query($connect, "SELECT `idoruser` FROM `channels`");
    if ($chs) {
        while ($row = mysqli_fetch_assoc($chs)) {
            $ar[] = $row["idoruser"];
        }
    }

    foreach ($ar as $okk) {
        $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `channels` WHERE `idoruser` = '$okk' LIMIT 1"));
        if (!$ch) continue;

        $link = $ch['link'];
        $chlink = $ch['idoruser'];

        $ch_info = KajSafe("getChat", ["chat_id" => "$chlink"]);
        $ch_name = (is_object($ch_info)) ? $ch_info->result->title : $ch_info['result']['title'];

        if ($link != null && check_link($from_id, $okk) == false) {
            $d4[] = [['text' => "$ch_name", 'url' => $link]];
        }
    }

    $d4[] = [['text' => '✅ تایید عضویت', 'callback_data' => 'join']];

    return $d4;
}

//====// ارسال لیست چنل ها //====//
function is_join($chat_id)
{
    global $botname;
    $keyboard = join_keyboard($chat_id);

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "کاربر گرامی، جهت استفاده از ربات $botname الزامیست، عضو کانال زیر شوید 👇🏼

👇 بعد از عضویت در کانال روی دکمه « ✅ تایید عضویت » بزنید 👇",
        'parse_mode' => 'MarkDown',
        'reply_markup' => json_encode(array('inline_keyboard' => $keyboard))
    ]);

    exit;
}

//====// بروزرسانی لیست چنل ها //====//
function edit_join($chat_id, $message_id)
{
    $keyboard = join_keyboard($chat_id);

    bot('editMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => $keyboard
        ])
    ]);
}
//==============================// keybord and Text //==============================//
$home = json_encode([
    'keyboard' => [
        [['text' => "🚀 سرویس جدید"]],
        [['text' => "🛍 سرویس های من"], ['text' => "👤 حساب کاربری"]],
        [['text' => "💎 افزایش رفرال"]],
        [['text' => "🎉 سکه روزانه"], ['text' => "📝 ماموریت‌ها"]],
    ],
    'resize_keyboard' => true,
]);

if (is_bot_admin($from_id)) {
    $HomeData = json_decode($home, true);
    $HomeData['keyboard'][] = [['text' => '👤 پنل مدیریت 👤']];
    $home = json_encode($HomeData);
}

//=======// Missions Section //=======//

$missions = [];
$res = mysqli_query($connect, "SELECT mission FROM `Missions` WHERE `user` = '$from_id'");
while ($row = mysqli_fetch_assoc($res)) {
    $missions[] = $row['mission'];
}

$buttons = [
    ["🎖 رسیدن به 10 رفرال", "referral|10"],
    ["🥉 رسیدن به 30 رفرال", "referral|30"],
    ["🥈 رسیدن به 50 رفرال", "referral|50"],
    ["🥇 رسیدن به 100 رفرال", "referral|100"],
];

$inline_keyboard = [];

foreach ($buttons as $b) {
    $done = in_array($b[1], $missions);
    $inline_keyboard[] = [[
        'text' => $b[0] . ($done ? " ✅" : " ❌"),
        'callback_data' => "bonus|" . $b[1]
    ]];
}

$missions_section = json_encode([
    'inline_keyboard' => $inline_keyboard
]);

//=======// End Missions //=======//

$back = json_encode([
    'keyboard' => [
        [['text' => "↩️ صفحه قبل"]],
    ],
    'resize_keyboard' => true,
]);

$admin_panel = json_encode([
    'keyboard' => [
        [['text' => "📊 آمار ربات 📊"]],
        [['text' => "💬 بخش ارسال"], ['text' => "🤖 تنظیمات ربات"], ['text' => "👤 بخش کاربران"]],
        [['text' => "🔒 عضویت اجباری"], ['text' => "🔌 وضعیت ربات"]],
        [['text' => "🟢 روشن کردن ربات"], ['text' => "🔴 خاموش کردن ربات"]],
        [['text' => "↩️ صفحه قبل"]],
    ],
    'resize_keyboard' => true,
]);

$user_section = json_encode([
    'keyboard' => [
        [['text' => "📊 اطلاعات کاربر"]],
        [['text' => "➖ حذف کانفیگ"], ['text' => "➕ افزودن کانفیگ"]],
        [['text' => "➖ کاهش سکه"], ['text' => "➕ افزایش سکه"]],
        [['text' => "❌ حذف مسدودیت"], ['text' => "⚠️ مسدود کردن"]],
        [['text' => "برگشت 🔙"]],
    ],
    'resize_keyboard' => true,
]);

$send_section = json_encode([
    'keyboard' => [
        [['text' => "💬 ارسال پیام به کاربر"]],
        [['text' => "💬 پیام همگانی"], ['text' => "↗️ فوروارد همگانی"]],
        [['text' => "برگشت 🔙"]],
    ],
    'resize_keyboard' => true,
]);

$setting_section = json_encode([
    'keyboard' => [
        [['text' => "💎 تنظیمات ربات"]],
        [['text' => "📣 مدیریت قفل ها"], ['text' => "👤 مدیریت ادمین ها"]],
        [['text' => "برگشت 🔙"]],
    ],
    'resize_keyboard' => true,
]);

$manage_admin = json_encode([
    'keyboard' => [
        [['text' => "➕ افزودن ادمین"]],
        [['text' => "👤 پنل مدیریت 👤"], ['text' => "📚 لیست ادمین ها"]],
    ],
    'resize_keyboard' => true,
]);

$manage_channel = json_encode([
    'keyboard' => [
        [['text' => "➕ افزودن چنل"]],
        [['text' => "📚 لیست چنل ها"]],
        [['text' => "👤 پنل مدیریت 👤"]],
    ],
    'resize_keyboard' => true,
]);

$config_section = json_encode([
    'keyboard' => [
        [['text' => "❌ حذف همه"]],
        [['text' => "📆 7 روز اخیر"], ['text' => "⏰ 24 ساعت اخیر"]],
        [['text' => "برگشت 🔙"]],
    ],
    'resize_keyboard' => true,
]);

$backpanel = json_encode([
    'keyboard' => [
        [['text' => "برگشت 🔙"]],
    ],
    'resize_keyboard' => true,
]);

$welcome = "👋 سلام، به $botname خوش اومدی؛

🚀 ربات $botname طراحی شده تا در شرایطی که اتصال به اینترنت آزاد سخت و غیرممکنه، شمارو به صورت رایگان به اینترنت آزاد وصل کنه!

طراحی شده با عشق، برای مردم شریف ایران❤️";
//==============================//  Anti Spam //==============================//
if ($user["spam"] > time()) {
    exit();
}

if (!(is_bot_admin($from_id))) {
    $tt = time() + 0.2;
    $connect->query("UPDATE `user` SET `spam` = '$tt' WHERE `id` = '$from_id' LIMIT 1");
}
//===========================// checking //===========================//
if (mysqli_num_rows($block) > 0) {
    exit();
}

if (($settings['bot_mode'] ?? 'on') == "off" && !is_bot_admin($from_id) && $tc == 'private') {
    return bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❌ ربات به صورت موقت خاموش شده، لطفا ساعاتی بعد مجدد امتحان کنید.",
    ]);
}

if ($user['id'] != true) {
    $connect->query("INSERT INTO `user` (`id`, `join_date`, `create_at`, `update_at`) VALUES ('$from_id', '$date', '$ti', '$ti')");
}
//===========================// update //===========================//
if (($message or $data) && $tc == "private") {
    $connect->query("UPDATE `user` SET `update_at` = '$timestamp' WHERE `id` = '$from_id' LIMIT 1");
}
//===========================// start //===========================//
if (($text == "/start" or $text == "↩️ صفحه قبل") and $tc == 'private') {
    bot('sendmessage', [
        'chat_id' => $from_id,
        'text' => $welcome,
        'parse_mode' => "MarkDown",
        'reply_markup' => $home
    ]);

    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    exit;
}

if (strpos($text, "/start ref_") === 0) {
    $ref_id = str_replace("/start ref_", "", $text);

    bot('sendmessage', [
        'chat_id' => $from_id,
        'text' => $welcome,
        'parse_mode' => "MarkDown",
        'reply_markup' => $home
    ]);

    if ($user['id'] or $ref_id == $from_id) {
        exit();
    }

    if (check_join($from_id) !== true) {
        $connect->query("UPDATE `user` SET `data` = 'ref_$ref_id', `ref_id` = '$ref_id' WHERE `id` = '$from_id' LIMIT 1");
    } else {
        bot('sendmessage', [
            'chat_id' => $ref_id,
            'text' => "🎉 یک کاربر با لینک شما عضو ربات شد، $coin_referral سکه به حساب شما اضافه شد.",
        ]);

        $connect->query("UPDATE `user` SET `ref_id` = '$ref_id' WHERE `id` = '$from_id' LIMIT 1");
        $connect->query("UPDATE `user` SET `coin` = `coin` + $coin_referral, `ref_count` = `ref_count` + 1 WHERE `id` = '$ref_id' LIMIT 1");
    }

    exit();
}
//===========================// join checker //===========================//
if (check_join($from_id) !== true && $tc == 'private' && !is_bot_admin($from_id)) {
    is_join("$from_id");
    exit;
}
//===========================// data //===========================//
if ($data == "back" or $data == "cancel") {
    bot('deletemessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => $welcome,
        'reply_markup' => $home
    ]);
    $connect->query("UPDATE `user` SET `step` = 'none', `data` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    exit;
}

if ($data == 'join') {
    if (check_join($from_id) === true) {
        bot('deletemessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => $welcome,
            'reply_markup' => $home
        ]);

        if (strpos($user['data'], "ref_") === 0) {
            $ref_id = str_replace("ref_", "", $user['data']);

            bot('sendmessage', [
                'chat_id' => $ref_id,
                'text' => "🎉 یک کاربر با لینک شما عضو ربات شد، $coin_referral سکه به حساب شما اضافه شد.",
            ]);

            $connect->query("UPDATE `user` SET `data` = 'none' WHERE `id` = '$from_id' LIMIT 1");
            $connect->query("UPDATE `user` SET `coin` = `coin` + $coin_referral, `ref_count` = `ref_count` + 1 WHERE `id` = '$ref_id' LIMIT 1");
        }
    } else {
        bot('answercallbackquery', [
            'callback_query_id' => $callback_query_id,
            'text' => "⚠️ - لطفا ابتدا عضو کانال های جوین اجباری شوید.",
            'show_alert' => true
        ]);
    }
}
//===================// تغییر وضعیت ربات //===================//
if (($data == "back_setting") && is_bot_admin($from_id) && $tc == "private") {
    bot('editMessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💎 از منوی پایین میتونید بخش های مختلف ربات رو مدیریت کنید 👇",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "💎 تنظیمات عمومی", 'callback_data' => "general_setting"]],
            ]
        ])
    ]);
}

if ($data == "general_setting" && is_bot_admin($from_id)) {
    $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));

    $Bot_mode = get_setting_status('bot_mode');

    bot('editMessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💎 از منوی پایین میتونید بخش های مختلف ربات رو مدیریت کنید 👇",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => $settings['botname'], 'callback_data' => "change_settings|botname|1"], ['text' => "💎 نام ربات ›", 'callback_data' => "none"]],
                [['text' => $Bot_mode, 'callback_data' => "change_settings|bot_mode|1"], ['text' => "📊 وضعیت ربات ›", 'callback_data' => "none"]],
                [['text' => $settings['can_reset'], 'callback_data' => "change_settings|can_reset|1"], ['text' => "♻️ حداکثر تغییر لینک ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_need'], 'callback_data' => "change_settings|coin_need|1"], ['text' => "💳 هزینه کانفیگ ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_daily'], 'callback_data' => "change_settings|coin_daily|1"], ['text' => "🎉 جایزه روزانه ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_referral'], 'callback_data' => "change_settings|coin_referral|1"], ['text' => "👥 جایزه رفرال ›", 'callback_data' => "none"]],
                [['text' => "↩️ منوی قبل", 'callback_data' => "back_setting"]],
            ]
        ])
    ]);
}

if (strpos($data, "change_settings|") === 0) {
    $get_data = explode("|", $data);
    $Change_i = $get_data[1];
    $panel_id = $get_data[2];

    if ($Change_i == "bot_mode") {

        $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));
        $New_Mode = ($settings[$Change_i] == "on") ? "off" : "on";
        $connect->query("UPDATE `settings` SET `$Change_i` = '$New_Mode'");

        $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));
        $Bot_mode = get_setting_status('bot_mode');

        if ($panel_id == "1") {
            $keyboard = json_encode([
                'inline_keyboard' => [
                    [['text' => $settings['botname'], 'callback_data' => "change_settings|botname|1"], ['text' => "💎 نام ربات ›", 'callback_data' => "none"]],
                    [['text' => $Bot_mode, 'callback_data' => "change_settings|bot_mode|1"], ['text' => "📊 وضعیت ربات ›", 'callback_data' => "none"]],
                    [['text' => $settings['can_reset'], 'callback_data' => "change_settings|can_reset|1"], ['text' => "♻️ حداکثر تغییر لینک ›", 'callback_data' => "none"]],
                    [['text' => $settings['coin_need'], 'callback_data' => "change_settings|coin_need|1"], ['text' => "💳 هزینه کانفیگ ›", 'callback_data' => "none"]],
                    [['text' => $settings['coin_daily'], 'callback_data' => "change_settings|coin_daily|1"], ['text' => "🎉 جایزه روزانه ›", 'callback_data' => "none"]],
                    [['text' => $settings['coin_referral'], 'callback_data' => "change_settings|coin_referral|1"], ['text' => "👥 جایزه رفرال ›", 'callback_data' => "none"]],
                    [['text' => "↩️ منوی قبل", 'callback_data' => "back_setting"]],
                ]
            ]);
        }

        bot('editMessagetext', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "💎 از منوی پایین میتونید بخش های مختلف ربات رو مدیریت کنید 👇",
            'parse_mode' => "HTML",
            'reply_markup' => $keyboard
        ]);
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "💎 لطفا مقدار جدید $Change_i رو بفرستید:",
            'parse_mode' => "HTML",
            'reply_markup' => $backpanel
        ]);

        $connect->query("UPDATE `user` SET `step` = 'change_settings|$Change_i|$panel_id' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//============// Buy Config //============//
if ($text == "🚀 سرویس جدید") {
    if ($user['coin'] >= $coin_need) {
        $result = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `Configs` ORDER BY RAND() LIMIT 1"));
        $service_link = $result['config'];

        $connect->query("INSERT INTO `Services` (`user`, `config`, `time`, `date`, `create_at`) VALUES ('$from_id', '$service_link', '$time', '$date', '$ti')");
        $service_id = $connect->insert_id;

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "✅ اشتراک جدید با موفقیت ساخته شد؛

‏🚀 اطلاعات اشتراک:

‏🔢| نام اشتراک: Super ViP #$service_id
‏💡| وضعیت اشتراک: 🟢 - فعال
‏📀| حجم اشتراک: ♾️
‏👤| تعداد کاربر: ♾️

🔗 لینک اتصال:

`$service_link`

🆔 @$usernamebot",
            'parse_mode' => "MarkDown",
            'reply_markup' => $back
        ]);

        $connect->query("UPDATE `user` SET `coin` = `coin` - $coin_need WHERE `id` = '$from_id' LIMIT 1");
    } else {
        $user_coin = number_format($user['coin']);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "👈 برای دریافت اشتراک نیاز به $coin_need سکه دارید، سکه فعلی شما $user_coin عدد میباشد. از بخش افزایش رفرال سکه های خود را به صورت رایگان افزایش دهید :)",
        ]);
    }
}
//============// Account //============//
if ($text == "👤 حساب کاربری") {

    $join_date = $user['join_date'];
    $user_coin = number_format($user['coin']);
    $user_ref = number_format($user['ref_count']);

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👤 اطلاعات حساب شما (`$from_id`) به شرح زیر میباشد:",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "$from_id", 'callback_data' => "none"], ['text' => "👤 شناسه کاربری:", 'callback_data' => "none"]],
                [['text' => "$join_date", 'callback_data' => "none"], ['text' => "📅 تاریخ عضویت:", 'callback_data' => "none"]],
                [['text' => "$user_coin", 'callback_data' => "none"], ['text' => "💵 تعداد سکه:", 'callback_data' => "none"]],
                [['text' => "$user_ref", 'callback_data' => "none"], ['text' => "👥 تعداد رفرال:", 'callback_data' => "none"]],
                [['text' => "📅 : $date", 'callback_data' => "none"], ['text' => "🗓 : $ToDay", 'callback_data' => "none"], ['text' => "⏰ : $time", 'callback_data' => "none"]],
            ]
        ])
    ]);
}
//============// My Service //============//
if ($text == "🛍 سرویس های من" or $data == "my_service") {

    $result = mysqli_query($connect, "SELECT `id` FROM `Services` WHERE `user` = '$from_id'");
    $scount = mysqli_num_rows($result);

    if ($scount > 0) {

        $keyboard = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $service_id = $row['id'];

            $keyboard[] = [
                [
                    'text' => "🟢 Super ViP #$service_id",
                    'callback_data' => "get_service|$service_id"
                ]
            ];
        }

        $reply_markup = json_encode([
            'inline_keyboard' => $keyboard
        ]);

        if ($data) {
            bot('editMessagetext', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "🔽 سرویس مورد نظر رو از منوی زیر انتخاب کن:",
                'reply_markup' => $reply_markup
            ]);
        } else {
            bot('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "🔽 سرویس مورد نظر رو از منوی زیر انتخاب کن:",
                'reply_markup' => $reply_markup,
            ]);
        }
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "❌ شما سرویس فعالی ندارید.",
        ]);
    }
}

if (strpos($data, "get_service|") === 0) {
    $get_data = explode("|", $data);
    $service_id = $get_data[1];

    $result = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `Services` WHERE `id` = '$service_id'"));

    if ($result) {
        $service_link = $result['config'];

        bot('editMessagetext', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "‏🚀 اطلاعات اشتراک:

‏🔢| نام اشتراک: Super ViP #$service_id
‏💡| وضعیت اشتراک: 🟢 - فعال
‏📀| حجم اشتراک: ♾️
‏👤| تعداد کاربر: ♾️

🔗 لینک اتصال:

`$service_link`

🆔 @$usernamebot",
            'parse_mode' => "MarkDown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "❌ حذف سرویس", 'callback_data' => "dels|$service_id"], ['text' => "♻️ تغییر لینک", 'callback_data' => "resets|$service_id"]],
                    [['text' => "↩️ صفحه قبل", 'callback_data' => "my_service"]],
                ]
            ])
        ]);
    }
}

if (strpos($data, "resets|") === 0) {
    $get_data = explode("|", $data);
    $service_id = $get_data[1];

    $result = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `Services` WHERE `id` = '$service_id'"));

    if ($can_reset > $result['reset']) {

        $old_config = $result['config'];
        $result2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `Configs` WHERE `config` != '$old_config' ORDER BY RAND() LIMIT 1"));
        $new_config = $result2['config'];

        $connect->query("UPDATE `Services` SET `config` = '$new_config', `reset` = `reset` + 1 WHERE `id` = '$service_id' LIMIT 1");

        bot('editMessagetext', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "✅ لینک اشتراک شما بروزرسانی شد؛

‏🚀 اطلاعات اشتراک:

‏🔢| نام اشتراک: Super ViP #$service_id
‏💡| وضعیت اشتراک: 🟢 - فعال
‏📀| حجم اشتراک: ♾️
‏👤| تعداد کاربر: ♾️

🔗 لینک اتصال:

`$new_config`

🆔 @$usernamebot",
            'parse_mode' => "MarkDown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "❌ حذف سرویس", 'callback_data' => "dels|$service_id"], ['text' => "♻️ تغییر لینک", 'callback_data' => "resets|$service_id"]],
                    [['text' => "↩️ صفحه قبل", 'callback_data' => "my_service"]],
                ]
            ])
        ]);

        bot('answercallbackquery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "✅ لینک اشتراک شما بروزرسانی شد.",
            'show_alert' => true
        ]);
    } else {
        bot('answercallbackquery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "❌ شما به محدودیت تغییر لینک رسیدید.",
            'show_alert' => true
        ]);
    }
}

if (strpos($data, "dels|") === 0) {
    $get_data = explode("|", $data);
    $service_id = $get_data[1];

    bot('deletemessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❌ سرویس Super ViP #$service_id با موفقیت حذف شد.",
    ]);

    $connect->query("DELETE FROM `Services` WHERE `id` = '$service_id'");
}
//============// Add Referral //============//
if ($text == "💎 افزایش رفرال") {
    $id = bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "🤨 نکنه هنوزم میری پول میدی کانفیگ میخری؟

🤦‍♂️ وقتی بهترین کانفیگا رو رایگان میشه استفاده کرد چرا بری پول بدی کانفیگ که دو روز بعد قطع بشه؟

🥸 حالا عیب نداره اشتباهیه که کردی، بیا تو ربات زیر کانفیگ رایگان مخصوص خودتو بگیر 👇

https://t.me/$usernamebot?start=ref_$from_id",
        'disable_web_page_preview' => true,
    ])->result->message_id;

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👆 بنر بالا رو برای دوستو رفیقات بفرست، هر نفر که با لینک تو بیاد $coin_referral سکه دریافت میکنی، وقتی سکه هات به $coin_need برسه میتونی کانفیگ مخصوص خودتو به صورت رایگان دریافت کنی :)",
        'parse_mode' => 'Markdown',
        'reply_to_message_id' => $id,
    ]);
}
//============// Missions //============//
if ($text == "📝 ماموریت‌ها") {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👇 لیست ماموریت ها به شرح زیر است:",
        'reply_markup' => $missions_section
    ]);
}

if (strpos($data, "bonus|") === 0) {
    $get_data = explode("|", $data);
    $missions = $get_data[1];
    $value = (int)$get_data[2];

    if ($missions == "referral") {
        $referrals = [
            10 => 3,
            30 => 5,
            50 => 7,
            100 => 10,
        ];

        if (!isset($referrals[$value])) {
            exit;
        }

        if ($user['ref_count'] >= $value) {
            $get_mis = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `Missions` WHERE `mission` = '$missions|$value' AND `user` = '$from_id' LIMIT 1"));
            if (!$get_mis) {
                $coin_bonus = $referrals[$value];

                bot('answercallbackquery', [
                    'callback_query_id' => $update->callback_query->id,
                    'text' => "✅ شما با موفقیت ماموریت رو تکمیل کردید و $coin_bonus سکه به شما اضافه شد.",
                    'show_alert' => true
                ]);

                $connect->query("UPDATE `user` SET `coin` = `coin` + '$coin_bonus' WHERE `id` = '$from_id' LIMIT 1");
                $connect->query("INSERT INTO `Missions` (`user`, `mission`, `create_at`) VALUES ('$from_id', '$missions|$value', '$ti')");
            } else {
                bot('answercallbackquery', [
                    'callback_query_id' => $update->callback_query->id,
                    'text' => "🤔 قبلا جایزه این ماموریت رو گرفتی که!",
                    'show_alert' => true
                ]);
            }
        } else {
            bot('answercallbackquery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "🤔 برای این ماموریت باید $value رفرال داشته باشی، داری؟",
                'show_alert' => true
            ]);
        }
    }
}
//============// Missions //============//
if ($text == "🎉 سکه روزانه") {

    $time_ago = $timestamp - $user['daily_gift'];

    if ($time_ago >= 24 * 60 * 60) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "🎊 به شما $coin_daily سکه اضافه شد.",
        ]);

        $connect->query("UPDATE `user` SET `coin` = `coin` + '$coin_daily', `daily_gift` = '$timestamp' WHERE `id` = '$from_id' LIMIT 1");
    } else {

        $remaining = (24 * 60 * 60) - $time_ago;

        $hlater = floor($remaining / 3600);
        $mlater = floor(($remaining % 3600) / 60);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "☹️ سکه امروزتو گرفتی که! $hlater ساعت و $mlater دقیقه دیگه برگرد :)",
        ]);
    }
}

//===========================// روشن/خاموش //===========================//
if ($text == "🔌 وضعیت ربات" && $tc == 'private' && is_bot_admin($from_id)) {
    $mode = $settings['bot_mode'] ?? 'on';
    $label = ($mode === 'on') ? '🟢 روشن' : '🔴 خاموش';
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "🔌 وضعیت فعلی ربات: <b>$label</b>\n\n"
            . "با دکمه‌های «روشن کردن ربات» / «خاموش کردن ربات» عوض کن.\n"
            . "وقتی خاموش باشد کاربران عادی پیام نگهداری می‌گیرند؛ ادمین دسترسی دارد.",
        'parse_mode' => 'HTML',
        'reply_markup' => $admin_panel
    ]);
}

if ($text == "🟢 روشن کردن ربات" && $tc == 'private' && is_bot_admin($from_id)) {
    $connect->query("UPDATE `settings` SET `bot_mode` = 'on'");
    $settings['bot_mode'] = 'on';
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ ربات روشن شد. کاربران می‌توانند استفاده کنند.",
        'reply_markup' => $admin_panel
    ]);
}

if ($text == "🔴 خاموش کردن ربات" && $tc == 'private' && is_bot_admin($from_id)) {
    $connect->query("UPDATE `settings` SET `bot_mode` = 'off'");
    $settings['bot_mode'] = 'off';
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "🔴 ربات خاموش شد.\nکاربران عادی تا روشن شدن مجدد پیام نگهداری می‌گیرند.",
        'reply_markup' => $admin_panel
    ]);
}

//===========================// panel admin //===========================//
if (($text == '/panel' || $text == '👤 پنل مدیریت 👤' || $text == 'برگشت 🔙') && $tc == 'private' && is_bot_admin($from_id)) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👋 ادمین عزیز به پنل مدیریت ربات خوش آمدید.",
        'reply_markup' => $admin_panel
    ]);

    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    exit;
}
//===========================// Admin Section //===========================//
if (($text == "👤 بخش کاربران") && is_bot_admin($from_id) && $tc == "private") {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❕️ به بخش مدیریت کاربران خوش آمدید

لطفا از بین گزینه های زیر انتخاب کنید.",
        'reply_markup' => $user_section
    ]);
}

if (($text == "💬 بخش ارسال") && is_bot_admin($from_id) && $tc == "private") {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❕️ به بخش مدیریت پیام های همگانی خوش آمدید

لطفا از بین گزینه های زیر انتخاب کنید.",
        'reply_markup' => $send_section
    ]);
}

if (($text == "🤖 تنظیمات ربات") && is_bot_admin($from_id) && $tc == "private") {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❕️ به بخش مدیریت ربات خوش آمدید

لطفا از بین گزینه های زیر انتخاب کنید.",
        'reply_markup' => $setting_section
    ]);
}
//====================// آمار ربات //====================//
if (($text == '📊 آمار ربات 📊' or $data == 'back_stats') and is_bot_admin($from_id)) {

    $alluser = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `user`")));
    $allblock = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `block`")));

    $all_service = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Services`")));
    $all_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs`")));

    $load = sys_getloadavg();

    $serverIP = gethostbyname(gethostname());
    $starttime = microtime(true);
    $socket = fsockopen($serverIP, 80, $errno, $errstr, 10);
    $stoptime  = microtime(true);
    $status    = 0;
    if (!$socket) {
        $status = -1;
    } else {
        fclose($socket);
        $server_ping = ($stoptime - $starttime) * 1000;
        $server_ping = round($server_ping, 2);
    }
    $mem = number_format(memory_get_usage());
    $ver = phpversion();
    $ip_add = $_SERVER['SERVER_ADDR'];
    $domain = $_SERVER['SERVER_NAME'];

    //====// User Active //====//
    $onlineUsers = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `update_at` > $timestamp - 60")) ?: 0);
    $onlineUsers1 = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `update_at` > $timestamp - 86400")) ?: 0);
    $onlineUsers2 = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `update_at` > $timestamp - 604800")) ?: 0);
    $onlineUsers3 = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `update_at` > $timestamp - 2592000")) ?: 0);

    //====// New User //====//
    $hourlyUsers = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `create_at` > $timestamp - 3600")) ?: 0);
    $dailyUsers = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `create_at` > $timestamp - 86400")) ?: 0);
    $weeklyUsers = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `create_at` > $timestamp - 604800")) ?: 0);
    $monthlyUsers = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT * FROM `user` WHERE `create_at` > $timestamp - 2592000")) ?: 0);

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "📊 آمار ربات شما به شرح زیر است:

━━━━━━━━━━━━━━━━━
👥 <b>تعداد کاربران:</b> <code>$alluser</code>
⛔️ <b>کاربران مسدود:</b> <code>$allblock</code>

🗳 <b>تعداد کل کانفیگ‌ها:</b> <code>$all_config</code>
🛍 <b>تعداد کل سرویس‌ها:</b> <code>$all_service</code>
━━━━━━━━━━━━━━━
💎 <b>فعالیت کاربران ربات شما به شرح زیر میباشد</b> 👇

🟢 <b>کاربران آنلاین:</b> <code>$onlineUsers</code> 
🕛 <b>24 ساعت گذشته:</b> <code>$onlineUsers1</code> 
📅 <b>7 روز گذشته:</b> <code>$onlineUsers2</code> 
🗓 <b>31 روز گذشته:</b> <code>$onlineUsers3</code>

💎 <b>آمار کاربران جدید ربات شما به شرح زیر میباشد</b> 👇

🕛 <b>24 ساعت گذشته:</b> <code>$dailyUsers</code> 
📅 <b>7 روز گذشته:</b> <code>$weeklyUsers</code>
🗓 <b>31 روز گذشته:</b> <code>$monthlyUsers</code>
━━━━━━━━━━━━━━━━━
📶 <b>Server Ping :</b> <code>$server_ping</code>
🎛 <b>LoadAvg :</b> <code>$load[0]</code>

📍 <b>IP Address :</b> <code>$ip_add</code>
🗂 <b>Memory Usage :</b> <code>$mem</code>

📦 <b>PHP Version :</b> <code>$ver</code>
━━━━━━━━━━━━━━━━━
        
🌐 <b>Domain :</b> <code>$domain</code>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📅 : $date", 'callback_data' => "none"], ['text' => "🗓 : $ToDay", 'callback_data' => "none"], ['text' => "⏰ : $time", 'callback_data' => "none"]],
            ]
        ])
    ]);
}
//===================// مدیریت قفل ها //===================//
if ($text == "📣 مدیریت قفل ها" || $text == "🔒 عضویت اجباری") {
    if (is_bot_admin($from_id)) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "❗️ به بخش تنظیم چنل های قفل خوش آمدید.

👈 برای حذف چنل، از بخش لیست چنل چنل مورد نظر را حذف کنید.",
            'parse_mode' => "HTML",
            'reply_markup' => $manage_channel
        ]);
    }
}
//=====// افزودن چنل //=====//
if ($text == '➕ افزودن چنل' and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👤 یک پیام از چنل/گروه مورد نظر به ربات فوروارد کنید:",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'new_ch' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'new_ch' && $tc == 'private') {

    if (!isset($message->forward_from_chat)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ لطفاً یک پیام از کانال یا گروه مورد نظر فوروارد کنید.",
            'reply_markup' => $backpanel
        ]);
        return;
    }

    $forward_chat = $message->forward_from_chat;
    $ch_id = $forward_chat->id;
    $ch_type = $forward_chat->type;
    $ch_name = $forward_chat->title;

    if ($ch_type != "channel" && $ch_type != "supergroup") {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ فقط پیام از کانال یا گروه را ارسال کنید.",
            'reply_markup' => $backpanel
        ]);
        return;
    }

    $admins = KajSafe('getChatAdministrators', ['chat_id' => $ch_id]);
    $is_admin = false;
    $can_invite = false;
    if ($admins->ok) {
        foreach ($admins->result as $admin) {
            if ($admin->user->id == SAFE_ID) {
                $is_admin = true;
                if ($ch_type == "channel" && isset($admin->can_invite_users) && $admin->can_invite_users) {
                    $can_invite = true;
                }
                if ($ch_type == "supergroup" && isset($admin->can_invite_users) && $admin->can_invite_users) {
                    $can_invite = true;
                }
                break;
            }
        }
    }

    if (!$is_admin) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ ربات داخل این کانال/گروه ادمین نیست!",
            'reply_markup' => $backpanel
        ]);
        return;
    }

    if (!$can_invite) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ ربات دسترسی ساخت لینک خصوصی ندارد!",
            'reply_markup' => $backpanel
        ]);
        return;
    }

    $create_link = KajSafe('createChatInviteLink', [
        'chat_id' => $ch_id,
        'name' => "لینک مخصوص @$usernamebot"
    ]);

    if (!$create_link->ok) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ ساخت لینک خصوصی با خطا مواجه شد.",
            'reply_markup' => $backpanel
        ]);
        return;
    }

    $ch_link = $create_link->result->invite_link;

    $connect->query("INSERT INTO `channels` (`idoruser`, `link`) VALUES ('$ch_id', '$ch_link')");

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✅ کانال/گروه *{$ch_name}* با موفقیت قفل شد.\n\n🔗 لینک خصوصی:\n{$ch_link}",
        'parse_mode' => 'Markdown',
        'reply_markup' => $manage_channel
    ]);

    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
}
//=====// لیست چنل ها //=====//
if ($text == "📚 لیست چنل ها") {
    if (is_bot_admin($from_id)) {

        $chs = mysqli_query($connect, "SELECT `idoruser` FROM `channels`");
        $fil = mysqli_num_rows($chs);

        if ($fil != 0) {

            $d4[] = [['text' => "💎 Channel:", 'callback_data' => "none"], ['text' => "🔗 Link:", 'callback_data' => "none"], ['text' => "🗑 Delete:", 'callback_data' => "none"]];

            while ($row = mysqli_fetch_assoc($chs)) {
                $ar[] = $row["idoruser"];
            }

            for ($i = 0; $i <= $fil; $i++) {

                $by = $i + 1;
                $okk = $ar[$i];

                $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `channels` WHERE `idoruser` = '$okk' LIMIT 1"));
                $link = $ch['link'];
                $chlink = $ch['idoruser'];

                $ch_info = KajSafe("getChat", ["chat_id" => "$chlink"]);
                $ch_name = (is_object($ch_info)) ? $ch_info->result->title : $ch_info['result']['title'];

                $link_show = str_replace("https://t.me/", '', $link);

                if (strlen($link_show) > 15) {
                    $link_show = substr($link_show, 0, 15) . "...";
                } else {
                    $link_show = $link_show;
                }

                if ($link != null) {
                    $d4[] = [['text' => $ch_name, 'url' => $link], ['text' => $link_show, 'callback_data' => "change_chlink|$okk"], ['text' => "❌ حذف", 'callback_data' => "delc|$okk"]];
                }
            }

            bot('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "👇🏻 لیست تمام چنل های قفل",
                'parse_mode' => "HTML",
                'reply_markup' => json_encode([
                    'inline_keyboard' => $d4
                ])
            ]);
        } else {
            bot('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "❌ هیچ چنل قفلی تنظیم نشده.",
                'parse_mode' => "HTML",
            ]);
        }
    }
}

if (strpos($data, "delc|") === 0) {
    if (is_bot_admin($from_id)) {

        $ok = str_replace("delc|", '', $data);

        $chs = mysqli_query($connect, "SELECT `idoruser` FROM `channels`");
        $fil = mysqli_num_rows($chs);

        if ($fil == 1) {

            $connect->query("DELETE FROM `channels` WHERE `idoruser` = '$ok'");

            bot('editMessagetext', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "👇🏻 لیست تمام چنل های قفل

❌ تمام چنل ها حذف شده است.",
                'parse_mode' => "HTML",
            ]);

            bot('answercallbackquery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "✅ چنل حذف شد.",
                'show_alert' => false
            ]);
        } else {
            $connect->query("DELETE FROM `channels` WHERE `idoruser` = '$ok'");

            $chs = mysqli_query($connect, "SELECT `idoruser` FROM channels");

            $fil = mysqli_num_rows($chs);

            while ($row = mysqli_fetch_assoc($chs)) {
                $ar[] = $row["idoruser"];
            }

            for ($i = 0; $i <= $fil; $i++) {
                $by = $i + 1;
                $okk = $ar[$i];
                $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM channels WHERE idoruser = '$okk' LIMIT 1"));
                $link = $ch['link'];
                $chlink = $ch['idoruser'];

                $ch_info = KajSafe("getChat", ["chat_id" => "$chlink"]);
                $ch_name = (is_object($ch_info)) ? $ch_info->result->title : $ch_info['result']['title'];

                $link_show = str_replace("https://t.me/", '', $link);

                if (strlen($link_show) > 10) {
                    $link_show = substr($link_show, 0, 10) . "...";
                } else {
                    $link_show = $link_show;
                }

                if ($link != null) {
                    $d4[] = [['text' => $ch_name, 'url' => $link], ['text' => $link_show, 'callback_data' => "change_link|$okk"], ['text' => "❌ حذف", 'callback_data' => "delc_$okk"]];
                }
            }

            bot('editMessagetext', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "👇🏻 لیست تمام چنل های قفل

❌ چنل حذف شد.",
                'parse_mode' => "HTML",
                'reply_markup' => json_encode([
                    'inline_keyboard' => $d4
                ])
            ]);
            bot('answercallbackquery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "✅ چنل حذف شد.",
                'show_alert' => false
            ]);
        }
    }
}

if (strpos($data, "change_chlink|") === 0) {
    if (is_bot_admin($from_id)) {

        $ok = str_replace("change_chlink|", '', $data);

        $link_info = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `channels` WHERE `idoruser` = '$ok' LIMIT 1"));

        $ch_link = $link_info['link'];

        bot('deletemessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "🔐 وضعیت فعلی لینک به شرح زیر است:

🔗 $ch_link

👇 درصورتی که قصد تغییر لینک را دارید، لینک جدید را ارسال کنید:",
            'disable_web_page_preview' => true,
            'reply_markup' => $backpanel
        ]);

        $connect->query("UPDATE `user` SET `step` = 'set_newlink|$ok' WHERE `id` = '$from_id' LIMIT 1");
    }
}

if (strpos($user['step'], "set_newlink|") === 0) {
    if (is_bot_admin($from_id)) {

        $ok = str_replace("set_newlink|", '', $user['step']);

        $link_info = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `channels` WHERE `idoruser` = '$ok' LIMIT 1"));

        $ch_link = $link_info['link'];

        if (strpos($text, "https://t.me/") !== 0) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ لطفا یک لینک معتبر ارسال کنید، نمونه فرمت قابل قبول:\n\nhttps://t.me/+abcd1234"
            ]);
            exit;
        }

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "✅ لینک جدید با موفقیت تنظیم شد:

🔗 $text",
            'disable_web_page_preview' => true,
            'reply_markup' => $manage_channel
        ]);

        $connect->query("UPDATE `channels` SET `link` = '$text' WHERE `idoruser` = '$ok' LIMIT 1");

        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//===================// مدیریت ادمین ها //===================//
if ($text == "👤 مدیریت ادمین ها" and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "❗️ به بخش تنظیم ادمین خوش آمدید.

👈 برای حذف ادمین، از بخش لیست ادمین . ادمین مورد نظر را حذف کنید.",
        'parse_mode' => "HTML",
        'reply_markup' => $manage_admin
    ]);
}
//=====// افزودن ادمین //=====//
if ($text == "➕ افزودن ادمین"  and $tc == 'private' and ($admin['admin'] == $creator)) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "لطفا آیدی عددی فرد موردنظر را وارد نمایید ✅",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "👤 پنل مدیریت 👤"]],
            ],
            'resize_keyboard' => true,
            'input_field_placeholder' => "$from_id"

        ])
    ]);
    $connect->query("UPDATE user SET step = 'add_admin' WHERE id = '$from_id' LIMIT 1");
}

if ($user['step'] == "add_admin" && $text != "👤 پنل مدیریت 👤"  and $tc == 'private' and (is_bot_admin($from_id))) {
    $ad = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `admin` WHERE `admin` = '$text' LIMIT 1"));
    if ($ad['admin'] == null) {
        $user_ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$text' LIMIT 1"));
        if ($user_ch['id'] != null) {
            $connect->query("INSERT INTO `admin` (`admin`) VALUES ('$text')");
            bot('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "✅ کاربر $text با موفقیت افزوده شد.",
                'parse_mode' => "HTML",
                'reply_markup' => $manage_admin
            ]);
            $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
        } else {
            bot('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "ایدی عددی کاربر <code>$text</code> در لیست کاربرها وجود ندارد",
                'parse_mode' => "HTML",
            ]);
        }
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "ایدی عددی کاربر <code>$text</code> در لیست ادمین ها وجود دارد",
            'parse_mode' => "HTML",
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//=====// لیست ادمین ها //=====//
if ($text == '📚 لیست ادمین ها' and $tc == 'private' and (is_bot_admin($from_id))) {

    $chs = mysqli_query($connect, "SELECT `admin` FROM `admin`");
    $fil = mysqli_num_rows($chs);

    if ($fil != 0) {

        while ($row = mysqli_fetch_assoc($chs)) {
            $ar[] = $row["admin"];
        }

        for ($i = 0; $i <= $fil; $i++) {
            $by = $i + 1;
            $okk = $ar[$i];
            $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `admin` WHERE `admin` = '$okk' LIMIT 1"));
            $link = $ch['admin'];
            if ($link != null) {
                $d4[] = [['text' => "$link", 'callback_data' => 'ok'], ['text' => "❌ حذف", 'callback_data' => "delad|$okk"]];
            }
        }

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "👇🏻 لیست تمام ادمین ها",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'inline_keyboard' => $d4
            ])
        ]);
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "❌ هیچ ادمینی  تنظیم نشده.",
            'parse_mode' => "HTML",
        ]);
    }
}

if (strpos($data, "delad|") === 0 and $from_id == $creator) {
    $ok = str_replace("delad|", '', $data);
    $chs = mysqli_query($connect, "SELECT `admin` FROM `admin`");
    $fil = mysqli_num_rows($chs);
    if ($fil == 1) {

        $connect->query("DELETE FROM `admin` WHERE `admin` = '$ok'");

        bot('editMessagetext', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "👇🏻 لیست تمام ادمین های 

❌ تمام ادمین ها حذف شده است.",
            'parse_mode' => "HTML",
        ]);

        bot('answercallbackquery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "✅ ادمین حذف شد .",
            'show_alert' => false
        ]);
    } else {
        $connect->query("DELETE FROM `admin` WHERE `admin` = '$ok'");

        $chs = mysqli_query($connect, "SELECT `admin` FROM `admin`");
        $fil = mysqli_num_rows($chs);

        while ($row = mysqli_fetch_assoc($chs)) {
            $ar[] = $row["admin"];
        }

        for ($i = 0; $i <= $fil; $i++) {
            $by = $i + 1;
            $okk = $ar[$i];
            $ch = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `admin` WHERE `admin` = '$okk' LIMIT 1"));
            $link = $ch['admin'];

            if ($link != null) {
                $d4[] = [['text' => "$link", 'callback_data' => 'ior'], ['text' => "❌ حذف", 'callback_data' => "delad|" . $okk . ""]];
            }
        }

        bot('editMessagetext', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "👇🏻 لیست تمام ادمین ها\n\n❌ ادمین حذف شد.",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'inline_keyboard' => $d4,
            ])
        ]);

        bot('answercallbackquery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "✅ ادمین حذف شد.",
            'show_alert' => false
        ]);
    }
}
//===================// تنظیمات ربات //===================//
if (($text == "💎 تنظیمات ربات") && is_bot_admin($from_id) && $tc == "private") {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "💎 از منوی پایین میتونید بخش های مختلف ربات رو مدیریت کنید 👇",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "💎 تنظیمات عمومی", 'callback_data' => "general_setting"]],
            ]
        ])
    ]);
}

if (strpos($user['step'], "change_settings|") === 0) {
    $get_data = explode("|", $user['step']);
    $Change_i = $get_data[1];
    $panel_id = $get_data[2];

    $connect->query("UPDATE `settings` SET `$Change_i` = '$text'");

    $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `settings` LIMIT 1"));

    $Bot_mode = get_setting_status('bot_mode');

    if ($panel_id == "1") {
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => $settings['botname'], 'callback_data' => "change_settings|botname|1"], ['text' => "💎 نام ربات ›", 'callback_data' => "none"]],
                [['text' => $Bot_mode, 'callback_data' => "change_settings|bot_mode|1"], ['text' => "📊 وضعیت ربات ›", 'callback_data' => "none"]],
                [['text' => $settings['can_reset'], 'callback_data' => "change_settings|can_reset|1"], ['text' => "♻️ حداکثر تغییر لینک ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_need'], 'callback_data' => "change_settings|coin_need|1"], ['text' => "💳 هزینه کانفیگ ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_daily'], 'callback_data' => "change_settings|coin_daily|1"], ['text' => "🎉 جایزه روزانه ›", 'callback_data' => "none"]],
                [['text' => $settings['coin_referral'], 'callback_data' => "change_settings|coin_referral|1"], ['text' => "👥 جایزه رفرال ›", 'callback_data' => "none"]],
                [['text' => "↩️ منوی قبل", 'callback_data' => "back_setting"]],
            ]
        ]);
    }

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "💎 از منوی پایین میتونید بخش های مختلف ربات رو مدیریت کنید 👇",
        'parse_mode' => "HTML",
        'reply_markup' => $keyboard
    ]);
    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
}
//===================// بخش همگانی //===================//
if ($text == '💬 پیام همگانی' and $tc == 'private' and is_bot_admin($from_id)) {
    if ($send['admin'] == null) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "👨🏻‍💻 لطفا متن یا رسانه خود را ارسال کنید [میتواند شامل عکس باشد]  همچنین میتوانید رسانه را همراه با کپشن [متن چسبیده به رسانه ارسال کنید]",
            'reply_markup' => $backpanel
        ]);
        $connect->query("UPDATE `user` SET `step` = 'sendtoall' WHERE `id` = '$from_id' LIMIT 1");
    } else {
        $tddd = $send['sended'];
        $users = mysqli_query($connect, "SELECT `id` FROM `user`");
        $fil = mysqli_num_rows($users);
        $tfrigh = $fil - $tddd;
        $min = Takhmin($tfrigh);
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "❌ خطا برای انجام عملیات همگانی

ادمین زیر اقدام به همگانی کرده و هنوز همگانی به اتمام نرسیده ، لطفا تا پایان همگانی قبلی صبر کنید .",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "👤 {$send['sended']}", 'callback_data' => "none"]],
                    [['text' => "🔹 تعداد افراد ارسال شده : $tddd", 'callback_data' => "none"]],
                    [['text' => "🔸 زمان تخمینی ارسال : $min دقیقه (باقیمانده)", 'callback_data' => "none"]],
                ]
            ])
        ]);
    }
}

if ($user['step'] == 'sendtoall') {
    $photo = $message->photo[count($message->photo) - 1]->file_id;
    $caption = $update->message->caption;
    $users = mysqli_query($connect, "SELECT `id` FROM `user`");
    $fil = mysqli_num_rows($users);
    $min = Takhmin($fil);
    $tddd = $send['sended'];
    $id = bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "📣 <i>پیام به صف ارسال قرار گرفت !</i>

✅ <b>بعد از اتمام ارسال، به شما اطلاع داده میشود.</b>

👥 تعداد اعضای ربات: <code>$fil</code> نفر

🔹 تعداد افراد ارسال شده در دکمه شیشه ای زیر، قابل مشاهده است ( خودکار ادیت میشود )",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔹 تعداد افراد ارسال شده : $tddd", 'callback_data' => "none"]],
                [['text' => "🚀 زمان تخمینی ارسال : $min دقیقه (باقیمانده)", 'callback_data' => "none"]],
            ]
        ])
    ])->result;
    $msgid22 = $id->message_id;
    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    $connect->query("UPDATE `sendall` SET `step` = 'send' , `admin` = '$from_id' , `messageid` = '$msgid22' , `text` = '$text$caption' , `chat` = '$photo' LIMIT 1");
}

if ($text == '↗️ فوروارد همگانی' and $tc == 'private' and is_bot_admin($from_id)) {
    if ($send['admin'] == null) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "👨🏻‍💻 لطفا پیام خود را فوروارد کنید [پیام فوروارد شده میتوانید از شخص یا کانال باشد]",
            'reply_markup' => $backpanel
        ]);
        $connect->query("UPDATE `user` SET `step` = 'fortoall' WHERE `id` = '$from_id' LIMIT 1");
    } else {
        $tddd = $send['sended'];
        $users = mysqli_query($connect, "SELECT `id` FROM `user`");
        $fil = mysqli_num_rows($users);
        $tfrigh = $fil - $tddd;
        $min = Takhmin($tfrigh);
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "❌ خطا برای انجام عملیات همگانی

ادمین زیر اقدام به همگانی کرده و هنوز همگانی به اتمام نرسیده ، لطفا تا پایان همگانی قبلی صبر کنید .",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "👤 {$send['sended']}", 'callback_data' => "none"]],
                    [['text' => "🔹 تعداد افراد ارسال شده : $tddd", 'callback_data' => "none"]],
                    [['text' => "🔸 زمان تخمینی ارسال : $min دقیقه (باقیمانده)", 'callback_data' => "none"]],
                ]
            ])
        ]);
    }
}

if ($user['step'] == 'fortoall') {
    $users = mysqli_query($connect, "SELECT `id` FROM `user`");
    $fil = mysqli_num_rows($users);
    $min = Takhmin($fil);
    $tddd = $send['sended'];
    $id = bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "📣 <i>پیام به صف فوروارد قرار گرفت !</i>

✅ <b>بعد از اتمام فوروارد، به شما اطلاع داده میشود.</b>
        
👥 تعداد اعضای ربات: <code>$fil</code> نفر

🔹 تعداد افراد ارسال شده در دکمه شیشه ای زیر، قابل مشاهده است ( خودکار ادیت میشود )",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔹 تعداد افراد ارسال شده : $tddd", 'callback_data' => "none"]],
                [['text' => "🚀 زمان تخمینی ارسال : $min دقیقه (باقیمانده)", 'callback_data' => "none"]],
            ]
        ])
    ])->result;
    $msgid22 = $id->message_id;
    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    $connect->query("UPDATE `sendall` SET `step` = 'forward' , `admin` = '$from_id' , `messageid` = '$msgid22' , `text` = '$message_id' , `chat` = '$chat_id' LIMIT 1");
}
//===================// افزودن کانفیگ //===================//
if ($text == '➕ افزودن کانفیگ' and $tc == 'private' and is_bot_admin($from_id)) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "🗳 کانفیگ رو برام بفرست تا اضافش کنم 👇",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'add_config' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'add_config' && $tc == 'private') {

    $text = preg_replace('/\\\\[nr]/', " ", $text);
    preg_match_all('/(?:vmess|vless|trojan|ss|socks|http|https):\/\/[^\s<>"\'`]+/i', $text, $matches);

    if (!empty($matches[0])) {

        $added_configs = [];

        foreach ($matches[0] as $raw_config) {

            $config = normalizeConfig($raw_config);
            if (!$config) continue;

            $parsed = parseConfig($config);
            if (!$parsed['valid']) continue;

            $safe_config = SafeConfig($config);

            $stmt = $connect->prepare("INSERT IGNORE INTO `Configs` (`config`, `type`, `create_at`) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $safe_config, $parsed['type'], $ti);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $added_configs[] = $safe_config;
            }

            $stmt->close();
        }

        if (!empty($added_configs)) {

            $configs_text = implode("\n\n", array_map(function ($cfg) {
                return htmlspecialchars($cfg);
            }, $added_configs));

            $msg = "✅ کانفیگ اضافه شد:\n\n<blockquote><code>$configs_text</code></blockquote>";
        } else {
            $msg = "❌ هیچ کانفیگ معتبری پیدا نشد";
        }
    } else {
        $msg = "❌ هیچ کانفیگی شناسایی نشد!";
    }

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => $msg,
        'parse_mode' => 'HTML',
    ]);
}
//===================// حذف کانفیگ //===================//
if ($text == '➖ حذف کانفیگ' and $tc == 'private' and is_bot_admin($from_id)) {
    $all_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs`")));
    $daily_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs` WHERE `create_at` > $timestamp - 86400")));
    $weekly_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs` WHERE `create_at` > $timestamp - 604800")));

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👇 از منوی زیر نوع حذف رو انتخاب کن:
        
💎 همه کانفیگ‌ها: $all_config
⏰ 24 ساعت اخیر: $daily_config
📆 7 روز اخیر: $weekly_config",
        'reply_markup' => $config_section
    ]);
}

if ($text == '❌ حذف همه' and $tc == 'private' and is_bot_admin($from_id)) {
    $all_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs`")));

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ تعداد $all_config کانفیگ با موفقیت از ربات حذف شد.",
        'reply_markup' => $user_section
    ]);

    $connect->query("DELETE FROM `Configs`");
}

if ($text == '⏰ 24 ساعت اخیر' and $tc == 'private' and is_bot_admin($from_id)) {
    $all_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs` WHERE `create_at` > $timestamp - 86400")));

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ تعداد $all_config کانفیگ که در 24 ساعت اخیر اضافه شده بود با موفقیت از ربات حذف شد.",
        'reply_markup' => $user_section
    ]);

    $connect->query("DELETE FROM `Configs` WHERE `create_at` > $timestamp - 86400");
}

if ($text == '📆 7 روز اخیر' and $tc == 'private' and is_bot_admin($from_id)) {
    $all_config = number_format(mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `Configs` WHERE `create_at` > $timestamp - 604800")));

    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ تعداد $all_config کانفیگ که در 7 روز اخیر اضافه شده بود با موفقیت از ربات حذف شد.",
        'reply_markup' => $user_section
    ]);

    $connect->query("DELETE FROM `Configs` WHERE `create_at` > $timestamp - 604800");
}
//===================// بلاک آنبلاک //===================//
if ($text == '⚠️ مسدود کردن' and $tc == 'private' and is_bot_admin($from_id)) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👨🏻‍💻 لطفا شناسه کاربری فرد را ارسال کنید",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'block' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'block' && $tc == 'private') {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ فرد با موفقیت مسدود شد",
    ]);
    $connect->query("INSERT INTO `block` (`id`) VALUES ('$text')");
    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
}

if ($text == '❌ حذف مسدودیت' and $tc == 'private' and is_bot_admin($from_id)) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👨🏻‍💻 لطفا شناسه کاربری فرد را ارسال کنید",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'unblock' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'unblock' && $tc == 'private') {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ فرد با موفقیت لغو مسدود شد",
    ]);

    $connect->query("DELETE FROM `block` WHERE `id` = '$text'");
    $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
}
//===================// ارسال پیام به کاربر //===================//
if ($text == '💬 ارسال پیام به کاربر' and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "👤 لطفا ایدی عددی کاربر مورد نظر را ارسال کنید:",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'send_pm_user' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'send_pm_user' && $tc == 'private') {
    $checkuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$text' LIMIT 1"));
    if ($checkuser['id'] == true) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "💬 لطفا پیام مورد نظر را جهت ارسال به کاربر بفرستید.",
        ]);
        $connect->query("UPDATE `user` SET `data` = '$text' WHERE `id` = '$from_id' LIMIT 1");
        $connect->query("UPDATE `user` SET `step` = 'sendpmtouser2' WHERE `id` = '$from_id' LIMIT 1");
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ متاسفانه موفق به پیدا کردن اطلاعات کاربر فوق نشدم! لطفا در وارد کردن آیدی عددی دقت کنید!",
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}

if ($user['step'] == 'sendpmtouser2' && $tc == 'private') {
    bot('sendmessage', [
        'chat_id' => $user['data'],
        'text' => "👤 کاربر عزیز، پیام زیر توسط مدیریت ربات به شما ارسال شده است:

`$text`",
        'parse_mode' => 'MarkDown',
    ]);
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "✅ پیام مورد نظر با موفقیت به کاربر ارسال شد.",
    ]);
    $connect->query("UPDATE `user` SET `step` = 'none' , `data` = 'none' WHERE `id` = '$from_id' LIMIT 1");
}
//===================// اطلاعات کاربر //===================//
if ($text == '📊 اطلاعات کاربر' and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "📊 لطفا شناسه کاربری فرد را ارسال کنید",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'user_info' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'user_info' && $tc == 'private') {

    $checkuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$text' LIMIT 1"));

    if ($checkuser['id'] == true) {

        $user = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$text' LIMIT 1"));

        $user_amount = number_format($user['coin']);

        $allbuys = mysqli_num_rows(mysqli_query($connect, "SELECT `user` FROM `Payments` WHERE `user` = '$from_id'"));

        $result = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(`amount`) AS total_amount FROM `Payments` WHERE `user` = '$from_id'"));
        $totalAmount = $result['total_amount'] ? intval($result['total_amount']) : 0;

        if ($user['phone'] == null) {
            $user_phone = "ثبت نشده.";
        } else {
            $user_phone = $user['phone'];
        }

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "👤 اطلاعات حساب کاربر مورد نظر (`$from_id`) به شرح زیر میباشد:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "$text", 'callback_data' => "none"], ['text' => "👤 آیدی عددی:", 'callback_data' => "none"]],
                    [['text' => "$user_amount", 'callback_data' => "none"], ['text' => "💵 موجودی حساب:", 'callback_data' => "none"]],
                    [['text' => "$user_phone", 'callback_data' => "none"], ['text' => "📞 شماره فرد:", 'callback_data' => "none"]],
                    [['text' => "$allbuys", 'callback_data' => "none"], ['text' => "💰 تعداد پرداختی‌ها:", 'callback_data' => "none"]],
                    [['text' => "$totalAmount", 'callback_data' => "none"], ['text' => "💳 جمع مبلغ پرداختی‌ها:", 'callback_data' => "none"]],
                    [['text' => "📅 : $date", 'callback_data' => "none"], ['text' => "🗓 : $ToDay", 'callback_data' => "none"], ['text' => "⏰ : $time", 'callback_data' => "none"]],
                ]
            ])
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ متاسفانه موفق به پیدا کردن اطلاعات کاربر فوق نشدم! لطفا در وارد کردن آیدی عددی دقت کنید!",
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//===================// افزایش سکه کاربر //===================//
if ($text == '➕ افزایش سکه' and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "💵 لطفا در خط اول ایدی فرد و در خط دوم میزان سکه را وارد کنید

267785153
5",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'addamount' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'addamount' && $tc == 'private') {
    $all = explode("\\n", $text);
    $checkuser22 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$all[0]' LIMIT 1"));
    if ($checkuser22['id'] == true) {
        $user = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$all[0]' LIMIT 1"));
        $amount = $user['coin'] + $all[1];
        $connect->query("UPDATE `user` SET `coin` = '$amount' WHERE `id` = '$all[0]' LIMIT 1");

        bot('sendmessage', [
            'chat_id' => $all[0],
            'text' => "`✅ مقدار $all[1] سکه در تاریخ $date ساعت $time با موفقیت از #مدیریت دریافت شد.`",
            'parse_mode' => 'Markdown',
        ]);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "✅ انتقال موجودی با موفقیت انجام شد

amount = $all[1]
user_id = $all[0]
user amount = $amount",
        ]);
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ متاسفانه موفق به پیدا کردن اطلاعات کاربر فوق نشدم! لطفا در وارد کردن آیدی عددی دقت کنید!",
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//===================// کاهش سکه کاربر //===================//
if ($text == '➖ کاهش سکه' and $tc == 'private' and (is_bot_admin($from_id))) {
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'text' => "💵 لطفا در خط اول ایدی فرد و در خط دوم میزان سکه را وارد کنید

267785153
5",
        'reply_markup' => $backpanel
    ]);
    $connect->query("UPDATE `user` SET `step` = 'removeamount' WHERE `id` = '$from_id' LIMIT 1");
}

if ($user['step'] == 'removeamount' && $tc == 'private') {
    $all = explode("\\n", $text);
    $checkuser22 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$all[0]' LIMIT 1"));
    if ($checkuser22['id'] == true) {
        $user = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `user` WHERE `id` = '$all[0]' LIMIT 1"));
        $amount = $user['coin'] - $all[1];
        $connect->query("UPDATE `user` SET `coin` = '$amount' WHERE `id` = '$all[0]' LIMIT 1");

        bot('sendmessage', [
            'chat_id' => $all[0],
            'text' => "`⚠️ مقدار $all[1] سکه در تاریخ $date ساعت $time توسط #مدیریت از حساب شما کسر شد!`",
            'parse_mode' => 'Markdown',
        ]);

        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "✅ انتقال موجودی با موفقیت انجام شد

amount = $all[1]
user_id = $all[0]
user amount = $amount",
        ]);
    } else {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ متاسفانه موفق به پیدا کردن اطلاعات کاربر فوق نشدم! لطفا در وارد کردن آیدی عددی دقت کنید!",
        ]);
        $connect->query("UPDATE `user` SET `step` = 'none' WHERE `id` = '$from_id' LIMIT 1");
    }
}
//=====// End //=====//
$connect->close();
