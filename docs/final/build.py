#!/usr/bin/env python3
"""Assemble the final documentation into one A4 HTML file, then print it to PDF.

    python build.py                  # writes TarpaLink-Final-Documentation.html
    python build.py --toc-pages p.json   # same, with page numbers read from p.json

The page numbers come from a second pass: print the PDF, read which page each heading
landed on, write that map to JSON, rebuild. Chrome cannot resolve a cross-reference to a
page number, so the only way to put one in a table of contents is to measure it.

Markdown supported: # / ## / ### headings, pipe tables, fenced code, blockquotes, ordered
and unordered lists, bold, italic, inline code, and one extension —

    [[FIG:context|Figure 14.1 — The system context.]]

which inlines docs/diagrams/context.svg and prints the caption beneath it.
"""
import html
import io
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
DIAGRAMS = os.path.join(ROOT, "docs", "diagrams")
OUT_HTML = os.path.join(HERE, "TarpaLink-Final-Documentation.html")

PARTS = [
    "01-intro.md",
    "02-requirements.md",
    "03-quality.md",
    "04-design.md",
    "05-data.md",
    "06-deploy.md",
]

COURSE = "Web Systems and Technologies 2 · System Integration and Architecture 1"
TITLE = "TarpaLink"
SUBTITLE = "Final Documentation"
BLURB = "Software Requirements Specification and System Design"


# ---------------------------------------------------------------- inline markup

def inline(t: str) -> str:
    t = html.escape(t, quote=False)
    t = re.sub(r"`([^`]+)`", r"<code>\1</code>", t)
    t = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", t)
    t = re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"<em>\1</em>", t)
    return t


def split_row(line: str) -> list:
    cells = [c.strip() for c in line.strip().strip("|").split("|")]
    return cells


def table_html(rows: list) -> str:
    body = [r for r in rows if not re.match(r"^\|[\s\-:|]+\|$", r)]
    if not body:
        return ""
    head = split_row(body[0])
    out = ["<table>", "<thead>", "<tr>" + "".join(f"<th>{inline(c)}</th>" for c in head) + "</tr>", "</thead>"]
    if len(body) > 1:
        out.append("<tbody>")
        for r in body[1:]:
            cells = split_row(r)
            # a row may be short if the source dropped a trailing empty cell
            while len(cells) < len(head):
                cells.append("")
            out.append("<tr>" + "".join(f"<td>{inline(c)}</td>" for c in cells) + "</tr>")
        out.append("</tbody>")
    out.append("</table>")
    return "\n".join(out)


def figure_html(name: str, caption: str) -> str:
    path = os.path.join(DIAGRAMS, name + ".svg")
    if not os.path.isfile(path):
        raise SystemExit(f"missing diagram: {path}")
    svg = io.open(path, encoding="utf-8").read().strip()
    svg = re.sub(r"<\?xml[^>]*\?>", "", svg).strip()
    # the figure is sized by CSS, so the intrinsic width/height attributes only fight it
    svg = re.sub(r'(<svg[^>]*?)\swidth="[^"]*"', r"\1", svg, count=1)
    svg = re.sub(r'(<svg[^>]*?)\sheight="[^"]*"', r"\1", svg, count=1)

    label, _, text = caption.partition("—")
    cap = f'<span class="figlabel">{inline(label.strip())}</span> — {inline(text.strip())}' if text else inline(caption)
    return f'<figure class="fig">{svg}<figcaption>{cap}</figcaption></figure>'


# ---------------------------------------------------------------- block parser

def parse(src: str):
    lines = src.split("\n")
    blocks = []
    i, n = 0, len(lines)
    while i < n:
        raw = lines[i]
        s = raw.strip()

        if not s:
            i += 1
            continue

        if s.startswith("```"):
            i += 1
            buf = []
            while i < n and not lines[i].strip().startswith("```"):
                buf.append(lines[i])
                i += 1
            i += 1
            blocks.append(("code", "\n".join(buf)))
            continue

        m = re.match(r"^(#{1,3})\s+(.*)$", s)
        if m:
            blocks.append((f"h{len(m.group(1))}", m.group(2).strip()))
            i += 1
            continue

        m = re.match(r"^\[\[FIG:([a-z0-9-]+)\|(.+)\]\]$", s)
        if m:
            blocks.append(("fig", (m.group(1), m.group(2).strip())))
            i += 1
            continue

        if s.startswith("|"):
            rows = []
            while i < n and lines[i].strip().startswith("|"):
                rows.append(lines[i].strip())
                i += 1
            blocks.append(("table", rows))
            continue

        if s.startswith(">"):
            buf = []
            while i < n and lines[i].strip().startswith(">"):
                buf.append(lines[i].strip().lstrip(">").strip())
                i += 1
            blocks.append(("quote", " ".join(x for x in buf if x)))
            continue

        m = re.match(r"^(\d+)\.\s+(.*)$", s)
        if m:
            items = []
            while i < n:
                mm = re.match(r"^\s*(\d+)\.\s+(.*)$", lines[i])
                if not mm:
                    break
                items.append(mm.group(2).strip())
                i += 1
                while i < n and lines[i].startswith("   ") and lines[i].strip():
                    items[-1] += " " + lines[i].strip()
                    i += 1
            blocks.append(("ol", items))
            continue

        if s.startswith("- "):
            items = []
            while i < n and lines[i].strip().startswith("- "):
                item = lines[i].strip()[2:].strip()
                i += 1
                while i < n and lines[i].startswith("  ") and lines[i].strip() and not lines[i].strip().startswith("- "):
                    item += " " + lines[i].strip()
                    i += 1
                items.append(item)
            blocks.append(("ul", items))
            continue

        buf = [s]
        i += 1
        while i < n:
            nxt = lines[i].strip()
            if (not nxt or nxt.startswith("#") or nxt.startswith("|")
                    or nxt.startswith("```") or nxt.startswith(">")
                    or nxt.startswith("[[FIG:") or re.match(r"^\d+\.\s", nxt)
                    or nxt.startswith("- ")):
                break
            buf.append(nxt)
            i += 1
        blocks.append(("p", " ".join(buf)))

    return blocks


def render(blocks):
    body, headings = [], []
    chapter = 0

    for kind, payload in blocks:
        if kind == "h1":
            chapter += 1
            hid = f"ch-{chapter}"
            headings.append((1, hid, payload))
            body.append(f'<h1 id="{hid}">{inline(payload)}</h1>')
        elif kind == "h2":
            hid = f"ch-{chapter}-{len([h for h in headings if h[0] == 2])}"
            headings.append((2, hid, payload))
            body.append(f'<h2 id="{hid}">{inline(payload)}</h2>')
        elif kind == "h3":
            body.append(f"<h3>{inline(payload)}</h3>")
        elif kind == "p":
            body.append(f"<p>{inline(payload)}</p>")
        elif kind == "code":
            body.append(f"<pre><code>{html.escape(payload)}</code></pre>")
        elif kind == "quote":
            body.append(f'<blockquote><p>{inline(payload)}</p></blockquote>')
        elif kind == "table":
            body.append(table_html(payload))
        elif kind == "ol":
            body.append("<ol>" + "".join(f"<li>{inline(x)}</li>" for x in payload) + "</ol>")
        elif kind == "ul":
            body.append("<ul>" + "".join(f"<li>{inline(x)}</li>" for x in payload) + "</ul>")
        elif kind == "fig":
            body.append(figure_html(*payload))

    return body, headings


# ---------------------------------------------------------------- page furniture

CSS = """
:root {
  --ink: #14161a;
  --muted: #5b6167;
  --rule: #d2d6da;
  --tint: #f3f5f7;
  --accent: #1d3a4f;
  --body: "Georgia", "Charter", "Times New Roman", serif;
  --head: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
  --mono: "Consolas", "SF Mono", "Courier New", monospace;
}

* { box-sizing: border-box; }

html, body { margin: 0; padding: 0; }

body {
  font-family: var(--body);
  font-size: 10.2pt;
  line-height: 1.55;
  color: var(--ink);
  background: #fff;
  hyphens: auto;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

p { margin: 0 0 0.55em; text-align: justify; }
p + p { text-indent: 0; }

h1, h2, h3 { font-family: var(--head); color: var(--ink); hyphens: none; }

h1 {
  font-size: 19pt;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin: 0 0 0.15em;
  padding-bottom: 0.28em;
  border-bottom: 1.6pt solid var(--ink);
  break-before: page;
  break-after: avoid;
}
h1:first-of-type { break-before: auto; }

h2 {
  font-size: 12.6pt;
  font-weight: 600;
  margin: 1.35em 0 0.35em;
  padding-left: 0.55em;
  border-left: 2.6pt solid var(--accent);
  break-after: avoid;
}

h3 {
  font-size: 10.6pt;
  font-weight: 600;
  margin: 1.05em 0 0.25em;
  color: var(--accent);
  break-after: avoid;
}

strong { font-weight: 700; }
em { font-style: italic; }

code {
  font-family: var(--mono);
  font-size: 8.7pt;
  background: var(--tint);
  padding: 0.05em 0.25em;
  border-radius: 2px;
}

pre {
  background: var(--tint);
  border-left: 2.6pt solid var(--rule);
  padding: 7pt 10pt;
  margin: 0.7em 0;
  overflow: hidden;
  break-inside: avoid;
}
pre code { background: none; padding: 0; font-size: 8.4pt; line-height: 1.42; }

ul, ol { margin: 0.35em 0 0.75em; padding-left: 1.25em; }
li { margin-bottom: 0.25em; text-align: left; }
li::marker { color: var(--muted); }

blockquote {
  margin: 0.8em 0;
  padding: 0.15em 0 0.15em 0.9em;
  border-left: 2.6pt solid var(--accent);
  color: var(--ink);
  break-inside: avoid;
}
blockquote p { font-style: italic; margin-bottom: 0.25em; }

table {
  width: 100%;
  border-collapse: collapse;
  margin: 0.7em 0 1em;
  font-family: var(--head);
  font-size: 8.2pt;
  line-height: 1.34;
  break-inside: auto;
}
thead { display: table-header-group; }
tr { break-inside: avoid; }
th {
  text-align: left;
  font-weight: 600;
  color: #fff;
  background: var(--accent);
  padding: 3.4pt 5pt;
  border: none;
}
td {
  padding: 3.2pt 5pt;
  border-bottom: 0.5pt solid var(--rule);
  vertical-align: top;
}
tbody tr:nth-child(even) td { background: #fafbfc; }

figure.fig {
  margin: 1em 0 1.15em;
  padding: 0;
  break-inside: avoid;
}
figure.fig svg {
  display: block;
  width: 100%;
  height: auto;
  border: 0.6pt solid var(--rule);
  background: #fff;
  padding: 6pt;
}
figcaption {
  font-family: var(--head);
  font-size: 8pt;
  line-height: 1.4;
  color: var(--muted);
  margin-top: 0.4em;
  text-align: left;
}
.figlabel { font-weight: 600; color: var(--ink); }

/* ---------------- cover ---------------- */

.cover {
  break-after: page;
  height: 245mm;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
}
.cover .kicker {
  font-family: var(--head);
  font-size: 8.6pt;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 2.2em;
  text-align: center;
}
.cover h1.title {
  font-family: var(--head);
  font-size: 46pt;
  font-weight: 600;
  letter-spacing: -0.025em;
  border: none;
  margin: 0;
  padding: 0;
  break-before: auto;
}
.cover .rule {
  width: 42mm;
  height: 2pt;
  background: var(--ink);
  margin: 1.1em auto;
}
.cover .sub {
  font-family: var(--head);
  font-size: 17pt;
  font-weight: 400;
  margin: 0 0 0.2em;
  text-align: center;
  text-indent: 0;
}
.cover .blurb {
  font-family: var(--body);
  font-size: 11pt;
  font-style: italic;
  color: var(--muted);
  margin: 0.4em 0 0;
  text-align: center;
  text-indent: 0;
}
.cover .foot {
  margin-top: 4.2em;
  font-family: var(--head);
  font-size: 9pt;
  color: var(--muted);
  line-height: 1.7;
  text-align: center;
}

/* ---------------- document control ---------------- */

.control { break-after: page; }
.control h1 { break-before: auto; }
.control table { font-size: 8.6pt; }
.control td:first-child { width: 32%; font-weight: 600; }

/* ---------------- contents ---------------- */

.toc { break-after: page; }
.toc h1 { break-before: auto; }
.toc ol { list-style: none; padding: 0; margin: 0.8em 0 0; }
.toc > ol > li {
  font-family: var(--head);
  font-size: 10pt;
  font-weight: 600;
  margin: 0.62em 0 0.16em;
  display: flex;
  align-items: baseline;
}
.toc > ol > li > a { color: var(--ink); text-decoration: none; }
.toc .subs { list-style: none; padding: 0; margin: 0.1em 0 0.5em 1.4em; }
.toc .subs li {
  font-family: var(--head);
  font-size: 8.6pt;
  font-weight: 400;
  color: var(--muted);
  margin: 0.1em 0;
  display: flex;
  align-items: baseline;
}
.toc .subs a { color: var(--muted); text-decoration: none; }
.toc .dots {
  flex: 1;
  border-bottom: 0.5pt dotted #b9bec4;
  margin: 0 0.4em;
  min-width: 1em;
}
.toc .pg {
  font-family: var(--head);
  font-size: 8.6pt;
  color: var(--muted);
  font-variant-numeric: tabular-nums;
}

.part-label {
  font-family: var(--head);
  font-size: 8pt;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--muted);
  margin: 1.6em 0 0.2em;
}
"""


def build_toc(headings, pages=None):
    rows = []
    open_sub = False
    for lvl, hid, text in headings:
        page = pages.get(text.strip()) if pages else None
        pg = f'<span class="pg">{page}</span>' if page else ""
        if lvl == 1:
            if open_sub:
                rows.append("</ul>")
                open_sub = False
            rows.append(
                f'<li><a href="#{hid}">{inline(text)}</a><span class="dots"></span>{pg}</li>'
            )
        else:
            if not open_sub:
                rows.append('<ul class="subs">')
                open_sub = True
            rows.append(
                f'<li><a href="#{hid}">{inline(text)}</a><span class="dots"></span>{pg}</li>'
            )
    if open_sub:
        rows.append("</ul>")
    return '<nav class="toc"><h1 id="contents">Contents</h1><ol>' + "\n".join(rows) + "</ol></nav>"


def main():
    pages = None
    if "--toc-pages" in sys.argv:
        path = sys.argv[sys.argv.index("--toc-pages") + 1]
        if os.path.isfile(path):
            pages = json.load(io.open(path, encoding="utf-8"))

    all_blocks = []
    for part in PARTS:
        src = io.open(os.path.join(HERE, part), encoding="utf-8").read()
        all_blocks.extend(parse(src))

    body, headings = render(all_blocks)

    cover = f"""
<section class="cover">
  <div class="kicker">Capstone Project</div>
  <h1 class="title">{TITLE}</h1>
  <div class="rule"></div>
  <p class="sub">{SUBTITLE}</p>
  <p class="blurb">{BLURB}</p>
  <div class="foot">
    {COURSE}<br>
    A job-order platform for tarpaulin and print shops
  </div>
</section>
"""

    control = """
<section class="control">
  <h1 id="document-control">Document control</h1>
  <table>
    <tbody>
      <tr><td>Document</td><td>TarpaLink — Final Documentation (SRS and System Design)</td></tr>
      <tr><td>Version</td><td>1.0, issued for final submission</td></tr>
      <tr><td>Status</td><td>Complete. Requirements and design are frozen at the commit this was built from.</td></tr>
      <tr><td>Prepared by</td><td>&nbsp;</td></tr>
      <tr><td>Instructor</td><td>&nbsp;</td></tr>
      <tr><td>Institution</td><td>&nbsp;</td></tr>
      <tr><td>Date</td><td>25 November 2026</td></tr>
      <tr><td>Source of truth</td><td>This document is a snapshot. The living requirements are <code>docs/SRS.md</code> in the repository; where the two disagree, that file is right.</td></tr>
      <tr><td>Companion files</td><td><code>docs/ARCHITECTURE.md</code> (design rationale), <code>docs/SCHEMA.md</code> (full data dictionary), <code>docs/DEPLOY-RENDER.md</code> (deployment reference), <code>docs/DEPLOY-CHECKLIST.md</code> (deployment runbook)</td></tr>
    </tbody>
  </table>

  <h2>How to read this document</h2>
  <p>Part I states what the system must do. Part II states how it is built. A reader who has twenty
  minutes should read chapter 2 (objectives), chapter 3 (scope) and chapter 23 (the decisions worth
  disagreeing with). A reader checking completeness should read chapter 11, which names the test
  that proves every requirement, and chapter 12, which states what is not done.</p>
  <p>Three chapters are worth reading in the order they appear: chapter 15 (use cases) says who does
  what, chapter 18 (lifecycle) says what order it happens in, and chapter 20 (schema) says what is
  kept afterwards. Everything else supports one of those three.</p>
</section>
"""

    doc = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{TITLE} — {SUBTITLE}</title>
<style>{CSS}</style>
</head>
<body>
{cover}
{control}
{build_toc(headings, pages)}
{chr(10).join(body)}
</body>
</html>
"""

    io.open(OUT_HTML, "w", encoding="utf-8").write(doc)
    print(f"wrote {OUT_HTML} ({len(headings)} headings, {len(body)} blocks)")


if __name__ == "__main__":
    main()
