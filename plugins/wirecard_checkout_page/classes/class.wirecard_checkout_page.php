<?php

/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard Central Eastern
 * Europe GmbH
 * (abbreviated to Wirecard CEE) and are explicitly not part of the Wirecard
 * CEE range of products and services.
 *
 * They have been tested and approved for full functionality in the standard
 * configuration
 * (status on delivery) of the corresponding shop system. They are under
 * General Public License Version 2 (GPLv2) and can be used, developed and
 * passed on to third parties under the same terms.
 *
 * However, Wirecard CEE does not provide any guarantee or accept any liability
 * for any errors occurring when used in an enhanced, customized shop system
 * configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and
 * requires a comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard CEE does not guarantee
 * their full functionality neither does Wirecard CEE assume liability for any
 * disadvantages related to the use of the plugins. Additionally, Wirecard CEE
 * does not guarantee the full functionality for customized shop systems or
 * installed plugins of other vendors of plugins within the same shop system.
 *
 * Customers are responsible for testing the plugin's functionality before
 * starting productive operation.
 *
 * By installing the plugin into the shop system the customer agrees to these
 * terms of use. Please do not use the plugin if you do not agree to these
 * terms of use!
 */

defined('_VALID_CALL') or die ('Direct Access is not allowed.');

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

class wirecard_checkout_page
{
    var $data = array();
    var $post_form = false;
    var $iframe = false;
    var $external = true;
    var $IFRAME_URL = '';
    var $_transaction_table = 'wirecard_checkout_page_transaction';
    var $_transaction_id = '';
    /**
     * WD Variablen
     */
    var $demoMode = false;
    var $initHost = 'checkout.wirecard.com';
    var $initPath = '/page/init-server.php';
    var $initPort = '443';
    var $initParams = array();

    var $version = '1.6.7';

    var $paymentTypes = array(
        'WIRECARD_CHECKOUT_PAGE_SELECT' => 'SELECT',
        'WIRECARD_CHECKOUT_PAGE_CCARD' => WirecardCEE_QPay_PaymentType::CCARD,
        'WIRECARD_CHECKOUT_PAGE_MASTERPASS' => WirecardCEE_QPay_PaymentType::MASTERPASS,
        'WIRECARD_CHECKOUT_PAGE_MAESTRO' => WirecardCEE_QPay_PaymentType::MAESTRO,
        'WIRECARD_CHECKOUT_PAGE_PAYBOX' => WirecardCEE_QPay_PaymentType::PBX,
        'WIRECARD_CHECKOUT_PAGE_PAYSAFECARD' => WirecardCEE_QPay_PaymentType::PSC,
        'WIRECARD_CHECKOUT_PAGE_EPS_ONLINETRANSACTION' => WirecardCEE_QPay_PaymentType::EPS,
        'WIRECARD_CHECKOUT_PAGE_DIRECT_DEBIT' => WirecardCEE_QPay_PaymentType::SEPADD,
        'WIRECARD_CHECKOUT_PAGE_IDEAL' => WirecardCEE_QPay_PaymentType::IDL,
        'WIRECARD_CHECKOUT_PAGE_GIROPAY' => WirecardCEE_QPay_PaymentType::GIROPAY,
        'WIRECARD_CHECKOUT_PAGE_PAYPAL' => WirecardCEE_QPay_PaymentType::PAYPAL,
        'WIRECARD_CHECKOUT_PAGE_SOFORTUEBERWEISUNG' => WirecardCEE_QPay_PaymentType::SOFORTUEBERWEISUNG,
        'WIRECARD_CHECKOUT_PAGE_BMC' => WirecardCEE_QPay_PaymentType::BMC,
        'WIRECARD_CHECKOUT_PAGE_INVOICE' => WirecardCEE_QPay_PaymentType::INVOICE,
        'WIRECARD_CHECKOUT_PAGE_INSTALLMENT' => WirecardCEE_QPay_PaymentType::INSTALLMENT,
        'WIRECARD_CHECKOUT_PAGE_P24' => WirecardCEE_QPay_PaymentType::P24,
        'WIRECARD_CHECKOUT_PAGE_MONETA' => WirecardCEE_QPay_PaymentType::MONETA,
        'WIRECARD_CHECKOUT_PAGE_POLI' => WirecardCEE_QPay_PaymentType::POLI,
        'WIRECARD_CHECKOUT_PAGE_EKONTO' => WirecardCEE_QPay_PaymentType::EKONTO,
        'WIRECARD_CHECKOUT_PAGE_TRUSTLY' => WirecardCEE_QPay_PaymentType::TRUSTLY,
        'WIRECARD_CHECKOUT_PAGE_MPASS' => WirecardCEE_QPay_PaymentType::MPASS,
        'WIRECARD_CHECKOUT_PAGE_SKRILLWALLET' => WirecardCEE_QPay_PaymentType::SKRILLWALLET,
        'WIRECARD_CHECKOUT_PAGE_TATRAPAY' => WirecardCEE_QPay_PaymentType::TATRAPAY,
        'WIRECARD_CHECKOUT_PAGE_VOUCHER' => WirecardCEE_QPay_PaymentType::VOUCHER,
        'WIRECARD_CHECKOUT_PAGE_EPAY_BG' => WirecardCEE_QPay_PaymentType::EPAYBG
    );

    /**
     * php style constructor
     *
     * @access public
     */
    function __construct()
    {
        global $xtLink;
        if (WIRECARD_CHECKOUT_PAGE_USE_IFRAME == 'true' &&
            $_SESSION['selected_payment_sub'] != 'WIRECARD_CHECKOUT_PAGE_SOFORTUEBERWEISUNG') {
            $this->external = false;
            $this->iframe = true;
            $this->IFRAME_URL = $xtLink->_link(array('page' => 'checkout', 'paction' => 'pay_frame', 'conn' => 'SSL'));
            $this->initParams = Array('windowName' => 'veyton_paymentframe');
        }
    }

    /**
     * XTC-Funktion, um das Paymentrequest an einen externen PSP zu senden
     *
     * Die Funktion spiegelt in etwa die alte "payment_action" wieder. An dieser Stelle
     * wird die Anfrage gestellt und je nach der Ergebnis der Sprung auf die entsprechende
     * Seite vorbereitet (idR IFrame oder Fehlerseite)
     *
     * @param $order_data array mit den wichtigsten Infos zur Bestellung
     * @return $URL, zu der als nächstes gesprungen werden soll
     * @access public
     */
    function pspRedirect($order_data = null)
    {
        global $xtLink, $filter, $order, $db;
        if (!$order_data) {
            $order_data = $order->order_data;
        }
        if (($res = $this->_checkOrderData($order_data)) !== true) {
            return $xtLink->_link($res);
        }

        $orders_id = ( int )$order_data ['orders_id'];
        $redirect_url = $xtLink->_link(
            array(
                'page' => 'wirecard_checkout_page_checkout',
                'paction' => 'failure',
                'conn' => 'SSL',
                'params' => 'code_1=210'
            )
        );
        if (!is_int($orders_id)) {
            return $redirect_url;
        }

        # Special, da xt der Meinung ist alle alten GET Parameter mit anzuh�ngen
        $_GET = array();

        # Anfrage durchführen
        $strPaymentType = $this->paymentTypes[$_SESSION ['selected_payment_sub']];
        $paymentType1 = (isset ($strPaymentType) && !empty ($strPaymentType)) ? $strPaymentType : "SELECT";

        # Daten setzen
        try {
            $initiation = $this->initiate();
            if($initiation->hasFailed()){
                $this->_failureRedirect($initiation->getResponse()['message']);
                die();
            }
            $redirect_url = $initiation->getRedirectUrl();
        } catch (Exception $e){
               $this->_failureRedirect($e->getMessage());
        }

        @$db->Execute(
            "INSERT INTO " . $this->_transaction_table . " (TRID, PAYSYS,    STATE, DATE) VALUES ('" . $this->_transaction_id . "', '" . $paymentType1 . "','REDIRECTED', NOW())"
        );
        return $redirect_url;
    }

    /**
     * XTC-Funktion, um auf eine spezielle Success-Seite zu springen
     *
     * Da der Aufruf in der checkout-Klasse "payment_process" falsch ausgewertet wird (!= anstatt !==)
     * macht die Funktion zur Zeit keinen Sinn, da auch eine URL "true" wäre und nie aufgerufen werden
     * würde.
     *
     * @return URL oder true
     * @access public
     */
    function pspSuccess()
    {
        return true;
    }

    /**
     * Führt Prüfungen vor Absenden des Request durch
     *
     * @return true im Erfolgsfall, ansonsten Array mit Daten für Sprung zur Fehlerseite
     * @access private
     */
    function _checkOrderData($order_data)
    {
        # Prüfen, ob Paymenttype gsetezt
        if (!array_key_exists($_SESSION ['selected_payment_sub'], $this->paymentTypes)) {
            return array(
                'page' => 'wirecard_checkout_page_checkout',
                'paction' => 'failure',
                'conn' => 'SSL',
                'params' => 'code_1=209'
            );
        }
        return true;
    }

    function isInstallmentAllowed()
    {
        global $currency;

        if (!array_key_exists('customer', $_SESSION)) {
            return false;
        }

        if (!array_key_exists('cart', $_SESSION)) {
            return false;
        }

        if ($currency->code != 'EUR') {
            return false;
        }

        $customer = $_SESSION['customer'];
        $cart = $_SESSION['cart'];

        $paymentAddress = $customer->customer_payment_address;
        $shippingAddress = $customer->customer_shipping_address;

        $total = $cart->content_total['plain'];

        if ($paymentAddress['address_book_id'] != $shippingAddress['address_book_id']) {
            $fields = array(
                'customers_country',
                'customers_company',
                'customers_firstname',
                'customers_lastname',
                'customers_street_address',
                'customers_suburb',
                'customers_postcode',
                'customers_city',
                'customers_federal_state_code'
            );
            foreach ($fields as $f) {
                if ($paymentAddress[$f] != $shippingAddress[$f]) {
                    return false;
                }
            }

        }

        if (WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MIN_AMOUNT == 0 || WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MAX_AMOUNT == 0) {
            return false;
        }

        if (WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MIN_AMOUNT && WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MIN_AMOUNT > $total) {
            return false;
        }

        if (WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MAX_AMOUNT && WIRECARD_CHECKOUT_PAGE_INSTALLMENT_MAX_AMOUNT < $total) {
            return false;
        }

        return true;
    }

    function isInvoiceAllowed()
    {
        global $currency;

        if (!array_key_exists('customer', $_SESSION)) {
            return false;
        }

        if (!array_key_exists('cart', $_SESSION)) {
            return false;
        }

        if ($currency->code != 'EUR') {
            return false;
        }

        $customer = $_SESSION['customer'];
        $cart = $_SESSION['cart'];

        $paymentAddress = $customer->customer_payment_address;
        $shippingAddress = $customer->customer_shipping_address;

        $total = $cart->content_total['plain'];

        if ($paymentAddress['address_book_id'] != $shippingAddress['address_book_id']) {
            $fields = array(
                'customers_country',
                'customers_company',
                'customers_firstname',
                'customers_lastname',
                'customers_street_address',
                'customers_suburb',
                'customers_postcode',
                'customers_city',
                'customers_federal_state_code'
            );
            foreach ($fields as $f) {
                if ($paymentAddress[$f] != $shippingAddress[$f]) {
                    return false;
                }
            }

        }

        if (WIRECARD_CHECKOUT_PAGE_INVOICE_MIN_AMOUNT == 0 || WIRECARD_CHECKOUT_PAGE_INVOICE_MAX_AMOUNT == 0) {
            return false;
        }

        if (WIRECARD_CHECKOUT_PAGE_INVOICE_MIN_AMOUNT && WIRECARD_CHECKOUT_PAGE_INVOICE_MIN_AMOUNT > $total) {
            return false;
        }

        if (WIRECARD_CHECKOUT_PAGE_INVOICE_MAX_AMOUNT && WIRECARD_CHECKOUT_PAGE_INVOICE_MAX_AMOUNT < $total) {
            return false;
        }

        return true;
    }

    /**
     * @return WirecardCEE_QPay_Response_Initiation
     */

    function initiate()
    {
        global $order, $language;

        $order_data = $order->order_data;
        $this->_transaction_id = $this->generate_trid();
        $payment_type = $this->paymentTypes[$_SESSION['selected_payment_sub']];


        $init = new WirecardCEE_QPay_FrontendClient($this->getConfigArray());
        $init->trid = $this->_transaction_id;

        $init->setAmount(number_format($order->order_total['total']['plain'], 2, '.', ''))
            ->setCurrency($order_data ['currency_code'])
            ->setPaymentType((isset ($payment_type) && !empty ($payment_type)) ? $payment_type : "SELECT")
            ->setSuccessUrl($this->_link(array(
                'page' => 'wirecard_checkout_page_checkout',
                'conn' => 'SSL'
            )))
            ->setPendingUrl($this->_link(array(
                'page' => 'wirecard_checkout_page_checkout',
                'conn' => 'SSL'
            )))
            ->setFailureUrl($this->_link(array(
                'page' => 'wirecard_checkout_page_checkout',
                'conn' => 'SSL'
            )))
            ->setCancelUrl($this->_link(array(
                'page' => 'wirecard_checkout_page_checkout',
                'conn' => 'SSL'
            )))
            ->setConfirmUrl($this->_link(array(
                'lang_code' => $language->default_language,
                'page' => 'wirecard_checkout_page_checkout',
                'paction' => 'confirm',
                'conn' => 'SSL'
            )))
            ->setServiceUrl(WIRECARD_CHECKOUT_PAGE_SERVICE_URL)
            ->setImageUrl(WIRECARD_CHECKOUT_PAGE_IMAGE_URL)
            ->setDisplayText(WIRECARD_CHECKOUT_PAGE_DISPLAY_TEXT)
            ->setMaxRetries(intval(WIRECARD_CHECKOUT_PAGE_MAX_RETRIES))
            ->setOrderDescription($this->_transaction_id . ' - ' . $order->order_data ['customers_email_address'])
            ->setPluginVersion($this->_getPluginVersion())
            ->createConsumerMerchantCrmId($_SESSION['customer']->customer_info['customers_email_address'])
            ->setCustomerStatement(sprintf('%s: %s',_STORE_NAME, $order->oID));

        if(isset($_SESSION['wcp-consumerDeviceId'])){
        	$init->consumerDeviceId = $_SESSION['wcp-consumerDeviceId'];
        	unset($_SESSION['wcp-consumerDeviceId']);
        }

        if(isset($_SESSION['financialInstitution'])){
            $init->setFinancialInstitution($_SESSION['financialInstitution']);
        }

        $init->last_order_id = $_SESSION['last_order_id'];
        $init->orderDesc = $this->_transaction_id . ' - ' . $order->order_data['customers_email_address'];

        $init->setConsumerData($this->getConsumerData($payment_type));

        if (WIRECARD_CHECKOUT_PAGE_SEND_BASKET_DATA == 'true'
            || ($payment_type == 'INSTALLMENT' && (WIRECARD_CHECKOUT_PAGE_INSTALLMENT_PROVIDER == 'ratepay'))
            || ($payment_type == 'INVOICE' && (
                    WIRECARD_CHECKOUT_PAGE_INVOICE_PROVIDER == 'ratepay'
                    || WIRECARD_CHECKOUT_PAGE_INVOICE_PROVIDER == 'wirecard'
                )
            )

        ) {
            $init->setBasket($this->getBasketData());
        }

        if ($payment_type == 'MASTERPASS') {
            $init->setShippingProfile('NO_SHIPPING');
        }

        if (WIRECARD_CHECKOUT_PAGE_SEND_ORDERNUMBER == 'true') {
            $orderNumber = (int)$order_data['orders_id'];
            while ($orderNumber <= (int)$_SESSION['last_order_id']) {
                $orderNumber++;
            }
            //start from specific ordernumber
            if (is_numeric(WIRECARD_CHECKOUT_PAGE_START_ORDERNUMBER)) {
                $orderNumber += (int)WIRECARD_CHECKOUT_PAGE_START_ORDERNUMBER;
            }
            $init->setOrderNumber((string)$orderNumber);
        }

        return $init->initiate();
    }

    function _link($data)
    {
        global $xtLink;
        $ampedLink = $xtLink->_link($data);
        $link = str_replace('&amp;', '&', $ampedLink);
        return $link;
    }

    /**
     * set consumer data returning an array for legacy reasons or change the values in the reference
     * @param $payment_type
     * @return WirecardCEE_Stdlib_ConsumerData
     */
    function getConsumerData($payment_type)
    {
        $genericData = $_SESSION['customer']->customer_default_address;
        $shippingData = $_SESSION['customer']->customer_shipping_address;
        $billingData = $_SESSION['customer']->customer_payment_address;

        $birth_date = date('Y-m-d', strtotime($genericData['customers_dob']));
        $birth_date = DateTime::createFromFormat('Y-m-d', $birth_date);

        $shipping_address = new WirecardCEE_Stdlib_ConsumerData_Address(WirecardCEE_Stdlib_ConsumerData_Address::TYPE_SHIPPING);
        $billing_address = new WirecardCEE_Stdlib_ConsumerData_Address(WirecardCEE_Stdlib_ConsumerData_Address::TYPE_BILLING);

        $shipping_address->setFirstname($shippingData['customers_firstname'])
            ->setLastname($shippingData['customers_lastname'])
            ->setAddress1($shippingData['customers_street_address'])
            ->setAddress2($shippingData['customers_suborb'])
            ->setCity($shippingData['customers_city'])
            ->setZipCode($shippingData['customers_postcode'])
            ->setCountry($shippingData['customers_country_code'])
            ->setPhone($genericData['customers_phone'])
            ->setFax($genericData['customers_fax']);


        $billing_address->setFirstname($billingData['customers_firstname'])
            ->setLastname($billingData['customers_lastname'])
            ->setAddress1($billingData['customers_street_address'])
            ->setAddress2($billingData['customers_suborb'])
            ->setCity($billingData['customers_city'])
            ->setZipCode($billingData['customers_postcode'])
            ->setCountry($billingData['customers_country_code'])
            ->setPhone($billingData['customers_phone'])
            ->setFax($billingData['customers_fax']);

        $consumer_data = new WirecardCEE_Stdlib_ConsumerData();
        $consumer_data->setBirthDate($birth_date)
            ->setEmail($_SESSION['customer']->customer_info['customers_email_address'])
            ->setUserAgent($_SERVER['HTTP_USER_AGENT'])
            ->setIpAddress($this->getConsumerIpAddress());

        if (WIRECARD_CHECKOUT_PAGE_SEND_BILLING_DATA == 'true'
            || ($payment_type == 'INSTALLMENT')
            || ($payment_type == 'INVOICE')
            || $payment_type == 'TRUSTLY'
            || $payment_type == 'SKRILLWALLET'
        ) {
            $consumer_data->addAddressInformation($billing_address);
        }
        if (WIRECARD_CHECKOUT_PAGE_SEND_SHIPPING_DATA == 'true'
            || ($payment_type == 'INSTALLMENT')
            || ($payment_type == 'INVOICE')) {
            $consumer_data->addAddressInformation($shipping_address);
        }

        return $consumer_data;
    }

    function getBasketData()
    {
        global $order, $db, $mediaImages;

        $image_path = _SYSTEM_BASE_URL . "/" . $mediaImages->getPath() . "info/";
        $basket = new WirecardCEE_Stdlib_Basket();
        $sql = "SELECT products_image FROM " . TABLE_PRODUCTS . " WHERE products_id = %d";
        $order_products = $order->order_products;

        foreach ($order_products as $order_product) {
            $image = $db->_Execute(sprintf($sql,
                $order_product['products_id']))->GetRowAssoc()["products_image"];

            $basket_item = new WirecardCEE_Stdlib_Basket_Item($order_product['products_id']);
            $basket_item->setName($order_product['products_name'])
                ->setImageUrl($image_path . $image)
                ->setUnitGrossAmount(number_format($order_product['products_price']['plain'],2))
                ->setUnitNetAmount(number_format($order_product['products_price']['plain'] - $order_product['products_tax']['plain'],2))
                ->setUnitTaxAmount(number_format($order_product['products_tax']['plain'],2))
                ->setUnitTaxRate(number_format($order_product['products_tax_rate'],2));

            $basket->addItem($basket_item,
                number_format($order_product['products_quantity'], 0));
        }

        $shipping_data = $order->order_total_data;
        foreach ($shipping_data as $entry) {
            if ($entry['orders_total_key'] == 'shipping') {
                $shipping_item = new WirecardCEE_Stdlib_Basket_Item('shipping');
                $shipping_item->setDescription('Shipping')
                    ->setName('Shipping')
                    ->setUnitGrossAmount(number_format($entry['orders_total_final_price']['plain'],2))
                    ->setUnitNetAmount(number_format($entry['orders_total_final_price']['plain'] - $entry['orders_total_final_tax']['plain'],2))
                    ->setUnitTaxRate(number_format($entry['orders_total_tax_rate'],2))
                    ->setUnitTaxAmount(number_format($entry['orders_total_final_tax']['plain'],2));

                $basket->addItem($shipping_item);
            }
        }

        return $basket;
    }

    /**
     * @param WirecardCEE_QPay_FrontendClient $init
     * @return string
     */
    function _getPluginVersion(){
        return WirecardCEE_QPay_FrontendClient::generatePluginVersion(
            $this->getMajorVersion() <= 4?'Veyton; 4.x; ; xtCommerce4':'xtCommerce5',
            _SYSTEM_VERSION,
            'wirecard_checkout_page',
            $this->version
        );
    }

    function _createWirecardCheckoutPagePostData()
    {
        $requestArray = $this->initParams;
        $requestData = Array();
        foreach ($requestArray AS $key => $value) {
            $requestData[] = urlencode($key) . '=' . urlencode($value);
        }
        $requestDataString = implode('&', $requestData);
        return $requestDataString;
    }

    function _failureRedirect($message)
    {
        global $xtLink;
        $failureUrl = $xtLink->_link(
            array('page' => 'wirecard_checkout_page_checkout', 'params' => 'message=' . $message, 'conn' => 'SSL')
        );
        $xtLink->_redirect($failureUrl);
    }

    function generate_trid()
    {
        global $db;
        do {
            $trid = $this->create_random_value(16);
            //$oDB = oxDb::getDb();
            $sSelect = "SELECT TRID FROM " . $this->_transaction_table . " WHERE TRID = '" . $trid . "'";
            $rs = @$db->Execute($sSelect);
        } while ($rs->recordCount());

        return $trid;
    }

    function create_random_value($length, $type = 'mixed')
    {
        if (($type != 'mixed') && ($type != 'chars') && ($type != 'digits')) {
            return false;
        }

        if(!function_exists('ereg')) {
            function ereg($pattern, $subject, &$matches = []) {
                return preg_match('/'.$pattern.'/', $subject, $matches);
            }
        }

        if(!function_exists('eregi')) {
            function eregi($pattern, $subject, &$matches = []) {
                return preg_match('/'.$pattern.'/i', $subject, $matches);
            }
        }

        $rand_value = '';
        while (strlen($rand_value) < $length) {
            if ($type == 'digits') {
                $char = $this->randomvalue(0, 9);
            } else {
                $char = chr($this->randomvalue(0, 255));
            }
            if ($type == 'mixed') {
                if (eregi('^[a-z0-9]$', $char)) {
                    $rand_value .= $char;
                }
            } elseif ($type == 'chars') {
                if (eregi('^[a-z]$', $char)) {
                    $rand_value .= $char;
                }
            } elseif ($type == 'digits') {
                if (ereg('^[0-9]$', $char)) {
                    $rand_value .= $char;
                }
            }
        }

        return $rand_value;
    }

    function randomvalue($min = null, $max = null)
    {
        static $seeded;

        if (!$seeded) {
            mt_srand(( double )microtime() * 1000000);
            $seeded = true;
        }

        if (isset ($min) && isset ($max)) {
            if ($min >= $max) {
                return $min;
            } else {
                return mt_rand($min, $max);
            }
        } else {
            return mt_rand();
        }
    }

    function getMajorVersion()
    {
        $parts = explode('.', _SYSTEM_VERSION);
        return (int)$parts[0];
    }

    function getMinorVersion()
    {
        $parts = explode('.', _SYSTEM_VERSION);
        return (int)$parts[1];
    }

    private function getConsumerIpAddress()
    {
        if (!method_exists('Tools', 'getRemoteAddr')) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $_SERVER['HTTP_X_FORWARDED_FOR']) {
                if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')) {
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    return $ips[0];
                } else {
                    return $_SERVER['HTTP_X_FORWARDED_FOR'];
                }
            }
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return Tools::getRemoteAddr();
        }
    }

    /**
     * return config data as needed by the client library
     *
     * @return array
     */
    public function getConfigArray()
    {
        global $order;
        $language = $order->order_data['language_code'];

        switch(WIRECARD_CHECKOUT_PAGE_CONFIGURATION){
            case 'demo':
                return array(
                    'LANGUAGE' => $language,
                    'CUSTOMER_ID' => 'D200001',
                    'SHOP_ID' => '',
                    'SECRET' => 'B8AKTPWBRMNBV455FG6M2DANE99WU2');
            case 'test':
                return array(
                    'LANGUAGE' => $language,
                    'CUSTOMER_ID' => 'D200411',
                    'SHOP_ID' => '',
                    'SECRET' => 'CHCSH7UGHVVX2P7EHDHSY4T2S4CGYK4QBE4M5YUUG2ND5BEZWNRZW5EJYVJQ');
            case 'test3d':
                return array(
                    'LANGUAGE' => $language,
                    'CUSTOMER_ID' => 'D200411',
                    'SHOP_ID' => '3D',
                    'SECRET' => 'DP4TMTPQQWFJW34647RM798E9A5X7E8ATP462Z4VGZK53YEJ3JWXS98B9P4F');
            case 'production':
                return array(
                    'LANGUAGE' => $language,
                    'CUSTOMER_ID' => WIRECARD_CHECKOUT_PAGE_PROJECT_ID,
                    'SHOP_ID' => (trim(WIRECARD_CHECKOUT_PAGE_SHOP_ID) != '-')?WIRECARD_CHECKOUT_PAGE_SHOP_ID:'',
                    'SECRET' => WIRECARD_CHECKOUT_PAGE_PROJECT_SECRET);
        }
    }

}
