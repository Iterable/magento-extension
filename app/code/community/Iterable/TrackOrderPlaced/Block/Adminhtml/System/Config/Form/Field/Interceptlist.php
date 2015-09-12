<?php

/**
 * Create map used in admin config
 * Template_name => campaign_id
 *
 * Class Iterable_TrackOrderPlaced_Block_Adminhtml_System_Config_Form_Field_Interceptlist
 * @author Lucas van Staden (sales@proxiblue.com.au)
 */

class Iterable_TrackOrderPlaced_Block_Adminhtml_System_Config_Form_Field_Interceptlist
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn('template', array(
            'label' => Mage::helper('trackorderplaced')->__('Template Name'),
            'style' => 'width:300px',
        ));
        $this->addColumn('campaign_id', array(
            'label' => Mage::helper('trackorderplaced')->__('Campaign ID'),
            'style' => 'width:300px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('trackorderplaced')->__('Add New');
        parent::__construct();
    }
}