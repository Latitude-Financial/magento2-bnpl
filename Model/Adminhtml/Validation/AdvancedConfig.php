<?php
namespace LatitudeNew\Payment\Model\Adminhtml\Validation;

use \Magento\Framework\App\Config\Value;

class AdvancedConfig extends Value
{
    public function beforeSave()
    {
        $data = $this->getValue();

        if (!empty($data) && json_decode($data) === null) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Please enter a valid JSON for Latitude advanced config.'));
        }
        
        $this->setValue($this->getValue());
        parent::beforeSave();
    }
}
