# Hyvä integration

Verified on **Hyvä default theme 1.4.2 with theme-module 1.5.2** on Magento Open Source 2.4.9 (M0, 2026-10-09). Re-verify on every Hyvä minor release; the Playwright spec `playwright/tests/hyva-hooks.spec.ts` in `openlabel-dev-env` fails when any fact below changes.

## 1. Product listing (category, search, widgets, sliders)

| Fact | Value |
|---|---|
| Item renderer block | `product_list_item`, class `Magento\Catalog\Block\Product\View`, template `Magento_Catalog::product/list/item.phtml`, declared in `Magento_Catalog/layout/catalog_list_item.xml` |
| Renders through | `Hyva\Theme\ViewModel\ProductListItem::getItemHtml()` / `getItemHtmlWithRenderer()`; used by `product/list.phtml`, `product/slider/product-slider.phtml` (related, upsell, cross-sell), `Magento_CatalogWidget::product/widget/content/grid.phtml`, `Magento_PageBuilder::catalog/product/widget/content/carousel.phtml`, the cart form and the order item lists |
| Child blocks available in `item.phtml` | `details.renderers`, `addto` (`catalog.list.item.addto`, a `ProductList\Item\Container`), `wishlist`, `stockstatus`. **All render below the image.** |
| Image markup | `<a class="product photo product-item-photo block mx-auto mb-3">` (not `relative`) containing exactly one `<img>` produced by `Magento\Catalog\Block\Product\Image` with template `Magento_Catalog::product/list/image.phtml`; `width`/`height` attributes present; `x-data="initProductItemImage"` and `@update-gallery-<productId>.window` for hover and swatch image swap |
| Non-HTML hook | `additional_item_renderer_processors` argument on `product_list_item`: objects with `beforeListItemToHtml($itemRendererBlock, $product)` run right before `toHtml()` |
| Block cache | Every item is block-cached for `hyva_theme_catalog/developer/cache/product_list_item_block_cache_lifetime` (default 3600 s) unless `..._enabled` is off. Cache key: product id, view mode, template, store, currency, hide flags, category id, customer group, image area, custom image attributes, tax rates. Cache tags: `$product->getIdentities()` |
| Absolutely positioned elements on the card | None over the image. Wishlist, compare, add-to-cart and stock status sit in a flex row under the product info; swatch radio inputs are absolute but sit under their buttons below the image. |

**There is no child-block call inside the image anchor**, so a layout-only injection is impossible without overriding `item.phtml`. OpenLabel therefore uses an `after` plugin on `Magento\Catalog\Block\Product\Image::toHtml()` that is active only when the block template is `Magento_Catalog::product/list/image.phtml`. The plugin wraps the output:

```html
<span class="ol-anchor">            <!-- position:relative; display:block (no containment, see section 4) -->
  <img ...>                          <!-- untouched Hyvä output -->
  <span class="ol-stack ol-pos-ts">…labels…</span>
</span>
```

One plugin covers every surface that renders through the item template. The labels are resolved once per page by the `Labels` view model (one SELECT for all product ids of the collection) and read from the memoized result per item.

Cache: the `additional_item_renderer_processors` hook is used to append `openlabel_<label_id>` tags to each item's block cache tags, so a label save or reindex invalidates the right cards. Because items are block-cached for up to one hour, a label whose time window opens or closes can take up to one hour to appear or disappear even without Varnish; the hourly transition cron cleans the tags.

Default starter positions (all corners are free): Sale → top start (`ol-pos-ts`), New → top end (`ol-pos-te`), Low stock → bottom start (`ol-pos-bs`).

## 2. Product page gallery

| Fact | Value |
|---|---|
| Block | `product.media`, class `Magento\Catalog\Block\Product\View\Gallery`, template `Magento_Catalog::product/view/gallery.phtml`, rendered by `Magento_Catalog::product/product-detail-page.phtml` via `getChildHtml('product.media')` |
| Container | `<div id="gallery-main" class="relative mb-6" aria-live="polite">` |
| Contents | an `invisible` placeholder `<img width height>` that reserves the aspect ratio (CLS-safe), Alpine `absolute inset-0` images (`x-show="active === index"`), an `absolute inset-0` fullscreen button, YouTube/Vimeo overlays |
| Fullscreen | the wrapper two levels up gets `fixed top-0 left-0 z-50` while `fullscreen` is true |
| Child blocks in `gallery.phtml` | none |

OpenLabel declares a child block `openlabel.product.labels` under `product.media` in `hyva_catalog_product_view.xml` and uses an `after` plugin on `Gallery::toHtml()` that inserts the child HTML directly after the `id="gallery-main"` opening tag and adds `ol-anchor` to that element's class list. The plugin skips when the output already contains `ol-stack` (custom theme that calls the child block itself). The stack uses `z-index: var(--ol-z, 5)` to sit above the gallery images and is hidden in fullscreen mode (`.fixed .ol-stack { display: none }`). `pointer-events: none` keeps the fullscreen button clickable.

## 3. Events and layout handles

| Purpose | Name | Payload |
|---|---|---|
| Swatch change, product page | `configurable-selection-changed` (window `CustomEvent`) | `detail.productId`, `detail.optionId`, `detail.value`, `detail.productIndex` (selected simple id or `undefined`), `detail.selectedValues`, `detail.candidates`, `detail.skuCandidates` |
| Swatch change, listing | `listing-configurable-selection-changed` | same shape |
| Gallery swap, product page | `update-gallery` / `reset-gallery` | image list |
| Gallery swap, listing | `update-gallery-<productId>` | image |
| Layout handles | Hyvä adds `hyva_<handle>` for every handle (`Hyva\Theme\Observer\AddLayoutHandles`), so `hyva_catalog_category_view`, `hyva_catalogsearch_result_index`, `hyva_catalog_product_view` and `hyva_default` are valid | |
| CSP | every inline `<script>` is followed by `$hyvaCsp->registerInlineScript()` (`Hyva\Theme\ViewModel\HyvaCsp`) | |

The 1.1 variant switch listens to `configurable-selection-changed` and keys on `detail.productIndex`.

## 4. Container queries (FE3)

**Do not put `container-type: inline-size` on Hyvä's image anchor.** `a.product-item-photo` is `block mx-auto` inside a `flex flex-col` card, so it is shrink-to-fit: its width comes from the image. Inline-size containment removes that size source and the anchor collapses to 0 × 0 (verified; the Playwright spec keeps a test for this). The same applies to any wrapper OpenLabel inserts inside the anchor.

The listing container is therefore the **product card** (`.product-item`, `w-full` in the grid): `.product-item { container-type: inline-size }` causes no layout shift (verified on desktop and mobile), and label sizes in `cqw` relate to the card width, which on Hyvä equals the image column. The `.ol-anchor` wrapper stays `position: relative; display: block` without containment.

On the product page `#gallery-main` is a block with a definite width (`w-full` parent), so `#gallery-main { container-type: inline-size }` is safe (verified).

Absolutely positioned elements on the card (FE5): only the swatch radio inputs (`input.absolute.product-option-value-input`, visually under the swatch buttons, below the image) and `sr-only` helpers. Nothing overlaps the image area, so all four corners are free for labels.

## 5. Custom theme fallback

Themes that override `product/list/image.phtml` with another template name or rename `#gallery-main` render the labels themselves: one `getChildHtml('openlabel.product.labels')` call in the gallery, one `LabelRenderer::getStacksHtml()` call in the listing image. Exact snippets: [storefront.md](storefront.md#custom-themes). The plugins detect the `ol-stack` marker and never render twice.

## 6. Labels in the product text (1.1)

- **Listing, next to the price:** `Plugin\PriceBoxPlugin` appends the stack to `Magento\Catalog\Pricing\Render\FinalPriceBox`
  in the `item_list` zone (every product card, widget and slider).
- **Listing, under the name and next to Add to Cart:** the card has no child block there, so
  `Plugin\ListItemPlugin` inserts the stacks right after `a.product-item-link` and `[data-addto="cart"]` of the
  rendered `Magento_Catalog::product/list/item.phtml`. Cards without those elements keep their markup.
- **Product page:** `Plugin\InlineSlotPlugin` appends the stacks after the layout elements `product.info.title`,
  `product.info.price` and `product.info.addtocart`. A theme that renames them adds its names to the
  `productPageSlots` argument of the plugin in `etc/frontend/di.xml`.
- All three render inside the cached card or page, tagged like the image labels; no JavaScript.

## 7. Variants and countdown (1.1)

- `Block\ProductLabels` renders `LabelRenderer::getProductPageStacksHtml()`: for a configurable product the
  stacks also hold each variant's labels (the product's own labels plus the variant's, arranged by priority),
  hidden with `ol-label--hidden` and tagged `data-ol-variant`. Parent and variants are resolved with one query.
- `Block\Script` (`openlabel.script` in `before.body.end`, template `js/labels.phtml`) is rendered only when the
  page needs it (`LabelRenderer::isScriptNeeded()`, also set by product cards served from the block cache). It
  listens to `configurable-selection-changed` and ticks `.ol-countdown[data-ol-end]`; the inline script is
  registered with `$hyvaCsp->registerInlineScript()`.

## 8. Item lists (1.1)

- **Cart page:** `Plugin\Items\CartItemPlugin` inserts the item's labels after `h3.product-item-name` of
  `php-cart/item/default.phtml`.
- **Mini cart:** Hyvä renders each item's `product_price` from the cart section as HTML, so
  `Plugin\Items\MiniCartItemPlugin` appends the labels there (`AbstractItem::getItemData`). No JavaScript; the
  labels update when the cart section reloads.
- **Wish list:** `Plugin\Items\WishlistItemPlugin` after the name column (`Item\Column\Info`).
- **Compare page:** `Plugin\Items\CompareListPlugin` after each product's name link, found by its URL.

## Hyvä 1.5 (verified on default theme 1.5.2, 2026-10-11)

Hyvä 1.5 rebuilt the product gallery as a snap slider:

- `#gallery` (a `<section>`, a grid column of the product page) → `div.relative` (positioned wrapper with the
  navigation buttons and the `gallery.additional` container) → `#gallery-main[data-track]`, the horizontally
  scrolling track with one slide per image.
- **Slot:** `Plugin\GalleryPlugin` recognises the track by `data-track` and inserts the label stacks right before
  it, inside the positioned wrapper. Labels stay on the visible image while the shopper swipes; inside the track
  they would scroll away with the first slide.
- **Container-query root:** `#gallery` (only when it holds labels, `:has`). Containment must not go on the track
  or the wrapper: the wrapper is a shrink-to-fit flex item on desktop, and inline-size containment collapses the
  whole gallery to zero width (found by the 1.5 end-to-end leg). On 1.3/1.4 the root stays `#gallery-main`.
- **Previous/next buttons:** they sit in a bottom corner of the image (`bottom-2 end-2`, or the start corner with
  the theme's "start" setting) above the track. Labels placed in that corner move up 3.25 rem, above the buttons,
  or they would be covered and not clickable (found by the 1.1 end-to-end leg). The rule is keyed on the buttons
  being in the markup, so nothing moves after Alpine starts.
- Listings are unchanged in 1.5: the image link of the product card is the slot, the card is the root.
- Swatch selection still dispatches `configurable-selection-changed`; the swatches are a `<fieldset>` with a
  legend instead of a `radiogroup`.
