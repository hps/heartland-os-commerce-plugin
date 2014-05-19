<?php
class securesubmit {
    var $code, $title, $description, $enabled;
	protected $config;

// class constructor
    function securesubmit() {
      global $order;

      $this->signature = 'hps|securesubmit|1.0|2.2';
      $this->api_version = 'Ver1.0';

      $this->code = 'securesubmit';
      $this->title = MODULE_PAYMENT_SECURESUBMIT_TEXT_TITLE;
      $this->public_title = MODULE_PAYMENT_SECURESUBMIT_TEXT_PUBLIC_TITLE;
      $this->description = MODULE_PAYMENT_SECURESUBMIT_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_SECURESUBMIT_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();
    }

    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_SECURESUBMIT_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_SECURESUBMIT_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
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
                   'module' => $this->public_title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      global $order;

	  $public_key = MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY;

	  if ($public_key == '')
	  {
?>
		<script type="text/javascript">
			alert('No Public Key found - unable to procede.');
        </script>
<?php
	  }

      for ($i=1; $i<13; $i++) {
        $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B',mktime(0,0,0,$i,1,2000)));
      }

      $today = getdate();
      for ($i=$today['year']; $i < $today['year']+10; $i++) {
        $expires_year[] = array('id' => strftime('%Y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
      }

	$confirmation = array('fields' => array(array('title' => MODULE_PAYMENT_SECURESUBMIT_CREDIT_CARD_NUMBER,
												'field' => tep_draw_input_field('', '', 'class="card_number"')),
										  array('title' => MODULE_PAYMENT_SECURESUBMIT_CREDIT_CARD_EXPIRES,
												'field' => tep_draw_pull_down_menu('', $expires_month, '', 'class="card_expiry_month"') .
												'&nbsp;' . tep_draw_pull_down_menu('', $expires_year, '', 'class="card_expiry_year"')),
										  array('title' => MODULE_PAYMENT_SECURESUBMIT_CREDIT_CARD_CVC,
												'field' => tep_draw_input_field('', '', 'class="card_cvc" size="5" maxlength="4"'))));

    if (MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY) {
        $confirmation['title'] .= '<script type="text/javascript" src="' . DIR_WS_INCLUDES . 'jquery.js"></script>';
    }
    
	$confirmation['title'] .= '<script type="text/javascript" src="' . DIR_WS_INCLUDES . 'secure.submit-1.0.2.js"></script>';
	$confirmation['title'] .= '<script type="text/javascript">
		jQuery(document).ready(function($) {
			$("form[name=\'checkout_confirmation\']").bind("submit", handleSubmit);

			function handleSubmit() {
				hps.tokenize({
					data: {
						public_key: \'' . $public_key . '\',
						number: $(\'.card_number\').val().replace(/\D/g, \'\'),
						cvc: $(\'.card_cvc\').val(),
						exp_month: $(\'.card_expiry_month\').val(),
						exp_year: $(\'.card_expiry_year\').val()
					},
					success: function(response) {
						secureSubmitResponseHandler(response);
					},
					error: function(response) {
						secureSubmitResponseHandler(response);
					}
				});

				return false; // stop the form submission
			}

			function secureSubmitResponseHandler(response) {
				if ( response.message ) {
					alert(response.message);
				} else {
					var form$ = $("form[name=checkout_confirmation]");

					form$.append("<input type=\'hidden\' name=\'securesubmit_token\' value=\'" + response.token_value + "\'/>");

					$(\'.card_number\').val(\'\');
					$(\'.card_cvc\').val(\'\');
					$(\'.card_expiry_month\').val(\'\');
					$(\'.card_expiry_year\').val(\'\');

					$("#tbd5").hide();
					$("form[name=\'checkout_confirmation\']").unbind("submit");
					$("form[name=\'checkout_confirmation\']").submit();
				}
			}
		});
		</script>';

      return $confirmation;
    }

    function process_button() {
      return false;
    }

    function before_process() {
		global $HTTP_POST_VARS, $customer_id, $order, $sendto, $currency;
		$error = '';
		require_once(DIR_FS_CATALOG.'ext/modules/payment/securesubmit/Hps.php');

            $config = new HpsConfiguration();

            $config->secretApiKey = MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY;
            $config->versionNumber = '1515';
            $config->developerId = '002914';

            $chargeService = new HpsChargeService($config);

            $hpsaddress = new HpsAddress();
            $hpsaddress->address = $order->billing['street_address'];
            $hpsaddress->city = $order->billing['city'];
            $hpsaddress->state = $order->billing['state'];
            $hpsaddress->zip = preg_replace('/[^0-9]/', '', $order->billing['postcode']);
            $hpsaddress->country =$order->billing['country']['title'];
            
            $cardHolder = new HpsCardHolder();
            $cardHolder->firstName = $order->billing['firstname'];
            $cardHolder->lastName = $order->billing['lastname'];
            $cardHolder->phone = preg_replace('/[^0-9]/', '', $order->customer['telephone']);
            $cardHolder->email = $order->customer['email_address'];
            $cardHolder->address = $hpsaddress;

            $hpstoken = new HpsTokenData();
            $hpstoken->tokenValue = $_POST['securesubmit_token'];
        
        
		try {
			if (MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD == 'Authorization')
			{
                $response = $chargeService->authorize(
                    substr($this->format_raw($order->info['total']), 0, 15),
                    'usd',
                    $hpstoken,
                    $cardHolder,
                    false,
                    null);
			}
			else
			{
                $response = $chargeService->charge(
                    substr($this->format_raw($order->info['total']), 0, 15),
                    'usd',
                    $hpstoken,
                    $cardHolder,
                    false,
                    null);
			}
		}
		catch (Exception $e) {
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . $response->responseText, 'SSL'));
		}
    }

    function after_process() {
      return false;
    }

   function get_error() {
      global $HTTP_GET_VARS;

      $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_GENERAL;

      switch ($HTTP_GET_VARS['error']) {
        case 'invalid_expiration_date':
          $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_INVALID_EXP_DATE;
          break;

        case 'expired':
          $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_EXPIRED;
          break;

        case 'declined':
          $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_DECLINED;
          break;

        case 'cvc':
          $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_CVC;
          break;

        default:
          $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_GENERAL;
          break;
      }

      $error = array('title' => MODULE_PAYMENT_SECURESUBMIT_ERROR_TITLE,
                     'error' => $error_message);

      return $error;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SECURESUBMIT_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable SecureSubmit', 'MODULE_PAYMENT_SECURESUBMIT_STATUS', 'False', 'Do you want to accept Secure Submit Credit Card payments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Public API Key', 'MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY', '', 'The Public API Key for your Secure Submit Account', '6', '0', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret API Key', 'MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY', '', 'The Secret API Key for your Secure Submit Account', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Include jQuery', 'MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY', 'False', 'Do you need our plugin to include jQuery?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Method', 'MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD', 'Authorization', 'The processing method to use for each transaction. <strong>If using <i>authorization</i>, you will have to capture using the Virtual Terminal.</strong>', '6', '0', 'tep_cfg_select_option(array(\'Authorization\', \'Capture\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_SECURESUBMIT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_SECURESUBMIT_STATUS',
		  'MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY',
		  'MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY',
          'MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY',
		  'MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD',
		  'MODULE_PAYMENT_SECURESUBMIT_ZONE',
		  'MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID',
		  'MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER');
    }
// format prices without currency formatting
    function format_raw($number, $currency_code = '', $currency_value = '') {
      global $currencies, $currency;

      if (empty($currency_code) || !$this->is_set($currency_code)) {
        $currency_code = $currency;
      }

      if (empty($currency_value) || !is_numeric($currency_value)) {
        $currency_value = $currencies->currencies[$currency_code]['value'];
      }

      return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }
  }
?>
