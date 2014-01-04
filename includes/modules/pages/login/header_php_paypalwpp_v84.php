<?php
// -----
// On the login page, need to check to see if the paypalwpp_v84 payment method is enabled and if its ECS button is
// also enabled.  If so and there's something in the current cart, enable the express-checkout button.
//

// Check for PayPal express checkout button suitability:
$paypalec_v84_enabled = (defined('MODULE_PAYMENT_PAYPALWPP_V84_STATUS') && MODULE_PAYMENT_PAYPALWPP_V84_STATUS == 'True' && defined('MODULE_PAYMENT_PAYPALWPP_ECS_BUTTON') && MODULE_PAYMENT_PAYPALWPP_ECS_BUTTON == 'On');
// Check for express checkout button suitability:
$ec_button_enabled = $ec_button_enabled | ($paypalec_v84_enabled && ($_SESSION['cart']->count_contents() > 0 && $_SESSION['cart']->total > 0));