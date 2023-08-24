<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/config.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/scraping.php';
require_once  __DIR__ . '/utils/database.php';

use voku\helper\HtmlDomParser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

$db  = new Database();

// Connect to DB
if (!$db->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);
$driver->get('https://api.scrapingdog.com/scrape?api_key=64e5b95985a16a20b0fdf02c&url=https://www.zillow.com/in/foreclosures/');

$result = [];
while (true) {
  $html = $driver->findElement(WebDriverBy::tagName('html'));
  $html->sendKeys(WebDriverKeys::END);
  sleep(5);

  $htmlContent = $driver->getPageSource();
  $htmlDomParser = HtmlDomParser::str_get_html($htmlContent);

  // scraping properties
  $propertyElements = $htmlDomParser->find("#grid-search-results > ul > li > div > div > article.property-card");
  
  if (count((array)$propertyElements) === 0) {
    break;
  }

  foreach ($propertyElements as $propertyElement) {
    $data = scrapeProperty($propertyElement);
    $sql = "
      INSERT INTO properties
      (
        zpid, 
        address,
        price,
        beds,
        baths,
        hasImage,
        images,
        url,
        createdAt
      )
      VALUES
      (
        '" . $db->makeSafe($data['zpid']) . "',
        '" . $db->makeSafe($data['address']) . "',
        '" . $db->makeSafe($data['price']) . "',
        '" . $db->makeSafe($data['beds']) . "',
        '" . $db->makeSafe($data['baths']) . "',
        '" . $db->makeSafe($data['hasImage'] ? 1 : 0) . "',
        '" . $db->makeSafe(json_encode($data['images'])) . "',
        '" . $db->makeSafe($data['url']) . "',
        '" . date('Y-m-d H:i:s') . "'
      )";

    $db->query($sql);
    $result[] = $data;
  }

  // pagination
  $paginationElements = $htmlDomParser->find("li.PaginationNumberItem-c11n-8-84-3__sc-bnmlxt-0.cA-Ddyj");
  if (count((array)$paginationElements) > 0) {
    $currentPageNum = $htmlDomParser->findOne("a[aria-pressed=\"true\"]")->text;
    $nextPageNum = intval($currentPageNum) + 1;

    $nextPageElement = $driver->findElement(WebDriverBy::cssSelector("a[title=\"Page " . strval($nextPageNum) . "\"]"));
    if (empty($nextPageElement)) {
      break;
    }
    $nextPageElement->click();
    sleep(5);
  } else {
    break;
  }
}

$result = json_encode($result);
print_r($result);

// Close the WebDriver session
$driver->quit();
