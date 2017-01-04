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

require_once( dirname(__FILE__) . "/../objects/empire.php" );
require_once( dirname(__FILE__) . "/../objects/orders.php" );
require_once( dirname(__FILE__) . "/../objects/obj_list.php" );

function BidInFile( $gameObj, $turn )
{
  global $BID_IN_DIRECTORY, $BID_IN_FILE_REGEX;

  $gameID = $gameObj->modify('id');
  $tempFileName = sprintf( $BID_IN_FILE_REGEX, $turn, $gameID );

  $fileList = array();

  $dirListing = scandir( $BID_IN_DIRECTORY );
  foreach( $dirListing as $fileItem )
  {
    $result = preg_match( "/^$tempFileName/", $fileItem, $matches );
    if( ! $result )
      continue;
    $fileList[] = array( 'file'=>$matches[0], 'empireID'=>$matches[1] );
  }

  foreach( $fileList as $fileData )
  {
    $ordersFile = file( "$BID_IN_DIRECTORY/".$fileData['file'], FILE_SKIP_EMPTY_LINES );
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

      // create the order in memory
      $order = new Orders( array(
                'game'=> $gameID, 'orders' => implode( ",", $orderCollection ),
                'empire'=> $fileData['empireID'], 'turn' => $turn
              ) );
      $order->create(); // save the order to the database
      unset( $order ); // discard the order from memory
  }

  $output = new ObjList( 'orders', $gameID, $turn );
  return $output;
}

###
# This updates the file used in BidInFile() with whatever new orders were added to the DB
###
# Arguments:
# - (object) 
# Returns:
# - None. Modifies a file
###
function BidInFileUpdate( $feed )
{
  global $BID_IN_DIRECTORY;

  $gameID = $feed->game;
  $gameTurn = $feed->turn;

  $orderList = new objList( "orders", $gameID, $gameTurn, false );
  $collectedOrders = array();

  foreach( $orderList->objByID as $id=>$orderObj )
  {
    $empID = $orderObj->modify('empire');
    $orderString = $orderObj->modify('orders');
    if( empty( $orderString ) )
      continue;
    if( ! isset($collectedOrders[ $empID ]) )
      $collectedOrders[ $empID ] = "";
    else
      $collectedOrders[ $empID ] .= "\n";
    $collectedOrders[ $empID ] .= str_replace( ",", "\n", $orderObj->modify('orders') );
  }

  // make a file for each empire
  foreach( $collectedOrders as $empireID=>$orderString )
  {
    // write the order file. Replaces the previous one
    $fileName = $BID_IN_DIRECTORY.$empireID."input".$gameID."to".$gameTurn.".txt";
    file_put_contents( $fileName, $orderString, LOCK_EX );
  }
}
?>
