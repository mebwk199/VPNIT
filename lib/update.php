<?php

include '../config.php';

/*
|--------------------------------------------------------------------------
| Helper function: check column exists
|--------------------------------------------------------------------------
*/
function columnExists($connect, $table, $column)
{
    $result = mysqli_query($connect, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

/*
|--------------------------------------------------------------------------
| Helper function: check table exists
|--------------------------------------------------------------------------
*/
function tableExists($connect, $table)
{
    $result = mysqli_query($connect, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

/*
|--------------------------------------------------------------------------
| USER TABLE (add daily_gift)
|--------------------------------------------------------------------------
*/
if (tableExists($connect, 'user')) {
    if (!columnExists($connect, 'user', 'daily_gift')) {
        mysqli_query($connect, "ALTER TABLE `user` ADD `daily_gift` BIGINT DEFAULT NULL");
        echo "✔ user.daily_gift added<br>";
    }
}

/*
|--------------------------------------------------------------------------
| CONFIGS TABLE (add unique index if not exists)
|--------------------------------------------------------------------------
*/
if (tableExists($connect, 'Configs')) {
    mysqli_query($connect, "ALTER TABLE `Configs` ADD UNIQUE (`config`)");
    echo "✔ Configs unique index checked<br>";
}

/*
|--------------------------------------------------------------------------
| MISSIONS TABLE (new table)
|--------------------------------------------------------------------------
*/
if (!tableExists($connect, 'Missions')) {
    mysqli_query($connect, "CREATE TABLE `Missions` (
        `id` BIGINT(32) AUTO_INCREMENT PRIMARY KEY,
        `user` BIGINT(32) NOT NULL,
        `mission` TEXT NOT NULL,
        `create_at` BIGINT DEFAULT 0
    ) default charset = utf8mb4;");

    echo "✔ Missions table created<br>";
}

/*
|--------------------------------------------------------------------------
| SETTINGS TABLE (add new columns)
|--------------------------------------------------------------------------
*/
if (tableExists($connect, 'settings')) {

    if (!columnExists($connect, 'settings', 'coin_daily')) {
        mysqli_query($connect, "ALTER TABLE `settings` ADD `coin_daily` INT DEFAULT '1'");
        echo "✔ settings.coin_daily added<br>";
    }

    if (!columnExists($connect, 'settings', 'coin_referral')) {
        mysqli_query($connect, "ALTER TABLE `settings` ADD `coin_referral` INT DEFAULT '1'");
        echo "✔ settings.coin_referral added<br>";
    }
}

/*
|--------------------------------------------------------------------------
| DONE
|--------------------------------------------------------------------------
*/
echo "<br>✅ Database update completed!";
