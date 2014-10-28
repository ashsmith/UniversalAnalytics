<?php

class BlueAcorn_UniversalAnalytics_Model_Monitor {

    private $productImpressionList = Array();

    private $quoteList = Array();

    private $productAttributeValueList = Array();

    private $helper;

    /**
     * Constructor, sets up a shortcut variable for the main helper
     * class.
     *
     * @name __construct
     */
    public function __construct() {
        $this->helper = Mage::helper('baua');
        $this->JS = Mage::getSingleton('baua/js');
    }

    public function generateProductImpressions() {
        return $this->generateProductJSList('ec:addImpression');
    }

    public function generateProductClickEvents() {
        return $this->generateProductClickList();
    }

    /**
     * Add product information to the impression list
     *
     * @name addProductImpression
     * @param Mage_Catalog_Model_Product $product
     * @param string $listName
     */
    public function addProductImpression($product, $listName) {
        if ($product->getVisibility() == 1) return;

        $trans = $this->helper->getTranslation('addImpression');
        $data = Array();
        $attributeList = Array();

        foreach ($trans as $googleAttr => $magentoAttr) {
            $attributeList = (is_array($magentoAttr)) ? array_keys($magentoAttr) : Array($magentoAttr);

            foreach ($attributeList as $subAttribute) {
                $data[$googleAttr] = $this->findAttributeValue($product, $subAttribute);
                if ($data[$googleAttr] !== null) break;
            }
        }

        $data['list'] = $listName;
        $data['position'] = isset($this->productImpressionList[$listName]) ? count($this->productImpressionList[$listName]) : '0';

        $this->productImpressionList[$listName][$product->getProductUrl()] = array_filter($data, 'strlen');
    }

    /**
     * Generates a list of product calls in a Universal Analytics
     * format.
     *
     * @name generateProductJSList
     * @param $action
     * @return string
     */
    protected function generateProductJSList($action) {
        $impressionList = '';

        foreach ($this->productImpressionList as $listName => $listItem) {
            foreach ($listItem as $item) {
                $impressionList .= $this->JS->generateGoogleJS($action, $item);
            }
        }

        return $impressionList;
    }

    protected function generateProductClickList() {
        $text = '';
        $urlList = Array();

        foreach ($this->productImpressionList as $listName => $listItem) {
            foreach ($listItem as $url => $item) {
                // This is required in order to avoid multiple event
                // calls, but has the side-effect of basically
                // homoginizing listNames
                if (in_array($url, $urlList)) break;
                $urlList[] = $url;

                $product = $this->JS->generateGoogleJS('ec:addProduct', $item);
                $action = $this->JS->generateGoogleJS('ec:setAction', 'click');
                $send = $this->JS->generateGoogleJS('send', 'event', $listName, 'click', '');

                $text .= '$$(\'a[href="' . $url . '"]\')';
                $text .= $this->JS->each('element' . $this->JS->observe('click', $product . $action . $send));
                $text .= "\n";

                if (in_array($item['id'], $this->quoteList)) {
                    $localQuoteList = $this->findQuoteProduct($item['id']);

                    $removeAction = $this->JS->generateGoogleJS('ec:setAction', 'remove');
                    $send = $this->JS->generateGoogleJS('send', 'event', $listName, 'click', 'removeFromCart');

                    foreach ($localQuoteList as $quoteId) {
                        $text .= '$$(\'a[href*="checkout/cart"][href*="elete/id/' . $quoteId . '"]\')';
                        $text .= $this->JS->each('element' . $this->JS->observe('click', $product . $removeAction . $send));
                        $text .= "\n";
                    }
                }

                $action = $this->JS->generateGoogleJS('ec:setAction', 'add');
                $send = $this->JS->generateGoogleJS('send', 'event', 'UX', 'click', 'add to cart');

                $text .= '$$(\'button[onClick*="checkout/cart/add"][onClick*="product/' . $item['id'] . '"]\')';
                $text .= $this->JS->each('element' . $this->JS->observe('click', $product . $action . $send));
                $text .= "\n";

                if ($listName == 'Detail') {
                    $text .= '$$(\'form[action*="checkout/cart/add"][action*="product/' . $item['id'] . '"] button.btn-cart\')';
                    $text .= $this->JS->each('element' . $this->JS->observe('click', $product . $action . $send));
                    $text .= "\n";
                }

            }
        }

        return $text;
    }

    public function addQuoteProduct($item) {
        $product = $item->getProduct();
        $this->quoteList[$item->getId()] = $product->getId();
    }

    protected function findQuoteProduct($id) {
        $results = Array();

        foreach ($this->quoteList as $quoteId => $productId) {
            if ($id == $productId) {
                $results[] = $quoteId;
            }
        }
        
        return $results;
    }

    /**
     * Initial entry point for finding product attribute values
     *
     * @name findAttributeValue
     * @param Mage_Catalog_Model_Product $product
     * @param string $attribute
     * @return mixed
     */
    protected function findAttributeValue($product, $attribute) {
        $newValue = null;

        foreach (Array('getListAttributeValue', 'getNormalAttributeValue') as $method) {
            $newValue = $this->$method($product, $attribute);
            if ($newValue !== null) break;
        }

        return $newValue;
    }

    /**
     * Gets values out of list attributes
     *
     * @name getListAttributeValue
     * @param Mage_Catalog_Model_Product $product
     * @param string $name
     * @return mixed
     */
    protected function getListAttributeValue($product, $name) {
        if (array_key_exists($name, $this->productAttributeValueList)) {
            $index = $product->getData($name);
            if ($index !== null) {
                return $this->productAttributeValueList[$name][$index];
            }
        } else {
            if ($this->getAttributeValueFromList($name)) return $this->getListAttributeValue($product, $name);
        }
    }

    /**
     * Gets values out of "normal" attributes. This is for any
     * attribute that can be retrieved via normal get methods.
     *
     * @name getNormalAttributeValue
     * @param Mage_Catalog_Model_Product $product
     * @param string $name
     * @return mixed
     */
    protected function getNormalAttributeValue($product, $name) {
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        $value = $product->$method();

        if (is_a($value, 'Mage_Catalog_Model_Resource_Category_Collection')) {
            $value = $this->parseCategoryValue($value);
        }

        return $value;
    }

    /**
     * Build a list of product categories in hierarchical order.
     *
     * @name parseCategoryValue
     * @param Mage_Catalog_Model_Resource_Category_Collection $objectCollection
     * @return string
     */
    protected function parseCategoryValue($objectCollection) {
        $objectCollection->addAttributeToSelect('name');
        $object = $objectCollection->getFirstItem();
        $names = Array();

        while ($object->getLevel() > 0) {
            $names[] = $object->getName();
            $object = $object->getParentCategory();
        }
        
        return implode('/', array_reverse($names));
    }

    /**
     * Attempts to pull all values for an attribute list and save
     * them.
     *
     * @name getAttributeValueFromList
     * @param string $attributeCode
     * @return bool
     */
    protected function getAttributeValueFromList($attributeCode) {
        $attributeDetails = Mage::getSingleton("eav/config")->getAttribute("catalog_product", $attributeCode); 
        $options = $attributeDetails->getSource()->getAllOptions(false);

        foreach ($options as $option) {
            $this->productAttributeValueList[$attributeCode][$option['value']] = $option['label'];
        }

        return (is_array($options) && count($options) > 0);
    }


}