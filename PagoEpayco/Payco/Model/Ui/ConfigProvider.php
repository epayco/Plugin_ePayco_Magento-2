<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace PagoEpayco\Payco\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use PagoEpayco\Payco\Gateway\Http\Client\ClientMock;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'epayco';

    private const SEARCH_ENGINE_PATH = 'payment/epayco/';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        $scopeConfig = ObjectManager::getInstance()->get(ScopeConfigInterface::class);

        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ],
                    'p_cust_id_cliente' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'p_cust_id_cliente',
                        $storeScope
                    ),
                    'payco_public_key' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'payco_public_key',
                        $storeScope
                    ),
                    'payco_private_key' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'payco_private_key',
                        $storeScope
                    ),
                    'payco_test' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'payco_test',
                        $storeScope
                    ),
                    'vertical_cs' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'vertical_cs',
                        $storeScope
                    ),
                    'language_cs' => $scopeConfig->getValue(
                        self::SEARCH_ENGINE_PATH.'language_cs',
                        $storeScope
                    ),
                    'getQuoteData'=> $this->getQuoteData(),
                    'getSessionId'=> $this->getSessionId(),
                    'getQuoteId'=> $this->getQuoteId(),
                    'getLanguage'=> $this->getLanguage(),
                    'getCustomerIp'=> $this->getCustomerIp()
                ]
            ]
        ];
    }

    public function getQuoteData(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getQuote()->getData();
    }

    public function getSessionId(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getSessionId();
    }

    public function getQuoteId(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getQuoteId();
    }

    public function getLanguage(){
        $objectManager = ObjectManager::getInstance();
        $store = $objectManager->get('Magento\Framework\Locale\Resolver');
        return $store->getLocale();
    }

    public function getCustomerIp(){
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        $ipaddress = '181.134.248.46';
        return $ipaddress;
    }
}
