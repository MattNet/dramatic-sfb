<?php

$ADD_NEWLINES_TO_OUTPUT = false;	// adds HTML breaks to the script output if true
$ADVANCE_ENCOUNTERS_TEXT = "Advance Past Encounters";	// text for the encounter-advancement button
$ADVANCE_ORDERS_TEXT = "Advance Past Orders";	// text for the order-advancement button
$ADVANCE_PAST_BIDDING = dirname(__FILE__) . "/process_turn.pre_encounter.php"; // script to advance a game after the orders are in
$ADVANCE_PAST_ENCOUNTERS = dirname(__FILE__) . "/process_turn.post_encounter.php"; // script to advance a game after the encounters are in
$AUTO_ADVANCE_OPENING_GAME = true;	// set to true to automatically call $ADVANCE_PAST_ENCOUNTERS when opening a game
$CHECK_BIDDING = dirname(__FILE__) . "/check_turn.pre_encounter.php"; // script to advance a game after the orders are in
$CHECK_ENCOUNTERS = dirname(__FILE__) . "/check_turn.post_encounter.php"; // script to advance a game after the encounters are in
$CHECK_ENCOUNTERS_TEXT = "Check For Encounter Errors";	// text for the encounter-advancement button
$CHECK_ORDERS_TEXT = "Check For Order Errors";	// text for the order-advancement button
$GOTO_ON_FAIL = "/index.php";	// page to serve if we've had very serious errors
$GOTO_ON_BACK = "/campaign/menu.php";	// page to serve if we click on a game link
$GOTO_ON_ORDERS = "/campaign/orders.php";	// page to serve if we click on the orders link
$GOTO_ON_EMPIRE = "/campaign/empire.php";	// page to serve if we click on an empire link
$GOTO_ON_INTEREST = $_SERVER['PHP_SELF'];	// page to serve if we click on the "I'm Interested" link
$RENAME_GAME_TEXT = "renameGame";	// name input for renaming a game
$TEMPLATE_FILE = "./game.template";	// Template file to load
$UTILITY_CSV = dirname(__FILE__) . "/../utils/overallStatus.php";	// utility to generate an Overall-Status .CSV file
$UTILITY_REMAKE = dirname(__FILE__) . "/process_turn.remake_files.php";	// utility to recreate the data files

include_once( dirname(__FILE__) . "/../Login/Login_common.php" );
include_once( dirname(__FILE__) . "/../campaign_config.php" );
include_once( dirname(__FILE__) . "/../objects/gameDB.php");
include_once( dirname(__FILE__) . "/../objects/obj_list.php");
require_once( dirname(__FILE__) . "/../objects/ship.php" );

// This code block allows the $_REQUEST variable to catch up to the rest of the script
// This fixes a bug where the $_REQUEST variable is not set until it was subject to print_r()
// Don't ask me how. Apparently, the $_REQUEST variable has to be accessed before it is assigned
if( ! isset($_REQUEST) || ! isset($_REQUEST['game']) )
  redirect( $GOTO_ON_FAIL );

$database = gameDB::giveme();	// get the database object
$empireObj = "";
$empIncome = 0;		// amount of EP income for this empire
$empStoredEP = 0;	// amount of stored EPs for this empire
$errors = "";	// the error string that will be output
$gameID = intval($_REQUEST['game']);	// the Game Identifier as given by the input
$gameObj = array();	// the game data from the DB
$gameTurn = 0;	// the latest turn of the game
$raceID = -1;	// the Race Identifier as given by the input. Set to -1 when there is no race input
$realGameObj = "";	// the actual game object from the database. Needed because some thiungs on this page actually update the DB
$result = false;	// used to track success of database reads
$stdURLSuffix =	"";	// Set of GET arguments, very commonly sent with outgoing URLs

if( isset($_REQUEST['race']) && intval($_REQUEST['race']) >= 0 )
  $raceID = intval($_REQUEST['race']);

$realGameObj = loadOneObject( "game", $gameID, "$GOTO_ON_BACK?".$authObj->getSessionRequest() );

  $gameObj = array (
    'allowConjectural' => $realGameObj->modify('allowConjectural'),
    'currentTurn' => $realGameObj->modify('currentTurn'),
    'campaignSpeed' => $realGameObj->modify('campaignSpeed'),
    'gameName' => $realGameObj->modify('gameName'),
    'gameStart' => $realGameObj->modify('gameStart'),
    'largestSizeClass' => $realGameObj->modify('largestSizeClass'),
    'moderator' => $realGameObj->modify('moderator'),
    'overwhelmingForce' => $realGameObj->modify('overwhelmingForce'),
    'status' => $realGameObj->modify('status'),
    'turnSection' => $realGameObj->modify('turnSection'),
    'useExperience' => $realGameObj->modify('useExperience'),
    'useUnitSwapping' => $realGameObj->modify('useUnitSwapping'),
    'gameYear' => $realGameObj->gameYear()
  );

$modPlayerObj = loadOneObject( "user", $realGameObj->modify('moderator'), "$GOTO_ON_BACK?".$authObj->getSessionRequest() );

$gameObj['modName'] = $modPlayerObj->modify('fullName');
$gameObj['modEmail'] = $modPlayerObj->modify('email');


if( $raceID > 0 )
{
  $empireObj = loadOneObjectTurn( "empire", $raceID, "$GOTO_ON_BACK?".$authObj->getSessionRequest(), $realGameObj->modify('currentTurn') );

// Check the empire ID given in the script's arguments to make sure the player isn't hacking the header arguments
  if( ! sanitizeRace( $empireObj, $gameID ) )
    redirect( "$GOTO_ON_BACK?".$authObj->getSessionRequest() );
}

// Make sure that if we are trying to be the moderator, that we are the moderator of this game
if( $raceID == 0 && $realGameObj->modify('moderator') != $userObj->modify('id') )
  redirect( "$GOTO_ON_BACK?".$authObj->getSessionRequest() );

$gameTurn = $realGameObj->modify('currentTurn');
$stdURLSuffix =	"?game=$gameID&race=$raceID&".$authObj->getSessionRequest();

if( $raceID > 0 )
{
  // toggle the player's advance status if they hit the "advance" button
  if( $realGameObj->modify('status') == Game::STATUS_PROGRESSING && ! empty($_REQUEST['canadvance']) && $_REQUEST['canadvance'] == "true" )
  {
    $state = $empireObj->modify('advance');
    if( ! $state )
      $empireObj->modify('advance', true);
    else
      $empireObj->modify('advance', false);
    $empireObj->update();
  }
}

if( ! empty($_REQUEST[ $RENAME_GAME_TEXT ]) && $_REQUEST[ $RENAME_GAME_TEXT ] != $realGameObj->modify('gameName') && $raceID == 0 )
{
  $realGameObj->modify('gameName', $_REQUEST[ $RENAME_GAME_TEXT ]);
  $realGameObj->update();
  $gameObj['gameName'] = $realGameObj->modify('gameName');
}

if( ! empty($_REQUEST['modbidin']) && $_REQUEST['modbidin'] != $realGameObj->modify('moduleBidsIn') && $raceID == 0 )
{
  $realGameObj->modify( 'moduleBidsIn', $_REQUEST['modbidin'] );
  $realGameObj->update();
}
if( ! empty($_REQUEST['modbidout']) && $_REQUEST['modbidout'] != $realGameObj->modify('moduleBidsOut') && $raceID == 0 )
{
  $realGameObj->modify( 'moduleBidsOut', $_REQUEST['modbidout'] );
  $realGameObj->update();
}
if( ! empty($_REQUEST['modencin']) && $_REQUEST['modencin'] != $realGameObj->modify('moduleEncountersIn') && $raceID == 0 )
{
  $realGameObj->modify( 'moduleEncountersIn', $_REQUEST['modencin'] );
  $realGameObj->update();
}
if( ! empty($_REQUEST['modencout']) && $_REQUEST['modencout'] != $realGameObj->modify('moduleEncountersOut') && $raceID == 0 )
{
  $realGameObj->modify( 'moduleEncountersOut', $_REQUEST['modencout'] );
  $realGameObj->update();
}

// create a new empire position
if( ! empty($_REQUEST['newposition']) && $raceID == 0 )
{
  $initialRace = "";
  if( isset($BASICRACE_EMPIRES[0]) )
    $initialRace = $BASICRACE_EMPIRES[0];

  // create the new Empire object
  $options = array(
    'advance' => false,
    'ai' => "",
    'borders' => "",
    'game' => $gameID,
    'income' => 0,
    'player' => 0,
    'race' => $initialRace,
    'storedEP' => 0,
    'turn' => $gameTurn
  );

  $obj = new Empire( $options );
  $result = $obj->create();

}

// Advance the game
if( ! empty($_REQUEST['advance']) && 
    $realGameObj->modify('status') == Game::STATUS_PROGRESSING &&
    ( $authObj->checkPrivs( $userObj, "advance" ) || $authObj->checkPrivs( $userObj, "advanceAll" ) )
  )
{
  $output = array(); // captures each script's output
  $execString = "";

  // call advancement script
  if( $_REQUEST['advance'] == $ADVANCE_ORDERS_TEXT )
    $execString = "$ADVANCE_PAST_BIDDING $gameID";
  else if( $_REQUEST['advance'] == $ADVANCE_ENCOUNTERS_TEXT )
    $execString = "$ADVANCE_PAST_ENCOUNTERS $gameID";
  elseif( $_REQUEST['advance'] == $CHECK_ORDERS_TEXT )
    $execString = "$CHECK_BIDDING $gameID";
  else if( $_REQUEST['advance'] == $CHECK_ENCOUNTERS_TEXT )
    $execString = "$CHECK_ENCOUNTERS $gameID";

  if( ! empty($execString) )
  {
    exec( $execString, $output, $return );
    if( ! $return )
    {
      $empireDBList = new objList( "empire", $gameID, $gameTurn, false );
      // reset each empire's "ready" flag
      foreach( $empireDBList->objByID as $obj )
      {
        $obj->modify('advance',false);
        $obj->update();
      }
      // update the user's session timestamp for idleness
      $status = $authObj->storeSession( $userObj );
      if( ! $status )
        $errorString .= "Error with storing session.";
      else
        $userObj->update();
    }

    // re-read the game object
    $realGameObj->read();

    // announce finish
    if( $ADD_NEWLINES_TO_OUTPUT )
      $logTag = implode( "\n<br>", $output );
    else
      $logTag = implode( "\n", $output );
    unset($empireDBList);
  }
}

// Express or remove interest
$interestGames = $database->openPositions();
if( isset( $interestGames[ $gameID ] ) &&
    ! empty($_REQUEST['interested']) &&
    $raceID != 0
  )
{
  if( $realGameObj->getInterest( $userObj->modify('id') ) )
    $realGameObj->removeInterest( $userObj->modify('id') );
  else
    $realGameObj->addInterest( $userObj->modify('id') );
  $realGameObj->update();
  redirect( $GOTO_ON_INTEREST.$stdURLSuffix );
}

// Close the game
if( $realGameObj->modify('status') != Game::STATUS_CLOSED &&
    ! empty($_REQUEST['close']) &&
    ( $authObj->checkPrivs( $userObj, "close" ) || $authObj->checkPrivs( $userObj, "closeAll" ) )
  )
{
  $realGameObj->modify('status', Game::STATUS_CLOSED );
  $realGameObj->update();
  redirect( $_SERVER['PHP_SELF'].$stdURLSuffix );
}

// Change the game from 'Open' to 'Progressing'
if( $realGameObj->modify('status') == Game::STATUS_OPEN &&
    ! empty($_REQUEST['start']) &&
    ( $authObj->checkPrivs( $userObj, "advance" ) || $authObj->checkPrivs( $userObj, "advanceAll" ) )
  )
{
  $realGameObj->modify('status', Game::STATUS_PROGRESSING );
  $realGameObj->update();
  $redirectURL = $_SERVER['PHP_SELF'].$stdURLSuffix;
  if( $AUTO_ADVANCE_OPENING_GAME )
    $redirectURL .= "&advance=".urlencode($ADVANCE_ENCOUNTERS_TEXT);
  redirect( $redirectURL );
}

// Use the CSV utility
if( ! empty($_REQUEST['utilityCSV']) && $raceID == 0 && $gameObj['status'] == Game::STATUS_PROGRESSING && 
    $authObj->checkPrivs( $userObj, "advance" && $authObj->checkPrivs( $userObj, "advanceAll" ) )
  )
{
  $execString = "$UTILITY_CSV $gameID";

  exec( $execString, $output, $return );
    $logTag = implode( "\n<br>", $output );
}
// Use the utility to remake the data files
if( ! empty($_REQUEST['utilityRemake']) && $raceID == 0 && $gameObj['status'] == Game::STATUS_PROGRESSING && 
    $authObj->checkPrivs( $userObj, "advance" && $authObj->checkPrivs( $userObj, "advanceAll" ) )
  )
{
  $execString = "$UTILITY_REMAKE $gameID";

  exec( $execString, $output, $return );
    $logTag = implode( "\n<br>", $output );
}


// Set up the variables used inside the template

$advanceEncountersTag = "<input type='submit' name='advance' value='$ADVANCE_ENCOUNTERS_TEXT'>";
$advanceOrdersTag = "<input type='submit' name='advance' value='$ADVANCE_ORDERS_TEXT'>";
$backUpTag = "<a href='$GOTO_ON_BACK?".$authObj->getSessionRequest()."'>Account Menu</a>";
$canAdvanceTag = "";
$checkOrdersTag = "<input type='submit' name='advance' value='$CHECK_ORDERS_TEXT'>";
$checkEncountersTag = "<input type='submit' name='advance' value='$CHECK_ENCOUNTERS_TEXT'>";
$closeTag = "<input type='submit' name='close' value='Close Game' onclick='return deleteConfirm();'>";
$empireScore = 0;
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
$formTag .= $authObj->getSessionTag();
$formTag .= "<input type='hidden' name='game' value='$gameID'>\n";
$formTag .= "<input type='hidden' name='race' value='$raceID'>\n";
$isInterestedTag = "";
$javascript = "<script type='text/javascript'>function deleteConfirm(){r = window.confirm(";
$javascript .= "'Do you want to close this game? It can not be un-done.');return r}</script>";
$logOutTag = $tag_logout;
$ordersTag = "";
$startTag = " &bull; <input type='submit' name='start' value='Begin Game'>";
$utilityCSVTag = "<input type='submit' name='utilityCSV' value='Use CSV Utility'>";
$remakeFilesTag = "<input type='submit' name='utilityRemake' value='Recreate the Data Files'>";

$allowConjTag = "No Conjectural Units Allowed";
$empAdvanceState = "No orders to process";
$empEcon = "";
$empList = "";
$empPool = "";
$empRace = "";
$empTech = "";
$empTreaties = "";
$colonyMapFile = "";
$sectorMapFile = "";
$gameList = "";
$gameModTag = "<a href='mailto:".$gameObj['modEmail']."'>".$gameObj['modName']."</a>";
$jsRedirectURL = $_SERVER['PHP_SELF'].$stdURLSuffix;
$largestSizeClass = "Size Class ".$gameObj['largestSizeClass'];
$moduleBidInTag = $realGameObj->modify('moduleBidsIn');
$moduleBidOutTag = $realGameObj->modify('moduleBidsOut');
$moduleEncInTag = $realGameObj->modify('moduleEncountersIn');
$moduleEncOutTag = $realGameObj->modify('moduleEncountersOut');
$playerName = $userObj->modify('fullName');
$renameTag = "";
$turnSection = "";

if( $gameObj['allowConjectural'] )
  $allowConjTag = "Conjectural Units Allowed";

// set up the "I want to change my interest in this game" tag
$isInterestedTag = "<input type='submit' name='interested' value='";
if( $realGameObj->getInterest( $userObj->modify('id') ) )
{
  $isInterestedTag .= "I want to leave this game";
  // drive-by assignment: set the state of the orders to "Waiting for mod to start the game"
  $empAdvanceState = "Waiting for a position to be assigned";
}
else
{
  $isInterestedTag .= "I want to join this game";
}
$isInterestedTag .= "'>";

switch( $gameObj['turnSection'] )
{
  case Game::TURN_SECTION_EARLY:
  $turnSection = "Waiting for Mod to advance past orders";
  break;
  case Game::TURN_SECTION_MID:
  $turnSection = "Waiting for Mod to advance past encounters";
  break;
  case Game::TURN_SECTION_LATE:
  $turnSection = "";
  break;
}

if( $gameObj['status'] == Game::STATUS_OPEN )
  $turnSection = "Waiting to begin game";

if( ! isset( $interestGames ) ) // check the assignment of this, just in case
  $interestGames = $database->openPositions();
if( ! isset( $interestGames[ $gameID ] ) || $raceID >= 0 )
  $isInterestedTag = "";

if( $raceID != 0 && ! empty($empireObj) )
{
  $advanceButtonText = "Ready to Advance";
  $ordersTag = "<a href='$GOTO_ON_ORDERS$stdURLSuffix'>Assign Orders</a>";

  if( $empireObj->modify('advance') )
  {
    $empAdvanceState = "Ready to advance the turn";
    $advanceButtonText = "Wait for orders";
  }
  else
  {
    $empAdvanceState = "Waiting for orders";
  }

  $canAdvanceTag = "<a href='".$_SERVER['PHP_SELF'].$stdURLSuffix."&canadvance=true'>$advanceButtonText</a>";

  if( $gameObj['status'] != Game::STATUS_PROGRESSING )
  {
    $canAdvanceTag = "";
    $empAdvanceState = "No Orders Allowed";
    $ordersTag = "";
  }

  $empList = populateScenarioList( $gameTurn, $gameID, $raceID );

  $empireScore = $database->calcEmpireScore( $gameID, $gameTurn, $raceID );
}
else if( $raceID == 0 )
{
  // Create list of game positions so as to modify them
  $empList = populateEmpireList();

  // create the tag for renaming the game
  $renameTag = "<input type='text' name='$RENAME_GAME_TEXT' value='{$realGameObj->modify('gameName')}' onChange=";
  $renameTag .= "'noIssueRedirect(this)' onkeydown='if (event.keyCode == 13) {event.preventDefault();this.blur()}'>";

  // create drop-downs for the I/O modules

  // Input Bids modules
  if( count( $MODULE_BIDS_IN ) == 1 )
  {
    $moduleBidInTag = $MODULE_BIDS_IN[0]."<input type='hidden' name='modbidin' value='{$MODULE_BIDS_IN[0]}'>";
  }
  else
  {
    $moduleBidInTag = "<select name='modbidin' onchange='jsRedirect(this,\"the bidding input module\")'>\n";
    foreach( $MODULE_BIDS_IN as $mod )
    {
      $moduleBidInTag .= "<option";
      if( $mod == $realGameObj->modify('moduleBidsIn') )
        $moduleBidInTag .= " selected";
      $moduleBidInTag .= ">$mod</option>\n";
    }
    $moduleBidInTag .= "</select>";
  }
  // Output Bids modules
  if( count( $MODULE_BIDS_OUT ) == 1 )
  {
    $moduleBidOutTag = $MODULE_BIDS_OUT[0]."<input type='hidden' name='modbidout' value='{$MODULE_BIDS_OUT[0]}'>";
  }
  else
  {
    $moduleBidOutTag = "<select name='modbidout' onchange='jsRedirect(this,\"the bidding output module\")'>\n";
    foreach( $MODULE_BIDS_OUT as $mod )
    {
      $moduleBidOutTag .= "<option";
      if( $mod == $realGameObj->modify('moduleBidsOut') )
        $moduleBidOutTag .= " selected";
      $moduleBidOutTag .= ">$mod</option>\n";
    }
    $moduleBidOutTag .= "</select>";
  }
  // Input Encounters modules
  if( count( $MODULE_ENCOUNTERS_IN ) == 1 )
  {
    $moduleEncInTag = $MODULE_ENCOUNTERS_IN[0]."<input type='hidden' name='modencin' value='{$MODULE_ENCOUNTERS_IN[0]}'>";
  }
  else
  {
    $moduleEncInTag = "<select name='modencin' onchange='jsRedirect(this,\"the encounter input module\")'>\n";
    foreach( $MODULE_ENCOUNTERS_IN as $mod )
    {
      $moduleEncInTag .= "<option";
      if( $mod == $realGameObj->modify('moduleEncountersIn') )
        $moduleEncInTag .= " selected";
      $moduleEncInTag .= ">$mod</option>\n";
    }
    $moduleEncInTag .= "</select>";
  }
  // Output Encounters modules
  if( count( $MODULE_ENCOUNTERS_OUT ) == 1 )
  {
    $moduleEncOutTag = $MODULE_ENCOUNTERS_OUT[0]."<input type='hidden' name='modencout' value='{$MODULE_ENCOUNTERS_OUT[0]}'>";
  }
  else
  {
    $moduleEncOutTag = "<select name='modencout' onchange='jsRedirect(this,\"the encounter output module\")'>\n";
    foreach( $MODULE_ENCOUNTERS_OUT as $mod )
    {
      $moduleEncOutTag .= "<option";
      if( $mod == $realGameObj->modify('moduleEncountersOut') )
        $moduleEncOutTag .= " selected";
      $moduleEncOutTag .= ">$mod</option>\n";
    }
    $moduleEncOutTag .= "</select>";
  }
}

if( ! isset($logTag) )
  $logTag = "";

// if the empire is not the moderator, or they don't have 'advance' privs, or the 
// game is not progressing, empty the contents of the 'advance' buttons
if( $raceID != 0 ||
    ! ( $authObj->checkPrivs( $userObj, "advance" ) || $authObj->checkPrivs( $userObj, "advanceAll" ) ) || 
    $gameObj['status'] != Game::STATUS_PROGRESSING
  )
{
  $checkOrdersTag = "";
  $checkEncountersTag = "";
  $advanceOrdersTag = "";
  $advanceEncountersTag = "";
  $utilityCSVTag = "&nbsp;";
  $remakeFilesTag = "&nbsp;";
}
if( $raceID != 0 ||
    ! ( $authObj->checkPrivs( $userObj, "close" ) || $authObj->checkPrivs( $userObj, "closeAll" ) ) ||
    $gameObj['status'] == Game::STATUS_CLOSED
  )
  $closeTag = "";
if( $raceID != 0 ||
    ! ( $authObj->checkPrivs( $userObj, "advance" ) || $authObj->checkPrivs( $userObj, "advanceAll" ) ) ||
    $gameObj['status'] != Game::STATUS_OPEN
  )
  $startTag = "";
$errorTag = $errors;


// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

###
# Pulls out the data needed to populate the lists of empires and their advancement status
###
# Args are:
# - None
# Returns:
# - (string) the HTML tag that lists the empires with advancement status
###
function populateEmpireList()
{
  global $GOTO_ON_EMPIRE, $stdURLSuffix, $realGameObj, $gameID, $gameTurn;

  $empList = new objList( "empire", $gameID, $gameTurn, false );

  $output = "<table>\n<tr><th>Empire</th><th>Is Ready</th></tr>\n";

  // generate the listing for empire positions
  foreach( $empList->objByID as $obj )
  {
    $playerID = $obj->modify('player');
    $playerObj = "";
    $status = "Please wait";
    if( $obj->modify('advance') == true )
      $status = "Ready to advance";

    // attempt to load up the player object if we have a player ID
    if( ! empty($playerID) )
    {
      $playerObj = loadOneObject( 'user', $playerID );
      // check to see if the object loading was successful
      if( ! $playerObj )
        continue;
    }

    // generate the listing for the empire
    $output .= "<tr><td class='empire_entry'><a href='$GOTO_ON_EMPIRE$stdURLSuffix&empire=".$obj->modify('id');
    $output .= "' class='empire'>".$obj->modify('textName')." - ".$obj->modify('race')." (";
    if( $playerObj )
     $output .= $playerObj->modify('username');
    else
     $output .= "No Player";
    $output .= ")</a></td><td>$status</td></tr>\n";

    unset( $obj, $playerObj );
  }

  // generate the listing for players with interest in the game
  $interestedList = explode( ",", $realGameObj->modify('interestedPlayers') );
  foreach( $interestedList as $id )
  {
    $playerObj = new user( $id );
    // read the rest of the player if we can
    if( $playerObj )
      $result = $playerObj->read();
    if( ! $result )
      continue;
    // make it so the player object won't save itself
    $playerObj->modify( 'autowrite', false );

    // generate the interest entry
    $status = "";
    $output .= "<tr><td class='empire_entry'><a href='";
    if( $realGameObj->modify('status') != Game::STATUS_CLOSED )
      $output .= "$GOTO_ON_EMPIRE$stdURLSuffix&player=$id";
    $output .= "' class='player'>".$playerObj->modify('username')."</a></td><td>$status</td></tr>\n";

    // remove the Player Object
    unset( $playerObj );
  }

  // make the button for adding a new (empty) position
  if( $realGameObj->modify('status') != Game::STATUS_CLOSED )
  {
    $output .= "<tr><td>&nbsp;</td></tr>\n";
    $output .= "<tr><td><input type='submit' name='newposition' value='Add New Empire'></td><td></td></tr>\n";
  }

  $output .= "</table>\n";

  return $output;
}

###
# Creates a display of players and their units/scenarios:
# - A table with each player having two columns.
# - - Column 1 is a unit list
# - - Column 2 is a scenario list of scenario images. link of scenario image goes to scenario description
###
# Args are:
# - (int) The turn number to look for
# - (int) The player identifier to look for
# Returns:
# - (string) the HTML tag that lists the empires with their units and scenarios
###
function populateScenarioList( $turn, $game, $empireID )
{
  global $MODULE_FILE_STORE, $BID_OUT_FILE_FORMAT;
  $output = "";

  $filename = array(
      "$MODULE_FILE_STORE/".sprintf( $BID_OUT_FILE_FORMAT, $turn, $game, $empireID ),
// possibly look for the previous-turn's file if the first one fails
//      "$MODULE_FILE_STORE/".sprintf( $BID_OUT_FILE_FORMAT, ($turn-1), $game, $empireID ),
    );
  foreach( $filename as $file )
    if( is_readable($file) )
    {
      $output = file_get_contents( $file );
      break;
    }

  return $output;
}

?>
