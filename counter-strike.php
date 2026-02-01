<?php

if (php_sapi_name() != "cli") {
    exit();
}

declare(ticks=1);

function shutdown()
{
    file_put_contents("counter-strike.json", "", LOCK_EX);
    exit();
}

register_shutdown_function("shutdown");
pcntl_signal(SIGINT, "shutdown");
pcntl_signal(SIGTERM, "shutdown");

date_default_timezone_set("UTC");
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "counter-strike.log");

use xPaw\SourceQuery\SourceQuery;

require __DIR__ . "/configuration.php";
require __DIR__ . "/vendor/autoload.php";

if (file_exists("counter-strike.json") && filesize("counter-strike.json") > 0) {
    $json = json_decode(file_get_contents("counter-strike.json"));
} else {
    $json = new stdClass();
    $json->Info = new stdClass();
    $json->Players = [];
}

while (true) {
    $query = new SourceQuery();
    try {
        $query->Connect(COUNTERSTRIKE_IP, 27015);
        $query->SetRconPassword(COUNTERSTRIKE_PASSWORD);
        while (true) {
            time_sleep_until(time() + 5);
            $json->Info = $query->GetInfo();
            $map = $json->Info["Map"];

            if (strpos($map, "workshop/") === 0) {
                preg_match('/^workshop\/([0-9]+)\/.+$/', $map, $matches);
                if (isset($matches[1])) {
                    $wsid = $matches[1];
                    $ch = curl_init();
                    curl_setopt(
                        $ch,
                        CURLOPT_URL,
                        "https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/",
                    );
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt(
                        $ch,
                        CURLOPT_POSTFIELDS,
                        "?key=" .
                            COUNTERSTRIKE_SECRET .
                            "&itemcount=1&publishedfileids%5B0%5D=" .
                            $wsid,
                    );
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $server_output = curl_exec($ch);
                    curl_close($ch);
                    $ws = json_decode($server_output);
                    $json->Info["MapImage"] =
                        $ws->response->publishedfiledetails[0]->preview_url ??
                        "";
                }
            } else {
                $clean_map = str_replace("de_", "", $map);

                if ($clean_map === "dust2") {
                    $clean_map = "Dust 2";
                } else {
                    $clean_map = ucfirst($clean_map);
                }

                $json->Info["MapImage"] =
                    "https://raw.githubusercontent.com/thecs2cup-sys/cs2-maps-images/main/" .
                    $clean_map .
                    ".webp";
            }
            $status = $query->Rcon("status");

            preg_match_all(
                "/^\s*\d+\s+.*\s+'(.+)'/m",
                $status,
                $matches,
                PREG_SET_ORDER,
            );

            $arr = [];

            foreach ($matches as $match) {
                $arr[] = [
                    "name" => $match[1],
                ];
            }

            $json->Players = $arr;
            file_put_contents(
                "counter-strike.json",
                json_encode($json),
                LOCK_EX,
            );
        }
    } catch (Exception $e) {
        file_put_contents("counter-strike.json", "", LOCK_EX);
        sleep(5);
    }
}
