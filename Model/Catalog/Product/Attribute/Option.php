<?php
namespace Mash2\Cobby\Model\Catalog\Product\Attribute;

/**
 * Class Option
 * @package Mash2\Cobby\Model\Catalog\Product\Attribute
 */
class Option implements \Mash2\Cobby\Api\CatalogProductAttributeOptionInterface
{
    const ERROR_OPTION_ALREADY_EXISTS = 'option_already_exists';

    /**
     * Json Helper
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var\Magento\Catalog\Model\ResourceModel\Product
     */
    protected $productResource;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
     */
    protected $optionCollectionFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\OptionFactory
     */
    private $attrOptionFactory;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\OptionLabel
     */
    private $attrOptionLabelFactory;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Eav\Api\AttributeOptionManagementInterface
     */
    protected $eavOptionManagement;

    /**
     * @var \Magento\Swatches\Helper\Data
     */
    protected $swatchHelper;

    protected $adapterFactory;
    protected $config;
    protected $filesystem;
    protected $uploaderFactory;

    protected $defaultValue;
    protected $optionsArray;
    protected $swatchesArray;

    /**
     * Import constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory
     * @param \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Api\AttributeOptionManagementInterface $eavOptionManagement
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\Registry $registry,
        \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory,
        \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\Eav\Api\AttributeOptionManagementInterface $eavOptionManagement,
        \Magento\Framework\Image\AdapterFactory $adapterFactory,
        \Magento\Catalog\Model\Product\Media\Config $config,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $mediaUploaderFactory,
        \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->productResource = $productResource;
        $this->registry = $registry;
        $this->attrOptionFactory = $attrOptionFactory;
        $this->attrOptionLabelFactory = $attrOptionLabelFactory;
        $this->storeManager = $storeManager;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->eventManager = $eventManager;
        $this->eavOptionManagement = $eavOptionManagement;
        $this->swatchHelper = $swatchHelper;
        $this->adapterFactory = $adapterFactory;
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
    }

    public function export($attributeId){
        $attribute = $this->productResource->getAttribute($attributeId);

        if (!$attribute) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Requested attribute doesn\'t exist'));
        }

        $options = $this->getOptions($attributeId);

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setData($options);

        $this->eventManager->dispatch('cobby_catalog_product_attribute_option_export_after',
            array('attribute' => $attribute, 'transport' => $transportObject));

        return $transportObject->getData();
    }

    public function getOptions($attributeId, $filter = null)
    {
        $result = array();

        foreach ($this->storeManager->getStores(true) as $store) {
            $storeId = $store->getStoreId();

            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            $attribute = $this->productResource
                ->getAttribute($attributeId)
                ->setStoreId($storeId);

            //some magento extension use boolean as input type, but forgot to set source model too boolean
            //magento renders the fields properly because of dropdown fields
            //we are setting the source_model to boolean to get the localized values for yes/no fields
            if ( $attribute->getFrontendInput() === 'boolean'  &&
                ($attribute->getData('source_model') == '' || $attribute->getData('source_model') == 'eav/entity_attribute_source_table') ) {
                $attribute->setSourceModel('Magento\Eav\Model\Entity\Attribute\Source\Boolean');
            }

            if ($attribute->usesSource()) {
                if( $attribute->getSource() instanceof \Magento\Eav\Model\Entity\Attribute\Source\Table  ) {
                    if ($filter != null){
                        $options = $this->optionCollectionFactory->create()
                            ->addFieldToFilter('tdv.value', $filter)
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);

                        foreach($options as $option) {
                            if ($option->getValue() == $filter) {
                                $result[] = array(
                                    'store_id' => $storeId,
                                    'value' => $option->getId(),
                                    'label' => $option->getValue(),
                                    'use_default' => $storeId > \Magento\Store\Model\Store::DEFAULT_STORE_ID && $option->getStoreDefaultValue() == null
                                );
                            }
                        }
                    }
                    else{
                        $options = $this->optionCollectionFactory->create()
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);

                        foreach($options as $option) {
                            $result[] = array(
                                'store_id' => $storeId,
                                'value' => $option->getId(),
                                'label' => $option->getValue(),
                                'use_default' => $storeId > \Magento\Store\Model\Store::DEFAULT_STORE_ID && $option->getStoreDefaultValue() == null
                            );
                        }
                    }
                } else {
                    foreach ($attribute->getSource()->getAllOptions(false, true) as $optionValue) {
                        $result[] = array(
                            'store_id' => $storeId,
                            'value' => $optionValue['value'],
                            'label' => $optionValue['label'],
                            'use_default' => false
                        );
                    }
                }
            }
        }

        return $result;
    }

    public function import($jsonData)
    {
        $this->registry->register('is_cobby_import', 1);

        $result = array();
        $rows = $this->jsonHelper->jsonDecode($jsonData);

        foreach ($rows as $row) {
            $attributeId = $row['attribute_id'];
            $attribute = $this->productResource->getAttribute($attributeId);

            if (!$attribute) {
                $result[] = array('attribute_id' => $attributeId,
                    'options' => null,
                    'error_code' => \Mash2\Cobby\Model\Catalog\Product\Attribute::ERROR_ATTRIBUTE_NOT_EXISTS);
            }
            else {
                foreach ($row['options'] as $requestedOption) {
                    $label = $requestedOption['labels']['0']['value'];
                    $options = $this->getOptions($attributeId, $label);

                    if (empty($options) || (int)$requestedOption['option_id']) {
                        if($requestedOption['swatch'] !== '') {
                            $this->_prepareSwatch($requestedOption['option_id'], $requestedOption['swatch'], $label, $attribute);
                        }
                        $this->_saveAttributeOptions($attribute, array($requestedOption));
                        $options = $this->getOptions($attributeId, $label);
                        if ($this->swatchHelper->isTextSwatch($attribute)) {
                            $this->saveSwatchParams($attributeId, $options);
                        }
                        $result[] = ['attribute_id' => $attributeId, 'options' => $options];
                    } else {
                        $result[] = ['attribute_id' => $attributeId, 'options' => $options,
                            'error_code' => self::ERROR_OPTION_ALREADY_EXISTS];
                    }
                }
            }
        }

        return $result;
    }

    protected function _prepareSwatch($optionId, $swatchValue, $label, $attribute) {
        $color = strpos($swatchValue, '#') === 0 ? true : false;

        if (!$color) {
            $fileName = $this->generateRandomString();
            $this->_copyExternalImageFile($swatchValue, $fileName);
            $this->_upload();
        }

        $defaultValue = array(0 => $optionId);
        $swatchesArray = array(
            'value' => array(
                $optionId => $swatchValue
            )
        );

        $order = array(
        );

        $value = array(
            $optionId => array(
                0 => $label,
                1 => ''
            )
        );

        $delete = array(
            $optionId => ''
        );

        $optionsArray = [
            'order' => $order,
            'value' => $value,
            'delete' => $delete
        ];

        $attribute->setData('defaultvisual', $defaultValue);
        $attribute->setData('optionvisual', $optionsArray);
        $attribute->setData('swatchvisual', $swatchesArray);
    }

    protected function _copyExternalImageFile($url, $fileName)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {
                //$dir = $this->mediaDirectory->getAbsolutePath('tmp');
                $dir = '/tmp';

                // @codingStandardsIgnoreStart
                $fileHandle = fopen($dir . '/' . basename($fileName), 'w+');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fileHandle);
                #curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                // use basic auth ony current installation
                //TODO: M2 .htaccess is missing
                //if( $this->_htUser != '' && $this->_htPassword != '' && parse_url($url, PHP_URL_HOST) == parse_url($this->_mediaUrl, PHP_URL_HOST))
//                {
//                    curl_setopt($ch, CURLOPT_USERPWD, "$this->_htUser:$this->_htPassword");
//                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//                }
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fileHandle);
                // @codingStandardsIgnoreEnd

                if ($http_code !== 200) {
                    throw new \Exception();
                }
                //$this->imageAdapter->validateUploadFile($dir . '/' . $fileName);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    protected function _upload()
    {
        try {
            //$uploader = $this->uploaderFactory->create(['fileId' => 'datafile']);
            $uploader = $this->uploaderFactory->create();
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            /** @var \Magento\Framework\Image\Adapter\AdapterInterface $imageAdapter */
            $imageAdapter = $this->adapterFactory->create();
            $uploader->addValidateCallback('catalog_product_image', $imageAdapter, 'validateUploadFile');
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            /** @var \Magento\Framework\Filesystem\Directory\Read $mediaDirectory */
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $config = $this->config;
            $result = $uploader->save($mediaDirectory->getAbsolutePath($config->getBaseTmpMediaPath()));
            unset($result['path']);

            $this->_eventManager->dispatch(
                'swatch_gallery_upload_image_after',
                ['result' => $result, 'action' => $this]
            );

            unset($result['tmp_name']);

            $result['url'] = $this->config->getTmpMediaUrl($result['file']);
            $result['file'] = $result['file'] . '.tmp';

            $newFile = $this->swatchHelper->moveImageFromTmp($result['file']);
            $this->swatchHelper->generateSwatchVariations($newFile);
            $fileData = ['swatch_path' => $this->swatchHelper->getSwatchMediaUrl(), 'file_path' => $newFile];
            $this->getResponse()->setBody(json_encode($fileData));
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            $this->getResponse()->setBody(json_encode($result));
        }
    }

    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Save swatch text
     *
     * @param int $attributeId
     * @param array $options
     * @return void
     */
    protected function saveSwatchParams($attributeId, $options)
    {
        foreach ($options as $option) {
            if($option['store_id'] == 0) {
                $attribute = $this->productResource->getAttribute($attributeId);
                $attribute->setData('swatchtext', array('value'=> array( $option['value'] => array($option['label']))));
                $attribute->save();
            }
        }
    }

    private function _saveAttributeOptions($attribute, $data)
    {
        /* @var $option \Magento\Eav\Api\Data\AttributeOptionInterface */
        $option = $this->attrOptionFactory->create();
        $attributeCode = $attribute->getAttributeCode();

        foreach ($data as $row) {
            $optionLabels = array();
            $adminLabel = $row['labels']['0']['value'];

            foreach ($row['labels'] as $label) {
                /* @var $optionLabel \Magento\Eav\Api\Data\AttributeOptionLabelInterface */
                $optionLabel = $this->attrOptionLabelFactory->create();

                $optionLabel->setStoreId($label['store_id']);
                $optionLabel->setLabel($label['value']);
                $optionLabels[] = $optionLabel;
            }

            if(isset($row['option_id']) && (int)$row['option_id']) {
                $option->setValue($row['option_id']);
            } else {
                $option->setValue('');
            }

            $option->setStoreLabels($optionLabels);
            $option->setLabel($adminLabel);
            $this->eavOptionManagement->add(
                \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode,
                $option
            );
        }
    }
}
