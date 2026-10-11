# OpenLabel: product labels for Magento 2 and Hyvä

**Labels that just work on Hyvä, free, with zero slowdown.**

"Sale -25%", "New", "Only 3 left": rule-based product labels on listings and product pages. One query per page,
zero layout shift, correct under Varnish for every customer group, no JavaScript and no Tailwind rebuild. MIT.

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
- **Show where:** a 3×3 position picker for listings and the product page.
- **Who and when:** store views, customer groups, schedule.
- **Design:** pick one of 15 built-in designs or make your own.

The live preview follows every change. Details: [docs/admin.md](docs/admin.md).

## Why OpenLabel

- **Fast.** A dedicated indexer matches rules in SQL; a category page with 36 products costs one extra query.
- **Cache-correct.** Labels are part of the cached HTML; Varnish and the built-in page cache keep one copy per
  customer group, as Magento does for prices. Saving a label purges exactly the pages it changes.
- **No layout shift, no JavaScript.** Labels are positioned with CSS inside the product image; one small
  generated stylesheet per store view. Lighthouse scores and CLS are identical with and without the module.
- **Hyvä-native, theme-safe.** Works on a fresh Hyvä install without a Tailwind build; CSP-clean (no inline
  styles or scripts); right-to-left stores mirror positions automatically.
- **Everything scriptable.** `openlabel:export` / `openlabel:import` keep labels in git, `openlabel:preview`
  shows what a label matches, GraphQL `openlabel_labels` serves headless storefronts.

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
Themes B.V. or Adobe Inc. "Hyvä" and "Magento" are trademarks of their respective owners.

## License

MIT. See [LICENSE](LICENSE).
