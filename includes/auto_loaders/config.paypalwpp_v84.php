<?php
// -----
// Autoloader to install the PayPal Express Checkout extension required for the v84.0 interface.
//
$autoLoadConfig[190][] = array('autoType'=>'class',
                              'loadFile'=>'observers/class.paypalwpp_v84_observer.php');
$autoLoadConfig[190][] = array('autoType'=>'classInstantiate',
                              'className'=>'paypalwpp_v84_observer',
                              'objectName'=>'paypalwpp_v84_observer');