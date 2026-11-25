#!/usr/bin/env python3
"""Measure which page each heading landed on, so the table of contents can carry numbers.

    python toc_pages.py <document.pdf> <out.json>

Chrome cannot resolve a cross-reference to a page number, so the only way to put one in a
table of contents is to print the document first and read the pages back. Run this after
build.py, then rebuild with --toc-pages.
"""
import io
import json
import os
import re
import sys

import pymupdf

HERE = os.path.dirname(os.path.abspath(__file__))
PARTS = ["01-intro.md", "02-requirements.md", "03-quality.md",
         "04-design.md", "05-data.md", "06-deploy.md"]

# The first body paragraph. Everything before the page that carries it is front matter,
# and the contents page itself repeats every heading — which would otherwise win the search.
BODY_MARKER = "requirements and design record"


def norm(s: str) -> str:
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def main():
    pdf_path, out_path = sys.argv[1], sys.argv[2]

    headings = []
    for part in PARTS:
        src = io.open(os.path.join(HERE, part), encoding="utf-8").read()
        for line in src.split("\n"):
            m = re.match(r"^(#{1,2})\s+(.*)$", line.strip())
            if m:
                headings.append(m.group(2).strip())

    doc = pymupdf.open(pdf_path)
    pages = [norm(doc[i].get_text()) for i in range(doc.page_count)]

    body_start = next((i for i, t in enumerate(pages) if BODY_MARKER in t), 0)

    result = {}
    cursor = body_start
    for h in headings:
        key = norm(h)[:26]
        found = None
        for i in range(cursor, len(pages)):
            if key and key in pages[i]:
                found = i
                break
        if found is None:  # fall back to a whole-document search before giving up
            for i in range(body_start, len(pages)):
                if key and key in pages[i]:
                    found = i
                    break
        if found is not None:
            result[h] = found + 1
            cursor = found
        else:
            print(f"  no page found for: {h}")

    io.open(out_path, "w", encoding="utf-8").write(json.dumps(result, indent=1, ensure_ascii=False))
    missing = [h for h in headings if h not in result]
    print(f"mapped {len(result)}/{len(headings)} headings, body starts on page {body_start + 1}")
    if missing:
        print("missing:", missing)


if __name__ == "__main__":
    main()
