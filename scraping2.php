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

function scrape($db)
{
  global $host, $capabilities, $apiKey;
  // $driver = RemoteWebDriver::create($host, $capabilities);
  // $result = array();

  foreach (STATE_LIST as $state) {
    foreach (LISTING_TYPE as $type) {
      foreach (CATEGORY as $category) {
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

        echo "$url\n";
      }
    }
    // foreach (SQFT_VALUES as $sqft) {
    //   foreach (CAT_VALUES as $cat) {
    //     $stateAlias = strtolower($state);

    //     $sqftValue = $sqft;

    //     if ($sqftValue["min"] === 0) {
    //       unset($sqftValue["min"]);
    //     }

    //     if ($sqftValue["max"] === 0) {
    //       unset($sqftValue["max"]);
    //     }

    //     $filterState = array(
    //       "sqft" => $sqftValue,
    //       "pmf" => array(
    //         "value" => true
    //       ),
    //       "sort" => array(
    //         "value" => "globalrelevanceex"
    //       ),
    //       "isAllHomes" => array(
    //         "value" => True
    //       ),
    //       "nc" => array(
    //         "value" => false
    //       ),
    //       "fsbo" => array(
    //         "value" => false
    //       ),
    //       "cmsn" => array(
    //         "value" => false
    //       ),
    //       "pf" => array(
    //         "value" => true
    //       ),
    //       "fsba" => array(
    //         "value" => false
    //       )
    //     );

    //     $query = array(
    //       "pagination" => new stdClass(),
    //       "usersSearchTerm" => $state,
    //       "filterState" => $filterState,
    //       "category" => $cat,
    //       "isListVisible" => true,
    //     );

    //     $queryString = json_encode($query);
    //     $searchQueryState = urlencode($queryString);

    //     $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

    //     $driver->get($url);

    //     try {
    //       $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
    //       $totalCount = str_replace(",", "", $totalCount);
    //       preg_match('/\d+/', $totalCount, $matches);

    //       if (isset($matches[0])) {
    //         $totalCount = intval($matches[0]);
    //       }
    //     } catch (NoSuchElementException $e) {
    //       $totalCount = 0;
    //     }

    //     echo "total count->>$totalCount\n";

    //     $result[] = array(
    //       "url" => $url,
    //       "count" => $totalCount,
    //     );
    //   }
    // }
  }

  // array2csv($result);
  // echo json_encode($result);

  // $driver->close();
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

// Scrape and store the batch of states
scrape($db);
