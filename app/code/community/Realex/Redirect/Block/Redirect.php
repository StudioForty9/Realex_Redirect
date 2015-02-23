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
class Realex_Redirect_Block_Redirect extends Mage_Core_Block_Abstract
{
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $session = Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();

        $timestamp = $merchantid = $account = $orderid = $currency = $cardnumber = $expdate = $cardname = $cardtype = $issueno = $cvc = $customerID = $productID = $billingPostcode = $billingCountry =	$shippingPostcode = $shippingCountry = $sha1hash = '';
        $redirect = Mage::getModel('realex_redirect/redirect');
        
        $timestamp = strftime("%Y%m%d%H%M%S");

        $form = new Varien_Data_Form();
        $form->setAction($redirect->getRealexUrl())
            ->setId('realex_redirect_checkout')
            ->setName('realex_redirect_checkout')
            ->setMethod('POST')
            ->setUseContainer(true);


        $orderid = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderid);
        		
		if($redirect->getConfigData('currency') == 'display'){
	        $currency = $quote->getStore()->getCurrentCurrencyCode();
            $amount = $order->getGrandTotal();
		}else{
			$currency = $order->getBaseCurrencyCode();
			$amount = $order->getBaseGrandTotal();
		}
				
		$amount = $amount * 100;
		$amount = round($amount);
        
        $merchantid = Mage::getStoreConfig('realex/account/merchantid');
        $secret = Mage::getStoreConfig('realex/account/secret');
        
		if(Mage::getSingleton('core/session')->getRealexCcType() == 'amex'){
		      	$account = Mage::getStoreConfig('realex/account/amexAccount');
		}else{
            $account = Mage::getStoreConfig('realex/account/account');
		}
        
        $tmp = "$timestamp.$merchantid.$orderid.$amount.$currency";
        $sha1hash = sha1($tmp);
		$tmp = "$sha1hash.$secret";
		$sha1hash = sha1($tmp);
		
		if($redirect->getConfigData('capture')){
			$autosettle = 1;
		}else{
			$autosettle = 0;	
		}

		$customerID = $quote->getCustomerId();

		$billing = $quote->getBillingAddress();
		$shipping = $quote->getShippingAddress();

        $billingCountry = $billing->getCountry();
		$billingPostcode = $billing->getPostcode();
		$billingPostcodeNumbers = preg_replace('/[^\d]/', '', $billingPostcode);

		$billingStreetName = $billing->getStreet1();
		preg_match('{(\d+)}', $billingStreetName, $m);
		if(isset($m[1])){
			$billingStreetNumber = $m[1];
		}else{
			$billingStreetNumber = '';
		}

		$billingCode = $billingPostcodeNumbers . '|' . $billingStreetNumber;

		if($shipping){
			$shippingCountry = $shipping->getCountry();
			$shippingPostcode = $shipping->getPostcode();
		}

        $comment1 = '';
        $comment2 = 'Magento';
        $varref = '';
        $prodid = '';
        
        $form->addField('MERCHANT_ID', 'hidden', array('name'=>'MERCHANT_ID', 'value'=>$merchantid));
        $form->addField('ORDER_ID', 'hidden', array('name'=>'ORDER_ID', 'value'=>$orderid));
        $form->addField('ACCOUNT', 'hidden', array('name'=>'ACCOUNT', 'value'=>$account));
        $form->addField('CURRENCY', 'hidden', array('name'=>'CURRENCY', 'value'=>$currency));
        $form->addField('AMOUNT', 'hidden', array('name'=>'AMOUNT', 'value'=>$amount));
        $form->addField('TIMESTAMP', 'hidden', array('name'=>'TIMESTAMP', 'value'=>$timestamp));
        $form->addField('SHA1HASH', 'hidden', array('name'=>'SHA1HASH', 'value'=>$sha1hash));
        $form->addField('AUTO_SETTLE_FLAG', 'hidden', array('name'=>'AUTO_SETTLE_FLAG', 'value'=>$autosettle));
        $form->addField('RETURN_TSS', 'hidden', array('name'=>'RETURN_TSS', 'value'=>1));
        $form->addField('SHIPPING_CODE', 'hidden', array('name'=>'SHIPPING_CODE', 'value'=>$shippingPostcode));
        $form->addField('SHIPPING_CO', 'hidden', array('name'=>'SHIPPING_CO', 'value'=>$shippingCountry));
        $form->addField('BILLING_CODE', 'hidden', array('name'=>'BILLING_CODE', 'value'=>$billingCode));
        $form->addField('BILLING_CO', 'hidden', array('name'=>'BILLING_CO', 'value'=>$billingCountry));
        $form->addField('CUST_NUM', 'hidden', array('name'=>'CUST_NUM', 'value'=>$customerID));
        $form->addField('VAR_REF', 'hidden', array('name'=>'VAR_REF', 'value'=>$varref));
        $form->addField('PROD_ID', 'hidden', array('name'=>'PROD_ID', 'value'=>$prodid));
        $form->addField('COMMENT1', 'hidden', array('name'=>'COMMENT1', 'value'=>$comment1));
        $form->addField('COMMENT2', 'hidden', array('name'=>'COMMENT2', 'value'=>$comment2));
        $html = '<html><body>';
        $html.= '<p>' . Mage::getStoreConfig('payment/realex_redirect/redirect_message') . '</p>';
        $html.= $form->toHtml();
//        $html.= '<script type="text/javascript">document.getElementById("realex_redirect_checkout").submit();</script>';
        $html.= '</body></html>';

        Mage::helper('realex_core')->log($html, '/redirect');

        return $html;
    }
}