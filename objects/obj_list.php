<?php
###
# Collects all of the objects of one type into one object.
# Provides lookups for the list
###
# allByGameTurn( $game, $turn )
# - Groups all of the objects used in the given game and turn into the $objByTypeID lookup
# push( $object )
# - Adds an object to the various lookups
# Returns all of the object identifiers in this collection
# - keys()
# tableSearch( $property, $value, $table )
# - Searches all of the objects for the given property with the given value
# Writes all of the contained objects into the database
# - write()
# objByID
# - a list of object instances, keyed in a array by object ID
###

require_once( dirname(__FILE__) . "/gameDB.php" );

class ObjList
{
  public $error_string = "";
  public $objByID = array(); // a list of object instances, keyed by ID
  public $turn = 0; // The turn that the object is set to
  public $game = 0; // The game that the object is set to

  private $dbase = ""; // stores a reference to the database

  ###
  # The object constructor
  ###
  # Args are:
  # - (string) the type of object to load up (an object class name). This can be an empty string, which will not pre-populate from the DB
  # - (int) The turn number to look for
  # - (int) The game identifier to look for
  # - (bool) [optional] Set to to the value that the objects in the list should have their 'autowrite' property set to
  # Returns:
  # - None
  ###
  function __construct ( $objType, $game, $turn, $automaticWrite=true )
  {
    $this->turn = intval($turn);
    $this->game = intval($game);
    if( $objType != "" )
    {
      require_once( dirname(__FILE__) . "/" . strtolower( $objType ) . ".php" );
      if( ! property_exists($objType, 'game') || ! property_exists($objType, 'turn') )
        return;
      $this->_allByGameTurn( $objType, $automaticWrite );
    }
  }

  ###
  # The Destructor. Unloads all of the objects
  ###
  # Args are:
  # - None
  # Returns:
  # - None
  ###
  function __destruct ()
  {
    $keys = array_keys($this->objByID);
    foreach( $keys as $id )
    {
      $this->objByID[$id]->__destruct();
      unset( $this->objByID[$id] );
    }
  }

  ###
  # Adds an object to the collection
  ###
  # Args are:
  # - (object) the object to add
  # Returns:
  # - (int) The ID of the object
  ###
  function push( &$obj )
  {
    $id = $obj->modify( 'id' );

    if( $id === false )
    {
      $this->error_string .= "<br>Error in ObjList->push() ". $obj->error_string."\n";
      return false;
    }
    $this->objByID[ $id ] = $obj;
    return $id;
  }

  ###
  # Returns all of the object identifiers in this collection
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) a list of object IDs
  ###
  function keys()
  {
    return array_keys( $this->objByID );
  }

  ###
  # Groups all of the objects used in the given game and turn into the $objByTypeID lookup
  # This will not import those objects missing either of 'game' or 'turn' properties
  # e.g. BaseDesign, Configuration, Conversion, Game, Player, or ShipDesign objects
  ###
  # Args are:
  # - (string) The object class name
  # - (bool) The value to set each object's "autowrite" property to
  # Returns:
  # - (boolean) true for success, false for failure.
  ###
  function _allByGameTurn( $class, $write )
  {
    $database = gameDB::giveme();
    // check if this object has a 'game' and 'turn' property
    if( ! property_exists($class, 'game') || ! property_exists($class, 'turn') )
      continue;
    $inputs = array();
    $result = $database->getAllGameTurn( $this->game, $this->turn, $class::table, $objIDList );
    if( $result === false )
    {
      $this->error_string .= "\nError in ObjList->allByGameTurn( {$this->game}, {$this->turn} ), class '$class'.\n".$database->error_string;
      return false;
    }
    foreach( $objIDList as $identifier )
    {
      $nowInstance = new $class( $identifier );
      if( $class != "History" )
        $nowInstance->modify( 'turn', $this->turn );
      $nowInstance->read();
      $nowInstance->modify( 'autowrite', $write );
      $this->push( $nowInstance );
    }
    unset($nowInstance, $inputs);
    return true;
  }

  ###
  # Searches all of the objects for the given property with the given value
  ###
  # Args are:
  # - (string) The property name to look for
  # - (string) The property value to look for
  # Returns:
  # - (array) a list of IDs
  ###
  function tableSearch( $property, $value )
  {
    $output = array();
    if( empty($this->objByID) )
      return $output;
    foreach( $this->objByID as $id => $obj )
    {
      if( ! method_exists( $obj, 'modify' ) )
        break;
      $result = $obj->modify( $property );
      if( $result === false )
        continue;
      if( $result == $value )
        $output[] = $id;
    }
    return $output;
  }

  ###
  # Searches all of the history objects for the events of a certain type that occurred to a given empire in the last N turns
  ###
  # Args are:
  # - (int) The empire ID to search against
  # - (int) The event type (against the History::History_XXX type)
  # - (int) The number of preceeding turns to check against
  # Returns:
  # - (array) a list of event IDs that match
  ###
  function historySearch( $empireID, $eventID, $turnSearch )
  {
    $output = $this->tableSearch( 'reciever', $empireID, 'History' );
    if( empty($output) )
      return $output;
    foreach( $output as $key=>$eventID )
    {
      $obj = $this->objByTypeID['History'][$eventID];
      if( $obj->modify('what') != $eventID )
        unset( $output[$key] );
      else if( $obj->modify('turn') < ($this->turn - $turnSearch) )
        unset( $output[$key] );
      else if( $obj->modify('turn') > $this->turn )
        unset( $output[$key] );
    }
    $output = array_values($output);
    return $output;
  }

  ###
  # Writes all of the contained objects into the database
  ###
  # Args are:
  # None
  # Returns:
  # - (string) empty for success, otherwise the error string
  ###
  function write()
  {
    $output = "";
    foreach( $this->objByID as $id => $obj )
    {
      $result = $obj->update();
      if( $result === false )
        $output = $obj->error_string;
    }
    return $output;
  }

  ###
  # Displays all objects loaded in this collation object
  ###
  # Args are:
  # - None
  # Returns:
  # - (string) the HTML to display
  ###
  function viewAllLoaded ()
  {
    $output = "";
    foreach( $this->objByID as $table )
    {
      $headingFlag = false;
      $isEvenFlag = true;
      $output .= "<table width='100%'>\n";
      foreach( $table as $obj )
      {
        if( ! $headingFlag )
        {
          $output .= $obj->HTMLform( "#ffcccc", true );
          $headingFlag = true;
        }
        if( $isEvenFlag )
        {
          $output .= $obj->HTMLform( "#ffffff" );
          $isEvenFlag = false;
        }
        else
        {
          $output .= $obj->HTMLform( "#ccccff" );
          $isEvenFlag = true;
        }
      }
      $output .= "</table>\n";
    }
    return $output;
  }
}

?>
