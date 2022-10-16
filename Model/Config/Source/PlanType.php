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
 * Class PlanType
 */
class PlanType implements OptionSourceInterface
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
                ['value' => 'equal', 'label' => __('Equal Monthly')],
                ['value' => 'minimum', 'label' => __('Minimum Monthly or Flexible')]
            ];
        }

        return $this->options;
    }
}
