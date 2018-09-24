/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
	    
        'Magento_Checkout/js/view/payment/default',
	    'mage/url',
	    'Magento_Checkout/js/model/quote',
	    'Magento_Checkout/js/checkout-data',
	    'https://checkout.epayco.co/checkout.js'
	   
	   
	    
    ],
    function (Component,url,quote,checkoutData) {
        'use strict';

        return Component.extend({
            defaults: {
            	self:this,
                template: 'PagoEpayco_Payco/payment/epayco'
            },
	
	        redirectAfterPlaceOrder: false,
	
	       /** next:function(){
		        this.placeOrder();
		        //console.log(stepNavigator);
		        //this.navigateToNextStep();
		        
	        },
	
	        navigateToNextStep: function () {
		        stepNavigator.next();
	        },**/
	        renderCheckout: function() {
		        
		        //console.log(window);
		        var ord = this.placeOrder(quote);
		        console.log(ord);
		        // var quote = quote.shippingAddress();
		        var customerData = checkoutData.getShippingAddressFromData();
		        console.log(quote);
		        if(window.checkoutConfig.payment.Epayco.payco_test == "1"){
			        window.checkoutConfig.payment.Epayco.payco_test= "true";
		        } else {
			        window.checkoutConfig.payment.Epayco.payco_test = "false";
		        }
		
		        var handler = ePayco.checkout.configure({
			        key: window.checkoutConfig.payment.Epayco.payco_public_key,
			        test: window.checkoutConfig.payment.Epayco.payco_test
		        })
		
		        var taxes = 0;
		        for (var i = window.checkoutConfig.quoteItemData.length - 1; i >= 0; i--) {
			        taxes += window.checkoutConfig.quoteItemData[i].tax_amount
		        }
		        var items = '';
		        for(var i = 0; i <  window.checkoutConfig.quoteItemData.length; i++){
			        
		        	items += ', '+window.checkoutConfig.quoteItemData[i].product.name;
		        }
		        //console.log(items);
		       // window.checkoutConfig.quoteItemData.foreach(function(item){
			   //     items += ', '+item.product.name;
		       // });
		        
		        
		        var orderFromBack = quote.getQuoteId();
		        console.log(orderFromBack);
		        var docType='';
		        var mobile = '';
		        var doc= '';
		        var country = '';
		        if(!window.checkoutConfig.isCustomerLoggedIn){
			
			        var name_billing =  customerData.firstname + ' ' + customerData.lastname;
			        var address_billing =  customerData.street[0]+ ' ' + customerData.street[1];
			        country = customerData.country_id;
		        } else {
			        var defaultConf = parseInt(window.checkoutConfig.customerData.default_billing);
			        var  name_billing = window.checkoutConfig.customerData.addresses[0].firstname + ' '+ window.checkoutConfig.customerData.addresses[0].lastname;
			        mobile = window.checkoutConfig.customerData.telephone;
			        var address_billing = window.checkoutConfig.customerData.addresses[0].inline;
			        country = window.checkoutConfig.customerData.addresses[0].country_id;
		        }
		        var lang = '';
		        var temp = window.checkoutConfig.payment.Epayco.language.split("_");
		        lang = temp[0];
		        var amount = '';
		        var totals = quote.getTotals();
		        console.log(totals._latestValue.grand_total);
		        amount = totals._latestValue.grand_total;
		        var data={
			        //Parametros compra (obligatorio)
			        name: items,
			        description: items,
			        invoice: this.getOrderId(),
			        currency: window.checkoutConfig.quoteData.store_currency_code,
			        amount: amount,
			        tax_base: window.checkoutConfig.quoteItemData[0].tax_percent,
			        tax: taxes.replace('.',','),
			        country: country,
			        lang: lang,
			
			        //Onpage='false' - Standard='true'
			        external: window.checkoutConfig.payment.Epayco.vertical_cs,
			
			
			        //Atributos opcionales
			        extra1: 'extra1',
			        extra2: 'extra2',
			        extra3: 'extra3',
			        confirmation:url.build("confirmation/epayco/index"),
			        response: url.build("confirmation/epayco/index"),
			
			        //Atributos cliente
			        name_billing: name_billing,
			        address_billing: address_billing,
			        type_doc_billing: docType,
			        mobilephone_billing: mobile,
			        number_doc_billing: doc
			
		        };
		        handler.open(data);
		        
	        },
	        getOrderId: function(){
		        return window.checkoutConfig.payment.Epayco.getOrderId;
	        },
	
	        /**placeOrder: function () {
		        var self = this;
		
		        
			        $.when(setPaymentInformationAction(this.messageContainer, {
				        'method': self.getCode()
			        })).done(function () {
				        self.placeOrderHandler().fail(function () {
					        fullScreenLoader.stopLoader();
				        });
			        }).fail(function () {
				        fullScreenLoader.stopLoader();
				        self.isPlaceOrderActionAllowed(true);
			        });
		    },
	        
            
            test : function(data){
                console.log(data);
                console.log(window.checkoutConfig.payment);
	            
            },

            /** Returns send check to info */
            getMailingAddress: function() {

                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            responseAction: function(){
            	return window.checkoutConfig.payment.Epayco.responseAction;
            },
	        
	        

           
        });
    }
);
