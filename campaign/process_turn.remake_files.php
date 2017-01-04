#!/usr/bin/php -q
<?php

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

$moduleOutRoutine = $game->modify('moduleBidsOut');
$moduleOutFile = $modulePath.$moduleOutRoutine.".php";
if( is_readable( $moduleOutFile ) )
  require_once( $moduleOutFile );

$encounterObjs = new objList( "encounter", $this->gameID, $this->turnNum );

// remove all existing orders for this turn


// broadcast the remaining encounters
if( function_exists($moduleOutRoutine) )
  $moduleOutRoutine( $encounterObjs );


?>
