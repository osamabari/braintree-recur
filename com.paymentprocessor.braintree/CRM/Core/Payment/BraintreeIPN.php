<?php

require_once 'lib/Braintree.php';
 
require_once 'CRM/Core/Payment/BaseIPN.php';


class CRM_Core_Payment_BraintreeIPN extends CRM_Core_Payment_BaseIPN {

  function __construct() {
    parent::__construct();

  }

  function main($notification,$component = 'contribute') {
      
    $x_subscription_id = $notification["subscription"]->_attributes["id"];
    $trigger = $notification["kind"];
    if ($x_subscription_id) {
      //Approved

      $ids = $objects = array();
      $input['component'] = $component;
      $input['subscription_id'] = $x_subscription_id;
      $input['trigger'] = $trigger;
      // load post vars in $input
      $this->getInput($input, $ids);

      // load post ids in $ids
      $this->getIDs($ids, $input);

      $paymentProcessorID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType',
                                                        'Braintree', 'id', 'name'
                                                        );

      if (!$this->validateData($input, $ids, $objects, TRUE, $paymentProcessorID)) {
        return FALSE;
      }

      if ($component == 'contribute' && $ids['contributionRecur']) {
        // check if first contribution is completed, else complete first contribution
        $first = TRUE;
        if ($objects['contribution']->contribution_status_id == 1) {
          $first = FALSE;
        }
        return $this->recur($input, $ids, $objects, $first);
      }
    }
  }

  function recur(&$input, &$ids, &$objects, $first) {
    $recur = &$objects['contributionRecur'];

    // do a subscription check
    if ($recur->processor_id != $input['subscription_id']) {
      CRM_Core_Error::debug_log_message("Unrecognized subscription.");
      echo "Failure: Unrecognized subscription<p>";
      return FALSE;
    }

    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    $transaction = new CRM_Core_Transaction();

    $now = date('YmdHis');

    // fix dates that already exist
    $dates = array('create_date', 'start_date', 'end_date', 'cancel_date', 'modified_date');
    foreach ($dates as $name) {
      if ($recur->$name) {
        $recur->$name = CRM_Utils_Date::isoToMysql($recur->$name);
      }
    }

    //load new contribution object if required.
    if (!$first) {
      // create a contribution and then get it processed
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->contact_id = $ids['contact_id'];
      $contribution->financial_type_id  = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];
      $contribution->receive_date = $now;
      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = $objects['contribution']->payment_instrument_id;
      $contribution->amount_level = $objects['contribution']->amount_level;
      $contribution->address_id = $objects['contribution']->address_id;
      $contribution->honor_contact_id = $objects['contribution']->honor_contact_id;
      $contribution->honor_type_id = $objects['contribution']->honor_type_id;
      $contribution->campaign_id = $objects['contribution']->campaign_id;

      $objects['contribution'] = &$contribution;
    }
    $objects['contribution']->invoice_id = md5(uniqid(rand(), TRUE));
    $objects['contribution']->total_amount = $input['amount'];
    $objects['contribution']->trxn_id = $input['trxn_id'];

   
    $sendNotification = FALSE;
    if ($input['trigger'] == "subscription_charged_successfully") {
      // Approved
      if ($first) {
        $recur->start_date = $now;
        $recur->trxn_id = $recur->processor_id;
        $sendNotification = TRUE;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_START;
      }
      $statusName = 'In Progress';
      if ($recur->installments > 0) {
        $statusName = 'Completed';
        $recur->end_date = $now;
        $sendNotification = TRUE;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_END;
      }
      $recur->modified_date = $now;
      $recur->contribution_status_id = array_search($statusName, $contributionStatus);
      $recur->save();
    }
    else {
      // Declined
      // failed status
      $recur->contribution_status_id = array_search('Failed', $contributionStatus);
      $recur->cancel_date = $now;
      $recur->save();

      CRM_Core_Error::debug_log_message("Subscription payment failed - '{$input['response_reason_text']}'");


      return TRUE;
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($objects['contribution']->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction($input, $ids, $objects, $transaction, $recur);

    if ($sendNotification) {
      $autoRenewMembership = FALSE;
      if ($recur->id &&
          isset($ids['membership']) && $ids['membership']
          ) {
        $autoRenewMembership = TRUE;
      }

      //send recurring Notification email for user
      CRM_Contribute_BAO_ContributionPage::recurringNotify($subscriptionPaymentStatus,
                                                           $ids['contact_id'],
                                                           $ids['contributionPage'],
                                                           $recur,
                                                           $autoRenewMembership
                                                           );
    }
  }

  function getInput(&$input, &$ids) {
    if ($input['trxn_id']) {
      $input['is_test'] = 0;
    }
    else {
      $input['is_test'] = 1;
      $input['trxn_id'] = md5(uniqid(rand(), TRUE));
    }


  }

  function getIDs(&$ids, &$input) {

    // joining with contribution table for extra checks
    $sql = "
    SELECT cr.id, cr.contact_id,co.id contribution_id,cr.amount,co.contribution_page_id
      FROM civicrm_contribution_recur cr
INNER JOIN civicrm_contribution co ON co.contribution_recur_id = cr.id
     WHERE cr.processor_id = '{$input['subscription_id']}'
     LIMIT 1";
    $contRecur = CRM_Core_DAO::executeQuery($sql);
    $contRecur->fetch();
    $ids['contributionRecur'] = $contRecur->id;
    $input['amount'] = $contRecur->amount;
    if($ids['contact_id'] != $contRecur->contact_id){
      $ids['contact_id'] = $contRecur->contact_id;
    }
    if (!$ids['contributionRecur']) {
      CRM_Core_Error::debug_log_message("Could not find contributionRecur id: ".print_r($input, TRUE));
      echo "Failure: Could not find contributionRecur<p>";
      exit();
    }


    
    $ids['contribution'] =  $contRecur->contribution_id;
    $ids['contributionPage'] =  $contRecur->contribution_page_id; 
    
    // Get membershipId. Join with membership payment table for additional checks
    $sql = "
    SELECT m.id
      FROM civicrm_membership m
INNER JOIN civicrm_membership_payment mp ON m.id = mp.membership_id AND mp.contribution_id = {$ids['contribution']}
     WHERE m.contribution_recur_id = {$ids['contributionRecur']}
     LIMIT 1";
    if ($membershipId = CRM_Core_DAO::singleValueQuery($sql)) {
      $ids['membership'] = $membershipId;
    }

  }

  static function retrieve($name, $type, $abort = TRUE, $default = NULL, $location = 'POST') {
    static $store = NULL;
    $value = CRM_Utils_Request::retrieve($name, $type, $store,
                                         FALSE, $default, $location
                                         );
    if ($abort && $value === NULL) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name in $location");
      CRM_Core_Error::debug_var('POST', $_POST);
      CRM_Core_Error::debug_var('REQUEST', $_REQUEST);
      echo "Failure: Missing Parameter<p>";
      exit();
    }
    return $value;
  }

  function checkMD5($ids, $input) {
    $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($ids['paymentProcessor'],
                                                                       $input['is_test'] ? 'test' : 'live'
                                                                       );
    $paymentObject = CRM_Core_Payment::singleton($input['is_test'] ? 'test' : 'live', $paymentProcessor);

    if (!$paymentObject->checkMD5($input['MD5_Hash'], $input['trxn_id'], $input['amount'], TRUE)) {
      CRM_Core_Error::debug_log_message("MD5 Verification failed.");
      echo "Failure: Security verification failed<p>";
      exit();
    }
    return TRUE;
  } 
  
  static function destinationVerfication()
  {
    self::setBraintreeAuthValues();
    echo  Braintree_WebhookNotification::verify($_GET['bt_challenge']);
  }
  
  static function processNotification()
  {
    self::setBraintreeAuthValues();
    $webhookNotification = Braintree_WebhookNotification::parse(
                                                                $_POST['bt_signature_param'], $_POST['bt_payload_param']
                                                                );
      
    $this->main($webhookNotification->_attributes);
  }
  
  static function testNotification($subscriptionId)
  {
    self::setBraintreeAuthValues();
    $sampleNotification = Braintree_WebhookTesting::sampleNotification(
                                                                       Braintree_WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY,
                                                                       $subscriptionId
                                                                       );
      
    $webhookNotification = Braintree_WebhookNotification::parse(
                                                                $sampleNotification['signature'],
                                                                $sampleNotification['payload']
                                                                );
    return $webhookNotification->_attributes;
  }
  
  static function setBraintreeAuthValues()
  {
    $paymentProcessorTypeID = CRM_Core_DAO::getFieldValue(
                                                          'CRM_Financial_DAO_PaymentProcessorType',
                                                          'braintree',
                                                          'id',
                                                          'name'
                                                          );
    $paymentProcessorID = CRM_Core_DAO::getFieldValue(
                                                      'CRM_Financial_DAO_PaymentProcessor',
                                                      $paymentProcessorTypeID,
                                                      'id',
                                                      'payment_processor_type_id'
                                                      );
    $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($paymentProcessorID, "test");
    Braintree_Configuration::environment("sandbox");
    Braintree_Configuration::merchantId($paymentProcessor["user_name"]);
    Braintree_Configuration::publicKey($paymentProcessor["password"]);
    Braintree_Configuration::privateKey($paymentProcessor["signature"]); 
  }
}
