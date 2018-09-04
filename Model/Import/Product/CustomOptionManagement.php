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
    const ADD           = 'add';
    const DELETE        = 'delete';
    const NONE          = 'none';
    const UPDATE        = 'update';
    const UPDATE_TYPE   = 'update_type';

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
        $deletePriceTable = array();
        $items = array
        (
            'add' => array(),
            'delete' => array(),
            'update' => array()
        );

        $this->eventManager->dispatch('cobby_import_product_customoption_import_before', array( 'products' => $productIds ));

        foreach($rows as $productId => $productCustomOptions) {
            if (!in_array($productId, $existingProductIds))
                continue;

            $changedProductIds[] = $productId;
            $product[$productId] = array(
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

                $action = '';
                switch ($productCustomOption['action']) {
                    case self::ADD:
                        $action = self::ADD;
                        break;
                    case self::DELETE:
                        $action = self::DELETE;
                        break;
                    case self::UPDATE:
                        $action = self::UPDATE;
                        break;
                    case self::UPDATE_TYPE:
                        $deletePriceTable[] = $productCustomOption['option_id'];
                        $action = self::UPDATE;
                        break;

                }

                if (!isset($items[$action][$productId]['product'])) {
                    $items[$action][$productId] = $product[$productId];
                }

                $items[$action][$productId]['options'][] = array(
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

                $items[$action][$productId]['product']['has_options'] = 1;
                if($productCustomOption['is_require'] == 1) { //if one is required, product should be set to required
                    $items[$productId]['product']['required_options'] = 1;
                }

                foreach($productCustomOption['titles'] as $productCustomOptionTitle) {
                    $items[$action][$productId]['titles'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionTitle['store_id'],
                        'title' => $productCustomOptionTitle['title']
                    );
                }

                foreach($productCustomOption['prices'] as $productCustomOptionPrice) {
                    $items[$action][$productId]['prices'][] = array(
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

                    if ($productCustomOption['action'] == 'add' && $value['action'] == 'add') {
                        $items[$action][$productId]['values'][] = array(
                            'option_type_id' => $nextValueId,
                            'option_id' => $nextOptionId,
                            'sku' => $value['sku'],
                            'sort_order' => $value['sort_order']
                        );
                    }
                    else {
                        $items[$action][$productId]['values'][] = array(
                            'option_type_id' => $nextValueId,
                            'option_id' => $nextOptionId,
                            'action' => $value['action'],
                            'sku' => $value['sku'],
                            'sort_order' => $value['sort_order']
                        );
                    }

                    foreach($value['titles'] as $valueTitle) {
                        $items[$action][$productId]['values_titles'][] = array(
                            'option_type_id' => $nextValueId,
                            'store_id' => $valueTitle['store_id'],
                            'title' => $valueTitle['title'],
                        );
                    }

                    foreach($value['prices'] as $valuePrice) {
                        $items[$action][$productId]['values_prices'][] = array(
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

        if (count($deletePriceTable) > 0) {
            $this->deletePriceTable($deletePriceTable);
        }

        if (count($items[self::ADD]) > 0) {
            $this->addOption($items[self::ADD]);
        }
        if (count($items[self::DELETE]) > 0) {
            $this->deleteOption($items[self::DELETE]);
        }
        if (count($items[self::UPDATE]) > 0) {
            $this->updateOption($items[self::UPDATE]);
        }

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

    protected function deleteOption($options)
    {
        $items = array();
        foreach ($options as $productId => $optionsTableData) {
            foreach ($optionsTableData['options'] as $option) {
                $items[] = $option['option_id'];
            }
        }

        $this->connection->delete($this->optionTable, array($this->connection->quoteInto('option_id IN (?)', $items)));
    }

    protected function deletePriceTable($optionIds)
    {
        $this->connection->delete($this->priceTable, $this->connection->quoteInto('option_id IN (?)', $optionIds));
    }

    protected function updateOption($options)
    {
        $subOptions = array();
        $optionType = array();

        foreach ($options as $productId => $item) {
            if($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);
            }
            if ($item['titles'] && count($item['titles']) > 0) {
                $this->connection->insertOnDuplicate($this->titleTable, $item['titles']);
            }
            if($item['prices'] && count($item['prices']) > 0) {
                $this->connection->insertOnDuplicate($this->priceTable, $item['prices']);
            }

            foreach ($item['values'] as $value) {
                $value['tableName'] = $this->typeValueTable;
                $subOptions[$value['option_type_id']][] = $value;
            }
            foreach ($item['values_titles'] as $value) {
                $value['tableName'] = $this->typeTitleTable;
                $subOptions[$value['option_type_id']][] = $value;
            }
            foreach ($item['values_prices'] as $value) {
                $value['tableName'] = $this->typePriceTable;
                $subOptions[$value['option_type_id']][] = $value;
            }

            foreach ($subOptions as $subOptionId => $options) {
                $action = null;
                foreach ($options as $option) {
                    if (isset($option['action'])) {
                        $action = $option['action'];
                        unset($option['action']);
                    }
                    $optionType[$action][$productId][$subOptionId][] = $option;
                }
            }
        }

        if (count($optionType[self::ADD]) > 0) {
            foreach ($optionType[self::ADD] as $option) {
                $this->addSubOption($option);
            }
        }
        if (count($optionType[self::DELETE]) > 0) {
            foreach ($optionType[self::DELETE] as $option) {
                $this->deleteSubOption($option);
            }
        }
        if (count($optionType[self::UPDATE]) > 0) {
            foreach ($optionType[self::UPDATE] as $option) {
                $this->updateSubOption($option);
            }
        }
    }

    protected function addSubOption($options)
    {
        $add = array();
        foreach ($options as $subOptions) {
            foreach ($subOptions as $subOption) {
                $tableName = $subOption['tableName'];
                unset($subOption['tableName']);
                $add[$tableName][] = $subOption;
            }
        }
        foreach ($add as $tableName => $value) {
            $values = null;
            switch ($tableName) {
                case $this->typeValueTable:
                    $values = array('sku', 'sort_order');
                    break;
                case $this->typeTitleTable:
                    $values = array('title');
                    break;
                case $this->typePriceTable:
                    $values = array('price', 'price_type');
                    break;
            }
            $this->connection->insertOnDuplicate($tableName, $value, $values);
        }

    }

    protected function deleteSubOption($options)
    {
        $optionTypeIds = array_keys($options);
        $this->connection->delete($this->typeValueTable, $this->connection->quoteInto('option_type_id IN (?)', $optionTypeIds));
    }

    protected function updateSubOption ($options)
    {
        $add = array();
        foreach ($options as $subOptions) {
            foreach ($subOptions as $subOption) {
                $tableName = $subOption['tableName'];
                unset($subOption['tableName']);
                $add[$tableName][] = $subOption;
            }
        }

        foreach ($add as $tableName => $value) {
            $this->connection->insertOnDuplicate($tableName, $value);
        }
    }
}