#!/usr/bin/php -q
<?php
###
# Attempts to reconstruct the economy of the last couple of turns for an empire
###
# Outputs
# - (string) a description of the empire's economy
###

$DEBUG = false; // show debug information with the output

if( ! isset($argv[2]) )
{
  echo "\nAttempts to reconstruct the economy of the last couple of turns for an empire.\n\n";
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

$gameID = intval($argv[1]);
$raceName = $argv[2];
$empID = 0;
// fill in $empID if given
if( isset($argv[3]) && ! empty($argv[3]) )
  $empID = intval($argv[3]);
// if the second argument is numeric and there was no third argument, then assume the second was the empire ID
else if( is_numeric($argv[2]) )
{
  $empID = intval($argv[2]);
  $raceName = "";
}

if( $DEBUG )
  echo "Empire ID: ".$empID."\n";

$database = DataBase::giveme();
$currentTurn = 0; // turn number of current turn
$lastTurn = 0; // turn number of previous turn
$currentIncome = 0; // income from the current turn
$lastIncome = 0; // income from last turn
$currentStoredEP = 0; // stored EPs from current turn
$lastStoredEP = 0; // stored EPs from previous turn
$builtShips = array(); // format is 0=>array("designator","cost")
$refitShips = array(); // format is 0=>array("designator","cost")
$encounters = array(); // format is 0=>array("name+num","won/lost","EP change", "income change")
$output = "";
$mathString = ""; // presents the math of the economy as a simple equation

// get the turn info
if( ! empty($raceName) ) 
  $raceName = $database->wash($raceName);
$query = "select MAX(turn) as turn,id from ".Empire::table." WHERE game=$gameID and "; // this gets data from first entry
if( $empID )
  $query .= "id=$empID";
else
  $query .= "race='$raceName'";
$dbData = dbQuery( $query );
$currentTurn = $dbData['turn'];
$lastTurn = ($currentTurn-1);
$empID = $dbData['id']; // set this in case we didn't have it
if( $DEBUG )
{
  echo "Turn: ".$currentTurn."\n";
  echo "Empire ID: ".$empID."\n";
}


// get the most recent EP stockpile
$query = "select storedEP from ".Empire::table." WHERE game=$gameID and id=$empID and turn=$currentTurn";
$dbData = dbQuery( $query );
$currentStoredEP = $dbData['storedEP'];
if( $DEBUG )
  echo "Stored EP: ".$currentStoredEP."\n";

// get previous empire info
$query = "select income,storedEP from ".Empire::table." WHERE game=$gameID and id=$empID and turn=$lastTurn";
$dbData = dbQuery( $query );
$currentIncome = $dbData['income']; // this is because the previous income is modified by rewards before being applied
$lastStoredEP = intval($dbData['storedEP']);
if( $DEBUG )
{
  echo "Income: ".$currentIncome."\n";
  echo "Last Stored EP: ".$lastStoredEP."\n";
}

// get the previous income
$query = "select income from ".Empire::table." WHERE game=$gameID and id=$empID and turn=".($lastTurn-1);
$dbData = dbQuery( $query );
$lastIncome = $dbData['income']; // this is because the previous income is modified by rewards before being applied
if( $DEBUG )
  echo "Previous Income: ".$lastIncome."\n";

// assemble $builtShips
$orders = new Orders( array( 'game'=>$gameID, 'empire'=>$empID, 'turn'=>$lastTurn, ) );
$orders->getID( 'empire', 'turn' );
$orders->read();
$orderString = $orders->decodeOrders();

foreach( $orderString['builds'] as $shipData )
{
  $query = "select designator, empire, bpv from ".ShipDesign::table." WHERE id={$shipData['ship']}";
  $dbData = dbQuery( $query );

  $builtShips[] = array( $dbData['empire']." ".$dbData['designator'], $dbData['bpv'] );
}
foreach( $orderString['conversions'] as $shipData )
{
  $subquery = "select design from ".Ship::table." WHERE id={$shipData['ship']} and turn=$lastTurn";
  $query = "select bpv from ".ShipDesign::table." WHERE id=($subquery)";
  $dbData = dbQuery( $query );
  $oldCost = $dbData['bpv'];

  $query = "select designator, empire, bpv from ".ShipDesign::table." WHERE id={$shipData['design']}";
  $dbData = dbQuery( $query );
  $newCost = $dbData['bpv'];
  $refitShips[] = array( $dbData['empire']." ".$dbData['designator'], abs($newCost-$oldCost) );
  if( $DEBUG )
  {
    echo "Previous Cost: ".$oldCost."\n";
    echo "New Cost: ".$newCost."\n";
  }
}

// assemble $encounters
$query = "select id,scenario,status,playerA from ".Encounter::table." WHERE game=$gameID and turn=$lastTurn and (playerA=$empID";
$query .= " or playerB=$empID) and (status=".Encounter::PLAYER_A_VICTORY." or status=".Encounter::PLAYER_A_DEFEATED.")";
$dbData = dbQuery( $query );

//print_r($query);exit();

foreach( $dbData as $data )
{
  $scenarioData = $SCENARIOS[ $data['scenario'] ];
  $name = $scenarioData[0]." #{$data['id']}";
  $type = "";
  $num = 0;
  $ep = 0;
  $income = 0;

  // determine what reward the player gets
  if( $data['status'] == Encounter::PLAYER_A_VICTORY )
  {
    // defender won
    if( $data['playerA'] == $empID )
    {
      // player is defender
      $winStatus = "Won";
      $rewardString = $scenarioData[2];
    }
    else
    {
      // player is attacker
      $winStatus = "Lost";
      $rewardString = "";
    }
  }
  else if( $data['status'] == Encounter::PLAYER_A_DEFEATED )
  {
    // defender lost
    if( $data['playerA'] == $empID )
    {
      // player is defender
      $winStatus = "Lost";
      $rewardString = $scenarioData[3];
    }
    else
    {
      // player is attacker
      $winStatus = "Won";
      $rewardString = $scenarioData[4];
    }
  }

  // determine the number of the reward
  if( $rewardString && preg_match( '/^([a-zA-Z]+)(\+?\-?\d+)?/', $rewardString, $matches ) )
  {
    $type = $matches[1];
    if( isset($matches[2]) )
      $num = intval( $matches[2] );
    switch( $type )
    {
    case "INCOME":
      $income = $num;
      break;
    case "EP":
      $ep = $num;
      break;
    }
  }

  // format is 0=>array("name+num","won/lost","EP change", "income change")
  $encounters[] = array( $name, $winStatus, $ep, $income );
}

$mathString = "$lastStoredEP [in storage] "; // presents the math of the economy as a simple equation

$output .= "Turn $lastTurn Income: $lastIncome\n";
$output .= "Turn $lastTurn Stockpile: $lastStoredEP\n";
$output .= "Turn $currentTurn Income: $currentIncome\n";
$output .= "Turn $currentTurn Stockpile: $currentStoredEP\n";
foreach( $builtShips as $shipData )
{
  $mathString .= "- {$shipData[1]} [build] ";
  $output .= "Built a {$shipData[0]} for {$shipData[1]} EPs\n";
}
foreach( $refitShips as $shipData )
{
  $mathString .= "- {$shipData[1]} [conversion] ";
  $output .= "Converted to a {$shipData[0]} for {$shipData[1]} EPs\n";
}
foreach( $encounters as $encounterData )
{
  $output .= $encounterData[1]." the {$encounterData[0]} for ";
  if( ! empty($encounterData[3]) )
  {
    $output .= $encounterData[3]." income\n";
  }
  else
  {
    if( $encounterData[2] != "0" )
    {
      if( strpos($encounterData[2],"-") === false )
        $mathString .= "+ ";
      $mathString .= $encounterData[2]." [reward] ";
    }
    $output .= $encounterData[2]." EPs\n";
  }
}

$mathString .= "+ $currentIncome [income] ~= $currentStoredEP [new in storage]";
$mathString = wordwrap( $mathString, 80, "\n", false );

echo "$output\n$mathString\n";

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
