<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
// require_once  __DIR__ . '/initialize.php';
// require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverKeys;

$states = STATE_LIST;
$startIndex = intval($argv[1]); // Get the startIndex from the command line argument

// load environment variable
// $envConfig = parse_ini_file(__DIR__ . "/.env");

// $host = $envConfig['DB_HOST'];
// $username = $envConfig['DB_USERNAME'];
// $password = $envConfig['DB_PASSWORD'];
// $dbname = $envConfig['DB_DATABASE'];
// $apiKey = $envConfig['API_KEY'];

// // Connect to DB
// $db  = new Database();
// if (!$db->connect($host, $username, $password, $dbname)) {
//   die("DB Connection failed: " . $conn->connect_error);
// }

// // initialize
// _init();
print_r($startIndex);
print_r("\n");
function scrape($states, $db) {
  print_r($states);
}

// Divide states into batches of 5
$stateBatches = array_chunk($states, 5);

// Get the batch to scrape based on the startIndex
$batchToScrape = isset($stateBatches[$startIndex]) ? $stateBatches[$startIndex] : [];

// Scrape and store the batch of states
scrape($batchToScrape, $db);

