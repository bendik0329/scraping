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
$url = "https://www.zillow.com/in/foreclosures";
$driver->get('https://api.scrapingdog.com/scrape?api_key=64e4c5478d07b1208ead57b8&url=' . $url . "&dynamic=false");

$totalCount = $driver->findElement(WebDriverBy::cssSelector(".search-subtitle span.result-count"))->getText();
$pattern = '/\d+/';

preg_match('/\d+/', $totalCount, $matches);

if (isset($matches[0])) {
  $totalCount = intval($matches[0]);
  $itemsPerPage = 41;
  $currentPage = 1;
  $maxPage = ceil($totalCount / $itemsPerPage);

  var_dump($totalCount);
  var_dump($maxPage);

  while ($currentPage <= $maxPage) {
    $pageUrl = "https://api.scrapingdog.com/scrape?api_key=64e4c5478d07b1208ead57b8&url=" . $url . "/" . strval($currentPage) . "_p/" . "&dynamic=false";
    $driver->executeScript("window.location.href = '$pageUrl';");
    $wait = new WebDriverWait($driver, 10); // Maximum wait time in seconds
    $wait->until(WebDriverExpectedCondition::urlContains($pageUrl));

    $html = $driver->findElement(WebDriverBy::tagName('html'));
    $html->sendKeys(WebDriverKeys::END);
    $wait->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::tagName('html')));

    $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
    if (count($propertyElements) > 0) {
      foreach ($propertyElements as $propertyElement) {
        $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
        $url = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
        $address = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a > address"))->getText();
        $price = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data span.PropertyCardWrapper__StyledPriceLine-srp__sc-16e8gqd-1"))->getText();
        $beds = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(1) > b"))->getText();
        $baths = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(2) > b"))->getText();

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

        $result[] = array(
          "zpid" => $zpid,
          "url" => $url,
          "address" => $address,
          "price" => $price,
          "beds" => $beds,
          "baths" => $baths,
          "images" => $imgList,
        );
      }
    }

    $currentPage++;
  }
}

echo json_encode($result);
$driver->close();
exit();
