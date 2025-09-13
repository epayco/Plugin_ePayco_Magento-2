<?php
namespace PagoEpayco\Payco\Cron;

use Psr\Log\LoggerInterface;
use PagoEpayco\Payco\Model\ResourceModel\OrderEpayco\CollectionFactory;
use Magento\Sales\Model\Order;

class OrderConsult
{
    protected $logger;

    public function __construct(
        LoggerInterface $logger
    ){
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
            /** @var \Magento\Framework\HTTP\Client\Curl $curl */
            $curl = $objectManager->create(\Magento\Framework\HTTP\Client\Curl::class); 
            $collectionFactory = $objectManager->get(CollectionFactory::class);
            $collection = $collectionFactory->create();

            // Puedes aplicar filtros si quieres
            $collection->addFieldToFilter('status', 'pending');

            foreach ($collection as $item) {
                $retry = (int)$item->getData('retry');
                if($retry<=0){
                    $orderId = (int)$item->getData('order');
                    if($orderId){
                        $order = $orderRepository->get($orderId);
                        $curl->get("http://eks-cms-backend-platforms-service.epayco.io/transaction/" .$item->getData('ref_payco'));
                        $response = $curl->getBody();
                        $dataTransaction = json_decode($response);
                        if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                            $code = $dataTransaction->data->log->x_cod_response;
                            $x_ref_payco = $dataTransaction->data->log->x_ref_payco;
                            $pendingOrderState = Order::STATE_PENDING_PAYMENT;
                            if($code == 1){
                                if($order->getState() != "canceled"  ){
                                    $order->setState(Order::STATE_PROCESSING, true);
                                    $order->setStatus(Order::STATE_PROCESSING, true);
                                    $orderRepository->save($order);
                                }
                            } else if($code == 3){
                                $order->setState($pendingOrderState, true);
                                $order->setStatus($pendingOrderState, true);
                                $item->setData('ref_payco', $x_ref_payco);
                                $item->setData('status', 'pending');
                                $item->save(); 
                                $orderRepository->save($order);
                            } else if($code == 2 ||
                                $code == 4 ||
                                $code == 6 ||
                                $code == 9 ||
                                $code == 10 ||
                                $code == 11
                            ){
                                if($order->getState() == "pending" || 
                                    $order->getState() == "pending_payment" || 
                                    $order->getState() == "new" ){
                                    $order->setState(Order::STATE_CANCELED, true);
                                    $order->setStatus(Order::STATE_CANCELED, true);
                                    $this->uploadInventory($objectManager,$orderId);
                                    $orderRepository->save($order);
                                }
                            } else if($code == 12)  {
                                if($order->getState() == "pending" || 
                                    $order->getState() == "pending_payment" || 
                                    $order->getState() == "new" ){
                                    $order->setState(Order::STATUS_FRAUD, true);
                                    $order->setStatus(Order::STATUS_FRAUD, true);
                                    $this->uploadInventory($objectManager,$orderId);
                                    $orderRepository->save($order);
                                }
                            }
                            $item->delete();
                            $this->logger->info(  'ID: ' . $item->getId() . ' - ref_payco: ' . $x_ref_payco.' - order_status: ' . $order->getState() . ' - response '. $code);

                        }
                    }
                }else{
                    $retry -= 1;
                    $item->setData('retry', $retry);
                    $item->save(); 
                } 
            }
            $this->logger->info( 'corn actualizacion de ordenes epayco ejecutado');
        return $this;
        } catch (\Exception $e) {
            $this->logger->error('ErrorepaycoCron: ' . $e->getMessage());
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
