<?php
/**
 * Created by PhpStorm.
 * User: mash2
 * Date: 05.03.18
 * Time: 10:51
 */

namespace Mash2\Cobby\Controller\Test;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class Sayhello extends \Magento\Framework\App\Action\Action
{
    protected $_filesystem;

    public function __construct(
        Context $context,
        \Magento\Framework\Filesystem $_filesystem
    ){
        parent::__construct($context);
        $this->_filesystem = $_filesystem;
    }

    /**
     * say hello text
     */
    public function execute()
    {
//        die("Hello 😉 - Inchoo\\CustomControllers\\Controller\\Demonstration\\Sayhello - execute() method");
        if ($this->getRequest()->getParam('function') == 'getImage') {
            $this->getImage();
        }
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
            $product = Mage::getModel('catalog/product')->load($id);
            if ($product->getId()) {
                foreach ($product->getMediaGallery('images') as $image) {
                    $tokens = explode('/', $image['file']);
                    $str = trim(end($tokens));
                    if ($str == $filename){
                        $file = Mage::helper('catalog/image')->init($product, 'thumbnail', $image['file']);
                        $fileString = (string)$file;
                        $filePath = str_replace($baseMediaUrl, $baseMediaDir, $fileString);

                        return $filePath;
                    }
                }
            }
        } else if (file_exists($baseMediaDir . 'import/'. $filename)) {
            $filePath = $baseMediaDir . 'import/' . $filename;

            return $filePath;
        }

//        $placeholder = Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/thumbnail.jpg');
//        $baseSkinUrl = Mage::getBaseUrl('skin');
//        $baseSkinDir = Mage::getBaseDir() . DS . 'skin' . DS;
//        $filePath = str_replace($baseSkinUrl, $baseSkinDir, $placeholder);

//        return $filePath;
    }
}