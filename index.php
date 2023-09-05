<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
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

// initialize table
_init();

// remove data files
$resultDir = __DIR__ . "/result";
if (!is_dir($resultDir)) {
  mkdir($resultDir, 0777, true);
} else {
  $files = glob($resultDir . '/*');

  foreach ($files as $file) {
    if (is_file($file)) {
      unlink($file);
    }
  }
}

// clear log files
$logDir = __DIR__ . "/log";
if (!is_dir($logDir)) {
  mkdir($logDir, 0777, true);
} else {
  $files = glob($logDir . '/*');

  foreach ($files as $file) {
    if (is_file($file)) {
      unlink($file);
    }
  }
}

$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);

$properties = [];
$counter = 0;
$fileCounter = 1;

$numParallel = 10;
$pids = [];
$chunks = array_chunk(STATE_LIST, $numParallel);

foreach ($chunks as $chunk) {
  $pid = pcntl_fork();

  if ($pid == -1) {
    die('Could not fork');
  } elseif ($pid == 0) {
    $driver = RemoteWebDriver::create($host, $capabilities);

    foreach ($chunk as $state) {
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
              "isAllHomes" => array(
                "value" => True
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

            $driver->get($url);
            // sleep(2);

            try {
              $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
              $totalCount = str_replace(",", "", $totalCount);
              preg_match('/\d+/', $totalCount, $matches);

              if (isset($matches[0])) {
                $totalCount = intval($matches[0]);
                $itemsPerPage = 41;
                $currentPage = 1;
                $maxPage = ceil($totalCount / $itemsPerPage);

                while ($currentPage <= $maxPage) {
                  if ($currentPage != 1) {
                    $pagination = array(
                      "currentPage" => $currentPage,
                    );
                    $query["pagination"] = $pagination;

                    $queryString = json_encode($query);
                    $searchQueryState = urlencode($queryString);
                    $pageUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/$stateAlias/?searchQueryState=$searchQueryState&dynamic=false";

                    $driver->get($pageUrl);
                  }

                  $wait = new WebDriverWait($driver, 10);
                  $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector("footer.site-footer")));

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

                        if ($zpid) {
                          try {
                            $images = array();
                            $imgElements = $element->findElements(WebDriverBy::cssSelector("a.Anchor-c11n-8-84-3__sc-hn4bge-0.kxrUt.carousel-photo picture img.Image-c11n-8-84-3__sc-1rtmhsc-0"));
                            if (count($imgElements) > 0) {
                              foreach ($imgElements as $imgElement) {
                                $images[] = $imgElement->getAttribute("src");;
                              }
                            }

                            $link = $element->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");
                            $detailUrl = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=" . $link;

                            $result = array_merge(array("zpid" => $zpid, "url" => $link, "images" => json_encode($images)), scrapePropertyDetail($detailUrl));

                            $sql = "
                              INSERT INTO properties
                              (
                                zpid,
                                url,
                                images,
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
                                special,
                                overview,
                                createdAt
                              )
                              VALUES
                              (
                                '" . $db->makeSafe($result["zpid"]) . "',
                                '" . $db->makeSafe($result["url"]) . "',
                                '" . $db->makeSafe($result["images"]) . "',
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
                                '" . $db->makeSafe($result["special"]) . "',
                                '" . $db->makeSafe($result["overview"]) . "',
                                '" . date('Y-m-d H:i:s') . "'
                              )";

                            if (!$db->query($sql)) {
                              echo "Error inserting properties table: \n";
                              echo $sql . "\n";
                            }

                            // $properties[] = $result;
                            // $counter++;

                            // if ($counter === 100) {
                            //   file_put_contents(__DIR__ . "/result/data-$fileCounter.json", json_encode($properties));
                            //   $counter = 0;
                            //   $properties = [];

                            //   $fileCounter++;
                            // }
                          } catch (NoSuchElementException $e) {
                          }
                        }
                      } catch (NoSuchElementException $e) {
                      }
                    }
                  }

                  $currentPage++;
                }
              }
            } catch (NoSuchElementException $e) {
            }
          }
        }
      }
    }

    $driver->close();
  } else {
    $pids[] = $pid;
  }
}

// Wait for all child processes to finish
foreach ($pids as $pid) {
  pcntl_waitpid($pid, $status);
}

exit();

if (!empty($properties)) {
  file_put_contents(__DIR__ . "/result/data-$fileCounter.json", json_encode($properties));
}

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// initialize table
_init();

// store to MySQL DB
$fileCounter = 1;
while (file_exists(__DIR__ . "/result/data-$fileCounter.json")) {
  $json = file_get_contents(__DIR__ . "/result/data-$fileCounter.json");
  $properties = json_decode($json, true);

  foreach ($properties as $property) {
    $sql = "
      INSERT INTO properties
      (
        zpid,
        url,
        images,
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
        special,
        overview,
        createdAt
      )
      VALUES
      (
        '" . $db->makeSafe($property["zpid"]) . "',
        '" . $db->makeSafe($property["url"]) . "',
        '" . $db->makeSafe($property["images"]) . "',
        '" . $db->makeSafe($property["price"]) . "',
        '" . $db->makeSafe($property["address"]) . "',
        '" . $db->makeSafe($property["city"]) . "',
        '" . $db->makeSafe($property["state"]) . "',
        '" . $db->makeSafe($property["zipcode"]) . "',
        '" . $db->makeSafe($property["beds"]) . "',
        '" . $db->makeSafe($property["baths"]) . "',
        '" . $db->makeSafe($property["sqft"]) . "',
        '" . $db->makeSafe($property["acres"]) . "',
        '" . $db->makeSafe($property["type"]) . "',
        '" . $db->makeSafe($property["zestimate"]) . "',
        '" . $db->makeSafe($property["houseType"]) . "',
        '" . $db->makeSafe($property["builtYear"]) . "',
        '" . $db->makeSafe($property["heating"]) . "',
        '" . $db->makeSafe($property["cooling"]) . "',
        '" . $db->makeSafe($property["parking"]) . "',
        '" . $db->makeSafe($property["lot"]) . "',
        '" . $db->makeSafe($property["priceSqft"]) . "',
        '" . $db->makeSafe($property["agencyFee"]) . "',
        '" . $db->makeSafe($property["days"]) . "',
        '" . $db->makeSafe($property["views"]) . "',
        '" . $db->makeSafe($property["saves"]) . "',
        '" . $db->makeSafe($property["special"]) . "',
        '" . $db->makeSafe($property["overview"]) . "',
        '" . date('Y-m-d H:i:s') . "'
      )";

    if (!$db->query($sql)) {
      echo "Error inserting properties table: \n";
      echo $sql . "\n";
    }
  }

  $fileCounter++;
}

// download images
$properties = $db->query("SELECT * FROM properties");

if ($properties) {
  if ($properties->num_rows > 0) {
    while ($row = $properties->fetch_assoc()) {
      try {
        $zpid = $row['zpid'];
        $images = json_decode($row['images'], true);

        $imgFolder = __DIR__ . '/download/images/' . $zpid;
        if (!file_exists($imgFolder)) {
          mkdir($imgFolder, 0777, true);
        }

        foreach ($images as $image) {
          $imgPath = $imgFolder . "/" . basename($image);
          if (!file_exists($imgPath)) {
            $imgData = file_get_contents($image);
            if ($imgData !== false) {
              file_put_contents($imgPath, $imgData);
            }
          }
        }
      } catch (Exception $e) {
      }
    }
  }
}

exit();
