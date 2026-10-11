# Storefront

How labels reach the shop (milestone M3). Theme specifics for Hyvä are in [hyva-integration.md](hyva-integration.md).

## What the shopper's browser gets

- **HTML only.** Labels are rendered on the server inside the cached page. OpenLabel adds no JavaScript and no inline styles, so a strict Content Security Policy needs no exception.
- **One stylesheet per store view**, `pub/media/openlabel/<store_id>/openlabel.<hash>.css` (about 4 KB for the 15 built-in designs; ≤ 20 KB for 100 designs). It holds the structural `ol-*` rules, one custom-property line per design and per placement with offsets, and the sanitized custom CSS. The file name changes with its content, so the web server can cache it for a year (Magento's sample nginx and Apache configs already do for `pub/media`).
- **No theme build needed.** Nothing depends on Tailwind: labels are positioned and styled by that stylesheet, also on a theme that was never rebuilt after installing OpenLabel. `bin/magento hyva:config:generate` still lists the module for theme builds that want to scan it.

The stylesheet is rebuilt after every design or label save, by **Stores › Configuration › Iranimij › OpenLabel › Regenerate CSS**, and by:

```bash
bin/magento openlabel:css:regenerate
```

Run it after copying a database or `pub/media` between environments. On a fresh install the file is written on the first storefront request. Previous files stay two days so pages still in Varnish keep their stylesheet. If your media URL is on another host (a CDN), allow that host in your `style-src` CSP policy.

## Markup contract (stable from 1.0)

```html
<span class="ol-anchor">                                   <!-- position: relative wrapper around the image -->
  <img …>                                                  <!-- the theme's image, untouched -->
  <span class="ol-stack ol-stack--v ol-pos-ts ol-p-12">     <!-- one per occupied position -->
    <span class="ol-label ol-label--text ol-d-3 ol-shape-pill">Sale -25%</span>
  </span>
</span>
```

| Class | Meaning |
|---|---|
| `ol-stack`, `ol-stack--v` / `--h` | positioned container of one corner, vertical or horizontal stacking |
| `ol-pos-ts te tc ms mc me bs bc be` | position with logical sides (start/end): mirrors in right-to-left stores |
| `ol-pos-tl tr ml mr bl br` | placements set to "keep left/right in RTL" |
| `ol-p-<placement_id>` | offsets and gap of that placement (custom properties) |
| `ol-label`, `ol-label--text` / `--image` / `--shape`, `ol-d-<design_id>`, `ol-shape-*` | the badge |
| `data-ol-label="<label_id>"` | only with **Debug mode** on |

Custom properties: `--ol-bg --ol-fg --ol-border --ol-bw --ol-w --ol-rot --ol-op --ol-fs-min --ol-fs-cq --ol-fs-max --ol-gap --ol-ox --ol-oy --ol-z`. All rules use a single class, without `!important`, so a theme overrides them with two classes, for example `.product-item .ol-stack { --ol-z: 20; }`.

Labels have `pointer-events: none`: clicks, hover image swaps and swatches work through them. Text sizes use container queries on the product card and the gallery, with an 11 px floor on small cards and phones.

## Right-to-left stores

Positions mirror automatically when the page direction is right-to-left. Magento and Hyvä do not set it by themselves; RTL themes declare it in their layout, for example `app/design/frontend/<Vendor>/<theme>/Magento_Theme/layout/default.xml`:

```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <html>
        <attribute name="dir" value="rtl"/>
    </html>
</page>
```

## Caching

- Pages vary by customer group in Magento's full page cache and in Varnish, so group-limited labels are rendered on the server like all others: one cached copy per visiting group, which is core behaviour.
- Pages showing a label are tagged `openlabel_<label_id>` and `openlabel_design_<design_id>`, and with `cat_p_<id>` of every product card OpenLabel looked at. Saving a label, a design, or reindexing purges exactly those pages and the theme's cached product cards (Hyvä caches each card for an hour).
- A label whose schedule starts or ends shows up or disappears within one hour (hourly cron), with Varnish or the built-in cache.
- Performance budget, checked in CI: one extra query for a 36-product listing, one for a product page.

## Custom themes

The Hyvä package finds its slots automatically on Hyvä 1.4 (verified on 1.4.2; the 1.3 run is part of the end-to-end CI job). A theme that changes the product image template of listings or the gallery container renders the labels itself:

**Product page.** Inside your gallery container (it must be `position: relative`):

```php
<?= $block->getChildHtml('openlabel.product.labels') ?>
```

**Listings.** Inside your image link, after the image, wrapped in a positioned element:

```php
<?php $labelRenderer = $viewModels->require(\Iranimij\OpenLabel\ViewModel\LabelRenderer::class); ?>
<span class="ol-anchor">
    <?= $image->toHtml() ?>
    <?= $labelRenderer->getStacksHtml($product, 'listing', 'lazy') ?>
</span>
```

OpenLabel sees the `ol-stack` marker and does not render the labels a second time. The fallback is tested on a copy of Hyvä default whose gallery container is renamed (`openlabel-dev-env`, `playwright/tests/fallback`).
