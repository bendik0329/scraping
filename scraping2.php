<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
// require_once  __DIR__ . '/init.php';
require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
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

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);

// $startIndex = intval($argv[1]);

function scrape($db)
{
  global $host, $capabilities, $apiKey;
  $driver = RemoteWebDriver::create($host, $capabilities);
  $total = 0;
  // $result = array();

  foreach (STATE_LIST as $state) {
    foreach (LISTING_TYPE as $type) {
      foreach (SQFT_VALUES as $sqft) {
        foreach (CATEGORY as $category) {
          $stateAlias = strtolower($state);
          $sqftValue = $sqft;
          if ($sqftValue["min"] === 0) {
            unset($sqftValue["min"]);
          }
  
          if ($sqftValue["max"] === 0) {
            unset($sqftValue["max"]);
          }

          $filterState = array(
            "sqft" => $sqftValue,
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
            "fsba" => array(
              "value" => false
            ),
            "fore" => array(
              "value" => false
            ),
            "auc" => array(
              "value" => false
            ),
            "pmf" => array(
              "value" => false
            ),
            "pf" => array(
              "value" => false
            )
          );

          $filterState[$type]["value"] = true;

          $query = array(
            "pagination" => new stdClass(),
            "usersSearchTerm" => $state,
            "filterState" => $filterState,
            "category" => $category,
            "isListVisible" => true,
          );

          $queryString = json_encode($query);
          $searchQueryState = urlencode($queryString);

          $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

          $driver->get($url);

          try {
            $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
            $totalCount = str_replace(",", "", $totalCount);
            preg_match('/\d+/', $totalCount, $matches);

            if (isset($matches[0])) {
              $totalCount = intval($matches[0]);
            }
          } catch (NoSuchElementException $e) {
            $totalCount = 0;
          }

          $total += $totalCount;

          print_r("total count->>" . $totalCount);
          print_r("\n");

          // if ($totalCount > 0) {
          //   $itemsPerPage = 41;
          //   $currentPage = 1;
          //   $maxPage = ceil($totalCount / $itemsPerPage);

          //   print_r("max page->>" . $maxPage);
          //   print_r("\n");

          //   while ($currentPage <= $maxPage) {
          //     if ($currentPage != 1) {
          //       $pagination = array(
          //         "currentPage" => $currentPage,
          //       );
          //       $query["pagination"] = $pagination;

          //       $queryString = json_encode($query);
          //       $searchQueryState = urlencode($queryString);
          //       $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

          //       print_r($pageUrl);
          //       print_r("\n");

          //       $driver->get($pageUrl);
          //     }

          //     // $wait = new WebDriverWait($driver, 10);
          //     // $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector("footer.site-footer")));

          //     $list = array();

          //     $propertyElements = $driver->findElements(WebDriverBy::cssSelector("li.ListItem-c11n-8-84-3__sc-10e22w8-0.StyledListCardWrapper-srp__sc-wtsrtn-0.iCyebE.gTOWtl > div"));

          //     foreach ($propertyElements as $propertyElement) {
          //       $renderStatus = $propertyElement->getAttribute("data-renderstrat");
          //       if ($renderStatus) {
          //         $driver->executeScript('arguments[0].scrollIntoView(true);', array($propertyElement));

          //         $wait = new WebDriverWait($driver, 10);
          //         $wait->until(function () use ($propertyElement) {
          //           $attributeValue = $propertyElement->getAttribute('data-renderstrat');
          //           return $attributeValue !== 'timeout';
          //         });

          //         try {
          //           $element = $propertyElement->findElement(WebDriverBy::cssSelector("article.property-card"));
          //           $zpid = str_replace("zpid_", "", $element->getAttribute("id"));
          //           $zpid = intval($zpid);

          //           $list[] = $zpid;
          //           // if ($zpid) {
          //           //   $exists = $db->query("SELECT * FROM properties WHERE zpid=$zpid");
          //           //   if ($db->numrows($exists) === 0) {
          //           //     try {
          //           //       $link = $element->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
          //           //       $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $link;

          //           //       $detailHtml = retryCurlRequest($detailUrl);
          //           //       // sleep(2);

          //           //       if ($detailHtml && !($detailHtml instanceof \voku\helper\SimpleHtmlDomBlank)) {
          //           //         $result = scrapePropertyDetail($detailHtml);

          //           //         $sql = "
          //           //           INSERT INTO properties
          //           //           (
          //           //             zpid,
          //           //             url,
          //           //             image,
          //           //             price,
          //           //             address,
          //           //             city,
          //           //             state,
          //           //             zipcode,
          //           //             beds,
          //           //             baths,
          //           //             sqft,
          //           //             acres,
          //           //             type,
          //           //             zestimate,
          //           //             houseType,
          //           //             builtYear,
          //           //             heating,
          //           //             cooling,
          //           //             parking,
          //           //             lot,
          //           //             priceSqft,
          //           //             agencyFee,
          //           //             days,
          //           //             views,
          //           //             saves,
          //           //             special,
          //           //             overview,
          //           //             createdAt
          //           //           )
          //           //           VALUES
          //           //           (
          //           //             '" . $db->makeSafe($zpid) . "',
          //           //             '" . $db->makeSafe($link) . "',
          //           //             '" . $db->makeSafe($result["image"]) . "',
          //           //             '" . $db->makeSafe($result["price"]) . "',
          //           //             '" . $db->makeSafe($result["address"]) . "',
          //           //             '" . $db->makeSafe($result["city"]) . "',
          //           //             '" . $db->makeSafe($result["state"]) . "',
          //           //             '" . $db->makeSafe($result["zipcode"]) . "',
          //           //             '" . $db->makeSafe($result["beds"]) . "',
          //           //             '" . $db->makeSafe($result["baths"]) . "',
          //           //             '" . $db->makeSafe($result["sqft"]) . "',
          //           //             '" . $db->makeSafe($result["acres"]) . "',
          //           //             '" . $db->makeSafe($result["type"]) . "',
          //           //             '" . $db->makeSafe($result["zestimate"]) . "',
          //           //             '" . $db->makeSafe($result["houseType"]) . "',
          //           //             '" . $db->makeSafe($result["builtYear"]) . "',
          //           //             '" . $db->makeSafe($result["heating"]) . "',
          //           //             '" . $db->makeSafe($result["cooling"]) . "',
          //           //             '" . $db->makeSafe($result["parking"]) . "',
          //           //             '" . $db->makeSafe($result["lot"]) . "',
          //           //             '" . $db->makeSafe($result["priceSqft"]) . "',
          //           //             '" . $db->makeSafe($result["agencyFee"]) . "',
          //           //             '" . $db->makeSafe($result["days"]) . "',
          //           //             '" . $db->makeSafe($result["views"]) . "',
          //           //             '" . $db->makeSafe($result["saves"]) . "',
          //           //             '" . $db->makeSafe($result["special"]) . "',
          //           //             '" . $db->makeSafe($result["overview"]) . "',
          //           //             '" . date('Y-m-d H:i:s') . "'
          //           //           )";

          //           //         if (!$db->query($sql)) {
          //           //           echo "Error inserting properties table: \n";
          //           //           echo $sql . "\n";
          //           //         }
          //           //       }
          //           //     } catch (NoSuchElementException $e) {
          //           //     }
          //           //   }
          //           // }
          //         } catch (NoSuchElementException $e) {
          //         }
          //       }
          //     }

          //     print_r("current page->>" . $currentPage);;
          //     print_r("\n");
          //     print_r("\n");

          //     print_r($list);
          //     print_r("\n");
          //     print_r("\n");

          //     print_r("property counts->>" . count($list));
          //     print_r("\n");

          //     $currentPage++;
          //   }
          // }
        }
      }
    }
  }

  print_r("SUM->>" . $total);

  // array2csv($result);
  // echo json_encode($result);

  $driver->close();
}

function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\")
{
  $f = fopen(__DIR__ . "/result.csv", 'w');
  foreach ($data as $item) {
    fputcsv($f, $item, $delimiter, $enclosure, $escape_char);
  }
  fclose($f);
  return;
}

// Divide states into batches of 5
// $stateBatches = array_chunk(LISTING_TYPE, 1);

// Get the batch to scrape based on the startIndex
// $batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
// scrape($batchToScrape, $db);

scrape($db);
