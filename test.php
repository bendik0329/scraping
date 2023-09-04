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

$numParallel = 5;
$pids = [];
$chunks = array_chunk(STATE_LIST, $numParallel);
$properties = [];
$total = 0;

foreach ($chunks as $chunk) {
  $pid = pcntl_fork();

  if ($pid == -1) {
      // Fork failed
      die('Could not fork');
  } elseif ($pid == 0) {
      // Child process
      // Create a new WebDriver instance
      // $driver = RemoteWebDriver::create($host, $capabilities);

      // Process each state in the chunk
      foreach ($chunk as $state) {
          // Add your code here to control the Selenium instance for each state
          // For example, navigate to a website and perform actions
          echo "Processing state: $state\n";
      }

      // Quit the WebDriver instance
      // $driver->quit();

      exit(); // Exit the child process
  } else {
      // Parent process
      $pids[] = $pid;
  }
}

// Wait for all child processes to finish
foreach ($pids as $pid) {
  pcntl_waitpid($pid, $status);
}