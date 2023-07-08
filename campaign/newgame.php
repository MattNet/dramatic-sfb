<?php

$GOTO_ON_FAIL = "/index.php";	// page to serve if we've had very serious errors
$GOTO_ON_BACK = "/campaign/menu.php";	// page to serve if we click on a game link
$TEMPLATE_FILE = "./newgame.template";	// Template file to load

include_once( dirname(__FILE__) . "/../Login/Login_common.php" );
include_once( dirname(__FILE__) . "/../campaign_config.php" );
include_once( dirname(__FILE__) . "/../objects/game.php");

$empireDBList = "";	// A collection of empire objects associated with this game
$encounterDBList = "";	// A collection of encounter objects associated with this game turn
$empireObj = "";
$empResult = false;	// used to track success of the database read of the empire object (when needed)
$empIncome = 0;		// amount of EP income for this empire
$empStoredEP = 0;	// amount of stored EPs for this empire
$errors = "";	// the error string that will be output
$modPlayerObj = "";	// the object reference for the game's moderator in the database
$raceID = -1;	// the Race Identifier as given by the input. Set to -1 when there is no race input
$result = false;	// used to track success of database reads

// Set up the variables used inside the template

$backUpTag = "<a href='$GOTO_ON_BACK?".$authObj->getSessionRequest()."'>Account Menu</a>";
$formTag = "<form action='".$_SERVER['PHP_SELF']."' method='post' target='_SELF' class=''>\n";
$formTag .= $authObj->getSessionTag();
$logOutTag = "<a href='$GOTO_ON_LOGOUT&".$authObj->getSessionRequest()."'>LOG OUT</a>";
$startTag = "<input type='submit' name='createGame' value='Begin Game'>";
$gameNameTag = "<input type='text' name='name' size=48 class=''>";
$startYearTag = "<input type='text' name='startyear' value='165' size=3 class=''>";
$campaignSpeedTag = "<input type='text' name='campaignspeed' value='2' size=1 class=''>";
$overwhelmingForceTag = "<input type='text' name='overwhelmingForce' value='150' size=3 class=''>";
$allowConjecturalTag = "<select name='allowConjectural'><option value='0' selected>No</option><option value='1'>Yes</option></select>";
$allowPublicUnitsTag = "<select name='allowPublicUnits'><option value='0'>No</option><option value='1' selected>Yes</option></select>";
$allowPublicScenTag = "<select name='allowPublicScenarios'><option value='0'>No</option><option value='1' selected>Yes</option></select>";
$sizeClassTag = "<input type='text' name='sizeclass' value='1' size=1 class=''>";
$nonIncomeTurnTag = "";
$errorTag = "";

// Input Bids modules
if( count( $MODULE_BIDS_IN ) == 1 )
{
  $modBidInTag = $MODULE_BIDS_IN[0]."<input type='hidden' name='modbidin' value='{$MODULE_BIDS_IN[0]}'>";
}
else
{
  $modBidInTag = "<select name='modbidin'>\n";
  foreach( $MODULE_BIDS_IN as $key=>$mod )
  {
    $modBidInTag .= "<option";
    if( $key == 0 )
      $modBidInTag .= " selected";
    $modBidInTag .= ">$mod</option>\n";
  }
  $modBidInTag .= "</select>";
}
// Output Bids modules
if( count( $MODULE_BIDS_OUT ) == 1 )
{
  $modBidOutTag = $MODULE_BIDS_OUT[0]."<input type='hidden' name='modbidout' value='{$MODULE_BIDS_OUT[0]}'>";
}
else
{
  $modBidOutTag = "<select name='modbidout'>\n";
  foreach( $MODULE_BIDS_OUT as $key=>$mod )
  {
    $modBidOutTag .= "<option";
    if( $key == 0 )
      $modBidOutTag .= " selected";
    $modBidOutTag .= ">$mod</option>\n";
  }
  $modBidOutTag .= "</select>";
}
// Input Encounters modules
if( count( $MODULE_ENCOUNTERS_IN ) == 1 )
{
  $modEncInTag = $MODULE_ENCOUNTERS_IN[0]."<input type='hidden' name='modencin' value='{$MODULE_ENCOUNTERS_IN[0]}'>";
}
else
{
  $modEncInTag = "<select name='modencin'>\n";
  foreach( $MODULE_ENCOUNTERS_IN as $key=>$mod )
  {
    $modEncInTag .= "<option";
    if( $key == 0 )
      $modEncInTag .= " selected";
    $modEncInTag .= ">$mod</option>\n";
  }
  $modEncInTag .= "</select>";
}
// Output Encounters modules
if( count( $MODULE_ENCOUNTERS_OUT ) == 1 )
{
  $modEncOutTag = $MODULE_ENCOUNTERS_OUT[0]."<input type='hidden' name='modencout' value='{$MODULE_ENCOUNTERS_OUT[0]}'>";
}
else
{
  $modEncOutTag = "<select name='modencout'>\n";
  foreach( $MODULE_ENCOUNTERS_OUT as $key=>$mod )
  {
    $modEncOutTag .= "<option";
    if( $key == 0 )
      $modEncOutTag .= " selected";
    $modEncOutTag .= ">$mod</option>\n";
  }
  $modEncOutTag .= "</select>";
}

if( ! empty($_REQUEST['createGame']) )
{
  do // make a DO loop so we can break out on failure
  {
    if( ! isset($_REQUEST['name']) || ! isset($_REQUEST['startyear']) ||
        ! isset($_REQUEST['campaignspeed']) || ! isset($_REQUEST['overwhelmingForce']) ||
        ! isset($_REQUEST['allowConjectural']) || ! isset($_REQUEST['sizeclass']) ||
        ! isset($_REQUEST['modbidin']) || ! isset($_REQUEST['modbidout']) || 
        ! isset($_REQUEST['modencin']) || ! isset($_REQUEST['modencout']) ||
        ! isset($_REQUEST['allowPublicUnits']) || ! isset($_REQUEST['allowPublicScenarios'])
      )
    {
      $errors .= "<br>Some configuration items were not set properly.";
      break; // skip creating a new game if we don't have all the inputs
    }

    $game = new Game( array(
            'currentTurn'=> 0, 'borderSize'=> 0, 'campaignSpeed'=> intval($_REQUEST['campaignspeed']),
            'gameName'=> $_REQUEST['name'], 'gameStart' => intval($_REQUEST['startyear']),
            'moderator'=> $userObj->modify('id'), 'moduleEncountersIn'=> $_REQUEST['modencin'],
            'moduleEncountersOut' => $_REQUEST['modencout'], 'moduleBidsIn' => $_REQUEST['modbidin'],
            'moduleBidsOut'=> $_REQUEST['modbidout'], 'randomSeeds'=> '', 'status'=>Game::STATUS_OPEN,
            'useExperience'=> 0, 'useUnitSwapping'=> 0, 'overwhelmingForce'=>intval($_REQUEST['overwhelmingForce']),
            'allowConjectural'=>intval($_REQUEST['allowConjectural']), 'largestSizeClass'=>intval($_REQUEST['sizeclass']),
            'allowPublicUnits'=>intval($_REQUEST['allowPublicUnits']),'allowPublicScenarios'=>intval($_REQUEST['allowPublicScenarios'])
          ) );
    $game->create();

    redirect( "$GOTO_ON_BACK?".$authObj->getSessionRequest() );
  }
  while(false);
}

$errorTag = $errors;

// display the page
header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();

?>
