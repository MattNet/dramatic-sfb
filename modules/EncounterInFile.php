<?php
###
# This creates a set of bidding orders based on text files found in the directory structure
###
# Adds two configuration constants:
# - $BID_IN_DIRECTORY - the directory where the orders are found
# - $BID_IN_FILE_REGEX - the format of the order filenames. Designed for a sprintf(), 
#	with turn number and player ID assigned in that order. The game identifier is in perl regex format
# The order filename is run through sprintf() to fill in the turn number and playerID (in that order)
###
# basic format for this filename is [playerID the file is intended for][type of file][game identifier]to[turn number].html
###

require_once( dirname(__FILE__) . "/../objects/empire.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );

function EncounterInFile( $gameObj, $turn )
{
  global $ENCOUNTER_IN_DIRECTORY, $ENCOUNTER_IN_FILE_REGEX;

  $gameID = $gameObj->modify('id');
  $tempFileName = sprintf( $ENCOUNTER_IN_FILE_REGEX, $turn, $gameID );

  $fileList = array();

  $dirListing = scandir( $ENCOUNTER_IN_DIRECTORY );
  foreach( $dirListing as $fileItem )
  {
    $result = preg_match( "/^$tempFileName/", $fileItem, $matches );
    if( ! $result )
      continue;
    $fileList[] = array( 'file'=>$matches[0], 'empireID'=>$matches[1] );
  }

  foreach( $fileList as $fileData )
  {
    $ordersFile = file( "$ENCOUNTER_IN_DIRECTORY/".$fileData['file'], FILE_SKIP_EMPTY_LINES );
    $orderCollection = array(); // stores the list of orders that will be placed in the DB

    foreach( $ordersFile as $order )
    {
/*
// this collides with lawful use of hash-marks within unit (ship) names
      // trim post-comment stuff from orders
      if( strpos( $order, "#" ) !== false )
        $order = substr( $order, 0, strpos( $order, "#" ) );
*/
      // trim whitespace from the orders
      $order = trim($order);
      if( ! empty($order) )
        $orderCollection[] = $order;
    }

    // find the old order entry and update it
    $order = new Orders( array( 'game'=> $gameID, 'empire'=> $fileData['empireID'], 'turn' => $turn ) );
    $order->getID('empire');
    $order->modify( 'orders', implode( ",", $orderCollection ) );
    $order->update();

    unset( $order );
  }

  $output = new ObjList( 'orders', $gameID, $turn );
  return $output;
}

function EncounterInFileUpdate( $feed )
{
/*
  global $BID_IN_DIRECTORY;

  $gameID = $feed->game;
  $gameTurn = $feed->turn;
*/
}

?>
