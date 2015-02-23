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
class Realex_Redirect_RedirectController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return void
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('realex_redirect/redirect')
        );
        $this->renderLayout();
    }

    /**
     * @return void
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');

        // cancel order
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $session->addNotice($this->__('Your order with Realex has been cancelled. No funds have been transferred from your credit card'));
                $order->cancel()->save();
            }
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * @return void
     */
    public function responseAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $post = $this->getRequest()->getPost();

        if($post){
            if (isset($post['ORDER_ID'])) {
                if(Mage::getModel('realex/redirect')->processRedirectResponse($post)){
                    $session->setQuoteId($session->getRealexRedirectQuoteId());
                    $this->getResponse()->setBody($this->getLayout()->createBlock('realex/redirect_success')->toHtml());
                }else{
                    $this->getResponse()->setBody($this->getLayout()->createBlock('realex/redirect_error')->toHtml());
                }
            }
        }else{
            //set the quote as inactive after back from Realex
            $session->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}
