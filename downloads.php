<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/database.php';

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];
$tableName = $envConfig['DB_TABLE'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// download images
$data = $db->query("SELECT * FROM $tableName");

if ($db->numrows($data) > 0) {
  while ($row = $db->fetchArray($data)) {
    try {
      $zpid = $row['zpid'];
      $imgUrl = $row['image'];

      $imgFolder = __DIR__ . '/download/images/' . $zpid;
      $imgPath = $imgFolder . "/image.jpg";

      if (!file_exists($imgPath)) {
        if ($imgUrl && filter_var($imgUrl, FILTER_VALIDATE_URL)) {
          if (!file_exists($imgFolder)) {
            mkdir($imgFolder, 0777, true);
          }

          if (strpos($imgUrl, 'maps.googleapis.com') !== false) {
            $imgUrl = str_replace('&amp;', '&', $imgUrl);
          }

          $curl = curl_init($imgUrl);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
          $imageData = curl_exec($curl);

          if (curl_errno($curl)) {
            echo "cURL error: " . curl_error($curl) . "\n";
          } else {
            file_put_contents($imgPath, $imageData);
            curl_close($curl);
          }
        }
      }
    } catch (Exception $e) {
    }
  }
}

echo "Image download completed!\n";
exit();
