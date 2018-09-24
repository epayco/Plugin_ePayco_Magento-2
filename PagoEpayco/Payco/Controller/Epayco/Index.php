<?php
	/**
	 * Module for payment provide by ePayco
	 * Copyright (C) 2017
	 *
	 * This file is part of EPayco/EPaycoPayment.
	 *
	 * EPayco/EPaycoPayment is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with this program. If not, see <http://www.gnu.org/licenses/>.
	 */
	
	namespace PagoEpayco\Payco\Controller\Epayco;
	
	
	class Index extends \Magento\Framework\App\Action\Action
	{
		
		protected $resultPageFactory;
		protected $resultJsonFactory;
		protected $checkoutSession;
		protected $orderFactory;
		protected $cartManagement;
		protected $quote;
		protected $resultRedirect;
		protected $_curl;
		
		/**
		 * Constructor
		 *
		 * @param \Magento\Framework\App\Action\Context  $context
		 * @param \Magento\Framework\Json\Helper\Data $jsonHelper
		 */
		public function __construct(
			\Magento\Framework\App\Action\Context $context,
			\Magento\Framework\View\Result\PageFactory $resultPageFactory,
			\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
			\Magento\Checkout\Model\Session $checkoutSession,
			\Magento\Sales\Model\OrderFactory $orderFactory,
			\Magento\Quote\Api\CartManagementInterface $cartManagement,
			\Magento\Quote\Model\Quote $quote,
			\Magento\Framework\HTTP\Client\Curl $curl,
			\Magento\Framework\App\Helper\Context $contextApp,
			\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
			\PagoEpayco\Payco\Controller\PaymentController $payment_controller,
			\Magento\Sales\Api\OrderRepositoryInterface $orderRepository
			
		
			
			
		) {
			$this->resultPageFactory = $resultPageFactory;
			$this->resultJsonFactory = $resultJsonFactory;
			$this->checkoutSession = $checkoutSession;
			$this->orderFactory = $orderFactory;
			$this->cartManagement = $cartManagement;
			$this->quote = $quote;
			$this->_curl = $curl;
			$this->contextApp = $contextApp;
			$this->scopeConfig = $scopeConfig;
			$this->paymentController = $payment_controller;
			$this->orderRepository = $orderRepository;
			parent::__construct($context);
		}
		
		/**
		 * Execute view action
		 *
		 * @return \Magento\Framework\Controller\ResultInterface
		 */
		public function execute()
		{
		
//			return $this->resultPageFactory->create();
			//$resultPage = $this->resultPageFactory->create();
			//$resultPage->getConfig()->getTitle()->prepend(__('Custom Front View'));
			//return $resultPage;
			
			$result = $this->resultJsonFactory->create();
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
			$urlRedirect = $this->scopeConfig->getValue('payment/epayco/payco_callback',$storeScope);
			
			
				//$this->quote = $this->checkoutSession->getQuote();
				//$this->quote->getPayment()->setMethod('ePayco');
				//$this->disabledQuoteAddressValidation($quote);
				//$this->cartManagement->placeOrder($this->quote->getId());
				if(isset($_GET['ref_payco'])){
					$this->_curl->get("https://secure.epayco.co/validation/v1/reference/" . $_GET['ref_payco']);
					$response = $this->_curl->getBody();
					$dataTransaction = json_decode($response);
					
					if(isset($dataTransaction) && $dataTransaction->success){
						
						$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
						//$realOrderId= str_pad($dataTransaction->data->x_id_invoice, 9, "0", STR_PAD_LEFT);
						$orderId = (Integer)$dataTransaction->data->x_id_invoice;
						$order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);
						/*$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
						$orderDatamodel = $objectManager->get('Magento\Sales\Model\Order')->getCollection()->getLastItem();
						$orderId   =   $orderDatamodel->getId();
						$order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);*/
						$code = $dataTransaction->data->x_cod_response;
						if($code == 1){
							$order->setState("complete")->setStatus("complete");
						} else if($code == 3){
							$order->setState("pending")->setStatus("pending");
						} else if($code == 2 || $code == 6 || $code == 9 || $code == 10){
							$order->setState("canceled")->setStatus("canceled");
						} else if($code == 4){
							$order->setState("pending")->setStatus("pending");
						} else if($code == 12)  {
							$order->setState("fraud")->setStatus("fraud");
						}
						//$order->addStatusToHistory($order->getStatus(), 'ref_payco: '.$_GET['ref_payco']);
						$order->save();
						//$order->getPayment()->setAdditionalInformation("ref_payco",$_GET['ref_payco']);
						//$this->orderRepository->save($order);
						//$resultRedirect = $this->resultPageFactory->create(ResultFactory::TYPE_REDIRECT);
						//$resultRedirect->setUrl($urlRedirect);
						if($urlRedirect != ''){
							return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
						} else {
							return $this->resultRedirectFactory->create()->setUrl('/');
						}
						
						
					}
					
					//return $this->resultPageFactory->create()->addHandle('confirmation_epayco_index');
					
					
				} else {
					
					if(isset($_REQUEST['x_response'])){
						$p_cust_id_cliente = $this->scopeConfig->getValue('payment/epayco/payco_merchant',$storeScope);
						$p_key             = $this->scopeConfig->getValue('payment/epayco/payco_key',$storeScope);;
						$x_response     = $_REQUEST['x_response'];
						$x_motivo       = $_REQUEST['x_response_reason_text'];
						$x_id_invoice   = $_REQUEST['x_id_invoice'];
						$x_currency_code  = $_REQUEST['x_currency_code'];
						$x_amount         = $_REQUEST['x_amount'];
						$x_signature      = $_REQUEST['x_signature'];
						$x_autorizacion = $_REQUEST['x_approval_code'];
						$x_transaction_id = $_REQUEST['x_transaction_id'];
						$x_ref_payco      = $_REQUEST['x_ref_payco'];
						
						$signature =  $signature = hash('sha256', $p_cust_id_cliente . '^' . $p_key . '^' . $x_ref_payco . '^' . $x_transaction_id . '^' . $x_amount . '^' . $x_currency_code);
						if($x_signature == $signature){
							$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//							$realOrderId= str_pad($_REQUEST['x_id_invoice'], 9, "0", STR_PAD_LEFT);
							$orderId = $x_id_invoice;
							$order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);
							
							$x_response     = $_REQUEST['x_response'];
							$x_motivo       = $_REQUEST['x_response_reason_text'];
							$x_id_invoice   = $_REQUEST['x_id_invoice'];
							$x_autorizacion = $_REQUEST['x_approval_code'];
							$code = $x_response;
							if($code == 1){
								$order->setState("complete")->setStatus("complete");
							} else if($code == 3){
								$order->setState("pending")->setStatus("pending");
							} else if($code == 2 || $code == 6 || $code == 9 || $code == 10){
								$order->setState("canceled")->setStatus("canceled");
							} else if($code == 4){
								$order->setState("pending")->setStatus("pending");
							} else if($code == 12)  {
								$order->setState("fraud")->setStatus("fraud");
							}
							$order->save();
							//$order->getPayment()->setAdditionalInformation("ref_payco",$_GET['ref_payco']);
							//$order->save();
							return true;
						}
						else{
							return true;
						}
						
						
					} else{
						return $result->setData('No se creo la orden');
					}
					
				}
				
		}
		
		
		
		public function getRealOrderId()
		{
			$lastorderId = $this->checkoutSession->getLastOrderId();
			return $lastorderId;
		}
		
		public function getOrder()
		{
			if ($this->checkoutSession->getLastRealOrderId()) {
				$order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
				return $order;
			}
			return false;
		}
		
		public function getShippingInfo()
		{
			$order = $this->getOrder();
			if($order) {
				$address = $order->getShippingAddress();
				
				return $address;
			}
			return false;
			
		}
		
		
	}
