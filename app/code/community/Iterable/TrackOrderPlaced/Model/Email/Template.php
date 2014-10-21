<?php
/**
 * This class overwrites Magento's default send functionality by routing all
 * emails through Iterable using the Send API call.
 * 
 * @author    Iterable
 */
class Iterable_TrackOrderPlaced_Model_Email_Template extends Mage_Core_Model_Email_Template {

    private function getIterableCampaignIdForTemplateName($templateName) {
        $transactionalEmailConfig = Mage::helper('trackorderplaced')->getTransactionalEmailConfig();
        if (array_key_exists($templateName, $transactionalEmailConfig)) {
            // if there's something for this template, check whether it's enabled and return the id if so
            list($enabled_cfg, $iterable_campaign_id_cfg) = $transactionalEmailConfig[$templateName];
            return Mage::getStoreConfig($enabled_cfg) ? intval(Mage::getStoreConfig($iterable_campaign_id_cfg)): null;
        } else {
            // if there's nothing for this template don't send through Iterable
            return null;
        }
    }

    private function isDefaultSendingDisabledForTemplateName($templateName) {
        $disabledTemplateConfig = Mage::helper('trackorderplaced')->getDefaultEmailDisabledConfig();
        return
            array_key_exists($templateName, $disabledTemplateConfig) &&
            intval(Mage::getStoreConfig($disabledTemplateConfig[$templateName]));
    }

    /**
     * Send mail to recipient
     *
     * @param   array|string       $email        E-mail(s)
     * @param   array|string|null  $name         receiver name(s)
     * @param   array              $variables    template variables
     * @return  boolean
     **/
    public function send($email, $name = null, array $variables = array()) 
    {
        $helper = Mage::helper('trackorderplaced');

        $template_name = $this->getId();
        if ($this->isDefaultSendingDisabledForTemplateName($template_name)) {
//            Mage::log("Suppressing default email for " . $template_name . " due to Iterable config");
            return true;
        }
        $iterable_campaign_id = $this->getIterableCampaignIdForTemplateName($template_name);

        if (empty($iterable_campaign_id)) {
//            Mage::log("Not sending " . $template_name . " through Iterable");
            return parent::send($email, $name, $variables);
        }

        // email and name can be either arrays or strings; we don't care about the name though
        $emails = array_values((array)$email);

        $anyFailures = false;
        foreach ($emails as $email) {
            try {
                $response = $helper->triggerCampaign($email, $iterable_campaign_id);
                if (is_null($response) || ($response->getStatus() != 200)) {
                    Mage::log("Unable to trigger Iterable email for user " . $email . " and campaign " . $iterable_campaign_id . "; sending default Magento email");
                    parent::send($email, null, $variables);
                }
            } catch (Exception $e) {
                Mage::logException($e);
                $anyFailures = true;
            }
        }

        return ! $anyFailures;
    } 

}
?>