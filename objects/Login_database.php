<?php
###
# Provides the underlying database functionality
###
# giveme()
# - Creates/returns the only instance of this object
# createobj( $table, $list )
# - Creates an object in the database
# destroyobj( $table, $id )
# - Removes an existing object from the database
# getTempID( $tableName );
# - retrieves the next available ID number from the database for this object type
# readobj( $table, $id, &$values )
# - Reads an existing object from the database
# updateobj( $table, $id, $list )
# - Updates an existing object in the database
# getAllGameTurn( $game, $turn, $table, &$values )
# - Reads all existing objects from the given table that are in the given game and the given turn
# wash( $string )
# - escapes a string for use in a query
# genquery( $query )
# - A very general query function. Should not be used
###

require_once( dirname(__FILE__) . "/../Login/Login_config.php" );

class DataBase
{

// About the database server
  protected $mysql_server = "";
  protected $mysql_database = "";
  protected $mysql_user_member = "";
  protected $mysql_pw_member = "";
  protected $mysql_user_admin = "";
  protected $mysql_pw_admin = "";

// Other object variables
  protected $dblink = false;
  protected $instance; // the only instance allowed of this class
  public $error_string = "";

  ###
  # Class constructor
  # private to prevent direct construction of this object
  ###
  protected function __construct( $asAdmin )
  {
    global $MYSQL_server,$MYSQL_database,$MYSQL_user_member,$MYSQL_pw_member,$MYSQL_user_admin,$MYSQL_pw_admin;

    $this->mysql_server = $MYSQL_server;
    $this->mysql_database = $MYSQL_database;
    $this->mysql_user_member = $MYSQL_user_member;
    $this->mysql_pw_member = $MYSQL_pw_member;
    $this->mysql_user_admin = $MYSQL_user_admin;
    $this->mysql_pw_admin = $MYSQL_pw_admin;


    if( $asAdmin === true )
      $result = self::superOpen();
    else
      $result = self::open();
    if( ! $result )
    {
      $this->error_string .= "\n<br>Could not open connection to Database.\n";
      die( $this->error_string ); // instead of returning false from a constructor
    }
  }

  ###
  # Class destructor
  ###
  function __destruct()
  {
    self::close();
  }

  ###
  # This is how you get a copy of this object
  ###
  # Args are:
  # - (bool) [optional] should the database connection be with the admin user
  ###
  public static function giveme( $asAdmin=false )
  {
    $c = get_called_class();
    if( ! isset($instance[$c]) )
    {
      $instance[$c] = new $c($asAdmin);
    }
    return $instance[$c];
  }

  ###
  # Opens a connection to the database
  ###
  # Args are:
  # - none
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  ###
  protected function open()
  {
/*
    # This sets MySQL server options with the connection
    if( ! $this->dblink = mysqli_init() )
    {
      $this->error_string .= "\n<br>Could not initialize the database server.";
      return false;
    }
    if( ! $this->dblink->options(MYSQLI_READ_DEFAULT_GROUP, "max_allowed_packet=".ini_get('upload_max_filesize')) )
    {
      $this->error_string .= "\n<br>Could not set connection options on the database server.";
      return false;
    }
    if( ! $this->dblink->real_connect($this->mysql_server, $this->mysql_user_member, $this->mysql_pw_member, $this->mysql_database) )
    {
      $this->error_string .= "\n<br>Could not connect to database server.\n".mysqli_connect_error();
      return false;
    }
*/
    $this->dblink = new mysqli( $this->mysql_server, $this->mysql_user_member, $this->mysql_pw_member, $this->mysql_database );
    if( ! $this->dblink || $this->dblink->connect_errno )
    {
      $this->error_string .= "\n<br>Could not connect to database server.\n".mysqli_connect_error();
      return false;
    }
    return true;
  }

  ###
  # Opens a connection to the database as this database's superuser
  ###
  # Args are:
  # - none
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  ###
  protected function superOpen()
  {
    $this->dblink = new mysqli( $this->mysql_server, $this->mysql_user_admin, $this->mysql_pw_admin, $this->mysql_database );
    if( ! $this->dblink || $this->dblink->connect_errno )
    {
      $this->error_string .= "\n<br>Could not connect to database server as administrator.\n".mysqli_connect_error();
      return false;
    }
    return true;
  }

  ###
  # Closes a connection to the database
  ###
  # Args are:
  # - None
  # Returns:
  # - None
  ###
  protected function close ()
  {
    if ($this->dblink !== false)
    {
      $this->dblink->close();
      $this->dblink = false;
    }
  }

  ###
  # Creates an object in the database
  ###
  # Args are:
  # - (string) The database table name
  # - (array) a key/value list of the object property names / property values
  # Returns:
  # - (integer) the object ID for success, false for failure
  # - Errors put at $this->error_string
  ###
  public function createObj( $table, $list )
  {
    if( ! is_array( $list ) )
    {
      $this->error_string .= "\n<br>Improper 2nd argument supplied to database->createobj($table)\\n";
      return false;
    }
    $columnList = $this->dblink->real_escape_string( implode( ",", array_keys($list) ) );
    $valueList = "";
    $output = false;
    $arrayValueList = array_values($list);

    foreach( $arrayValueList as $value )
    {
      if( ! is_int($value) )
        $valueList .= "'".$this->dblink->real_escape_string( $value )."',";
      else
        $valueList .= intval($value) .",";
    }
    $valueList = rtrim( $valueList, "," );
    $query = "INSERT INTO $table ($columnList) VALUES ($valueList);";
    $result = $this->dblink->query( $query );

    if( $result )
    {
      $output = (int) $this->dblink->insert_id;
      // update the id, if needed
      $query = "UPDATE $table SET id=$table.dbid WHERE id=0";
      $this->dblink->query( $query );
    }
    else
    {
      $this->error_string .= "\n<br>Error in database->createObj($table): ".$this->dblink->error;
    }
    return $output;
  }

  ###
  # Removes an existing object from the database
  ###
  # Args are:
  # - (string) The database table name
  # - (integer) The object's id number
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  public function destroyobj( $table, $id )
  {
    $id = intval($id);
    $result = $this->dblink->query( "DELETE FROM $table WHERE id = $id;" );
    return $result;
  }

  ###
  # Reads an existing object from the database
  ###
  # Args are:
  # - (string) The database table name
  # - (integer) The object's id number
  # - (integer) The object's turn number (acts as a version number). 'null' if none
  # - (array) a list to hold the object property names / property values
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Data is put in the array given in the arguments
  ###
  public function readObj( $table, $id, $turnNum=null, &$values )
  {
    $id = intval($id);
    $values = array();
    $ignore = array( 'dbid' ); // do not return these database values to the rest of the program
    $query = "SELECT * FROM $table WHERE id=$id";
    if( $turnNum != null )
      $query .= " AND turn=$turnNum";
    if( $result = $this->dblink->query( $query ) )
    {
# what if this returns multiple rows?
      $values = $result->fetch_assoc();
      $result->free();
      foreach( $ignore as $noSeeItem )
        unset( $values[ $noSeeItem ] );
      return true;
    } else {
      $this->error_string .= "\n<br>Error in database->readObj($table #$id): ".$this->dblink->error;
      return false;
    }
  }

  ###
  # Updates an existing object in the database
  ###
  # Args are:
  # - (string) The database table name
  # - (integer) The object's id number
  # - (integer) The turn number identifier of the object to update
  # - (array) a key/value list of the object property names / property values
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  ###
  public function updateObj( $table, $id, $turn, $list )
  {
    if( ! is_array( $list ) )
    {
      $this->error_string .= "\n<br>Improper value list supplied to database->updateobj($table, $id).\n";
      return false;
    }

    $id = intval($id);
    if( $id == 0 )
      return false;

    // set up the query string
    $query = "UPDATE $table SET ";
    // make a list of "column=value" sets in the query string
    foreach( $list as $key=>$value )
    {
      $query .= $this->dblink->real_escape_string( $key )."=";
      if( ! is_int($value) )
        $query .= "'".$this->dblink->real_escape_string( $value )."',";
      else
        $query .= intval($value) .",";
    }
    // add the tail end of the query string
    $query = rtrim( $query, "," ) . " WHERE id=$id";
    if( $turn !== null )
      $query .= " AND turn=$turn";
    $query .= ";";
    $result = $this->dblink->query($query);
    if( ! $result )
    {
      $values = implode( ",", array_values($list) );
      $this->error_string .= "\n<br>Error in database->updateObj($table)\n<br>Where values = $values.\n<br>".$this->dblink->error;
//      $this->error_string .= "<br>".print_r( $list, true );
    }
    return $result;
  }

  ###
  # Retrive the database-row independant identifier for this object
  ###
  # Args are:
  # - (string) The database table name
  # - (string) A third property to use to identify this object
  # - (string) The value of the property
  # Returns:
  # - (integer) the identifier for this object; false for failure
  # - Errors put at $this->error_string
  ###
  public function getID( $table, $property, $value, $property2=null, $value2=null )
  {
    $table = $this->dblink->real_escape_string($table);
    $property = $this->dblink->real_escape_string($property);
    $value = $this->dblink->real_escape_string($value);
    if( ! is_numeric($value) )
      $value = "'".$value."'";
    $query = "SELECT id FROM $table WHERE $property=$value";
    if( ! empty($value2) )
    {
      $property2 = $this->dblink->real_escape_string($property2);
      $value2 = $this->dblink->real_escape_string($value2);
      if( ! is_numeric($value2) )
        $value2 = "'".$value2."'";
      $query .= " AND $property2=$value2";
    }

    if( $result = $this->dblink->query( $query ) )
    {
      $output = false;
      while( $row = $result->fetch_assoc() )
        $output = $row['id'];
      $result->free();
      return intval( $output );
    }
    else
    {
      $this->error_string .= "\n<br>Error in database->getID(table=$table, $property=$value): ".$this->dblink->error;
      return false;
    }
  }

/*
  ###
  # Reads all existing entry objects for this user from the DB.
  # Returns the ID and the given property for each entry
  ###
  # Args are:
  # - (integer) The user's id number
  # - (string) The property name to retrieve
  # - (array) a list to hold the entry IDs / property-values
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Data is put in the array given in the arguments as nested arrays
  ###
  public function getAllEntries( $userID, $property, &$values )
  {
    $property = $this->dblink->real_escape_string($property);
    $userID = intval($userID);
    $values = array();
    $query = "SELECT id,$property FROM ".Entry::table." WHERE player=$userID;";
    if( $result = $this->dblink->query( $query ) )
    {
      while( $row = $result->fetch_assoc() )
        $values[] = array( 'id'=>$row['id'], $property=>$row[$property] );
      $result->free();
      return true;
    } else {
      $this->error_string .= "\n<br>Error in database->getAllEntries(User '$userID', Property '$property')\n".$this->dblink->error;
      return false;
    }
  }

  ###
  # Reads all existing entry objects in chunks.
  # Returns the ID and the given property for each entry
  ###
  # Args are:
  # - (integer) How large a chunk to read
  # - (integer) How far from the end to read
  # - (string) The property name to retrieve
  # - (array) a list to hold the entry IDs / property-values
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Data is put in the array given in the arguments as nested arrays
  ###
  public function getReviewEntries( $chunkSize, $chunkWhere, $property, &$values )
  {
    $values = array();
    $subQuery = "SELECT max(id) FROM ".Entry::table;
    // get the data from the top $chunkSize entries with IDs less than [the last entry] - $chunkWhere
    $query = "SELECT id,$property FROM ".Entry::table." WHERE id<=($subQuery)-$chunkWhere LIMIT $chunkSize";
    if( $result = $this->dblink->query( $query ) )
    {
      while( $row = $result->fetch_assoc() )
        $values[] = array( 'id'=>$row['id'], $property=>$row[$property] );
      $result->free();
      return true;
    } else {
      $this->error_string .= "\n<br>Error in database->getReviewEntries(size $chunkSize, ";
      $this->error_string .= "location $chunkWhere, Property $property)\n".$this->dblink->error;
      return false;
    }
  }
*/

  ###
  # A very general query function. Should not be used.
  ###
  # Args are:
  # - (string) The query string to use
  # - (reference) [optional] a reference to a variable that will recieve the results of the database query
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Return data is not handled
  ###
  public function genquery( $query, &$output=null )
  {
//    $query = $this->dblink->real_escape_string($query);
    if( $sql_result = $this->dblink->query($query) )
    {
      if( $sql_result != null )
      {
        $output = array();
        while( $row = $sql_result->fetch_assoc() )
          $output[] = $row;
        $sql_result->free();
      }
      return true;
    }
    else
    {
      $this->error_string .= "\n<br>Error in database->genquery($query): ".$this->dblink->error;
      return false;
    }
  }
  ###
  # wash( $string )
  # - escapes a string for use in a query
  ###
  # Args are:
  # - (string) The string to escape
  # Returns:
  # - (string) the escaped string
  ###
  public function wash( $string )
  {
    $query = $this->dblink->real_escape_string($string);
    return $query;
  }
}

?>
