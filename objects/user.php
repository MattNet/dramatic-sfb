<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class User extends BaseObject
{
  const table	= "player";

  protected $configuration	= "";	// token string for configuration items for the user
  protected $email	= "";
  protected $fullName	= "";
  protected $isApproved	= false;	// this user has been approved by someone with 'canApprove' privs
  protected $isVerified	= false;	// This user has verified their email account
  protected $pass	= "";	// stored encrypted
// Privs are set as a codeword. "decoded" to privs after login to perform these actions
  protected $priviledges	= "";	// may create games, join games, close games.
  protected $sessionID	= "";	// Identifier for the latest session
  protected $sessionTime	= 0;	// time of the latest session
  protected $signupDate	= "";	// NOTE: only extracted from the DB, never stored
  protected $theme	= "";
  protected $username	= "";
  protected $verifyKey	= "";	// Random string, used to verify new accounts

  ###
  # The Class Constructor
  ###
  # Args are:
  # - (integer) The Identifier number for this object.
  #   If an array, then the values are put into the properties matching the array keys
  # Returns:
  # - None
  ###
  function __construct( $id = 0 )
  {
    parent::__construct( $id );
    $intProps = array(
      'sessionTime'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Configuration getter and setter
  ###
  # Args are:
  # - (string) The configuration item to retrieve
  # - (string) [optional] The value to set the configuration item to
  # Returns:
  # - (string) The value stored for the configuration item.
  #            NULL for an inability to retrieve the requested value
  ###
  function config( $get, $set=null )
  {
    $deliminator = ",";
    $configArray = explode( $deliminator, $this->configuration );
    $targetKey = false; // false if this is a new key to the array. The key that has the $set value is ($targetKey+1)

    $targetKey = array_search( $get, $configArray ); // $targetKey has our index into the $configArray

    // set the value of our item, if needed
    if( $set !== null )
      if( $targetKey === false )
      {
        $configArray[] = (string) $get;
        $configArray[] = (string) $set;

        if( empty($configArray[0]) )	// remove a leading blank entry
          array_shift($configArray);

        $this->configuration = implode( $deliminator, $configArray );
        $this->taint = true;
        return $set;
      }
      else
      {
        $configArray[($targetKey+1)] = (string) $set;

        if( empty($configArray[0]) )	// remove a leading blank entry
          array_shift($configArray);

        $this->configuration = implode( $deliminator, $configArray );
        $this->taint = true;
        return $configArray[($targetKey+1)];
      }

    if( $targetKey === false )
      return false; // Nothing to retrieve, was not asked to set
    return $configArray[($targetKey+1)];
  }

  ###
  # Returns those properties which need to be stored in the database
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) List of property_names => property_values
  ###
  function values ()
  {
    $output = array(
      'configuration'	=> $this->configuration,
      'email'	=> $this->email,
      'fullName'	=> $this->fullName,
      'isApproved'	=> $this->isApproved,
      'isVerified'	=> $this->isVerified,
      'pass'	=> $this->pass,
      'priviledges'	=> $this->priviledges,
      'sessionID'	=> $this->sessionID,
      'sessionTime'	=> $this->sessionTime,
      'theme'	=> $this->theme,
      'username'	=> $this->username,
      'verifyKey'	=> $this->verifyKey
    );
    return $output;
  }

}

?>
