#!/usr/bin/php -q
<?php
###
# Creates a .CSV file to show the relative status's of the players in a game.
# Used to create relative-positioning graphs and other benchmarks of noteriety.
###
# Output .CSV file format:
# ROW A: The heading of the metric.
# - Metrics are: Fleet BPV, Raw Income, EP stockpiled, Total number of borders
# ROW B: Empire "race" name of position ("Klingon", "Federation", etc), repeated for each ROW A metric
# ROW C: Turn 1 values for the above
# ROW D+: Turn 2+ values for the above
# There is a blank column between ROW A blocks
# the first column is a turn # heading for rows C+ and blank for rows A & B
###



$OUTPUT_FILE = dirname(__FILE__) . "/status.csv"; // the file to put the csv output

if( ! isset($argv[1]) )
{
  echo "\nCreates a CSV file to show the relative status of each player.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." [Game ID Number]\n\n";
  exit(1);
}

$gameID = $argv[1];
$empires = array(); // list of empire names to use in the headings
$errors = ""; // error string from various subroutines
$headings = array(); // the headings to put at the start of the output file. $output does 
// not get this because some headings are unknown until the data has been gone through
$metrics = array(
             "BPV" =>	"FLEET BPV",
             "total borders" =>	"TOTAL BORDERS",
             "income" =>	"EP INCOME",
             "storedEP" =>	"EP STOCKPILE",
           ); // The titles of the metrics in the format of [empire object property name] => "title to display"
		// Special property names of "BPV" and "total borders" reference special metrics
$output = array(); // final output to the CSV file
$empireObjects = array(); // Holds the empire objects, by turn, then by key (the lookup is $objLookup)
$objLookup = array(); // a lookup array to go from empire_ID to $empireObjects key, indexed by turn then empire ID

require_once( dirname(__FILE__) . "/../login/Login_config.php" );
require_once( dirname(__FILE__) . "/../objects/gameDB.php" );
require_once( dirname(__FILE__) . "/../objects/game.php" );
require_once( dirname(__FILE__) . "/../objects/empire.php" );
require_once( dirname(__FILE__) . "/../objects/ship.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );

$database = gameDB::giveme();

// try to get the game's latest turn, store it at $gameData[0]['currentTurn']
$gameData = $database->getGameData( $gameID );
if( ! $gameData )
{
  echo "Could not get Game Object: ".$database->error_string."\n";
  exit(1);
}

// create an array of 1 to $gameData[0]['currentTurn'], then iterate through it
$range = range( 1, $gameData[0]['currentTurn'] );
// this is to load the empire objects by each turn and then to generate the headings
foreach( $range as $turnNum )
{
  $objects = "";
  // get the empire objects of the turn # $turnNum
  $result = $database->getAllGameTurn( $gameID, $turnNum, Empire::table, $objects );
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get Empire Objects: ".$database->error_string."\n";
    else
      echo "Could not get Empire Objects.\n";
    exit(1);
  }

  foreach( $objects as $key => $empireID )
  {
    // write the object and enter it in the lookup
    $empireObjects[$turnNum][$key] = readObject( "empire", $empireID, $turnNum );
    $objLookup[$turnNum][$empireID] = $key;

    // check for the empire race-name being in $headings
    if( ! in_array( $empireObjects[$turnNum][$key]->modify('race'), $headings ) )
      $headings[$empireID] = $empireObjects[$turnNum][$key]->modify('race');
  }
}

// Generate the main output
foreach( $range as $turnNum )
{
  $output[$turnNum] = array();

  // iterate over the major-headings: the metrics
  foreach( $metrics as $property => $title )
  {
    // iterate over the sub-headings: the empires
    foreach( $headings as $empID=>$raceName )
    {
      $outputKey = count($output[$turnNum]); // the key for the next new entry to $output for this turn

      if( ! isset($objLookup[$turnNum][$empID]) || empty($empireObjects[$turnNum][$objLookup[$turnNum][$empID]]) )
      {
        $output[$turnNum][$outputKey] = "";
        continue;
      }
      // create the info for the metric
      switch( $property )
      {
      case "BPV":
        $num = 0;
        // add the BPV from this player's fleet together
        $subquery = "select design from ".Ship::table." where empire=$empID and turn=$turnNum";
        $query = "select SUM(BPV) from ".ShipDesign::table." inner join ($subquery) ships on ".ShipDesign::table.".id=ships.design";

        $result = $database->genquery( $query, $num);
        if( ! $result )
        {
          if( $SHOW_DB_ERRORS )
            echo "Could not get fleet BPVs: ".$database->error_string."\n";
          else
            echo "Could not get fleet BPVs.\n";
          exit(1);
        }

        $output[$turnNum][$outputKey] = (int) $num[0]['SUM(BPV)'];
        break;
      case "total borders":
        // add all of the border-counts together
        $borders = $empireObjects[$turnNum][$objLookup[$turnNum][$empID]]->bordersDecode();
        $output[$turnNum][$outputKey] = 0;
        foreach( $borders as $num )
          $output[$turnNum][$outputKey] += (int) $num;
        break;
      default:
        // handle some property from the Empire object
        $output[$turnNum][$outputKey] = $empireObjects[$turnNum][$objLookup[$turnNum][$empID]]->modify($property) ;
        break;
      }
    }

    // add a blank entry to separate metrics
    $output[$turnNum][] = "";
  }
  $output[$turnNum] = implode( ",", $output[$turnNum] );
}

// put spacing into the heading arrays
$headingCount = count($headings);
$metricCount = count($metrics)-1;
$headingFilling = "";
$metricFilling = str_repeat( ",", $headingCount ); // this includes the blank column for spacing

$metrics = array_values($metrics);
array_push($headings,",");

foreach( $metrics as $key=>&$metricData )
{
  $headingFilling .= implode( ",", $headings );
  if( $key == $metricCount )
    continue;
  $metricData .= $metricFilling;
}

// assemble the output file
$metrics = implode( ",", $metrics );
array_unshift( $output, $metrics, $headingFilling );
$output = implode( "\n", $output );

if( ! file_put_contents( $OUTPUT_FILE, $output ) )
{
  echo "Could not write output file '$OUTPUT_FILE'\n";
  exit(1);
}

exit(0); // success!


###
# Loads a singular object
###
# Args are:
# - (string) The object name to load
# - (string) The ID of the object to load
# Returns:
# - (object) The requested object. returns false if it fails
###
function readObject( $objName, $ID, $turn )
{
  global $errors;
  $objName = ucfirst($objName);

  $obj = new $objName( array('id'=>$ID, 'turn'=>$turn ) );
  $result = $obj->read();
  // if the object doesn't exist
  if( ! $result )
  {
    $errors .= "Cannot read object '" . strtolower($objName) . "'.";
    return false;
  }
  $obj->modify('autowrite', false);

  return $obj;
}

?>
