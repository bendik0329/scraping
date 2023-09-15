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

  $homeStatus = HOME_STATUS;

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
      // $jsonData = json_encode($data, JSON_PRETTY_PRINT);
      // $filePath = 'file.json';
      // file_put_contents($filePath, $jsonData);

      // exit();

      if (isset($data['props']['pageProps']['searchPageState'][$category]['searchResults']['listResults'])) {
        $list = $data['props']['pageProps']['searchPageState'][$category]['searchResults']['listResults'];

        if (is_array($list) && count($list) > 0) {
          foreach ($list as $item) {
            $zpid = isset($item["zpid"]) ? $item["zpid"] : "";
            if ($zpid !== "") {
              $exists = $db->query("SELECT * FROM $tableName WHERE zpid=$zpid");
              if ($db->numrows($exists) === 0) {
                $result = array(
                  "zpid" => $zpid,
                  "url" => isset($item["detailUrl"]) ? $item["detailUrl"] : "",
                  "image" => isset($item["imgSrc"]) ? $item["imgSrc"] : "",
                  "homeStatus" => $homeStatus[$type],
                  "street" => isset($item["hdpData"]["homeInfo"]["streetAddress"]) ? $item["hdpData"]["homeInfo"]["streetAddress"] : "",
                  "city" => isset($item["hdpData"]["homeInfo"]["city"]) ? $item["hdpData"]["homeInfo"]["city"] : "",
                  "state" => isset($item["hdpData"]["homeInfo"]["state"]) ? $item["hdpData"]["homeInfo"]["state"] : "",
                  "zipcode" => isset($item["hdpData"]["homeInfo"]["zipcode"]) ? $item["hdpData"]["homeInfo"]["zipcode"] : "",
                  "country" => isset($item["hdpData"]["homeInfo"]["country"]) ? $item["hdpData"]["homeInfo"]["country"] : "",
                  "latitude" => isset($item["hdpData"]["homeInfo"]["latitude"]) ? $item["hdpData"]["homeInfo"]["latitude"] : "",
                  "longitude" => isset($item["hdpData"]["homeInfo"]["longitude"]) ? $item["hdpData"]["homeInfo"]["longitude"] : "",
                  "currency" => isset($item["hdpData"]["homeInfo"]["currency"]) ? $item["hdpData"]["homeInfo"]["currency"] : "",
                  "price" => isset($item["hdpData"]["homeInfo"]["price"]) ? $item["hdpData"]["homeInfo"]["price"] : 0,
                  "zestimate" => isset($item["hdpData"]["homeInfo"]["zestimate"]) ? $item["hdpData"]["homeInfo"]["zestimate"] : 0,
                  "rentZestimate" => isset($item["hdpData"]["homeInfo"]["rentZestimate"]) ? $item["hdpData"]["homeInfo"]["rentZestimate"] : 0,
                  "bedrooms" => isset($item["hdpData"]["homeInfo"]["bedrooms"]) ? $item["hdpData"]["homeInfo"]["bedrooms"] : 0,
                  "bathrooms" => isset($item["hdpData"]["homeInfo"]["bathrooms"]) ? $item["hdpData"]["homeInfo"]["bathrooms"] : 0,
                  "livingArea" => isset($item["hdpData"]["homeInfo"]["livingArea"]) ? $item["hdpData"]["homeInfo"]["livingArea"] : 0,
                  "lotAreaUnit" => isset($item["hdpData"]["homeInfo"]["lotAreaUnit"]) ? $item["hdpData"]["homeInfo"]["lotAreaUnit"] : "",
                  "lotAreaValue" => isset($item["hdpData"]["homeInfo"]["lotAreaValue"]) ? $item["hdpData"]["homeInfo"]["lotAreaValue"] : 0,
                  "homeType" => isset($item["hdpData"]["homeInfo"]["homeType"]) ? $item["hdpData"]["homeInfo"]["homeType"] : "",
                  "daysOnZillow" => isset($item["hdpData"]["homeInfo"]["daysOnZillow"]) ? $item["hdpData"]["homeInfo"]["daysOnZillow"] : 0,
                  "brokerName" => isset($item["hdpData"]["homeInfo"]["brokerName"]) ? $item["hdpData"]["homeInfo"]["brokerName"] : "",
                );

                $sql = "
                  INSERT INTO $tableName
                  (
                    zpid,
                    url,
                    image,
                    homeStatus,
                    street,
                    city,
                    state,
                    zipcode,
                    country,
                    latitude,
                    longitude,
                    currency,
                    price,
                    zestimate,
                    rentZestimate,
                    bedrooms,
                    bathrooms,
                    livingArea,
                    lotAreaUnit,
                    lotAreaValue,
                    homeType,
                    daysOnZillow,
                    brokerName,
                    createdAt
                  )
                  VALUES
                  (
                    '" . $db->makeSafe($result["zpid"]) . "',
                    '" . $db->makeSafe($result["url"]) . "',
                    '" . $db->makeSafe($result["image"]) . "',
                    '" . $db->makeSafe($result["homeStatus"]) . "',
                    '" . $db->makeSafe($result["street"]) . "',
                    '" . $db->makeSafe($result["city"]) . "',
                    '" . $db->makeSafe($result["state"]) . "',
                    '" . $db->makeSafe($result["zipcode"]) . "',
                    '" . $db->makeSafe($result["country"]) . "',
                    '" . $db->makeSafe($result["latitude"]) . "',
                    '" . $db->makeSafe($result["longitude"]) . "',
                    '" . $db->makeSafe($result["currency"]) . "',
                    '" . $db->makeSafe($result["price"]) . "',
                    '" . $db->makeSafe($result["zestimate"]) . "',
                    '" . $db->makeSafe($result["rentZestimate"]) . "',
                    '" . $db->makeSafe($result["bedrooms"]) . "',
                    '" . $db->makeSafe($result["bathrooms"]) . "',
                    '" . $db->makeSafe($result["livingArea"]) . "',
                    '" . $db->makeSafe($result["lotAreaUnit"]) . "',
                    '" . $db->makeSafe($result["lotAreaValue"]) . "',
                    '" . $db->makeSafe($result["homeType"]) . "',
                    '" . $db->makeSafe($result["daysOnZillow"]) . "',
                    '" . $db->makeSafe($result["brokerName"]) . "',
                    '" . date('Y-m-d H:i:s') . "'
                  )";

                if (!$db->query($sql)) {
                  echo "Error inserting $tableName table: \n";
                  echo $sql . "\n";
                }
              }
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
$stateBatches = array_chunk(STATE_LIST, 1);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
_main($batchToScrape, $db);
