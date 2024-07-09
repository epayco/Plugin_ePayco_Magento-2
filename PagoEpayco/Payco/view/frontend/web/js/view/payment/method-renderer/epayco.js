/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/authentication-messages',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js'
    ],
    function ($,Component,url,quote,checkoutData,messageContainer, urlBuilder, customer,placeOrderService,fullScreenLoader,placeOrderAction,ePayco) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PagoEpayco_Payco/payment/form',
                transactionResult: ''
            },
            redirectAfterPlaceOrder: false,
            /*initObservable: function () {
                this.loadScript('https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js', function() {
                    console.log('Script loaded successfully.');
                });
                return this;
            },*/
            renderCheckout: function() {
                fullScreenLoader.startLoader();
                var getQuoteId = this.getQuoteId();
                var ip = this.getCustomerIp();
                var _this = this;
                $.ajax({
                    url: url.build("epayco/index/index"),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    method: 'POST',
                    async: false,
                    data:  {
                        "order_id": getQuoteId
                    },
                    success: function(data){
                        if(data.success){
                            var checkoutConfig= window.checkoutConfig;
                            let stringNumber = "000000000";
                            let number = parseInt(stringNumber, 10);
                            let result = number + data.order_id;
                            let invoice = result.toString().padStart(9, '0');
                            var shippingAddress = quote.shippingAddress();
                            var billingAddress = quote.billingAddress();
                            var docType='';
                            var mobile = shippingAddress.telephone??billingAddress.telephone;
                            var doc= '';
                            var country = shippingAddress.countryId??billingAddress.countryId;
                            var email = quote.guestEmail;
                            var name_billing = shippingAddress.firstname??billingAddress.firstname+" "+shippingAddress.lastname??billingAddress.lastname;
                            var address_billing = shippingAddress.street[0]??billingAddress.street[0];
                            var currency = checkoutConfig.quoteData.store_currency_code;
                            var totals = quote.getTotals();
                            var amount = 0;
                            amount = totals._latestValue.base_grand_total;
                            var taxes = 0;
                            taxes = totals._latestValue.base_tax_amount;
                            var tax_base = 0;
                            tax_base = amount - taxes;
                            var items = '';
                            var test = false;
                            for(var i = 0; i < checkoutConfig.quoteItemData.length; i++){
                                if(checkoutConfig.totalsData.items.length==1){
                                    items=checkoutConfig.quoteItemData[i].product.name;
                                }else{
                                    items += checkoutConfig.quoteItemData[i].product.name+',';
                                }
                            }
                            if(checkoutConfig.payment.epayco.payco_test === "1"){
                                var test = true;
                            }
                            var data={
                                //Parametros compra (obligatorio)
                                name: items,
                                description: items,
                                invoice: invoice,
                                currency: currency,
                                amount: amount.toString(),
                                tax_base: tax_base.toString(),
                                tax: taxes.toString(),
                                country: country,
                                lang: checkoutConfig.payment.epayco.language_cs,
                                //Onpage='false' - Standard='true'
                                external: checkoutConfig.payment.epayco.vertical_cs,
                                //Atributos opcionales
                                extra1: data.order_id,
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
                                test: test.toString()
                            };
                            const apiKey = window.checkoutConfig.payment.epayco.payco_public_key.trim();
                            const privateKey = window.checkoutConfig.payment.epayco.payco_private_key.trim();
                            var handler = window.ePayco.checkout.configure({
                               key: apiKey,
                               test:test
                           })
                           fullScreenLoader.stopLoader();
                           handler.open(data);
                            if(localStorage.getItem("invoicePayment") == null){
                                localStorage.setItem("invoicePayment", invoice);
                                //_this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                            }else{
                                if(localStorage.getItem("invoicePayment") != invoice){
                                    localStorage.removeItem("invoicePayment");
                                    localStorage.setItem("invoicePayment", invoice);
                                    //_this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                                }else{
                                    //_this.makePayment(privateKey,apiKey,data, data.external == 'true'?true:false)
                                }
                            }
                            //window.location.replace(url.build('checkout/onepage/success'));
                        }else{
                            fullScreenLoader.stopLoader();
                            alert({
                                content: $.mage.__('Sorry, something went wrong. Please try again later.')
                            });
                            console.log('error: '+error);
                        }
                    },
                    error :function(error){
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                        console.log('error: '+error);
                    }
                });
            },
            getCode: function() {
                return 'epayco';
            },
            getQuoteData: function(){
                return window.checkoutConfig.payment.epayco.getQuoteData;
            },
            getSessionId: function(){
                return window.checkoutConfig.payment.epayco.getSessionId;
            },
            getQuoteId: function(){
                return window.checkoutConfig.payment.epayco.getQuoteId;
            },
            getLanguage: function(){
                return window.checkoutConfig.payment.epayco.getLanguage;
            },
            getCustomerIp: function(){
                return window.checkoutConfig.payment.epayco.getCustomerIp;
            },
            loadScript: function (url,callback){
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.onload = function() {
                    if (callback) {
                        callback();
                    }
                };
                script.onerror = function() {
                    console.error('Error loading script:', url);
                };
                document.head.appendChild(script);
            },
            makePayment:  function (privatekey, apikey, info, external) {
                const headers = { 'Content-Type': 'application/json' } ;
                headers['privatekey'] = privatekey;
                headers['apikey'] = apikey;
                var payment = function (){
                    return  fetch("https://cms.epayco.io/checkout/payment/session", {
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
            },
            afterPlaceOrder: function () {
                this.renderCheckout();
            },
            beforePlaceOrder: function () {
                this.renderCheckout();
            },
        });
    }
);
