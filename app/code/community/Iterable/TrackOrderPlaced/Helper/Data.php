<?php

class Iterable_TrackOrderPlaced_Helper_Data extends Mage_Core_Helper_Abstract
{

    const XML_PATH_ITERABLE_API_KEY = 'api_options/api_key_options/api_key';
    const XML_PATH_ENABLED_EVENTS = 'advanced_options/tracking_options/enabled_events';

    private function getDecodedMagentoApiToken()
    {
        $magentoApiKey = Mage::getStoreConfig(self::XML_PATH_ITERABLE_API_KEY);

        return json_decode(base64_decode($magentoApiKey));
    }

    private function getIterableApiToken()
    {
        $apiKeyJson = $this->getDecodedMagentoApiToken();
        if ($apiKeyJson == null) {
            return null;
        }

        return $apiKeyJson->t;
    }

    public function getNewsletterEmailListId()
    {
        $apiKeyJson = $this->getDecodedMagentoApiToken();
        if ($apiKeyJson == null) {
            return null;
        }

        return $apiKeyJson->n;
    }

    public function getAccountEmailListId()
    {
        $apiKeyJson = $this->getDecodedMagentoApiToken();
        if ($apiKeyJson == null) {
            return null;
        }

        return $apiKeyJson->u;
    }

    private function callIterableApi($event, $endpoint, $params, $skipTrackCheck = false)
    {
        if ($skipTrackCheck == false) {
            $eventsToTrack = Mage::getStoreConfig(self::XML_PATH_ENABLED_EVENTS);
            $eventsToTrack = explode(",", $eventsToTrack);
            if (!in_array($event, $eventsToTrack)) {
                Mage::log("Iterable: tracking disabled for event " . $event);

                // TODO - maybe run this before gathering data about the cart
                return null;
            }
        }
        $apiKey = $this->getIterableApiToken();
        if ($apiKey == null) {
            return null;
        }
        $url = "https://api.iterable.com/{$endpoint}?api_key={$apiKey}";
//        $url = "http://localhost:9000{$endpoint}?api_key={$apiKey}";
        try {
            $client = new Zend_Http_Client($url);
        } catch (Exception $e) {
            Mage::log("Warning: unable to create http client with url {$url} ({$e->getMessage()})");

            return null;
        }
        $client->setMethod(Zend_Http_Client::POST);
        // $client->setHeaders('Content-Type', 'application/json'); 
        $json = json_encode($params);
        $client->setRawData($json, 'application/json');
        try {
            $response = $client->request();
            $status = $response->getStatus();
            if ($status != 200) {
                Mage::log(
                    "Iterable Tracker: Unable to track event at {$endpoint} with params {$json}; got status {$status} with body {$response->getBody()}"
                );
            }

            return $response;
        } catch (Exception $e) {
            Mage::log(
                "Warning: unable to send event at {$endpoint} with params {$json} to Iterable ({$e->getMessage()})"
            );

            return null;
        }
    }

    public function getIp()
    {
        return Mage::helper('core/http')->getRemoteAddr(false);
    }

    public function setCurrentIp(&$dataFields)
    {
        if (!array_key_exists('ip', $dataFields)) {
            $dataFields['ip'] = $this->getIp();
        }
    }

    public function updateUser($email, $dataFields = array(), $eventNameHint = null)
    {
        $endpoint = '/api/users/update';
        $params = array(
            'email' => $email
        );
        $this->setCurrentIp($dataFields);
        $params['dataFields'] = $dataFields;
        $eventName = isset($eventNameHint) ? $eventNameHint
            : Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_USER;

        return $this->callIterableApi($eventName, $endpoint, $params);
    }

    public function subscribeEmailToList($email, $listId, $dataFields = array(), $resubscribe = false)
    {
        $endpoint = '/api/lists/subscribe';
        $params = array(
            'listId'      => $listId,
            'subscribers' => array(
                array(
                    'email' => $email
                )
            ),
            'resubscribe' => $resubscribe
        );
        if (!empty($dataFields)) {
            $params['subscribers'][0]['dataFields'] = $dataFields;
        }

        return $this->callIterableApi(
            Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_NEWSLETTER_SUBSCRIBE, $endpoint, $params
        );
    }

    public function unsubscribeEmailFromList($email, $listId)
    {
        $endpoint = '/api/lists/unsubscribe';
        $params = array(
            'listId'      => $listId,
            'subscribers' => array(
                array(
                    'email' => $email
                )
            )
            // 'campaignId' => iterableCid cookie?
        );

        return $this->callIterableApi(
            Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_NEWSLETTER_UNSUBSCRIBE, $endpoint, $params
        );
    }

    public function track(
        $event, $email, $dataFields = array(), $campaignId = null, $templateId = null, $skipTrackCheck = false
    ) {
        $endpoint = '/api/events/track';
        $params = array(
            'email'     => $email,
            'eventName' => $event
        );

        $cookieModel = Mage::getModel('core/cookie');
        if ($campaignId == null) {
            $campaignId = $cookieModel->get('iterableEmailCampaignId');
            $campaignId = empty($campaignId) ? null: intval($campaignId);
        }
        if ($campaignId != null) {
            $params['campaignId'] = $campaignId;
        }

        if ($templateId == null) {
            $templateId = $cookieModel->get('iterableTemplateId');
            $templateId = empty($templateId) ? null : intval($templateId);
        }
        if ($templateId != null) {
            $params['templateId'] = $templateId;
        }
        if (!empty($dataFields)) {
            $params['dataFields'] = $dataFields;
        }

        return $this->callIterableApi($event, $endpoint, $params, $skipTrackCheck);
    }

    public function updateCart($email, $items, $dataFields = array())
    {
        $endpoint = '/api/commerce/updateCart';
        $params = array(
            'user'  => array(
                'email' => $email
            ),
            'items' => $items
        );
        if (!empty($dataFields)) {
            $params['user']['dataFields'] = $dataFields;
        }

        return $this->callIterableApi(
            Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_CART_UPDATED, $endpoint, $params
        );
    }

    public function trackPurchase(
        $email, $items, $total, $campaignId = null, $templateId = null, $dataFields = array(),
        $customerDataFields = array()
    ) {
        $endpoint = '/api/commerce/trackPurchase';
        $params = array(
            'user'  => array(
                'email' => $email
            ),
            'items' => $items,
            'total' => $total
        );
        if (!empty($dataFields)) {
            $params['dataFields'] = $dataFields;
        }
        if (!empty($customerDataFields)) {
            $params['user']['dataFields'] = $customerDataFields;
        }

        $cookieModel = Mage::getModel('core/cookie');
        if ($campaignId == null) {
            $campaignId = $cookieModel->get('iterableEmailCampaignId');
            $campaignId = empty($campaignId) ? null: intval($campaignId);
        }
        if ($campaignId != null) {
            $params['campaignId'] = $campaignId;
        }

        if ($templateId == null) {
            $templateId = $cookieModel->get('iterableTemplateId');
            $templateId = empty($templateId) ? null : intval($templateId);
        }
        if ($templateId != null) {
            $params['templateId'] = $templateId;
        }

        return $this->callIterableApi(
            Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_ORDER, $endpoint, $params
        );
    }

    public function triggerCampaign($email, $campaignId, $dataFields = null)
    {
        $endpoint = '/api/email/target';
        $params = array(
            'recipientEmail' => $email,
            'campaignId'     => $campaignId
        );
        if (!is_null($dataFields)) {
            $params['dataFields'] = $dataFields;
        }

        return $this->callIterableApi(
            Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_TRIGGER_EMAIL, $endpoint, $params
        );
    }

    public function trackShipment($email, $shipment)
    {
        return $this->track(Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_SHIPMENT, $email, $shipment);
    }

    public function trackReview($email, $review)
    {
        return $this->track(Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_REVIEW, $email, $review);
    }

    public function trackWishlist($email, $wishlist)
    {
        return $this->updateUser(
            $email, array(
            'wishlist' => $wishlist
        ), Iterable_TrackOrderPlaced_Model_Trackingeventtypes::EVENT_TYPE_WISHLIST_ADD_PRODUCT
        );
    }
}
