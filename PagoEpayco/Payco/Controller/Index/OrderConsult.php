<?php
/**
 * Copyright 漏 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PagoEpayco\Payco\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use PagoEpayco\Payco\Model\ResourceModel\OrderEpayco\CollectionFactory;
use Magento\Sales\Model\Order;

class OrderConsult extends Action
{
    protected $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        try{
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
            /** @var \Magento\Framework\HTTP\Client\Curl $curl */
            $curl = $objectManager->create(\Magento\Framework\HTTP\Client\Curl::class); 
            // Crear la colecci贸n
            $collection = $this->collectionFactory->create();

            // Ejemplo: SELECT * FROM epayco_custom_table WHERE status = 'pending'
            $collection->addFieldToFilter('status', 'pending');

            foreach ($collection as $item) {
                $retry = (int)$item->getData('retry');
                //$item->delete(); 
                $orderId = (int)$item->getData('order');
                $refpayco = $item->getData('ref_payco');
               // echo 'ID: ' . $item->getId() . ' - ref_payco: ' . $refpayco .'<br>'; 
                if($orderId && $refpayco){
                    //$order = $orderRepository->get($orderId);
                    $url = "https://cms.epayco.co/transaction/" .$refpayco;
                    $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
                    $curl->get($url);
                    $response = $curl->getBody();
                    $dataTransaction = json_decode($response);
                    if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                        $transactionData = $dataTransaction->data; 
                        $x_ref_payco = $transactionData->referencePayco;
                        $status = $transactionData->status;
                        $pendingOrderState = Order::STATE_PENDING_PAYMENT;
                        $orderId = (Integer)$transactionData->log->x_extra1??$orderId;
                        $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);
                        if($status == 'Aceptada' || $status == 'aceptada'){
                            if($order->getState() != "canceled"  ){
                                $order->setState(Order::STATE_PROCESSING, true);
                                $order->setStatus(Order::STATE_PROCESSING, true);
                                $orderRepository->save($order);
                                $item->delete();
                            }
                        } else if($status == 'Pendiente' || $status == 'pending'){
                            $order->setState($pendingOrderState, true);
                            $order->setStatus($pendingOrderState, true);
                            $item->setData('ref_payco', $x_ref_payco);
                            $item->setData('status', 'pending');
                            $item->save(); 
                            $orderRepository->save($order);
                        } else if($status == 'Rechazada' ||
                            $status == 'Fallida' ||
                            $status == 'caducada' ||
                            $status == 'abandonada' ||
                            $status == 'Cancelada'
                        ){
                            if($retry<=0){
                                if($order->getState() == "pending" || 
                                    $order->getState() == "pending_payment" || 
                                    $order->getState() == "new" ){
                                    $order->setState(Order::STATE_CANCELED, true);
                                    $order->setStatus(Order::STATE_CANCELED, true);
                                    $this->uploadInventory($objectManager,$orderId);
                                    $orderRepository->save($order);
                                }
                            }else{
                                $retry -= 1;
                                $item->setData('retry', $retry);
                                $item->save(); 
                            }
                        } else if($status == 12)  {
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $order->setState(Order::STATUS_FRAUD, true);
                                $order->setStatus(Order::STATUS_FRAUD, true);
                                $this->uploadInventory($objectManager,$orderId);
                                $orderRepository->save($order);
                                $item->delete();
                                echo 'ID: ' . $item->getId() . ' - ref_payco: ' . $x_ref_payco.' - order_status: ' . $order->getState() . ' - response '. $status .'<br>';
                            }
                        }
                        
                    }
                }
            }
            exit;
        }catch(\Exception $e){
           var_dump($e->getMessage());
        }
            
    }

    public function uploadInventory($objectManager, $orderId){
        try{
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "SELECT sku FROM quote_item WHERE quote_id = '$orderId'";
            $result = $connection->fetchAll($sql);
            if($result != null){
                foreach($result as $sku){
                    $sku  = $sku["sku"];
                    $sql_ = "SELECT MAX(reservation_id),sku,quantity FROM inventory_reservation WHERE sku = '$sku' ORDER BY reservation_id ASC";
                    $query = $connection->fetchAll($sql_);
                    if($query != null){
                        foreach($query as $productInventory){
                            $connection->update(
                                'inventory_reservation',
                                ['quantity' => '0.0000'],
                                ['reservation_id = ?' => $productInventory["MAX(reservation_id)"]]
                            );
                        }
                    }
                }
            }
        } catch(\Exception $e){
           // return $result->setData(['Error actualizando inventario '+ $e->getMessage()]);
        }
    }


}