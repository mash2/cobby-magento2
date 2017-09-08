<?php
namespace Mash2\Cobby\Model\Import\Product;

class CategoryManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductCategoryManagementInterface
{
    /**
     * @var string category table name
     */
    private $categoryTable;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollection;
    
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
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection
     * @param \Mash2\Cobby\Helper\Settings $settings
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection,
        \Mash2\Cobby\Helper\Settings $settings,
        \Mash2\Cobby\Model\Product $product
    ) {
        $this->categoryTable = $resourceModel->getTableName('catalog_category_product');
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->categoryCollection = $categoryCollection;
        $this->settings = $settings;
    }

    private function getCategoryProductPositions($productIds)
    {
        $select = $this->connection->select()
            ->from($this->categoryTable)
            ->where('product_id IN (?)', $productIds);

        $stmt = $this->connection->query($select);
        $result = array();
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            if (!isset($result[$productId])) {
                $result[$productId] = array();
            }

            $result[$productId][$row['category_id']] = $row['position'];
        }
        return $result;
    }

    private function getCategoryIds()
    {
        return $this->categoryCollection->getAllIds();
    }

    public function import($rows)
    {
        $result = array();

        if ($rows) {
            $defaultPosition = $this->settings->getProductCategoryPosition();
            $productIds = $this->getColumnValues($rows, 'product_id');
            $productCategoryPositions = $this->getCategoryProductPositions($productIds);
            $availableCategoryIds = $this->getCategoryIds();
            $existingProductIds = $this->loadExistingProductIds($productIds);

            $categoriesIn = array();
            $changedCategoryIds = array();
            $changedProductIds = array();

            foreach ($rows as $row) {
                $productId = $row['product_id'];
                $productLog = array( 'product_id' => $productId, 'categories' => array(), 'log' => 'not found');
                if (in_array($productId, $existingProductIds)) {
                    $productLog['log'] = 'added';

                    $changedProductIds[] = $productId;
                    $categoryPositions = array();
                    if (isset($productCategoryPositions[$productId])) {
                        $categoryPositions = $productCategoryPositions[$productId];
                        $productLog['log'] = 'updated';
                    }

                    foreach ($row['categories'] as $categoryId) {
                        $categoryLog = array(
                            'category_id' => $categoryId,
                            'position' => $defaultPosition,
                            'log' => 'not found');

                        if (in_array($categoryId, $availableCategoryIds)) {
                            $categoryLog['log'] = 'added';

                            $position = $defaultPosition;
                            if (array_key_exists($categoryId, $categoryPositions)) {
                                $position = (int)$categoryPositions[$categoryId];
                                $categoryLog['log'] = 'updated';
                            }
                            $categoryLog['position'] = $position;

                            $changedCategoryIds[] = $categoryId;

                            $categoriesIn[] = array(
                                'product_id' => $productId,
                                'category_id' => $categoryId,
                                'position' => $position);
                        }
                        $productLog['categories'][] = $categoryLog;
                    }
                }
                $result[] = $productLog;
            }

            $this->eventManager->dispatch('cobby_import_product_category_import_before', array(
                'products' => $changedProductIds
            ));

            $this->connection->delete(
                $this->categoryTable,
                $this->connection->quoteInto('product_id IN (?)', $changedProductIds)
            );

            if ($categoriesIn) {
                $this->connection->insertOnDuplicate($this->categoryTable, $categoriesIn, array('position'));
            }

            $this->touchProducts($changedProductIds);

            $this->eventManager->dispatch('cobby_import_product_category_import_after', array(
                'products' => $changedProductIds,
                'categories' => $changedCategoryIds
            ));
        }

        return $result;
    }
}
