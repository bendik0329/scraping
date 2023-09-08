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
    `image` TEXT,
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
    `zestimate` INT ( 11 ),
    `houseType` VARCHAR ( 255 ),
    `builtYear` INT ( 11 ),
    `heating` VARCHAR ( 255 ),
    `cooling` VARCHAR ( 255 ),
    `parking` VARCHAR ( 255 ),
    `lot` FLOAT ( 4 ),
    `priceSqft` INT ( 11 ),
    `agencyFee` FLOAT ( 4 ),
    `days` INT ( 11 ),
    `views` INT ( 11 ),
    `saves` INT ( 11 ),
    `special` VARCHAR ( 255 ),
    `overview` TEXT,
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )
  ENGINE = MyISAM";

  if ($db->query($createPropertiesSql) === TRUE) {
    echo "Table properties created successfully \n";
  } else {
    echo "Error creating properties table: " . $conn->error . "\n";
  }
} else {
  echo "Error dropping properties table: " . $conn->error . "\n";
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
