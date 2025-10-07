<?php

if (php_sapi_name() != 'cli') {
    exit;
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'places.log');

require __DIR__ . '/configuration.php';

/**
 * Renders a progress bar in the command line.
 *
 * @param int $done The number of items completed.
 * @param int $total The total number of items.
 * @param int $width The width of the progress bar in characters.
 */
function progressBar($done, $total, $width = 50)
{
    if ($total == 0) {
        return; // Avoid division by zero
    }

    $percentage = ($done / $total);
    $bar = floor($percentage * $width);

    // Build the progress bar string
    $statusBar = "\r[";
    $statusBar .= str_repeat("=", $bar);
    if ($bar < $width) {
        $statusBar .= ">";
        $statusBar .= str_repeat(" ", $width - $bar - 1);
    } else {
        $statusBar .= "=";
    }

    $disp = number_format($percentage * 100, 0);

    $statusBar .= "] $disp% ($done/$total)";

    echo $statusBar;
    flush();
}


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
$totalCheckins = 0;

$baseMarker = imagecreatefrompng('marker.png');
imagepalettetotruecolor($baseMarker);
imagealphablending($baseMarker, true);
imagesavealpha($baseMarker, true);

$loop = 0;
echo "Fetching check-in data..." . PHP_EOL;

do {

    $res = json_decode(file_get_contents('https://api.foursquare.com/v2/users/self/checkins?oauth_token=' . FOURSQUARE_SECRET . '&v=20200303&limit=250&offset=' . $offset));

    if ($loop === 0 && isset($res->response->checkins->count)) {
        $totalCheckins = $res->response->checkins->count;
    }

    if (isset($res->response->checkins->items)) {
        $currentFetched = min($offset + count($res->response->checkins->items), $totalCheckins);
        progressBar($currentFetched, $totalCheckins);
    }

    if ($res->meta->code != 200) {
        ++$sequentialErrors;
        ++$totalErrors;
        if ($sequentialErrors < 10 && $totalErrors < 100) {
            sleep(1);
            continue;
        } else {
            echo PHP_EOL . "Exiting due to too many consecutive errors." . PHP_EOL;
            exit;
        }
    } else {
        $sequentialErrors = 0;
    }

    if (!isset($res->response->checkins->items)) {
        break;
    }

    foreach ($res->response->checkins->items as $item) {
        if (in_array($item->venue->id, $unique)) {
            continue;
        }
        $unique[] = $item->venue->id;
        if (isset($item->venue->categories[0]->icon)) {
            $icon = str_replace('https://ss3.4sqi.net/img/categories_v2/', '', $item->venue->categories[0]->icon->prefix);
            $icon = str_replace('/', '-', $icon);
            $icon = rtrim($icon, '-');
            $icon = rtrim($icon, '_');
            if (!file_exists('markers/' . $icon . '.png')) {
                // file_put_contents('places/' . $icon . '.png', file_get_contents($item->venue->categories[0]->icon->prefix . '512' . $item->venue->categories[0]->icon->suffix));
                $pngData = file_get_contents($item->venue->categories[0]->icon->prefix . '512' . $item->venue->categories[0]->icon->suffix);

                // Create an image resource from the PNG data
                $image = imagecreatefromstring($pngData);

                if ($image !== false) {
                    $canvas = imagecreatetruecolor(imagesx($baseMarker), imagesy($baseMarker));

                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefill($canvas, 0, 0, $transparent);
                    imagealphablending($canvas, true);

                    imagecopy($canvas, $baseMarker, 0, 0, 0, 0, imagesx($baseMarker), imagesy($baseMarker));

                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);

                    $canvasSize = imagesx($canvas);
                    $iconSize = intval($canvasSize * 0.8); // Make the icon ~80% of the total marker size
                    $destX = intval(($canvasSize - $iconSize) / 2);
                    $destY = intval(($canvasSize - $iconSize) / 2);

                    imagecopyresampled($canvas, $image, $destX, $destY, 0, 0, $iconSize, $iconSize, imagesx($image), imagesy($image));

                    imagepng($canvas, 'markers/' . $icon . '.png');

                    // Free up memory
                    imagedestroy($image);
                    imagedestroy($canvas);
                }
            }
        } else {
            $icon = 'default';
        }
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
                'icon' => $icon,
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
    $loop++;
} while (count($res->response->checkins->items) > 0);

echo PHP_EOL; // Add a newline after the progress bar is complete


echo "Generating map icon sprite from composited images..." . PHP_EOL;

// Use npx to run the locally installed spritezero-cli
// Point it to our directory of freshly made composite markers
shell_exec('node markers.js');

echo "Sprite generated successfully (sprite.png, sprite.json)." . PHP_EOL;


echo "Processing complete. Saving files..." . PHP_EOL;

$bounds = array(
    array(
        $minlng - ($maxlng - $minlng) * 0.05,
        $minlat - ($maxlat - $minlat) * 0.05
    ),
    array(
        $maxlng + ($maxlng - $minlng) * 0.05,
        $maxlat + ($maxlat - $minlat) * 0.05
    )
);

file_put_contents('places.geojson', json_encode($geojson));
file_put_contents('places.js', 'var bounds = ' . json_encode($bounds) . ';');
