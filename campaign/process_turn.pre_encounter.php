#!/usr/bin/env php
<?php
/*
Program accepts ship dispersements (via module).
Program auto-handles one-sided or no-sided encounters
Program announces Full Scenario Details (which include ships involved) for all scenarios (via module), including auto-handled scenarios (but announces those as finished).
*/

if( ! isset($argv[1]) )
{
  echo "\nEnd of bidding processor script.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GameIdentifier [TurnNumber]\n";
  echo "If TurnNumber is omitted, it will use the latest turn as reported by the game.\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../campaign_config.php" );
require_once( dirname(__FILE__) . "/../objects/game.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../objects/process_turn.php" );

$STOP_ON_ERRORS = false;	// Stop processing if the order checks don't pan out

date_default_timezone_set($TIMEZONE);

$gameID = intval($argv[1]);
$inputFeed = "";
$moduleInFile = "";	// The module that gives us our input
$moduleInRoutine = "";	// The subroutine that gives us our input
$moduleOutFile = "";	// The module that gives us our output
$moduleOutRoutine = "";	// The subroutine that gives us our output
$modulePath = dirname(__FILE__) . "/../modules/";
$turnNum = 0;
$statusFeed = "";

// load up the game object
$game = new Game( $gameID );
$game->read();

// set the turn number
if( isset($argv[2]) )
  $turnNum = intval($argv[2]);
else
  $turnNum = intval( $game->modify('currentTurn') );

// remove all existing orders for this turn
$input = new ObjList( 'orders', $gameID, $turnNum );
foreach( $input->objByID as &$commands )
{
  $commands->destroy();
  $commands->modify( 'autowrite', false);
  unset( $commands );
}
unset( $input );

// load up the input and output modules
$moduleInRoutine = $game->modify('moduleBidsIn');
$moduleOutRoutine = $game->modify('moduleBidsOut');
$moduleInUpdate = "";
$moduleInFile = $modulePath.$moduleInRoutine.".php";
$moduleOutFile = $modulePath.$moduleOutRoutine.".php";
if( is_readable( $moduleInFile ) )
{
  require_once( $moduleInFile );
  $inputFeed = $moduleInRoutine( $game, $turnNum );
  $moduleInUpdate = $moduleInRoutine."Update";
}
if( is_readable( $moduleOutFile ) )
{
  require_once( $moduleOutFile );
}

/*
###
ModuleInRoutine goes outside the script and fetches the bidding orders from wherever (e.g. an email inbox, or a place on the hard disk)
ModuleInRoutine makes those orders available to the script as orders objects
ModuleInRoutine is responsible for writing those bidding orders into the database
Those orders should be in the database at this point in the script
###
*/

// load up the turnProcess object
$process = new ProcessTurn( $gameID, $turnNum );
$process->DISPLAY_PROGRESS = true;
$process->outputDisplay = "<br>".date(DATE_COOKIE).":\n";

// Error-check the orders
list( $status, $output ) = $process->performPostOrderChecks( $inputFeed );
$process->outputDisplay .= $output;
incrementalLogOutput( $process->outputDisplay );
if( ! $status && $STOP_ON_ERRORS )
  exit();

// Adjucate the bidding and set up the scenarios
$statusFeed = $process->autoAdjudicateEasy( $inputFeed );
incrementalLogOutput( $process->outputDisplay );

$process->setTurnStatus( Game::TURN_SECTION_MID );

// perform any updates to external sources
if( function_exists($moduleInUpdate) )
  $moduleInUpdate( $statusFeed );

// broadcast the remaining encounters
if( function_exists($moduleOutRoutine) )
  $moduleOutRoutine( $statusFeed );

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
  global $LOGFILE;
  if( empty($output) )
    return;

  if( file_exists($LOGFILE) && is_file($LOGFILE) && is_writable($LOGFILE) )
  {
    file_put_contents( $LOGFILE, $output, FILE_APPEND|LOCK_EX );
  }
  echo $output;
//    ob_flush();
  $output = "";
}
?>
