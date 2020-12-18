<?php

if (php_sapi_name() != 'cli') {
    exit;
}

declare(ticks = 1);

function shutdown() {
    file_put_contents('minecraft.json', '', LOCK_EX);
    exit;
}

register_shutdown_function('shutdown');
pcntl_signal(SIGINT, 'shutdown');
pcntl_signal(SIGTERM, 'shutdown');

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'minecraft.log');

use xPaw\MinecraftQuery;
use xPaw\MinecraftQueryException;
use xPaw\SourceQuery\SourceQuery;

require __DIR__ . '/configuration.php';
require __DIR__ . '/node_modules/PHP-Minecraft-Query/src/MinecraftQuery.php';
require __DIR__ . '/node_modules/PHP-Minecraft-Query/src/MinecraftQueryException.php';
require __DIR__ . '/node_modules/PHP-Source-Query/SourceQuery/bootstrap.php';

if (file_exists('minecraft.json') && filesize('minecraft.json') > 0) {
    $json = json_decode(file_get_contents('minecraft.json'));
} else {
    $json = new stdClass;
    $json->Info = new stdClass;
    $json->Players = array();
    $json->Tickrate = array_fill(0, 60, 0);
    $json->Memory = array_fill(0, 60, 0);
}

while (true) {
    for ($time = time(); $time == time(); usleep(1000));
    $query = new MinecraftQuery();
    $rcon = new SourceQuery();
    try {
        $rcon->Connect(MINECRAFT_IP, 25575);
        $rcon->SetRconPassword(MINECRAFT_PASSWORD);
        while (true) {
            for ($time = time(); $time == time(); usleep(1000));
            array_shift($json->Tickrate);
            array_shift($json->Memory);
            $query->Connect(MINECRAFT_IP, 25565, 1);
            $json->Info = $query->GetInfo();
            $players = $query->GetPlayers();
            $json->Players = $players ? $players : array();
            $lm = explode(PHP_EOL, $rcon->Rcon('lm'));
            $json->Tickrate[] = (double) explode(' ', $lm[0])[2];
            $memory = explode(' ', $lm[1])[3];
            $memory = (double) substr($memory, 1, strlen($memory) - 3);
            $json->Memory[] = $memory;
            file_put_contents('minecraft.json', json_encode($json), LOCK_EX);
        }
    } catch (Exception $e) {
        file_put_contents('minecraft.json', '', LOCK_EX);
        file_put_contents('minecraft.log', '[' . date('d-M-y H:i:s T') . ']' . $e . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
