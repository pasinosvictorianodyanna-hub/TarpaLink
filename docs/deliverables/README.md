# Deliverables

The submission documents, as `.docx`. They are **snapshots**, not a build output that CI keeps in
step with the markdown in `docs/`.

| File | What it is |
|---|---|
| `TarpaLink-SRS.docx` | The software requirements specification, rendered from `docs/SRS.md` |
| `TarpaLink-SDD.docx` | The software design document — the only copy of it in this repository |
| `Guide-to-Writing-the-SRS-and-SDD.docx` | The writing guide the two documents above follow |

## They do not update themselves

Change `docs/SRS.md` and `TarpaLink-SRS.docx` still says what it said the day it was rendered.
Nothing fails when they drift apart, which is the same shape as the defects in `docs/ISSUES.md`
#82 and #100: a published claim with nothing behind it, discovered by a reader rather than by a
test.

If you edit the markdown, re-render before submitting. The pipeline lives in `output/` — ignored by
git on purpose (#111), because it is 3.4 MB of intermediate markdown, HTML in two languages, and
build scripts, and five copies of the same text in history helps nobody. `output/tarpalink-docs/`
holds `build_doc.py` and the `sdd-part*.md` sources; `stage3/` is where the `.docx` come out.

## Why the SRS is here twice

`docs/SRS.md` is the source of truth and is versioned per pass — it carries the changelog, the
requirement tables and the honest gaps. The `.docx` is the same text formatted for a reader who
will not open a markdown file. When the two disagree, the markdown is right.
