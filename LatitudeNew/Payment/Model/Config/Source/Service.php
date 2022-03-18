<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace LatitudeNew\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Service
 */
class Service implements OptionSourceInterface
{
    /**
     * @var array
     */
    private $options;

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = [
                ['value' => 'LPAY', 'label' => __('LatitudePay')],
                ['value' => 'LPAYPLUS', 'label' => __('LatitudePay+')],
                ['value' => 'LPAY,LPAYPLUS', 'label' => __('Co-Presentment')],
            ];
        }

        return $this->options;
    }
}
