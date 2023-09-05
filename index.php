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

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/homedetails/703-Farlow-Ave-Rapid-City-SD-57701/117814162_zpid/");
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
$html = curl_exec($curl);
curl_close($curl);

$htmlDomParser = HtmlDomParser::str_get_html($html);

$detailHtml = $htmlDomParser->findOne("div.detail-page");

// get address
// get address
$addressElement = $detailHtml->findOne("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd");
if ($addressElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $address = "";
  $city = "";
  $state = "";
  $zipcode = "";
} else {
  $addressText = $addressElement->text;
  $addressText = str_replace([", ", ", "], ",", $addressText);
  $addressArray = explode(",", $addressText);

  if (isset($addressArray[0])) {
    $address = trim($addressArray[0]);
  } else {
    $address = "";
  }

  if (isset($addressArray[1])) {
    $city = trim($addressArray[1]);
  } else {
    $city = "";
  }

  if (isset($addressArray[2])) {
    $state = trim($addressArray[2]);
    $stateArray = explode(" ", trim($addressArray[2]));

    if (isset($stateArray[0])) {
      $state = $stateArray[0];
    } else {
      $state = "";
    }

    if (isset($stateArray[1])) {
      $zipcode = $stateArray[1];
    } else {
      $zipcode = "";
    }
  } else {
    $state = "";
    $zipcode = "";
  }
}

print_r("address->>" . $address);
print_r("\n");

print_r("city->>" . $city);
print_r("\n");

print_r("state->>" . $state);
print_r("\n");

print_r("zipcode->>" . $zipcode);
print_r("\n");

exit();
// get price
$priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");
if ($priceElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $price = 0;
} else {
  $priceText = $priceElement->text();
  $price = deformatPrice($priceText);
}

// get address
$addressElement = $detailHtml->findOne("div.summary-container h1.Text-c11n-8-84-3__sc-aiai24-0.hrfydd");
if ($addressElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $address = "";
  $city = "";
  $state = "";
  $zipcode = "";
} else {
  $addressText = $addressElement->text;
  $addressArray = explode(",", $addressText);

  if (isset($addressArray[0])) {
    $address = trim($addressArray[0]);
  } else {
    $address = "";
  }

  if (isset($addressArray[1])) {
    $city = trim($addressArray[1]);
  } else {
    $city = "";
  }

  if (isset($addressArray[2])) {
    $state = trim($addressArray[2]);
    $stateArray = explode(" ", trim($addressArray[2]));

    if (isset($stateArray[0])) {
      $state = $stateArray[0];
    } else {
      $state = "";
    }

    if (isset($stateArray[1])) {
      $zipcode = $stateArray[1];
    } else {
      $zipcode = "";
    }
  } else {
    $state = "";
    $zipcode = "";
  }
}

// get type
$typeElement = $detailHtml->findOne("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.dpf__sc-1yftt2a-0.bNENJa span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1yftt2a-1.hrfydd.ixkFNb");
if ($typeElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $type = "";
} else {
  $type = $typeElement->text();
}

// get zestimate
$zestimateElement = $detailHtml->findOne("div.hdp__sc-13r9t6h-0.ds-chip-removable-content span div.hdp__sc-j76ge-1.fomYLZ > span.Text-c11n-8-84-3__sc-aiai24-0.hrfydd > span.Text-c11n-8-84-3__sc-aiai24-0.hqOVzy span");
if ($zestimateElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $zestimate = 0;
} else {
  $zestimateText = $zestimateElement->text();
  if ($zestimateText === "None") {
    $zestimate = 0;
  } else {
    $zestimate = deformatPrice($zestimateText);
  }
}

// get special info
$specialElement = $detailHtml->findOne("div.hdp__sc-ld4j6f-0.cKHmSE span.StyledTag-c11n-8-84-3__sc-1945joc-0.ftTUfk hdp__sc-ld4j6f-1.cosjzO");
if ($specialElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $special = "";
} else {
  $special = $specialElement->text();
}

// get overview
$overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd.jKaobh");
if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $overview = "";
} else {
  $overview = $overviewElement->text();
}

// get bed, bath info
$beds = 0;
$baths = 0;
$sqft = 0;
$acres = 0;

$bedBathElements = $detailHtml->find("span[data-testid=\"bed-bath-item\"]");
$count = $bedBathElements->count();

if ($count > 1) {
  foreach ($bedBathElements as $bedBathElement) {
    $title = $bedBathElement->findOne("span > span")->text();
    $value = $bedBathElement->findOne("span > strong")->text();

    preg_match('/\d+(\.\d+)?/', $value, $matches);
    if (!empty($matches)) {
      $value = $matches[0];
    } else {
      $value = 0;
    }

    switch ($title) {
      case "bd":
        $beds = $value;
        break;
      case "ba":
        $baths = $value;
        break;
      case "sqft":
        $sqft = $value;
        break;
      case "Acres":
        $acres = $value;
        break;
    }
  }
} else if ($count === 1) {
  foreach ($bedBathElements as $bedBathElement) {
    $text = $bedBathElement->findOne("span > strong")->text();
    $array = explode(" ", $text);
    $title = $array[1];
    $value = $array[0];

    if ($title && $value) {
      preg_match('/\d+(\.\d+)?/', $value, $matches);
      if (!empty($matches)) {
        $value = $matches[0];
      } else {
        $value = 0;
      }

      switch ($title) {
        case "bd":
          $beds = $value;
          break;
        case "ba":
          $baths = $value;
          break;
        case "sqft":
          $sqft = $value;
          break;
        case "Acres":
          $acres = $value;
          break;
      }
    }
  }
}

// get house info
$houseType = "";
$builtYear = 0;
$heating = "";
$cooling = "";
$parking = "";
$lot = 0;
$priceSqft = 0;
$agencyFee = 0;

$houseElements = $detailHtml->find("div.data-view-container ul.dpf__sc-xzpkxd-0.dFxsBL li.dpf__sc-2arhs5-0.gRshUo");
$count = $houseElements->count();
if ($count > 0) {
  foreach ($houseElements as $houseElement) {
    $title = $houseElement->findOne("svg.Icon-c11n-8-84-3__sc-13llmml-0.iAcAav title")->text();
    $value = $houseElement->findOne("span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-2arhs5-3.hrfydd.kOlNqB")->text();
    if ($title) {
      switch ($title) {
        case "Type";
          $houseType = $value;
          if ($houseType === "No data") {
            $houseType = "";
          }
          break;
        case "Year Built";
          $builtYear = $value;
          if ($builtYear === "No data") {
            $builtYear = 0;
          } else {
            $pattern = '/\b\d+\b/'; // Regular expression pattern to match any number

            if (preg_match($pattern, $builtYear, $matches)) {
              $builtYear = $matches[0];
            } else {
              $builtYear = 0;
            }
          }
          break;
        case "Heating";
          $heating = $value;
          if ($heating === "No data") {
            $heating = "";
          }
          break;
        case "Cooling";
          $cooling = $value;
          if ($cooling === "No data") {
            $cooling = "";
          }
          break;
        case "Parking";
          $parking = $value;
          if ($parking === "No data") {
            $parking = "";
          }
          break;
        case "Lot";
          $lot = $value;
          if ($lot == "No data") {
            $lot = 0;
          } else {
            $lotArray = explode(" ", $lot);
            $lot = deformatNumber($lotArray[0]);
            $unit = $lotArray[1];

            if ($unit == "Acres") {
              $lot = floatval($lot) * 43560;
            }
          }
          break;
        case "Price/sqft";
          $priceSqft = $value;
          if ($priceSqft == "No data") {
            $priceSqft = 0;
          } else {
            preg_match('/([^\d\s]+[\d,]+)/', $priceSqft, $matches);
            if (!empty($matches)) {
              $priceSqft = deformatPrice($matches[0]);
            } else {
              $priceSqft = 0;
            }
          }
          break;
        case "Buyers Agency Fee";
          $agencyFee = $value;
          if ($agencyFee == "No data") {
            $agencyFee = 0;
          } else {
            preg_match('/\d+(\.\d+)?/', $agencyFee, $matches);
            if (!empty($matches)) {
              $agencyFee = $matches[0];
            } else {
              $agencyFee = 0;
            }
          }
          break;
      }
    }
  }
}

$days = 0;
$views = 0;
$saves = 0;

$dtElements = $detailHtml->find("dl.hdp__sc-7d6bsa-0.gmVtvh dt");
$count = $dtElements->count();
if ($count > 0) {
  foreach ($dtElements as $key => $dtElement) {
    $value = $dtElement->findOne("strong")->text();
    $value = deformatNumber($value);
    preg_match('/\d+/', $value, $matches);
    if (isset($matches[0])) {
      $value = intval($matches[0]);
    } else {
      $value = 0;
    }

    switch ($key) {
      case 0:
        $days = $value;
        break;
      case 1:
        $views = $value;
        break;
      case 2:
        $saves = $value;
        break;
    }
  }
}

print_r(array(
  "price" => $price,
  "address" => $address,
  "city" => $city,
  "state" => $state,
  "zipcode" => $zipcode,
  "beds" => $beds,
  "baths" => $baths,
  "sqft" => $sqft,
  "acres" => $acres,
  "type" => $type,
  "zestimate" => $zestimate,
  "houseType" => $houseType,
  "builtYear" => $builtYear,
  "heating" => $heating,
  "cooling" => $cooling,
  "parking" => $parking,
  "lot" => $lot,
  "priceSqft" => $priceSqft,
  "agencyFee" => $agencyFee,
  "special" => $special,
  "overview" => $overview,
  "days" => $days,
  "views" => $views,
  "saves" => $saves,
));

exit();

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

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);
$driver = RemoteWebDriver::create($host, $capabilities);

$properties = [];
$total = 0;

foreach (STATE_LIST as $state) {
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

            print_r("total count->>" . $totalCount);
            print_r("\n");

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
                      $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

                      if ($exist->num_rows == 0) {
                        $link = $element->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

                        $images = array();
                        $imgElements = $element->findElements(WebDriverBy::cssSelector("a.Anchor-c11n-8-84-3__sc-hn4bge-0.kxrUt.carousel-photo picture img.Image-c11n-8-84-3__sc-1rtmhsc-0"));
                        if (count($imgElements) > 0) {
                          foreach ($imgElements as $imgElement) {
                            $images[] = $imgElement->getAttribute("src");;
                          }
                        }

                        $list[] = array(
                          "zpid" => $zpid,
                          "link" => $link,
                          "images" => $images,
                        );
                      }
                    }
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

echo json_encode($properties);
echo "Total Count->>" . $total;

// close chrome driver
$driver->close();

// download images
downloadImages();
