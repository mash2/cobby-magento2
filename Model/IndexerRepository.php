<?php

namespace Mash2\Cobby\Model;

class IndexerRepository implements \Mash2\Cobby\Api\IndexerRepositoryInterface
{
    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Flat\Processor
     */
    protected $productFlatProcessor;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Flat\State
     */
    protected $productFlatState;

    /**
     * @var \Magento\Catalog\Model\Indexer\Category\Flat\State
     */
    protected $categoryFlatState;

    /**
     * @var \Magento\Catalog\Model\Indexer\Category\Product
     */
    private $categoryProductProcessor;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price
     */
    private $priceProductProcessor;

    /**
     * @var \Magento\CatalogInventory\Model\Indexer\Stock
     */
    private $stockProductProcessor;

    private $indexerCollectionFactory;

    /**
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\Processor $productFlatProcessor
     * @param \Magento\Catalog\Model\Indexer\Category\Product $categoryProductProcessor
     * @param \Magento\Catalog\Model\Indexer\Product\Price $priceProductProcessor
     * @param \Magento\CatalogInventory\Model\Indexer\Stock $stockProductProcessor
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState
     */
    public function __construct(
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Catalog\Model\Indexer\Product\Flat\Processor $productFlatProcessor,
        \Magento\Catalog\Model\Indexer\Category\Product $categoryProductProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price $priceProductProcessor,
        \Magento\CatalogInventory\Model\Indexer\Stock $stockProductProcessor,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState
    )
    {
        $this->productFlatProcessor = $productFlatProcessor;
        $this->jsonHelper = $jsonHelper;
        $this->productFlatState = $productFlatState;
        $this->categoryFlatState = $categoryFlatState;
        $this->categoryProductProcessor = $categoryProductProcessor;
        $this->priceProductProcessor = $priceProductProcessor;
        $this->stockProductProcessor = $stockProductProcessor;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
    }

    public function export()
    {
        $result = array();

        $indexerCollection = $this->indexerCollectionFactory->create()->getItems();

        foreach ($indexerCollection as $indexer) {
            if(
                ($indexer->getId() == 'catalog_product_flat' &&
                    !$this->productFlatState->isFlatEnabled()) ||
                ($indexer->getId() == 'catalog_category_flat' &&
                    !$this->categoryFlatState->isFlatEnabled())
            ) {
                continue;
            }

            $title = $indexer->getTitle();
            $code = $indexer->getId();
            $status = $indexer->getStatus();
            $mode = $indexer->isScheduled() ? 'scheduled' : 'real_time';

            $result[] = array(
                'code' => $code,
                'title' => $title,
                'status' => $status,
                'mode' => $mode
            );
        }

        return $result;
    }

    public function reindex($jsonData)
    {
        $data = $this->jsonHelper->jsonDecode($jsonData);

        $indexer = $data['indexer'];
        $productIds = $data['product_ids'];

        $result = false;

        if (!count($productIds)) {
            return $result;
        }

        switch ($indexer)
        {
            case 'cataloginventory_stock':
                $this->stockProductProcessor->executeList($productIds);
                $result = true;
                break;
            case 'catalog_product_flat':
                $this->productFlatProcessor->reindexList($productIds, true);
                $result = true;
                break;
            case 'catalog_category_product':
                $this->categoryProductProcessor->executeList($productIds);
                $result = true;
                break;
            case 'catalog_product_price':
                $this->priceProductProcessor->executeList($productIds);
                $result = true;
                break;
        }

        return $result;
    }
}
