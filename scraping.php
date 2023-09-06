<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/init.php';
require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverExpectedCondition;

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");
$apiKey = $envConfig['API_KEY'];

// Set up Selenium WebDriver
$host = 'http://localhost:4444/wd/hub';
$capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
$capabilities->setCapability('goog:chromeOptions', ['args' => ["--headless", "--user-agent=" . USER_AGENT]]);

$states = STATE_LIST;
$startIndex = intval($argv[1]);

function scrape ($batch, $db) {
  global $host, $capabilities;
  $driver = RemoteWebDriver::create($host, $capabilities);

  foreach ($batch as $state) {
    print_r($state);
    print_r("\n");
  }

  $driver->close();
}

// Divide states into batches of 5
$stateBatches = array_chunk($states, 10);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
scrape($batchToScrape, $db);
