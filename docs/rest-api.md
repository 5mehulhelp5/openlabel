# REST API

From 1.1, labels and designs can be created, read, changed and deleted over Magento's REST API, for example by a PIM
or ERP that adds a "Clearance" label during its product import. The storefront needs none of this: product labels for
headless storefronts come from [GraphQL](graphql.md).

## Access

Every call needs a token of an admin user or an integration. The role decides what the token may do:

| Routes | Permission (System › Permissions › User Roles › Role Resources) |
|---|---|
| `/V1/openlabel/labels…` | Catalog › OpenLabel › Labels (`Iranimij_OpenLabel::labels`) |
| `/V1/openlabel/designs…` | Catalog › OpenLabel › Designs (`Iranimij_OpenLabel::designs`) |
| custom CSS of a design | also Edit custom CSS of designs (`Iranimij_OpenLabel::custom_css`) |

```bash
TOKEN=$(curl -s -X POST https://shop.example/rest/V1/integration/admin/token \
  -H 'Content-Type: application/json' -d '{"username":"api-user","password":"…"}' | tr -d '"')
curl -s https://shop.example/rest/V1/openlabel/labels/12 -H "Authorization: Bearer $TOKEN"
```

Without a token, or with a role that lacks the permission, the answer is `401`.

## Routes

| Method and path | What it does |
|---|---|
| `GET /V1/openlabel/labels/:labelId` | One label with its placements |
| `GET /V1/openlabel/labels?searchCriteria…` | Labels matching Magento search criteria (filters, sort, pages) |
| `POST /V1/openlabel/labels` | Creates a label: body `{"label": {…}}` |
| `PUT /V1/openlabel/labels/:labelId` | Replaces the label: body `{"label": {…}}` |
| `DELETE /V1/openlabel/labels/:labelId` | Deletes the label |
| `GET /V1/openlabel/designs/:designId` | One design |
| `GET /V1/openlabel/designs?searchCriteria…` | Designs matching search criteria |
| `POST /V1/openlabel/designs` | Creates a design: body `{"design": {…}}` |
| `PUT /V1/openlabel/designs/:designId` | Replaces the design: body `{"design": {…}}` |
| `DELETE /V1/openlabel/designs/:designId` | Deletes the design (refused while labels use it) |

`PUT` replaces the whole entity: send every field, as `GET` returned it, with your changes. Fields left out take their
defaults. `created_at` and `updated_at` may be sent back; they are ignored.

A saved label is matched to its products at once, and the pages that show it are cleaned from the cache, exactly as
when it is saved in the admin. A saved design rebuilds the label stylesheet.

## A label

```json
{
  "label": {
    "name": "Clearance",
    "status": 1,
    "priority": 5,
    "design_id": 2,
    "store_ids": [],
    "customer_group_ids": [],
    "valid_from": null,
    "valid_to": "2026-12-31 23:59:59",
    "conditions_serialized": "{\"type\":\"Iranimij\\\\OpenLabel\\\\Model\\\\Rule\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":\"1\",\"conditions\":[{\"type\":\"Iranimij\\\\OpenLabel\\\\Model\\\\Rule\\\\Condition\\\\Product\",\"attribute\":\"sku\",\"operator\":\"()\",\"value\":\"24-MB01, 24-MB04\"}]}",
    "apply_to_parent": true,
    "hide_lower_priority": false,
    "hide_on_zero_variable": false,
    "click_url": "/sale.html",
    "click_nofollow": false,
    "placements": [
      { "area": "listing", "position": "tr" },
      { "area": "product", "position": "tl", "max_labels": 2 }
    ]
  }
}
```

- `status`: `1` enabled, `0` disabled. `priority`: `0` is the highest.
- `store_ids`, `customer_group_ids`: empty means all.
- `valid_from`, `valid_to`: UTC, or `null`.
- `conditions_serialized`: the conditions as JSON text in Magento's rule format, the same as in an
  [export file](import-export.md). The easiest way to get it right is to build the conditions in the admin once and
  read the label with `GET`. Empty or `null` matches every product.
- Placements: `area` is `listing`, `product`, `cart`, `minicart`, `wishlist`, `compare` or `email`; `position` as in
  [GraphQL](graphql.md). The other placement fields (`offset_x`, `offset_y`, `max_labels`, `stacking`, `gap`,
  `pin_physical_side`, `sort_order`) are optional.

Products assigned by hand on the product edit page are not part of the label in the API.

## A design

```json
{
  "design": {
    "name": "Clearance (orange)",
    "type": "shape",
    "shape": "pill",
    "bg_color": "#c2410c",
    "text_color": "#ffffff",
    "size_mode": "percent",
    "width": 24,
    "font_size": 14,
    "animation": "none",
    "hover_effect": "grow",
    "sizes": { "sm": { "width": 40 } },
    "store_texts": {
      "0": { "text": "Clearance -{SAVE_PERCENT}%", "tooltip": "Only while stock lasts" },
      "2": { "text": "Abverkauf -{SAVE_PERCENT}%" }
    }
  }
}
```

- `store_texts` maps a store view ID to its `text`, `alt_text` and `tooltip`; `0` is the default for all store views.
  In answers it is a JSON object, or a list when the IDs are 0, 1, 2…, where the position is the store ID. Both forms
  can be sent back.
- `sizes` holds overrides for phones (`sm`), tablets (`md`) and desktops (`lg`): `width` and `font_size`.
- Image designs (`"type": "image"`): upload the image in the admin first, then use its `image_path` (e.g.
  `designs/clearance.png`). Paths of files that were not uploaded there are refused.
- `custom_css` is saved only when the token's role has "Edit custom CSS of designs"; otherwise the design keeps the
  CSS it had. It is sanitized like in the admin.
- The built-in designs (`"system": true`) cannot be changed or deleted, and a new design cannot be marked as built in.

## Errors

| Status | When |
|---|---|
| `400` | The entity breaks a rule, e.g. no placement, an unknown position, a link starting with `javascript:`. The answer lists every problem under `errors`. Also for built-in designs and images that were not uploaded. |
| `401` | No token, or the role lacks the permission |
| `404` | No label or design with that ID |
