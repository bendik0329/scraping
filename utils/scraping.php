<?php

require_once  __DIR__ . '/database.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;

function _init()
{
  print_r("init function");
  print_r("\n");

  global $db, $conn;

  // check properties table
  $dropPropertiesSql = "DROP TABLE IF EXISTS properties";

  if ($db->query($dropPropertiesSql) === TRUE) {
    $createPropertiesSql = "CREATE TABLE IF NOT EXISTS properties (
      `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `zpid` INT ( 11 ) NOT NULL UNIQUE,
      `url` VARCHAR ( 255 ) NOT NULL,
      `image` VARCHAR ( 255 ),
      `currency` VARCHAR ( 255 ),
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
      `zestimateCurrency` VARCHAR ( 255 ),
      `zestimatePrice` INT ( 11 ),
      `houseType` VARCHAR ( 255 ),
      `builtYear` INT ( 11 ),
      `heating` VARCHAR ( 255 ),
      `cooling` VARCHAR ( 255 ),
      `parking` VARCHAR ( 255 ),
      `lot` FLOAT ( 4 ),
      `priceSqftCurrency` VARCHAR ( 255 ),
      `priceSqft` INT ( 11 ),
      `agencyFee` FLOAT ( 4 ),
      `days` INT ( 11 ),
      `views` INT ( 11 ),
      `saves` INT ( 11 ),
      `special` VARCHAR ( 255 ),
      `overview` TEXT,
      `images` TEXT,
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

  // check images table
  $dropImagesSql = "DROP TABLE IF EXISTS images";

  if ($db->query($dropImagesSql) === TRUE) {
    $imagesSql = "CREATE TABLE IF NOT EXISTS images (
      `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `zpid` VARCHAR ( 255 ) NOT NULL,
      `url` VARCHAR ( 255 ) NOT NULL,
      `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
    )";

    if ($db->query($imagesSql) === TRUE) {
      echo "Table images created successfully \n";
    } else {
      echo "Error creating images table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping images table: " . $conn->error . "\n";
  }

  // check price_history table
  $dropPriceHistoriesSql = "DROP TABLE IF EXISTS price_histories";

  if ($db->query($dropPriceHistoriesSql) === TRUE) {
    $priceHistoriesSql = "CREATE TABLE IF NOT EXISTS price_histories (
      `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `zpid` VARCHAR ( 255 ) NOT NULL,
      `date` DATE,
      `event` VARCHAR ( 255 ),
      `price` VARCHAR ( 255 ),
      `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
    )";

    if ($db->query($priceHistoriesSql) === TRUE) {
      echo "Table price_histories created successfully \n";
    } else {
      echo "Error creating price_histories table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping price_histories table: " . $conn->error . "\n";
  }

  // check tax_history table
  $dropTaxHistoriesSql = "DROP TABLE IF EXISTS tax_histories";

  if ($db->query($dropTaxHistoriesSql) === TRUE) {
    $taxHistoriesSql = "CREATE TABLE IF NOT EXISTS tax_histories (
      `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `zpid` VARCHAR ( 255 ) NOT NULL,
      `year` INT ( 11 ),
      `tax` VARCHAR ( 255 ),
      `taxAssessment` VARCHAR ( 255 ),
      `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
    )";

    if ($db->query($taxHistoriesSql) === TRUE) {
      echo "Table tax_histories created successfully \n";
    } else {
      echo "Error creating tax_histories table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping tax_histories table: " . $conn->error . "\n";
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

function scrapeProperties($propertyElements)
{
  global $db;
  $result = array();

  if (count($propertyElements) > 0) {
    foreach ($propertyElements as $propertyElement) {
      $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
      $zpid = intval($zpid);
      $images = array();
      $imgElements = $propertyElement->findElement(WebDriverBy::cssSelector("a.Anchor-c11n-8-84-3__sc-hn4bge-0.kxrUt.carousel-photo picture img.Image-c11n-8-84-3__sc-1rtmhsc-0"));
      if (count($imgElements) > 0) {
        foreach ($imgElements as $imgElement) {
          $images[] = $imgElement->getAttribute("src");;
        }
      }

      if ($zpid) {
        $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

        if ($exist->num_rows == 0) {
          $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

          $result[] = array(
            "zpid" => $zpid,
            "link" => $link,
            "images" => $images,
          );
        }
      }
    }
  }

  return $result;
}

function scrapePropertyDetail($zpid, $detailHtml)
{
  // get image
  try {
    $image = $detailHtml->findElement(WebDriverBy::cssSelector("div.media-column-container ul.hdp__sc-1wi9vqt-0.dDzspE.ds-media-col.media-stream li:nth-child(1) img"))->getAttribute("src");
  } catch (NoSuchElementException $e) {
    $image = "";
  }
  // get price
  try {
    $priceText = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span"))->getText();
    $deformatedPrice = deformatPrice($priceText);
    $currency = $deformatedPrice["currency"];
    $price = $deformatedPrice["price"];
  } catch (NoSuchElementException $e) {
    $currency = "";
    $price = 0;
  }

  // get address
  try {
    $addressText = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd"))->getText();
    $addressArray = explode(", ", $addressText);
    $address = $addressArray[0];
    $city = $addressArray[1];
    $stateArray = explode(" ", $addressArray[2]);
    $state = $stateArray[0];
    $zipcode = $stateArray[1];
  } catch (NoSuchElementException $e) {
    $address = "";
    $city = "";
    $state = "";
    $zipcode = "";
  }

  // get bed bath elements
  $bedBathElements = $detailHtml->findElements(WebDriverBy::cssSelector("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE div.hdp__sc-1s2b8ok-2.wmMDq span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd"));
  $bedBathElementsResult = scrapeBedBathElements($bedBathElements);

  // get type
  try {
    $type = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.dpf__sc-1yftt2a-0.bNENJa span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1yftt2a-1.hrfydd.ixkFNb"))->getText();
  } catch (NoSuchElementException $e) {
    $type = "";
  }

  // get zestimate
  try {
    $zestimatePriceText = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.hdp__sc-j76ge-1.fomYLZ > span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd > span.Text-c11n-8-84-3__sc-aiai24-0.hqOVzy span"))->getText();
    if ($zestimatePriceText == "None") {
      $zestimateCurrency = "";
      $zestimatePrice = 0;
    } else {
      $deformatedZestimatePrice = deformatPrice($zestimatePriceText);
      $zestimateCurrency = $deformatedZestimatePrice["currency"];
      $zestimatePrice = $deformatedZestimatePrice["price"];
    }
  } catch (NoSuchElementException $e) {
    $zestimateCurrency = "";
    $zestimatePrice = 0;
  }

  // get special info
  try {
    $special = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-ld4j6f-0.cKHmSE span.StyledTag-c11n-8-84-3__sc-1945joc-0.ftTUfk hdp__sc-ld4j6f-1.cosjzO"))->getText();
  } catch (NoSuchElementException $e) {
    $special = "";
  }

  // get overview
  try {
    $overview = $detailHtml->findElement(WebDriverBy::cssSelector("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd.jKaobh"))->getText();
  } catch (NoSuchElementException $e) {
    $overview = "";
  }

  // get house info
  $houseElements = $detailHtml->findElements(WebDriverBy::cssSelector("div.data-view-container ul.dpf__sc-xzpkxd-0.dFxsBL li.dpf__sc-2arhs5-0.gRshUo"));
  $houseElementsResult = scrapeHouseElements($houseElements);

  // get days, views, saves
  $dtElements = $detailHtml->findElements(WebDriverBy::cssSelector("dl.hdp__sc-7d6bsa-0.gmVtvh dt"));
  $dtElementsResult = scrapeDtElements($dtElements);

  // get price history
  $priceRowElements = $detailHtml->findElements(WebDriverBy::cssSelector("table.hdp__sc-f00yqe-2.jaEmHG tbody tr.hdp__sc-f00yqe-3.hTnieU"));
  $priceHistory = scrapePriceHistory($zpid, $priceRowElements);

  // get tax history
  $taxRowElements = $detailHtml->findElements(WebDriverBy::cssSelector("table.StyledTable-c11n-8-84-3__sc-b979s8-0.jtAqyI tbody tr.StyledTableRow-c11n-8-84-3__sc-1gk7etl-0.kaeLLi"));
  $taxHistory = scrapeTaxHistory($zpid, $taxRowElements);

  return array(
    "image" => $image,
    "currency" => $currency,
    "price" => $price,
    "address" => $address,
    "city" => $city,
    "state" => $state,
    "zipcode" => $zipcode,
    "beds" => $bedBathElementsResult["beds"],
    "baths" => $bedBathElementsResult["baths"],
    "sqft" => $bedBathElementsResult["sqft"],
    "acres" => $bedBathElementsResult["acres"],
    "type" => $type,
    "zestimateCurrency" => $zestimateCurrency,
    "zestimatePrice" => $zestimatePrice,
    "houseType" => $houseElementsResult["houseType"],
    "builtYear" => $houseElementsResult["builtYear"],
    "heating" => $houseElementsResult["heating"],
    "cooling" => $houseElementsResult["cooling"],
    "parking" => $houseElementsResult["parking"],
    "lot" => $houseElementsResult["lot"],
    "priceSqftCurrency" => $houseElementsResult["priceSqftCurrency"],
    "priceSqft" => $houseElementsResult["priceSqft"],
    "agencyFee" => $houseElementsResult["agencyFee"],
    "special" => $special,
    "overview" => $overview,
    "days" => $dtElementsResult["days"],
    "views" => $dtElementsResult["views"],
    "saves" => $dtElementsResult["saves"],
    "priceHistory" => $priceHistory,
    "taxHistory" => $taxHistory,
  );
}

function scrapeBedBathElements($bedBathElements)
{
  $beds = 0;
  $baths = 0;
  $sqft = 0;
  $acres = 0;

  $count = count($bedBathElements);

  if ($count > 1) {
    foreach ($bedBathElements as $bedBathElement) {
      try {
        $title = $bedBathElement->findElement(WebDriverBy::cssSelector("span"))->getText();
        $valueText = $bedBathElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
        preg_match('/\d+(\.\d+)?/', $valueText, $matches);
        if (!empty($matches)) {
          $value = $matches[0];
        } else {
          $value = 0;
        }
        if ($title) {
          switch ($title) {
            case "bd":
              $beds = $value;
              break;
            case "ba":
              $baths = $value;
              break;
            case "sqft":
              $sqft = $value;
              break;
            case "acres":
              $acres = $value;
              break;
          }
        }
      } catch (NoSuchElementException $e) {
      }
    }
  } else if ($count == 1) {
    foreach ($bedBathElements as $bedBathElement) {
      try {
        $acresText = $bedBathElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
        $acresArray = explode(" ", $acresText);
        $title = $acresArray[1];
        $value = $acresArray[0];
        if ($title && $value) {
          preg_match('/\d+(\.\d+)?/', $value, $matches);
          if (!empty($matches)) {
            $value = $matches[0];
          } else {
            $acres = 0;
          }

          switch ($title) {
            case "bd":
              $beds = $value;
              break;
            case "ba":
              $beds = $value;
              break;
            case "sqft":
              $sqft = $value;
              break;
            case "Acres":
              $acres = $value;
              break;
          }
        }
      } catch (NoSuchElementException $e) {
      }
    }
  }

  return array(
    "beds" => $beds,
    "baths" => $baths,
    "sqft" => $sqft,
    "acres" => $acres,
  );
}

function scrapeHouseElements($houseElements)
{
  global $driver;

  $houseType = "";
  $builtYear = 0;
  $heating = "";
  $cooling = "";
  $parking = "";
  $lot = 0;
  $priceSqftCurrency = "";
  $priceSqft = 0;
  $agencyFee = 0;

  if (count($houseElements) > 0) {
    foreach ($houseElements as $houseElement) {
      try {
        $svgElement = $houseElement->findElement(WebDriverBy::cssSelector("svg.Icon-c11n-8-84-3__sc-13llmml-0.iAcAav"));

        $script = "return arguments[0].querySelector('title').textContent;";
        $title = $driver->executeScript($script, [$svgElement]);

        if ($title) {
          switch ($title) {
            case "Type":
              try {
                $houseType = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($houseType == "No data") {
                  $houseType = "";
                }
              } catch (NoSuchElementException $e) {
                $houseType = "";
              }
              break;
            case "Year Built":
              try {
                $builtYearText = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($builtYearText == "No data") {
                  $builtYear = 0;
                } else {
                  $pattern = '/\b\d+\b/'; // Regular expression pattern to match any number

                  if (preg_match($pattern, $builtYearText, $matches)) {
                    $builtYear = $matches[0];
                  } else {
                    $builtYear = 0;
                  }
                }
              } catch (NoSuchElementException $e) {
                $builtYear = 0;
              }
              break;
            case "Heating":
              try {
                $heating = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($heating == "No data") {
                  $heating = "";
                }
              } catch (NoSuchElementException $e) {
                $heating = "";
              }
              break;
            case "Cooling":
              try {
                $cooling = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($cooling == "No data") {
                  $cooling = "";
                }
              } catch (NoSuchElementException $e) {
                $cooling = "";
              }
              break;
            case "Parking":
              try {
                $parking = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
                if ($parking == "No data") {
                  $parking = "";
                }
              } catch (NoSuchElementException $e) {
                $parking = "";
              }
              break;
            case "Lot":
              try {
                $lotText = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($lotText == "No data") {
                  $lot = 0;
                } else {
                  $lotArray = explode(" ", $lotText);
                  $lot = deformatNumber($lotArray[0]);
                  $unit = $lotArray[1];

                  if ($unit == "Acres") {
                    $lot = intval($lot) * 43560;
                  }
                }
              } catch (NoSuchElementException $e) {
                $lot = 0;
              }
              break;
            case "Price/sqft":
              try {
                $priceSqftText = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($priceSqftText == "No data") {
                  $priceSqftCurrency = "";
                  $priceSqft = 0;
                } else {
                  preg_match('/([^\d\s]+[\d,]+)/', $priceSqftText, $matches);
                  if (!empty($matches)) {
                    $deformatedPrice = deformatPrice($matches[0]);
                    $priceSqftCurrency = $deformatedPrice["currency"];
                    $priceSqft = $deformatedPrice["price"];
                  } else {
                    $priceSqftCurrency = "";
                    $priceSqft = 0;
                  }
                }
              } catch (NoSuchElementException $e) {
                $priceSqftCurrency = "";
                $priceSqft = 0;
              }
              break;
            case "Buyers Agency Fee":
              try {
                $agencyFeeText = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();

                if ($agencyFeeText == "No data") {
                  $agencyFee = 0;
                } else {
                  preg_match('/\d+(\.\d+)?/', $agencyFeeText, $matches);
                  if (!empty($matches)) {
                    $agencyFee = $matches[0];
                  } else {
                    $agencyFee = 0;
                  }
                }
              } catch (NoSuchElementException $e) {
                $agencyFee = 0;
              }
              break;
          }
        }
      } catch (NoSuchElementException $e) {
      }
    }
  }

  return array(
    "houseType" => $houseType,
    "builtYear" => $builtYear,
    "heating" => $heating,
    "cooling" => $cooling,
    "parking" => $parking,
    "lot" => $lot,
    "priceSqftCurrency" => $priceSqftCurrency,
    "priceSqft" => $priceSqft,
    "agencyFee" => $agencyFee,
  );
}

function scrapeDtElements($dtElements)
{
  $days = 0;
  $views = 0;
  $saves = 0;

  if (count($dtElements) > 0) {
    foreach ($dtElements as $key => $dtElement) {
      try {
        $valueText = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
        $valueText = str_replace(",", "", $valueText);
        preg_match('/\d+/', $valueText, $matches);
        if (isset($matches[0])) {
          $value = intval($matches[0]);
        } else {
          $value = 0;
        }
      } catch (NoSuchElementException $e) {
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
    "days" => $days,
    "views" => $views,
    "saves" => $saves,
  );
}

function scrapePriceHistory($zpid, $priceRowElements)
{
  global $db, $conn;
  $result = array();

  if (count($priceRowElements) > 0) {
    foreach ($priceRowElements as $priceRowElement) {
      $date = "";
      $event = "";
      $priceItem = "";
      $pricePerSqft = "";

      $priceColumnElements = $priceRowElement->findElements(WebDriverBy::cssSelector("td"));
      if (count($priceColumnElements) > 0) {
        foreach ($priceColumnElements as $key => $priceColumnElement) {
          switch ($key) {
            case 0:
              try {
                $date = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
              } catch (NoSuchElementException $e) {
                $date = "";
              }
              break;
            case 1:
              try {
                $event = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-4.bRcAjm.fTyeIS"))->getText();
              } catch (NoSuchElementException $e) {
                $event = "";
              }
              break;
            case 2:
              try {
                $priceItem = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-6.dQBikw.epSCRt span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-5.bRcAjm.ldMXqX"))->getText();
              } catch (NoSuchElementException $e) {
                $priceItem = "";
              }

              break;
          }
        }
      }

      $sql = "
        INSERT INTO price_histories
        (
          zpid,
          date,
          event,
          price,
          createdAt
        )
        VALUES
        (
          '" . $db->makeSafe($zpid) . "',
          '" . ($date != "" ? date("Y-m-d", strtotime($date)) : NULL) . "',
          '" . $db->makeSafe($event) . "',
          '" . $db->makeSafe($priceItem) . "',
          '" . date('Y-m-d H:i:s') . "'
        )";

      if (!$db->query($sql)) {
        echo "Error inserting price_histories table: " . $conn->error . "\n";
      }

      $result[] = array(
        "date" => $date,
        "event" => $event,
        "price" => $priceItem,
      );
    }
  }

  return $result;
}

function scrapeTaxHistory($zpid, $taxRowElements)
{
  global $db, $conn;
  $result = array();

  if (count($taxRowElements) > 0) {
    foreach ($taxRowElements as $taxRowElement) {
      $year = 0;
      $propertyTax = "";
      $taxAssessment = "";

      try {
        $year = $taxRowElement->findElement(WebDriverBy::cssSelector("th.StyledTableCell-c11n-8-84-3__sc-1mvjdio-0.StyledTableHeaderCell-c11n-8-84-3__sc-j48v56-0.eeNqSO span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
        $year = intval($year);
      } catch (NoSuchElementException $e) {
        $year = 0;
      }

      $taxColumnElements = $taxRowElement->findElements(WebDriverBy::cssSelector("td"));

      if (count($taxColumnElements) > 0) {
        foreach ($taxColumnElements as $key => $taxColumnElement) {
          switch ($key) {
            case 0:
              try {
                $propertyTaxText = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
                $propertyTaxArray = explode(" ", $propertyTaxText);
                $propertyTax = $propertyTaxArray[0];
              } catch (NoSuchElementException $e) {
                $propertyTax = "";
              }

              break;
            case 1:
              try {
                $taxAssessmentText = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
                $taxAssessmentArray = explode(" ", $taxAssessmentText);
                $taxAssessment = $taxAssessmentArray[0];
              } catch (NoSuchElementException $e) {
                $taxAssessment = "";
              }

              break;
          }
        }
      }

      $sql = "
        INSERT INTO tax_histories
        (
          zpid,
          year,
          tax,
          taxAssessment,
          createdAt
        )
        VALUES
        (
          '" . $db->makeSafe($zpid) . "',
          '" . $db->makeSafe($year) . "',
          '" . $db->makeSafe($propertyTax) . "',
          '" . $db->makeSafe($taxAssessment) . "',
          '" . date('Y-m-d H:i:s') . "'
        )";

      if (!$db->query($sql)) {
        echo "Error inserting tax_histories table: " . $conn->error . "\n";
      }

      $result[] = array(
        "year" => $year,
        "tax" => $propertyTax,
        "taxAssessment" => $taxAssessment,
      );
    }
  }

  return $result;
}

function deformatPrice($formated_price)
{
  $currency = substr($formated_price, 0, 1);
  $price = str_replace([$currency, ','], '', $formated_price);

  return array(
    "currency" => $currency,
    "price" => $price,
  );
}

function deformatNumber($formated_number)
{
  return str_replace(',', '', $formated_number);
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
