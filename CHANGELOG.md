# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Data for the 1.1 features (shown in the admin and storefront by the following 1.1 changes): labels get "hide when a variable is zero or empty", a click-through link (https://, http:// or a /path; `javascript:`, `data:` and `//host` links are refused) and its nofollow flag; designs get an entry animation (none, fade, pop), a hover effect (none, grow, lift) and per-breakpoint size overrides (sm, md, lg: width and font size).
- Export file format version 2 with these fields; version 1 files from OpenLabel 1.0 still import, the new fields taking their defaults.

### Changed
- **API (minor, new methods):** `Api\Data\LabelInterface` gains `isHideOnZeroVariable()`, `getClickUrl()`, `isClickNofollow()` and their setters; `Api\Data\DesignInterface` gains `getAnimation()`, `getHoverEffect()`, `getSizes()` and their setters, plus the `ANIMATIONS`, `HOVER_EFFECTS` and `BREAKPOINTS` constants. Code that only uses these interfaces is unaffected; classes that implement them must add the methods.

### Fixed
- After a full reindex `bin/magento setup:db:status` reported the schema as out of date (so deploy pipelines ran `setup:upgrade` every time): swapping the index with its replica swapped their table comments. Both tables now carry the same comment.

## [1.0.0] - 2026-10-11

OpenLabel 1.0: everything of 1.0.0-rc1 and rc2 plus the changes below. Supported: Magento 2.4.7–2.4.9, PHP 8.2–8.4, Hyvä 1.4 and 1.5 (1.3 best effort).

### Changed
- Hyvä 1.5 is supported and tested end to end on every change, like 1.4 (product-page labels via openlabel-hyva).

### Fixed
- Saving a label that combines a quick switch (on sale, new, stock, rating) with an advanced condition (e.g. a category) failed with "Unknown column ol_price.price": the core rule SQL builder drops the joins it collected before a nested condition group. OpenLabel now uses its own builder subclass for its rules.
- Conditions behave the same on Magento 2.4.7 to 2.4.9 for every operator: products without the compared data (no price index row, no rating, no stock row, no value for a product attribute) never match, "is not" included; 2.4.7 and 2.4.8 used to treat missing values as 0, so e.g. "news from date on or before …" matched products without that date. Products without reviews count as 0 reviews. Yes/no conditions offer "is" and "is not" only.
- Admin live preview: the sample image box is reserved up front, so the preview card (and the labels, sized relative to it) no longer collapses while the image is being laid out after "Preview as" or a design change.

### Added
- Matched products explain an empty label ("Why no products?"): the label-level reasons (switched off, not in the default store view, not started, ended) and every condition on its own with its product count, conditions that match nothing alone in red; when each matches but none together, a hint about contradicting conditions (e.g. on sale and out of stock).
- "Show when" advanced conditions: the clickable parts of the rule tree ("is" for the comparison, "..." for the value, "ALL" / "TRUE") look like links, with a one-line hint; numeric conditions offer is, is not, greater/less (or equal) and is (not) one of there.
- Logging channel `openlabel` (`var/log/openlabel.log`): indexing errors after a save (with the label and its conditions) and failed imports. The admin shows "The label was saved, but its products could not be matched: …" and keeps the label open instead of Magento's error page.

## [1.0.0-rc2] - 2026-10-11

### Fixed
- GraphQL `openlabel_labels`: price variables (`{SAVE_PERCENT}`, `{SAVE_AMOUNT}`, `{PRICE}`, `{SPECIAL_PRICE}`, `{FINAL_PRICE}`, `{SPECIAL_ENDS_IN}`, `{SPECIAL_END_DATE}`) rendered empty in `products` queries that did not ask for prices ("Sale -%"); asking for `openlabel_labels` now loads the price attributes.

## [1.0.0-rc1] - 2026-10-11

First release candidate of OpenLabel 1.0: engine (M1), admin (M2), Hyvä render (M3), GraphQL, export/import, Smile importer and documentation (M4). Everything in `Api/` follows semantic versioning from 1.0.0. Supported: Magento 2.4.7–2.4.9, PHP 8.2–8.4, Hyvä 1.4 (1.3 best effort; on 1.5 listings work, product-page labels not yet).

### Added
- M4 S6 · README for 1.0 (one-line pitch, the three install commands, a five-minute quick start, compatibility (Hyvä 1.4 tested; 1.3 best effort; 1.5 listings only, product gallery not yet), caching notes, support promise and Discussions); documentation site (`mkdocs.yml`, built with `--strict` in CI, published to GitHub Pages on release tags) with new pages for installation/upgrade/uninstall and the CLI; draft Hyvä compatibility tracker entry (`docs/tracker-entry.md`); `dev-main` is aliased `1.0.x-dev`.
- M4 S4 · German storefront translations (`i18n/de_DE.csv`: day and hour units of {SPECIAL_ENDS_IN} and {NEW_FOR}); a unit test keeps `en_US.csv` complete.
- M4 S3 · `bin/magento openlabel:import-smile [--dry-run]` copies the labels of the open-source Smile product label module (read straight from its `smile_productlabel` tables; the module need not be installed): each becomes an image design and a label "attribute is option" with listing/product placements (top/lower left/right), store views and status; it goes through the regular importer (same checks, one transaction). Labels already imported (same name) are skipped, so it can run again; missing images and unknown positions are reported.
- M4 S2 · `bin/magento openlabel:export [file] [--labels=1,2]` and `openlabel:import <file> [--dry-run] [--replace]`: labels with their designs, store-view texts, conditions, placements and images (embedded) as one portable JSON file; store views and customer groups travel by code, built-in designs by name. Import is all or nothing: the whole file is checked against the shop first (codes, design references, condition classes, label and design rules, image validation with SVG sanitizing, custom CSS sanitizing), then written in one transaction and reindexed; `--replace` updates labels and designs with the same name.
- M4 S1 · GraphQL: `openlabel_labels` on `ProductInterface` returns the labels visible for the request's store view (`Store` header) and customer group (customer token), one entry per placement, as structured data (`id name type text image_url alt position area css_class bg_color text_color priority`; `text` is plain text with the variables filled in, never HTML). All products of a query are resolved with one label query (batch resolver); disabled, out-of-window and other groups' labels are never exposed, nor are conditions. Cached GET responses carry `openlabel_<id>` and `openlabel_design_<id>` tags, so label and design saves purge them. New core dependencies `magento/module-graph-ql`, `magento/module-catalog-graph-ql`, `magento/module-customer-graph-ql`.
- M3 S2 · Storefront label markup: `ViewModel\LabelRenderer::getStacksHtml($product, $area, $loading)` renders one `ol-stack` per occupied position with `ol-label ol-label--text|image|shape ol-d-<id> ol-shape-*` badges (template `base/stacks.phtml`, no inline styles, logical `ol-pos-*` classes, physical `ol-pos-tl|tr|ml|mr|bl|br` for placements that keep their side in RTL, `ol-p-<id>` for offsets and gap); image labels carry width, height, alt and the product image's `loading`; variables rendered with only the review/sales preloads the page's texts need; `data-ol-label` in debug mode. `ViewModel\Labels::remember()` resolves a whole collection with the first label lookup (one query per listing). `Block\Css` (the single `<link>`, generated on first use) and `Block\Identities` (page cache tags `openlabel_<label_id>` and `openlabel_design_<design_id>`). The generator accepts extra structural sources from theme packages (`sources` DI argument).
- M3 S1 · Generated stylesheet: one file per store view at `pub/media/openlabel/<store_id>/openlabel.<hash>.css` with the structural `ol-*` CSS, one custom-property line per design (`.ol-d-<id>`), one per placement with offsets or gap (`.ol-p-<id>`) and the sanitized custom CSS; rebuilt after every design and label save, by `bin/magento openlabel:css:regenerate` and by the new "Regenerate CSS" button in the settings; generated on first use after a fresh install; previous files are kept two days for pages still in the page cache.
- M2 S1 · 15 built-in designs (idempotent data patch; existing system designs are never overwritten, missing ones restored) and `Model\Design\Contrast` (WCAG ratio and level).
- M2 S2 · Admin menu Catalog › OpenLabel › Labels / Designs; labels grid (design preview, placements summary, matched product count, active-now filter, store views) with inline edit of name, status, priority and dates (shop timezone ↔ UTC) and mass enable/disable/delete; designs grid with mass delete that keeps built-in and in-use designs. New core dependency `magento/module-ui`.
- M2 S3 · Design form: Text/Image/Shape cards, colour pickers with a live contrast badge, variable picker and 24-character counter, per-store-view text, image upload with content-type check, size limits, SVG sanitizing and stored dimensions, custom CSS sanitizer behind `Iranimij_OpenLabel::custom_css`; built-in designs locked with "Duplicate to edit".
- M2 S4 · Label form: visual design picker, priority, hide other labels, apply to parents, store views, customer groups, schedule in the shop timezone, placement rows; Save, Save & Continue, Save & Duplicate (disabled copy), Delete; validation shown above the form without a reload.
- M2 S5 · "Show when": switches for On sale, New, Low stock, Out of stock and Well rated stored as built-in conditions next to the advanced rule tree (combined with AND).
- M2 S6 · "Show where": 3×3 position picker, per-page design override, max labels, stacking, gap, offsets, keep left/right in RTL.
- M2 S7 · Live preview panel (sample values, desktop/mobile, contrast warning, Preview as a customer group) and `view/base/web/css/openlabel.css`, the structural `ol-*` stylesheet the storefront generator will reuse.
- M2 S8 · Matched products section with the first 50 products and "Reindex now".
- M2 S9 · Empty-state starters (Sale, New, Low stock) and a feedback link.
- M2 S10 · Settings: default max labels per position, label indexer mode hint, debug mode; `docs/admin.md`; coverage gate now also covers Model/Css and Model/Image.
- M1 S1 · Engine data layer: declarative schema for `openlabel_label`, `openlabel_design`, `openlabel_design_store`, `openlabel_placement`, `openlabel_index` (+ replica); `Api/Data` interfaces for Label, Design (store-scoped text, alt text, tooltip) and Placement; `LabelRepositoryInterface` and `DesignRepositoryInterface` (placements and store texts are saved with their parent); validators with actionable messages; ACL resources `Iranimij_OpenLabel::openlabel`, `::labels`, `::designs`, `::custom_css`; uninstall class. Index rows use `customer_group_id = -1` for "all groups".
- M1 S2 · Rule engine: `Model\Rule\Rule` (condition tree evaluated in SQL through the core `Sql\Builder`), `Combine` and `Product` conditions (native product attributes incl. store-scoped ones, via the catalog widget condition), built-in conditions On sale (on_sale, discount %, discount amount from the price index per website and customer group), Is new (news dates, days since created/updated), Stock (salable and salable quantity, MSI with legacy fallback), Price range (final/regular price), Rating (stars) and Review count, plus a DI `ConditionPool` for custom conditions.
- M1 S3 · Indexer `openlabel_product` (mview, depends on price and stock indexers): full rebuild into `openlabel_index_replica` with an atomic table swap and a lock, partial rebuilds for changed products and their configurable/grouped/bundle parents, one row per customer group for price labels, parent rows for `apply_to_parent` labels, label reindex on save with cache cleaning bounded by the diff, plugins on the price, stock and review updates, hourly window-transition cron and daily is-new cron.
- M1 S4 · Variables: `VariableProcessorInterface` + DI `Pool` with {SAVE_PERCENT} {SAVE_AMOUNT} {PRICE} {SPECIAL_PRICE} {FINAL_PRICE} {STOCK_QTY} {NEW_FOR} {SKU} {ATTR:code} {BR} {SPECIAL_ENDS_IN} {SPECIAL_END_DATE} {RATING} {REVIEW_COUNT} {SOLD_LAST_30D}; `Renderer` with locale number, currency and date formatting, escaping and the HTML allow-list (b strong i em br span small sup sub); `VariablePreloadInterface` so review and sales data load once per listing; empty/zero variables are reported for the 1.1 hide flag.
- M1 S5 · `LabelResolverInterface` (one SELECT per listing or product page over the index joined with labels, placements, designs and store texts; status, time window, customer group, hide-lower-priority and max-per-stack applied) returning `ResolvedLabelInterface` DTOs, and the `ViewModel\Labels` (memoized per request, customer group from the HTTP context, respects the enable switch). Labels limited to customer groups now index one row per allowed group even without price conditions.
- M1 S6 · CLI: `openlabel:reindex [label_id]` (full rebuild through the indexer, or one label with its diff) and `openlabel:preview <label_id> [--store=<id>]` (matched count and the first 20 SKUs from the index).
- M1 S7 · `docs/engine.md`; unit coverage gate in CI (≥ 85 % on Model/Variable, Model/Condition, Model/Resolver, Model/Rule); 2,000-product full-reindex budget test (main and nightly).
- Module skeleton `Iranimij_OpenLabel` (M0): system configuration section `openlabel` under the Iranimij tab with `general/enabled` (default on), ACL resources, translations, CI template (PHPCS, PHPStan level 6, unit and integration matrix Magento 2.4.7/2.4.8/2.4.9 × PHP 8.2/8.3/8.4).
- `docs/hyva-integration.md`: verified Hyvä 1.4.2 hook points, events and container-query rules.

### Fixed
- `openlabel:reindex` and `openlabel:preview` run in the adminhtml area: right after a cache flush they failed with "Area code is not set" while building a label's condition tree.
- Label and design changes now also clean the application cache (theme block caches such as Hyvä's one-hour product card cache), not only the page cache; a design save cleans `openlabel_design_<id>` even when only a store text changed. Found by the M3 storefront purge spec.

[Unreleased]: https://github.com/iranimij/openlabel/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/iranimij/openlabel/compare/v1.0.0-rc2...v1.0.0
[1.0.0-rc2]: https://github.com/iranimij/openlabel/compare/v1.0.0-rc1...v1.0.0-rc2
[1.0.0-rc1]: https://github.com/iranimij/openlabel/releases/tag/v1.0.0-rc1
