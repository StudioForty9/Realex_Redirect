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
class Realex_Redirect_Block_Form extends Mage_Payment_Block_Form
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('realex/redirect/form.phtml');
        parent::_construct();
    }

    /**
     * @return mixed
     */
    public function getCheckoutMessage()
    {
        return Mage::getStoreConfig('payment/realex_redirect/checkout_message');
    }
}