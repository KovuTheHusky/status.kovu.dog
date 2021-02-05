<?php

if (php_sapi_name() != 'cli') {
    exit;
}

declare(ticks = 1);

function shutdown() {
    file_put_contents('terraria.json', '', LOCK_EX);
    exit;
}

register_shutdown_function('shutdown');
pcntl_signal(SIGINT, 'shutdown');
pcntl_signal(SIGTERM, 'shutdown');

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'terraria.log');

require __DIR__ . '/configuration.php';

while (true) {
    for ($time = time(); $time == time(); usleep(1000));
    $status = file_get_contents('http://' . TERRARIA_IP . ':7878/status?players=true');
    if ($status) {
        file_put_contents('terraria.json', $status);
    } else {
        file_put_contents('terraria.json', '', LOCK_EX);
    }
}
