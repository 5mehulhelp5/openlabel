# GraphQL

OpenLabel adds one field to `ProductInterface`, so every product type in every product query has it:

```graphql
{
  products(filter: { sku: { eq: "24-MB04" } }) {
    items {
      sku
      openlabel_labels {
        id name type text image_url alt position area css_class bg_color text_color priority
      }
    }
  }
}
```

| Field | Meaning |
|---|---|
| `id`, `name` | Label ID and admin name |
| `type` | Design type: `text`, `image` or `shape` |
| `text` | Text for the store view with variables filled in, as plain text (`{BR}` becomes a line break). `null` for image labels |
| `image_url`, `alt` | Image labels only |
| `position` | `tl tc tr ml mc mr bl bc br` |
| `area` | `listing` or `product` |
| `css_class` | The `ol-*` classes the storefront uses; load the generated stylesheet to reuse them |
| `bg_color`, `text_color` | Hex colours of the design |
| `priority` | 0 is the highest |

## Rules

- One entry per placement: a label shown on listings and on the product page appears twice, once per `area`.
- The store view comes from the `Store` header, the customer group from the customer token (guests are NOT LOGGED IN).
  Only enabled labels inside their time window and allowed for that group are returned; conditions are never exposed.
- Configurable, grouped and bundle products return the labels of matching children when the label has "apply to
  parents" on, like the storefront.
- All products of one query cost one label query (batch resolver), however many products the query returns.
- With the module disabled for a store view the field returns an empty list.
- Asking for `openlabel_labels` makes the product query load the price attributes the variables need, so
  `{SAVE_PERCENT}`, `{PRICE}` and friends match the storefront. `{ATTR:code}` shows a value only when the query
  also asks for that attribute (the storefront has the same rule: the attribute must be loaded on the page).

## Caching

GET queries are cacheable. Responses carry the tags `openlabel_<label_id>` and `openlabel_design_<design_id>`, which
label and design saves clean, plus the usual product tags. Customers' queries vary by customer group through Magento's
`X-Magento-Cache-Id`: send the value of the previous response back, as Magento's own clients do, and Varnish serves
logged-in customers from the cache too.
