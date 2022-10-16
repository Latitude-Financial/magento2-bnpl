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
 * Class Layout
 */
class Layout implements OptionSourceInterface
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
                ['value' => 'standard', 'label' => __('Standard')],
                ['value' => 'inversed', 'label' => __('Inversed')],
                ['value' => 'logo-only', 'label' => __('Logo Only')],
                ['value' => 'disabled', 'label' => __('Disabled')],
            ];
        }

        return $this->options;
    }
}
