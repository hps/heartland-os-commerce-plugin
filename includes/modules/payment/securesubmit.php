<?php


use GlobalPayments\Api\Entities\EncryptionData;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\PaymentMethods\CreditTrackData;
use GlobalPayments\Api\Services\CreditService;
use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;

class securesubmit
{

    public $code;
    public $title;
    public $description;
    public $enabled;
    protected $config;

    // class constructor
    public function securesubmit()
    {
        global $order;

        $this->signature = 'hps|securesubmit|1.0|2.2';
        $this->api_version = 'Ver1.0';

        $this->code = 'securesubmit';
        $this->title = MODULE_PAYMENT_SECURESUBMIT_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_SECURESUBMIT_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_SECURESUBMIT_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_SECURESUBMIT_STATUS == 'True') ? true : false);
        $this->allow_suspicious = ((MODULE_PAYMENT_SECURESUBMIT_ALLOW_SUSPICIOUS == 'True') ? true : false);
        $this->email_suspicious = ((MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS == 'True') ? true : false);
        $this->email_suspicious_address = MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS_ADDRESS;
        $this->fraud_text = MODULE_PAYMENT_SECURESUBMIT_FRAUD_TEXT;

        if ((int) MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_SECURESUBMIT_ZONE > 0)) {
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

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array(
            'id' => $this->code,
            'module' => $this->public_title,
        );
    }

    public function pre_confirmation_check()
    {
        return false;
    }

    public function confirmation()
    {
        global $order;

        $public_key = MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY;

        if ($public_key == '') {

            ?>
            <script type="text/javascript">
                alert('No Public Key found - unable to procede.');
            </script>
            <?php
        }

        $content = '<!-- make iframes styled like other form -->
                <style type="text/css">
                    #iframes iframe{
                        float:left;
                        width:100%;
                    }
                    .iframeholder:after,
                    .iframeholder::after{
                        content:"";
                        display:block;
                        width:100%;
                        height:0px;
                        clear:both;
                        position:relative;
                    }
                </style>

                <!-- The Payment Form -->
                <form id="iframes" action="" method="GET">
                    <div class="form-group">
                        <label for="iframesCardNumber">Card Number:</label>
                        <div class="iframeholder" id="iframesCardNumber"></div>
                    </div>
                    <div class="form-group">
                        <label for="iframesCardExpiration">Card Expiration:</label>
                        <div class="iframeholder" id="iframesCardExpiration"></div>
                    </div>
                    <div class="form-group">
                        <label for="iframesCardCvv">Card CVV:</label>
                        <div class="iframeholder" id="iframesCardCvv"></div>
                    </div>
                </form>';

        $content .= '<script type="text/javascript" src="https://api2.heartlandportico.com/SecureSubmit.v1/token/2.1/securesubmit.js"></script>';

        if (MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY) {
            $content .= '<script type="text/javascript" src="' . DIR_WS_INCLUDES . 'jquery.js"></script>';
        }

        $content .= '<script type="text/javascript">var public_key = \'' . $public_key . '\';</script>';
        $content .= '<script type="text/javascript" src="' . DIR_WS_INCLUDES . 'secure.submit-1.1.1.js"></script>';
        $content .= '<script type="text/javascript">                        
            jQuery(document).ready(function($) {
                $("form[name=\'checkout_confirmation\']").bind("submit", handleSubmit);
                
                function handleSubmit(e) { 
                    // Prevent the form from continuing to the `action` address
                    e.preventDefault();
                    // Tell the iframes to tokenize the data
                    hps.Messages.post(
                            {
                                accumulateData: true,
                                action: \'tokenize\',
                                message: public_key
                            },
                            \'cardNumber\'
                    );

                    return false; // stop the form submission
                }                
            });
            </script>';
        

        $confirmation['title'] = $content;

        return $confirmation;
    }

    public function process_button()
    {
        return false;
    }

    public function before_process()
    {
        global $HTTP_POST_VARS, $customer_id, $order, $sendto, $currency;
        $error = '';
        
        require 'vendor/autoload.php';


        $creditService = $this->getConfig();

        $hpsaddress = new Address();
        $hpsaddress->address = $order->billing['street_address'];
        $hpsaddress->city = $order->billing['city'];
        $hpsaddress->state = $order->billing['state'];
        $hpsaddress->zip = preg_replace('/[^0-9]/', '', $order->billing['postcode']);
        $hpsaddress->country = $order->billing['country']['title'];

        $cardHolder = new Customer();
        $cardHolder->firstName = $order->billing['firstname'];
        $cardHolder->lastName = $order->billing['lastname'];
        $cardHolder->phone = preg_replace('/[^0-9]/', '', $order->customer['telephone']);
        $cardHolder->email = $order->customer['email_address'];
        $cardHolder->address = $hpsaddress;

        $hpstoken = new CreditCardData();
        $hpstoken->token = $_POST['securesubmit_token'];


        try {
            if (MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD == 'Authorization') {
                $response = $hpstoken->authorize(
                    substr($this->format_raw($order->info['total']), 0, 15), 'usd', $hpstoken, $cardHolder, false, null
                );
            } else {
                $response = $hpstoken->charge(
                    substr($this->format_raw($order->info['total']), 0, 15), 'usd', $hpstoken, $cardHolder, false, null
                );
            }

            $order->info['cc_type'] = $_POST['card_type'];
        } catch (HpsException $e) {
            if ($this->allow_suspicious && $e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED) {
                // we can skip the card saving: if it fails for possible fraud there will be no token.
                if ($this->email_suspicious && $this->email_suspicious_address != '') {
                    $this->sendEmail(
                        $this->email_suspicious_address, $this->email_suspicious_address, 'Suspicious order allowed (' . $order_id . ')', 'Hello,<br><br>Heartland has determined that you should review order ' . $order_id .
                        ' for the amount of ' . substr($this->format_raw($order->info['total']), 0, 15) . '.'
                    );
                }
                $order->info['cc_type'] = $_POST['card_type'];
            } else {
                $code = $e->getCode();
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . $code, 'SSL'));
            }
        } catch (Exception $e) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode($e->getMessage()), 'SSL'));
        }
    }
    
    protected function getConfig()
    {
        $config = new ServicesConfig();
        $config->secretApiKey = MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY;
        $config->serviceUrl = ($this->enableCryptoUrl) ?
                              'https://cert.api2-c.heartlandportico.com/':
                              'https://cert.api2.heartlandportico.com';
        $service = new CreditService(
                $config
        );
        return $service;
    }
    
    public function sendEmail($to, $from, $subject, $body, $headers = array(), $isHtml = true)
    {
        $headers[] = sprintf('From: %s', $from);
        $headers[] = sprintf('Reply-To: %s', $from);

        $message = $body;
        if ($isHtml) {
            $message = sprintf('<html><body>%s</body></html>', $body);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=ISO-8859-1';
        }
        $message = wordwrap($message, 70, "\r\n");

        mail($to, $subject, $message, implode("\r\n", $headers));
    }

    public function after_process()
    {
        return false;
    }

    public function get_error()
    {
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

            case '27':
                $error_message = $this->fraud_text;
                break;

            default:
                $error_message = MODULE_PAYMENT_SECURESUBMIT_ERROR_GENERAL;
                break;
        }

        $error = array(
            'title' => MODULE_PAYMENT_SECURESUBMIT_ERROR_TITLE,
            'error' => $error_message,
        );

        return $error;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SECURESUBMIT_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable SecureSubmit', 'MODULE_PAYMENT_SECURESUBMIT_STATUS', 'False', 'Do you want to accept Secure Submit Credit Card payments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Public API Key', 'MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY', '', 'The Public API Key for your Secure Submit Account', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret API Key', 'MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY', '', 'The Secret API Key for your Secure Submit Account', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Include jQuery', 'MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY', 'False', 'Do you need our plugin to include jQuery?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Method', 'MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD', 'Authorization', 'The processing method to use for each transaction. <strong>If using <i>authorization</i>, you will have to capture using the Virtual Terminal.</strong>', '6', '0', 'tep_cfg_select_option(array(\'Authorization\', \'Capture\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_SECURESUBMIT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Allow Suspicious', 'MODULE_PAYMENT_SECURESUBMIT_ALLOW_SUSPICIOUS', 'False', 'Do you want to allow suspicious orders? Note: You will have 72 hours from the original authorization date to manually review suspicious orders in the virtual terminal and make a final decision (either to accept the gateway fraud decision or to manually override).', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Email Suspicious', 'MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS', 'False', 'Do you want to email the store owner on suspicious orders?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Notification Email Address', 'MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS_ADDRESS', '', 'This email address will be notified of suspicious orders.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Fraud Text', 'MODULE_PAYMENT_SECURESUBMIT_FRAUD_TEXT', '', 'This is the text that will display to the customer when fraud is detected and the transaction fails.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys()
    {
        return array(
            'MODULE_PAYMENT_SECURESUBMIT_STATUS',
            'MODULE_PAYMENT_SECURESUBMIT_PUBLIC_API_KEY',
            'MODULE_PAYMENT_SECURESUBMIT_SECRET_API_KEY',
            'MODULE_PAYMENT_SECURESUBMIT_INCLUDE_JQUERY',
            'MODULE_PAYMENT_SECURESUBMIT_TRANSACTION_METHOD',
            'MODULE_PAYMENT_SECURESUBMIT_ZONE',
            'MODULE_PAYMENT_SECURESUBMIT_ORDER_STATUS_ID',
            'MODULE_PAYMENT_SECURESUBMIT_SORT_ORDER',
            'MODULE_PAYMENT_SECURESUBMIT_ALLOW_SUSPICIOUS',
            'MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS',
            'MODULE_PAYMENT_SECURESUBMIT_EMAIL_SUSPICIOUS_ADDRESS',
            'MODULE_PAYMENT_SECURESUBMIT_FRAUD_TEXT',
        );
    }

    // format prices without currency formatting
    public function format_raw($number, $currency_code = '', $currency_value = '')
    {
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
