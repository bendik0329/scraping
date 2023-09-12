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

$startIndex = intval($argv[1]);

function _main($batch, $db)
{
  global $host, $capabilities;
  $driver = RemoteWebDriver::create($host, $capabilities);
  $total = 0;

  foreach ($batch as $state) {
    foreach (LISTING_TYPE as $type) {
      foreach (CATEGORY as $category) {
        $pageUrl = getPageUrl($state, $type, $category);
        $count = getPropertyCount($driver, $pageUrl);

        if ($count > 0 && $count <= 820) {
          $total += $count;
          scrapeProperties($driver, $db, $count, $state, $type, $category);
        } elseif ($count > 820) {
          $start = 0;
          $end = 7500;
          $ranges = [[$start, $end]];

          while (!empty($ranges)) {
            $range = array_shift($ranges);
            $pageUrl = getPageUrl($state, $type, $category, $range);
            $count = getPropertyCount($driver, $pageUrl);

            if ($count > 0 && $count <= 820) {
              $total += $count;
              scrapeProperties($driver, $db, $count, $state, $type, $category, $range);
            } elseif ($count > 820) {
              $mid = $range[0] + floor(($range[1] - $range[0]) / 2);
              $ranges[] = [$range[0], $mid];
              $ranges[] = [$mid + 1, $range[1]];
            }
          }

          $start = 7501;
          $end = 0;
          $range = [$start, $end];

          $pageUrl = getPageUrl($state, $type, $category, $range);
          $count = getPropertyCount($driver, $pageUrl);
          $total += $count;

          scrapeProperties($driver, $db, $count, $state, $type, $category, $range);
        }
      }
    }
  }

  $driver->close();
}

function getPageUrl($state, $type, $category, $range = [0, 0], $currentPage = 0)
{
  global $apiKey;

  $stateAlias = strtolower($state);

  $filterState = array(
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

  $sqft = [
    "min" => $range[0],
    "max" => $range[1],
  ];

  if ($sqft["min"] === 0) {
    unset($sqft["min"]);
  }

  if ($sqft["max"] === 0) {
    unset($sqft["max"]);
  }

  if (!empty($sqft)) {
    $filterState["sqft"] = $sqft;
  }

  $filterState[$type]["value"] = true;

  $parameter = array(
    "pagination" => new stdClass(),
    "usersSearchTerm" => $state,
    "filterState" => $filterState,
    "category" => $category,
    "isListVisible" => true,
  );

  if ($currentPage > 1) {
    $pagination = array(
      "currentPage" => $currentPage,
    );

    $parameter["pagination"] = $pagination;
  }

  $searchQueryState = urlencode(json_encode($parameter));

  $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

  return $url;
}

function getPropertyCount($driver, $url)
{
  $driver->get($url);

  $count = 0;

  try {
    $count = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
    $count = str_replace(",", "", $count);
    preg_match('/\d+/', $count, $matches);

    if (isset($matches[0])) {
      $count = intval($matches[0]);
    }
  } catch (NoSuchElementException $e) {
    $count = 0;
  }

  return $count;
}

function scrapeProperties($driver, $db, $count, $state, $type, $category, $range = [0, 0])
{
  global $apiKey;
  $itemsPerPage = 41;
  $currentPage = 1;
  $maxPage = ceil($count / $itemsPerPage);

  while ($currentPage <= $maxPage) {
    if ($currentPage != 1) {
      $pageUrl = getPageUrl($state, $type, $category, $range, $currentPage);
      $driver->get($pageUrl);
    }

    $propertyElements = $driver->findElements(WebDriverBy::cssSelector("li.ListItem-c11n-8-84-3__sc-10e22w8-0.StyledListCardWrapper-srp__sc-wtsrtn-0.iCyebE.gTOWtl > div"));

    foreach ($propertyElements as $propertyElement) {
      $renderStatus = $propertyElement->getAttribute("data-renderstrat");
      if ($renderStatus) {
        $driver->executeScript('arguments[0].scrollIntoView(true);', array($propertyElement));
        $wait = new WebDriverWait($driver, 10);
        $wait->until(function () use ($propertyElement) {
          $attributeValue = $propertyElement->getAttribute('data-renderstrat');
          return $attributeValue !== 'timeout';
        });

        try {
          $element = $propertyElement->findElement(WebDriverBy::cssSelector("article.property-card"));
          $zpid = str_replace("zpid_", "", $element->getAttribute("id"));
          $zpid = intval($zpid);

          if ($zpid) {
            $exists = $db->query("SELECT * FROM properties WHERE zpid=$zpid");
            if ($db->numrows($exists) === 0) {
              try {
                $link = $element->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
                $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $link;

                $detailHtml = retryCurlRequest($detailUrl);

                if (!($detailHtml instanceof \voku\helper\SimpleHtmlDomBlank)) {
                  $result = scrapePropertyDetail($detailHtml);

                  print_r($result);
                  print_r("\n");

                  $sql = "
                    INSERT INTO properties
                    (
                      zpid,
                      url,
                      image,
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
                      zestimate,
                      houseType,
                      builtYear,
                      heating,
                      cooling,
                      parking,
                      lot,
                      priceSqft,
                      agencyFee,
                      days,
                      views,
                      saves,
                      overview,
                      createdAt
                    )
                    VALUES
                    (
                      '" . $db->makeSafe($zpid) . "',
                      '" . $db->makeSafe($link) . "',
                      '" . $db->makeSafe($result["image"]) . "',
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
                      '" . $db->makeSafe($result["zestimate"]) . "',
                      '" . $db->makeSafe($result["houseType"]) . "',
                      '" . $db->makeSafe($result["builtYear"]) . "',
                      '" . $db->makeSafe($result["heating"]) . "',
                      '" . $db->makeSafe($result["cooling"]) . "',
                      '" . $db->makeSafe($result["parking"]) . "',
                      '" . $db->makeSafe($result["lot"]) . "',
                      '" . $db->makeSafe($result["priceSqft"]) . "',
                      '" . $db->makeSafe($result["agencyFee"]) . "',
                      '" . $db->makeSafe($result["days"]) . "',
                      '" . $db->makeSafe($result["views"]) . "',
                      '" . $db->makeSafe($result["saves"]) . "',
                      '" . $db->makeSafe($result["overview"]) . "',
                      '" . date('Y-m-d H:i:s') . "'
                    )";

                  if (!$db->query($sql)) {
                    echo "Error inserting properties table: \n";
                    echo $sql . "\n";
                  }
                }
              } catch (NoSuchElementException $e) {
              }
            }
          }
        } catch (NoSuchElementException $e) {
        }
      }
    }

    $currentPage++;
  }
}

// Divide states into batches of 5
$stateBatches = array_chunk(STATE_LIST, 1);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
_main($batchToScrape, $db);