# Residual risks & verification protocol

Because this package was produced **without live access** to the site or wp‑admin (the audit environment's egress policy blocks `thrivedowntown.com`), a handful of exact values — real DOM selectors, the LCP image URL, JS handle names, the specific failing contrast nodes, plugin UI label wording — can only be confirmed on the live site. This doc lists (1) the short "confirm these first" list, (2) the final verification protocol, and (3) the full adversarial‑verification record: every bug the verification pass caught in the drafts, and every residual risk per workstream.

Every snippet in this pack marks these with `VERIFY` / `PLACEHOLDER` inline. Nothing here is destructive, and every change is reversible.

---

## 1. Confirm these against the live site before trusting the placeholders

| Value to confirm | Where to read it | Feeds |
|---|---|---|
| **LCP element** (is it an `<img>` or a Divi CSS `background-image`?) + its exact URL(s) | PSI mobile → "Largest Contentful Paint element" → Show | Snippet 05, FlyingPress preload |
| **Layout‑shift culprits** (the real shifting nodes) | PSI → CLS diagnostic → "Layout shift culprits"; DevTools Performance → Layout Shift track | CSS 01 selectors + measured heights |
| **Real JS handle names** | Snippet 02 (View Source, logged out) | Snippets 03, 08; FlyingPress excludes |
| **Failing contrast nodes** (element + current ratio) | PSI → "Background and foreground colors…" → Show | CSS 02 selectors/colors |
| **Non‑crawlable anchors** (which module emits each) | PSI → SEO → "Links are not crawlable" → Show | Divi social/menu fixes |
| **Console errors + severity** (error vs warning) | DevTools Console (Errors filter) + PSI → Best Practices → Show | Meta Pixel / Elfsight removal decision |
| **Hamburger / search element type** + which node carries Divi's click handler | DevTools inspect + the keyboard test in snippet 07 | Snippet 07 |
| **Smart Slider container id**, **Trustindex outer wrapper**, **Divi hero class**, **Divi font stacks** | View Source + DevTools Computed | CSS 01 |
| **reCAPTCHA in use on Gravity Forms?** (v2/v3) | Forms → Settings → reCAPTCHA | JS delay excludes |
| **Server type** (Apache vs LiteSpeed/Nginx) | Host panel / `curl -I` `Server:` header | htaccess‑01 applicability |
| **Exact FlyingPress v5 label strings** | The live FlyingPress settings UI | All FlyingPress steps |
| **Whether the hero/slider is placed via Divi Theme Builder** (not post_content) | Divi → Theme Builder + View Source | Slider dequeue + CLS reservation |

---

## 2. Final verification protocol

1. **Purge everything** (FlyingPress → Purge Everything; plus WebP Express / CDN / host cache if present) so you test fresh output — several fixes only apply at cache‑generation time.
2. **Re‑run PageSpeed Insights twice** on both mobile and desktop; read the **second** (warm) run.
3. **Targets:** Performance ≥ 95 both · Accessibility ≥ 95 (aim 97–100) · SEO ≥ 96 · Best Practices 100 · Agentic Browsing 2/2 · lab CLS ≤ 0.05 · TBT < 200 ms · LCP < 2.0 s.
4. **Functional regression pass (do not skip)** — logged out, phone + desktop:
   - Mobile hamburger opens/closes by **mouse and by keyboard (Tab → Enter)**; header search opens.
   - Smart Slider hero paints instantly (no blank → pop‑in); arrows/autoplay work.
   - **Gravity Forms**: submit a real test entry; conditional logic; validation styling; confirmation; reCAPTCHA passes; entry appears in Entries + the delivery log.
   - WhatsApp button opens chat and is keyboard‑focusable; only **one** button shows.
   - Dark hero/CTA/footer text is still readable (contrast CSS didn't over‑reach).
   - DevTools Console: no red errors; `.htaccess` change didn't 500 any page.
   - `document.querySelectorAll('[role="main"]').length` === `1`; the §7 duplicate‑id and `aria-hidden-focus` queries return empty.
5. **Field Core Web Vitals** (the mobile CLS "pass") updates on CrUX's **28‑day rolling window** — track it in **Search Console → Core Web Vitals**; expect the mobile assessment to flip to **PASSED** within ~1–2 weeks of live traffic once lab CLS ≤ 0.05.
6. If mobile Performance is still 90–94, apply the **Phase‑5 escalation** (interaction‑delay the heavy third‑party embeds) — though Phase 7's Elfsight removal + Trustindex scoping usually closes it first.

---

## 3. Adversarial‑verification record (per workstream)

The following was produced by a second, independent pass whose only job was to break each draft. It lists the correctness/safety **bugs it caught and fixed**, and the **residual risks** it could not resolve without the live site. This is your QA trail.


## FlyingPress config  (verifier confidence: medium)

**Bugs the adversarial verifier caught & fixed in the draft:**
- JS engine mislabeled: the draft claimed v5 'Delay JavaScript' is one toggle with three modes named 'Defer loading / Load when idle / Load after interaction.' Verified v5 strategy names are 'Load when idle', 'Load third-party scripts on interaction', and 'Delay specific scripts on interaction', with exclusions edited via 'Edit Exclusions.' Reframed to the real model and marked labels as functional/verify-in-install.
- Fonts label wrong: 'Display Swap' is not a FlyingPress toggle. Corrected to 'Display Fallback Fonts', and noted it applies a metric-adjusted system fallback (helps the font-swap CLS more than plain font-display:swap).
- Overstated/incorrect CLS claim fixed: draft said delaying Elfsight/Trustindex/Pixel 'reduces field CLS.' Delaying a widget does not reduce its layout shift and can push the shift later (field CLS spans the full page lifecycle). Clarified that the CLS levers here are image dimensions, early hero init (slider+jQuery excluded from delay), fallback-font metrics, and CSS space reservation; and that the WhatsApp button is position:fixed so it is not a layout-shift source at all.
- Divi 'divi-custom-script' delay risk was unflagged: delaying it under idle usually works but can break the mobile menu, sticky header, and scroll animations. Added an explicit QA-driven exclusion escalation (divi-custom-script → custom.unified → /js/custom).
- Images tab reconciled with v5: in v5 a single 'Preload critical images' checkbox auto-detects above-the-fold images, excludes them from lazy load, AND preloads them with fetchpriority=high. The draft's separate 'Exclude Above the Fold Images count=2' + 'Preload Critical Images/LCP' rows are partly redundant; described both the single-checkbox case and the legacy count field, and kept the keyword pins as a manual safety net.
- Lazy Render exclusion mechanism corrected: the draft suggested a '-'-prefix removal trick that is not documented syntax. Changed to using the Lazy Render exclusions/ignore field, and marked '.et_pb_section_0' as a guess to VERIFY AGAINST LIVE DOM.
- 'Ignore Query Strings' paste-list field may not exist as described in every v5 build (FlyingPress normalizes query strings internally). Softened to 'verify the field exists; if absent this is already handled and there is nothing to add' so nobody hand-rolls a query-string cache filter.
- Hardcoded self-hosted font path '/wp-content/uploads/flying-press/fonts/' removed as unreliable; replaced with 'copy the exact .woff2 URL from the DevTools Network row' (the path varies by version).
- Marked all guessed Divi/Smart Slider/hero selectors in the CSS Include-Selectors safelist and image-exclude list as VERIFY AGAINST LIVE DOM, and noted the safe direction: over-safelisting is harmless, under-safelisting breaks styling.
- Stated explicitly that this workstream ships NO PHP/JS/CSS code (only plugin toggles + plain-text keyword/selector lists), so there is nothing to run in WPCode and nothing that can white-screen the site; added an UpdraftPlus backup note and preserved all cache-clear/retest gates.

**Residual risks — MUST verify against the live site before/after applying:**
- Exact FlyingPress UI label strings vary between point releases and I could not fetch the live plugin/docs pages (the FlyingPress docs + several guides returned HTTP 403 through the proxy); labels here are corroborated by search snippets and described functionally, but each must be confirmed in the actual install.
- The CSS Include-Selectors safelist, the JS Edit-Exclusions keywords, and the image-exclude keywords contain Divi/Smart Slider/Gravity Forms defaults plus educated guesses; the real hero section id, the actual JS handles, and the true LCP image filename must be confirmed against the live DOM and the PSI 'LCP element' detail before trusting them.
- Whether Delay JS even needs the jQuery/Smart Slider excludes: v5 docs claim Delay JS is stable without exclusions, but keeping the excludes is the safe direction. The hero, mobile menu, sticky header, and Gravity Form MUST be exercised live (Section 11) after enabling.
- The query-string ignore list only applies if a corresponding field exists in this build; if it does not, do not add code — confirm before expecting to paste it.
- Font preload URLs must be read from the live Network waterfall after 'Optimize Google Fonts' is enabled; the guide gives the method, not literal URLs.
- The final mobile field CLS pass depends on the CSS workstream's reserved-space rules (min-height/aspect-ratio for the Trustindex reviews embed) and, if still marginal, size-adjust fallback @font-face metrics — FlyingPress toggles alone may not fully close CLS 0.2 → <0.1.
- 'Cache Lifespan 10 Hours' assumes that discrete option exists; pick the nearest available value if not.
- Cloudflare add-on steps only apply if DNS is actually on Cloudflare — verify before configuring.

## JavaScript / TBT  (verifier confidence: high)

**Bugs the adversarial verifier caught & fixed in the draft:**
- §4a diagnostic read $wp_scripts->queue, which omits dependency handles (jquery, jquery-migrate, etc.) that are actually printed. Switched to $wp_scripts->done at PHP_INT_MAX priority so the emitted list is complete and reflects everything you might need to dequeue.
- reCAPTCHA-on-load hazard: Tier A (delay ALL JS) can break Gravity Forms reCAPTCHA v3/invisible, which executes on page load to mint a token BEFORE user interaction — causing silent submit failures on a business-critical booking form. Added mandatory reCAPTCHA exclude keywords (recaptcha, gstatic.com/recaptcha, google.com/recaptcha) and a check-before-ship instruction.
- Smart Slider conditional dequeue keyed only on $post->post_content would strip the slider from Divi Theme Builder templates, widget placements, PHP, or Divi modules that reference the slider by numeric ID (the string 'smartslider' need not appear in content). Added a strong verify caveat and a safer default (keep enqueued unless confirmed absent); removed the over-broad 'n2' handle guess.
- Shared Counts dequeue keyed only on is_singular('post') would remove share buttons from pages/CPTs that legitimately embed them. Added a shortcode/block content check.
- The optional Gravity Forms dequeue block referenced $post/$content but was presented detached from the function, and used a single GF-version-specific handle. Moved it inside tdc_conditional_js_trim() (after $content is set) and left it commented out by default, with a note that GF handles vary by version and a live-submit retest is required.
- jQuery Migrate removal used add_filter on wp_default_scripts with a non-returning callback. Switched to add_action (correct hook type; $scripts is passed by reference so the mutation persists and no return is needed) to avoid confusion and future breakage.
- Softened 'exact label' plugin-setting claims to version-robust UI-path + functional descriptions, and documented WHY FlyingPress exclude keywords work (they match the rendered <script> tag, which contains WordPress's id="{handle}-js" attribute and the src URL) — so both handle-name and filename keywords are reliable. Added filename fallbacks (custom.min.js) to Divi exclusions.
- Added a global cache-purge + double-load + double-PSI + form-QA protocol after every risky change, and per-section retest steps, which the draft lacked.
- CAOS (Path B) can conflict with the installed Site Kit by Google, which injects its own gtag. Added a conflict/test/rollback caution.
- Best Practices console-error attribution: the draft assumed the Meta Pixel deprecation is the logged error. Added the method (read the actual error in DevTools Console) before attributing/removing, per the 'extract, don't invent' constraint.
- Added correctness context that FlyingPress re-dispatches synthetic DOMContentLoaded/load for delayed scripts (why delay-all doesn't leave Divi's on-ready menu/slider dead) and that Smart Slider renders its first slide server-side (so delaying its JS should not delay LCP).

**Residual risks — MUST verify against the live site before/after applying:**
- All vendor script handles in §4 are placeholders — they MUST be replaced with what the §4a diagnostic reports on the live install before enabling the dequeue snippet.
- Whether Gravity Forms uses reCAPTCHA, and which version (v2 vs v3 invisible), determines whether the §1b reCAPTCHA excludes are mandatory. Verify in Forms → Settings → reCAPTCHA (or per-form) before shipping Tier A.
- Whether the homepage hero / any slider is placed via a Divi Theme Builder template or widget rather than post_content — this affects both the Smart Slider dequeue safety and the CLS height reservation. Verify in Divi → Theme Builder and by viewing page source.
- Tier A first-tap mobile menu behavior is timing/version dependent; must be QA'd on a real mobile device, not just an emulator.
- The exact Best Practices console error text must be read in DevTools before attributing it to the Meta Pixel or removing that plugin; record the Pixel ID first and clear removal with the marketing owner.
- Confirm on this specific install that Smart Slider renders the first slide server-side (view source) so delaying its JS does not delay LCP; re-check the LCP element in the PSI 'Largest Contentful Paint element' detail after shipping.
- jQuery Migrate removal (§6) must be QA'd with the console open across the mobile menu, Smart Slider, and a GF submit; a legacy plugin could still depend on deprecated APIs.
- wp-polyfill removal (§5) must be tested on any page using WP blocks / interactive block UI; some blocks need it.
- CAOS (if chosen) must be verified against the installed Site Kit for gtag conflicts and confirmed via GA4 Realtime.
- Estimated score/metric gains are projections; they must be validated by re-running PageSpeed Insights twice (reading the second, warm run) after cache purge on both mobile and desktop.

## CLS fix  (verifier confidence: high)

**Bugs the adversarial verifier caught & fixed in the draft:**
- CLS-CAUSING RULE REMOVED: the draft's `img[loading="lazy"] { content-visibility: auto; }` 'belt & suspenders' rule actually CREATES layout shift/scrollbar jump because, without `contain-intrinsic-size`, off-screen images collapse to 0 height and expand when scrolled into view. Removed it and pointed to width/height attributes (FlyingPress) as the correct box-reservation fix.
- ACCESSIBILITY BUG (threatens the >=95 goal): the static WhatsApp button used white text on `#25D366` and claimed it 'qualifies as large text.' White on #25D366 is ~1.98:1 (verified) which fails WCAG for ALL text AND fails the 3:1 non-text/icon rule; a 15px bold label is NOT large text (large starts at 18.66px bold), and even large text needs 3:1. Changed background to `#075E54` (~7.67:1, passes AA/AAA + 3:1 graphic), corrected the false claim, and made the focus ring `#1a1a1a` so it's visible on a light page.
- WPCODE HOOK-TIMING BUG: PHP snippets that call `add_action('wp_head', ...)` were told to use Auto Insert location 'Site Wide Header'. That location executes the code DURING wp_head, too late to reliably register a wp_head callback. Corrected the location to 'Run Everywhere' (functions.php-equivalent that registers hooks before wp_head fires); the is_front_page() guard keeps it front-page-only and is safely false in admin.
- PRELOAD DOUBLE-DOWNLOAD / CONSOLE-WARNING RISK (threatens Best Practices 'browser errors logged'): hardcoding a single preload URL for an image that is rendered with `srcset` (Divi Image module) or rewritten by WebP Express can cause the browser to download a different candidate (wasted preload + double download) and emit a 'preloaded but not used' warning. Added the requirement to match the exact rendered URL / use imagesrcset, or preferably just set fetchpriority on the img.
- FONT-PRELOAD FRAGILITY: hardcoding a FlyingPress self-hosted font path is unsafe because those filenames are content-hashed and change on every cache purge, producing a stale preload + console warning. Changed guidance to prefer FlyingPress's own font-preload feature and re-verify the URL after every purge.
- TRUSTINDEX SELECTOR OVER-MATCH: the broad `[class*="trustindex"]`, `[id^="ti-widget"]` selectors plus forced `display:block` match nested child nodes, stacking multiple min-heights and potentially breaking the widget's internal card grid. Scoped to the single outermost wrapper and recommended setting Min Height on the Divi row instead.
- ELFSIGHT COLLAPSE RULE UNSAFE: `.elfsight-app-XXX { height:0 !important }` HIDES the widget if it renders its content inline rather than as a fixed body-appended element; `overflow:visible` does not reliably let a fixed child escape. Gated the rule behind explicit verification (case 1 only, empty stub only) and commented it out by default.
- BLANKET FONT-FAMILY OVERRIDE: overriding `body, h1, h2, h3, .et_pb_text, .et_pb_slider` to one stack flattens Divi's distinct heading vs body fonts (a visible design regression). Replaced with guidance to re-declare Divi's ACTUAL existing stacks with the metric-matched fallback inserted as the 2nd family only.
- BRITTLE DIVI SELECTORS: `.et_pb_section_0` auto-index classes renumber when sections are added/removed. Replaced with instructions to assign a stable custom CSS class (Divi Advanced → CSS ID & Classes).
- `contain: layout` on the Smart Slider wrapper removed: it doesn't reserve space (only min-height/aspect-ratio does) and it turns the wrapper into a containing block that can interfere with Smart Slider's absolutely-positioned layers/controls.
- OVER-CLAIM CORRECTED: turning off Smart Slider 'Fade' was presented as a CLS fix. Fade is opacity-based and does not cause CLS; reframed as low-value for CLS so effort isn't wasted, keeping height reservation as the real fix.
- Added explicit backup/revert note up front (copy existing Divi Custom CSS before editing; disable-not-delete Divi modules; one WPCode snippet per change) and a 'Cache-clear + retest' step after every risky section, plus Gravity Forms handles (gravityforms/gform/recaptcha) added to the FlyingPress delay-JS exclude list to protect form conditional logic and reCAPTCHA.

**Residual risks — MUST verify against the live site before/after applying:**
- All selectors marked VERIFY/PLACEHOLDER (Smart Slider container id, Trustindex outer wrapper, Elfsight app class, Divi hero section class, Divi font-family stacks/names) must be read from the live DOM via View Source + DevTools before trusting them.
- All min-height values must be the MEASURED final mobile/desktop heights of each block. A value larger than the real content height converts a shift into a visible static whitespace gap; a value smaller leaves residual shift.
- Preload URLs must equal the exact URL the page actually requests — including the srcset candidate chosen and whether WebP Express is in picture-tag mode vs server-rewrite mode. Verify in View Source and confirm the image downloads only once in DevTools Network.
- The @font-face size-adjust/ascent-override/descent-override/line-gap-override numbers are illustrative placeholders and must be generated for the site's actual primary font (fontpie / capsize).
- Whether Elfsight leaves an in-flow placeholder that actually shifts must be confirmed in the PSI 'Layout shift culprits' list before applying the §6/§7 Elfsight collapse rule; if it renders inline the rule would hide the widget.
- Field CLS will not flip immediately after deploy — CrUX is a 28-day rolling 75th-percentile window; track the trend in Search Console / CrUX History rather than expecting an instant pass.
- FlyingPress Delay JavaScript exclusion syntax and defaults vary by version; after enabling delay, manually re-test the mobile menu, the hero slider paint, and a full Gravity Forms submission (with conditional logic + reCAPTCHA if present).
- Plugin UI labels (Smart Slider Optimize/Size options, FlyingPress Fonts/Images/Preload toggles) are described functionally because exact strings shift between versions — locate by section + intent, not by an exact assumed label.
- The static WhatsApp button uses #075E54 for guaranteed contrast; if the surrounding footer/page area is dark, the #1a1a1a focus ring and the button contrast should be re-checked against that background.

## Images / caching / LCP  (verifier confidence: high)

**Bugs the adversarial verifier caught & fixed in the draft:**
- FATAL/site-breaking: the .htaccess compression block enabled BOTH mod_brotli and mod_deflate output filters unconditionally for the same MIME types. When both modules are loaded, both filters can run on the same response and double-compress it (brotli-then-gzip), corrupting output for every visitor. Fixed by wrapping the deflate block in <IfModule !mod_brotli.c> so gzip only applies when Brotli is absent.
- Double-download bug: the preload snippet used a responsive imagesrcset/imagesizes preload unconditionally, but a Divi CSS background-image requests one exact single URL. A responsive preload variant mismatches the CSS-requested URL, so the browser downloads the hero twice and LCP gets worse. Branched the guidance: responsive preload ONLY for an <img> LCP; single exact-URL preload (with optional per-breakpoint media) for a CSS-background LCP.
- Added a placeholder guard to the WPCode preload snippet (bails if the URL still contains 'REPLACE-'), so if the operator forgets to edit the hero URL the snippet emits nothing instead of shipping a 404 preload to production.
- The mod_headers 'Header set Cache-Control' FilesMatch covers image extensions and could theoretically interfere with WebP Express's Vary: Accept content negotiation. Added an explicit post-apply curl verification for 'Vary: Accept' plus a concrete fallback (remove image extensions from the FilesMatch and rely on mod_expires for images) if negotiation is disturbed.
- Added the missing 'immutable' stale-asset caveat: any CSS/JS/font/SVG/image replaced in place with the same filename and no version change can serve stale for up to a year; documented the workaround and the option to drop 'immutable' while still passing the audit.
- Softened invented/exact plugin labels to version-robust functional descriptions (FlyingPress above-the-fold/critical-image count, Preload, Fonts self-host; Smart Slider image optimize/load-type; WebP Express conversion-method and Alter HTML options) per the 'no made-up exact strings' constraint, and marked live-DOM-dependent selectors/URLs 'VERIFY AGAINST LIVE DOM'.
- Corrected the invented plugin-author attribution for the AVIF alternative (removed the made-up 'Mattins/mattplugins' byline; referenced it only as the 'Converter for Media' plugin).
- Added an explicit WPCode PHP-snippet note (leading <?php optional/tolerated, never add a closing ?>) to avoid a parse error, and added a conversion-'scope' check for WebP Express so Divi/Smart Slider image directories are actually covered.
- Tightened the compression MIME list (removed already-compressed font/woff), and added explicit backup-first + immediate 500-check + cache-clear-then-retest steps around the .htaccess edit and the FlyingPress delay/defer changes.

**Residual risks — MUST verify against the live site before/after applying:**
- The real LCP element type (<img> vs CSS background-image), its exact URL(s), the hero filenames, and the count of above-the-fold images must be read from the live PSI 'LCP element' detail and the live DOM before editing the snippet and the FlyingPress count. All are marked VERIFY AGAINST LIVE DOM.
- The .htaccess block assumes Apache with mod_expires/mod_headers and mod_brotli or mod_deflate. On LiteSpeed or Nginx it will not apply and the equivalent server/plugin config is needed. After saving, a bad directive returns HTTP 500 — the operator must load the homepage + an inner page immediately and restore the backup if so.
- Exact FlyingPress (Images/Preload/Fonts) and Smart Slider (image optimize / first-slide eager load) toggle labels vary by version; confirm the functional control in the live UI before toggling.
- Whether Divi serves different background images per breakpoint determines whether a single preload or two media-scoped preloads are correct for a CSS-background hero — verify against the live CSS.
- After adding the mod_headers Cache-Control block, must re-verify with curl that image responses still send 'Vary: Accept' (WebP Express negotiation intact); if not, remove image extensions from the FilesMatch as noted.
- Confirm WebP Express's conversion 'scope' actually includes the directories where the Divi-builder and Smart Slider hero images live, or those images stay JPEG even in Varied mode.
- Replacing the Elfsight widget with a static wa.me button changes a visible UI element; preview before shipping (it is reversible by re-adding the embed).

## Accessibility  (verifier confidence: high)

**Bugs the adversarial verifier caught & fixed in the draft:**
- Snippet B delivery location was wrong and would break/no-op the snippet: the draft said WPCode auto-insert 'Site Wide Footer' (an HTML INSERTION location) for PHP that itself calls add_action('wp_footer', ...). That either double-injects the <script> or registers the action after the footer has rendered. Corrected to an EXECUTION-scope location: 'Frontend Only' (Run Everywhere also acceptable).
- NEW WCAG 2.1.1 (Keyboard) failure introduced by the draft: it set role='button' + tabindex='0' on the hamburger/search icon <span>s with NO keyboard activation handler, producing a focusable control announced as a button that does nothing on Enter/Space. Rewrote to a makeControl() helper that (a) only adds a name to native <a>/<button>, and (b) promotes a non-interactive element to role=button ONLY together with a once-bound Enter/Space -> click() handler that re-fires Divi's existing click.
- isEmpty() only checked textContent/aria-label/aria-labelledby, so it could overwrite an existing accessible name provided by img[alt], svg <title>, or the title attribute (Divi social-follow uses title='Follow'). Replaced with hasName() that also detects img[alt], svg title, and title attribute; labeling is now truly gap-fill and idempotent.
- Performance/Best-Practices risk: the Elfsight shadow-DOM walker ran querySelectorAll('*') across the whole document on every one of 11 retries (~8s), needless main-thread work that fights the perf workstream's TBT 560ms / INP goal, and an uncaught throw would log to the console and cost the Best-Practices 96. Gated the deep walk behind an actual-widget-present check, reduced retries to 6, and wrapped run() and the walker in try/catch so it can never throw a console error.
- Snippet C used blanket element selectors (body, p, li, a) that would also recolor text sitting on DARK backgrounds (dark hero/CTA/footer), turning previously-passing light-on-dark text into failing dark-on-dark and INTRODUCING contrast failures. Scoped body/link overrides to #main-content, excluded Divi buttons (.et_pb_button/.et_pb_more_button/.et_pb_promo_button) and header/footer menus, and added an explicit dark-section exemption block plus a mandatory visual re-check step.
- Snippet A used strpos($output,'lang=') which also matches xml:lang= (would skip injecting a real lang in XHTML output) and wasn't null-safe. Tightened to preg_match('/(^|\s)lang=/i', (string) $output).
- Plugin-update steps in section 7 (Smart Slider 3 Pro / Trustindex / Elfsight) had no backup or reversal guidance. Added a mandatory UpdraftPlus backup-first note, one-plugin-at-a-time updates, and clear-cache-then-retest between each.
- FlyingPress Delay-JS exclusion relied solely on the script id matching the keyword. Added a redundant in-body '/* tdc-a11y */' marker comment so the keyword still matches if an optimizer strips the id, and described the Delay exclude field functionally (version-robust: the exclude list paired with the Delay JavaScript option, not the general optimization-exclude field).

**Residual risks — MUST verify against the live site before/after applying:**
- Every selector marked VERIFY must be confirmed against the live DOM before trusting it: the hamburger (#et_mobile_nav_menu .mobile_menu_bar) and search (#et_search_icon) element TYPE and which element actually carries Divi's click handler (the keyboard test in the snippet's regression note will reveal if Enter does nothing because the handler is on a different/parent element); Smart Slider arrow/dot classes (.nextArrow/.previousArrow/.n2-bullet) can differ by version; Divi social-follow markup.
- Elfsight may render inside a CLOSED shadow root or an <iframe>. In both cases neither axe nor the script can read or label it (so it is not scored by Lighthouse, but also cannot be fixed via Snippet B). If so, set the label in the Elfsight editor or replace it with a plain labeled <a href='https://wa.me/...' aria-label='Chat on WhatsApp'>.
- Snippet C colors and selectors must be reconciled with the SPECIFIC failing nodes PSI reports; the applier must visually verify after purging cache that no dark-background text (dark hero, CTA rows, dark footer) was darkened, choose the correct single footer block (dark vs light background), and enable at most one .et_pb_button color line after confirming the real button background. The chosen link/brand color must be re-checked to be >= 4.5:1 on the actual background it appears on.
- Lighthouse takes its accessibility snapshot after page load/quiet; labels added by the late setInterval retries (for third-party widgets that mount after the snapshot) may not be counted by the audit. This is expected (those late nodes typically aren't in the snapshot either) but should be verified — the DOMContentLoaded run() covers all static Divi elements.
- Confirm Divi/child theme does not already emit a <main> or role=main and does not already provide names/roles on these icons; the snippet guards against duplicates and clobbering, but a stale child theme could place the click handler or landmarks differently than assumed.
- The keyboard handler re-fires Divi's click via el.click(); if Divi also binds its own key handling to the same element there is a small chance of a double-toggle. The regression test (Tab -> Enter opens the menu once) will surface this; if seen, target the actual interactive element instead of the icon glyph.
- The identical-links-same-purpose item is a review/manual axe check with low scoring weight; it may not move the numeric score even after the aria-label differentiation. Treat it as headroom, not a primary lever.

## SEO / Best-Practices / plugin audit  (verifier confidence: high)

**Bugs the adversarial verifier caught & fixed in the draft:**
- CORRECTNESS: The draft assumed the Meta Pixel deprecation notice causes the 'Browser errors were logged to the console' Best-Practices failure. That audit (errors-in-console) counts only severity=error entries, uncaught exceptions, and failed network requests — NOT console.warn/[Deprecation] notices. A deprecation notice is almost always a warning and may not affect the score at all. Rewrote §2 to require reading the actual error and its severity first, and downgraded the Meta Pixel removal from a guaranteed BP fix to conditional.
- CORRECTNESS: Draft claimed crawlable-anchors is 'weight 3 of the SEO category' — a specific and likely-wrong weight number. Replaced with an accurate framing (it is one binary pass/fail audit; the SEO category has ~a dozen weighted audits, consistent with the 8-point gap).
- SCOPE/ACCURACY: Both the crawlable-anchors audit and the draft's console one-liner only walk the main-document light DOM; they do not pierce cross-origin iframes or shadow DOM. Third-party widgets (Elfsight/Trustindex) commonly render there, so they are LOW-confidence culprits for the crawlable failure, not high. Reordered the culprit table by real confidence (Divi/theme inline markup high; iframe/shadow-DOM widgets low) and annotated the console snippet's limitation.
- ACCURACY: Draft asserted Shared Counts emits 'javascript:' anchors that fail crawlability. Shared Counts normally outputs real share-URL hrefs (crawlable-safe). Marked as VERIFY and reframed its removal as a payload decision, not an SEO-audit fix.
- MISCHARACTERIZATION: Draft called Meta Pixel a 'duplicate' of Site Kit/analytics. Meta Pixel is a Facebook tag, not a Google-analytics duplicate; removing it stops Facebook conversion tracking. Corrected the claim and added a business-impact guard (deactivate/observe and migrate to CAPI/GTM before deleting if ad tracking is live).
- WPCODE APPLICABILITY BUG: §2.4 gave a bare CSS block for an HTML snippet. A WPCode HTML snippet does not apply loose CSS — it must be wrapped in <style> or added as a separate CSS Snippet, or the button styling silently won't load. Made that explicit. Also added guidance to paste PHP snippets without <?php tags.
- RELIABILITY: The §3.1 Smart Slider dequeue at wp_enqueue_scripts priority 100 can silently no-op because Smart Slider 3 often enqueues assets on-render (later than that hook). Added the caveat, pointed to FlyingPress excludes as the reliable route, and added a widen-the-guard example for pages that also use a slider.
- SAFETY: Added missing backup/dry-run/cache-clear/retest notes — UpdraftPlus backup before the Better Search Replace http->https pass (with dry-run first), form-submission retest after scoping Gravity Forms, single-button verification before removing Elfsight, and 'switch to SFTP before deleting WP File Manager so you retain file access.'
- SAFETY: Flagged that removing Custom Post Type UI while it still registers CPTs would make those post types disappear; added a guard to §5/table.
- COMPLETENESS: Added §2.5 to require confirming no other failing scored Best-Practices audit exists, since 96->100 assumes the console audit is the sole failing item.

**Residual risks — MUST verify against the live site before/after applying:**
- The exact failing anchors and which module/plugin emits each must be read from the mobile PSI 'Links are not crawlable' > Show details panel (or the browser console one-liner). The culprit table is prioritized guesswork until then.
- The exact console-error text AND its severity (error vs warning) must be read from PSI Best Practices > Show details before deleting Meta Pixel or Elfsight for a Best-Practices reason — if the flagged entries are warnings, those removals won't move the score.
- Smart Slider handle names in the §3.1 snippet are guesses; verify via View-Source (search 'smartslider'/'n2-') before relying on the dequeue, and confirm Smart Slider isn't enqueuing on-render (in which case use FlyingPress excludes instead).
- Whether the static WhatsApp button overlaps a back-to-top button, cookie banner, or the Divi mobile menu can only be confirmed on the live site at mobile and desktop widths.
- Removing Meta Pixel will stop Facebook conversion/ad attribution if campaigns are live — confirm with the business before deleting; deactivate-and-observe first.
- The KiB reduction figures in §3.2 are estimates; re-measure with PSI after each change rather than trusting the totals.
- Best Practices reaching exactly 100 depends on there being no other failing scored audit (§2.5) — verify in the PSI Best Practices section.
- Scoping Gravity Forms, Smart Slider, Trustindex, and Divi Supreme off pages risks breaking a form/slider/embed that lives on a page you didn't expect — retest the homepage, a blog post, and the contact form after each scoping change and after clearing FlyingPress/host/CDN cache.

