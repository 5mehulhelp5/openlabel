# Admin

Milestone M2. Everything lives under **Catalog › OpenLabel**: **Labels** (when and where) and **Designs** (how it looks). Designs are reusable: make one "red pill" and use it in many labels.

## First label in one click

While there are no labels, the Labels grid shows three starters. Each creates an enabled label with a built-in design, applies it to configurable, grouped and bundle parents, and places it on the category listing and the product page:

| Starter | Shows on | Position |
|---|---|---|
| Sale -{SAVE_PERCENT}% | every discounted product | top start |
| New (30 days) | products created in the last 30 days | top end |
| Only {STOCK_QTY} left | products with 5 or fewer pieces left (salable quantity) | bottom start |

The positions were checked free of collisions on the Hyvä 1.4 product card. A second click within a minute does not create a duplicate. The starter's message tells you how many products match.

## Labels grid

Columns: status, name, design preview, where it shows ("Listing TR, Product TL"), matched products (distinct products in the index), active now (with the schedule in the shop timezone), from/to, priority and store views. Filter by name, status, store view or "active now". Click a row to edit name, status, priority or dates inline; mass actions enable, disable or delete. Every change reindexes the label and cleans the cached pages it affects.

## Label form

| Section | What it does |
|---|---|
| Basics | Name (admin only), switch it on or off, pick a design from thumbnails (or create one in a new tab and press "Refresh designs"), priority (lower number wins), "hide other labels", "hide when a variable is zero" (e.g. leave "Only {STOCK_QTY} left" out when the quantity is 0), a link (a page of this shop such as `/sale.html`, or `https://…`; `javascript:` and other schemes are refused) with "ask search engines not to follow the link", "show on configurable, grouped and bundle products". |
| Show when | Switches for the common cases — On sale (minimum discount %), New (days), Low stock (pieces left), Out of stock, Well rated (stars), Bestseller (among the top N by units sold in the last 30 days) — and the advanced rule tree for anything else (any product attribute, category, SKU, attribute set, type). A product must match the switches **and** the tree. Nothing switched on means every product. |
| Show where | One row per placement: page (category/search/widgets, product page, and since 1.1 the shopping cart, mini cart, wish list, compare page and order emails, where labels show as text next to the item), position with the picker (arrow keys work): the 3×3 grid on the image, the dashed band around it (above, below, left, right), or in the product text next to the price, next to Add to Cart or under the name, a different design for this page, max labels in that corner, stacking, gap, offsets and "keep left/right in RTL". |
| Who and when | Store views ("All Store Views" = everywhere), customer groups (empty = everyone), start and end in the shop timezone. A date without a time ends at the end of that day. Stored in UTC. |
| Matched products | After the first save: how many products match in the default store view, the first 50 with thumbnails, what to check when nothing matches, and **Reindex now**. |

The **live preview** panel (bottom right, can be hidden) renders the chosen design on a sample product with the same structural CSS as the shop: every placement of the selected page, variables filled with sample values ({SAVE_PERCENT} → 25), desktop and mobile widths, a warning when text contrast is below 4.5:1, and "Preview as" a customer group.

Save, Save & Continue and Save & Duplicate (creates a disabled copy). Problems are listed above the form without a page reload, each saying what to do, for example "Pick at least one placement so the label appears somewhere."

## On the product page (1.1)

Catalog › Products › edit a product › **OpenLabel** (needs the Labels permission):

- **Labels this product shows now**, from the index (any store view and customer group), each linking to its
  label; "(by hand)" marks the ones assigned here.
- **Always show these labels**: the product shows them whatever their conditions, within their store views,
  customer groups and dates. Saving the product reindexes the labels you add or remove.

Assignments are not part of the export file yet; they go with their label or product when either is deleted.

## Designs

Choose **Text**, **Image** or **Shape**; the form shows only what that type needs.

- **Text and shape:** label text with a variable picker and a 24-character counter, colours with a live contrast badge (green ≥ 4.5:1, amber ≥ 3:1, red below), border, shape (rectangle, pill, circle, ribbon, corner) and rotation presets.
- **Image:** PNG, JPG, GIF, WebP, AVIF or SVG up to 1 MB (the limit is a setting; a warning above 50 KB). The real content type is checked; SVGs are cleaned (no scripts, event handlers, external references or entities). Width and height are stored so images never shift the layout. Alternative text is required.
- **Size:** percent of the product image width (default) or pixels. Optional overrides of the width and the largest font size for phones (up to 639 px wide), tablets (640–1023 px) and desktops (1024 px and up).
- **Motion:** an entry animation (fade in, pop in) that plays once, and a hover effect (grow, lift) while the shopper points at the product. CSS only; not on touch screens for hover, and never for shoppers who ask their device for reduced motion.
- **Tooltip:** optional, per store view, variables allowed (e.g. "Offer ends {SPECIAL_END_DATE}"). Shown when the shopper points at the label or moves to it with the keyboard.
- **Text per store view:** each store view uses the default text unless you untick "Use the default text".
- **Custom CSS:** only for users with the "Edit custom CSS of designs" permission; `@media`, `@supports` and `@container` blocks are kept; other at-rules (such as `@import`), expressions, script URLs and escapes are removed before it is saved.

The 31 built-in designs are locked: open one and press **Duplicate to edit**. They pass WCAG AA contrast. Designs used by labels and built-in designs cannot be deleted.

## Settings

Stores › Configuration › Iranimij › OpenLabel:

| Setting | Default | |
|---|---|---|
| Enable OpenLabel | Yes | Per store view. Labels stay indexed while disabled. |
| Default max labels per position | 3 | For new placements (1–10). |
| Label indexer | — | Shows the current mode; "Update by Schedule" is recommended. |
| Debug mode | No | Adds `data-ol-label` attributes to the shop markup (render package, M3). |
| Hide labels on these pages | none | Page types without labels (home, category, search, product, other content pages, cart, wish list, compare); no query is made there. Flush the page cache after a change. |
| Hide labels on these other pages | empty | More pages by full action name, one per line (e.g. `brand_index_view`). |
| Brand attribute (Label text variables) | `manufacturer` | The attribute `{BRAND}` shows. |
| Largest image upload (Images) | 1024 KB | 50 to 5120 KB. |

**Global CSS** has its own section (Stores › Configuration › Iranimij › OpenLabel custom CSS) and permission: CSS added after all designs in the label stylesheet of every store, cleaned like the custom CSS of designs. Saving it rebuilds the stylesheet.

## Permissions

| ACL resource | Allows |
|---|---|
| `Iranimij_OpenLabel::labels` | Labels grid, form, starters, matched products, reindex |
| `Iranimij_OpenLabel::designs` | Designs grid, form, uploads |
| `Iranimij_OpenLabel::custom_css` | Editing custom CSS of designs |
| `Iranimij_OpenLabel::global_css` | The global CSS settings section |
| `Iranimij_OpenLabel::config` | The settings section |
