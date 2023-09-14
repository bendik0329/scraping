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
  echo "Download directory reseted!\n";
}

// download images
$properties = $db->query("SELECT * FROM properties");

if ($db->numrows($properties) > 0) {
  while ($row = $db->fetchArray($properties)) {
    try {
      $zpid = $row['zpid'];
      $imgUrl = $row['image'];

      if ($imgUrl && filter_var($imgUrl, FILTER_VALIDATE_URL)) {
        $imgFolder = __DIR__ . '/download/images/' . $zpid;
        if (!file_exists($imgFolder)) {
          mkdir($imgFolder, 0777, true);
        }

        if (strpos($imgUrl, 'maps.googleapis.com') !== false) {
          $imgUrl = str_replace('&amp;', '&', $imgUrl);
        }

        print_r($imgUrl);
        print_r("\n");

        $imgPath = $imgFolder . "/image.jpg";
        if (!file_exists($imgPath)) {
          $curl = curl_init($imgUrl);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
          $imageData = curl_exec($curl);

          if (curl_errno($curl)) {
            echo 'cURL error: ' . curl_error($curl);
          } else {
            file_put_contents($imgPath, $imageData);
            curl_close($curl);
            echo 'Image saved successfully!';
          }
        }



        // if (strpos($imgUrl, 'maps.googleapis.com') !== false) {
        //   $imgPath = $imgFolder . "/image.jpg";
        //   $imgUrl = str_replace('&amp;', '&', $imgUrl);
        //   // $imgUrl = preg_replace('/&signature=[^&]*/', '', $imgUrl);
        // } else {
        //   $imgPath = $imgFolder . "/" . basename($imgUrl);
        // }

        // print_r($imgUrl);
        // print_r("\n");

        // if (!file_exists($imgPath)) {
        //   try {
        //     $arrContextOptions = array(
        //       "ssl" => array(
        //         "verify_peer" => false,
        //         "verify_peer_name" => false,
        //       ),
        //     );
        //     $imgData = file_get_contents($imgUrl, false, stream_context_create($arrContextOptions));
        //     if ($imgData !== false) {
        //       file_put_contents($imgPath, $imgData);
        //     }
        //   } catch (Exception $e) {
        //     print_r($e);
        //     print_r("\n");
        //     print_r($imgUrl);
        //     print_r("\n");
        //   }
        // }
      }
    } catch (Exception $e) {
    }
  }
}

echo "Image download completed!\n";
exit();
