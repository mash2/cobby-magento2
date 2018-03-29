<?php

namespace Mash2\Cobby\Model;


class IndexerRepository implements \Mash2\Cobby\Api\IndexerRepositoryInterface
{
    /**
     * Indexer collection
     *
     * @var \Magento\Indexer\Model\Indexer\Collection
     */
    protected $indexerCollection;

    /**
     * @var \Magento\Indexer\Model\Indexer $indexer
     */
    protected $indexer;

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

    /**
     * @param \Magento\Indexer\Model\Indexer\Collection $indexerCollection
     * @param \Magento\Indexer\Model\Indexer $indexer
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\Processor $productFlatProcessor
     * @param \Magento\Catalog\Model\Indexer\Category\Product $categoryProductProcessor
     * @param \Magento\Catalog\Model\Indexer\Product\Price $priceProductProcessor
     * @param \Magento\CatalogInventory\Model\Indexer\Stock $stockProductProcessor
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState
     */
    public function __construct(
        \Magento\Indexer\Model\Indexer\Collection $indexerCollection,
        \Magento\Indexer\Model\Indexer $indexer,
        \Magento\Catalog\Model\Indexer\Product\Flat\Processor $productFlatProcessor,
        \Magento\Catalog\Model\Indexer\Category\Product $categoryProductProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price $priceProductProcessor,
        \Magento\CatalogInventory\Model\Indexer\Stock $stockProductProcessor,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState
    )
    {
        $this->indexerCollection = $indexerCollection;
        $this->indexer = $indexer;
        $this->productFlatProcessor = $productFlatProcessor;
        $this->jsonHelper = $jsonHelper;
        $this->productFlatState = $productFlatState;
        $this->categoryFlatState = $categoryFlatState;
        $this->categoryProductProcessor = $categoryProductProcessor;
        $this->priceProductProcessor = $priceProductProcessor;
        $this->stockProductProcessor = $stockProductProcessor;
    }

    public function export()
    {
        $result = array();

        foreach ($this->indexerCollection as $item) {
            if(
                ($item->getState()->getIndexerId() == 'catalog_product_flat' &&
                    !$this->productFlatState->isFlatEnabled()) ||
                ($item->getState()->getIndexerId() == 'catalog_category_flat' &&
                    !$this->categoryFlatState->isFlatEnabled())
            ) {
                continue;
            }

            $result[] = array(
                'code' => $item->getState()->getIndexerId(),
                'status' => $item->getView()->getState()->getStatus(),
                'mode' => $item->getView()->getState()->getMode()
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
