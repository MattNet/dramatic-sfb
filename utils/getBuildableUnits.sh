#!/usr/bin/php -q
<?php
###
# Grabs the list of units buildable at the given year for the given empire
###

if( ! isset($argv[3]) )
{
  echo "\nGrabs the high/low/and average economy of a game.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." EMPIRE_TYPE YEAR FILE\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../objects/gameDB.php" );
$database = gameDB::giveme();

$year = intval($argv[2]);
$empire = $database->wash($argv[1]);
$file = $argv[3];

$columns = "designator,BPV,sizeClass,commandRating,yearInService,obsolete,baseHull,carrier";
$query = "SELECT $columns FROM sfbdrama_shipdesign WHERE empire='$empire' AND yearInService<='$year' AND (obsolete>='$year' OR obsolete=0)";

  $result = $database->genquery( $query, $fleet );
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get unit list: ".$database->error_string."\n";
    else
      echo "Could not get unit list.\n";
    exit(1);
  }

$output = $columns."\n";
foreach( $fleet as $fleetData )
  $output .= implode( ",", $fleetData )."\n";

file_put_contents( $file, $output );

echo "\nEmpire $empire, year Y$year, ".count($fleet)." units\n\n";

?>
