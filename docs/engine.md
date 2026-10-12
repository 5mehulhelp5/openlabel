# The engine (M1)

How OpenLabel decides which label shows on which product. Everything below the admin and the storefront:
schema, rule conditions, the indexer, variables, the resolver and the CLI. Written during milestone M1; the
admin (M2), Hyvä rendering (M3) and GraphQL / import (M4) build on it.

## Data model

| Table | Holds |
|---|---|
| `openlabel_label` | the *when and where*: name, status, priority (0 = highest), store views, customer groups, `valid_from` / `valid_to` (UTC), the serialized condition tree, the default design, `apply_to_parent`, `hide_lower_priority` |
| `openlabel_design` | the *how it looks*: type (text, image, shape), colours, size, rotation, custom CSS, `is_system` |
| `openlabel_design_store` | text, alt text and tooltip per store view (store 0 = default, other stores fall back to it) |
| `openlabel_placement` | one *where* per label: area (`listing`, `product`), position (`tl … br`), optional design override, offsets, `max_labels`, stacking, gap |
| `openlabel_index` | the result: (`label_id`, `product_id`, `store_id`, `customer_group_id`) with the label's priority and window copied in; `customer_group_id = -1` means every group |

Repositories: `LabelRepositoryInterface` saves a label with its placements, `DesignRepositoryInterface` saves a
design with its store texts. Both validate and answer with actionable messages. A design that labels still
use cannot be deleted. Everything in `Api/` follows semantic versioning from 1.0.

## Conditions

A label's conditions are a core `Magento\Rule` tree (ALL / ANY, nested) stored as JSON in
`conditions_serialized`. Leaves are either the native product-attribute condition (every attribute,
category, SKU, attribute set; store-scoped attributes are evaluated per store view) or one of the built-ins:

| Built-in | Attributes | Source |
|---|---|---|
| On sale | `on_sale` (yes/no), `discount_percent` (rounded half up), `discount_amount` | price index of the store's website and the customer group, so special prices and catalog price rules both count |
| Is new | `news_dates` (inside *Set Product as New* dates, default scope), `days_since_created`, `days_since_updated` | product entity |
| Stock | `is_salable` (yes/no), `salable_qty` | MSI stock of the website's sales channel, legacy stock status without MSI; quantity applies to simple, virtual and downloadable products only |
| Price range | `final_price`, `price` | price index |
| Rating | `rating` (stars) | review summary of the store view |
| Review count | `review_count` | review summary of the store view |
| Bestseller (1.1) | `sold_last_7d`, `sold_last_30d`, `sold_last_90d` (units), `sales_rank_30d` (1 = best) | order items of the website's store views; canceled and refunded quantities do not count; a configurable sale counts for the configurable |
| Most viewed (1.1) | `views_last_7d`, `views_last_30d`, `view_rank_30d` | product view events of Magento's reports (`report_event`; keep product view reports enabled) |
| Ships free (1.1) | `free_shipping` (yes/no) | an active cart rule without coupon and without conditions that gives free shipping to the website and group, or the product's final price (price index) reaching the free shipping method's minimum order amount |

**Operators.** Numeric built-ins offer *is*, *is not*, *equals or greater than*, *greater than*, *equals or less
than*, *less than*, *is one of* and *is not one of* (e.g. "days since created is less than 30", "salable quantity
is one of 1, 2, 3"). Yes/no built-ins offer *is* and *is not*. Native attributes keep Magento's operators for their
type (text: contains, is one of…; dates: is, on or after, on or before; category: is, is not, is one of…). Every
condition is tested with every operator it offers (`Test/Integration/Model/Condition/OperatorMatrixTest`).

**Missing data never matches**, whatever the operator, *is not* included: a product without a price index row
for price conditions, without a rating for `rating`, without a stock row (and composites) for `salable_qty`, and a
product without a value for a product attribute condition (e.g. "news from date on or before 2020-01-01" does not
pick up products without that date). A product without reviews has `review_count` 0, so "review count is 0" finds
products nobody reviewed yet. Likewise products without sales or views count 0 units / 0 views but have **no
rank**, so "bestseller rank at most 10" only picks products that sold. This holds on every supported Magento version: 2.4.7 and 2.4.8 would otherwise
treat missing values as 0.

**Daily refresh.** Bestseller, most viewed, ships free and the "days since" conditions change with time, not with
product saves: labels using them are reindexed by the daily cron (`openlabel_daily_is_new`, despite its name it covers every time-dependent condition), so a new
bestseller appears within a day. Run `bin/magento openlabel:reindex <label_id>` to refresh one sooner.

Matching happens in SQL: the tree is attached to a product collection with `Iranimij\OpenLabel\Model\Rule\SqlBuilder`,
the core rule builder with three changes. It keeps the joins of every condition when it recurses into nested groups
(the core one drops those collected before a nested group, which broke quick switches combined with advanced
conditions). And it writes the SQL of every condition itself (the core operators, without the `IFNULL(field, 0)` of 2.4.7 and
2.4.8), so the rules above hold on every Magento version. And a condition may join a bounded subquery instead of
a table (the 7/30/90-day sales and view counts with their `RANK()`). PHP `validate()` exists
on every condition for previews and tests. Custom conditions: extend
`Iranimij\OpenLabel\Model\Condition\AbstractBuiltIn` and register the class in the
`Iranimij\OpenLabel\Model\Condition\ConditionPool` `types` argument.

Two consequences worth knowing:

- Price-based conditions read the price index. Depending on the Magento version and *Display Out of Stock
  Products*, the price index may omit out-of-stock products; those then get no price labels.
- Errors while matching are logged to `var/log/openlabel.log` (with the label and its conditions); the admin shows
  the cause and keeps the label saved.
- Disabled products never match. Children of configurable, grouped and bundle products do, which is what
  `apply_to_parent` builds on.

## Indexer

`openlabel_product` (Index Management › *OpenLabel Products*) depends on the price and stock indexers.

- **Full reindex** builds into `openlabel_index_replica`, computes the products that gained or lost a label,
  swaps the tables atomically and cleans the full page cache for exactly those products. A lock prevents
  two full reindexes from overlapping.
- **Partial reindex** (Update on Save, or the changelog in Update by Schedule) rebuilds the rows of the
  changed products plus their configurable / grouped / bundle parents and siblings.
- **Per label**: saving a label rebuilds its rows at once, whatever the indexer mode, and cleans the cache
  for the diff plus the `openlabel_<id>` tag. Deleting a label drops its rows. `openlabel:reindex <id>` and
  the daily cron do the same.
- **Rows**: one per store view the label is limited to (all active store views otherwise). One row per
  allowed customer group when the label is limited to groups or uses a price condition, otherwise one row with
  `customer_group_id = -1`. With `apply_to_parent`, a parent gets a row when any child matches; the row
  remembers the lowest matching child in `parent_product_id`.
- **Triggers**: mview subscriptions on the product, category, website, relation, stock item and review
  summary tables; plugins on the price indexer (`Rows`, `Full`), the stock indexer and review aggregation
  so price, stock and review changes arrive even though index tables are never subscribed (that would flood
  the changelog). A full price or stock reindex invalidates the OpenLabel index.
- **Time**: `valid_from` / `valid_to` are copied into the index and filtered at resolve time, so no cron
  activates labels. Two small crons remain: hourly, clean the cache of labels whose window opened or closed
  during the last hour (a label can therefore appear up to one hour late on cached pages); daily, rebuild
  labels whose conditions depend on the date ("is new", days since created).
- **Known 1.0 limitation**: on multi-source MSI shops, source-item changes that bypass the legacy stock
  item reach the index on the next product save, stock indexer run or full reindex.

## Variables

Label text may contain variables, substituted at render time from the already loaded product (no extra
queries for prices and stock):

`{SAVE_PERCENT}` `{SAVE_AMOUNT}` `{PRICE}` `{SPECIAL_PRICE}` `{FINAL_PRICE}` `{STOCK_QTY}` `{NEW_FOR}` `{SKU}`
`{ATTR:code}` `{BR}` `{SPECIAL_ENDS_IN}` `{SPECIAL_END_DATE}` `{RATING}` `{REVIEW_COUNT}` `{SOLD_LAST_30D}`

Numbers, currency amounts and dates are formatted for the store's locale. Values are escaped; the text itself
may use `b strong i em br span small sup sub` (attributes other than `class`, `id`, `title`, `style` are
removed). Unknown variables stay literal so typos are visible. A zero number renders as "0" and is reported as
empty, which the 1.1 "hide label when a variable is zero/empty" flag will use. `{RATING}`, `{REVIEW_COUNT}` and
`{SOLD_LAST_30D}` load their data once per listing through `VariablePreloadInterface`; `{SPECIAL_ENDS_IN}`
treats `special_to_date` as valid through that day. Custom variables implement
`Iranimij\OpenLabel\Api\VariableProcessorInterface` and are registered in the
`Iranimij\OpenLabel\Model\Variable\Pool` `processors` argument.

## Resolver

`Iranimij\OpenLabel\Api\LabelResolverInterface::getForProducts(int[] $productIds, int $storeId, int $customerGroupId)`
runs **one SELECT** over the index joined with labels, placements, designs and store texts, filtered by status,
UTC time window and `customer_group_id IN (-1, group)`, then arranges the rows: priority order, `hide_lower_priority`
(a matching label with the flag suppresses labels with a higher priority number on that product), and the
per-stack limit (a stack is one area + position; its limit is the `max_labels` of its highest-priority label).

Templates use `Iranimij\OpenLabel\ViewModel\Labels`: call `getForProducts()` once with every product id of the
page, then `getStacks($productId, 'listing')` per item. Results are memoized per request; the customer group
comes from the HTTP context, which the full page cache already varies on. When `openlabel/general/enabled`
is off for the store, the view model returns nothing without a query.

Budget, enforced by integration tests with a query counter: a listing of 36 products costs one query, a
product page costs one query.

## CLI

```
bin/magento openlabel:reindex            # full rebuild through the indexer (lock, swap, cache diff)
bin/magento openlabel:reindex 3          # one label, prints the diff
bin/magento openlabel:preview 3 --store=1 # matched count and the first 20 SKUs from the index
```
