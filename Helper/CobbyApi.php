<?php
namespace Mash2\Cobby\Helper;

class CobbyApi extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * cobby service url
     */
	const COBBY_API = 'https://api.cobby.mash2.com/';

    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $settings;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    private $productMetadata;

    /**
     * constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Mash2\Cobby\Helper\Settings $settings
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Framework\App\ProductMetadata $productMetadata
    ) {
        parent::__construct($context);
        $this->settings = $settings;
        $this->httpClientFactory = $httpClientFactory;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Create a cobby request with required items
     *
     * @return array
     */
    private function createCobbyRequest()
    {
        $result = array();
        $result['LicenseKey']   = $this->settings->getLicenseKey();
        $result['ShopUrl']      = $this->settings->getDefaultBaseUrl();
        $result['CobbyVersion'] = $this->settings->getCobbyVersion();

        return $result;
    }

    /**
     *
     * Performs an HTTP POST request to cobby service
     *
     * @param $method
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function restPost($method, array $data)
    {
        $client = $this->httpClientFactory->create();
        $client->setUri(self::COBBY_API .'/'. $method);
        $client->setConfig(['maxredirects' => 0, 'timeout' => 60]);
        $client->setParameterPost($data);
        $client->setMethod(\Zend_Http_Client::POST);

        //TODO: try catch
        $response = $client->request();

        //TODO: validate exception
        if ($response->getStatus() != 200 && $response->getStatus() != 201) {
            $errorRestResultAsObject = json_decode($response->getBody());
            throw new \Exception($errorRestResultAsObject->message);
        }
        $restResultAsObject = json_decode($response->getBody());

        return $restResultAsObject;
    }

    /**
     * @param $apiUser
     * @param $apiPassword
     * @return mixed
     * @throws \Exception
     */
    public function registerShop($apiUser, $apiPassword)
    {
        $request = $this->createCobbyRequest();
        $request['ApiUser'] = $apiUser;
        $request['ApiKey'] = $apiPassword;
        $request['ContactEmail'] = $this->settings->getContactEmail();
        $request['HtaccessUser'] = $this->settings->getHtaccessUser();
        $request['HtaccessPassword'] = $this->settings->getHtaccessPassword();
        $request['MagentoVersion'] = $this->productMetadata->getVersion();

        //TODO: Validate response
        $response = $this->restPost("register", $request);

        return $response;
    }

    /**
     * Notify cobby about magento changes
     *
     * @param $objectType
     * @param $method
     * @param $objectIds
     */
    public function notifyCobbyService($objectType, $method, $objectIds)
    {
        $request = $this->createCobbyRequest();
        if ($request['LicenseKey'] != '') {
            $request['ObjectType'] = $objectType;
            $request['ObjectId'] = $objectIds;
            $request['Method'] = $method;

            try {
                $this->restPost('notify', $request);
            } catch (\Exception $e) { // Zend_Http_Client_Adapter_Exception
                if ($e->getCode() != 1000) { //Timeout
//                    throw $e;
                }
            }
        }
    }
}