<?php

class BlueAcorn_UniversalAnalytics_Model_Monitor {

    private $productImpressionList = Array();

    private $productAttributeValueList = Array();

    private $helper;

    public function __construct() {
        $this->helper = Mage::helper('baua');
    }

    public function addProductImpression($product) {
        $trans = $this->helper->getTranslation('addImpression');

        $data = Array();

        foreach ($trans as $googleAttr => $ba) {
            $data[$googleAttr] = $this->findAttributeValue($product, $ba);
        }

    }

    protected function findAttributeValue($product, $set) {
        $newValue = null;

        foreach (Array('getListAttributeValue', 'getNormalAttributeValue') as $method) {
            $newValue = $this->$method($product, $set);
            if ($newValue !== null) break;
        }

        return $newValue;
    }

    protected function getListAttributeValue($product, $name) {
        if (array_key_exists($name, $this->productAttributeValueList)) {
            return $this->productAttributeValueList[$name][$product->getData($name)];
        } else {
            if ($this->getAttributeValueFromList($name)) $this->getListAttributeValue($product, $name);
        }
    }

    protected function getNormalAttributeValue($product, $name) {
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        $value = $product->$method();
        return $value;
    }

    protected function getAttributeValueFromList($attributeCode) {

        $attributeDetails = Mage::getSingleton("eav/config")->getAttribute("catalog_product", $attributeCode); 
        $options = $attributeDetails->getSource()->getAllOptions(false);

        foreach ($options as $option) {
            $this->productAttributeValueList[$attributeCode][$option['value']] = $option['label'];
        }

        return (is_array($options) && count($options) > 0);
    }


}