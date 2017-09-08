<?php

namespace Mash2\Cobby\Model\Import\Product;

class BundleManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductBundleManagementInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var
     */
    private $_existingProductIds;

    /**
     * Attributes' codes which will be allowed anyway, independently from its visibility property.
     *
     * @var array
     */
    protected $_forcedAttributesCodes = array(
        'weight_type',
        'price_type',
        'sku_type',
        'shipment_type'
    );

    /**
     * BundleManagement constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $prodAttrColFac
     * @param \Mash2\Cobby\Model\Product $product
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $prodAttrColFac,
        \Mash2\Cobby\Model\Product $product
    )
    {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->prodAttrColFac = $prodAttrColFac->create();
        $this->resourceModel = $resourceModel;
        $this->productCollectionFactory = $productCollectionFactory->create();
    }

    public function import($rows)
    {
        $result = array();

        $coreResource = $this->resourceModel;

        $optionTable = $coreResource->getTableName('catalog_product_bundle_option');
        $titleTable = $coreResource->getTableName('catalog_product_bundle_option_value');
        $selectionTable = $coreResource->getTableName('catalog_product_bundle_selection');
        $selectionPriceTable = $coreResource->getTableName('catalog_product_bundle_selection_price');
        $relationTable = $coreResource->getTableName('catalog_product_relation');

        $nextAutoOptionId = $this->resourceHelper->getNextAutoincrement($optionTable);
        $nextAutoSelectionId = $this->resourceHelper->getNextAutoincrement($selectionTable);

        $productIds = array_keys($rows);
        $this->_existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        $this->eventManager->dispatch('cobby_import_product_bundleoption_import_before', array('products' => $productIds));

        $items = array();
        foreach ($rows as $productId => $productBundleOptions) {
            if (!in_array($productId, $this->_existingProductIds))
                continue;

            $changedProductIds[] = $productId;
            $items[$productId] = array(
                'relations' => array(),
                'options' => array(),
                'titles' => array(),
                'prices' => array(),
                'selections' => array(),
            );

            $selectionIndex = 0;
            foreach ($productBundleOptions as $productBundleOption) {
                if($productBundleOption['status'] == 'Delete') {
                    continue;
                }

                if (isset($productBundleOption['option_id'])) {
                    $nextOptionId = $productBundleOption['option_id'];
                } else {
                    $nextOptionId = $nextAutoOptionId++;
                }

                $items[$productId]['options'][] = array(
                    'option_id' => $nextOptionId,
                    'parent_id' => $productId,
                    'type' => $productBundleOption['type'],
                    'required' => $productBundleOption['required'],
                    'position' => $productBundleOption['position'],
                );

                foreach ($productBundleOption['titles'] as $productCustomOptionTitle) {
                    $items[$productId]['titles'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionTitle['store_id'],
                        'title' => $productCustomOptionTitle['title']
                    );
                }

                foreach ($productBundleOption['selections'] as $selection) {

                    if (isset($selection['selection_id'])) {
                        $nextSelectionId = $selection['selection_id'];
                    } else {
                        $nextSelectionId = $nextAutoSelectionId++;
                    }

                    $items[$productId]['selections'][] = array(
                        'selection_id' => $nextSelectionId,
                        'option_id' => $nextOptionId,
                        'product_id' => $selection['assigned_product_id'],
                        'parent_product_id' => $productId,
                        'position' => $selection['position'],
                        'is_default' => $selection['is_default'],
                        'selection_qty' => $selection['qty'],
                        'selection_can_change_qty' => $selection['can_change_qty'],
                    );

                    $items[$productId]['relations'][] = array(
                        'parent_id' => $productId,
                        'child_id' => $selection['assigned_product_id']
                    );


                    foreach ($selection['prices'] as $selectionPrice) {
                        $websiteId = $selectionPrice['website_id'];
                        if ($websiteId == 0) {
                            $items[$productId]['selections'][$selectionIndex]['selection_price_value'] = $selectionPrice['price_value'];
                            $items[$productId]['selections'][$selectionIndex]['selection_price_type'] = $selectionPrice['price_type'];
                            $selectionIndex++;
                        } else {
                            $items[$productId]['prices'][] = array(
                                'selection_id' => $nextSelectionId,
                                'website_id' => $websiteId,
                                'selection_price_value' => $selectionPrice['price_value'],
                                'selection_price_type' => $selectionPrice['price_type'],
                            );
                        }
                    }
                }
            }
        }

        foreach ($items as $productId => $item) {
            $this->connection->delete($optionTable, $this->connection->quoteInto('parent_id = ?', $productId));
            $this->connection->delete($relationTable, $this->connection->quoteInto('parent_id = ?', $productId));
            if ($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($optionTable, $item['options']);
                $this->connection->insertOnDuplicate($titleTable, $item['titles'], array('title'));
                if ($item['selections'] && count($item['selections']) > 0) {
                    $this->connection->insertMultiple($selectionTable, $item['selections']);
                    $this->connection->insertOnDuplicate($relationTable, $item['relations']);
                    if ($item['prices'] && count($item['prices'] > 0)) {
                        $this->connection->insertOnDuplicate($selectionPriceTable, $item['prices'], array('selection_price_value', 'selection_price_type'));
                    }
                }
            }
            $result[] = $productId;
        }

        $this->touchProducts($changedProductIds);

        $this->eventManager->dispatch('cobby_import_product_bundleoption_import_after', array('products' => $changedProductIds));

        return array('product_ids' => $result);
    }
}