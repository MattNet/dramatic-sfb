<?php

$GOTO_ON_ADJUST = "/Login/Login_acctedit.php";	// page to serve if we want to adjust our acct
$GOTO_ON_NEW = "/campaign/newgame.php";	// page to serve if we want to create a new game
$GOTO_ON_FAIL = "/index.php";		// page to serve if we've had very serious errors
$GOTO_ON_LINK = "/campaign/game.php";	// page to serve if we click on a game link
$GOTO_ON_RETURN = $_SERVER['PHP_SELF'];
$TEMPLATE_FILE = "./menu.template";	// Template file to load
$ACCOUNT_LIST_TEMPLATE = "./acct_list.template";	// Template file to load when listing accounts to be adjusted
$GAME_LIST_TEMPLATE = "./game_list.template";	// Template file to load when listing accounts to be adjusted

include_once( dirname(__FILE__) . "/../Login/Login_common.php" );
include_once( dirname(__FILE__) . "/../campaign_config.php" );
include_once( dirname(__FILE__) . "/../objects/empire.php");
include_once( dirname(__FILE__) . "/../objects/game.php");
include_once( dirname(__FILE__) . "/../objects/gameDB.php");

$errors = "";	// the error string that will be output
$empireData = array();	// holds specific points of data about each empire the player is in control of
$openData = array();	// holds specific points of data about each game that is open
$out = "";	// output from the DB queries

// Put up an account list when asked to do an "Other Game Adjustment"
if( ! empty( $_REQUEST['adjustGame'] ) && ( $authObj->checkPrivs( $userObj, "closeAll" ) || $authObj->checkPrivs( $userObj, "advanceAll" ) ) )
{
  // setup the tags for the template file
  $backTag = "<a href='$GOTO_ON_RETURN?".$authObj->getSessionRequest()."'>Account Menu</a>";
  $gameList = populateOtherGames();
  $errorTag = $errors;
  $formTag = "<form action='$GOTO_ON_LINK' method='post' target='_SELF' class=''>\n";
  $formTag .= "<input type='hidden' name='race' value='0'>\n";
  $formTag .= $authObj->getSessionTag();
  $selectTag = "<select name='game' size=3>\n";
  $submitTag = "<input type='submit' name='editGame' value='Edit Game'>\n";

  // send out the template file
  header( 'Cache-Control: no-cache, must-revalidate' );
  include( $GAME_LIST_TEMPLATE );
  exit();
}
// Redirect on New Game
if( ! empty( $_REQUEST['create'] ) )
  redirect( "$GOTO_ON_NEW?".$authObj->getSessionRequest() );
// Redirect on Account Adjustment
if( ! empty( $_REQUEST['adjust'] ) )
  redirect( "$GOTO_ON_ADJUST?".$authObj->getSessionRequest() );
// Redirect on Other Account Adjustment
if( ! empty( $_REQUEST['editAcct'] ) )
  redirect( "$GOTO_ON_ADJUST?user=".$_REQUEST['acct']."&".$authObj->getSessionRequest() );
// Redirect on Account Adjustment
if( ! empty( $_REQUEST['adjustOther'] ) )
  redirect( "$GOTO_ON_ADJUST?adjustOther=".$_REQUEST['adjustOther']."&".$authObj->getSessionRequest() );
/*
// Put up an account list when asked to do an "Other Account Adjustment"
if( ! empty( $_REQUEST['adjustOther'] ) && ( $authObj->checkPrivs( $userObj, "deleteAcct" ) || $authObj->checkPrivs( $userObj, "changeAcct" ) ) )
{
  // setup the tags for the template file
  $acctList = populateAcctLists();
  $backTag = "<a href='$GOTO_ON_RETURN?".$authObj->getSessionRequest()."'>Account Menu</a>";
  $formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
  $formTag .= $authObj->getSessionTag();
  $selectTag = "<select name='acct' size=3>\n";
  $submitTag = "<input type='submit' name='editAcct' value='Edit Account'>\n";

  // send out the template file
  header( 'Cache-Control: no-cache, must-revalidate' );
  include( $ACCOUNT_LIST_TEMPLATE );
  exit();
}
*/
// Handle Logging Out
if( ! empty( $_REQUEST['logout'] ) )
  redirect( $GOTO_ON_LOGOUT );

// Set up the variables used inside the template

$accountName = $userName;
$adjustOtherAcct = "<span class='button'>$tag_adjustAccts</span>";
$adjustOtherGame = "<input type='submit' name='adjustGame' value='Adjust Another Game'>";
$adjustPlayerLink = "<span class='button'>$tag_account</span>";
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
$formTag .= $authObj->getSessionTag();
$gamesPartOf = "";
$logOutLink = "<span class='button'>$tag_logout</span>";
$lookingForGames = "";
$newGameLink = "<input type='submit' name='create' value='New Game'>";
$playerName = $userObj->modify('fullName');

list( $gamesPartOf, $lookingForGames ) = populateGameLists( $userObj->modify('id') );

// disable new games if the privilege has not been given
if( ! $authObj->checkPrivs( $userObj, "create" ) )
  $newGameLink = "";
// disable adjusting other accounts if the privilege has not been given
if( ! $authObj->checkPrivs( $userObj, "changeAcct" ) && ! $authObj->checkPrivs( $userObj, "deleteAcct" ) )
  $adjustOtherAcct = "";
// disable adjusting other games if the privilege has not been given
if( ! $authObj->checkPrivs( $userObj, "advanceAll" ) && ! $authObj->checkPrivs( $userObj, "closeAll" ) )
  $adjustOtherGame = "";

// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

###
# Pulls out the data needed to populate the list of adjustable games
###
# Args are:
# - none
# Returns:
# - (string) a list of HTML OPTION tags, one for each game. Or false for an error
###
function populateOtherGames()
{
  global $GOTO_ON_LINK, $SHOW_DB_ERRORS, $errors;

  $database = gameDB::giveme();	// get the database object
  $output = ""; // the returned list at the end of the function

  // look through all of the non-closed games
  $gameData = $database->findNonClosedGames();
  if( $gameData === false )
  {
    $errors .= "Error finding non-closed games.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
    return false;
  }
  foreach( $gameData as $key=>$row )
  {
    $output .= "<option value='{$row['id']}'";
    if( $key == 0 )
      $output .= " selected";
    $output .= ">{$row['gameName']}</option>";
  }
  return $output;
}
###
# Pulls out the data needed to populate the lists of games
###
# Args are:
# - (integer) The identifier for the player that provides context for the game lists
# Returns:
# - (array) two elements:
# - - The HTML of the "Games this user is part of" list
# - - The HTML of the "Games that are open for new joinings" list
###
function populateGameLists( $playerID )
{
  global $GOTO_ON_LINK, $SHOW_DB_ERRORS, $errors, $authObj;

  $database = gameDB::giveme();	// get the database object
  $empireData = array();	// the collection of empires the player is playing.
		// Format is: array( 'gameID'=>(int), 'gameName'=>(string), 'mod'=>(bool) )
  $openData = array();	// the collection of open games. format is: 
		// array( (int) 'id', (string) 'gameName' )
  $out = "";	// Used to store the data pulled from the database
  $output = array( 0=>"", 1=>"" );	// the returned values at the end of the function
  $query = "";	// the DB query string
  $result = "";	// the result of the DB query

  // look through all of the games for the moderator with the player ID of playerobj
  $empireData = $database->findModeratedGames( $playerID );
  if( $empireData === false )
  {
    $errors .= "Error finding moderated games for the player.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
    return $output;
  }
  // look through all of the empires for the player ID of playerobj
  $tempData = $database->getEmpiresWithPlayer( $playerID );
  if( $tempData === false )
  {
    $errors .= "Error finding empires for the player.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
    return $output;
  }
  $empireData = array_merge( $empireData, $tempData );
  // look through all of the games for the game ID from $empireData
  foreach( $empireData as $key=>$data )
  {
    $tempData = $database->getGameData( $data['gameID'] );
    if( $tempData === false )
    {
      $errors .= "Error finding game data.\n";
      if( $SHOW_DB_ERRORS )
        $errors .= $database->error_string;
      return $output;
    }
    else
    {
      foreach( $tempData as $row )
      {
        $empireData[$key]['gameName'] = $row['gameName'];
        // empty this $empireData if the game is closed or the gamedata turn does not match the current turn of the game
        if( $row['status'] == Game::STATUS_CLOSED )
          unset( $empireData[$key] );
        // possibly empty this $empireData for games where this player is not the moderator 
        else if( ! $empireData[$key]['mod'] )
        {
          // empty the data if the game's currentTurn doesn't match the data's turn
          if( $row['currentTurn'] != $empireData[$key]['empTurn'] )
            unset( $empireData[$key] );
        }
      }
    }
  }
  // look through all of the progressing games where there are positions with no players assigned
  // Also get any open games (which assumes open games can collect interested players
  $openData = $database->openPositions();
  if( $openData === false )
  {
    $errors .= "Error finding games with open positions.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
    return $output;
  }

  // populate the $gamesPartOf variable
  foreach( $empireData as $data )
    if( $data['mod'] )
      $output[0] .= "<a href='$GOTO_ON_LINK?game={$data['gameID']}&race=0&{$authObj->getSessionRequest()}'>The {$data['gameName']} game as the Moderator</a>\n<br>";
    else
      $output[0] .= "<a href='$GOTO_ON_LINK?game={$data['gameID']}&race={$data['empID']}&{$authObj->getSessionRequest()}'>The {$data['gameName']} game as {$data['textName']} ({$data['raceName']})</a>\n<br>";

  // populate the $lookingForGames variable
  foreach( $openData as $data )
    $output[1] .= "<a href='$GOTO_ON_LINK?game={$data['id']}&{$authObj->getSessionRequest()}'>The {$data['gameName']} game</a>\n<br>";
  return $output;
}


###
# Pulls out the data needed to populate the lists of accounts
###
# Args are:
# - none
# Returns:
# - (string) a list of HTML OPTION tags, one for each acct
###
function populateAcctLists()
{
  global $SHOW_DB_ERRORS, $errors;

  $database = gameDB::giveme();	// get the database object
  $output = ""; // the returned list at the end of the function
  $query = ""; // the DB query string
  $result = ""; // the result of the DB query

  // look through all of the players for the pertinant data
  $query = "SELECT id,fullName,username FROM ".User::table;
  $result = $database->genquery( $query, $out );
  if( $result === false )
  {
    $errors .= "Error loading list.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
  }
  else
  {
    foreach( $out as $row )
      $output .= "<option value='{$row['id']}'>{$row['username']}({$row['fullName']})</option>";
  }
  return $output;
}



?>
