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
            
            // Log total de registros antes del filtro
            $this->logger->info('Total registros en OrderEpayco antes del filtro: ' . $collection->getSize());
            
            // Primero verifica sin filtro para debug
            $collectionDebug = $collectionFactory->create();
            $this->logger->info('Total registros en OrderEpayco (sin filtro): ' . $collectionDebug->getSize());
            
            // Log de algunos registros para debug
            if ($collectionDebug->getSize() > 0) {
                $debugItems = $collectionDebug->setPageSize(5)->load();
                foreach ($debugItems as $debugItem) {
                    $this->logger->info('Debug - ID: ' . $debugItem->getId() . ', Status: ' . $debugItem->getData('status') . ', Order: ' . $debugItem->getData('order'));
                }
            }

            // Aplica el filtro para registros pendientes y started
            $collection->addFieldToFilter('status', ['in' => ['pending', 'started']]);
            
            $this->logger->info('Total registros con status pending o started: ' . $collection->getSize());
            
            if ($collection->getSize() == 0) {
                $this->logger->info('No hay registros con status pending o started. Verificando todos los status...');
                
                // Verifica todos los status disponibles
                $allStatusCollection = $collectionFactory->create();
                $allStatusCollection->getSelect()->group('status');
                foreach ($allStatusCollection as $statusItem) {
                    $this->logger->info('Status encontrado: ' . $statusItem->getData('status'));
                }
            }

            foreach ($collection as $item) {
                $this->logger->info('Procesando item con ID: ' . $item->getId());
                
                $retry = (int)$item->getData('retry');
                $orderId = (int)$item->getData('order');
                $refpayco = $item->getData('ref_payco');
                
                $this->logger->info('Item datos - Retry: ' . $retry . ', OrderID: ' . $orderId . ', RefPayco: ' . $refpayco);
                if($orderId && $refpayco){
                    //$order = $orderRepository->get($orderId);
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
                    $url = "http://eks-cms-backend-platforms-service.epayco.io/transaction/" .$refpayco;
                    $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
                    $curl->get($url);
                    $response = $curl->getBody();
                    $dataTransaction = json_decode($response);
                    if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                        $transactionData = $dataTransaction->data; 
                        $x_ref_payco = $transactionData->referencePayco;
                        $status = $transactionData->status;
                        $pendingOrderState = Order::STATE_PENDING_PAYMENT;
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
            $this->logger->info('Cron actualización de órdenes Epayco ejecutado. Procesados: ' . $collection->getSize() . ' registros');
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
