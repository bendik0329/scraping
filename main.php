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
$batchCount = $envConfig['BATCH_COUNT'];

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
        $html = getHtmlElement($pageUrl, "div.search-page-container");
        $count = getPropertyCount($html);

        if ($count > 0 && $count <= 820) {
          scrapeProperties($db, $count, $state, $type, $category);
          $total += $count;
        } elseif ($count > 820) {
          $start = 0;
          $end = 7500;
          $ranges = [[$start, $end]];

          while (!empty($ranges)) {
            $range = array_shift($ranges);
            $pageUrl = getPageUrl($state, $type, $category, $range);
            $html = getHtmlElement($pageUrl, "div.search-page-container");
            $count = getPropertyCount($html);

            if ($count > 0 && $count <= 820) {
              scrapeProperties($db, $count, $state, $type, $category, $range);
              $total += $count;
            } elseif ($count > 820) {
              $mid = $range[0] + floor(($range[1] - $range[0]) / 2);
              $ranges[] = [$range[0], $mid];
              $ranges[] = [$mid + 1, $range[1]];
            }
          }

          $range = [7501, 0];
          $pageUrl = getPageUrl($state, $type, $category, $range);
          $html = getHtmlElement($pageUrl, "div.search-page-container");
          $count = getPropertyCount($html);

          if ($count > 0 && $count <= 820) {
            scrapeProperties($db, $count, $state, $type, $category, $range);
            $total += $count;
          } elseif ($count > 820) {
            $start = 7501;
            $mid = $start + 2500;
            $end = 0;
            $ranges = [[$start, $mid], [$mid + 1, $end]];

            while (!empty($ranges)) {
              $range = array_shift($ranges);
              $pageUrl = getPageUrl($state, $type, $category, $range);
              $html = getHtmlElement($pageUrl, "div.search-page-container");
              $count = getPropertyCount($html);

              if ($count > 0 && $count <= 820) {
                scrapeProperties($db, $count, $state, $type, $category, $range);
                $total += $count;
              } elseif ($count > 820) {
                if ($range[1] === 0) {
                  $mid = $range[0] + 2500;
                } else {
                  $mid = $range[0] + floor(($range[1] - $range[0]) / 2);
                }

                $ranges[] = [$range[0], $mid];
                $ranges[] = [$mid + 1, $range[1]];
              }
            }
          }
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

function getPropertyCount($html)
{
  $count = 0;

  $countElement = $html->findOne("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count");
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

function scrapeProperties($db, $count, $state, $type, $category, $range = [0, 0])
{
  global $apiKey, $tableName;
  $itemsPerPage = 41;
  $currentPage = 1;
  $maxPage = ceil($count / $itemsPerPage);
  $homeStatus = HOME_STATUS;

  while ($currentPage <= $maxPage) {
    $pageUrl = getPageUrl($state, $type, $category, $range, $currentPage);
    $html = getHtmlElement($pageUrl, "script[id=__NEXT_DATA__]");

    if ($html !== '' || !($html instanceof \voku\helper\SimpleHtmlDomBlank)) {
      $jsonString = $html->text();
      $data = json_decode($jsonString, true);
      // $jsonData = json_encode($data, JSON_PRETTY_PRINT);
      // $filePath = "file-$currentPage.json";
      // file_put_contents($filePath, $jsonData);

      // exit();

      if (isset($data['props']['pageProps']['searchPageState'][$category]['searchResults']['listResults'])) {
        $list = $data['props']['pageProps']['searchPageState'][$category]['searchResults']['listResults'];

        if (is_array($list) && count($list) > 0) {
          foreach ($list as $item) {
            $zpid = isset($item["zpid"]) ? $item["zpid"] : "";
            $link = isset($item["detailUrl"]) ? $item["detailUrl"] : "";
            
            if ($zpid !== "" || $link !== "") {
              $exists = $db->query("SELECT * FROM $tableName WHERE zpid=$zpid");
              if ($db->numrows($exists) === 0) {
                $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $link;
                $dataElement = getHtmlElement($detailUrl, "script[id=__NEXT_DATA__]");

                if ($dataElement !== '' || !($dataElement instanceof \voku\helper\SimpleHtmlDomBlank)) {
                  $jsonString = $dataElement->text();
                  $data = json_decode($jsonString, true);

                  if (isset($data['props']['pageProps']['gdpClientCache'])) {
                    $gdpClientCache = json_decode($data['props']['pageProps']['gdpClientCache'], true);

                    if (is_array($gdpClientCache) || is_object($gdpClientCache)) {
                      $property = array();
                      foreach ($gdpClientCache as $value) {
                        $property = $value['property'];
                      }

                      $result = array(
                        "image" => isset($property["desktopWebHdpImageLink"]) ? $property["desktopWebHdpImageLink"] : "",
                        "streetAddress" => isset($property["address"]["streetAddress"]) ? $property["address"]["streetAddress"] : "",
                        "city" => isset($property["address"]["city"]) ? $property["address"]["city"] : "",
                        "state" => isset($property["address"]["state"]) ? $property["address"]["state"] : "",
                        "zipcode" => isset($property["address"]["zipcode"]) ? $property["address"]["zipcode"] : "",
                        "latitude" => isset($property["latitude"]) ? $property["latitude"] : "",
                        "longitude" => isset($property["longitude"]) ? $property["longitude"] : "",
                        "country" => isset($property["country"]) ? $property["country"] : "",
                        "bedrooms" => isset($property["bedrooms"]) ? $property["bedrooms"] : 0,
                        "bathrooms" => isset($property["bathrooms"]) ? $property["bathrooms"] : 0,
                        "livingAreaUnits" => isset($property["livingAreaUnits"]) ? $property["livingAreaUnits"] : "",
                        "livingAreaValue" => isset($property["livingAreaValue"]) ? $property["livingAreaValue"] : 0,
                        "lotAreaUnits" => isset($property["lotAreaUnits"]) ? $property["lotAreaUnits"] : "",
                        "lotAreaValue" => isset($property["lotAreaValue"]) ? $property["lotAreaValue"] : 0,
                        "currency" => isset($property["currency"]) ? $property["currency"] : "",
                        "price" => isset($property["price"]) ? $property["price"] : 0,
                        "zestimate" => isset($property["zestimate"]) ? $property["zestimate"] : 0,
                        "rentZestimate" => isset($property["rentZestimate"]) ? $property["rentZestimate"] : 0,
                        "homeType" => isset($property["homeType"]) ? $property["homeType"] : "",
                        "yearBuilt" => isset($property["yearBuilt"]) ? $property["yearBuilt"] : 0,

                        "hasHeating" => isset($property["resoFacts"]["hasHeating"]) ? $property["resoFacts"]["hasHeating"] : false,
                        "heating" => isset($property["resoFacts"]["hasHeating"]) && $property["resoFacts"]["hasHeating"] ? $property["resoFacts"]["heating"] : "",

                        "hasCooling" => isset($property["resoFacts"]["hasCooling"]) ? $property["resoFacts"]["hasCooling"] : false,
                        "cooling" => isset($property["resoFacts"]["hasCooling"]) && $property["resoFacts"]["hasCooling"] ? $property["resoFacts"]["cooling"] : "",

                        "hasGarage" => isset($property["resoFacts"]["hasGarage"]) ? $property["resoFacts"]["hasGarage"] : false,
                        "hasAttachedGarage" => isset($property["resoFacts"]["hasAttachedGarage"]) ? $property["resoFacts"]["hasAttachedGarage"] : false,
                        "parkingCapacity" => isset($property["resoFacts"]["parkingCapacity"]) ? $property["resoFacts"]["parkingCapacity"] : 0,
                        "garageParkingCapacity" => isset($property["resoFacts"]["garageParkingCapacity"]) ? $property["resoFacts"]["garageParkingCapacity"] : 0,

                        "pricePerSquareFoot" => isset($property["resoFacts"]["pricePerSquareFoot"]) ? $property["resoFacts"]["pricePerSquareFoot"] : 0,
                        "buyerAgencyCompensation" => isset($property["resoFacts"]["buyerAgencyCompensation"]) ? $property["resoFacts"]["buyerAgencyCompensation"] : 0,
                        "pageViewCount" => isset($property["pageViewCount"]) ? $property["pageViewCount"] : 0,
                        "favoriteCount" => isset($property["favoriteCount"]) ? $property["favoriteCount"] : 0,
                        "daysOnZillow" => isset($property["daysOnZillow"]) ? $property["daysOnZillow"] : 0,
                        "agentName" => isset($property["attributionInfo"]["agentName"]) ? $property["attributionInfo"]["agentName"] : "",
                        "agentPhoneNumber" => isset($property["attributionInfo"]["agentPhoneNumber"]) ? $property["attributionInfo"]["agentPhoneNumber"] : "",
                        "brokerName" => isset($property["attributionInfo"]["brokerName"]) ? $property["attributionInfo"]["brokerName"] : "",
                        "brokerPhoneNumber" => isset($property["attributionInfo"]["brokerPhoneNumber"]) ? $property["attributionInfo"]["brokerPhoneNumber"] : "",
                        "coAgentName" => isset($property["attributionInfo"]["coAgentName"]) ? $property["attributionInfo"]["coAgentName"] : "",
                        "coAgentNumber" => isset($property["attributionInfo"]["coAgentNumber"]) ? $property["attributionInfo"]["coAgentNumber"] : "",
                        "buyerAgentName" => isset($property["attributionInfo"]["buyerAgentName"]) ? $property["attributionInfo"]["buyerAgentName"] : "",
                        "description" => isset($property["description"]) ? $property["description"] : "",
                      );

                      $sql = "
                        INSERT INTO $tableName
                        (
                          zpid,
                          url,
                          image,
                          streetAddress,
                          city,
                          state,
                          zipcode,
                          latitude,
                          longitude,
                          country,
                          bedrooms,
                          bathrooms,
                          livingAreaUnits,
                          livingAreaValue,
                          lotAreaUnits,
                          lotAreaValue,
                          currency,
                          price,
                          zestimate,
                          rentZestimate,
                          homeType,
                          homeStatus,
                          yearBuilt,
                          hasHeating,
                          heating,
                          hasCooling,
                          cooling,
                          hasGarage,
                          hasAttachedGarage,
                          parkingCapacity,
                          garageParkingCapacity,
                          pricePerSquareFoot,
                          buyerAgencyCompensation,
                          pageViewCount,
                          favoriteCount,
                          daysOnZillow,
                          agentName,
                          agentPhoneNumber,
                          brokerName,
                          brokerPhoneNumber,
                          coAgentName,
                          coAgentNumber,
                          buyerAgentName,
                          description,
                          createdAt
                        )
                        VALUES
                        (
                          '" . $db->makeSafe($zpid) . "',
                          '" . $db->makeSafe($link) . "',
                          '" . $db->makeSafe($result["image"]) . "',
                          '" . $db->makeSafe($result["streetAddress"]) . "',
                          '" . $db->makeSafe($result["city"]) . "',
                          '" . $db->makeSafe($result["state"]) . "',
                          '" . $db->makeSafe($result["zipcode"]) . "',
                          '" . $db->makeSafe($result["latitude"]) . "',
                          '" . $db->makeSafe($result["longitude"]) . "',
                          '" . $db->makeSafe($result["country"]) . "',
                          '" . $db->makeSafe($result["bedrooms"]) . "',
                          '" . $db->makeSafe($result["bathrooms"]) . "',
                          '" . $db->makeSafe($result["livingAreaUnits"]) . "',
                          '" . $db->makeSafe($result["livingAreaValue"]) . "',
                          '" . $db->makeSafe($result["lotAreaUnits"]) . "',
                          '" . $db->makeSafe($result["lotAreaValue"]) . "',
                          '" . $db->makeSafe($result["currency"]) . "',
                          '" . $db->makeSafe($result["price"]) . "',
                          '" . $db->makeSafe($result["zestimate"]) . "',
                          '" . $db->makeSafe($result["rentZestimate"]) . "',
                          '" . $db->makeSafe($result["homeType"]) . "',
                          '" . $db->makeSafe($homeStatus[$type]) . "',
                          '" . $db->makeSafe($result["yearBuilt"]) . "',
                          '" . $db->makeSafe($result["hasHeating"] ? 1 : 0) . "',
                          '" . $db->makeSafe(implode(", ", $result["heating"])) . "',
                          '" . $db->makeSafe($result["hasCooling"] ? 1 : 0) . "',
                          '" . $db->makeSafe(implode(", ", $result["cooling"])) . "',
                          '" . $db->makeSafe($result["hasGarage"] ? 1 : 0) . "',
                          '" . $db->makeSafe($result["hasAttachedGarage"] ? 1 : 0) . "',
                          '" . $db->makeSafe($result["parkingCapacity"]) . "',
                          '" . $db->makeSafe($result["garageParkingCapacity"]) . "',
                          '" . $db->makeSafe($result["pricePerSquareFoot"]) . "',
                          '" . $db->makeSafe($result["buyerAgencyCompensation"]) . "',
                          '" . $db->makeSafe($result["pageViewCount"]) . "',
                          '" . $db->makeSafe($result["favoriteCount"]) . "',
                          '" . $db->makeSafe($result["daysOnZillow"]) . "',
                          '" . $db->makeSafe($result["agentName"]) . "',
                          '" . $db->makeSafe($result["agentPhoneNumber"]) . "',
                          '" . $db->makeSafe($result["brokerName"]) . "',
                          '" . $db->makeSafe($result["brokerPhoneNumber"]) . "',
                          '" . $db->makeSafe($result["coAgentName"]) . "',
                          '" . $db->makeSafe($result["coAgentNumber"]) . "',
                          '" . $db->makeSafe($result["buyerAgentName"]) . "',
                          '" . $db->makeSafe($result["description"]) . "',
                          '" . date('Y-m-d H:i:s') . "'
                        )";

                      if (!$db->query($sql)) {
                        echo "Error inserting $tableName table: \n";
                        echo $sql . "\n";
                      }
                    }
                  }
                }
              } else {
                echo "$zpid already exists \n";
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

function getHtmlElement($url, $element)
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

    $html = $htmlDomParser->findOne($element);
    if ($html instanceof \voku\helper\SimpleHtmlDomBlank) {
      sleep(2);
      $retryCount++;
    } else {
      break;
    }
  }

  return $html;
}

// Divide states into batches
$countPerBatch = floor(count(STATE_LIST) / $batchCount);
$stateBatches = array_chunk(STATE_LIST, $countPerBatch);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
_main($batchToScrape, $db);
