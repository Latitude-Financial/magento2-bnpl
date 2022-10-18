<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Setup;

/**
 * Class UpgradeSchema 
 */
class UpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $_resourceConfig;

    protected $helper;

    const ERROR_CODE_DUPLICATE_ENTRY = 1062;
    /**
     * Construct
     *
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     */
    public function __construct(
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \LatitudeNew\Payment\Helper\Data $helper
    ) {
        $this->_resourceConfig = $resourceConfig;
        $this->helper = $helper;
    }

    /**
     * Install 
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function upgrade(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $this->helper->log('*** LATITUDEPAY UPGRADE - Adding Custom Status, current version: '.$context->getVersion().' ***');
        $installer = $setup;

        // Required tables
        $statusTable = $installer->getTable('sales_order_status');
        $statusStateTable = $installer->getTable('sales_order_status_state');

        $this->helper->log($statusTable ? 'sales_order_status exists' : 'sales_order_status doesn\'t exists');
        $this->helper->log($statusTable ? 'sales_order_status_state exists' : 'sales_order_status_state doesn\'t exists');

        $installer->startSetup();
        if (version_compare($context->getVersion(), '3.0.0', '<')) {
            $this->helper->log('*** LATITUDEPAY UPGRADE - version below 3.0.0, adding new status... ***');

            try{
                // Insert statuses
                $installer->getConnection()->insertArray(
                    $statusTable,
                    [
                        'status',
                        'label'
                    ],
                    [
                        ['status' => 'pending_latitude_approval', 'label' => 'Pending Latitude Approval']
                    ]
                );
            }
            catch(\Exception $e){
                $this->helper->log('Status Exists - '.$e->getCode().' trying update...');
                if ($e->getCode() === self::ERROR_CODE_DUPLICATE_ENTRY) 
                {
                        $installer->getConnection()->update(
                            $statusTable,
                            [
                                'label' => 'Pending Latitude Approval'
                            ],
                            "status='pending_latitude_approval'"
                        );
                }
                else{
                    throw $e;
                }
            }
            
            try{
                // Insert states and mapping of statuses to states
                $installer->getConnection()->insertArray(
                    $statusStateTable,
                    [
                        'status',
                        'state',
                        'is_default'
                    ],
                    [
                        [
                            'status' => 'pending_latitude_approval',
                            'state' => 'new',
                            'is_default' => 0
                        ]
                    ]
                );
            }
            catch(\Exception $e){
                $this->helper->log('Status State Exists - '.$e->getCode().' trying update...');
                if ($e->getCode() === self::ERROR_CODE_DUPLICATE_ENTRY) 
                {
                        $installer->getConnection()->update(
                            $statusStateTable,
                            [
                                'is_default' => 0
                            ],
                            "state='new'&status='pending_latitude_approval'",
                        );
                }
                else{
                    throw $e;
                }
            }

            $installer->endSetup();
        }
    }
}
