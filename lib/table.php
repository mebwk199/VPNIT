<?php
include '../config.php';

$sql = "

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
) default charset = utf8mb4;

CREATE TABLE IF NOT EXISTS `Services` (
	`id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `user` BIGINT(32) NOT NULL,
  `config` TEXT NOT NULL,
  `reset` TINYINT(1) DEFAULT '0',
  `time` VARCHAR(155) NOT NULL,
	`date` VARCHAR(155) NOT NULL,
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) default charset = utf8mb4;

CREATE TABLE IF NOT EXISTS `Configs` (
	`id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `config` TEXT NOT NULL,
  `type` ENUM('vless','vmess','trojan','ss') DEFAULT 'vless',
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) default charset = utf8mb4;
ALTER TABLE `Configs` ADD UNIQUE (`config`);

CREATE TABLE IF NOT EXISTS `Missions` (
	`id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
  `user` BIGINT(32) NOT NULL,
  `mission` TEXT NOT NULL,
  `create_at` BIGINT DEFAULT UNIX_TIMESTAMP()
) default charset = utf8mb4;

CREATE TABLE IF NOT EXISTS `channels` (
  `idoruser` varchar(30) PRIMARY KEY,
  `link` varchar(200) NOT NULL
) default charset = utf8mb4;

CREATE TABLE IF NOT EXISTS `admin` (
  `admin` BIGINT(32) PRIMARY KEY
) default charset = utf8mb4;

INSERT IGNORE INTO `admin` (`admin`) VALUES ('$admin');

CREATE TABLE IF NOT EXISTS `block` (
  `id` BIGINT(32) NOT NULL
) default charset = utf8mb4;

CREATE TABLE IF NOT EXISTS `sendall` (
  `step` VARCHAR(20) DEFAULT NULL,
  `admin` BIGINT(32) DEFAULT NULL,
  `messageid` BIGINT(32) DEFAULT NULL,
  `text` TEXT DEFAULT NULL,
  `chat` VARCHAR(100) DEFAULT NULL,
  `sended` BIGINT(32) DEFAULT '0'
) default charset = utf8mb4;

INSERT IGNORE INTO `sendall` () VALUES ();

CREATE TABLE IF NOT EXISTS `settings` (
  `botname` TEXT DEFAULT 'اینترنت آزاد',
  `can_reset` INT DEFAULT '3',
  `coin_need` INT DEFAULT '3',
  `coin_daily` INT DEFAULT '1',
  `coin_referral` INT DEFAULT '1',
  `bot_mode` TEXT DEFAULT 'on'
  ) default charset = utf8mb4;

INSERT IGNORE INTO `settings` () VALUES ();

";

if ($connect->multi_query($sql)) {
  do {
    $connect->store_result();
  } while ($connect->more_results() && $connect->next_result());

  echo "Database installed successfully ✅";
} else {
  echo "Error: " . $connect->error;
}
