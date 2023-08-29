<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
// require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;

$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];
$apiKey = $envConfig['API_KEY'];
$db  = new Database();

// Connect to DB
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

_init();

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);

$result = [];

$filterState = array(
  "beds" => array(
    "min" => 1
  ),
  "baths" => array(
    "min" => 1
  ),
  "sqft" => array(
    "min" => 500,
    "max" => 750,
  ),
  "pmf" => array(
    "value" => true
  ),
  "sort" => array(
    "value" => "globalrelevanceex"
  ),
  "nc" => array(
    "value" => false
  ),
  "fsbo" => array(
    "value" => false
  ),
  "cmsn" => array(
    "value" => false
  ),
  "pf" => array(
    "value" => true
  ),
  "fsba" => array(
    "value" => false
  )
);

$query = array(
  "pagination" => new stdClass(),
  "usersSearchTerm" => "CA",
  "filterState" => $filterState,
  "isListVisible" => true
);

$queryString = json_encode($query);
$searchQueryState = urlencode($queryString);
$url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";

$driver->get($url);

try {
  $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
  $pattern = '/\d+/';

  preg_match('/\d+/', $totalCount, $matches);

  if (isset($matches[0])) {
    $totalCount = intval($matches[0]);
    $itemsPerPage = 41;


    $currentPage = 1;
    $maxPage = ceil($totalCount / $itemsPerPage);

    while ($currentPage <= $maxPage) {
      if ($currentPage !== 1) {
        $pagination = array(
          "currentPage" => $currentPage,
        );
        $query["pagination"] = $pagination;
      }

      $queryString = json_encode($query);
      $searchQueryState = urlencode($queryString);
      $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";
      $driver->get($pageUrl);

      $html = $driver->findElement(WebDriverBy::tagName('html'));
      $html->sendKeys(WebDriverKeys::END);
      sleep(5);

      $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
      $list = scrapeProperties($propertyElements);

      print_r($list);
      // $list = array();
      
      // if (count($propertyElements) > 0) {
      //   foreach ($propertyElements as $propertyElement) {
      //     $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
      //     $zpid = intval($zpid);

      //     if ($zpid) {
      //       $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

      //       if ($exist->num_rows == 0) {
      //         $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

      //         $list[] = array(
      //           "zpid" => $zpid,
      //           "link" => $link,
      //         );
      //         // try {
      //         //   $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

      //         //   $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=$link";
      //         //   print_r($pageUrl);
      //         //   print_r("\n");
      //         // $driver->get($pageUrl);
      //         // sleep(5);

      //         // $html = $driver->findElement(WebDriverBy::tagName('html'));
      //         // $html->sendKeys(WebDriverKeys::END);
      //         // sleep(5);

      //         // $detailHtml = $driver->findElement(WebDriverBy::cssSelector("div.detail-page"));

      //         // try {
      //         //   $price = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $price = 0;
      //         // }

      //         // try {
      //         //   $address = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $address = "";
      //         // }

      //         // try {
      //         //   $beds = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd strong"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $beds = 0;
      //         // }

      //         // try {
      //         //   $baths = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span.Text-c11n-8-84-3__sc-aiai24-0 hrfydd"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $baths = 0;
      //         // }

      //         //   $result = array(
      //         //     "zpid" => $zpid,
      //         //     "url" => $link,
      //         //     // "price" => $price,
      //         //     // "address" => $address,
      //         //   );
      //         // } catch (NoSuchElementException $e) {
      //         // }

      //         // $address = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a > address"))->getText();

      //         // try {
      //         //   $beds = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(1) > b"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $beds = 0;
      //         // }

      //         // try {
      //         //   $baths = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(2) > b"))->getText();
      //         // } catch (NoSuchElementException $e) {
      //         //   $baths = 0;
      //         // }

      //         // $price = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data span.PropertyCardWrapper__StyledPriceLine-srp__sc-16e8gqd-1"))->getText();


      //         // $imgList = [];
      //         // $imgElements = $propertyElement->findElements(WebDriverBy::cssSelector("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0"));
      //         // foreach ($imgElements as $imgElement) {
      //         //   $imgUrl = $imgElement->findElement(WebDriverBy::cssSelector("img.Image-c11n-8-84-3__sc-1rtmhsc-0"))->getAttribute("src");

      //         //   $imgExist = $db->query("SELECT * FROM images WHERE zpid = $zpid AND url = '$imgUrl'");

      //         //   if ($imgExist->num_rows == 0) {
      //         //     $sql = "
      //         //       INSERT INTO images
      //         //       (
      //         //         zpid, 
      //         //         url,
      //         //         createdAt
      //         //       )
      //         //       VALUES
      //         //       (
      //         //         '" . $db->makeSafe($zpid) . "',
      //         //         '" . $db->makeSafe($imgUrl) . "',
      //         //         '" . date('Y-m-d H:i:s') . "'
      //         //       )";
      //         //     $db->query($sql);
      //         //   }

      //         //   $imgList[] = $imgUrl;
      //         // }

      //         // $sql1 = "
      //         //   INSERT INTO properties
      //         //   (
      //         //     zpid, 
      //         //     address,
      //         //     price,
      //         //     beds,
      //         //     baths,
      //         //     images,
      //         //     url,
      //         //     createdAt
      //         //   )
      //         //   VALUES
      //         //   (
      //         //     '" . $db->makeSafe($zpid) . "',
      //         //     '" . $db->makeSafe($address) . "',
      //         //     '" . $db->makeSafe($price) . "',
      //         //     '" . $db->makeSafe($beds) . "',
      //         //     '" . $db->makeSafe($baths) . "',
      //         //     '" . $db->makeSafe(json_encode($imgList)) . "',
      //         //     '" . $db->makeSafe($url) . "',
      //         //     '" . date('Y-m-d H:i:s') . "'
      //         //   )";

      //         // $db->query($sql1);

      //         // $result[] = array(
      //         //   "zpid" => $zpid,
      //         //   "url" => $url,
      //         //   "address" => $address,
      //         //   "price" => $price,
      //         //   "beds" => intval($beds),
      //         //   "baths" => intval($baths),
      //         //   "images" => $imgList,
      //         // );
      //       }
      //     }
      //   }
      // }

      // foreach ($list as $item) {
      //   if ($item["zpid"] && $item["link"]) {
      //     $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $item["link"];
      //     $driver->get($detailUrl);
      //     sleep(5);

      //     $detailHtml = $driver->findElement(WebDriverBy::cssSelector("div.detail-page"));

      //     try {
      //       $price = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $price = "";
      //     }

      //     try {
      //       $address = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $address = "";
      //     }

      //     try {
      //       $beds = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span[data-testid=\"bed-bath-item\"]:nth-child(1) strong"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $beds = "";
      //     }

      //     try {
      //       $baths = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container button[class*=\"TriggerText\"] span[data-testid=\"bed-bath-item\"] strong"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $baths = "";
      //     }

      //     try {
      //       $sqft = $detailHtml->findElement(WebDriverBy::cssSelector("div.summary-container span[data-testid=\"bed-bath-item\"]:nth-child(5) strong"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $sqft = "";
      //     }

      //     try {
      //       $type = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.dpf__sc-1yftt2a-0.bNENJa span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1yftt2a-1.hrfydd.ixkFNb"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $type = "";
      //     }

      //     try {
      //       $zestimate = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.hdp__sc-j76ge-1.fomYLZ > span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd > span.Text-c11n-8-84-3__sc-aiai24-0.hqOVzy span"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $zestimate = "None";
      //     }

      //     $houseType = "";
      //     $builtYear = "";
      //     $heating = "";
      //     $cooling = "";
      //     $parking = "";
      //     $lot = "";
      //     $priceSqft = "";
      //     $agencyFee = "";
      //     try {
      //       $houseElements = $detailHtml->findElements(WebDriverBy::cssSelector("div.data-view-container ul.dpf__sc-xzpkxd-0.dFxsBL li.dpf__sc-2arhs5-0.gRshUo"));
      //       if (count($houseElements) > 0) {
      //         foreach ($houseElements as $houseElement) {
      //           try {
      //             $svgElement = $houseElement->findElement(WebDriverBy::cssSelector("svg.Icon-c11n-8-84-3__sc-13llmml-0.iAcAav"));

      //             $script = "return arguments[0].querySelector('title').textContent;";
      //             $title = $driver->executeScript($script, [$svgElement]);

      //             if ($title) {
      //               switch ($title) {
      //                 case "Type":
      //                   try {
      //                     $houseType = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $houseType = "";
      //                   }
      //                   break;
      //                 case "Year Built":
      //                   try {
      //                     $builtYear = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $builtYear = "";
      //                   }
      //                   break;
      //                 case "Heating":
      //                   try {
      //                     $heating = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $heating = "";
      //                   }
      //                   break;
      //                 case "Cooling":
      //                   try {
      //                     $cooling = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $cooling = "";
      //                   }
      //                   break;
      //                 case "Parking":
      //                   try {
      //                     $parking = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $parking = "";
      //                   }
      //                   break;
      //                 case "Lot":
      //                   try {
      //                     $lot = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $lot = "";
      //                   }
      //                   break;
      //                 case "Price/sqft":
      //                   try {
      //                     $priceSqft = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $priceSqft = "";
      //                   }
      //                   break;
      //                 case "Buyers Agency Fee":
      //                   try {
      //                     $agencyFee = $houseElement->findElement(WebDriverBy::cssSelector("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB"))->getText();
      //                   } catch (NoSuchElementException $e) {
      //                     $agencyFee = "";
      //                   }
      //                   break;
      //               }
      //             }
      //           } catch (NoSuchElementException $e) {
      //             print_r($e);
      //           }
      //         }
      //       }
      //     } catch (NoSuchElementException $e) {
      //     }

      //     try {
      //       $special = $detailHtml->findElement(WebDriverBy::cssSelector("div.hdp__sc-ld4j6f-0.cKHmSE span.StyledTag-c11n-8-84-3__sc-1945joc-0.ftTUfk hdp__sc-ld4j6f-1.cosjzO"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $special = "";
      //     }

      //     try {
      //       $overview = $detailHtml->findElement(WebDriverBy::cssSelector("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd.jKaobh"))->getText();
      //     } catch (NoSuchElementException $e) {
      //       $overview = "";
      //     }

      //     $days = "";
      //     $views = "";
      //     $saves = "";

      //     $dtElements = $detailHtml->findElements(WebDriverBy::cssSelector("dl.hdp__sc-7d6bsa-0.gmVtvh dt"));
      //     if (count($dtElements) > 0) {
      //       foreach ($dtElements as $key => $dtElement) {
      //         switch ($key) {
      //           case 0:
      //             try {
      //               $days = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
      //             } catch (NoSuchElementException $e) {
      //               $days = "";
      //             }
      //             break;
      //           case 1:
      //             try {
      //               $views = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
      //             } catch (NoSuchElementException $e) {
      //               $views = "";
      //             }
      //             break;
      //           case 2:
      //             try {
      //               $saves = $dtElement->findElement(WebDriverBy::cssSelector("strong"))->getText();
      //             } catch (NoSuchElementException $e) {
      //               $saves = "";
      //             }
      //             break;
      //         }
      //       }
      //     }

      //     $priceHistory = array();

      //     $priceRowElements = $detailHtml->findElements(WebDriverBy::cssSelector("table.hdp__sc-f00yqe-2.jaEmHG tbody tr.hdp__sc-f00yqe-3.hTnieU"));
      //     if (count($priceRowElements) > 0) {
      //       foreach ($priceRowElements as $priceRowElement) {
      //         $date = "";
      //         $event = "";
      //         $priceItem = "";
      //         $pricePerSqft = "";

      //         $priceColumnElements = $priceRowElement->findElements(WebDriverBy::cssSelector("td"));
      //         if (count($priceColumnElements) > 0) {
      //           foreach ($priceColumnElements as $key => $priceColumnElement) {
      //             switch ($key) {
      //               case 0:
      //                 try {
      //                   $date = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $date = "";
      //                 }
      //                 break;
      //               case 1:
      //                 try {
      //                   $event = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-4.bRcAjm.fTyeIS"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $event = "";
      //                 }
      //                 break;
      //               case 2:
      //                 try {
      //                   $priceItem = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-6.dQBikw.epSCRt span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-5.bRcAjm.ldMXqX"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $priceItem = "";
      //                 }

      //                 try {
      //                   $pricePerSqft = $priceColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.hdp__sc-reo5z7-7.fEaJVg.jNzJBc"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $pricePerSqft = "";
      //                 }

      //                 break;
      //             }
      //             try {
      //             } catch (NoSuchElementException $e) {
      //             }
      //           }
      //         }
      //         $priceHistory[] = array(
      //           "date" => $date,
      //           "event" => $event,
      //           "price" => $priceItem,
      //           "priceSqft" => $pricePerSqft,
      //         );
      //       }
      //     }

      //     $taxHistory = array();
      //     $taxRowElements = $detailHtml->findElements(WebDriverBy::cssSelector("table.StyledTable-c11n-8-84-3__sc-b979s8-0.jtAqyI tbody tr.StyledTableRow-c11n-8-84-3__sc-1gk7etl-0.kaeLLi"));
      //     if (count($taxRowElements) > 0) {
      //       foreach ($taxRowElements as $taxRowElement) {
      //         $year = 0;
      //         $propertyTax = "";
      //         $propertyTaxRate = "";
      //         $taxAssessment = "";
      //         $taxAssessmentRate = "";

      //         try {
      //           $year = $taxRowElement->findElement(WebDriverBy::cssSelector("th.StyledTableCell-c11n-8-84-3__sc-1mvjdio-0.StyledTableHeaderCell-c11n-8-84-3__sc-j48v56-0.eeNqSO span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
      //           $year = intval($year);
      //         } catch (NoSuchElementException $e) {
      //           $year = 0;
      //         }

      //         $taxColumnElements = $taxRowElement->findElements(WebDriverBy::cssSelector("td"));

      //         if (count($taxColumnElements) > 0) {
      //           foreach ($taxColumnElements as $key => $taxColumnElement) {
      //             switch ($key) {
      //               case 0:
      //                 try {
      //                   $propertyTax = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $propertyTax = "";
      //                 }

      //                 try {
      //                   $propertyTaxRate = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm span.hdp__sc-vcntbl-0.frFkpC"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $propertyTaxRate = "";
      //                 }

      //                 break;
      //               case 1:
      //                 try {
      //                   $taxAssessment = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $taxAssessment = "";
      //                 }

      //                 try {
      //                   $taxAssessmentRate = $taxColumnElement->findElement(WebDriverBy::cssSelector("span.hdp__sc-reo5z7-1.bRcAjm span.hdp__sc-vcntbl-0.frFkpC"))->getText();
      //                 } catch (NoSuchElementException $e) {
      //                   $taxAssessmentRate = "";
      //                 }

      //                 break;
      //             }
      //           }
      //         }

      //         $taxHistory[] = array(
      //           "year" => $year,
      //           "tax" => $propertyTax,
      //           "taxRate" => $propertyTaxRate,
      //           "taxAssessment" => $taxAssessment,
      //           "taxAssessmentRate" => $taxAssessmentRate,
      //         );
      //       }
      //     }

      //     $result[] = array(
      //       "zpid" => $item["zpid"],
      //       "url" => $item["link"],
      //       "price" => $price,
      //       "address" => $address,
      //       "beds" => $beds,
      //       "baths" => $baths,
      //       "sqft" => $sqft,
      //       "type" => $type,
      //       "zestimate" => $zestimate,
      //       "houseType" => $houseType,
      //       "builtYear" => $builtYear,
      //       "heating" => $heating,
      //       "cooling" => $cooling,
      //       "parking" => $parking,
      //       "lot" => $lot,
      //       "priceSqft" => $priceSqft,
      //       "agencyFee" => $agencyFee,
      //       "special" => $special,
      //       "overview" => $overview,
      //       "days" => $days,
      //       "views" => $views,
      //       "saves" => $saves,
      //       "priceHistory" => $priceHistory,
      //       "taxHistory" => $taxHistory,
      //     );
      //   }
      // }

      $currentPage++;
    }
  }
} catch (NoSuchElementException $e) {
  print_r($e);
}

echo json_encode($result);
$driver->close();

function _init()
{
  global $db, $conn;
  
  // check properties table
  $dropPropertiesSql = "DROP TABLE IF EXISTS properties";

  if ($db->query($dropPropertiesSql) === TRUE) {
    $createPropertiesSql = "CREATE TABLE IF NOT EXISTS properties (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL UNIQUE,
    `url` VARCHAR ( 255 ) NOT NULL,
    `address` VARCHAR ( 255 ),
    `price` VARCHAR ( 255 ),
    `beds` VARCHAR ( 255 ),
    `baths` VARCHAR ( 255 ),
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
  $dropPriceHistorySql = "DROP TABLE IF EXISTS price_history";

  if ($db->query($dropPriceHistorySql) === TRUE) {
    $priceHistorySql = "CREATE TABLE IF NOT EXISTS price_history (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL,
    `date` DATE,
    `event` VARCHAR ( 255 ),
    `price` VARCHAR ( 255 ),
    `priceSqft` VARCHAR ( 255 ),
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

    if ($db->query($priceHistorySql) === TRUE) {
      echo "Table price_history created successfully \n";
    } else {
      echo "Error creating price_history table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping price_history table: " . $conn->error . "\n";
  }

  // check tax_history table
  $dropTaxHistorySql = "DROP TABLE IF EXISTS tax_history";

  if ($db->query($dropTaxHistorySql) === TRUE) {
    $taxHistorySql = "CREATE TABLE IF NOT EXISTS tax_history (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL,
    `year` INT ( 11 ),
    `tax` VARCHAR ( 255 ),
    `taxRate` VARCHAR ( 255 ),
    `taxAssessment` VARCHAR ( 255 ),
    `taxAssessmentRate` VARCHAR ( 255 ),
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

    if ($db->query($taxHistorySql) === TRUE) {
      echo "Table tax_history created successfully \n";
    } else {
      echo "Error creating tax_history table: " . $conn->error . "\n";
    }
  } else {
    echo "Error dropping tax_history table: " . $conn->error . "\n";
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

function scrapePropertyDetail($propertyElements) {
  global $db;
  $value = array();

  if (count($propertyElements) > 0) {
    foreach ($propertyElements as $propertyElement) {
      $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
      $zpid = intval($zpid);

      if ($zpid) {
        $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

        if ($exist->num_rows == 0) {
          $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

          $value[] = array(
            "zpid" => $zpid,
            "link" => $link,
          );
        }
      }
    }
    return $value;
  } else {
    return array();
  }
}
exit();

// download images
$query = "SELECT * FROM images";
$images = $db->query("SELECT * FROM images");

if ($images) {
  if ($images->num_rows > 0) {
    while ($row = $images->fetch_assoc()) {
      try {
        $id = $row['id'];
        $zpid = $row['zpid'];
        $imgUrl = $row['url'];

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
        print_r($e);
      }
    }
  }
}



foreach (STATE_LIST as $key => $state) {
  foreach (BED_VALUES as $bed) {
    foreach (BATH_VALUES as $bath) {
      foreach (SQFT_VALUES as $sqft) {
        $stateAlias = strtolower($key);

        if ($sqft["min"] === 0) {
          unset($sqft["min"]);
        }

        if ($sqft["max"] === 0) {
          unset($sqft["max"]);
        }

        $filterState = array(
          "beds" => array(
            "min" => $bed
          ),
          "baths" => array(
            "min" => $bath
          ),
          "sqft" => $sqft,
          "pmf" => array(
            "value" => true
          ),
          "sort" => array(
            "value" => "globalrelevanceex"
          ),
          "nc" => array(
            "value" => false
          ),
          "fsbo" => array(
            "value" => false
          ),
          "cmsn" => array(
            "value" => false
          ),
          "pf" => array(
            "value" => true
          ),
          "fsba" => array(
            "value" => false
          )
        );

        $query = array(
          "pagination" => new stdClass(),
          "usersSearchTerm" => $key,
          "filterState" => $filterState,
          "isListVisible" => true
        );

        $queryString = json_encode($query);
        $searchQueryState = urlencode($queryString);
        $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
        echo $url;

        $driver->get($url);

        // $driver->get('https://api.scrapingdog.com/scrape?api_key=64e4c5478d07b1208ead57b8&url=https://www.zillow.com/in/foreclosures/&dynamic=false');

        try {
          $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
          $pattern = '/\d+/';

          preg_match('/\d+/', $totalCount, $matches);

          if (isset($matches[0])) {
            $totalCount = intval($matches[0]);
            $itemsPerPage = 41;


            $currentPage = 1;
            $maxPage = ceil($totalCount / $itemsPerPage);

            while ($currentPage <= $maxPage) {
              if ($currentPage !== 1) {
                $pagination = array(
                  "currentPage" => $currentPage,
                );
                $query["pagination"] = $pagination;
              }

              $queryString = json_encode($query);
              $searchQueryState = urlencode($queryString);
              $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
              $driver->get($pageUrl);
              sleep(5);

              $html = $driver->findElement(WebDriverBy::tagName('html'));
              $html->sendKeys(WebDriverKeys::END);
              sleep(5);

              $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
              if (count($propertyElements) > 0) {
                foreach ($propertyElements as $propertyElement) {
                  $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
                  $zpid = intval($zpid);

                  if ($zpid) {
                    $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

                    if ($exist->num_rows == 0) {
                      $url = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
                      $address = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a > address"))->getText();

                      try {
                        $beds = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(1) > b"))->getText();
                      } catch (NoSuchElementException $e) {
                        $beds = 0;
                      }

                      try {
                        $baths = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(2) > b"))->getText();
                      } catch (NoSuchElementException $e) {
                        $baths = 0;
                      }

                      $price = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data span.PropertyCardWrapper__StyledPriceLine-srp__sc-16e8gqd-1"))->getText();


                      $imgList = [];
                      $imgElements = $propertyElement->findElements(WebDriverBy::cssSelector("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0"));
                      foreach ($imgElements as $imgElement) {
                        $imgUrl = $imgElement->findElement(WebDriverBy::cssSelector("img.Image-c11n-8-84-3__sc-1rtmhsc-0"))->getAttribute("src");

                        $imgExist = $db->query("SELECT * FROM images WHERE zpid = $zpid AND url = '$imgUrl'");

                        if ($imgExist->num_rows == 0) {
                          $sql = "
                            INSERT INTO images
                            (
                              zpid, 
                              url,
                              createdAt
                            )
                            VALUES
                            (
                              '" . $db->makeSafe($zpid) . "',
                              '" . $db->makeSafe($imgUrl) . "',
                              '" . date('Y-m-d H:i:s') . "'
                            )";
                          $db->query($sql);
                        }

                        $imgList[] = $imgUrl;
                      }

                      $sql1 = "
                        INSERT INTO properties
                        (
                          zpid, 
                          address,
                          price,
                          beds,
                          baths,
                          images,
                          url,
                          createdAt
                        )
                        VALUES
                        (
                          '" . $db->makeSafe($zpid) . "',
                          '" . $db->makeSafe($address) . "',
                          '" . $db->makeSafe($price) . "',
                          '" . $db->makeSafe($beds) . "',
                          '" . $db->makeSafe($baths) . "',
                          '" . $db->makeSafe(json_encode($imgList)) . "',
                          '" . $db->makeSafe($url) . "',
                          '" . date('Y-m-d H:i:s') . "'
                        )";

                      $db->query($sql1);

                      $result[] = array(
                        "zpid" => $zpid,
                        "url" => $url,
                        "address" => $address,
                        "price" => $price,
                        "beds" => intval($beds),
                        "baths" => intval($baths),
                        "images" => $imgList,
                      );
                    }
                  }
                }
              }

              $currentPage++;
            }
          }
        } catch (NoSuchElementException $e) {
          print_r($e);
        }
      }
    }
  }
}

echo json_encode($result);
$driver->close();

// download images
$query = "SELECT * FROM images";
$images = $db->query("SELECT * FROM images");

if ($images) {
  if ($images->num_rows > 0) {
    while ($row = $images->fetch_assoc()) {
      try {
        $id = $row['id'];
        $zpid = $row['zpid'];
        $imgUrl = $row['url'];

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
        print_r($e);
      }
    }
  }
}

exit();
