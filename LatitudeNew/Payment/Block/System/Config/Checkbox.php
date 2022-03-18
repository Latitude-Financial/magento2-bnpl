<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace LatitudeNew\Payment\Block\System\Config;

class Checkbox extends \Magento\Config\Block\System\Config\Form\Field
{
    const CONFIG_PATH = 'payment/latitudepay/payment_terms';

    protected $_template = 'LatitudeNew_Payment::system/config/checkbox.phtml';

    protected $_values = null;
    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Config\Model\Config\Structure $configStructure
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
    /**
     * Retrieve element HTML markup.
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setNamePrefix($element->getName())
            ->setHtmlId($element->getHtmlId());

        return $this->_toHtml();
    }
    
    public function getValues()
    {
        $values = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        foreach ($objectManager->create('LatitudeNew\Payment\Model\Config\Source\Term')->toOptionArray() as $value) {
            $values[$value['value']] = $value['label'];
        }

        return $values;
    }
    /**
     * 
     * @param  $name 
     * @return boolean
     */
    public function isChecked($name)
    {
        return in_array($name, $this->getCheckedValues());
    }
    /**
     * 
     *get the checked value from config
     */
    public function getCheckedValues()
    {
        if (is_null($this->_values)) {
            $data = $this->getConfigData();
            if (isset($data[self::CONFIG_PATH])) {
                $data = $data[self::CONFIG_PATH];
            } else {
                $data = '';
            }
            $this->_values = explode(',', $data);
        }

        return $this->_values;
    }
}