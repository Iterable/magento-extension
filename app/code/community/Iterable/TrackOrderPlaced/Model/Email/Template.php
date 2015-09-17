<?php

/**
 * This class overwrites Magento's default send functionality by routing all
 * emails through Iterable using the Send API call.
 *
 */
class Iterable_TrackOrderPlaced_Model_Email_Template extends Mage_Core_Model_Email_Template
{

    protected $_helper;

    /**
     * Send mail to recipient
     *
     * @param   array|string      $email     E-mail(s)
     * @param   array|string|null $name      receiver name(s)
     * @param   array             $variables template variables
     *
     * @return  boolean
     **/
    public function send($email, $name = null, array $variables = array())
    {
        $this->_helper = Mage::helper('trackorderplaced');

        // events take precedence.

        $templateCode = ($this->getTemplateCode()) ? $this->getTemplateCode() : $this->getTemplateId();

        $intercept = unserialize(Mage::getStoreConfig('transactional_email_options/events/intercept'));
        $cleanMap = array();
        if (is_array($intercept) && count($intercept) > 0) {
            foreach ($intercept as $map) {
                $cleanMap = array($map['template'] => array('campaign_id' => $map['campaign_id'],
                                                            'template_id' => $map['template_id'],
                                                            'event_name'  => $map['event_name']));
            }
        }
        if (array_key_exists($templateCode, $cleanMap)) {
            $result = $this->_sendAsEvent($email, $name, $variables, $cleanMap);
            if (!is_null($result)) {
                return $result;
            }
        } else {
            $intercept = unserialize(Mage::getStoreConfig('transactional_email_options/campaigns/intercept'));
            $cleanMap = array();
            if (is_array($intercept) && count($intercept) > 0) {
                foreach ($intercept as $map) {
                    $cleanMap = array($map['template'] => $map['campaign_id']);
                }
            }
            if (array_key_exists($templateCode, $cleanMap)) {
                $result = $this->_sendAsCampaign($email, $name, $variables, $cleanMap);
                if (!is_null($result)) {
                    return $result;
                }

            }
        }

        // the fallback default

        return parent::send($email, $name, $variables);

    }


    /**
     * Email intercepted to event
     *
     * @param   array|string      $email     E-mail(s)
     * @param   array|string|null $name      receiver name(s)
     * @param   array             $variables template variables
     * @param   array             $cleanMap  admin config data
     *
     * @return bool
     */
    private function _sendAsEvent($email, $name = null, array $variables = array(), $cleanMap = array())
    {
        try {
            // the default event name is the template code
            $eventName = ($this->getTemplateCode()) ? $this->getTemplateCode() : $this->getTemplateId();
            if (array_key_exists('event_name', $cleanMap[$eventName]) && !empty($cleanMap[$eventName]['event_name'])) {
                $eventName = $cleanMap[$eventName]['event_name'];
            }
            $campaignId = null;
            if (array_key_exists('campaign_id', $cleanMap[$eventName])
                && !empty($cleanMap[$eventName]['campaign_id'])
            ) {
                $campaignId = $cleanMap[$eventName]['campaign_id'];
            }
            $templateId = null;
            if (array_key_exists('template_id', $cleanMap[$eventName])
                && !empty($cleanMap[$eventName]['template_id'])
            ) {
                $templateId = $cleanMap[$eventName]['template_id'];
            }
            $variables['name'] = $name;
            unset($variables['store']);
            if (array_key_exists('data', $variables)) {
                if ($variables['data'] instanceof Varien_Object) {
                    $data = $variables['data']->getData();
                }
            }
            $dataFields = array_merge($variables, $data);
            unset($dataFields['data']);

            return $this->_helper->track($eventName, $email, $dataFields, $campaignId, $templateId, true);

        } catch (Exception $e) {
            mage::logException($e);

            return parent::send($email, $name, $variables);
        }

        return true;
    }


    /**
     * EMail intercepted as campaign
     *
     * @param   array|string      $email     E-mail(s)
     * @param   array|string|null $name      receiver name(s)
     * @param   array             $variables template variables
     * @param   array             $cleanMap  admin config data
     *
     * @return bool
     */
    private function _sendAsCampaign($email, $name = null, array $variables = array(), $cleanMap = array())
    {
        try {
            $campaignId = (int)$cleanMap[$this->getTemplateCode()];

            if (empty($campaignId)) {
                return parent::send($email, $name, $variables);
            }

            // email and name can be either arrays or strings; we don't care about the name though
            $emails = array_values((array)$email);

            $anyFailures = false;
            foreach ($emails as $email) {
                try {
                    $response = $this->_helper->triggerCampaign($email, $campaignId);
                    if (is_null($response) || ($response->getStatus() != 200)) {
                        Mage::log(
                            "Unable to trigger Iterable email for user " . $email . " and campaign "
                            . $campaignId
                            . "; sending default Magento email"
                        );
                        parent::send($email, null, $variables);
                    }
                } catch (Exception $e) {
                    Mage::logException($e);
                    $anyFailures = true;
                }
            }

            return !$anyFailures;
        } catch (Exception $e) {
            mage::logException($e);
        }
    }

}
