<?php

class BlueAcorn_UniversalAnalytics_Model_Observer extends Mage_Core_Model_Observer {

    
    public function viewProductCollection($observer) {
        $collection = $observer->getCollection();
        $monitor = Mage::getSingleton('baua/monitor');

        preg_match('/Resource_(.*)_Collection/', get_class($collection), $listName);
        if (is_array($listName) && count($listName) >= 2) {
            $listName = str_replace('_', ' ', $listName[1]);
        }

        foreach ($collection as $product) {
            $monitor->addProductImpression($product, $listName);
        }
    }


    public function viewProduct($observer) {
        $product = $observer->getProduct();

        if ($product !== null) {
            $monitor = Mage::getSingleton('baua/monitor');
            if( preg_match('/checkout\/cart/', Mage::helper('core/url')->getCurrentUrl()) === 0){
                $monitor->setAction('detail');
            }
            $monitor->addProduct($product);
        }
    }

    public function viewPage($observer) {
        $monitor = Mage::getSingleton('baua/monitor');
        $cartItems = Mage::getModel('checkout/cart')->getQuote()->getAllItems();

        foreach ($cartItems as $item) {
            $monitor->addQuoteProduct($item);
        }
    }

    public function viewPromotion($observer) {
        $block = $observer->getBlock();
        $className = get_class($block);

        if ($className == 'Enterprise_Banner_Block_Widget_Banner') {
            $monitor = Mage::getSingleton('baua/monitor');
            $alias = $block->getBlockAlias();
            $transport = $observer->getTransport();
            $html = $transport->getHtml();
            $modifiedHtml = preg_replace('/(^<\w+\s+)/', '$1 banner-alias="' . $alias . '" ', $html);
            $transport->setHtml($modifiedHtml);

            foreach ($block->getBannerIds() as $id) {
                $banner = Mage::getModel('enterprise_banner/banner')->load($id);
                $monitor->addPromoImpression($banner, $alias);
            }
        }
    }
}