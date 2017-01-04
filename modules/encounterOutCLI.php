<?php
###
# This outputs the encounter feed to the Command Line
###

require_once( dirname(__FILE__) . "/../objects/obj_list.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../scenarios/scenarios.php" );

function encounterOutCLI( $feed )
{
  global $MODULE_FILE_STORE, $SCENARIOS;

  $boldText = `tput bold`;
  $normalText = `tput sgr0`;
  $empireList = new ObjList( 'empire', $feed->game, $feed->turn );

  foreach( $feed->objByID as $encounterObj )
  {
    $scenarioData = $SCENARIOS[ $encounterObj->modify('scenario') ];
    $name = $scenarioData[0];
    $description = $scenarioData[1];
    $reward = $scenarioData[2];
    $penalty = $scenarioData[3];
    $playerA = ucfirst( $empireList->objByID[ $encounterObj->modify('playerA') ]->modify('race') );
    $playerB = ucfirst( $empireList->objByID[ $encounterObj->modify('playerB') ]->modify('race') );

    $description = preg_replace( "/<b>/", $boldText, $description );
    $description = preg_replace( "/<\/b>/", $normalText, $description );
    $description = preg_replace( "/<br>/", "\n", $description );
    $description = preg_replace( "/<p>/", "\n\n", $description );

    $description = sprintf( $description, $playerA, $playerB, "", "" );

    echo( "$boldText$name\n$playerA gain for victory:$normalText $reward$boldText $playerA loss for defeat:$normalText $penalty\n" );
    echo( "$description\n" );

  }
}
?>
