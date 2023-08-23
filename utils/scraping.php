<?php

require_once __DIR__ . '/../vendor/autoload.php';

use voku\helper\HtmlDomParser;

function scrapeProperty($element)
{
  $zpid = str_replace("zpid_", "", $element->getAttribute("id"));
  $url = $element->findOne("div.property-card-data > a")->getAttribute("href");
  $address = $element->findOne("div.property-card-data > a > address")->text;
  $price = $element->findOne("div.property-card-data span.PropertyCardWrapper__StyledPriceLine-srp__sc-16e8gqd-1")->text;

  $beds = $element->findOne("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(1) > b")->text;
  $baths = $element->findOne("div.property-card-data div.StyledPropertyCardDataArea-c11n-8-84-3__sc-yipmu-0.dbDWjx > ul > li:nth-child(2) > b")->text;

  $imgList = [];
  $imgElements = $element->find("div.StyledPropertyCardPhoto-c11n-8-84-3__sc-ormo34-0.dGCVxQ.StyledPropertyCardPhoto-srp__sc-1gxvsd7-0");
  foreach ($imgElements as $imgElement) {
    if ($imgElement->findOne("img.Image-c11n-8-84-3__sc-1rtmhsc-0")) {
      $imgUrl = $imgElement->findOne("img.Image-c11n-8-84-3__sc-1rtmhsc-0")->getAttribute("src");
      $imgFolder = __DIR__ . '/../download/images/' . $zpid;
      if (!file_exists($imgFolder)) {
        mkdir($imgFolder, 0777, true);
      }
      $imgPath = $imgFolder . "/" . basename($imgUrl);
      $imgData = file_get_contents($imgUrl);
      if ($imgData !== false) {
        file_put_contents($imgPath, $imgData);
      }
      $imgList[] = $imgUrl;
    }
  }

  return array(
    "zpid" => intval($zpid),
    "url" => $url,
    "address" => $address,
    "price" => $price,
    "beds" => $beds,
    "baths" => $baths,
    "hasImage" => count($imgList) > 0 ? true : false,
    "images" => $imgList
  );
}
