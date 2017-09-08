<?php
//namespace Mash2\Cobby\Model;
//
//use \Magento\Framework\Api\AttributeValueFactory;
//
//class Product extends \Magento\Framework\Api\AbstractExtensibleObject
//    implements \Mash2\Cobby\Api\Data\ProductInterface
//{
//
//    /**
//     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
//     */
//    protected $metadataService;
//
//    /**
//     * Initialize dependencies.
//     *
//     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
//     * @param AttributeValueFactory $attributeValueFactory
//     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataService
//     * @param array $data
//     */
//    public function __construct(
//        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
//        AttributeValueFactory $attributeValueFactory,
//        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataService,
//        $data = []
//    ) {
//        $this->metadataService = $metadataService;
//        parent::__construct($extensionFactory, $attributeValueFactory, $data);
//        $this->_init('Mash2\Cobby\Model\ResourceModel\Product');
//    }
//
//    /**
//     * Retrieve sku through type instance
//     *
//     * @return string
//     */
//    public function getSku()
//    {
//        return $this->_get(self::SKU);
//    }
//
//
//    //@codeCoverageIgnoreEnd
//    /**
//     * Set product sku
//     *
//     * @param string $sku
//     * @return $this
//     */
//    public function setSku($sku)
//    {
//        return $this->setData(self::SKU, $sku);
//    }
//
//    /**
//     * {@inheritdoc}
//     */
//    protected function getCustomAttributesCodes()
//    {
//        if ($this->customAttributesCodes === null) {
//            $this->customAttributesCodes = $this->getEavAttributesCodes($this->metadataService);
//        }
//        return $this->customAttributesCodes;
//    }
//}


namespace Mash2\Cobby\Model;

/**
 * Class Product
 * @package Mash2\Cobby\Model
 */
class Product extends \Magento\Framework\Model\AbstractModel
{

    /**
     * @var
     */
    public $mathRandom;

    /**
     * @param \Magento\Framework\Math\Random $mathRandom
     */
    public function __construct(
        \Magento\Framework\Math\Random $mathRandom
    ){
        $this->_init('Mash2\Cobby\Model\ResourceModel\Product');
        $this->mathRandom = $mathRandom;
    }

    public function resetHash($prefix)
    {
        $hash = $prefix.' '.$this->mathRandom->getRandomString(30);

        $this->_getResource()->resetHash($hash);
        return $this;
    }

    public function updateHash($ids)
    {
        $hash = $this->mathRandom->getRandomString(30);
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach(array_chunk($ids, 1024) as $chunk )  {
            $this->_getResource()->updateHash($chunk, $hash);
        }

        return $this;
    }
}