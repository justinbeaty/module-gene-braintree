/**
 * Braintree Apple Pay Express class
 *
 * @type {BraintreeExpressAbstract}
 * @author Dave Macaulay <dave@gene.co.uk>
 */
var BraintreeApplePayExpress = Class.create(BraintreeExpressAbstract, {
    vzeroApplePay: false,

    /**
     * Init the PayPal button class
     *
     * @private
     */
    _init: function () {
        if (!window.ApplePaySession || window.ApplePaySession && !ApplePaySession.canMakePayments()) {
            return false;
        }

        this.vzeroApplePay = new vZeroApplePay(
            false,
            this.storeFrontName,
            false,
            false,
            this.urls.clientTokenUrl
        );

        this.selectedMethod = false;
        this.amount = false;

        this.items = [];

        this.initOverlay();
    },

    /**
     * Init our overlay for Apple Pay loading states
     */
    initOverlay: function () {
        if ($$('.apple-pay-loading-overlay').length == 0) {
            $$('body').first().insert('<div class="apple-pay-loading-overlay">' +
                    '<div class="ball-scale-ripple-multiple">' +
                        '<div></div>' +
                        '<div></div>' +
                        '<div></div>' +
                    '</div>' +
                '</div>');
        }
    },

    /**
     * Show the Apple Pay loading state
     */
    setLoading: function () {
        $$('.apple-pay-loading-overlay').first().addClassName('active');
    },

    /**
     * Reset the Apple Pay loading state
     */
    resetLoading: function () {
        $$('.apple-pay-loading-overlay').first().removeClassName('active');
    },

    /**
     * Set the vZero Apple Pay amount
     *
     * @param amount
     */
    setAmount: function (amount) {
        this.amount = amount;
        this.vzeroApplePay.amount = amount;
    },

    /**
     * Attach the express instance to a number of buttons
     *
     * @param buttons
     */
    attachToButtons: function (buttons) {
        // If Apple Pay is not supported, hide the buttons
        if (!window.ApplePaySession || window.ApplePaySession && !ApplePaySession.canMakePayments()) {
            buttons.each(function (button) {
                button.hide();
            });

            return false;
        }

        var options = {
            validate: this.validateForm,
            onSuccess: function (payload, event) {
                if (this.selectedMethod === false && (typeof this.config.virtual === 'undefined' || !this.config.virtual)) {
                    alert('We\'re unable to ship to the address you\'ve selected. You have not been charged.');
                    return false;
                }

                // Submit the Apple Pay session
                return this.submitApplePay(
                    payload.nonce,
                    event.payment.shippingContact,
                    event.payment.billingContact,
                    this.selectedMethod
                );
            }.bind(this),
            paymentRequest: {
                requiredShippingContactFields: ['postalAddress', 'email', 'phone'],
                requiredBillingContactFields: ['postalAddress']
            },
            onShippingContactSelect: this.onShippingContactSelect.bind(this),
            onShippingMethodSelect: this.onShippingMethodSelect.bind(this)
        };

        // We don't require shipping details for virtual orders
        if (typeof this.config.virtual !== 'undefined' && this.config.virtual) {
            options.paymentRequest.requiredShippingContactFields = ['email'];
        }

        // Add a class to the parents of the buttons
        buttons.each(function (button) {
            button.up().addClassName('braintree-applepay-express-container');
        });

        // Initialize the PayPal button logic on any valid buttons on the page
        this.vzeroApplePay.attachApplePayEvent(buttons, options);
    },

    /**
     * Submit an Apple Pay transaction to the server
     *
     * @param nonce
     * @param shippingAddress
     * @param billingAddress
     * @param shippingMethod
     */
    submitApplePay: function (nonce, shippingAddress, billingAddress, shippingMethod) {
        var params = {
            nonce: nonce,
            shippingAddress: Object.toJSON(shippingAddress),
            billingAddress: Object.toJSON(billingAddress),
            shippingMethod: shippingMethod
        };

        // Pass over the product ID to the submit action
        if (typeof this.config.productId !== 'undefined') {
            params.productId = this.config.productId;
            params.productForm = $('product_addtocart_form') ? $('product_addtocart_form').serialize() : $('pp_express_form').serialize();
        }

        this.setLoading();

        new Ajax.Request(this.urls.submitUrl, {
            method: 'POST',
            parameters: params,
            onSuccess: function (transport) {
                var response = this._parseTransportAsJson(transport);
                if (response.success == true) {
                    window.location = this.urls.successUrl;
                } else if (response.message) {
                    this.resetLoading();
                    alert(response.message);
                } else {
                    this.resetLoading();
                    alert('An unknown issue has occurred whilst processing your Apple Pay payment.');
                }
            }.bind(this),
            onFailure: function () {
                this.resetLoading();
                alert('An unknown issue has occurred whilst processing your Apple Pay payment.');
            }.bind(this)
        });
    },

    /**
     * Handle a shipping contact being selected with express flow
     *
     * @param event
     * @param applePayInstance
     * @param session
     */
    onShippingContactSelect: function (event, applePayInstance, session) {
        var address = event.shippingContact,
            params = address;

        // Pass over the product ID if not already present
        if (typeof this.config.productId !== 'undefined') {
            params.productId = this.config.productId;
            params.productForm = $('product_addtocart_form') ? $('product_addtocart_form').serialize() : $('pp_express_form').serialize();
        }

        new Ajax.Request(this.urls.fetchMethodsUrl, {
            method: 'POST',
            parameters: params,
            onSuccess: function (data) {
                var response = this._parseTransportAsJson(data);
                if (response.success == true) {
                    var rates = [],
                        newItems = [],
                        firstRate = false;

                    // Update the total from the request
                    if (response.total) {
                        this.setAmount(parseFloat(response.total).toFixed(2));
                    }

                    // Add extra items to the view
                    this.items = [];
                    if (response.items) {
                        response.items.each(function (item) {
                            if (item.label && item.amount) {
                                this.items.push(item);
                                newItems.push({
                                    type: 'final',
                                    label: item.label,
                                    amount: parseFloat(item.amount).toFixed(2)
                                });
                            }
                        }.bind(this));
                    }

                    // If there are available rates, update the session
                    if (response.rates.length > 0) {
                        // Sort the rates by price (amount)
                        response.rates.sort(function(a,b) {
                            return (a.amount > b.amount) ? 1 : ((b.amount > a.amount) ? -1 : 0);}
                        );
                        response.rates.each(function (rate, index) {
                            rate.amount = parseFloat(rate.amount).toFixed(2);
                            rates.push(rate);
                        });
                        firstRate = rates[0];
                        this.selectedMethod = firstRate.identifier;
                        newItems.push({
                            type: 'final',
                            label: 'Shipping',
                            amount: parseFloat(firstRate.amount).toFixed(2)
                        });
                    }

                    // Build up the new total
                    var newTotal = {
                        label: this.storeFrontName,
                        amount: parseFloat(parseFloat(this.amount) + (firstRate ? parseFloat(firstRate.amount) : 0)).toFixed(2)
                    };

                    // Display error if invalid postal address
                    if (rates.length > 0) {
                        session.completeShippingContactSelection(ApplePaySession.STATUS_SUCCESS, rates, newTotal, newItems);
                    } else {
                        session.completeShippingContactSelection(ApplePaySession.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS, rates, newTotal, newItems);
                    }
                }
            }.bind(this),
            onFailure: function () {
                session.abort();
                alert(Translator.translate('An error was encountered whilst trying to determine shipping rates for your order.'));
            }
        });
    },

    /**
     * Update line items and total cost with shipping methods
     *
     * @param event
     * @param applePayInstance
     * @param session
     */
    onShippingMethodSelect: function (event, applePayInstance, session) {
        var shippingMethod = event.shippingMethod;
        this.selectedMethod = shippingMethod.identifier;

        var newTotal = {
            label: this.storeFrontName,
            amount: parseFloat(parseFloat(this.amount) + parseFloat(shippingMethod.amount)).toFixed(2)
        },
            newItems = [];

        if (this.items) {
            this.items.each(function (item) {
                if (item.label && item.amount) {
                    newItems.push({
                        type: 'final',
                        label: item.label,
                        amount: item.amount
                    });
                }
            }.bind(this));
        }

        newItems.push({
            type: 'final',
            label: 'Shipping',
            amount: shippingMethod.amount
        });

        session.completeShippingMethodSelection(ApplePaySession.STATUS_SUCCESS, newTotal, newItems);
    },

    /**
     * Parse a transports response into JSON
     *
     * @param transport
     * @returns {*}
     * @private
     */
    _parseTransportAsJson: function (transport) {
        try {
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
        } catch (e) {
            return false;
        }
    }

});
