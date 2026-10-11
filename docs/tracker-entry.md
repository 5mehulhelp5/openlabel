# Hyvä compatibility tracker entry (draft)

Draft for the Hyvä compatibility tracker (submitted by Iman after the 1.0.0 release). Not linked from the
documentation navigation.

| Field | Value |
|---|---|
| Module | OpenLabel (product labels) |
| Vendor | Iman Aboheydary (`iranimij`) |
| Composer package | `iranimij/openlabel-hyva-bundle` (core `iranimij/openlabel`, Hyvä package `iranimij/openlabel-hyva`) |
| Repository | https://github.com/iranimij/openlabel |
| Licence | MIT, free |
| Compatibility | Native: Hyvä support is built in through `iranimij/openlabel-hyva`; no `hyva-themes/magento2-*-compat` package is needed |
| Tested Hyvä versions | default theme 1.4.2 (every change); 1.3.x best effort; 1.5.x listings only (product gallery support planned) |
| Tested Magento versions | 2.4.7, 2.4.8, 2.4.9; PHP 8.2 to 8.4 |
| Hyvä Checkout | Not applicable in 1.0 (labels on listings and the product page only) |
| Notes | No Tailwind build needed (OpenLabel generates one small stylesheet per store view); no JavaScript; CSP-clean (no inline styles or scripts); labels render inside cached pages and vary by customer group through the page cache; right-to-left stores mirror positions |

## Short description

Rule-based product labels ("Sale -25%", "New", "Only 3 left") for listings, search, widgets and the product
gallery. A dedicated indexer keeps a category page at one extra query; labels add no layout shift and need no
theme changes on a stock Hyvä theme.
