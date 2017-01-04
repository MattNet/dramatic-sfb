<?php
/*
New User page

Creates a new user in the DB
Sends them to the edit-acct page
*/

include_once( dirname(__FILE__) . "/Login_config.php" );

$ACCT_EDIT_PAGE = "/Login/Login_acctedit.php";
$GOTO_ON_FAIL = $LOGIN_START_FILE;
$MAYBE_NEW_USER = true;
$ERROR_GET_STRING = "?error="; // what to put in the URL when there is an error

include_once( dirname(__FILE__) . "/Login_common.php" );

// if we can't get the username from the create form, then error out
if( ! isset($_REQUEST["login"]) || empty($_REQUEST["login"]) )
  redirect($GOTO_ON_FAIL);

// if the username already exists, then error out
// first, create a user with this name
$user = new user( array('username'=>$_REQUEST["login"]) );
$user->modify( 'autowrite', false );
// then see if we can get an ID that matches this user
$result = $user->getID('username');
// if we got an ID, then the user already exists
if( $result )
  redirect($GOTO_ON_FAIL.$ERROR_GET_STRING.urlencode("User already exists"));

// these are the values given to the newly-created user
$userSettings = array(
    'priviledges' => $DEFAULT_PRIVILEDGE,
    'signupDate' => date( "F d, Y", $_SERVER['REQUEST_TIME'] ),
    'theme' => $DEFAULT_THEME,
    'username' => $_REQUEST["login"],
  );

// set up the default values for new accounts and write it
$userObj = new user( $userSettings );
$authObj->generateNewSession( $_REQUEST["login"] );
$authObj->storeSession( $userObj );
$userObj->create();

// go on to edit the newly-created account
redirect( $ACCT_EDIT_PAGE );

?>
