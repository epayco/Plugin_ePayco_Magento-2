/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        "jquery",
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/authentication-messages',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/place-order',
        'https://checkout.epayco.co/checkout.js',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/alert',
    ],
    function ($,Component,url,quote,checkoutData,messageContainer, urlBuilder, customer, placeOrderService, ePayco, fullScreenLoader, alert) {
        'use strict';
        return Component.extend({
            defaults: {
                self:this,
                template: 'PagoEpayco_Payco/payment/epayco'
            },
            redirectAfterPlaceOrder: false,
            renderCheckout: function() {
               // $('#loader-gateway').trigger('processStart');
               fullScreenLoader.startLoader();
                var button0 = document.getElementsByClassName('action primary checkout')[0];
                var button1 = document.getElementsByClassName('action primary checkout')[1];
                button0.disabled = true;
                button1.disabled = true;
                button0.style.disabled = true;
                button1.style.disabled = true;
                var countryBllg = quote.shippingAddress();
                var customerData = checkoutData.getShippingAddressFromData();
                var paymentData = {
                    method: 'epayco'
                };
                  var docType='';
                   var mobile = '';
                   var doc= '';
                   var country = '';
                   var email = '';
                   var name_billing = '';
                   var address_billing = '';
                var serviceUrl, payload;
                payload = {
                    cartId: quote.getQuoteId(),
                    billingAddress: quote.billingAddress(),
                    paymentMethod: paymentData
                };

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
                    email = customer.customerData.email;
                    //mobile = customer.customerData.addresses[0].telephone;
                    mobile = '';
                    name_billing =  customer.customerData.firstname + ' ' + customer.customerData.lastname;
                    //address_billing =  customer.customerData.addresses[0].street[0];
                    //country = customer.customerData.addresses[0].country_id;
                    } else {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                        quoteId: quote.getQuoteId()
                    });
                    payload.email = quote.guestEmail;
                     email = quote.guestEmail;
                     mobile = '';
                    name_billing = quote.billingAddress().firstname + ' '+ quote.billingAddress().lastname;
                    address_billing = quote.billingAddress().street[0];
                    country = quote.billingAddress().countryId;
                }
                // placeOrderService(serviceUrl, payload, messageContainer);
                var orderId = this.getOrderId();
                var getQuoteIncrement = this.getQuoteIncrementId();
                var totals = quote.getTotals();
                var quoteIdData = this.getQuoteIdData();
                var ip =  this.getCustomerIp();
                var _this =  this;
                var invoice;
                var settings = {
                    "url": url.build("response/payment/index"),
                    "method": "POST",
                    "timeout": 120,
                    "async":false,
                    "headers": {
                        "X-Requested-With": "XMLHttpRequest",
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    "data": {
                        "order_id": quoteIdData
                    }
                }
                  $.ajax({
                    url: url.build("response/payment/index"),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    method: 'POST',
                    async: false,
                    data:  {
                        "order_id": quoteIdData
                    },
                    success: function(data){
                        console.log('processing...');
                        if(data == "warning" || data.length == 0 || data == "error" ) {
                            $.ajax(settings).done(function (response) {
                                if( response.increment_id){
                                    invoice = response.increment_id;
                                }
                            });
                        }else{
                            invoice = data.increment_id;
                        }

                       if(invoice){
                           if(window.checkoutConfig.payment.epayco.payco_test == "1"){
                               window.checkoutConfig.payment.epayco.payco_test= "true";
                               var test2 = true;
                           } else {
                               window.checkoutConfig.payment.epayco.payco_test = "false";
                               var test2 = false;
                           }

                           var items = '';
                           for(var i = 0; i <  window.checkoutConfig.quoteItemData.length; i++){
                               if(window.checkoutConfig.totalsData.items.length==1){
                                   items=window.checkoutConfig.quoteItemData[i].product.name;
                               }else{
                                   items += window.checkoutConfig.quoteItemData[i].product.name+',';
                               }

                           }

                           // fin calcular base iva
                           if(!window.checkoutConfig.isCustomerLoggedIn){
                               if(customerData){
                                   name_billing =  customerData.firstname + ' ' + customerData.lastname;
                                   address_billing =  customerData.street[0]+ ' ' + customerData.street[1];
                                   country = customerData.country_id;
                               }else{
                                   country = 'CO';
                               }
                           } else {
                               name_billing = window.checkoutConfig.customerData.firstname + ' '+ window.checkoutConfig.customerData.lastname;
                               mobile = countryBllg.telephone;
                               address_billing = countryBllg.street[0];
                               country = countryBllg.countryId;
                           }
                           var lang = '';
                           var temp = window.checkoutConfig.payment.epayco.language.split("_");
                           lang = temp[0];
                           var amount = 0;
                           amount = totals._latestValue.base_grand_total;
                           var taxes = 0;
                           taxes = totals._latestValue.base_tax_amount;
                           var tax_base = 0;
                           tax_base = amount - taxes;
                           parseFloat(tax_base);

                           var data={
                               //Parametros compra (obligatorio)
                               name: items,
                               description: items,
                               invoice: invoice,
                               currency: window.checkoutConfig.quoteData.store_currency_code,
                               amount: amount.toString(),
                               tax_base: tax_base.toString(),
                               tax: taxes.toString(),
                               country: country,
                               lang: lang,
                               //Onpage='false' - Standard='true'
                               external: window.checkoutConfig.payment.epayco.vertical_cs,
                               //Atributos opcionales
                               extra1: orderId,
                               extra2: invoice,
                               confirmation:url.build("confirmation/epayco/index"),
                               response: url.build("confirmation/epayco/index"),
                               //Atributos cliente
                                email_billing:email,
                               name_billing: name_billing,
                               address_billing: address_billing,
                               type_doc_billing: docType,
                               mobilephone_billing: mobile,
                               number_doc_billing: doc,
                               autoclick: "true",
                               ip: ip,
                               test: test2.toString()
                           };
                            button0.disabled = false;
                            button1.disabled = false;
                            button0.style.disabled = false;
                            button1.style.disabled = false;
                           const apiKey = window.checkoutConfig.payment.epayco.payco_public_key;
                           const privateKey = window.checkoutConfig.payment.epayco.payco_private_key;
                           if(localStorage.getItem("invoicePayment") == null){
                               localStorage.setItem("invoicePayment", invoice);
                               _this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                           }else{
                               if(localStorage.getItem("invoicePayment") != invoice){
                                   localStorage.removeItem("invoicePayment");
                                   localStorage.setItem("invoicePayment", invoice);
                                   _this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                               }else{
                                    _this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                               }
                           }
                       }
                    },
                    error :function(error){
                        //$('body').trigger('processStop');
                         fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                        console.log('error: '+error);
                    }
                });

            },
            getOrderId: function(){
                return window.checkoutConfig.payment.epayco.getOrderId;
            },
            getQuoteData: function(){
                return window.checkoutConfig.payment.epayco.getQuoteData;
            },
            getStoreData: function(){
                return window.checkoutConfig.payment.epayco.getStoreData;
            },
            getOrderIncrementId: function(){
                return window.checkoutConfig.payment.epayco.getOrderIncrementId;
            },
            getQuoteIncrementId: function(){
                return window.checkoutConfig.payment.epayco.getQuoteIncrementId;
            },
            getQuoteIdData: function(){
                return window.checkoutConfig.payment.epayco.getQuoteIdData;
            },
            getdisplayTitle: function () {
                return window.checkoutConfig.payment.epayco.payco_title;
            },
            text: function(){
                return window.checkoutConfig.payment.epayco.text;
            },
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            responseAction: function(){
                return window.checkoutConfig.payment.epayco.responseAction;
            },
            getCustomerIp: function(){
                return window.checkoutConfig.payment.epayco.getCustomerIp;
            },
            afterPlaceOrder: function () {
                this.renderCheckout();
            },
            makePayment:  function (privatekey, apikey, info, external) {
                const headers = { 'Content-Type': 'application/json' } ;
                headers['privatekey'] = privatekey;
                headers['apikey'] = apikey;
                var payment =   function (){
                    return  fetch("https://cms.epayco.co/checkout/payment/session", {
                        method: 'POST',
                        body: JSON.stringify(info),
                        headers
                    })
                        .then(res =>  res.json())
                        .catch(err => err);
                }
                payment()
                    .then(session => {
                        if(session.data.sessionId != undefined){
                            localStorage.removeItem("sessionPayment");
                            localStorage.setItem("sessionPayment", session.data.sessionId);
                            const handlerNew = window.ePayco.checkout.configure({
                                sessionId: session.data.sessionId,
                                external: external,
                            });
                            //$('body').trigger('processStop');
                            //$('#loader-gateway').trigger('processStop');
                            fullScreenLoader.stopLoader();
                            handlerNew.openNew()
                        }
                    })
                    .catch(error => {
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                        error.message;
                    });
            }

        });
    }
);
