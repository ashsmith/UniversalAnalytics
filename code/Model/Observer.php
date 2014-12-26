<?php

class BlueAcorn_UniversalAnalytics_Model_Observer extends Mage_Core_Model_Observer {

    const registryName = 'baua_observer_lock_';

    public function __construct() {
        $this->monitor = Mage::getSingleton('baua/monitor');
        $this->helper  = Mage::helper('baua');
    }

    protected function lockObserver($name) {
        $registryName = self::registryName . $name;

        if (Mage::registry($registryName)) return true;

        Mage::register($registryName, true);

        return false;
    }

    protected function unlockObserver($name) {
        $registryName = self::registryName . $name;
        Mage::unregister($registryName);
    }
    
    public function viewProductCollection($observer) {
        if ($this->lockObserver('collection')) return;

        $collection   = $observer->getCollection();
        $listName     = $this->helper->getCollectionListName($collection);

        foreach ($collection as $product) {
            $this->monitor->addProductImpression($product, $listName);
        }

        $this->unlockObserver('collection');
    }


    public function viewProduct($observer) {
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

    public function viewPage($observer) {
        $cartItems = Mage::getModel('checkout/cart')->getQuote()->getAllItems();

        foreach ($cartItems as $item) {
            $this->monitor->addQuoteProduct($item);
        }
    }

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