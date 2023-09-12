<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use voku\helper\HtmlDomParser;

$envConfig = parse_ini_file(__DIR__ . "/.env");
$apiKey = $envConfig['API_KEY'];

for ($i = 0; $i <= 100; $i++) {
  $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/homedetails/10501-S-Normandie-Ave-Los-Angeles-CA-90044/135914078_zpid/";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
  $html = curl_exec($curl);
  curl_close($curl);

  $htmlDomParser = HtmlDomParser::str_get_html($html);
  $detailHtml = $htmlDomParser->findOne("div.detail-page");

  $layoutElement = $detailHtml->findOne("div.layout-container-desktop");
  if ($layoutElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    
  } else {
    print_r($html);
    exit();
  }

  // // get image
  // $imgElement = $detailHtml->findOne("div.media-column-container ul.hdp__sc-1wi9vqt-0.dDzspE.ds-media-col.media-stream li:nth-child(1) img");
  // if ($imgElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  //   $image = "";
  // } else {
  //   $image = $imgElement->getAttribute("src");
  // }

  // // get price
  // $priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");
  // if ($priceElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  //   $price = 0;
  // } else {
  //   $priceText = $priceElement->text();
  //   $price = deformatPrice($priceText);
  // }

  // // get overview
  // $overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd");
  // if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  //   $overview = "";
  // } else {
  //   $overview = $overviewElement->text();
  // }

  // print_r("image->>" . $image);
  // print_r("\n");
  // print_r("price->>" . $price);
  // print_r("\n");
  // print_r("overview->>" . $overview);
  // print_r("\n");
  // print_r("\n");
}

exit();
