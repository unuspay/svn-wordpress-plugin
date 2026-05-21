/**
 * Unuspay WooCommerce Blocks — Payment Method Registration
 *
 * Registers the Unuspay gateway with the WooCommerce Checkout Block editor
 * so it appears as a selectable payment method in the block-based checkout.
 *
 * @package UnusPay\WooCommerce
 */
/* global wc */

(function () {
    'use strict';

    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var settings = Object(window.wc.wcSettings.getSetting('unuspay_data', {}));

    var label = settings.title || 'Pay with Crypto - Unuspay';
    var description = settings.description || '';
    var tokenIcons = settings.token_icons || {};

    var Content = function () {
        return wp.element.createElement(wp.htmlEntities.RawHTML, {
            key: 'unuspay-description',
        }, description || null);
    };

    var tokenIconStyle = {
        maxHeight: '24px',
        width: 'auto',
        marginLeft: '4px',
        verticalAlign: 'middle',
    };

    var Label = function () {
        var icons = [];

        icons.push(wp.element.createElement('span', { key: 'label-text' }, label));

        if (tokenIcons.usdc) {
            icons.push(wp.element.createElement('img', {
                key: 'usdc-icon',
                className: 'unuspay-token-icon',
                src: tokenIcons.usdc,
                alt: 'USDC',
                style: tokenIconStyle,
            }));
        }

        if (tokenIcons.usdt) {
            icons.push(wp.element.createElement('img', {
                key: 'usdt-icon',
                className: 'unuspay-token-icon',
                src: tokenIcons.usdt,
                alt: 'USDT',
                style: tokenIconStyle,
            }));
        }

        return wp.element.createElement('div', {
            key: 'unuspay-label',
            className: 'wc-block-components-payment-method-label',
        }, icons);
    };

    registerPaymentMethod({
        name: 'unuspay',
        label: wp.element.createElement(Label),
        content: wp.element.createElement(Content),
        edit: wp.element.createElement(Content),
        canMakePayment: function () {
            return true;
        },
        ariaLabel: label,
        supports: {
            features: settings.supports || [],
        },
    });
})();
