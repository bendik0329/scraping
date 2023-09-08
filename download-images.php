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

// download images
$properties = $db->query("SELECT * FROM properties");

if ($db->numrows($properties) > 0) {
  while ($row = $db->fetchArray($properties)) {
    try {
      $zpid = $row['zpid'];
      $image = $row['image'];

      if ($image && filter_var($image, FILTER_VALIDATE_URL)) {
        $imgFolder = __DIR__ . '/download/images/' . $zpid;
        if (!file_exists($imgFolder)) {
          mkdir($imgFolder, 0777, true);
        }

        $imgPath = $imgFolder . "/" . basename($image);
        if (!file_exists($imgPath)) {
          $imgData = file_get_contents($image);
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
