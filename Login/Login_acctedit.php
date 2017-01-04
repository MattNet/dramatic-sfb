<?php
/*
Edit Account page

Allows the user to edit their login details
*/

include_once( dirname(__FILE__) . "/Login_config.php");

$ACCOUNT_LIST_TEMPLATE = dirname(__FILE__)."/templates/Login_acct_list.html";
$TEMPLATE_FILE = dirname(__FILE__)."/templates/Login_acctedit.html";
$APPROVAL_GET_STRING = "approve";	// Post string for approving of an account
$APPROVE_NAME_STRING = "approve";	// HTML name for the approve-account function
$EMAIL_UPDATE_STRING = "emailupdate";	// HTML name for allowing updates to the games being emailed
$GOTO_ON_BACK = $LOGIN_EXIT_FILE;
$GOTO_ON_FAIL = $LOGIN_START_FILE;
$GOTO_ON_FORWARD = $_SERVER['PHP_SELF'];
$GOTO_ON_UNVERIFIED = "/Login/Login_unverified.php";
$GOTO_ON_UNAPPROVED = "/Login/templates/Login_unapproved.html";
$LIST_SIZE = 0.75;	// the amount of the account list to show in the select box (1 = 100% of the list)
$OTHER_ACCT_GET_STRING = "acct";	// Post string for other-acct-to-edit dropdown select
$PRIV_LEVEL_GET_STRING = "privLevel";	// Post string for changing the priviledge level
$SAVE_NAME_STRING = "finished";	// HTML name for the save-and-exit function

include_once( dirname(__FILE__) . "/Login_common.php" );

// Handle Logging Out
if( ! empty( $_REQUEST['logout'] ) )
{
  $authObj->endSession( $userObj );
  redirect( $GOTO_ON_FAIL );
}

// set up the privs of the original user
$canApprove = false; // can this user change other accounts?
$canChange = $authObj->checkPrivs( $userObj, "changeAcct" ); // can this user change other accounts?
$canDelete = $authObj->checkPrivs( $userObj, "deleteAcct" ); // can this user delete accounts?
$canElevate = $authObj->checkPrivs( $userObj, "elevate" ); // can this user elevate account privs?

if( $MUST_APROVE_ACCTS )
  $canApprove = $authObj->checkPrivs( $userObj, "canApprove" );

$originalUser = true; // Is this user editing themselves? false for editing a different user
$styleSheet = $THEME_DIRECTORY.$userObj->modify("theme").".css";
$editOtherAcctID = 0; // the ID number of the acct to edit
if( ! empty($_REQUEST[$OTHER_ACCT_GET_STRING]) )
  $editOtherAcctID = intval($_REQUEST[$OTHER_ACCT_GET_STRING]);

// Put up an account list when asked to do an "Other Account Adjustment"
if( ! empty( $_REQUEST[$ADJUST_OTHER_GET_STRING] ) && ( $canDelete || $canChange || $canApprove ) )
{
  // setup the tags for the template file
  $size = 3; // the HTML size of the select list. This number is a minimum to show
  $acctList = ""; // The list of all accounts, for modification or deletion
  $appList = ""; // The list of the accounts needing approval
  if( $canDelete || $canChange )
  {
    $acctList = populateAcctLists();
    $amt = count($acctList) * $LIST_SIZE; // amount of the list to show
    if( $size < $amt )
      $size = $amt;
    // add the HTML select container around the acctList
    $acctList = "<select name='$OTHER_ACCT_GET_STRING' size=$size>\n".$acctList."</select>";
  }
  if( $canApprove )
  {
    $appList = populateAppLists();
    $amt = count($appList) * $LIST_SIZE; // amount of the list to show
    if( $size < $amt )
      $size = $amt;
    // add the HTML select container around the appList. Note the same HTML 'name' as the $acctList
    $appList = "<select name='$OTHER_ACCT_GET_STRING' size=$size>\n".$appList."</select>";
  }

  $formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
  $formTag .= $authObj->getSessionTag();
  $submitTag = "<input type='submit' name='editAcct' value='Edit Account'>\n";
  $backTag = "<a href='$GOTO_ON_BACK?".$authObj->getSessionRequest()."'>Account Menu</a>";

  // send out the template file
  header( 'Cache-Control: no-cache, must-revalidate' );
  include( $ACCOUNT_LIST_TEMPLATE );
  exit();
}

// change users if we are editing a different account
if( $editOtherAcctID > 0 && ( $canDelete || $canChange || $canApprove || $canElevate ) && $editOtherAcctID != $userObj->modify('id') )
{
  // create the userObj of the person we're editing, based on the ID provided in the POST data
  // overwrite the $userObj given in the common file
  // (e.g. forget who is doing the editing. that data is still in the session variable)
  $userObj = new user( array( 'id'=> $editOtherAcctID ) );
  // make it so the user object won't save itself
  $userObj->modify( 'autowrite', false );
  // read the rest of the user if we can
  $result = $userObj->read();

  if( ! $result )
    redirect($GOTO_ON_FAIL);

  $originalUser = false;
}

if( $canDelete && ! $originalUser )
{
  // handle deleting the entry
  if( ! empty($_REQUEST['delete']) )
  {
    if( ! $userObj->destroy() )
    {
      $errorString = urlencode("Unable to remove user ".$userObj->modify('fullName')." (".$userObj->modify('username')."). ");
      if( $SHOW_DB_ERRORS )
        $errorString .= ( urlencode($database->error_string) );
      redirect($GOTO_ON_FAIL.$ERROR_GET_STRING.$errorString );
    }
    redirect($GOTO_ON_BACK);
  }
}

// Assign IAW the input form
list( $emailValue, $nameValue, $privValue, $themeValue ) = assignViaForm( $canChange );

// Validate the currently-assigned values
$errors = errorCheckForm();

// If the "save and exit" was hit
if( ! empty($_REQUEST[$SAVE_NAME_STRING]) && empty($errors) )
{
  // if we need an email verification of the acct if it has not already been verified
  if( $MUST_VERIFY_EMAIL && ! $userObj->modify('isVerified') && $originalUser )
  {
    generateVerifyKey();
    redirect( $GOTO_ON_UNVERIFIED );
  }
  else if( $MUST_APROVE_ACCTS && ! $userObj->modify('isApproved') && $originalUser )
  {
    generateVerifyKey();
    redirect( $GOTO_ON_UNAPPROVED );
  }
  else
  {
    redirect( $GOTO_ON_BACK );
  }
}

if( ! empty($_REQUEST[$APPROVE_NAME_STRING]) && $canApprove )
{
  $userObj->modify('isApproved', true);
  $userObj->update();
}

// allow the user data to be changed, if allowed
if( $canChange || $originalUser )
{
  // determine what HTML needs to be inserted in the various HTML inputs
  $emailTagInsert = "";
  $fullNameTagInsert = "";
  $passwordTagInsert = "";
  // the email input
  if( ! empty( $userObj->modify('email') ) )
    $emailTagInsert .= " placeholder='".$userObj->modify('email')."'";
  else
    $emailTagInsert .= " required";
  if( ! empty( $emailValue ) )
    $emailTagInsert .= " value='$emailValue'";
  // the full-name input
  if( ! empty( $userObj->modify('fullName') ) )
    $fullNameTagInsert .= " placeholder='".$userObj->modify('fullName')."'";
  else
    $fullNameTagInsert .= " required";
  if( ! empty( $nameValue ) )
    $fullNameTagInsert .= " value='$nameValue'";
  // the password inputs
  if( $userObj->modify('pass') == "" )
    $passwordTagInsert .= " required";
  // The theme
  if( empty( $themeValue ) )
    $themeValue = $userObj->modify('theme');

  $emailTag = "<input type='text' name='email'$emailTagInsert autocomplete='off' class=''>";
  $fullNameTag = "<input type='text' name='fullName'$fullNameTagInsert autocomplete='off' class=''>";
  $password1Tag = "<input type='password' name='pass1' class=''$passwordTagInsert autocomplete='off'>";
  $password2Tag = "<input type='password' name='pass2' class=''$passwordTagInsert autocomplete='off'>";
  $themeTag = "<select name='theme'>\n";
  foreach( $THEMES as $themeName )
  {
    $themeTag .= "<option value='$themeName'";
    if( $themeName == $themeValue )
      $themeTag .= " selected";
    $themeTag .= ">$themeName</option>\n";
  }
  $themeTag .= "</select>\n";
}
else
{
  $emailTag = $userObj->modify('email');
  $fullNameTag = $userObj->modify('fullName');
  $password1Tag = "";
  $password2Tag = "";
  $themeTag = $userObj->modify('theme');
}
$approveButton = ""; // the button to approve an account for posting
$deleteTag = " &bull; <input type='submit' name='delete' value='Delete Account' class='' onclick='return deleteConfirm();'>\n";
if( ! $canDelete || ( $canDelete && $originalUser ) )
  $deleteTag = "";
$emailUpdateTag = "<input type='checkbox' name='$EMAIL_UPDATE_STRING'";
if( $userObj->config('emailUpdate') )
  $emailUpdateTag .= " CHECKED";
$emailUpdateTag .= " >";
$errorTag = $errors;
$formTag = "<form action='$GOTO_ON_FORWARD' method='post' target='_SELF'>\n";
$isApproved = "No";
if( $userObj->modify('isApproved') )
  $isApproved = "Yes";
$isVerified = "No";
if( $userObj->modify('isVerified') )
  $isVerified = "Yes";
$javascript = "<script type='text/javascript'>function deleteConfirm(){r = window.confirm('Do you want to delete this user? It cannot be undone.');return r}</script>";
$privsTag = $userObj->modify('priviledges');
if( $canElevate && ! $originalUser )
  $privsTag = getPrivs( $userObj->modify('priviledges') );
$signupTag = $userObj->modify('signupDate');
$submitTag = "<input type='submit' name='$SAVE_NAME_STRING' value='Save and Exit' class=''>";
$tag_back = "<a href='$GOTO_ON_BACK'>Back</a>";
$userNameTag = $userObj->modify('username');
$userNameTag .= "<input type='hidden' name='user' value='".$userObj->modify('id')."'>";

if( $canApprove && $userObj->modify('isApproved') == false )
  $approveButton = "<input type=submit name='$APPROVE_NAME_STRING' value='Approve This Account'>";

// if this is editing a different acct, note that fact in the form
if( ! $originalUser && ( $canDelete || $canChange || $canApprove || $canElevate ) )
  $formTag .= "<input type='hidden' name='$OTHER_ACCT_GET_STRING' value='$editOtherAcctID'>\n";

header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();


###
# Assigns the values given by the input form
###
# Args are:
# - (bool) true if this user can change privleges
# Returns:
# - (array) a list of the email value given, the name value given, and the theme value given
###
function assignViaForm( $canChangePrivs )
{
  global $authObj, $userObj, $EMAIL_UPDATE_STRING, $PRIVILEDGE_LEVELS, $PRIV_LEVEL_GET_STRING, $THEMES;
  global $SAVE_NAME_STRING;
  $email = "";
  $name = "";
  $newEmailUpdate = "";
  $privs = "";
  $theme = "";
  $isAnUpdate = false; // flag to perform the DB update

  if( isset($_REQUEST[$EMAIL_UPDATE_STRING]) )
    $newEmailUpdate = (bool) $_REQUEST[$EMAIL_UPDATE_STRING];	// an explicit casting to boolean
  else
    $newEmailUpdate = false;

  if( empty($_REQUEST[$SAVE_NAME_STRING]) )
    return array( $email, $name, $privs, $theme );

  // assign the given password
  if( ! empty( $_REQUEST['pass1'] ) || ! empty( $_REQUEST['pass2'] ) )
  {
    // check that the passwords match and then write
    if( $_REQUEST['pass1'] == $_REQUEST['pass2'] )
    {
      $authObj->plainPassword($_REQUEST['pass1']);
      $userObj->modify('pass', $authObj->encryptedPassword() );
      $isAnUpdate = true;
    }
  }
  // assign the given email
  if( ! empty( $_REQUEST['email'] ) )
  {
    $userObj->modify('email', filter_var( $_REQUEST['email'], FILTER_SANITIZE_EMAIL) );
    $email = $userObj->modify('email');
    $isAnUpdate = true;
  }
  // assign the given name
  if( ! empty( $_REQUEST['fullName'] ) )
  {
    $userObj->modify('fullName', $_REQUEST['fullName'] );
    $name = $_REQUEST['fullName'];
    $isAnUpdate = true;
  }
  // assign the given privledge level
  if( ! empty( $_REQUEST[$PRIV_LEVEL_GET_STRING] ) && $canChangePrivs && isset($PRIVILEDGE_LEVELS[$_REQUEST[$PRIV_LEVEL_GET_STRING]]) )
  {
    $userObj->modify('priviledges', $_REQUEST[$PRIV_LEVEL_GET_STRING] );
    $privs = $_REQUEST[$PRIV_LEVEL_GET_STRING];
    $isAnUpdate = true;
  }
  // assign the given theme
  if( ! empty( $_REQUEST['theme'] ) && in_array($_REQUEST['theme'], $THEMES) )
  {
    $userObj->modify('theme', $_REQUEST['theme'] );
    $theme = $_REQUEST['theme'];
    $isAnUpdate = true;
  }

  if( $userObj->config('emailUpdate') != $newEmailUpdate )
  {
    $userObj->config('emailUpdate', $newEmailUpdate);
    $isAnUpdate = true;
  }

  // write the user's stuff to the DB
  if( $isAnUpdate )
    $userObj->update();
  return array( $email, $name, $privs, $theme );
}

###
# Checks the current values of a player's data for validity
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

###
# Creates a verification key, if needed. The key is used to verify the email of a new acct
###
# Args are:
# - None
# Returns:
# - (string) The key generated, or false for no key needed. This is also placed in the user's acct data
###
function generateVerifyKey ()
{
  global $userObj;

  if( empty( $userObj->modify('verifyKey') ) )
  {
    // encode the user's ID into the key
    // inflate the number to 10 places and then append a unique string to the end
    $key = str_pad( $userObj->modify('id'), 10, 0, STR_PAD_LEFT ) . time();

    // Base64 encode the key
    $key = rtrim( base64_encode( $key ), "=" );

    $userObj->modify('verifyKey', $key );
    $userObj->update();
    return $key;
  }
  return false;
}

###
# Returns an HTML drop-down box of the priv levels available to select
###
# Args are:
# - (string) The priv level to select by default
# Returns:
# - (string) An HTML drop-down box
###
function getPrivs( $selectedPrivs = "" )
{
  global $PRIVILEDGE_LEVELS, $PRIV_LEVEL_GET_STRING;
  $privLevelNames = array_keys($PRIVILEDGE_LEVELS);
  $output = "<select name='$PRIV_LEVEL_GET_STRING'>\n";
  foreach( $privLevelNames as $levelName )
  {
    // if $selectedPrivs points to this priv-level entry, then make it default
    if( ! empty($selectedPrivs) && strtolower($selectedPrivs) == strtolower($levelName) )
      $output .= "<option value='$levelName' SELECTED>$levelName</option>";
    else
      $output .= "<option value='$levelName'>$levelName</option>";
  }
  $output .= "</select>\n";
  return $output;
}

###
# Pulls out the data needed to populate the lists of accounts needing approval
###
# Args are:
# - none
# Returns:
# - (string) a list of HTML OPTION tags, one for each acct
###
function populateAppLists()
{
  global $SHOW_DB_ERRORS, $errors;

  $database = Database::giveme();	// get the database object
  $output = ""; // the returned list at the end of the function
  $query = ""; // the DB query string
  $result = ""; // the result of the DB query

  // look through all of the players for the pertinant data
  $query = "SELECT id,fullName,username FROM ".User::table." WHERE isApproved=false";
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
      $output .= "<option value='{$row['id']}'>{$row['fullName']}({$row['username']})</option>\n";
  }
  return $output;
}

###
# Pulls out the data needed to populate the lists of all accounts
###
# Args are:
# - none
# Returns:
# - (string) a list of HTML OPTION tags, one for each acct
###
function populateAcctLists()
{
  global $SHOW_DB_ERRORS, $errors;

  $database = Database::giveme();	// get the database object
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
      $output .= "<option value='{$row['id']}'>{$row['fullName']}({$row['username']})</option>\n";
  }
  return $output;
}

?>
