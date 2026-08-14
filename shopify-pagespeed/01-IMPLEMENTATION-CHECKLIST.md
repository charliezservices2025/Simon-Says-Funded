# Implementation checklist

Work top to bottom. Re-measure after each stage (`06-verification.md`) so you know which change
bought which points. Effort estimates assume familiarity with the Shopify theme editor.

**Before anything:** duplicate the live theme (Online Store → Themes → ⋯ → Duplicate) and make
every change on the copy. Publish only after verifying.

---

## Stage 0 — Baseline (10 min)

- [ ] Get a free PageSpeed API key and record 3-run medians for mobile and desktop
- [ ] Save the expanded **"3rd parties"**, **"Reduce unused JavaScript"**, and
      **"Avoid long main-thread tasks"** sections from the mobile report — these name the actual
      vendors and are the input to Stage 2
- [ ] Expand **"LCP breakdown"** on mobile and note which phase dominates
- [ ] Duplicate the theme

---

## Stage 1 — LCP and render-blocking · mobile +25 pts · risk: low

Ref: `02-lcp-and-render-blocking.md`

- [ ] Identify the LCP element from the LCP breakdown
- [ ] Render the hero as a real `<img>` in the HTML — not a CSS background, not JS-injected
- [ ] First slide only: `loading="eager"` + `fetchpriority="high"`; all other slides stay `lazy`
- [ ] Add intrinsic `width` / `height` attributes (protects your current CLS of 0)
- [ ] Install `snippets/perf-lcp-preload.liquid`, configured for the homepage hero
- [ ] Confirm `imagesrcset` matches the `<img>` `srcset` exactly (or the image downloads twice)
- [ ] Cut preconnects to **≤ 4** (report warns >6 found)
- [ ] Add `defer` to every `<script>` in `<head>`
- [ ] Re-measure → expect **mobile 45 → 70–80**

---

## Stage 2 — JavaScript / TBT · desktop +23 pts · risk: medium

Ref: `03-javascript-and-tbt.md` — **the only path to desktop 95**

- [ ] Profile in DevTools (Performance → 4× CPU → Bottom-Up by URL) and list what costs >50 ms
- [ ] Uninstall unused apps
- [ ] **Remove leftover code from uninstalled apps** — theme files *and* the Script Tags API
      (the most commonly missed step; §2 has the API calls)
- [ ] Disable app embeds on templates that don't need them (reviews on the homepage, etc.)
- [ ] Remove jQuery if only used incidentally
- [ ] Fix the forced reflow (batch layout reads before writes)
- [ ] Move Meta / TikTok pixels server-side via Customer Events where possible
- [ ] Install `snippets/perf-delay-third-party.js` — **empty blocklist first**, then add
      **one vendor at a time**, verifying each before adding the next
- [ ] Verify checkout, cart, add-to-cart and every deferred widget still work
- [ ] Re-measure → expect **desktop 72 → 93–97**, mobile → 88–96

---

## Stage 3 — Images, fonts, payload · mobile FCP/SI · risk: low

Ref: `04-images-fonts-caching.md`

- [ ] Add `width:` to every `image_url` call; add `srcset` + `sizes`
- [ ] `loading="lazy"` on everything below the fold
- [ ] `font_display: 'swap'` on all `font_face` calls
- [ ] Preload at most 1–2 above-the-fold fonts, with `crossorigin`
- [ ] Ship `woff2` only; drop unused weights
- [ ] Get total page weight from 2,341 KiB toward < 1,000 KiB
- [ ] **Skip** `Use efficient cache lifetimes` — third-party origins, not fixable
- [ ] **Skip** legacy-JS / minify-JS — app-owned and worth ~0 points

---

## Stage 4 — A11y / Best Practices / SEO · 3 categories to ~100 · risk: low

Ref: `05-accessibility-bestpractices-seo.md`

- [ ] Install `snippets/perf-meta-description.liquid`
- [ ] Write a real homepage meta description (Online Store → Preferences)
- [ ] Fix contrast failures — a hero scrim preserves the design (SEO/A11y both)
- [ ] Title every iframe; `snippets/perf-a11y-fixes.js` covers app-injected ones
- [ ] Clear console errors (often 404s from removed apps)
- [ ] Re-check deprecated-API warnings after Stage 2 — most disappear with the apps

---

## Stage 5 — Verify and publish

- [ ] 3-run medians, mobile and desktop, on the preview URL
- [ ] Confirm **CLS has not regressed** — the biggest risk from Stages 1 and 3
- [ ] Full purchase test: add to cart → checkout → payment
- [ ] Confirm pixels fire (vendor's own debugger, not just the network tab)
- [ ] Publish, then re-measure the live URL
- [ ] Keep the old theme unpublished for a few days

---

## Expected outcome

| | Now | After |
|---|---|---|
| Desktop performance | 72 | **95+** — confident |
| Mobile performance | 45 | **88–96** — 95 depends on Stage 2 app cuts |
| Accessibility | 94 | 100 |
| Best Practices | 73 | 92–100 |
| SEO | 92 | 100 |

The honest caveat, expanded in `00-DIAGNOSIS.md` §2: mobile 95 requires the homepage's first
paint to carry essentially no third-party JavaScript. A well-optimised store with a normal app
stack scores **86** on mobile. The difference between 86 and 95 is which apps you are willing to
defer or drop — a business decision, not a technical one.
