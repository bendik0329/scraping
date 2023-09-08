<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/database.php';

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// delete download directory
$downloadDir = __DIR__ . "/download";
if (is_dir($downloadDir)) {
  $command = "rm -rf $downloadDir";
  shell_exec($command);
}

// download images
$properties = $db->query("SELECT * FROM properties");

if ($db->numrows($properties) > 0) {
  while ($row = $db->fetchArray($properties)) {
    try {
      $zpid = $row['zpid'];
      $imgUrl = $row['image'];

      
      if ($imgUrl && filter_var($imgUrl, FILTER_VALIDATE_URL)) {
        print_r("url->>" . $imgUrl);
        print_r("\n");

        $imgFolder = __DIR__ . '/download/images/' . $zpid;
        if (!file_exists($imgFolder)) {
          mkdir($imgFolder, 0777, true);
        }

        if (strpos($imgUrl, 'maps.googleapis.com') !== false) {
          echo 'The URL is from Google Maps.';
          $imgPath = $imgFolder . "/image.jpg";

          $imgUrl = str_replace('&amp;', '&', $imgUrl);
          $imgUrl = preg_replace('/&signature=[^&]*/', '', $imgUrl);

        } else {
          echo 'The URL is not from Google Maps.';
          $imgPath = $imgFolder . "/" . basename($imgUrl);
        }

        if (!file_exists($imgPath)) {
          $imgData = file_get_contents($imgUrl);
          if ($imgData !== false) {
            file_put_contents($imgPath, $imgData);
          }
        }
      }
    } catch (Exception $e) {
    }
  }
}

echo "Image download completed!";
exit();
