<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;
use voku\helper\HtmlDomParser;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverExpectedCondition;

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];
$apiKey = $envConfig['API_KEY'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// initialize
_init();

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);

$properties = [];
$total = 0;

foreach (STATE_LIST as $state) {
  foreach (BED_VALUES as $bed) {
    foreach (BATH_VALUES as $bath) {
      foreach (SQFT_VALUES as $sqft) {
        $stateAlias = strtolower($state);

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
          "usersSearchTerm" => $state,
          "filterState" => $filterState,
          "isListVisible" => true
        );

        $queryString = json_encode($query);
        $searchQueryState = urlencode($queryString);
        $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";
        echo $url . "\n";

        $driver->get($url);

        try {
          $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
          $totalCount = str_replace(",", "", $totalCount);
          preg_match('/\d+/', $totalCount, $matches);

          if (isset($matches[0])) {
            $totalCount = intval($matches[0]);
            $itemsPerPage = 41;
            $currentPage = 1;
            $maxPage = ceil($totalCount / $itemsPerPage);

            print_r($totalCount);
            print_r("\n");

            while ($currentPage <= $maxPage) {
              if ($currentPage !== 1) {
                $pagination = array(
                  "currentPage" => $currentPage,
                );
                $query["pagination"] = $pagination;
              }

              $queryString = json_encode($query);
              $searchQueryState = urlencode($queryString);
              $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

              $driver->get($pageUrl);
              
              // $html = $driver->findElement(WebDriverBy::tagName('html'));
              // $html->sendKeys(WebDriverKeys::END);

              $html = $driver->findElement(WebDriverBy::cssSelector("div.search-page-list-container.double-column-only.short-list-cards"));
              $html->sendKeys(WebDriverKeys::END);
              sleep(5);

              // $wait = new WebDriverWait($driver, 10);
              // $wait->until(WebDriverExpectedCondition::urlContains($url));

              $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
              $list = scrapeProperties($propertyElements);

              foreach ($list as $item) {
                if ($item["zpid"] && $item["link"]) {
                  $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $item["link"];

                  $curl = curl_init();
                  curl_setopt($curl, CURLOPT_URL, $detailUrl);
                  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                  curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
                  $html = curl_exec($curl);
                  curl_close($curl);

                  $htmlDomParser = HtmlDomParser::str_get_html($html);

                  $detailHtml = $htmlDomParser->findOne("div.detail-page");
                  $price = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span")->text();
                  print_r($price);
                  print_r("\n");
                  // $driver->get($detailUrl);
                  // sleep(2);

                  // $detailHtml = $driver->findElement(WebDriverBy::cssSelector("div.detail-page"));
                  // $result = scrapePropertyDetail($item["zpid"], $detailHtml);
                  // $result["zpid"] = $item["zpid"];
                  // $result["url"] = $item["link"];
                  // $result["images"] = $item["images"];

                  // // insert properties to table
                  // $sql = "
                  //     INSERT INTO properties
                  //     (
                  //       zpid,
                  //       url,
                  //       image,
                  //       currency,
                  //       price,
                  //       address,
                  //       city,
                  //       state,
                  //       zipcode,
                  //       beds,
                  //       baths,
                  //       sqft,
                  //       acres,
                  //       type,
                  //       zestimateCurrency,
                  //       zestimatePrice,
                  //       houseType,
                  //       builtYear,
                  //       heating,
                  //       cooling,
                  //       parking,
                  //       lot,
                  //       priceSqftCurrency,
                  //       priceSqft,
                  //       agencyFee,
                  //       days,
                  //       views,
                  //       saves,
                  //       special,
                  //       overview,
                  //       images,
                  //       createdAt
                  //     )
                  //     VALUES
                  //     (
                  //       '" . $db->makeSafe($result["zpid"]) . "',
                  //       '" . $db->makeSafe($result["url"]) . "',
                  //       '" . $db->makeSafe($result["image"]) . "',
                  //       '" . $db->makeSafe($result["currency"]) . "',
                  //       '" . $db->makeSafe($result["price"]) . "',
                  //       '" . $db->makeSafe($result["address"]) . "',
                  //       '" . $db->makeSafe($result["city"]) . "',
                  //       '" . $db->makeSafe($result["state"]) . "',
                  //       '" . $db->makeSafe($result["zipcode"]) . "',
                  //       '" . $db->makeSafe($result["beds"]) . "',
                  //       '" . $db->makeSafe($result["baths"]) . "',
                  //       '" . $db->makeSafe($result["sqft"]) . "',
                  //       '" . $db->makeSafe($result["acres"]) . "',
                  //       '" . $db->makeSafe($result["type"]) . "',
                  //       '" . $db->makeSafe($result["zestimateCurrency"]) . "',
                  //       '" . $db->makeSafe($result["zestimatePrice"]) . "',
                  //       '" . $db->makeSafe($result["houseType"]) . "',
                  //       '" . $db->makeSafe($result["builtYear"]) . "',
                  //       '" . $db->makeSafe($result["heating"]) . "',
                  //       '" . $db->makeSafe($result["cooling"]) . "',
                  //       '" . $db->makeSafe($result["parking"]) . "',
                  //       '" . $db->makeSafe($result["lot"]) . "',
                  //       '" . $db->makeSafe($result["priceSqftCurrency"]) . "',
                  //       '" . $db->makeSafe($result["priceSqft"]) . "',
                  //       '" . $db->makeSafe($result["agencyFee"]) . "',
                  //       '" . $db->makeSafe($result["days"]) . "',
                  //       '" . $db->makeSafe($result["views"]) . "',
                  //       '" . $db->makeSafe($result["saves"]) . "',
                  //       '" . $db->makeSafe($result["special"]) . "',
                  //       '" . $db->makeSafe($result["overview"]) . "',
                  //       '" . $db->makeSafe(json_encode($result["images"])) . "',
                  //       '" . date('Y-m-d H:i:s') . "'
                  //     )";

                  // if (!$db->query($sql)) {
                  //   echo "Error inserting properties table: \n";
                  //   echo $sql . "\n";
                  // }

                  // $properties[] = $result;
                  // $total++;
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

echo json_encode($properties);
echo "Total Count->>" . $total;

// close chrome driver
$driver->close();

// download images
downloadImages();
