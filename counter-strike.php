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
    for ($time = time(); $time == time(); usleep(1000));
    $query = new SourceQuery();
    try {
        $query->Connect(COUNTERSTRIKE_IP, 27015);
        $query->SetRconPassword(COUNTERSTRIKE_PASSWORD);
        while (true) {
            for ($time = time(); $time == time(); usleep(1000));
            $json->Info = $query->GetInfo();
            $map = $json->Info["Map"];
            if (strpos($map, "workshop/") === 0) {
                preg_match('/^workshop\/([0-9]+)\/.+$/', $map, $matches);
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
                    $ws->response->publishedfiledetails[0]->preview_url;
            } else {
                $json->Info["MapImage"] =
                    "https://raw.githubusercontent.com/SteamDatabase/GameTracking-CSGO/master/csgo/maps/" .
                    $map .
                    ".jpg";
            }
            $status = $query->Rcon("status");
            preg_match_all(
                "/STEAM_1:([0-9]+):([0-9]+)/i",
                $status,
                $players,
                PREG_SET_ORDER,
            );
            $arr = [];
            foreach ($players as $player) {
                $id = $player[2] * 2 + $player[1] + 76561197960265728;

                $req = json_decode(
                    file_get_contents(
                        "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=" .
                            COUNTERSTRIKE_SECRET .
                            "&steamids=" .
                            $id,
                    ),
                );

                $avatar = $req->response->players[0]->avatarfull;
                $name = $req->response->players[0]->personaname;
                $url = $req->response->players[0]->profileurl;

                $arr[$id] = [
                    "name" => $name,
                    "avatar" => $avatar,
                    "url" => $url,
                ];
            }
            //preg_match_all('/"(.*)" BOT/i', $status, $bots, PREG_SET_ORDER);
            $json->Players = $arr;
            file_put_contents(
                "counter-strike.json",
                json_encode($json),
                LOCK_EX,
            );
        }
    } catch (Exception $e) {
        file_put_contents("counter-strike.json", "", LOCK_EX);
        file_put_contents(
            "counter-strike.log",
            "[" . date("d-M-y H:i:s T") . "] " . $e . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
