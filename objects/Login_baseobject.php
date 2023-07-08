<?php
// TODO
// handle (in the individual object) converting a SET to an array and back
###
# Provides the basic methods for the various objects
###
# create()
# - Creates itself in the database from memory
# createSelf( $inputs )
# - Creates itself from given inputs into memory
# destroy()
# - Destroys this object in the database
# modify( $property, $value )
# - Sets the given object property. If $value is null or missing, then returns the current value
# read()
# - creates this object from the databse
# update()
# - updates the databse with the values from this object
# values()
# - Returns an array of the properties of this object that are to be stored in the database
# getID( $property )
# - Sets the identifier for this object. Useful for a preparation to read() or update()
# HTMLform( $backgroundColor, $sendKeys )
# - direct HTML editing of the object
###

require_once( dirname(__FILE__) . "/Login_database.php" );

class BaseObject
{
  protected $id	= 0; // an identifier that does not change for the life of the object. used to track an object across entries, etc.
  protected $taint	= false;
  protected $autowrite	= false;
  protected $READONLY	= false;

  public $error_string	= "";

  const IS_CLEAN	= false;
  const IS_DIRTY	= true;
  const NOT_IN_DB	= -1;
  const DEBUG_SHOW_WRITTEN_OBJECTS	= false; // set to true to show an object that will update when the script exits

  ###
  # Class constructor
  ###
  # Args are:
  # - (integer) [optional] The id number for this object
  # Returns:
  # - None
  ###
  function __construct( $id = 0, $readOnly = false )
  {
    if( $readOnly )
      $this->READONLY = true;
    if( is_array( $id ) )
    {
      foreach( $id as $property=>$value )
        $this->$property = $value;
      if( ! $this->READONLY )
        $this->taint = self::IS_DIRTY; // so that it will write itself to the DB later
      $this->autowrite = true;
    }
    else
    {
      $this->id = intval( $id );
      $this->taint = self::IS_CLEAN;
    }
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
    if( $this->taint == self::IS_DIRTY && $this->autowrite )
    {
      if( self::DEBUG_SHOW_WRITTEN_OBJECTS )
        echo "Object ".get_class($this)." #".$this->id." to be destroyed.\n";
      $result = $this->update();
      if( $result == false )
        die( "\n<br>Unable to destroy object '".get_class($this)."'. ".$this->error_string );
    }
  }

  ###
  # Creates itself in the database from memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function create()
  {
    if( $this->READONLY )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->create(): Object is read only.";
      return false;
    }
    if( $this->id != 0 )
      return $this->update();
    $database = DataBase::giveme();
    $tableName = constant(get_class($this)."::table");
    $result = $database->createObj( $tableName, $this->values() );
    if( $result === false )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->create(): ".$database->error_string;
      return false;
    }
    $this->id = $result;
    $this->taint = self::IS_CLEAN;
    $this->autowrite = true;
    return true;
  }

  ###
  # Destroys itself from the database
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function destroy()
  {
    if( $this->READONLY )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->destroy(): Object is read only.";
      return false;
    }
    $database = DataBase::giveme();
    $tableName = constant(get_class($this)."::table");
    $result = $database->destroyobj( $tableName, $this->id );
    if( $result === false )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->destroy(): ".$database->error_string;
      return false;
    }
    $this->taint = self::IS_CLEAN;
    $this->autowrite = false;
    return true;
  }

  ###
  # Modifies or retrieves a property of this object
  ###
  # Args are:
  # - (string) The property to adjust
  # - (string) [optional] The value to use. Set to null if no value is to be set
  # Returns:
  # - (string) the adjusted property
  ###
  function modify( $property, $value=null )
  {
    if( ! isset( $this->$property ) )
    {
      $this->error_string .= "\n<br>Attempt to retrieve '$property' in '".get_class($this)."' for item '".$this->id."'. Property does not exist";
      return false;
    }
    if( ! $this->READONLY && isset( $value ) && $this->$property != $value )
    {
      $this->$property = $value;
      $this->taint = self::IS_DIRTY;
    }
    return $this->$property;
  }

  ###
  # Reads itself from the database into memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function read()
  {
    if( $this->id == 0 )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->read(). 'id' field not set.";
      return false;
    }
    $readFromDB = array(); // The values read from the database
    $database = DataBase::giveme();
    $tableName = constant(get_class($this)."::table"); // the table name of this object
    $turn = null; // the turn (version) of the object to retrieve
    if( isset( $this->turn ) )
      $turn = $this->turn;
    $result = $database->readObj( $tableName, $this->id, $turn, $readFromDB );
    if( $result === false )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->read( {$this->id} ). ".$database->error_string;
      $this->autowrite = false;
      return false;
    }
    if( empty( $readFromDB ) )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->readObj( $tableName, {$this->id} ), Empty result returned from database.";
      $this->autowrite = false;
      return false;
    }
    foreach( $readFromDB as $key=>$value )
      $this->$key = $value;
    $this->taint = self::IS_CLEAN;
    return true;
  }

  ###
  # Updates itself in the database from memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function update()
  {
    if( $this->READONLY )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->create(): Object is read only.";
      return false;
    }
    if( $this->id == 0 )
      return $this->create();
    $database = DataBase::giveme();
    $tableName = constant(get_class($this)."::table");
    $turn = null;
    if( isset( $this->turn ) )
      $turn = $this->turn;
    $result = $database->updateObj( $tableName, $this->id, $turn, $this->values() );
    if( $result === false )
    {
      $this->error_string .= "\n<br>Error in ".get_class($this)."->update(). ".$database->error_string;
      return false;
    }
    $this->taint = self::IS_CLEAN;
    return true;
  }

  ###
  # Sets the identifier for this object. Useful for a preparation to read() or update()
  ###
  # Args are:
  # - (string) a property to use to further identify oneself. 
  # - (string) a second property to use to further identify oneself. 
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function getID( $property, $property2=null )
  {
    $thisClass = get_class($this);
    if( ! property_exists( $thisClass, $property ) )
    {
      $this->error_string .= "\n<br>Error in ".$thisClass."->getID(). Missing '$property' field.";
      return false;
    }
    $database = DataBase::giveme();
    $tableName = constant($thisClass."::table");
    if( ! empty( $property2 ) )
      $result = $database->getID( $tableName, $property, $this->$property, $property2, $this->$property2 );
    else
      $result = $database->getID( $tableName, $property, $this->$property );
    if( $result === false )
    {
      $this->error_string .= "\n<br>Error in ".$thisClass."->getID( '$property' ). ".$database->error_string;
      return false;
    }
    $this->id = $result;

    // if $result = 0 then that means there were no IDs found
    if( $result === 0 )
      return false;

    return true;
  }

  ###
  # Displays an HTML form for a sysadmin to directly edit this object
  # Also accepts the results of that form to directly edit the database for that object
  ###
  # Args are:
  # - (string) [optional] Highlight color, "#"+hex format
  # - (bool) [optional] Outputs the keys in a heading format for printing in columnar format
  # Returns:
  # - (string) the HTML to display
  ###
  function HTMLform( $background="#ffffff", $sendKeys=false )
  {
    // put any keys to skip over in the $exceptionList
    $exceptionList = array( 'id' );
    $output = "<tr style='background-color:$background'>";
    // this is the first element sent
    if( $sendKeys )
      $output .= "<td>Object + ID</td>";
    else
      $output .= "<td>".$this::table." #".$this->id."</td>";
    foreach( $this as $key => $value )
    {
      if( array_search($key, $exceptionList) !== false )
        continue; // skip if the current key is in the list of exceptions
      if( $sendKeys )
      {
        $output .= "<td";
        if( strlen($key) > 10 )
          $output .= " style='font-size:small'";
        $output .= ">$key</td>";
        continue;
      }
      $displaySize = 3;
      if( is_array($value) )
        $value = serialize( $value );
      if( ! is_numeric($value) && ! is_bool($value) && ! empty($value) )
        $displaySize = 20;
      $value = htmlentities($value,ENT_QUOTES|ENT_HTML5);
      $output .= "<td><input type='text' name='' value='$value' style='background-color:$background' size='$displaySize'></td>";
    }
    return $output."</tr>\n";
  }
}

?>
