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
use Facebook\WebDriver\WebDriverDimension;
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

$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$properties = [];
$total = 0;

$numParallel = 10;
$pids = [];
$chunks = array_chunk(STATE_LIST, $numParallel);

foreach ($chunks as $chunk) {
  $pid = pcntl_fork();

  if ($pid == -1) {
    // Fork failed
    die('Could not fork');
  } elseif ($pid == 0) {
    $driver = RemoteWebDriver::create($host, $capabilities);

    foreach ($chunk as $state) {

      $stateAlias = strtolower($state);
      $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/foreclosures/&dynamic=false";
      $driver->get($url);
      sleep(5);

      print_r($url);
      print_r("\n");

      try {
        $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
        $totalCount = str_replace(",", "", $totalCount);
        preg_match('/\d+/', $totalCount, $matches);

        if (isset($matches[0])) {
          $totalCount = intval($matches[0]);
          print_r("total count->>" . $totalCount);
          print_r("\n");

          $list = array();
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
                print_r("zpid->>" . $zpid);
                // if ($zpid) {
                //   $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

                //   if ($exist->num_rows == 0) {
                //     $link = $element->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

                //     $images = array();
                //     $imgElements = $element->findElements(WebDriverBy::cssSelector("a.Anchor-c11n-8-84-3__sc-hn4bge-0.kxrUt.carousel-photo picture img.Image-c11n-8-84-3__sc-1rtmhsc-0"));
                //     if (count($imgElements) > 0) {
                //       foreach ($imgElements as $imgElement) {
                //         $images[] = $imgElement->getAttribute("src");;
                //       }
                //     }

                //     $list[] = array(
                //       "zpid" => $zpid,
                //       "link" => $link,
                //       "images" => $images,
                //     );
                //   }
                // }
              } catch (NoSuchElementException $e) {
              }
            }
          }

          foreach ($list as $item) {
            if ($item["zpid"] && $item["link"]) {
              $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $item["link"];

              $driver->get($detailUrl);
              sleep(2);

              $detailHtml = $driver->findElement(WebDriverBy::cssSelector("div.detail-page"));
              $result = scrapePropertyDetail($item["zpid"], $detailHtml);
              $result["zpid"] = $item["zpid"];
              $result["url"] = $item["link"];
              $result["images"] = $item["images"];

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
                  priceSqftCurrency,
                  priceSqft,
                  agencyFee,
                  days,
                  views,
                  saves,
                  special,
                  overview,
                  images,
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
                  '" . $db->makeSafe($result["priceSqftCurrency"]) . "',
                  '" . $db->makeSafe($result["priceSqft"]) . "',
                  '" . $db->makeSafe($result["agencyFee"]) . "',
                  '" . $db->makeSafe($result["days"]) . "',
                  '" . $db->makeSafe($result["views"]) . "',
                  '" . $db->makeSafe($result["saves"]) . "',
                  '" . $db->makeSafe($result["special"]) . "',
                  '" . $db->makeSafe($result["overview"]) . "',
                  '" . $db->makeSafe(json_encode($result["images"])) . "',
                  '" . date('Y-m-d H:i:s') . "'
                )";

              if (!$db->query($sql)) {
                echo "Error inserting properties table: \n";
                echo $sql . "\n";
              }

              $properties[] = $result;
              $total++;
            }
          }
        }
      } catch (NoSuchElementException $e) {
        print_r($e);
      }
    }

    $driver->close();
  } else {
    // Parent process
    $pids[] = $pid;
  }
}

// Wait for all child processes to finish
foreach ($pids as $pid) {
  pcntl_waitpid($pid, $status);
}

echo json_encode($properties);
echo "Total Count->>" . $total;

exit();