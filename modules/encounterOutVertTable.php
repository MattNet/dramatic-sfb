<?php
###
# This outputs the encounter feed as an HTML Table into a file
# Creates a display of players and their units/scenarios:
# - A table with each player having two columns.
# - - Column 1 is a unit list
# - - Column 2 is a scenario list of scenario images. link of scenario image goes to scenario description
# ** This includes a routine to send emails to all of the players **
###

require_once( dirname(__FILE__) . "/common.tableOut.php" );
require_once( dirname(__FILE__) . "/../Login/Login_email.php" );

function encounterOutVertTable( $feed )
{
  global $MODULE_FILE_STORE, $SCENARIOS, $ENCOUNTER_OUT_FILE_FORMAT;
  global $COMMON_JAVASCRIPT, $BUSINESS_GIVEN_NAME;

  $gameID = $feed->game;
  $gameTurn = $feed->turn;

  $empList = new objList( "empire", $gameID, $gameTurn, false );
  $unitList = new objList( "ship", $gameID, $gameTurn, false );
  $gameObj = loadOneObject( "game", $gameID, $gameTurn );

  $empireIteration = array_keys( $empList->objByID );
  $javascript = "";
  $output = "";

  $eBody = "Dear  %1\$s,\nYour %2\$s empire reports for the start of turn $gameTurn of the {$gameObj->modify('gameName')} game have been completed. Please go to $BUSINESS_GIVEN_NAME website and enter your orders.\n\nThis mailbox is unattended. Please do not reply to this message.";
  $eTo = "";
  $eSubject = "$BUSINESS_GIVEN_NAME: Waiting for {$gameObj->modify('gameName')} bid orders";

  // make a file for each empire
  foreach( $empireIteration as $iterationID )
  {
    $currentEmpireObj = $empList->objByID[$iterationID];
    $currentPlayer = loadOneObject( "user", $currentEmpireObj->modify('player') );
    if( $currentPlayer === false ) // if we could not load the player object
      continue;

    $javascript = "<script type='text/javascript'>\n";
    $javascript .= $COMMON_JAVASCRIPT;
    $javascript .= "\nvar scenarioJS = new Array();\n";
    $output = "<table class='scenario_table'>\n";

    // make the row for the current empire as the first one
    $output .= "<tr><th rowspan=2 class='scenario_table_empire'>".$empList->objByID[$iterationID]->modify('textName');
    $output .= " ({$empList->objByID[$iterationID]->modify('race')})</th>\n<td colspan=";
    $output .= count($empireIteration)." class='scenario_table_units'>";
    // do the unit row for the current empire
    $output .= listEmpireUnits( $unitList, $currentEmpireObj );
    $output .= "</td></tr>\n<tr><td class='scenario_table_scenarios'>";
    // do the scenario row for the current empire
    list( $tempOutput, $tempJS, $scenarioNum ) = encounterScenarioText( $feed, $iterationID, 0, $empList );
    $output .= "$tempOutput</td></tr>\n";
    $javascript .= $tempJS;

    // do the rows for the other empires
    if( $gameObj->modify('allowPublicUnits') || $gameObj->modify('allowPublicScenarios') )
    {
      // iterate by empire
      foreach( $empList->objByID as $empID=>$empObj )
      {
        if( $empID == $iterationID ) // skip the current empire. it's heading was done first
          continue;

        $tempOutput = "";

        // make the row heading
        $output .= "<tr><th rowspan=2 class='scenario_table_empire'>".$empObj->modify('textName');
        $output .= " ({$empObj->modify('race')})</th>\n<td colspan=".count($empireIteration);
        $output .= " class='scenario_table_units'>";

        // make the first row
        if( $gameObj->modify('allowPublicUnits') )
        {
          $output .= listEmpireUnits( $unitList, $empObj );
        }
        $output .= "</td></tr>\n<tr><td class='scenario_table_scenarios'>";
        // make the second row
        if( $gameObj->modify('allowPublicScenarios') )
          list( $tempOutput, $tempJS, $scenarioNum ) = encounterScenarioText( $feed, $empID, $scenarioNum, $empList );
        $output .= "$tempOutput</td></tr>\n";
        $javascript .= $tempJS;
      }
    }

    $output .= "</table>\nClick on the scenario for it's description. Click on the list of ships to get more/less information.\n";
    $javascript .= "</script>\n";

    $filename = $MODULE_FILE_STORE.sprintf($ENCOUNTER_OUT_FILE_FORMAT, $gameTurn, $gameID, $iterationID );
    file_put_contents( $filename, $javascript.$output, LOCK_EX );

    dataFileOut( $gameObj, $empList->objByID[$iterationID], $unitList, $feed, $empList );

    if( isset($currentPlayer) && $currentPlayer->config('emailUpdate') )
    {
      $tempEBody = sprintf( $eBody, $currentPlayer->modify('fullName'), $currentEmpireObj->modify('textName') );
      $eTo = $currentPlayer->modify('email');
      send_email( $tempEBody, $eTo, $eSubject );
    }
    unset( $currentPlayer );
  }

  $gameObj->__destruct();
  unset( $gameObj );
  $empList->__destruct();
  unset( $empList );
  $unitList->__destruct();
  unset( $unitList );
}

?>
