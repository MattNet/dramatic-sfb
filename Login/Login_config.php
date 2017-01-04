<?php
/*
Configuration file
*/

$BUSINESS_GIVEN_NAME = "SFB Dramatic Universe Campaign";	// The name of the business hosting the website. Used in emails
$DEFAULT_PRIVILEDGE = "Iron";	// the priviledge level (below) that new accts are given
$DEFAULT_THEME = "simple";
$LOGIN_EXPIRE_TIME = 3600;	// time that a player can stay logged in, in seconds. An hour is 3600 seconds
$MUST_VERIFY_EMAIL = true;	// set to true so new accts must respond to a letter sent to their reported email acct
$MUST_APROVE_ACCTS = false;	// set to true so new accts must be approved by someone with 'canApprove' privs
$RENDER_UNAPPROVED_IF_LOST_PASSWORD = false;	// make the user unapproved if they lost their password
$REPORT_UPLOAD_SIZE = true;	// set to true to display the file upload size to the users
$SHOW_DB_ERRORS = true;	// set to true to display database errors
$THEME_DIRECTORY = "/style/";	// Directory the CSS files and their images can be found
$TIMEZONE = "America/Los_Angeles";	// per the list at http://php.net/manual/en/timezones.php
$LOGIN_VERSION = "Mattnet Login v0.91b";	// The software version

$LOGIN_EXIT_FILE = "/campaign/menu.php";	// The file to access after the login process is successful
$LOGIN_START_FILE = "/index.php";	// The file to access the login process, used if unsuccessful

###
# Database Items
###
$MYSQL_server = "localhost";
//$MYSQL_server = "mysql.sfbdrama.mattnet.org";
$MYSQL_database = "DATABASENAME";
$MYSQL_user_member = "MEMBERNAME";
$MYSQL_pw_member = "MEMBERPASSWORD";
$MYSQL_user_admin = "ADMINNAME";
$MYSQL_pw_admin = "ADMINPASSWORD";


###
# Priviledge Levels
###
# Available privs are:
#
# advance - Can advance games they create and administrate the players of games they create
# advanceAll - May advance any game and adminstrate the players of any game
# anyRace - Join games as any race
# basicRace - Join games as generic race
# canApprove - Can approve of accounts, which then allows those accounts to create entries
# changeAcct - Change any user acct
# close - Close games that they create
# closeAll - Close any games
# create - Create games
# deleteAcct - Delete any user acct
# elevate - May adjust the priviledge levels of another acct (but not yourself)
###
$PRIVILEDGE_LEVELS = array(
"Admin" => array( 'advance', 'advanceAll', 'anyRace', 'basicRace', 'canApprove', 'changeAcct', 'close', 'closeAll', 'create', 'deleteAcct', 'elevate' ), // can do anything
"Gold" => array( 'advance','anyRace', 'basicRace', 'close', 'create' ), // Can create and adjust games
"Silver" => array( 'anyRace', 'basicRace' ), // Can play as a complete empire
"Iron" => array( 'basicRace' ) // Can only play as a collection of civilian ships
);


###
# Available Themes
###
# These are represented by CSS files that adjust the look of the HTML presentation
###
$THEMES = array(
"simple"
);

?>
