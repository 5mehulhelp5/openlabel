# OpenLabel: product labels for Magento 2 and Hyvä

**Labels that just work on Hyvä, free, with zero slowdown.**

"Sale -25%", "New", "Only 3 left": rule-based product labels on listings, product pages, the cart, wish list,
compare list and order emails. One query per page, zero layout shift, correct under Varnish for every customer group,
no Tailwind rebuild. MIT.

<!-- A short screen recording of the quick start goes here (docs/assets/quick-start.gif). -->

## Install

<!-- install:start -->
```bash
composer require iranimij/openlabel-hyva-bundle
bin/magento setup:upgrade
bin/magento openlabel:reindex
```
<!-- install:end -->

That is all on a stock Hyvä theme: no theme changes, no `npm run build`. In production mode, also run
`bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy` as for any module.

## Quick start (under 5 minutes)

1. In the admin, open **Catalog › OpenLabel › Labels**.
2. Click the **Sale -{SAVE_PERCENT}%** starter. The label is live at once, and the message says how many
   products it matches.
3. Open any discounted product, or a category with discounted products: the badge shows the real discount,
   e.g. "Sale -25%".

To change it, open the label. The form has these parts:

- **Show when:** quick switches and the full rule tree.
- **Show where:** a position picker for listings and the product page (21 positions: on the image, around it,
  and next to the price, Add to Cart or the name), plus the cart, mini cart, wish list, compare list and emails.
- **Who and when:** store views, customer groups, schedule.
- **Design:** pick one of 31 built-in designs or make your own.

The live preview follows every change. Details: [docs/admin.md](docs/admin.md).

## Why OpenLabel

- **Fast.** A dedicated indexer matches rules in SQL; a category page with 36 products costs one extra query.
- **Cache-correct.** Labels are part of the cached HTML; Varnish and the built-in page cache keep one copy per
  customer group, as Magento does for prices. Saving a label purges exactly the pages it changes.
- **No layout shift, almost no JavaScript.** Labels are positioned with CSS; one small generated stylesheet per
  store view. Tooltips, links and animations are CSS only. A few lines of JavaScript load only on pages that use
  a live countdown, variant labels or the mini cart. Lighthouse scores and CLS are identical with and without
  the module.
- **Hyvä-native, theme-safe.** Works on a fresh Hyvä install without a Tailwind build; CSP-clean (no inline
  styles or scripts); right-to-left stores mirror positions automatically.
- **Everything scriptable.** `openlabel:export` / `openlabel:import` keep labels in git, `openlabel:preview`
  shows what a label matches, the REST API lets a PIM or ERP manage labels, GraphQL `openlabel_labels` serves
  headless storefronts.

## How it compares

OpenLabel 1.1 next to the two best-known paid label modules. Their columns come only from their public
documentation and product pages (October 2026), never from their code. "Not in docs" means we found no mention,
not that the feature is missing. Corrections are welcome as an issue.

| | OpenLabel 1.1 | Amasty Product Labels | Mirasvit Product Labels |
|---|---|---|---|
| Price | Free, MIT | Paid | Paid |
| Hyvä | ✓ native render package, included | ✓ | ✓ |
| Text, shape and image labels | ✓ | ✓ | ✓ |
| Image formats | PNG, JPG, GIF, SVG (sanitized), WebP, AVIF; size limit setting | PNG, JPG, GIF, WebP | Not in docs |
| Built-in designs | 31, all WCAG AA contrast | ✓ ready-made pack | ✓ |
| Size per phone, tablet, desktop | ✓ | Not in docs | Not in docs |
| Custom CSS | ✓ per design and global, sanitized, own permissions | ✓ | ✓ |
| Entry animation and hover effect (CSS only, off for reduced motion) | ✓ | Through custom CSS | Not in docs |
| Positions on the image | 9 | 9 | 9 |
| Positions around the image (above, below, left, right) | 12 | Not in docs | Not in docs |
| Next to the price, next to Add to Cart, under the name | ✓ | ✓ | Not in docs |
| Listing, search, product page, widgets, related / upsell / cross-sell | ✓ | ✓ | ✓ |
| Cart and mini cart | ✓ (Hyvä) | Paid add-on | Not in docs |
| Checkout | No (Hyvä Checkout planned on request) | Paid add-on | Not in docs |
| Wish list | ✓ | Not in docs | Not in docs |
| Compare list | ✓ | Not in docs | ✓ |
| Order, invoice, shipment and credit memo emails | ✓ | Not in docs | Not in docs |
| Hide labels on chosen pages | ✓ | ✓ | Not in docs |
| Conditions: any attribute, category, SKU, on sale, new, stock, price | ✓ | ✓ | ✓ |
| Conditions: bestseller, most viewed | ✓ | Bestseller as add-on | Not in docs |
| Condition: ships free | ✓ | Not in docs | Not in docs |
| Configurable products: label follows the chosen variant | ✓ | Not in docs | ✓ |
| Variables: discount, prices, stock, SKU, any attribute, days left | ✓ | ✓ | ✓ |
| Variables: category, brand | ✓ | Not in docs | Category |
| Hide the label when a variable is zero | ✓ | ✓ | Not in docs |
| Live countdown | ✓ | Separate module | Not in docs |
| Tooltip | ✓ (hover, keyboard focus, touch) | ✓ | Not in docs |
| Click-through link (nofollow option) | ✓ | ✓ | ✓ |
| Schedule, store views, customer groups, priority | ✓ | ✓ | ✓ |
| Label texts translated | EN, DE, FR, ES, NL, IT | EN, DE | Not in docs |
| Live preview and matched-products count in the admin | ✓ | Count | Count |
| Labels on the product edit page, assign by hand | ✓ | Not in docs | Not in docs |
| Dedicated indexer | ✓ | ✓ | ✓ |
| Cached per customer group in Varnish, no AJAX, zero layout shift | ✓ | Not in docs | Not in docs |
| GraphQL (storefront) | ✓ | ✓ | Not in docs |
| REST API | ✓ create, read, update, delete | ✓ | Not in docs |
| Export and import (JSON, CLI); import from Smile | ✓ | Not in docs | Not in docs |
| Remote storage (S3) for label images | Should work, not tested | ✓ | Not in docs |

Not in OpenLabel 1.1: GraphQL mutations for the admin, a bulk REST read, checkout labels, a Luma render package
and importers from Amasty and Mirasvit (planned for 1.2).

## Compatibility

| | Versions |
|---|---|
| Magento Open Source, Mage-OS, Adobe Commerce | 2.4.7, 2.4.8, 2.4.9 |
| PHP | 8.2, 8.3, 8.4 |
| Hyvä default theme | 1.4.x and 1.5.x (both tested end to end on every change); 1.3.x best effort (not tested in CI) |
| MSI | optional: stock conditions use the website's MSI stock when present, legacy stock otherwise |

Other themes: the core package (`iranimij/openlabel`) works with any theme through two documented calls; see
[Custom themes](docs/storefront.md#custom-themes). A Luma render package is planned.

## Caching notes

- Labels are rendered into the cached page; there is no private content and no AJAX.
- A label whose schedule starts or ends appears or disappears within the hour (an hourly job purges those
  pages). Saving a label purges its pages immediately.
- Hyvä caches product cards for an hour; OpenLabel cleans those caches on every label or design change.
- Details: [docs/storefront.md#caching](docs/storefront.md#caching).

## Documentation

[docs/](docs/index.md) covers:

- [installation and upgrades](docs/install.md)
- [the admin](docs/admin.md)
- [the storefront and CSS contract](docs/storefront.md)
- [custom themes](docs/storefront.md#custom-themes)
- [the engine](docs/engine.md)
- [CLI](docs/cli.md)
- [GraphQL](docs/graphql.md)
- [REST API](docs/rest-api.md)
- [export, import and moving from Smile](docs/import-export.md)

## Packages

| Package | Purpose |
|---|---|
| `iranimij/openlabel` (this repo) | Core: entities, rule conditions, indexer, resolver, admin, GraphQL, CLI |
| [`iranimij/openlabel-hyva`](https://github.com/iranimij/openlabel-hyva) | Hyvä render package |
| [`iranimij/module-base`](https://github.com/iranimij/module-base) | Shared config tab and helpers. No license checks, no phone-home. |
| [`iranimij/openlabel-hyva-bundle`](https://github.com/iranimij/openlabel-hyva-bundle) | Metapackage installing all three |

## Support and feedback

- Issues are triaged within 7 days. There is no SLA. Security issues: see [SECURITY.md](SECURITY.md).
- Show your shop or ask a question in [GitHub Discussions](https://github.com/iranimij/openlabel/discussions).
- Missing a label you wanted to build? Tell us in a feature request.
- Contributions are welcome; please read [CONTRIBUTING.md](CONTRIBUTING.md), including the clean-room rule.

## Notice

OpenLabel is an independent open-source project. It is not affiliated with, endorsed by, or sponsored by Hyvä
Themes B.V., Adobe Inc., Amasty or Mirasvit. "Hyvä", "Magento", "Amasty" and "Mirasvit" are trademarks of their
respective owners.

## License

MIT. See [LICENSE](LICENSE).
