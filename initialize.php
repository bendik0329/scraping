<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/database.php';

// load environment variable
$envConfig = parse_ini_file(__DIR__ . "/.env");

$host = $envConfig['DB_HOST'];
$username = $envConfig['DB_USERNAME'];
$password = $envConfig['DB_PASSWORD'];
$dbname = $envConfig['DB_DATABASE'];
$tableName = $envConfig['DB_TABLE'];

// Connect to DB
$db  = new Database();
if (!$db->connect($host, $username, $password, $dbname)) {
  die("DB Connection failed: " . $conn->connect_error);
}

// check table exists or not
$tableExists = $db->query("SHOW TABLES LIKE '$tableName'");

if ($db->numrows($tableExists) === 0) {
  $createSql = "CREATE TABLE IF NOT EXISTS $tableName (
    `id` INT ( 0 ) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zpid` INT ( 0 ) NOT NULL UNIQUE,
    `url` VARCHAR ( 255 ) NOT NULL,
    `image` TEXT,
    `streetAddress` VARCHAR ( 50 ),
    `city` VARCHAR ( 50 ),
    `state` VARCHAR ( 50 ),
    `zipcode` VARCHAR ( 50 ),
    `latitude` VARCHAR ( 50 ),
    `longitude` VARCHAR ( 50 ),
    `country` VARCHAR ( 50 ),
    `bedrooms` FLOAT,
    `bathrooms` FLOAT,
    `livingAreaUnits` VARCHAR ( 50 ),
    `livingAreaValue` FLOAT,
    `lotAreaUnits` VARCHAR ( 50 ),
    `lotAreaValue` FLOAT,
    `currency` VARCHAR ( 50 ),
    `price` INT ( 0 ),
    `zestimate` INT ( 0 ),
    `rentZestimate` INT ( 0 ),
    `homeType` VARCHAR ( 50 ),
    `homeStatus` VARCHAR ( 50 ),
    `yearBuilt` INT ( 0 ),
    `hasHeating` TINYINT ( 1 ),
    `heating` VARCHAR ( 255 ),
    `hasCooling` TINYINT ( 1 ),
    `cooling` VARCHAR ( 255 ),
    `hasGarage` TINYINT ( 1 ),
    `hasAttachedGarage` TINYINT ( 1 ),
    `parkingCapacity` INT ( 0 ),
    `garageParkingCapacity` INT ( 0 ),
    `pricePerSquareFoot` INT ( 0 ),
    `buyerAgencyCompensation` VARCHAR ( 255 ),
    `pageViewCount` INT ( 0 ),
    `favoriteCount` INT ( 0 ),
    `daysOnZillow` INT ( 0 ),
    `agentName` VARCHAR ( 255 ),
    `agentPhoneNumber` VARCHAR ( 255 ),
    `brokerName` VARCHAR ( 255 ),
    `brokerPhoneNumber` VARCHAR ( 255 ),
    `coAgentName` VARCHAR ( 255 ),
    `coAgentNumber` VARCHAR ( 255 ),
    `buyerAgentName` VARCHAR ( 255 ),
    `description` TEXT,
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
  )
  ENGINE = MyISAM";

  if ($db->query($createSql) === TRUE) {
    echo "Table $tableName created successfully \n";
  } else {
    echo "Error creating $tableName table: " . $conn->error . "\n";
  }
} else {
  echo "Table $tableName already exists \n";
}
