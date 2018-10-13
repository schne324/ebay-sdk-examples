<?php

require __DIR__.'/../vendor/autoload.php';
$config = require __DIR__.'/../configuration.php';

use \DTS\eBaySDK\Constants;
use \DTS\eBaySDK\Product\Services;
use \DTS\eBaySDK\Product\Types;
use \DTS\eBaySDK\Product\Enums;

$service = new Services\ProductService([
  'credentials' => $config['production']['credentials'],
  'globalId'    => Constants\GlobalIds::MOTORS
]);

/**
 * Configuration
 */
$inputFile = 'product/input/ymm.csv';
$outputFile = 'product/output/ymm-test1234.csv';
$outputArray = array();
$headers = array(
  'EPID', 'PartNumber', 'Engine', 'Make', 'Model', 'Trim', 'Year', 'Notes'
);

array_push($outputArray, $headers);

$inputArray = array_map('str_getcsv', file($inputFile));

array_walk($inputArray, function(&$a) use($inputArray) {
  $a = array_combine($inputArray[0], $a);
});
// remove headers
array_shift($inputArray);
$idx = 1;
$total = count($inputArray);

foreach ($inputArray as $row) {
  print("- - - - - - - - - - Request ${idx} of ${total} - - - - - - - - - -\n");
  $epid = $row['EPID'];
  $partNumber = $row['PartNumber'];

  $request = new Types\GetProductCompatibilitiesRequest();
  $request->dataset = ['DisplayableProductDetails'];
  $request->productIdentifier = new Types\ProductIdentifier();
  $request->productIdentifier->ePID = $epid;
  $request->paginationInput = new Types\PaginationInput();
  $request->paginationInput->entriesPerPage = 100;
  $pageNum = 1;

  do {
    $request->paginationInput->pageNumber = $pageNum;
    $response = $service->getProductCompatibilities($request);

    if (isset($response->errorMessage)) {
      foreach ($response->errorMessage->error as $error) {
        printf(
          "%s: %s\n\n",
          $error->severity=== Enums\ErrorSeverity::C_ERROR ? 'Error' : 'Warning',
          $error->message
        );

        array_push($outputArray, array_fill(0, count($headers), ""));
      }
    }

    if ($response->ack !== 'Failure') {
      foreach ($response->compatibilityDetails as $details) {
        $row = array($epid, $partNumber);
        $notes = array();

        // Engine / Make / Model / Trim / Year
        foreach ($details->productDetails as $detail) {
          foreach ($detail->value as $value) {
            if (isset($value->number)) {
              array_push($row, $value->number->value);
            } elseif (isset($value->text)) {
              array_push($row, $value->text->value);
            } elseif (isset($value->URL)) {
              array_push($row, $value->URL->value);
            } else {
              array_push($row, "");
            }
          }
        }

        // Notes (";" separated)
        foreach ($details->notes->noteDetails as $note) {
          if (isset($note->value)) {
            foreach ($note->value as $val) {
              if ($val->text->value) {
                array_push($notes, $val->text->value);
              }
            }
          }
        }

        array_push($row, join('; ', $notes));
        array_push($outputArray, $row);
      }
    }

    $pageNum += 1;
  } while (isset($response->compatibilityDetails) && $pageNum <= $response->paginationOutput->totalPages);

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
