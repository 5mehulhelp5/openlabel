# CLI

| Command | What it does |
|---|---|
| `openlabel:reindex` | Full rebuild of the label index (lock, replica table, atomic swap, cache cleaning by diff). |
| `openlabel:reindex <label_id>` | Rebuild one label and print how many products were added and removed. |
| `openlabel:preview <label_id> [--store=<id>]` | How many products a label matches in a store view, and the first 20 SKUs. |
| `openlabel:css:regenerate` | Rebuild the generated stylesheet of every store view (also a button in the settings). |
| `openlabel:export [file] [--labels=1,2]` | Labels with their designs and images as JSON, to a file or stdout. See [Export and import](import-export.md). |
| `openlabel:import <file> [--dry-run] [--replace]` | Import such a file, all or nothing. |
| `openlabel:import-smile [--dry-run]` | Copy the labels of the Smile product label module. |

All commands run in the admin area, so they also work right after `cache:flush`.

## Examples

```bash
bin/magento openlabel:preview 3 --store=1
bin/magento openlabel:export var/openlabel/labels.json
bin/magento openlabel:import var/openlabel/labels.json --dry-run
```
