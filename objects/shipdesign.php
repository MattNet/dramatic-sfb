<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class ShipDesign extends BaseObject
{
  const table	= "sfbdrama_shipdesign";

  protected $BPV	= 0;
  protected $baseHull	= ""; // designation of base hull for this design, for conversions (two steps: to base hull, to target hull)
  protected $carrier	= 0; // number indicates how many fighter spaces
  protected $carrierHeavy	= 0; // number indicates how many fighter spaces
  protected $carrierPFT	= 0; // number indicates how many fighter spaces (PFs are 2 spaces)
  protected $carrierBomber	= 0; // number indicates how many fighter spaces
  protected $carrierHvyBomber	= 0; // number indicates how many fighter spaces
  protected $commandRating	= 0;
  protected $configA	= 0; // number of Class-A BAM spots
  protected $configB	= 0; // number of Class-B BAM spots
  protected $configLTT	= 0; // number of LTT-pod spots
  protected $configTug	= 0; // number of tug-pod spots
  protected $designator	= "";
  protected $empire	= ""; // string of empire that builds this unit. generally the same as empire->race
  protected $fighterMechLink	= 0; // number indicates how many fighter spaces on mechlinks
  protected $heavyMechLink	= 0; // number indicates how many heavy fighters on mechlinks, in spaces
  protected $obsolete	= 0; // First year that the shipdesign should no-longer be offerred
  protected $opt	= 0; // number of single option-mounts
  protected $opt2	= 0; // number of double option-mounts
  protected $shipyard	= 0;
  protected $sidcorAtk	= 0;
  protected $sidcorDmg	= 0;
  protected $sidcorCAtk	= 0;
  protected $sidcorCDmg	= 0;
  protected $sidcorEW	= 0;
  protected $sizeClass	= 0;
  protected $supplyCarry	= 0; // carrying capacity in cargo boxes
  protected $troopCarry	= 0; // carrying capacity in barracks boxes
  protected $yearInService	= 0; // First year that the shipdesign should be offerred
  protected $switches	= ""; // a comma delim'd list of: bombardment civilian cloak conjectural escort fast mineLayer scout slow tug

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
      'BPV', 'carrier', 'carrierHeavy', 'carrierPFT', 'carrierBomber', 'carrierHvyBomber', 'commandRating',
      'configA', 'configB', 'configLTT', 'configTug', 'fighterMechLink', 'heavyMechLink', 'opt', 'opt2',
      'shipyard', 'sizeClass', 'sidcorAtk', 'sidcorDmg', 'sidcorCAtk', 'sidcorCDmg', 'sidcorEW', 'supplyCarry',
      'troopCarry', 'yearInService'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
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
      'BPV'	=> $this->BPV,
      'baseHull'	=> $this->baseHull,
      'carrier'	=> $this->carrier,
      'carrierHeavy'	=> $this->carrierHeavy,
      'carrierPFT'	=> $this->carrierPFT,
      'carrierBomber'	=> $this->carrierBomber,
      'carrierHvyBomber'	=> $this->carrierHvyBomber,
      'commandRating'	=> $this->commandRating,
      'configA'	=> $this->configA,
      'configB'	=> $this->configB,
      'configLTT'	=> $this->configLTT,
      'configTug'	=> $this->configTug,
      'designator'	=> $this->designator,
      'empire'	=> $this->empire,
      'fighterMechLink'	=> $this->fighterMechLink,
      'heavyMechLink'	=> $this->heavyMechLink,
      'obsolete'	=> $this->obsolete,
      'opt'	=> $this->opt,
      'opt2'	=> $this->opt2,
      'shipyard'	=> $this->shipyard,
      'sidcorAtk'	=> $this->sidcorAtk,
      'sidcorDmg'	=> $this->sidcorDmg,
      'sidcorCAtk'	=> $this->sidcorCAtk,
      'sidcorCDmg'	=> $this->sidcorCDmg,
      'sidcorEW'	=> $this->sidcorEW,
      'sizeClass'	=> $this->sizeClass,
      'supplyCarry'	=> $this->supplyCarry,
      'troopCarry'	=> $this->troopCarry,
      'yearInService'	=> $this->yearInService,
      'switches'	=> strtolower( $this->switches )
    );
    return $output;
  }

}

?>
