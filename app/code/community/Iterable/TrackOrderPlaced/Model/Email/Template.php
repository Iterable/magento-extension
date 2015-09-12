<?php

/**
 * This class overwrites Magento's default send functionality by routing all
 * emails through Iterable using the Send API call.
 *
 * @author Lucas van Staden (sales@proxiblue.com.au)
 */
class Iterable_TrackOrderPlaced_Model_Email_Template extends Mage_Core_Model_Email_Template 
{

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
        $helper = Mage::helper('trackorderplaced');

        $interceptMap = unserialize(Mage::getStoreConfig('transactional_email_options/intercept/list'));
        $cleanMap = array();
        if (is_array($interceptMap) && count($interceptMap) > 0) {
            foreach ($interceptMap as $map) {
                $cleanMap = array($map['template'] => $map['campaign_id']);
            }
        }
        if (array_key_exists($this->getTemplateCode(), $cleanMap)) {
            try {
                $iterableCampaignId = (int)$cleanMap[$this->getTemplateCode()];

                if (empty($iterableCampaignId)) {
                    return parent::send($email, $name, $variables);
                }

                // email and name can be either arrays or strings; we don't care about the name though
                $emails = array_values((array)$email);

                $anyFailures = false;
                foreach ($emails as $email) {
                    try {
                        $response = $helper->triggerCampaign($email, $iterableCampaignId);
                        if (is_null($response) || ($response->getStatus() != 200)) {
                            Mage::log(
                                "Unable to trigger Iterable email for user " . $email . " and campaign "
                                . $iterableCampaignId
                                . "; sending default Magento email"
                            );
                            parent::send($email, $name, $variables);
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
        // default, send normal
        parent::send($email, $name, $variables);
    }

}

?>