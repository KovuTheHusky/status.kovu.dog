<?php

if (php_sapi_name() != 'cli') {
    exit;
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'places.log');

require __DIR__ . '/configuration.php';

$maxlng = -180;
$maxlat = -90;
$minlng = 180;
$minlat = 90;

$geojson = array(
    'type' => 'FeatureCollection',
    'features' => array()
);

$offset = 0;
$unique = array();
$sequentialErrors = 0;
$totalErrors = 0;

do {

    $res = json_decode(file_get_contents('https://api.foursquare.com/v2/users/self/checkins?oauth_token=' . FOURSQUARE_SECRET . '&v=20200303&limit=250&offset=' . $offset));

    if ($res->meta->code != 200) {
        ++$sequentialErrors;
        ++$totalErrors;
        if ($sequentialErrors < 10 && $totalErrors < 100) {
            continue;
        } else {
            exit;
        }
    } else {
        $sequentialErrors = 0;
    }

    foreach ($res->response->checkins->items as $item) {
        if (in_array($item->venue->id, $unique)) {
            continue;
        }
        $unique[] = $item->venue->id;
        if (!isset($item->venue->location->formattedAddress)) {
            $item->venue->location->formattedAddress = '';
        }
        $geojson['features'][] = array(
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $item->venue->location->lng,
                    $item->venue->location->lat
                )
            ),
            'properties' => array(
                'id' => $item->venue->id,
                'name' => $item->venue->name,
                'address' => $item->venue->location->formattedAddress
            )
        );
        if ($item->venue->location->lng > $maxlng) {
            $maxlng = $item->venue->location->lng;
        }
        if ($item->venue->location->lat > $maxlat) {
            $maxlat = $item->venue->location->lat;
        }
        if ($item->venue->location->lng < $minlng) {
            $minlng = $item->venue->location->lng;
        }
        if ($item->venue->location->lat < $minlat) {
            $minlat = $item->venue->location->lat;
        }
    }

    $offset += 250;

} while (count($res->response->checkins->items) > 0);

$bounds = array(
    array(
        $minlng - ($maxlng - $minlng) * 0.05,
        $minlat - ($maxlat - $minlat) * 0.05
    ), array(
        $maxlng + ($maxlng - $minlng) * 0.05,
        $maxlat + ($maxlat - $minlat) * 0.05
    )
);

file_put_contents('places.geojson', json_encode($geojson));
file_put_contents('places.js', 'var bounds = ' . json_encode($bounds) . ';');
