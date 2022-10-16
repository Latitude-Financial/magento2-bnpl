<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace LatitudeNew\Payment\Model\Config\Source;


use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class PlanPeriod
 */
class PlanPeriod implements OptionSourceInterface
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
                ['value' => '6', 'label' => __('6 Months')],
                ['value' => '12', 'label' => __('12 Months')],
                ['value' => '18', 'label' => __('18 Months')],
                ['value' => '24', 'label' => __('24 Months')],
                ['value' => '36', 'label' => __('36 Months')],
                ['value' => '48', 'label' => __('48 Months')],
                ['value' => '50', 'label' => __('50 Months')],
                ['value' => '60', 'label' => __('60 Months')],
            ];
        }

        return $this->options;
    }
}
