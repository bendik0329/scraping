<?php

const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36";

const STATE_LIST = [
  'AL',
  'AK',
  'AZ',
  'AR',
  'CA',

  'CO',
  'CT',
  'DE',
  'FL',
  'GA',

  'HI',
  'ID',
  'IL',
  'IN',
  'IA',

  'KS',
  'KY',
  'LA',
  'ME',
  'MD',

  'MA',
  'MI',
  'MN',
  'MS',
  'MO',

  'MT',
  'NE',
  'NV',
  'NH',
  'NJ',

  'NM',
  'NY',
  'NC',
  'ND',
  'OH',

  'OK',
  'OR',
  'PA',
  'RI',
  'SC',

  'SD',
  'TN',
  'TX',
  'UT',
  'VT',

  'VA',
  'WA',
  'WV',
  'WI',
  'WY'
];

const LISTING_TYPE = [
  "fore", 
  "auc", 
  "pmf", 
  "pf"
];

const HOME_STATUS = [
  "fore" => "Foreclosures", 
  "auc" => "Auctions", 
  "pmf" => "Foreclosed", 
  "pf" => "Pre-foreclosures"
];

const CATEGORY = [
  "cat1", 
  "cat2"
];