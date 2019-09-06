<?php

class Ipaymu_Icheckout_Model_Icheckout extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'icheckout';

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('ipaymu/payment/process');
    }

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getUsername() {
        return $this->getConfigData('username');
    }

    public function getApiKey() {
        return $this->getConfigData('apikey');
    }

    public function getApiURL() {
        return $this->getConfigData('apiurl');
    }

}
