<?php
// itVPN v2.0 - Railway-safe configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set(getenv('TZ') ?: 'Asia/Tehran');

function envv(string $name, $default = null) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function apply_mysql_url(string $url): void {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return;
    }
    if (!getenv('MYSQLHOST') && !empty($parts['host'])) {
        putenv('MYSQLHOST=' . $parts['host']);
    }
    if (!getenv('MYSQLPORT') && !empty($parts['port'])) {
        putenv('MYSQLPORT=' . (string) $parts['port']);
    }
    if (!getenv('MYSQLUSER') && isset($parts['user'])) {
        putenv('MYSQLUSER=' . $parts['user']);
    }
    if (!getenv('MYSQLPASSWORD') && isset($parts['pass'])) {
        putenv('MYSQLPASSWORD=' . rawurldecode($parts['pass']));
    }
    if (!getenv('MYSQLDATABASE') && !empty($parts['path'])) {
        putenv('MYSQLDATABASE=' . ltrim($parts['path'], '/'));
    }
}

foreach (['MYSQL_URL', 'DATABASE_URL', 'MYSQL_PRIVATE_URL'] as $urlKey) {
    $u = getenv($urlKey);
    if ($u) {
        apply_mysql_url($u);
        break;
    }
}

define('API_KEY', (string) envv('BOT_TOKEN', ''));
if (API_KEY === '' && PHP_SAPI !== 'cli') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('BOT_TOKEN is not configured');
}
define('API_URL', API_KEY !== '' ? ('https://api.telegram.org/bot' . API_KEY . '/') : '');

$admin = (string) envv('ADMIN_ID', '');
$channel = (string) envv('CHANNEL', '');
$web = (string) envv('WEB_URL', '');
$sendall_min = (int) envv('SENDALL_MIN', '300');

$dbhost = (string) envv('MYSQLHOST', envv('DB_HOST', '127.0.0.1'));
$dbport = (int) envv('MYSQLPORT', envv('DB_PORT', '3306'));
$dbname = (string) envv('MYSQLDATABASE', envv('DB_NAME', ''));
$dbuser = (string) envv('MYSQLUSER', envv('DB_USER', ''));
$dbpass = (string) envv('MYSQLPASSWORD', envv('DB_PASSWORD', ''));

if (($dbname === '' || $dbuser === '') && PHP_SAPI !== 'cli') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Database is not configured. Attach a MySQL service on Railway.');
}

$connect = null;
$lastDbError = '';
for ($i = 0; $i < 8; $i++) {
    if ($dbname === '' || $dbuser === '') {
        break;
    }
    $connect = @new mysqli($dbhost, $dbuser, $dbpass, $dbname, $dbport);
    if ($connect && !$connect->connect_errno) {
        break;
    }
    $lastDbError = $connect ? $connect->connect_error : 'unknown';
    error_log('Database connection attempt ' . ($i + 1) . ' failed: ' . $lastDbError);
    $connect = null;
    usleep(500000);
}

if (!$connect) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Database connection failed');
    }
    fwrite(STDERR, "Database connection failed: $lastDbError\n");
} else {
    $connect->set_charset('utf8mb4');
}

$get_user = null;
$usernamebot = '';
if (API_KEY !== '' && function_exists('curl_init')) {
    $ch = curl_init(API_URL . 'getMe');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        $get_user = json_decode($response);
        $usernamebot = $get_user->result->username ?? '';
    }
}
