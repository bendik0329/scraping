<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once  __DIR__ . '/utils/constants.php';
require_once  __DIR__ . '/utils/database.php';
require_once  __DIR__ . '/utils/scraping.php';

use voku\helper\HtmlDomParser;

$envConfig = parse_ini_file(__DIR__ . "/.env");
$apiKey = $envConfig['API_KEY'];

$url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.zillow.com/homedetails/506-E-Philadelphia-St-Rapid-City-SD-57701/117808623_zpid/";

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
$overviewElement = $detailHtml->findOne("div.Text-c11n-8-84-3__sc-aiai24-0.sc-oZIhv.hrfydd.jKaobh");
if ($overviewElement instanceof \voku\helper\SimpleHtmlDomBlank) {
  $overview = "";
} else {
  $overview = $overviewElement->text();
}

print_r("overview->>" . $overview);
print_r("\n");
exit();

$priceElement = $detailHtml->findOne("div.summary-container div.hdp__sc-1s2b8ok-1.ckVIjE span.Text-c11n-8-84-3__sc-aiai24-0.dpf__sc-1me8eh6-0.OByUh.fpfhCd > span");

$agentText = $detailHtml->findOne("Text-c11n-8-84-3__sc-aiai24-0.lBimH")->text();


print_r($agentText);
exit();
