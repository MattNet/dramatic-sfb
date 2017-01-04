<?php

###
# Sends an email with the given message to the given recipient
###
# Args are:
# - (string) The body of the message
# - (string) The recipient of the message
# - (string) The subject of the message
# - (integer) [optional] An error level from a previous attempt at this function. This allows multiple different attempts to send the email
# Returns:
# - (bool) true for success, false for failure
###
# We use 'error levels' in this function, because there is no way to track the 
# success of sending an email by a given mail function, until we try that mail 
# function. Upon failure of one method of trying, we recursively call this
# function in order to try a different mail function.
###
function send_email( $emailBody, $emailTo, $emailSubject, $level = 0 )
{
  global $EMAIL_ADDRESS;
  $emailBody = wordwrap($emailBody, 70);
  $host = $EMAIL_ADDRESS;
  $header = "";
  $result = false;

  if( empty($host) )
  {
    if( isset($_SERVER['HTTP_HOST']) )
      $host = $_SERVER['HTTP_HOST'];
    else
      return $result;	// could not find a hostname to use
  }

  $header = "From: webmaster@$host\r\n";
  $header .= "Reply-To: noreply@$host\r\n";

  if( $level == 0 && function_exists('imap_mail') )
  {
    $result = imap_mail( $emailTo, $emailSubject, $emailBody, $header );
    // if it failed to send the email, try a different method
    if( ! $result )
      $result = send_email( $emailBody, $emailTo, $emailSubject, ++$level );
    return $result;
  }
  else if( $level == 1 && function_exists('mail') )
  {
    $result = mail( $emailTo, $emailSubject, $emailBody, $header );
    // if it failed to send the email, try a different method
    if( ! $result )
      $result = send_email( $emailBody, $emailTo, $emailSubject, ++$level );
    return $result;
  }
  else
  {
    // otherwise error out
    return false;
  }
}

?>
