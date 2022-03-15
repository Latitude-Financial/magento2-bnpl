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
 * Class Term
 */
class Term implements OptionSourceInterface
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
                ['value' => '6', 'label' => __('6 months')],
                ['value' => '12', 'label' => __('12 months')],
                ['value' => '18', 'label' => __('18 months')],
                ['value' => '24', 'label' => __('24 months')],
            ];
        }

        return $this->options;
    }
}
