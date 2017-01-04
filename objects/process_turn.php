#!/usr/bin/php -q
<?php
/*
###
Players bid ships to encounters
###
accept ship dispersements (via module).
auto-handle one-sided or no-sided encounters
announce Full Scenario Details (which include ships involved) for all scenarios (via module), including auto-handled scenarios (but announces those as finished).
###
Players run encounters
###
accept encounter results (win/loss, cripples/destroyed) (via module)
accept build orders, conversion orders, repair orders (via module). Converted/refit/repaired ships may not participate in bidding.
Cripple and Destroy units
Capture Units
assign rewards based on encounter results.
track experience (if needed). Awards crew quality.
advance turn
(fencepole issue: objects at this point represent their status at the end of the last turn.
Thus the 0th turn objects represent the start of the game. The 1st turn objects do not.)
draw encounters.
build / convert units
Announce encounters (via module) to all players.
*/
## noborder then EPs give no EPs
require_once( dirname(__FILE__) . "/../objects/game.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../scenarios/scenarios.php" );

class ProcessTurn
{
  protected $currentYear = 0;
  protected $empireObjs = "";
  protected $encounterObjs = "";
  protected $gameID = 0;
  protected $gameObj = "";
  protected $lookUps = "";
  protected $ordersObjs = ""; // this isn't assigned in the constructor. Assigned from input feeds
  protected $shipObjs = "";
  protected $turnNum = 0;

  public $outputDisplay = "";
  public $DISPLAY_ODDS = false;	// if true, will output the odds and random numbers used inside the processing

  function __construct( $gameID, $turnNum )
  {
    $this->gameID = intval( $gameID );
    $this->turnNum = intval( $turnNum );
    $this->gameObj = new Game( $this->gameID );
    $this->gameObj->read();
    $this->empireObjs = new objList( "empire", $this->gameID, $this->turnNum );
    $this->encounterObjs = new objList( "encounter", $this->gameID, $this->turnNum );
    $this->shipObjs = new objList( "ship", $this->gameID, $this->turnNum );

    $this->currentYear = $this->gameObj->modify('gameStart') + floor( $this->turnNum / $this->gameObj->modify('campaignSpeed') );

    // seed the random generator
    $this->_randomSeed();

  }

  function __destruct ()
  {
    // kill the lookup lists
    $this->gameObj->__destruct();
    unset( $this->gameObj );
    $this->encounterObjs->__destruct();
    unset( $this->encounterObjs );
    $this->shipObjs->__destruct();
    unset( $this->shipObjs );
    $this->empireObjs->__destruct();
    unset( $this->empireObjs );
  }


  ###
  # Checks for an empire that has not given any orders
  ###
  # Arguments:
  # - (array) a list of empire identifiers that have submitted orders. format is ( [ID]=>ID,[ID]=>ID, ... )
  # Returns:
  # - (bool) false if someone has not given orders. true otherwise
  ###
  function _checkNoOrders( $listFromOrders )
  {
    $empireListFromObjList = array(); // list of empire IDs from $this->empireObjs
    $empKeys = $this->empireObjs->keys();
    $outputText = "";

    // populate $empireListFromObjList in the same format as $empireListFromOrders will have.
    // Allows a simple compare of arrays
    foreach( $empKeys as $empireID )
    {
      $empObj = $this->empireObjs->objByID[$empireID];

      // Treat the exceptions for games just starting
      if( $this->gameObj->modify('currentTurn') < 1 )
        continue;
      // Treat the exceptions for empires with no borders
      $borders = $empObj->bordersDecode();
      foreach( $borders as $key=>$value )
        if( $value == 0 )
          unset( $borders[$key] );
      if( empty($borders) )
        continue;
      // Treat the exceptions for empires with no ships
      $ships = $this->shipObjs->tableSearch( "empire", $empireID );
      if( empty($ships) )
        continue;

      $empireListFromObjList[$empireID] = $empireID;
    }

    // check $empireListFromOrders against the empires listed as part of this game
    $ordersCompare = array_diff( $empireListFromObjList, $listFromOrders );
    if( ! empty($ordersCompare) )
    {
      $ordersCompare = array_values( $ordersCompare );
      // if only one empire failed to submit orders, report who it was
      if( count($ordersCompare) == 1 )
      {
        $empObj = $this->empireObjs->objByID[ $ordersCompare[0] ];
        $outputText .= "<br>&nbsp;&bull; The ".$empObj->modify('race')." empire did not submit orders.\n";
      }
      else
      {
        $outputText .= "<br>&nbsp;&bull; ".count($ordersCompare)." empires did not submit orders.\n";
      }

      return array( false, $outputText );
    }

    return array( true, $outputText );
  }

  ###
  # Applies the reward/penalty from an encounter
  ###
  # Arguments:
  # - (int) The ID of the player to apply this to
  # - (int) The ID of the opposing player
  # - (string) The result to apply. If blank, gives no reward
  # - (int) [optional] The amount of the result to apply
  # Returns:
  # - none
  ###
  function _performReward( $empireID, $switchedID, $result, $amount=0 )
  {
    $empireObj = $this->empireObjs->objByID[ $empireID ];
    $switchedObj = $this->empireObjs->objByID[ $switchedID ];
    // create an effect according to $resultString
    switch( strtolower($result) )
    {
    case "border":
      // perform this function for the switched ID only if it doesn't match the original ID
      if( $empireID != $switchedID )
      {
        $borderAmt = $empireObj->bordersFind( $switchedID ) + 1;
        $empireObj->bordersChange( $switchedID, $borderAmt );
        $switchedObj->bordersChange( $empireID, $borderAmt );
        $empireObj->update();
        $switchedObj->update();
      }
      break;
    case "ep":
      $ep = $empireObj->modify('storedEP');
      $empireObj->modify('storedEP', ($ep+$amount) );
      $empireObj->update();
      break;
    case "gift":
      $gift = new Ship( array(
              'design' => $amount,
              'empire' => $empireID,
              'game' => $this->gameID,
              'turn' => $this->turnNum,
            ) );
      $gift->create();
      $this->shipObjs->push( $gift );
      unset( $gift );
      break;
    case "income":
      $income = $empireObj->modify('income');
      $empireObj->modify('income', ($income+$amount) );
      break;
    case "newborder":
      // perform this function for the switched ID only if it doesn't match the original ID
      if( $empireID != $switchedID )
      {
        $empireKeys = $this->empireObjs->keys();
        // randomly select an empire ID from the lookup
        $chanceKey = rand( 0, count( $empireKeys )-1 );
        while( $empireKeys[ $chanceKey ] == $empireID ) // we don't want a border with ourselves
        {
          $chanceKey = rand( 0, count( $empireKeys ) );
        }

        $newBorderTargetID = $empireKeys[ $chanceKey ]; // target empireID for the new border
        $newBorderTargetObj = $this->empireObjs->objByID[ $newBorderTargetID ];
        $borderAmt = $empireObj->bordersFind( $newBorderTargetID ) + 1;
        $empireObj->bordersChange( $newBorderTargetID, $borderAmt );
        $newBorderTargetObj->bordersChange( $empireID, $borderAmt );
        $empireObj->update();
        $newBorderTargetObj->update();
      }
      break;
    case "noborder":
      // perform this function for the switched ID only if it doesn't match the original ID
      if( $empireID != $switchedID )
      {
        $borderAmt = $empireObj->bordersFind( $switchedID ) - 1;
        $empireObj->bordersChange( $switchedID, $borderAmt );
        $switchedObj->bordersChange( $empireID, $borderAmt );
        $empireObj->update();
        $switchedObj->update();
      }
      break;
    case "xpbatt":
      break;
    case "xpheavy":
      break;
    case "xpphaser":
      break;
    default:
      break;
    }
    unset( $empireObj );
    unset( $switchedObj );
  }

  ###
  # Tests for being able to build/convert/refit a new ship
  ###
  # Arguments:
  # - (int) The design ID of the new ship to create
  # - (int) The current balance of the empire's stored EPs
  # - (int) The ship ID if a conversion is to occur
  # - (object) The empire object that is building the unit
  # Returns:
  # - (int) The ID of the created ship object, if nothing stopped the build. false otherwise
  ###
  protected function _performNewShipType( $designID, $balance, $thisEmpireObj, $preConversionID=-1 )
  {
    $output = false;
    $designID = intval( $designID );
    if( $designID <= 0 ) // error out if the design is not defined
      return false;
    if( $this->gameObj->modify('currentSubTurn') > 0 ) // error out if we cannot build this turn
      return false;

    $targetShipDesign = new ShipDesign( $designID );
    $targetShipDesign->read();
    $targetShipDesign->modify('autowrite', false);
    if( $this->currentYear < $targetShipDesign->modify('yearInService') )
    {
      // The YIS date of the build is in the future
      $this->outputDisplay .= "<br>&nbsp;&bull; Player for the ".$thisEmpireObj->modify('race')." attempted to build or convert a '";
      $this->outputDisplay .= $targetShipDesign->modify('designator')."' before they are available.\n";
    }
    else if( strtolower( $targetShipDesign->modify('empire') ) != 'general' && 
             strtolower( $targetShipDesign->modify('empire') ) != strtolower( $thisEmpireObj->modify('race') ) )
    {
      // The building empire doesn't match the design empire
      $this->outputDisplay .= "<br>&nbsp;&bull; Player for the ".$thisEmpireObj->modify('race')." attempted to build or convert a '";
      $this->outputDisplay .= $targetShipDesign->modify('designator')."' of a different race.\n";
    }
    else if( $balance < $targetShipDesign->modify('BPV') )
    {
      // stored EP are less than the cost to build this unit
      $this->outputDisplay .= "<br>&nbsp;&bull; Player for the ".$thisEmpireObj->modify('race')." attempted to build or convert a '";
      $this->outputDisplay .= $targetShipDesign->modify('designator')."' with insufficient funds.\n";
    }
    else if( $this->gameObj->modify('allowConjectural') == false &&
             stripos( $targetShipDesign->modify('switches'), 'conjectural' ) !== false
           )
    {
      // this unit is conjectural and not allow by the game
      $this->outputDisplay .= "<br>&nbsp;&bull; Player for the ".$thisEmpireObj->modify('race')." attempted to build or convert a '";
      $this->outputDisplay .= $targetShipDesign->modify('designator')."', which is a disallowed unit by the game.\n";
    }
    else if( $preConversionID >= 0 && isset($this->shipObjs->objByID[$preConversionID]) )
    {
      $this->shipObjs->objByID[$preConversionID]->modify('design', $designID );
      $output = $preConversionID;
    }
    else
    {
      $newShip = new Ship( array(
          'design'=>$designID, 'empire'=>$thisEmpireObj->modify('id'),
          'game'=>$this->gameID, 'turn'=>$this->turnNum
        ) );
      $result = $newShip->create();
      if( $result )
        $output = $this->shipObjs->push( $newShip );
      unset( $newShip );
    }
    unset( $thisEmpireObj );
    return $output;
  }

  ###
  # Seeds the random-number generator
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  protected function _randomSeed()
  {
    $lastTurn = $this->turnNum - 1;
    $seeds = $this->gameObj->modify('randomSeeds');
    $seedArray = explode( ",",$seeds );

    // Use rand() instead of mt_rand() because mt_rand() won't reproduce the same values for the same seed
    if( empty($seedArray) )
    {
      srand();
      if( $this->turnNum > 1 )
        $seedArray = array_fill( 0, $lastTurn, rand() );
      else
        $seedArray[0] = rand();

      $seeds = implode( ",", $seedArray );
      $this->gameObj->modify( 'randomSeeds', $seeds );
    }
    else if( ! isset( $seedArray[$lastTurn] ) || empty( $seedArray[$lastTurn] )  )
    {
      srand( intval(end($seedArray)) );
      $seedArray[($this->turnNum-1)] = rand();

      $seeds = implode( ",", $seedArray );
      $this->gameObj->modify( 'randomSeeds', $seeds );
    }
    else
    {
      srand( $seedArray[$lastTurn] );
    }
  }

  ###
  # Handle one-sided or no-sided encounters
  ###
  # For each encounter in which only one empire sent ships, it is resolved outright in favor of that empire.
  # For each encounter in which no empire sent ships, it is counted as a defeat for the defender.
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # Returns:
  # - none
  ###
  public function autoAdjudicateEasy( $inputFeed )
  {
    global $AUTO_ORDER_ONE_SIDED_SCENARIOS;
    $encounterBids = array(); // list of encounters that have units assigned to them
    $overwhelmingForceConstant = $this->gameObj->modify( 'overwhelmingForce' ) / 100;
    $numAutoAdjudicated = 0;
    $this->outputDisplay .= "<br>&nbsp; <b>Handling Lopsided Encounters.</b>\n";

    // build a list of encounters that have 1 or more bids
    foreach( $inputFeed->objByID as $orderID=>$orderObj )
    {
      $playerOrders = $orderObj->decodeOrders();
      foreach( $playerOrders['bids'] as $shipBids )
        if( ! empty( $shipBids['ship'] ) )
        {
          if( ! isset( $encounterBids[ $shipBids['encounter'] ] ) )
            $encounterBids[ $shipBids['encounter'] ] = array();
          $encounterBids[ $shipBids['encounter'] ][ $orderObj->modify('empire') ][] = $shipBids['ship'];
        }
    }


    // Mark the encounters with no ships assigned as No Result
    foreach( $this->encounterObjs->objByID as $encounterID=>$encounterObj )
    {
      // if the encounter is not on the unit-present-list, then mark it APPLY_NO_RESULT
      if( ! isset( $encounterBids[$encounterID] ) )
      {
        $encounterObj->modify( 'status', Encounter::APPLY_NO_RESULT );
        $encounterObj->update();
      }
    }

    // check for encounters with 0 or 1 players assigned
    foreach( $encounterBids as $encounterID=>$playerArray )
    {
      // skip if there is no encounter with this identifier
      if( ! isset( $this->encounterObjs->objByID[$encounterID] ) )
        continue;
      // set this encounter
      $encounterObj = $this->encounterObjs->objByID[$encounterID];
      // clear out any previous resolution-status on this encounter
      $encounterObj->modify( 'status', Encounter::NEEDS_RESOLUTION );
      // if two players are involved, skip this encounter
      if( count($playerArray) > 1 )
        continue;
      $playerArrayKeys = array_keys( $playerArray );
      // prepare for giving orders
      if( $AUTO_ORDER_ONE_SIDED_SCENARIOS )
      {
        // get the index into the order lists, by finding the order(s) with the known empire ID
        $playerAOrderID = $inputFeed->tableSearch( "empire", $encounterObj->modify('playerA') );
        $playerBOrderID = $inputFeed->tableSearch( "empire", $encounterObj->modify('playerB') );
        // get the order objects out of the $inputFeed
        $playerAOrderObj = $inputFeed->objByID[ $playerAOrderID[0] ];
        $playerBOrderObj = $inputFeed->objByID[ $playerBOrderID[0] ];
      }
      // determine which player has ships there
      if( $encounterObj->modify('playerA') == end($playerArrayKeys) )
      {
        $encounterObj->modify( 'status', Encounter::PLAYER_A_VICTORY );
        if( $AUTO_ORDER_ONE_SIDED_SCENARIOS )
        {
          $playerAOrderObj->addOrders( "victory".$encounterID );
          $playerBOrderObj->addOrders( "victory".$encounterID );
          $playerAOrderObj->update();
          $playerBOrderObj->update();
        }
      }
      else if( $encounterObj->modify('playerB') == end($playerArrayKeys) )
      {
        $encounterObj->modify( 'status', Encounter::PLAYER_A_DEFEATED );
        if( $AUTO_ORDER_ONE_SIDED_SCENARIOS )
        {
          $playerAOrderObj->addOrders( "defeat".$encounterID );
          $playerBOrderObj->addOrders( "defeat".$encounterID );
          $playerAOrderObj->update();
          $playerBOrderObj->update();
        }
      }
      $numAutoAdjudicated++;
    }
    $this->outputDisplay .= "<br>&nbsp;&bull; $numAutoAdjudicated encounters are auto-adjudicated.\n";
    $numAutoAdjudicated = 0;
    // determine if overwhelming force is present
    foreach( $this->encounterObjs->objByID as $encounterID=>&$encounterObj )
    {
      // if the encounter does not need resolution, skip it
      if( $encounterObj->modify('status') != Encounter::NEEDS_RESOLUTION )
        continue;
      if( ! isset( $encounterBids[$encounterID] ) )
        continue; // not sure if this is a good thing here
      $playerBPV = array();
      $playerShips = array(); // create the unit list while we are counting the BPV present
      foreach( $encounterBids[$encounterID] as $playerID=>$shipArray )
      {
        $playerBPV[$playerID] = 0;
        $playerShips[$playerID] = array();
        foreach( $shipArray as $shipID )
        {
          $shipObj = $this->shipObjs->objByID[$shipID];
          $designObj = new shipDesign( $shipObj->modify('design') );
          $designObj->read();
          $shipBPV = $designObj->modify('BPV');
          $playerBPV[$playerID] += $shipBPV;
          $playerShips[$playerID][] = $designObj->modify('designator');
          unset( $designObj );
        }
      }
      $playerBPV = array_values( $playerBPV );
      list( $A, $B ) = $playerBPV;
      if( ($A != 0 && $B != 0) &&
          ( $A * $overwhelmingForceConstant <= $B || $B * $overwhelmingForceConstant <= $A )
        )
      {
        $encounterObj->modify( 'status', Encounter::OVERWHELMING_FORCE );
        $numAutoAdjudicated++;
      }
      $encounterObj->modify( 'playerAShips', implode($playerShips[$encounterObj->modify('playerA')]) );
      $encounterObj->modify( 'playerBShips', implode($playerShips[$encounterObj->modify('playerB')]) );
    }
    $this->outputDisplay .= "<br>&nbsp;&bull; $numAutoAdjudicated encounters have overwhelming force.\n";

    $outputFeed = $this->encounterObjs;

    $this->outputDisplay .= "<br>&nbsp;&bull; Examined ".count($this->encounterObjs->objByID)." encounters.\n";

    return $outputFeed;
  }

  ###
  # Makes sure there were no errors with the bid orders
  ###
  # Checks for assigning a ship that should have been held back or is dead
  # Checks for double-assigning a ship
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # Returns:
  # - (bool) False if an error worth suspending the script occurs. True otherwise
  # - (string) A list of any errors found
  ###
  public function checkBidErrors( &$inputFeed )
  {
    global $STOP_ON_MISSING_ORDERS;

    $outputText = "<br>&nbsp; <b>Checking for errors in the bidding orders.</b>\n";
    $empireListFromOrders = array(); // list of empire IDs that have supplied orders

    foreach( $inputFeed->objByID as &$orderObj )
    {
      $empireID = $orderObj->modify('empire');
      $empireListFromOrders[ $empireID ] = $empireID;
      $orders = $orderObj->decodeOrders();
      $isModified = false;
      $orderOwnerRace = ucfirst( $this->empireObjs->objByID[ $empireID ]->modify('race') );
      $orderOwnerRaceName = ucfirst( $this->empireObjs->objByID[ $empireID ]->modify('name') );

      foreach( $orders['bids'] as $shipBids )
      {
        // check for orders to bid, but the ship is in the reserves (e.g. being held back because of other orders)
          if( ! isset($this->shipObjs->objByID[ $shipBids['ship'] ]) )
          {
            $outputText .= "<br>&nbsp;&bull; $orderOwnerRaceName attempted to give bid order to ";
            $outputText .= "$orderOwnerRace ship #{$shipBids['ship']}, which does not exist.";
            // remove the offending order from the order string
            $orderObj->removeOrders( "bid{$shipBids['ship']}to{$shipBids['encounter']}" );
            $isModified = true;
            continue;
          }
          $shipObj = &$this->shipObjs->objByID[ $shipBids['ship'] ];
          if( $shipObj->modify('status') == Ship::RESERVE || $shipObj->modify('status') == Ship::MOTHBALL )
          {
            // mark the ship as in an encounter
            $shipObj->modify('isInEncounter', true );
            // remove the offending order from the order string
            $orderObj->removeOrders( "bid{$shipBids['ship']}to{$shipBids['encounter']}" );
            $isModified = true;
            // report the problem
            $shipOwner = ucfirst( $this->empireObjs->objByID[ $shipObj->modify('empire') ]->modify('race') );
            $outputText .= "<br>&nbsp;&bull; $orderOwnerRace ship #{$shipBids['ship']} '{$shipObj->modify('textName')}' had bid orders ";
            $outputText .= "to encounter #{$shipBids['encounter']}, but was being held back for repair, conversion, or mothballs.\n";
          }

        // fail if this ship is dead
          if( $shipObj->modify('isDead') == true )
          {
            // mark the ship as in an encounter
            $shipObj->modify('isInEncounter', true );
            // remove the offending order from the order string
            $orderObj->removeOrders( "bid{$shipBids['ship']}to{$shipBids['encounter']}" );
            $isModified = true;
            // report the problem
            $shipOwner = ucfirst( $this->empireObjs->objByID[ $shipObj->modify('empire') ]->modify('race') );
            $outputText .= "<br>&nbsp;&bull; $shipOwner ship #{$shipBids['ship']} '{$shipObj->modify('textName')}'";
            $outputText .= " had bid orders, but was previously destroyed.\n";
          }

        // look for ships that are bid multiple times
          if( $shipObj->modify('isInEncounter') == true )
          {
            // remove the offending order from the order string
            $orderObj->removeOrders( "bid{$shipBids['ship']}to{$shipBids['encounter']}" );
            $isModified = true;
            // report the problem
            $shipOwner = ucfirst( $this->empireObjs->objByID[ $shipObj->modify('empire') ]->modify('race') );
            $outputText .= "<br>&nbsp;&bull; $shipOwner ship #{$shipBids['ship']} '{$shipObj->modify('textName')}'";
            $outputText .= " had multiple bid orders. Skipping it's assignment to encounter";
            $outputText .= " #{$shipBids['encounter']}.\n";
          }

          // note that this ship is in an encounter
          $shipObj->modify('isInEncounter', true);
          $shipObj->update();
      }
      // clear the decoded orders because some of the orders behind it might have changed
      $orderObj->modify( 'decodedOrders', "" );
      // update the orders if they were modified
      if( $isModified )
        $orderObj->update();
    }

    // check the empires for someone not submitting orders
    list( $status, $text) = $this->_checkNoOrders( $empireListFromOrders );
    $outputText .= $text;

/*
    // erase the orders feed if someone did not provide any orders
    if( ! $status )
    {
      // shut down the Orders Feed
      $inputFeed->__destruct();
      unset( $inputFeed );

      return array( false, $outputText);
    }
*/

    // If someone did not submit orders, put in a blank set of orders for them
    $empKeys = $this->empireObjs->keys();
    foreach( $empKeys as $empireID )
    {
      if( ! isset($empireListFromOrders[$empireID]) )
      {
        // add a blank order object for these people
        $orderData = array( 'game'=>$this->gameID,'orders'=>"",'empire'=>$empireID,'turn'=>$this->turnNum );
        $newOrder = new Orders( $orderData );
        $newOrder->modify( "autowrite", false );
//
        $newOrder->create();
        $newOrder->read();
//
        $inputFeed->push( $newOrder );
        $outputText .= "<br>Gave a blank order for '{$this->empireObjs->objByID[$empireID]->modify('textName')}'";
        $outputText .= " because they did not submit any orders.";
      }
    }

    return array( true, $outputText);
  }

  ###
  # Makes sure there were no errors with the encounter orders
  ###
  # resolves the encounters per the orders
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # Returns:
  # - (bool) False if an error worth suspending the script occurs. True otherwise
  # - (string) A list of any errors found
  ###
  public function checkEncounterErrors( &$inputFeed )
  {
    global $STOP_ON_MISSING_ORDERS, $STOP_ON_UNFINISHED_SCENARIOS, $SCENARIOS;

    $empireListFromOrders = array(); // list of empire IDs that have supplied orders
    $outputText = "<br>&nbsp; <b>Checking for errors in the encounter orders.</b>\n";
    $notResolvedFlag = false;	// error flag because an encounter is not resolved

    foreach( $inputFeed->objByID as $orderObj )
    {
      $empireID = $orderObj->modify('empire');
      $empireListFromOrders[ $empireID ] = $empireID;
    }

    // check the empires for someone not submitting orders
    list( $status, $text ) = $this->_checkNoOrders( $empireListFromOrders );
    $outputText .= $text;
    if( ! $status && $STOP_ON_MISSING_ORDERS )
      return array( false, $outputText );

    // determine if all encounters were resolved
    foreach( $this->encounterObjs->objByID as $encounter )
    {
      $encHasResolveOrder = false;
      // skip the order if it will be dropped
      if( $encounter->modify('status') == Encounter::APPLY_NO_RESULT )
        continue; // continue the encounter-check loop

      // Check for if no player has victory orders regarding this encounter
      foreach( $inputFeed->objByID as $orderObj )
      {
        if( $orderObj->modify('empire') == $encounter->modify('playerA') ||
            $orderObj->modify('empire') == $encounter->modify('playerB')   )
        {
          // check to see if the victory order is given
          $orderSet = $orderObj->decodeOrders();
          if( isset( $orderSet['encounters'][ $encounter->modify('id') ] ) )
          {
            $encHasResolveOrder = true;
            break; // break the order-check loop
          }
        }
      }

      if( ! $encHasResolveOrder )
      {
        $outputText .= "<br>&nbsp;&bull; Encounter #{$encounter->modify('id')} '";
        $outputText .= $SCENARIOS[$encounter->modify('scenario')][0]."' was not resolved in orders.\n";
        $notResolvedFlag = true;
      }
    }

    if( $notResolvedFlag )
      return array( false, $outputText );
    return array( true, $outputText );
  }

  ###
  # Resolves the encounters per the orders
  ###
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # - (bool) [optional] set to true to erase any previous resolution of 
  #          encounters before performing this resolution
  # Returns:
  # - (bool) False if an error worth suspending the script occurs. True otherwise
  ###
  public function ResolveEncounters( &$feed, $erasePreviousResolve=false )
  {
    global $STOP_ON_UNFINISHED_SCENARIOS;

    $notResolvedFlag = false;

    $this->outputDisplay .= "<br>&nbsp;&bull; Resolving Encounters.\n";

    // remove any previous resolution, if needed
    foreach( $this->encounterObjs->objByID as $encounter )
    {
      if( $erasePreviousResolve )
        $encounter->modify( 'status', Encounter::NEEDS_RESOLUTION );
    }

/*
    $encounterListWithUnits = array(); // list of encounters that have units assigned to them
    // make a list of encounters that have units present        
    foreach( $feed->objByID as $orderObj )
    {
      $inputOrders = $orderObj->decodeOrders();

      foreach( $inputOrders['bids'] as $shipBids )
      {
        $encounterID = $shipBids['encounter'];
        $encounterListWithUnits[$encounterID] = $encounterID;
      }
    }
    foreach( $this->encounterObjs->objByID as $encounterID=>$encounterObj )
    {
      // if the encounter is not on the unit-present-list, then mark it APPLY_NO_RESULT
      if( ! isset( $encounterListWithUnits[$encounterID] ) )
        $encounterObj->modify( 'status', Encounter::APPLY_NO_RESULT );
    }
*/

    foreach( $feed->objByID as $orderObj )
    {
      $inputOrders = $orderObj->decodeOrders();
      // set the encounters as victory or defeat, per the orders
      foreach( $inputOrders['encounters'] as $encounterID=>$victory )
      {
        if( ! isset($this->encounterObjs->objByID[$encounterID]) )
        {
          $this->outputDisplay .= "<br>&nbsp;&bull; Error: Attempted to give orders to an encounter that doesn't exist.\n";
          // attempt to kill that order
          unset( $inputOrders['encounters'][$encounterID] ); // this won't hold up against a new decodeOrders()
        }
        $encounterObj = $this->encounterObjs->objByID[$encounterID];
        // if the encounter is not yet resolved, or the defender is overruling the attacker's claim
        if( $encounterObj->modify('status') == Encounter::NEEDS_RESOLUTION ||
            $encounterObj->modify('status') == Encounter::OVERWHELMING_FORCE ||
            ( $orderObj->modify('empire') == $encounterObj->modify('playerA') &&
              $encounterObj->modify('status') != Encounter::APPLY_NO_RESULT )
          )
        {
          if( $victory )
            $encounterObj->modify( 'status', Encounter::PLAYER_A_VICTORY );
          else
            $encounterObj->modify( 'status', Encounter::PLAYER_A_DEFEATED );
        }
      }
    }

    // incorporate the input feed as our orders list
    $this->ordersObjs = $feed;

    // determine if all encounters were resolved
    foreach( $this->encounterObjs->objByID as $encounter )
    {
      if( $encounter->modify('status') == Encounter::NEEDS_RESOLUTION ||
          $encounter->modify('status') == Encounter::OVERWHELMING_FORCE
        )
      {
        $this->outputDisplay .= "<br>&nbsp;&bull; Encounter with ID# {$encounter->modify('id')} was not resolved in orders.\n";
        $notResolvedFlag = true;
      }
    }

    // if we have unresolved scenarios and the config files says we stop on those, then do the stopping
    if( $notResolvedFlag && $STOP_ON_UNFINISHED_SCENARIOS )
      return false;

    return true;
  }

  ###
  # Destroys units per orders
  # Cripples units per orders
  # Gifts units per orders
  # Changes empire names when so ordered
  # Changes ship names when so ordered
  ###
  # Checks for 
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  public function destroyUnits ()
  {
    $this->outputDisplay .= "<br>&nbsp; <b>Destroying and Crippling Units.</b>\n";

    $countKills = 0;
    $countCripples = 0;
    $countGifts = 0;

    foreach( $this->ordersObjs->objByID as $orders )
    {
      if( ! isset( $this->empireObjs->objByID[ $orders->modify('empire') ] ) )
      {
        $this->outputDisplay .= "<br>&nbsp; - Error in orders for empire #".$orders->modify('empire').". Empire does not exist in database.\n";
        continue;
      }
      $decodedOrders = $orders->decodeOrders();
      $empireObj = $this->empireObjs->objByID[ $orders->modify('empire') ];
      $newBalance = $empireObj->modify('storedEP');
      // destroy units
      foreach( $decodedOrders['destroy'] as $shipID )
      {
        $this->shipObjs->objByID[$shipID]->modify('damage', 100 );
        $this->shipObjs->objByID[$shipID]->modify('isDead', true );
        $countKills++;
      }
      // cripple units
      foreach( $decodedOrders['cripples'] as $shipID )
      {
        $this->shipObjs->objByID[$shipID]->modify('damage', 50 );
        $countCripples++;
      }
      // gift units
      foreach( $decodedOrders['gifts'] as $shipID=>$giftArray )
      {
        $this->shipObjs->objByID[ $giftArray['ship'] ]->modify('captureEmpire', $giftArray['empire'] );
        $countGifts++;
      }

      // Change Ship and Empire names
      foreach( $decodedOrders['names'] as $nameArray )
      {
        // skip if not renaming a ship
        if( ! empty($nameArray['ship']) )
        {
          $shipObj = $this->shipObjs->objByID[ $nameArray['ship'] ];
          // skip if the empire referenced is not the empire that the orders belong to
          if( $shipObj->modify('empire') == $orders->modify('empire') )
            $shipObj->modify('textName', $nameArray['name'] );
        }
        // skip if not renaming an empire
        // skip if the empire referenced is not the empire that the orders belong to
        if( ! empty($nameArray['empire']) && $nameArray['empire'] == $orders->modify('empire') )
          $empireObj->modify('textName', $nameArray['name'] );
      }

      unset( $empireObj );
    }

    // "kill" the ships of empires being deleted
    foreach( $this->empireObjs->objByID as $empID=>$empObj )
    {
      if( ! $empObj->modify('status') || $empObj->modify('status') != 'delete' )
        continue;
      // destroy units
      foreach( $this->shipObjs->objByID as $shipObj )
      {
        if( $shipObj->modify('empire') != $empID )
          continue;
        $shipObj->modify('damage', 100 );
        $shipObj->modify('isDead', true );
        $shipObj->update();
        $countKills++;
      }
    }


    $this->outputDisplay .= "<br>&nbsp;&bull; $countKills units destroyed, $countCripples units crippled, $countKills units gifted.\n";
  }

  ###
  # Draws the encounters for everyone and assigns them
  ###
  # Arguments:
  # - none
  # Returns:
  # - (array) the list of encounters, fit for feeding the output module
  ###
  public function drawEncounters ()
  {
    $this->outputDisplay .= "<br>&nbsp; <b>Drawing Encounters.</b>\n";

    global $SCENARIOS;

    $this->encounterObjs = new objList( "", $this->gameID, $this->turnNum );

    $borderList = array(); // format is ['empA','empB','amt']
    $output = array();

    $scenarioChance = 0; // this is a constant modifier to the scenario's chance of being drawn
    $scenarioIndex = array(); // format is: [ 'adjusted chance of being selected' => index_into_$SCENARIOS, ... ]
    $runningTotal = 0; // used to track the actual chance that a given scenario will come up
    $precision = 100; // amt to multiply the scenario chances, so as to allow for very small chances to draw encounters
    $count = 0;

    // Get a list of all the borders, who is involved, and how many to draw
    foreach( $this->empireObjs->objByID as $empireID=>$empireObj )
    {
      $empireBorders = $empireObj->bordersDecode();

      foreach( $empireBorders as $otherEmpireID=>$amt )
      {
        // check for a repeated empire in $empireBorders
        $isInList = false;
        foreach( $borderList as $value )
          if( ( $value['empA'] == $empireID || $value['empB'] == $empireID ) &&
              ( $value['empA'] == $otherEmpireID || $value['empB'] == $otherEmpireID ) )
          {
            $isInList = true;
            break;
          }
        if( $isInList )
          continue;
        // generate the list
        $borderList[] = array( 'empA'=>$empireID,'empB'=>$otherEmpireID,'amt'=>$amt );
      }
      unset($empireObj);
    }

    // determine the chance that an encounter will show up
    foreach( $SCENARIOS as $entry )
      $scenarioChance += $entry[5];
    $scenarioChance = floor( 100 / $scenarioChance * $precision );
    foreach( $SCENARIOS as $key=>$entry )
    {
      $runningTotal += $entry[5]*$scenarioChance;
      $scenarioIndex[ $runningTotal ] = $key;
    }

    // draw and assign encounters
    foreach( $borderList as $borderArray )
    {
      for( $i=0; $i<$borderArray['amt']; $i++ )
      {
        $chance = rand( 1, 100 * $precision );
        if( $this->DISPLAY_ODDS )
          $this->outputDisplay .= "<br>Random number to draw scenario: $chance\n";

        // find the index in $scenarioIndex where $chance is greater than the 
        //  previous entry, but less than or equal to the next entry
        foreach( $scenarioIndex as $index=>$scenario )
        {
          if( $this->DISPLAY_ODDS )
            $this->outputDisplay .= "<br>Previous random number less than or equal to $index, to select the scenario #$scenario.\n";

          // if $chance is less than or equal to $index, then it is already greater than the last $index we examined
          if( $chance <= $index )
          {
            // flip a coin to see who is player A for this
            $rand = rand(1,100);
            if( $this->DISPLAY_ODDS )
              $this->outputDisplay .= "<br>Random number to find defender: $rand. High numbers find the second player as defender.\n";

            if( $rand <= 50 )
            {
              $playerA = $borderArray['empA'];
              $playerB = $borderArray['empB'];
            }
            else
            {
              $playerA = $borderArray['empB'];
              $playerB = $borderArray['empA'];
            }
            $newEncounter = new Encounter( array(
                  'game'=>$this->gameID, 'playerA'=>$playerA, 'playerB'=>$playerB, 
                  'scenario'=>$scenario, 'status'=>Encounter::NEEDS_RESOLUTION, 'turn'=>$this->turnNum
                ) );
            $newEncounter->create();
            $this->encounterObjs->push($newEncounter);
            unset($newEncounter);
            $count++;
            break;
          }
        }
      }
    }

    $this->empireObjs->write();
    $this->encounterObjs->write();
    $this->outputDisplay .= "<br>&nbsp;&bull; $count encounters drawn.\n";
    return $this->encounterObjs;
  }

  ###
  # End of Turn processing
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  function endTurn ()
  {
    $this->outputDisplay .= "<h3>End of Turn {$this->turnNum}</h3>\n";

    $this->turnNum += 1;
    $this->currentYear = $this->gameObj->modify('gameStart') + floor( $this->turnNum / $this->gameObj->modify('campaignSpeed') );

    // kill the lookup lists
    $this->gameObj->__destruct();
    unset( $this->gameObj );
    $this->encounterObjs->__destruct();
    unset( $this->encounterObjs );
    $this->shipObjs->__destruct();
    unset( $this->shipObjs );
    $this->empireObjs->__destruct();
    unset( $this->empireObjs );
  }

  ###
  # Adds the Income value to the Stored EP value for each race
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  public function generateIncome ()
  {
    $this->outputDisplay .= "<br>&nbsp; <b>Generating Income.</b>\n";

    if( $this->gameObj->modify('currentSubTurn') > 0 ) // don't do anything if we cannot add income this turn
    {
      $this->outputDisplay .= "<br>&nbsp; &bull; Income is not added during this sub-turn.\n";
      return false;
    }

    foreach( $this->empireObjs->objByID as $empireObj )
    {
      $income = $empireObj->modify( 'income' );
      $oldBalance = $empireObj->modify( 'storedEP' );
      $empireObj->modify( 'storedEP', $oldBalance+$income );
    }
  }

  ###
  # Builds/converts/repairs units
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  public function makeBuilds ()
  {
    $this->outputDisplay .= "<br>&nbsp; <b>Building, Converting, Refitting, or Repairing Units.</b>\n";
    $countBuilds = 0;
    $countConversions = 0;
    $countRepairs = 0;

    if( $this->gameObj->modify('currentSubTurn') > 0 ) // don't do anything if we cannot do builds this turn
    {
      $this->outputDisplay .= "<br>&nbsp; &bull; Shipyard activity cannot be performed during this sub-turn.\n";
      return false;
    }

    foreach( $this->ordersObjs->objByID as $orders )
    {
      if( ! isset( $this->empireObjs->objByID[ $orders->modify('empire') ] ) )
      {
        $this->outputDisplay .= "<br>&nbsp; - Error in orders for empire #".$orders->modify('empire').". Empire does not exist in database.\n";
        continue;
      }
      $decodedOrders = $orders->decodeOrders();
      $empireObj = $this->empireObjs->objByID[ $orders->modify('empire') ];
      $newBalance = $empireObj->modify('storedEP');
      // perform the unit building
      foreach( $decodedOrders['builds'] as $data )
      {
        $newShipID = $this->_performNewShipType( $data['ship'], $newBalance, $empireObj );
        if( $newShipID !== false )
        {
          // deduct the cost of building the unit
          $newBalance -= $this->shipObjs->objByID[$newShipID]->specs['BPV'];
          // name the ship if possible
          if( ! empty($data['name']) )
          {
            $this->shipObjs->objByID[$newShipID]->modify( 'textName', $data['name'] );
            $this->shipObjs->objByID[$newShipID]->update();
          }
        }
        $countBuilds++;
      }
      // perform unit conversions/refits
      foreach( $decodedOrders['conversions'] as $convertArray )
      {
        $newDesignID = $convertArray['design'];
        $oldShipID = $convertArray['ship'];
        $oldShipCost = $this->shipObjs->objByID[$oldShipID]->specs['BPV'];
        $fakeBalance = $newBalance+$oldShipCost;
        $newShipID = $this->_performNewShipType( $newDesignID, $fakeBalance, $empireObj, $oldShipID );
        if( $newShipID !== false )
        {
          // subtract the difference in cost of the new ship and the old one
          $newBalance -= abs( $this->shipObjs->objByID[$newShipID]->specs['BPV'] - $oldShipCost );
          // remove the old ship
//          $this->shipObjs->objByID[$oldShipID]->destroy();
//          unset( $this->shipObjs->objByID[$oldShipID] );
          // make the new unit unable to be used this next turn
          $this->shipObjs->objByID[$newShipID]->modify('status', Ship::RESERVE);
          $this->shipObjs->objByID[$newShipID]->update();
          $countConversions++;
        }
      }
      // perform the unit repairs
      foreach( $decodedOrders['repairs'] as $designID )
      {
        $this->shipObjs->objByID[$designID]->modify( 'damage', 0 );
        $this->shipObjs->objByID[$designID]->update();
        $countRepairs++;
      }

      $empireObj->modify('storedEP', $newBalance);
    }
    unset( $orderObjs, $empireObj );
    $this->outputDisplay .= "<br>&nbsp;&bull; $countBuilds units built, $countConversions units converted/refit, $countRepairs units repaired.\n";
  }

  ###
  # Does all of the post-order, pre-encounter checks
  ###
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # Returns:
  # - (bool) True for no errors. False for errors
  # - (string) A list of the errors encounters
  ###
  public function performPostOrderChecks( &$inputFeed )
  {
    list( $result, $errorString ) = $this->checkBidErrors($inputFeed);

    return array( $result, $errorString );
  }

  ###
  # Does all of the post-encounter, pre-turn-cycle checks
  ###
  # Arguments:
  # - (array) An objList instance of orders given for this turn
  # Returns:
  # - (bool) True for no errors. False for errors
  # - (string) A list of the errors encounters
  ###
  public function performPostEncounterChecks( &$inputFeed )
  {
    list( $result, $errorString ) = $this->checkEncounterErrors($inputFeed);

    return array( $result, $errorString );
  }

  ###
  # Assigns the rewards or penalties for the encounters
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  public function rewardResults ()
  {
    $this->outputDisplay .= "<br>&nbsp; <b>Rewarding empires for victory or defeat.</b>\n";
    $count = 0;
    $dropCount = 0;
    $resultRegex = '/^([a-zA-Z]+)(\+?\-?\d+)?/';

    foreach( $this->encounterObjs->objByID as $encounter )
    {
      $playerAID = $encounter->modify('playerA');
      $playerBID = $encounter->modify('playerB');
      $defenderString = "";
      $defenderNum = 0;
      $attackerString = "";
      $attackerNum = 0;

      // determine if we need to apply the reward or the penalty
      if( $encounter->modify('status') == Encounter::NEEDS_RESOLUTION || $encounter->modify('status') == Encounter::OVERWHELMING_FORCE )
      {
        $this->outputDisplay .= "<br>&nbsp;&bull; Encounter with ID# {$encounter->modify('id')} was not ever resolved.\n";
        $dropCount++;
      }
      else if( $encounter->modify('status') == Encounter::PLAYER_A_VICTORY )
      {
        // defender wins, and gets index #2
        $defenderString = $GLOBALS['SCENARIOS'][ $encounter->modify('scenario') ][2];
        $attackerString = "";
      }
      else if( $encounter->modify('status') == Encounter::PLAYER_A_DEFEATED )
      {
        // attacker wins and gets index #4, the defender gets index #3
        $defenderString = $GLOBALS['SCENARIOS'][ $encounter->modify('scenario') ][3];
        $attackerString = $GLOBALS['SCENARIOS'][ $encounter->modify('scenario') ][4];
      }
      else if( $encounter->modify('status') == Encounter::APPLY_NO_RESULT )
      {
        $dropCount++;
      }
      // separate the reward/penalty string into it's constituant parts
      // set up the values for the initial result
      if( $defenderString && preg_match( $resultRegex, $defenderString, $matches ) )
      {
        if( isset($matches[2]) )
          $defenderNum = intval( $matches[2] );
        $defenderString = strtolower($matches[1]);
      }

      // set up the values for the attacker's result
      if( $attackerString && preg_match( $resultRegex, $attackerString, $matches ) )
      {
        if( isset($matches[2]) )
          $attackerNum = intval( $matches[2] );
        $attackerString = strtolower($matches[1]);
      }

      $this->_performReward( $playerAID, $playerBID, $defenderString, $defenderNum );
      $this->_performReward( $playerBID, $playerAID, $attackerString, $attackerNum );
      $count++;
    }
    $this->outputDisplay .= "<br>&nbsp;&bull; $count rewards given.\n";
    $this->outputDisplay .= "<br>&nbsp;&bull; $dropCount encounters dropped without resolution.\n";

    // remove borders with deleted empires
    foreach( $this->empireObjs->objByID as $empID=>$empObj )
    {
      if( $empObj->modify('status') != 'delete' )
        continue;
      // get the borders shared with this deleted empire
      $borders = $empObj->bordersDecode();
      // iterate through the undeleted empires and remove traces of this deleted empire
      foreach( $borders as $otherID=>$numBorders )
      {
        $this->empireObjs->objByID[$otherID]->bordersChange( $empID, $numBorders );
        $this->empireObjs->objByID[$otherID]->bordersKill( $empID ); // attempt to remove the border entry
        $this->empireObjs->objByID[$otherID]->update();
      }
    }
  }

  ###
  # Sets the turn status of the game. Used to track which command the moderator should execute
  ###
  # Arguments:
  # - (int) A TURN_SECTION_XXX constant from the game object
  # Returns:
  # - none
  ###
  public function setTurnStatus( $status )
  {
    $this->gameObj->modify( 'turnSection', $status );
    $this->gameObj->update();
  }

  ###
  # Start of Turn processing
  ###
  # Arguments:
  # - none
  # Returns:
  # - none
  ###
  function startTurn ()
  {
    $this->outputDisplay .= "<h3>Start of Turn $this->turnNum</h3>\n";

    $database = gameDB::giveme(true);

    if( ! $database->blankTurn( $this->gameID, $this->turnNum ) )
      die( "\nError in TurnProcess->newTurn(), emptying turn '{$this->turnNum}'.\n".$database->error_string );

    // advance the objects into the new turn
    if( ! $database->forwardTurn( $this->gameID, $this->turnNum ) )
      die( "\nError in TurnProcess->newTurn(), populating turn '{$this->turnNum}'.\n".$database->error_string );

    // re-load the lookup lists
    $this->gameObj = new Game( $this->gameID );
    $this->gameObj->read();
    $this->empireObjs = new objList( "empire", $this->gameID, $this->turnNum );
    $this->encounterObjs = new objList( "encounter", $this->gameID, $this->turnNum );
    $this->shipObjs = new objList( "ship", $this->gameID, $this->turnNum );

    // set all empires to "not ready to advance"
    foreach( $this->empireObjs->objByID as $empObj )
      $empObj->modify('advance', false);

  }

}
?>
