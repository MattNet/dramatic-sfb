#!/usr/bin/php -q
<?php
###
# Attempts to perform a standard SIDCORS resolution
###
# The input is a file in the format of:
# EMPIRE DESIGNATION
# EMPIRE DESIGNATION CRIPPLED
# ...
# [blank line denotes change of player]
# EMPIRE DESIGNATION
# ...
###
# The contents of the lists of priorities on how to be damaged
# - "hardest to damage"
# - "easiest to damage"
# - "is damaged"
# - "is mauler"
# - "is not mauler"
# - "is scout"
# - "is not scout"
# - "is undamaged"
# - "least bpv" - does not consider crippled BPV in figuring least BPV
# - "least carrier"
# - "least command rating"
# - "most bpv" - does not consider crippled BPV in figuring most BPV
# - "most carrier"
# - "most command rating"
###
# Outputs
# - (string) a description of how the battle progressed
###

if( ! isset($argv[1]) )
{
  echo "\nAttempts to perform a SIDCORS resolution of a battle.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." [File of Fleets]\n\n";
  echo "  The input file is in the format of:\nEMPIRE\t DESIGNATION\nEMPIRE\t DESIGNATION\t CRIPPLED\n...\n[blank line denotes change of player]\nEMPIRE\t DESIGNATION\nEMPIRE\t DESIGNATION\t CRIPPLED\n...\n\n";
  exit(1);
}

$CARRY_DAMAGE = true; // set to true to carry over damage from turn to turn. False to drop any extra damage each turn
$CRIPPLED_ATTACK_CONSTANT = 0.5; // amount to multiply the BPV to get the crippled attack value
$CRIPPLED_DEFENSE_CONSTANT = 0.5; // amount to multiply the BPV to get the crippled defense value
$GENERAL_UNIT_FILE = "./general_units.csv"; // The .CSV file to pull the data on General Units from
$SCOUT_ATTACK_CONSTANT = 0.7; // amount to multiply the BPV of scouts to get the attack value
$BIR_TABLE = array(
               3=>array( 0.15,0.20,0.20,0.25,0.25,0.30 ),
               4=>array( 0.20,0.20,0.25,0.25,0.30,0.30 ),
               5=>array( 0.20,0.25,0.25,0.30,0.30,0.35 ),
               6=>array( 0.25,0.25,0.30,0.30,0.35,0.35 ),
               7=>array( 0.25,0.30,0.30,0.35,0.35,0.40 )
             ); // combat coefficient table. First key is the actual BIR, second key gives the combat value modifier
// Priority list for dealing damage
$DMG_PRIORITY_DEALING = array(
    "is scout",
    "is mauler",
    "most command rating",
    "most carrier",
    "is damaged",
    "hardest to damage"
  );
// Priority list for taking damage
$DMG_PRIORITY_RECEIVE = array(
    "is not scout",
    "is not mauler",
    "is undamaged",
    "least carrier",
    "least command rating",
    "least bpv"
  );

$carryOverDamage = array(); // The amount of damage that carries from turn-to-turn
$crippled = array(); // list holding $shipObjs keys of ships that are crippled
$csvData = array(); // a holder for the General Units data from the CSV file
$errors = ""; // error string from various subroutines
$fleetFile = $argv[1];
$keepGoing = true; // flag for when to stop running the turns
$output = ""; // final output string
$player = 0; // key to $playersShips. player to reference
$playersShips = array(); // lookup for who's units are who's. format is [0][]=[$shipObjs key, plr #1], [1][]=[$shipObjs key, plr #2]
$roundNum = 0; // the current round of accounting (each round is ~ 3 SFB turns)
$shipObjs = array(); // list of ship objects

require_once( dirname(__FILE__) . "/../Login/Login_config.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );
// pull in the CSV file
$handle = fopen($GENERAL_UNIT_FILE, "r");
if(empty($handle) === false)
{
  while(($data = fgetcsv($handle)) !== false)
    $csvData[] = $data;
  fclose($handle);
}

$carryOverDamage[$player] = 0; //  initial setup of $carryOverDamage
$playersShips[$player] = array(); // initial setup of $playersShips

# Load up the fleet file
$fleetFileString = file( $fleetFile ); // store the file contents in this variable

# iterate through the fleet file, and stuff the ships therein into $shipObjects
foreach( $fleetFileString as $line )
{
  $empire = "";
  $designation = "";
  $isCrippled = true;
  $line = trim($line);

  // if it is an empty line, change players
  if( empty($line) )
  {
    $player++;
    $playersShips[$player] = array();
    $carryOverDamage[$player] = 0;
    continue;
  }

  // pull out the ship info from the line of the file
  $firstSpace = strpos( $line, " ", 2 );
  $secondSpace = stripos( $line, " cripple" );
  if( $secondSpace === false)
  {
    $secondSpace = strlen($line);
    $isCrippled = false;
  }
  $empire = substr( $line, 0, $firstSpace );
  $designation = substr( $line, $firstSpace, ($secondSpace-$firstSpace) );
  $empire = trim($empire);
  $designation = trim($designation);

  ### Include code to import General units from a nearby CSV, and not to go to the DB for them
  if( $empire != "General" ) // if a ship from the database
  {
    // pull out the shipdesign object from the database
    $shipKey = count( $shipObjs ); // the key for the new ship object
    $shipObjs[$shipKey] = new shipDesign();
    $shipObjs[$shipKey]->modify( 'designator', $designation );
    $shipObjs[$shipKey]->modify( 'empire', $empire );
    $shipObjs[$shipKey]->getID( 'designator', 'empire' );
    $shipObjs[$shipKey]->read();
    $shipObjs[$shipKey]->modify( 'autowrite', false );
  }
  else // if a "general unit" ship, pull from the CSV file
  {
    // find the entry in the CSV file for this unit
    $csvKey = 0;
    foreach( $csvData as $key=>$csvEntry )
    {
      if( $csvEntry[0] == $designation )
        $csvKey = $key;
    }
    // pull out the shipdesign object from the CSV
    $shipKey = count( $shipObjs ); // the key for the new ship object
    $shipObjs[$shipKey] = new shipDesign();
    $shipObjs[$shipKey]->modify( 'autowrite', false );
    $shipObjs[$shipKey]->modify( 'designator', $designation );
    $shipObjs[$shipKey]->modify( 'empire', "General" );
    $shipObjs[$shipKey]->modify( 'BPV', intval($csvData[$csvKey][1]) );
    $shipObjs[$shipKey]->modify( 'commandRating', intval($csvData[$csvKey][2]) );
    $shipObjs[$shipKey]->modify( 'carrier', intval($csvData[$csvKey][3]) );
    $shipObjs[$shipKey]->modify( 'sidcorAtk', intval($csvData[$csvKey][4]) );
    $shipObjs[$shipKey]->modify( 'sidcorDmg', intval($csvData[$csvKey][5]) );
    $shipObjs[$shipKey]->modify( 'sidcorCAtk', intval($csvData[$csvKey][6]) );
    $shipObjs[$shipKey]->modify( 'sidcorCDmg', intval($csvData[$csvKey][7]) );
    $shipObjs[$shipKey]->modify( 'sidcorEW', intval($csvData[$csvKey][8]) );
    $shipObjs[$shipKey]->modify( 'switches', trim($csvData[$csvKey][9]) );
  }
  if( $isCrippled )
    $crippled[$shipKey] = $shipKey;

  $playersShips[$player][] = $shipKey; // populate the lookup
}

# run through each round
while( $keepGoing )
{
  $AtkVals = array(); // the attack value of the players
  $bir = 5; // the Battle Intensity Rating
  $combatCoefficient = array(); // the per-player BIR value against the attack value
  $effAtkVals = array(); // The effective attack values, per player, after BIR % is figured
  $effMauler = array(); // The effective attack values of maulers, per player, after BIR % is figured
  $EWVals = array();
  $maulers = array(); // track mauler attack values (e.g. always direct-damage sources)
  $maulersNotGoodEnough = array(); // track maulers effective value for the case where they can't kill something on their own
  $roundNum++;

  // determine the BIR
  $variableBIR = mt_rand(1,36);
  if( $variableBIR < 7)
      $bir -= 2;
  else if( $variableBIR < 13)
      $bir -= 1;
  else if( $variableBIR > 23)
      $bir += 1;
  else if( $variableBIR > 29)
      $bir += 1;

  // populate $AtkVals and $EWVals
  for( $playerKey=0; $playerKey<count($playersShips); $playerKey++ )
  {
    $AtkVals[$playerKey] = 0;
    $effAtkVals[$playerKey] = 0;
    $EWVals[$playerKey] = 0;
    $maulers[$playerKey] = 0;
    $maulersNotGoodEnough[$playerKey] = 0;

    foreach( $playersShips[$playerKey] as $key )
    {
      $attackValue = $shipObjs[$key]->modify('BPV'); // the attack value of this ship
      // if it is a scout
      if( $shipObjs[$key]->modify( 'sidcorEW' ) )
      {
        $attackValue *= $SCOUT_ATTACK_CONSTANT;
        // track the EW if not crippled
        if( ! isset($crippled[$key]) )
          $EWVals[$playerKey] += $shipObjs[$key]->modify( 'sidcorEW' );
      }
      // if it is crippled
      if( isset($crippled[$key]) )
        $attackValue *= $CRIPPLED_ATTACK_CONSTANT;
      // if it is a mauler, put it in the mauler variable
      if( stripos($shipObjs[$key]->modify('switches'), 'mauler') !== false && ! isset($crippled[$key]) )
      {
        $maulers[$playerKey] += round( $attackValue );
        $maulersNotGoodEnough[$playerKey] += round($attackValue);
      }
      else
        // if it is not a mauler, put it in the normal-ship variable
        $AtkVals[$playerKey] += round( $attackValue );
    }
  }

  // populate $effAtkVals (need $EWVals to be fully populated)
  for( $playerKey=0; $playerKey<count($EWVals); $playerKey++ )
  {
    $notMyEW = 0; // holds the total of EW that is against this player
    // count up the EW against this player
    for( $ewKey=0; $ewKey<count($EWVals); $ewKey++ )
      if( $ewKey != $playerKey )
        $notMyEW += $EWVals[$ewKey];
    $birRoll = mt_rand(1,6);
    // if the EW against this player is greater than their own EW, subtract the difference from the roll
    if( $notMyEW > $EWVals[$playerKey] )
      $birRoll -= ($notMyEW + $EWVals[$playerKey]);
    // keep the BIR roll from falling off the chart
    if( $birRoll < 1 )
      $birRoll = 1;
    // effective attack value is [the fleet attack value] x [the value from the coefficient table]
    $effAtkVals[$playerKey] = round( $AtkVals[$playerKey] * $BIR_TABLE[$bir][($birRoll-1)] );
    $maulersNotGoodEnough[$playerKey] = round( $maulersNotGoodEnough[$playerKey] * $BIR_TABLE[$bir][($birRoll-1)] );
  }

# - print the SIDCORS information: ship SIDCOR values, fleet sidcore values, effective BIR
  $output .= "\nROUND $roundNum\n";
  $output .= "Actual BIR: $bir\n";
  for( $playerKey=0; $playerKey<count($playersShips); $playerKey++ )
  {
    $output .= "\nPlayer #".($playerKey+1)."\n";
    $output .= "Fleet attack value: {$AtkVals[$playerKey]}\n";
    if( $maulers[$playerKey] )
      $output .= "Attack value from Maulers: {$maulers[$playerKey]} or +{$maulersNotGoodEnough[$playerKey]} to fleet after BIR\n";
    $output .= "EW shift: {$EWVals[$playerKey]}\n";
    $output .= "Total effective attack value (after BIR): {$effAtkVals[$playerKey]}";
    if( $carryOverDamage[$playerKey] )
      $output .= "+{$carryOverDamage[$playerKey]}";
    $output .= " / {$maulers[$playerKey]}\n\n";
    foreach( $playersShips[$playerKey] as $key )
    {
      $attackValue = $shipObjs[$key]->modify('BPV'); // the attack value of this ship

      $output .= $shipObjs[$key]->modify('empire')." ".$shipObjs[$key]->modify('designator');
      if( $shipObjs[$key]->modify( 'sidcorEW' ) ) // if the ship is a scout
      {
        $output .= " (Scout)";
        $attackValue *= $SCOUT_ATTACK_CONSTANT;
      }

      if( stripos($shipObjs[$key]->modify('switches'), 'mauler') !== false ) // if this ship is a mauler
        $output .= " (Mauler)";
      if( isset($crippled[$key]) ) // if this ship is in the $crippled lookup
      {
        $output .= " (Crippled)";
        $attackValue *= $CRIPPLED_ATTACK_CONSTANT;
      }
      $output .= ": ".floor($attackValue);
      if( $shipObjs[$key]->modify( 'sidcorEW' ) ) // if the ship is a scout
        $output .= "-".floor($attackValue/$SCOUT_ATTACK_CONSTANT);
      $output .= " attack value.\n";
    }
  }
  $output .= "\n"; // newline to separate the info from the battle


# do mauler damage against each player
  foreach( $maulers as $playerKey=>$maulerDMG )
  {
    if( $maulerDMG == 0 )
      continue;
    $targetPlayer = 0; // the $playersShips key of the player to be damaged

    // find the player to be damaged
    for( $tempPlayer=0; $tempPlayer<count($playersShips); $tempPlayer++ )
    {
      if( $tempPlayer == $playerKey )
        continue;
      $targetPlayer = $tempPlayer;
    }

  # determine which ship is highest on the priority list
    $targetKey = determineTarget( $DMG_PRIORITY_DEALING, $maulerDMG, $playersShips[$targetPlayer] );

    if( $targetKey === false ) // if we failed to find a target, skip the rest of this step
    {
      $effAtkVals[$playerKey] += $maulersNotGoodEnough[$playerKey];
      continue;
    }
    $beenMauled = $shipObjs[$targetKey]->modify('designator'); // capture the designator here before being removed by dealDamage()
    list($result) = dealDamage( $targetKey, $maulerDMG );

    if( $result == 0 )
    {
      $effAtkVals[$playerKey] += $maulersNotGoodEnough[$playerKey];
      continue;
    }

    $output .= "$beenMauled was mauled and ";
    if( $result == 1 )
      $output .= "crippled.\n";
    if( $result == 2 )
    {
      $output .= "destroyed.\n";
      unset($playersShips[$targetPlayer][ array_search($targetKey, $playersShips[$targetPlayer]) ]);
    }

  # check for mauler breakdown
    if( mt_rand(1,36) < 12 ) // 1/3 chance of breaking down
    {
      $targetMauler = ""; // mauler to break down
      // find a mauler to breakdown
      foreach( $playersShips[$playerKey] as $objKey ) // go through the target player's ships
        if( stripos($shipObjs[$objKey]->modify('switches'), 'mauler') !== false ) // if it is a mauler
          $targetMauler = $objKey; // use this mauler
      $output .= "{$shipObjs[$targetMauler]->modify('empire')} mauler {$shipObjs[$targetMauler]->modify('designator')} has broken down.\n";
      $crippled[$targetMauler] = $targetMauler;
    }
  }

# do regular damage against each player
  foreach( $effAtkVals as $playerKey=>$attackDMG )
  {
    $attackDMG += $carryOverDamage[$playerKey];
    if( $attackDMG == 0 )
      continue;
    $targetPlayer = 0; // the $playersShips key of the player to be damaged
    $attackDmgLeft = $attackDMG; // the amount of damage left after damaging things

    // find the player to be damaged
    for( $tempPlayer=0; $tempPlayer<count($playersShips); $tempPlayer++ )
    {
      if( $tempPlayer == $playerKey )
        continue;
      $targetPlayer = $tempPlayer;
    }

    // Assign as much damage as possible to the target
    while( $attackDmgLeft )
    {
      $dmgBefore = 0; // how much damage was left before being assigned to the ship
      // determine which ship is highest on the priority list
      $targetKey = determineTarget( $DMG_PRIORITY_RECEIVE, $attackDMG, $playersShips[$targetPlayer] );
      if( $targetKey === false ) // if we failed to find a target, skip the rest of this step
      {
        if( $CARRY_DAMAGE )
          $carryOverDamage[$playerKey] = $attackDmgLeft;
        $attackDmgLeft = 0;
        continue;
      }
      $beenAttacked = $shipObjs[$targetKey]->modify('designator'); // capture the designator here before being removed by dealDamage()
      $dmgBefore = $attackDmgLeft;
      list($result, $attackDmgLeft) = dealDamage( $targetKey, $attackDmgLeft );

      if( $result == 0 ) // if we can't deal damage to this target, skip the rest of the step
      {
        if( $CARRY_DAMAGE )
          $carryOverDamage[$playerKey] = $attackDmgLeft;
        $attackDmgLeft = 0;
        continue;
      }

      $output .= "$beenAttacked was ";
      if( $result == 1 )
        $output .= "crippled for ".($dmgBefore-$attackDmgLeft)." damage.\n";
      if( $result == 2 )
      {
        $output .= "destroyed for ".($dmgBefore-$attackDmgLeft)." damage.\n";
        unset( $playersShips[$targetPlayer][ array_search($targetKey, $playersShips[$targetPlayer]) ] );
      }

    }
  }

  $output .= "\n";
  // determine when to stop
  foreach( $playersShips as $playerArray )
    if( count($playerArray) == 0 )
      $keepGoing = false;
  if( $roundNum > 9 )
    $keepGoing = false;
}

echo $output;

###
# Determines the target ship (by key into $shipObjs) that should be assigned damage next
###
# for each entry in the list, looks for a match.
# If one or more matches are found, then future entries winnow those matches down until one is found
# If no matches are found for that entry, then the entry is skipped
# If the end of the list is reached, picks one from the remaining viable targets
# At no time will the target picked be able to absorb more damage than is given as an input
###
# Inputs:
# (array) List of priorities to be damaged
# - "hardest to damage"
# - "easiest to damage"
# - "is damaged"
# - "is mauler"
# - "is not mauler"
# - "is scout"
# - "is not scout"
# - "is undamaged"
# - "least bpv" - does not consider crippled BPV in figuring least BPV
# - "least carrier"
# - "least command rating"
# - "most bpv" - does not consider crippled BPV in figuring most BPV
# - "most carrier"
# - "most command rating"
# (int) Amount of damage being dealt in this volley
# (array) list of ships to choose from. Typically a list of one player's ships
# (bool) Whether to just use the $playersShipList as given (true), or winnow the list down with the normal priority matching (default)
# Outputs:
# (int) Key of $shipObjs array of the ship to be targetted
###
function determineTarget( $priorityList, $damageDealt, $playersShipList, $skip=false )
{

  global $shipObjs, $crippled, $CRIPPLED_DEFENSE_CONSTANT;
  $targetShip = null;
  $priority = 0; // index to the highest $DMG_PRIORITY_DEALING that matches a ship
  $list = $playersShipList; // list of ships to scroll through for priority-matching. Gives key into $shipObjs

  if( ! $skip )
  {
    // while no target ship is known
    while( $targetShip === null )
    {
      if( ! empty($possibleTargets) )
        $list = $possibleTargets;
      $possibleTargets = array(); // empty this array, now that it is assigned to $list. Allows for re-assignment to this array

      $priorityList[$priority] = strtolower($priorityList[$priority]);

      // while we might have more to add to the $possibleTargets list
      $foundTarget = true;
      // determine which ship is highest on the priority list
      switch( $priorityList[$priority] )
      {
      case "hardest to damage": // find the highest BPV, include crippled status
        $runningTotal = 0; // BPV of least-BPV ship
        $runningKey = null; // key of least-BPV ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $BPV = $shipObjs[$objKey]->modify('BPV');
          if( isset($crippled[$objKey]) ) // if it is on the crippled list
            $BPV *= $CRIPPLED_DEFENSE_CONSTANT;
          if( $BPV > $runningTotal ) // if it is less than $runningTotal
          {
            // if it can be damaged and is not already in $possibleTargets
            if( $BPV < $damageDealt && ! in_array($objKey, $possibleTargets) )
            {
              $runningTotal = $BPV;
              $runningKey = $objKey;
            }
          }
        }
        if( isset($runningKey) ) // don't set $possibleTargets if we found nothing that matched
          $possibleTargets[] = $runningKey;
        break;
      case "easiest to damage": // find the lowest BPV, include crippled status
        $runningTotal = 1000; // BPV of least-BPV ship
        $runningKey = null; // key of least-BPV ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $BPV = $shipObjs[$objKey]->modify('BPV');
          if( isset($crippled[$objKey]) ) // if it is on the crippled list
            $BPV *= $CRIPPLED_DEFENSE_CONSTANT;
          if( $BPV < $runningTotal ) // if it is less than $runningTotal
          {
            // if it can be damaged and is not already in $possibleTargets
            if( $BPV < $damageDealt && ! in_array($objKey, $possibleTargets) )
            {
              $runningTotal = $BPV;
              $runningKey = $objKey;
            }
          }
        }
        if( isset($runningKey) ) // don't set $possibleTargets if we found nothing that matched
          $possibleTargets[] = $runningKey;
        break;
      case "is damaged": // if it is crippled
        foreach( $list as $objKey ) // go through the target player's ships
          // if it is on the crippled list and is not already in $possibleTargets
          if( isset($crippled[$objKey]) )
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "is mauler":
        foreach( $list as $objKey ) // go through the target player's ships
          if( stripos($shipObjs[$objKey]->modify('switches'), 'mauler') !== false ) // if it is a mauler
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "is not mauler":
        foreach( $list as $objKey ) // go through the target player's ships
          if( stripos($shipObjs[$objKey]->modify('switches'), 'mauler') === false ) // if it is not a mauler
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "is scout":
        foreach( $list as $objKey ) // go through the target player's ships
          if( $shipObjs[$objKey]->modify( 'sidcorEW' ) ) // if it is a scout
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "is not scout":
        foreach( $list as $objKey ) // go through the target player's ships
          if( $shipObjs[$objKey]->modify( 'sidcorEW' ) == 0 ) // if it is not a scout
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "is undamaged":
        foreach( $list as $objKey ) // go through the target player's ships
          if( ! isset($crippled[$objKey]) ) // if it is not on the crippled list
            $possibleTargets[] = $objKey; // add to list of possible targets
        break;
      case "least bpv": // find the lowest BPV
        $runningTotal = 1000; // BPV of least-BPV ship
        $runningKey = null; // key of least-BPV ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $BPV = $shipObjs[$objKey]->modify('BPV');
          if( $BPV < $runningTotal && ! in_array($objKey, $possibleTargets) ) // if it is less than $runningTotal
          {
            $runningTotal = $BPV;
            $runningKey = $objKey;
            $possibleTargets = array(); // empty the array because the others referenced a largest $capacity
          }
          if( $BPV == $runningTotal )
            $possibleTargets[] = $objKey;
        }
        break;
      case "least carrier":
        $runningTotal = 100; // capacity of least-carrier/PF/Bomber ship
        $runningKey = null; // key of least-capacity ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $capacity = $shipObjs[$objKey]->modify('carrier') +
                $shipObjs[$objKey]->modify('carrierHeavy') +
                $shipObjs[$objKey]->modify('carrierPFT') +
                $shipObjs[$objKey]->modify('carrierBomber') +
                $shipObjs[$objKey]->modify('carrierHvyBomber');
          if( $capacity < $runningTotal ) // if it is less than $runningTotal
          {
            $runningTotal = $capacity;
            $runningKey = $objKey;
            $possibleTargets = array(); // empty the array because the others referenced a largest $capacity
          }
          if( $capacity == $runningTotal )
            $possibleTargets[] = $objKey;
        }
        break;
      case "least command rating":
        $runningTotal = 100; // CR of least-CR ship
        $runningKey = null;; // key of least-CR ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $CR = intval($shipObjs[$objKey]->modify('commandRating'));
          if( $CR < $runningTotal ) // if it is less than $runningTotal
          {
            $runningTotal = $CR;
            $runningKey = $objKey;
            $possibleTargets = array(); // empty the array because the others referenced a largest $capacity
          }
          if( $CR == $runningTotal )
            $possibleTargets[] = $objKey;
        }
        break;
      case "most bpv": // find the largest BPV that is less than or equal to the damage dealt
        $runningTotal = 0; // BPV of largest-BPV ship
        $runningKey = null; // key of largest-BPV ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $BPV = $shipObjs[$objKey]->modify('BPV');
          if( $BPV > $runningTotal && ! in_array($objKey, $possibleTargets) ) // if it is greater than $runningTotal
          {
            $defense = $BPV;
            if( isset($crippled[$objKey]) ) // if it is crippled
              $defense *= $CRIPPLED_DEFENSE_CONSTANT;
            if( $defense < $damageDealt ) // if it can be damaged
            {
              $runningTotal = $BPV;
              $runningKey = $objKey;
            }
          }
        }
        if( isset($runningKey) ) // don't set $possibleTargets if we found nothing that matched
          $possibleTargets[] = $runningKey;
        break;
      case "most carrier":
        $runningTotal = 0; // capacity of most-carrier/PF/Bomber ship
        $runningKey = null; // key of largest-capacity ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $capacity = $shipObjs[$objKey]->modify('carrier') +
                $shipObjs[$objKey]->modify('carrierHeavy') +
                $shipObjs[$objKey]->modify('carrierPFT') +
                $shipObjs[$objKey]->modify('carrierBomber') +
                $shipObjs[$objKey]->modify('carrierHvyBomber');
          if( $capacity > $runningTotal && ! in_array($objKey, $possibleTargets) ) // if it is greater than $runningTotal
          {
            $defense = $shipObjs[$objKey]->modify('BPV');
            if( isset($crippled[$objKey]) ) // if it is crippled
              $defense *= $CRIPPLED_DEFENSE_CONSTANT;
            if( $defense < $damageDealt ) // if it can be damaged
            {
              $runningTotal = $capacity;
              $runningKey = $objKey;
            }
          }
        }
        if( isset($runningKey) ) // don't set $possibleTargets if we found nothing that matched
          $possibleTargets[] = $runningKey;
        break;
      case "most command rating":
        $runningTotal = 0; // CR of largest-CR ship
        $runningKey = null; // key of largest-CR ship
        foreach( $list as $objKey ) // go through the target player's ships
        {
          $CR = $shipObjs[$objKey]->modify('commandRating');
          if( $CR > $runningTotal && ! in_array($objKey, $possibleTargets) ) // if it is greater than $runningTotal
          {
            $defense = $shipObjs[$objKey]->modify('BPV');
            if( isset($crippled[$objKey]) ) // if it is crippled
              $defense *= $CRIPPLED_DEFENSE_CONSTANT;
            if( $defense < $damageDealt ) // if it can be damaged
            {
              $runningTotal = $CR;
              $runningKey = $objKey;
            }
          }
        }
        if( isset($runningKey) ) // don't set $possibleTargets if we found nothing that matched
          $possibleTargets[] = $runningKey;
        break;
      }

      // remove those entries that cannot be damaged by this attack
      foreach( $possibleTargets as $localKey=>$shipKey )
      {
        $defense = intval( $shipObjs[$shipKey]->modify('BPV') );
        if( isset($crippled[$shipKey]) ) // if it is crippled
          $defense *= $CRIPPLED_DEFENSE_CONSTANT;
        if( floor($defense) > $damageDealt ) // if it can not be damaged
          unset( $possibleTargets[$localKey] );
      }

      if( count($possibleTargets) == 1 ) // if there is only one target
      {
        $possibleTargets = array_values($possibleTargets);
        $targetShip = $possibleTargets[0]; // assign $targetShip as that 1 ship
      }

      $priority++; // go one more entry down the priority list
      if( ! isset($priorityList[$priority]) ) // if there is no more to the priority list
      {
        if( isset($list[0]) )
          $targetShip = $list[0]; // assign $targetShip as the first ship found in the previous set of $possibleTargets
        if( ! isset($targetShip) ) // determine a target without priority matching, so we can find something that can be damaged at all
          $targetShip = determineTarget( $priorityList, $damageDealt, $playersShipList, true );
      }
    }
  }
  else
  {
    // remove those entries that cannot be damaged by this attack
    foreach( $list as $key )
    {
      $defense = intval( $shipObjs[$key]->modify('BPV') );
      if( isset($crippled[$key]) ) // if it is crippled
        $defense *= $CRIPPLED_DEFENSE_CONSTANT;
      if( floor($defense) >= $damageDealt ) // if it can not be damaged
        unset( $list[$key] );
    }

    if( count($list) == 0 ) // if there is no targets
      $targetShip = false; // give $targetShip an error value
    if( count($list) >= 1 ) // if there is one or more targets
    {
      $list = array_values($list);
      $targetShip = $list[0]; // assign $targetShip to the first (or only) ship
    }
  }

  return $targetShip;
}

###
# Cripples or destroys the indicated ship, if possible
###
# Inputs:
# (int) Key to $shipObjs to indicate which ship to damage
# (int) Amount of damage being dealt in this volley
# Outputs:
# (int) 0 for failure, 1 for ship was crippled, 2 for ship was destroyed
###
function dealDamage( $targetShip, $dmgAmt )
{
  global $shipObjs, $crippled, $playersShips, $CRIPPLED_DEFENSE_CONSTANT;
  $output = 1;
  $amtLeft = $dmgAmt;
  // note that scouts use full BPV for damage purposes, not the (lower) attack damage
  $defense = intval($shipObjs[$targetShip]->modify('BPV'));

  if( isset($crippled[$targetShip]) ) // if it is crippled
  {
    $defense *= $CRIPPLED_DEFENSE_CONSTANT;
    $defense = floor($defense);
    $output = 2; // conditionally set to destroyed
  }

  if( $defense > $dmgAmt ) // if it can not be damaged
    $output = 0;
  else
    $amtLeft = $dmgAmt-$defense;

  if( $output == 1 ) // cripple the ship, if recieving crippling damage
    $crippled[$targetShip] = $targetShip;
  if( $output == 2 ) // destroy the ship, if recieving destroying damage
    unset( $shipObjs[$targetShip], $crippled[$targetShip] );

  return array($output, $amtLeft);
}

?>
