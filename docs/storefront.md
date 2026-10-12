# Storefront

How labels reach the shop (milestone M3). Theme specifics for Hyvä are in [hyva-integration.md](hyva-integration.md).

## What the shopper's browser gets

- **HTML first.** Labels are rendered on the server inside the cached page, with no inline styles. Tooltips, links, animations and hover effects are CSS only. JavaScript is added only where a page needs it: the live countdown and labels that follow the chosen variant. The script is small and registered with Hyvä's CSP helper, so a strict Content Security Policy needs no exception.
- **One stylesheet per store view**, `pub/media/openlabel/<store_id>/openlabel.<hash>.css` (about 8 KB, 2 KB compressed, for the 31 built-in designs; ≤ 20 KB for 100 designs). It holds the structural `ol-*` rules, one custom-property line per design and per placement with offsets, and the sanitized custom CSS. The file name changes with its content, so the web server can cache it for a year (Magento's sample nginx and Apache configs already do for `pub/media`).
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
| `ol-pos-ots otc ote obs obc obe ost osm osb oet oem oeb` | the bands just outside the image (1.1): o(utside) + t(op), b(ottom), s(tart side), e(nd side) + the place along it; over the space around the image |
| `ol-pos-otl otr obl obr olt olm olb ort orm orb` | the bands with "keep left/right in RTL" |
| `ol-label--link` | the label is an `<a href>` (click-through, 1.1); never inside the product card's image link, where labels stay `<span>` |
| `ol-has-tip` + `ol-tip` | a label with a tooltip: the next sibling `<span class="ol-tip" role="tooltip">` is shown on hover and focus and referenced by `aria-describedby`; outside links the label is focusable (`tabindex="0"`) |
| `ol-label--hidden`, `data-ol-variant="<id>"` | product page of a configurable product (1.1): every variant's labels are in the page; `0` is the product as it opens, the others stay hidden until that variant is chosen |
| `ol-countdown` (`data-ol-end`, an `aria-hidden` ticking part, an `ol-sr` end date) | the `{COUNTDOWN}` variable (1.1) |
| `ol-inline--cart` / `--minicart` / `--wishlist` / `--compare` | the row of labels of an item list (1.1): after the item name on the cart, wish list and compare pages, next to the item price in the mini cart |
| `ol-inline`, `ol-inline--near-price` / `--near-cart` / `--under-name` | labels in the product text (1.1): part of the text flow, always side by side, sized by the design's font size |
| `ol-p-<placement_id>` | offsets and gap of that placement (custom properties) |
| `ol-label`, `ol-label--text` / `--image` / `--shape`, `ol-d-<design_id>`, `ol-shape-*` | the badge |
| `data-ol-label="<label_id>"` | only with **Debug mode** on |

Custom properties: `--ol-bg --ol-fg --ol-border --ol-bw --ol-w --ol-rot --ol-op --ol-fs-min --ol-fs-cq --ol-fs-max --ol-gap --ol-ox --ol-oy --ol-z`. All rules use a single class, without `!important`, so a theme overrides them with two classes, for example `.product-item .ol-stack { --ol-z: 20; }`.

JavaScript: none for display. Only pages with a `{COUNTDOWN}` or variant labels get one small inline script (openlabel-hyva, under 1 KB gzipped, registered for the CSP): one timer for all countdowns, paused in background tabs, and the switch between variant labels on Hyvä's `configurable-selection-changed` event (the labels of a variant show when exactly one variant matches the choice).

Labels have `pointer-events: none`: clicks, hover image swaps and swatches work through them. Labels with a tooltip or a link (1.1) take the pointer; inside a product card a click on them still opens the product. Text sizes use container queries on the product card and the gallery, with an 11 px floor on small cards and phones.

## Order emails (1.1)

Labels of the "Order emails" area appear under the item name in order, invoice, shipment and credit memo emails
(`<p class="openlabel-email">` after `<p class="product-name">` of the core item templates). Email clients ignore
stylesheets, so each label is a `<span>` with inline styles built from the design's colours and shape; image
designs are left out (many clients block images). Labels are resolved for the order's store view and customer
group when the email is sent; price variables show the current price, so emails are best with plain text.

## Languages: label packs (1.1)

The built-in designs show their text in the store view's language (Stores › Configuration › General › Locale
Options): English, German, French, Spanish, Dutch and Italian are included, also in GraphQL and order emails. For
example "Sale -25%" becomes "Promo -25 %" in a French store view. The same files translate the texts shoppers see
from variables ("3 days", "Ends 12/31/26").

- Your own designs keep the texts you enter, per store view. **Duplicate to edit** copies a built-in design with its
  English text: enter the texts per store view in the copy.
- The phrases are in `i18n/<locale>.csv` of the module, prefixed with `Label text: ` (e.g.
  `"Label text: New","Nouveau"`) so they never change other modules' texts. To change one, or to add a language or a
  regional variant (`de_AT`, `fr_BE`, …), put the same lines in your theme's `i18n/<locale>.csv` or a language pack.
- The French, Spanish, Dutch and Italian texts were written without a native-speaker review yet: corrections are
  welcome as pull requests.

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

The Hyvä package finds its slots automatically on Hyvä 1.4 and 1.5 (end-to-end tests on 1.4.2 and 1.5.2 for every change; 1.3 is supported on a best-effort basis and not tested in CI). On Hyvä 1.5, whose `#gallery-main` is the scrolling image track, the product-page labels go into the gallery's positioned wrapper (`#gallery > .relative`) right before the track, so they stay on the visible image while the shopper swipes.

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
