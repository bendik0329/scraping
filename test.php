<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use voku\helper\HtmlDomParser;

$envConfig = parse_ini_file(__DIR__ . "/.env");
$apiKey = $envConfig['API_KEY'];

for ($i = 0; $i <= 100; $i++) {
  $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/homedetails/9887-Macarthur-Blvd-I-Oakland-CA-94605/2063699609_zpid/";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
  $html = curl_exec($curl);
  curl_close($curl);

  $htmlDomParser = HtmlDomParser::str_get_html($html);
  $detailHtml = $htmlDomParser->findOne("div.detail-page");

  // get overview
  $overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd");
  if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
    $overview = "";
  } else {
    $overview = $overviewElement->text();
  }

  print_r("overview->>" . $overview);
  print_r("\n");
}

exit();

// for ($i = 0; $i <= 100; $i++) {
//   $url = 'https://www.zillow.com/homedetails/506-E-Philadelphia-St-Rapid-City-SD-57701/117808623_zpid/';
//   $ch = curl_init();
//   curl_setopt($ch, CURLOPT_URL, $url);
//   curl_setopt($ch, CURLOPT_PROXY, 'http://60cb4030eb5826662d404f6cb6bb10040a3f775a:@proxy.zenrows.com:8001');
//   curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
//   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//   curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
//   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//   $html = curl_exec($ch);
//   curl_close($ch);

//   $htmlDomParser = HtmlDomParser::str_get_html($html);
//   $detailHtml = $htmlDomParser->findOne("div.detail-page");

//   // get price
//   $priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");
//   if ($priceElement instanceof \voku\helper\SimpleHtmlDomBlank) {
//     $price = 0;
//   } else {
//     $priceText = $priceElement->text();
//     $price = deformatPrice($priceText);
//   }


//   // get overview
//   // $overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd");
//   // if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
//   //   $overview = "";
//   // } else {
//   //   $overview = $overviewElement->text();
//   // }

//   print_r("price->>" . $price);
//   print_r("\n");

//   // print_r("overview->>" . $overview);
//   // print_r("\n");
// }

// print_r("overview->>" . $overview);
// print_r("\n");
exit();

// $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/homedetails/506-E-Philadelphia-St-Rapid-City-SD-57701/117808623_zpid/";

// $curl = curl_init();
// curl_setopt($curl, CURLOPT_URL, $url);
// curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
// curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);
// $html = curl_exec($curl);
// curl_close($curl);

// $htmlDomParser = HtmlDomParser::str_get_html($html);

// $detailHtml = $htmlDomParser->findOne("div.detail-page");

// // get overview
// $overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd.jKaobh");
// if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
//   $overview = "";
// } else {
//   $overview = $overviewElement->text();
// }

// print_r("overview->>" . $overview);
// print_r("\n");
// exit();

// $priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");

// $agentText = $detailHtml->findOne("Text-c11n-8-84-3__sc-aiai24-0.lBimH")->text();


// print_r($agentText);
// exit();
