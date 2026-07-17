# Thrive Downtown Counselling — Website Performance & Quality Audit

**Site:** https://thrivedowntown.com/
**Platform:** WordPress + Divi (Elegant Themes) + Divi Builder / Divi Supreme
**Audit basis:** Google PageSpeed Insights reports dated **Jul 16, 2026, 10:24 PM** (mobile + desktop), plus a full review of the active 32‑plugin stack.
**Prepared as:** Senior full‑stack / performance & accessibility engineering review.
**Target:** ≥ 95 Lighthouse **Performance** on mobile **and** desktop, ≥ 95 **Accessibility** and **SEO**, keep **Best Practices** ≥ 96, and turn the **mobile Core Web Vitals assessment from FAILED → PASSED**.

> ⚠️ **How to read this pack.** I could not log into your wp‑admin or reach the live site from my environment (isolated container + an egress policy that blocks `thrivedowntown.com`). So this is a **precise, copy‑paste, reversible remediation package** built from your two PageSpeed reports and your exact plugin stack. Everything here is designed to be applied by you (or me, if you later give a working access path) **without guesswork**. Where an exact value can only be known by reading the live DOM (e.g., which element fails contrast), I give you (a) the 30‑second method to extract it and (b) a strong safe default — rather than inventing a selector.

---

## 1. Executive summary

Your site is **already fast in the field** — real Chrome users get good LCP and INP on both mobile and desktop. The scores are held back by **three fixable things**:

1. **Too much JavaScript** running on the main thread (mobile: 7.0 s JS execution, 3.9 s main‑thread work, 560 ms TBT). This is what pins mobile Performance at **79**. Sources: Divi + Smart Slider + several third‑party trackers/widgets (Meta Pixel, Google/Site Kit, Elfsight WhatsApp, Trustindex reviews).
2. **A real‑user layout shift on mobile (field CLS 0.2)** that fails Core Web Vitals even though the lab CLS is only 0.05. Culprits are late‑loading elements: the hero **slider**, the floating **WhatsApp** widget, the **Google Reviews** embed, and web‑font swap.
3. **Heavy, under‑optimized payload** (3.3 MB mobile / 2.9 MB desktop) and **weak cache lifetimes** — mostly oversized images and third‑party scripts.

Accessibility (**78/80**) and SEO (**92**) each have a **small, finite** list of concrete defects that are quick wins.

**The good news:** you already own every tool needed to fix this — **FlyingPress** (does ~70% of the performance work safely), **WebP Express** (images), **WPCode Lite** (to insert the accessibility/preload snippets), and **Rank Math**. No new plugins required. The plan below is ordered by ROI and is fully reversible.

### Projected outcome

| Category | Mobile now | Desktop now | Target after this pack |
|---|---|---|---|
| Performance | **79** | **80** | **95–99** |
| Accessibility | **78** | **80** | **95–100** |
| Best Practices | 96 | 96 | **100** |
| SEO | 92 | 92 | **100** |
| Core Web Vitals (field) | **FAILED** (CLS 0.2) | PASSED | **PASSED** |

*Field Core Web Vitals update ~28 days after the fixes are live and crawled, because CrUX is a rolling 28‑day real‑user dataset. Lab/Lighthouse scores improve immediately on the next run.*

---

## 2. Current state — exact numbers

### 2.1 Mobile

**Core Web Vitals (field / real users): ❌ FAILED**

| Metric | Value | Verdict |
|---|---|---|
| Largest Contentful Paint (LCP) | 0.9 s | ✅ Good |
| Interaction to Next Paint (INP) | 72 ms | ✅ Good |
| **Cumulative Layout Shift (CLS)** | **0.2** | ❌ **Fails** (needs ≤ 0.1) |
| First Contentful Paint (FCP) | 0.8 s | ✅ Good |
| Time to First Byte (TTFB) | 0.7 s | ✅ Good |

**Lab (Lighthouse) — Performance 79**

| Metric | Value |
|---|---|
| First Contentful Paint | 1.1 s |
| Largest Contentful Paint | 2.8 s |
| Total Blocking Time | **560 ms** |
| Cumulative Layout Shift | 0.05 |
| Speed Index | 3.6 s |

**Opportunities / diagnostics (mobile):**
- Reduce unused JavaScript — ~407 KiB
- Reduce JavaScript execution time — **7.0 s**
- Minimize main‑thread work — **3.9 s**
- Avoid enormous network payloads — **3,350 KiB**
- Use efficient cache lifetimes — ~372 KiB
- Improve image delivery — ~161 KiB
- Reduce unused CSS — ~12 KiB
- Legacy JavaScript — ~11 KiB
- Avoid long main‑thread tasks — 11 found
- Avoid non‑composited animations — 4 elements
- Forced reflow / LCP request discovery / Font display / Layout‑shift culprits — flagged

Other categories: **Accessibility 78**, **Best Practices 96** ("Browser errors were logged to the console"), **SEO 92** ("Links are not crawlable"), **Agentic Browsing 1/2** ("Accessibility tree is not well formed").

### 2.2 Desktop

**Core Web Vitals (field): ✅ PASSED** — LCP 1.1 s, INP 43 ms, CLS 0.04 (TTFB 1.7 s).

**Lab — Performance 80:** FCP 1.1 s · LCP 2.2 s · TBT 130 ms · CLS 0.014 · Speed Index 1.6 s.

**Opportunities (desktop):** Improve image delivery **~557 KiB** (bigger than mobile → oversized desktop hero images) · Reduce unused JS ~406 KiB · Reduce unused CSS ~27 KiB · Payload **2,899 KiB** · Cache lifetimes ~372 KiB · Legacy JS ~11 KiB. **Accessibility 80**, Best Practices 96, SEO 92, Agentic 1/2.

---

## 3. Root‑cause analysis (why the scores are where they are)

| Symptom | Root cause on this stack | Fixed in section |
|---|---|---|
| Perf 79/80, TBT/JS high | Divi + Smart Slider + 4–5 third‑party scripts (Meta Pixel, Google/Site Kit, Elfsight WhatsApp, Trustindex reviews, Shared Counts) all execute on load | §A JavaScript, §D FlyingPress |
| **Mobile field CLS 0.2** | Late‑loading hero slider, floating WhatsApp button, reviews widget, and font swap shift content after paint | §B CLS |
| 3.3 MB payload / image savings | Oversized images not fully served as WebP/responsive sizes; desktop hero especially | §C Images/Caching |
| Cache lifetimes ~372 KiB | Third‑party scripts (gtag/fbevents) have short cache; static assets need long `Cache-Control` | §C, §A (self‑host) |
| Accessibility 78/80 | Missing `main` landmark, icon buttons without names, low‑contrast text, an ARIA/role mismatch, duplicate links | §E Accessibility |
| SEO 92 | Some anchors aren't crawlable (no real `href`) | §F SEO/Best‑Practices |
| Best Practices 96 | Console errors (likely a 404 asset + the deprecated Meta Pixel) | §F |
| Agentic 1/2 | Same malformed accessibility tree as §E | §E / §F |

The detailed, copy‑paste fixes for each section follow in the numbered companion files and the sections below.

<!-- WORKSTREAM_SECTIONS -->
