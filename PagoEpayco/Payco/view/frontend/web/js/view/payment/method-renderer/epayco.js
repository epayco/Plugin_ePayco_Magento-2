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
        'https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod-v2.js'
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
                this.loadScript('https://checkout.epayco.co/checkout.js', function() {
                    console.log('Script loaded successfully.');
                });
                return this;
            },*/
            renderCheckout: function() {
                try {
                    fullScreenLoader.startLoader();
                    var getQuoteId = this.getQuoteId();
                    var _this = this;

                    if (getQuoteId) {
                        var storedQuoteId = localStorage.getItem("epayco_quote_id");
                        if (storedQuoteId == getQuoteId) {
                            localStorage.setItem("epayco_quote_id", getQuoteId);
                            var data = localStorage.getItem("epayco_invoice");
                            if (data) {
                                _this.onEpaycoSuccess(data, _this);
                            } else {
                                fullScreenLoader.stopLoader();
                                alert({
                                    content: $.mage.__('Sorry, something went wrong. Please try again later.')
                                });
                            }
                        } else {
                            $.ajax({
                                url: url.build("epayco/index/index"),
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                method: 'POST',
                                async: false,
                                data:  { "order_id": getQuoteId },
                                success: function(data) {
                                    _this.onEpaycoSuccess(data, _this);
                                },
                                error: function(error) {
                                    fullScreenLoader.stopLoader();
                                    alert({
                                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                                    });
                                    console.log('error: ' + error);
                                }
                            });
                        }
                    } else {
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                    }
                } catch (error) {
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                    console.log('error: ' + error);
                }
            },
            onEpaycoSuccess: function(data, _this){
                if(data.success){
                    var ip = this.getCustomerIp();
                    var checkoutConfig= window.checkoutConfig;
                    let stringNumber = "000000000";
                    let increment_id = data.increment_id;
                    let number = parseInt(stringNumber, 10);
                    let result = number + data.order_id;
                    //let invoice = result.toString().padStart(9, '0');
                    let invoice = increment_id;
                    localStorage.setItem("epayco_invoice", JSON.stringify(data));
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
                    let typeCheckout = checkoutConfig.payment.epayco.vertical_cs === 'true' ? 'standard' : 'onepage';
                    //let date_ = new Date().getTime();
                    var data={
                        //Parametros compra (obligatorio)
                        name: items,
                        description: items,
                        invoice: invoice,
                        currency: currency,
                        amount: parseFloat(amount),
                        taxBase: parseFloat(tax_base),
                        tax: parseFloat(taxes),
                        country: country,
                        lang: checkoutConfig.payment.epayco.language_cs,
                        //Onpage='false' - Standard='true'
                        //external: checkoutConfig.payment.epayco.vertical_cs,
                        //Atributos opcionales
                        //extra1: data.order_id,
                        extras:{
                            extra1: data.order_id,
                            extra2: getQuoteId
                        },
                        confirmation:url.build("confirmation/epayco/index"),
                        response: url.build("confirmation/epayco/index"),
                        forceResponse:false,//no mostrar el detalle de la transaccion
                        noRedirectOnClose: false,
                        uniqueTransactionPerBill:false,
                        //Atributos cliente
                        billing:{
                            email: email,
                            name: name_billing,
                            address: address_billing,
                            mobilePhone: mobile,
                            typeDoc: docType,
                            numberDoc: doc,
                        },
                        //email_billing:email,
                        //name_billing: name_billing,
                        //address_billing: address_billing,
                        //type_doc_billing: docType,
                        //mobilephone_billing: mobile,
                        //number_doc_billing: doc,
                        method: "POST",
                        autoClick:true,
                        ip: ip,
                        test: test,
                        checkout_version:"2",
                        extrasEpayco:{
                            extra5:"P27"
                        }
                    };
                    //console.log("data",data)
                    const apiKey = window.checkoutConfig.payment.epayco.payco_public_key.trim();
                    const privateKey = window.checkoutConfig.payment.epayco.payco_private_key.trim();
                    /*var handler = window.ePayco.checkout.configure({
                        key: apiKey,
                        test:test
                    })*/
                    //fullScreenLoader.stopLoader();
                    //handler.open(data);
                    _this.makePayment(privateKey,apiKey,data, typeCheckout, test)
                    //window.location.replace(url.build('checkout/onepage/success'));
                }else{
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                    console.log('error: '+error);
                }
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
            makePayment:  function (privatekey, apikey, info, external, test) {
                const _this = this;
                const headers = { "Content-Type": "application/json" };
                const payment = function () {
                    return fetch("https://eks-apify-service.epayco.io/payment/session/create", {
                        method: "POST",
                        body: JSON.stringify(info),
                        headers
                    })
                    .then(res => res.json());
                };
                return _this.getBearerToken(privatekey, apikey)
                    .then(token => {
                        headers["Authorization"] = "Bearer " + token;
                        return payment();
                    })
                    .then(session => {
                        if (session.data && session.data.sessionId) {
                            localStorage.removeItem("sessionPayment");
                            localStorage.setItem("sessionPayment", session.data.sessionId);
                            const handlerNew = window.ePayco.checkout.configure({
                                sessionId: session.data.sessionId,
                                type: external,
                                test: test,
                            });
                            fullScreenLoader.stopLoader();
                            handlerNew.open();
                        } else {
                            fullScreenLoader.stopLoader();
                            alert({
                                content: $.mage.__('Sorry, something went wrong. Please try again later.')
                            });
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                    });
            },
            getBearerToken: function (priv,pub) {
                const cacheKey = 'epaycoBearer';
                const expKey = cacheKey + ':exp';
                const cached = localStorage.getItem(cacheKey);
                const exp = parseInt(localStorage.getItem(expKey) || '0', 10);
                if (cached && Date.now() < exp) return Promise.resolve(cached);

                return fetch("https://eks-apify-service.epayco.io/login", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": "Basic " + btoa(`${pub}:${priv}`)
                    }
                })
                .then(r => r.json())
                .then(json => {
                    const token = json.token || json.access_token;
                    if (!token) throw new Error("No se recibió token");
                    const ttlMs = (14 * 60 * 1000) - 15000;
                    localStorage.setItem(cacheKey, token);
                    localStorage.setItem(expKey, String(Date.now() + ttlMs));
                    return token;
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
