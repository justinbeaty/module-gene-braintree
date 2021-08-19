var BraintreePayPalExpress = Class.create(BraintreeExpressAbstract, {
    vzeroPayPal: false,

    /**
     * Init the PayPal button class
     *
     * @private
     */
    _init: function () {
        this.vzeroPayPal = new vZeroPayPalButton(
            false,
            '',
            false, /* Vault flow forced as the final amount can change */
            this.config.locale,
            false,
            false,
            this.urls.clientTokenUrl,
            {}
        );
    },

    /**
     * Attach the PayPal instance to the buttons
     *
     * @param buttons
     */
    attachToButtons: function (buttons) {
        var that = this;
        var options = {
            env: that.config.env,
            commit: false,
            style: that.config.buttonStyle,
            funding: that.config.funding,
            payment: {
                flow: 'checkout',
                amount: that.config.total,
                currency: that.config.currency,
                enableShippingAddress: true,
                shippingAddressEditable: true,
                displayName: that.config.displayName
            },
            events: {
                validate: that.validateForm,
                onAuthorize: function (payload) {
                    var params = {
                        paypal: JSON.stringify(payload)
                    };
                    if (typeof that.config.productId !== 'undefined') {
                        params.product_id = that.config.productId;
                        params.form_data = $('product_addtocart_form') ? $('product_addtocart_form').serialize() : $('pp_express_form').serialize();
                    }
                    that.initModal(params);
                },
                onCancel: function() {
                    that.hideModal();
                },
                onError: function() {
                    alert(typeof Translator === "object" ? Translator.translate("We were unable to complete the request. Please try again.") : "We were unable to complete the request. Please try again.");
                }
            }
        };

        // Add a class to the parents of the buttons
        buttons.each(function (button) {
            button.up().addClassName('braintree-paypal-express-container');
        });

        // Initialize the PayPal button logic on any valid buttons on the page
        this.vzeroPayPal.attachPayPalButtonEvent(buttons, options);
    }

});