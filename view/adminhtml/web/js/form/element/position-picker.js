/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

/**
 * The visual position picker for a placement (08 · UX Spec §4): a 5×5 grid laid out like the product image, the
 * inner 3×3 on the image and the outer ring in the bands around it, plus the three positions in the product text.
 * Arrow keys move the choice inside the grid, so it works without a mouse.
 */
define([
    'Magento_Ui/js/form/element/select'
], function (Select) {
    'use strict';

    var SIZE = 5,
        GRID = [
            null, 'above_l', 'above_c', 'above_r', null,
            'left_t', 'tl', 'tc', 'tr', 'right_t',
            'left_m', 'ml', 'mc', 'mr', 'right_m',
            'left_b', 'bl', 'bc', 'br', 'right_b',
            null, 'below_l', 'below_c', 'below_r', null
        ],
        INLINE = ['near_price', 'near_cart', 'under_name'];

    return Select.extend({
        defaults: {
            elementTmpl: 'Iranimij_OpenLabel/form/element/position-picker'
        },

        /** @returns {Object} options by value */
        byValue: function () {
            var map = {};

            this.options().forEach(function (option) {
                map[option.value] = option;
            });

            return map;
        },

        /** @returns {Array} 25 cells in grid order; corners are empty spacers */
        cells: function () {
            var map = this.byValue();

            return GRID.map(function (value) {
                return value === null ?
                    {value: null, label: '', spacer: true} :
                    {value: value, label: (map[value] || {}).label || value, spacer: false, band: value.indexOf('_') > -1};
            });
        },

        /** @returns {Array} the positions in the product text */
        inlineCells: function () {
            var map = this.byValue();

            return INLINE.map(function (value) {
                return {value: value, label: (map[value] || {}).label || value};
            });
        },

        /**
         * @param {Object} cell
         */
        choose: function (cell) {
            if (cell.value !== null) {
                this.value(cell.value);
            }
        },

        /**
         * @param {Object} cell
         * @returns {Boolean}
         */
        isChosen: function (cell) {
            return cell.value !== null && String(this.value()) === String(cell.value);
        },

        /**
         * Roving tab stop: the chosen cell of a group, or its first cell when the choice is in the other group.
         *
         * @param {Object} cell
         * @param {Boolean} inline
         * @returns {Number}
         */
        tabIndex: function (cell, inline) {
            var group = inline ? INLINE : GRID,
                current = String(this.value()),
                first = inline ? INLINE[0] : 'tl';

            if (cell.value === null) {
                return -1;
            }
            if (group.indexOf(current) > -1) {
                return current === cell.value ? 0 : -1;
            }

            return cell.value === first ? 0 : -1;
        },

        /**
         * Arrow-key navigation inside the grid; spacers are skipped.
         *
         * @param {Object} cell
         * @param {Object} data knockout passes the bound context first
         * @param {jQuery.Event} event
         * @returns {Boolean}
         */
        onKey: function (cell, data, event) {
            var index = GRID.indexOf(cell.value),
                moves = {ArrowLeft: [0, -1], ArrowRight: [0, 1], ArrowUp: [-1, 0], ArrowDown: [1, 0]},
                row = Math.floor(index / SIZE),
                col = index % SIZE,
                move,
                next;

            if (!moves.hasOwnProperty(event.key)) {
                return true;
            }
            move = moves[event.key];
            row += move[0];
            col += move[1];
            while (row >= 0 && row < SIZE && col >= 0 && col < SIZE) {
                next = row * SIZE + col;
                if (GRID[next] !== null) {
                    this.value(GRID[next]);
                    event.currentTarget.parentNode.children[next].focus();
                    break;
                }
                row += move[0];
                col += move[1];
            }

            return false;
        },

        /**
         * Arrow keys inside the row of text positions.
         *
         * @param {Object} cell
         * @param {Object} data
         * @param {jQuery.Event} event
         * @returns {Boolean}
         */
        onInlineKey: function (cell, data, event) {
            var index = INLINE.indexOf(cell.value),
                step = {ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1}[event.key],
                next;

            if (step === undefined) {
                return true;
            }
            next = index + step;
            if (next >= 0 && next < INLINE.length) {
                this.value(INLINE[next]);
                event.currentTarget.parentNode.children[next].focus();
            }

            return false;
        }
    });
});
