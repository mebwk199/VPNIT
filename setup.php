<?php
require __DIR__ . '/config.php';

if (!$connect) {
    fwrite(STDERR, "setup.php: no database connection. Check MySQL variables.\n");
    exit(1);
}

function q($db, $sql) {
    if (!$db->query($sql)) {
        throw new RuntimeException($db->error . ' | SQL: ' . substr($sql, 0, 120));
    }
}

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `user` (
  `id` BIGINT(32) PRIMARY KEY,
  `coin` FLOAT DEFAULT '0',
  `ref_count` INT DEFAULT '0',
  `ref_id` BIGINT(32) DEFAULT NULL,
  `join_date` TEXT DEFAULT NULL,
  `spam` varchar(20) DEFAULT NULL,
  `step` VARCHAR(50) DEFAULT NULL,
  `data` TEXT DEFAULT NULL,
  `daily_gift` BIGINT DEFAULT NULL,
  `create_at` BIGINT DEFAULT (UNIX_TIMESTAMP()),
  `update_at` BIGINT DEFAULT (UNIX_TIMESTAMP())
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Services` (
  `id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `user` BIGINT(32) NOT NULL,
  `config` TEXT NOT NULL,
  `reset` TINYINT(1) DEFAULT '0',
  `time` VARCHAR(155) NOT NULL,
  `date` VARCHAR(155) NOT NULL,
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Configs` (
  `id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `config` TEXT NOT NULL,
  `type` ENUM('vless','vmess','trojan','ss') DEFAULT 'vless',
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Missions` (
  `id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `user` BIGINT(32) NOT NULL,
  `mission` TEXT NOT NULL,
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `channels` (
  `idoruser` varchar(30) PRIMARY KEY,
  `link` varchar(200) NOT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin` (
  `admin` BIGINT(32) PRIMARY KEY
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `block` (
  `id` BIGINT(32) NOT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sendall` (
  `step` VARCHAR(20) DEFAULT NULL,
  `admin` BIGINT(32) DEFAULT NULL,
  `messageid` BIGINT(32) DEFAULT NULL,
  `text` TEXT DEFAULT NULL,
  `chat` VARCHAR(100) DEFAULT NULL,
  `sended` BIGINT(32) DEFAULT '0'
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `botname` TEXT DEFAULT 'اینترنت آزاد',
  `can_reset` INT DEFAULT '3',
  `coin_need` INT DEFAULT '3',
  `coin_daily` INT DEFAULT '1',
  `coin_referral` INT DEFAULT '1',
  `bot_mode` TEXT DEFAULT 'on'
) DEFAULT CHARSET=utf8mb4;
SQL;

try {
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))) as $statement) {
        if ($statement === '') continue;
        q($connect, $statement);
    }
    if ($admin !== '') {
        $stmt = $connect->prepare("INSERT IGNORE INTO `admin` (`admin`) VALUES (?)");
        $aid = (string) $admin;
        $stmt->bind_param('s', $aid);
        $stmt->execute();
        $stmt->close();
    }
    $r = $connect->query("SELECT COUNT(*) AS c FROM `sendall`");
    if ($r && ((int)$r->fetch_assoc()['c'] === 0)) $connect->query("INSERT INTO `sendall` () VALUES ()");
    $r = $connect->query("SELECT COUNT(*) AS c FROM `settings`");
    if ($r && ((int)$r->fetch_assoc()['c'] === 0)) $connect->query("INSERT INTO `settings` () VALUES ()");
    @$connect->query("ALTER TABLE `user` ADD `daily_gift` BIGINT DEFAULT NULL");
    @$connect->query("ALTER TABLE `settings` ADD `coin_daily` INT DEFAULT '1'");
    @$connect->query("ALTER TABLE `settings` ADD `coin_referral` INT DEFAULT '1'");
    @$connect->query("ALTER TABLE `settings` ADD `bot_mode` TEXT DEFAULT 'on'");
    echo "Database ready\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Database setup failed: ".$e->getMessage()."\n");
    exit(1);
}

if (API_KEY === '') {
    echo "BOT_TOKEN missing; webhook skipped\n";
    exit(0);
}

$webhook = envv('WEBHOOK_URL');
if (!$webhook) {
    $domain = envv('RAILWAY_PUBLIC_DOMAIN');
    if ($domain) $webhook = 'https://' . rtrim($domain, '/') . '/bot.php';
}
if (!$webhook) {
    echo "WEBHOOK_URL/RAILWAY_PUBLIC_DOMAIN not available; webhook skipped.\n";
    exit(0);
}

$ch = curl_init(API_URL . 'setWebhook');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['url' => $webhook, 'drop_pending_updates' => 'true'],
    CURLOPT_TIMEOUT => 20,
]);
$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if ($err) {
    fwrite(STDERR, "Webhook warning: $err\n");
    exit(0);
}
$decoded = json_decode($result, true);
if (!($decoded['ok'] ?? false)) {
    fwrite(STDERR, "Webhook warning: $result\n");
    exit(0);
}
echo "Webhook set: $webhook\n";
exit(0);
