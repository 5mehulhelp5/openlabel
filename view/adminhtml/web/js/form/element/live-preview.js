/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

/**
 * Live preview of the label being edited (08 · UX Spec §4.5, 10 · FE11): the chosen design on a sample product,
 * every placement of the selected page, variables filled with sample values, a desktop/mobile toggle, a contrast
 * warning and "Preview as" a customer group. Rendered with the same ol-* structural CSS as the shop.
 */
define([
    'ko',
    'uiElement',
    'uiRegistry',
    'Iranimij_OpenLabel/js/contrast',
    'mage/translate'
], function (ko, Element, registry, contrast, $t) {
    'use strict';

    var POSITION_CLASS = {
        tl: 'ol-pos-ts', tc: 'ol-pos-tc', tr: 'ol-pos-te',
        ml: 'ol-pos-ms', mc: 'ol-pos-mc', mr: 'ol-pos-me',
        bl: 'ol-pos-bs', bc: 'ol-pos-bc', br: 'ol-pos-be',
        above_l: 'ol-pos-ots', above_c: 'ol-pos-otc', above_r: 'ol-pos-ote',
        below_l: 'ol-pos-obs', below_c: 'ol-pos-obc', below_r: 'ol-pos-obe',
        left_t: 'ol-pos-ost', left_m: 'ol-pos-osm', left_b: 'ol-pos-osb',
        right_t: 'ol-pos-oet', right_m: 'ol-pos-oem', right_b: 'ol-pos-oeb'
    },
        INLINE = ['near_price', 'near_cart', 'under_name'];

    return Element.extend({
        defaults: {
            template: 'Iranimij_OpenLabel/form/element/live-preview',
            designs: [],
            samples: {},
            groups: [],
            sampleImage: '',
            area: 'listing',
            device: 'desktop',
            previewGroup: '',
            collapsed: false,
            formData: {},
            listens: {
                '${ $.provider }:data': 'refresh'
            }
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super().observe(['area', 'device', 'previewGroup', 'collapsed', 'formData']);
            this.stacks = ko.pureComputed(this.buildStacks, this);
            this.imageStacks = ko.pureComputed(function () {
                return this.stacks().filter(function (stack) {
                    return !stack.inline;
                });
            }, this);
            this.contrastWarning = ko.pureComputed(this.buildContrastWarning, this);
            this.hiddenReason = ko.pureComputed(this.buildHiddenReason, this);

            return this;
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            registry.get(this.provider, function () {
                this.refresh();
            }.bind(this));

            return this;
        },

        /** Takes a copy of the form data so the computed values re-evaluate. */
        refresh: function () {
            var provider = registry.get(this.provider);

            if (provider) {
                this.formData(JSON.parse(JSON.stringify(provider.get('data') || {})));
            }
        },

        /**
         * @param {String} value
         */
        setArea: function (value) {
            this.area(value);
        },

        /**
         * @param {String} value
         */
        setDevice: function (value) {
            this.device(value);
        },

        /** Collapses or expands the panel. */
        toggle: function () {
            this.collapsed(!this.collapsed());
        },

        /**
         * @param {String|Number} id
         * @returns {Object|undefined}
         */
        design: function (id) {
            id = String(id || '');

            return this.designs.filter(function (option) {
                return String(option.value) === id;
            })[0];
        },

        /**
         * @returns {Array} visible, non-deleted placements of the selected page
         */
        placements: function () {
            var area = this.area();

            return (this.formData().placements || []).filter(function (row) {
                return row && row.area === area && !(row.delete === true || row.delete === 'true');
            });
        },

        /**
         * Replaces {VARIABLES} with their samples; unknown ones stay visible, like in the shop.
         *
         * @param {String} text
         * @returns {Array} lines
         */
        render: function (text) {
            var samples = this.samples;

            text = String(text || '').replace(/<[^>]*>/g, '').replace(/\{([A-Z0-9_]+)(?::[a-z0-9_]+)?\}/g, function (match, code) {
                var key = match.indexOf(':') > -1 ? code + ':code' : code;

                return samples.hasOwnProperty(key) ? samples[key] : match;
            });

            return text.split('\n');
        },

        /**
         * @param {Object} design
         * @param {Object} placement
         * @returns {Object}
         */
        labelView: function (design, placement) {
            var data = design.design,
                type = data.type || 'text',
                classes = ['ol-label', 'ol-label--' + type];

            if (type !== 'image') {
                classes.push('ol-shape-' + (type === 'shape' && data.shape ? data.shape : 'rectangle'));
            }

            if (data.animation && data.animation !== 'none') {
                classes.push('ol-anim-' + data.animation);
            }

            if (data.hover_effect && data.hover_effect !== 'none') {
                classes.push('ol-hover-' + data.hover_effect);
            }

            return {
                classes: classes.join(' '),
                vars: {
                    '--ol-bg': data.bg_color || '',
                    '--ol-fg': data.text_color || '',
                    '--ol-border': data.border_color || '',
                    '--ol-bw': (data.border_width || 0) + 'px',
                    '--ol-op': String((data.opacity === undefined ? 100 : data.opacity) / 100),
                    '--ol-rot': (data.rotation || 0) + 'deg',
                    '--ol-w': data.width ? data.width + (data.size_mode === 'px' ? 'px' : 'cqw') : 'auto',
                    '--ol-fs-max': (data.font_size || 14) + 'px'
                },
                isImage: type === 'image',
                imageUrl: data.image_url,
                imageWidth: data.image_width,
                imageHeight: data.image_height,
                alt: this.render(data.alt_text).join(' '),
                lines: this.render(data.text),
                position: placement.position
            };
        },

        /**
         * @param {String} position near_price, near_cart or under_name
         * @returns {Array} the stack in that place of the product text (at most one)
         */
        inlineStacks: function (position) {
            return this.stacks().filter(function (stack) {
                return stack.position === position;
            });
        },

        /** @returns {Array} one stack per occupied position */
        buildStacks: function () {
            var data = this.formData(),
                byPosition = {},
                stacks = [];

            this.placements().forEach(function (placement) {
                var design = this.design(placement.design_id) || this.design(data.design_id),
                    key = placement.position || 'tl';

                if (!design) {
                    return;
                }
                if (!byPosition[key]) {
                    byPosition[key] = {
                        position: key,
                        inline: INLINE.indexOf(key) > -1,
                        stackClass: (INLINE.indexOf(key) > -1 ?
                            ['ol-stack', 'ol-stack--h', 'ol-inline', 'ol-inline--' + key.replace('_', '-')] :
                            [
                                'ol-stack',
                                placement.stacking === 'horizontal' ? 'ol-stack--h' : 'ol-stack--v',
                                POSITION_CLASS[key] || 'ol-pos-ts'
                            ]).join(' '),
                        stackStyle: {
                            '--ol-gap': (placement.gap === undefined || placement.gap === '' ? 4 : placement.gap) + 'px',
                            '--ol-ox': (placement.offset_x || 0) + 'px',
                            '--ol-oy': (placement.offset_y || 0) + 'px'
                        },
                        labels: []
                    };
                    stacks.push(byPosition[key]);
                }
                byPosition[key].labels.push(this.labelView(design, placement));
            }, this);

            return stacks;
        },

        /** @returns {String} empty when every shown design is readable */
        buildContrastWarning: function () {
            var data = this.formData(),
                weak = [];

            this.placements().forEach(function (placement) {
                var design = this.design(placement.design_id) || this.design(data.design_id),
                    ratio;

                if (!design || design.design.type === 'image') {
                    return;
                }
                ratio = contrast.ratio(design.design.text_color, design.design.bg_color);
                if (ratio !== null && ratio < 4.5 && weak.indexOf(design.label) === -1) {
                    weak.push(design.label);
                }
            }, this);

            return weak.length ?
                $t('The text of "%1" is hard to read: contrast is below 4.5:1. Pick stronger colours.').replace('%1', weak.join('", "')) :
                '';
        },

        /** @returns {String} why the shopper would not see the label, if so */
        buildHiddenReason: function () {
            var data = this.formData(),
                groups = (data.customer_group_ids || []).map(String),
                group = this.previewGroup();

            if (String(data.status) === '0') {
                return $t('The label is switched off: shoppers do not see it.');
            }
            if (group !== '' && group !== undefined && groups.length && groups.indexOf(String(group)) === -1) {
                return $t('This label is hidden for this customer group.');
            }
            if (!this.placements().length) {
                return $t('No placement on this page yet.');
            }

            return '';
        }
    });
});
