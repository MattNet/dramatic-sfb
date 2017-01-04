<?php

$GOTO_ON_FAIL = "/index.php";	// page to serve if we've had very serious errors
$GOTO_ON_ACCOUNT = "/campaign/menu.php";	// page to serve if we click on a game link
$GOTO_ON_BACK = "/campaign/game.php";	// page to serve if we click on a game link
$TEMPLATE_FILE = "./orders.template";	// Template file to load

include_once( dirname(__FILE__) . "/../Login/Login_common.php" );
include_once( dirname(__FILE__) . "/../campaign_config.php" );
include_once( dirname(__FILE__) . "/../objects/game.php");
include_once( dirname(__FILE__) . "/../objects/orders.php");

// This code block allows the $_REQUEST variable to catch up to the rest of the script
// This fixes a bug where the $_REQUEST variable is not set until it was subject to print_r()
// Don't ask me how. Apparently, the $_REQUEST variable has to be accessed before it is assigned
if( ! isset($_REQUEST) || ! isset($_REQUEST['game']) || ! isset($_REQUEST['race']) )
  redirect( $GOTO_ON_FAIL );

$gameID = intval($_REQUEST['game']);	// the Game Identifier as given by the input
$errors = "";
$raceID = intval($_REQUEST['race']);	// the Race Identifier as given by the input
$stdURLSuffix =	"?game=$gameID&race=$raceID&".$authObj->getSessionRequest(); // Set of GET arguments, very commonly sent with outgoing URLs

// bounce if attempted to give orders while a moderator or a non-participant
if( $raceID <= 0 )
{
  $errors .= "Attempted to give orders as a non-participant.";
  redirect( "$GOTO_ON_ACCOUNT?".$authObj->getSessionRequest()."&".$ERROR_GET_STRING.urlencode($errors) );
}

  // find the data file
  $dataFileName = sprintf( $DATA_OUT_FILE_REGEX, $gameID, $raceID );
  $dirListing = scandir( $MODULE_FILE_STORE );
  $fileCount = 0;
  $tempDataFileName = "";
  foreach( $dirListing as $fileItem )
  {
    $result = preg_match( "/^$dataFileName/", $fileItem, $matches );
    if( ! $result || $matches[1] < $fileCount )
      continue;
    $fileCount = $matches[1];
    $tempDataFileName = $matches[0];
  }
  $dataFileName = "$MODULE_FILE_STORE/".$tempDataFileName;

  // if $dataFileName exists, put the data contents into the output
  // otherwise back out
  if( is_file($dataFileName) )
  {
    $dataFileContents = file_get_contents( $dataFileName );

    // A JSON to PHP converter here
    $mangledDataFile = str_replace( array("\n","\r"), "", $dataFileContents ); // remove newlines
    $mangledDataFile = str_replace( "'", '"', $mangledDataFile ); // replace single-quotes with double-quotes
    preg_match_all( "/((\{|\[\[).*?(\}|\];))/", $mangledDataFile, $matches );

    $gameObj = json_decode($matches[0][0], true);
    $designList = json_decode($matches[0][3], true);
    $encounterList = json_decode($matches[0][4], true);
    $empireList = json_decode($matches[0][5], true);
    $empireObj = json_decode($matches[0][1], true);
    $unitList = json_decode($matches[0][2], true);
  }
  else
  {
    $errors .= "Could not get order file for empire #$raceID.";
    redirect( "$GOTO_ON_BACK".$stdURLSuffix."&".$ERROR_GET_STRING.urlencode($errors) );
  }

// bounce if the empire doesn't belong in this game
if( ! sanitizeRace( $empireObj, $gameID ) )
{
  $errors .= "Empire '{$empireObj->modify('textName')}' does not belong in this game.";
  redirect( "$GOTO_ON_ACCOUNT?".$authObj->getSessionRequest()."&".$ERROR_GET_STRING.urlencode($errors) );
}

// tags used in the template
$accountTag = "<a href='$GOTO_ON_ACCOUNT?".$authObj->getSessionRequest()."'>Account Menu</a>";
$backUpTag = "<a href='$GOTO_ON_BACK$stdURLSuffix'>Game Menu</a>";
$errorTag = "";
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
$formTag .= $authObj->getSessionTag();
$formTag .= "<input type='hidden' name='game' value='$gameID'>\n";
$formTag .= "<input type='hidden' name='race' value='$raceID'>\n";
$gameTurn = $gameObj['currentTurn'];
$gameYear = $gameObj['gameStart'] + floor( $gameTurn / $gameObj['campaignSpeed'] );
$javascriptLookup = $dataFileContents; // The data file contents go here
$jsRows = "orderArray = {};\n"; // previously-given orders as a JSON object
$logOutTag = "<a href='$GOTO_ON_LOGOUT'>LOG OUT</a>";
$ordersFileContents = "";
$permRows = ""; // previously-given orders as HTML rows and hidden inputs
$playerID = $empireObj['player'];
$processOutput = populateScenarioList( $gameTurn, $gameID, $raceID );
$saveTag = "<input type='submit' value='Save Orders'>\n";

### start order-table assembly

// Read in the orders from the submitted form, if available. Also writes them to a file
$status = readOrders( $raceID, $gameID, $gameTurn );

// update the user's session timestamp for idleness
if( $status )
{
  $status = $authObj->storeSession( $userObj );
  if( ! $status )
    $errors .= "Error with storing session.";
  else
    $userObj->update();
}

// find the order file
$orderFileName = sprintf( $BID_IN_FILE_REGEX, $gameTurn, $gameID );
$dirListing = scandir( $BID_IN_DIRECTORY );
foreach( $dirListing as $fileItem )
{
  $result = preg_match( "/^$orderFileName/", $fileItem, $matches );
  if( ! $result || $matches[1] != $raceID )
    continue;
  $orderFileName = "$BID_IN_DIRECTORY/".$matches[0];
  break;
}

// do nothing if $orderFileName didn't exist
// otherwise, pull orders from the order file
if( file_exists($orderFileName) )
{
  $ordersFileContents = file( $orderFileName, FILE_SKIP_EMPTY_LINES );
  foreach( $ordersFileContents as $index=>$order )
  {
/*
// this collides with lawful use of hash-marks within unit (ship) names
    // trim post-comment stuff from orders
    if( strpos( $order, "#" ) !== false )
      $order = substr( $order, 0, strpos( $order, "#" ) );
*/
    // trim whitespace from the orders
    $order = trim($order);
    if( $order == '' )
      continue;

    // create the order in memory
    // this allows us to decode the order
    // NOTE that the order object can handle many orders at once. We are only 
    //   treating one order at a time
    $order = new Orders( array(
                'game'=> $gameID, 'orders' => $order,
                'empire'=> $raceID, 'turn' => $gameTurn
             ) );
    $order->modify('autowrite', false); // don't write these to the database
    $decode = $order->decodeOrders();

    // build the table row
    $builtRow = buildRow( $index, $decode, array( $unitList, $designList, $encounterList ) );
    $permRows .= $builtRow[0];
    $jsRows .= $builtRow[1];

    unset( $order );
  }
}

### end order-table assembly

// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

  ###
  # Assembles a single HTML table row of HTML drop-downs of order type, unit, scenario, and empire
  # Tells the HTML which item to select, according to the second argument
  ###
  # Args are:
  # - (int) An index of which row is being created
  # - (array) [optional] An array of decoded orders, per orders::decodeOrders()
  # -  - If blank, then blank values will be selected
  # - (array) [optional] An array of a decoded JSON data file, from '/modules/common.tableOut.php'
  # Returns:
  # - (array) first element is a string containing text and hidden inputs.
  # -         second element is a string containing the JSON
  ###
function buildRow( $rowIndex, $orderArray = array(), $jsonData = array() )
{
  global $SCENARIOS, $raceID, $gameObj;

  $design = "";
  $makeDropDown = true; // true to emit JSON to auto-select inputs. False to emit un-changable text and hidden inputs
  $output = "";
  $json = "";
  $scenario = "";
  $ship = "";
  $text = "";
  $type = ""; // note that this must match some order-token (e.g. 'convert' to convert units. see '/docs/orders.txt')
  $unitList = array();
  $designList = array();
  $encounterList = array();
  if( ! empty($jsonData) )
  {
    $unitList = $jsonData[0];
    $designList = $jsonData[1];
    $encounterList = $jsonData[2];
  }

  // scan $orderArray for the order we are treating on this table row
  foreach( $orderArray as $orderString=>$orderData )
  {
    if( empty($orderData) )
      continue;

    // now set up a couple variables so that the below series of IFs are simple
    switch($orderString)
    {
    case "bids":
      $data = end( $orderData );
      $design = '';
      if( $gameObj['turnSection'] != Game::TURN_SECTION_EARLY )
        $makeDropDown = false;
      $scenario = $data['encounter'];
      $ship = $data['ship'];
      $text = '';
      $type = "bid";
      break;
    case "builds":
      $data = end( $orderData ); // There is only one array key in the build data
      if( $gameObj['turnSection'] != Game::TURN_SECTION_EARLY )
        $makeDropDown = false;
      $design = $data['ship'];
      $scenario = '';
      $ship = '';
      $text = $data['name'];
      $type = "build";
      break;
    case "conversions":
      $data = end( $orderData ); // There is only one array key in the conversion data
      $design = $data['design'];
      if( $gameObj['turnSection'] != Game::TURN_SECTION_EARLY )
        $makeDropDown = false;
      $scenario = '';
      $ship = $data['ship'];
      $text = '';
      $type = "convert";
      break;
    case "cripples":
      $design = '';
      $makeDropDown = true;
      $scenario = '';
      $ship = end( $orderData );
      $text = '';
      $type = "cripple";
      break;
    case "destroy":
      $design = '';
      $makeDropDown = true;
      $ship = end( $orderData );
      $scenario = '';
      $text = '';
      $type = "destroy";
      break;
    case "encounters":
      $data = end( array_keys( $orderData ) ); // There is only one array key in the encounter data
      $design = '';
      $makeDropDown = true;
      $scenario = $data;
      $ship = '';
      $text = '';
      if( $orderData[ $data ] == true )
        $type = "victory";
      else
        $type = "defeat";
      break;
    case "gifts":
      $data = end( $orderData ); // There is only one array key in the gift data
      $design = '';
      $makeDropDown = true;
      $scenario = $data['empire'];
      $ship = $data['ship'];
      $text = '';
      $type = "gift";
      break;
    case "names":
      $data = end( $orderData ); // There is only one array key in the gift data
      $design = '';
      $scenario = $data['empire'];
      $ship = $data['ship'];
      $text = $data['name'];
      $type = "name";
      break;
    case "repairs":
      $design = '';
      if( $gameObj['turnSection'] != Game::TURN_SECTION_EARLY )
        $makeDropDown = false;
      $scenario = '';
      $ship = end( $orderData );
      $text = '';
      $type = "repair";
      break;
    }
  }

  // generate JSON to set the drop-downs to 'selected'
  $json .= "orderArray[$rowIndex] = [ '$type', '$ship', '$scenario', '$design','$text' ];\n";

  if( ! $makeDropDown )
  {
    $output = "<tr><td colspan=5>\n<input type='hidden' name='order".$rowIndex."' value='$type'>\n";
    $output .= "<input type='hidden' name='ship".$rowIndex."' value='$ship'>\n";
    $output .= "<input type='hidden' name='scenario".$rowIndex."' value='$scenario'>\n";
    $output .= "<input type='hidden' name='design".$rowIndex."' value='$design'>\n";
    $output .= "<input type='hidden' name='text".$rowIndex."' value='$text'>\n";
    switch( $type )
    {
    case 'bid':
      $output .= "Bid ship ".$unitList[$ship]." to encounter ";
      $output .= "#$scenario: '".$encounterList[$scenario][0]."'";
      break;
    case 'build':
      $output .= "Build a ".$designList[$design][0]." named \"$text\"";
      break;
    case 'convert':
      $output .= "Convert / Refit ".$unitList[$ship]." into a ".$designList[$design][0];
      break;
    case 'repair':
      $output .= "Hold ".$unitList[$ship]." out for repairs";
      break;
    }
    $output .= "</td></tr>\n";
  }

  return array( $output, $json );
}

  ###
  # Reads the orders from the POST data and writes out the order file
  ###
  # Args are:
  # - (int) The player identifier
  # - (int) The game identifier
  # - (int) The game's turn
  # Returns:
  # - (bool) False if something was wrong or the orders are empty. True if it wrote to the order file
  ###
function readOrders( $empireID, $game, $turn )
{
  global $BID_IN_DIRECTORY, $MAX_ORDER_INPUTS;
  $output = "";

  // loop through all of the orders in the form-inputs
  for( $i=0; $i < $MAX_ORDER_INPUTS; $i++ )
  {
    // skip if the order is blank
    if( ! isset($_REQUEST["order$i"]) )
      continue;

    // select based on the text of the current order-form-input
    switch( $_REQUEST["order$i"] )
    {
    case "bid":
      if( ! empty($_REQUEST["ship$i"]) && ! empty($_REQUEST["scenario$i"]) )
        $output .= "bid".$_REQUEST["ship$i"]."to".$_REQUEST["scenario$i"];
      break;
    case "build":
      if( empty($_REQUEST["design$i"]) )
        break;
      if( ! empty($_REQUEST["text$i"]) )
        $output .= "build".$_REQUEST["design$i"]."named\"".$_REQUEST["text$i"]."\"";
      else
        $output .= "build".$_REQUEST["design$i"];
      break;
    case "convert":
      if( ! empty($_REQUEST["ship$i"]) && ! empty($_REQUEST["design$i"]) )
        $output .= "convert".$_REQUEST["ship$i"]."to".$_REQUEST["design$i"];
      break;
    case "cripple":
      if( ! empty($_REQUEST["ship$i"]) )
        $output .= "cripple".$_REQUEST["ship$i"];
      break;
    case "destroy":
      if( ! empty($_REQUEST["ship$i"]) )
        $output .= "destroy".$_REQUEST["ship$i"];
      break;
    case "victory":
      if( ! empty($_REQUEST["scenario$i"]) )
        $output .= "victory".$_REQUEST["scenario$i"];
      break;
    case "defeat":
      if( ! empty($_REQUEST["scenario$i"]) )
        $output .= "defeat".$_REQUEST["scenario$i"];
      break;
    case "gift":
      if( ! empty($_REQUEST["ship$i"]) && ! empty($_REQUEST["scenario$i"]) )
        $output .= "gift".$_REQUEST["ship$i"]."empire".$_REQUEST["scenario$i"];
      break;
    case "repair":
      if( ! empty($_REQUEST["ship$i"]) )
        $output .= "repair".$_REQUEST["ship$i"];
      break;
    case "name":
      if( empty($_REQUEST["text$i"]) )
        break;
      if( ! empty($_REQUEST["ship$i"]) )
        $output .= "name".$_REQUEST["ship$i"]."ship\"".$_REQUEST["text$i"]."\"";
      else
        $output .= "name".$empireID."empire\"".$_REQUEST["text$i"]."\"";
      break;
    }
    $output .= "\n";
  }

  // leave if the orders are blank
  if( $output == "" )
    return false;

  // write the order file. Replaces the previous one
  $fileName = $BID_IN_DIRECTORY.$empireID."input".$game."to".$turn.".txt";
  return file_put_contents( $fileName, $output, LOCK_EX );
}

###
# Creates a display of players and their units/scenarios:
# - A table with each player having two columns.
# - - Column 1 is a unit list
# - - Column 2 is a scenario list of scenario images. link of scenario image goes to scenario description
###
# Args are:
# - (int) The turn number to look for
# - (int) The player identifier to look for
# Returns:
# - (string) the HTML tag that lists the empires with their units and scenarios
###
function populateScenarioList( $turn, $game, $empireID )
{
  global $MODULE_FILE_STORE, $BID_OUT_FILE_FORMAT;
  $output = "";

  $filename = array(
      "$MODULE_FILE_STORE/".sprintf( $BID_OUT_FILE_FORMAT, $turn, $game, $empireID ),
// possibly look for the previous-turn's file if the first one fails
//      "$MODULE_FILE_STORE/".sprintf( $BID_OUT_FILE_FORMAT, ($turn-1), $game, $empireID ),
    );
  foreach( $filename as $file )
    if( is_readable($file) )
    {
      $output = file_get_contents( $file );
      break;
    }

  return $output;
}

?>
