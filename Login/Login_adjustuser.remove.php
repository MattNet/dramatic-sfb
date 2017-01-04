<?php

include_once( dirname(__FILE__) . "/Login_config.php" );

$GOTO_ON_SUCCESS = "/Login/Login_menu.php";	// page to serve if we've saved and exited successfully
$GOTO_ON_FAIL = $LOGIN_START_FILE;	// page to serve if we've had very serious errors
$MAYBE_NEW_USER = true;	// flag for common.php that says that having a not-user is allowed
$TEMPLATE_FILE = "/Login/templates/Login_adjustuser.template";	// Template file to load

include_once( dirname(__FILE__) . "/Login_common.php" );

$canDelete = false; // can this user delete accounts?
$canChange = false; // can this user change other accounts?
$errors = "";	// the error string that will be output
$isOtherAdjustment = false;	// true if this really is an account other than the original one
$result = false;	// used to track success of database reads

// if the user is not in DB. Then this is a new and unmade acct
if( ! $userObj )
{

  // set up the default values for new accounts and write it
  $userObj = new User( array('username'=>$userName) );

  $userObj->modify('priviledges', $DEFAULT_PRIVILEDGE );
  $userObj->modify('theme', $DEFAULT_THEME );
  $userObj->modify('signupDate', date( "Y-m-d H:i:s", $_SERVER['REQUEST_TIME'] ) );
  $signup = "New Account"; // set the signup date to something besides a 0 value
  $authObj->storeSession( $userObj );
  $userObj->create();
}
else
{

  $userObj = checkOtherAcct( $userObj );

  // go back to the login page if the session is no-good
  if( ! $isOtherAdjustment && ! $authObj->verifySession( $userObj ) )
    redirect( $GOTO_ON_FAIL );

  // set the signup date to the real date
  $signup = $userObj->modify('signupDate');
  if( ! $signup )
    $signup = "New Account"; // set the signup date to something besides a 0 value
  else
    $signup = substr( $signup, 0, stripos( $signup, " " ) ); // pull off the time component of the signup

  // do error-checking on currently-written accounts
  // includes accounts with default values written

  // Assign IAW the input form
  list( $emailValue, $nameValue, $themeValue ) = assignViaForm();

  // Validate the currently-assigned values
  $errors .= errorCheckForm();
}

if( ! empty( $_REQUEST['delete'] ) && $canDelete )
{
  $userObj->destroy();
  $_REQUEST['finished'] = true;
}

// leave this page if told to exit and if there are no errors
if( ! empty( $_REQUEST['finished'] ) && empty( $errors ) )
{
  $authObj->storeSession( $userObj );
  redirect( "$GOTO_ON_SUCCESS?".$authObj->getSessionRequest() );
}

// don't write the user's stuff to the DB
$userObj->modify('autowrite', false);

// Set up the variables used inside the template
$deleteTag = "";
if( $canDelete )
  $deleteTag = " &bull; <input type='submit' name='delete' value='Delete Account' class='' onclick='return deleteConfirm();'>";
$emailTag = "<input type='text' name='email'";
if( ! empty( $userObj->modify('email') ) )
  $emailTag .= " placeholder='".$userObj->modify('email')."'";
else
  $emailTag .= " required";
if( ! empty( $emailValue ) )
  $emailTag .= " value='$emailValue'";
$emailTag .= " autocomplete='off' class=''>";
$errorTag = $errors;
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF'>\n";
$formTag .= $authObj->getSessionTag();
$fullNameTag = "<input type='text' name='fullName'";
if( ! empty( $userObj->modify('fullName') ) )
  $fullNameTag .= " placeholder='".$userObj->modify('fullName')."'";
else
  $fullNameTag .= " required";
if( ! empty( $nameValue ) )
  $fullNameTag .= " value='$nameValue'";
$fullNameTag .= " autocomplete='off' class=''>";
$javascript = "<script type='text/javascript'>function deleteConfirm(){r = window.confirm('Do you want to delete this user? It may break some games they are part of.');return r}</script>";
$password1Tag = "<input type='password' name='pass1' class=''";
if( $userObj->modify('pass') == "" )
  $password1Tag .= " required";
$password1Tag .= " autocomplete='off'>";
$password2Tag = "<input type='password' name='pass2' class=''";
if( $userObj->modify('pass') == "" )
  $password2Tag .= " required";
$password2Tag .= " autocomplete='off'>";
$priviledgesTag = "<span class=''>".$userObj->modify('priviledges')."</span>";
if( $canChange )
{
  $priviledgesTag = "<select name='privLevel'>";
  $levels = array_keys($PRIVILEDGE_LEVELS);
  foreach( $levels as $privName )
  {
    $priviledgesTag .= "<option value='$privName'";
    if( $userObj->modify('priviledges') == $privName )
      $priviledgesTag .= " selected";
    $priviledgesTag .= ">$privName</option>";
  }
  $priviledgesTag .= "</select>";
}
$signupTag = "<span class=''>$signup</span>";
$submitTag = "<input type='submit' name='finished' value='Save and Exit' class=''>";
$userNameTag = "<span class=''>".$userObj->modify('username')."</span>\n";
$userNameTag .= "<input type='hidden' name='user' value='".$userObj->modify('id')."'>";

// if adjusting another account, but can't make any adjustments (except delete)
// then set the input tags to merely reporting thier values
if( $isOtherAdjustment && ! $canChange )
{
  $emailTag = "<span class=''>".$userObj->modify('email')."</span>";
  $fullNameTag = "<span class=''>".$userObj->modify('fullName')."</span>";
  $themeTag = "<span class=''>".$userObj->modify('theme')."</span>";
}

// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

###
# Checks if this is to adjust someone else's account
###
# Args are:
# - (object) the user-data object
# Returns:
# - (object) a user-data object. The new one if successful, the old one if not.
###
function checkOtherAcct( &$user )
{
  global $authObj, $canDelete, $canChange, $GOTO_ON_SUCCESS, $isOtherAdjustment;

  // set up the privs of the original user so we can make the proper buttons
  $canDelete = $authObj->checkPrivs( $user, "deleteAcct" ); // can this user delete accounts?
  $canChange = $authObj->checkPrivs( $user, "changeAcct" ); // can this user change other accounts?

  // we are affecting ourselves
  if( empty($_REQUEST['user']) ||
      ( ! $canDelete && ! $canChange ) ||
      $_REQUEST['user'] == $user->modify('id')
    )
  {
    $canDelete = false;
    $canChange = false;
    return $user;
  }

  $isOtherAdjustment = true;

  // set the $playerObj to the person we are adjusting
  $newUser = new User( array('id'=>$_REQUEST['user']) );
  $result = $newUser->read();
  if( ! $result )
  {
    // Send a REDIRECT (302) status so the URL remains correct
    header( "Location: $GOTO_ON_SUCCESS" );
    exit();
  }

  return $newUser;
}

###
# Assigns the values given by the input form
###
# Args are:
# - none
# Returns:
# - (array) a list of the email value given, the name value given, and the theme value given
###
function assignViaForm ()
{
  global $authObj, $userObj, $canChange;
  $email = "";
  $name = "";
  $theme = "";

  // assign the given password
  if( ! empty( $_REQUEST['pass1'] ) || ! empty( $_REQUEST['pass2'] ) )
  {
    // check that the passwords match and then write
    if( $_REQUEST['pass1'] == $_REQUEST['pass2'] )
    {
      $authObj->plainPassword($_REQUEST['pass1']);
      $userObj->modify('pass', $authObj->encryptedPassword() );
    }
  }
  // assign the given email
  if( ! empty( $_REQUEST['email'] ) )
  {
    $userObj->modify('email', filter_var( $_REQUEST['email'], FILTER_SANITIZE_EMAIL) );
    $email = $userObj->modify('email');
  }
  // assign the given name
  if( ! empty( $_REQUEST['fullName'] ) )
  {
    $userObj->modify('fullName', $_REQUEST['fullName'] );
    $name = $_REQUEST['fullName'];
  }
  // assign the given theme
  if( ! empty( $_REQUEST['theme'] ) )
  {
    $userObj->modify('theme', $_REQUEST['theme'] );
    $theme = $_REQUEST['theme'];
  }
  // assign the given privledge level
  if( ! empty( $_REQUEST['privLevel'] ) && $canChange )
  {
    $userObj->modify('priviledges', $_REQUEST['privLevel'] );
    $theme = $_REQUEST['privLevel'];
  }

  // write the user's stuff to the DB
  $userObj->update();

  return array( $email, $name, $theme );
}


###
# Checks the current values of a user's data for validity
###
# Args are:
# - none
# Returns:
# - (string) any errors encountered
###
function errorCheckForm ()
{
  global $userObj;
  $output = "";

  // check if a password has been assigned. Error out if not.
  if( empty( $userObj->modify('pass') ) )
    $output .= "Must have a password set.\n<br>";
  // check if a email has been assigned. Error out if not.
  if( empty( $userObj->modify('email') ) || ! filter_var($userObj->modify('email'), FILTER_VALIDATE_EMAIL) )
  {
    $userObj->modify('email', "");
    $output .= "Must have a valid email address set.\n<br>";
  }
  // check if a name has been assigned. Error out if not.
  if( empty( $userObj->modify('fullName') ) )
    $output .= "Must have your name set.\n<br>";

  return $output;
}

?>
