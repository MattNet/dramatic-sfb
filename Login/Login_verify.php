<?php
$GOTO_ON_BACK = $LOGIN_EXIT_FILE;
$GOTO_ON_FAIL = $LOGIN_START_FILE;
$KEY_GET_STRING = "key";	// Post string for user's verification key
$TEMPLATE_FILE = dirname(__FILE__) . "/templates/Login_verified.php";
$RELOG_FILE = $LOGIN_START_FILE;	// link to where the user goes at the end of the verification process

include_once( dirname(__FILE__) . "/../objects/Login_auth.php");
include_once( dirname(__FILE__) . "/../objects/user.php");

$authObj = new Auth();	// Authentication object, for password encryption and session tracking
$userObj = ""; // the object with the user's data in it

// Pull out the user's ID from the key
$result = base64_decode( $_REQUEST[$KEY_GET_STRING], true );
if( ! $result ) // base64_decode() returned false
  redirectError( "Could not identify user." );
$userID = substr( $result, 0, 10 );
if( $userID < 1 )
  redirectError( "Could not identify user." );

// create a user with this ID
$userObj = new User( array('id'=>$userID) );
$userObj->modify( 'autowrite', false );
$result = $userObj->read();
// if the read isn't successful, then error out
if( ! $result )
  redirectError( "Could not retrieve user." );

if( $_REQUEST[$KEY_GET_STRING] != $userObj->modify('verifyKey') )
  redirectError( "Could not verify user." );

// if we made it here, then things must have worked out
$userObj->modify( 'isVerified', true );
$userObj->update();

$tag_relog = "<a href='http://{$_SERVER['SERVER_NAME']}$RELOG_FILE'>http://{$_SERVER['SERVER_NAME']}$RELOG_FILE</a>";

header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
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
  header( "Location: http://{$_SERVER['SERVER_NAME']}.$GOTO_ON_FAIL$ERROR_GET_STRING".urlencode($error) );
  exit();
}
