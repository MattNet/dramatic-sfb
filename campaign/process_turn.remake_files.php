#!/usr/bin/php -q
<?php
/*
Program accepts ship dispersements (via module).
Program auto-handles one-sided or no-sided encounters
Program announces Full Scenario Details (which include ships involved) for all scenarios (via module), including auto-handled scenarios (but announces those as finished).
*/

if( ! isset($argv[1]) )
{
  echo "\nRecreates the data files.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GameIdentifier [TurnNumber]\n";
  echo "If TurnNumber is omitted, it will use the latest turn as reported by the game.\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../campaign_config.php" );
require_once( dirname(__FILE__) . "/../objects/game.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
//require_once( dirname(__FILE__) . "/../objects/process_turn.php" );

date_default_timezone_set($TIMEZONE);

$gameID = intval($argv[1]);

$moduleOutFile = "";	// The module that gives us our output
$moduleOutRoutine = "";	// The subroutine that gives us our output
$modulePath = dirname(__FILE__) . "/../modules/";
$turnNum = 0;

// load up the game object
$game = new Game( $gameID );
$game->read();

// set the turn number
if( isset($argv[2]) )
  $turnNum = intval($argv[2]);
else
  $turnNum = intval( $game->modify('currentTurn') );

if( $game->modify('turnSection') == GAME::TURN_SECTION_EARLY )
  $moduleOutRoutine = $game->modify('moduleEncountersOut');
else
  $moduleOutRoutine = $game->modify('moduleBidsOut');

// retrieve the module file
$moduleOutFile = $modulePath.$moduleOutRoutine.".php";
if( is_readable( $moduleOutFile ) )
  require_once( $moduleOutFile );


// get the feeds that gives the module it's data
$encounterObjs = new ObjList( "encounter", $game->modify('id'), $turnNum );
$shipObjs = new objList( "ship", $game->modify('id'), $turnNum );
$orderObjs = new ObjList( "orders", $game->modify('id'), $turnNum );
    $encounterBids = array(); // list of encounters that have units assigned to them

// build a list of encounters that have 1 or more bids
foreach( $orderObjs->objByID as $orderID=>$orderObj )
{
  $playerOrders = $orderObj->decodeOrders();
  foreach( $playerOrders['bids'] as $shipBids )
    if( ! empty( $shipBids['ship'] ) )
    {
      if( ! isset( $encounterBids[ $shipBids['encounter'] ] ) )
        $encounterBids[ $shipBids['encounter'] ] = array();
      $encounterBids[ $shipBids['encounter'] ][ $orderObj->modify('empire') ][] = $shipBids['ship'];
    }
}

// set the ships used in each encounter
foreach( $encounterObjs->objByID as $encounterID=>&$encObj )
{
  // if no units were bid to the encounter, turn the encounter ship-lists to 
  // empty strings and then go on to another encounter
  if( ! isset($encounterBids[$encounterID]) )
  {
    $encObj->modify( 'playerAShips', "" );
    $encObj->modify( 'playerBShips', "" );
    continue;
  }

  // create the unit list
  $playerShips = [
      $encObj->modify('playerA') => array(),
      $encObj->modify('playerB') => array()
  ];
  // fill $playerShips with a list of the unit in the encounter
  foreach( $encounterBids[$encounterID] as $playerID=>$shipArray )
  {
    $playerShips[$playerID] = array();
    foreach( $shipArray as $shipID )
      $playerShips[$playerID][] = $shipObjs->objByID[$shipID]->specs['designator'];
  }
  // fill the encounter with the unit list
  $encObj->modify( 'playerAShips', implode($playerShips[$encObj->modify('playerA')]) );
  $encObj->modify( 'playerBShips', implode($playerShips[$encObj->modify('playerB')]) );
}

//print_r( $encounterObjs );exit();

//exit();

// broadcast the remaining encounters
if( function_exists($moduleOutRoutine) )
  $moduleOutRoutine( $encounterObjs );

?>
