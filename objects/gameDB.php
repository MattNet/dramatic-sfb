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
# calcEmpireScore( $game, $turn )
# - Calculates the score of the given empire on the given turn
# genquery( $query )
# - A very general query function. Should not be used
###

require_once( dirname(__FILE__) . "/Login_database.php" );

class gameDB extends DataBase
{
// List of object class names. Used for scrubbing a turn to bare metal. These are the classes to keep
  static $allObjects = array(
                         "Empire", "Encounter", "Game", "Orders", "User", "Ship", "ShipDesign"
                       );

  ###
  # Class constructor
  # private to prevent direct construction of this object
  ###
  protected function __construct( $asAdmin )
  {
    parent::__construct($asAdmin);
  }

  ###
  # Reads all existing objects from the given table that are in the given game and the given turn
  ###
  # Args are:
  # - (integer) The game's id number
  # - (integer) The turn number
  # - (string) The table name
  # - (array) a list to hold the object property names / property values
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Data is put in the array given in the arguments as nested arrays
  ###
  public function getAllGameTurn( $game, $turn, $table, &$values )
  {
    $game = intval($game);
    $turn = intval($turn);
    $values = array();
    $query = "select id from $table where game=$game and turn=$turn;"; // return only the ID of the object
    if( $table == "history" ) // get all of the "history" events up to this point
      $query = "select id from history where game=$game and turn<=$turn;";
    if( $result = $this->dblink->query( $query ) )
    {
      while( $row = $result->fetch_assoc() )
        $values[] = $row['id'];
      $result->free();
      return true;
    } else {
      $this->error_string .= "\n<br>Error in gameDB->getAllGameTurn(Game $game, Turn $turn, Table $table)\n".$this->dblink->error;
      return false;
    }
  }

  ###
  # Remove the objects from the database that match the given turn
  # (Used to re-do a turn)
  ###
  # Args are:
  # - (int) The game to look for
  # - (int) The turn to look for
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Return data is not handled
  ###
  public function blankTurn( $game, $turn )
  {
    $tableNames = array();
    $game = intval($game);
    $turn = intval($turn);
    foreach( gameDB::$allObjects as $obj )
    {
      if( ! property_exists($obj, 'game') || ! property_exists($obj, 'turn') )
        continue;
      $query = "DELETE FROM ".$obj::table." WHERE game=$game AND turn=$turn";
      if( ! $sql_result = $this->dblink->query($query) )
      {
        $this->error_string .= "\n<br>Error in gameDB->blankTurn(Game $game, Turn $turn), table '".$obj::table."': ".$this->dblink->error;
        return false;
      }
    }
    return true;
  }

  ###
  # Calculates the score of the given empire on the given turn
  ###
  # Args are:
  # - (int) The game to look for
  # - (int) The current turn to use
  # - (int) The empire ID to use
  # Returns:
  # - (int) The number calculated as the empire's score
  # - Errors put at $this->error_string
  ###
  public function calcEmpireScore( $game, $turn, $empire )
  {
    $output = 0;

    $query = "SELECT borders, income, storedEP FROM ".Empire::table." WHERE game=$game AND turn=$turn AND id=$empire";
    if( $result = $this->dblink->query( $query ) )
    {
      $row = $result->fetch_assoc();
      $borders = explode( ",", $row['borders'] );
    // perform the score calculation here
      $output += $row['storedEP']; // Stockpile is worth 1 pt per EP
      $output += $row['income'] * 5; // Income is worth 5 pts per EP

      while( ! empty($borders) )
      {
        $value = array_shift( $borders ); // throw away the first of each value pair
        $value = array_shift( $borders );
        $output += $value * 150; // Borders are worth 150 pts per border
      }
    // end calculation
      $result->free();
    } else {
      $this->error_string .= "\n<br>Error in gameDB->calcEmpireScore(Game $game, Turn $turn, Empire $empire)";
      $this->error_string .= ", table ".Empire::table.": ".$this->dblink->error;
      return false;
    }

    $subquery = "SELECT design FROM ".Ship::table." WHERE empire=$empire AND turn=$turn AND game=$game";
    $query = "SELECT SUM(BPV) FROM ".ShipDesign::table." inner join ($subquery) ships on ".ShipDesign::table.".id=ships.design";
    if( $result = $this->dblink->query( $query ) )
    {
      $row = $result->fetch_assoc();
    // perform more score calculation here
      $output += $row['SUM(BPV)']; // Ship BPVs are worth 1 pt per BPV
    // end calculation
      $result->free();
    } else {
      $this->error_string .= "\n<br>Error in gameDB->calcEmpireScore(Game $game, Turn $turn, Empire $empire)";
      $this->error_string .= ", table ".Ship::table.": ".$this->dblink->error;
      return false;
    }

    unset($borders);
    return $output;
  }

  ###
  # Pulls objects from the preceeding turn into the given turn
  ###
  # Args are:
  # - (int) The game to look for
  # - (int) The current turn to use
  # - (int) The 'currentTurn' property of the game
  # Returns:
  # - (boolean) true for success, false for failure
  # - Errors put at $this->error_string
  # - Return data is not handled
  ###
  public function forwardTurn( $game, $turn )
  {
    // Set true if we set the game-object's 'currentTurn' property to
    // $turn, but only if the $turn is larger than the old value
    // Set to false if we always set the 'currentTurn' to the new value
    $LEAVE_GAME_TURN_IF_LARGER = false;

    $tableNames = array();
    $game = intval($game);
    $turn = intval($turn);
    $lastTurn = $turn-1;
    $ignoreList = array( 'Orders', 'Encounter', 'History' );

    foreach( gameDB::$allObjects as $obj )
    {
      if( ! property_exists($obj, 'game') || ! property_exists($obj, 'turn') )
        continue;
      // exclude any marked objects
      if( array_search( $obj, $ignoreList ) !== false )
        continue;
      // this creates a temporary table, loads the appropriate rows into it, adjusts the turn, 
      // and then puts those rows back into the original table
      $tempTableName = "tmptable_".$obj::table;
      $queries = array(
          // create a temp table and populate it with the stuff from the old turn
          "CREATE TEMPORARY TABLE $tempTableName SELECT * FROM ".$obj::table." WHERE game=$game AND turn=$lastTurn",
          // set all the dbid's in the temp table to 0, so they will be updated correctly when merged to the main table
          "UPDATE $tempTableName SET dbid=0",
          "UPDATE $tempTableName SET turn=$turn",	// set the new turn in the temp table
          "DELETE FROM ".$obj::table." WHERE game=$game AND turn=$turn",	// remove the old values in the main table for the new turn
          "INSERT INTO ".$obj::table." SELECT * FROM $tempTableName",	// merge the temp table and the main table
          "DROP TEMPORARY TABLE IF EXISTS $tempTableName"	// remove the temp table
        );
      // edit the "select *" statement to exclude dead things if possible
      if( property_exists($obj, 'isDead') )
        $queries[0] .= " AND isDead=false";
      // edit the "select *" statement to exclude "deleted" empires, if possible
      if( property_exists($obj, 'status') )
        $queries[0] .= " AND status!='delete'";
      // loop through the above queries, doing one at a time
      foreach( $queries as $query )
        if( ! $sql_result = $this->dblink->query($query) )
        {
          $this->error_string .= "\n<br>Error in gameDB->forwardTurn(Game $game, Turn $turn),";
          $this->error_string .= " table '".$obj::table."': ".$this->dblink->error;
          return false;
        }
    }

    // get the highest "currentTurn" turn of the game
    $query = "SELECT MAX(currentTurn) AS currentTurn FROM game WHERE id=$game";
    if( $result = $this->dblink->query( $query ) )
    {
      $values = $result->fetch_assoc();
      $result->free();
      $currentTurn = $values['currentTurn'];
    }
    // update to the "real" turn of the game
    if( ! $LEAVE_GAME_TURN_IF_LARGER || $currentTurn < $turn )
    {
      $query = "UPDATE ".Game::table." SET currentTurn=$turn WHERE id=$game;";
      if( ! $sql_result = $this->dblink->query($query) )
      {
        $this->error_string .= "\n<br>Error in gameDB->forwardTurn(Game $game, Turn $turn),";
        $this->error_string .= " updating 'currentTurn' to '$turn': ".$this->dblink->error;
        return false;
      }
    }
    return true;
  }


  ###
  # Finds games where the player ID is the moderator
  ###
  # Args are:
  # - (int) the player identifier
  # Returns:
  # - (array) A list of game IDs and game names. returns false on error
  # - Errors put at $this->error_string
  ###
  public function findModeratedGames( $id )
  {
    $output = array();
    $query = "SELECT id,gameName FROM ".Game::table." WHERE moderator=$id";
    $result = $this->genquery( $query, $out );
    if( $result === false )
    {
      $this->error_string = "Error in gameDB::openPositions()\n".$this->error_string;
      return false;
    }
    else if( isset($out[0]) )
    {
      foreach( $out as $row )
        $output[] = array( 'gameID'=>$row['id'], 'gameName'=>$row['gameName'], 'mod'=>true );
    }
    unset( $out );
    return $output;
  }

  ###
  # Finds games that are not closed
  ###
  # Args are:
  # - none
  # Returns:
  # - (array) A list of game IDs and game names. returns false on error
  # - Errors put at $this->error_string
  ###
  public function findNonClosedGames()
  {
    $output = array();
    $query = "SELECT id,gameName FROM ".Game::table." WHERE NOT status='".Game::STATUS_CLOSED."'";
    $result = $this->genquery( $query, $out );
    if( $result === false )
    {
      $this->error_string = "Error in gameDB::findNonClosedGames()\n".$this->error_string;
      return $output;
    }
    else if( isset($out[0]) )
    {
      foreach( $out as $row )
        $output[] = array( 'id'=>$row['id'], 'gameName'=>$row['gameName'] );
    }
    unset( $out );
    return $output;
  }

  ###
  # Retrieves some game data from a game identifier
  ###
  # Args are:
  # - (int) the game identifier
  # Returns:
  # - (array) A list of game names, status's, and turns. returns false on error
  # - Errors put at $this->error_string
  ###
  public function getGameData( $id )
  {
    $output = array();
    $query = "SELECT gameName,status,currentTurn FROM ".Game::table." WHERE id=$id";
    $result = $this->genquery( $query, $out );
    if( $result === false )
    {
      $this->error_string = "Error in gameDB::getGameData( $id )\n".$this->error_string;
      return false;
    }
    else if( isset($out[0]) )
    {
      foreach( $out as $row )
        $output[] = array( 'currentTurn'=>$row['currentTurn'], 'gameName'=>$row['gameName'], 'status'=>$row['status'] );
    }
    unset( $out );
    return $output;
  }

  ###
  # Finds the empire objects with the given player ID
  ###
  # Args are:
  # - (int) the player identifier
  # Returns:
  # - (array) A list of empire IDs, game IDs, empire names, and the game turn. returns false on error
  # - Errors put at $this->error_string
  ###
  public function getEmpiresWithPlayer( $id )
  {
    $output = array();
    $subQuery = "select MAX(empire2.turn) from ".Empire::table." empire2 WHERE empire2.id = empire.id";
    $query = "SELECT empire.id, empire.game, empire.race, empire.textName, empire.turn FROM ".Empire::table;
    $query .= " empire WHERE empire.player=$id AND empire.turn=($subQuery) GROUP BY id";
    $query = $this->dblink->real_escape_string($query);
    $result = $this->genquery( $query, $out );
    if( $result === false )
    {
      $this->error_string = "Error in gameDB::getEmpiresWithPlayer( $id )\n".$this->error_string;
      return false;
    }
    else if( isset($out[0]) )
    {
      foreach( $out as $row )
        $output[] = array( 'empID'=>$row['id'], 'gameID'=>$row['game'], 'textName'=>$row['textName'],
                           'raceName'=>$row['race'], 'mod'=>false, 'empTurn'=>$row['turn']
                         );
    }
    unset( $out );
    return $output;
  }

  ###
  # Finds games with open positions
  ###
  # Args are:
  # - none
  # Returns:
  # - (array) A list of game IDs and game names. returns false on error
  # - Errors put at $this->error_string
  ###
  public function openPositions()
  {
    $output = array();
    // the subquery counts the number of unassigned player positions in the game with ID given in the main query
// may need to add a clause for where there are some borders with someone
    $subQuery = "select count(*) from ".Empire::table." empire WHERE empire.player = 0 AND empire.game = game.id";
    // the query grabs the info of each game that is either [ progressing and has any unassigned positions ] or is open
    $query = "SELECT id,gameName FROM ".Game::table." game WHERE ( game.status='progressing' AND 1 <= ($subQuery) ) OR game.status='open'";
    $result = $this->genquery( $query, $out );
    if( $result === false )
    {
      $this->error_string = "Error in gameDB::openPositions() \n".$this->error_string;
      return false;
    }
    else if( isset($out[0]) )
    {
      foreach( $out as $row )
        $output[ $row['id'] ] = array( 'id'=>$row['id'], 'gameName'=>$row['gameName'] );
    }
    unset( $out );
    return $output;
  }

}

?>
