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

$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);

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
      $url = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/$stateAlias/foreclosures/&dynamic=false";
      $driver->get($url);
      sleep(5);

      try {
        $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
        $totalCount = str_replace(",", "", $totalCount);
        preg_match('/\d+/', $totalCount, $matches);
      
        if (isset($matches[0])) {
          $totalCount = intval($matches[0]);
          print_r("total count->>" . $totalCount);
          print_r("\n");
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

exit();

$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);

$driver = RemoteWebDriver::create($host, $capabilities);
$url = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=%7B%22pagination%22%3A%7B%7D%2C%22usersSearchTerm%22%3A%22CA%22%2C%22filterState%22%3A%7B%22beds%22%3A%7B%22min%22%3A1%7D%2C%22baths%22%3A%7B%22min%22%3A3%7D%2C%22sqft%22%3A%7B%22min%22%3A1500%2C%22max%22%3A1750%7D%2C%22pmf%22%3A%7B%22value%22%3Atrue%7D%2C%22sort%22%3A%7B%22value%22%3A%22globalrelevanceex%22%7D%2C%22isAllHomes%22%3A%7B%22value%22%3Atrue%7D%2C%22nc%22%3A%7B%22value%22%3Afalse%7D%2C%22fsbo%22%3A%7B%22value%22%3Afalse%7D%2C%22cmsn%22%3A%7B%22value%22%3Afalse%7D%2C%22pf%22%3A%7B%22value%22%3Atrue%7D%2C%22fsba%22%3A%7B%22value%22%3Afalse%7D%7D%2C%22isListVisible%22%3Atrue%7D&dynamic=false";
$driver->get($url);
sleep(5);

try {
  $totalCount = $driver->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
  $totalCount = str_replace(",", "", $totalCount);
  preg_match('/\d+/', $totalCount, $matches);

  if (isset($matches[0])) {
    $totalCount = intval($matches[0]);
    print_r("total count->>" . $totalCount);
    print_r("\n");
  }
} catch (NoSuchElementException $e) {
  print_r($e);
}
$driver->close();

$driver1 = RemoteWebDriver::create($host, $capabilities);
$url1 = "https://api.scrapingdog.com/scrape?api_key=64ea0a7c389c1c508e3bb43b&url=https://www.zillow.com/ca/?searchQueryState=%7B%22pagination%22%3A%7B%7D%2C%22usersSearchTerm%22%3A%22CA%22%2C%22filterState%22%3A%7B%22beds%22%3A%7B%22min%22%3A1%7D%2C%22baths%22%3A%7B%22min%22%3A3%7D%2C%22sqft%22%3A%7B%22min%22%3A1750%2C%22max%22%3A2000%7D%2C%22pmf%22%3A%7B%22value%22%3Atrue%7D%2C%22sort%22%3A%7B%22value%22%3A%22globalrelevanceex%22%7D%2C%22isAllHomes%22%3A%7B%22value%22%3Atrue%7D%2C%22nc%22%3A%7B%22value%22%3Afalse%7D%2C%22fsbo%22%3A%7B%22value%22%3Afalse%7D%2C%22cmsn%22%3A%7B%22value%22%3Afalse%7D%2C%22pf%22%3A%7B%22value%22%3Atrue%7D%2C%22fsba%22%3A%7B%22value%22%3Afalse%7D%7D%2C%22isListVisible%22%3Atrue%7D&dynamic=false";
$driver1->get($url1);
sleep(5);

try {
  $totalCount = $driver1->findElement(WebDriverBy::cssSelector("div.ListHeader__NarrowViewWrapping-srp__sc-1rsgqpl-1.idxSRv.search-subtitle span.result-count"))->getText();
  $totalCount = str_replace(",", "", $totalCount);
  preg_match('/\d+/', $totalCount, $matches);

  if (isset($matches[0])) {
    $totalCount = intval($matches[0]);
    print_r("total count->>" . $totalCount);
    print_r("\n");
  }
} catch (NoSuchElementException $e) {
  print_r($e);
}
$driver1->close();
