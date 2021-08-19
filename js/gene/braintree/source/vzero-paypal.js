/**
 * Separate class to handle functionality around the vZero PayPal button
 *
 * @class vZeroPayPalButton
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
var vZeroPayPalButton = Class.create();
vZeroPayPalButton.prototype = {

    /**
     * Initialize the PayPal button class
     *
     * @param clientToken Client token generated from server
     * @param storeFrontName The store name to show within the PayPal modal window
     * @param singleUse Should the system attempt to open in single payment mode?
     * @param locale The locale for the payment
     * @param futureSingleUse When using future payments should we process the transaction as a single payment?
     * @param onlyVaultOnVault Should we only show the Vault flow if the customer has opted into saving their details?
     * @param clientTokenUrl URL to retrieve client token from
     * @param additionalOptions Additional arguments for paypal button
     */
    initialize: function (clientToken, storeFrontName, singleUse, locale, futureSingleUse, onlyVaultOnVault, clientTokenUrl, additionalOptions) {
        this.clientToken = clientToken || false;
        this.clientTokenUrl = clientTokenUrl;
        this.storeFrontName = storeFrontName;
        this.singleUse = singleUse;
        this.locale = locale;
        this.additionalOptions = additionalOptions;

        // Set these to default values on initialization
        this.amount = 0.00;
        this.currency = false;

        this.client = false;

        this.onlyVaultOnVault = onlyVaultOnVault || false;
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
     * Update the pricing information for the PayPal button
     * If the PayPalClient has already been created we also update the _clientOptions
     * so the PayPal modal window displays the correct values
     *
     * @param amount The amount formatted to two decimal places
     * @param currency The currency code
     */
    setPricing: function (amount, currency) {
        // Set them into the class
        this.amount = parseFloat(amount);
        this.currency = currency;
    },

    /**
     * Rebuild the button
     *
     * @deprecated due to JavaScript v3
     * @returns {boolean}
     */
    rebuildButton: function () {
        return false;
    },

    /**
     * Inject the PayPal button into the document
     *
     * @param options Object containing onSuccess method
     * @param buttonHtml
     * @param containerQuery
     * @param append
     */
    addPayPalButton: function (options, containerQuery) {
        var container;
        containerQuery = containerQuery || '#paypal-container';

        // Get the container element
        if (typeof containerQuery === 'string') {
            container = $$(containerQuery);
        } else {
            container = containerQuery;
        }

        // Verify the container is present on the page
        if (!container) {
            console.error('Unable to locate container ' + containerQuery + ' for PayPal button.');
            return false;
        }

        // Attach our PayPal button event to our injected button
        this.attachPayPalButtonEvent(container, options);
    },

    /**
     * Attach the PayPal button event
     *
     * @param buttons
     * @param options
     */
    attachPayPalButtonEvent: function (buttons, options) {
        var __that = this;

        // Grab an instance of the Braintree client
        this.getClient(function (clientInstance) {

            braintree.paypalCheckout.create({
                client: clientInstance
            }, function (paypalCheckoutErr, paypalCheckoutInstance) {

                if (paypalCheckoutErr) {
                    console.error('Error creating PayPal Checkout:', paypalCheckoutErr);
                    return;
                }

                var id;
                for (var i = 0; i < buttons.length; ++i) {
                    // Create random id for button
                    id = 'paypal_button_'+this.getRandomQS();
                    buttons[i].id = id;
                    buttons[i].className += " paypalbtn-rendered";
                    var params = {
                            env: options.env,
                            commit: options.commit,
                            style: options.style,
                            funding: {allowed: [], disallowed: []},

                            payment: function () {
                                if (typeof options.events.validate === 'function' && options.events.validate() === false) {
                                    return reject(new Error('Please select the required product options.'));
                                }

                                return paypalCheckoutInstance.createPayment(options.payment);
                            },
                            onAuthorize: function (data, actions) {
                                return paypalCheckoutInstance.tokenizePayment(data)
                                    .then(options.events.onAuthorize);
                            },
                            onCancel: function () {
                                if (typeof options.events.onCancel === 'function') {
                                    options.events.onCancel();
                                }
                            },
                            onError: function (err) {
                                if (typeof options.events.onError === 'function') {
                                    options.events.onError();
                                }
                            }
                        };

                    // Build up funding object to prevent paypal.Button.render referencing our inital object
                    if (options.funding.allowed.indexOf('credit') >= 0) {
                        params.funding.allowed.push(paypal.FUNDING.CREDIT);
                    } else if(options.funding.disallowed.indexOf('credit') >= 0) {
                        params.funding.disallowed.push(paypal.FUNDING.CREDIT);
                    }
                    if (options.funding.allowed.indexOf('card') >= 0) {
                        params.funding.allowed.push(paypal.FUNDING.CARD);
                    } else if(options.funding.disallowed.indexOf('card') >= 0) {
                        params.funding.disallowed.push(paypal.FUNDING.CARD);
                    }
                    if (options.funding.allowed.indexOf('elv') >= 0) {
                        params.funding.allowed.push(paypal.FUNDING.ELV);
                    } else if(options.funding.disallowed.indexOf('elv') >= 0) {
                        params.funding.disallowed.push(paypal.FUNDING.ELV);
                    }

                    // Override style options if present on the button element
                    if (buttons[i].getAttribute('data-style-layout'))
                        params.style.layout = buttons[i].getAttribute('data-style-layout');
                    if (buttons[i].getAttribute('data-style-size'))
                        params.style.size = buttons[i].getAttribute('data-style-size');
                    if (buttons[i].getAttribute('data-style-shape'))
                        params.style.shape = buttons[i].getAttribute('data-style-shape');
                    if (buttons[i].getAttribute('data-style-color'))
                        params.style.color = buttons[i].getAttribute('data-style-color');

                    // Call to render button
                    __that.renderPayPalBtn(Object.assign({}, params), '#' + id);
                }
            }.bind(this));

        }.bind(this));
    },

    /**
     * Render the PayPal button
     * @param params
     * @param elId
     */
    renderPayPalBtn: function(params, elId) {
        paypal.Button.render(params, elId);
    },

    /**
     * Build the options for our tokenization
     *
     * @returns {{displayName: *, amount: *, currency: *}}
     * @private
     */
    _buildOptions: function () {
        var funding = this.additionalOptions.funding,
            options = {
                env: this.additionalOptions.env,
                commit: true,
                style: this.additionalOptions.buttonStyle,
                payment: {
                    flow: this._getFlow(),
                    amount: this.amount,
                    currency: this.currency,
                    enableShippingAddress: false,
                    shippingAddressEditable: false,
                    displayName: this.storeFrontName
                },
                funding: funding
            };

        return options;
    },

    /**
     * Determine the flow for the PayPal window
     *
     * @returns {*}
     * @private
     */
    _getFlow: function () {
        var flow;

        // @todo this shouldn't force the vault flow for GUEST users

        // Determine the flow based on the singleUse parameter
        if (this.singleUse === true) {
            flow = 'checkout';
        } else {
            flow = 'vault';
        }

        // Determine if the user should be forced back to the checkout flow
        if ($('gene_braintree_paypal_store_in_vault') !== null) {
            if (this.onlyVaultOnVault && /* Are we set to only vault when the customer requests */
                flow == 'vault' && /* Has the flow been set to vault already? */
                !$('gene_braintree_paypal_store_in_vault').checked /* The user has opted not to save their details */
            ) {
                flow = 'checkout';
            }
        }

        return flow;
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
    },

    /**
     * Generates a random number for PayPal button QuerySelector
     *
     * @returns int
     */
    getRandomQS: function() {
        var num = Math.random() * (999999 - 1) + 1;
        return Math.floor(num);
    }
};
