<?php
/*
 *    This file will be hitted by the Braintree Payment Processor for recurring payment
 */

session_start();

require_once '../civicrm.config.php';

/* Cache the real UF, override it with the SOAP environment */

$config = CRM_Core_Config::singleton();

// for the destination verfication uncomment below code
/*
CRM_Core_Payment_BraintreeIPN::destinationVerfication();
die;
*/

//$rawPostData = file_get_contents('php://input');
$ipn = new CRM_Core_Payment_BraintreeIPN();
//$ipn->main();
/*
 *    To test successfull subscrition charged uncomment benlow code.
 *    And change the $subscriptionId value 
 
$subscriptionId = 'bg9tb2';
$notificationArray = CRM_Core_Payment_BraintreeIPN::testNotification($subscriptionId);
 * 
 */
$notificationArray = CRM_Core_Payment_BraintreeIPN::processNotification($subscriptionId);
$ipn->main($notificationArray);


