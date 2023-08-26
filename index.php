<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriver;

$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];

$db  = new Database();

// Connect to DB
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

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
  "usersSearchTerm" => 'CA',
  "filterState" => $filterState,
  "isListVisible" => true
);

$queryString = json_encode($query);
$searchQueryState = urlencode($queryString);
$url = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";
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
      $pageUrl = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=$searchQueryState";
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
            $exist = $db->query("SELECT COUNT(*) AS count FROM properties WHERE zpid = $zpid");
            $existRow = $exist->fetch_assoc();

            if ($existRow['count'] == 0) {
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
              // $imgFolder = __DIR__ . '/download/images/' . $zpid;
              // if (!file_exists($imgFolder)) {
              //   mkdir($imgFolder, 0777, true);
              // }

              $imgElements = $propertyElement->findElements(WebDriverBy::cssSelector("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0"));
              foreach ($imgElements as $imgElement) {
                $imgUrl = $imgElement->findElement(WebDriverBy::cssSelector("img.Image-c11n-8-84-3__sc-1rtmhsc-0"))->getAttribute("src");
                // $imgPath = $imgFolder . "/" . basename($imgUrl);
                // $imgData = file_get_contents($imgUrl);
                // if ($imgData !== false && !file_exists($imgPath)) {
                //   file_put_contents($imgPath, $imgData);
                // }
                $imgExist = $db->query("SELECT COUNT(*) AS count FROM images WHERE zpid = $zpid AND url = $imgUrl");
                $imgExistRow = $exist->fetch_assoc();

                if ($imgExistRow['count'] == 0) {
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

echo json_encode($result);
$driver->close();

exit();


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
        $url = "https://api.scrapingdog.com/scrape?api_key=64e4c5478d07b1208ead57b8&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
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
              $pageUrl = "https://api.scrapingdog.com/scrape?api_key=64e4c5478d07b1208ead57b8&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
              $driver->get($pageUrl);

              // $driver->executeScript("window.location.href = '$pageUrl';");
              // $wait = new WebDriverWait($driver, 10); // Maximum wait time in seconds
              // $wait->until(WebDriverExpectedCondition::urlContains($pageUrl));
              sleep(5);

              $html = $driver->findElement(WebDriverBy::tagName('html'));
              $html->sendKeys(WebDriverKeys::END);
              sleep(5);
              // $wait->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::tagName('html')));

              $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
              if (count($propertyElements) > 0) {
                foreach ($propertyElements as $propertyElement) {
                  $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
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
                  // $imgFolder = __DIR__ . '/download/images/' . $zpid;
                  // if (!file_exists($imgFolder)) {
                  //   mkdir($imgFolder, 0777, true);
                  // }

                  $imgElements = $propertyElement->findElements(WebDriverBy::cssSelector("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0"));
                  foreach ($imgElements as $imgElement) {
                    $imgUrl = $imgElement->findElement(WebDriverBy::cssSelector("img.Image-c11n-8-84-3__sc-1rtmhsc-0"))->getAttribute("src");
                    // $imgPath = $imgFolder . "/" . basename($imgUrl);
                    // $imgData = file_get_contents($imgUrl);
                    // if ($imgData !== false && !file_exists($imgPath)) {
                    //   file_put_contents($imgPath, $imgData);
                    // }
                    $imgList[] = $imgUrl;
                  }

                  $sql = "
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

                  $db->query($sql);

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

              $currentPage++;
            }
          }
        } catch (NoSuchElementException $e) {
        }
      }
    }
  }
}

echo json_encode($result);
$driver->close();
exit();
