#!/usr/bin/php -q
<?php
$FILENAME = dirname(__FILE__) . "/../scenarios/scenarios.php";
require_once( $FILENAME );

foreach($SCENARIOS as &$data)
  $data[1] = '';

print_r($SCENARIOS)

?>
