<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class Game extends BaseObject
{
  const table	= "sfbdrama_game";
  const STATUS_CLOSED	= "closed";
  const STATUS_OPEN	= "open";
  const STATUS_PROGRESSING	= "progressing";
  const TURN_SECTION_EARLY	= 0;
  const TURN_SECTION_MID	= 1;
  const TURN_SECTION_LATE	= 2;

  protected $currentTurn	= 0;
  protected $currentSubTurn	= 0; // Number of sub-turns that have passed since the last $currentTurn.
                                     // If this reaches $buildSpeed then we do a real turn.
  protected $gameName	= "";
  protected $gameStart	= 0; // The starting year of the game
  protected $interestedPlayers	= ""; // comma delim'd list of player IDs that are interested in the game
  protected $moderator	= 0; // player object ID for the player who has moderator privledges for this game
// a list of randomly generated values, one used per turn function, to be able to re-create a turn with minimal disturbance.
  protected $randomSeeds	= ""; // comma delim'd list
  protected $status	= ""; // the game status in relation to the players. Set to STATUS_XXX
  // "open" allows players to join the game, "progressing" means only players can see the game, "closed" means the game will no longer advance
  protected $turnSection	= 0; // the staus of the game inside the latest turn. Set to TURN_SECTION_XXX

// optional rule switches
  protected $allowConjectural	= false; // flag for wether to allow the building of conjectural and unbuilt units
  protected $allowPublicUnits	= true; // flag for wether to allow all players to see the units of other players
  protected $allowPublicScenarios	= true; // flag for wether to allow all players to see the encounters of other players
  protected $borderSize	= 0; // the number of encounters to run per border
  protected $buildSpeed	= 0; // the number of "sub" turns per turn. Income stockpiling and builds do not occur on these turns
  protected $campaignSpeed	= 0; // the number of turns per "year"
  protected $largestSizeClass	= 0; // the largest size-class allowed. Note that the smaller this number, the larger the ships allowed
  protected $moduleBidsIn	= ""; // function name of module to send the incoming encounter-bids feed through
  protected $moduleBidsOut	= ""; // function name of module to send the outgoing encounter-bids feed through
  protected $moduleEncountersIn	= ""; // function name of module to send the outgoing encounter feed through
  protected $moduleEncountersOut	= ""; // function name of module to send the incoming encounter feed through
  protected $overwhelmingForce	= 0; // how much % of the lesser BPV does the larger BPV have to be before it is called "overwhelming force"
  protected $useExperience	= false; // flag for wether to use the Experience system (U7.9) for crew quality
  protected $useUnitSwapping	= false; // flag for wether to allow swapping one unit for another (class for class) in an encounter

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
      'currentTurn', 'campaignSpeed', 'borderSize', 'gameStart', 'moderator', 'overwhelmingForce'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Adds an interested player to the game
  ###
  # Args are:
  # - (int) The player ID to add
  # Returns:
  # - None
  ###
  function addInterest( $playerID )
  {
    $interest = explode( ",", $this->interestedPlayers );
    $interest[] = $playerID;
    $interest = array_unique( $interest );
    $key = array_search( "", $interest ); // remove blank entries
    if( $key !== false )
      unset( $interest[$key] );
    $interest = array_values( $interest );
    $this->interestedPlayers = implode( ",", $interest );
    $this->taint = self::IS_DIRTY;
  }

  ###
  # Determines the game's current year
  ###
  # Args are:
  # - none
  # Returns:
  # - (int) The year of the game, based on the currentTurn number
  ###
  function gameYear ()
  {
    return floor( $this->currentTurn / $this->campaignSpeed ) + $this->gameStart;
  }

  ###
  # Checks for a player's interest in the game
  ###
  # Args are:
  # - (int) The player ID to add
  # Returns:
  # - (bool) true if interested, false otherwise
  ###
  function getInterest( $playerID )
  {
    $interest = explode( ",", $this->interestedPlayers );
    $output = in_array( $playerID, $interest );
    return $output;
  }

  ###
  # Removes a player's interest from the game
  ###
  # Args are:
  # - (int) The player ID to remove
  # Returns:
  # - None
  ###
  function removeInterest( $playerID )
  {
    $interest = explode( ",", $this->interestedPlayers );
    $key = array_search( $playerID, $interest );
    if( $key === false )
      return;
    unset( $interest[$key] );
    $interest = array_values( $interest );
    $this->interestedPlayers = implode( ",", $interest );
    $this->taint = self::IS_DIRTY;
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
      'allowConjectural'	=> $this->allowConjectural,
      'allowPublicUnits'	=> $this->allowPublicUnits,
      'currentTurn'	=> $this->currentTurn,
      'borderSize'	=> $this->borderSize,
      'campaignSpeed'	=> $this->campaignSpeed,
      'gameName'	=> $this->gameName,
      'gameStart'	=> $this->gameStart,
      'interestedPlayers'	=> $this->interestedPlayers,
      'largestSizeClass'	=> $this->largestSizeClass,
      'moderator'	=> $this->moderator,
      'moduleEncountersIn'	=> $this->moduleEncountersIn,
      'moduleEncountersOut'	=> $this->moduleEncountersOut,
      'moduleBidsIn'	=> $this->moduleBidsIn,
      'moduleBidsOut'	=> $this->moduleBidsOut,
      'overwhelmingForce'	=> $this->overwhelmingForce,
      'randomSeeds'	=> $this->randomSeeds,
      'status'		=> $this->status,
      'turnSection'		=> $this->turnSection,
      'useExperience'	=> $this->useExperience,
      'useUnitSwapping'	=> $this->useUnitSwapping
    );
    return $output;
  }

}

?>
