<?php

require_once  __DIR__ . '/utils/database.php';
use Facebook\WebDriver\WebDriverBy;

function scrapeProperties() {

}

function scrapePropertyDetail($propertyElements) {
  global $db;
  $list = array();

  if (count($propertyElements) > 0) {
    foreach ($propertyElements as $propertyElement) {
      $zpid = str_replace("zpid_", "", $propertyElement->getAttribute("id"));
      $zpid = intval($zpid);

      if ($zpid) {
        $exist = $db->query("SELECT * FROM properties WHERE zpid = $zpid");

        if ($exist->num_rows == 0) {
          $link = $propertyElement->findElement(WebDriverBy::cssSelector("div.property-card-data > a"))->getAttribute("href");

          $list[] = array(
            "zpid" => $zpid,
            "link" => $link,
          );
        }
      }
    }
    return $list;
  } else {
    return [];
  }
}