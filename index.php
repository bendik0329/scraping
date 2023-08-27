<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
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

$propertiesSql = "CREATE TABLE IF NOT EXISTS properties (
  `id` INT(6) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY,
  `zpid` INT(11) NOT NULL,
  `url` VARCHAR(255),
  `address` VARCHAR(255),
  `price` VARCHAR(255),
  `beds` VARCHAR(255),
  `baths` VARCHAR(255),
  `images` TEXT,
  'createdAt' TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";


$imagesSql = "CREATE TABLE IF NOT EXISTS images (
  `id` INT(6) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY,
  `zpid` INT(11) NOT NULL,
  `url` VARCHAR(255),
  'createdAt' TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($db->query($propertiesSql) === TRUE) {
  echo "Table properties created successfully";
} else {
  die("Error creating table: " . $conn->error);
}

if ($db->query($imagesSql) === TRUE) {
  echo "Table images created successfully";
} else {
  die("Error creating table: " . $conn->error);
}

// Create SQL query
$sql = "SHOW TABLES";

// Execute SQL query
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  // Output data of each row
  while($row = $result->fetch_assoc()) {
    echo $row[0]."<br>";
  }
} else {
  echo "0 results";
}

exit();
// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);

$result = [];

// $filterState = array(
//   "beds" => array(
//     "min" => 1
//   ),
//   "baths" => array(
//     "min" => 1
//   ),
//   "sqft" => array(
//     "min" => 500,
//     "max" => 750,
//   ),
//   "pmf" => array(
//     "value" => true
//   ),
//   "sort" => array(
//     "value" => "globalrelevanceex"
//   ),
//   "nc" => array(
//     "value" => false
//   ),
//   "fsbo" => array(
//     "value" => false
//   ),
//   "cmsn" => array(
//     "value" => false
//   ),
//   "pf" => array(
//     "value" => true
//   ),
//   "fsba" => array(
//     "value" => false
//   )
// );

// $query = array(
//   "pagination" => new stdClass(),
//   "usersSearchTerm" => 'CA',
//   "filterState" => $filterState,
//   "isListVisible" => true
// );

// $queryString = json_encode($query);
// $searchQueryState = urlencode($queryString);
// $url = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";
// $driver->get($url);

// try {
//   $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
//   $pattern = '/\d+/';

//   preg_match('/\d+/', $totalCount, $matches);

//   if (isset($matches[0])) {
//     $totalCount = intval($matches[0]);
//     $itemsPerPage = 41;
//     $currentPage = 1;
//     $maxPage = ceil($totalCount / $itemsPerPage);

//     while ($currentPage <= $maxPage) {
//       if ($currentPage !== 1) {
//         $pagination = array(
//           "currentPage" => $currentPage,
//         );
//         $query["pagination"] = $pagination;
//       }

//       $queryString = json_encode($query);
//       $searchQueryState = urlencode($queryString);
//       $pageUrl = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";
//       $driver->get($pageUrl);
//       sleep(5);

//       $html = $driver->findElement(WebDriverBy::tagName('html'));
//       $html->sendKeys(WebDriverKeys::END);
//       sleep(5);

//       $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
//       if (count($propertyElements) > 0) {
//         foreach ($propertyElements as $propertyElement) {
//           $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
//           $zpid = intval($zpid);

//           if ($zpid) {
//             $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

//             if ($exist->num_rows == 0) {
//               $url = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
//               $address = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a > address"))->getText();

//               try {
//                 $beds = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(1) > b"))->getText();
//               } catch (NoSuchElementException $e) {
//                 $beds = 0;
//               }

//               try {
//                 $baths = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(2) > b"))->getText();
//               } catch (NoSuchElementException $e) {
//                 $baths = 0;
//               }

//               $price = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data span.PropertyCardWrapper__StyledPriceLine-srp__sc-16e8gqd-1"))->getText();


//               $imgList = [];
//               $imgElements = $propertyElement->findElements(WebDriverBy::cssSelector("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0"));
//               foreach ($imgElements as $imgElement) {
//                 $imgUrl = $imgElement->findElement(WebDriverBy::cssSelector("img.Image-c11n-8-84-3__sc-1rtmhsc-0"))->getAttribute("src");

//                 $imgExist = $db->query("SELECT * FROM images WHERE zpid = $zpid AND url = '$imgUrl'");

//                 if ($imgExist->num_rows == 0) {
//                   $sql = "
//                     INSERT INTO images
//                     (
//                       zpid, 
//                       url,
//                       createdAt
//                     )
//                     VALUES
//                     (
//                       '" . $db->makeSafe($zpid) . "',
//                       '" . $db->makeSafe($imgUrl) . "',
//                       '" . date('Y-m-d H:i:s') . "'
//                     )";
//                   $db->query($sql);
//                 }

//                 $imgList[] = $imgUrl;
//               }

//               $sql1 = "
//                 INSERT INTO properties
//                 (
//                   zpid, 
//                   address,
//                   price,
//                   beds,
//                   baths,
//                   images,
//                   url,
//                   createdAt
//                 )
//                 VALUES
//                 (
//                   '" . $db->makeSafe($zpid) . "',
//                   '" . $db->makeSafe($address) . "',
//                   '" . $db->makeSafe($price) . "',
//                   '" . $db->makeSafe($beds) . "',
//                   '" . $db->makeSafe($baths) . "',
//                   '" . $db->makeSafe(json_encode($imgList)) . "',
//                   '" . $db->makeSafe($url) . "',
//                   '" . date('Y-m-d H:i:s') . "'
//                 )";

//               $db->query($sql1);

//               $result[] = array(
//                 "zpid" => $zpid,
//                 "url" => $url,
//                 "address" => $address,
//                 "price" => $price,
//                 "beds" => intval($beds),
//                 "baths" => intval($baths),
//                 "images" => $imgList,
//               );
//             }
//           }
//         }
//       }

//       $currentPage++;
//     }
//   }
// } catch (NoSuchElementException $e) {
//   print_r($e);
// }

// echo json_encode($result);
// $driver->close();

// // download images
// $query = "SELECT * FROM images";
// $images = $db->query("SELECT * FROM images");

// if ($images) {
//   if ($images->num_rows > 0) {
//     while ($row = $images->fetch_assoc()) {
//       $id = $row['id'];
//       $zpid = $row['zpid'];
//       $imgUrl = $row['url'];

//       $imgFolder = __DIR__ . '/download/images/' . $zpid;
//       if (!file_exists($imgFolder)) {
//         mkdir($imgFolder, 0777, true);
//       }

//       $imgPath = $imgFolder . "/" . basename($imgUrl);
//       if (!file_exists($imgPath)) {
//         $imgData = file_get_contents($imgUrl);
//         if ($imgData !== false) {
//           file_put_contents($imgPath, $imgData);
//         }
//       }
//     }
//   }
// }

// exit();


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
    }
  }
}

exit();
