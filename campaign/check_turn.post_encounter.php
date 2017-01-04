#!/usr/bin/env php
<?php
/*
accept encounter results (win/loss, cripples/destroyed) (via module)
accept build orders, conversion orders, repair orders (via module). Converted/refit/repaired ships may not participate in bidding.
Cripple and Destroy units
Capture Units
assign rewards based on encounter results.
track experience (if needed). Awards crew quality.
advance turn
build / convert units
draw encounters.
Announce encounters (via module) to all players.
*/

if( ! isset($argv[1]) )
{
  echo "\nEnd of encounter processor script.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GameIdentifier [TurnNumber]\n";
  echo "If TurnNumber is omitted, it will use the latest turn as reported by the game.\n\n";
  exit(1);
}

//require_once( dirname(__FILE__) . "/../Login/Login_common.php" );
require_once( dirname(__FILE__) . "/../campaign_config.php" );
require_once( dirname(__FILE__) . "/../objects/game.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../objects/process_turn.php" );

date_default_timezone_set($TIMEZONE);

// set the database user as the superuser
gameDB::giveme( true );

$gameID = intval($argv[1]);
$inputFeed = "";
$moduleInFile = "";	// The module that gives us our input
$moduleInRoutine = "";	// The subroutine that gives us our input
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

// ensure that only the bidding orders that exist at this point
$orderObjs = new ObjList( "orders", $game->modify('id'), $turnNum );
foreach( $orderObjs->objByID as &$commands )
  $commands->pruneToBids();
$orderObjs->write();
unset($orderObjs);

// load up the input and output modules
$moduleInRoutine = $game->modify('moduleEncountersIn');
$moduleInUpdate = "";
$moduleInFile = $modulePath.$moduleInRoutine.".php";
// Load up the input module and use it
if( is_readable( $moduleInFile ) )
{
  require_once( $moduleInFile );
  // read in the data from the input module
  $inputFeed = $moduleInRoutine( $game, $turnNum );
  $moduleInUpdate = $moduleInRoutine."Update";

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
$process->DISPLAY_ODDS = false;
$process->outputDisplay = "<br>".date(DATE_COOKIE).":\n";

// Error-check the orders
list( $status, $output ) = $process->performPostEncounterChecks( $inputFeed );
if( ! $status )
  $output = "<br>Errors were found!\n" . $output;
else
  $output = "<br>No errors were found.\n" . $output;

incrementalLogOutput( $output );


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

  if( ! empty($LOGFILE) && file_exists($LOGFILE) && is_file($LOGFILE) && is_writable($LOGFILE) )
    file_put_contents( $LOGFILE, $output, FILE_APPEND|LOCK_EX );
  echo $output;
//    ob_flush();
  $output = "";
}
?>
