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
    const ADD       = 'add';
    const DELETE    = 'delete';
    const NONE      = 'none';
    const UPDATE    = 'update';

    private $productTable;
    private $optionTable;
    private $priceTable;
    private $titleTable;
    private $typePriceTable;
    private $typeTitleTable;
    private $typeValueTable;
    private $nextAutoOptionId;
    private $nextAutoValueId;

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

    protected function init()
    {
        $this->productTable   = $this->resourceModel->getTableName('catalog_product_entity');
        $this->optionTable    = $this->resourceModel->getTableName('catalog_product_option');
        $this->priceTable     = $this->resourceModel->getTableName('catalog_product_option_price');
        $this->titleTable     = $this->resourceModel->getTableName('catalog_product_option_title');
        $this->typePriceTable = $this->resourceModel->getTableName('catalog_product_option_type_price');
        $this->typeTitleTable = $this->resourceModel->getTableName('catalog_product_option_type_title');
        $this->typeValueTable = $this->resourceModel->getTableName('catalog_product_option_type_value');

        $this->nextAutoOptionId   = $this->resourceHelper->getNextAutoincrement($this->optionTable);
        $this->nextAutoValueId    = $this->resourceHelper->getNextAutoincrement($this->typeValueTable);

    }

    /**
     * @param $rows
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function import($rows)
    {
        $result = array();

        $this->init();

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
                    $nextOptionId = $this->nextAutoOptionId++;
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
                        $nextValueId = $this->nextAutoValueId++;
                    }

                    $items[$productId]['values'][] = array(
                        'option_type_id' => $nextValueId,
                        'option_id' => $nextOptionId,
                        'action' => $value['action'],
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

                switch ($productCustomOption['action']) {
                    case self::ADD:
                        $this->addOption($items);
                        break;
                    case self::DELETE:
                        $this->deleteOption($productId, $productCustomOption['option_id']);
                        break;
                    case self::UPDATE:
                        $this->updateOption($items);
                        break;
                    //case self::NONE:
                    //  return true;
                }
            }

            $result[] = $productId;
        }

//        foreach($items as $productId => $item) {
//            $this->connection->delete($this->optionTable, $this->connection->quoteInto('product_id = ?', $productId));
//            $this->connection->insertOnDuplicate($this->productTable, $item['product'], array('has_options', 'required_options', 'updated_at'));
//            if($item['options'] && count($item['options']) > 0) {
//                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);
//                $this->connection->insertOnDuplicate($this->titleTable, $item['titles'], array('title'));
//                if($item['prices'] && count($item['prices']) > 0) {
//                    $this->connection->insertOnDuplicate($this->priceTable, $item['prices'], array('price', 'price_type'));
//                }
//                if($item['values'] && count($item['values']) > 0) {
//                    $this->connection->insertOnDuplicate($this->typeValueTable, $item['values'], array('sku', 'sort_order'));
//                    $this->connection->insertOnDuplicate($this->typeTitleTable, $item['values_titles'], array('title'));
//                    $this->connection->insertOnDuplicate($this->typePriceTable, $item['values_prices'], array('price', 'price_type'));
//                }
//            }
//        }

        $this->touchProducts($changedProductIds);

        $this->eventManager->dispatch('cobby_import_product_customoption_import_after', array( 'products' => $changedProductIds ));

        return true;
    }

    protected function addOption($options)
    {
        foreach($options as $productId => $item) {
            $this->connection->insertOnDuplicate($this->productTable, $item['product'], array('has_options', 'required_options', 'updated_at'));
            if($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);
                $this->connection->insertOnDuplicate($this->titleTable, $item['titles'], array('title'));
                if($item['prices'] && count($item['prices']) > 0) {
                    $this->connection->insertOnDuplicate($this->priceTable, $item['prices'], array('price', 'price_type'));
                }
                if($item['values'] && count($item['values']) > 0) {
                    $this->connection->insertOnDuplicate($this->typeValueTable, $item['values'], array('sku', 'sort_order'));
                    $this->connection->insertOnDuplicate($this->typeTitleTable, $item['values_titles'], array('title'));
                    $this->connection->insertOnDuplicate($this->typePriceTable, $item['values_prices'], array('price', 'price_type'));
                }
            }
        }
    }

    protected function deleteOption($productId, $optionId)
    {
        $this->connection->delete($this->optionTable, array(
                $this->connection->quoteInto('product_id = ?', $productId),
                $this->connection->quoteInto('option_id = ?', $optionId)
            )
        );


    }

    protected function updateOption($options)
    {
        foreach ($options as $productId => $item) {
            //unset($options['action']);
            if($item['options'] && count($item['options']) > 0) {
                foreach ($item['options'] as $option) {
                    $optionId = $option['option_id'];
                    $this->connection->update($this->optionTable, $option, array(
                        $this->connection->quoteInto('option_id = ?', $optionId)
                    ));
                }
                foreach ($item['titles'] as $title) {
                    $this->connection->update($this->titleTable, $title, array(
                        $this->connection->quoteInto('option_id = ?', $optionId)
                    ));
                }
                if($item['prices'] && count($item['prices']) > 0) {
                    $this->connection->update($this->priceTable, $item['prices'], array('price', 'price_type'));
                }

                $subOptions = array();

                foreach ($item['values'] as $value) {
                    $subOptions[$value['option_type_id']] = $value;
                }
                foreach ($item['values_titles'] as $value) {
                    if (array_key_exists($value['option_type_id'], $subOptions)) {
                        $subOptions[$value['option_type_id']]['values_titles'] = $value;
                    }
                }
                foreach ($item['values_prices'] as $value) {
                    if (array_key_exists($value['option_type_id'], $subOptions)) {
                        $subOptions[$value['option_type_id']]['values_prices'] = $value;
                    }
                }


                foreach ($subOptions as $subOption) {
                    $action = $subOption['action'];
                    $valuesTitles = $subOption['values_titles'];
                    $valuesPrices= $subOption['values_prices'];
                    unset($subOption['values_titles']);
                    unset($subOption['values_prices']);
                    unset($subOption['action']);

                    switch ($action) {
                        case self::ADD:
                            $this->connection->insertOnDuplicate($this->typeValueTable, $subOption, array('sku', 'sort_order'));
                            $this->connection->insertOnDuplicate($this->typeTitleTable, $valuesTitles, array('title'));
                            $this->connection->insertOnDuplicate($this->typePriceTable, $valuesPrices, array('price', 'price_type'));
                            break;
                        case self::DELETE:
                            $this->connection->delete($this->typeValueTable, $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id']));
                            break;
                        case self::UPDATE:
                            $this->connection->update($this->typeValueTable, $subOption, array(
                                $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                            $this->connection->update($this->typeTitleTable, $valuesTitles, array(
                                $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                            $this->connection->update($this->typePriceTable, $valuesPrices, array(
                                $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                    }

                }
            }
        }

    }
}