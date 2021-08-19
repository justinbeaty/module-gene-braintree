/**
 * Magento Braintree class to bridge the v.zero JS SDK and Magento
 *
 * @class BraintreeExpressAbstract
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
var BraintreeExpressAbstract = Class.create();
BraintreeExpressAbstract.prototype = {

    /**
     * Initialize the Braintree Express abstract class
     *
     * @param clientToken Client token generated from server
     * @param storeFrontName The store name to show within the PayPal modal window
     * @param formKey
     * @param source
     * @param urls
     * @param config
     */
    initialize: function (clientToken, storeFrontName, formKey, source, urls, config) {
        this.clientToken = clientToken || false;
        this.storeFrontName = storeFrontName;
        this.formKey = formKey;
        this.source = source;
        this.urls = urls;
        this.config = config || {};

        this._init();
        this.insertDom();
    },

    /**
     * Private init function
     *
     * @returns {boolean}
     * @private
     */
    _init: function () {
        return false;
    },

    /**
     * Insert our elements into the DOM
     */
    insertDom: function () {
        if (!this.getModal()) {
            $$('body').first().insert('<div id="pp-express-overlay"></div>' +
                '<div id="pp-express-modal"></div>' +
                '<div id="pp-express-container"></div>'
            );
        }
    },

    /**
     * Get modal's overlay element
     *
     * @returns {Element}
     */
    getOverlay: function() {
        return document.getElementById('pp-express-overlay');
    },

    /**
     * Get the modal element
     *
     * @returns {Element}
     */
    getModal: function() {
        return document.getElementById('pp-express-modal');
    },

    /**
     * Hide the modal
     */
    hideModal: function() {
        this.getOverlay().style.display = 'none';
        this.getModal().style.display = 'none';

        this.getModal().innerHTML = '';
    },

    /**
     * Show the modal
     */
    showModal: function() {
        this.getModal().innerHTML = '';
        this.getModal().classList.add('loading');

        this.getOverlay().style.display = 'block';
        this.getModal().style.display = 'block';
    },

    /**
     * Init the modal
     */
    initModal: function (params) {
        if (typeof params.form_key === 'undefined') {
            params.form_key = this.formKey;
        }
        if (typeof params.source === 'undefined') {
            params.source = this.source;
        }
        this.showModal();

        /* Build the order */
        new Ajax.Request(this.urls.authUrl, {
            method: 'POST',
            parameters: params,

            onSuccess: function (data) {
                this.getModal().classList.remove('loading');
                this.getModal().innerHTML = data.responseText;
                this.prepareCoupon();
                this.ajaxHandler();
            }.bind(this),

            onFailure: function () {
                this.hideModal();
                alert(typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again.");
            }.bind(this)
        });
    },

    /**
     * Update the grand total display within the modal
     */
    updateShipping: function (method) {
        this._setLoading($('paypal-express-submit'));
        new Ajax.Request(this.urls.shippingSaveUrl, {
            method: 'POST',
            parameters: {
                'submit_shipping': true,
                'shipping_method': method
            },

            onSuccess: function (data) {
                var response = this._getJson(data);
                this._unsetLoading($('paypal-express-submit'));
                this._updateTotals(response);
            }.bind(this),

            onFailure: function () {
                this._unsetLoading($('paypal-express-submit'));
                api.hideModal();
                alert( typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again." );
            }
        });
    },

    /**
     * Prepare the coupon form by handling users hitting enter
     */
    prepareCoupon: function () {
        if ($('paypal-express-coupon')) {
            $('paypal-express-coupon').observe('keypress', function (event) {
                var key = event.which || event.keyCode;
                if (key == Event.KEY_RETURN) {
                    Event.stop(event);
                    this.updateCoupon();
                }
            }.bind(this));
        }
    },

    /**
     * Allow customers to add coupons into their basket
     *
     * @param coupon
     */
    updateCoupon: function (coupon) {
        $('paypal-express-coupon-error').hide();
        if (!coupon && $('paypal-express-coupon')) {
            coupon = $('paypal-express-coupon').value;
        }

        // Only update if the coupon is set to something
        if (coupon == '') {
            return false;
        }

        this._setLoading($('paypal-express-coupon-apply'));
        new Ajax.Request(this.urls.couponSaveUrl, {
            method: 'POST',
            parameters: {
                'coupon': coupon
            },

            onSuccess: function (data) {
                var response = this._getJson(data);
                this._unsetLoading($('paypal-express-coupon-apply'));
                this._updateTotals(response);
                if (response.success == true) {
                    $('paypal-express-coupon-remove').show();
                    $('paypal-express-coupon-apply').hide();
                } else if (response.message) {
                    $('paypal-express-coupon-error').update(response.message).show();
                }
            }.bind(this),

            onFailure: function () {
                this._unsetLoading($('paypal-express-coupon-submit'));
                api.hideModal();
                alert( typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again." );
            }
        });
        return false;
    },

    /**
     * Allow the user the ability to remove the coupon code from their quote
     */
    removeCoupon: function () {
        $('paypal-express-coupon-error').hide();
        this._setLoading($('paypal-express-coupon-remove'));
        new Ajax.Request(this.urls.couponSaveUrl, {
            method: 'POST',
            parameters: {
                'remove': true
            },

            onSuccess: function (data) {
                var response = this._getJson(data);
                this._unsetLoading($('paypal-express-coupon-remove'));
                this._updateTotals(response);
                if (response.success == true) {
                    $('paypal-express-coupon-remove').hide();
                    $('paypal-express-coupon-apply').show();
                    $('paypal-express-coupon').value = '';
                    $('paypal-express-coupon').focus();
                } else if (response.message) {
                    $('paypal-express-coupon-error').update(response.message).show();
                }
            }.bind(this),

            onFailure: function () {
                this._unsetLoading($('paypal-express-coupon-submit'));
                api.hideModal();
                alert( typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again." );
            }
        });
    },

    /**
     * Update the totals from the response
     *
     * @param response
     * @private
     */
    _updateTotals: function (response) {
        if (typeof response.totals !== 'undefined') {
            $('paypal-express-totals').update(response.totals);
        }
    },

    /**
     * Return the JSON from the request
     *
     * @param data
     * @returns {*}
     * @private
     */
    _getJson: function (data) {
        if (typeof data.responseJSON !== 'undefined') {
            return data.responseJSON;
        } else if (typeof data.responseText === 'string') {
            return data.responseText.evalJSON();
        }
    },

    /**
     * Set an element to a loading state
     *
     * @param element
     * @private
     */
    _setLoading: function (element) {
        if (!element) {
            return false;
        }
        element.setAttribute('disabled', 'disabled');
        element.addClassName('loading');
    },

    /**
     * Unset the loading state
     *
     * @param element
     * @private
     */
    _unsetLoading: function (element) {
        if (!element) {
            return false;
        }
        element.removeAttribute('disabled');
        element.removeClassName('loading');
    },

    /**
     * Ajax handler
     */
    ajaxHandler: function () {
        var form = document.getElementById('gene_braintree_paypal_express_pp');

        Element.observe(form, 'submit', function (e) {
            Event.stop(e);
            var formParams = $('gene_braintree_paypal_express_pp').serialize(true);
            this.getModal().classList.add('loading');
            this.getModal().innerHTML = '';

            new Ajax.Request(e.target.getAttribute('action'), {
                method: 'POST',
                parameters: formParams,

                onSuccess: function (data) {
                    if (data.responseText == 'complete') {
                        document.location = this.urls.successUrl;
                        return;
                    }

                    this.getModal().classList.remove('loading');
                    this.getModal().innerHTML = data.responseText;
                    this.ajaxHandler();
                }.bind(this),

                onFailure: function () {
                    this.hideModal();
                    alert(typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again.");
                }.bind(this)
            });

            return false;
        }.bind(this));
    },

    /**
     * Validate any present forms on the page
     *
     * @returns {boolean}
     */
    validateForm: function () {
        // Validate the product add to cart form
        if (typeof productAddToCartForm === 'object' && productAddToCartForm.validator.validate()) {
            if (typeof productAddToCartFormOld === 'object' && productAddToCartFormOld.validator.validate()) {
                return true;
            } else if (typeof productAddToCartFormOld !== 'object') {
                return true;
            }
        }

        if (typeof productAddToCartForm !== 'object' && typeof productAddToCartFormOld !== 'object') {
            return true;
        } else {
            return false;
        }
    },

    /**
     * Attach the express instance to a number of buttons
     */
    attachToButtons: function (buttons) {
        console.warn('This method cannot be called directly.');
    }
};