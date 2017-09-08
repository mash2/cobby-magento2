<?php
namespace Mash2\Cobby\Model\Import\Product;

use Magento\Framework\App\Filesystem\DirectoryList;

class ImageManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductImageManagementInterface
{
    /**
     * Media gallery attribute code.
     */
    const MEDIA_GALLERY_ATTRIBUTE_CODE = 'media_gallery';

    private $uploadMediaFiles = array();
    
    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $mediaDirectory;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel
     */
    protected $resource;

    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $settings;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Media files uploader
     *
     * @var \Magento\CatalogImportExport\Model\Import\Uploader
     */
    protected $fileUploader;
    
    /**
     * @var \Magento\CatalogImportExport\Model\Import\UploaderFactory
     */
    private $uploaderFactory;

    /**
     * @var string
     */
    protected $mediaGalleryTableName;

    /**
     * @var string
     */
    protected $mediaGalleryValueTableName;
    /**
     * @var string
     */
    protected $mediaGalleryEntityToValueTableName;

    /**
     * @var string
     */
    protected $productEntityTableName;

    /**
     * constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Mash2\Cobby\Helper\Settings $settings
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Mash2\Cobby\Model\Product $product
    ) {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->settings = $settings;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $this->storeManager = $storeManager;
        $this->resource = $resourceFactory->create();
        $this->uploaderFactory = $uploaderFactory;
        $this->initUploader();
        $this->initMediaGalleryResources();
    }

    /**
     * Init media gallery resources
     * @return void
     */
    private function initMediaGalleryResources()
    {
        $this->productEntityTableName = $this->resourceModel->getTableName('catalog_product_entity');
        $this->mediaGalleryTableName = $this->resourceModel->getTableName('catalog_product_entity_media_gallery');
        $this->mediaGalleryValueTableName = $this->resourceModel->getTableName(
            'catalog_product_entity_media_gallery_value'
        );
        $this->mediaGalleryEntityToValueTableName = $this->resourceModel->getTableName(
            'catalog_product_entity_media_gallery_value_to_entity'
        );
    }

    /**
     * Create the media/import folder
     */
    protected function createMediaImportFolder()
    {
        if (!$this->mediaDirectory->isExist('import')) {
            $this->mediaDirectory->create('import');
        }
    }

    public function import($rows)
    {
        $result = array();

//        $this->createMediaImportFolder();

//        $this->_getUploader()->setAllowRenameFiles(!$this->settings->getOverwriteImages());

        $mediaGallery = $this->processRows($rows);
        $productIds = array_keys($mediaGallery);

        $this->eventManager->dispatch('cobby_import_product_media_import_before', array(
            'products' => $productIds ));

        $this->saveMediaImages($mediaGallery);
        $this->saveMediaGallery($mediaGallery);
        $this->saveProductImageAttributes($mediaGallery);

        //enable when added errors for images
//        foreach($mediaGallery as $productId => $value ) {
//            $result[$productId] = $value['errors'];
//        }

        $this->touchProducts($productIds);

        $this->eventManager->dispatch('cobby_import_product_media_import_after', array(
            'products' => $productIds ));

        return $result;
    }

    private function saveMediaImages(array $mediaGalleryData)
    {
        $galleryAttributeId = $this->resource
            ->getAttribute(self::MEDIA_GALLERY_ATTRIBUTE_CODE)
            ->getId();


        foreach ($mediaGalleryData as $productId => $productImageData) {
            $linkId = $this->getLinkId($productId);

            $mediaValues = $this->connection->fetchPairs($this->connection->select()
                ->from(
                    ['mg' => $this->mediaGalleryTableName],
                    ['value' => 'mg.value']
                )->joinInner(
                    ['mgvte' => $this->mediaGalleryEntityToValueTableName],
                    '(mg.value_id = mgvte.value_id)',
                    ['value_id' => 'mgvte.value_id']
                )->where('mgvte.'.$this->getProductEntityLinkField().' = ?', $linkId) );

            $images = $productImageData['images'];
            $newImages = array_diff(array_values($images),array_keys($mediaValues));
            $deletedImages = array_diff(array_keys($mediaValues), array_values($images));

            foreach($deletedImages as $file)
            {
                if (array_key_exists($file, $mediaValues)) {
                    $deleteValueId =  $mediaValues[$file];
                    $this->connection->delete($this->mediaGalleryValueTableName, $this->connection->quoteInto('value_id IN (?)', $deleteValueId));
                    $this->connection->delete($this->mediaGalleryTableName, $this->connection->quoteInto('value_id IN (?)', $deleteValueId));
                    //TODO: M2 Skinny table
                }
            }


            $multiInsertData = [];
            $imageNames = [];
            foreach ($newImages as $file) {
                if (!in_array($file, $mediaValues)) {
                    $valueArr = array(
                        'attribute_id' => $galleryAttributeId,
//                        'entity_id'    => $productId,
                        'value'        => $file
                    );
                    $imageNames[] = $file;
                    $multiInsertData[] = $valueArr;
//                    $this->connection->insertOnDuplicate($this->mediaGalleryValueTableName, $valueArr, array('entity_id'));
                    //TODO: M2 Skinny table
                }
            }

            $oldMediaValues = $this->connection->fetchAssoc(
                $this->connection->select()->from($this->mediaGalleryTableName, ['value_id', 'value'])
                    ->where('value IN (?)', $imageNames)
            );
            if ($multiInsertData) {
                $this->connection->insertOnDuplicate($this->mediaGalleryTableName, $multiInsertData, []);
            }
            $newMediaSelect = $this->connection->select()->from($this->mediaGalleryTableName, ['value_id', 'value'])
                ->where('value IN (?)', $imageNames);
            if (array_keys($oldMediaValues)) {
                $newMediaSelect->where('value_id NOT IN (?)', array_keys($oldMediaValues));
            }

            $dataForSkinnyTable = [];
            $multiInsertData = [];
            $newMediaValues = $this->connection->fetchAssoc($newMediaSelect);
            foreach ($newMediaValues as $valueId => $values) {
                $valueArr = [
                    'value_id' => $valueId,
                    $this->getProductEntityLinkField() => $linkId
                ];

                $multiInsertData[] = $valueArr;
                $dataForSkinnyTable[] = [
                    'value_id' => $valueId,
                    $this->getProductEntityLinkField() => $linkId
                ];
            }

            if($multiInsertData) {
                $this->connection->insertOnDuplicate(
                    $this->mediaGalleryValueTableName,
                    $multiInsertData,
                    ['value_id', $this->getProductEntityLinkField()]
                );
                $this->connection->insertOnDuplicate(
                    $this->mediaGalleryEntityToValueTableName,
                    $dataForSkinnyTable,
                    ['value_id']
                );
            }


        }

        return $this;
    }

    /**
     * Download image file from url to tmp folder
     *
     * @param $url
     * @param $fileName
     */
    protected function _copyExternalImageFile($url, $fileName)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {
                $dir = $this->mediaDirectory->getAbsolutePath('pub/media/import');
                // @codingStandardsIgnoreStart
                $fileHandle = fopen($dir . '/' . basename($fileName), 'w+');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fileHandle);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                // use basic auth ony current installation
                //TODO: M2 .htaccess is missing
                //if( $this->_htUser != '' && $this->_htPassword != '' && parse_url($url, PHP_URL_HOST) == parse_url($this->_mediaUrl, PHP_URL_HOST))
//                {
//                    curl_setopt($ch, CURLOPT_USERPWD, "$this->_htUser:$this->_htPassword");
//                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//                }
                curl_exec($ch);
                curl_close($ch);
                fclose($fileHandle);
                // @codingStandardsIgnoreEnd
            } catch (\Exception $e) {
                throw new \Exception('Download of file ' . $url . ' failed: ' . $e->getMessage());
            }
        }
    }

    // @codingStandardsIgnoreStart
    private function processRows($rows)
    {
        $mediaGallery = array();
        $uploadedGalleryFiles = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $storeIds = array_keys($this->storeManager->getStores(true));

        foreach($rows as $productId => $mediaData)
        {
            if(!in_array($productId, $existingProductIds))
                continue;

            $result[$productId] = array();
            $mediaGallery[$productId] = array(
                'images' => array(),
                'gallery' => array(),
                'attributes' => array(),
                'errors' => array());

            $images = $mediaData['images'];
            $gallery = $mediaData['gallery'];
            $attributes = $mediaData['attributes'];
            $useDefaultStores = $mediaData['use_default_stores'];

            foreach($images as $imageData)
            {
                $image = $imageData['image'];

                if(!empty($imageData['import'])) {
                    if(empty($imageData['name'])) {
                        $imageData['name'] = $image;
                    }

                    //only copy if exists in import folder
                    if (is_file($this->fileUploader->getTmpDir() . '/' . $imageData['import'])) {
                        // @codingStandardsIgnoreStart
                        copy($this->fileUploader->getTmpDir() . '/' . $imageData['import'], $this->fileUploader->getTmpDir() . '/'. $imageData['name']);
                        // @codingStandardsIgnoreEnd
                    }

                }else  if(!empty($imageData['upload'])) {
                    if(empty($imageData['name'])) {
                        // @codingStandardsIgnoreStart
                        $imageData['name'] = basename(parse_url($imageData['upload'], PHP_URL_PATH));
                        // @codingStandardsIgnoreEnd
                    }
                    $this->_copyExternalImageFile($imageData['upload'], $imageData['name']);
                }

                if(!empty($imageData['import']) || !empty($imageData['upload'])) {
                    if (!array_key_exists($imageData['name'], $uploadedGalleryFiles)) {
                        $uploadedGalleryFiles[$imageData['name']] = $this->uploadMediaFiles($imageData['name']);
                    }
                    $imageData['file'] = $uploadedGalleryFiles[$imageData['name']];
                }

//                if($imageData['file'] == '') {
//                    $mediaGallery[$productId]['errors'][$imageData['image']] = self::ERROR_FILE_NOT_FOUND;
//                }

                if(!isset($mediaGallery[$productId]['errors'][$imageData['image']])){
                    $mediaGallery[$productId]['images'][$imageData['image']] = $imageData['file'];
                }
            }

            foreach($gallery as $storeId => $storeGalleryData)
            {
                if(!in_array($storeId, $storeIds))
                    continue;

                $mediaGallery[$productId]['gallery'][$storeId] = array();
                foreach($storeGalleryData as $galleryData){
                    if(!isset($mediaGallery[$productId]['errors'][$galleryData['image']])) {
                        $mediaGallery[$productId]['gallery'][$storeId][] = array(
                            'image' => $galleryData['image'],
                            'disabled' => $galleryData['disabled'],
                            'position' => $galleryData['position'],
                            'label' => $galleryData['label'],
                            'use_default' => in_array($storeId, $useDefaultStores)
                        );
                    }
                }
            }

            foreach($attributes as $storeId => $storeAttributeData)
            {
                if(!in_array($storeId, $storeIds))
                    continue;

                $mediaGallery[$productId]['attributes'][$storeId] = array();
                foreach($storeAttributeData as $imageAttribute => $image) {

                    if(!isset($mediaGallery[$productId]['errors'][$image])) {

                        if (in_array($storeId, $useDefaultStores))
                            $image = '';

                        $mediaGallery[$productId]['attributes'][$storeId][$imageAttribute] = $image;
                    }
                }
            }
        }

        return $mediaGallery;
    }
    // @codingStandardsIgnoreEnd

    private function uploadMediaFiles($fileName)
    {
        try {
            // cache uploaded files
            if(isset($this->uploadMediaFiles[$fileName]))
                return $this->uploadMediaFiles[$fileName];

            $res = $this->fileUploader->move($fileName);
            $this->uploadMediaFiles[$fileName] = $res['file'];
            return $res['file'];
        } catch (\Exception $e) {
            return '';
        }
    }
    
    private function initUploader()
    {
        $this->fileUploader = $this->uploaderFactory->create();
        
        $this->fileUploader->init();

        $dirConfig = DirectoryList::getDefaultConfig();
        $dirAddon = $dirConfig[DirectoryList::MEDIA][DirectoryList::PATH];

        $DS = DIRECTORY_SEPARATOR;

//        if (!empty($this->_parameters[Import::FIELD_NAME_IMG_FILE_DIR])) {
//            $tmpPath = $this->_parameters[Import::FIELD_NAME_IMG_FILE_DIR];
//        } else {
            $tmpPath = $dirAddon . $DS . $this->mediaDirectory->getRelativePath('import');
//        }

        if (!$this->fileUploader->setTmpDir($tmpPath)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('File directory \'%1\' is not readable.', $tmpPath)
            );
        }
        $destinationDir = "catalog/product";
        $destinationPath = $dirAddon . $DS . $this->mediaDirectory->getRelativePath($destinationDir);

        $this->mediaDirectory->create($destinationPath);
        if (!$this->fileUploader->setDestDir($destinationPath)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('File directory \'%1\' is not writable.', $destinationPath)
            );
        }
    }

    

    /**
     * Save product media gallery.
     *
     * @param array $mediaGalleryData
     * @return $this
     */
    private function saveMediaGallery(array $mediaGalleryData)
    {
        foreach ($mediaGalleryData as $productId => $productImageData) {
            $linkId = $this->getLinkId($productId);

            $mediaValues = $this->connection->fetchPairs($this->connection->select()
                ->from(
                    ['mg' => $this->mediaGalleryTableName],
                    ['value' => 'mg.value']
                )->joinInner(
                    ['mgvte' => $this->mediaGalleryEntityToValueTableName],
                    '(mg.value_id = mgvte.value_id)',
                    ['value_id' => 'mgvte.value_id']
                )->where('mgvte.'.$this->getProductEntityLinkField().' = ? ', $linkId) );

            $images = $productImageData['images'];
            $gallery = $productImageData['gallery'];

            foreach($gallery as $storeId => $storeGalleryData)
            {
                foreach($storeGalleryData as $galleryData)
                {
                    $image = $galleryData['image'];
                    $file = $images[$image];
                    $valueId = $mediaValues[$file];

                    $this->connection->delete($this->mediaGalleryValueTableName, array(
                        'value_id=?'      => (int) $valueId,
                        'store_id=?'       => (int) $storeId,
                    ));

                    if($galleryData['use_default'] == false)
                    {
                        $insertValueArr = array(
                            'value_id' => $valueId,
                            'store_id' => $storeId,
                            $this->getProductEntityLinkField() => $linkId,
                            'label'    => $galleryData['label'],
                            'position' => $galleryData['position'],
                            'disabled' => $galleryData['disabled']
                        );
                        $this->connection->insertOnDuplicate($this->mediaGalleryValueTableName, $insertValueArr, array('value_id'));
                    }
                }
            }
        }

        return $this;
    }

    private function saveProductImageAttributes(array $mediaGalleryData)
    {
        $attributesData = array();

        foreach ($mediaGalleryData as $productId => $productImageData)
        {
            $attributes = $productImageData['attributes'];
            $images = $productImageData['images'];

            foreach($attributes as $storeId => $storeAttributeData)
            {
                foreach ($storeAttributeData as $key => $value)
                {
                    $file = null;
                    if(!empty($value)){
                        $file = $value == 'no_selection' ? 'no_selection' : $images[$value];
                    }

                    $attribute = $this->resource->getAttribute($key);
                    $attrTable = $attribute->getBackend()->getTable();
                    $attrId = $attribute->getId();
                    $attributesData[$attrTable][$productId][$attrId][$storeId] = $file;
                }
            }
        }

        $this->saveProductAttributes($attributesData);
        return $this;
    }

    private function saveProductAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $productData) {
            $tableData = array();

            foreach ($productData as $productId => $attributes) {

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {

                        if (!is_null($storeValue)) {
                            $tableData[] = array(
                                $this->getProductEntityLinkField() => $this->getLinkId($productId),
//                                'entity_type_id' => $this->_entityTypeId,
                                'attribute_id'   => $attributeId,
                                'store_id'       => $storeId,
                                'value'          => $storeValue
                            );
                        } else {
                            $this->connection->delete($tableName, array(
                                $this->getProductEntityLinkField() => $this->getLinkId($productId),
//                                'entity_type_id=?' => (int) $this->_entityTypeId,
                                'attribute_id=?'   => (int) $attributeId,
                                'store_id=?'       => (int) $storeId,
                            ));
                        }
                    }
                }
            }

            if (count($tableData)) {
                $this->connection->insertOnDuplicate($tableName, $tableData, array('value'));
            }
        }
        return $this;
    }

    public function getLinkId($productId){
        $linkId = $this->connection->fetchOne(
            $this->connection->select()
                ->from($this->resourceModel->getTableName('catalog_product_entity'))
                ->where('entity_id = ?', $productId)
                ->columns($this->getProductEntityLinkField())
        );

        return $linkId;
    }
}
