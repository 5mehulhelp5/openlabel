/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

/**
 * Design type as three big cards (Text, Image, Shape) instead of a drop-down (08 · UX Spec §5).
 */
define([
    'Magento_Ui/js/form/element/select'
], function (Select) {
    'use strict';

    return Select.extend({
        defaults: {
            hints: {
                text: 'Plain text on a coloured background',
                image: 'Your own PNG, SVG, WebP or AVIF badge',
                shape: 'Text inside a pill, ribbon, circle or corner'
            }
        },

        /**
         * @param {Object} option
         */
        choose: function (option) {
            this.value(option.value);
        },

        /**
         * @param {Object} option
         * @returns {Boolean}
         */
        isChosen: function (option) {
            return String(this.value()) === String(option.value);
        }
    });
});
