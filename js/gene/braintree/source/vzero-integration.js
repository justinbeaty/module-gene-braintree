/**
 * The integration class for the Default checkout
 *
 * @class vZeroIntegration
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
var vZeroIntegration = Class.create();
vZeroIntegration.prototype = {

    /**
     * Device Data instance from braintree
     */
    dataCollectorInstance: null,

    /**
     * Create an instance of the integration
     *
     * @param vzero The vZero class that's being used by the checkout
     * @param vzeroPaypal The vZero PayPal object
     * @param paypalWrapperMarkUp The markup used to wrap the PayPal button
     * @param paypalButtonClass The class of the button we need to replace with the above mark up
     * @param isOnepage Is the integration a onepage checkout?
     * @param config Any further config the integration wants to push into the class
     * @param submitAfterPayment Is the checkout going to submit the actual payment after the payment step? For instance a checkout with a review step
     */
    initialize: function (vzero, vzeroPaypal, paypalWrapperMarkUp, paypalButtonClass, isOnepage, config, submitAfterPayment) {

        // Only allow the system to be initialized twice
        if (vZeroIntegration.prototype.loaded) {
            console.error('Your checkout is including the Braintree resources multiple times, please resolve this.');
            return false;
        }
        vZeroIntegration.prototype.loaded = true;

        this.vzero = vzero || false;
        this.vzeroPaypal = vzeroPaypal || false;

        // If both methods aren't present don't run the integration
        if (this.vzero === false && this.vzeroPaypal === false) {
            console.warn('The vzero and vzeroPaypal objects are not initiated.');
            return false;
        }

        this.paypalWrapperMarkUp = paypalWrapperMarkUp || false;
        this.paypalButtonClass = paypalButtonClass || false;
        this.submitButtonClass = this.paypalButtonClass; /* Used for other integrations */

        this.isOnepage = isOnepage || false;

        this.config = config || {};

        this.submitAfterPayment = submitAfterPayment || false;

        this._methodSwitchTimeout = false;

        this._originalSubmitFn = false;

        this.kountEnvironment = false;
        this.kountId = false;

        // Wait for the DOM to finish loading before creating observers
        document.observe("dom:loaded", function () {

            // Capture the original submit function
            if (this.captureOriginalSubmitFn()) {
                this.observeSubmissionOverride();
            }

            // Call the function which is going to intercept the submit event
            this.prepareSubmitObserver();
            this.preparePaymentMethodSwitchObserver();

        }.bind(this));

        // Has the hosted fields method been generated successfully?
        this.hostedFieldsGenerated = false;

        // On onepage checkouts we need to do some other magic
        if (this.isOnepage) {
            this.observeAjaxRequests();

            document.observe("dom:loaded", function () {
                this.initSavedPayPal();
                this.initDefaultMethod();

                if ($('braintree-hosted-submit') !== null) {
                    this.initHostedFields();
                }
            }.bind(this));
        }

        document.observe("dom:loaded", function () {
            // Saved methods need events to!
            this.initSavedMethods();

            if ($('braintree-hosted-submit') !== null) {
                this.initHostedFields();
            }
        }.bind(this));

        // Initialize device data events
        this._deviceDataInit = false;
        this.vzero.observeEvent([
            'onHandleAjaxRequest',
            'integration.onInitSavedMethods'
        ], this.initDeviceData, this);
        this.vzero.observeEvent('integration.onBeforeSubmit', function () {
            if ($('braintree-device-data') != null) {
                $('braintree-device-data').writeAttribute('disabled', false);
            }
        }, this);

        // Fire our onInit event
        this.vzero.fireEvent(this, 'integration.onInit', {integration: this});
    },

    /**
     * Add device_data into the session
     */
    initDeviceData: function (params, self) {
        if ($('credit-card-form') != null) {
            var form = $('credit-card-form').up('form');
            if (form != undefined) {
                if (form.select('#braintree-device-data').length == 0) {
                    if (self._deviceDataInit === true) {
                        return false;
                    }
                    self._deviceDataInit = true;

                    // Create a new element and insert it into the DOM
                    var input = new Element('input', {
                        type: 'hidden',
                        name: 'payment[device_data]',
                        id: 'braintree-device-data'
                    });
                    form.insert(input);

                    // Populate the new input with the device data
                    self.populateDeviceData(input);
                }
            }
        }
    },

    /**
     * Populate device data using the data collector
     *
     * @param input
     */
    populateDeviceData: function (input) {
        // Teardown if device data is already generated
        if (this.dataCollectorInstance !== null) {
            this.dataCollectorInstance.teardown(function () {
                this.dataCollectorInstance = null;
                return this.populateDeviceData(input);
            }.bind(this));
            return;
        }

        this.vzero.getClient(function (clientInstance) {
            var params = {
                client: clientInstance,
                kount: true
            };

            // Should we generate device data for PayPal?
            if (this.vzeroPaypal !== false) {
                params.paypal = true;
            }

            braintree.dataCollector.create(params, function (err, dataCollectorInstance) {
                if (err) {
                    // We don't want to console warn if the merchant isn't setup to accept Kount
                    if (err.code != 'DATA_COLLECTOR_KOUNT_NOT_ENABLED' &&
                        err.code != 'DATA_COLLECTOR_PAYPAL_NOT_ENABLED'
                    ) {
                        // Handle error in creation of data collector
                        console.error(err);
                    } else {
                        // Warn the user of the issue, but it's not important
                        console.warn('A warning occurred whilst initialisation the Braintree data collector. This warning can be safely ignored.');
                        console.warn(err);
                    }
                    return;
                }

                this.dataCollectorInstance = dataCollectorInstance;

                input.value = dataCollectorInstance.deviceData;
                input.writeAttribute('disabled', false);
                this._deviceDataInit = false;
            }.bind(this));
        }.bind(this));
    },

    /**
     * Init the saved method events
     */
    initSavedMethods: function () {

        // Loop through each saved card being selected
        $$('#creditcard-saved-accounts input[type="radio"], #paypal-saved-accounts input[type="radio"]').each(function (element) {

            // Determine which method we're observing
            var parentElement = '';
            var targetElement = '';
            if (element.up('#creditcard-saved-accounts') !== undefined) {
                parentElement = '#creditcard-saved-accounts';
                targetElement = '#credit-card-form';
            } else if (element.up('#paypal-saved-accounts') !== undefined) {
                parentElement = '#paypal-saved-accounts';
                targetElement = '.paypal-info';
            }

            // Observe the elements changing
            $(element).stopObserving('change').observe('change', function (event) {
                return this.showHideOtherMethod(parentElement, targetElement);
            }.bind(this));

        }.bind(this));

        this.vzero.fireEvent(this, 'integration.onInitSavedMethods');
    },

    /**
     * Hide or show the "other" method for both PayPal & Credit Card
     *
     * @param parentElement
     * @param targetElement
     */
    showHideOtherMethod: function (parentElement, targetElement) {

        // Has the user selected other?
        if ($$(parentElement + ' input:checked[type=radio]').first() !== undefined && $$(parentElement + ' input:checked[type=radio]').first().value == 'other') {

            if ($$(targetElement).first() !== undefined) {

                // Show the credit card form
                $$(targetElement).first().show();

                // Enable the credit card form all the elements in the credit card form
                $$(targetElement + ' input, ' + targetElement + ' select').each(function (formElement) {
                    formElement.removeAttribute('disabled');
                });

            }

        } else if ($$(parentElement + ' input:checked[type=radio]').first() !== undefined) {

            if ($$(targetElement).first() !== undefined) {

                // Hide the new credit card form
                $$(targetElement).first().hide();

                // Disable all the elements in the credit card form
                $$(targetElement + ' input, ' + targetElement + ' select').each(function (formElement) {
                    formElement.setAttribute('disabled', 'disabled');
                });

            }

        }

        this.vzero.fireEvent(this, 'integration.onShowHideOtherMethod', {
            parentElement: parentElement,
            targetElement: targetElement
        });
    },

    /**
     * Check to see if the "Other" option is selected and show the div correctly
     */
    checkSavedOther: function () {
        var parentElement = '';
        var targetElement = '';

        if (this.getPaymentMethod() == 'gene_braintree_creditcard') {
            parentElement = '#creditcard-saved-accounts';
            targetElement = '#credit-card-form';
        } else if (this.getPaymentMethod() == 'gene_braintree_paypal') {
            parentElement = '#paypal-saved-accounts';
            targetElement = '.paypal-info';
        }

        // Only run this action if the parent element exists on the page
        if ($$(parentElement).first() !== undefined) {
            this.showHideOtherMethod(parentElement, targetElement);
        }

        this.vzero.fireEvent(this, 'integration.onCheckSavedOther');
    },

    /**
     * After the payment methods have switched run this
     *
     * @returns {boolean}
     */
    afterPaymentMethodSwitch: function () {
        return true;
    },

    /**
     * Init hosted fields
     */
    initHostedFields: function () {

        // Only init hosted fields if it's enabled
        if (this.vzero.hostedFields) {

            // Verify the form is on the page
            if ($('braintree-hosted-submit') !== null) {

                // Verify this checkout has a form (would be weird to have a formless checkout, but you never know!)
                if ($('braintree-hosted-submit').up('form') !== undefined) {

                    // Store the form in the integration class
                    this.form = $('braintree-hosted-submit').up('form');

                    // Init hosted fields upon the form
                    this.vzero.initHostedFields(this);

                } else {
                    console.error('Hosted Fields cannot be initialized as we\'re unable to locate the parent form.');
                }
            }
        }
    },

    /**
     * Validate hosted fields is complete and error free
     *
     * @returns {boolean}
     */
    validateHostedFields: function () {
        if (!this.vzero.usingSavedCard() && this.vzero._hostedIntegration) {
            var state = this.vzero._hostedIntegration.getState(),
                errorMsgs = [],
                translate = {
                    'number': Translator.translate('Card Number'),
                    'expirationMonth': Translator.translate('Expiry Month'),
                    'expirationYear': Translator.translate('Expiry Year'),
                    'cvv': Translator.translate('CVV'),
                    'postalCode': Translator.translate('Postal Code')
                };

            // Loop through each field and ensure it's validity
            $H(state.fields).each(function (field) {
                if (field[1].isValid == false) {
                    errorMsgs.push(translate[field[0]] + ' ' + Translator.translate('is invalid.'));
                }
            }.bind(this));

            // If any errors are present, alert the user and stop the checkout process
            if (errorMsgs.length > 0) {
                alert(
                    Translator.translate('There are a number of errors present with the credit card form:') +
                    "\n" +
                    errorMsgs.join("\n")
                );
                return false;
            }

            // Validate the card type
            if (this.vzero.cardType && this.vzero.supportedCards) {
                // Detect whether or not the card is supported
                if (this.vzero.supportedCards.indexOf(this.vzero.cardType) == -1) {
                    alert(Translator.translate(
                        'We\'re currently unable to process this card type, please try another card or payment method.'
                    ));
                    return false;
                }
            }
        }

        return true;
    },

    /**
     * Init the default payment methods
     */
    initDefaultMethod: function () {
        if (this.shouldAddPayPalButton(false)) {
            this.setLoading();
            this.vzero.updateData(function () {
                this.resetLoading();
                this.updatePayPalButton('add');
            }.bind(this));
        }

        // Run the after payment method switch on init of the default method
        this.afterPaymentMethodSwitch();

        this.vzero.fireEvent(this, 'integration.onInitDefaultMethod');
    },

    /**
     * Observe any Ajax requests and refresh the PayPal button or update the checkouts data
     */
    observeAjaxRequests: function () {
        this.vzero.observeAjaxRequests(function () {
            this.vzero.updateData(function () {

                // The Ajax request might kill our events
                if (this.isOnepage) {
                    this.initSavedPayPal();
                    this.rebuildPayPalButton();
                    this.checkSavedOther();

                    // If hosted fields is enabled init the environment
                    if (this.vzero.hostedFields) {
                        this.initHostedFields();
                    }
                }

                // Make sure we're observing the saved methods correctly
                this.initSavedMethods();

                // Run the after payment method switch on init of the default method
                this.afterPaymentMethodSwitch();

                // Fire an event to capture any instances of the checkout updating it's DOM
                this.vzero.fireEvent(this, 'integration.onObserveAjaxRequests');

            }.bind(this));
        }.bind(this), (typeof this.config.ignoreAjax !== 'undefined' ? this.config.ignoreAjax : false))
    },

    /**
     * Rebuild the PayPal button if it's been removed
     */
    rebuildPayPalButton: function () {

        // Check to see if the DOM element has been removed?
        if ($('paypal-container') == null) {
            this.updatePayPalButton();
        }

    },

    /**
     * Handle saved PayPals being present on the page
     */
    initSavedPayPal: function () {

        // If we have any saved accounts we'll need to do something jammy
        if ($$('#paypal-saved-accounts input[type=radio]').first() !== undefined) {
            $('paypal-saved-accounts').on('change', 'input[type=radio]', function (event) {

                // Update the PayPal button accordingly
                this.updatePayPalButton(false, 'gene_braintree_paypal');

            }.bind(this));
        }

    },

    /**
     * Capture the original submit function
     *
     * @returns {boolean}
     */
    captureOriginalSubmitFn: function () {
        return false;
    },

    /**
     * Start an interval to ensure the submit function has been correctly overidden
     */
    observeSubmissionOverride: function () {
        setInterval(function () {
            if (this._originalSubmitFn) {
                this.prepareSubmitObserver();
            }
        }.bind(this), 500);
    },

    /**
     * Set the submit function to be used
     *
     * This should be overridden within each checkouts .phtml file
     * vZeroIntegration.prototype.prepareSubmitObserver = function() {}
     *
     * @returns {boolean}
     */
    prepareSubmitObserver: function () {
        return false;
    },

    /**
     * Event to run before submit
     * Should always return _beforeSubmit
     *
     * @returns {boolean}
     */
    beforeSubmit: function (callback) {
        return this._beforeSubmit(callback);
    },

    /**
     * Private before submit function
     *
     * @param callback
     * @private
     */
    _beforeSubmit: function (callback) {
        this.vzero.fireEvent(this, 'integration.onBeforeSubmit');

        // Remove the save after payment to ensure validation fires correctly
        if (this.submitAfterPayment && $('braintree-submit-after-payment')) {
            $('braintree-submit-after-payment').remove();
        }

        callback();
    },

    /**
     * Event to run after submit
     *
     * @returns {boolean}
     */
    afterSubmit: function () {
        this.vzero.fireEvent(this, 'integration.onAfterSubmit');
        return false;
    },

    /**
     * Submit the integration to tokenize the card
     *
     * @param type
     * @param successCallback
     * @param failedCallback
     * @param validateFailedCallback
     */
    submit: function (type, successCallback, failedCallback, validateFailedCallback) {

        // Set the token being generated back to false on a new submission
        this.vzero._hostedFieldsTokenGenerated = false;
        this.hostedFieldsGenerated = false;

        // Check we actually want to intercept this credit card transaction?
        if (this.shouldInterceptSubmit(type)) {

            // If the type is card, validate Hosted Fields
            if (type != 'creditcard' || (type == 'creditcard' && this.validateHostedFields())) {

                // Validate the form before submission
                if (this.validateAll()) {

                    // Show the loading information
                    this.setLoading();

                    // Call the before submit function
                    this.beforeSubmit(function () {

                        // Always attempt to update the card type on submission
                        if ($$('[data-genebraintree-name="number"]').first() != undefined) {
                            this.vzero.updateCardType($$('[data-genebraintree-name="number"]').first().value);
                        }

                        // Update the data within the vZero object
                        this.vzero.updateData(
                            function () {

                                // Update the billing details if they're present on the page
                                this.updateBilling();

                                // Process the data on the page
                                this.vzero.process({
                                    onSuccess: function () {

                                        // Make some modifications to the form
                                        this.enableDeviceData();

                                        // Unset the loading, as this can block success functions
                                        this.resetLoading();
                                        this.afterSubmit();

                                        // Enable/disable the correct nonce input fields
                                        this.enableDisableNonce();

                                        this.vzero._hostedFieldsTokenGenerated = true;
                                        this.hostedFieldsGenerated = true;

                                        // Call the callback function
                                        if (typeof successCallback === 'function') {
                                            var response = successCallback();
                                        }

                                        // Enable loading again, as things are happening!
                                        this.setLoading();

                                        return response;

                                    }.bind(this),
                                    onFailure: function () {

                                        this.vzero._hostedFieldsTokenGenerated = false;
                                        this.hostedFieldsGenerated = false;

                                        alert(Translator.translate(
                                            'We\'re unable to process your payment, please try another card or payment method.'
                                        ));

                                        this.resetLoading();
                                        this.afterSubmit();
                                        if (typeof failedCallback === 'function') {
                                            return failedCallback();
                                        }
                                    }.bind(this)
                                })
                            }.bind(this),
                            this.getUpdateDataParams()
                        );

                    }.bind(this));

                } else {

                    this.vzero._hostedFieldsTokenGenerated = false;
                    this.hostedFieldsGenerated = false;

                    this.resetLoading();
                    if (typeof validateFailedCallback === 'function') {
                        validateFailedCallback();
                    }
                }
            } else {
                this.resetLoading();
            }
        }
    },

    /**
     * Submit the entire checkout
     */
    submitCheckout: function () {
        // Submit the checkout steps
        window.review && review.save();
    },

    /**
     * How to submit the payment section
     */
    submitPayment: function () {
        payment.save && payment.save();
    },

    /**
     * Enable/disable the correct nonce input fields
     */
    enableDisableNonce: function () {
        // Make sure the nonce inputs aren't going to interfere
        if (this.getPaymentMethod() == 'gene_braintree_creditcard') {
            if ($('creditcard-payment-nonce') !== null) {
                $('creditcard-payment-nonce').removeAttribute('disabled');
            }
            if ($('paypal-payment-nonce') !== null) {
                $('paypal-payment-nonce').setAttribute('disabled', 'disabled');
            }
        } else if (this.getPaymentMethod() == 'gene_braintree_paypal') {
            if ($('creditcard-payment-nonce') !== null) {
                $('creditcard-payment-nonce').setAttribute('disabled', 'disabled');
            }
            if ($('paypal-payment-nonce') !== null) {
                $('paypal-payment-nonce').removeAttribute('disabled');
            }
        }
    },

    /**
     * Replace the PayPal button at the correct time
     *
     * This should be overridden within each checkouts .phtml file
     * vZeroIntegration.prototype.preparePaymentMethodSwitchObserver = function() {}
     */
    preparePaymentMethodSwitchObserver: function () {
        return this.defaultPaymentMethodSwitch();
    },

    /**
     * If the checkout uses the Magento standard Payment.prototype.switchMethod we can utilise this function
     */
    defaultPaymentMethodSwitch: function () {

        // Store a pointer to the vZero integration
        var vzeroIntegration = this;

        // Store the original payment method
        var paymentSwitchOriginal = Payment.prototype.switchMethod;

        // Intercept the save function
        Payment.prototype.switchMethod = function (method) {

            // Run our method switch function
            vzeroIntegration.paymentMethodSwitch(method);

            // Run the original function
            return paymentSwitchOriginal.apply(this, arguments);
        };

    },

    /**
     * Function to run when the customer changes payment method
     * @param method
     */
    paymentMethodSwitch: function (method) {

        // Wait for 50ms to see if this function is called again, only ever run the last instance
        clearTimeout(this._methodSwitchTimeout);
        this._methodSwitchTimeout = setTimeout(function () {

            // Should we add a PayPal button?
            if (this.shouldAddPayPalButton(method)) {
                this.updatePayPalButton('add', method);
            } else {
                this.updatePayPalButton('remove', method);
            }

            // Has the user enabled hosted fields?
            if ((method ? method : this.getPaymentMethod()) == 'gene_braintree_creditcard') {
                this.initHostedFields();
            }

            // Check to see if the other information should be displayed
            this.checkSavedOther();

            // Run the event once the payment method has switched
            this.afterPaymentMethodSwitch();

            this.vzero.fireEvent(this, 'integration.onPaymentMethodSwitch', {method: method});

        }.bind(this), 50);

    },

    /**
     * Complete a PayPal transaction
     *
     * @returns {boolean}
     */
    completePayPal: function (obj) {

        // Make sure the nonces are the correct way around
        this.enableDisableNonce();

        // Enable the device data
        this.enableDeviceData();

        if (obj.nonce && $('paypal-payment-nonce') !== null) {
            $('paypal-payment-nonce').value = obj.nonce;
            $('paypal-payment-nonce').setAttribute('value', obj.nonce);
        } else {
            console.warn('Unable to update PayPal nonce, please verify that the nonce input field has the ID: paypal-payment-nonce');
        }

        // Check the callback type is a function
        this.afterPayPalComplete();

        return false;
    },

    /**
     * Any operations that need to happen after the PayPal integration has completed
     *
     * @returns {boolean}
     */
    afterPayPalComplete: function () {
        this.resetLoading();
        return this.submitCheckout();
    },

    /**
     * Return the mark up for the PayPal button
     *
     * @returns {string}
     */
    getPayPalMarkUp: function () {
        return $('braintree-paypal-button').innerHTML;
    },

    /**
     * Update the PayPal button on the page
     *
     * @param action
     * @param method
     * @returns {boolean}
     */
    updatePayPalButton: function (action, method) {

        if (this.paypalWrapperMarkUp === false) {
            return false;
        }

        // Refresh is deprecated
        if (action == 'refresh') {
            return true;
        }

        // Check to see if we should be adding a PayPal button?
        if ((this.shouldAddPayPalButton(method) && action != 'remove') || action == 'add') {

            // Hide the checkout button
            if ($$(this.paypalButtonClass).first() !== undefined) {

                // Hide the original checkout button
                $$(this.paypalButtonClass).first().hide();

                // Does a button already exist on the page and is visible?
                if ($$('#paypal-complete').first() !== undefined) {
                    $$('#paypal-complete').first().show();
                    return true;
                }

                // Insert the wrapper mark up in prepation for adding the button
                $$(this.paypalButtonClass).first().insert({after: this.paypalWrapperMarkUp});

                // Add in the PayPal button
                var options = this.vzeroPaypal._buildOptions();
                options.events = {
                    validate: this.validateAll,
                    onAuthorize: this.completePayPal.bind(this),
                    onCancel: function() {},
                    onError: function(err) {
                        alert(typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again.");
                        console.error('Error while processing payment', err);
                    }
                };

                this.vzeroPaypal.addPayPalButton(options, '#paypal-container');
            } else {
                console.warn('We\'re unable to find the element ' + this.paypalButtonClass + '. Please check your integration.');
            }

        } else {

            // If not we need to remove it
            // Revert our madness
            if ($$(this.paypalButtonClass).first() !== undefined) {
                $$(this.paypalButtonClass).first().show();
            }

            // Remove the PayPal element
            if ($$('#paypal-complete').first() !== undefined) {
                $('paypal-complete').hide();
            }
        }

    },

    /**
     * When the review step is shown on non one step checkout solutions update the PayPal button
     */
    onReviewInit: function () {
        if (!this.isOnepage) {
            this.updatePayPalButton();
        }
        this.vzero.fireEvent(this, 'integration.onReviewInit');
    },

    /**
     * Attach a click event handler to the button to validate the form
     *
     * @param integration
     */
    paypalOnReady: function (integration) {
        return true;
    },

    /**
     * Set the loading state
     */
    setLoading: function () {
        checkout.setLoadWaiting('payment');
    },

    /**
     * Reset the loading state
     */
    resetLoading: function () {
        checkout.setLoadWaiting(false);
    },

    /**
     * Make sure the device data field isn't disabled
     */
    enableDeviceData: function () {
        if ($('device_data') !== null) {
            $('device_data').removeAttribute('disabled');
        }
    },

    /**
     * Update the billing of the vZero object
     *
     * @returns {boolean}
     */
    updateBilling: function () {

        // Verify we're not using a saved address
        if (($('billing-address-select') !== null && $('billing-address-select').value == '') || $('billing-address-select') === null) {

            // Grab these directly from the form and update
            if ($('billing:firstname') !== null && $('billing:lastname') !== null) {
                this.vzero.setBillingName($('billing:firstname').value + ' ' + $('billing:lastname').value);
            }
            if ($('billing:postcode') !== null) {
                this.vzero.setBillingPostcode($('billing:postcode').value);
            }
        }
    },

    /**
     * Any extra data we need to pass through to the updateData call
     *
     * @returns {{}}
     */
    getUpdateDataParams: function () {
        var parameters = {};

        // If the billing address is selected and we're wanting to ship to that address we need to pass the addressId
        if ($('billing-address-select') !== null && $('billing-address-select').value != '') {
            parameters.addressId = $('billing-address-select').value;
        }

        return parameters;
    },

    /**
     * Return the current payment method
     *
     * @returns {*}
     */
    getPaymentMethod: function () {
        return payment.currentMethod;
    },

    /**
     * Should we intercept the save action of the checkout
     *
     * @param type
     * @returns {*}
     */
    shouldInterceptSubmit: function (type) {
        switch (type) {
            case 'creditcard':
                return (this.getPaymentMethod() == 'gene_braintree_creditcard' && this.vzero.shouldInterceptCreditCard());
                break;
            case 'paypal':
                return (this.getPaymentMethod() == 'gene_braintree_paypal' && this.vzero.shouldInterceptCreditCard());
                break;
        }
        return false;
    },

    /**
     * Should we be adding a PayPal button?
     * @returns {boolean}
     */
    shouldAddPayPalButton: function (method) {
        return (((method ? method : this.getPaymentMethod()) == 'gene_braintree_paypal' && $('paypal-saved-accounts') === null) || ((method ? method : this.getPaymentMethod()) == 'gene_braintree_paypal' && ($$('#paypal-saved-accounts input:checked[type=radio]').first() !== undefined && $$('#paypal-saved-accounts input:checked[type=radio]').first().value == 'other')));
    },

    /**
     * Function to run once 3D retokenization is complete
     */
    threeDTokenizationComplete: function () {
        this.resetLoading();
    },

    /**
     * Validate the entire form
     *
     * @returns {boolean}
     */
    validateAll: function () {
        return true;
    },

    /**
     * @deprecated
     */
    disableCreditCardForm: function () {

    },

    /**
     * @deprecated
     */
    enableCreditCardForm: function () {

    }
};