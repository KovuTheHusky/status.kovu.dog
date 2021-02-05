<?php

if (php_sapi_name() != 'cli') {
    exit;
}

declare(ticks = 1);

function shutdown() {
    file_put_contents('counter-strike.json', '', LOCK_EX);
    exit;
}

register_shutdown_function('shutdown');
pcntl_signal(SIGINT, 'shutdown');
pcntl_signal(SIGTERM, 'shutdown');

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'counter-strike.log');

use xPaw\SourceQuery\SourceQuery;

require __DIR__ . '/configuration.php';
require __DIR__ . '/node_modules/PHP-Source-Query/SourceQuery/bootstrap.php';

if (file_exists('counter-strike.json') && filesize('counter-strike.json') > 0) {
    $json = json_decode(file_get_contents('counter-strike.json'));
} else {
    $json = new stdClass;
    $json->Info = new stdClass;
    $json->Players = array();
}

while (true) {
    for ($time = time(); $time == time(); usleep(1000));
    $query = new SourceQuery();
    try {
        $query->Connect(COUNTERSTRIKE_IP, 27015);
        while (true) {
            for ($time = time(); $time == time(); usleep(1000));
            $json->Info = $query->GetInfo();
            $players = $query->GetPlayers();
            $json->Players = $players ? $players : array();
            file_put_contents('counter-strike.json', json_encode($json), LOCK_EX);
        }
    } catch (Exception $e) {
        file_put_contents('counter-strike.json', '', LOCK_EX);
        file_put_contents('counter-strike.log', '[' . date('d-M-y H:i:s T') . '] ' . $e . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
