<?php

require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../scenarios/scenarios.php" );
require_once( dirname(__FILE__) . "/../campaign_config.php" );

$COMMON_JAVASCRIPT = <<<EOJ
var popitup=function(arrayIndex)
{
  var newwindow=window.open('','Scenario Description','resizable=yes,scrollbars=yes');
  if(window.focus)
    newwindow.focus();
  var tmp=newwindow.document;
  tmp.write(scenarioJS[arrayIndex]);
  tmp.close();
  return false;
}
EOJ;

###
# Loads a singular object
###
# Args are:
# - (string) The object name to load
# - (string) The ID of the object to load
# - (integer) [optional] The turn number of the object to load
# Returns:
# - (object) The requested object. redirects to the URL if it fails. If the URL was not given, returns false
###
function loadOneObject( $objName, $ID, $turn=-1 )
{
  global $errors;

  if( $ID <= 0 )
  {
    $errors .= basename(__FILE__).": Invalid object ID: '$ID'.\n";
    return false;
  }

  $objectFile = dirname(__FILE__) . "/../objects/" . strtolower($objName) . ".php";
  if( ! file_exists($objectFile) )
  {
    $errors .= basename(__FILE__).": Unable to load file '$objectFile'.\n";
    error_log( $errors, 0 );
    return false;
  }
  include_once( $objectFile );

  $obj = new $objName( array('id'=>$ID) );
  if( $turn >= 0 )
    $obj->modify( 'turn', $turn );
  $result = $obj->read();
  // if the object doesn't exist
  if( ! $result )
  {
    $errors .= basename(__FILE__).": Object '$objName' #$ID was not found, though loaded '$objectFile' successfully.\n";
    error_log( $errors, 0 );
    unset( $obj );
    return false;
  }
  $obj->modify('autowrite', false);

  return $obj;
}

###
# Emits a JSON data file so a client does not need the database to display game and orders information for the given game
###
# Args are:
# - (object) The current game object
# - (object) The object of the player's empire
# - (object) A list-object of the in-play units
# - (object) A list-object of the encounters ['name', 'attacker|defender', 'icon image path' ]
# - (object) A list-object of the empires
# Returns:
# - None, but writes a file
###
function dataFileOut( $gameObj, $empireObj, $playersUnits, $encounterObjs, $empireList )
{
  global $DATA_OUT_FILE_FORMAT, $MODULE_FILE_STORE, $SCENARIOS;

  $modPlayerObj = loadOneObject( "user", $gameObj->modify('moderator') );
  $designList = getDesignList( $empireObj->modify('race'), $gameObj );
  $empireObjBlackList = array( "ai" ); // the empire-object properties to not emit
  $gameObjBlackList = array(
      "borderSize", "interestedPlayers", "randomSeeds",
      "moduleEncountersIn", "moduleEncountersOut", "moduleBidsIn", "moduleBidsOut"
    ); // the game-object properties to not emit
  $output = "<script type='text/javascript'>\n";

  // generate the list of this game's attributes
  $output .= "var gameObj = {\n";
  $objValues = $gameObj->values();
  foreach( $objValues as $key=>$value )
    if( ! in_array( $key, $gameObjBlackList ) )
      $output .= "'$key': '$value',\n";
  $output .= "'modName': '".$modPlayerObj->modify('fullName')."',\n";
  $output .= "'modEmail': '".$modPlayerObj->modify('email')."',\n";
  $output .= "'gameYear': '".$gameObj->gameYear()."'";
  $output = rtrim( $output, "\n,");
  $output .= "\n};\n";

  // generate the list of this empire's attributes
  $output .= "var empireObj = {\n";
  $objValues = $empireObj->values();
  foreach( $objValues as $key=>$value )
    if( ! in_array( $key, $empireObjBlackList ) )
      $output .= "'$key': '$value',\n";
  $output = rtrim( $output, "\n,");
  $output .= "\n};\n";

  // generate the list of built units
  $output .= "var unitList = {\n";
  $objValues = $playersUnits->objByID;
  $tempArray = array(); // stuff the output here, then sort it before emitting it
  foreach( $objValues as $key=>$value )
  {
    // get those ships built by and owned by this player
    if( $value->modify('empire') == $empireObj->modify('id') && empty( $value->modify('captureEmpire') ) )
    {
      $tempArray[$key] = ", '{$value->specs['designator']} &quot;{$value->modify('textName')}&quot;', '";
      $tempArray[$key] .= $value->specs['baseHull']."', '".strtolower(substr( $value->specs['empire'], 0, 3 ))."' ],";
    }
    // get those ships built by someone else and owned by this player (e.g captured)
    else if( $value->modify('captureEmpire') == $empireObj->modify('id') )
    {
      $tempArray[$key] = ", '{$value->specs['designator']} &quot;{$value->modify('textName')}&quot;', '";
      $tempArray[$key] .= $value->specs['baseHull']."', '".strtolower(substr( $value->specs['empire'], 0, 3 ))."' ],";
    }
  }
  natcasesort($tempArray); // sort the array
  foreach( $tempArray as $key=>&$value ) // put the keys into the string
    $value = "'$key': [ '$key'$value";
  $output .= implode( "\n", $tempArray );
  $output = rtrim( $output, ",");
  $output .= "\n};\n";

  // generate the list of available hull designs
  $output .= "var designList = {\n";
  $objValues = array();
  if( ! empty($designList) )
    $objValues = $designList;
  foreach( $objValues as $key=>$value )
    $output .= "'$key': [ '{$value[0]}', {$value[1]}, '{$value[2]}' ],\n";
  $output = rtrim( $output, "\n,");
  $output .= "\n};\n";

  // generate the list of encounters
  $output .= "var encounterList = {\n";
  $objValues = $encounterObjs;
  foreach( $objValues->objByID as $key=>$value )
  {
    $status = "";
    if( $value->modify('playerA') == $empireObj->modify('id') )
      $status = "Defender";
    else if( $value->modify('playerB') == $empireObj->modify('id') )
      $status = "Attacker";
    else // not a participant in this encounter
      continue;

    $scenarioIndex = $value->modify('scenario');
    $output .= "'$key': [ '{$SCENARIOS[ $scenarioIndex ][0]}', '$status', '{$SCENARIOS[ $scenarioIndex ][6]}' ],\n";
  }
  $output = rtrim( $output, "\n,");
  $output .= "\n};\n";

  // generate the list of other empires
  $output .= "var empireList = {\n";
  $objValues = $empireList;
  foreach( $objValues->objByID as $key=>$value )
  {
    if( $value->modify('id') == $empireObj->modify('id') )
      continue;

    $output .= "'$key': '{$value->modify('textName')} ({$value->modify('race')})',\n";
  }
  $output = rtrim( $output, "\n,");
  $output .= "\n};\n";

  $output .= "</script>\n";

  $filename = $MODULE_FILE_STORE.sprintf($DATA_OUT_FILE_FORMAT, $gameObj->modify('currentTurn'), $gameObj->modify('id'), $empireObj->modify('id') );
  file_put_contents( $filename, $output, LOCK_EX );
}

  ###
  # Creates a list of available ships designs for the given empire and year
  ###
  # Args are:
  # - (string) The empire to compare against
  # - (object) The object of the current game
  # Returns:
  # - (array) List of identifier => array( ship_designator, ship_bpv)
  ###
function getDesignList( $empire, $gameObj )
{
  global $SHOW_DB_ERRORS;

  $database = DataBase::giveme();	// get the database object
  $errors = "";
  $list = "";
  $output = array();
  $result = "";
  $year = $gameObj->GameYear();

  $query = "SELECT id,designator,BPV,baseHull FROM ".ShipDesign::table." WHERE empire='$empire' AND yearInService<=$year";
  $query .= " AND sizeClass>=".$gameObj->modify('largestSizeClass'); // limit by size-class
  $query .= " AND ( obsolete>=$year OR obsolete=0 )"; // limit by obsolete
  // exclude conjectural units if the game doesn't use them
  if( $gameObj->modify('allowConjectural') == false )
    $query .= " AND switches NOT LIKE '%conjectural%'";
  // set up the displayed order of the units
  $query .= " ORDER BY baseHull ASC, designator ASC";
//  $query .= " ORDER BY BPV ASC";

  $result = $database->genquery( $query, $list );
  if( $result === false )
  {
    $errors .= "TableOut: Error loading list.\n";
    if( $SHOW_DB_ERRORS )
      $errors .= $database->error_string;
    error_log( $errors, 0 );
    return false;
  }
  else
  {
    foreach( $list as $row )
      $output[ $row['id'] ] = array( $row['designator'], $row['BPV'], $row['baseHull'] );
  }
  return $output;
}


  ###
  # Creates the unit row/column for the given empire
  ###
  # Args are:
  # - (object) The list of all units in the game
  # - (object) The object of the current empire
  # - (string) [optional] A delineator to put between unit entries
  # Returns:
  # - (string) HTML that lists the units for this empire
  ###
function listEmpireUnits( $unitList, $currentEmpireObj, $delineator=", " )
{
  $output = "";
  $listA = ""; // The list of units with name
  $listB = ""; // the list of just the hull types
  $hullCount = array(); // the list of the hull counts, keyed by designation
  $listATrigger = ""; // Set to the first entry of $listA, to tell the javascript which list is being displayed
  $listBTrigger = ""; // Set to the first entry of $listB, to tell the javascript which list is being displayed

  foreach( $unitList->objByID as $unitObj )
  {
    // skip if the unit does not belong to the empire being listed for
    if( ( $unitObj->modify('empire') != $currentEmpireObj->modify('id') && 
          empty($unitObj->modify('captureEmpire')) 
        ) ||
        ( ! empty($unitObj->modify('captureEmpire')) && 
          $unitObj->modify('captureEmpire') != $currentEmpireObj->modify('id')
        )
      )
      continue;

    // assemble $listA for this unit
    $shipDesign = $unitObj->modify('specs'); // the $shipDesign object for this unit
    $designator = htmlspecialchars($shipDesign[ 'designator' ])." &quot;".$unitObj->modify('textName')."&quot;";
    // if the builder is not the same as the owner then note the builder
    if( $shipDesign['empire'] != $currentEmpireObj->modify('race') )
      $designator = htmlspecialchars($shipDesign['empire'])." $designator";
    // note the whole designator, with attributions to crippled if needed
    if( $unitObj->modify('damage') >= 50 )
      $listA .= "<span class=&quot;crippled&quot; title=&quot;Crippled&quot;>$designator</span>".$delineator;
    else
      $listA .=  $designator.$delineator;
    // fill $listATrigger if not already filled (e.g. this is the first entry of $listA)
    if( empty($listATrigger) )
      $listATrigger = $listA;

    // assemble $hullCount for this unit
    $designator = $shipDesign[ 'designator' ]; // the shipdesign string, up to the first space
    $position = strpos( $shipDesign[ 'designator' ], " " ); // where to trim out the extra from the designation
    if( $position !== false )
      $designator = substr( $shipDesign[ 'designator' ], 0, $position );
    $designator = trim( $designator ); // trim any extra whitespace
    // if the builder is not the same as the owner then note the builder
    if( $shipDesign['empire'] != $currentEmpireObj->modify('race') )
      $designator = substr( $shipDesign['empire'], 0, 3 )." $designator";
    $designator = htmlspecialchars($designator); // escape $designator
    // add the given hull to the count in $hullCount
    if( ! isset($hullCount[$designator]) )
      $hullCount[$designator] = 0;
    $hullCount[$designator]++;
  }

  // assemble $listB from $hullCount
  foreach( $hullCount as $hull=>$count )
  {
    $listB .= $count."x ".$hull.$delineator;
    // fill $listBTrigger if not already filled (e.g. this is the first entry of $listB)
    if( empty($listBTrigger) )
      $listBTrigger = $listB;
  }

  $listA = rtrim( $listA, $delineator ); // trim $listA
//  $listA = htmlspecialchars($listA); // escape $listA

  $listB = rtrim( $listB, $delineator ); // trim $listB
//  $listB = htmlspecialchars($listB); // escape $listB

  $output .= "<div onclick=\"if(this.innerHTML.indexOf('$listBTrigger')>-1)";
  $output .= "{this.innerHTML='$listA'}else{this.innerHTML='$listB'}\">$listB</div>";

  return $output;
}
###
# Creates the text of the scenarios, fit for display
###
# Args are:
# - (object) list of encounter objects
# - (int) The empire identifier of one of the scenario players
# - (int) The number to give this scenario within a list of scenarios
# - (object) list of empire objects
# - (object) list of unit objects
# - (object) list of order objects
# Returns:
# - (array) A collection of outputs:
# - - (string) The HTML of an image representing the scenario
# - - (string) The text of the scenario as part of a javascript array
# - - (int) The scenario number, incremented for use in the next call of this function
###
function bidScenarioText( $encObjList, $empireID, $scenarioIterator, $empireObjList, $unitList, $orderList )
{
  global $SCENARIOS;

  $EMPIRE_SEPARATOR = "</td><td class='scenario_table_scenarios'>"; // put between scenarios when the opponent changes
  $IMG_FILE_LOCATION = "../scenarios/";

  $output = "";
  $javascript = "";
  $previousOpponent = "";
  $opponentID = "";

  foreach( $encObjList->objByID as $encObj )
  {
    // skip if the empire in $empireID is neither player in the given scenario
    if( $encObj->modify('playerA') != $empireID && $encObj->modify('playerB') != $empireID )
      continue;

    $encounterID = $encObj->modify('id');
    $isDefender = false;
    $opponentID = 0;
    $scenarioIndex = $encObj->modify('scenario');
    $shipCountFriendly = 0;
    $shipCountHostile = 0;
    $shipBPVFriendly = 0;
    $shipBPVHostile = 0;
    $shipListFriendly = "";
    $shipListHostile = "";

    // determine who the current opponent is
    if( $encObj->modify('playerA') == $empireID )
    {
      $isDefender = true;
      $opponentID = $encObj->modify('playerB');
    }
    else
    {
      $opponentID = $encObj->modify('playerA');
    }

    // set up the separator between empires
    if( $previousOpponent != $opponentID && $previousOpponent != "" )
      $output .= $EMPIRE_SEPARATOR;
    $previousOpponent = $opponentID;

    $empireAName  = $empireObjList->objByID[ $encObj->modify('playerA') ]->modify('textName')." (";
    $empireAName .= $empireObjList->objByID[ $encObj->modify('playerA') ]->modify('race').")";
    $empireBName  = $empireObjList->objByID[ $encObj->modify('playerB') ]->modify('textName')." (";
    $empireBName .= $empireObjList->objByID[ $encObj->modify('playerB') ]->modify('race').")";

    // generate the fleet list for the scenario
    foreach( $orderList->objByID as $orderObj )
    {
      $orders = $orderObj->decodeOrders();

      foreach( $orders['bids'] as $shipBids )
      {
          // skip the orders if they are for an empire not involved in the scenario
          if( $shipBids['encounter'] != $encObj->modify('id') )
            continue;
          if( $unitList->objByID[$shipBids['ship']]->modify('captureEmpire') )
            $ownerEmpire = $unitList->objByID[$shipBids['ship']]->modify('captureEmpire');
          else
            $ownerEmpire = $unitList->objByID[$shipBids['ship']]->modify('empire');
          $shipEmpire = $unitList->objByID[$shipBids['ship']]->modify('empire');
          $shipDesign = $unitList->objByID[$shipBids['ship']]->modify('specs');
          $designator = $shipDesign[ 'designator' ]." \"".$unitList->objByID[$shipBids['ship']]->modify('textName')."\"";
          $designator = addslashes($designator);
          if( $ownerEmpire == $encObj->modify('playerA') )
          {
            // if the builder is not the same as the owner then note the builder
            if( $ownerEmpire != $shipEmpire )
              $designator = $shipDesign['empire']." $designator";
            // provide attributions to damage if needed
            if( $unitList->objByID[$shipBids['ship']]->modify('damage') >= 50 )
              $designator .= " (Crippled)";

            $shipListFriendly .= "$designator, ";
            $shipBPVFriendly += $unitList->objByID[$shipBids['ship']]->specs['BPV'];
            $shipCountFriendly++;
          }
          if( $ownerEmpire == $encObj->modify('playerB') )
          {
            // if the builder is not the same as the owner then note the builder
            if( $ownerEmpire != $shipEmpire )
              $designator = $shipDesign['empire']." $designator";
            // provide attributions to damage if needed
            if( $unitList->objByID[$shipBids['ship']]->modify('damage') >= 50 )
              $designator .= " (Crippled)";

            $shipListHostile .= "$designator, ";
            $shipBPVHostile += $unitList->objByID[$shipBids['ship']]->specs['BPV'];
            $shipCountHostile++;
          }
      }
    }

    $shipListFriendly = rtrim( $shipListFriendly, ", " );
    $shipListHostile = rtrim( $shipListHostile, ", " );
    // if the ship lists are empty, put in some placeholder text
    if( $shipListFriendly == "" )
      $shipListFriendly = "No defender's ships bid";
    if( $shipListHostile == "" )
      $shipListHostile = "No attacker's ships bid";

    // populate the text output
    $output .= "<a href='#' onclick='return popitup($scenarioIterator)' title='";
    $output .= $SCENARIOS[ $scenarioIndex ][0]." #$encounterID'><img src='";
    $output .= $IMG_FILE_LOCATION.$SCENARIOS[ $scenarioIndex ][6]."' alt='";
    $output .= $SCENARIOS[ $scenarioIndex ][0]." #$encounterID' class='scenario'></a>\n";

    // set up the Javascript array index for this encounter
    // The scenario name
    $javascript .= "scenarioJS[$scenarioIterator] = \"<h3>".$SCENARIOS[ $scenarioIndex ][0]." #$encounterID</h3>";
    // The scenario has been auto-resolved, if need be
    if( $encObj->modify('status') == Encounter::OVERWHELMING_FORCE )
      $javascript .= "<b>Overwhelming force is present.</b><br>";
    else if( $encObj->modify('status') == Encounter::APPLY_NO_RESULT )
      $javascript .= "<b>This encounter will be dropped with no resolution.</b><br>";
    else if( $encObj->modify('status') == Encounter::PLAYER_A_VICTORY )
      $javascript .= "<b>The $empireAName has successfully defended the scenario.</b><br>";
    else if( $encObj->modify('status') == Encounter::PLAYER_A_DEFEATED )
      $javascript .= "<b>The $empireAName has not been successful in their defense.</b><br>";
    // The scenario rewards
    $javascript .= "<b>Defender's reward for successful defense by the $empireAName:</b> ".$SCENARIOS[ $scenarioIndex ][2]."<br>";
    $javascript .= "<b>Defender's penalty for unsuccessful defense:</b> ".$SCENARIOS[ $scenarioIndex ][3]."<br>";
    $javascript .= "<b>Attacker's reward for an unsuccessful defense:</b> ".$SCENARIOS[ $scenarioIndex ][4]."<br>";
    // the bulk of the scenario text
    $javascript .= sprintf( $SCENARIOS[ $scenarioIndex ][1],
                     $empireAName, $empireBName,
                     $shipListFriendly, $shipListHostile,
                     $shipBPVFriendly, $shipBPVHostile,
                     $shipCountFriendly, $shipCountHostile
                   );
    $javascript .= "\";\n";
    $scenarioIterator += 1;
  }
  return array( $output, $javascript, $scenarioIterator );
}

###
# Creates the text of the scenario, fit for display
###
# Args are:
# - (object) list of encounter objects
# - (int) The empire identifier of one of the scenario players
# - (int) The number to give this scenario within a list of scenarios
# - (object) list of empire objects
# Returns:
# - (array) A collection of outputs:
# - - (string) The HTML of an image representing the scenario
# - - (string) The text of the scenario as part of a javascript array
# - - (int) The scenario number, incremented for use in the next call of this function
###
function encounterScenarioText( $encObjList, $empireID, $scenarioIterator, $empireObjList )
{
    global $SCENARIOS;

    $EMPIRE_SEPARATOR = "</td><td class='scenario_table_scenarios'>"; // put between scenarios when the opponent changes
    $IMG_FILE_LOCATION = "../scenarios/";
    $output = "";
    $javascript = "";
    $previousOpponent = "";
    $opponentID = "";

    foreach( $encObjList->objByID as $encObj )
    {
      // skip if the empire in $empireID is neither player in the given scenario
      if( $encObj->modify('playerA') != $empireID && $encObj->modify('playerB') != $empireID )
        continue;

      // determine who the current opponent is
      if( $encObj->modify('playerA') == $empireID )
      {
        $isDefender = true;
        $opponentID = $encObj->modify('playerB');
      }
      else
      {
        $opponentID = $encObj->modify('playerA');
      }

      // set up the separator between empires
      if( $previousOpponent != $opponentID && $previousOpponent != "" )
        $output .= $EMPIRE_SEPARATOR;
      $previousOpponent = $opponentID;

      $encounterID = $encObj->modify('id');
      $scenarioIndex = $encObj->modify('scenario');
      $empireAName  = $empireObjList->objByID[ $encObj->modify('playerA') ]->modify('textName')." (";
      $empireAName .= $empireObjList->objByID[ $encObj->modify('playerA') ]->modify('race').")";
      $empireBName  = $empireObjList->objByID[ $encObj->modify('playerB') ]->modify('textName')." (";
      $empireBName .= $empireObjList->objByID[ $encObj->modify('playerB') ]->modify('race').")";

      // populate the function outputs
      $output .= "<a href='#' onclick='return popitup($scenarioIterator)' title='";
      $output .= $SCENARIOS[ $scenarioIndex ][0]." #$encounterID'><img src='";
      $output .= $IMG_FILE_LOCATION.$SCENARIOS[ $scenarioIndex ][6]."' alt='";
      $output .= $SCENARIOS[ $scenarioIndex ][0]." #$encounterID' class='scenario'></a>\n";
      // The scenario name
      $javascript .= "scenarioJS[$scenarioIterator] = \"<h3>".$SCENARIOS[ $scenarioIndex ][0]." #$encounterID</h3>";
      // The scenario rewards
      $javascript .= "<b>Defender's reward for successful defense:</b> ".$SCENARIOS[ $scenarioIndex ][2]."<br>";
      $javascript .= "<b>Defender's penalty for unsuccessful defense:</b> ".$SCENARIOS[ $scenarioIndex ][3]."<br>";
      $javascript .= "<b>Attacker's reward for an unsuccessful defense:</b> ".$SCENARIOS[ $scenarioIndex ][4]."<br>";
      // the bulk of the scenario text
      $javascript .= sprintf( $SCENARIOS[ $scenarioIndex ][1],
                       $empireAName, $empireBName,
                       "The defender's ships that are bid", "The attacker's ships that are bid", 0, 0, 0, 0
                     );
      $javascript .= "\";\n";
      $scenarioIterator += 1;
    }
    return array( $output, $javascript, $scenarioIterator );
}
?>
