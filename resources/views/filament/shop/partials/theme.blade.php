{{--
    One set of tokens and primitives for every custom shop-panel screen.

    `@once` keeps the rules out of the DOM when several widgets on the same page
    include this partial. Each consumer only needs a root element carrying
    `.tl-wrap`; the rest of the classes read the variables from there.
--}}
@once
    <style>
        .tl-wrap {
            --tl-paper: #F5F3ED;
            --tl-rule: #E2DED2;
            --tl-ink: #0C0F0E;
            --tl-muted: #6B6F6C;
            --tl-card: #FFFFFF;
            --tl-bar: #0C0F0E;
            --tl-bar-past: #D8D4C6;
            --tl-good: #0F7A4F;
            --tl-wait: #B26A00;
            --tl-bad: #A3261B;
        }
        .dark .tl-wrap {
            --tl-paper: #1C1F1E;
            --tl-rule: #2E3331;
            --tl-ink: #F2F0EA;
            --tl-muted: #A8AEAA;
            --tl-card: #242827;
            --tl-bar: #F2F0EA;
            --tl-bar-past: #4E5452;
            --tl-good: #6EE7A8;
            --tl-wait: #F3C969;
            --tl-bad: #F0918A;
        }

        .tl-card {
            background: var(--tl-card); border: 1px solid var(--tl-rule);
            border-radius: 10px; overflow: hidden;
        }
        .tl-card > header {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: baseline;
            justify-content: space-between; padding: 13px 16px;
            border-bottom: 1px solid var(--tl-rule);
        }
        .tl-card h3 { margin: 0; font-size: 13px; font-weight: 650; color: var(--tl-ink); }
        .tl-card header .meta { font-size: 11.5px; color: var(--tl-muted); }

        .tl-stat {
            background: var(--tl-card); border: 1px solid var(--tl-rule);
            border-radius: 10px; padding: 14px 16px;
        }
        .tl-stat .lbl {
            font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
            color: var(--tl-muted); font-weight: 600; display: block; margin-bottom: 4px;
        }
        .tl-stat .val { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: var(--tl-ink); }
        .tl-stat .sub { font-size: 11.5px; color: var(--tl-muted); margin-top: 2px; }
        .tl-stat.is-held .val { color: var(--tl-wait); }

        table.tl-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        table.tl-tbl th {
            text-align: left; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
            color: var(--tl-muted); font-weight: 600; padding: 9px 16px;
            border-bottom: 1px solid var(--tl-rule);
        }
        table.tl-tbl td { padding: 9px 16px; border-bottom: 1px solid var(--tl-rule); color: var(--tl-ink); }
        table.tl-tbl tr:last-child td { border-bottom: 0; }
        table.tl-tbl td.num, table.tl-tbl th.num { text-align: right; font-variant-numeric: tabular-nums; }
        /* A reference is one token: wrapping it mid-hyphen reads as two jobs. */
        table.tl-tbl td.mono { font-family: ui-monospace, monospace; font-size: 11.5px; white-space: nowrap; }

        .tl-pill {
            display: inline-block; font-size: 10.5px; font-weight: 650; letter-spacing: .04em;
            text-transform: uppercase; padding: 2px 7px; border-radius: 999px;
            border: 1px solid currentColor; white-space: nowrap;
        }
        .tl-pill.live { color: var(--tl-ink); }
        .tl-pill.proof { color: var(--tl-muted); }
        .tl-pill.ready { color: var(--tl-good); }
        .tl-pill.done { color: var(--tl-muted); }
        .tl-pill.cancel { color: var(--tl-bad); }
        .tl-pill.paid { color: var(--tl-good); }
        .tl-pill.pending { color: var(--tl-wait); }

        .tl-empty { padding: 20px 16px; font-size: 12.5px; color: var(--tl-muted); }
        .tl-scroll { max-height: 380px; overflow-y: auto; }

        .tl-chart {
            display: flex; align-items: flex-end; gap: 10px;
            padding: 18px 16px 12px; height: 190px;
        }
        .tl-chart.is-short { height: 128px; padding: 14px 16px 10px; }
        .tl-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; }
        .tl-track { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
        .tl-bar {
            width: 68%; max-width: 54px; min-height: 3px;
            background: var(--tl-bar-past); border-radius: 4px 4px 2px 2px;
        }
        .tl-col.is-current .tl-bar { background: var(--tl-bar); }
        .tl-col.is-current .tl-lbl { color: var(--tl-ink); font-weight: 650; }
        .tl-amt { font-size: 10.5px; color: var(--tl-muted); margin-top: 6px; white-space: nowrap; }
        .tl-lbl { font-size: 10.5px; color: var(--tl-muted); margin-top: 1px; white-space: nowrap; }
    </style>
@endonce
