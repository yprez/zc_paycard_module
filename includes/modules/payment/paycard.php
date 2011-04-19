<?php       
/*
  paycard.php, v0.9 25/02/2011
  
  PayCard (Israel) payment module
  Created By: Yuri Prezument
*/

  class paycard {
    var $code, $title, $description, $enabled;

// class constructor
    function paycard() {
      global $order;

      $this->code = 'paycard';
      $this->title = MODULE_PAYMENT_PAYCARD_TEXT_TITLE;
//      if (IS_ADMIN_FLAG === true && (MODULE_PAYMENT_PAYCARD_PAYTO == 'the Store Owner/Website Name' || MODULE_PAYMENT_PAYCARD_PAYTO == ''))
//          $this->title .= '<span class="alert"> (not configured - needs pay-to)</span>';
      $this->description = MODULE_PAYMENT_PAYCARD_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_PAYCARD_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_PAYCARD_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_PAYCARD_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_PAYCARD_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();

      $this->email_footer = MODULE_PAYMENT_PAYCARD_TEXT_EMAIL_FOOTER;
      
      $this->form_action_url = 'https://secure.paycard.co.il/webi/html/interface.aspx';
    }

// class methods
    function update_status() {
      global $order, $db;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYCARD_ZONE > 0) ) {
        $check_flag = false;
        $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYCARD_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      return array('title' => MODULE_PAYMENT_PAYCARD_TEXT_DESCRIPTION);
    }

    function process_button() {
        global $db, $order;
        $options = array();
        $buttonArray = array();
    
        $this->totalsum = $order->info['total'];
        
        $options = array(
            'INAME' => 'mtpurchase',
            'mer' => 'PayCard',
            'CreditorID' => MODULE_PAYMENT_PAYCARD_PAYTO,
            'TransactionID' => 'automatic',
            'Amount' => $this->totalsum,
            'Currency' => 'ILS',
            'SuccessUserPage' => zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
            'FailureUserPage' => zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'),
            'UserDesc' => MODULE_PAYMENT_PAYCARD_DESCRIPTION,
            'AnswerPage' => '',
            'ItemType' => 3,
            'successPageParam' => 1,
            'Sync' => 0,
            'ResultPageMethod' => 'GET',
            //'redirect_cmd' => '_xclick','rm' => 2,'bn' => 'zencart','mrb' => 'R-6C7952342H795591R','pal' => '9E82WJBKKGPLQ',
        );
        // build the button fields
        foreach ($options as $name => $value) {
            // remove quotation marks
            $value = str_replace('"', '', $value);
            // check for invalid chars
//            if (preg_match('/[^a-zA-Z_0-9]/', $name)) {
//                ipn_debug_email('datacheck - ABORTING - preg_match found invalid submission key: ' . $name . ' (' . $value . ')');
//                break;
//            }
            // do we need special handling for & and = symbols?
            //if (strpos($value, '&') !== false || strpos($value, '=') !== false) $value = urlencode($value);
            $buttonArray[] = zen_draw_hidden_field($name, $value);
        }
        $process_button_string = implode("\n", $buttonArray) . "\n";

        return $process_button_string;
    }

    function before_process() {
        //zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        return false;
//        if (isset($_GET['referer']) && $_GET['referer'] == 'paycard') {
//            zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
//        }
//        else {
//            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
//        }
    }

    function after_process() {
        return false;
//        $_SESSION['order_created'] = '';
//        global $insert_id, $db, $order;
//        $sql_data_array= array(
//            array('fieldName'=>'orders_id', 'value'=>$insert_id, 'type'=>'integer'),
//            array('fieldName'=>'orders_status_id', 'value'=>$this->order_status, 'type'=>'integer'),
//            array('fieldName'=>'date_added', 'value'=>'now()', 'type'=>'noquotestring'),
//            array('fieldName'=>'customer_notified', 'value'=>0, 'type'=>'integer')
//        );
//        $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }

    function get_error() {
      return false;
    }

    function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYCARD_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

    function install() {
      global $db, $messageStack;
      if (defined('MODULE_PAYMENT_PAYCARD_STATUS')) {
        $messageStack->add_session('PayCard module already installed.', 'error');
        zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=paycard', 'NONSSL'));
        return 'failed';
      }
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Paycard Order Module', 'MODULE_PAYMENT_PAYCARD_STATUS', 'True', 'Do you want to accept PayCard payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PayCard MerchantID:', 'MODULE_PAYMENT_PAYCARD_PAYTO', '', 'Your MerchantID on PayCard', '6', '1', now());");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYCARD_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYCARD_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYCARD_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Set Description String', 'MODULE_PAYMENT_PAYCARD_DESCRIPTION', 'Otaku Shop Payment', 'Set the user description on PayCard.', '6', '0', now())");
    }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_PAYCARD_STATUS', 'MODULE_PAYMENT_PAYCARD_ZONE', 'MODULE_PAYMENT_PAYCARD_ORDER_STATUS_ID', 'MODULE_PAYMENT_PAYCARD_SORT_ORDER', 'MODULE_PAYMENT_PAYCARD_PAYTO', 'MODULE_PAYMENT_PAYCARD_DESCRIPTION');
    }
  }
