<?php
/**
 * Realex_Redirect extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Realex
 * @package    Realex_Redirect
 * @copyright  Copyright (c) 2015 StudioForty9
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Realex
 * @package    Realex_Redirect
 * @author     StudioForty9 <info@studioforty9.com>
 */
class Realex_Redirect_Model_Redirect extends Mage_Payment_Model_Method_Abstract{

    protected $_code  = 'realex';
    protected $_formBlockType = 'realex/redirect_form';
    protected $_allowCurrencyCode = array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD','USD');

    /**
     * @param $data
     * @return SF9_Realex_Model_Redirect
     */
 	public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCcType($data->getAmex());
                
        return $this;
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Using internal pages for input payment data
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return false;
    }

    /**
     * Using for multiple shipping address
     *
     * @return bool
     */
    public function canUseForMultishipping()
    {
        return false;
    }

    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('realex/redirect_form', $name)
            ->setMethod('realex_redirect')
            ->setPayment($this->getPayment())
            ->setTemplate('realex/redirect/form.phtml');

        return $block;
    }

    /**
     * Validate the currency code is avaialable to use for Realex or not
     *
     * @return SF9_Realex_Model_Redirect
     */

    public function validate()
    {
        parent::validate();
        $currency_code = $this->getQuote()->getBaseCurrencyCode();
        if (!in_array($currency_code,$this->_allowCurrencyCode)) {
            Mage::throwException(Mage::helper('realex')->__('Selected currency code ('.$currency_code.') is not compatabile with Realex'));
        }
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return SF9_Realex_Model_Redirect
     */
    public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment)
    {
       return $this;
    }

    /**
     * @param Mage_Sales_Model_Invoice_Payment $payment
     * @return void
     */
    public function onInvoiceCreate(Mage_Sales_Model_Invoice_Payment $payment)
    {

    }

    /**
     * @return bool
     */
    public function canCapture()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
		Mage::getSingleton('core/session')->setRealexCcType(Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getCcType());
		return Mage::getUrl('realex/redirect/', array('_secure' => true));
    }

    /**
     * @return string
     */
    /*
public function getCheckoutRedirectUrl()
    {
          return Mage::getUrl('realex/redirect/', array('_secure' => true));
    }
*/

    /**
     * @return string
     */
    public function getSuccessUrl(){
    	return Mage::getUrl('realex/response/');
    }

    /**
     * @return string
     */
    public function getCancelUrl(){
    	return Mage::getUrl('realex/redirect/cancel');
    }

    /**
     * @return string
     */
    public function getRealexUrl(){
		$url = "https://epage.payandshop.com/epage.cgi";
        return $url;
    }

     /**
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return true;
    }

    /**
     * @param $paymentAction
     * @param $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        //$state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $state = "Realex Processing";
        $stateObject->setState($state);
        //$stateObject->setStatus(Mage::getSingleton('sales/order_config')->getStateDefaultStatus($state));
        $stateObject->setIsNotified(false);
    }

    /**
     * @return bool
     */
    public function processRedirectResponse($post){
    	Mage::log($post);    
    	$this->saveRealexTransaction($post);
    	
        $timestamp = $post['TIMESTAMP'];
		$result = $post['RESULT'];
		$orderid = $post['ORDER_ID'];
		$message = $post['MESSAGE'];
		$authcode = $post['AUTHCODE'];
		$pasref = $post['PASREF'];
		$realexsha1 = $post['SHA1HASH'];

        //get the information from the module configuration
        $redirect = Mage::getModel('realex/redirect');
        $merchantid = $redirect->getConfigData('login');
        $secret = $redirect->getConfigData('pwd');

		$tmp = "$timestamp.$merchantid.$orderid.$result.$message.$pasref.$authcode";
		$sha1hash = sha1($tmp);
		$tmp = "$sha1hash.$secret";
		$sha1hash = sha1($tmp);
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderid);

		$session = Mage::getSingleton('checkout/session');
		$session->setOrderId($orderid);

		//Check to see if hashes match or not
		if ($sha1hash != $realexsha1) {
			if ($order->getId()) {
				$order->cancel();
				$order->addStatusToHistory('cancelled', 'The hashes do not match - response not authenticated!', false);
				$order->save();
			}
			return false;
		}else{
			if ($result == "00") {
				if ($order->getId()) {
					$order->addStatusToHistory('processing', 'Payment Successful: ' . $result . ': ' . $message, false);
					$order->addStatusToHistory('processing', 'Authorisation Code: ' . $authcode, false);
					$order->sendNewOrderEmail();
					$order->setEmailSent(true);

					$session->setLastSuccessQuoteId($order->getId());
					$session->setLastQuoteId($order->getId());
			        $session->setLastOrderId($order->getId());

					$order->save();
				}
		        if($redirect->getConfigData('capture')){
					Mage::helper('realex')->createInvoice($orderid);
				}
				return true;
			}else{
				$session->addError('There was a problem completing your order. Please try again');
				if ($order->getId()) {
					$order->addStatusToHistory('cancelled', $result . ': ' . $message, false);
					$order->cancel();
				}
				$order->save();
				return false;
	        }
	    }
    }
    
    public function saveRealexTransaction($post){
        $realex = Mage::getModel('realex/realex');

		try{
	        $realex->setOrderId($post['ORDER_ID'])
                    ->setTimestamp(Mage::helper('realex')->getDateFromTimestamp($post['TIMESTAMP']))
                    ->setMerchantid($post['MERCHANT_ID'])
                    ->setAccount($post['ACCOUNT'])
                    ->setAuthcode($post['AUTHCODE'])
                    ->setResult($post['RESULT'])
                    ->setMessage($post['MESSAGE'])
                    ->setPasref($post['PASREF'])
                    ->setCvnresult($post['CVNRESULT'])
                    ->setBatchid($post['BATCHID'])
                    ->setTssResult($post['TSS'])
                    ->setAvspostcoderesponse($post['AVSPOSTCODERESULT'])
                    ->setAvsaddressresponse($post['AVSADDRESSRESULT'])
                    ->setHash($post['SHA1HASH'])
                    ->setFormKey($post['form_key'])
                    ->setPasUuid($post['pas_uuid'])
                    ->save();
        }catch(Exception $e){
    		Mage::logException($e);
    	}
    }
}

?>
