<?php
require_once( dirname(__FILE__) . "/Login_baseobject.php" );

class Orders extends BaseObject
{
  const table	= "sfbdrama_orders";

  protected $game	= 0; // id of the game object.
  protected $orders	= ""; // a comma delim'd list of orders
  protected $empire	= 0; // id of the empire object.
  protected $turn	= 0;

  protected $decodedOrders	= array();
  protected $ordersRegex	= array(	// holds the regular expressions used to tokenize orders
              'bid_ship' => "/^bid(\d+)to(\d+)/i",
              'build_ship' => "/^build(\d+)/i",
              'build_name_ship' => "/^build(\d+)named\"([a-zA-Z0-9\ \'#\.-]+)\"/i",
              'convert_ship' => "/^convert(\d+)to(\d+)/i",
              'cripple_ship' => "/^cripple(\d+)/i",
              'defeat' => "/^defeat(\d+)/i",
              'destroy_ship' => "/^destroy(\d+)/i",
              'gift_ship' => "/^gift(\d+)empire(\d+)/i",
              'name_empire' => "/^name(\d+)empire\"([a-zA-Z0-9\ \'#\.-]+)\"/i",
              'name_ship' => "/^name(\d+)ship\"([a-zA-Z0-9\ \'#\.-]+)\"/i",
              'repair_ship' => "/^repair(\d+)/i",
              'victory' => "/^victory(\d+)/i",
            );

  ###
  # The Class Constructor
  ###
  # Args are:
  # - (integer) The Identifier number for this object.
  #   If an array, then the values are put into the properties matching the array keys
  # Returns:
  # - None
  ###
  function __construct( $id = 0 )
  {
    parent::__construct( $id );
    $intProps = array(
      'game', 'empire', 'turn'
    );
    foreach( $intProps as $prop )
      $this->$prop = (int) $this->$prop;
  }

  ###
  # Class destructor
  ###
  # Args are:
  # - None
  # Returns:
  # - None
  ###
  function __destruct()
  {
    if( $this->orders == "" )
      $this->destroy();
    parent::__destruct();
  }

  ###
  # Turns the Orders string into an assoc array
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) the Orders string converted to a sensible associated array
  ###
  function decodeOrders()
  {
    if( ! empty( $this->decodedOrders ) )
      return $this->decodedOrders;
    $orders = explode( ",", $this->orders );
    $output = array(
        'bids'=>array(),
        'builds'=>array(),
        'conversions'=>array(),
        'cripples'=>array(),
        'destroy'=>array(),
        'encounters'=>array(),
        'gifts'=>array(),
        'names'=>array(),
        'repairs'=>array()
    );
    foreach( $orders as $line )
    {
      $line = stripcslashes($line);
      if( preg_match( $this->ordersRegex['bid_ship'], $line, $matches ) )
        $output['bids'][] = array( 'ship'=>(int) $matches[1],'encounter'=>(int) $matches[2]);
      else if( preg_match( $this->ordersRegex['build_name_ship'], $line, $matches ) )
        $output['builds'][] = array( 'ship'=>(int) $matches[1], 'name'=> addcslashes($matches[2],"'"), );
      else if( preg_match( $this->ordersRegex['build_ship'], $line, $matches ) )
        $output['builds'][] = array( 'ship'=>(int) $matches[1], 'name'=>'', );
      else if( preg_match( $this->ordersRegex['convert_ship'], $line, $matches ) )
        $output['conversions'][ intval($matches[1]) ] = array( 'ship'=>(int) $matches[1],'design'=>(int) $matches[2]);
      else if( preg_match( $this->ordersRegex['cripple_ship'], $line, $matches ) )
        $output['cripples'][] = (int) $matches[1];
      else if( preg_match( $this->ordersRegex['destroy_ship'], $line, $matches ) )
        $output['destroy'][] = (int) $matches[1];
      else if( preg_match( $this->ordersRegex['defeat'], $line, $matches ) )
        $output['encounters'][ intval($matches[1]) ] = false;
      else if( preg_match( $this->ordersRegex['gift_ship'], $line, $matches ) )
        $output['gifts'][ intval($matches[1]) ] = array( 'ship'=>(int) $matches[1],'empire'=>(int) $matches[2]);
      else if( preg_match( $this->ordersRegex['name_empire'], $line, $matches ) )
        $output['names'][] = array( 'empire'=>(int) $matches[1],'ship'=>"",'name'=> addcslashes($matches[2],"'"));
      else if( preg_match( $this->ordersRegex['name_ship'], $line, $matches ) )
        $output['names'][] = array( 'empire'=>"",'ship'=>(int) $matches[1],'name'=> addcslashes($matches[2],"'"));
      else if( preg_match( $this->ordersRegex['repair_ship'], $line, $matches ) )
        $output['repairs'][] = (int) $matches[1];
      else if( preg_match( $this->ordersRegex['victory'], $line, $matches ) )
        $output['encounters'][ intval($matches[1]) ] = true;
    }
    $this->decodedOrders = $output;
    return $this->decodedOrders;
  }

  ###
  # Adds orders to an existing line of orders
  ###
  # Args are:
  # - (string) The tokenized order to add to the object
  # Returns:
  # - None, but changes the results of decodeOrders
  ###
  function addOrders( $newOrder )
  {
    $oldOrders = explode( ",", $this->orders );
    if( $oldOrders[0] == "" ) // empty sets of orders give us an empty first element
      unset( $oldOrders[0] );
    $oldOrders[] = $newOrder;
    $this->orders = implode( ",", $oldOrders );
    $this->decodedOrders = array();
    $this->taint = self::IS_DIRTY;
  }

  ###
  # Prunes the order list down to just the bidding orders.
  # Used when re-doing a turn halfway into it
  ###
  # Args are:
  # - None
  # Returns:
  # - (bool) True for success. false otherwise.
  ###
  function pruneToBids()
  {
    $orderList = explode( ",", $this->orders );
    $newOrderList = array();
    foreach( $orderList as $order )
    {
      if( stripos( $order, "bid" ) === 0 )
        $newOrderList[] = $order;
    }
    $this->orders = implode( ",", $newOrderList );
    $this->decodedOrders = array();
    return true;
  }

  ###
  # Removes an order from an existing line of orders
  ###
  # Args are:
  # - (string) The tokenized order to remove to the object
  # Returns:
  # - None, but changes the results of decodeOrders
  ###
  function removeOrders( $oldOrder )
  {
    $orderString = explode( ",", $this->orders );
    foreach( $orderString as $key=>$listedOrder )
      foreach( $this->ordersRegex as $regex )
      {
        // check the argument-order against the same regex as we check each original-order against
        preg_match( $regex, $oldOrder, $oldMatches );
        preg_match( $regex, $listedOrder, $listedMatches );
        // if the two orders perform the same thing with the same numbers, then remove the listed order
        if( $oldMatches === $listedMatches )
        {
          unset( $orderString[$key] );
          break 2; // stop iterating through the orders
        }
      }
    $orderString = array_values($orderString);
    $this->orders = implode( ",", $orderString );
    $this->decodedOrders = array();
    $this->taint = self::IS_DIRTY;
  }

  ###
  # Returns those properties which need to be stored in the database
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) List of property_names => property_values
  ###
  function values ()
  {
    $output = array(
      'game'	=> $this->game,
      'orders'	=> $this->orders,
      'empire'	=> $this->empire,
      'turn'	=> $this->turn
    );
    return $output;
  }
}

?>
