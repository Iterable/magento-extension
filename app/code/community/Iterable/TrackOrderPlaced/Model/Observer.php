<?php
/**
 * Our class name should follow the directory structure of
 * our Observer.php model, starting from the namespace,
 * replacing directory separators with underscores.
 * i.e. app/code/local/Iterable/
 *                     TrackOrderPlaced/Model/Observer.php
 *
 * @author Iterable
 */
class Iterable_TrackOrderPlaced_Model_Observer
{

    // HELPER FUNCTIONS
    // TODO - move to Helpers

    private function getCategories($product) {
        // http://stackoverflow.com/questions/4252547/display-all-categories-that-a-product-belongs-to-in-magento
        $currentCatIds = $product->getCategoryIds();
        if (empty($currentCatIds)) {
            return array();
        }
        $childCategories = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('url')
            ->addAttributeToFilter('entity_id', $currentCatIds)
            ->addIsActiveFilter();
        $categories = array();
        foreach($childCategories as $category) {
            $curkey = &$categories;
            $parents = $category->getParentCategories();
            usort($parents,function($categoryA, $categoryB) { return $categoryA->getLevel() < $categoryB->getLevel() ? -1: 1; } );
            /*
              // if instead we want it in dictionary/map form, we can use this (also uasort instead of usort)
            $lastName = end($parents)->getName();
            foreach ($parents as $parentCat) {
                $name = $parentCat->getName();
                $isLast = $name == $lastName;
                if (!array_key_exists($name, $curkey)) {
                    if ($isLast) {
                        array_push($curkey, $name);
                    } else {
                        $curkey[$name] = array();
                    }
                }
                if (!$isLast) { // assigning to that index creates it so don't assign
                    $curkey = &$curkey[$name];
                }
            }
            */
            $names = array_map(function($c) { return $c->getName(); }, $parents);
            array_push($categories, $names);
        }
        return $categories;
    }

    private function toIterableItem($product)
    {
        $item = $product;
        $cls = get_class($product);
        $isOrder = $cls == "Mage_Sales_Model_Order_Item";
        $isQuote = $cls == "Mage_Sales_Model_Quote_Item";
        if ($isOrder or $isQuote) {
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
        }
        $typeId = $product->getTypeId();
        
        if ($isOrder) {
            $price = $item->getPrice();
            $quantity = $item->getQtyOrdered();
        } elseif ($isQuote) {
            $price = $item->getCalculationPrice(); 
            $quantity = $item->getQty();
        } else {
            $price = ($typeId == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ?
                // or maybe $pricemodel->getTotalBundleItemsPrice($product)
                Mage::getModel('bundle/product_price')->getFinalPrice($qty=1.0, $product) :
                $product->getPrice();
            $quantity = intval($product->getCartQty());
        }
        
        $imageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/'.$product->getImage();
        $thumbnailUrl = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getThumbnail());
        $categoryNames = array();
        foreach ($this->getCategories($product) as $categoryBreadcrumbs) {
            $categoryNames[] = implode('/', $categoryBreadcrumbs);
        }
        return array(
            'id' => $product->getEntityId(),
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'categories' => $categoryNames,
            //'itemDescription' => $product->getDescription(), // maybe use short description instead?
            'imageUrl' => $imageUrl,
            'thumbnailUrl' => $thumbnailUrl,
            'url' => $product->getProductUrl(),
            'quantity' => $quantity,
            'price' => $price == NULL ? 0.0: floatval($price) // TODO - make sure price isn't NULL
            //'dataFields' => array(
            //    'typeId' => $typeId
            //)
        );
    }

    public function getItemsFromQuote($quote=NULL, $includeConfigurableSubproducts=TRUE)
    { 
        if ($quote == NULL) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
        }
        $quoteItems = array();
        foreach ($quote->getAllItems() as $item) {
            $iterableItem = $this->toIterableItem($item);
            $parent = $item->getParentItem();
            if (!$parent) {
                $quoteItems[$item->getId()] = $iterableItem;
            } /* else {
                $parentItem = &$quoteItems[$parent->getId()];
                if ($includeConfigurableSubproducts or ($parentItem['dataFields']['typeId'] != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)) {
                    if (!array_key_exists('subproducts', $parentItem)) {
                        $parentItem['subproducts'] = array();
                    }
                    array_push($parentItem['subproducts'], $iterableItem);
                }
                } */
        }
        return $quoteItems;
    }


    // EVENT HOOKS

    private function sendCartUpdated($items=NULL)
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return;
        }
        if ($items == NULL) {
            $items = array_values($this->getItemsFromQuote(NULL, FALSE));
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $helper = Mage::helper('trackorderplaced');
        $helper->updateCart($customer->getEmail(), $items); 
    }

    /**
     * Called when something is added to the cart
     */
    public function checkoutCartProductAddAfter(Varien_Event_Observer $observer)
    {
        // it seems that the price is empty sometimes on a configurable, and this seems to fix that...
        $quote = $observer->getEvent()->getQuoteItem()->getQuote();
        $quote->collectTotals();
        $quote->save();
        
        $this->sendCartUpdated();
    }

    /**
     * Called when a product is updated (quantity changed on item page, bundle reconfigured, etc)
     */
    public function checkoutCartProductUpdateAfter(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuoteItem()->getQuote();
        $quote->collectTotals();
        $quote->save();

        $this->sendCartUpdated();
    }

    /**
     * Called when quantities changed, etc on shopping cart page ("Update Shopping Cart" clicked)
     */
    public function checkoutCartUpdateItemsAfter(Varien_Event_Observer $observer)
    {
        $this->sendCartUpdated();
    }

    /** 
     * Called when something is removed from the cart (for example via the trash can symbol on cart page)
     * Unforunately it also gets called on updateItems with quantity = 0, or when you reconfigure a configurable product with different options (so we'll get a few extra events)
     */
    public function salesQuoteRemoveItem(Varien_Event_Observer $observer)
    {
        $this->sendCartUpdated();
    }
    
    /**
     * Gets fired before the quote is saved. Seems to happen on changes to cart, in addition to whenever we view it
     * There doesn't seem to be any event called when the user clicks "Clear Shopping Cart", so hook into this and check what they clicked
     */
    public function salesQuoteSaveBefore(Varien_Event_Observer $observer)
    {
        $updateAction = (string)Mage::app()->getRequest()->getParam('update_cart_action');

        if ($updateAction == 'empty_cart') {
            $this->sendCartUpdated();
        }
    }

    /**
     * Generates the items html. Copied from Mage_Core_Model_Email_Template_Filter->layoutDirective
     */
    private function generateOrderItemsHtml($order)
    {
        $params = array(
            'order' => $order
        );

        $layout = Mage::getModel('core/layout');
        $layout->getUpdate()->addHandle('sales_email_order_items');
        $layout->getUpdate()->load();

        $layout->generateXml();
        $layout->generateBlocks();

        foreach ($layout->getAllBlocks() as $blockName => $block) {
            foreach ($params as $k => $v) {
                $block->setDataUsingMethod($k, $v);
            }
        }

        /**
         * Add output method for first block
         */
        $allBlocks = $layout->getAllBlocks();
        $firstBlock = reset($allBlocks);
        if ($firstBlock) {
            $layout->addOutputBlock($firstBlock->getNameInLayout());
        }

        $layout->setDirectOutput(false);

        return $layout->getOutput();
    }

    /**
     * borrowed from Mage_Core_Model_Email_Template
     */
    protected function getLogoUrl($store)
    {
        $fileName = $store->getConfig(Mage_Core_Model_Email_Template::XML_PATH_DESIGN_EMAIL_LOGO);
        if ($fileName) {
            $uploadDir = Mage_Adminhtml_Model_System_Config_Backend_Email_Logo::UPLOAD_DIR;
            $fullFileName = Mage::getBaseDir('media') . DS . $uploadDir . DS . $fileName;
            if (file_exists($fullFileName)) {
                return Mage::getBaseUrl('media') . $uploadDir . '/' . $fileName;
            }
        }
        return Mage::getDesign()->getSkinUrl('images/logo_email.gif');
    }

    /**
     * borrowed from Mage_Core_Model_Email_Template
     */
    protected function getLogoAlt($store)
    {
        $alt = $store->getConfig(Mage_Core_Model_Email_Template::XML_PATH_DESIGN_EMAIL_LOGO_ALT);
        if ($alt) {
            return $alt;
        }
        return $store->getFrontendName();
    }

    /**
     * Gets called when an order is placed
     */
    public function orderPlaced(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $store = $order->getStore();
        $payment = $order->getPayment();
        $orderItems = $this->getItemsFromQuote($order, FALSE);
        $items = array_values($orderItems);
        $email = $order->getCustomerEmail();

        $cookieModel = Mage::getModel('core/cookie');
        // $iterableUid = $cookieModel->get('iterable_uid');
        $campaignId = $cookieModel->get('iterableEmailCampaignId');
        $campaignId = empty($campaignId) ? NULL: intval($campaignId);
        $templateId = $cookieModel->get('iterableTemplateId');
        $templateId = empty($templateId) ? NULL: intval($templateId);

        $subtotal = $order->getSubtotal();
        $dataFields = array(
            'subtotal' => $subtotal,
            'grandTotal' => $order->getGrandTotal(),
            'taxAmount' => $order->getTaxAmount(),
            'shippingAmount' => $order->getShippingAmount(),
            'incrementId' => $order->increment_id,
            'orderCreatedAtLong' => $order->getCreatedAtFormated('long'),
            'orderIsNotVirtual' => $order->getIsNotVirtual(),
            'billingAddress' => $order->getBillingAddress()->getData(),
            'billingAddressHtml' => $order->getBillingAddress()->format('html'),
            'shippingAddress' => $order->getShippingAddress()->getData(),
            'shippingAddressHtml' => $order->getShippingAddress()->format('html'),
            'shippingDescription' => $order->getShippingDescription(),
            'emailCustomerNote' => $order->getEmailCustomerNote(),
            'statusLabel' => $order->getStatusLabel(),
            'storeFrontendName' => $order->getStore()->getFrontendName(),
            'storeUrl' => Mage::app()->getStore(Mage::getDesign()->getStore())->getUrl(''), // from Mage_Core_Model_Email_Template_Filter->storeDirective
            'logoUrl' => $this->getLogoUrl($store),
            'logoAlt' => $this->getLogoAlt($store),
            'payment' => $payment->getData(),
            'discountAmount' => $order->getDiscountAmount(),
            'discountDescription' => $order->getDiscountDescription()
        );

        ///////////////
        // COPIED FROM MAGENTO CORE: Mage_Sales_Model_Order->sendNewOrderEmail()
        $storeId = $order->getStore()->getId();
        try {
            // Retrieve specified view block from appropriate design package (depends on emulated store)
            $paymentBlock = Mage::helper('payment')->getInfoBlock($payment)
                ->setIsSecureMode(true);
            $paymentBlock->getMethod()->setStore($storeId);
            $dataFields['paymentHtml'] = $paymentBlock->toHtml();
        } catch (Exception $exception) {
            Mage::log("Iterable Warning: unable to generate paymentHtml ({$exception->getMessage()})");
        }
        //////////// end payment_html

        $dataFields['orderItemsHtml'] = $this->generateOrderItemsHtml($order);

        $customerDataFields = array(
            'firstName' => $order->getCustomerFirstname(),
            'lastName' => $order->getCustomerLastname()
        );
        $helper = Mage::helper('trackorderplaced');
        $helper->trackPurchase($email, $items, $subtotal, $campaignId, $templateId, $dataFields, $customerDataFields);

        // don't need to clear cart, server does it automatically
    }

    /**
     * Gets called when a customer saves their data
     * Also seems to get called at several other times (after an order, etc)
     */
    public function customerSaveAfter(Varien_Event_Observer $observer) 
    {
        $customer = $observer->getCustomer();
        
        $email = $customer->getEmail();

        $dataFields = $customer->getData();
        unset($dataFields["is_subscribed"]); // type changes between int (0 and 1) and boolean
        $dataFields['firstName'] = $customer->getFirstname();
        $dataFields['lastName'] = $customer->getLastname();

        // set shipping address
        $defaultShipping = $customer->getDefaultShippingAddress();
        if ($defaultShipping) { $dataFields['defaultShipping'] = $defaultShipping->getData(); }
        // set billing address
        $defaultBilling = $customer->getDefaultBillingAddress();
        if ($defaultBilling) { $dataFields['defaultBilling'] = $defaultBilling->getData(); }
        // unset password/conf... unset created_at because that never changes, and it's in a bad format to boot
        $fieldsToUnset = array('password', 'password_hash', 'confirmation', 'subscriber_confirm_code', 'created_at'); 
        foreach ($fieldsToUnset as $fieldToUnset) {
            if (array_key_exists($fieldToUnset, $dataFields)) {
                unset($dataFields[$fieldToUnset]);
            }
        }
        $helper = Mage::helper('trackorderplaced');
        $helper->updateUser($email, $dataFields);

        // new accounts fire two saves. updating the user profile a 2nd time should be fine, but don't subscribe them to new accounts twice
        if (Mage::registry('iterable_customer_save_after_observer_executed'.$email)) {
            return;
        }
        Mage::register('iterable_customer_save_after_observer_executed'.$email, true);

        // if they're just creating their account, add them to a new users list
        if (!$customer->getOrigData()) {
            $listId = $helper->getAccountEmailListId();
            if ($listId != NULL) {
                $helper->subscribeEmailToList($email, $listId, $dataFields); 
            }
        }
    }

    /*
    public function addToCart(Varien_Event_Observer $observer)
    {
        Mage::log("add to cart!");
        if (!Mage::helper('customer')->isLoggedIn()) {
            return;
        }
        $customer = Mage::helper('customer')->getCustomer();
        $email = $customer->getEmail();
        $product = $observer->getProduct();
        $cartItem = $this->toIterableItem($product);
        $subproductsKey = 'subproducts';
        $typeId = $product->getTypeId();
        if ($typeId == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            // nothing extra to do for simple products
        } elseif ($typeId == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            // configurable item seems to already have all important stuff set
        } elseif ($typeId == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL) {
            // hmm... probably nothing extra needed here
        } elseif ($typeId == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $cartItem[$subproductsKey] = array();
            $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
            foreach ($associatedProducts as $associatedProduct) {
                if (!$associatedProduct->getCartQty()) {
                    continue;
                }
                array_push($cartItem[$subproductsKey], $this->toIterableItem($associatedProduct));
            }
        } elseif ($typeId == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $cartItem[$subproductsKey] = array();
            // also have thing in Mage_Bundle_Block_Catalog_Product_View_Type_Bundle
            $bundlePriceHelper = Mage::getModel("bundle/product_price");
            $optionsCollection = $bundlePriceHelper->getOptions($product);
            foreach ($optionsCollection as $option) {
                if (!$option->getSelections()) {
                    continue; // no selections
                }
                foreach ($option->getSelections() as $selection) {
                    // Mage::log("{$option->getTitle()} - {$selection->getName()} ({$selection->getCartQty()} @ {$selection->getPrice()})");
                    array_push($cartItem[$subproductsKey], $this->toIterableItem($selection));
                }
            }            
        } else { 
            // hmm...
        }

        $dataFields = array(
            'item' => $cartItem
        );
        $helper = Mage::helper('trackorderplaced');
        $helper->track(Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_ADD_TO_CART, $email, $dataFields);
    }
    */

    /** 
     * Called whenever a newsletter subscriber is saved
     */
    public function newsletterSubscriberSaveAfter(Varien_Event_Observer $observer)
    {
        if (!$subscriber = $observer->getEvent()->getSubscriber()) {
            return;
        }
        if (!$subscriber->getIsStatusChanged()) {
            return; // don't send if nothing changed
        }
        $helper = Mage::helper('trackorderplaced');
        $listId = $helper->getNewsletterEmailListId();
        if ($listId == NULL) {
            return;
        }
        $email = $subscriber->getSubscriberEmail();
        $dataFields = $subscriber->getData();
        unset($dataFields["is_subscribed"]); // type changes between int (0 and 1) and boolean
        switch ($subscriber->getStatus()) {
            case Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED:
                $helper->subscribeEmailToList($email, $listId, $dataFields, True);
                break;
            case Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED:
                $helper->unsubscribeEmailFromList($email, $listId);
                break;
            default:
                break;
        }
    }

    public function shipmentSaveAfter($observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();

        $trackingData = array();
        $tracks = $shipment->getAllTracks();
        foreach ($tracks as $track) {
            $trackingData[] = $track->getData();
        }

        $comments = array();
        $commentsCollection = $shipment->getCommentsCollection();
        foreach ($commentsCollection as $comment) {
            $comments[] = $comment->getData();
        }

        $items = array();
        foreach ($shipment->getAllItems() as $item) {
            $items[] = $item->getData();
        }

        $shipmentData = array(
            "tracking" => $trackingData,
            "comments" => $comments,
            "billingAddress" => $shipment->getBillingAddress()->getData(),
            "shippingAddress" => $shipment->getShippingAddress()->getData(),
            "shippingDescription" => $order->getShippingDescription(),
            "items" => $items,
            "incrementId" => $order->increment_id
        );

        $email = $shipment->getOrder()->getCustomerEmail();

        $helper = Mage::helper('trackorderplaced');
        $helper->trackShipment($email, $shipmentData);
    }

    public function reviewSaveAfter($observer)
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return;
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $review = $observer->getObject();

        $helper = Mage::helper('trackorderplaced');
        $helper->trackReview($customer->getEmail(), $review->getData());
    }

    public function wishlistAddProduct($observer)
    {
        $wishlist = $observer->getWishlist();
        $itemCollection = $wishlist->getItemCollection();
        $itemCollection->setOrder('added_at','desc');

        $items = array();
        foreach ($itemCollection->getData() as $item) {
            $product = Mage::getModel('catalog/product')->load($item['product_id']);
            $item['product'] = $this->toIterableItem($product);
            $items[] = $item;
        }

        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $helper = Mage::helper('trackorderplaced');
        $helper->trackWishlist($customer->getEmail(), $items);
    }

    public function productView($observer)
    {
        $iterableLastViewedItems = "iterableLastViewedItems";

        // make sure they're actually viewing a product
        $action = $observer->getAction();
        if (!$action) {
            return;
        }

        $request = $action->getRequest();
        if (!$request) {
            return;
        }

        if (!in_array($action->getFullActionName(), array('catalog_product_view'))) {
            return;
        }

        $product = Mage::registry('current_product');
        if (!$product) {
            return;
        }

        // get the product they're viewing
        $item = $this->toIterableItem($product);
        $date = Mage::getModel('core/date');
        $gmtDate = $date->gmtDate('Y-m-d H:i:s');
        $item['createdAt'] = $gmtDate;

        // get saved history
        $cookie = Mage::getModel('core/cookie');
        $items = $cookie->get($iterableLastViewedItems);
        if ($items) {
            $items = json_decode($items, true);
        } else {
            $items = array();
        }

        array_unshift($items, $item); // prepend the item just viewed

        $items = array_slice($items, 0, 5); // make sure at most 5 items - they're big and there's a cookie size limit

        $epochSeconds = $date->gmtTimestamp();
        $epochSecondsDayAgo = $epochSeconds - (60 * 60 * 24);
        foreach ($items as $index => $item) {
            $createdAt = $item['createdAt'];
            $createdAtTimestamp = strtotime($createdAt);
            if ($createdAtTimestamp < $epochSecondsDayAgo) {
                unset($items[$index]);
            }
        }

        $cookie->set(
            $iterableLastViewedItems,
            json_encode($items),
            3600 * 24, // period
            "/", // path
            null, // domain
            null, // secure
            true); // httponly

        if (!Mage::helper('customer')->isLoggedIn()) {
            return;
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $helper = Mage::helper('trackorderplaced');
        $helper->updateUser($customer->getEmail(), array(
            'lastViewedItems' => $items
        ));
    }

}
