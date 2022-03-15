<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Setup;

/**
 * Class InstallSchema 
 */
class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $_resourceConfig;

    /**
     * Construct
     *
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     */
    public function __construct(
        \Magento\Config\Model\ResourceModel\Config $resourceConfig
    ) {
        $this->_resourceConfig = $resourceConfig;
    }

    /**
     * Install 
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function install(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {

        $installer = $setup;

        // Required tables
        $statusTable = $installer->getTable('sales_order_status');
        $statusStateTable = $installer->getTable('sales_order_status_state');

        $installer->startSetup();

        // Insert statuses
        $installer->getConnection()->insertArray(
            $statusTable,
            [
                'status',
                'label'
            ],
            [
                ['status' => 'pending_approval', 'label' => 'Pending Latitude\'s Approval']
            ]
        );
         
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
                    'status' => 'pending_approval',
                    'state' => 'new',
                    'is_default' => 0
                ]
            ]
        );

        $installer->endSetup();
    }
}
