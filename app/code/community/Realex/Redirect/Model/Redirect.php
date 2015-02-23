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
class Realex_Redirect_Model_Redirect extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'realex_redirect';
    protected $_formBlockType = 'realex_redirect/form';
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;
    protected $_allowCurrencyCode = array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD','USD');
    protected $_canAuthorize = true;
    protected $_canCapture  = true;

    /**
     * @param $data
     * @return Realex_Redirect_Model_Redirect
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

    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock($this->_formBlockType, $name)
            ->setMethod($this->_code)
            ->setPayment($this->getPayment());

        return $block;
    }

    /**
     * Validate the currency code is avaialable to use for Realex or not
     *
     * @return Realex_Redirect_Model_Redirect
     */

    public function validate()
    {
        parent::validate();
        $currencyCode = Mage::getSingleton('checkout/session')->getQuote()->getBaseCurrencyCode();
        if (!in_array($currencyCode, $this->_allowCurrencyCode)) {
            Mage::throwException(
                Mage::helper('realex_redirect')->__(
                    'Selected currency code (%s) is not compatabile with Realex',
                    $currencyCode
                )
            );
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('realex/redirect/', array('_secure' => true));
    }

    /**
     * @return string
     */
    public function getRealexUrl()
    {
        $url = "https://epage.payandshop.com/epage.cgi";
        return $url;
    }

    /**
     * @return bool
     */
    public function processRedirectResponse($post)
    {
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
        } else {
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
                if ($redirect->getConfigData('capture')) {
                    Mage::helper('realex')->createInvoice($orderid);
                }
                return true;
            } else {
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

    public function saveRealexTransaction($post)
    {
        $realex = Mage::getModel('realex/realex');

        try {
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
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}

?>
