#!/usr/bin/php -q
<?php
###
# Attempts to create a carrier group from certain parameters
###
# switches:
#  extra escort
#  heavy escorts (e.g. used DDEs instead of FFEs)
#  small escorts (all escorts are SC-4)
###
# Outputs
# - (string) a description of the carrier group(s) that fit
###

// normal escort group:
//   1x SC-4 escort
//   remaining escorts are of same SC as carrier
//   1x escort for SC-4 carriers
//   2x escorts for SC-3 carriers
//   3x escorts for SC-2 carriers

$CARRIER_PERCENTAGE = 0.667; // percentage of the total BPV that is set aside for the carrier
$EXTRA_ESCORT = false; // adds an escort to the group
$HEAVY_ESCORTS = false; // Uses higher-priced escorts
$SHOW_DB_ERRORS = true;
$SHOW_REJECTS = false; // set to true to show which escorts were rejected and why
$SMALL_ESCORTS = false; // forces all escorts to be SC-4
$YIS_THRESHOLD = 10; // number of year difference between carrier and escort before escort is thrown out

if( ! isset($argv[2]) )
{
  echo "\nAttempts to create a carrier group from certain parameters.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]."  EMPIRE  YEAR  BPV_CAP [SWITCHES]\n\n";
  echo "Allowed switches are: (case insensative)\n";
  echo "-extra  Adds an extra escort to the carrier group.\n";
  echo "-heavy  Forces the escorts to be of heavier hulls but keep the size-class selection.\n";
  echo "-rejects  Shows why certain escorts were rejected (if any).\n";
  echo "-small  Forces all of the escorts to be of the smallest size-class.\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../objects/gameDB.php" );
require_once( dirname(__FILE__) . "/../objects/shipdesign.php" );

reassignDefaults(); // reassign the default behavior based on command-line switches

$bpvCap = intval($argv[3]); // the year to draw from
$carrierBPV = floor($bpvCap*$CARRIER_PERCENTAGE); // the maximum BPV of the carrier
$database = gameDB::giveme();
$empire = ucfirst($argv[1]); // the empire to draw from
$hasSCthree = false; // flag to determine if all of the escorts are SC 4 or not
$rejectReason = ""; // reason for an escort to be rejected
$output = "";
$year = intval($argv[2]); // the year to draw from

// if there is an extra escort, reduce the BPV of the carrier to look for
if( $EXTRA_ESCORT )
  $CARRIER_PERCENTAGE *= 0.75; 

// Get a list of the carrier(s)
  $query = "select BPV,designator,carrier,sizeClass,yearInService from ".ShipDesign::table." where ";
  $query .= "empire='$empire' and yearInService<=$year and (obsolete>$year or obsolete=0) and ";
  $query .= "BPV<=$carrierBPV and carrier>=6";

  $result = $database->genquery( $query, $carriers);
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get carrier: ".$database->error_string."\n";
    else
      echo "Could not get carrier.\n";
    exit(1);
  }

// Get a list of the escorts
  $query = "select BPV,designator,sizeClass,carrier,yearInService from ".ShipDesign::table." where empire='$empire'";
  $query .= " and switches like '%escort%' and yearInService<=$year and (obsolete>$year or obsolete=0) and ";
  $query .= "designator not like 'FCR%'";
  if( $SMALL_ESCORTS )
    $query .= " and sizeClass=4";

  $result = $database->genquery( $query, $escorts);
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get escorts: ".$database->error_string."\n";
    else
      echo "Could not get escorts.\n";
    exit(1);
  }

  // if there are no carriers available
  if( count($carriers) == 0 )
  {
    echo "No $empire carriers are available in Y$year.\n".$output;
    exit(0);
  }
  // if there are no escorts available
  if( count($escorts) == 0 )
  {
    echo "No $empire escorts are available in Y$year.\n".$output;
    exit(0);
  }

  // if there are no SC 3 escorts, set $SMALL_ESCORTS to true
  foreach( $escorts as $escortData )
  {
    if( $escortData['sizeClass'] == 3 )
      $hasSCthree = true;
  }
  if( ! $hasSCthree )
    $SMALL_ESCORTS = true; // this is effectively true, because of available escorts

  // summarize what was found in the DB
  $output .= "There are ".count($escorts)." $empire escorts found for Y$year";
  if( $SMALL_ESCORTS)
    $output .= " that were used for this assembly";
  $output .= ".\n\n";

  foreach( $carriers as $carrierKey=>$carrierData )
  {
    $escortArray = array(); // keeps track of the keys into $escorts that was chosen
    $remainingBPV = $bpvCap-$carrierData['BPV'];
    $numIterations = 5 - intval($carrierData['sizeClass']);
    $bpvTotal = 0;
    $fighterTotal = 0;
    if( $EXTRA_ESCORT )
      $numIterations += 1;
    for( $i=0; $i<$numIterations; $i++ )
    {
      list($result, $reason) = getEscort( $carrierData['sizeClass'], $remainingBPV, ($i+1), $numIterations, $carrierData['yearInService'] );
var_dump($result);
var_dump($carrierData['designator']);

      if( $result === false )
      {
        $i=10; // stop iterating through the escorts
        unset( $carriers[$carrierKey], $escortArray );
      }
      else
      {
        $escortArray[] = $result;
        $remainingBPV -= $escorts[$result]['BPV'];
      }
      $rejectReason .= $carrierData['designator'].":\n$reason\n";
    }
    unset( $i, $result, $reason ); // these variables only exist in the above loop
    if( isset($carriers[$carrierKey]) )
    {
      $output .= "$empire {$carrierData['designator']} ({$carrierData['BPV']} BPV)\n"; // say something about the carrier
      $bpvTotal = $carrierData['BPV'];
      $fighterTotal = $carrierData['carrier'];
      foreach( $escortArray as $escortKey )
      {
        $output .= "{$escorts[$escortKey]['designator']} ({$escorts[$escortKey]['BPV']} BPV)\n"; // say something about the escort
        $bpvTotal += $escorts[$escortKey]['BPV'];
        $fighterTotal += $escorts[$escortKey]['carrier'];
      }
      if( $SHOW_REJECTS )
        $output .= $rejectReason;
      if( $remainingBPV < 0 )
      {
        $output .= "$empire {$carrierData['designator']} carrier group is too expensive. (".($bpvCap+abs($remainingBPV))." out of $bpvCap)\n\n";
        unset( $carriers[$carrierKey], $escortArray );
      }
      else
      {
        $output .= "$fighterTotal total fighters, $bpvTotal total BPV\n\n";
      }
    }
    else
    {
      $output .= $rejectReason;
    }
    $rejectReason = ""; // the end of the current carrier, reset this string
  }

echo $output;

###
# Determines the escort to add to the group
###
# Inputs:
# (int) The size class of the carrier
# (int) The BPV allowed for this escort
# (int) Which escort this is, numbered from smallest to largest. 1-based
# (int) How many escorts will there be
# (int) When the carrier was brought into service
# Outputs:
# (int) the key to the $escorts array that indicates the ecort chosen
#   returns false if there was none that could be found to fit
# (string) reason an escort was rejected
###
function getEscort( $size, $BPV, $escortNum, $totalEscorts, $YIS )
{
  global $HEAVY_ESCORTS, $SMALL_ESCORTS, $YIS_THRESHOLD, $escorts;

  $output = false;
  $SC=3; // the size class of the escort. There are no SC 2 escorts
  $leastBigBPV = 1000; // the BPV of the largest SC3 escort
  $mostBigBPV = 0; // the BPV of the smallest SC3 escort
  $leastSmallBPV = 1000; // the BPV of the largest SC4 escort
  $mostSmallBPV = 0; // the BPV of the smallest SC4 escort
  $rejected = ""; // reason an escort was rejected

  // determine the size class of the escort:
  // the first escort is always SC 4
  // always go with SC 4 escorts if $SMALL_ESCORTS is true
  // if the carrier is SC4, then the escort is SC 4
  // the second escort probably should be SC 4, unless $HEAVY_ESCORTS is true
  // otherwise the escort is SC 3
  if( $escortNum == 1 || $SMALL_ESCORTS || $size == 4 || ($escortNum == 2 && ! $HEAVY_ESCORTS) )
    $SC=4;

  // search for smallest and largest BPVs, in hopes that they are the FF/DD and CW/CL escorts we want
  foreach( $escorts as $escortData )
  {
    // don't consider those escorts that are too old
    if( $YIS-$YIS_THRESHOLD >= $escortData['yearInService'] )
      continue;

    if( $escortData['sizeClass'] == 4 )
    {
      if( $escortData['BPV'] < $leastSmallBPV )
        $leastSmallBPV = $escortData['BPV'];
      if( $escortData['BPV'] > $mostSmallBPV )
        $mostSmallBPV = $escortData['BPV'];
    }
    else
    {
      if( $escortData['BPV'] < $leastBigBPV )
        $leastBigBPV = $escortData['BPV'];
      if( $escortData['BPV'] > $mostBigBPV )
        $mostBigBPV = $escortData['BPV'];
    }
  }

  // iterate through the escorts, looking for a match
  foreach( $escorts as $escortKey=>$escortData )
  {
var_dump($escortData['designator']);
    // skip if the SC does not match with what it should be
    if( $escortData['sizeClass'] != $SC )
    {
      continue;
    }

    // if the escort YIS date is $yisThreshold or more years behind the carrier's YIS date, don't use that escort
    if( $YIS-$YIS_THRESHOLD >= $escortData['yearInService'] )
    {
      $rejected .= $escortData['designator']." was rejected for being too old an escort for the carrier.\n";
      continue;
    }

    // use the larger escorts
    if( $HEAVY_ESCORTS )
    {
      if( $SC == 4 )
      {
        // skip if the escort is not a large SC4
        if( $escortData['BPV'] != $mostSmallBPV )
        {
          $rejected .= $escortData['designator']." was rejected for not being a large SC 4 escort.\n";
          continue;
        }
      }
      else // escort is SC 3
      {
        // skip if the first escort is not a large SC3
        if( $escortData['BPV'] != $mostBigBPV )
        {
          $rejected .= $escortData['designator']." was rejected for not being a large SC 3 escort.\n";
          continue;
        }
      }
    }
    else // use "normal" escorts
    {
      if( $SC == 4 )
      {
        // if this is the first escort
        if( $escortNum == 1 )
        {
          // skip if the escort is not a small SC4
          if( $escortData['BPV'] != $leastSmallBPV )
          {
            $rejected .= $escortData['designator']." was rejected for not being a small SC 4 escort.\n";
            continue;
          }
        }
        else // escort is the supposed to be a large SC 4 escort
        {
          // skip if the escort is not a small SC4
          if( $escortData['BPV'] != $mostSmallBPV )
          {
            $rejected .= $escortData['designator']." was rejected for not being a large SC 4 escort.\n";
            continue;
          }
        }
      }
      else // escort is SC 3
      {
        // skip if the first escort is not a small SC3
        if( $escortData['BPV'] != $leastBigBPV )
        {
          $rejected .= $escortData['designator']." was rejected for not being a small SC 3 escort.\n";
          continue;
        }
      }
    }
    $output = $escortKey; // if we made it to here, then we were not rejected in the above
  }

  return array( $output, $rejected);
}

###
# Determines if the default behavior needs to change, based on command-line arguments
###
# Inputs:
# - None
# Outputs:
# - None
###
function reassignDefaults ()
{
  global $EXTRA_ESCORT, $HEAVY_ESCORTS, $SMALL_ESCORTS, $SHOW_REJECTS, $argv;

  while( count($argv) > 4 )
  {
    $argument = strtolower(array_pop($argv)); // get the argument and drop it to lowercase
    $argument = ltrim( $argument, "-" ); // trim off the leading dash, if needed

    switch( $argument )
    {
    case "extra":
      $EXTRA_ESCORT = true;
      break;
    case "heavy":
      $HEAVY_ESCORTS = true;
      break;
    case "small":
      $SMALL_ESCORTS = true;
      break;
    case "rejects":
      $SHOW_REJECTS = true;
      break;
    }
  }
}
?>
