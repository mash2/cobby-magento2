<?php
namespace Mash2\Cobby\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        /**
         * Create table 'mash2_cobby_queue'
         */
        $table = $installer->getConnection()
            ->newTable($installer->getTable('mash2_cobby_queue'))
        ->addColumn(
            'queue_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Queue Id'
        )->addColumn(
            'object_ids',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '16k',
            ['nullable' => false],
            'Object Ids'
        )->addColumn(
            'object_entity',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            [],
            'Object Entity'
        )->addColumn(
            'object_action',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Object Action'
        )->addColumn(
            'user_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            [],
            'User Name'
        )->addColumn(
            'context',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            [],
            'Context'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
            'Creation Time'
        )->setComment('Cobby Queue Table');

        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }
}