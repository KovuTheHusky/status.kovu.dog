<?php

if (php_sapi_name() != "cli") {
    exit();
}

declare(ticks=1);

function shutdown()
{
    if (file_exists("minecraft.txt")) {
        unlink("minecraft.txt");
    }
    file_put_contents("minecraft.json", "", LOCK_EX);
    exit();
}

register_shutdown_function("shutdown");
pcntl_signal(SIGINT, "shutdown");
pcntl_signal(SIGTERM, "shutdown");

date_default_timezone_set("UTC");
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "minecraft.log");

use xPaw\MinecraftQuery;
use xPaw\MinecraftQueryException;
use xPaw\SourceQuery\SourceQuery;

require __DIR__ . "/configuration.php";
require __DIR__ . "/vendor/autoload.php";

if (file_exists("minecraft.json") && filesize("minecraft.json") > 0) {
    $json = json_decode(file_get_contents("minecraft.json"));
} else {
    $json = new stdClass();
    $json->Info = new stdClass();
    $json->Players = [];
    $json->Tickrate = array_fill(0, 60, 0);
}

while (true) {
    $query = new MinecraftQuery();
    $rcon = new SourceQuery();

    try {
        $rcon->Connect(MINECRAFT_IP, 25575, 1);
        $rcon->SetRconPassword(MINECRAFT_PASSWORD);

        while (true) {
            time_sleep_until(time() + 1);

            while (count($json->Tickrate) >= 60) {
                array_shift($json->Tickrate);
            }

            $query->Connect(MINECRAFT_IP, 25565, 1);
            $json->Info = $query->GetInfo();
            $players = $query->GetPlayers();
            $json->Players = $players ? $players : [];

            $day = explode(" ", $rcon->Rcon("time query day"))[3] + 1;
            $time = explode(" ", $rcon->Rcon("time query daytime"))[3];
            if ($time >= 18000) {
                $day++;
            }
            $json->Day = (int) $day;
            $json->Time = (int) $time;

            $rainingRaw = $rcon->Rcon(
                'execute if predicate [{"condition":"minecraft:weather_check","raining":true}]',
            );
            $raining = strpos($rainingRaw, "passed") !== false;

            $thunderRaw = $rcon->Rcon(
                'execute if predicate [{"condition":"minecraft:weather_check","thundering":true}]',
            );
            $thundering = strpos($thunderRaw, "passed") !== false;

            $json->Weather = $thundering
                ? "thunder"
                : ($raining
                    ? "rain"
                    : "clear");

            $moonPhases = [
                "full_moon",
                "waning_gibbous",
                "last_quarter",
                "waning_crescent",
                "new_moon",
                "waxing_crescent",
                "first_quarter",
                "waxing_gibbous",
            ];
            $json->Moon = $moonPhases[$day % 8];

            $msptRaw = preg_replace(
                '/\xA7[0-9A-FK-OR]/i',
                "",
                explode(PHP_EOL, $rcon->Rcon("mspt"))[1],
            );
            $mspt = explode("/", explode(" ", $msptRaw)[2]);
            $json->Ticktime = new stdClass();
            $json->Ticktime->Average = (float) preg_replace(
                "/[^0-9.]/",
                "",
                $mspt[0],
            );
            $json->Ticktime->Minimum = (float) preg_replace(
                "/[^0-9.]/",
                "",
                $mspt[1],
            );
            $json->Ticktime->Maximum = (float) preg_replace(
                "/[^0-9.]/",
                "",
                $mspt[2],
            );

            $tpsRaw = preg_replace(
                '/\xA7[0-9A-FK-OR]/i',
                "",
                $rcon->Rcon("tps"),
            );
            $tpsMatch = explode(": ", $tpsRaw);
            if (isset($tpsMatch[1])) {
                $tpsValues = explode(", ", $tpsMatch[1]);
                $json->Tickrate[] = (float) $tpsValues[0];
            }

            file_put_contents("minecraft.txt", 1, LOCK_EX);
            file_put_contents("minecraft.json", json_encode($json), LOCK_EX);
        }
    } catch (Exception $e) {
        if (file_exists("minecraft.txt")) {
            unlink("minecraft.txt");
        }
        file_put_contents("minecraft.json", "", LOCK_EX);

        sleep(1);
    }
}
