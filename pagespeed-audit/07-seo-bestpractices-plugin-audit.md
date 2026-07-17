> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. Crawlable links, console errors, 32-plugin audit. Code: html-01, snippet 08. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

Both direct fetch paths (curl and WebFetch) return HTTP 403 — thrivedowntown.com is blocked from my container by the same egress policy that blocks you, so I cannot read the live DOM. Per the hard constraints, everything below is written to be applied blind, is reversible, and — wherever the exact culprit can only be seen in the live PSI detail — gives you the extraction METHOD plus a strong default fix rather than an invented selector. Every PHP/JS/CSS snippet below has been re-verified for production safety, correct hooks, and clean reversal (deactivate the snippet = full revert). Where a selector, handle name, or third-party behavior can only be confirmed on the live site, it is explicitly marked **VERIFY AGAINST LIVE DOM**.

> **Verifier's note on two claims the original draft got wrong** (details inline): (1) Lighthouse's "Browser errors were logged to the console" audit counts **only console entries at severity `error`** (plus failed network requests and uncaught exceptions) — it does **not** count `warn`-level deprecation notices. So a Meta Pixel *deprecation warning* may not be affecting your Best Practices score at all; you must read the actual error first. (2) The `crawlable-anchors` audit and the console one-liner below both walk the **main-document light DOM only** — they do not pierce cross-origin `<iframe>`s or shadow DOM, so third-party widgets that render inside those (often Elfsight/Trustindex) are **low-confidence** culprits for the crawlable failure; the likely culprits are Divi/theme inline markup.

---

# Workstream C — SEO, Best Practices & Plugin Bloat Audit
### thrivedowntown.com (Divi + 32 plugins)

---

## 1. SEO 92 → 96–100: "Links are not crawlable"

### 1.1 What Lighthouse actually means
This is the `crawlable-anchors` SEO audit. It is a **binary pass/fail** audit. The SEO category is made up of roughly a dozen weighted audits, so a single failure landing you at 92 (an ~8-point gap) is consistent with one binary audit failing — clearing it should recover most or all of that gap. (Don't over-index on an exact "weight" number; the point is it's one pass/fail audit and it's the only failing SEO item shown.)

It fails an `<a>` element when the thing that would make it followable by Googlebot is missing. Googlebot executes JS but **does not click** — it only follows a real URL in `href`. Per the audit's own logic, an anchor is flagged when it has:

- **no `href` attribute at all** (`<a class="icon">`)
- `href=""` (empty / whitespace-only)
- `href="#"` (a **bare** hash — note: `href="#section-id"` pointing at a real element ID is **crawlable-safe**; only a lone `#` fails)
- `href="javascript:void(0)"` or any `javascript:` protocol
- an anchor whose only navigation is an `onclick`/JS handler with no usable `href`

An anchor that carries a non-empty `role` (e.g. `role="button"`) is treated by the audit as "not a link" and is **not** flagged — but that is an accessibility smell of its own (see §4), so prefer converting true action-only anchors to real `<button>`s.

**Rank Math is NOT the cause and needs no change** — this is 100% page-markup, emitted by the theme/plugins, not by the SEO plugin. Do not touch Rank Math settings for this.

### 1.2 Likely culprits on THIS stack (in priority order)
Because the audit only sees the **main-document light DOM**, anything a third party renders inside a cross-origin iframe or shadow DOM will **not** be flagged by it. That reorders the likelihood: inline Divi/theme markup is the high-confidence culprit; iframe/shadow-DOM widgets are lower-confidence.

| Confidence | Culprit | Why it fails | Typical markup |
|---|---|---|---|
| **High** | **Divi header/footer social-icon module** with an empty network field | Divi outputs `href="/#"` or `href="#"` when a social URL is left blank | `<a href="#" class="et-social-icon">` |
| **High** | **Divi parent menu items** used only to open a dropdown | Parent set to "no link" renders `href="#"` | `<a href="#">Services ▾</a>` |
| **High** | **Author Box WP Lens** social row | Empty social fields → `href="#"` icon anchors | `<a href="#" class="author-social">` |
| **Medium** | **Smart Slider linked slides / controls** | A slide with its Link left as `#`; a custom arrow/bullet that renders as `<a>` instead of `<button>` | `<a href="#" class="nextArrow">` |
| **Low–Medium (VERIFY)** | **Shared Counts** share buttons | Shared Counts normally emits **real share-URL hrefs** (facebook.com/sharer, x.com/intent, etc.), which ARE crawlable; only flagged if configured/skinned to use `javascript:`/`#` | verify in PSI details |
| **Low (VERIFY)** | **Elfsight WhatsApp floating widget** | Often renders in an iframe/shadow DOM → invisible to this audit; only a culprit if it injects an inline `<a href="#">` into the light DOM | `<a href="#" onclick=…>` |
| **Low** | **"Read more" / pagination** | Usually fine in Divi (real permalinks) — verify but low risk | — |

Note the strong overlap with your accessibility and CLS workstreams: **the inline icon-only social/menu anchors are the same elements** that fail "links not crawlable" (no href), fail accessibility (no accessible name), and feed the agentic-tree failure. One fix pattern clears all three.

### 1.3 METHOD — find the exact failing anchors
**Option A (from the PSI report itself — authoritative, no site access needed by you):**
1. Open the mobile PSI report → **SEO** section → **"Links are not crawlable"** → click **Show audit / Show details**.
2. PSI lists every failing element as a copyable outerHTML snippet plus a CSS selector. Copy them verbatim — that tells you exactly which module/plugin emitted each one. **This is the source of truth; trust it over the culprit table above.**

**Option B (whoever has a browser — exhaustive within the light DOM):** open the page in Chrome incognito, F12 → **Console**, paste:
```js
copy([...document.querySelectorAll('a')]
  .filter(a => { const h = a.getAttribute('href');
    return !h || h.trim() === '' || h === '#' || /^javascript:/i.test(h); })
  .map(a => a.outerHTML.slice(0, 160)).join('\n'));
```
This copies every offending anchor's HTML to the clipboard. Run it on the homepage, a blog post, and the contact page. **Limitation (matches Lighthouse):** `querySelectorAll('a')` does **not** reach anchors inside cross-origin iframes or shadow DOM, so widgets that render there won't appear here — which is fine, because Lighthouse won't flag those either. If Option A shows a selector you can't find with this snippet, it is likely inside shadow DOM in the same light-DOM tree — re-run with a shadow-piercing walk only if needed.

### 1.4 The correct fixes
Rule of thumb: **if it navigates, give it a real `href`; if it only performs an action, make it a `<button>`.**

- **Divi social icons (header/footer/Author Box):** in Divi → the **Social Media Follow** module → either fill each network's real profile URL or **delete the empty icons**. An empty field = `href="#"`. This is the single most common Divi culprit and pure config. *(Reversible: re-add the icon.)*
- **Divi "no-link" parent menu items:** if a top-level menu item exists only to reveal a dropdown, give it a real destination (its landing/overview page) in **Appearance → Menus**, or accept it as a dropdown toggle and convert per the button pattern below. Setting a real URL is the low-risk fix.
- **Smart Slider linked slides:** Smart Slider → the slide → **Link** → set a real URL, or remove the link so no anchor is emitted. Slider arrows/bullets in Smart Slider 3 render as `<button>`/role elements by default — **VERIFY** in the Show-details output; if any render as `<a href="#">`, that's a custom skin/theme override to fix.
- **Shared Counts share buttons:** **VERIFY first** — inspect one button in the PSI details. If its `href` is a real share URL, it is already crawlable and needs no change; the removal recommendation in §3 is about weight, not crawlability. Only if it emits `javascript:`/`#` treat it as a crawlable failure.
- **Elfsight WhatsApp:** if (and only if) PSI shows it injects an inline `<a href="#">`, replacing it with the static crawlable button in §2.4 / §3 swaps that for a real `href="https://wa.me/…"` link. If Elfsight renders in an iframe, it isn't the crawlable culprit — but §2.4 is still worth doing for payload/CLS/console reasons.
- **Genuine action-only anchors (a toggle you must keep as JS):** convert the element to a button. Minimal, reversible pattern, styled to still look like a link:
```html
<button type="button" class="link-like" aria-label="Describe the action">Toggle</button>
```
```css
.link-like{background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer}
.link-like:focus-visible{outline:2px solid currentColor;outline-offset:2px}
```

**Expected impact:** SEO **92 → 96–100**. `crawlable-anchors` is pass/fail; clearing every flagged anchor removes the only failing SEO audit shown. No performance movement — this is markup-only.

---

## 2. Best Practices 96 → 100: "Browser errors were logged to the console"

**Critical framing correction:** the Lighthouse `errors-in-console` audit ("Browser errors were logged to the console") only counts console entries at **severity `error`**, uncaught runtime exceptions, and **failed network requests (4xx/5xx)**. It does **not** count `console.warn`, `[Deprecation]` notices, `[Intervention]` messages, or entries in the DevTools "Issues" panel. **So do not assume the failure is the Meta Pixel deprecation notice — that is almost certainly a warning and may not touch the score at all. Read the actual error first (§2.1), then fix that.** Reaching 100 requires clearing **all** error-level entries, so confirm this is the only failing Best-Practices audit (see §2.5).

### 2.1 METHOD — read the errors precisely
**From PSI:** Best Practices → **"Browser errors were logged to the console"** → **Show details** → each row gives the error text **and its source URL**. That URL tells you which plugin/third-party emitted it. Note the row's nature: a JS exception vs. a failed request.

**From a browser (definitive):** Chrome incognito → F12:
- **Console** tab → set the level filter to **Errors** only → reload → note every red entry (message + source `file:line`). Ignore yellow warnings — they don't affect this audit.
- **Network** tab → sort by **Status** → anything **4xx/5xx (red)** is a failed request that logs a console error (a missing font, image, or JS/CSS asset).
- Filter the console by **"Mixed Content"** to catch `http://` assets on the `https://` page (note: only *blocked/active* mixed content is an error; passive mixed content like an `http://` image is a warning and won't score).

### 2.2 Resolve by class

**(a) Meta Pixel deprecation — likely a WARNING, verify before acting.** The **Meta Pixel for WordPress** plugin shows a deprecation notice tied to Meta retiring the old Business-Extension/login flow in favor of **Facebook Login for Business**. **But confirm in §2.1 whether it logs at `error` level or `warn` level.** If it's a warning, removing the plugin will **not** change the Best Practices score — treat its removal (§3) as a payload/maintenance decision, not a Best-Practices fix.
→ **If** the console shows a genuine **error** from `fbevents.js` / the plugin, and Meta Pixel tracking is **not in active use for ad campaigns**, remove the plugin (see §3). **If ad tracking IS in use, do not delete blind** — you'd lose Facebook conversion attribution. In that case deactivate-then-observe first, and re-implement via **Meta's Conversions API (server-side)** or a **GTM** container before deleting.
→ **Correction to the original draft:** Meta Pixel is a **Facebook** tag; it is **not** a duplicate of Site Kit/GA4 (different platform). Removing it stops Facebook tracking, not a Google-analytics duplicate. Only call it "duplicate" if a *second* Facebook pixel also fires (e.g. one via plugin + one via GTM) — check for that.

**(b) 404 for a missing asset/font/image.** Every failed request logs a console error and directly trips this audit. Common on a Divi site that has swapped plugins: a stylesheet references a font/image that no longer exists, or a deleted plugin (you already removed WP Mail SMTP) left a dangling enqueue.
→ **Fix:** from the Network 404 row, open the referencing file. If it's a real missing file, re-upload it; if it's an orphaned reference, remove the enqueue/CSS line (a favicon or a font file are the usual suspects). Confirm the row turns 200. *(Reversible: keep a copy of any CSS line you remove.)*

**(c) Third-party JS errors — Elfsight / Trustindex / Smart Slider.** External widgets frequently throw error-level exceptions.
→ **Fix:** replacing **Elfsight WhatsApp** with the static link (§2.4) and **scoping/lazy-loading Trustindex reviews** (§3) removes their scripts from most page loads and thus their errors. Any Smart Slider error usually traces to a mis-set slide link or a JS-delay conflict — verify after the slider is scoped and after confirming FlyingPress isn't delaying its init script (add it to FlyingPress's JS "delay exclusions" if so).

**(d) Mixed content.** An active `http://` asset (script/stylesheet) on the HTTPS page is **blocked** and logs an error; passive mixed content (images) logs a warning and is allowed.
→ **Fix (with backup):** **First run UpdraftPlus → Backup Now (files + database).** Then run **Better Search Replace**: **Search** `http://thrivedowntown.com` → **Replace** `https://thrivedowntown.com`, select **all tables**, run with **"Run as dry run?" checked first**, review the count, then uncheck and run live. BSR handles PHP-serialized data safely, and the search is scoped to your own domain so external `http://` links are untouched. Clear FlyingPress cache and reconfirm the console is clean. *(This is the legitimate one-time use of BSR before you deactivate it in §3. Reversal: restore the UpdraftPlus backup.)*

### 2.3 Note on "Agentic Browsing 1/2" (see also §4)
The **"Browser errors were logged to the console"** audit and the agentic-browsing failure both punish an unstable runtime; clearing error-level console entries can help the agentic result too. But the agentic failure is primarily the a11y-tree issue — covered in §4.

### 2.4 Static WhatsApp button (replaces Elfsight — helps console + crawlable + CLS + payload)
Add via **WPCode Lite → + Add Snippet → Add Your Custom Code (Manual) → HTML Snippet**. Paste the HTML **and** the CSS inside a single `<style>` block (an HTML snippet does not apply a bare CSS block — it must be wrapped in `<style>`), or create the CSS as a separate **CSS Snippet**. Set **Location = Site Wide Footer**, **Auto Insert = Active**. Deactivating the snippet fully reverts it.

```html
<a class="tdc-wa" href="https://wa.me/1XXXXXXXXXX" target="_blank" rel="noopener"
   aria-label="Chat with Thrive Downtown on WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.9c0 2.1.55 4.06 1.6 5.82L2 22l4.42-1.7a9.9 9.9 0 0 0 5.62 1.74h.01c5.46 0 9.9-4.45 9.9-9.9C21.95 6.45 17.5 2 12.04 2Zm5.8 14.16c-.24.68-1.4 1.3-1.94 1.35-.5.05-1.13.07-1.82-.11-.42-.11-.96-.31-1.66-.6-2.92-1.26-4.83-4.2-4.98-4.4-.14-.2-1.18-1.57-1.18-3s.75-2.12 1.02-2.41c.27-.29.58-.36.78-.36l.56.01c.18.01.42-.07.66.5.24.58.82 2 .89 2.14.07.14.12.31.02.5-.09.2-.14.31-.28.48l-.42.49c-.14.14-.28.29-.12.57.16.29.7 1.16 1.51 1.88 1.04.93 1.92 1.22 2.2 1.36.27.14.43.12.59-.07.16-.2.68-.79.86-1.06.18-.27.36-.22.61-.13.25.09 1.58.75 1.85.88.27.14.45.2.52.31.07.12.07.68-.17 1.36Z"/>
  </svg>
</a>
```
Wrap this CSS in `<style>…</style>` inside the same HTML snippet (or paste it as a separate CSS Snippet):
```css
.tdc-wa{position:fixed;right:18px;bottom:18px;z-index:9990;width:56px;height:56px;
  display:flex;align-items:center;justify-content:center;border-radius:50%;
  background:#25D366;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.25);text-decoration:none}
.tdc-wa:focus-visible{outline:3px solid #128C7E;outline-offset:2px}
```
- Replace `1XXXXXXXXXX` with the real number as **digits only** in international format — country code + number, **no `+`, no spaces, no leading `00`** (e.g. `16045551234`).
- **VERIFY overlap:** a fixed bottom-right button can collide with a back-to-top button, cookie banner, or the Divi mobile menu. Load mobile + desktop and adjust `right`/`bottom`/`z-index` if it overlaps.
- **Sequencing:** publish this button **before** deactivating Elfsight (§6), then load a page and confirm **only one** WhatsApp button appears (no duplicate).

Because it's `position:fixed`, it is out of normal flow and **reserves no layout box → contributes no CLS**, and it adds ~0 KB vs Elfsight's external platform bundle. It's a real, crawlable, accessibly-named link.

**Expected impact:** Best Practices **improves toward 100** *if* Elfsight was the/an error source (VERIFY). Also drops third-party JS and one late-mounting CLS source — assists the mobile field-CLS fix owned by the CLS workstream.

### 2.5 Confirm no other Best-Practices audit is failing
At 96 there may be a second low-weight audit in play. In the PSI Best Practices section, scan for other failing/flagged scored audits beyond the console one — e.g. deprecated APIs in use, a failing HTTPS/HSTS item, or an image with incorrect aspect ratio. (Informational-only items like "Uses third-party cookies," CSP/COOP advisories, and missing source maps generally do **not** deduct.) Clearing console errors returns you to 100 **only if** it's the sole failing scored audit.

---

## 3. Plugin Bloat Audit — all 32, classified

Legend: **KEEP** · **DEACTIVATE-ON-PROD** (dev/one-time tool, reactivate on demand) · **REMOVE** (delete) · **CONSOLIDATE/SCOPE** (keep but restrict loading or fold into another tool). "FE" = front-end JS/CSS weight. **Every removal below is preceded by an UpdraftPlus backup (§6) and is reversible by reinstalling/reactivating.**

| # | Plugin | Verdict | One-line reason (FE weight / security / redundancy) |
|---|---|---|---|
| 1 | **Advanced Custom Fields PRO** | KEEP | Back-end field engine; ~0 FE assets; templates depend on it. |
| 2 | **Author Box WP Lens** | REMOVE / CONSOLIDATE | Adds FE CSS + icon font + `href="#"` social anchors on posts; rebuild box in Divi/theme or a tiny snippet **before** removing. |
| 3 | **Better Search Replace** | DEACTIVATE-ON-PROD | One-time migration/URL tool (use it for the http→https pass in §2.2, then deactivate); DB-write = security surface. |
| 4 | **Classic Editor** | KEEP | Admin-only, 0 FE; needed for editorial stability with Divi/ACF. |
| 5 | **Custom Post Type UI** | KEEP (migrate later) | Admin-only, 0 FE; long-term, export "Get code" into a snippet and remove to shrink admin surface. **Do not remove while CPTs are registered by it** or those post types disappear. |
| 6 | **Elfsight WhatsApp Chat CC** | **REMOVE** (after §2.4 live) | Heavy external `platform.js`, late-mounting widget (CLS), external cache-lifetime + possible console noise; replace with static button (§2.4). |
| 7 | **Mail Mint (free)** | CONSOLIDATE (investigate) | Newsletter/automation; if newsletters aren't actively sent, remove; else scope opt-in form assets to pages that have them. **Confirm no active automations/lists before removing.** |
| 8 | **Mail Mint Pro** | CONSOLIDATE (investigate) | Pro add-on to #7 — keep-both or remove-both; decision hinges on whether email automations actually run. |
| 9 | **External links new tab - nofollow** | CONSOLIDATE | Rewrites links at runtime; replace with a WPCode `the_content` filter or drop. Low priority — not required for any failing audit. |
| 10 | **FluentSMTP** | KEEP | Deliverability; back-end only, 0 FE; correct single SMTP tool now that WP Mail SMTP is gone. |
| 11 | **FlyingPress** | KEEP (core) | Primary caching/optimization driver. |
| 12 | **Gravity Forms** | KEEP + SCOPE | Primary forms but heavy `gform` CSS/JS; ensure assets load **only** where a form exists (GF has a "No conflict"/conditional output setting + FlyingPress dequeue elsewhere). Test every form after scoping. |
| 13 | **Gravity Forms Polls Add-On** | **REMOVE** | License error + polls almost certainly unused; adds JS/CSS. Remove **only after confirming no live poll** exists on any page. |
| 14 | **Health Check & Troubleshooting** | DEACTIVATE-ON-PROD | Dev diagnostic; 0 FE but unneeded admin surface; reactivate when debugging. |
| 15 | **Meta Pixel for WordPress** | REMOVE (conditional) | See §2.2(a): remove only if it's a real error source **and** Facebook tracking isn't in active use; otherwise migrate to CAPI/GTM first. Not a Site Kit duplicate. |
| 16 | **One User Avatar** | KEEP or REMOVE | Minimal FE; keep only if custom avatars appear in author box/reviews — otherwise redundant with Gravatar; overlaps #2. |
| 17 | **Post Type Switcher** | DEACTIVATE-ON-PROD | Admin-only editorial tool, 0 FE; reactivate on demand. |
| 18 | **Rank Math SEO** | KEEP (core) | SEO; **not** the cause of "links not crawlable." Leave as-is. |
| 19 | **SEO Table Of Contents** | CONSOLIDATE/SCOPE | FE CSS/JS + in-page anchors; load only on posts, or fold into Rank Math/Divi TOC; verify its anchors point to real IDs (crawlable-safe). |
| 20 | **Shared Counts** | REMOVE (weight) | Share buttons = FE JS/CSS + possible external count-API calls; low value for a counselling site. **Note:** its anchors are usually crawlable (real share URLs) — remove for weight, not for the SEO audit (VERIFY §1.4). |
| 21 | **Site Kit by Google** | KEEP (single Google source) | Canonical GA4/Search Console. Verify there isn't a *second* GA tag hardcoded in Divi/WPCode. |
| 22 | **Smart Slider 3 Pro** | KEEP + SCOPE to homepage | Enqueues slider assets where used; if it loads site-wide, scope to front page (§3.1) and set explicit hero dimensions to kill CLS. |
| 23 | **SVG Support** | KEEP | Enables logo/SVG; minimal FE. Security: keep **Sanitize SVG** ON and restrict SVG uploads to administrators. |
| 24 | **TablePress** | KEEP + SCOPE | Loads only where a table exists; **turn OFF the DataTables (jQuery) feature** unless a table needs sort/search — that's avoidable JS. Setting lives in TablePress → each table's "Table Features for Site Visitors," or the global default. |
| 25 | **UpdraftPlus** | KEEP | Backups/rollback safety net; 0 FE. **Run a full backup before any change below.** |
| 26 | **WebP Express** | KEEP (avoid double-pipeline) | WebP delivery; ensure FlyingPress is **not also** converting/serving WebP/AVIF — pick one image pipeline to avoid double-processing and broken `<picture>` output. |
| 27 | **Widgets for Google Reviews (Trustindex)** | CONSOLIDATE/SCOPE + lazy | Heavy Trustindex CSS/JS + review images + external calls (payload + late-embed CLS); load only on review pages, enable Trustindex's **cache/CDN + "load after page load"/lazy** option, or render static. Reserve fixed height to avoid CLS. |
| 28 | **Wordfence Security** | KEEP (tune) | Essential with a 32-plugin surface; minimal FE; if admin overhead is high, consider running **Wordfence Login Security** (lighter) instead of the full suite. |
| 29 | **WordPress Importer** | DEACTIVATE-ON-PROD / REMOVE | One-time import tool, 0 FE; reinstall in seconds when needed. |
| 30 | **WP File Manager** | **REMOVE (security)** | Filesystem access from wp-admin and a history of critical file-manager CVEs makes it a high-value target. Use host SFTP/cPanel file manager instead. Highest-priority removal. |
| 31 | **WPCode Lite** | KEEP (core) | Snippet manager you'll use to deploy these fixes; outputs only what you add. |
| 32 | **Divi Supreme Modules Lite** (listed under theme) | CONSOLIDATE/SCOPE | Extra Divi modules can load CSS/JS site-wide; keep only if modules are in use and enable its performance/asset-scoping option. **VERIFY** modules aren't in use on live pages before disabling assets. |

> Count note: the brief lists 31 named plugins + WP Mail SMTP already deleted; the "Divi Supreme" modules plugin (row 32) is the likely 32nd active plugin — audited above regardless.

### 3.1 Scope Smart Slider to the homepage (WPCode, reversible)
**Primary, safest route is FlyingPress JS/CSS exclusions** (owned by the perf workstream) — do that first. The PHP below is a secondary belt-and-suspenders dequeue.

**Important caveat before relying on this snippet:** Smart Slider 3 frequently enqueues its assets **on demand, at render time** (when the slider shortcode/Divi module actually renders in the body), which can be **later** than `wp_enqueue_scripts`. In that case a `wp_enqueue_scripts` dequeue at priority 100 will find nothing to remove and silently do nothing. If View-Source shows the slider assets loading on non-home pages *despite* this snippet, the assets are being enqueued on-render — use the FlyingPress exclude route instead (or dequeue on the `wp_print_footer_scripts`/`wp_footer` pass). This snippet only helps when Smart Slider enqueues globally.

**WPCode → + Add Snippet → Add Your Custom Code → PHP Snippet.** Paste the code **without** wrapping `<?php ?>` tags (WPCode's PHP editor expects raw PHP). Location = **Run Everywhere** (or "Frontend Only" if offered — the hook is front-end only either way). Then **test the homepage slider AND a subpage** before trusting it; deactivating the snippet reverts instantly.

```php
add_action( 'wp_enqueue_scripts', function () {
    // Homepage keeps the slider; everywhere else drops its assets.
    // If sliders are ALSO used on specific other pages, widen this guard, e.g.:
    // if ( is_front_page() || is_page( array( 'about', 'services' ) ) ) { return; }
    if ( is_front_page() ) {
        return;
    }
    // VERIFY these handle names via View-Source (search "smartslider" / "n2-"); Smart Slider versions them.
    foreach ( array( 'smartslider-frontend', 'n2-runtime', 'n2-css', 'smartslider-frontend-css' ) as $handle ) {
        if ( wp_script_is( $handle, 'enqueued' ) ) {
            wp_dequeue_script( $handle );
        }
        if ( wp_style_is( $handle, 'enqueued' ) ) {
            wp_dequeue_style( $handle );
        }
    }
}, 100 );
```

Safety notes: the guarded closure fatals on nothing — `wp_script_is`/`wp_style_is` return `false` for unknown handles and the loop no-ops. `is_front_page()` is available on `wp_enqueue_scripts`. It never runs in wp-admin (the hook is front-end only). Do **not** dequeue on the front page or where a slider actually renders — that would break the hero. Reversal = deactivate the snippet.

### 3.2 Net expected reduction
Removals + scoping attack the exact flagged audits (Reduce unused JS ~407 KiB, JS execution 7.0 s, main-thread 3.9 s, payload 3,350 KiB, external cache lifetimes ~372 KiB). **These KiB figures are estimates** — treat the ranges as directional, not guaranteed, and re-measure with PSI after each change.

| Action | ~JS/CSS + payload removed (estimate) |
|---|---|
| Remove Meta Pixel (`fbevents.js`) — if removed | ~40–70 KiB JS (+ console error only if it was an error) |
| Replace Elfsight WhatsApp | ~150–300 KiB external JS + CLS source |
| Remove Shared Counts | ~15–30 KiB |
| Remove GF Polls | ~10–20 KiB (where loaded) |
| Scope Smart Slider off non-home pages | ~100–150 KiB per subpage |
| Scope/lazy Trustindex reviews | ~100–200 KiB off non-review pages |
| Scope Gravity Forms off form-less pages | ~90–150 KiB off those pages |
| Remove Author Box (+ icon font) | ~10–20 KiB |
| TablePress DataTables OFF | ~30–50 KiB (if currently on) |
| Scope Divi Supreme | ~20–50 KiB |

**Homepage:** roughly **250–450 KiB** off the critical path (mostly the ~407 KiB unused-JS + third-party execution). **Interior pages:** **300–600 KiB** each once Smart Slider/GF/Trustindex stop loading globally. Directly lifts lab Performance and reduces the "Reduce JS execution time / Minimize main-thread work / long tasks / non-composited animations" diagnostics.

---

## 4. Agentic Browsing 1/2 — "Accessibility tree is not well formed"

This is the AI/agentic-browsing readiness check. It fails when the **accessibility tree** — the semantic model assistive tech and AI agents read — is malformed: missing/duplicate landmarks (more than one `<main>`, no `<nav>`/`<header>`/`<footer>` roles), interactive elements with **no accessible name** (icon-only social/WhatsApp anchors, unnamed buttons), invalid or orphaned ARIA (`aria-labelledby` pointing at a non-existent ID, roles without required names), and broken heading order. It is **derived from the same DOM semantics that produce your Accessibility 78** — it is not a separate fix list.

**Cross-reference the Accessibility workstream.** Resolving it and reaching **2/2** comes from the same work that lifts a11y ≥ 95:
- One `<main>` landmark; proper `<header>`/`<nav>`/`<footer>`; label duplicate nav regions (`aria-label="Primary"` / `"Footer"`).
- Give **every interactive icon** an accessible name — `aria-label` on the WhatsApp button (done in §2.4), social icons, hamburger toggle, slider arrows.
- Remove/repair invalid ARIA; ensure logical heading order (single `<h1>`, no skipped levels).

Note the **triple-duty overlap**: the inline icon-only social/menu anchors are simultaneously the "links not crawlable" failures (§1), the a11y "missing name" failures, and the agentic-tree malformations. Fixing them once — real `href` + `aria-label`, or convert to a named `<button>` — clears all three.

---

## 5. Expected score deltas (this workstream)

| Metric | Now | After | Driver / caveat |
|---|---|---|---|
| **SEO** | 92 | **96–100** | Clear every non-crawlable anchor (§1) — high confidence, markup-only |
| **Best Practices** | 96 | **100** | Zero **error-level** console entries (§2) — only if the flagged item is at `error` severity and is the sole failing scored audit; VERIFY first |
| **Agentic Browsing** | 1/2 | **2/2** | Landmarks + accessible names + valid ARIA (§4, via a11y workstream) |
| **Performance (assist)** | 79/80 | ↑ | est. ~250–600 KiB less JS/CSS/payload per page (§3) — combines with the perf workstream toward ≥ 95; re-measure |
| **Accessibility (assist)** | 78/80 | ↑ | Named interactive elements (§1.4, §2.4, §4) |

---

## 6. Safe execution order (all reversible)

1. **UpdraftPlus → Backup Now** (files **+** database) before touching anything. This is your rollback. Note where the backup is stored.
2. Deactivate **one plugin at a time**, then: **FlyingPress → Cache → Clear/Purge all cache** (and purge any host/CDN cache, e.g. Cloudflare, if present) → load the **homepage + one blog post + the contact page (with form)** in **incognito** → open DevTools **Console (Errors filter)** and submit the contact form → re-run PSI. Only then proceed to the next.
3. Order (zero-risk first → higher-verification last):
   1. Health Check & Troubleshooting
   2. WordPress Importer
   3. Post Type Switcher
   4. Gravity Forms Polls Add-On *(confirm no live poll)*
   5. **WP File Manager** *(security — highest priority removal; switch to SFTP first so you retain file access)*
   6. Shared Counts *(confirm no share buttons wanted; not required for the SEO audit)*
   7. Meta Pixel for WordPress *(only after confirming it's a real error source AND deciding GTM/CAPI replacement — deactivate & observe before deleting if tracking is live)*
   8. Author Box WP Lens *(after rebuilding the box in Divi/snippet)*
   9. Elfsight WhatsApp *(only after the §2.4 static button is live and you've confirmed a single button shows)*
   10. Better Search Replace *(run the http→https pass first per §2.2(d), then deactivate)*
4. **Scope (do NOT deactivate):** Smart Slider (§3.1 or FlyingPress excludes), Trustindex reviews, Gravity Forms, Divi Supreme, TablePress DataTables. **Retest the same three pages after each** — confirm the slider, mobile menu, and every form still work.
5. **Investigate before removing:** Mail Mint free + Pro *(confirm no active email automations/lists)*; One User Avatar *(confirm no custom avatars in use)*; Custom Post Type UI *(confirm no CPTs depend on it)*.

If any step regresses a page, a form, the slider, the mobile menu, or the console, **reactivate that one plugin (or deactivate the WPCode snippet)** — each change here is independently reversible. Re-run PSI only after cache is cleared, or you'll be reading a stale cached page.

---

### Sources
- Lighthouse `crawlable-anchors` audit definition & fixes: [Unlighthouse — Fix Uncrawlable Links](https://unlighthouse.dev/learn-lighthouse/seo/crawlable-anchors), [Lighthouse issue #15127 (empty href)](https://github.com/GoogleChrome/lighthouse/issues/15127)
- Lighthouse `errors-in-console` audit (counts error-level console entries, exceptions, and failed requests — not warnings): [web.dev — No browser errors logged to the console](https://web.dev/articles/errors-in-console)
- Meta social-plugin / SDK deprecation timeline: [Smash Balloon — Facebook Page Plugin limitations](https://smashballoon.com/facebook-page-plugin-limitations/)
- Meta Pixel for WordPress plugin (Business-Extension/login flow): [WordPress.org — Meta pixel for WordPress](https://wordpress.org/plugins/official-facebook-pixel/)

**Note to caller:** the items I could not verify against the live DOM (403-blocked) are: (1) the exact list of failing anchors and their emitting modules, (2) the exact console-error text **and its severity level** (error vs warning — this determines whether the Meta Pixel/Elfsight removal actually moves Best Practices), and (3) the exact Smart Slider handle names. §1.3 and §2.1 give the extraction methods to pull (1) and (2) verbatim from the PSI "Show details" panel; §3.1 tells you how to confirm (3) via View-Source. Every fix above has a strong default that applies without that list, but **read the PSI details before deleting any plugin for a Best-Practices reason.**