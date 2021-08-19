/**
 * Google Pay vZero integration
 *
 * @class vZeroGooglePay
 * @author Paul Canning <paul.canning@gene.co.uk>
 */
var vZeroGooglePay = Class.create({

    /**
     * Initialize
     *
     * @param clientToken
     * @param storeName
     * @param integration
     * @param clientTokenUrl
     * @param additionalOptions
     */
    initialize: function (clientToken, storeName, integration, clientTokenUrl, additionalOptions) {
        this.clientToken = clientToken || false;
        this.storeName = storeName;
        this.integration = integration || false;
        this.clientTokenUrl = clientTokenUrl;
        this.additionalOptions = additionalOptions;

        this.vzero = this.integration.vzero || false;
        this.methodCode = 'gene_braintree_googlepay';
        this.client = false;
        this.paymentsClient = null;

        if (this.integration) {
            this.bindEvents();
        }

        // Add a body class if the browser supports GooglePay
        if ($$('body').first()) {
            $$('body').first().addClassName('supports-google-pay');
        }
    },

    bindEvents: function () {
        this.vzero.observeEvent('integration.onInitDefaultMethod', this.onInitDefaultMethod, this);
        this.vzero.observeEvent('onAfterUpdateData', this.onAfterUpdateData, this);

        if (this.integration.isOnepage) {
            this.vzero.observeEvent('integration.onPaymentMethodSwitch', this.onPaymentMethodSwitch, this);
            this.vzero.observeEvent('integration.onObserveAjaxRequests', this.onObserveAjaxRequests, this);
        } else {
            this.vzero.observeEvent('integration.onReviewInit', this.onReviewInit, this);
        }
    },

    onInitDefaultMethod: function (event, self) {
        return self._updateButton();
    },

    onAfterUpdateData: function (response, self) {
        if (typeof response.grandTotal !== 'undefined' && response.grandTotal) {
            self.amount = response.grandTotal;
        }

        return self._updateButton();
    },

    onPaymentMethodSwitch: function (event, self) {
        return self._updateButton();
    },

    onObserveAjaxRequests: function (event, self) {
        return self._updateButton();
    },

    onReviewInit: function (event, self) {
        return self._updateButton();
    },

    isGooglePayActive: function () {
        return (!this.integration || this.integration.getPaymentMethod() === this.methodCode);
    },

    _updateButton: function () {
        if (this.isGooglePayActive()) {
            this.addButton();
        } else {
            this.hideButton();
        }
    },

    addButton: function () {
        if (this.isGooglePayActive()) {
            this.attachGooglePayEvent();
        }
    },

    hideButton: function () {
        if (this.button) {
            this.button.hide();
        }
    },

    attachGooglePayEvent: function () {
        var merchantAccountId = this.additionalOptions.merchantAccountId;
        var currencyCode = this.additionalOptions.currencyCode;
        var allowedCardNetworks = this.additionalOptions.allowedCardNetworks;

        // Grab an instance of the Braintree client
        this.getClient(function (clientInstance) {
            // Create a new instance of PayPal
            braintree.googlePayment.create({
                client: clientInstance,
                googlePayVersion: 2,
                googleMerchantId: merchantAccountId
            }, function (googlePaymentErr, googlePaymentInstance) {
                if (googlePaymentErr) {
                    console.error('Error creating google pay instance:', googlePaymentErr);
                    return;
                }

                var paymentsClient = this.getGooglePaymentsClient();

                paymentsClient.isReadyToPay({
                    apiVersion: 2,
                    apiVersionMinor: 0,
                    allowedPaymentMethods: googlePaymentInstance.createPaymentDataRequest().allowedPaymentMethods
                }).then(function (response) {
                    if (response.result) {
                        var button = paymentsClient.createButton({onClick: function () {
                                var responseData;
                                var paymentDataRequest = googlePaymentInstance.createPaymentDataRequest({
                                    transactionInfo: {
                                        currencyCode: currencyCode,
                                        totalPriceStatus: 'ESTIMATED',
                                        totalPrice: this.vzero.amount
                                    },
                                    allowedPaymentMethods: [{
                                        type: "CARD",
                                        parameters: {
                                            allowedCardNetworks: allowedCardNetworks,
                                            billingAddressRequired: true,
                                            billingAddressParameters: {
                                                format: 'MIN',
                                                phoneNumberRequired: true
                                            }
                                        }
                                    }],
                                    emailRequired: true,
                                    shippingAddressRequired: true,
                                });

                                paymentsClient.loadPaymentData(paymentDataRequest).then(function(paymentData) {
                                    responseData = paymentData;
                                    return googlePaymentInstance.parseResponse(paymentData);
                                }).then(function (result) {
                                    $('googlepay-payment-nonce').value = result.nonce;
                                    this.vzero.integration.submitCheckout();
                                }.bind(this)).catch(function (err) {
                                    console.error(err);
                                });
                            }.bind(this)
                        });

                        if (button) {
                            // hide "place order" button
                            $$('#review-buttons-container .btn-checkout').first().hide();
                            // add Google Pay button
                            document.getElementById('review-buttons-container').append(button);
                        }
                    }
                }).catch(function (err) {
                    console.error(err);
                });
            }.bind(this));
        }.bind(this));
    },

    getGooglePaymentsClient: function () {
        var environment = this.additionalOptions.environment;

        if (this.paymentsClient === null) {
            this.paymentsClient = new google.payments.api.PaymentsClient({
                environment: environment === 'sandbox' ? 'TEST' : 'PRODUCTION'
            });
        }

        return this.paymentsClient;
    },

    getClient: function (callbackFn) {
        if (this.client !== false) {
            if (typeof callbackFn === 'function') {
                callbackFn(this.client);
            }
        } else {
            // Retrieve a client token
            this.getClientToken(function (clientToken) {
                // Create a new braintree client instance
                braintree.client.create({
                    authorization: clientToken
                }, function (clientErr, clientInstance) {
                    if (clientErr) {
                        // Handle error in client creation
                        console.error(clientErr);
                        return;
                    }

                    this.client = clientInstance;
                    callbackFn(this.client);
                }.bind(this));
            });
        }
    },

    getClientToken: function (callbackFn) {
        if (this.clientToken !== false) {
            return callbackFn(this.clientToken);
        } else if (window.braintreeClientToken) {
            return callbackFn(window.braintreeClientToken);
        } else {
            new Ajax.Request(
                this.clientTokenUrl,
                {
                    method: 'get',
                    onSuccess: function (transport) {
                        // Verify we have some response text
                        if (transport && (transport.responseJSON || transport.responseText)) {
                            // Parse the response from the server
                            var response = this._parseTransportAsJson(transport);
                            if (response.success === true && typeof response.client_token === 'string') {
                                this.clientToken = response.client_token;
                                window.braintreeClientToken = response.client_token;
                                return callbackFn(this.clientToken);
                            } else {
                                console.error('We were unable to retrieve a client token from the server to initialize the Braintree flow.');
                                if (response.error) {
                                    console.error(response.error);
                                }
                            }
                        }
                    }.bind(this),
                    onFailure: function () {
                        console.error('We were unable to retrieve a client token from the server to initialize the Braintree flow.');
                    }.bind(this)
                }
            );
        }
    },

    _parseTransportAsJson: function (transport) {
        if (transport.responseJSON && typeof transport.responseJSON === 'object') {
            return transport.responseJSON;
        } else if (transport.responseText) {
            if (typeof JSON === 'object' && typeof JSON.parse === 'function') {
                return JSON.parse(transport.responseText);
            } else {
                return eval('(' + transport.responseText + ')');
            }
        }

        return {};
    }
});