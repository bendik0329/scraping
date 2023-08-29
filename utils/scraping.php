<?php

require_once  __DIR__ . '/database.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;

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
    `image` VARCHAR ( 255 ),
    `price` VARCHAR ( 255 ),
    `address` VARCHAR ( 255 ),
    `beds` VARCHAR ( 255 ),
    `baths` VARCHAR ( 255 ),
    `sqft` VARCHAR ( 255 ),
    `type` VARCHAR ( 255 ),
    `zestimate` VARCHAR ( 255 ),
    `houseType` VARCHAR ( 255 ),
    `builtYear` VARCHAR ( 255 ),
    `heating` VARCHAR ( 255 ),
    `cooling` VARCHAR ( 255 ),
    `parking` VARCHAR ( 255 ),
    `lot` VARCHAR ( 255 ),
    `priceSqft` VARCHAR ( 255 ),
    `agencyFee` VARCHAR ( 255 ),
    `days` VARCHAR ( 255 ),
    `views` VARCHAR ( 255 ),
    `saves` VARCHAR ( 255 ),
    `special` VARCHAR ( 255 ),
    `overview` VARCHAR ( 255 ),
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
    `priceSqft` VARCHAR ( 255 ),
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
    `taxRate` VARCHAR ( 255 ),
    `taxAssessment` VARCHAR ( 255 ),
    `taxAssessmentRate` VARCHAR ( 255 ),
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

      if ($zpid) {
        $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

        if ($exist->num_rows == 0) {
          $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

          $result[] = array(
            "zpid" => $zpid,
            "link" => $link,
          );
        }
      }
    }
  }

  return $result;
}

function scrapePropertyDetail($zpid, $detailHtml)
{
  global $db, $driver;

  // get image
  try {
    $image = $detailHtml->findElement(WebDriverBy::cssSelector("div.media-column-container ul.hdp__sc-1wi9vqt-0.dDzspE.ds-media-col.media-stream li:nth-child(1) img"))->getAttribute("src");
  } catch (NoSuchElementException $e) {
    $image = "";
  }
  // get price
  try {
    $price = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span"))->getText();
  } catch (NoSuchElementException $e) {
    $price = "";
  }

  // get address
  try {
    $address = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd"))->getText();
  } catch (NoSuchElementException $e) {
    $address = "";
  }

  // get beds
  try {
    $beds = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span[data-testid=\"bed-bath-item\"]:nth-child(1) strong"))->getText();
  } catch (NoSuchElementException $e) {
    $beds = "";
  }

  // get baths
  try {
    $baths = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container button[class*=\"TriggerText\"] span[data-testid=\"bed-bath-item\"] strong"))->getText();
  } catch (NoSuchElementException $e) {
    $baths = "";
  }

  // get sqft
  try {
    $sqft = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span[data-testid=\"bed-bath-item\"]:nth-child(5) strong"))->getText();
  } catch (NoSuchElementException $e) {
    $sqft = "";
  }

  // get type
  try {
    $type = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.dpf__sc-1yftt2a-0.bNENJa span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1yftt2a-1.hrfydd.ixkFNb"))->getText();
  } catch (NoSuchElementException $e) {
    $type = "";
  }

  // get zestimate
  try {
    $zestimate = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.hdp__sc-j76ge-1.fomYLZ > span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd > span.Text-c11n-8-84-3__sc-aiai24-0.hqOVzy span"))->getText();
  } catch (NoSuchElementException $e) {
    $zestimate = "None";
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
    "price" => $price,
    "address" => $address,
    "beds" => $beds,
    "baths" => $baths,
    "sqft" => $sqft,
    "type" => $type,
    "zestimate" => $zestimate,
    "houseType" => $houseElementsResult["houseType"],
    "builtYear" => $houseElementsResult["builtYear"],
    "heating" => $houseElementsResult["heating"],
    "cooling" => $houseElementsResult["cooling"],
    "parking" => $houseElementsResult["parking"],
    "lot" => $houseElementsResult["lot"],
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

function scrapeHouseElements($houseElements)
{
  global $driver;

  $houseType = "";
  $builtYear = 0;
  $heating = "";
  $cooling = "";
  $parking = "";
  $lot = "";
  $priceSqft = "";
  $agencyFee = "";

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
              } catch (NoSuchElementException $e) {
                $houseType = "";
              }
              break;
            case "Year Built":
              try {
                $builtYearText = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
                $pattern = '/\b\d+\b/'; // Regular expression pattern to match any number

                if (preg_match($pattern, $builtYearText, $matches)) {
                  $builtYear = $matches[0];
                } else {
                  $builtYear = 0;
                }
              } catch (NoSuchElementException $e) {
                $builtYear = 0;
              }
              break;
            case "Heating":
              try {
                $heating = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $heating = "";
              }
              break;
            case "Cooling":
              try {
                $cooling = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $cooling = "";
              }
              break;
            case "Parking":
              try {
                $parking = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $parking = "";
              }
              break;
            case "Lot":
              try {
                $lot = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $lot = "";
              }
              break;
            case "Price/sqft":
              try {
                $priceSqft = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $priceSqft = "";
              }
              break;
            case "Buyers Agency Fee":
              try {
                $agencyFee = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
              } catch (NoSuchElementException $e) {
                $agencyFee = "";
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
    "priceSqft" => $priceSqft,
    "agencyFee" => $agencyFee,
  );
}

function scrapeDtElements($dtElements)
{
  $days = "";
  $views = "";
  $saves = "";

  if (count($dtElements) > 0) {
    foreach ($dtElements as $key => $dtElement) {
      switch ($key) {
        case 0:
          try {
            $days = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
          } catch (NoSuchElementException $e) {
            $days = "";
          }
          break;
        case 1:
          try {
            $views = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
          } catch (NoSuchElementException $e) {
            $views = "";
          }
          break;
        case 2:
          try {
            $saves = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
          } catch (NoSuchElementException $e) {
            $saves = "";
          }
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
  global $db;
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

              try {
                $pricePerSqft = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-7.fEaJVg.jNzJBc"))->getText();
              } catch (NoSuchElementException $e) {
                $pricePerSqft = "";
              }

              break;
          }
          try {
          } catch (NoSuchElementException $e) {
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
          priceSqft,
          createdAt
        )
        VALUES
        (
          '" . $db->makeSafe($zpid) . "',
          '" . ($date != "" ? date("Y-m-d", strtotime($date)) : NULL) . "',
          '" . $db->makeSafe($event) . "',
          '" . $db->makeSafe($priceItem) . "',
          '" . $db->makeSafe($pricePerSqft) . "',
          '" . date('Y-m-d H:i:s') . "'
        )";

      $db->query($sql);
      
      $result[] = array(
        "date" => $date,
        "event" => $event,
        "price" => $priceItem,
        "priceSqft" => $pricePerSqft,
      );
    }
  }

  return $result;
}

function scrapeTaxHistory($zpid, $taxRowElements)
{
  global $db;
  $result = array();

  if (count($taxRowElements) > 0) {
    foreach ($taxRowElements as $taxRowElement) {
      $year = 0;
      $propertyTax = "";
      $propertyTaxRate = "";
      $taxAssessment = "";
      $taxAssessmentRate = "";

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

              try {
                $propertyTaxRate = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm span.hdp__sc-vcntbl-0.frFkpC"))->getText();
              } catch (NoSuchElementException $e) {
                $propertyTaxRate = "";
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

              try {
                $taxAssessmentRate = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm span.hdp__sc-vcntbl-0.frFkpC"))->getText();
              } catch (NoSuchElementException $e) {
                $taxAssessmentRate = "";
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
          taxRate,
          taxAssessment,
          taxAssessmentRate,
          createdAt
        )
        VALUES
        (
          '" . $db->makeSafe($zpid) . "',
          '" . $db->makeSafe($year) . "',
          '" . $db->makeSafe($propertyTax) . "',
          '" . $db->makeSafe($propertyTaxRate) . "',
          '" . $db->makeSafe($taxAssessment) . "',
          '" . $db->makeSafe($taxAssessmentRate) . "',
          '" . date('Y-m-d H:i:s') . "'
        )";

      $db->query($sql);

      $result[] = array(
        "year" => $year,
        "tax" => $propertyTax,
        "taxRate" => $propertyTaxRate,
        "taxAssessment" => $taxAssessment,
        "taxAssessmentRate" => $taxAssessmentRate,
      );
    }
  }

  return $result;
}
