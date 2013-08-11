
For CiviCRM 4.3 and 4.2:

Install extension
 
You can get Braintree Payment Processor Extension from here : https://github.com/vivekarora/braintree-recur


Tasks need to be done after installing the extension.

1. signup to https://www.braintreepayments.com/get-started 
2. Go to System Settings -> Payment Processors
3. Select Braintree from Payment processor type dropdown
4. Fill the Merchant Id,Public Key and Private Key which will you get when you login to https://sandbox.braintreegateway.com

Tasks needs to be done for setting up recurring payments

1. Login on https://sandbox.braintreegateway.com
2. Go to Settings->Webhooks
3. Define accesspath for braintreeNotify.php in Destination text box
4. Uncomment line 16 and 17 of braintreeNotify.php for desitnation verification  
             CRM_Core_Payment_BraintreeIPN::destinationVerfication();
             die;
5. Comment line 16 and 17 again after successfull destination verification. 

Tasks needs to be done to run the test cases.

1. Go to the tests folder of the extention.
2. Copy All files inside the com.paymentprocessor.braintree/tests/phpunit/WebTest/Cotribute and paste it in civicrm(Your Civicrm module directory)/tests/phpunit/WebTest/Cotribute 
3. Copy All files inside the com.paymentprocessor.braintree/tests/phpunit/WebTest/Event and paste it in civicrm(Your Civicrm module directory)/tests/phpunit/WebTest/Event  
4. Copy All files inside the com.paymentprocessor.braintree/tests/phpunit/WebTest/Member and paste it in civicrm(Your Civicrm module directory)/tests/phpunit/WebTest/Member   
5. cd path/to/civicrm/packages/SeleniumRC 
6. sh selenium.sh

Now you are all set to run the web tests.

To run 

Online Contribution tests

       scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Contribute_OnlineContributionBraintreeTest

Offline Contribution tests

        scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Contribute_OfflineContributionBraintreeTest


 Event creation and Online registration tests

        scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Event_AddEventBraintreeTest


 Event creation and Ofline  add participants tests

       	 scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Event_AddParticipationBraintreeTest


 Online membership signup

         scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME  WebTest_Member_OnlineMembershipBraintreeCreateTest

 Offline membership payment and autorenew
 
          scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Member_OfflineAutoRenewBraintreeMembershipTest
 
Online Contribution Recur tests

       scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME WebTest_Contribute_OnlineRecurContributionBraintreeTest

Offline Contribution Recur tests

        scripts/phpunit -uUSERNAME -pPASSWORD -hHOST -bTESTDBNAME  WebTest_Contribute_OfflineRecurContributionBraintreeTest
