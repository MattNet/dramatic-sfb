<?php
###
# Performs the tasks common to all of the pages
# - Loads up the player object associated with this login
# - Performs a hacking-check (not neccesarily an exhaustive check)
# Must have $GOTO_ON_FAIL assigned as the document to redirect upon failure of the hacking-check
# Must have $GOTO_ON_BACK assigned as the document to redirect upon failure of the loading of a single object
# May optionally have $MAYBE_NEW_PLAYER set to true, so as to not attempt to set the $userObj
###
# Available Variables:
# - (object) $authObj - An instance of the Auth object, used for session tracking. see /objects/admin_auth.php
# - (string) $ERROR_GET_STRING - The beginning of a Get request string, which defines the argument that sends an error
# - (string) $GOTO_ON_LOGOUT - The page to serve if we click on the logout link
# - (string) $tag_account - An HTML string that places the 'Go to edit account' button
# - (string) $tag_logout - An HTML string that palces the logout button
# - (string) $userName - the username of the player acct to be used
# - (obj) $userObj - the object reference for this player in the database
# Available Functions:
# - displayPage( $location ) - Displays the given template page, with appropriate headers
# - loadOneObject( $objName, $ID, [$failureURL], [$turn] ) - Loads a singular object
# - redirect( $location ) - Redirects to the given page and quits
# - sanitizeEntry( $empObj, $gameID ) - Checks that the user is associated with this empire
###

require_once( dirname(__FILE__) . "/Login_config.php" );
include_once( dirname(__FILE__) . "/../objects/Login_auth.php");
include_once( dirname(__FILE__) . "/../objects/empire.php");
include_once( dirname(__FILE__) . "/../objects/user.php");
include_once( dirname(__FILE__) . "/Login_config.php");

date_default_timezone_set($TIMEZONE);

$ADJUST_OTHER_GET_STRING = "adjustOther";	// flag to give the adjust-other-accts page
$authObj = new Auth();	// Authentication object, for password encryption and session tracking
$ERROR_GET_STRING = "error=";	// what to put in the URL when there is an error
$GOTO_ON_LOGOUT = "/Login/Login_acctedit.php?logout=true&".$authObj->getSessionRequest();	// page to serve if we click on the logout link
$objectsDirectory = dirname(__FILE__) . "/../objects/";
$tag_account = "<a href='/Login/Login_acctedit.php'>Account</a>";
$tag_adjustAccts = "<a href='/Login/Login_acctedit.php?$ADJUST_OTHER_GET_STRING=true' class=''>Adjust Accounts</a>";
$tag_logout = "<a href='$GOTO_ON_LOGOUT'>Log Out</a>";
$userObj = "";	// the object reference for this player in the database
$userName = $authObj->getUser();	// the username of the player acct to be used

$userObj = _loadUserObject( $userName );
if( isset($userObj) )
  $styleSheet = $THEME_DIRECTORY.$userObj->modify("theme").".css";

###
# Loads the Player Object
###
# Args are:
# - None
# Returns:
# - None
###
function _loadUserObject( $name )
{
  global $authObj, $MAYBE_NEW_USER, $GOTO_ON_FAIL, $ERROR_GET_STRING;

  $result = false;	// used to track success of database reads

  // if we can't get the username and this isn't a new acct, then error out
  if( ! $name && ! $MAYBE_NEW_USER )
    // leave the page
    redirect( $GOTO_ON_FAIL."?".$ERROR_GET_STRING.urlencode("There was no username given") );

  // set up the user-object. Attempt to read in it's data
  $user = new user( array('username'=>$name) );
  // make it so the user object won't save itself
  $user->modify( 'autowrite', false );
  // get the object's ID
  $result = $user->getID('username');
  // read the rest of the user if we can
  if( $result )
    $result = $user->read();

  if( ! $result || ! $authObj->verifySession( $user ) )
  {
    if( ! isset( $MAYBE_NEW_USER ) || $MAYBE_NEW_USER != true )
    {
      // if the user is not in DB or it seems this page was loaded without a true login AND there is no chance this is a new user.
      // Then this is a (basic) hack attempt
      $authObj->endSession( $user );
      // generate the reported reason for failure
      $reason = "Session expired."; // report that verifySession() failed
      if( ! $result )
        $reason = "Username '$name' does not exist."; // report that the $user read failed
      // leave the page
      redirect( $GOTO_ON_FAIL."?".$ERROR_GET_STRING.urlencode($reason) );
    }
    // return a null UserObj if the user doesn't exist and if we aren't going to fail on a missing UserObj
    return null;
  }

  $authObj->storeSession( $user );
  return $user;
}

###
# Displays the given template page, with appropriate headers
# These pages can use the variables already defined
###
# Args are:
# - (string) The url to display
# Returns:
# - None
###
function displayPage( $location )
{
  header('Cache-Control: no-cache, must-revalidate');
  include($location);
  exit();
}

###
# Loads a singular object
###
# Args are:
# - (string) The object name to load
# - (string) The ID of the object to load
# - (string) [optional] The URL to follow upon failure
# Returns:
# - (object) The requested object. redirects to the URL if it fails. If the URL was not given, returns false
###
function loadOneObject( $objName, $ID, $failureURL="" )
{
  global $errors, $ERROR_GET_STRING, $objectsDirectory;
  $includeFile = $objectsDirectory . $objName . ".php";
  if( ! file_exists($includeFile) )
  {
    $errors .= "Object file '" . strtolower($objName) . "' does not exist.";
    if( ! empty($failureURL) )
      redirect( $failureURL."&".$ERROR_GET_STRING.urlencode($errors) );
    else
      return false;
  }
  include_once( $includeFile );

  $obj = new $objName( array('id'=>$ID) );
  $result = $obj->read();
  // if the object doesn't exist
  if( ! $result )
  {
    $errors .= "Cannot read object '" . strtolower($objName) . "'.";
    if( ! empty($failureURL) )
      redirect( $failureURL."&".$ERROR_GET_STRING.urlencode($errors) );
    else
      return false;
  }
  $obj->modify('autowrite', false);

  return $obj;
}

###
# Loads a singular object
###
# Args are:
# - (string) The object name to load
# - (string) The ID of the object to load
# - (string) [optional] The URL to follow upon failure
# - (integer) [optional] The game turn of the object to use
# Returns:
# - (object) The requested object. redirects to the URL if it fails. If the URL was not given, returns false
###
function loadOneObjectTurn( $objName, $ID, $failureURL="", $turn=-1 )
{
  global $errors, $ERROR_GET_STRING, $objectsDirectory;
  $includeFile = $objectsDirectory . $objName . ".php";
  if( ! file_exists($includeFile) )
  {
    $errors .= "Object file '" . strtolower($objName) . "' does not exist.";
    if( ! empty($failureURL) )
      redirect( $failureURL."&".$ERROR_GET_STRING.urlencode($errors) );
    else
      return false;
  }
  include_once( $includeFile );

  $obj = new $objName( array('id'=>$ID) );
  if( $turn > -1 )
    $obj->modify('turn', $turn );
  $result = $obj->read();
  // if the object doesn't exist
  if( ! $result )
  {
    $errors .= "Cannot read object '" . strtolower($objName) . "'.";
    if( ! empty($failureURL) )
      redirect( $failureURL."&".$ERROR_GET_STRING.urlencode($errors) );
    else
      return false;
  }
  $obj->modify('autowrite', false);

  return $obj;
}

###
# Redirects to the given page and quits
# These pages will not use the environment already defined in the page
###
# Args are:
# - (string) The url to display
# Returns:
# - None
###
function redirect( $location )
{
  // Send a REDIRECT (302) status so the URL remains correct
  if( isset($_SERVER['SERVER_NAME']) )
    header( "Location: http://{$_SERVER['SERVER_NAME']}$location" );
  else
    header( "Location: /$location" );
  exit();
}

###
# Checks that the player plays this empire
###
# Args are:
# - (object) The empire object to check this player against
# - (integer) The Game object ID that we check against
# Returns:
# - (bool) true if they play this empire. false otherwise
###
function sanitizeRace( $empObj, $gameID )
{
  global $userObj;
  $output = false;


  if( method_exists( $empObj, 'modify' ) )
  {
    // does this player plays this empire?
    if( $empObj->modify('player') == $userObj->modify('id') )
      $output = true;

    // does this empire not play in this game?
    if( $empObj->modify('game') != $gameID )
      $output = false;
  }
  else
  {
    // does this player plays this empire?
    // this assumes we are still importing a playerobj from the DB
    if( $empObj['player'] == $userObj->modify('id') )
      $output = true;

    // does this empire not play in this game?
    if( $empObj['game'] != $gameID )
      $output = false;
  }

  return $output;
}

?>
