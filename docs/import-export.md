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

One JSON document: `{"format": "openlabel", "version": 2, "designs": [...], "labels": [...]}`.

- **Labels:** name, status, priority, schedule (UTC), condition tree, apply to parents, hide lower priority,
  hide when a variable is zero, click-through link and its nofollow flag, and the placements with their per-area
  design.
- **Designs:** every design the exported labels use, with all store-view texts, the custom CSS, the entry
  animation, the hover effect and the per-breakpoint sizes. Image designs carry the image file (base64), so the
  file is self-contained.
- **Versions:** OpenLabel 1.1 writes version 2 and still reads version 1 files from OpenLabel 1.0; the fields
  added in 1.1 then take their defaults (no animation, no link, no size overrides).
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

## Moving from the Smile product label module

```bash
bin/magento openlabel:import-smile --dry-run
bin/magento openlabel:import-smile
```

The command reads the `smile_productlabel` tables directly, so it works even after the Smile module was disabled
(keep its tables and `pub/media/smile_productlabel/imagelabel/` until you are done). Every Smile label becomes:

- an image design `Smile: <name>` with the label's image and alt text (18 % of the image width; adjust it in the
  design form),
- a label with the same name, status and store views, the condition "<attribute> is <option>", and a placement on
  listings and/or the product page at the same corner (`top-left` → top left, `lower-right` → bottom right, …).

Labels whose name already exists in OpenLabel are skipped, so you can run the command again after fixing a
problem. A Smile label whose image file or attribute is missing is reported and skipped; everything else goes
through the same checks and the same single transaction as `openlabel:import`. Importers for paid modules are not
part of 1.0.
