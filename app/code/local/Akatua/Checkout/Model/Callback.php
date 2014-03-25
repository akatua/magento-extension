<?php

class Akatua_Checkout_Model_Callback {

	const DEFAULT_LOG_FILE = 'Akatua_callback.log';

	protected $_order = null;
	protected $_request = array();
	protected $_debugData = array();
	protected $confirmed = null;

	const TEST_MODE = 'payment/Akatua/test_mode';
	const APP_ID = 'payment/Akatua/application_id';
	const APP_SECRET = 'payment/Akatua/application_secret';

	public function processCallback() {
		$this->_request = $_POST;
		$this->_debugData = array('callback' => $_POST);
		ksort($this->_debugData['callback']);

		try {
			$this->_getOrder();
			$this->_confirm();
			$this->_processOrder();
		}
		catch (Exception $e) {
			$this->_debugData['exception'] = $e->getMessage();
			$this->_debug();
			throw $e;
		}
	}

	protected function _getOrder() {
		if (empty($this->_order)) {
			$id = $this->_request['invoice'];
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($id);
			if (!$this->_order->getId()) {
				$this->_debugData['exception'] = sprintf('Wrong order ID: "%s".', $id);
				$this->_debug();
				Mage::app()->getResponse()
					->setHeader('HTTP/1.1','503 Service Unavailable')
					->sendResponse();
				exit;
			}
		}
		return $this->_order;
	}

	protected function _confirm() {
		$appid = Mage::getStoreConfig(APP_ID);
		$secret = Mage::getStoreConfig(APP_SECRET);
		$test = Mage::getStoreConfig(TEST_MODE);

		$data['transaction_id'] = $this->_request['transaction_id'];
		$data['timestamp'] = time();
		if ($test) $data['test_mode'] = 1;

		$serverurl = "https://secure.akatua.com/api/v1/getTransactionDetails";

		$headers[] = "Content-Type: application/json";
		$headers[] = "Akatua-Application-ID: ".$appid;
		$headers[] = "Akatua-Signature: ".hash_hmac('sha256',json_encode($data),$secret);

		$confirm = $this->make_httprequest("GET",$serverurl,$data,$headers);

		if (!$confirm) {
			throw new Exception('No response from server. See ' . self::DEFAULT_LOG_FILE . ' for details.');
		}
		$json = json_decode($confirm);

		if (!$json->success) {
			throw new Exception($json->errorText.' See ' . self::DEFAULT_LOG_FILE . ' for details.');
		}

		if ($json->response->status != "completed") {
			throw new Exception('Payment not completed. See ' . self::DEFAULT_LOG_FILE . ' for details.');
		}

		if ($json->response->amount != $this->_order->getGrandTotal()) {
			Mage::throwException('Amount paid does not match order amount.');
		}

		$this->confirmed = $json->response;
	}

	protected function _processOrder() {
		$payment = $this->_order->getPayment();
		$payment->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE)
			->setShouldCloseParentTransaction(1)
			->setIsTransactionClosed(1)
			->registerCaptureNotification($this->confirmed->amount);
		$this->_order->save();

		$payment->setAdditionalInformation("transaction_id", $this->confirmed->id)
				->setAdditionalInformation("fee", $this->confirmed->fee)
				->setAdditionalInformation("status", $this->confirmed->status)
				->setAdditionalInformation("time", $this->confirmed->status)
				->save();

		$invoice = $payment->getCreatedInvoice();
		if ($invoice && !$this->_order->getEmailSent()) {
			$this->_order->sendNewOrderEmail()
				->setIsCustomerNotified(true)
				->save();
		}
	}

	public function getRequestData($key = null) {
		if (null === $key) {
			return $this->_request;
		}
		return isset($this->_request[$key]) ? $this->_request[$key] : null;
	}

	protected function _debug() {
			$file = self::DEFAULT_LOG_FILE;
			Mage::getModel('core/log_adapter', $file)->log($this->_debugData);
	}

	protected function make_httprequest($method="GET",$url,$data=array(),$headers=array()) {
		$method = strtoupper($method);
		$json = json_encode($data);

		if (function_exists('curl_version') && strpos(ini_get('disable_functions'),'curl_exec') === false) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

			$result = curl_exec($ch);
			$error = curl_error($ch);
			if ($error) throw new Exception($error);
			curl_close($ch);
		}
		else {
			$urlbits = parse_url($url);
			$host = $urlbits['host'];
			$path = $urlbits['path'];

			$remote = fsockopen("ssl://{$host}", 443, $errno, $errstr, 30);

			if (!$remote) {
				throw new Exception("$errstr ($errno)");
			}

			$req = "{$method} {$path} HTTP/1.1\r\n";
			$req .= "Host: {$host}\r\n";
			foreach($headers as $header) {
				$req .= $header."\r\n";
			}
			$req .= "Content-Length: ".strlen($json)."\r\n";
			$req .= "Connection: Close\r\n\r\n";
			$req .= $json;
			fwrite($remote, $req);
			$response = '';
			while (!feof($remote)) {
				$response .= fgets($remote, 1024);
			}
			fclose($remote);

			$responsebits = explode("\r\n\r\n", $response, 2);
			$header = isset($responsebits[0]) ? $responsebits[0] : '';
			$result = isset($responsebits[1]) ? $responsebits[1] : '';
		}
		return $result;
	}

}
