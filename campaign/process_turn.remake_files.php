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
  $moduleOutRoutine = $game->modify('moduleBidsOut');
else
  $moduleOutRoutine = $game->modify('moduleEncountersOut');

$moduleOutFile = $modulePath.$moduleOutRoutine.".php";
if( is_readable( $moduleOutFile ) )
  require_once( $moduleOutFile );

$encounterObjs = new ObjList( "encounter", $game->modify('id'), $turnNum );

// broadcast the remaining encounters
if( function_exists($moduleOutRoutine) )
  $moduleOutRoutine( $encounterObjs );


?>
