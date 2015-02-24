<?php

class BlueAcorn_UniversalAnalytics_Model_Observer extends Mage_Core_Model_Observer {

    const registryName = 'baua_observer_lock_';

    public function __construct() {
        $this->monitor = Mage::getSingleton('baua/monitor');
        $this->helper  = Mage::helper('baua');
    }

    /**
     * Sets a registry entry based on the provided $name.
     *
     * @name lockObserver
     * @param string $name
     * @return bool
     */
    protected function lockObserver($name) {
        $registryName = self::registryName . $name;

        if (Mage::registry($registryName)) return true;

        Mage::register($registryName, true);

        return false;
    }

    /**
     * Remove registry entry based on the provided $name.
     *
     * @name unlockObserver
     * @param string $name
     */
    protected function unlockObserver($name) {
        $registryName = self::registryName . $name;
        Mage::unregister($registryName);
    }
    
    /**
     * Main entry point when loading a product collection. Generates a
     * $listName and then passes the products to the monitor to be
     * added as product impressions.
     *
     * @name viewProductCollection
     * @param observer $observer
     */
    public function viewProductCollection($observer) {
        // Lock down this function in order to prevent infinite
        // recursion loops
        if ($this->lockObserver('collection')) return;

        $collection   = $observer->getCollection();
        $listName     = $this->helper->getCollectionListName($collection);

        foreach ($collection as $product) {
            $this->monitor->addProductImpression($product, $listName);
        }

        $this->unlockObserver('collection');
    }

    /**
     * Main entry point when loading a single product. Collects
     * pertinent information before sending product to the monitor to
     * add a product impression. Has additional logic for handling
     * grouped products.
     *
     * @name viewProduct
     * @param observer $observer
     */
    public function viewProduct($observer) {
        // Lock down this function in order to prevent infinite
        // recursion loops
        if ($this->lockObserver('product')) return;

        $product = $observer->getProduct();

        if ($product->getVisibility() == 1) return null;

        if ($product !== null) {
            $list = 'Single';

            if( preg_match('/' . $product->getUrlKey() . '/', Mage::helper('core/url')->getCurrentUrl())){
                $this->monitor->setAction('detail');
                $list = 'Detail';
            }

            $this->monitor->addProduct($product, $list);

            // Also add all associated products if this is a grouped
            // product
            if ($product->getTypeId() == 'grouped') {
                $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);

                foreach ($associatedProducts as $associatedProduct) {
                    $this->monitor->addProductImpression($associatedProduct, 'Grouped');
                }
            }
        }

        $this->unlockObserver('product');
    }

    /**
     * Collects quote items in the user's cart and sends them to the
     * monitor.
     *
     * @name viewPage
     * @param observer $observer
     */
    public function viewPage($observer) {
        $cartItems = Mage::getModel('checkout/cart')->getQuote()->getAllItems();

        foreach ($cartItems as $item) {
            $this->monitor->addQuoteProduct($item);
        }
    }

    /**
     * Main entry point when loading a promotion. Collects pertinent
     * information before sending to monitor to add a promo
     * impression. In order to be able to track promotions via
     * Javascript, the outputted HTML of the promotion is modified
     * here to add a 'banner-alias' parameter to the enclosing HTML
     * node.
     *
     * @name viewPromotion
     * @param observer $observer 
     */
    public function viewPromotion($observer) {
        $block = $observer->getBlock();
        $className = get_class($block);

        if ($className == 'Enterprise_Banner_Block_Widget_Banner') {
            $alias = $block->getBlockAlias();
            $transport = $observer->getTransport();
            $html = $transport->getHtml();
            $modifiedHtml = preg_replace('/(^<\w+\s+)/', '$1 banner-alias="' . $alias . '" ', $html);
            $transport->setHtml($modifiedHtml);

            foreach ($block->getBannerIds() as $id) {
                $banner = Mage::getModel('enterprise_banner/banner')->load($id);
                $this->monitor->addPromoImpression($banner, $alias);
            }
        }
    }
}