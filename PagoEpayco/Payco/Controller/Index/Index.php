<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PagoEpayco\Payco\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PagoEpayco\Payco\Model\OrderEpaycoFactory;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;

class Index extends Action implements CsrfAwareActionInterface
{
    protected $resultPageFactory;
    protected $resultJsonFactory;
    protected $orderRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderRepository = $orderRepository;
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->create(\Psr\Log\LoggerInterface::class);
            $result = $this->resultJsonFactory->create();
            $orderEpayco =  $objectManager->create(\PagoEpayco\Payco\Model\OrderEpayco::class);
            $storeScope = ScopeInterface::SCOPE_STORE;
            $scopeConfig = ObjectManager::getInstance()->get(ScopeConfigInterface::class);
            $p_cust_id_cliente = $scopeConfig->getValue(
                'payment/epayco/p_cust_id_cliente',
                $storeScope
            );
            // Get request parameters
            $request = $this->getRequest();
            $orderId = $request->getParam('order_id');

            $orderEpayco->setData('order', $orderId);
            $orderEpayco->setData('retry', 5);
            $orderEpayco->setData('customer_id', $p_cust_id_cliente);
            $orderEpayco->setData('status', 'started');
            $orderEpayco->save();
            
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
            
            // Cargar la orden por quote_id y obtener el increment_id
            $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
            
            // Validar que la orden existe
            if (!$order->getId()) {
                $logger->error('ePayco: Orden no encontrada con quote_id: ' . $orderId);
                return $result->setData([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                    'order_id' => $orderId
                ]);
            }
            
            // Obtener el increment_id de la orden
            $incrementId = $order->getIncrementId();
            $logger->info('ePayco: Order ID: ' . $orderId . ', Increment ID: ' . $incrementId . ', Entity ID: ' . $order->getId());
            // Actualizar el estado y estatus de la orden
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $orderRepository->save($order);

            $data = [
                'success' => true,
                'message' => 'Custom payment controller works!',
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ];
            
            return $result->setData($data);
        }catch (\Exception $error) {
            $logger->error('ErrorepaycoController: ' . $error->getMessage());
            die($error->getMessage());
        } catch (\Error $e) {
            $logger->error('ErrorepaycoController: ' . $e->getMessage());
            die($e->getMessage());
        }
    }
}
