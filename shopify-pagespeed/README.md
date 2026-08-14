# badgemyauto.com — Shopify PageSpeed optimisation package

Target: **95+ on both mobile and desktop**, without changing the site's layout or design.

Baseline (PageSpeed Insights, Aug 14 2026): **Desktop 72 · Mobile 45** · A11y 94 · BP 73 · SEO 92.

---

## Read in this order

| File | What it covers |
|---|---|
| `00-DIAGNOSIS.md` | Where the points are actually lost, and what 95 really requires |
| `01-IMPLEMENTATION-CHECKLIST.md` | The ordered task list — start here once you've read the diagnosis |
| `02-lcp-and-render-blocking.md` | Stage 1 — mobile LCP 15.8 s → 2.2 s (up to +25 pts) |
| `03-javascript-and-tbt.md` | Stage 2 — TBT 640 ms → 129 ms (the only path to desktop 95) |
| `04-images-fonts-caching.md` | Stage 3 — payload, responsive images, font-display |
| `05-accessibility-bestpractices-seo.md` | Stage 4 — the other three categories to ~100 |
| `06-verification.md` | How to measure without fooling yourself; rollback plan |

## Code

| File | Purpose |
|---|---|
| `snippets/theme-liquid-head-order.md` | Correct `<head>` ordering — the structural fix behind Stage 1 |
| `snippets/perf-lcp-preload.liquid` | Preloads the hero so the preload scanner can find it |
| `snippets/perf-delay-third-party.js` | Holds non-critical app scripts until first interaction (Stage 2's main lever) |
| `snippets/perf-meta-description.liquid` | Guarantees a meta description (SEO 92 → 100) |
| `snippets/perf-a11y-fixes.js` | Titles app-injected iframes (A11y 94 → 100) |
| `tools/lighthouse-score-model.py` | Score calculator — model a change before doing the work |
| `tools/audit-theme.py` | Scans a theme export for these anti-patterns and reports `file:line` |

### Auditing a theme export

Export the live theme (Online Store → Themes → ⋯ → **Download theme file**), then:

```bash
python3 tools/audit-theme.py path/to/theme.zip      # or an unzipped directory
```

It reports findings grouped by remediation stage and severity — render-blocking scripts, lazy or
CSS-background hero images, missing `width`/`height`, `image_url` without a width, fonts without
`swap`, hardcoded third-party scripts, oversized assets, and the list of **enabled app embeds**
(the Stage 2 hit list) read from `config/settings_data.json`.

Findings are heuristic: a static scan cannot prove which element is the LCP, so confirm against
the report's "LCP breakdown" before rewriting a hero.

The two JavaScript files are unit-tested for behaviour and safety guards (checkout/cart/account
paths, theme editor, empty-config no-op, post-release pass-through).

---

## The headline finding

The scoring model in `tools/` reproduces both published scores exactly from the reported metrics,
so the breakdown below is arithmetic rather than estimate:

- **Desktop has one problem: `TBT = 640 ms`.** FCP, SI and CLS are already perfect. 24.6 of the 28
  lost points are blocking time. Fix the JavaScript and desktop clears 95.
- **Mobile has that same problem plus a broken LCP.** At 15.8 s, LCP scores a literal zero and
  forfeits all 25 of its points. The 9.5 s gap between FCP (6.3 s) and LCP is the signature of a
  hero image the browser cannot discover in the HTML.

## The honest caveat

Desktop 95 is a confident yes. Mobile 95 is not a tuning exercise.

A *well-optimised* Shopify store with a normal app stack lands around LCP 3.0 s / TBT 250 ms —
which scores **86**, not 95. Mobile 95 requires the homepage's first paint to carry essentially
no third-party JavaScript. The distance between 86 and 95 is which apps you allow to load before
first paint — a business decision (attribution, chat, reviews) rather than a technical one.
`00-DIAGNOSIS.md` §2 and `03-javascript-and-tbt.md` §1 lay out that decision with the numbers.

---

## Scope note

This package was produced from the two PageSpeed reports. The session that wrote it had no
Shopify API credentials and no network access to `badgemyauto.com` or `cdn.shopify.com`, so the
live theme was never inspected and **nothing here has been applied or measured against the real
store**. Snippets are written for any Online Store 2.0 theme and marked where they need to be
pointed at your theme's actual section and setting names.
