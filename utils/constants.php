<?php

const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36";

const STATE_LIST = [
  'AL' => "Alabama",
  'AK' => "Alaska",
  'AZ' => "Arizona",
  'AR' => "Arkansas",
  'CA' => "California",
  'CO' => "Colorado",
  'CT' => "Connecticut",
  'DE' => "Delaware",
  'FL' => "Florida",
  'GA' => "Georgia",
  'HI' => "Hawaii",
  'ID' => "Idaho",
  'IL' => "Illinois",
  'IN' => "Indiana",
  'IA' => "Iowa",
  'KS' => "Kansas",
  'KY' => "Kentucky",
  'LA' => "Louisiana",
  'ME' => "Maine",
  'MD' => "Maryland",
  'MA' => "Massachusetts",
  'MI' => "Michigan",
  'MN' => "Minnesota",
  'MS' => "Mississippi",
  'MO' => "Missouri",
  'MT' => "Montana",
  'NE' => "Nebraska",
  'NV' => "Nevada",
  'NH' => "New Hampshire",
  'NJ' => "New Jersey",
  'NM' => "New Mexico",
  'NY' => "New York",
  'NC' => "North Carolina",
  'ND' => "North Dakota",
  'OH' => "Ohio",
  'OK' => "Oklahoma",
  'OR' => "Oregon",
  'PA' => "Pennsylvania",
  'RI' => "Rhode Island",
  'SC' => "South Carolina",
  'SD' => "South Dakota",
  'TN' => "Tennessee",
  'TX' => "Texas",
  'UT' => "Utah",
  'VT' => "Vermont",
  'VA' => "Virginia",
  'WA' => "Washington",
  'WV' => "West Virginia",
  'WI' => "Wisconsin",
  'WY' => "Wyoming"
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
    "min" => 500,
    "max" => 750,
  ],
  [
    "min" => 750,
    "max" => 1000,
  ],
  [
    "min" => 1000,
    "max" => 1250,
  ],
  [
    "min" => 1250,
    "max" => 1500,
  ],
  [
    "min" => 1500,
    "max" => 1750,
  ],
  [
    "min" => 1750,
    "max" => 2000,
  ],
  [
    "min" => 2000,
    "max" => 2250,
  ],
  [
    "min" => 2250,
    "max" => 2500,
  ],
  [
    "min" => 2500,
    "max" => 2750,
  ],
  [
    "min" => 2750,
    "max" => 3000,
  ],
  [
    "min" => 3000,
    "max" => 3500,
  ],
  [
    "min" => 3500,
    "max" => 4000,
  ],
  [
    "min" => 4000,
    "max" => 5000,
  ],
  [
    "min" => 5000,
    "max" => 7500,
  ],
  [
    "min" => 7500,
    "max" => 0,
  ],
];

