> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. Central optimization tool. Ready-to-paste snippets referenced here live in ./snippets/. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

# FlyingPress — Definitive Configuration for thrivedowntown.com

> **No PHP/JS/CSS code ships in this workstream.** Everything here is FlyingPress plugin toggles plus a handful of *plain-text keyword/selector lists* pasted into the plugin's own fields (query params, CSS safelist selectors, JS exclusion keywords, image-exclude keywords). There is **nothing to run in WPCode from this section**, so there is no snippet that can white-screen the site. Every change is a toggle or a list entry — **flipping the toggle off / clearing the list fully reverts it.**
>
> **Before you start:** take one UpdraftPlus backup (Settings → snapshot) so there is a named restore point, even though nothing here writes to the DB or theme. Then work top-to-bottom through the **Safe Rollout Order (Section 10)**, purging cache and re-testing between the two ⚠️ risky steps.

**Target plugin version:** FlyingPress **v5.x**. The JavaScript engine was rebuilt in v5. In v5 the JS controls are exposed as an idle-based loading model plus optional interaction-based loading — the **verified strategy names are `Load when idle`, `Load third-party scripts on interaction`, and `Delay specific scripts on interaction`**, with exclusions edited via an **`Edit Exclusions`** control. (Exact wording moves slightly between point releases — treat every label below as *functional*, and confirm it in your install.) If this install still shows the **old separate `Defer JS` + `Delay JS` toggles**, it's v4 — update to v5 first, because the v5 idle engine is what makes Divi safe.

**Two things to know before you touch anything:**

1. **Mobile cache:** FlyingPress serves the *same* cached HTML to mobile and desktop (responsive), but generates **device-specific Used CSS** (a separate mobile critical-CSS payload) automatically once "Remove Unused CSS" is on. There is **no mobile-cache on/off toggle to set** — this is handled for you. Don't go looking for one.
2. **The two panic toggles** (memorize these for the recovery note): **`CSS → Remove Unused CSS`** and **`JavaScript → Delay JavaScript`**. ~90% of "the site broke after optimizing" is one of these two. Turning both OFF + purging cache reverts the site to normal within ~30 seconds.

Everything below is reversible: each toggle OFF = revert. No permanent changes are written to your theme or DB.

---

## 1. Cache tab

| Setting (functional label) | Set to | One-line rationale |
|---|---|---|
| **Cache Lifespan** | **`10 Hours`** (nearest available option) *or* **`Never`** if you prefer manual purges | Long enough that near-all traffic hits warm cache; short enough that ACF/Rank Math edits refresh. A local practice site has low content churn, so `Never` + auto-purge-on-update is also fine and gives the best TTFB. |
| **Cache Logged-in Users** | **OFF** | Only you/admins are ever logged in; caching admin sessions wastes disk and risks serving admin bars to visitors. Public visitors are anonymous → they get full-page cache regardless. |
| **Query-string handling** (see ⚠️ note) | **Normalize/ignore tracking params** — paste the list below **only if a field to do so exists** | Tracking/ad params must NOT fork the cache into hundreds of uncached variants. |
| **Never Cache URLs** | Leave **empty** (see note) | No WooCommerce cart/checkout/account on this site. Gravity Forms submits via AJAX and is cache-safe, so there is nothing dynamic to exclude today. |
| **Never Cache Cookies** | **Leave default** | FlyingPress already ships logged-in/comment-author cookie rules; don't edit. |

> ⚠️ **VERIFY THE FIELD EXISTS FIRST.** FlyingPress already **normalizes/ignores most query strings internally**, and a visible "paste your ignored params here" text field is **not guaranteed to exist in every v5 build**. Look under the Cache tab (and Advanced/Misc). **If you find a query-string / ignored-parameters field, paste the list below. If there is no such field, do nothing — this is already handled for you, and there is no code to add.** Never hand-roll a query-string cache filter for this.

**Query-string ignore list — paste one per line *if the field exists*:**
```
utm_source
utm_medium
utm_campaign
utm_term
utm_content
utm_id
gclid
gbraid
wbraid
fbclid
msclkid
_gl
mc_cid
mc_eid
ref
```
> Rationale: these are the params Site Kit/GA4, Meta Pixel, Mail Mint and Google/Facebook ads append. Ignoring them means `/?gclid=123` and `/` share ONE cache entry. Rank Math canonical still points to the clean URL, so no SEO risk.

**Never Cache URLs — only if applicable:** if you later add a Mail Mint "unsubscribe/preferences" page or a Gravity Forms "thank-you" page that echoes submitted data, add its slug here. Not needed today.

**Expected impact:** TTFB consistency (field TTFB 0.7s mobile / 1.7s desktop — desktop TTFB is your weakest field number; higher cache-hit rate is the lever). No Lighthouse-score change, but stabilizes real-user LCP.

---

## 2. CSS tab  ⚠️ *risky — QA required*

| Setting | Set to | Rationale |
|---|---|---|
| **Minify CSS** | **ON** | Strips whitespace/comments from all CSS. Safe. Small win on the "unused CSS ~12 KiB" + payload. |
| **Remove Unused CSS** | **ON** — *but only after adding the Include-Selectors safelist below* | This is your biggest FCP/LCP lever: it inlines only above-the-fold CSS in a `<style>` tag and defers the rest until idle, killing render-blocking CSS. **This is also the toggle most likely to break Divi/Smart Slider** — see safelist. |
| **Lazy Render Elements** | **ON**, default selectors, **but see the hero warning** | Skips rendering of below-the-fold DOM until it nears the viewport (`content-visibility`). Cuts main-thread layout cost. |

### ⚠️ Remove Unused CSS — the Divi/Smart Slider safelist (mandatory)

FlyingPress extracts "used" CSS by analyzing the *static* HTML. It cannot see classes that Divi/Smart Slider/Gravity Forms add **later via JavaScript** (mobile-menu open-state, slide transitions, animation waypoints, form validation errors). Those styles get stripped → broken menu / un-styled slider / invisible form errors.

**Safety direction:** over-safelisting is **harmless** (worst case, slightly larger inlined CSS); under-safelisting **breaks styling**. So an aggressive safelist is the safe choice.

**In `Remove Unused CSS → Include Selectors` (safelist), paste this list.** The leading `.`/`[` is fine — FlyingPress accepts selectors. **Some of these are Divi/Smart Slider defaults and some are educated guesses — the exact ones your theme emits must be confirmed; VERIFY AGAINST LIVE DOM if any element ends up unstyled:**
```
.et_mobile_menu
.mobile_menu_bar
.et_pb_menu__wrap
.et-menu
.mobile_nav
.et_pb_toggle_open
.et_pb_toggle_close
.et_pb_accordion
.et_pb_tab
.et-fixed-header
.et_pb_sticky
.et-animated
[class*="et_pb_animation"]
[class*="et-waypoint"]
.et_pb_slider
.et-pb-controllers
.et_pb_active_slide
[class*="n2-ss"]
[class*="smartslider"]
.n2-section-smartslider
.gform_wrapper
.gfield
.gfield_error
.gform_validation_error
.gform_confirmation_message
.elfsight-app
[class*="eapps-"]
[class*="ti-widget"]
[class*="trustindex"]
```

**Also protect Divi's dynamic stylesheet:** Divi writes a per-page stylesheet under **`/et-cache/`**. If your version exposes a **CSS file-exclusion / "Exclude from Used CSS"** field, add the keyword **`et-cache`** so that file is never run through unused-CSS removal. If there's no file-exclusion field, the Include-Selectors safelist above is sufficient in practice — do not invent one.

### ⚠️ Lazy Render — do NOT let it touch the hero

Lazy Render defers rendering of below-fold sections — good. But if the Smart Slider hero (or anything above the fold) is caught by a default selector, it renders late → **layout shift + delayed LCP**, which is exactly the mobile CLS 0.2 you're fighting. FlyingPress's defaults are below-fold-only, but **verify:** after enabling, hard-reload the homepage on a phone viewport and confirm the hero paints instantly with no "blank → pop-in." If the hero flickers, add the hero's section selector to the **Lazy Render exclusion field** (the same panel has an exclusions/ignore list — add the selector there; do **not** rely on a `-`-prefix trick, which is not a documented syntax). The hero selector is theme-specific (e.g. a `.et_pb_section` / Smart Slider wrapper id like `#n2-ss-…`) — **VERIFY AGAINST LIVE DOM** by inspecting the first section in DevTools rather than assuming `.et_pb_section_0`.

**Expected impact:** Removes render-blocking CSS → **FCP 1.1s → ~0.7–0.8s, mobile LCP 2.8s → ~2.0–2.3s**, clears the "Reduce unused CSS" diagnostic, contributes ~+4–7 Performance points. Net-neutral-to-positive on CLS *if* the hero is excluded from Lazy Render.

---

## 3. JavaScript tab  ⚠️ *highest-ROI + highest-risk — QA required*

This is your single biggest Performance lever (TBT 560ms, "Reduce JS execution 7.0s", "unused JS 407 KiB", "main-thread 3.9s" all live here).

In v5, all scripts are delayed *unless excluded*, FlyingPress checks the full `<script>` tag (src, id, and inline content), and matching is **partial and case-insensitive with no wildcards** (type `jquery`, not `*jquery*`). The v5 docs state Delay JS is very stable and often needs **no** exclusions — but for a "no room for error" Divi + Smart Slider + Gravity Forms site we add a small belt-and-suspenders exclusion list anyway.

| Setting | Set to | Rationale |
|---|---|---|
| **Minify JavaScript** | **ON** | Safe. Minor payload/parse win; clears part of "legacy JS ~11 KiB." |
| **Delay JavaScript** | **ON** | Master switch: delays non-critical JS off the critical path. This is what crushes TBT/"JS execution time." |
| **Loading strategy = `Load when idle`** (primary recommendation) | **Select `Load when idle`** | Runs delayed scripts one-by-one when the browser goes idle *after* the important content paints. This crushes TBT **and improves INP without requiring user interaction**, so Divi's menu, animations, and the Smart Slider still initialize on their own → nothing looks broken. |

### Why `Load when idle` (not a global "load only after interaction")

A global "run *everything* only after the first user interaction" gives a marginally better *lab* TBT (scripts never run in a no-interaction Lighthouse load) but risks a **dead-looking hero** and a first-tap menu delay for real users — bad on a Divi site with a Smart Slider hero + mobile menu above the fold. `Load when idle` gets ~90% of the TBT benefit and is Divi-safe. **Start here.**

> **Targeted aggressive option (preferred over a global interaction mode):** if mobile Performance is still <95 after everything else, use **`Delay specific scripts on interaction`** (or the built-in **`Load third-party scripts on interaction`** default domain list) to interaction-delay *only the heavy third-party embeds* — Elfsight WhatsApp, Trustindex reviews, Meta Pixel, GA4/gtag. This defers the biggest offenders until the user scrolls/taps **without touching the hero, menu, or forms**. This is safer and more surgical than flipping the whole site to a global "after interaction" mode. Add those scripts by keyword (e.g. `elfsight`, `trustindex`, `fbevents`, `googletagmanager`, `gtag`) and re-test.

### Delay JavaScript → Excluded Keywords (via `Edit Exclusions` — do NOT delay these)

Paste:
```
jquery
nextend
smartslider
smart-slider
n2-
gravityforms
gform
recaptcha
```
**Dependency reasoning (the part people get wrong):** Smart Slider is jQuery-dependent. Because we **un-delay the slider** (`nextend`/`smartslider`/`n2-`) so the hero initializes early — which *prevents the hero pop-in shift and protects CLS* — we **must** also un-delay **`jquery`**; otherwise the non-delayed slider script executes before jQuery exists and throws, killing the hero. Gravity Forms + reCAPTCHA are excluded because the contact form is conversion-critical and GF's conditional logic/validation should never wait.

**Deliberately NOT excluded (let these delay — this is the whole point):** Elfsight WhatsApp, Trustindex Google Reviews, Meta Pixel, Site Kit/GA4/gtag, Shared Counts, Mail Mint, and Divi's `divi-custom-script`. These third-party/tracking scripts are the bulk of the 407 KiB unused JS and the 7.0s execution time; delaying them to idle is exactly what fixes the *performance* score.

> **Divi menu/animation caveat — do this if QA fails.** `divi-custom-script` powers Divi's mobile menu, sticky header, and scroll animations. Under `Load when idle` it almost always still works (idle fires within a fraction of a second of load), which is why we leave it delayed for the TBT win. **But** if Section 11 QA shows the mobile hamburger not opening, the sticky header not sticking, or scroll animations not firing, add one of these to `Edit Exclusions` and re-test: `divi-custom-script`, then (if still broken) `custom.unified`, then `/js/custom`. Only exclude the minimum that fixes it — each exclusion you add gives back some TBT.

**Expected impact (biggest single win):** **TBT 560ms → ~100–180ms, mobile Performance 79 → ~90+**, clears "Reduce unused JavaScript," "JS execution time," "Minimize main-thread work," and most "long main-thread tasks."
**CLS note (corrected):** the CLS benefit on this tab comes from **excluding the slider + jQuery so the hero initializes early** (no blank→pop-in). Merely *delaying* Elfsight/Trustindex/Pixel is a **TBT/execution-time win, not a CLS fix** — see the CLS clarification in the summary and defer the real widget-CLS fix (reserved space) to the CSS workstream.

---

## 4. Fonts tab

| Setting (functional label) | Set to | Rationale |
|---|---|---|
| **Optimize Google Fonts** | **ON** | Divi loads Google Fonts render-blocking from `fonts.googleapis.com`. This self-hosts them, removing the external connection + render-block → faster FCP, and it applies `font-display: swap`. |
| **Display Fallback Fonts** *(this is the correct v5 label — NOT "Display Swap")* | **ON** | Shows a **metric-adjusted system fallback** immediately, then swaps to the web font. Because the fallback is size-matched, this reduces the font-swap *reflow* — i.e. it directly trims the font-driven portion of your field CLS, not just FOIT. |
| **Preload Fonts** | **ON** — add the above-the-fold `.woff2` URL(s) (method below) | Tells the browser to fetch the hero/heading font immediately so the swap completes near first paint, shrinking the font-driven layout shift. |

**Method to find the exact font(s) to preload (do this, don't guess):**
1. Open Chrome DevTools → **Network** → filter **Font** → hard-reload `thrivedowntown.com` on a mobile viewport.
2. Identify the **1–2 `.woff2` files used above the fold** (the hero H1/body font). After "Optimize Google Fonts" runs they are self-hosted; **copy the exact URL straight from the Network row** rather than assuming a path (FlyingPress's asset directory varies by version — do not hardcode `/wp-content/uploads/flying-press/fonts/…`; read the real URL from DevTools).
3. Paste those exact URLs into **Preload Fonts**.

> **Do NOT preload more than 2 fonts** — each preload competes with the LCP image for bandwidth. Preload only what paints above the fold.

**Zero-CLS font hardening (hand off to the CSS/WPCode workstream):** even with `Display Fallback Fonts`, a small metric shift can remain when the real font replaces the fallback. If field CLS is still marginal after this, add `size-adjust`/`ascent-override`/`descent-override` fallback `@font-face` metric overrides in the CSS workstream — but only after measuring; don't pre-optimize.

**Expected impact:** FCP -0.1 to -0.3s; removes the "Font display" flag; measurable dent in the mobile field CLS 0.2 (font swap is one of your named culprits).

---

## 5. Images tab

| Setting (functional label) | Set to | Rationale |
|---|---|---|
| **Lazy Load** | **ON** | Defers offscreen images → smaller initial payload (attacks "enormous network payloads 3,350 KiB"). |
| **Add missing image dimensions** *(label may read "Properly size images" / "Add width & height"; on by default in v5)* | **ON** | **Directly attacks the mobile field CLS 0.2.** Adds `width`/`height` so the browser reserves space before the image loads → no reflow. Highest-ROI CLS toggle in the plugin. Works only if CSS keeps `height:auto` (Divi's default) so the aspect ratio applies — confirm images still scale correctly after enabling. |
| **Responsive Images** *(or "Serve responsive images"/"Properly size")* | **ON** | Generates/serves correct `srcset` sizes so mobile downloads a phone-sized image, not the desktop original → clears "Improve image delivery ~161 KiB (mobile) / 557 KiB (desktop)." |
| **Preload critical images** *(the v5 single-checkbox ATF+LCP feature)* | **ON** | In v5 this **one** checkbox auto-detects above-the-fold images, **excludes them from lazy load**, and **preloads them with `fetchpriority="high"`**. That single toggle covers what the old separate "exclude above-the-fold" + "preload LCP" settings did. Never lazy-load the LCP image/logo — this prevents that classic LCP+CLS regression. |
| **Exclude from lazy load — keyword pins** (manual list, if auto-detect misses) | Add the keywords below | Safety net so the logo + Smart Slider hero image are never lazy-loaded even if auto-detection is wrong. |
| **Lazy Load iframes / YouTube facade** ("Replace YouTube iframe with preview image") | **ON** | If any page embeds YouTube (about/blog), this swaps the heavy iframe for a click-to-load thumbnail → large payload + TBT win. Harmless if no videos. |

> **Reconciliation vs the old UI:** if your build still shows a separate **"Exclude above the fold images"** count field, set it to **`2`** (logo + hero). If it only shows the single **"Preload critical images"** checkbox, that checkbox already does the exclusion + preload — you do not need both. Don't hunt for a setting that your version folded into the checkbox.

**Exclude-from-lazy-load keyword pins — add these** (edit to the real hero filename — get it from PSI "LCP element" or DevTools):
```
logo
thrive
hero
slider
n2-ss
smart-slider
```
> Replace/extend with the actual hero image filename once you read the PSI **"Largest Contentful Paint element"** detail. The default above covers the Divi logo and Smart Slider hero classes — **VERIFY AGAINST LIVE DOM / PSI** rather than trusting the guesses.

**Interaction with WebP Express:** keep WebP conversion in **WebP Express** (already installed) and image *delivery/lazyload/dimensions* in FlyingPress. Do **not** also enable image optimization inside FlyingCDN (see CDN tab) — running two WebP pipelines double-processes and can serve broken images.

**Expected impact:** **This is your #1 CLS fix** (add-missing-dimensions) → helps flip mobile field CLS 0.2 below 0.1. Plus payload/image-delivery savings (~161–557 KiB) and faster LCP via the preload-critical checkbox.

---

## 6. Preload tab

| Setting (functional label) | Set to | Rationale |
|---|---|---|
| **Preload Pages** (crawl links/sitemap to warm cache) | **ON** | Small site → cheap to keep every page pre-cached, so real users almost always hit warm cache (best field LCP/TTFB). If your host CPU is very constrained, set `Never` cache lifespan + manual preload instead. |
| **Preload on Link Hover** ("Preload pages on hover/instant") | **ON** | Prefetches the next page on mouse-hover/touch-start → near-instant navigation between service pages. Zero risk. |
| **Preload Fonts** | Covered in the Fonts tab above | — |
| **Preload critical images** | Covered by the Images tab checkbox above | — |

**Expected impact:** Real-user navigation speed + cache-hit rate. No direct Lighthouse-score change (Lighthouse is a cold load), but improves field metrics.

---

## 7. CDN tab

**Recommendation: leave FlyingCDN OFF for now.**

| Option | Decision | Rationale |
|---|---|---|
| **FlyingCDN** (BunnyCDN-based, usage-billed, includes edge image optimization) | **OFF** | (a) Your audience is **local (Vancouver)** — a global edge network barely moves LCP for a metro-local practice; full-page cache + good origin matters far more. (b) Its edge image optimization **conflicts with your existing WebP Express** pipeline (double WebP processing). (c) Adds cost/complexity against a marginal gain. |
| **Custom CDN URL rewrite** | **OFF / empty** | Not needed without a CDN. |

> If you ever move to a CDN, prefer **Cloudflare (free)** at the DNS level and *disable* WebP Express's rewriting first — don't stack two image optimizers. The "Use efficient cache lifetimes ~372 KiB" diagnostic is **mostly third-party** (GA4/gtag, Meta Pixel, Elfsight, Trustindex) whose cache headers you cannot control from your origin or a CDN — so don't expect a CDN to clear it. FlyingPress already sets far-future cache headers on *your own* optimized assets, which is the part you can fix.

---

## 8. Add-ons / Cloudflare integration

- **Cloudflare add-on:** Only configure if the domain's DNS is actually on Cloudflare (check first — do not assume). If so, add the **Cloudflare API token + Zone ID** so FlyingPress **auto-purges Cloudflare's edge cache** whenever it purges its own (prevents stale pages after edits). If you're *not* on Cloudflare, leave this untouched.
- If on Cloudflare, set a Cloudflare **Cache Rule** to **bypass cache on HTML** (or "Respect existing headers") so FlyingPress's page cache remains the source of truth and you never serve stale HTML from two cache layers.
- **No other add-on is needed** for this site.

---

## 9. Database / cleanup

**FlyingPress has no database-cleanup module — by design.** Do not go looking for a "Database" tab; there isn't one. Handle post-revision/transient/spam cleanup separately (you already have **UpdraftPlus** for backups; a dedicated cleanup pass can be run there or via a maintenance plugin). Out of scope for this workstream — flagging so nobody wastes time hunting for the tab.

---

## 10. SAFE ROLLOUT ORDER

Enable in this sequence. **Purge cache and re-test between the two ⚠️ risky steps.** Never flip Remove-Unused-CSS and Delay-JavaScript in the same untested step.

1. **Cache tab** — Lifespan, Cache Logged-in OFF, query-string list *(only if the field exists)*. *(Safe.)* Purge cache.
2. **Images tab** — Lazy Load, **Add missing image dimensions**, Responsive Images, **Preload critical images** (+ keyword pins). *(Safe, high CLS ROI.)* Purge cache. **← get the CLS win banked early.**
3. **Fonts tab** — Optimize Google Fonts + Display Fallback Fonts. Then add Preload Fonts URLs (after reading the Network waterfall). *(Low risk.)* Purge cache.
4. **CSS tab** ⚠️ — First paste the **Include-Selectors safelist**, *then* turn **Remove Unused CSS ON**, then Minify CSS, then Lazy Render. **Purge cache. Now do the manual QA pass (Section 11).** Do not proceed until the site is verified clean.
5. **JavaScript tab** ⚠️ — Minify JS, then **Delay JavaScript ON → `Load when idle`** with the `Edit Exclusions` list. **Purge cache. Repeat the full manual QA pass (Section 11).**
6. **Preload tab** — Preload Pages + Preload on Hover. *(Safe.)* Purge cache.
7. **Re-run PageSpeed Insights** (mobile + desktop). If mobile Performance is still <95, *only then* add the heavy third-party embeds to `Delay specific scripts on interaction` (Section 3) and QA the hero/menu/forms again.

---

## 11. Mandatory manual QA after steps 4 and 5

After enabling **Remove Unused CSS** and again after **Delay JavaScript**, purge cache and click through these **as a logged-out visitor** (incognito), on **both a phone and desktop**:

- [ ] **Homepage** — Smart Slider hero paints instantly, first slide visible, autoplay/arrows work, **no blank→pop-in shift**.
- [ ] **Primary menu + mobile hamburger menu** — opens, sub-menus expand, closes. *(If broken → Divi caveat in Section 3: exclude `divi-custom-script`.)*
- [ ] **Sticky header + scroll animations** — header sticks on scroll; Divi fade/slide animations fire. *(If broken → same Divi caveat.)*
- [ ] **A blog post** — Table of Contents, images, author box, share buttons render and are styled.
- [ ] **Contact / Gravity Form** — fields render styled; conditional logic works; submit an actual test entry; validation errors show styled; confirmation message appears.
- [ ] **Trustindex Google Reviews** widget renders (not blank).
- [ ] **Elfsight WhatsApp** floating button appears and opens.
- [ ] **DevTools → Console** — no new red errors (this also protects your Best Practices "browser errors" audit).

If any item fails → it's almost always a missing safelist entry (CSS Include Selectors) or a script that needs excluding (JS `Edit Exclusions`). Add the offending selector/keyword, purge, re-test.

---

## 12. 🔴 IF THE SITE BREAKS — recovery

**Turn OFF these two toggles first, in this order, then purge cache:**
1. **`JavaScript → Delay JavaScript` → OFF**
2. **`CSS → Remove Unused CSS` → OFF**
3. **FlyingPress → Purge Cache** (or Settings → Purge Everything).

The site returns to normal within seconds. Then re-enable **one at a time** and re-run Section 11 to isolate which toggle + which missing exclude caused it. Every setting here is a toggle — there is nothing to "undo" beyond flipping it back. (Belt-and-suspenders: the UpdraftPlus snapshot from the top of this doc is your named restore point if you ever need it.)

---

## Expected-impact summary (ordered by ROI)

| # | Fix (FlyingPress) | Primary metric moved | Rough magnitude |
|---|---|---|---|
| 1 | **Delay JavaScript = Load when idle** (+ excludes) | TBT / unused JS / main-thread | **TBT 560→~150ms; Perf +8–12 mobile.** Biggest score lever. |
| 2 | **Add missing image dimensions** (+ preload-critical) | **Mobile field CLS** | **CLS 0.2 → <0.1** (the failing CWV). Highest CWV-pass ROI. |
| 3 | **Remove Unused CSS** (+ safelist) + Minify CSS | FCP / LCP / render-block | FCP −0.3s; LCP 2.8→~2.1s; Perf +4–7. |
| 4 | **Responsive Images + Lazy Load** | Image delivery / payload | −161 KiB mobile / −557 KiB desktop. |
| 5 | **Optimize Google Fonts + Display Fallback Fonts + Preload** | FCP / font-swap CLS | FCP −0.1–0.3s; trims field CLS. |
| 6 | **Cache + query-string normalize + Preload Pages/Hover** | Field TTFB / LCP consistency | Stabilizes real-user metrics; no lab-score change. |
| 7 | **CDN: OFF** (avoid WebP double-processing) | — | Neutral by design for a local-audience site. |

**Bottom line:** Fixes 1–3 carry mobile Performance from **79 → ~93–96** and desktop **80 → ~95+**. The **mobile field CLS 0.2 → passing** flip is driven by **(a) add-missing-image-dimensions, (b) excluding the Smart Slider + jQuery from JS delay so the hero inits early with no pop-in, (c) `Display Fallback Fonts` metric-matched swap, and (d) the CSS-workstream min-height/aspect-ratio space reservations for the Trustindex reviews embed.** Note the Elfsight WhatsApp button is `position:fixed` (out of normal flow) and is **not** a layout-shift source, so "delaying it" is a performance action, not a CLS fix — *delaying third-party widgets does not by itself reduce CLS and can move the shift later; reserving space does.* FlyingPress also cannot clear the third-party portion of "efficient cache lifetimes" (GA/Pixel/Elfsight/Trustindex headers) — that ceiling is external, not a config miss.

---

**References (FlyingPress v5 UI/behavior — labels verified functionally; confirm exact strings in your install):** [Delay All JavaScript — FlyingPress Docs](https://docs.flyingpress.com/en/articles/11406701-delay-all-javascript) · [Delay Specific Scripts on Interaction — FlyingPress Docs](https://docs.flyingpress.com/en/articles/11654303-delay-specific-scripts-on-interaction) · Remove Unused CSS — FlyingPress Docs (docs.flyingpress.com → Remove Unused CSS) · [FlyingPress v5 release notes](https://flyingpress.com/blog/v5-release/) · [The Ideal FlyingPress Settings 2026 — OnlineMediaMasters](https://onlinemediamasters.com/flyingpress-settings/) · [What scripts to exclude from FlyingPress JS Delay — WordPress.org](https://wordpress.org/support/topic/what-scripts-to-exclude-from-flyingpress-javascript-delay/)