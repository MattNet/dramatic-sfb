<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );
require_once( dirname(__FILE__) . "/shipdesign.php" );

class Ship extends BaseObject
{
  const table	= "sfbdrama_ship";
  const ACTIVE	= 1;
  const RESERVE	= 2;
  const MOTHBALL	= 3;

  protected $captureEmpire	= 0; // non-zero empire ID if this unit will change hands at the end of the turn
  protected $configuration	= "";
  protected $damage	= 0; // percentage of damage this unit has sustained
  protected $design	= 0; // id of ShipDesign object
  protected $empire	= 0; // id of owning empire object. If captured, then the 'design' property will reflect that
  protected $game	= 0; // id of the game object
  protected $isDead	= false; // true if the unit is not to be carried forward at the end of the turn.
  protected $isInEncounter	= false; // true if the unit is to be in an encounter this turn. Note: not put into DB
  protected $locationIsLane	= false; // true if it's MapSector ID is actually a JumpLane ID
  protected $manifest	= 0; // id of manifest object (cargo ships only, 0 for all others)
  protected $mapObject	= 0; // id of MapObject object it is located at
  protected $mapSector	= 0; // id of MapSector object it is located at
  protected $previousLocation	= 0; // id of mapSecter/lane object that the unit just moved from. Note: not put in DB
  public    $specs	= array(); // holds all of the shipDesign properties so they won't polute this namespace. Note: Not put in DB
  protected $status	= 0; // (Active, Reserve, Mothball)
  protected $stopMove	= false; // flag to stop movement for the rest of the turn. Note: Not put into DB
  protected $supplyLevel	= 0; // The out-of-supply level for this unit. 0 means unit is in supply
  protected $textName	= ""; // The name of the ship, as per the player
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
      'captureEmpire', 'damage', 'design', 'empire', 'game', 'manifest',
      'mapObject', 'mapSector', 'status', 'supplyLevel', 'turn'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Creates itself in the database from memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function create()
  {
    $output = parent::create();

    // Import the ShipDesign properties
    if( $this->design != 0 )
    {
      $targetShipDesign = new ShipDesign( $this->design );
      $targetShipDesign->read();
      $targetShipDesign->modify('autowrite', false);

      $this->specs = $targetShipDesign->values();
      unset( $targetShipDesign );
    }

    return $output;
  }

  ###
  # Reads itself from the database into memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function read()
  {
    $output = parent::read();

    // Import the ShipDesign properties
    if( $this->design != 0 )
    {
      $targetShipDesign = new ShipDesign( $this->design );
      $targetShipDesign->read();
      $targetShipDesign->modify('autowrite', false);

      $this->specs = $targetShipDesign->values();
      unset( $targetShipDesign );
    }

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
      'captureEmpire'	=> $this->captureEmpire,
      'configuration'	=> $this->configuration,
      'damage'	=> $this->damage,
      'design'	=> $this->design,
      'empire'	=> $this->empire,
      'game'	=> $this->game,
      'isDead'	=> $this->isDead,
      'locationIsLane'	=> $this->locationIsLane,
      'manifest'	=> $this->manifest,
      'mapObject'	=> $this->mapObject,
      'mapSector'	=> $this->mapSector,
      'status'	=> $this->status,
      'supplyLevel'	=> $this->supplyLevel,
      'textName'	=> $this->textName,
      'turn'	=> $this->turn
    );
    return $output;
  }
}

?>
