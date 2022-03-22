<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace LatitudeNew\Payment\Model\Config\Backend;

/**
 * Class Term
 */
class Term extends \Magento\Framework\App\Config\Value
{

    /**
     * {@inheritdoc}
     */
    public function beforeSave()
    {
        $label = $this->getData('field_config/label');
        $services = $this->getFieldsetDataValue('payment_services');

        if($services != 'LPAY'){
            if ($this->getValue() == '') {
                throw new \Magento\Framework\Exception\ValidatorException(__($label . ' is required.'));
            }
            $this->setValue($this->getValue());
        } else {
            $this->setValue('');
        }
        parent::beforeSave();
    }
}
