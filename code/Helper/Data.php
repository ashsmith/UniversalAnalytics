<?php

class BlueAcorn_UniversalAnalytics_Helper_Data extends Mage_Core_Helper_Abstract
{
    const BAUA_SESSION_STOREDHTML_NAME = 'baua_session_data';
    protected $_translationArray = array();


    public function generateProductImpressions() {
        $monitor = Mage::getSingleton('baua/monitor');
        
        return $monitor->generateProductImpressions();
    }

    public function generatePromoImpressions() {
        $monitor = Mage::getSingleton('baua/monitor');

        return $monitor->generatePromoImpressions();
    }

    public function generateProductClickEvents() {
        $monitor = Mage::getSingleton('baua/monitor');

        return $monitor->generateProductClickEvents();
    }

    public function generatePromoClickEvents() {
        $monitor = Mage::getSingleton('baua/monitor');

        return $monitor->generatePromoClickEvents();
    }

    public function getAction() {
        $monitor = Mage::getSingleton('baua/monitor');

        return $monitor->getAction();
    }


    public function getCollectionListName($collectionObject) {
        $listName = null;

        preg_match('/Resource_(.*)_Collection/', get_class($collectionObject), $listName);
        if (is_array($listName) && count($listName) >= 2) {
            $listName = str_replace('_', ' ', $listName[1]);
        }

        return $listName;
    }

    /**
     * Get translation values from Config Defaults
     * @param $part
     * @return mixed
     */
    public function getTranslation($part)
    {
        return Mage::getStoreConfig('baua/translation/'.$part);
    }

    /**
     * Sets session varable for rending on the frontend in the next pageview
     * @param $data
     */
    public function setSessionData($data)
    {
        /*
         * STORE $data IN SESSION
         * */
        Mage::getSingleton('core/session')->setData(self::BAUA_SESSION_STOREDHTML_NAME, $data);
    }

    public function isActive()
    {
        return (bool) Mage::getStoreConfig('google/baua/active');
    }
}