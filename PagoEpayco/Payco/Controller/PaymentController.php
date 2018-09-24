<?php
	
	namespace PagoEpayco\Payco\Controller;
	
class PaymentController extends \Magento\Framework\App\Action\Action {

public function __construct(
	\Magento\Framework\App\Action\Context $context,

	\Magento\Checkout\Model\Session $checkoutSession,
	\Magento\Sales\Model\OrderFactory $orderFactory,
	\Magento\Quote\Model\QuoteManagement $quote_management
) {
	
	$this->checkoutSession = $checkoutSession;
	$this->orderFactory = $orderFactory;
	$this->quoteManagement = $quote_management;
	
	parent::__construct($context);
}
	


	public function execute(){}

 
	
	public function responseAction($control = false)
	{
		if(!$control){
			return true;
		} else{
			$this->_redirect($this->_buildUrl('confirmation/index/index'));
			$x_respuesta=$_POST['x_response'];
			$x_cod_response=$_POST['x_cod_response'];
			$x_transaction_id=$_POST['x_transaction_id'];
			$x_approval_code=$_POST['x_approval_code'];
			$x_id_invoice=$_POST['x_id_invoice'];
			$x_ref_payco=$_POST['x_ref_payco'];
			$x_response_reason_text=$_POST['x_response_reason_text'];
			$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
			
			$order_comment = "";
			
			foreach($_POST as $key=>$value){
				$order_comment .= "<br/>$key: $value";
			}
			if($order->getStatus()=='complete'){
				echo 'Transacción ya procesada';
				exit;
			}
			
			if($x_respuesta=='Aceptada'  && $x_cod_response=='1'){
				
				$order->getPayment()->setTransactionId($x_ref_payco);
				$order->getPayment()->registerCaptureNotification($_POST['x_amount'] );
				$order->addStatusToHistory($order->getStatus(), $order_comment);
				$order->save();
				echo utf8_encode('Transacción Aceptada');
				
			} else {
				
				if($x_respuesta=='Pendiente'){
					$order->addStatusToHistory('pending', $order_comment);
					echo utf8_encode('Transacción Pendiente');
				}
				if($x_respuesta=='Rechazada' || $x_respuesta=='Fallida'){
					$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
					$order->cancel();
					$order->addStatusToHistory($order->getStatus(), $order_comment);
					$order->save();
					echo utf8_encode('Transacción Rechazada');
				}
			}
			exit;
			
		
		}
		//echo '<pre>'.$this->_request.'</pre>';
		//$this->_redirect('checkout/onepage/success');
		/*$x_respuesta=$_POST['x_response'];
        $x_cod_response=$_POST['x_cod_response'];
        $x_transaction_id=$_POST['x_transaction_id'];
        $x_approval_code=1;//$_POST['x_approval_code'];
		
		if($x_respuesta=='Aceptada' && $x_cod_response=='1' &&  $x_approval_code!='000000'){
				$this->_redirect('checkout/onepage/success');
		} else {
				$this->_redirect('checkout/onepage/failure');
		}*/

	}
	
	public function confirmAction()
	{
		$x_respuesta=$_POST['x_response'];
        $x_cod_response=$_POST['x_cod_response'];
        $x_transaction_id=$_POST['x_transaction_id'];
        $x_approval_code=$_POST['x_approval_code'];
        $x_id_invoice=$_POST['x_id_invoice'];
        $x_ref_payco=$_POST['x_ref_payco'];
		$x_response_reason_text=$_POST['x_response_reason_text'];
        $order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
       
		$order_comment = "";

		foreach($_POST as $key=>$value){
			$order_comment .= "<br/>$key: $value";
		}
		if($order->getStatus()=='complete'){
			echo 'Transacción ya procesada';
			exit;
		}

		if($x_respuesta=='Aceptada'  && $x_cod_response=='1'){
				
				$order->getPayment()->setTransactionId($x_ref_payco);	
				$order->getPayment()->registerCaptureNotification($_POST['x_amount'] );
				$order->addStatusToHistory($order->getStatus(), $order_comment);
				$order->save();
				echo utf8_encode('Transacción Aceptada');
                    
		} else {
			
                if($x_respuesta=='Pendiente'){
                	$order->addStatusToHistory('pending', $order_comment);
                	echo utf8_encode('Transacción Pendiente');
                }
               	if($x_respuesta=='Rechazada' || $x_respuesta=='Fallida'){
               		$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
                	$order->cancel();
                	$order->addStatusToHistory($order->getStatus(), $order_comment);
                	$order->save();
                	echo utf8_encode('Transacción Rechazada');
               	} 
		}
		exit;

	}
	
	public function getOrderId(){
		//$lastorderId = $this->checkoutSession->getLastOrderId();
		//$order = $this->orderFactory->create()->loadByIncrementId($lastorderId);
		//return $lastorderId+1;
		
		/**$this->checkoutSession->getQuote()->reserveOrderId();
		$reservedOrderId = $this->checkoutSession->getQuote()->getReservedOrderId();
		return $reservedOrderId+1;**/
		
//		$order = $this->checkoutSession->getLastRealOrder();
//		$orderId=$order->getIncrementId();
		
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$last_order_increment_id = $objectManager->create('\Magento\Sales\Model\Order')->getCollection()->getLastItem()->getIncrementId();
		
		return $last_order_increment_id+1;
	}
	
}