<?php

class BlueAcorn_UniversalAnalytics_Model_Observer extends Mage_Core_Model_Observer {

    public function __construct() {
        $this->monitor = Mage::getSingleton('baua/monitor');
        $this->helper  = Mage::helper('baua');
    }
    
    public function viewProductCollection($observer) {
        $collection   = $observer->getCollection();
        $listName     = $this->helper->getCollectionListName($collection);

        foreach ($collection as $product) {
            $this->monitor->addProductImpression($product, $listName);
        }
    }


    public function viewProduct($observer) {

        if (Mage::registry('baua_observer_lock')) return;

        Mage::register('baua_observer_lock', true);

        $product = $observer->getProduct();

        if ($product->getVisibility() == 1) return null;

        if ($product !== null) {
            if( preg_match('/' . $product->getUrlKey() . '/', Mage::helper('core/url')->getCurrentUrl())){
                $this->monitor->setAction('detail');
            }

            $this->monitor->addProduct($product);

            // Also add all associated products if this is a grouped
            // product
            if ($product->getTypeId() == 'grouped') {
                $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);

                foreach ($associatedProducts as $associatedProduct) {
                    $this->monitor->addProductImpression($associatedProduct, 'Grouped');
                }
            }
        }

        Mage::unregister('baua_observer_lock');
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