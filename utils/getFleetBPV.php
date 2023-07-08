#!/usr/bin/php -q
<?php
###
# Grabs the high/low/and average economy of a game
###

if( ! isset($argv[1]) )
{
  echo "\nGrabs the high/low/and average economy of a game.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]."  GAME\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../objects/gameDB.php" );

$game = intval($argv[1]); // the game to draw from

$turnQuery = "SELECT currentturn FROM sfbdrama_game WHERE id=$game";
$empireQuery = "SELECT id,textName,storedEP,turn FROM sfbdrama_empire WHERE game=2 AND turn=($turnQuery)";
$fleetQuery = "SELECT SUM(BPV) AS fleet FROM sfbdrama_ship ships LEFT JOIN ";
$fleetQuery .= "sfbdrama_shipdesign design ON design.id=ships.design WHERE ";
$fleetQuery .= "ships.game=$game AND ships.turn=($turnQuery) and ships.empire="; // empire ID appended to line

$maxFleet = 0;
$maxTotal = 0;
$minFleet = 65000;
$minTotal = 65000;

$database = gameDB::giveme();

  $result = $database->genquery( $empireQuery, $empires );
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get empire list: ".$database->error_string."\n";
    else
      echo "Could not get empire list.\n";
    exit(1);
  }

  foreach( $empires as $empireData )
  {
    $result = $database->genquery( $fleetQuery.$empireData['id'], $BPV );
    if( ! $result )
    {
      if( $SHOW_DB_ERRORS )
        echo "Could not get BPVs for ".$empireData['textName'].": ".$database->error_string."\n";
      else
        echo "Could not get BPVs for ".$empireData['textName'].".\n";
      exit(1);
    }

    if( empty($BPV[0]['fleet']) )
    {
      echo "BPV for ".$empireData['textName']." is empty.\n";
    }
    
    if( $BPV[0]['fleet'] > $maxFleet )
      $maxFleet = $BPV[0]['fleet'];
    if( $BPV[0]['fleet']+$empireData['storedEP'] > $maxTotal )
      $maxTotal = $BPV[0]['fleet']+$empireData['storedEP'];
    if( $BPV[0]['fleet'] < $minFleet )
      $minFleet = $BPV[0]['fleet'];
    if( $BPV[0]['fleet']+$empireData['storedEP'] < $minTotal )
      $minTotal = $BPV[0]['fleet']+$empireData['storedEP'];
  }

echo "\nGame $game, turn ".$empireData['turn']."\n";
echo "Fleet BPV: $minFleet < > $maxFleet\n";
echo "Total BPV: $minTotal < > $maxTotal\n";

?>
