<?php


class Iterable_TrackOrderPlaced_Block_Adminhtml_System_Config_Form_Field_Events
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn('template', array(
            'label' => Mage::helper('trackorderplaced')->__('Template Name'),
            'style' => 'width:200px',
        ));
        $this->addColumn('event_name', array(
            'label' => Mage::helper('trackorderplaced')->__('Event Name'),
            'style' => 'width:300px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('trackorderplaced')->__('Add New');
        parent::__construct();
    }
}