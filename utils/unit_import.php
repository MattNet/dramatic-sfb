#!/usr/bin/php -q
<?php

$UNIT_FILENAME = dirname(__FILE__) . "/shipdesign.update.xls";
$longOutput = false; // true if an HTML table of the imported data is put in output log

require_once( dirname(__FILE__) . "/spreadsheet_object.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );
require_once( dirname(__FILE__) . "/../campaign_config.php" );

date_default_timezone_set($TIMEZONE);
$logFile = $LOGFILE; // the file to put the status of this file

// Read the spreadsheet file
$dataSource = new Spreadsheet($UNIT_FILENAME);
if( ! empty( $dataSource->error_string ) )
  die( "Could not load spreadsheet file.\n".$dataSource->error_string );
$sheetOne = $dataSource->readBulkAssoc( spreadsheet::SHEET1 );
if( ! empty( $dataSource->error_string ) )
  die( "Could not read spreadsheet file, sheet 1.\n".$dataSource->error_string );

// Check for missing columns in the ship-data sheet
$dataColumnNames = array_shift($sheetOne);
$tempInstance = new ShipDesign();
$wrongKeyCompare = array_diff_key( $tempInstance->values(), $dataColumnNames );
$tempInstance->modify( 'autowrite', false );
$tempInstance->__destruct();
unset($tempInstance);
if( ! empty($wrongKeyCompare) )
  die( "Some column names are missing from sheet one of '$UNIT_FILENAME': ".implode( ",", array_keys($wrongKeyCompare) ) );

// Import the data and collect the print-out of what was imported
$output = date(DATE_COOKIE) .": <table width='100%'>\n";
$output .= handleEachUnit( "ShipDesign", $sheetOne );
$output .= "</table>\n";

if( ! $longOutput )
  $output = "";
// perform final print-out
$output .= date(DATE_COOKIE) .": Import Complete. \n";
$output .= date(DATE_COOKIE) .": There were ".count($sheetOne)." ships imported.\n";
incrementalLogOutput( $output );

###
# Imports and assembles the printout for each unit in the given sheet
###
# Args are:
# - (string) The object name that is being imported
# - (string) The sheet data to be imported
# Returns:
# - (string) The resulting printout gathered from each object
###
function handleEachUnit( $objectName, $sheetData )
{
  $output = "";
  $headingFlag = false;
  $isEvenFlag = true;
  foreach( $sheetData as $keys=>$array )
  {
    if( empty($array) )
      continue;
    $unit = new $objectName( $array );
    if( $unit == false )
      die( "Could not create unit during import.\n".$unit->error_string );
    $result = $unit->create();
    if( $result == false )
      die( "Could not write unit during import.\n".$unit->error_string );
    if( ! $headingFlag )
    {
      $output .= $unit->HTMLform( "#ffcccc", true );
      $headingFlag = true;
    }
    if( $isEvenFlag )
    {
      $output .= $unit->HTMLform( "#ccccff" );
      $isEvenFlag = false;
    }
    else
    {
      $output .= $unit->HTMLform( "#ffffff" );
      $isEvenFlag = true;
    }
    $unit->__destruct();
    unset( $unit );
  }
  return $output;
}


###
# Adds the given output to the logging method
###
# If $logfile is a valid file, then will put the output there
# Else will put it in stdout
# Arguments:
# - (string) The item to add to the script output
# Returns:
# - none
###
function incrementalLogOutput( &$output )
{
  global $logFile;
  if( empty($output) )
    return;
  if( file_exists($logFile) && is_file($logFile) && is_writable($logFile) )
  {
    file_put_contents( $logFile, $output, FILE_APPEND|LOCK_EX );
  }
  else
  {
    echo $output;
//    ob_flush();
  }
  $output = "";
}

?>
