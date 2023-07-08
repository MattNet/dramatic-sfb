<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class Encounter extends BaseObject
{
  const table	= "sfbdrama_encounter";
  const NEEDS_RESOLUTION	= 0;
  const OVERWHELMING_FORCE	= 1;
  const PLAYER_A_VICTORY	= 2;
  const PLAYER_A_DEFEATED	= 3;
  const APPLY_NO_RESULT	= 4;

  protected $game	= 0; // id of the game object.
  protected $playerA	= 0; // the ID of first empire involved in the encounter. The "defender"
  protected $playerAShips	= array(); // a list of the playerA's ships. Not put in the DB
  protected $playerB	= 0; // the ID of second empire involved in the encounter. The "attacker"
  protected $playerBShips	= array(); // a list of the playerB's ships. Not put in the DB
  protected $scenario	= 0; // index number of the scenario in the $SCENARIOS array
  protected $status	= 0; // needs to be resolved, overwhelming force is present, victory for player A, or defeat for player A
  protected $turn	= 0;

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
      'game', 'playerA', 'playerB', 'scenario', 'status', 'turn'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Modifies or retrieves a property of this object.
  # This overloaded function sanity-checks input values
  ###
  # Args are:
  # - (string) The property to adjust
  # - (string) [optional] The value to use. Set to null if no value is to be set
  # Returns:
  # - (string) the adjusted property
  ###
  function modify( $property, $value=null )
  {
    if( $value != null )
      switch($property)
      {
      case 'game':
        $this->game = (bool) $value;
        break;
      case 'playerA':
        $this->playerA = (bool) $value;
        break;
      case 'playerB':
        $this->playerB = (int) $value;
        break;
      case 'scenario':
        $this->scenario = (int) $value;
        break;
      case 'status':
        $this->status = (int) $value;
        break;
      case 'turn':
        $this->turn = (int) $value;
        break;
      }
    return parent::modify( $property, $value );
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
      'game'	=> $this->game,
      'playerA'	=> $this->playerA,
      'playerB'	=> $this->playerB,
      'scenario'	=> $this->scenario,
      'status'	=> $this->status,
      'turn'	=> $this->turn
    );
    return $output;
  }
}

?>
