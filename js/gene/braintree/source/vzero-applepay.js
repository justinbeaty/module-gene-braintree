/**
 * Separate class to handle functionality around the vZero Apple Pay functionality
 *
 * @class vZeroApplePay
 * @author Dave Macaulay <dave@gene.co.uk>
 */
var vZeroApplePay = Class.create();
vZeroApplePay.prototype = {
    /**
     * Initialize the PayPal button class
     *
     * @param clientToken Client token generated from server
     * @param storeFrontName The store name to show within the PayPal modal window
     * @param integration
     * @param appleButtonSelector
     * @param clientTokenUrl
     */
    initialize: function (clientToken, storeFrontName, integration, appleButtonSelector, clientTokenUrl) {
        if (!window.ApplePaySession || window.ApplePaySession && !ApplePaySession.canMakePayments()) {
            console.warn('This browser does not support Apple Pay, the method will be hidden.');
            return false;
        }

        this.clientToken = clientToken || false;
        this.storeFrontName = storeFrontName;
        this.integration = integration || false;
        this.appleButtonSelector = appleButtonSelector;
        this.clientTokenUrl = clientTokenUrl;

        // Retrieve the vzero class to attach events
        this.vzero = this.integration.vzero || false;

        this.methodCode = 'gene_braintree_applepay';
        this.client = false;
        this.amount = false;
        this.button = false;

        if (this.integration) {
            this.bindEvents();
        }

        // Add a body class if the browser supports ApplePay
        if ($$('body').first()) {
            $$('body').first().addClassName('supports-apple-pay');
        }
    },

    /**
     * Bind various events to ensure Apple Pay functionality
     */
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

    /**
     * Retrieve the client token
     *
     * @param callbackFn
     * @returns {*}
     */
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
                            if (response.success == true && typeof response.client_token === 'string') {
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

    /**
     * Retrieve the client from the class, or initialize the client if not already present
     *
     * @param callbackFn
     */
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

    /**
     * Is the Apple Pay payment method selected?
     *
     * @returns {boolean}
     */
    isApplePayActive: function () {
        return (!this.integration || this.integration.getPaymentMethod() == this.methodCode);
    },

    /**
     * After an update from vZero ensure the amount in this class is updated
     *
     * @param response
     * @param self
     */
    onAfterUpdateData: function (response, self) {
        if (typeof response.grandTotal !== 'undefined' && response.grandTotal) {
            self.amount = response.grandTotal;
        }

        return self._updateButton();
    },

    /**
     * Check to see if Apple Pay is active by default
     *
     * @param event
     * @param self
     */
    onInitDefaultMethod: function (event, self) {
        return self._updateButton();
    },

    /**
     * On payment method switch detect if we should modify the Apple Pay button
     *
     * @param event
     * @param self
     */
    onPaymentMethodSwitch: function (event, self) {
        return self._updateButton();
    },

    /**
     * When an ajax request is observed run some code
     *
     * @param event
     * @param self
     * @returns {*}
     */
    onObserveAjaxRequests: function (event, self) {
        return self._updateButton();
    },

    /**
     * In non one step checkouts we add the button on the review step
     *
     * @param event
     * @param self
     */
    onReviewInit: function (event, self) {
        return self._updateButton();
    },

    /**
     * Update the button dependant on the active state
     *
     * @private
     */
    _updateButton: function () {
        if (this.isApplePayActive()) {
            this.addButton(false, false, true);
        } else {
            this.hideButton();
        }
    },

    /**
     * Add our button to the page
     */
    addButton: function (buttonHtml, submitButtonQuery, append) {
        var submitButton;
        buttonHtml = buttonHtml || $$(this.appleButtonSelector).first().innerHTML;
        submitButtonQuery = submitButtonQuery || this.integration.submitButtonClass;
        append = append || false;

        if (this.isApplePayActive()) {
            if (!buttonHtml) {
                console.error('Unable to locate Apple Pay button with selector ' + this.appleButtonSelector);
            } else if (!submitButtonQuery) {
                console.error('Unable to locate element with selector ' + this.appleButtonSelector + ' for button insertion');
            } else {

                // Get the container element
                if (typeof submitButtonQuery === 'string') {
                    submitButton = $$(submitButtonQuery).first();
                } else {
                    submitButton = submitButtonQuery;
                }
                var submitButtonParent = submitButton.up();

                // Verify the container is present on the page
                if (!submitButton) {
                    console.warn('Unable to locate container ' + containerQuery + ' for Apple Pay button.');
                    return false;
                }

                if (this.button) {
                    this.button.show();
                    if (append) {
                        submitButton.hide();
                    }
                } else {
                    // Insert the button element
                    if (append) {
                        submitButtonParent.insert(buttonHtml);
                        submitButton.hide();
                    } else {
                        submitButtonParent.update(buttonHtml);
                    }

                    // Check the container contains a valid button element
                    if (!submitButtonParent.select('[data-applepay]').length) {
                        console.warn('Unable to find valid <button /> element within container.');
                        return false;
                    }

                    // Grab the button and add a loading class
                    var button = submitButtonParent.select('[data-applepay]').first();
                    this.button = button;
                    button.addClassName('braintree-applepay-loading');
                    button.setAttribute('disabled', 'disabled');

                    // Attach the click event
                    var options = {
                        validate: this.integration.validateAll
                    };
                    this.attachApplePayEvent(button, options);
                }
            }
        }
    },

    /**
     * Attach our apple pay button event
     *
     * @param buttons
     * @param options
     */
    attachApplePayEvent: function (buttons, options) {
        // Grab an instance of the Braintree client
        this.getClient(function (clientInstance) {
            // Create a new instance of PayPal
            braintree.applePay.create({
                client: clientInstance
            }, function (applePayErr, applePayInstance) {
                if (applePayErr) {
                    console.error('Error creating applePayInstance:', applePayErr);
                    return;
                }

                var that = this;
                var promise = window.ApplePaySession.canMakePaymentsWithActiveCard(applePayInstance.merchantIdentifier);
                promise.then(function (canMakePaymentsWithActiveCard) {
                    if (canMakePaymentsWithActiveCard) {
                        that.bindButtons(buttons, options, applePayInstance);
                    }
                });
            }.bind(this));
        }.bind(this));
    },

    /**
     * Bind the button events
     */
    bindButtons: function (buttons, options, applePayInstance) {
        var session;
        options = options || {};

        // Convert the buttons to an array and handle them all at once
        if (!Array.isArray(buttons)) {
            buttons = [buttons];
        }

        // Handle each button
        buttons.each(function (button) {
            button.removeClassName('braintree-applepay-loading');
            button.removeAttribute('disabled');
            button.show();

            // Remove any events currently assigned to the button
            Event.stopObserving(button, 'click');

            // Observe the click event to fire the tokenization of PayPal (ie open the window)
            Event.observe(button, 'click', function (event) {
                Event.stop(event);

                if (typeof options.validate === 'function') {
                    try {
                        var optionsPassed = options.validate();
                    } catch(err) {
                        if (typeof productAddToCartForm !== 'object' || productAddToCartForm.form === null) {
                            var optionsPassed = true;
                        } else {
                            var optionsPassed = false;
                            throw err;
                        }
                    }
                    if (optionsPassed) {
                        // Create and begin the Apple Pay session
                        session = this.createApplePaySession(applePayInstance, options);
                        session.begin();
                    }
                } else {
                    // Create and begin the Apple Pay session
                    session = this.createApplePaySession(applePayInstance, options);
                    session.begin();
                }
            }.bind(this));
        }.bind(this));
    },

    /**
     * Build our payment request
     *
     * @param applePayInstance
     * @param options
     * @returns {*}
     */
    buildPaymentRequest: function (applePayInstance, options) {
        var paymentRequest = {
            total: {
                label: this.storeFrontName,
                amount: this.amount || this.vzero.amount
            }
        };

        if (typeof options.paymentRequest === 'object') {
            paymentRequest = Object.extend(paymentRequest, options.paymentRequest);
        }

        return applePayInstance.createPaymentRequest(paymentRequest);
    },

    /**
     * Create our Apple Pay session
     *
     * @param applePayInstance
     * @param options
     * @returns {ApplePaySession}
     */
    createApplePaySession: function (applePayInstance, options) {
        // Create a new ApplePay session
        var session = new ApplePaySession(1, this.buildPaymentRequest(applePayInstance, options));

        // Attach our two callbacks
        session.onvalidatemerchant = function (event) {
            this.onValidateMerchant(event, applePayInstance, session);
        }.bind(this);
        session.onpaymentauthorized = function (event) {
            this.onPaymentAuthorized(event, applePayInstance, session, options);
        }.bind(this);
        session.onshippingcontactselected = function (event) {
            if (typeof options.onShippingContactSelect === 'function') {
                options.onShippingContactSelect(event, applePayInstance, session);
            }
        }.bind(this);
        session.onshippingmethodselected = function (event) {
            if (typeof options.onShippingMethodSelect === 'function') {
                options.onShippingMethodSelect(event, applePayInstance, session);
            }
        }.bind(this);

        return session;
    },

    /**
     * Handle validation of merchant
     *
     * @param event
     * @param applePayInstance
     * @param session
     */
    onValidateMerchant: function (event, applePayInstance, session) {
        applePayInstance.performValidation({
            validationURL: event.validationURL,
            displayName: this.storeFrontName
        }, function (validationErr, merchantSession) {
            if (validationErr) {
                // You should show an error to the user, e.g. 'Apple Pay failed to load.'
                console.error('Error validating merchant:', validationErr);
                session.abort();
                return;
            }
            session.completeMerchantValidation(merchantSession);
        });
    },

    /**
     * Handle the payment being authorized
     *
     * @param event
     * @param applePayInstance
     * @param session
     * @param options
     */
    onPaymentAuthorized: function (event, applePayInstance, session, options) {
        applePayInstance.tokenize({
            token: event.payment.token
        }, function (tokenizeErr, payload) {
            if (tokenizeErr) {
                console.error('Error tokenizing Apple Pay:', tokenizeErr);
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                return;
            }
            session.completePayment(ApplePaySession.STATUS_SUCCESS);

            // Update the payment method and submit the checkout
            if (this.integration) {
                this.updatePaymentNonce(payload.nonce);
                this.integration.resetLoading();
                this.integration.submitCheckout();
            } else {
                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(payload, event);
                }
            }

        }.bind(this));
    },

    /**
     * Update the payment nonce
     *
     * @param nonce
     */
    updatePaymentNonce: function (nonce) {
        $('applepay-payment-nonce').value = nonce;
    },

    /**
     * Hide the button
     */
    hideButton: function () {
        if (this.button) {
            this.button.hide();
        }
    },

    /**
     * Parse a transports response into JSON
     *
     * @param transport
     * @returns {*}
     * @private
     */
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
};

