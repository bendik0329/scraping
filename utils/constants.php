<?php

const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36";

const STATE_LIST = [
  // 'AL',
  // 'AK',
  // 'AZ',
  // 'AR',
  'CA',
  // 'CO',
  // 'CT',
  // 'DE',
  // 'FL',
  // 'GA',
  // 'HI',
  // 'ID',
  // 'IL',
  // 'IN',
  // 'IA',
  // 'KS',
  // 'KY',
  // 'LA',
  // 'ME',
  // 'MD',
  // 'MA',
  // 'MI',
  // 'MN',
  // 'MS',
  // 'MO',
  // 'MT',
  // 'NE',
  // 'NV',
  // 'NH',
  // 'NJ',
  // 'NM',
  // 'NY',
  // 'NC',
  // 'ND',
  // 'OH',
  // 'OK',
  // 'OR',
  // 'PA',
  // 'RI',
  // 'SC',
  // 'SD',
  // 'TN',
  // 'TX',
  // 'UT',
  // 'VT',
  // 'VA',
  // 'WA',
  // 'WV',
  // 'WI',
  // 'WY'
];

const BED_VALUES = [1, 2, 3, 4, 5];
const BATH_VALUES = [1, 1.5, 2, 3, 4];
// const BED_VALUES = [1];
// const BATH_VALUES = [1];
const SQFT_VALUES = [
  [
    "min" => 0,
    "max" => 500,
  ],
  [
    "min" => 501,
    "max" => 750,
  ],
  [
    "min" => 751,
    "max" => 1000,
  ],
  [
    "min" => 1001,
    "max" => 1250,
  ],
  [
    "min" => 1251,
    "max" => 1500,
  ],
  [
    "min" => 1501,
    "max" => 1750,
  ],
  [
    "min" => 1751,
    "max" => 2000,
  ],
  [
    "min" => 2001,
    "max" => 2250,
  ],
  [
    "min" => 2251,
    "max" => 2500,
  ],
  [
    "min" => 2501,
    "max" => 2750,
  ],
  [
    "min" => 2751,
    "max" => 3000,
  ],
  [
    "min" => 3001,
    "max" => 3500,
  ],
  [
    "min" => 3501,
    "max" => 4000,
  ],
  [
    "min" => 4001,
    "max" => 5000,
  ],
  [
    "min" => 5001,
    "max" => 7500,
  ],
  [
    "min" => 7501,
    "max" => 0,
  ],
];
