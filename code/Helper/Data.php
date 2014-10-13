<?php

class BlueAcorn_UniversalAnalytics_Helper_Data extends Mage_Core_Helper_Abstract
{
    const BAUA_SESSION_STOREDHTML_NAME = 'baua_session_data';
    protected $_translationArray = array();

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

    /**
     * Fetches session varable of data to render on the frontend in next pageview
     * @param $data
     * @return mixed
     */
    public function getSessionData($data)
    {
        return Mage::getSingleton('core/session')->getData(self::BAUA_SESSION_STOREDHTML_NAME);
    }

    public function isActive()
    {
        return (bool) Mage::getStoreConfig('google/baua/active');
    }
}