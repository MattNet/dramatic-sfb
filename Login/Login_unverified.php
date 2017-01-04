<?php
include_once( dirname(__FILE__) . "/Login_config.php" );

$TEMPLATE_FILE = dirname(__FILE__) . "/templates/Login_unverified.html";
$EMAIL_TEMPLATE = dirname(__FILE__) . "/templates/Login_template_verify_email.txt";
$GOTO_ON_BACK = "/Login/Login_acctedit.php";
$GOTO_ON_FAIL = $LOGIN_START_FILE;
$GOTO_ON_REVERIFY = "/Login/Login_verify.php"; // script that the user hits to verify the acct
$KEY_GET_STRING = "key"; // Post string for user's verification key

include_once( dirname(__FILE__) . "/Login_common.php" );
include_once( dirname(__FILE__) . "/Login_email.php" );

$msgBody = file_get_contents($EMAIL_TEMPLATE);
// when replacing template text, the order to use is: [User's Name], [URL to visit], [Server's business-name]
$urlToVisit = "http://{$_SERVER['SERVER_NAME']}$GOTO_ON_REVERIFY?$KEY_GET_STRING=".$userObj->modify('verifyKey');
$msgBody = sprintf( $msgBody, $userObj->modify('fullName'), $urlToVisit, $BUSINESS_GIVEN_NAME );

$result = send_email( $msgBody, $userObj->modify('email'), "Account Verification for ".$_SERVER['HTTP_HOST'] );

if( ! $result )
  $tag_error = "<div class=\"error\">Error sending email. There is no mail service set up, or there is no way to access it.</div>";

$tag_back = "<a href='$GOTO_ON_BACK'>Back</a>";


header( 'Cache-Control: no-cache, must-revalidate' );
include( $TEMPLATE_FILE );
exit();
?>
