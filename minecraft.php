<?php

if (php_sapi_name() != 'cli') {
    exit;
}

declare(ticks=1);

function shutdown()
{
    if (file_exists('minecraft.txt')) {
        unlink('minecraft.txt');
    }
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
require __DIR__ . '/vendor/autoload.php';

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
            while (count($json->Tickrate) >= 60) {
                array_shift($json->Tickrate);
            }
            while (count($json->Memory) >= 60) {
                array_shift($json->Memory);
            }
            $query->Connect(MINECRAFT_IP, 25565, 1);
            $json->Info = $query->GetInfo();
            $players = $query->GetPlayers();
            $json->Players = $players ? $players : array();
            $day = explode(' ', $rcon->Rcon('time query day'))[3] + 1;
            $time = explode(' ', $rcon->Rcon('time query daytime'))[3];
            if ($time >= 18000) {
                ++$day;
            }
            $json->Day = (int) $day;
            $json->Time = (int) $time;
            $raining = explode(' ', $rcon->Rcon('execute if predicate [{"condition":"minecraft:weather_check","raining":true}]'))[1] == 'passed';
            $thundering = explode(' ', $rcon->Rcon('execute if predicate [{"condition":"minecraft:weather_check","thundering":true}]'))[1] == 'passed';
            if ($thundering) {
                $weather = 'thunder';
            } else if ($raining) {
                $weather = 'rain';
            } else {
                $weather = 'clear';
            }
            $json->Weather = $weather;
            $moonIndex = $day % 8;
            switch ($moonIndex) {
                case 0:
                    $moonPhase = 'full_moon';
                    break;
                case 1:
                    $moonPhase = 'waning_gibbous';
                    break;
                case 2:
                    $moonPhase = 'last_quarter';
                    break;
                case 3:
                    $moonPhase = 'waning_crescent';
                    break;
                case 4:
                    $moonPhase = 'new_moon';
                    break;
                case 5:
                    $moonPhase = 'waxing_crescent';
                    break;
                case 6:
                    $moonPhase = 'first_quarter';
                    break;
                case 7:
                    $moonPhase = 'waxing_gibbous';
                    break;
            }
            $json->Moon = $moonPhase;
            $lm = explode(PHP_EOL, $rcon->Rcon('lm'));
            $json->Tickrate[] = (float) explode(' ', $lm[0])[2];
            $memory = explode(' ', $lm[1])[3];
            $memory = (float) substr($memory, 1, strlen($memory) - 3);
            $json->Memory[] = $memory;
            $mspt = explode('/', explode(' ', preg_replace('/\xA7[0-9A-FK-OR]/i', '', explode(PHP_EOL, $rcon->Rcon('mspt'))[1]))[2]);
            $json->Ticktime = new stdClass;
            $json->Ticktime->Average = (float) preg_replace('/[^0-9.]/', '', $mspt[0]);
            $json->Ticktime->Minimum = (float) preg_replace('/[^0-9.]/', '', $mspt[1]);
            $json->Ticktime->Maximum = (float) preg_replace('/[^0-9.]/', '', $mspt[2]);
            file_put_contents('minecraft.txt', 1, LOCK_EX);
            file_put_contents('minecraft.json', json_encode($json), LOCK_EX);
        }
    } catch (Exception $e) {
        if (file_exists('minecraft.txt')) {
            unlink('minecraft.txt');
        }
        file_put_contents('minecraft.json', '', LOCK_EX);
        file_put_contents('minecraft.log', '[' . date('d-M-y H:i:s T') . '] ' . $e . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
