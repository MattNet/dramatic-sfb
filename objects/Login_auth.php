<?php
/*
###
# Session management and password management object
###

Methods:
checkPrivs() - Determines if the player has the given priviledge
encryptedPassword() - gets or sets the encrypted password
generateNewSession() - creates a new session ID from the supplied username
getSessionRequest() - returns the session argument and sessionID as if part of a GET request
getSessionTag() - returns the session argument and sessionID as an HTML hidden tag
getUser() - retrieves the user from the session ID
plainPassword() - sets the plaintext password
storeSession() - stores the session in the player object
verifySession() - Ensures the session in the database has not expired
*/

class Auth
{
  private $ENCRYPT_SALT = "StarFleetDramaSoftware";	// Salting phrase for password encyption
  private $ENCRYPT_COST = 10;	// Processor cost for password encyption
  private $SESSION_NAME = "SFBDRAMALOGINSESSION";	// Name to give internally to the sessions
  private $EXPIRE_TIME = 0;	// time to allow a session to live, in seconds

  private $encryptedPassword = "";	// the password after encryption
  private $plainTextPassword = "";	// the password before encryption
  private $newSession = false;	// True if we are told there will be a new session-pad generated

  public $hasError = false;	// True if some error was generated inside the object

###
# Class constructor
###
function __construct( $isNew=false )
{
  global $LOGIN_EXPIRE_TIME;
  $this->EXPIRE_TIME = $LOGIN_EXPIRE_TIME;

  $this->newSession = $isNew;
  session_name($this->SESSION_NAME);
  if( $this->newSession )
    return;
  session_start();
  $status = session_status();
  if( $status != PHP_SESSION_ACTIVE )
    $this->hasError = true;
}
###
# Class destructor
###
function __destruct ()
{
  session_write_close();
}

###
# Determines if the player has the given priviledge
###
# Args are:
# - (object) The player object to check against
# - (string) The priviledge to check for
# Returns:
# - (bool) True if the player can perform the action. False otherwise
###
function checkPrivs( $obj, $priv )
{
  global $PRIVILEDGE_LEVELS;
  if( ! method_exists( $obj, "modify" ) || empty( $obj->modify('priviledges') ) )
    return false;
  $privList = $PRIVILEDGE_LEVELS[ $obj->modify('priviledges') ];
  if( is_array($privList) && in_array( $priv, $privList ) )
    return true;
  return false;
}

###
# Encrypts a password for storage into the database
###
# Args are:
# - (string) [optional] the encrypted password. If not otherwise set, will encrypt the plaintext password
# Returns:
# - (string) the encrypted password
###
function encryptedPassword( $password="" )
{
  if( empty( $password ) )
    $this->encryptedPassword = "";
  if( empty( $this->encryptedPassword ) && ! empty( $this->plainTextPassword ) )
    $this->encryptedPassword = password_hash(
			$this->plainTextPassword,
			PASSWORD_BCRYPT,
			array('salt'=>$this->ENCRYPT_SALT,'cost'=>$this->ENCRYPT_COST)
		);
  return $this->encryptedPassword;
}

###
# Quits the current session
###
# Args are:
# - (object) the player object to store the session into
# Returns:
# - none
###
function endSession( $obj )
{
  // destroy the session in the server-side PHP
  // does not attempt to kill the client-side cookie. Re-verification of the session will do that when they log in again
  $_SESSION = array();
  session_destroy();
  // destroy the session in the database. This (if nothing else) will make the session verification fail
  $obj->modify('sessionID', "" );
  $obj->modify('sessionTime', 0 );
}

###
# Encodes the username into the session ID
###
# Args are:
# - (string) the username to encode
# Returns:
# - none
###
function generateNewSession( $username )
{
  $status = session_status();
  if( $status != PHP_SESSION_ACTIVE )
    session_start();
  else
    session_regenerate_id( true );
  $status = session_status();
  if( $status != PHP_SESSION_ACTIVE )
    $this->hasError = true;
  $this->newSession = false;
  $_SESSION['username'] = $username;
  $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
}

###
# Returns the sessionID in a form that can be passed through a GET request
###
# Args are:
# - none
# Returns:
# - (string) the sessionID and the argument to pass it. Returns a bogus string if the server will only accept cookies for the new session
###
function getSessionRequest()
{
  if( ini_get('session.use_only_cookies') )
    return "Ym9ndXM=ZGF0YQ"; // "bogus=data", B64 encoded
  return SID;
}

###
# Returns the sessionID as an HTML HIDDEN tag
###
# Args are:
# - none
# Returns:
# - (string) an HTML "input type=hidden" tag. Returns a blank string if the server will only accept cookies for the new session
###
function getSessionTag()
{
  if( ini_get('session.use_only_cookies') )
    return "";
  return "<input type='hidden' name='".session_name()."' value='".session_id()."'>";
}

###
# Decodes the username from the session ID
###
# Args are:
# - none
# Returns:
# - (string) the username found in the sessionID. False if this is supposed to be a new session
###
function getUser()
{
  if( $this->newSession || empty( $_SESSION['username'] ) )
    return false;
  return $_SESSION['username'];
}

###
# Sets a plaintext password for future encryption
###
# Args are:
# - (string) the plaintext password
# Returns:
# - none
###
function plainPassword( $password )
{
  $this->plainTextPassword = $password;
}

###
# Stores the session in the given player object
###
# Args are:
# - (object) the player object to store the session into
# Returns:
# - (bool) true for success, otherwise false
###
function storeSession( $obj )
{
  if( ! session_id() || empty( $_SERVER['REQUEST_TIME'] ) || empty($obj) )
    return false;

  $obj->modify('sessionID', session_id() );
  $obj->modify('sessionTime', $_SERVER['REQUEST_TIME'] );
  $result = $obj->update();
  return $result;
}

###
# Ensures the session in the database has not expired
###
# Args are:
# - (object) the player object to verify the session from
# Returns:
# - (bool) true for success, otherwise false
###
function verifySession( $obj )
{
  // if something important is unset, fail
  if( ! session_id() || empty( $_SERVER['REQUEST_TIME'] ) || empty($_SERVER['REMOTE_ADDR']) || empty($obj) )
  {
    $this->hasError = true;
    return false;
  }
  // if the session IDs don't match, fail
  if( $obj->modify('sessionID') != session_id() )
  {
    $this->hasError = true;
    return false;
  }
  // if the usernames don't match, fail
  if( $obj->modify('username') != $_SESSION['username'] )
  {
    $this->hasError = true;
    return false;
  }
  // if the session timestamp has expired, fail
  if( $obj->modify('sessionTime' ) < ($_SERVER['REQUEST_TIME'] - $this->EXPIRE_TIME)  )
  {
    $this->hasError = true;
    return false;
  }
  // if the session IP address's don't match, fail
  if( $_SESSION['IP'] != $_SERVER['REMOTE_ADDR'] )
  {
    $this->hasError = true;
    return false;
  }

  // it must be all right. Pass.
  return true;
}

#*#*#*#

###
# Creates the front-end HTML for the forgotten password screen
###
# Args are:
# - (string) the target HTML file to point to upon success
# - (string) [optional] the CSS class to give the elements produced by this function
# Returns:
# - (string) an HTML text form that accepts the credentials
###
function forgotFrontEnd( $target, $style="" )
{
  $output = "<form action='$target' method='post' target='_SELF' enctype='text/plain' class=''>\n";
  $output .= "Username: <input type='text' name='username' required placeholder='User Name' class=''>\n";
  $output .= "<br>Your email according to your account:\n";
  $output .= "<input type='text' name='email' required placeholder='Email Address' class=''>\n";
  $output .= "<br><input type='submit' value='Login' class=''>\n</form>\n";
  return $output;
}

}
?>
