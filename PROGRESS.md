# PROGRESS

Fine-grained checkpoint for the OpenLabel build. Updated with every merged PR. The Notion build log (07 · Build Brief, section 7) holds the per-milestone summary.

## Current milestone

**M5 · Release 1.1 · Superset: done** (2026-10-12, released as `v1.1.0` on openlabel, openlabel-hyva and openlabel-hyva-bundle) (started 2026-10-12; plan approved by Iman, recommendations Q1–Q7 applied). Previous: M4 · API, import/export, release: **done** (2026-10-11), released as `v1.0.0` on openlabel, openlabel-hyva and openlabel-hyva-bundle. Recorded real-person tests waived by Iman for now ("no need for human test for now"). M3 · Hyvä render done 2026-10-11 (Hyvä 1.3 leg: best effort, see decisions). M2 done 2026-10-11, M1 2026-10-10, M0 2026-10-09.

## M5 slices

Merged by me when CI is green. Slices were built stacked branch on branch (S3 on S2, S4 on S3, ...) and committed locally while Iman did not want GitHub activity in working hours; on 2026-10-12 evening Iman allowed pushing at any time again, so the whole stack was pushed as stacked PRs (each PR's base is the slice below), CI ran on all of them at once, and the full matrix ran once on the top of the stack. They are merged in order, each PR retargeted to `main` before the one below it is merged. Order and scope from the approved plan; Hyvä Checkout skipped (no licence, Iman 2026-10-12).

| # | Slice | State |
|---|---|---|
| S1 | 1.1 data: label hide-on-zero / click-through / nofollow, design animation / hover / breakpoint sizes, export format 2; fix: `setup:db:status` diff after full reindex | merged (#44, full matrix green) |
| S2 | Conditions: bestseller (units + rank), most viewed (views + rank), ships free; Bestseller quick switch | merged (#45, full matrix green incl. coverage gate after the fix) |
| S3 | Variables `{CATEGORY_NAME}` `{BRAND}` + hide on zero (storefront + GraphQL) | merged (#46) |
| S4 | Design 1.1: breakpoint sizes, animation/hover CSS, global CSS, AVIF + size setting, 31 system designs | merged (#47) + dev-env e2e |
| S5 | 21 positions, near price / add to cart / under name (+ openlabel-hyva) | merged (#48); openlabel-hyva PR #12; dev-env e2e |
| S6 | Tooltip, click-through | merged (#49) + dev-env e2e |
| S7 | Variant-aware labels (product page), countdown | merged (#50); openlabel-hyva PR #13; dev-env e2e |
| S8 | Cart, mini-cart, wishlist, compare, emails, hide on pages | merged (#51); openlabel-hyva PR #14 (+ PHPStan fix: compare list items); dev-env e2e |
| S9 | Product edit tab + manual assignment (`openlabel_label_product`) | merged (#52, carries the QA-pass fixes; + PHPStan fix: phrases passed to the escaper as strings) |
| S10 | REST CRUD (scope cut by Iman 2026-10-12: no GraphQL mutations, no bulk read) | merged (#53); full matrix found two 2.4.7/2.4.8 issues, both fixed: design fixture sizes through `setSizes()`, and `UnstructuredArray` output (2.4.7/2.4.8 cast it element by element: `Plugin/Webapi/DesignOutputPlugin` restores store texts and sizes); full matrix 20/20 green (run 38274764765) |
| S11 | Remote storage (S3) | skipped by Iman 2026-10-12 ("skip it please"): code already writes media through Magento's filesystem; docs/install.md says "should work, not tested"; no MinIO CI leg |
| S12 | Label packs FR/ES/NL/IT | merged (#54) |
| S13 | Close-out, README matrix, 1.1.0 | merged (#55): README comparison with Amasty and Mirasvit (public docs only), 1.1 docs, tooltip stacking fix; openlabel-hyva #15 (README, openlabel-ref back to main); E2E specs openlabel-dev-env #12; releases v1.1.0-rc1 then v1.1.0 (openlabel, openlabel-hyva, bundle) |

### M5 QA pass (2026-10-12, S1–S9, tech lead + product owner + QA, storefront and admin in Chrome)

Fixed and tested (openlabel commits on `feat/m5-s9-product-tab`, openlabel-hyva `e034f13` on `feat/m5-s8-areas`, dev-env `4937073`; they ship with the S8/S9 pushes):
1. Product cards on pages where labels are hidden got the cached card with labels (Hyvä's card cache key has no page type): `-openlabel-off` key suffix.
2. Countdown in order emails showed a frozen timer: emails show the end date.
3. Design image upload ignored the configured maximum size in the admin uploader.
4. Placement summary in the label grid had no words for the item areas (cart, mini cart, ...).
5. Live preview said nothing for item areas: it now explains that these show next to the item.
6. GraphQL `area` doc listed only listing/product.
7. Variant mode switched on for every configurable with a parent label, hiding labels on swatch change: only when a variant has labels of its own.
8. The label stylesheet link ignored "Enable OpenLabel = No": `Block/Css` uses `Labels::isModuleEnabled()`.
9. `setup:upgrade` kept serving the previous version's CSS: `Setup/RecurringData` rebuilds the stylesheet (failure only logs a warning); upgrade docs name static content deploy.
10. Saving the custom CSS in the admin rebuilt the stylesheet with the old value (configuration cached for the request): the saved value is passed to the rebuild.
11. `acceptance-starter.spec.ts` deleted every label on the dev store: it now backs up and restores labels, placements and manual assignments.

Verified: unit openlabel 442 + hyva 46, coverage gate, PHPStan, PHPCS, integration (full), Playwright 102/102, mkdocs strict. Browser: listing bands, inline slots, link labels, tooltips, hover, motion, PDP, variant switch (XS + Blue), countdown ticking, cart, mini cart, compare, wish list, hide on pages; admin labels grid, label form (31 new designs, hide-on-zero, link validation, Bestseller switch, new conditions, 5×5 picker, preview), design form (tooltip, AVIF, sizes, motion), configuration (hidden pages, brand attribute, upload limit, custom CSS), product tab assign + unassign (storefront follows). No console errors.

## M5 decisions

- Q1–Q7 unanswered at approval, recommendations applied: new methods on the existing `Api/Data` interfaces (CHANGELOG notes implementers must add them); 1.1.0 on all three repos (rc first); small Alpine piece for the mini-cart; "recently added to category" moved to 1.2; manual assignment in a new `openlabel_label_product` table; FR/ES/NL/IT by me, "community review wanted"; Amasty/Mirasvit named only in the README comparison.
- New placement areas and positions join `PlacementInterface::AREAS/POSITIONS` only in the slice that renders them (columns are `varchar(16)` already), so nothing can be saved that does not display.
- `click_url` is `varchar(1024)` (declarative-schema maximum); only https://, http:// and same-shop paths.
- Design `sizes` is JSON in a text column (`{"sm": {"width": 24, "font_size": 11}}`), width in the design's size mode.
- `openlabel_index` and its replica carry the same table comment (the full reindex swaps them by renaming).
- Bestseller and most viewed count per website over fixed windows (7/30/90 days sold, 7/30 days viewed) and rank over 30 days (`RANK()`, MariaDB 10.2+/MySQL 8). Each is one aggregated subquery joined by the SQL builder; no temp table. Products with no activity have no rank, so "top N" never matches them. Labels using them are reindexed by the daily cron.
- Hide on zero is applied when a label is rendered, after the priority rules: a hidden label does not free its place in a full stack and its "hide other labels" still applies (the arranger runs in the resolver, before variables are known).
- `{CATEGORY_NAME}` = the browsed category only on category pages (`current_category` without `current_product`); elsewhere the product's deepest active category in the store's tree (cache-safe: never the category the shopper came from). `{BRAND}` = configurable attribute code (`openlabel/variables/brand_attribute`, default `manufacturer`).
- Breakpoints for size overrides: sm ≤ 639 px, md 640–1023 px, lg ≥ 1024 px (viewport media queries, appended to the design's rule). Motion is CSS only: `ol-anim-*` / `ol-hover-*` classes from `DesignRules::labelClasses()` (shared by the storefront renderer and GraphQL); hover reacts to the card (`:hover > .ol-stack > …`) under `(hover: hover)`; `prefers-reduced-motion: reduce` switches animations and transitions off.
- Global CSS lives in its own config section `openlabel_css` (ACL `Iranimij_OpenLabel::global_css`), default scope only, sanitized by the backend model and again by the generator.
- AVIF is validated without image libraries: `ftyp` brand avif/avis plus the first `ispe` box for the size.
- Built-in designs grow to 31 through `SystemDesignsOneDotOne` (re-runs the idempotent catalog install); the designs grid now sorts newest first so merchant designs are not buried behind them.
- Local e2e note: the dev store has two manual labels from 2026-10-11 ("Sale -{SAVE_PERCENT}%" #323, "test" #341) on every product; they make the older storefront specs fail locally (stacks full). Left untouched (Iman's data); CI runs on a fresh store.
- Positions: 21 image positions = 3×3 grid + 12 band positions (`above_l/c/r`, `below_l/c/r`, `left_t/m/b`, `right_t/m/b`; classes `ol-pos-o{t,b,s,e}*`, physical `ol-pos-o{t,b,l,r}*`), absolutely placed just past the image edge (over the card padding, no layout shift). 3 text positions (`near_price`, `near_cart`, `under_name`) render as in-flow `ol-inline` stacks, always horizontal, sized by the design font size. `getStacks()` = image positions only, so 1.0 slots never print text positions.
- Hyvä text slots: listing price = plugin on `FinalPriceBox` (zone `item_list`); listing name/cart = insert after `a.product-item-link` / `[data-addto="cart"]` in the rendered card (`Magento\Catalog\Block\Product\View`, template guard); product page = plugin on `Layout::renderElement` for `product.info.title/price/addtocart` (DI map). Verified on 1.4.2 locally; the 1.5.2 leg runs in CI.
- CSS budget: structural CSS 4.6 KB; the 100-design stylesheet is 20,137 bytes of the 20,480 budget (2 KB gzipped of 8 KB). S6 (tooltip) must stay tiny or compact the design lines (e.g. short hex colours).
- openlabel-hyva requires openlabel ^1.1 (openlabel dev-main aliased 1.1.x-dev). Its `.github/ci/openlabel-ref` points at `feat/m5-s5-positions` while stacked: reset to `main` once openlabel S5 is merged.
- Tooltip = next sibling `ol-tip` of the label (not a child: ribbon shapes clip children), shown by CSS on hover/focus; id `ol-tip-<placement>-<product>` so cached product cards never repeat ids. CSS only, no Alpine (the plan allowed Alpine for touch; focusable labels open on tap via :focus).
- Click-through labels are `<a>` except on the product card image (already inside the product link): there they stay spans, not focusable, tooltip on hover only. Shop paths become absolute store URLs.
- Short hex colours in the generated stylesheet: 100-design stylesheet 19,973 of 20,480 bytes after the tooltip CSS.
- Variant labels: product page only (Feature Superset: "label follows selected swatch on product page"). All variants' labels are in the page, hidden; the script only toggles `ol-label--hidden` (no markup built in JS, CSP-safe). A variant shows the product's own labels (parent rows from children excluded) plus its own, re-arranged with the core Arranger. A variant is "chosen" when Hyvä's `candidates` has exactly one id; otherwise the product's default set shows.
- `{COUNTDOWN}` = end of the special price's last day (same rule as `{SPECIAL_ENDS_IN}`); one `setInterval` for the page, stopped on `visibilitychange`. The script (≈ 0.6 KB gzipped) is rendered by `Block\Script` in `before.body.end` only when `LabelRenderer::isScriptNeeded()`; cached product cards set the flag through `collectIdentities()`.
- Variable markup (`Value::html`) is put back after the allow-list via a private-use placeholder, so `data-ol-end`/`aria-hidden` survive; merchant text is still cleaned.
- Found and fixed a 1.0 bug: flex labels dropped the space between text and inline markup (`white-space: pre-wrap` on `.ol-label`). CSS budget after S7: 20,194 of 20,480 bytes.
- Item areas (cart, minicart, wishlist, compare, email) show one row of text labels with the item; the form stores `under_name` (positions do not apply). No new CSS (reuses `ol-inline`).
- Mini cart without JavaScript (better than Q3's Alpine piece): Hyvä prints each item's `product_price` from the cart section with `x-html`. The label HTML must be appended in `Cart::afterGetSectionData` (sortOrder 1000), after the Tax/Weee plugins that render every item's price again; appending in `DefaultItem::getItemData` is lost.
- Emails: text and shape designs only, inline styles from validated hex colours, resolved for the order's store view and group at send time (price variables show the current price).
- "Hide labels on these pages" is store config (page types + full action names), not per label: no schema change. `Labels::isEnabled()` is false there, so nothing is queried.
- Manual assignment: `openlabel_label_product` merged in `RowBuilder` (rule matches ∪ assigned products that are enabled in the store); the product form section is a catalog form modifier (Labels ACL) with a hidden `openlabel_touched` flag so an empty multiselect still clears assignments; the observer reindexes only labels whose set changed. Not in the export file yet (documented).
- Ships free = the store's free-shipping threshold met by the final price, or an active no-coupon cart rule for the website/group with free shipping and no conditions. Cart rules with conditions are ignored (they depend on the cart, not the product).

## M4 slices

Merged by me when CI is green (Iman, 2026-10-11).

| # | Slice | State |
|---|---|---|
| S1 | GraphQL `openlabel_labels` on `ProductInterface` (batch resolver, structured data, cache identity) | merged (#29, full matrix green); Playwright spec openlabel-dev-env #6 |
| S2 | `openlabel:export` / `openlabel:import` (portable JSON, all or nothing, `--dry-run`, `--replace`) | merged (#30) |
| S3 | `openlabel:import-smile` (Smile tables → converter → S2 importer) | merged (#31) |
| S4 | `de_DE.csv` + i18n test; `openlabel-hyva-bundle` repo (metapackage, `ci-ok` protection); branch alias `dev-main` = `1.0.x-dev` (openlabel #32, openlabel-hyva #8, bundle #1) | merged |
| S5 | README install gate (fresh store, README commands only, quick-start spec) + Hyvä 1.5.2 leg | merged (openlabel-dev-env #7) |
| S6 | README for 1.0, mkdocs site (strict in CI, Pages on tags), install/CLI pages, tracker entry draft | merged (#33) |
| S7 | 1.0.0-rc1 and rc2 (pre-releases), then 1.0.0 on openlabel, openlabel-hyva, bundle; Notion build log; milestone report | done |
| F1 | Hyvä 1.5 product gallery (stacks before the `data-track` scroll track, container-query root on `#gallery`); 1.5.2 leg is a PR gate | merged (openlabel-hyva #10, dev-env #9) |
| F2 | Own rule SQL builder: keeps joins across nested condition groups (quick switches + category crashed the save), same operator semantics on 2.4.7–2.4.9 (no IFNULL; missing data never matches), yes/no conditions only is / is not; operator matrix integration test (every built-in × every operator) | merged (#41) |
| F3 | Logging channel `var/log/openlabel.log` (indexing errors after save, failed imports); admin keeps the label open with a readable error | merged (#41) |
| F4 | Clickable rule-tree parts look like links ("is", "..."); Playwright regression for switches + category | merged (#41, dev-env #10) |
| F5 | "Why no products?" in Matched products: label-level reasons and each condition's own count | merged (#42) |
| F6 | Release 1.0.0 (CHANGELOG, tags, GitHub releases, Pages) | #43 |

## M4 decisions

- GraphQL: one entry per placement; `text` is plain text with variables rendered (no HTML); `name` kept as listed in the Brief (Iman: Q4 not answered, recommendation applied); store from the `Store` header, group from the customer token (`customer_group_id` context attribute, guests NOT LOGGED IN). New core dependencies `magento/module-graph-ql`, `-catalog-graph-ql`, `-customer-graph-ql` (^100.4.6).
- Export format: JSON only (no YAML: would need a non-core dependency). Store views and customer groups by code, built-in designs by name (`system:<name>`), images embedded as base64. Import validates everything first, then writes in one transaction; without `--replace` it always adds.
- Smile importer reuses the regular importer; Smile labels become image designs (18 % width) + "attribute is option" labels; re-runs skip labels with the same name.
- Release: `v1.0.0-rc1` and `-rc2` during M4; Iman then asked for `v1.0.0` without the recorded human tests (2026-10-11).
- Hyvä: 1.4.2 and 1.5.2 on every PR, both gates (1.5 product gallery supported after rc1); 1.3 "best effort" in the README (Q3; portal keys would add a leg).
- E2E legs: a planning job picks the matrix (PRs: Hyvä 1.4 and 1.5; main/nightly/manual: + README leg).
- Condition semantics: a product without the compared data (no price index row, no rating, no stock row, empty attribute) never matches, "is not" included; review count treats no reviews as 0; stock quantity only on simple/virtual/downloadable products. Owned by `Model/Rule/SqlBuilder` (subclass of the core builder), so 2.4.7–2.4.9 behave the same.
- Packagist: openlabel, openlabel-hyva and the bundle registered by Iman (2026-10-11).

## M3 slices

Merged by me as agreed with Iman on 2026-10-11 (no review wait; merge when CI is green; delete merged branches).

| # | Slice | State |
|---|---|---|
| S1 | Stylesheet generator (`Model/Css/{DesignRules,StylesheetGenerator,Storage,Regenerator}`, CLI `openlabel:css:regenerate`, settings button, regenerate on save) | merged (#24) |
| S2 | Label markup (`ViewModel\LabelRenderer`, `base/stacks.phtml`), `Block\Css`, `Block\Identities`, `Labels::remember()`, block-cache cleaning fix | merged (#23) |
| S3 | Hyvä listing slot (Product\Image plugin, collection preload, list-item cache tags) | merged (openlabel-hyva #6, with #4) |
| S4 | Hyvä gallery slot (child block + Gallery plugin) | merged (openlabel-hyva #6, with #5) |
| S5 | Customer groups × Varnish, admin saves purge cached pages (specs) | merged (openlabel-dev-env #2) |
| S6 | Tailwind source registration, CSP, no-rebuild, click-through, legibility, RTL (specs) | merged (openlabel-hyva #6, dev-env #2) |
| S7 | axe-core + Lighthouse on/off gate | merged (dev-env #2) |
| S8 | CI end-to-end job (public-source Hyvä 1.4.2 fixture, Varnish, CSP) | merged (dev-env #2); Hyvä 1.3 leg open |
| S9 | Custom-theme fallback (spec + docs/storefront.md), CLI area fix (#25), 2.4.7 fixture fix (#27, openlabel-hyva #7) | merged |

## M3 decisions

- Stylesheet name carries a 12-hex content hash; the current file is remembered in the cache (no storefront query) and found again from the directory after a flush; previous files stay 2 days for pages still in Varnish/FPC; generated on the first storefront request after a fresh install.
- One generator, theme rules from theme packages through the `sources` DI argument (`openlabel-hyva` adds `.product-item` / `#gallery-main` container roots and hides labels in the fullscreen gallery).
- "Keep left/right in RTL" uses physical classes `ol-pos-tl|tr|ml|mr|bl|br`; placements with offsets/gap get `ol-p-<id>` from the stylesheet (no inline styles).
- Page cache tags: `openlabel_<label_id>`, `openlabel_design_<design_id>` and `cat_p_<id>` of every product card looked at (core product widgets on CMS pages do not tag pages with their products). Label/design changes also clean the application cache (Hyvä's one-hour card cache); design saves clean `openlabel_design_<id>` even for store-text-only changes.
- Loaded storefront product collections (≤ 200 items) are remembered, so a page resolves labels with one query; variables preload only what the page's texts use.
- `openlabel:reindex` / `openlabel:preview` run in the adminhtml area (failed after a cache flush).
- RTL: Magento and Hyvä set no `dir`; documented as a theme layout attribute.
- Lighthouse gate uses the `lighthouse` package directly (module on vs off, 3 viewports, median of 3) instead of `@lhci/cli`; axe runs on the label markup.
- CI e2e fixture: Magento 2.4.9 + sample data from the public mirror, Hyvä from its public OSL GitHub repositories (Iman, 2026-10-11), OpenLabel main branches; Varnish without health probe; first-login admin popups dismissed by the specs.
- Playwright project order: admin (1 worker, one session) → theme fallback → storefront desktop/mobile; storefront spec files use their own products/categories and `sfe2e-<tag>` names.

## M2 slices

S1 merged as #12, S2–S10 through #21 (all M2 commits, full matrix green); #13–#20 closed.

| # | Slice | State |
|---|---|---|
| S1 | 15 system designs (data patch) + WCAG contrast | merged (#12) |
| S2 | Admin menu, routes, label and design grids (inline edit, mass actions, matched count) | merged (#21) |
| S3 | Design form (type cards, colours + contrast badge, per-store text, image upload + SVG sanitizer, custom CSS behind ACL) | merged (#21) |
| S4 | Label form: basics, who-and-when, save bar, duplicate | merged (#21) |
| S5 | Show when: quick toggles + rule tree | merged (#21) |
| S6 | Show where: placements + 3×3 position picker | merged (#21) |
| S7 | Live preview (sticky, desktop/mobile, contrast warning) | merged (#21) |
| S8 | Matched products tab + reindex button | merged (#21) |
| S9 | Empty-state starters | merged (#21) |
| S10 | System config additions + close-out (settings, docs, CHANGELOG, final review fixes) | merged (#21) |

## M1 slices

| # | Slice | State |
|---|---|---|
| S1 | Schema (5 tables + replica), `Api/Data` + repositories with validators, ACL resources, uninstall | done (PR #4, awaiting Iman's review) |
| S2 | Rule model + built-in conditions (OnSale, IsNew, Stock, PriceRange, Rating, ReviewCount) | done (PR #5, stacked on S1, auto-merge) |
| S3 | Indexer: mview, full/list/row, `reindexLabel` diff, parent rows, group rows, replica swap, price/stock/review plugins, crons, cache tags | done (PR #6, stacked on S2, auto-merge) |
| S4 | Variables (15 processors, pool, locale renderer) + HTML allow-list | done (PR #7, stacked on S3, auto-merge) |
| S5 | Resolver (one SELECT) + ViewModel + query-count tests | done (PR #8, stacked on S4, auto-merge) |
| S6 | CLI `openlabel:reindex`, `openlabel:preview` | done (PR #9, stacked on S5, auto-merge) |
| S7 | Close-out: CHANGELOG, docs/engine.md, coverage gate, perf test, build log | done (PR #10, stacked on S6, auto-merge) |

## M0 slices

| # | Slice | State |
|---|---|---|
| S0 | Fixture store `openlabel-dev-env`: Warden env `openlabel.test`, Magento 2.4.9 + sample data, Hyvä 1.4.2 (OSL packages, path repo), Varnish on, storefront CSP enforced, Playwright baseline (20 tests: smoke + Hyvä hook verification, desktop + mobile) | done |
| S1 | `module-base` skeleton + CI template (PHPCS, PHPStan 6 with bitexpert/phpstan-magento, unit + integration matrix 2.4.7-p10/2.4.8-p5/2.4.9 × PHP 8.2/8.3/8.4, `ci-ok` aggregate, Dependabot, issue templates) | done |
| S2 | `module-base` config tab `iranimij` + ACL | done |
| S3 | `module-base` typed helpers `TypedReader`, `SafeJson` | done |
| S4 | `module-base` System › Iranimij › Installed Modules page | done |
| S5 | `module-base` 1.0.0 release (CHANGELOG, LOC guard test, tag) | done when `v1.0.0` tag exists (see Last CI run) |
| S6 | `openlabel` skeleton with config section `openlabel/general/enabled` under tab `iranimij` | done |
| S7 | `openlabel-hyva` skeleton with `hyva_*` layout handles and Tailwind stub | done |
| S8 | `docs/hyva-integration.md`, Notion 02 §4/§5 + 06 F16 updated, entity spec pages (Label, Design, Placement) under 02 · Architecture | done |
| S9 | `iranimij/badger` and `iranimij/magento2-product-labels` marked abandoned (PRs merged; Iman clicks Packagist) | done |
| S10 | Milestone report, build log | done |

## Exact next step

M5 is done. Next: M6 / 1.2 planning when Iman wants it (04 · Roadmap). Merged M5 branches still to delete (openlabel `feat/m5-*`, openlabel-hyva `feat/m5-*`, openlabel-dev-env `test/m5-*`). Open for Iman: review the README comparison table; recorded real-person UX tests (M2 ≤ 3 min custom label, M3 VoiceOver); Hyvä compatibility tracker submission (`docs/tracker-entry.md`); optional operator dropdown on the quick switches; re-create the lost label #341 "test"?

## M1 decisions (also in the Notion build log at close-out)

- `openlabel_index.customer_group_id` is `INT NOT NULL`; `-1` = all groups (Iman, 2026-10-09). No DB default because the declarative-schema XSD only accepts `\d+|null`; the indexer always writes the value.
- `openlabel_label.design_id` FK uses `NO ACTION` (MariaDB reports RESTRICT as NO ACTION, which made `setup:db:status` report a perpetual diff).
- `hide_on_zero_variable` stays in 1.1; no column in M1.
- Unit tests carry both `@dataProvider` and `#[DataProvider]`: 2.4.7 ships PHPUnit 9 (annotations only), 2.4.9 ships PHPUnit 12 (attributes only).
- Built-in conditions contribute SQL as `Zend_Db_Expr` through the core `Magento\Rule\Model\Condition\Sql\Builder`; the native attribute condition extends `Magento\CatalogWidget\Model\Rule\Condition\Product` (new core dependency `magento/module-catalog-widget`), which already handles store-scoped attributes. Rule context (store, website, customer group) is set on `Model\Rule\Rule` and read by the conditions.
- Price-based conditions read the price index, which omits out-of-stock products unless "Display Out of Stock Products" is on.
- Is-new date conditions use the default-scope (store 0) `news_from_date`/`news_to_date`; website overrides of those dates are reachable through the native attribute condition.
- MSI is optional: `Model/Condition/Stock/MsiStockData` resolves the MSI interfaces through the object manager and is only used when `Magento_InventorySalesApi` + `Magento_InventoryIndexer` are enabled (`StockDataResolver`).
- Index rows: `parent_product_id` is NULL for direct matches; a parent row (configurable, grouped, bundle, written only with `apply_to_parent`) carries the id of the lowest matching child. Parents come from `catalog_product_relation` through the entity link field.
- Price changes reach the index through plugins on the price indexer actions (`Rows`, `Full`); catalog price rules already go through the price index, so no separate catalogrule plugin. Stock changes through the stock indexer actions, reviews through `Review::aggregate`. MSI multi-source changes that bypass the legacy stock reach the index on the next product save or full reindex (1.0 limitation, documented).
- Cache cleaning inside indexer actions is deferred and executed by the core `CacheCleaner` plugin after the action; `LabelReindexer` (label save, CLI, cron) cleans immediately.
- The integration test framework replaces the lock manager with a dummy, so lock refusal is unit-tested; the integration database carries the sample catalog, so tests filter their own SKUs.
- Variables: processors return typed `Value`s (text, html, number, currency, date); the `Renderer` formats for the locale, escapes values, keeps `{BR}`, applies the allow-list and leaves unknown variables literal. A zero number renders as "0" and is reported as empty (hiding is the 1.1 flag). `{SPECIAL_ENDS_IN}` treats `special_to_date` as valid through that day (UTC). `{STOCK_QTY}` is the salable quantity (orders reduce it).
- Resolver: a stack (area + position) is limited by the `max_labels` of its highest-priority label; `hide_lower_priority` suppresses higher priority numbers across all areas of that product; the design inside a `ResolvedLabel` carries the store-resolved text in its default slot. The `Labels` view model memoizes per request and returns nothing (no query) when the module is disabled for the store.
- Matrix lessons (2.4.7/2.4.8): from 2.4.8 the core Sql Builder wraps numeric fields in IFNULL(field, 0) (composites need a never-matching sentinel); 2.4.8 keeps out-of-stock products in the price index; CI's Elasticsearch 8 rejects 2.4.7 search writes (tests schedule the search indexer); the 2.4.7 test framework nulls test-case properties (declare them nullable). Run the full matrix on the branch (`gh workflow run CI --ref <branch>`) before merging anything that touches SQL.
- Local PHPStan runs with `modules/phpstan-local.neon` (level 6, generated factories scanned); CI uses `bitexpert/phpstan-magento`. After `setup:di:compile` on the fixture, delete `generated/metadata` or the integration sandbox install fails on the disabled 2FA module.

## Test inventory

| Repo | Unit | Integration | Other |
|---|---|---|---|
| module-base | 19 tests / 48 assertions | 6 tests / 12 assertions | PHPCS clean, PHPStan 6 clean, LOC guard (408 / 1000 lines) |
| openlabel (after M1 S7) | 117 / 340 | 59 / 238 (+ perf budget on main/nightly) | PHPCS zero errors, PHPStan 6 clean, coverage gate ≥ 85 % (Variable 96.7, Condition 97.7, Resolver 100, Rule 98.3) |
| openlabel (after M2) | 203 | 153 (1 skipped: perf) | PHPCS zero errors, PHPStan 6 clean, coverage gate ≥ 85 % now also over `Model/Css` and `Model/Image` |
| openlabel (after M3) | 245 / 667 | 173 (1 skipped on PRs: perf budget) — full matrix green, run 38118188377 | PHPCS zero errors, PHPStan 6 clean, coverage Variable 88.0, Condition 97.9, Resolver 100, Rule 100, Css 91.7, Image 90.5 % |
| openlabel-hyva (after M3) | 24 | 5 | PHPCS clean, PHPStan 6 clean |
| openlabel (1.0.0) | 313 | about 220 (+ GraphQl, ImportExport, Smile, operator matrix, diagnosis, logging) | PHPCS zero errors, PHPStan 6 clean, coverage gate ≥ 85 % also over `Model/GraphQl`; full matrix green |
| openlabel-dev-env | — | — | Playwright 63 (25 admin incl. login setup, 1 theme fallback, 37 storefront desktop + mobile); Lighthouse gate on/off |

## Open questions for Iman

- Hyvä 1.3 end-to-end leg: Hyvä 1.3 needs `hyva-themes/magento2-reset-theme`, which is not in Hyvä's public GitHub repositories. Options: your portal keys as a GitHub secret on openlabel-dev-env (adds a 1.3 leg), or test 1.4 only and state "1.3: best effort" in the README. CI runs Hyvä 1.4.2 from the public OSL repositories (your choice, 2026-10-11).
- Recorded real-person tests: M2 custom label ≤ 3 min; M3 VoiceOver/NVDA reading "Sale -25%" with the product name (the accessible name is asserted automatically).

- Label "test" (#341, 5 listing top-left placements, priority 0, no conditions) and #323 were deleted on 2026-10-12 by the old `acceptance-starter.spec.ts` run on the dev store (no binlog, not recoverable). Re-create it?

## Known failing or skipped tests

- `Test/Integration/Performance/FullReindexTest` is skipped unless `OPENLABEL_PERF=true` (CI sets it on `main` and nightly, not on pull requests; agreed 2026-10-09). (Playwright `configurable-selection-changed` test skips only if the sample-data configurable product `radiant-tee` is missing.)

## Fixture gotchas (see openlabel-dev-env README)

Local Docker shares 7.6 GB with another Magento stack: stop that stack's OpenSearch during integration and Lighthouse runs (M3 restarted ours twice after OOM). Composer `--no-plugins` in the Warden container; background jobs via `setsid nohup` inside the container; CSP enforce flag set via SQL; OpenSearch image pinned to 2.5.0; admin 2FA and URL secret keys disabled.

## Last CI run

- openlabel main, full matrix: https://github.com/iranimij/openlabel/actions/runs/38118188377 (19/19)
- openlabel-hyva main, full matrix: https://github.com/iranimij/openlabel-hyva/actions/runs/38116551394
- openlabel-dev-env E2E (Playwright 63/63 + Lighthouse gate, Hyvä 1.4.2, Varnish): https://github.com/iranimij/openlabel-dev-env/actions/runs/38126950152
