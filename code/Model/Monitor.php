<?php

class BlueAcorn_UniversalAnalytics_Model_Monitor {

    private $productImpressionList = Array();
    private $promoImpressionList   = Array();

    private $quoteList = Array();

    private $productAttributeValueList = Array();

    private $action = null;

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
        return $this->generateImpressionJSList('ec:addImpression', $this->productImpressionList);
    }

    public function generateProductClickEvents() {
        return $this->generateProductClickList();
    }

    public function generatePromoImpressions() {
        return $this->generateImpressionJSList('ec:addPromo', $this->promoImpressionList);
    }

    public function generatePromoClickEvents() {
        return $this->generatePromoClickList();
    }

    public function setAction($action) {
        $this->action = $action;
    }

    public function getAction() {
        if (isset($this->action)) {
            return $this->JS->generateGoogleJS('ec:setAction', $this->action);
        }
    }

    /**
     * Add generate an array of transaction data
     *
     * @name generateTransactionData
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function generateTransactionData($order) {

        $trans = $this->helper->getTranslation('transaction');
        $data = Array();
        $attributeList = Array();

        foreach ($trans as $magentoAttr => $googleAttr) {
            $attributeList = (is_array($magentoAttr)) ? array_keys($magentoAttr) : Array($magentoAttr);

            foreach ($attributeList as $subAttribute) {
                $data[$googleAttr] = $this->findAttributeValue($order, $subAttribute);
                if ($data[$googleAttr] !== null) break;
            }
        }

        return $data;
    }

    /**
     * Add generate an array of product data
     *
     * @name generateProductData
     * @param Mage_Sales_Model_Quote_Item $item
     * @return array
     */
    public function generateProductData($item) {
        $product = Mage::getModel('catalog/product')->load($item->getProductId());

        if ($product->getVisibility() == 1) return null;

        $data        = $this->parseObject($product, 'addProduct');
        $data['qty'] = $item->getQty();

        return $data;
    }

    /**
     * Add product information to the impression list
     *
     * @name addProductImpression
     * @param Mage_Catalog_Model_Product $product
     * @param string $listName
     */
    public function addProductImpression($product, $listName) {

        preg_match('/Resource_(.*)_Collection/', get_class(Mage::helper('catalog/product_compare')->getItemCollection()), $compareClass);

        if ($product->getVisibility() == 1 ||
            Mage::getSingleton('checkout/session')->getQuote()->hasProductId($product->getId()) ||
            $listName === str_replace('_', ' ', $compareClass[1])
        ) return;

        $data             = $this->parseObject($product, 'addImpression');
        $data['list']     = $listName;
        $data['position'] = isset($this->productImpressionList[$listName]) ? count($this->productImpressionList[$listName]) : '0';

        $this->productImpressionList[$listName][$product->getProductUrl()] = array_filter($data, 'strlen');
    }

    public function addProduct($product, $listName = 'Detail') {
        $data             = $this->parseObject($product, 'addProduct');
        $data['list']     = $listName;
        $data['position'] = isset($this->productImpressionList[$listName]) ? count($this->productImpressionList[$listName]) : '0';

        $this->productImpressionList[$listName][$product->getProductUrl()] = array_filter($data, 'strlen');
    }

    public function addPromoImpression($banner, $alias) {
        $data = $this->parseObject($banner, 'addPromo');

        $this->promoImpressionList['default'][$alias] = array_filter($data, 'strlen');
    }

    protected function parseObject($object, $translationName) {
        $trans         = $this->helper->getTranslation($translationName);
        $data          = Array();
        $attributeList = Array();

        foreach ($trans as $googleAttr => $magentoAttr) {
            $attributeList = (is_array($magentoAttr)) ? array_keys($magentoAttr) : Array($magentoAttr);

            foreach ($attributeList as $subAttribute) {
                $data[$googleAttr] = $this->findAttributeValue($object, $subAttribute);
                if ($data[$googleAttr] !== null) break;
            }
        }

        return $data;
    }

    protected function generateImpressionJSList($action, $list) {
        $impressionList = '';

        foreach ($list as $listName => $listItem) {
            $newAction = ($listName == "Detail") ? 'ec:addProduct' : $action;
            foreach ($listItem as $item) {
                $impressionList .= $this->JS->generateGoogleJS($newAction, $item);
            }
        }

        return $impressionList;
    }

    protected function generatePromoClickList() {
        $text = '';

        foreach ($this->promoImpressionList as $key => $item) {
            foreach ($item as $alias => $promoData) {
                
                $promoText = $this->JS->generateGoogleJS('ec:addPromo', $promoData);
                $action = $this->JS->generateGoogleJS('ec:setAction', 'promo_click');
                $send = $this->JS->generateGoogleJS('send', 'event', 'Promotions', 'click', '');

                $text .= $this->JS->attachForeachObserve('*[banner-alias="' . $alias . '"] a', $promoText . $action . $send);
            }
        }

        return $text;
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

                $text .= $this->JS->attachForeachObserve('a[href="' . $url . '"]', $product . $action . $send);

                if (in_array($item['id'], $this->quoteList)) {
                    $localQuoteList = $this->findQuoteProduct($item['id']);

                    $removeAction = $this->JS->generateGoogleJS('ec:setAction', 'remove');
                    $send = $this->JS->generateGoogleJS('send', 'event', $listName, 'click', 'removeFromCart');

                    foreach ($localQuoteList as $quoteId) {

                        $text .= $this->JS->attachForeachObserve(
                            'a[href*="checkout/cart"][href*="elete/id/' . $quoteId . '"]', 
                            $product . $removeAction . $send
                        );
                    }
                }

                $action = $this->JS->generateGoogleJS('ec:setAction', 'add');
                $send = $this->JS->generateGoogleJS('send', 'event', 'UX', 'click', 'add to cart');

                $text .= $this->JS->attachForeachObserve(
                    'button[onClick*="checkout/cart/add"][onClick*="product/' . $item['id'] . '"]', 
                    $product . $action . $send
                );

                if ($listName == 'Detail') {
                    $text .= $this->JS->attachForeachObserve(
                        'form[action*="checkout/cart/add"][action*="product/' . $item['id'] . '"] button.btn-cart',
                        $product . $action . $send
                    );
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