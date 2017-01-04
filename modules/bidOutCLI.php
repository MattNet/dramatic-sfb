<?php
###
# This outputs the bidding feed to the Command Line
###

require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../scenarios/scenarios.php" );

function bidOutCLI( $feed )
{
  $boldText = `tput bold`;
  $normalText = `tput sgr0`;
  $empireList = new ObjList( 'empire', $feed->game, $feed->turn );

  foreach( $feed->objByID as $encounterObj )
  {
    $scenarioData = $GLOBALS[ 'SCENARIOS' ][ $encounterObj->modify('scenario') ];
    $name = $scenarioData[0];
    $description = $scenarioData[1];
    $reward = $scenarioData[2];
    $penalty = $scenarioData[3];
    $playerA = ucfirst( $empireList->objByID[ $encounterObj->modify('playerA') ]->modify('race') );
    $playerB = ucfirst( $empireList->objByID[ $encounterObj->modify('playerB') ]->modify('race') );
    $playerAForce = $encounterObj->modify( 'playerAShips' );
    $playerBForce = $encounterObj->modify( 'playerBShips' );

    if( $encounterObj->modify('status') == Encounter::NEEDS_RESOLUTION ||
        $encounterObj->modify('status') == Encounter::OVERWHELMING_FORCE )
    {
      $description = preg_replace( "/<b>/", $boldText, $description );
      $description = preg_replace( "/<\/b>/", $normalText, $description );
      $description = preg_replace( "/<br>/", "\n", $description );
      $description = preg_replace( "/<p>/", "\n\n", $description );

      $description = sprintf( $description, $playerA, $playerB, $playerAForce, $playerBForce );

      echo( "$boldText$name\n$playerA gain for victory:$normalText $reward$boldText $playerA loss for defeat:$normalText $penalty.\n" );
      if( $encounterObj->modify('status') == Encounter::OVERWHELMING_FORCE )
        echo( "$boldText There is overwhelming force present.$normalText\n" );
      echo( "$description\n" );
    }
    else if( $encounterObj->modify('status') == Encounter::PLAYER_A_VICTORY )
    {
      echo( "$boldText$name\nScenario auto-ran. $playerA gains due to victory:$normalText $reward\n" );
    }
    else if( $encounterObj->modify('status') == Encounter::PLAYER_A_DEFEATED )
    {
      echo( "$boldText$name\nScenario auto-ran. $playerA loss due to defeat:$normalText $penalty\n" );
    }
  }
}
?>
