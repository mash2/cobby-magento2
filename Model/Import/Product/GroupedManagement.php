<?php
namespace Mash2\Cobby\Model\Import\Product;

class GroupedManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductGroupedManagementInterface
{
    /**
     * @var string product link table name
     */
    private $productLinkTable;

    /**
     * @var string product relation table name
     */
    private $productRelationTable;

    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $settings;

    /**
     * constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Mash2\Cobby\Helper\Settings $settings
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Helper\Settings $settings,
        \Mash2\Cobby\Model\Product $product
    ) {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->settings = $settings;
        $this->productLinkTable = $resourceModel->getTableName('catalog_product_link');
        $this->productRelationTable = $resourceModel->getTableName('catalog_product_relation');
    }

    public function import($rows)
    {
        $groupedLinkId = \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED;

        // pre-load attributes parameters
        $select = $this->connection->select()
            ->from($this->resourceModel->getTableName('catalog_product_link_attribute'), array(
                'id'   => 'product_link_attribute_id',
                'code' => 'product_link_attribute_code',
                'type' => 'data_type'
            ))->where('link_type_id = ?', $groupedLinkId);
        foreach ($this->connection->fetchAll($select) as $row) {
            $attributes[$row['code']] = array(
                'id' => $row['id'],
                'table' => $this->resourceModel->getTableName('catalog_product_link_attribute_' . $row['type'])
            );
        }

        $linksData     = array(
            'product_ids'      => array(),
            'links'            => array(),
            'attr_product_ids' => array(),
            'position'         => array(),
            'qty'              => array(),
            'relation'         => array()
        );
        $result = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        $this->eventManager->dispatch('cobby_import_product_grouped_import_before', array(
            'products' => $productIds ));

        foreach ($rows as $productId => $rowData) {
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $changedProductIds[] = $productId;
            $associatedIds = $rowData['_associated_ids'];

            $productData = array();

            $linksData['product_ids'][$productId] = true;

            foreach ($associatedIds as $associatedId => $associatedData) {
                $linksData['links'][$productId][$associatedId] = $groupedLinkId;
                $linksData['relation'][] = array('parent_id' => $productId, 'child_id' => $associatedId);
                $qty = empty($associatedData['qty']) ? 0 : $associatedData['qty'];
                $pos = empty($associatedData['pos']) ? 0 : $associatedData['pos'];
                $productData[$associatedId] = array('qty' => $qty, 'pos' => $pos);
                if ($qty || $pos) {
                    $linksData['attr_product_ids'][$productId] = true;
                    if ($pos) {
                        $linksData['position']["{$productId} {$associatedId}"] = array(
                            'product_link_attribute_id' => $attributes['position']['id'],
                            'value' => $pos
                        );
                    }
                    if ($qty) {
                        $linksData['qty']["{$productId} {$associatedId}"] = array(
                            'product_link_attribute_id' => $attributes['qty']['id'],
                            'value' => $qty
                        );
                    }
                }
            }

            $result[] = array('product_id' => $productId,'_associated_ids' =>  $productData);
        }

        // save links and relations
        if ($linksData['product_ids']) {
            $this->connection->delete(
                $this->productLinkTable,
                $this->connection->quoteInto(
                    'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                    array_keys($linksData['product_ids'])
                )
            );
        }

        if ($linksData['links']) {
            $mainData = array();

            foreach ($linksData['links'] as $productId => $linkedData) {
                foreach ($linkedData as $linkedId => $linkType) {
                    $mainData[] = array(
                        'product_id'        => $productId,
                        'linked_product_id' => $linkedId,
                        'link_type_id'      => $linkType
                    );
                }
            }
            $this->connection->insertOnDuplicate($this->productLinkTable, $mainData);
            $this->connection->insertOnDuplicate($this->productRelationTable, $linksData['relation']);
        }

        // save positions and default quantity
        if ($linksData['attr_product_ids']) {
            $savedData = $this->connection->fetchPairs(
                $this->connection->select()
                ->from($this->productLinkTable, array(
                    new \Zend_Db_Expr('CONCAT_WS(" ", product_id, linked_product_id)'), 'link_id'
                ))
                ->where(
                    'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                    array_keys($linksData['attr_product_ids'])
                )
            );
            foreach ($savedData as $pseudoKey => $linkId) {
                if (isset($linksData['position'][$pseudoKey])) {
                    $linksData['position'][$pseudoKey]['link_id'] = $linkId;
                }
                if (isset($linksData['qty'][$pseudoKey])) {
                    $linksData['qty'][$pseudoKey]['link_id'] = $linkId;
                }
            }
            if ($linksData['position']) {
                $this->connection->insertOnDuplicate($attributes['position']['table'], $linksData['position']);
            }
            if ($linksData['qty']) {
                $this->connection->insertOnDuplicate($attributes['qty']['table'], $linksData['qty']);
            }
        }

        $this->touchProducts($changedProductIds);

        $this->eventManager->dispatch('cobby_import_product_grouped_import_after', array(
            'products' => $changedProductIds ));

        return $result;
    }
}
