<?php
namespace Mash2\Cobby\Model\Import\Product;

abstract class AbstractManagement extends \Mash2\Cobby\Model\Import\AbstractEntity
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceModel;

    /**
     * DB connection.
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;
    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\ImportExport\Model\ResourceModel\Helper
     */
    protected $resourceHelper;
    /**
     * @var \Mash2\Cobby\Model\Product
     */
    private $product;

    /**
     * ImportProductLinkManagement constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Mash2\Cobby\Model\Product $product
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Model\Product $product
    )
    {
        $this->resourceModel = $resourceModel;
        $this->connection = $resourceModel->getConnection();
        $this->productCollectionFactory = $productCollectionFactory;
        $this->eventManager = $eventManager;
        $this->resourceHelper = $resourceHelper;
        $this->product = $product;
    }

    /**
     * load existing product Ids
     *
     * @param $productIds
     * @return array
     */
    protected function loadExistingProductIds($productIds)
    {
        $collection = $this->productCollectionFactory
            ->create()
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        return $collection->getAllIds();
    }

    /**
     * set updated_at to now
     *
     * @param $productIds
     * @return $this
     */
    protected function touchProducts($productIds)
    {
        $entityRowsUp = array();

        $this->product->updateHash($productIds);

        return $this; //HACK: staging is not supported

        $updatedAt = (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
        foreach ($productIds as $productId) {
            $entityRowsUp[] = array( 'updated_at' => $updatedAt, 'entity_id' => $productId );
        }

        if (count($entityRowsUp) > 0) {
            $productTable = $this->resourceModel->getTableName('catalog_product_entity');
            $this->connection->insertOnDuplicate($productTable, $entityRowsUp, array('updated_at'));
        }

        return $this;
    }

    /**
    * @param array $array
    * @param $column
    * @return array
    */
    protected function getColumnValues(array $array, $column)
    {
        return array_map(function($element) use ($column) {
            return $element[$column];
        }, $array);
    }

    /**
     * Get product entity link field
     *
     * @return string
     */
    protected function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }
}