# Implementation checklist — the ordered plan to ≥ 95

This is the **operational master doc**. Do the phases in order. Each phase links to the detailed section that explains the *why* and gives the exact settings/code. Every step is reversible.

**Golden rules (apply to every phase):**
1. **Back up first** — UpdraftPlus → *Backup Now* (files **+** database) before any plugin change or `.htaccess` edit. This is your rollback.
2. **One change at a time**, then **FlyingPress → Purge Everything**, reload in a fresh **incognito** window, and re-test.
3. **Re-run PageSpeed Insights twice** after a purge and read the **second** run (the first is cold/uncached).
4. **Never hand-defer/delay jQuery in code** — let FlyingPress own JS timing with its exclude list.
5. After the two risky FlyingPress toggles (Remove Unused CSS, Delay JavaScript), run the **QA gate** in Phase 4/5 before moving on.
6. **Field Core Web Vitals lag:** the mobile CLS "pass" is real-user data on a **28-day rolling window** — lab/Lighthouse scores improve immediately, the field badge follows within ~1–2 weeks of live traffic.

> **The two "panic" toggles** if anything breaks: turn **OFF** `FlyingPress → JavaScript → Delay JavaScript` and `FlyingPress → CSS → Remove Unused CSS`, then purge cache. ~90% of "the site broke after optimizing" is one of these two, and the site returns to normal in seconds.

---

## The authoritative JS strategy (read once — resolves the whole plan)

Getting mobile from TBT 560 ms → ~150 ms is the biggest score lever, and doing it **without breaking Divi** is the whole game. The correct path on **FlyingPress v5**:

1. **Delay JavaScript = ON, mode = "Load when idle".** v5's idle engine runs delayed scripts automatically shortly after paint — **no user interaction required** — so Divi's menu, sticky header, animations, and the Smart Slider still initialize on their own. You get ~90% of the TBT win with none of the "dead hero until first tap" risk of a global interaction-delay.
2. **Exclude from delay** (so the hero + form work perfectly and the hero inits early enough to protect CLS): `jquery`, `nextend`, `smartslider`, `smart-slider`, `n2-`, `gravityforms`, `gform`, `recaptcha`. (Slider is jQuery-dependent, so if you un-delay the slider you MUST un-delay jQuery too.)
3. **Let everything else delay** — especially the heavy third-party: Elfsight, Trustindex, Meta Pixel, Site Kit/gtag, Shared Counts, Mail Mint, and Divi's own `divi-custom-script`. These are the bulk of the 407 KiB unused JS and the 7.0 s execution time.
4. **Also exclude the accessibility script**: add `tdc-a11y` to the Delay-JS excludes (snippet 07 must run for Lighthouse, which never interacts).
5. **If mobile is still < 95 after Phases 1–6**, escalate surgically: use FlyingPress **"Delay specific scripts on interaction"** for the heavy embeds only (`elfsight`, `trustindex`, `fbevents`, `googletagmanager`, `gtag`) — defers the biggest offenders until scroll/tap without touching the hero, menu, or forms. **The cleanest version of this is Phase 7's plugin removals** (replace Elfsight with the static button, scope Trustindex), which delete the weight at the source.

This is the reconciliation of the three workstream docs: idle-mode + exclude-slider/jQuery is both the safest AND the most plugin-accurate path; the aggressive "delay Divi/jQuery too" option is a last-resort fallback only if idle mode plateaus below 95.

---

## Phase 0 — Baseline & safety (15 min)

- [ ] UpdraftPlus → **Backup Now** (files + database). Note where it's stored.
- [ ] Record current PSI scores (mobile + desktop) and screenshot the "Show details" panels for: **LCP element**, **Layout shift culprits**, **Links are not crawlable**, **Browser errors were logged to the console**, **Background/foreground contrast**. These give you the exact selectors/URLs the placeholders in this pack need.
- [ ] Confirm the stack: FlyingPress is **v5** (if it shows separate "Defer JS"/"Delay JS" toggles, it's v4 — update first). Confirm WebP Express, WPCode Lite, Rank Math active.
- [ ] Note whether Gravity Forms uses **reCAPTCHA** (Forms → Settings → reCAPTCHA) — affects the JS exclude list.

## Phase 1 — FlyingPress: Cache + Images + Fonts (safe, high CLS ROI) → see [`02-flyingpress-config.md`](02-flyingpress-config.md) + [`05-images-caching-lcp.md`](05-images-caching-lcp.md)

- [ ] **Cache tab:** Cache Logged-in Users **OFF**; sensible lifespan; paste the UTM/tracking query-string ignore list *only if that field exists*.
- [ ] **Images tab:** Lazy Load **ON**; **Add missing image dimensions ON** (← the #1 in-plugin CLS fix); Responsive Images **ON**; **Preload critical images ON**; add exclude-from-lazy keyword pins (`logo`, hero filename, `n2-ss`, `smart-slider`). Purge cache.
- [ ] **Fonts tab:** Optimize Google Fonts **ON**; **Display Fallback Fonts ON**; add the 1–2 above-the-fold `.woff2` URLs to Preload Fonts (read exact URLs from DevTools → Network → Font). Also check **Divi → Theme Options** isn't loading the same Google Fonts twice.
- [ ] Purge cache. Re-test: hero paints, images scale correctly. **Bank the early CLS win.**

## Phase 2 — WebP + image right-sizing → see [`05-images-caching-lcp.md`](05-images-caching-lcp.md)

- [ ] **WebP Express:** Converter = cwebp (verify "operational"); Encoding = auto; Quality 72; Alpha 85; Near-lossless 60; Metadata none; **Operation mode = "Varied image responses", Alter HTML = OFF** (safest on Divi — converts CSS backgrounds too, zero DOM change). Confirm scope covers `wp-content/uploads`. Run **Bulk conversion**.
- [ ] **Right-size the desktop hero** (the 557 KiB desktop tell): re-export ≤ 1920px wide + a ~800–1000px mobile variant, re-upload, re-point. Keep old file until confirmed.
- [ ] Verify WebP is served (DevTools Network → Type `webp`; `curl -I -H "Accept: image/webp"` → `Content-Type: image/webp` **and** `Vary: Accept`).

## Phase 3 — LCP preload (only if needed) → see [`05-images-caching-lcp.md`](05-images-caching-lcp.md)

- [ ] Identify the real LCP element in PSI (is it an `<img>` or a Divi CSS **background-image**?).
- [ ] If FlyingPress "Preload critical images" already covers it (`<img>` LCP) — **done, skip the snippet**.
- [ ] If it's a **CSS-background** hero — add **snippet 05** (`wpcode-05-lcp-hero-preload.php`), edit the real URL, use the single-URL variant. Keep only ONE preload for the LCP image.

## Phase 4 — FlyingPress: CSS ⚠️ (risky — QA gate) → see [`02-flyingpress-config.md`](02-flyingpress-config.md)

- [ ] Paste the **Divi/Smart Slider safelist** into `Remove Unused CSS → Include Selectors` **first** (over-safelisting is harmless; under-safelisting breaks styling).
- [ ] Turn **Remove Unused CSS ON**, **Minify CSS ON**, **Lazy Render ON** (verify the hero is NOT lazy-rendered — no blank→pop-in). Purge cache.
- [ ] **QA GATE (logged-out, phone + desktop):** homepage hero, menu + mobile hamburger, sticky header + animations, a blog post (TOC/images/author box), the **Gravity Form (submit a real test entry, conditional logic, validation styling, confirmation)**, Trustindex widget, DevTools Console (no new red errors). If anything is unstyled → add its selector to the safelist, purge, retest.

## Phase 5 — FlyingPress: JavaScript ⚠️ (biggest win — QA gate) → see [`02-flyingpress-config.md`](02-flyingpress-config.md) + [`03-javascript-and-tbt.md`](03-javascript-and-tbt.md)

- [ ] **Minify JS ON.**
- [ ] **Delay JavaScript ON → "Load when idle"**, with the exclude list from the authoritative strategy above (`jquery`, `nextend`, `smartslider`, `smart-slider`, `n2-`, `gravityforms`, `gform`, `recaptcha`, `tdc-a11y`). Purge cache.
- [ ] **Repeat the QA GATE from Phase 4.** If the mobile menu / sticky header / animations break, add `divi-custom-script` (then `custom.unified`, then `/js/custom`) to the excludes — minimum that fixes it.

## Phase 6 — Code snippets (WPCode) → see snippets + [`06-accessibility.md`](06-accessibility.md)

Add each as its **own** WPCode snippet (see [`snippets/README.md`](snippets/README.md) for exact locations). Activate one, purge, retest.

- [ ] **Accessibility native fixes first:** Settings → General → Site Language = **English (Canada)**; set Smart Slider arrow/dot aria-labels + slide alt text in the Smart Slider editor; fill or delete empty Divi social-icon URLs (this also fixes "links not crawlable").
- [ ] **Snippet 07** `wpcode-07-accessibility-fixes.php` (Frontend Only) + add `tdc-a11y` to FlyingPress Delay-JS excludes. QA: keyboard-open the hamburger (Tab → Enter).
- [ ] **Snippet 06** `wpcode-06-html-lang-fallback.php` (only if the native lang isn't rendering).
- [ ] **CSS 02** `css-02-accessibility-contrast.css` — reconcile with the PSI contrast node list; re-check dark sections after.
- [ ] **CSS 01** `css-01-cls-space-reservation.css` — replace selectors + **measured** heights for slider / reviews / fonts.
- [ ] **Snippet 01** `wpcode-01-remove-emoji-embed-migrate.php` — QA menu/slider/form with console open.
- [ ] **Snippet 02** (temp) to read real JS handles → fill **snippet 03** `wpcode-03-conditional-js-trim.php` → activate → **delete snippet 02**.
- [ ] (Optional) **Snippet 04** polyfills, **Snippet 08** slider scoping.

## Phase 7 — Plugin bloat + SEO/Best-Practices → see [`07-seo-bestpractices-plugin-audit.md`](07-seo-bestpractices-plugin-audit.md)

- [ ] **Static WhatsApp button:** publish **html-01** (accessible `#075E54`), confirm a single button shows, then **deactivate Elfsight WhatsApp** (kills its `platform.js`).
- [ ] **"Links are not crawlable":** from the PSI detail, give every flagged `<a>` a real `href` or convert action-only anchors to `<button>` (mostly empty Divi social icons + no-link parent menu items).
- [ ] **"Browser errors were logged to the console":** read the actual **error-level** entries in DevTools (ignore yellow warnings — they don't score). Fix 404s / mixed content (Better Search Replace `http://` → `https://`, dry-run first) / genuine third-party JS errors.
- [ ] **Plugin audit** — back up, then deactivate one-at-a-time (retest each): Health Check, WordPress Importer, Post Type Switcher, GF Polls Add-On (confirm no live poll), **WP File Manager (security — highest priority)**, Shared Counts, Meta Pixel (only if a real error source & tracking decision made), Author Box (rebuild box first). **Scope (don't remove):** Smart Slider, Trustindex, Gravity Forms, Divi Supreme, TablePress DataTables.

## Phase 8 — Server cache headers → see [`05-images-caching-lcp.md`](05-images-caching-lcp.md) §4

- [ ] **Apache only:** back up `.htaccess`, add the **htaccess-01** block above `# BEGIN WordPress`. Load homepage + inner page (watch for HTTP 500). Verify `Cache-Control` + `Content-Encoding` on a CSS/JS file and that image responses still send `Vary: Accept`. (LiteSpeed/Nginx → use the equivalent server config.)

## Phase 9 — Final verification → see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md)

- [ ] Purge everything. Re-run PSI (mobile + desktop) **twice**, read the second run.
- [ ] Targets: **Performance ≥ 95** both; **Accessibility ≥ 95** (aim 97–100); **SEO ≥ 96**; **Best Practices 100**; **Agentic Browsing 2/2**; lab **CLS ≤ 0.05**, **TBT < 200 ms**, **LCP < 2.0 s**.
- [ ] Full regression pass (menu, keyboard menu, slider, every form, WhatsApp button, dark-section readability, console clean).
- [ ] Track the **field** CLS in Search Console → Core Web Vitals over the next ~1–2 weeks until the mobile assessment flips to **PASSED**.
- [ ] If mobile Performance is still 90–94, apply the Phase-5 escalation (interaction-delay the heavy third-party embeds) — but Phase 7's Elfsight removal + Trustindex scoping usually gets you there first.
