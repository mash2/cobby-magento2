<?php
/**
 * Created by PhpStorm.
 * User: mash2
 * Date: 20.08.18
 * Time: 10:23
 */

namespace Mash2\Cobby\Model\Import\Product;


class CustomOptionManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductCustomOptionManagementInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Model\Product $product
    )
    {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
    }

    /**
     * @param $rows
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function import($rows)
    {
        $result = array();

        $productTable   = $this->resourceModel->getTableName('catalog_product_entity');
        $optionTable    = $this->resourceModel->getTableName('catalog_product_option');
        $priceTable     = $this->resourceModel->getTableName('catalog_product_option_price');
        $titleTable     = $this->resourceModel->getTableName('catalog_product_option_title');
        $typePriceTable = $this->resourceModel->getTableName('catalog_product_option_type_price');
        $typeTitleTable = $this->resourceModel->getTableName('catalog_product_option_type_title');
        $typeValueTable = $this->resourceModel->getTableName('catalog_product_option_type_value');

        $nextAutoOptionId   = $this->resourceHelper->getNextAutoincrement($optionTable);
        $nextAutoValueId    = $this->resourceHelper->getNextAutoincrement($typeValueTable);

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        $this->eventManager->dispatch('cobby_import_product_customoption_import_before', array( 'products' => $productIds ));

        $items = array();
        foreach($rows as $productId => $productCustomOptions) {
            if (!in_array($productId, $existingProductIds))
                continue;

            $changedProductIds[] = $productId;
            $items[$productId] = array(
                'product' =>  array(
                    'entity_id'        => $productId,
                    'has_options'      => 0,
                    'required_options' => 0,
                    'updated_at'       => (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT)
                ),
                'options' => array(),
                'titles' => array(),
                'prices' => array(),
                'values' => array(),
                'values_titles' => array(),
                'values_prices' => array()
            );

            foreach($productCustomOptions as $productCustomOption) {
                if(isset($productCustomOption['option_id'])) {
                    $nextOptionId = $productCustomOption['option_id'];
                }else {
                    $nextOptionId = $nextAutoOptionId++;
                }

                $items[$productId]['options'][] = array(
                    'option_id'      => $nextOptionId,
                    'sku'            => $productCustomOption['sku'],
                    'max_characters' => $productCustomOption['max_characters'],
                    'file_extension' => $productCustomOption['file_extension'],
                    'image_size_x'   => $productCustomOption['image_size_x'],
                    'image_size_y'   => $productCustomOption['image_size_y'],
                    'product_id'     => $productId,
                    'type'           => $productCustomOption['type'],
                    'is_require'     => $productCustomOption['is_require'],
                    'sort_order'     => $productCustomOption['sort_order'],
                );

                $items[$productId]['product']['has_options'] = 1;
                if($productCustomOption['is_require'] == 1) { //if one is required, product should be set to required
                    $items[$productId]['product']['required_options'] = 1;
                }

                foreach($productCustomOption['titles'] as $productCustomOptionTitle) {
                    $items[$productId]['titles'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionTitle['store_id'],
                        'title' => $productCustomOptionTitle['title']
                    );
                }

                foreach($productCustomOption['prices'] as $productCustomOptionPrice) {
                    $items[$productId]['prices'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionPrice['store_id'],
                        'price' => $productCustomOptionPrice['price'],
                        'price_type' => $productCustomOptionPrice['price_type']
                    );
                }

                foreach($productCustomOption['values'] as $value) {

                    if(isset($value['option_type_id'])){
                        $nextValueId = $value['option_type_id'];
                    } else {
                        $nextValueId = $nextAutoValueId++;
                    }

                    $items[$productId]['values'][] = array(
                        'option_type_id' => $nextValueId,
                        'option_id' => $nextOptionId,
                        'sku' => $value['sku'],
                        'sort_order' => $value['sort_order']
                    );

                    foreach($value['titles'] as $valueTitle) {
                        $items[$productId]['values_titles'][] = array(
                            'option_type_id' => $nextValueId,
                            'store_id' => $valueTitle['store_id'],
                            'title' => $valueTitle['title'],
                        );
                    }

                    foreach($value['prices'] as $valuePrice) {
                        $items[$productId]['values_prices'][] = array(
                            'option_type_id' => $nextValueId,
                            'store_id' => $valuePrice['store_id'],
                            'price' => $valuePrice['price'],
                            'price_type' => $valuePrice['price_type'],
                        );
                    }
                }
            }

            $result[] = $productId;
        }

        foreach($items as $productId => $item) {
            $this->connection->delete($optionTable, $this->connection->quoteInto('product_id = ?', $productId));
            $this->connection->insertOnDuplicate($productTable, $item['product'], array('has_options', 'required_options', 'updated_at'));
            if($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($optionTable, $item['options']);
                $this->connection->insertOnDuplicate($titleTable, $item['titles'], array('title'));
                if($item['prices'] && count($item['prices']) > 0) {
                    $this->connection->insertOnDuplicate($priceTable, $item['prices'], array('price', 'price_type'));
                }
                if($item['values'] && count($item['values']) > 0) {
                    $this->connection->insertOnDuplicate($typeValueTable, $item['values'], array('sku', 'sort_order'));
                    $this->connection->insertOnDuplicate($typeTitleTable, $item['values_titles'], array('title'));
                    $this->connection->insertOnDuplicate($typePriceTable, $item['values_prices'], array('price', 'price_type'));
                }
            }
        }

        $this->touchProducts($changedProductIds);

        $this->eventManager->dispatch('cobby_import_product_customoption_import_after', array( 'products' => $changedProductIds ));

        return true;
    }
}