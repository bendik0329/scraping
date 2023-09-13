<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverWait;
use voku\helper\HtmlDomParser;

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

  foreach ($batch as $state) {
    foreach (LISTING_TYPE as $type) {
      foreach (CATEGORY as $category) {
        $pageUrl = getPageUrl($state, $type, $category);
        $count = getPropertyCount($driver, $pageUrl);

        if ($count > 0 && $count <= 820) {
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
                $dataElement = retryCurlRequest($detailUrl);

                $dataElement =  HtmlDomParser::str_get_html($response);

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
                        INSERT INTO properties
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
                          '" . $db->makeSafe($result["yearBuilt"]) . "',
                          '" . $db->makeSafe($result["hasHeating"] ? 1 : 0) . "',
                          '" . $db->makeSafe(json_encode($result["heating"])) . "',
                          '" . $db->makeSafe($result["hasCooling"] ? 1 : 0) . "',
                          '" . $db->makeSafe(json_encode($result["cooling"])) . "',
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
                        echo "Error inserting properties table: \n";
                        echo $sql . "\n";
                      }
                    } else {
                      print_r("gdp client cache is not array or object");
                      print_r("\n");
                      print_r($gdpClientCache);
                      print_r("\n");
                    }
                  } else {
                    print_r("gdp client cache doesnt exists");
                    print_r("\n");
                    print_r($data);
                    print_r("\n");
                    print_r($detailUrl);
                    print_r("\n");
                  }
                } else {
                  print_r("there is no data element");
                  print_r("\n");
                  print_r("zpid->>" . $zpid);
                  print_r("\n");
                  print_r("link->>" . $link);
                  print_r("\n");
                }
              } catch (NoSuchElementException $e) {
                print_r("No such link element");
                print_r("\n");
                print_r("zpid->>" . $zpid);
                print_r("\n");
              }
            }
          }
        } catch (NoSuchElementException $e) {
          print_r("No such property card element");
          print_r("\n");
          print_r("state->>" . $state);
          print_r("\n");
          print_r("type->>" . $type);
          print_r("\n");
          print_r("category->>" . $category);
          print_r("\n");
          print_r($range);
          print_r("\n");
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

  return HtmlDomParser::str_get_html($response);
}

function retryCurlRequest($url)
{
  $retryCount = 0;
  $maxRetries = 5;
  $html = '';

  while ($retryCount < $maxRetries) {
    try {
      $response = sendCurlRequest($url);
      $html = $response->findOne("script[id=__NEXT_DATA__]");

      if ($html instanceof \voku\helper\SimpleHtmlDomBlank) {
        sleep(2);
        $retryCount++;
      } else {
        break;
      }
    } catch (Exception $e) {
      echo "Error Occured in this url->>" . $url . "\n";
      break;
    }
  }

  return $html;
}

// Divide states into batches of 5
$stateBatches = array_chunk(STATE_LIST, 5);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
_main($batchToScrape, $db);
