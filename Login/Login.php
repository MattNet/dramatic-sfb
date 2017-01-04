<?php
/*
Standard login page

Displays a list of entries this user has made, so that one may be selected for editing, or another may be created
It might serve an admin login, to show recent activity and to display entries sorted by user.
*/

include_once( dirname(__FILE__) . "/../objects/Login_auth.php");
include_once( dirname(__FILE__) . "/../objects/user.php");
include_once( dirname(__FILE__) . "/Login_config.php");

$GOTO_ON_FAIL = $LOGIN_START_FILE;
$GOTO_ON_FORWARD = $LOGIN_EXIT_FILE; // this leads out of the login process
$GOTO_ON_UNAPPROVED = "/Login/templates/Login_unapproved.html";
$GOTO_ON_UNVERIFIED = "/Login/Login_unverified.php";
$ERROR_GET_STRING = "?error=";	// what to put in the URL when there is an error

$authObj = new Auth();	// Authentication object, for password encryption and session tracking
$userObj = ""; // the object with the user's data in it

// if we can't get the username from the create form, then error out
if( ! isset($_REQUEST["login"]) || empty($_REQUEST["login"]) )
  redirectError( "There is no username to login with." );

// create a user with this name
$userObj = new User( array('username'=>$_REQUEST["login"]) );
$userObj->modify( 'autowrite', false );
// then see if we can get an ID that matches this user
$result = $userObj->getID('username');
// if we got an ID for this object, then read the rest of the object
if( $result )
  $result = $userObj->read();
// if we don't get an ID or the read isn't successful, then error out
if( ! $result )
  redirectError( "Could not get user '{$_REQUEST["login"]}'." );

// generate and store this session
$authObj->generateNewSession( $_REQUEST["login"] );
$result = $authObj->storeSession( $userObj );

if( ! $result )
  redirectError( "Error storing user '{$_REQUEST["login"]}'." );

// shunt the user to the unverified page if unverified
if( $MUST_VERIFY_EMAIL && $userObj->modify('isVerified') == false )
{
  // Send a REDIRECT (302) status so the URL remains correct
  header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_UNVERIFIED" );
  exit();
}

// shunt the user to the unapproved page if unapproved
if( $MUST_APROVE_ACCTS && $userObj->modify('isApproved') == false )
{
  // Send a REDIRECT (302) status so the URL remains correct
  header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_UNAPPROVED" );
  exit();
}

// this is the same code as "/common.php"->redirect(), but did not want the overhead of including that file
// Send a REDIRECT (302) status so the URL remains correct
header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_FORWARD" );
exit();


###
# Redirects to the failure page and quits
###
# Args are:
# - (string) The error to display
# Returns:
# - None
###
function redirectError( $error )
{
  global $GOTO_ON_FAIL, $ERROR_GET_STRING;

  // Send a REDIRECT (302) status so the URL remains correct
  header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_FAIL$ERROR_GET_STRING".urlencode($error) );
  exit();
}

?>
