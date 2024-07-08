<?php

namespace PagoEpayco\Payco\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use PagoEpayco\Payco\Gateway\Http\Client\ClientMock;

class CustomConfigProvider implements ConfigProviderInterface {

	const CODE = 'epayco';

	/**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ]
            ]
        ];
    }
}
