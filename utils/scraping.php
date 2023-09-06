<?php

require_once  __DIR__ . '/database.php';
require_once  __DIR__ . '/constants.php';

use voku\helper\HtmlDomParser;

function _init()
{
  global $db, $conn;

  // check properties table
  $dropPropertiesSql = "DROP TABLE IF EXISTS properties";

  if ($db->query($dropPropertiesSql) === TRUE) {
    $createPropertiesSql = "CREATE TABLE IF NOT EXISTS properties (
      `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `zpid` INT ( 11 ) NOT NULL UNIQUE,
      `url` VARCHAR ( 255 ) NOT NULL,
      `images` TEXT,
      `price` INT ( 11 ),
      `address` VARCHAR ( 255 ),
      `city` VARCHAR ( 255 ),
      `state` VARCHAR ( 255 ),
      `zipcode` VARCHAR ( 255 ),
      `beds` FLOAT ( 4 ),
      `baths` FLOAT ( 4 ),
      `sqft` FLOAT ( 4 ),
      `acres` FLOAT ( 4 ),
      `type` VARCHAR ( 255 ),
      `zestimate` INT ( 11 ),
      `houseType` VARCHAR ( 255 ),
      `builtYear` INT ( 11 ),
      `heating` VARCHAR ( 255 ),
      `cooling` VARCHAR ( 255 ),
      `parking` VARCHAR ( 255 ),
      `lot` FLOAT ( 4 ),
      `priceSqft` INT ( 11 ),
      `agencyFee` FLOAT ( 4 ),
      `days` INT ( 11 ),
      `views` INT ( 11 ),
      `saves` INT ( 11 ),
      `special` VARCHAR ( 255 ),
      `overview` TEXT,
      `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
    )";

    if ($db->query($createPropertiesSql) === TRUE) {
      echo "Table properties created successfully \n";
    } else {
      echo "Error creating properties table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping properties table: " . $conn->error . "\n";
  }

  // check the selenium server
  if (PHP_OS === "Linux") {
    $serviceName = "selenium.service";
    $checkCommand = "systemctl is-active $serviceName";
    $output = shell_exec($checkCommand);

    if (trim($output) !== "active") {
      $startCommand = "sudo systemctl start $serviceName";
      $startOutput = shell_exec($startCommand);

      echo "Selenium Service was not running. Attempting to start...\n";
      echo "Start command output: $startOutput\n";
    }
  }
}

function sendCurlRequest($url)
{
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
  $html = curl_exec($curl);
  curl_close($curl);

  $result = HtmlDomParser::str_get_html($html);

  return $result;
}

function retryCurlRequest($url, $maxRetries)
{
  print_r($url);
  print_r("\n");

  $retryCount = 0;
  $html = '';

  while (!($html instanceof \voku\helper\SimpleHtmlDomBlank) && $retryCount < $maxRetries) {
    print_r("retry");
    print_r("\n");
    $response = sendCurlRequest($url);
    $html = $response->findOne("div.detail-page");

    if (!($html instanceof \voku\helper\SimpleHtmlDomBlank)) {
      $retryCount++;
    }
  }

  return $html;
}

function scrapePropertyDetail($detailHtml)
{
  // $curl = curl_init();
  // curl_setopt($curl, CURLOPT_URL, $url);
  // curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  // curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
  // $html = curl_exec($curl);
  // curl_close($curl);

  // $htmlDomParser = HtmlDomParser::str_get_html($html);

  // $detailHtml = $htmlDomParser->findOne("div.detail-page");

  print_r($detailHtml);
  print_r("\n");
  
  // get price
  $priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");
  if ($priceElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $price = 0;
  } else {
    $priceText = $priceElement->text();
    $price = deformatPrice($priceText);
  }

  // get address
  $addressElement = $detailHtml->findOne("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd");
  if ($addressElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $address = "";
    $city = "";
    $state = "";
    $zipcode = "";
  } else {
    $addressText = $addressElement->text;
    $addressText = str_replace([", ", ", "], ",", $addressText);
    $addressArray = explode(",", $addressText);

    if (isset($addressArray[0])) {
      $address = trim($addressArray[0]);
    } else {
      $address = "";
    }

    if (isset($addressArray[1])) {
      $city = trim($addressArray[1]);
    } else {
      $city = "";
    }

    if (isset($addressArray[2])) {
      $state = trim($addressArray[2]);
      $stateArray = explode(" ", trim($addressArray[2]));

      if (isset($stateArray[0])) {
        $state = $stateArray[0];
      } else {
        $state = "";
      }

      if (isset($stateArray[1])) {
        $zipcode = $stateArray[1];
      } else {
        $zipcode = "";
      }
    } else {
      $state = "";
      $zipcode = "";
    }
  }

  // get type
  $typeElement = $detailHtml->findOne("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.dpf__sc-1yftt2a-0.bNENJa span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1yftt2a-1.hrfydd.ixkFNb");
  if ($typeElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $type = "";
  } else {
    $type = $typeElement->text();
  }

  // get zestimate
  $zestimateElement = $detailHtml->findOne("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.hdp__sc-j76ge-1.fomYLZ > span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd > span.Text-c11n-8-84-3__sc-aiai24-0.hqOVzy span");
  if ($zestimateElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $zestimate = 0;
  } else {
    $zestimateText = $zestimateElement->text();
    if ($zestimateText === "None") {
      $zestimate = 0;
    } else {
      $zestimate = deformatPrice($zestimateText);
    }
  }

  // get special info
  $specialElement = $detailHtml->findOne("div.hdp__sc-ld4j6f-0.cKHmSE span.StyledTag-c11n-8-84-3__sc-1945joc-0.ftTUfk hdp__sc-ld4j6f-1.cosjzO");
  if ($specialElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $special = "";
  } else {
    $special = $specialElement->text();
  }

  // get overview
  $overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd");
  if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $overview = "";
  } else {
    $overview = $overviewElement->text();
  }

  // get bed, bath info
  $beds = 0;
  $baths = 0;
  $sqft = 0;
  $acres = 0;

  $bedBathElements = $detailHtml->find("span[data-testid=\"bed-bath-item\"]");
  $count = $bedBathElements->count();

  if ($count > 1) {
    foreach ($bedBathElements as $bedBathElement) {
      $title = $bedBathElement->findOne("span > span")->text();
      $value = $bedBathElement->findOne("span > strong")->text();
      $value = deformatNumber($value);

      preg_match('/\d+(\.\d+)?/', $value, $matches);
      if (!empty($matches)) {
        $value = floatval($matches[0]);
      } else {
        $value = 0;
      }

      switch ($title) {
        case "bd":
          $beds = floatval($value);
          break;
        case "ba":
          $baths = floatval($value);
          break;
        case "sqft":
          $sqft = floatval($value);
          break;
        case "Acres":
          $acres = floatval($value);
          break;
      }
    }
  } else if ($count === 1) {
    foreach ($bedBathElements as $bedBathElement) {
      $text = $bedBathElement->findOne("span > strong")->text();
      $array = explode(" ", $text);
      $title = $array[1];
      $value = $array[0];

      if ($title && $value) {
        preg_match('/\d+(\.\d+)?/', $value, $matches);
        if (!empty($matches)) {
          $value = floatval($matches[0]);
        } else {
          $value = 0;
        }

        switch ($title) {
          case "bd":
            $beds = floatval($value);
            break;
          case "ba":
            $baths = floatval($value);
            break;
          case "sqft":
            $sqft = floatval($value);
            break;
          case "Acres":
            $acres = floatval($value);
            break;
        }
      }
    }
  }

  // get house info
  $houseType = "";
  $builtYear = 0;
  $heating = "";
  $cooling = "";
  $parking = "";
  $lot = 0;
  $priceSqft = 0;
  $agencyFee = 0;

  $houseElements = $detailHtml->find("div.data-view-container ul.dpf__sc-xzpkxd-0.dFxsBL li.dpf__sc-2arhs5-0.gRshUo");
  $count = $houseElements->count();
  if ($count > 0) {
    foreach ($houseElements as $houseElement) {
      $title = $houseElement->findOne("svg.Icon-c11n-8-84-3__sc-13llmml-0.iAcAav title")->text();
      $value = $houseElement->findOne("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB")->text();
      if ($title) {
        switch ($title) {
          case "Type";
            $houseType = $value;
            if ($houseType === "No data") {
              $houseType = "";
            }
            break;
          case "Year Built";
            $builtYear = $value;
            if ($builtYear === "No data") {
              $builtYear = 0;
            } else {
              $pattern = '/\b\d+\b/'; // Regular expression pattern to match any number

              if (preg_match($pattern, $builtYear, $matches)) {
                $builtYear = intval($matches[0]);
              } else {
                $builtYear = 0;
              }
            }
            break;
          case "Heating";
            $heating = $value;
            if ($heating === "No data") {
              $heating = "";
            }
            break;
          case "Cooling";
            $cooling = $value;
            if ($cooling === "No data") {
              $cooling = "";
            }
            break;
          case "Parking";
            $parking = $value;
            if ($parking === "No data") {
              $parking = "";
            }
            break;
          case "Lot";
            $lot = $value;
            if ($lot == "No data") {
              $lot = 0;
            } else {
              $lotArray = explode(" ", $lot);
              $lot = deformatNumber($lotArray[0]);
              $unit = $lotArray[1];

              if ($unit == "Acres") {
                $lot = floatval($lot) * 43560;
              }
            }
            break;
          case "Price/sqft";
            $priceSqft = $value;
            if ($priceSqft == "No data") {
              $priceSqft = 0;
            } else {
              preg_match('/([^\d\s]+[\d,]+)/', $priceSqft, $matches);
              if (!empty($matches)) {
                $priceSqft = deformatPrice($matches[0]);
              } else {
                $priceSqft = 0;
              }
            }
            break;
          case "Buyers Agency Fee";
            $agencyFee = $value;
            if ($agencyFee == "No data") {
              $agencyFee = 0;
            } else {
              preg_match('/\d+(\.\d+)?/', $agencyFee, $matches);
              if (!empty($matches)) {
                $agencyFee = floatval($matches[0]);
              } else {
                $agencyFee = 0;
              }
            }
            break;
        }
      }
    }
  }

  $days = 0;
  $views = 0;
  $saves = 0;

  $dtElements = $detailHtml->find("dl.hdp__sc-7d6bsa-0.gmVtvh dt");
  $count = $dtElements->count();
  if ($count > 0) {
    foreach ($dtElements as $key => $dtElement) {
      $value = $dtElement->findOne("strong")->text();
      $value = deformatNumber($value);
      preg_match('/\d+/', $value, $matches);
      if (isset($matches[0])) {
        $value = intval($matches[0]);
      } else {
        $value = 0;
      }

      switch ($key) {
        case 0:
          $days = $value;
          break;
        case 1:
          $views = $value;
          break;
        case 2:
          $saves = $value;
          break;
      }
    }
  }

  return array(
    "price" => $price,
    "address" => $address,
    "city" => $city,
    "state" => $state,
    "zipcode" => $zipcode,
    "beds" => $beds,
    "baths" => $baths,
    "sqft" => $sqft,
    "acres" => $acres,
    "type" => $type,
    "zestimate" => $zestimate,
    "houseType" => $houseType,
    "builtYear" => $builtYear,
    "heating" => $heating,
    "cooling" => $cooling,
    "parking" => $parking,
    "lot" => $lot,
    "priceSqft" => $priceSqft,
    "agencyFee" => $agencyFee,
    "special" => $special,
    "overview" => $overview,
    "days" => $days,
    "views" => $views,
    "saves" => $saves,
  );
}

function deformatPrice($formated_price)
{
  $currency = substr($formated_price, 0, 1);
  return floatval(str_replace([$currency, ','], '', $formated_price));
}

function deformatNumber($formated_number)
{
  return floatval(str_replace(',', '', $formated_number));
}

function downloadImages()
{
  global $db;
  $properties = $db->query("SELECT * FROM properties");

  if ($properties) {
    if ($properties->num_rows > 0) {
      while ($row = $properties->fetch_assoc()) {
        try {
          $zpid = $row['zpid'];
          $imgUrl = $row['image'];

          $imgFolder = __DIR__ . '/download/images/' . $zpid;
          if (!file_exists($imgFolder)) {
            mkdir($imgFolder, 0777, true);
          }

          $imgPath = $imgFolder . "/" . basename($imgUrl);
          if (!file_exists($imgPath)) {
            $imgData = file_get_contents($imgUrl);
            if ($imgData !== false) {
              file_put_contents($imgPath, $imgData);
            }
          }
        } catch (Exception $e) {
        }
      }
    }
  }
}

function logTimestamp($event)
{
  $logFile = __DIR__ . "/../log/logfile.txt";
  $timestamp = date('Y-m-d H:i:s');
  $logMessage = $event . ' - ' . $timestamp . PHP_EOL;
  file_put_contents($logFile, $logMessage, FILE_APPEND);
}
