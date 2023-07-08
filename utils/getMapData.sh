#!/usr/bin/php -q
<?php
###
# Grabs the list of units buildable at the given year for the given empire
###

if( ! isset($argv[3]) )
{
  echo "\nCreates a data-file for the map page.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GAME TURN FILE\n\n";
  exit(1);
}

require_once( dirname(__FILE__) . "/../objects/gameDB.php" );
$database = gameDB::giveme();

$game = intval($argv[1]);
$turn = intval($argv[2]);
$file = $argv[3];

$nodeList = array(); // format is [emp_id] => emp_name
$linkList = array(); // format is [] => array( source, target, strength )

// dot colors based on empires
$colors = array();
$colors["Andromedan"] = array("background"=>"Green","ship"=>"Black");
$colors["Barbarian"] = array("background"=>"White","ship"=>"Purple");
$colors["Borak"] = array("background"=>"","ship"=>"");
$colors["Britanian"] = array("background"=>"Turquoise","ship"=>"Black");
$colors["Canadi'en"] = array("background"=>"White","ship"=>"Red Stripes");
$colors["Carnivon"] = array("background"=>"Green","ship"=>"Yellow");
$colors["Deltan"] = array("background"=>"Turquoise","ship"=>"White");
$colors["Federation"] = array("background"=>"Blue","ship"=>"Black");
$colors["Flivver"] = array("background"=>"White","ship"=>"Turquoise");
$colors["Frax"] = array("background"=>"Gray","ship"=>"Purple");
$colors["General"] = array("background"=>"White","ship"=>"Blue");
$colors["Gorn"] = array("background"=>"White","ship"=>"Red");
$colors["Hispaniolan"] = array("background"=>"","ship"=>"");
$colors["Hydran"] = array("background"=>"Green","ship"=>"White");
$colors["ISC"] = array("background"=>"Yellow","ship"=>"Black");
$colors["Jindarian"] = array("background"=>"Gray","ship"=>"Black");
$colors["Klingon"] = array("background"=>"Black","ship"=>"White");
$colors["Kzinti"] = array("background"=>"White","ship"=>"Black");
$colors["LDR"] = array("background"=>"White","ship"=>"Green");
$colors["Lyran"] = array("background"=>"Yellow","ship"=>"Green");
$colors["Orion"] = array("background"=>"Blue","ship"=>"White");
$colors["Paravian"] = array("background"=>"Red","ship"=>"Yellow");
$colors["Peladine"] = array("background"=>"Black","ship"=>"Blue");
$colors["Quari"] = array("background"=>"Tan","ship"=>"Black");
$colors["Romulan"] = array("background"=>"Red","ship"=>"Black");
$colors["Seltorian"] = array("background"=>"White","ship"=>"Orange");
$colors["Sharkhunter"] = array("background"=>"Purple","ship"=>"Black");
$colors["Tholian"] = array("background"=>"Red","ship"=>"White");
$colors["Triaxian"] = array("background"=>"Purple","ship"=>"White");
$colors["Vudar"] = array("background"=>"Black","ship"=>"Yellow");
$colors["WYN"] = array("background"=>"Yellow","ship"=>"Red");
$colors["Maesron"] = array("background"=>"Red","ship"=>"White");
$colors["Koligahr"] = array("background"=>"Blue","ship"=>"White");
$colors["Trobrin"] = array("background"=>"Grey","ship"=>"White");
$colors["Vari"] = array("background"=>"Green","ship"=>"Black");
$colors["Probr"] = array("background"=>"Yellow","ship"=>"Red");
$colors["Chlorophon"] = array("background"=>"Brown","ship"=>"White");
$colors["Drex"] = array("background"=>"Grey","ship"=>"Purple");
$colors["Alunda"] = array("background"=>"Orange","ship"=>"White");
$colors["Hiver"] = array("background"=>"Blue","ship"=>"Grey");
$colors["Sigvirion"] = array("background"=>"Green","ship"=>"Yellow");
$colors["Loriyill"] = array("background"=>"Brown","ship"=>"Pink");
$colors["Souldra"] = array("background"=>"Purple","ship"=>"Yellow");
$colors["Iridani"] = array("background"=>"Pink","ship"=>"Green");
$colors["Ymatrian"] = array("background"=>"Pink","ship"=>"White");
$colors["Worb"] = array("background"=>"Pink","ship"=>"Purple");
$colors["FRA"] = array("background"=>"Blue","ship"=>"Red");
$colors["Singer"] = array("background"=>"Purple","ship"=>"Pink");
$colors["Juggernaught"] = array("background"=>"White","ship"=>"Blue");

$columns = "borders,IF( LOCATE(' ',fullname)>0,SUBSTRING(fullname,1,LOCATE(' ',fullname)),fullname) AS fullname,race,sfbdrama_empire.id";
$query = "SELECT $columns FROM sfbdrama_empire JOIN player ON player=player.id WHERE game=$game AND turn=$turn";

  $result = $database->genquery( $query, $empire );
  if( ! $result )
  {
    if( $SHOW_DB_ERRORS )
      echo "Could not get empire info: ".$database->error_string."\n";
    else
      echo "Could not get empire info.\n";
    exit(1);
  }

// expand the borders
foreach( $empire as $key=>$empireData )
{
  $nodeList[ $empireData['id'] ] = $key;
  $expand = explode( ",", $empireData['borders'] );
  for( $i=0; $i<count($expand); $i+=2 )
  {
    if( $expand[$i+1] == 0 )
      continue; // skip strength-0 links
    if( in_array( array( $expand[$i], $empireData['id'], $expand[$i+1] ), $linkList ) )
      continue; // skip links that go the opposite way
    $linkList[] = array( $empireData['id'], $expand[$i], $expand[$i+1] );
  }
}

// form the output
$output = "{\n  \"nodes\": [\n";
foreach( $nodeList as $key=>$val )
{
  $output .= "    {\"id\": \"$key\", \"name\": \"".$empire[$val]['race']."\\n".$empire[$val]['fullname'];
  $output .= "\", \"bg\": \"".$colors[ucfirst($empire[$val]['race'])]['background']."\", \"fg\": \"";
  $output .= $colors[ucfirst($empire[$val]['race'])]['ship']."\"},\n";
}

$output .= "  ],\n  \"links\": [\n";
foreach( $linkList as $val )
  $output .= "    {\"source\": $val[0], \"target\": $val[1], \"value\": $val[2]},\n";
$output .= "   ]\n}";

file_put_contents( $file, $output );

echo "\n".count($nodeList)." Empires, ".count($linkList)." links\n";
echo "Potential file name: ".substr(md5(time()),0,12).".json\n\n";

?>
