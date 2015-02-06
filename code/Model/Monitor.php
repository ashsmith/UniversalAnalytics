<?php

class BlueAcorn_UniversalAnalytics_Model_Monitor {

    private $productImpressionList = Array();
    private $promoImpressionList   = Array();

    private $quoteList = Array();

    private $productAttributeValueList = Array();

    private $exclusionList = Array();

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
        return $this->generateImpressionJSList('addImpression', $this->productImpressionList);
    }

    public function generateProductClickEvents() {
        return $this->generateProductClickList();
    }

    public function generatePromoImpressions() {
        return $this->generateImpressionJSList('addPromo', $this->promoImpressionList);
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

        $productOptions = $item->getProductOptions();
        $orderOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

        $productData      = $this->parseObject($product, 'addProduct');
        $itemData         = $this->parseObject($item, 'addProduct');
        $itemData['variant'] = $this->extractAttributes($productOptions, $orderOptions);

        return array_filter(array_merge($productData, $itemData), 'strlen');
    }

    /**
     * Add product information to the impression list
     *
     * @name addProductImpression
     * @param Mage_Catalog_Model_Product $product
     * @param string $listName
     */
    public function addProductImpression($product, $listName) {
        $this->addToProductImpressionList($product, $listName, 'addImpression');
    }

    public function addProduct($product, $listName = 'Detail') {
        $this->addToProductImpressionList($product, $listName, 'addProduct');
    }

    protected function extractAttributes($attributeInfo) {
        $params = func_get_args();
        $variantArray = Array();

        foreach ($params as $attributeInfo) {

            if ( is_array($attributeInfo) && in_array('attributes_info', array_keys($attributeInfo), true) ) {
                foreach ($attributeInfo['attributes_info'] as $option) {
                    $variantArray[] = $option['value'];
                }
            }
        }

        $variant = implode('-', $variantArray);

        return $variant;
    }

    protected function addToProductImpressionList($product, $listName, $action) {
        if ($action !== 'addProduct') {
            if ($this->isExcludedList($listName)) return;

            $wishlist = Mage::helper('wishlist')->getWishlistItemCollection();

            foreach ($wishlist as $wishlistItem) {
                if ($product->getId() == $wishlistItem->getProductId()) return;
            }
        }

        if ($product->getTypeID() == 'configurable') {
            $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

            $attributeOptionList = Array();
            foreach ($productAttributeOptions as $option) {
                $attributeOptionList[] = $option['attribute_id'];
            }
        }

        $productUrl = $product->getProductUrl();
        $oldData    = Array();

        if (isset($this->productImpressionList[$listName][$productUrl])) {
            $oldData = $this->productImpressionList[$listName][$productUrl];
        }

        $data             = $this->parseObject($product, $action);
        $data['list']     = $listName;

        if (isset($attributeOptionList)) $data['option-list'] = $attributeOptionList;

        $data = array_merge($data, $oldData);

        if (Mage::getSingleton('checkout/session')->getQuote()->hasProductId($product->getId())) {
            $data['hide-impression'] = true;
        }

        $this->productImpressionList[$listName][$productUrl] = $data;
    }

    public function addPromoImpression($banner, $alias) {
        $data = $this->parseObject($banner, 'addPromo');

        $this->promoImpressionList['default'][$alias] = $data;
    }

    protected function isExcludedList($listName) {
        $this->generateExclusionList();
        $pass = false;

        foreach ($this->exclusionList as $exclusionName) {
            $pass = ($pass || ($listName === $exclusionName));
        }

        return $pass;
    }

    protected function parseObject($object, $translationName) {
        $trans         = $this->helper->getTranslation($translationName);
        $data          = Array();
        $attributeList = Array();

        foreach ($trans as $googleAttr => $magentoAttr) {
            $attributeList = (is_array($magentoAttr)) ? array_keys($magentoAttr) : Array($magentoAttr);

            foreach ($attributeList as $subAttribute) {
                $data[$googleAttr] = $this->findAttributeValue($object, $subAttribute);

                if ( ($data[$googleAttr] !== null) && ($data[$googleAttr] !== '') ) {
                    if ($googleAttr == 'price') {
                        $newPrice = $this->convertPrice($data[$googleAttr]);
                        $newPrice = Mage::app()->getStore()->roundPrice($newPrice);
                        $data[$googleAttr] = (string)$newPrice;
                    }
                    if ($googleAttr == 'quantity') {
                        $data[$googleAttr] = (int)$data[$googleAttr];
                    }

                    break;
                }
            }
        }

        return array_filter($data, 'strlen');
    }

    protected function convertPrice($value) {
        $baseCurrencyCode    = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        return (string)Mage::helper('directory')->currencyConvert($value, $baseCurrencyCode, $currentCurrencyCode);
    }

    protected function generateImpressionJSList($action, $list) {
        $impressionList = '';
        $impressedList = Array();

        foreach ($list as $listName => $listItem) {
            $newAction = ($listName == "Detail") ? 'addProduct' : $action;
            $position = 1;
            foreach ($listItem as $item) {
                if ( (!isset($item['hide-impression']) || ($listName == 'Detail')) && !in_array($item['id'], $impressedList)) {
                    $impressedList[] = $item['id'];
                    // We may need to deal with promo positions here
                    // at a later point, but for now this field gets
                    // filtered out.
                    $item['position'] = $position++;
                    $item = $this->filterObjectArray($item, $newAction);
                    $impressionList .= $this->JS->generateGoogleJS('ec:' . $newAction, $item);
                }
            }
        }

        return $impressionList;
    }

    protected function filterObjectArray($itemArray, $translationName) {
        $finalArray = Array();
        $trans      = $this->helper->getTranslation($translationName);

        foreach ($trans as $googleAttr => $magentoAttr) {
            if (in_array($googleAttr, array_keys($itemArray))) {
                $finalArray[$googleAttr] = $itemArray[$googleAttr];
            }
        }

        return $finalArray;
    }

    protected function generatePromoClickList() {
        $text = '';

        foreach ($this->promoImpressionList as $key => $item) {
            foreach ($item as $alias => $promoData) {

                $promoText = $this->JS->generateGoogleJS('ec:addPromo', $promoData);
                $action = $this->JS->generateGoogleJS('ec:setAction', 'promo_click');
                $send = $this->JS->generateGoogleJS('send', 'event', 'Promotions', 'click');

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

                if (isset($item['option-list']) && $listName == 'Detail') {
                    $variantArray = Array();
                    foreach ($item['option-list'] as $optionId) {
                        $variantArray[] =  '$$(\'select[id="attribute'. $optionId .'"] option:selected\')[0].innerHTML';
                    }

                    $variantText = '[' . implode(', ', $variantArray) . ']' . '.join("-")';

                    $item['variant'] = new Zend_Json_Expr($variantText);
                }

                $item = $this->filterObjectArray($item, 'addProduct');

                $product = $this->JS->generateGoogleJS('ec:addProduct', $item);
                $action = $this->JS->generateGoogleJS('ec:setAction', 'click', array('list'=>$listName));
                $send = $this->JS->generateGoogleJS('send', 'event', $listName, 'click');

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
                $currency = $this->JS->generateGoogleJS('set', '&cu', Mage::app()->getStore()->getCurrentCurrencyCode());


                $text .= $this->JS->attachForeachObserve(
                    'button[onClick*="checkout/cart/add"][onClick*="product/' . $item['id'] . '"]',
                    $currency . $product . $action . $send
                );

                if ($listName == 'Detail') {
                    $productList = '';
                    if (isset($this->productImpressionList['Grouped'])) {
                        foreach ($this->productImpressionList['Grouped'] as $groupItem) {
                            $productList .= $this->JS->generateGoogleJS('ec:addProduct', $groupItem);
                        }
                    }

                    $text .= $this->JS->attachForeachObserve(
                        'form[action*="checkout/cart/add"][action*="product/' . $item['id'] . '"] button.btn-cart',
                        $currency . $product . $productList . $action . $send
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

        $newValue = str_replace(array("\n", "\t", "\r"), ' ', $newValue);

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

    protected function generateExclusionList() {
        if (count($this->exclusionList) < 1) {
            $this->exclusionList = Array (
                'Selection',
                $this->helper->getCollectionListName(Mage::helper('catalog/product_compare')->getItemCollection()),
                'Product Type Configurable Product',
            );
        }
    }

}