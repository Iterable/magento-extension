<?php

class Iterable_TrackOrderPlaced_Model_Trackingeventtypes
{

    const EVENT_TYPE_ORDER = 'order';
    const EVENT_TYPE_USER = 'user';
    const EVENT_TYPE_CART_UPDATED = 'cartUpdated';
    const EVENT_TYPE_NEWSLETTER_SUBSCRIBE = 'newsletterSubscribe';
    const EVENT_TYPE_NEWSLETTER_UNSUBSCRIBE = 'newsletterUnsubscribe';
    const EVENT_TYPE_TRIGGER_EMAIL = 'triggerEmail';
    const EVENT_TYPE_SHIPMENT = 'shipment';
    const EVENT_TYPE_REVIEW = 'review';
    const EVENT_TYPE_WISHLIST_ADD_PRODUCT = 'wishlistAddProduct';

    /** @const */
    private static $eventTypes = array(
        self::EVENT_TYPE_ORDER => 'Orders',
        self::EVENT_TYPE_USER => 'User',
        self::EVENT_TYPE_CART_UPDATED => 'Cart Updated',
        self::EVENT_TYPE_NEWSLETTER_SUBSCRIBE => 'Newsletter Subscribe',
        self::EVENT_TYPE_NEWSLETTER_UNSUBSCRIBE => 'Newsletter Unsubscribe',
        self::EVENT_TYPE_TRIGGER_EMAIL => 'Trigger Email',
        self::EVENT_TYPE_SHIPMENT => 'Shipment',
        self::EVENT_TYPE_REVIEW => 'Reviews',
        self::EVENT_TYPE_WISHLIST_ADD_PRODUCT => 'Added to Wishlist'
    );

    public function toOptionArray()
    {
        $events = array();
        foreach (self::$eventTypes as $key => $name) {
            $events[] = array('value' => $key, 'label'=>Mage::helper('trackorderplaced')->__($name));
        }
        return $events;
    }

}