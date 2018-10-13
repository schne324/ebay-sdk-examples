<?php

require __DIR__.'/../utils/Colors.php';
require __DIR__.'/../vendor/autoload.php';
$config = require __DIR__.'/../configuration.php';

use \DTS\eBaySDK\Constants;
use \DTS\eBaySDK\Product\Services;
use \DTS\eBaySDK\Product\Types;
use \DTS\eBaySDK\Product\Enums;

$colors = new Colors();
$service = new Services\ProductService([
  'credentials' => $config['production']['credentials'],
  'globalId'    => Constants\GlobalIds::MOTORS
]);

/**
 * Configuration
 */
$inputFile = 'product/input/10-11-18/parts-depot.csv';
$outputFile = 'product/output/10-11-18/parts-depot.csv';
$outputArray = array();
$headers = array(
  '*Action(SiteID=eBayMotors|Country=US|Currency=USD|Version=403|CC=UTF-8)',
  'ItemID',
  'Relationship',
  'RelationshipDetails'
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
  $complete = round((($idx / $total) * 100), 2);

  $epid = $row['EPID'];
  $partNumber = $row['PartNumber'];
  $xofy = "(${idx}/${total})";

  print(
    $colors->getColoredString(
      "${complete}% complete",
      "light_cyan"
    )
    ." "
    .$colors->getColoredString(
      "${xofy}",
      "blue"
    )
    ." "
    .$colors->getColoredString(
      "[Part Number: ${partNumber}]",
      "yellow"
    )
    ."\r"
  );

  $request = new Types\GetProductCompatibilitiesRequest();
  $request->dataset = ['DisplayableProductDetails'];
  $request->productIdentifier = new Types\ProductIdentifier();
  $request->productIdentifier->ePID = $epid;
  $request->paginationInput = new Types\PaginationInput();
  $request->paginationInput->entriesPerPage = 100;
  $pageNum = 1;

  // push the initial row (action/itemid)
  array_push($outputArray, array('Revise', $partNumber));

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
        $row = array("", "", 'Compatibility');
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
        if (isset($details->notes) && isset($details->notes->noteDetails)) {
          foreach ($details->notes->noteDetails as $note) {
            if (isset($note->value)) {
              foreach ($note->value as $val) {
                if ($val->text->value) {
                  array_push($notes, $val->text->value);
                }
              }
            }
          }
        }

        array_push($row, join('; ', $notes));

        $year = $row[7];
        $make = $row[4];
        $model = $row[5];
        $engine = $row[3];
        $notes = $row[8];

        array_push($outputArray, array(
          $row[0], $row[1], $row[2],
          "Year=${year}|Make=${make}|Model=${model}|Engine=${engine}|Notes=${notes}"
        ));
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

?>
