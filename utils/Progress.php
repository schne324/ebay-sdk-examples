<?php
require __DIR__.'/./Colors.php';

class Progress {
  public function __construct($inputArray) {
    $this->total = count($inputArray);
    $this->colors = new Colors();
  }

  public function log($index, $additional = "") {
    $complete = round((($index / $this->total) * 100), 2);
    $total = $this->total;
    $xofy = "(${index}/${total})";

    print(
      $this->colors->getColoredString(
        "${complete}% complete",
        "light_cyan"
      )
      ." "
      .$this->colors->getColoredString(
        "${xofy}",
        "blue"
      )
      ." "
      .$this->colors->getColoredString(
        "${additional}",
        "yellow"
      )
      ."\r"
    );
  }
}

?>
