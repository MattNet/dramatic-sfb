<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class Empire extends BaseObject
{
  const table	= "sfbdrama_empire";

  protected $advance	= 0; // does this empire have it's orders/combat-results in, and is ready to advance the turn another step?
  protected $ai		= ""; // notes from the AI to itself
  protected $borders	= ""; // comma delim'd list of borders shared with other empires. Format is: empire ID, amt, empire ID, ...
  protected $game	= 0; // id of the game object
  protected $income	= 0; // The amt of EPs added per turn
  protected $player	= 0; // id of the player object
  protected $race	= "";
  protected $status	= ""; // 'delete' to remove this empire from the game at the end of the turn
  protected $storedEP	= 0; // The amt of EPs currently held
  protected $textName	= "Empire Name"; // The name of the empire, as per the player
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
  function __construct( $id = 0, $reference = false )
  {
    $reference = (bool) $reference; // this is a readonly flag

    parent::__construct( $id, $reference );
    $intProps = array(
      'game', 'income', 'player', 'storedEP', 'turn'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Adds or modifies an entry to the 'borders' property
  ###
  # Args are:
  # - (int) The Empire ID that the border is shared with
  # - (int) The new size of the border with that empire
  # Returns:
  # - none
  ###
  function bordersChange( $empireID, $amt )
  {
    $borderList = explode( ",", $this->borders );
    $isModified = false;
    $empireID = intval($empireID);
    $amt = intval($amt);

    for( $i=1; $i<count($borderList); $i+=2 )
      // if we found the empire entry, change it's amount
      if( $borderList[($i-1)] == $empireID )
      {
/*
// this 'remove if zero' means new ones can't be added by the HTML because it's 
// a two-step process: add the empire, *then* add the amt for that empire
        if( $amt <= 0 )
          // if the amount is 0, then remove the entry
          unset( $borderList[$i] );
        else
          // otherwise change it's value
*/
          $borderList[$i] = $amt;
        $isModified = true;
        break;
      }
    // if we didn't find the entry, then add it
    if( ! $isModified )
    {
      $borderList[] = $empireID;
      $borderList[] = $amt;
      // fix the array if the first element is blank
      if( $borderList[0] == "" )
        array_shift($borderList);
    }

    // write it back to the object
    $this->borders = implode( ",", $borderList );
  }

  ###
  # Turns the 'borders' string into an associated array
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) List of other_empire_ID => num_of_borders_shared
  ###
  function bordersDecode ()
  {
    $borderArray = array();
    $borderList = explode( ",", $this->borders );
    // fix the array if the first element is blank
    if( $borderList[0] == "" )
      array_shift($borderList);

    for( $i=1; $i<count($borderList); $i+=2 )
      $borderArray[ $borderList[($i-1)] ] = $borderList[$i];
    return $borderArray;
  }

  ###
  # Finds the value for a certain bordering empire
  ###
  # Args are:
  # - (int) the empire to look for
  # Returns:
  # - (int) the bordering amount
  ###
  function bordersFind( $findEmpire )
  {
    $findArray = $this->bordersDecode();
    if( isset( $findArray[ $findEmpire ] ) )
      return $findArray[ $findEmpire ];
    // if nothing is found, return 0
    return 0;
  }

  ###
  # Removes an entry to the 'borders' property
  ###
  # Args are:
  # - (int) The Empire ID that the border is shared with
  # Returns:
  # - none
  ###
  function bordersKill( $empireID )
  {
    $borderList = explode( ",", $this->borders );

    for( $i=1; $i<count($borderList); $i+=2 )
      // if we found the empire entry, remove it
      if( $borderList[($i-1)] == $empireID )
      {
        unset( $borderList[$i] );
        unset( $borderList[$i-1] );
        break;
      }

    // write it back to the object
    $this->borders = implode( ",", array_values($borderList) );
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
      case 'advance':
        $this->advance = (int) $value;
        break;
      case 'game':
        $this->game = (int) $value;
        break;
      case 'income':
        $this->income = (int) $value;
        break;
      case 'player':
        $this->player = (int) $value;
        break;
      case 'storedEP':
        $this->storedEP = (int) $value;
        break;
      case 'turn':
        $this->turn = (int) $value;
        break;
      }
    return parent::modify( $property, $value );
  }

  ###
  # Updates itself in the database from memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function update()
  {
    $output = parent::update();
    return $output;
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
      'advance'	=> $this->advance,
      'ai'	=> $this->ai,
      'borders'	=> $this->borders,
      'game'	=> $this->game,
      'income'	=> $this->income,
      'player'	=> $this->player,
      'race'	=> $this->race,
      'status'	=> $this->status,
      'storedEP'	=> $this->storedEP,
      'textName'	=> $this->textName,
      'turn'	=> $this->turn
    );
    return $output;
  }

}

?>
