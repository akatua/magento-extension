<?php


class Akatua_Checkout_Model_Redirect extends Mage_Payment_Model_Method_Abstract {

    protected $_code          = 'Akatua';
    protected $_formBlockType = 'Akatua/form';
    protected $_infoBlockType = 'Akatua/info';
    protected $_order;

    const TEST_MODE = 'payment/Akatua/test_mode';
    const APP_ID = 'payment/Akatua/application_id';
    const APP_SECRET = 'payment/Akatua/application_secret';
    const LOGO_URL = 'payment/Akatua/logo_url';

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('Akatua/redirect', array('_secure' => true));
    }

    public function getAkatuaUrl() {
        $url = 'https://secure.akatua.com/checkout';
        return $url;
    }

    public function getCheckoutFormFields() {

        $order_id = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

        $appid = Mage::getStoreConfig(APP_ID);
        $secret = Mage::getStoreConfig(APP_SECRET);
        $test = Mage::getStoreConfig(TEST_MODE);
        $logourl = Mage::getStoreConfig(LOGO_URL);

        $description = base64_encode("Payment for order #$order_id");
        $timestamp = time();
        $signature = hash_hmac('sha256',$appid.":{$description}:{$timestamp}",$secret);

        $params = array(
            'application_id' => $appid,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'test_mode' => $test,
            'transaction_type' => "checkout",
            'description' => $description,
            'amount' => trim(round($order->getGrandTotal(), 2)),
            'invoice' => $order_id,
            'fail_url' => Mage::getUrl('Akatua/redirect/cancel', array('transaction_id' => $order_id)),
            'success_url' => Mage::getUrl('Akatua/redirect/success', array('transaction_id' => $order_id)),
            'callback_url' => Mage::getUrl('Akatua/callback'),
            'logo_url' => $logourl
        );
        return $params;

    }

    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

}
