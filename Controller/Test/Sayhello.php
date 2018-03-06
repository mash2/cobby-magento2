<?php
/**
 * Created by PhpStorm.
 * User: mash2
 * Date: 05.03.18
 * Time: 10:51
 */

namespace Mash2\Cobby\Controller\Test;

class Sayhello extends \Magento\Framework\App\Action\Action
{
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

    public function getImage() {
        $image = $this->getRequest()->getParam('image');

        $file = "/var/www/html/pub/media/import/tasche.jpg";
        $type = 'image/jpeg';

//        header('Content-Type: image/jpeg');
//        readfile("/var/www/html/pub/media/import/tasche.jpg");

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-type', $type, true);

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        $ioAdapter = new \Magento\Framework\Filesystem\Io\File();
        $ioAdapter->open(array('path' => $ioAdapter->dirname($file)));
//        $ioAdapter->streamOpen($file, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            print $buffer;
        }
        $ioAdapter->streamClose();

    }
}