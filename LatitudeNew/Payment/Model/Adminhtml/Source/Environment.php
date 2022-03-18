<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace LatitudeNew\Payment\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Environment
 */
class Environment implements OptionSourceInterface
{
    const LATITUDE_ENVIRONMENT_PRODUCTION = 'production';
    const LATITUDE_ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::LATITUDE_ENVIRONMENT_PRODUCTION,
                'label' => 'Production',
            ],
            [
                'value' => self::LATITUDE_ENVIRONMENT_SANDBOX,
                'label' => 'Sandbox'
            ]
        ];
    }
}
