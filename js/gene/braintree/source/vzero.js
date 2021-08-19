/**
 * Magento Braintree class to bridge the v.zero JS SDK and Magento
 *
 * @class vZero
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
var vZero = Class.create();
vZero.prototype = {

    /**
     * Initialize all our required variables that we'll need later on
     *
     * @param code The payment methods code
     * @param clientToken The client token provided by the server
     * @param threeDSecure Flag to determine whether 3D secure is active, this is verified server side
     * @param hostedFields Flag to determine whether we're using hosted fields
     * @param billingName Billing name used in verification of the card
     * @param billingPostcode Billing postcode also needed to verify the card
     * @param quoteUrl The URL to update the quote totals
     * @param tokenizeUrl The URL to re-tokenize 3D secure cards
     * @param clientTokenUrl Ajax end point to retrieve client token
     */
    initialize: function (code, clientToken, threeDSecure, hostedFields, billingName, billingPostcode, quoteUrl, tokenizeUrl, clientTokenUrl) {
        this.code = code;
        this.clientToken = clientToken || false;
        this.clientTokenUrl = clientTokenUrl;
        this.threeDSecure = threeDSecure;
        this.hostedFields = hostedFields; /* deprecated, hosted fields is the only option */

        if (billingName) {
            this.billingName = billingName;
        }
        if (billingPostcode) {
            this.billingPostcode = billingPostcode;
        }
        this.billingCountryId = false;
        if (quoteUrl) {
            this.quoteUrl = quoteUrl;
        }
        if (tokenizeUrl) {
            this.tokenizeUrl = tokenizeUrl;
        }

        this._hostedFieldsTokenGenerated = false;

        this.acceptedCards = false;

        // Store whether hosted fields is running or not
        this._hostedFieldsTimeout = false;

        // Store the Ajax request for the updateData
        this._updateDataCallbacks = [];
        this._updateDataTimeout = null;

        this.client = false;

        this.threeDSpecificCountries = false;
        this.threeDCountries = [];
        this.threeDSecureFailedAction = 0;

        this.supportedCards = [];
        this.cardType = false;

        this.initEvents();
    },

    /**
     * Create our events object, with the various events we support
     */
    initEvents: function () {
        this.events = {
            onBeforeUpdateData: [],
            onAfterUpdateData: [],
            onHandleAjaxRequest: [],
            integration: {
                onInit: [],
                onInitDefaultMethod: [],
                onInitSavedMethods: [],
                onShowHideOtherMethod: [],
                onCheckSavedOther: [],
                onPaymentMethodSwitch: [],
                onReviewInit: [],
                onBeforeSubmit: [],
                onAfterSubmit: [],
                onObserveAjaxRequests: []
            }
        };
    },

    /**
     * Set the Kount data for the data collector
     *
     * @param environment
     * @param kountId
     */
    setKount: function (environment, kountId) {
        this.kountEnvironment = environment;
        if (kountId != '') {
            this.kountId = kountId;
        }
    },

    /**
     * Set the supported card types
     *
     * @param cardTypes
     */
    setSupportedCards: function (cardTypes) {
        if (typeof cardTypes === 'string') {
            cardTypes = cardTypes.split(',');
        }
        this.supportedCards = cardTypes;
    },

    /**
     * Set the 3D secure specific countries
     *
     * @param countries
     */
    setThreeDCountries: function (countries) {
        if (typeof countries === 'string') {
            countries = countries.split(',');
        }
        this.threeDSpecificCountries = true;
        this.threeDCountries = countries;
    },

    /**
     * Set the action to occur when a 3Ds transactions liability doesn't shift
     *
     * @param action
     */
    setThreeDFailedAction: function (action) {
        this.threeDSecureFailedAction = action;
    },

    /**
     * Add an event into the system
     *
     * @param paths
     * @param eventFn
     * @param params
     */
    observeEvent: function (paths, eventFn, params) {
        if (!Array.isArray(paths)) {
            paths = [paths];
        }

        // Handle multiple paths
        paths.each(function (path) {
            var event = this._resolveEvent(path);
            if (event === undefined) {
                console.warn('Event for ' + path + ' does not exist.');
            } else {
                event.push({fn: eventFn, params: params});
            }
        }.bind(this));
    },

    /**
     * Fire an event
     *
     * @param caller
     * @param path
     * @param params
     */
    fireEvent: function (caller, path, params) {
        var events = this._resolveEvent(path);
        if (events !== undefined) {
            if (events.length > 0) {
                events.each(function (event) {
                    if (typeof event.fn === 'function') {
                        var arguments = [params];
                        if (typeof event.params === 'object') {
                            arguments.push(event.params);
                        }
                        event.fn.apply(caller, arguments);
                    }
                });
            }
        }
    },

    /**
     * Resolve an event by a path
     *
     * @param path
     * @returns {*}
     * @private
     */
    _resolveEvent: function (path) {
        return path.split('.').reduce(function(prev, curr) {
            return prev ? prev[curr] : undefined
        }, this.events)
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
     * Init the hosted fields system
     *
     * @param integration
     */
    initHostedFields: function (integration) {

        // If the hosted field number element exists hosted fields is on the page and working!
        if ($$('iframe[name^="braintree-"]').length > 0) {
            return false;
        }

        // If it's already running there's no need to start another instance
        // Also block the function if braintree-hosted-submit isn't yet on the page
        if ($('braintree-hosted-submit') === null) {
            return false;
        }

        // Pass the integration through to hosted fields
        this.integration = integration;

        this._hostedFieldsTokenGenerated = false;

        // Utilise a 50ms timeout to ensure the last call of HF is ran
        clearTimeout(this._hostedFieldsTimeout);
        this._hostedFieldsTimeout = setTimeout(function () {

            if (this._hostedIntegration !== false) {
                try {
                    this._hostedIntegration.teardown(function () {
                        this._hostedIntegration = false;
                        // Setup the hosted fields client
                        this.setupHostedFieldsClient();
                    }.bind(this));
                } catch (e) {
                    this.setupHostedFieldsClient();
                }
            } else {
                // Setup the hosted fields client
                this.setupHostedFieldsClient();
            }

        }.bind(this), 50);
    },

    /**
     * Tear down hosted fields
     *
     * @param callbackFn
     */
    teardownHostedFields: function (callbackFn) {
        if (typeof this._hostedIntegration !== 'undefined' && this._hostedIntegration !== false) {
            this._hostedIntegration.teardown(function () {
                this._hostedIntegration = false;

                if (typeof callbackFn === 'function') {
                    callbackFn();
                }
            }.bind(this));
        } else {
            if (typeof callbackFn === 'function') {
                callbackFn();
            }
        }
    },

    /**
     * Setup the hosted fields client utilising the Braintree JS SDK
     */
    setupHostedFieldsClient: function () {

        // If there are iframes within the fields already, don't run again!
        // This function has a delay from the original call so we need to verify everything is still good to go!
        if ($$('iframe[name^="braintree-"]').length > 0) {
            return false;
        }

        this._hostedIntegration = false;

        this.checkSubmitAfterPayment();

        // Retrieve the client from the class
        this.getClient(function (clientInstance) {
            // Build our hosted fields options
            var options = {
                client: clientInstance,
                styles: this.getHostedFieldsStyles(),
                fields: {
                    number: {
                        selector: "#card-number",
                        placeholder: "0000 0000 0000 0000"
                    },
                    expirationMonth: {
                        selector: "#expiration-month",
                        placeholder: "MM"
                    },
                    expirationYear: {
                        selector: "#expiration-year",
                        placeholder: "YY"
                    }
                }
            };

            // Include the CVV field with the request
            if ($('cvv') !== null) {
                options.fields.cvv = {
                    selector: "#cvv"
                };
            }

            // Create a new instance of hosted fields
            braintree.hostedFields.create(options, function (hostedFieldsErr, hostedFieldsInstance) {
                // Handle hosted fields errors
                if (hostedFieldsErr) {
                    // Duplicate IFRAME error can occur with certain checkouts implementations
                    if (hostedFieldsErr.code == 'HOSTED_FIELDS_FIELD_DUPLICATE_IFRAME') {
                        return;
                    }

                    // Handle error in Hosted Fields creation
                    console.error(hostedFieldsErr);
                    return;
                }

                // Run on ready function
                return this.hostedFieldsOnReady(hostedFieldsInstance);
            }.bind(this));
        }.bind(this));
    },

    /**
     * Called when Hosted Fields integration is ready
     *
     * @param integration
     */
    hostedFieldsOnReady: function (integration) {
        this._hostedIntegration = integration;

        // Unset the loading state if it's present
        if ($$('#credit-card-form.loading').length) {
            $$('#credit-card-form.loading').first().removeClassName('loading');
        }

        this.checkSubmitAfterPayment();

        // Handle card type changes
        integration.on('cardTypeChange', this.hostedFieldsCardTypeChange.bind(this));
    },

    /**
     * Check if the submit after payment should be present on the page
     */
    checkSubmitAfterPayment: function () {
        // Will this checkout submit the payment after the "payment" step. This is typically used in non one step checkouts
        // which contains a review step.
        if (this.integration.submitAfterPayment) {
            if ($('braintree-submit-after-payment') == null) {
                var input = new Element('input', {type: 'hidden', name: 'payment[submit_after_payment]', value: 1, id: 'braintree-submit-after-payment'});
                $('payment_form_gene_braintree_creditcard').insert(input);
            }
        } else {
            if ($('braintree-submit-after-payment')) {
                $('braintree-submit-after-payment').remove();
            }
        }
    },

    /**
     * Return the hosted field styles
     * See: https://developers.braintreepayments.com/guides/hosted-fields/styling/javascript/v2
     *
     * @returns {*}
     */
    getHostedFieldsStyles: function () {

        // Does the integration provide it's own styling options for hosted fields?
        if (typeof this.integration.getHostedFieldsStyles === 'function') {
            return this.integration.getHostedFieldsStyles();
        }

        // Return some default styles if all else fails
        return {
            // Style all elements
            "input": {
                "font-size": "14pt",
                "color": "#3A3A3A"
            },

            // Styling element state
            ":focus": {
                "color": "black"
            },
            ".valid": {
                "color": "green"
            },
            ".invalid": {
                "color": "red"
            }
        };
    },

    /**
     * Update the card type on field event
     *
     * @param event
     */
    hostedFieldsCardTypeChange: function (event) {
        if (typeof event.cards !== 'undefined') {
            var cardMapping = {
                'visa': 'VI',
                'american-express': 'AE',
                'master-card': 'MC',
                'discover': 'DI',
                'jcb': 'JCB',
                'maestro': 'ME'
            };
            if (typeof cardMapping[event.cards[0].type] !== undefined) {
                this.cardType = cardMapping[event.cards[0].type];
                this.updateCardType(false, this.cardType);

                // Detect whether or not the card is supported
                if (this.supportedCards.indexOf(this.cardType) == -1) {
                    this.showCardUnsupported();
                } else {
                    this.removeCardUnsupported();
                }
            } else {
                this.removeCardUnsupported();
                this.cardType = false;
                this.updateCardType(false, 'card');
            }
        }
    },

    /**
     * Show the card unsupported message by the card field
     */
    showCardUnsupported: function () {
        if ($$('.braintree-card-input-field').length > 0) {
            var parentElement = $$('.braintree-card-input-field').first().up();
            if (parentElement.select('.braintree-card-unsupported').length == 0) {
                var error = new Element('div', {class: 'braintree-card-unsupported'}).update(
                    Translator.translate('We\'re currently unable to process this card type, please try another card or payment method.')
                );
                parentElement.insert(error);
            }
        }
    },

    /**
     * Remove the card unsupported message
     */
    removeCardUnsupported: function () {
        if ($$('.braintree-card-unsupported').length > 0) {
            $$('.braintree-card-unsupported').each(function (ele) {
                ele.remove();
            })
        }
    },

    /**
     * Retrieve the billing country ID
     *
     * @returns {*}
     */
    getBillingCountryId: function () {
        if ($('billing-address-select') == null || $('billing-address-select').value == '') {
            var billing = this.getBillingAddress();
            if (typeof billing['billing[country_id]'] !== 'undefined') {
                return billing['billing[country_id]'];
            }
        }

        if (this.billingCountryId) {
            return this.billingCountryId;
        }

        return false;
    },

    /**
     * Should we invoke the 3Ds flow
     *
     * @returns {*}
     */
    shouldInvokeThreeDSecure: function () {
        // Are we invoking 3D secure for specific countries only?
        if (this.threeDSpecificCountries && this.threeDCountries.length > 0) {
            var countryId;
            if (countryId = this.getBillingCountryId()) {
                return this.threeDCountries.indexOf(countryId) !== -1;
            }
        }

        return this.threeDSecure;
    },

    /**
     * Once the nonce has been received update the field
     *
     * @param nonce
     * @param options
     */
    hostedFieldsNonceReceived: function (payload, options) {

        if (this.shouldInvokeThreeDSecure()) {
            // Show the loading state
            if (typeof this.integration.setLoading === 'function') {
                this.integration.setLoading();
            }


            // Verify the nonce through 3Ds
            this.verify3dSecureNonce(payload.nonce, {
                onSuccess: function (response) {
                    this.updateNonce(response.nonce);

                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess();
                    }
                }.bind(this),
                onFailure: function () {
                    if (typeof options.onFailure === 'function') {
                        options.onFailure();
                    }
                }.bind(this)
            });
        } else {
            this.updateNonce(payload.nonce);

            if (typeof options.onSuccess === 'function') {
                options.onSuccess();
            }
        }
    },

    /**
     * Update the nonce in the form
     *
     * @param nonce
     */
    updateNonce: function (nonce) {
        $('creditcard-payment-nonce').value = nonce;
        $('creditcard-payment-nonce').setAttribute('value', nonce);

        if (typeof this.integration.resetLoading === 'function') {
            this.integration.resetLoading();
        }

        this._hostedFieldsTokenGenerated = true;
    },

    /**
     * Handle hosted fields throwing an error
     *
     * @param response
     * @returns {boolean}
     */
    hostedFieldsError: function (response) {

        if (typeof this.integration.resetLoading === 'function') {
            this.integration.resetLoading();
        }

        // Stop any "Cannot place two elements in #xxx" messages being shown to the user
        // These are non critical errors and the functionality will still work as expected
        if (
            typeof response.message !== 'undefined' &&
            response.message.indexOf('Cannot place two elements in') == -1 &&
            response.message.indexOf('Unable to find element with selector') == -1 &&
            response.message.indexOf('User did not enter a payment method') == -1
        ) {
            // Let the user know what went wrong
            alert(response.message);
        }

        this._hostedFieldsTokenGenerated = false;

        if (typeof this.integration.afterHostedFieldsError === 'function') {
            this.integration.afterHostedFieldsError(response.message);
        }

        return false;

    },

    /**
     * Is the customer attempting to use a saved card?
     *
     * @returns {boolean}
     */
    usingSavedCard: function () {
        return ($('creditcard-saved-accounts') != undefined
            && $$('#creditcard-saved-accounts input:checked[type=radio]').first() != undefined
            && $$('#creditcard-saved-accounts input:checked[type=radio]').first().value !== 'other');
    },

    /**
     * Detect a saved card with 3Ds enabled
     * @returns {*|boolean}
     */
    usingSavedThreeDCard: function () {
        return this.usingSavedCard() && $$('#creditcard-saved-accounts input:checked[type=radio]').first().hasAttribute('data-threedsecure-nonce');
    },

    /**
     * Set the 3Ds flag
     *
     * @param flag a boolean value
     */
    setThreeDSecure: function (flag) {
        this.threeDSecure = flag;
    },

    /**
     * Set the amount within the checkout, this is only used in the default integration
     * For any other checkouts see the updateData method, this is used by 3D secure
     *
     * @param amount The grand total of the order
     */
    setAmount: function (amount) {
        this.amount = parseFloat(amount);
    },

    /**
     * We sometimes need to set the billing name later on in the process
     *
     * @param billingName
     */
    setBillingName: function (billingName) {
        this.billingName = billingName;
    },

    /**
     * Return the billing name
     *
     * @returns {*}
     */
    getBillingName: function () {

        // If billingName is an object we're wanting to grab the data from elements
        if (typeof this.billingName == 'object') {

            // Combine them with a space
            return this.combineElementsValues(this.billingName);

        } else {

            // Otherwise we can presume that the billing name is a string
            return this.billingName;
        }
    },

    /**
     * Same for billing postcode
     *
     * @param billingPostcode
     */
    setBillingPostcode: function (billingPostcode) {
        this.billingPostcode = billingPostcode;
    },

    /**
     * Return the billing post code
     *
     * @returns {*}
     */
    getBillingPostcode: function () {

        if (typeof this.billingPostcode == 'string') {
            return this.billingPostcode;
        } else if (typeof this.billingPostcode == 'object') {
            // If billingName is an object we're wanting to grab the data from elements

            // Combine them with a space
            return this.combineElementsValues(this.billingPostcode);
        } else {
            var billing = this.getBillingAddress();
            if (typeof billing['billing[postcode]'] !== 'undefined') {
                return billing['billing[postcode]'];
            }

            return null;
        }
    },

    /**
     * Push through the selected accepted cards from the admin
     *
     * @param cards an array of accepted cards
     */
    setAcceptedCards: function (cards) {
        this.acceptedCards = cards;
    },

    /**
     * Return the full billing address, if we cannot just serialize the billing address serialize everything
     *
     * @returns {array}
     */
    getBillingAddress: function () {

        // Is there a function in the integration for this action?
        if (typeof this.integration.getBillingAddress === 'function') {
            return this.integration.getBillingAddress();
        }

        var billingAddress = {};

        // If not try something generic
        if ($('co-billing-form') !== null) {
            if ($('co-billing-form').tagName == 'FORM') {
                billingAddress = $('co-billing-form').serialize(true);
            } else {
                billingAddress = this.extractBilling($('co-billing-form').up('form').serialize(true));
            }
        } else if ($('billing:firstname') !== null) {
            billingAddress = this.extractBilling($('billing:firstname').up('form').serialize(true));
        }

        if (billingAddress) {
            return billingAddress;
        }
    },

    /**
     * Extract only the serialized values that start with "billing"
     *
     * @param formData
     * @returns {{}}
     */
    extractBilling: function (formData) {
        var billing = {};
        $H(formData).each(function (data) {
            // Only include billing details, excluding passwords
            if (data.key.indexOf('billing') == 0 && data.key.indexOf('password') == -1) {
                billing[data.key] = data.value;
            }
        });
        return billing;
    },

    getShippingAddress: function () {
        // Is there a function in the integration for this action?
        if (typeof this.integration.getShippingAddress === 'function') {
            return this.integration.getShippingAddress();
        }

        var shippingAddress = {};

        // If not try something generic
        if ($('co-shipping-form') !== null) {
            if ($('co-shipping-form').tagName == 'FORM') {
                shippingAddress = $('co-shipping-form').serialize(true);
            } else {
                shippingAddress = this.extractShipping($('co-shipping-form').up('form').serialize(true));
            }
        } else if ($('shipping:firstname') !== null) {
            shippingAddress = this.extractShipping($('shipping:firstname').up('form').serialize(true));
        }

        if (shippingAddress) {
            return shippingAddress;
        }
    },

    extractShipping: function (formData) {
        var shipping = {};
        $H(formData).each(function (data) {
            // Only include billing details, excluding passwords
            if (data.key.indexOf('shipping') === 0 && data.key.indexOf('password') === -1) {
                shipping[data.key] = data.value;
            }
        });
        return shipping;
    },

    /**
     * Return the accepted cards
     *
     * @returns {boolean|*}
     */
    getAcceptedCards: function () {
        return this.acceptedCards;
    },


    /**
     * Combine elements values into a string
     *
     * @param elements
     * @param seperator
     * @returns {string}
     */
    combineElementsValues: function (elements, seperator) {

        // If no seperator is set use a space
        if (!seperator) {
            seperator = ' ';
        }

        // Loop through the elements and build up an array
        var response = [];
        elements.each(function (element, index) {
            if ($(element) !== undefined) {
                response[index] = $(element).value;
            }
        });

        // Join with a space
        return response.join(seperator);

    },

    /**
     * Update the card type from a card number
     *
     * @param cardNumber The card number that the user has entered
     * @param cardType The card type, if already known
     */
    updateCardType: function (cardNumber, cardType) {

        // Check the image exists on the page
        if ($('card-type-image') != undefined) {

            // Grab the skin image URL without the last part
            var skinImageUrl = $('card-type-image').src.substring(0, $('card-type-image').src.lastIndexOf("/"));

            // Rebuild the URL with the card type included, all card types are stored as PNG's
            $('card-type-image').setAttribute('src', skinImageUrl + "/" + cardType + ".png");

        }

    },

    /**
     * Observe all Ajax requests, this is needed on certain checkouts
     * where we're unable to easily inject into methods
     *
     * @param callback A defined callback function if needed
     * @param ignore An array of indexOf paths to ignore
     */
    observeAjaxRequests: function (callback, ignore) {

        // Only allow one initialization of this function
        if (vZero.prototype.observingAjaxRequests) {
            return false;
        }
        vZero.prototype.observingAjaxRequests = true;

        // For every ajax request on complete update various Braintree things
        Ajax.Responders.register({
            onComplete: function (transport) {
                return this.handleAjaxRequest(transport.url, callback, ignore);
            }.bind(this)
        });

        // Is jQuery present on the page
        if (window.jQuery) {
            jQuery(document).ajaxComplete(function (event, xhr, settings) {
                return this.handleAjaxRequest(settings.url, callback, ignore)
            }.bind(this));
        }

    },

    /**
     * Handle the ajax request form the observer above
     *
     * @param url
     * @param callback
     * @param ignore
     * @returns {boolean}
     */
    handleAjaxRequest: function (url, callback, ignore) {

        // Let's check the ignore variable
        if (typeof ignore !== 'undefined' && ignore instanceof Array && ignore.length > 0) {

            // Determine whether we should ignore this request?
            var shouldIgnore = false;
            ignore.each(function (element) {
                if (url && url.indexOf(element) != -1) {
                    shouldIgnore = true;
                }
            });

            // If so, stop here!
            if (shouldIgnore === true) {
                return false;
            }
        }

        // Check the transport object has a URL and that it wasn't to our own controller
        if (url && url.indexOf('/braintree/') == -1) {

            this.fireEvent(this, 'onHandleAjaxRequest', {url: url});

            // Some checkout implementations may require custom callbacks
            if (callback) {
                callback(url);
            } else {
                this.updateData();
            }
        }

    },

    /**
     * Make an Ajax request to the server and request up to date information regarding the quote
     *
     * @param callback A defined callback function if needed
     * @param params any extra data to be passed to the controller
     */
    updateData: function (callback, params) {
        this._updateDataCallbacks.push(callback);

        clearTimeout(this._updateDataTimeout);
        this._updateDataTimeout = setTimeout(function () {
            var callbacks = this._updateDataCallbacks;
            this._updateDataCallbacks = [];

            this.fireEvent(this, 'onBeforeUpdateData', {params: params});

            // Make a new ajax request to the server
            new Ajax.Request(
                this.quoteUrl,
                {
                    method: 'post',
                    parameters: params,
                    onSuccess: function (transport) {
                        // Verify we have some response text
                        if (transport && (transport.responseJSON || transport.responseText)) {

                            // Parse the response from the server
                            var response = this._parseTransportAsJson(transport);

                            if (response.billingName != undefined) {
                                this.billingName = response.billingName;
                            }
                            if (response.billingPostcode != undefined) {
                                this.billingPostcode = response.billingPostcode;
                            }
                            if (response.billingCountryId != undefined) {
                                this.billingCountryId = response.billingCountryId;
                            }
                            if (response.grandTotal != undefined) {
                                this.amount = response.grandTotal;
                            }
                            if (response.threeDSecure != undefined) {
                                this.setThreeDSecure(response.threeDSecure);
                            }

                            // If PayPal is active update it
                            if (typeof vzeroPaypal != "undefined") {
                                // Update the totals within the PayPal system
                                if (response.grandTotal != undefined && response.currencyCode != undefined) {
                                    vzeroPaypal.setPricing(response.grandTotal, response.currencyCode);
                                }
                            }

                            // Run any callbacks that have been stored
                            if (callbacks.length > 0) {
                                callbacks.each(function (callback) {
                                    callback(response);
                                }.bind(this));
                            }

                            this.fireEvent(this, 'onAfterUpdateData', {response: response});
                        }
                    }.bind(this),
                    onFailure: function () {

                        // Update Data failed

                    }.bind(this)
                }
            );
        }.bind(this), 250);
    },

    /**
     * If the user attempts to use a 3D secure vaulted card and then cancels the 3D
     * window the nonce associated with that card will become invalid, due to this
     * we have to tokenize all the 3D secure cards again
     *
     * @param callback A defined callback function if needed
     */
    tokenize3dSavedCards: function (callback) {

        // Check 3D is enabled
        if (this.threeDSecure) {

            // Verify we have elements with data-token
            if ($$('[data-token]').first() !== undefined) {

                // Gather our tokens
                var tokens = [];
                $$('[data-token]').each(function (element, index) {
                    tokens[index] = element.getAttribute('data-token');
                });

                // Make a new ajax request to the server
                new Ajax.Request(
                    this.tokenizeUrl,
                    {
                        method: 'post',
                        onSuccess: function (transport) {

                            // Verify we have some response text
                            if (transport && (transport.responseJSON || transport.responseText)) {

                                // Parse as an object
                                var response = this._parseTransportAsJson(transport);

                                // Check the response was successful
                                if (response.success) {

                                    // Loop through the returned tokens
                                    $H(response.tokens).each(function (element) {

                                        // If the token exists update it's nonce
                                        if ($$('[data-token="' + element.key + '"]').first() != undefined) {
                                            $$('[data-token="' + element.key + '"]').first().setAttribute('data-threedsecure-nonce', element.value);
                                        }
                                    });
                                }

                                if (callback) {
                                    callback(response);
                                }
                            }
                        }.bind(this),
                        parameters: {'tokens': Object.toJSON(tokens)}
                    }
                );
            } else {
                callback();
            }

        } else {
            callback();
        }
    },

    /**
     * Verify a nonce through 3ds
     *
     * @param nonce
     * @param options
     */
    verify3dSecureNonce: function (nonce, options) {

        // Create a new instance of the threeDSecure library
        this.getClient(function (clientInstance) {
            braintree.threeDSecure.create({
                version: 2,
                client: clientInstance
            }, function (threeDSecureError, threeDSecureInstance) {
                if (threeDSecureError) {
                    console.error(threeDSecureError);
                    return;
                }

                var billingAddressDetails = this.getBillingAddress();
                var shippingAddressDetails = this.getShippingAddress();

                var billingAddress = {
                    givenName: billingAddressDetails['billing[firstname]'],
                    surname: billingAddressDetails['billing[lastname]'],
                    phoneNumber: billingAddressDetails['billing[telephone]'] || '',
                    streetAddress: typeof billingAddressDetails['billing[street][]'] !== 'undefined' ? billingAddressDetails['billing[street][]'][0] : billingAddressDetails['billing[street][0]'],
                    extendedAddress: (typeof billingAddressDetails['billing[street][]'] !== 'undefined' ? billingAddressDetails['billing[street][]'][1] : billingAddressDetails['billing[street][1]']) || '',
                    locality: billingAddressDetails['billing[city]'],
                    region: billingAddressDetails['billing[region]'] || '',
                    postalCode: billingAddressDetails['billing[postcode]'],
                    countryCodeAlpha2: billingAddressDetails['billing[country_id]']
                };

                if (Object.keys(shippingAddressDetails).length > 0) {
                    var additionalInformation = {
                        shippingGivenName: shippingAddressDetails['shipping[firstname]'],
                        shippingSurname: shippingAddressDetails['shipping[lastname]'],
                        shippingPhone: shippingAddressDetails['shipping[telephone]'] || '',
                        shippingAddress: {
                            streetAddress: typeof shippingAddressDetails['shipping[street][]'] !== 'undefined' ? shippingAddressDetails['shipping[street][]'][0] : shippingAddressDetails['shipping[street][0]'],
                            extendedAddress: (typeof shippingAddressDetails['shipping[street][]'] !== 'undefined' ? shippingAddressDetails['shipping[street][]'][1] : shippingAddressDetails['shipping[street][1]']) || '',
                            locality: shippingAddressDetails['shipping[city]'],
                            region: shippingAddressDetails['shipping[region]'] || '',
                            postalCode: shippingAddressDetails['shipping[postcode]'],
                            countryCodeAlpha2: shippingAddressDetails['shipping[country_id]']
                        }
                    };
                }

                var verifyOptions = {
                    amount: this.amount,
                    nonce: nonce,
                    billingAddress: billingAddress,
                    additionalInformation: additionalInformation || null,
                    onLookupComplete: function (data, next) {
                        next();
                    },
                    addFrame: function (err, iframe) {
                        $$('#three-d-modal .bt-modal-body').first().insert(iframe);
                        $('three-d-modal').removeClassName('hidden');
                    },
                    removeFrame: function () {
                        $$('#three-d-modal .bt-modal-body iframe').first().remove();
                        $('three-d-modal').addClassName('hidden');
                    }.bind(this)
                };

                // Verify the card by it's nonce
                threeDSecureInstance.verifyCard(verifyOptions, function (verifyError, payload) {
                    if (!verifyError) {
                        if (payload.liabilityShifted) {
                            // Run any callback functions
                            if (options.onSuccess) {
                                options.onSuccess(payload);
                            }
                        } else {
                            // Allow the payment through if it's American Express
                            if (this.cardType === "AE") {
                                if (options.onSuccess) {
                                    options.onSuccess(payload);
                                }
                            }
                            // Block the payment
                            else if (this.threeDSecureFailedAction == 1) {
                                if (options.onFailure) {
                                    options.onFailure(
                                        payload,
                                        Translator.translate('Your payment has failed 3D secure verification, please try an alternate payment method.')
                                    );
                                }
                            } else {
                                // Otherwise let the server side handle this
                                if (options.onSuccess) {
                                    options.onSuccess(payload);
                                }
                            }
                        }
                    } else {
                        if (options.onFailure) {
                            options.onFailure(payload, verifyError);
                        }
                    }
                }.bind(this));
            }.bind(this));
        }.bind(this));

    },

    /**
     * Verify a card stored in the vault
     *
     * @param options Contains any callback functions which have been set
     */
    verify3dSecureVault: function (options) {

        // Get the payment nonce
        var paymentNonce = $$('#creditcard-saved-accounts input:checked[type=radio]').first().getAttribute('data-threedsecure-nonce');

        if (paymentNonce) {
            // Verify the nonce via 3d secure
            this.verify3dSecureNonce(paymentNonce, {
                onSuccess: function (response) {
                    // Store threeDSecure token and nonce in form
                    $('creditcard-payment-nonce').removeAttribute('disabled');
                    $('creditcard-payment-nonce').value = response.nonce;
                    $('creditcard-payment-nonce').setAttribute('value', response.nonce);

                    // Run any callback functions
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess();
                    }
                },
                onFailure: function (response, error) {
                    // Show the error
                    alert(error);

                    if (typeof options.onFailure === 'function') {
                        options.onFailure();
                    } else {
                        checkout.setLoadWaiting(false);
                    }
                }
            });
        } else {
            alert('No payment nonce present.');

            if (typeof options.onFailure === 'function') {
                options.onFailure();
            } else {
                checkout.setLoadWaiting(false);
            }
        }
    },

    /**
     * Process a standard card request
     *
     * @param options Contains any callback functions which have been set
     */
    processCard: function (options) {

        // Retrieve billing address postcode & pass to api (as of SDK 3.9.0)
        var postcode = this.getBillingPostcode(),
            opt = {};

        if (postcode) {
            opt = {
                billingAddress: {
                    postalCode: postcode
                }
            };
        }

        // Tokenize using hosted fields
        this._hostedIntegration.tokenize(opt, function (tokenizeErr, payload) {
            if (tokenizeErr) {

                if (typeof options.onFailure === 'function') {
                    options.onFailure();
                } else {
                    checkout.setLoadWaiting(false);
                }

                // Return the message to the user
                if (typeof tokenizeErr.message === 'string') {
                    alert(tokenizeErr.message);
                }
                return;
            }

            return this.hostedFieldsNonceReceived(payload, options);
        }.bind(this));
    },

    /**
     * Should our integrations intercept credit card payments based on the settings?
     *
     * @returns {boolean}
     */
    shouldInterceptCreditCard: function () {
        return (this.amount != '0.00');
    },

    /**
     * Should our integrations intercept PayPal payments based on the settings?
     *
     * @returns {boolean}
     */
    shouldInterceptPayPal: function () {
        return true;
    },

    /**
     * Wrapper function which defines which method should be called
     *
     * verify3dSecureVault - used for verifying any vaulted card when 3D secure is enabled
     * verify3dSecure - verify a normal card via 3D secure
     * processCard - verify a normal card
     *
     * If the customer has choosen a vaulted card and 3D is disabled no client side interaction is needed
     *
     * @param options Object containing onSuccess, onFailure functions
     */
    process: function (options) {
        options = options || {};

        // Run success action if the hosted field token is generated, or the user is using a saved card without 3Ds
        if (this._hostedFieldsTokenGenerated || (this.usingSavedCard() && !this.usingSavedThreeDCard())) {

            // No action required as we're using a saved card
            if (typeof options.onSuccess === 'function') {
                options.onSuccess()
            }

        } else if (this.usingSavedThreeDCard()) {

            // The user has selected a card stored via 3D secure
            return this.verify3dSecureVault(options);

        } else {

            // Otherwise process the card normally
            return this.processCard(options);

        }
    },

    /**
     * Called on Credit Card loading
     *
     * @returns {boolean}
     */
    creditCardLoaded: function () {
        return false;
    },

    /**
     * Called on PayPal loading
     *
     * @returns {boolean}
     */
    paypalLoaded: function () {
        return false;
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

// Avoid 'console' errors in browsers that lack a console.
(function () {
    var method;
    var noop = function () {
    };
    var methods = [
        'assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error',
        'exception', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log',
        'markTimeline', 'profile', 'profileEnd', 'table', 'time', 'timeEnd',
        'timeStamp', 'trace', 'warn'
    ];
    var length = methods.length;
    var console = (window.console = window.console || {});

    while (length--) {
        method = methods[length];

        // Only stub undefined methods.
        if (!console[method]) {
            console[method] = noop;
        }
    }
}());