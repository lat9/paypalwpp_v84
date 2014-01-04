<?php
/**
 * paypalwpp_admin_notification.php admin display component
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2011 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions Copyright 2004 DevosC.com
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: paypalwpp_admin_notification.php 18695 2011-05-04 05:24:19Z drbyte $
 */
 
  class paypalwppAdmin extends base {
    var $response;    // the response from getTransactionDetails
    var $ipns;        // An array of all the IPNs associated with the order, in the order they were created
    var $ipnCount;    // The number of IPNs associated with the order
    var $lastIPN;     // Array index of the last IPN in the array
    var $isPayFlow;   // (boolean) Identifies whether or not the order was processed via PayFlow
    
    function paypalwppAdmin($oID) {
      global $db, $doPayPal;
      
      $this->ipns = array();
      $this->isPayFlow = false;
      $this->ipnCount = 0;
      $this->lastIPN = 0;
      if (((int)$oID) > 0) {
        $sql = "SELECT * from " . TABLE_PAYPAL . " WHERE order_id = :orderID ORDER BY paypal_ipn_id ASC";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $txns = $db->Execute($sql);
        
        $this->ipnCount = $txns->RecordCount();
        $this->lastIPN  = $this->ipnCount - 1;

        while (!$txns->EOF) {
          // strip slashes in case they were added to handle apostrophes:
          foreach ($txns->fields as $key => $value){
            $txns->fields[$key] = stripslashes($value);
          }
          $this->ipns[] = $txns->fields;
          $txns->MoveNext();
        }
        
        $this->response = $doPayPal->GetTransactionDetails($this->ipns[$this->lastIPN]['txn_id']);
        if (is_array($this->response) && $this->responseNamePresent('RESPMSG') && $this->response['RESPMSG'] != '') {
          $this->isPayFlow = true;
          
        } else {
          $this->response = $doPayPal->GetTransactionDetails($this->ipns[0]['txn_id']);
          
        }
      }
    }
      
    // Function to pull an item from either the response or the paypal transactions' data array.
    function getItemValue($responseName, $ipnName = '', $whichIPN = 0) {
      $value = ($this->responseNamePresent($responseName)) ? urldecode($this->response[$responseName]) : (($ipnName != '') ? $this->ipns[$whichIPN][$ipnName] : '');
      return $value;
    }
    
    function responseNamePresent($responseName) {
      return array_key_exists($responseName, $this->response);
    }
    
    // Create a 2-column table row for the specified item
    function makeItemRow($label, $value) {
      return "<tr><td class=\"main\">$label</td><td class=\"main\">$value</td></tr>\n";

    }
    
    function makeItemRowWithName($label, $responseName, $ipnName = '', $whichIPN = 0) {
      return $this->makeItemRow($label, $this->getItemValue($responseName, $ipnName, $whichIPN));
      
    }
    
    function makeItemRowWithIPNData($label, $ipnName, $whichIPN) {
      return $this->makeItemRow($label, $this->ipns[$whichIPN][$ipnName]);
      
    }
    
  }

  // -----
  // Main processing .... included by the admin_notification function of /includes/modules/payment/paypalwpp.php
  //
  global $doPayPal;
  $doPayPal = $this->paypal_init();
  $module = $this->code;
  $output = '';
  if (((int)$zf_order_id) > 0) {
    $wppAdmin = new paypalwppAdmin($zf_order_id);
    $this->wppAdmin = $wppAdmin;

    $outputPFmain = "\n";
    $outputPayPal = "\n";
    if ($wppAdmin->isPayFlow) {
      // these would be payflow transactions
      $outputPFmain = '<td valign="top"><table>' . "\n";
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_AUTHCODE, 'AUTHCODE');
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_STATUS, getItemValue($responseRecords, 'RESPMSG'));
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_AVSADDR, getItemValue($responseRecords, 'AVSADDR'));
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_AVSZIP, getItemValue($responseRecords, 'AVSZIP'));
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_CVV2MATCH, getItemValue($responseRecords, 'CVV2MATCH'));
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_TXN_ID, getItemValue($responseRecords, 'ORIGPNREF'));
      $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_DATE, 'payment_date', $wppAdmin->lastIPN);
      $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_TRANSSTATE, 'TRANSSTATE');

      if ($wppAdmin->responseNamePresent('DAYS_TO_SETTLE') && $wppAdmin->response['DAYS_TO_SETTLE'] != '' ) {
        $outputPFmain .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_DAYSTOSETTLE, 'DAYS_TO_SETTLE');
 
      }
      
      $outputPFmain .= '</table></td>' . "\n";

      if ($wppAdmin->ipns[$wppAdmin->lastIPN]['mc_gross'] > 0) {
        $outputPFmain .= '<td valign="top"><table>' . "\n";

        $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_CURRENCY, 'mc_currency', $wppAdmin->lastIPN);
        $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_GROSS_AMOUNT, 'mc_gross', $wppAdmin->lastIPN);
        $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_FEE, 'mc_fee', $wppAdmin->lastIPN);
        $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_EXCHANGE_RATE, 'exchange_rate', $wppAdmin->lastIPN);
        $outputPFmain .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_CART_ITEMS, 'num_cart_items', $wppAdmin->lastIPN);

        $outputPFmain .= '</table></td>' . "\n";
        
      }

    } else {
      // display all paypal status fields (in admin Orders page):
      $outputPayPal .= '<td valign="top"><table>' . "\n";
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_FIRST_NAME, 'FIRSTNAME', 'first_name');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_LAST_NAME, 'LASTNAME', 'last_name');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_BUSINESS_NAME, 'BUSINESS', 'payer_business_name');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_NAME, 'NAME', 'address_name');
      $address = $wppAdmin->getItemValue('SHIPTOSTREET') . ' ' . $wppAdmin->getItemValue('SHIPTOSTREET2');
      $address = ($address == ' ') ? $wppAdmin->ipns[0]['address_street'] : $address;
      $outputPayPal .= $wppAdmin->makeItemRow(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_STREET, $address);
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_CITY, 'SHIPTOCITY', 'address_city');
      $outputPayPal .= $wppAdmin->makeItemRow(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_STATE, $wppAdmin->getItemValue('SHIPTOSTATE', 'address_state') . ' ' . $wppAdmin->getItemValue('SHIPTOZIP', 'address_zip'));
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_COUNTRY, 'SHIPTOCOUNTRYNAME', 'address_country');
      $outputPayPal .= '</table></td>' . "\n";

      $outputPayPal .= '<td valign="top"><table>' . "\n";
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_EMAIL_ADDRESS, 'EMAIL', 'payer_email');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_EBAY_ID, 'BUYERID');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_PAYER_ID, 'PAYERID', 'payer_id');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_PAYER_STATUS, 'PAYERSTATUS', 'payer_status');
      $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPAL_ENTRY_ADDRESS_STATUS, 'ADDRESSSTATUS', 'address_status');

      if (defined('MODULE_PAYMENT_PAYPALWPP_ENTRY_PROTECTIONELIG') && $wppAdmin->responseNamePresent('PROTECTIONELIGIBILITY') && $wppAdmin->getItemValue('PROTECTIONELIGIBILITY') != '') {
        $outputPayPal .= $wppAdmin->makeItemRowWithName(MODULE_PAYMENT_PAYPALWPP_ENTRY_PROTECTIONELIG, 'PROTECTIONELIGIBILITY');
      }
      if (defined('MODULE_PAYMENT_PAYPAL_ENTRY_COMMENTS') && $wppAdmin->ipns[0]['memo'] != '') {
        $outputPayPal .= $wppAdmin->makeItemRow(MODULE_PAYMENT_PAYPAL_ENTRY_COMMENTS, $wppAdmin->ipns[0]['memo']);
      }
      $outputPayPal .= '</table></td>' . "\n";

      $outputPayPal .= '<td valign="top"><table>' . "\n";
      
      for ($i = 0, $style = ''; $i < $wppAdmin->ipnCount; $i++) {
        $outputPayPal .= '<tr><td valign="top"' . $style . '><table>' . "\n";

        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_DATE, 'payment_date', $i);
        $txn_id = $wppAdmin->ipns[$i]['txn_id'];
        $outputPayPal .= $wppAdmin->makeItemRow(MODULE_PAYMENT_PAYPAL_ENTRY_TXN_ID, '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_view-a-trans&id=' . $txn_id . '" target="_blank">' . $txn_id . '</a>');
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PARENT_TXN_ID, 'parent_txn_id', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_TXN_TYPE, 'txn_type', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_TYPE, 'payment_type', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_STATUS, 'payment_status', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PENDING_REASON, 'pending_reason', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_INVOICE, 'invoice', $i);
        
        $outputPayPal .= '</table></td>' . "\n";

        $outputPayPal .= '<td valign="top"' . $style . '><table>' . "\n";
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_CURRENCY, 'mc_currency', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_GROSS_AMOUNT, 'mc_gross', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_PAYMENT_FEE, 'mc_fee', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_EXCHANGE_RATE, 'exchange_rate', $i);
        $outputPayPal .= $wppAdmin->makeItemRowWithIPNData(MODULE_PAYMENT_PAYPAL_ENTRY_CART_ITEMS, 'num_cart_items', $i);
        
        $outputPayPal .= '</table></td></tr>' . "\n";
        
        $style = ' style="border-top: 1px solid #414141;"';
        
      }
      
      $outputPayPal .= '</table></td>' . "\n";

    }

    if (!method_exists($this, '_doRefund')) {
      $outputRefund = '';
      
    } else {
      $outputRefund  = '<td><table class="noprint">'."\n";
      $outputRefund .= '<tr style="background-color : #eeeeee; border-style : dotted;">'."\n";
      $outputRefund .= '<td class="main">' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_TITLE . '<br />'. "\n";
      $outputRefund .= zen_draw_form('pprefund', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doRefund', 'post', '', true) . zen_hide_session_id();
      if (!$wppAdmin->isPayFlow) {
      // full refund (only for PayPal transactions, not Payflow)
        $outputRefund .= MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_FULL;
        $outputRefund .= '<br /><input type="submit" name="fullrefund" value="' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_FULL . '" title="' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_FULL . '" />' . ' ' . MODULE_PAYMENT_PAYPALWPP_TEXT_REFUND_FULL_CONFIRM_CHECK . zen_draw_checkbox_field('reffullconfirm', '', false) . '<br />';
        $outputRefund .= MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_TEXT_FULL_OR;
      } else {
        $outputRefund .= MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_PAYFLOW_TEXT;
      }
      //partial refund - input field
      $outputRefund .= MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_PARTIAL_TEXT . ' ' . zen_draw_input_field('refamt', 'enter amount', 'length="8"');
      $outputRefund .= '<input type="submit" name="partialrefund" value="' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_PARTIAL . '" title="' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_PARTIAL . '" /><br />';
      //comment field
      $outputRefund .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_TEXT_COMMENTS . '<br />' . zen_draw_textarea_field('refnote', 'soft', '50', '3', MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_DEFAULT_MESSAGE);
      //message text
      $outputRefund .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_SUFFIX;
      $outputRefund .= '</form>';
      $outputRefund .='</td></tr></table></td>'."\n";
    }

    if (!(method_exists($this, '_doAuth') && !$wppAdmin->isPayFlow)) {
      $outputAuth = '';
      
    } else {
      $outputAuth  = '<td valign="top"><table class="noprint">'."\n";
      $outputAuth .= '<tr style="background-color : #eeeeee; border-style : dotted;">'."\n";
      $outputAuth .= '<td class="main">' . MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_TITLE . '<br />'. "\n";
      $outputAuth .= zen_draw_form('ppauth', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doAuth', 'post', '', true);
      //partial auth - input field
      $outputAuth .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_PARTIAL_TEXT . ' ' . zen_draw_input_field('authamt', 'enter amount', 'length="8"') . zen_hide_session_id();
      $outputAuth .= '<input type="submit" name="orderauth" value="' . MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_BUTTON_TEXT_PARTIAL . '" title="' . MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_BUTTON_TEXT_PARTIAL . '" />' . MODULE_PAYMENT_PAYPALWPP_TEXT_AUTH_FULL_CONFIRM_CHECK . zen_draw_checkbox_field('authconfirm', '', false) . '<br />';
      //message text
      $outputAuth .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_SUFFIX;
      $outputAuth .= '</form>';
      $outputAuth .='</td></tr></table></td>'."\n";
    }

    if (!method_exists($this, '_doCapt')) {
      $outputCapt = '';
      
    } else {
      $outputCapt  = '<td valign="top"><table class="noprint">'."\n";
      $outputCapt .= '<tr style="background-color : #eeeeee; border-style : dotted;">'."\n";
      $outputCapt .= '<td class="main">' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_TITLE . '<br />'. "\n";
      $outputCapt .= zen_draw_form('ppcapture', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doCapture', 'post', '', true) . zen_hide_session_id();
      $outputCapt .= MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_FULL;
      $outputCapt .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_AMOUNT_TEXT . ' ' . zen_draw_input_field('captamt', 'enter amount', 'length="8"');
      $outputCapt .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_FINAL_TEXT . ' ' . zen_draw_checkbox_field('captfinal', '', true) . '<br />';
      $outputCapt .= '<input type="submit" name="btndocapture" value="' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_BUTTON_TEXT_FULL . '" title="' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_BUTTON_TEXT_FULL . '" />' . ' ' . MODULE_PAYMENT_PAYPALWPP_TEXT_REFUND_FULL_CONFIRM_CHECK . zen_draw_checkbox_field('captfullconfirm', '', false);
      //comment field
      $outputCapt .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_TEXT_COMMENTS . '<br />' . zen_draw_textarea_field('captnote', 'soft', '50', '2', MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_DEFAULT_MESSAGE);
      //message text
      $outputCapt .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_CAPTURE_SUFFIX;
      $outputCapt .= '</form>';
      $outputCapt .='</td></tr></table></td>'."\n";
    }

    if (!method_exists($this, '_doVoid')) {
      $outputVoid = '';
      
    } else {
      $outputVoid  = '<td valign="top"><table class="noprint">'."\n";
      $outputVoid .= '<tr style="background-color : #eeeeee; border-style : dotted;">'."\n";
      $outputVoid .= '<td class="main">' . MODULE_PAYMENT_PAYPAL_ENTRY_VOID_TITLE . '<br />'. "\n";
      $outputVoid .= zen_draw_form('ppvoid', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doVoid', 'post', '', true) . zen_hide_session_id();
      $outputVoid .= MODULE_PAYMENT_PAYPAL_ENTRY_VOID . '<br />' . zen_draw_input_field('voidauthid', 'enter auth ID', 'length="8"');
      $outputVoid .= '<input type="submit" name="ordervoid" value="' . MODULE_PAYMENT_PAYPAL_ENTRY_VOID_BUTTON_TEXT_FULL . '" title="' . MODULE_PAYMENT_PAYPAL_ENTRY_VOID_BUTTON_TEXT_FULL . '" />' . ' ' . MODULE_PAYMENT_PAYPALWPP_TEXT_VOID_CONFIRM_CHECK . zen_draw_checkbox_field('voidconfirm', '', false);
      //comment field
      $outputVoid .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_VOID_TEXT_COMMENTS . '<br />' . zen_draw_textarea_field('voidnote', 'soft', '50', '3', MODULE_PAYMENT_PAYPAL_ENTRY_VOID_DEFAULT_MESSAGE);
      //message text
      $outputVoid .= '<br />' . MODULE_PAYMENT_PAYPAL_ENTRY_VOID_SUFFIX;
      $outputVoid .= '</form>';
      $outputVoid .='</td></tr></table></td>'."\n";
    }

    // prepare output based on suitable content components
    $output  = '<!-- BOF: pp admin transaction processing tools -->';
    $output .= '<td><table class="noprint">' . "\n" . '<tr style="background-color : #cccccc; border-style : dotted;">' . "\n";
 
    //debug
    //$output .= '<pre>' . print_r($response, true) . '</pre>';

    if ($wppAdmin->isPayFlow || defined('MODULE_PAYMENT_PAYFLOW_STATUS')) { // payflow
      $output .= $outputPFmain;
      if (MODULE_PAYMENT_PAYPALDP_TRANSACTION_MODE == 'Auth Only' || MODULE_PAYMENT_PAYFLOW_TRANSACTION_MODE == 'Auth Only' || (isset($_GET['authcapt']) && $_GET['authcapt'] == 'on') ) {
        if (method_exists($this, '_doVoid')) {
          $output .= $outputVoid;
        }
        if (method_exists($this, '_doCapt')) {
          $output .= $outputCapt;
        }
      }
      if (method_exists($this, '_doRefund')) {
        $output .= $outputRefund;
      }
      
    } else {  // PayPal
      $output .= $outputPayPal;

      if (defined('MODULE_PAYMENT_PAYPALWPP_V84_STATUS') || defined('MODULE_PAYMENT_PAYPALDP_STATUS')) {
        $output .= '</tr>' . "\n" . '</table></td>' . "\n";
        $output .= '</tr><tr>' . "\n";
        $output .= '<td><table class="noprint">' . "\n" . '<tr style="background-color : #cccccc; border-style : dotted;">' . "\n";
        $output .= '<td><table class="noprint">' . "\n" . '<tr style="background-color : #cccccc; border-style : dotted;">' . "\n";
        if ($wppAdmin->response['TRANSACTION_TYPE'] == 'Authorization' || (in_array($wppAdmin->response['TRANSACTIONTYPE'], array('cart','expresscheckout','webaccept') ) && $wppAdmin->response['PAYMENTTYPE'] == 'instant' && $wppAdmin->response['PENDINGREASON'] == 'authorization') || (isset($_GET['authcapt']) && $_GET['authcapt'] == 'on')) {
          if (method_exists($this, '_doRefund') && ($wppAdmin->response['PAYMENTTYPE'] != 'instant' || $module == 'paypaldp')) {
            $output .= $outputRefund;
          }
          if (MODULE_PAYMENT_PAYPALWPP_TRANSACTION_MODE == 'Auth Only' || MODULE_PAYMENT_PAYPALDP_TRANSACTION_MODE == 'Auth Only') {
            if (method_exists($this, '_doVoid')) {
              $output .= $outputVoid;
            }
            if (method_exists($this, '_doCapt')) {
              $output .= $outputCapt;
            }
          }
          if (method_exists($this, '_doVoid')) {
            $output .= $outputVoid;
          }
          
        } else {
          if (method_exists($this, '_doRefund')) {
            $output .= $outputRefund;
          }
          if (method_exists($this, '_doVoid') && $wppAdmin->response['PAYMENTTYPE'] == 'instant' && $wppAdmin->response['PAYMENTSTATUS'] != 'Voided' && $module != 'paypaldp') {
            $output .= $outputVoid;
          }
        }
      }
    }
    $output .= '</tr>' . "\n" . '</table></td>' . "\n";
    $output .= '</tr>' . "\n" . '</table></td>' . "\n";

    $output .= '<!-- EOF: pp admin transaction processing tools -->';
    
  }