<?php

class BlueAcorn_UniversalAnalytics_Model_Observer extends Mage_Core_Model_Observer {


    public function viewProduct($observer) {
        $product = $observer->getProduct();

        if ($product !== null) {
            $monitor = Mage::getSingleton('baua/monitor');
            $monitor->addProductImpression($product);
        }
    }

    /**
     * Observer that builds add product payload
     * @param $observer
     */
    public function addProductJs($observer)
    {
        $test = Mage::getModel('baua/product');
        $productData = $test->getData();

        $_helper = Mage::helper('baua');
        $trans = $_helper->getTranslation('addproduct');
        $product = $observer->getProduct();
        $html = "ga('send', 'pageview');\n";
        $html .= "ga('ec:addProduct', {\n";
        foreach($trans as $ba => $ga)
        {
            $html .= "'$ga': '".$product->getData($ba)."',\n";
        }
        $html .= " });\n";
        $html .= "ga('ec:setAction', 'add');\n";
        $_helper->setSessionData($html);
    }

    /**
     * Builds out Remove Product payload
     * @param $observer
     */
    public function removeProductJS($observer)
    {
        $_helper = Mage::helper('baua');
        $trans = $_helper->getTranslation('removeproduct');
        $quote_item = $observer->getQuoteItem();
        $html = "ga('send', 'pageview');\n";
        $html .= "function addToCart(product) {\n";
        $html .= "ga('ec:addProduct', {\n";
        foreach($trans as $ba => $ga)
        {
            $html .= "'$ga': '".$quote_item->getData($ba)."',\n";
        }
        $html .= "});\n";
        $html .= "ga('ec:setAction', 'remove');\n";// REMOVED FROM CART
        $html .= "ga('send', 'event', 'ecommerce', 'click', 'remove from cart');}\n";// Send data using an event.
        $_helper->setSessionData($html);
    }

    public function captureTransactionJS($observer)
    {
        $_helper = Mage::helper('baua');
        $trans = $_helper->getTranslation('capturetransaction');
        $transitems = $_helper->getTranslation('capturetransactionitems');
        $order = $observer->getOrder();
        $quote = $observer->getQuote();
        $html = "\nga('ec:addProduct');\n";
        foreach($order->getAllItems() as $item) {
            foreach($trans as $ba => $ga) {
                if(!$item->getData($ba)==NULL)
                {
                    $html .= "'$ga': '".$item->getData($ba)."',\n";
                }else{
                    $html .= "'$ga': '".$order->getData($ba)."',\n";
                }
            }
            $html .= "\n";
        }
        $html .= "\n";
        $html .= "});\n";
        $html .= "ga('ec:setAction', 'purchase'{;\n";
        foreach($order->getAllItems() as $item) {
            foreach($transitems as $ba => $ga) {
                if(!$item->getData($ba)==NULL)
                {
                    $html .= "'$ga': '".$item->getData($ba)."',\n";
                }elseif(!$quote->getData($ba)==NULL){
                    $html .= "'$ga': '".$quote->getData($ba)."',\n";
                }else{
                    $html .= "'$ga': '".$order->getData($ba)."',\n";
                }
            }
            $html .= "\n";
        }
        $html .= "ga('send','pageview')";
        $_helper->setSessionData($html);
    }
}