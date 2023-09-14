<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';

use voku\helper\HtmlDomParser;

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];
$tableName = $envConfig['DB_TABLE'];
$apiKey = $envConfig['API_KEY'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

$startIndex = intval($argv[1]);

function _main($batch, $db)
{
  $total = 0;
  foreach ($batch as $state) {
    foreach (LISTING_TYPE as $type) {
      foreach (CATEGORY as $category) {
        $pageUrl = getPageUrl($state, $type, $category);
        $html = sendCurlRequest($pageUrl);
        $htmlDomParser = HtmlDomParser::str_get_html($html);

        $count = getPropertyCount($htmlDomParser);

        if ($count > 0 && $count <= 820) {
          scrapeProperties($htmlDomParser, $db, $count, $state, $type, $category);
          $total += $count;
        } elseif ($count > 820) {
          $start = 0;
          $end = 7500;
          $ranges = [[$start, $end]];

          while (!empty($ranges)) {
            $range = array_shift($ranges);
            $pageUrl = getPageUrl($state, $type, $category, $range);
            $html = sendCurlRequest($pageUrl);
            $htmlDomParser = HtmlDomParser::str_get_html($html);

            $count = getPropertyCount($htmlDomParser);

            if ($count > 0 && $count <= 820) {
              scrapeProperties($htmlDomParser, $db, $count, $state, $type, $category);
              $total += $count;
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
          $html = sendCurlRequest($pageUrl);
          $htmlDomParser = HtmlDomParser::str_get_html($html);

          $count = getPropertyCount($htmlDomParser);
          scrapeProperties($htmlDomParser, $db, $count, $state, $type, $category);
          $total += $count;
        }
      }
    }
  }

  print_r("total count->>" . $total);
  print_r("\n");
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

function getPropertyCount($htmlDomParser)
{
  $count = 0;

  $countElement = $htmlDomParser->findOne("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count");
  if (!($countElement instanceof \voku\helper\SimpleHtmlDomBlank)) {
    $count = $countElement->text();
    $count = str_replace(",", "", $count);
    preg_match('/\d+/', $count, $matches);

    if (isset($matches[0])) {
      $count = intval($matches[0]);
    }
  } else {
    $count = 0;
  }

  return $count;
}

function scrapeProperties($htmlDomParser, $db, $count, $state, $type, $category, $range = [0, 0])
{
  global $apiKey, $tableName;
  $itemsPerPage = 41;
  $currentPage = 1;
  $maxPage = ceil($count / $itemsPerPage);

  while ($currentPage <= $maxPage) {
    if ($currentPage != 1) {
      $pageUrl = getPageUrl($state, $type, $category, $range, $currentPage);
      $html = sendCurlRequest($pageUrl);
      $htmlDomParser = HtmlDomParser::str_get_html($html);
    }

    $listElement = $htmlDomParser->findOne("script[id=__NEXT_DATA__]");
    if ($listElement !== '' || !($listElement instanceof \voku\helper\SimpleHtmlDomBlank)) {
      $jsonString = $listElement->text();
      $data = json_decode($jsonString, true);

      if (isset($data['props']['pageProps']['searchPageState']['cat1']['searchResults']['listResults'])) {
        $list = $data['props']['pageProps']['searchPageState']['cat1']['searchResults']['listResults'];

        if (is_array($list) && count($list) > 0) {
          foreach ($list as $item) {
            $sql = "
              INSERT INTO temp
              (
                zpid,
                detailUrl,
                imgSrc,
                createdAt
              )
              VALUES
              (
                '" . $db->makeSafe($item["zpid"]) . "',
                '" . $db->makeSafe($item["detailUrl"]) . "',
                '" . $db->makeSafe($item["imgSrc"]) . "',
                '" . date('Y-m-d H:i:s') . "'
              )";

            if (!$db->query($sql)) {
              echo "Error inserting temp table: \n";
              echo $sql . "\n";
            }
          }
        }
      }
    }

    $currentPage++;
  }
}

function sendCurlRequest($url)
{
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
  $response = curl_exec($curl);
  curl_close($curl);

  return $response;
}

function retryCurlRequest($url)
{
  $retryCount = 0;
  $maxRetries = 5;
  $html = '';

  while ($retryCount < $maxRetries) {
    $response = sendCurlRequest($url);

    try {
      $htmlDomParser = HtmlDomParser::str_get_html($response);
    } catch (Exception $e) {
      echo "Html Dom Parser Error at " . $url . "\n";
      break;
    }

    $html = $htmlDomParser->findOne("script[id=__NEXT_DATA__]");
    if ($html instanceof \voku\helper\SimpleHtmlDomBlank) {
      sleep(2);
      $retryCount++;
    } else {
      break;
    }
  }

  return $html;
}

// Divide states into batches of 5
$stateBatches = array_chunk(STATE_LIST, 2);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
_main($batchToScrape, $db);
