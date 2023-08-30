<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/database.php';

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// check properties table
$dropPropertiesSql = "DROP TABLE IF EXISTS properties";

if ($db->query($dropPropertiesSql) === TRUE) {
  $createPropertiesSql = "CREATE TABLE IF NOT EXISTS properties (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` INT ( 11 ) NOT NULL UNIQUE,
    `url` VARCHAR ( 255 ) NOT NULL,
    `image` VARCHAR ( 255 ),
    `currency` VARCHAR ( 255 ),
    `price` INT ( 11 ),
    `address` VARCHAR ( 255 ),
    `city` VARCHAR ( 255 ),
    `state` VARCHAR ( 255 ),
    `zipcode` VARCHAR ( 255 ),
    `beds` FLOAT ( 4 ),
    `baths` FLOAT ( 4 ),
    `sqft` FLOAT ( 4 ),
    `acres` FLOAT ( 4 ),
    `type` VARCHAR ( 255 ),
    `zestimateCurrency` VARCHAR ( 255 ),
    `zestimatePrice` INT ( 11 ),
    `houseType` VARCHAR ( 255 ),
    `builtYear` INT ( 11 ),
    `heating` VARCHAR ( 255 ),
    `cooling` VARCHAR ( 255 ),
    `parking` VARCHAR ( 255 ),
    `lot` FLOAT ( 4 ),
    `lotUnit` VARCHAR ( 255 ),
    `priceSqftCurrency` VARCHAR ( 255 ),
    `priceSqft` INT ( 11 ),
    `agencyFee` FLOAT ( 4 ),
    `days` INT ( 11 ),
    `views` INT ( 11 ),
    `saves` INT ( 11 ),
    `special` VARCHAR ( 255 ),
    `overview` TEXT,
    `images` TEXT,
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

  if ($db->query($createPropertiesSql) === TRUE) {
    echo "Table properties created successfully \n";
  } else {
    die("Error creating properties table: " . $conn->error . "\n");
  }
} else {
  die("Error dropping properties table: " . $conn->error . "\n");
}

// check images table
$dropImagesSql = "DROP TABLE IF EXISTS images";

if ($db->query($dropImagesSql) === TRUE) {
  $imagesSql = "CREATE TABLE IF NOT EXISTS images (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL,
    `url` VARCHAR ( 255 ) NOT NULL,
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

  if ($db->query($imagesSql) === TRUE) {
    echo "Table images created successfully \n";
  } else {
    die("Error creating images table: " . $conn->error . "\n");
  }
} else {
  die("Error dropping images table: " . $conn->error . "\n");
}

// check price_history table
$dropPriceHistoriesSql = "DROP TABLE IF EXISTS price_histories";

if ($db->query($dropPriceHistoriesSql) === TRUE) {
  $priceHistoriesSql = "CREATE TABLE IF NOT EXISTS price_histories (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL,
    `date` DATE,
    `event` VARCHAR ( 255 ),
    `price` VARCHAR ( 255 ),
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

  if ($db->query($priceHistoriesSql) === TRUE) {
    echo "Table price_histories created successfully \n";
  } else {
    die("Error creating price_histories table: " . $conn->error . "\n");
  }
} else {
  die("Error dropping price_histories table: " . $conn->error . "\n");
}

// check tax_history table
$dropTaxHistoriesSql = "DROP TABLE IF EXISTS tax_histories";

if ($db->query($dropTaxHistoriesSql) === TRUE) {
  $taxHistoriesSql = "CREATE TABLE IF NOT EXISTS tax_histories (
    `id` INT ( 6 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` VARCHAR ( 255 ) NOT NULL,
    `year` INT ( 11 ),
    `tax` VARCHAR ( 255 ),
    `taxAssessment` VARCHAR ( 255 ),
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )";

  if ($db->query($taxHistoriesSql) === TRUE) {
    echo "Table tax_histories created successfully \n";
  } else {
    die("Error creating tax_histories table: " . $conn->error . "\n");
  }
} else {
  die("Error dropping tax_histories table: " . $conn->error . "\n");
}

// check the selenium server
if (PHP_OS === "Linux") {
  $serviceName = "selenium.service";
  $checkCommand = "systemctl is-active $serviceName";
  $output = shell_exec($checkCommand);

  if (trim($output) !== "active") {
    $startCommand = "sudo systemctl start $serviceName";
    $startOutput = shell_exec($startCommand);

    echo "Selenium Service was not running. Attempting to start...\n";
    echo "Start command output: $startOutput\n";
  }
}
?>