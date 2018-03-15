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
        \Magento\Catalog\Helper\Image $imageHelper
    ){
        $this->_filesystem = $filesystem;
        $this->_productFactory = $productFactory;
        $this->_imageHelper = $imageHelper;
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
//        readfile("/var/www/html/pub/media/import/tasche.jpg");

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-type', $type, true);

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        $ioAdapter = new \Magento\Framework\Filesystem\Io\File();
        $ioAdapter->open(array('path' => $ioAdapter->dirname($filePath)));
//        $ioAdapter->streamOpen($file, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            print $buffer;
        }
        $ioAdapter->streamClose();
    }

    private function getImagePath($id ,$filename)
    {
        $dirConfig = DirectoryList::getDefaultConfig();
        $dirAddon = $dirConfig[DirectoryList::MEDIA][DirectoryList::PATH];
        $baseMediaUrl = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $baseMediaDir = DirectoryList::MEDIA;



        if ((int)$id){
            $product = $this->_productFactory->create();
            $product->load($id);
            if ($product->getId()) {
                foreach ($product->getMediaGallery('images') as $image) {
                    $tokens = explode('/', $image['file']);
                    $str = trim(end($tokens));
                    if ($str == $filename){
                        $file = $this->_imageHelper->init($product, 'thumbnail', $image['file']);
                        $fileString = (string)$file;
                        $filePath = str_replace($baseMediaUrl, $baseMediaDir, $fileString);

                        return $filePath;
                    }
                }
            }
        } else if (file_exists($baseMediaDir . 'import/'. $filename)) {
            $filePath = $baseMediaUrl . 'import/' . $filename;

            return $filePath;
        }

        // for debug reasons anchor: palceholder
        $filePath = "http://magento.local:8080/pub/media/catalog/product/m/b/mb02-gray-0.jpg";
//        $placeholder = Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/thumbnail.jpg');
//        $baseSkinUrl = Mage::getBaseUrl('skin');
//        $baseSkinDir = Mage::getBaseDir() . DS . 'skin' . DS;
//        $filePath = str_replace($baseSkinUrl, $baseSkinDir, $placeholder);

        return $filePath;
    }
}