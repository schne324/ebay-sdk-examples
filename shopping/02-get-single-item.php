<?php
/**
 * Copyright 2016 David T. Sadler
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Include the SDK by using the autoloader from Composer.
 */
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../utils/Progress.php';
/**
 * Include the configuration values.
 *
 * Ensure that you have edited the configuration.php file
 * to include your application keys.
 */
$config = require __DIR__.'/../configuration.php';

/**
 * The namespaces provided by the SDK.
 */
use \DTS\eBaySDK\Shopping\Services;
use \DTS\eBaySDK\Shopping\Types;
use \DTS\eBaySDK\Shopping\Enums;

/**
 * Create the service object.
 */
$service = new Services\ShoppingService([
  'credentials' => $config['production']['credentials']
]);

$inputFile = 'shopping/input/07-31-18/ExtractMPN.csv';
$outputFile = 'shopping/output/07-31-18/ExtractMPN.csv';
$outputArray = array();
$headers = array('Item ID', 'Manufacturer Part Number', 'error');

array_push($outputArray, $headers);

$inputArray = array_map('str_getcsv', file($inputFile));

array_walk($inputArray, function(&$a) use($inputArray) {
  $a = array_combine($inputArray[0], $a);
});

// remove headers
array_shift($inputArray);

$idx = 1;
$total = count($inputArray);
$progress = new Progress($inputArray);

foreach ($inputArray as $row) {
  $progress->log($idx);
  $itemID = $row['Item ID'];
  $request = new Types\GetSingleItemRequestType();
  $request->ItemID = $itemID;
  $request->IncludeSelector = 'ItemSpecifics,Variations,Compatibility,Details';
  $response = $service->getSingleItem($request);
  $mpn = '';
  $err = '';

  if (isset($response->Errors)) {
    foreach ($response->Errors as $error) {
      $err = $error->ShortMessage;
    }
  }

  if ($response->Ack !== 'Failure') {
    $item = $response->Item;

    if (isset($item->ItemSpecifics)) {
      foreach ($item->ItemSpecifics->NameValueList as $nameValues) {
        if ($nameValues->Name === "Manufacturer Part Number") {
          $mpn = implode(', ', iterator_to_array($nameValues->Value));
        }
      }
    }
  }

  array_push($row, $mpn);
  array_push($row, $err);
  array_push($outputArray, $row);
  $idx++;
}

print("\nAll requests complete\n");

// write the csv
$fp = fopen($outputFile, 'w');

foreach ($outputArray as $fields) {
  fputcsv($fp, $fields);
}

fclose($fp);
print("\n${outputFile} written");
