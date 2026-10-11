# Export and import

Keep labels in git, copy them from staging to production, or share a set of labels with another shop.

```bash
bin/magento openlabel:export var/openlabel.json            # every label
bin/magento openlabel:export var/sale.json --labels=3,7    # some labels
bin/magento openlabel:export > labels.json                 # to stdout

bin/magento openlabel:import var/openlabel.json --dry-run  # check only
bin/magento openlabel:import var/openlabel.json            # add the labels
bin/magento openlabel:import var/openlabel.json --replace  # update labels and designs with the same name
```

## What the file contains

One JSON document: `{"format": "openlabel", "version": 1, "designs": [...], "labels": [...]}`.

- **Labels:** name, status, priority, schedule (UTC), condition tree, apply to parents, hide lower priority,
  and the placements with their per-area design.
- **Designs:** every design the exported labels use, with all store-view texts and the custom CSS. Image designs
  carry the image file (base64), so the file is self-contained.
- **Built-in designs** are referenced by name (`system:<name>`) and are never copied: the importing shop uses its
  own.
- **Store views and customer groups** are referenced by code (`default`, `General`, `Wholesale`), not by id, so
  a file works in a shop whose ids differ. The admin store view is `admin`.

## How an import behaves

All or nothing. The whole file is checked against the shop first, and nothing is written when anything is wrong;
the command lists what to fix:

```text
Nothing was imported. Fix these and run again:
  - labels[0] "Sale": this shop has no store view with code "de".
```

Checked before anything is written: store view and customer group codes, design references, condition classes
(only OpenLabel's and those registered in its condition pool), the same rules as the admin forms, images (type,
size, SVG sanitizing, as for uploads) and custom CSS (sanitized as in the design form). Then designs, images and
labels are written in one database transaction, and each label is indexed as it is saved.

- Without `--replace`, labels and designs are always added, so importing the same file twice gives two copies.
- With `--replace`, a label or design with the same name is updated instead; built-in designs are never changed.
- An image that already exists with the same content is reused; a different file with the same name gets a new
  name, so existing designs never change their picture.
