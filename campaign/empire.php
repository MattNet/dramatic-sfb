<?php

$GOTO_ON_FAIL = "/index.php";	// page to serve if we've had very serious errors
$GOTO_ON_BACK = "/campaign/game.php";	// page to serve if we click on a game link
$TEMPLATE_FILE = "./empire.template";	// Template file to load

include_once( dirname(__FILE__) . "/../Login/Login_common.php" );
include_once( dirname(__FILE__) . "/../campaign_config.php" );
include_once( dirname(__FILE__) . "/../objects/obj_list.php");
include_once( dirname(__FILE__) . "/../objects/shipdesign.php");
include_once( dirname(__FILE__) . "/../objects/empire.php");
include_once( dirname(__FILE__) . "/../objects/ship.php");

// This code block allows the $_REQUEST variable to catch up to the rest of the script
// This fixes a bug where the $_REQUEST variable is not set until it was subject to print_r()
// Don't ask me how. Apparently, the $_REQUEST variable has to be accessed before it is assigned
if( ! isset($_REQUEST) || ! isset($_REQUEST['game']) )
  redirect( $GOTO_ON_FAIL );

$actionEmpireTagName = "actionEmpire";	// name/value-pair name to give the change-player's-empire select box
$canAdvance = false; // can this player advance the turn for this game?
$empireDeleteString = "delete"; // value of empire status that indicates this empire will be deleted
$empireList = array();	// list of empires available to play to this player
$empireObj = "";
$empIncome = 0;
$empStoredEP = 0;
$errors = "";	// the error string that will be output
$gameID = intval($_REQUEST['game']);	// the Game Identifier as given by the input
$gameObj = "";	// the object reference for this game in the database
$gameTurn = 0;	// the latest turn of the game
$inputPlayer = 0;	// The player given in the script arguments
$inputPlayerObj = "";	// the object related to $inputPlayer
$inputEmpire = 0;	// The empire given in the script arguments
$inputEmpireObj = "";	// the object related to $inputEmpire
$result = false;	// used to track success of database reads
$stdURLSuffix =	"?game=$gameID&race=0&".$authObj->getSessionRequest(); // Set of GET arguments, very commonly sent with outgoing URLs

// set up the game-object. Attempt to read in it's data
$gameObj = loadOneObject( 'game', $gameID, $GOTO_ON_BACK.$stdURLSuffix );
$modPlayerObj = loadOneObject( 'user', $gameObj->modify('moderator'), $GOTO_ON_BACK.$stdURLSuffix."&".$ERROR_GET_STRING.urlencode($errors) );
$gameTurn = $gameObj->modify('currentTurn');

// set the $inputPlayer stuff
if( ! empty( $_REQUEST['player'] ) )
{
  $inputPlayer = intval( $_REQUEST['player'] );
  // Check that $inputPlayer exists in 'interestedPlayers' list of $gameObj
  // but don't check if 'newEmpire' is set. We are trying to re-assign this player
  if( ! $gameObj->getInterest( $inputPlayer ) &&
      ! empty($_REQUEST[$actionEmpireTagName])
    )
    redirect( $GOTO_ON_BACK.$stdURLSuffix );
  $inputPlayerObj = loadOneObject( 'user', $inputPlayer, $GOTO_ON_BACK.$stdURLSuffix );
  if( ! $inputPlayerObj )
    redirect( $GOTO_ON_BACK.$stdURLSuffix );
}

// set the $inputEmpire stuff
if( isset( $_REQUEST['empire'] ) && ! empty( $_REQUEST['empire'] ) )
{
  $inputEmpire = intval( $_REQUEST['empire'] );
  $inputEmpireObj = loadOneObjectTurn( 'empire', $inputEmpire, $GOTO_ON_BACK.$stdURLSuffix, $gameTurn );

  // Check that $inputEmpireObj belongs to $gameObj
  if( ! $inputEmpireObj || $inputEmpireObj->modify('game') != $gameID )
    redirect( $GOTO_ON_BACK.$stdURLSuffix );
  // load up the player of this empire if possible
  if( $inputEmpireObj->modify('player') )
    $empirePlayerObj = loadOneObject( 'user', $inputEmpireObj->modify('player'), $GOTO_ON_BACK.$stdURLSuffix );

  // Empire-related tags
  $empIncome = $inputEmpireObj->modify('income');	// amount of EP income for this empire
  $empStoredEP = $inputEmpireObj->modify('storedEP');	// amount of stored EPs for this empire
  $empBorders = borderListHTML( $inputEmpireObj->bordersDecode(), $inputEmpire );
}

// Check that the current player is the game's moderator and/or able to advance this game
if( $modPlayerObj->modify('id') == $userObj->modify('id') && $authObj->checkPrivs( $userObj, "advance" ) )
  $canAdvance = true;
if( $authObj->checkPrivs( $userObj, "advanceAll" ) )
  $canAdvance = true;

if( ! $canAdvance )
  redirect( $GOTO_ON_BACK.$stdURLSuffix );

// Remove an interested player
if( ! empty($_REQUEST['kick']) && $inputPlayerObj )
{
  $gameObj->removeInterest( $inputPlayerObj->modify('id') );
  $gameObj->update();
  redirect( $GOTO_ON_BACK.$stdURLSuffix );
}

// Remove player from empire
if( ! empty($_REQUEST['kick']) && $inputEmpireObj && $empirePlayerObj )
{
  $playerID = $empirePlayerObj->modify('id');

  $gameObj->addInterest( $playerID );
  $inputEmpireObj->modify('player', 0 );

/*
  // change the turn-summary (html) file to playerID=0
  // needed only if the filename is based on playerIDs, not empireID
  if( file_exists("$BID_IN_DIRECTORY/".$playerID."input".$gameID."to".$gameTurn.".html") )
    rename(
      "$BID_IN_DIRECTORY/".$playerID."input".$gameID."to".$gameTurn.".html",
      "$BID_IN_DIRECTORY/0input".$gameID."to".$gameTurn.".html"
    );
*/

  $gameObj->update();
  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Remove the empire from the game
if( ! empty($_REQUEST['delete']) && $inputEmpireObj && 
    ! isset($empirePlayerObj) && $inputEmpireObj->modify('status') != $empireDeleteString
  )
{
  $inputEmpireObj->modify('status', $empireDeleteString);
  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Restore the empire to the game
if( ! empty($_REQUEST['delete']) && $inputEmpireObj && 
    $inputEmpireObj->modify('status') == $empireDeleteString
  )
{
  $inputEmpireObj->modify('status', "");
  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Set player to the empty empire
if( ! empty($_REQUEST['setPlayer']) && $inputEmpireObj && $inputEmpireObj->modify('player') == 0 )
{
  do // a DO loop to provide a break to IF statement
  {
    $playerID = intval($_REQUEST['setPlayer']);
    if( $playerID <= 0 )
      break;

    $gameObj->removeInterest( $playerID );
    $inputEmpireObj->modify('player', $playerID );
/*
    // change the turn-summary (html) file to this playerID, if exists
// needed only if the filename is based on playerIDs, not empireID
    if( is_file("$BID_IN_DIRECTORY/0input".$gameID."to".$gameTurn.".txt") )
      rename(
        "$BID_IN_DIRECTORY/0input".$gameID."to".$gameTurn.".txt",
        "$BID_IN_DIRECTORY/".$playerID."input".$gameID."to".$gameTurn.".txt"
      );
*/

    $gameObj->update();
    $inputEmpireObj->update();
    redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
  }
  while(false);
}

// Change the empire at this game position
if( ! empty($_REQUEST[$actionEmpireTagName]) && ! empty($inputEmpireObj) )
{
  $inputEmpireObj->modify('race', $_REQUEST[$actionEmpireTagName] );

  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Create a game position
if( ! empty($_REQUEST[$actionEmpireTagName]) && empty($inputEmpireObj) )
{
  // create the new Empire object
  $options = array(
    'advance' => 0,
    'ai' => "",
    'borders' => "",
    'game' => $gameID,
    'income' => 0,
    'player' => $inputPlayer,
    'race' => $_REQUEST[$actionEmpireTagName],
    'storedEP' => 0,
    'turn' => $gameTurn
  );

  $obj = new Empire( $options );
//print_r($options);print_r($obj->error_string);exit();
  $result = $obj->create();

  // remove the player form the interested list
  $gameObj->removeInterest( $inputPlayer );
  $gameObj->update();

  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=".$obj->modify('id') );
}

// Change the empire's income
if( ! empty($_REQUEST['changeIncome']) && ! empty($inputEmpireObj) )
{
  $amt = intval($_REQUEST['changeIncome']);
  $inputEmpireObj->modify('income', $amt );

  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Change the empire's stockpiled EPs
if( ! empty($_REQUEST['changeStockpile']) && ! empty($inputEmpireObj) )
{
  $amt = intval($_REQUEST['changeStockpile']);
  $inputEmpireObj->modify('storedEP', $amt );

  $inputEmpireObj->update();
  redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
}

// Change the empire's borders
if( ! empty($inputEmpireObj) )
{
  for( $i=0; $i<100; $i++ ) // look for $_REQUEST['borderEmpire0'] -> $_REQUEST['borderEmpire99']
    if( ! empty($_REQUEST['borderEmpire'.$i]) )
    {
      $empID = intval( $_REQUEST['borderEmpire'.$i] );
      $amt = intval( $_REQUEST['borderSize'.$i] );

      // skip if not a valid empire ID or an invalid amount
      if( $empID <= 0 || $amt < 0 )
        continue;

      $otherEmpireObj = loadOneObjectTurn( 'empire', $empID, $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire", $gameTurn );

      $inputEmpireObj->bordersChange( $empID, $amt );
      $otherEmpireObj->bordersChange( $inputEmpire, $amt );

      $inputEmpireObj->update();
      $otherEmpireObj->update();

      unset($otherEmpireObj);
      redirect( $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire" );
    }
}

// Change the empire's ships
for( $i=0; $i<300; $i++ ) // look for $_REQUEST['design0'] -> $_REQUEST['design299']
{
  if( ! empty($_REQUEST['design'.$i]) &&
      ! empty($inputEmpireObj) &&
      preg_match( "/^(\d+)\-(\d+)/", $_REQUEST['design'.$i], $matches )
    )
  {
    $url = $_SERVER['PHP_SELF']."$stdURLSuffix&empire=$inputEmpire";
    $designID = intval($matches[1]);
    $shipID = intval($matches[2]);

    // if the ship ID is 0, then we are adding a new unit to this empire
    if( $shipID == 0 && $designID > 0 )
    {
      // create the new Ship object
      $options = array(
        'captureEmpire'	=> 0,
        'configuration'	=> '',
        'damage'	=> 0,
        'design'	=> $designID,
        'empire'	=> $inputEmpire,
        'game'	=> $gameID,
        'isDead'	=> 0,
        'locationIsLane'	=> 0,
        'manifest'	=> 0,
        'mapObject'	=> 0,
        'mapSector'	=> 0,
        'status'	=> Ship::ACTIVE,
        'supplyLevel'	=> 0,
        'turn'	=> $gameTurn
      );
      $obj = new Ship( $options );
      $result = $obj->create();
      redirect( $url );
    }

    // getting this far means the ship exists already
    $shipObj = loadOneObjectTurn( 'ship', $shipID, '', $gameTurn );

    // if the ship designator hasn't changed
    if( $shipObj->modify('design') == $designID )
      continue;

    // if the ship designator is 0, then kill the ship
    if( $designID == 0 )
      $shipObj->modify( 'isDead', true );
    // otherwise change the ship's design
    else
      $shipObj->modify( 'design', $designID );

    $shipObj->update();

    redirect( $url );
  }
}

// Set up the variables used inside the template

$actionPlayerTag = "";	// tag which does some player action, such as kicking an interested player
$actionEmpireTag = "";	// tag that selects which empire to assign to the player
$backUpTag = "<a href='$GOTO_ON_BACK$stdURLSuffix'>Game Menu</a>";
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
$formTag .= $authObj->getSessionTag();
$formTag .= "<input type='hidden' name='game' value='$gameID'>\n";
$formTag .= "<input type='hidden' name='race' value='0'>\n";
$jsRedirectURL = $_SERVER['PHP_SELF'].$stdURLSuffix;
	// The URL that the on-page JS sends the user to when they change an option
$logOutTag = "<a href='$GOTO_ON_LOGOUT?".$authObj->getSessionRequest()."'>LOG OUT</a>";
$logTag = "";
$ordersTag = "";

$addShipsTag = "";
$empireName = "";
$empOrders = "";  // holds the set of orders given last
$gameName = $gameObj->modify('gameName');
$gameSpeed = $gameObj->modify('campaignSpeed');
$gameStatus = $gameObj->modify('status');
$gameStart = $gameObj->modify('gameStart');
$gameTurn = $gameObj->modify('currentTurn');
$gameYear = $gameObj->gameYear();
$interestHTML = "";
$largestSizeClass = "Size Class ".$gameObj->modify('largestSizeClass');
$playerEmail = "";
$playerName = "";
$playerUserName = "";
$resetIncome = $empIncome;
$resetStockpile = $empStoredEP;

if( $inputPlayerObj )
{
  $actionPlayerTag = "<a href='".$_SERVER['PHP_SELF']."$stdURLSuffix&player=".$inputPlayerObj->modify('id')."&kick=".$inputPlayerObj->modify('id')."' class=''>Ignore Interested Player</a>";
  $playerEmail = $inputPlayerObj->modify('email');
  $playerName = $inputPlayerObj->modify('username')." (".$inputPlayerObj->modify('fullName').")";
  $playerUserName = $inputPlayerObj->modify('username');
  $jsRedirectURL .= "&player=".$inputPlayerObj->modify('id');
  // generate a list of empires from the ShipDesign data
  $empireList = getEmpireList( $inputPlayerObj );
  array_unshift( $empireList, "" );
}
if( $inputEmpireObj )
{
  $empireName = $inputEmpireObj->modify('race');
  // these items are for empires both with and without players
  $actionPlayerTag = "";
  $empireList = array( $empireName );
  $empireStatus = $inputEmpireObj->modify('status');
  $playerName = "No Player Assigned";
  $playerUserName = "No Player";
  $jsRedirectURL .= "&empire=".$inputEmpireObj->modify('id');
  // $interestHTML should be non-empty only when an empire has no player
  $interestHTML = getInterestListHTML( $gameObj, "setPlayer", "jsRedirect(this,\"the player of this empire\")" );
  $addShipsTag = buildRow( $inputEmpireObj );

  // these items are for empires with players
  if( isset($empirePlayerObj) )
  {
    // generate the "kick player from this empire" button
    $actionPlayerTag = "<a href='".$_SERVER['PHP_SELF']."$stdURLSuffix&empire=";
    $actionPlayerTag .= $inputEmpireObj->modify('id')."&kick=".$inputEmpireObj->modify('player');
    $actionPlayerTag .= "' class=''>Remove Player From Empire</a>";
    // generate a list of empires from the ShipDesign data
    $empireList = getEmpireList( $empirePlayerObj );
    // generate the player information
    $playerEmail = $empirePlayerObj->modify('email');
    $playerName = $empirePlayerObj->modify('username')." (".$empirePlayerObj->modify('fullName').")";
    $playerUserName = $empirePlayerObj->modify('username');
    // unset the "interested players to assign to this empty empire" drop-down
    $interestHTML = "";
    // set up the "change income" and "change stockpile" elements
    $resetIncome = "<input type='text' size=4 name='changeIncome' value='$empIncome'";
    $resetIncome .= " onchange='jsRedirect(this, \"the income\")'>";
    $resetStockpile = "<input type='text' size=4 name='changeStockpile' value='$empStoredEP'";
    $resetStockpile .= " onchange='jsRedirect(this, \"the stockpiled income\")'>";
  }
  else
  {
    if( $empireStatus != $empireDeleteString )
    {
      // Generate the "Kick empire from game" button
      $actionPlayerTag = "<a href='".$_SERVER['PHP_SELF']."$stdURLSuffix&empire=";
      $actionPlayerTag .= $inputEmpireObj->modify('id')."&delete=".$inputEmpireObj->modify('id');
      $actionPlayerTag .= "' class=''>Remove Empire from the Game</a>";
    }
    else
    {
      // Generate the "Un-kick empire from game" button
      $actionPlayerTag = "<a href='".$_SERVER['PHP_SELF']."$stdURLSuffix&empire=";
      $actionPlayerTag .= $inputEmpireObj->modify('id')."&delete=".$inputEmpireObj->modify('id');
      $actionPlayerTag .= "' class=''>Restore Empire to the Game</a>";
    }
  }
}

$actionEmpireTag = "<select name='$actionEmpireTagName' autocomplete='off' ";
$actionEmpireTag .= "onchange='jsRedirect(this,\"the empire for this game position\")'>\n";
foreach( $empireList as $empName )
{
  $actionEmpireTag .= "<option value='$empName'";
  if( ! empty($inputEmpireObj) && $empireName == $empName )
    $actionEmpireTag .= " SELECTED";
  $actionEmpireTag .= ">$empName</option>\n";
}
$actionEmpireTag .= "</select>";

$errorTag = $errors;


// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

###
# Creates an HTML list of empires that the given empire shares a border with
###
# Args are:
# - (array) List of other_empire_ID => num_of_borders_shared
# - (integer) ID of the empire being examined
# Returns:
# - (string) An HTML List of empire names with borders shared
###
function borderListHTML( $decodedBorders, $ourID )
{
  global $gameID, $gameTurn;
  $empireDBList = new objList( "empire", $gameID, $gameTurn, false );
  $output = "";
  $iteration = 0;

  foreach( $decodedBorders as $empID=>$numBorders )
  {
    $output .= borderListSubHTML( $empireDBList, $iteration, $ourID, $empID, $numBorders );
    $iteration++;
  }
  $output .= borderListSubHTML( $empireDBList, $iteration, $ourID, '', 0 );

  unset( $empireDBList );

  return $output;
}

###
# Creates the HTML used in borderListHTML()
###
# Args are:
# - (object) An ObjList object of empires associated with this game
# - (integer) A number for the iteration that these inputs are for
# - (integer) The Empire ID of the empire this is for
# - (integer) The Empire ID of the default empire
# - (integer) The size of the default border
# Returns:
# - (string) An HTML dropdown of empires and an input for border-count
###
function borderListSubHTML( $DBList, $iteration, $selfID, $defaultID, $defaultSize )
{
  $BORDER_EMPIRE_NAME = "borderEmpire".$iteration;
  $BORDER_COUNT_NAME = "borderSize".$iteration;
  $output = "<select name='$BORDER_EMPIRE_NAME' autocomplete='off' ";
  $output .= "onchange='jsMultiRedirect(this,nextElementSibling,\"this bordering empire\")'>\n";
  $output .= "<option value=''></option>\n";
  foreach( $DBList->objByID as $ID=>$tempEmpObj )
  {
    if( $ID == $selfID )
      continue;
    $output .= "<option value='$ID'";
    if( $ID == $defaultID )
      $output .= " SELECTED";
    $output .= ">".$tempEmpObj->modify('textName')." (".$tempEmpObj->modify('race').")</option>\n";
  }
  $output .= "</select>\n - <input type='text' name='$BORDER_COUNT_NAME'";
  $output .= " value='$defaultSize' size=1 autocomplete='off' ";
  $output .= "onchange='jsMultiRedirect(this,previousElementSibling,\"the border size\")'> deep<br>\n";

  return $output;
}

###
# Creates a list of empires available to play
###
# Args are:
# - (object) The player object to check empire privs against
# Returns:
# - (array) List of empire names
###
function getEmpireList( $playerObj )
{
  global $SHOW_DB_ERRORS, $BASICRACE_EMPIRES, $authObj;
  $output = array();

  if( ! $authObj->checkPrivs( $playerObj, "anyRace" ) )
  {
    // if the player doesn't have 'basicRace' or 'anyRace' privs, then give him an empty output
    if( ! $authObj->checkPrivs( $playerObj, "basicRace" ) )
      return $output;
    // if the player merely is missing 'anyRace' privs, give him a list of basic races from the configuration
    $output = $BASICRACE_EMPIRES;
    return $output;
  }

  $list = "";
  $errors = "";
  $database = gameDB::giveme();	// get the database object
  $query = "SELECT DISTINCT empire FROM ".ShipDesign::table." ORDER BY empire";
  $result = $database->genquery( $query, $list );
  if( $result === false )
  {
    $errors .= "Error loading list.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
  }
  else
  {
    foreach( $list as $row )
      $output[] = $row['empire'];
  }
  return $output;
}

###
# Creates an HTML select list of Interested players
###
# Args are:
# - (object) The game object to generate the list against
# - (string) The name to give the HTML element
# - (string) The javascript to process in an OnChange event
# Returns:
# - (string) An HTML drop-down list of interested players
###
function getInterestListHTML( $gameObj, $name, $onChange )
{
  $interestedPlayers = explode( ",", $gameObj->modify('interestedPlayers') );
  $output = "<select name='$name' autocomplete='off' onchange='$onChange'>\n<option value='' selected></option>\n";
  foreach( $interestedPlayers as $playerID )
  {
    $playerID = intval( $playerID );
    if( $playerID <= 0 )
      continue;
    $playerObj = loadOneObject( 'user', $playerID );
    if( ! $playerObj )
      continue;
    $output .= "<option value='$playerID'>".$playerObj->modify('username')."</option>\n";
    unset($playerObj);
  }
  $output .= "</select>";

  return $output;
}

  ###
  # Assembles a single HTML drop-down for adding new ships to an empire
  ###
  # Args are:
  # - (object) The empire object to get the ships from
  # - (int) An index of which row is being created
  # Returns:
  # - (string) An HTML dropdown menu of ships
  ###
function buildRow( $empireObj )
{
  global $gameID, $gameYear, $gameTurn;

  $designDBList = getDesignList( $empireObj->modify('race'), $gameYear, true );
  $shipDBList = new objList( "ship", $gameID, $gameTurn, false );
  $output = "";
  $rowIndex = 0;

  // display the current ships, for removal or designation change
  foreach( $shipDBList->objByID as $shipID=>$obj )
  {
    if( $empireObj->modify('id') != $obj->modify('empire') )
      continue;
    $output .= "#$shipID: ";
    if( $obj->modify('isDead') )
      $output .= "Dead ";
    if( $obj->modify('damage') >= 50 )
      $output .= "Crippled ";
    $output .= "<select name='design$rowIndex' autocomplete='off' ";
    $output .= "onchange='jsRedirect(this,\"the design for this ship\")'>\n<option value='0-$shipID'></option>\n";
    foreach( $designDBList as $designID=>$designator )
    {
      $output .= "<option value='$designID-$shipID'";
      if( $obj->modify('design') == $designID )
        $output .= " selected";
      $output .= ">$designator</option>\n";
    }
    $output .= "</select>\n<br>";
    $rowIndex++;
  }

  // display the ship designs, for adding new ships to the empire
  $output .= "Add: <select name='design$rowIndex' autocomplete='off' onchange='jsRedirect(this,\"the ships available to this empire\")'>\n<option value='0-0' selected></option>\n";
  foreach( $designDBList as $id=>$designator )
  {
    $output .= "<option value='$id-0'>$designator</option>\n";
  }
  $output .= "</select>\n<br>";

  return $output;
}


  ###
  # Creates a list of available ships designs for the given empire and year
  ###
  # Args are:
  # - (string) The empire to compare against
  # - (int) The highest year-in-service to provide
  # - (boolean) If true, then provides all designs up to the YIS date.
  #             If false, then weeds out the designs marked as obsolete. default false
  # Returns:
  # - (array) List of identifier => "ship_designator - ship_bpv EP"
  ###
function getDesignList( $empire, $year, $all=false )
{
  global $gameObj;
  $output = array();
  $list = "";
  $errors = "";
  $database = gameDB::giveme();	// get the database object
  $query = "SELECT id,designator,BPV FROM ".ShipDesign::table." WHERE empire='$empire' AND yearInService<=$year";
  $query .= " AND sizeClass>=".$gameObj->modify('largestSizeClass'); // limit by size-class
  if( ! $all )
    $query .= " AND ( obsolete>=$year OR obsolete=0 )"; // limit by obsolete
  // exclude conjectural units if the game doesn't use them
  if( $gameObj->modify('allowConjectural') == false )
    $query .= " AND switches NOT LIKE '%conjectural%'";
  // set up the displayed order of the units
  $query .= " ORDER BY BPV DESC";

  $result = $database->genquery( $query, $list );
  if( $result === false )
  {
    $errors .= "Error loading list.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
  }
  else
  {
    foreach( $list as $row )
      $output[ $row['id'] ] = $row['designator']." - ".$row['BPV']." EP";
  }

  return $output;
}

?>
