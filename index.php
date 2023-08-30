<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/init.php';
// require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;

$states = STATE_LIST;
$startIndex = intval($argv[1]); // Get the startIndex from the command line argument

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");
$apiKey = $envConfig['API_KEY'];

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);

function scrape($batch, $db)
{
  foreach ($batch as $state) {
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
          $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
          echo $url . "\n";

          $driver->get($url);
          sleep(5);
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
                $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState";
                $driver->get($pageUrl);

                $html = $driver->findElement(WebDriverBy::tagName('html'));
                $html->sendKeys(WebDriverKeys::END);
                sleep(5);

                $propertyElements = $driver->findElements(WebDriverBy::cssSelector("#grid-search-results > ul > li > div > div > article.property-card"));
                $list = scrapeProperties($propertyElements);

                foreach ($list as $item) {
                  if ($item["zpid"] && $item["link"]) {
                    $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $item["link"];
                    $driver->get($detailUrl);
                    sleep(5);

                    $detailHtml = $driver->findElement(WebDriverBy::cssSelector("div.detail-page"));
                    $result = scrapePropertyDetail($item["zpid"], $detailHtml);
                    $result["zpid"] = $item["zpid"];
                    $result["url"] = $item["link"];

                    // insert properties to table
                    $sql = "
                      INSERT INTO properties
                      (
                        zpid,
                        url,
                        image,
                        currency,
                        price,
                        address,
                        city,
                        state,
                        zipcode,
                        beds,
                        baths,
                        sqft,
                        acres,
                        type,
                        zestimateCurrency,
                        zestimatePrice,
                        houseType,
                        builtYear,
                        heating,
                        cooling,
                        parking,
                        lot,
                        lotUnit,
                        priceSqftCurrency,
                        priceSqft,
                        agencyFee,
                        days,
                        views,
                        saves,
                        special,
                        overview,
                        createdAt
                      )
                      VALUES
                      (
                        '" . $db->makeSafe($result["zpid"]) . "',
                        '" . $db->makeSafe($result["url"]) . "',
                        '" . $db->makeSafe($result["image"]) . "',
                        '" . $db->makeSafe($result["currency"]) . "',
                        '" . $db->makeSafe($result["price"]) . "',
                        '" . $db->makeSafe($result["address"]) . "',
                        '" . $db->makeSafe($result["city"]) . "',
                        '" . $db->makeSafe($result["state"]) . "',
                        '" . $db->makeSafe($result["zipcode"]) . "',
                        '" . $db->makeSafe($result["beds"]) . "',
                        '" . $db->makeSafe($result["baths"]) . "',
                        '" . $db->makeSafe($result["sqft"]) . "',
                        '" . $db->makeSafe($result["acres"]) . "',
                        '" . $db->makeSafe($result["type"]) . "',
                        '" . $db->makeSafe($result["zestimateCurrency"]) . "',
                        '" . $db->makeSafe($result["zestimatePrice"]) . "',
                        '" . $db->makeSafe($result["houseType"]) . "',
                        '" . $db->makeSafe($result["builtYear"]) . "',
                        '" . $db->makeSafe($result["heating"]) . "',
                        '" . $db->makeSafe($result["cooling"]) . "',
                        '" . $db->makeSafe($result["parking"]) . "',
                        '" . $db->makeSafe($result["lot"]) . "',
                        '" . $db->makeSafe($result["lotUnit"]) . "',
                        '" . $db->makeSafe($result["priceSqftCurrency"]) . "',
                        '" . $db->makeSafe($result["priceSqft"]) . "',
                        '" . $db->makeSafe($result["agencyFee"]) . "',
                        '" . $db->makeSafe($result["days"]) . "',
                        '" . $db->makeSafe($result["views"]) . "',
                        '" . $db->makeSafe($result["saves"]) . "',
                        '" . $db->makeSafe($result["special"]) . "',
                        '" . $db->makeSafe($result["overview"]) . "',
                        '" . date('Y-m-d H:i:s') . "'
                      )";

                    if (!$db->query($sql)) {
                      echo "Error inserting properties table: \n";
                      echo $sql . "\n";
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

  // close chrome driver
  $driver->close();

  // download images
  downloadImages();
}

// Divide states into batches of 5
$stateBatches = array_chunk($states, 5);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
scrape($batchToScrape, $db);
