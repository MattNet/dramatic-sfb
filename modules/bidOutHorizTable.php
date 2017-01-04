<?php
###
# This outputs the bidding feed as an HTML Table into a file
# Creates a display of players and their units/scenarios:
# - A table with each player having two columns.
# - - Column 1 is a unit list
# - - Column 2 is a scenario list of scenario images. link of scenario image goes to scenario description
###

require_once( dirname(__FILE__) . "/common.tableOut.php" );

function bidOutHorizTable( $feed )
{
  global $MODULE_FILE_STORE, $PUBLIC_UNITS, $PUBLIC_SCENARIOS, $SCENARIOS, $BID_OUT_FILE_FORMAT;
  global $COMMON_JAVASCRIPT, $COMMON_TABLE_STYLE;

  $gameID = $feed->game;
  $gameTurn = $feed->turn;

  $empList = new objList( "empire", $gameID, $gameTurn, false );
  $unitList = new objList( "ship", $gameID, $gameTurn, false );
  $orderList = new objList( "orders", $gameID, $gameTurn, false );
  $gameObj = loadOneObject( "game", $gameID, $gameTurn );

  $empireIteration = array_keys( $empList->objByID );
  $javascript = "";
  $output = "";

  // make a file for each empire
  foreach( $empireIteration as $iterationID )
  {
    $currentEmpireObj = $empList->objByID[$iterationID];
    $javascript = "<script type='text/javascript'>\n";
    $javascript .= $COMMON_JAVASCRIPT;
    $javascript .= "\nvar scenarioJS = new Array();\n";
    $output = "<table style='$COMMON_TABLE_STYLE'>\n<tr>";

    // make the column for the current empire as the first one
    $output .= "<th colspan=2>{$empList->objByID[$iterationID]->modify('textName')} ({$empList->objByID[$iterationID]->modify('race')})</th>";

    // make the table heading
    if( $PUBLIC_UNITS && $PUBLIC_SCENARIOS )
    {
      foreach( $empList->objByID as $empID=>$empObj )
        if( $empID != $iterationID ) // skip the current empire. it's heading was done first
          $output .= "<th colspan=2>{$empObj->modify('textName')} ({$empObj->modify('race')})</th>";
      $output .= "</tr>\n<tr><td valign='top'>";
    }

    // do the unit column for the current empire
    $output .= listEmpireUnits( $unitList, $currentEmpireObj, "\n<br>" ) . "</td><td>";
    // do the scenario column for the current empire
    list( $tempOutput, $tempJS, $scenarioNum ) = bidScenarioText( $feed, $iterationID, 0, $empList, $unitList, $orderList );
    $output .= "$tempOutput</td><td valign='top'>";
    $javascript .= $tempJS;

    // iterate by empire
    foreach( $empList->objByID as $empID=>$empObj )
    {
      // skip if it is the empire that we started with
      if( $empID == $iterationID )
        continue;
      // make the first column
      if( $PUBLIC_UNITS )
      {
        $output .= listEmpireUnits( $unitList, $empObj, "\n<br>" );
      }
      $output .= "</td><td>";
      // make the second column
      if( $PUBLIC_SCENARIOS )
        list( $tempOutput, $tempJS, $scenarioNum ) = bidScenarioText( $feed, $empID, $scenarioNum, $empList, $unitList, $orderList );
      $output .= "$tempOutput</td><td valign='top'>";
      $javascript .= $tempJS;
    }

    $output .= "</tr></table>\nClick on the scenario for it's description.";
    $javascript .= "</script>\n";

    $filename = $MODULE_FILE_STORE.sprintf($BID_OUT_FILE_FORMAT, $gameTurn, $gameID, $iterationID );
    file_put_contents( $filename, $javascript.$output, LOCK_EX );
  }

  $empList->__destruct();
  unset( $empList );
  $unitList->__destruct();
  unset( $unitList );
  $orderList->__destruct();
  unset( $orderList );

}

?>
