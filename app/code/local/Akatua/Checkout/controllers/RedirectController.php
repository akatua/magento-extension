<?php


class Akatua_Checkout_RedirectController extends Mage_Core_Controller_Front_Action {

    protected $_order;

    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }
    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function indexAction() {
        $this->getResponse()
                ->setHeader('Content-type', 'text/html; charset=utf8')
                ->setBody($this->getLayout()
                ->createBlock('Akatua/redirect')
                ->toHtml());
    }

    public function successAction() {
            $event = $this->getRequest()->getParams();
            $transaction_id = $event['transaction_id'];
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($transaction_id);
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }

    public function cancelAction() {
        $event = $this->getRequest()->getParams();
        $transaction_id= $event['transaction_id'];
        $this->_getCheckout()->addError('The order has been canceled. Order #'.$transaction_id);
        $this->_redirect('checkout/cart');
    }
}

?>
