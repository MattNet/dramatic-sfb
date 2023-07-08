#!/usr/bin/php -q
<?php
###
# Attempts to reconstruct the economy of the last couple of turns for all empires
###
# Outputs
# - (string) a description of the empire's economy
###

if( ! isset($argv[1]) )
{
  echo "\nAttempts to reconstruct the economy of the last couple of turns for all empires.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GAME_ID EMPIRE_RACE_NAME [EMPIRE_ID]\n\n";
  exit(1);
}


require_once( dirname(__FILE__) . "/../Login/Login_config.php" );
require_once( dirname(__FILE__) . "/../scenarios/scenarios.php" );
require_once( dirname(__FILE__) . "/../objects/Login_database.php" );
require_once( dirname(__FILE__) . "/../objects/empire.php" );
require_once( dirname(__FILE__) . "/../objects/encounter.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../objects/ship.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );

$command = "./reconstruct_economy.php";
$gameID = intval($argv[1]);
$raceName = "";
$empID = 0;
$currentTurn = 0; // turn number of current turn

$database = DataBase::giveme();
$output = array();
$DBout = "";

// get the turn info
$query = "select MAX(turn) as turn from ".Empire::table." WHERE game=$gameID";
$DBout = dbQuery( $query );
$currentTurn = $DBout['turn'];

// get the player list
$query = "select id,race from ".Empire::table." WHERE game=$gameID and turn=$currentTurn";
$DBout = dbQuery( $query );

// loop through the query and collect the output from the shell command into $output
foreach( $DBout as $index )
{
  array_push( $output, "\n<<".$index["race"].">>\n" );
  exec( "$command $gameID ".$index["race"]." ".$index["id"], $output );
}

$output = implode("\n",$output);
$output = wordwrap( $output, 80, "\n", false );

echo "$output\n";

###
# handles the database queries
###
# Inputs:
# (string) the query to ask the database
# Outputs:
# (array) An associative array of the data from the DB
###
function dbQuery( $query )
{
  global $database, $output;
  $data = "";
  $result = $database->genquery( $query, $data );
  if( $result === false )
  {
    $output .= "Error in database: {$database->error_string}\n";
    echo $output;
    exit();
  }
  if( isset($data[1]) )
    return $data;
  else
    return $data[0];
}

?>
