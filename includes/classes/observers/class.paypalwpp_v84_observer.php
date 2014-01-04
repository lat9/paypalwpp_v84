<?php
// -----
// This module hooks the notifier issued by /ipn_main_handler near the end of its Express Checkout processing.
// We'll check to see if the v84.0 interface of the paypalwpp payment method is enabled and take action, if so.
//

if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

class paypalwpp_v84_observer extends base {

  function paypalwpp_v84_observer() {
    if (defined('MODULE_PAYMENT_PAYPALWPP_V84_STATUS') && MODULE_PAYMENT_PAYPALWPP_V84_STATUS == 'True') {
      $this->attach($this, array('NOTIFY_IPN_MAIN_HANDLER_IS_EC_TXN'));
    }
  }
  
  function update(&$class, $eventID, $paramsArray) {
    global $paypalwpp_v84;
    $paypalwpp_module = 'paypalwpp_v84';
    // init the payment object
    $payment_modules = new payment($paypalwpp_module);
    // set the payment, if they're hitting us here then we know
    // the payment method selected right now.
    $_SESSION['payment'] = $paypalwpp_module;
    // check to see if we have a token sent back from PayPal.
    if (!isset($_SESSION['paypal_ec_token']) || empty($_SESSION['paypal_ec_token'])) {
      // We have not gone to PayPal's website yet in order to grab
      // a token at this time.  This will send the customer over to PayPal's
      // website to login and return a token
      $$paypalwpp_module->ec_step1();
    } else {
      // This will push on the second step of the paypal ec payment
      // module, as we already have a PayPal express checkout token
      // at this point.
      $$paypalwpp_module->ec_step2();
    }
    
  }
 
}