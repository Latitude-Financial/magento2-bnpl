<?php
/**
 * Created by PhpStorm.
 * User: brockie
 * Category: Latitude
 * Package: Payment
 * Date: 11/09/19
 * Time: 13:49 PM
 */

namespace LatitudeNew\Payment\Logger;
use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/latitude.log';
}