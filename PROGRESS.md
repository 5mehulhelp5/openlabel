# PROGRESS

Fine-grained checkpoint for the OpenLabel build. Updated with every merged PR. The Notion build log (07 · Build Brief, section 7) holds the per-milestone summary.

## Current milestone

M3 · Hyvä render: **done** (2026-10-11), except the Hyvä 1.3 end-to-end leg (open question for Iman, below). M2 · Admin: **done** (merged 2026-10-11; Iman's recorded real-person ≤ 3 min test still open). M1 · Engine done 2026-10-10. M0 · Foundation done 2026-10-09. Next: M4 · GraphQL, export/import, Smile importer, release 1.0.0, in a new session.

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

M4 in a new session. Open for Iman: Hyvä 1.3 e2e leg (its `magento2-reset-theme` is not public), recorded real-person UX tests (M2 ≤ 3 min, M3 VoiceOver check). Earlier note: M3 S2: open the PR, then S3 in `openlabel-hyva` (listing slot: Product\\Image plugin, collection preload, list-item cache tags). Earlier note kept for M2: M3 · Hyvä render, in a new session. Before that: Iman reviews PR #12 (S1); then retarget the S10 PR to `main` and close #13–#20 as in M1, and Iman runs the recorded ≤ 3 min custom-label test. Ledger: `openlabel-dev-env/modules/M2-ledger.md`.

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
| openlabel-dev-env | — | — | Playwright 63 (25 admin incl. login setup, 1 theme fallback, 37 storefront desktop + mobile); Lighthouse gate on/off |

## Open questions for Iman

- Packagist: register `iranimij/module-base` after the `v1.0.0` tag (CI pre-install scripts fall back to a VCS repository until then) and mark the two old packages abandoned (composer.json already carries the field).
- Hyvä 1.3 end-to-end leg: Hyvä 1.3 needs `hyva-themes/magento2-reset-theme`, which is not in Hyvä's public GitHub repositories. Options: your portal keys as a GitHub secret on openlabel-dev-env (adds a 1.3 leg), or test 1.4 only and state "1.3: best effort" in the README. CI runs Hyvä 1.4.2 from the public OSL repositories (your choice, 2026-10-11).
- Hyvä 1.5.x is out (theme 1.5.2, theme-module 1.5.2); the fixture is on theme 1.4.2. Add a 1.5 leg in M4?
- Recorded real-person tests: M2 custom label ≤ 3 min; M3 VoiceOver/NVDA reading "Sale -25%" with the product name (the accessible name is asserted automatically).

## Known failing or skipped tests

- `Test/Integration/Performance/FullReindexTest` is skipped unless `OPENLABEL_PERF=true` (CI sets it on `main` and nightly, not on pull requests; agreed 2026-10-09). (Playwright `configurable-selection-changed` test skips only if the sample-data configurable product `radiant-tee` is missing.)

## Fixture gotchas (see openlabel-dev-env README)

Local Docker shares 7.6 GB with another Magento stack: stop that stack's OpenSearch during integration and Lighthouse runs (M3 restarted ours twice after OOM). Composer `--no-plugins` in the Warden container; background jobs via `setsid nohup` inside the container; CSP enforce flag set via SQL; OpenSearch image pinned to 2.5.0; admin 2FA and URL secret keys disabled.

## Last CI run

- openlabel main, full matrix: https://github.com/iranimij/openlabel/actions/runs/38118188377 (19/19)
- openlabel-hyva main, full matrix: https://github.com/iranimij/openlabel-hyva/actions/runs/38116551394
- openlabel-dev-env E2E (Playwright 63/63 + Lighthouse gate, Hyvä 1.4.2, Varnish): https://github.com/iranimij/openlabel-dev-env/actions/runs/38126950152
