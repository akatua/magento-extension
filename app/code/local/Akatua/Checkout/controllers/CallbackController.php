<?php

class Akatua_Checkout_CallbackController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
        if (!$this->getRequest()->isPost()) {
            return;
        }
        try {
            $data = $this->getRequest()->getPost();
            Mage::getModel('Akatua/Callback')->processCallback();
        } catch (Exception $e) {
            Mage::logException($e);
			header("HTTP/1.1 406 Not Acceptable");
			exit;
        }
    }
}
