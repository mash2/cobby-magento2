<?php
/**
 * Created by PhpStorm.
 * User: mash2
 * Date: 05.03.18
 * Time: 10:51
 */

namespace Mash2\Cobby\Controller\Cobby;

use Magento\Framework\App\Filesystem\DirectoryList;
//use Magento\Framework\Filesystem;
//use \Magento\Catalog\Model\ProductFactory;

class GetImage extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\ActionInterface
{
    protected $_filesystem;
    protected $_productFactory;
    protected $_imageHelper;
    protected $_fileHelper;
    protected $_storeManager;
    protected $_productHelper;
    protected $_productRepository;

    /**
     * GetImage constructor.
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Framework\Filesystem\Io\File $fileHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductRepository $productRepository
    ){
        $this->_filesystem = $filesystem;
        $this->_productFactory = $productFactory;
        $this->_imageHelper = $imageHelper;
        $this->_fileHelper = $fileHelper;
        $this->_storeManager = $storeManager;
        $this->_productHelper = $productHelper;
        $this->_productRepository = $productRepository;
        parent::__construct($context);
    }

    /**
     * say hello text
     */
    public function execute()
    {
//        die("Hello 😉 - Inchoo\\CustomControllers\\Controller\\Demonstration\\GetImage - execute() method");
        $this->getImage();
    }

    private function getImage()
    {

        $id = $this->getRequest()->getParam('id');
        $filename = $this->getRequest()->getParam('filename');

        $filePath = $this->getImagePath($id, $filename);

        $type = 'image/jpeg';

//        header('Content-Type: image/jpeg');
//        readfile($filePath);  //only for debug
//
//        return;

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-type', $type, true);

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

//        $ioAdapter = $this->_fileHelper;//new \Magento\Framework\Filesystem\Io\File();
//        $ioAdapter->open(array('path' => $ioAdapter->dirname($filePath)));
        print $this->_fileHelper->read($filePath);
//        while ($buffer = $ioAdapter->read($filePath)) {
//            print $buffer;
//        }
//        $ioAdapter->streamClose();
    }

    private function getImagePath($id ,$filename)
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $workDir = $this->_filesystem->getDirectoryRead('base')->getAbsolutePath();
        $mediaName = DirectoryList::MEDIA;
        $baseMediaDir = $this->_filesystem->getDirectoryRead($mediaName)->getAbsolutePath();

        if ((int)$id){
//            $product = $this->_productFactory->create();
//            $product->load($id);
            $product = $this->_productRepository->getById($id);
            if ($product->getId()) {
                foreach ($product->getMediaGalleryImages()->getItems() as $image) {
                    $tokens = explode('/', $image->getFile());
                    $str = trim(end($tokens));
                    if ($str == $filename){
//                        $file = $this->_imageHelper->init($product, $image['file'], array('thumbnail'));
                        $fileUrl = $this->_imageHelper->init($product, $image->getId())->getUrl();
//                        $url = $this->_productHelper->getThumbnailUrl($product);
//                        $fileString = (string)$file;
                        $filePath = str_replace($baseUrl, $workDir, $fileUrl);

                        return $filePath;
                    }
                }
            }
        } else if ($this->_fileHelper->fileExists($baseMediaDir . 'import/'. $filename)) {
            $filePath = $baseMediaDir . 'import/' . $filename;

            return $filePath;
        }

        // for debug reasons anchor: palceholder
//        $filePath = "http://magento.local:8080/pub/media/catalog/product/m/b/mb02-gray-0.jpg";
        $filePathString = "/var/www/html/vendor/magento/module-catalog/view/base/web/images/product/placeholder/thumbnail.jpg";

        $placeHolderImage = $this->_imageHelper->getPlaceholder();
        $placeHolderImageUrl = $this->_imageHelper->getDefaultPlaceholderUrl($placeHolderImage);
        $this->_productHelper->getThumbnailUrl();
        $filePath = str_replace($baseUrl, $workDir, $placeHolderImageUrl);
//        $this->_imageHelper->getImage();

        return $filePathString;
    }
}