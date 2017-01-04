<?php
/*
Lost Password page

Ask a couple of questions:
- Username
- email address
- first business entry name (requires lookup against entry objects)
- - Scan all entries for user matching username
- - find the one with the lowest ID
- - match business name
email user that forgotten password has been used
possibly set them to 'unapproved' (moderator needs to OK them)
Go to listing page if possible

*/

include_once( dirname(__FILE__) . "/Login_config.php");

$TEMPLATE_FILE = dirname(__FILE__)."/templates/Login_lostpass.html";
$EMAIL_TEMPLATE = "/Login/templates/Login_template_lost_password.txt"; // email template for when the lost password was successfully used
$GOTO_ON_BACK = $LOGIN_START_FILE;
$GOTO_ON_FAIL = $LOGIN_START_FILE;
$GOTO_ON_FORWARD = $_SERVER['PHP_SELF'];
$GOTO_ON_SUCCESS = "/Login/Login.php";
$ERROR_GET_STRING = "?error=";	// what to put in the URL when there is an error

// Handle the form return and then speed off to $GOTO_ON_SUCCESS
if( ! empty($_REQUEST['finished']) )
{

  include_once( dirname(__FILE__) . "/../objects/Login_database.php");
  include_once( dirname(__FILE__) . "/../objects/entry.php");
  include_once( dirname(__FILE__) . "/../objects/Login_user.php");
  include_once( dirname(__FILE__) . "/Login_email.php");

  date_default_timezone_set($TIMEZONE);

  $database = ""; // the database object, so we can retrieve the entries this user created
  $carName = $_REQUEST['carName'];
  $entryName = $_REQUEST['businessName'];
  $email = $_REQUEST['email'];
  $fullName = $_REQUEST['fullName'];
  $userName = $_REQUEST['name'];
  $userObj = ""; // the Object for the user's data

  // Check the input form
  if( empty( $email ) || empty( $userName ) || empty( $entryName ) || ! filter_var($email, FILTER_VALIDATE_EMAIL)  )
    redirectError("Invalid entry");

  $database = DataBase::giveme();

// create a user with this name
  $userObj = new User( array('username'=>$userName) );
  $userObj->modify( 'autowrite', false );
  // then see if we can get an ID that matches this user
  $result = $userObj->getID('username');
  // if we got an ID for this object, then read the rest of the object
  if( $result )
    $result = $userObj->read();
  // if we don't get an ID or the read isn't successful, then error out
  if( ! $result )
    redirectError("Invalid entry");

// match Full Name
  if( strtolower($fullName) != strtolower($userObj->modify('fullName')) )
    redirectError("Invalid entry");

// match email addresses
  if( strtolower($email) != strtolower($userObj->modify('email')) )
    redirectError("Invalid entry");

// Get the business from the $userObj with the lowest ID
  $query = "select carType, companyName, id from ".Entry::table." where aaUser = ".$userObj->modify('id')." order by id limit 1";
  $result = $database->genquery( $query, $entries );

  if( $result )
  {
// match business names
    if( $entryName != $entries[0]['companyName'] )
      redirectError("Invalid entry");

// match car-type names
  if( $carName != $entries[0]['carType'] )
    redirectError("Invalid entry");
  }

// If we get this far, then everything matched, and the user is legitimate

// email the user that the function was used
  $msgBody = file_get_contents($EMAIL_TEMPLATE);
  // when replacing template text, the order to use is: [User's Name], [today's date], [Server's business-name]
  $msgBody = sprintf( $msgBody, $userObj->modify('fullName'), date("l, F j, Y"), $BUSINESS_GIVEN_NAME );
  send_email( $msgBody, $userObj->modify('email'), "Lost Paswsord at ".$_SERVER['HTTP_HOST'] );

// possibly render this user unapproved
  if( $RENDER_UNAPPROVED_IF_LOST_PASSWORD )
  {
    $userObj->modify( 'isApproved', false );
    $userObj->update();
  }

  header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_SUCCESS?login=$userName" );
  exit();
}

// determine what HTML needs to be inserted in the various HTML inputs
$businessTag = "<input type='text' name='businessName' autocomplete='off' class=''>";
$carTag = "<input type='text' name='carName' autocomplete='off' class=''>";
$emailTag = "<input type='text' name='email' autocomplete='off' class=''>";
$errorTag = "";
$formTag = "<form action='$GOTO_ON_FORWARD' method='post' target='_SELF'>\n";
$fullNameTag = "<input type='text' name='fullName' autocomplete='off' class=''>";
$submitTag = "<input type='submit' name='finished' value='Reset Password' class=''>";
$tag_back = "<span class='button'><a href='$GOTO_ON_BACK'>Back</a></span>";
$userNameTag = "<input type='text' name='name' autocomplete='off' class=''>";

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
  header( "Location: http://{$_SERVER['SERVER_NAME']}$GOTO_ON_FAIL$ERROR_GET_STRING".urlencode($error) );
  exit();
}

?>
