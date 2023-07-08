<?php
###
# This sets up the database and then installs an administration user into the database
###

# These are the login credentials to the MySQL server for a user that has CREATE and GRANT privs
$DATABASE_SUPERUSER_NAME = "root";
$DATABASE_SUPERUSER_PASSWORD = "stirlingsilver";

# these are the login credentials for the first user of the software
$SOFTWARE_SUPERUSER_LOGIN = "Matt";
$SOFTWARE_SUPERUSER_PASSWORD = "amanda";

require_once( dirname(__FILE__) . "/objects/Login_auth.php" );
require_once( dirname(__FILE__) . "/objects/user.php" );
require_once( dirname(__FILE__) . "/Login/Login_config.php");
require_once( dirname(__FILE__) . "/campaign_config.php");
date_default_timezone_set($TIMEZONE);

/*
// remove old versions of the database
echo "Removing old versions of the database\n<p>";
exec( "mysql -u $DATABASE_SUPERUSER_NAME -p$DATABASE_SUPERUSER_PASSWORD < objects/database_erase.sql", $outputText, $returnVar );
*/
// Set up a new version of the database
echo "Setting up the database\n<p>";
exec( "mysql -u $DATABASE_SUPERUSER_NAME -p$DATABASE_SUPERUSER_PASSWORD < objects/database_setup.sql", $outputText, $returnVar );


if( isset($returnVar) && $returnVar )
{
  echo "MySQL error while setting up database:\n<br>";
  $outputTextString = implode( "<br>", $outputText );
  echo "$outputTextString\n<br>";
  die();
}

// remove any files present from a previous iteration
echo "Removing unneeded files\n<p>";

// scans the directory, skips files starting with ".", and then deletes the remaining files
$dirListing = scandir( $BID_IN_DIRECTORY );
foreach( $dirListing as $fileItem )
{
  if( strpos( $fileItem, "." ) === 0 )
    continue;
  unlink( $BID_IN_DIRECTORY."/".$fileItem);
}

// add in the software's super-user
echo "Setting up the administrator\n<p>";

$auth = new Auth();
$auth->plainPassword( $SOFTWARE_SUPERUSER_PASSWORD );
$password = $auth->encryptedPassword();
$playerA = new User( array(
          'email'=>'','fullName'=>'','username'=> $SOFTWARE_SUPERUSER_LOGIN, 'isApproved'=> true, 'isVerified'=> true,
          'signupDate'=> date( "Y-m-d H:i:s" ), 'priviledges'=> 'Admin', 'pass'=>$password, 'theme'=>$DEFAULT_THEME
        ) );
$result = $playerA->create();

if( ! $result )
{
  echo $playerA->error_string;
  die();
}

// add in the software's super-user
echo "Importing the data-set.\n<br>";

exec( "./utils/unit_import.php", $outputText );

$outputTextString = implode( "<br>", $outputText );
echo $outputTextString."<p>";

echo "Finished.";
$baseURL = "http://".$_SERVER['SERVER_NAME']."/".substr( $_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']) );
echo " Now direct your browser to <a href='$baseURL'>$baseURL</a> and sign in as '$SOFTWARE_SUPERUSER_LOGIN' and then adjust your account settings\n<br>";

?>
