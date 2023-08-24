<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/scraping.php';
require_once  __DIR__ . '/utils/database.php';

use voku\helper\HtmlDomParser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

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
$driver->get('https://api.scrapingdog.com/scrape?api_key=64e5b95985a16a20b0fdf02c&url=https://www.zillow.com/in/foreclosures/');

$html = $driver->findElement(WebDriverBy::tagName('html'));
$html->sendKeys(WebDriverKeys::END);
sleep(5);

$htmlContent = $driver->getPageSource();
$htmlDomParser = HtmlDomParser::str_get_html($htmlContent);

$currentPageNum = $htmlDomParser->findOne("a[aria-pressed=\"true\"]")->text;
$nextPageNum = intval($currentPageNum) + 1;

$nextPageLink = $driver->findElement(WebDriverBy::cssSelector("a[title=\"Page " . strval($nextPageNum) . "\"]"));

print_r("current page->" . $currentPageNum);
print_r("\n");
print_r("next page->" . $nextPageNum);
print_r("\n");
print_r($nextPageLink);
print_r("\n");
exit();

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

    print_r("current page->", $currentPageNum);
    print_r("next page->", $nextPageNum);
    print_r("\n");
    $attributeValue = 'Page 2';
    $nextPageLink = $driver->findElement(WebDriverBy::xpath("//a[@title='$attributeValue']"));

    print_r($nextPageLink);
    print_r("\n");

    // $nextPageLink = $driver->findElement(WebDriverBy::cssSelector("a[title=\"Page " . strval($nextPageNum) . "\"]"));
    if (empty($nextPageLink)) {
      break;
    }
    $nextPageElement = $nextPageLink->findElement(WebDriverBy::xpath('..'));

    print_r($nextPageElement);
    print_r("\n");

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
