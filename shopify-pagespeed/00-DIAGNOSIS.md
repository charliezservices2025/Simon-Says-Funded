# badgemyauto.com — PageSpeed Diagnosis

Source data: PageSpeed Insights reports for `https://badgemyauto.com/`, run Aug 14 2026, 5:53 AM
(Lighthouse 13.1, desktop + mobile, emulated Moto G Power / slow 4G on mobile).

Current: **Desktop 72 · Mobile 45** · Accessibility 94 · Best Practices 73 · SEO 92.

---

## 1. Where the points are actually lost

The Lighthouse performance score is a weighted sum of five metrics. Reproducing the scoring
curves (`tools/lighthouse-score-model.py`) against the reported metrics returns **exactly 72 and
45**, which confirms the breakdown below is the real arithmetic — not an estimate.

### Desktop — 72

| Metric | Value | Metric score | Points earned | Points available |
|---|---|---|---|---|
| First Contentful Paint | 0.5 s | 100 | 10.0 | 10 |
| Speed Index | 1.0 s | 97 | 9.7 | 10 |
| Largest Contentful Paint | 1.3 s | 87 | 21.8 | 25 |
| **Total Blocking Time** | **640 ms** | **18** | **5.4** | **30** |
| Cumulative Layout Shift | 0.006 | 100 | 25.0 | 25 |

**Desktop has exactly one problem: JavaScript blocking the main thread.** Paint and layout
stability are already excellent. 24.6 of the 28 lost points are TBT.

### Mobile — 45

| Metric | Value | Metric score | Points earned | Points available |
|---|---|---|---|---|
| First Contentful Paint | 6.3 s | 3 | 0.3 | 10 |
| Speed Index | 7.2 s | 30 | 3.0 | 10 |
| **Largest Contentful Paint** | **15.8 s** | **0** | **0.0** | **25** |
| Total Blocking Time | 550 ms | 54 | 16.2 | 30 |
| Cumulative Layout Shift | 0 | 100 | 25.0 | 25 |

**Mobile is a rendering problem on top of the same JavaScript problem.** LCP at 15.8 s scores a
literal zero — the full 25 points are gone. FCP at 6.3 s earns 0.3 of 10.

The 9.5 s gap between FCP (6.3 s) and LCP (15.8 s) is the key signal: the page paints
*something* at 6.3 s, then takes another 9.5 s to paint its largest element. That gap is the
signature of an **LCP element the browser cannot discover in the HTML** — a hero rendered by a
JS slideshow, a CSS `background-image`, or a first slide marked `loading="lazy"`. The preload
scanner never sees it, so it isn't requested until scripts have executed.

---

## 2. What "95" actually requires

This is the part worth reading before committing to the target.

**Desktop 95 — realistic.** Holding every other metric where it is, TBT must fall from 640 ms to:

| If LCP becomes | TBT must be ≤ |
|---|---|
| 1.3 s (unchanged) | **129 ms** |
| 1.1 s | 156 ms |
| 0.9 s | 173 ms |
| 0.8 s | 179 ms |

So desktop is a single, well-defined job: cut main-thread blocking by ~75%, and shave LCP a
little to buy headroom on the TBT budget. Both are achievable on Shopify.

**Mobile 95 — achievable only with third-party cuts.** A budget that clears it:

| Metric | Required | Currently |
|---|---|---|
| FCP | ≤ 1.5 s | 6.3 s |
| Speed Index | ≤ 3.0 s | 7.2 s |
| LCP | ≤ 2.2 s | 15.8 s |
| TBT | ≤ 150 ms | 550 ms |
| CLS | ≤ 0.1 | 0 ✅ |

That combination scores 96. The honest caveat: a *well-optimized* Shopify store with a normal
app stack typically lands around **LCP 3.0 s / FCP 2.2 s / SI 4.0 s / TBT 250 ms — which scores
86**, not 95. Mobile 95 is not a tuning exercise; it requires the homepage's first paint to
carry essentially **no third-party JavaScript at all**, with the hero served as static HTML.

**My assessment:** desktop 95+ is a confident yes. Mobile 90+ is a confident yes. Mobile 95
depends on decisions only you can make — which apps are allowed to load before first paint
(§3 of `03-javascript-and-tbt.md` lists them as a decision table, not a recommendation to
delete your stack).

---

## 3. Root causes, mapped from the report's own audits

Both reports flag the same underlying issues. Grouped by what actually causes them on Shopify:

### A. Third-party / app JavaScript — causes TBT on both, and FCP on mobile
- `Reduce unused JavaScript` — **531 KiB** desktop / 530 KiB mobile
- `Reduce JavaScript execution time` — 1.7 s desktop / 2.0 s mobile
- `Minimize main-thread work` — 2.7 s desktop / 3.4 s mobile
- `Avoid long main-thread tasks` — **11 long tasks** on both
- `Render blocking requests` — 40 ms desktop but **700 ms mobile**
- `Warning: More than 6 preconnect/preload connections were found` — apps each adding their own
- `User Timing marks and measures` — 42/43 marks: an app is instrumenting heavily
- `Forced reflow` — a script is reading layout in a loop (typical of sliders / sticky headers)

Half a megabyte of unused JS and 11 long tasks on a homepage is an app-stack problem, not a
theme problem. This is the single highest-leverage area — it fixes desktop outright and is
required for mobile.

### B. LCP discoverability — causes the 25-point mobile loss
- `LCP breakdown` and `Network dependency tree` flagged on both
- FCP→LCP gap of 9.5 s on mobile
- `Improve image delivery` — 194 KiB desktop / 150 KiB mobile

### C. Payload and caching
- `Avoid enormous network payloads` — **2,522 KiB** desktop / 2,341 KiB mobile
- `Use efficient cache lifetimes` — 745 KiB desktop / 145 KiB mobile.
  Note: Shopify's own CDN assets already ship a 1-year TTL, so this audit is almost entirely
  third-party scripts with short TTLs. **Largely unfixable** — treat as noise, not a task.
- `Legacy JavaScript` (12–13 KiB) and `Minify JavaScript` (14 KiB) — small, and mostly owned by
  apps rather than your theme. Low priority; they are worth ~0 points.

### D. Fonts
- `Font display` — 10 ms savings, mobile only. Small, but trivially fixable.

### E. Non-performance categories
- **SEO 92** — `Document does not have a meta description`. One field.
- **Accessibility 94** — insufficient contrast; `<frame>`/`<iframe>` elements without a title.
- **Best Practices 73** — deprecated APIs (3 warnings), console errors, Chrome DevTools issues.
  These are nearly always emitted by third-party apps, so §A cleanup tends to fix this category
  as a side effect.

---

## 4. Strategy

1. **Fix the LCP element first** (`02-lcp-and-render-blocking.md`) — recovers up to 25 mobile
   points and buys desktop TBT headroom. Lowest risk, highest mobile return.
2. **Cut and defer third-party JS** (`03-javascript-and-tbt.md`) — the only path to desktop 95,
   and mandatory for mobile 95. Highest risk; staged rollout and verification included.
3. **Images, fonts, payload** (`04-images-fonts-caching.md`) — supports FCP/SI on mobile.
4. **A11y / Best Practices / SEO** (`05-accessibility-bestpractices-seo.md`) — cheap, fast,
   takes three categories to ~100.
5. **Verify** (`06-verification.md`) — measurement protocol, because a single PSI run has enough
   variance to mislead you by ±5 points on mobile.

Work in that order. Re-measure after each stage so you know which change bought which points.
