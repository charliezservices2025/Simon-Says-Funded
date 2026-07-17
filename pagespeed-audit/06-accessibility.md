> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. Code lives in snippets 06, 07 + css-02. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

# Accessibility Workstream — Thrive Downtown (Divi) → Lighthouse A11y 78/80 ➜ ≥ 95

Everything below is **reversible** (deactivate the WPCode snippet or delete the Additional-CSS block = full revert) and **blind-applicable**. No live-site access was needed to author it, but every assumed selector is tagged **VERIFY** with the exact method to confirm it against the real DOM.

> **Hardening changelog (safety fixes applied to the original draft — read this first):**
> 1. **Snippet B delivery location corrected.** The draft said auto-insert *"Site Wide Footer"* — that is a WPCode HTML **insertion** location and is *wrong* for this snippet, which is PHP that registers its own `add_action('wp_footer', …)`. Pairing them either double-injects the `<script>` or registers the action too late. It must use an **execution-scope** location: **Frontend Only** (or Run Everywhere). Fixed below.
> 2. **Hamburger/search no longer get a broken button.** The draft added `role="button"` + `tabindex="0"` to icon `<span>`s **without a keyboard handler** — that creates a focusable control announced as a button that does nothing on Enter/Space, i.e. a **new WCAG 2.1.1 (Keyboard) failure**. The corrected Snippet B only promotes a *non-interactive* element to a button when it **also** wires Enter/Space → `click()` (once, idempotently); native `<a>`/`<button>` get a name only and keep their semantics.
> 3. **`isEmpty()` hardened to `hasName()`** — now also detects names from `img[alt]`, `svg <title>`, and the `title` attribute, so we never clobber an existing accessible name.
> 4. **Elfsight shadow-DOM walk gated + wrapped.** The `querySelectorAll('*')` deep walk now runs only when a WhatsApp/Elfsight container is actually present, the retry count is reduced, and the whole routine is wrapped in `try/catch` so a thrown error can never log to the console (which would cost the Best-Practices 96) or abort labeling.
> 5. **Snippet C selectors scoped.** The draft's blanket `body, p, li, a { color: … }` can **darken light-on-dark text** in dark Divi sections/hero/CTA and thereby *introduce* contrast failures. Selectors are now scoped to content and exclude buttons/menus, with an explicit dark-section exemption method.
> 6. **Snippet A lang check** tightened so it can't be fooled by `xml:lang=`.

**Delivery mechanism summary**
- Snippet A (lang fallback) → **WPCode › Add Snippet › Add Your Custom Code › PHP Snippet**; Insert Method **Auto Insert**, Location **Frontend Only** (Run Everywhere is also fine).
- Snippet B (landmark + accessible names + duplicate-link fix) → **WPCode › PHP Snippet**; Insert Method **Auto Insert**, Location **Frontend Only** (**NOT** "Site Wide Footer"). It prints one guarded inline `<script>` on `wp_footer`.
- Snippet C (contrast) → **Appearance › Customize › Additional CSS** (easiest to revert) *or* WPCode CSS Snippet (Auto Insert · Site Wide Header/Footer).

> **CRITICAL FlyingPress interaction (do this or Snippet B silently fails the audit).** If FlyingPress's **Delay JavaScript execution** option (FlyingPress › Settings › **JavaScript** tab) is ON, our inline script is held until the first user interaction — Lighthouse never interacts, so the audit would still fail. Add the keyword `tdc-a11y` to the exclusion list that is paired with the **Delay JavaScript** option (labeled "Exclude Keywords" / "Excludes" directly under the Delay toggle — this is the *Delay* exclude list, **not** the general "Exclude from optimization" field). Snippet B carries both `id="tdc-a11y-fixes"` **and** an inline `/* tdc-a11y */` marker comment so the keyword matches even if an optimizer strips the id. Do **not** exclude anything else here. If Delay JavaScript is OFF, this step is harmless and can be skipped.

---

## ROI-ordered fix list (accessibility only)

| # | Fix | axe rule | LH weight | Expected A11y delta |
|---|-----|----------|-----------|---------------------|
| 1 | Color contrast overrides | `color-contrast` | **7 (highest)** | **+6 to +10** |
| 2 | Accessible names (icon buttons/links) | `button-name`, `link-name` | 7 each | **+4 to +8** |
| 3 | `main` landmark on `#main-content` | `landmark-one-main` | 3 | **+2 to +3** |
| 4 | `<html lang>` present/correct | `html-has-lang` / `html-lang-valid` | 3 | **+2 to +3** |
| 5 | Identical links same purpose | `identical-links-same-purpose` | 3 (review) | **+1 to +3** |
| 6 | `aria-hidden`-focus / role mismatch / malformed tree | `aria-hidden-focus`, `aria-allowed-role`, `duplicate-id-aria` | 4–7 | **+2 to +5**, fixes Agentic-Browsing |

Contrast + names together typically account for the bulk of a 78→95 jump on a Divi site, because they carry the two heaviest axe weights and usually have several failing nodes each.

---

## 1. `<html lang>` — ensure `lang="en-CA"`

**Native (correct) way — do this first:**
`WP Admin › Settings › General › Site Language` → select **English (Canada)**. WordPress then emits locale `en_CA`, and Divi's header (via WordPress `language_attributes()`) renders `<html lang="en-CA">`. (Any valid value — even `en-US` — passes `html-has-lang`; `en-CA` is just locale-correct for a Vancouver practice.) This setting is trivially reversible (switch it back).

**How to confirm:** View source of the homepage (or `curl -s https://thrivedowntown.com | head`), check the opening `<html …>` tag contains `lang="…"`.

**Fallback (only if a cache/optimizer ever strips it)** — Snippet A hooks the core `language_attributes` filter and injects `lang` only when it's genuinely missing (a word-boundary check so it is *not* fooled by an existing `xml:lang=`), so it never fights the native value:

```php
/* SNIPPET A — WPCode PHP · Auto Insert · Location "Frontend Only" (or Run Everywhere)
   Accessibility: guarantee <html> has a lang attribute (belt-and-suspenders fallback). */
add_filter( 'language_attributes', function ( $output ) {
    // Match a real lang= token only (won't match xml:lang=), and tolerate null/empty.
    if ( ! preg_match( '/(^|\s)lang=/i', (string) $output ) ) {
        $output = trim( 'lang="en-CA" ' . $output );
    }
    return $output;
}, 20 );
```

Safe: returns `$output` unchanged in the common case, never fatals, ignores the filter's 2nd `$doctype` arg. Reversible: deactivate the snippet. **Delta: +2–3.**

---

## 2. `main` landmark — add `role="main"` to `#main-content` (never a second main)

Divi's DOM is (VERIFY once in DevTools — a child theme can alter it):

```
#page-container
 ├─ header#main-header
 ├─ #et-main-area
 │   ├─ #main-content   ← tightest content wrapper (target THIS)
 │   └─ footer#main-footer
```

Target **`#main-content`**, *not* `#et-main-area` (the footer lives inside `#et-main-area`, so labeling that as `main` would wrongly swallow the footer). Divi prints `<div id="main-content">` with no server-side filter hook, so the safe, reversible route is a guarded attribute set in Snippet B. axe runs against the post-JS DOM, so a JS-added role is honored by both Lighthouse and screen readers. The guard `!document.querySelector('main,[role=main]')` guarantees we never create a second main (important if the child theme or a plugin already emits one).

**Delta: +2–3.** (Included in Snippet B below.)

---

## 3. Accessible names for icon-only controls

Confirmed *candidates* on the Divi + Smart Slider + Elfsight stack, each labeled only when a name is genuinely absent (won't clobber Divi's own hidden-text names). **Every selector here is VERIFY — confirm the element type and class against the live DOM before trusting it:**

| Control | Selector (**VERIFY AGAINST LIVE DOM**) | Native option? |
|---|---|---|
| Mobile hamburger | `#et_mobile_nav_menu .mobile_menu_bar` | None in Divi — needs snippet |
| Header search icon | `#et_search_icon` | None — needs snippet |
| Smart Slider arrows | `.nextArrow`, `.previousArrow` | **Smart Slider 3 › (open slider) › right-panel Controls › Arrow** — set the *Aria label* field if the version exposes it (do this first; snippet only fills gaps) |
| Smart Slider dots | `.n2-bullet` | Same right-panel *Bullet* control |
| Divi social-follow icons | `.et_pb_social_media_follow_network a`, `.et-social-icon a` | Divi usually emits hidden network text (`title="Follow"` + a name span) already — **VERIFY**; snippet fills only empties |
| Elfsight WhatsApp button | `[class*="elfsight-app"] a`, `[class*="whatsapp"] a` (may be **shadow DOM** or **iframe**) | Check the Elfsight editor; snippet includes an *open* shadow-DOM fallback |

**Important semantic rule (baked into Snippet B):** `button-name`/`link-name` only fire on real `<button>`/`[role=button]`/`<a href>`. If the hamburger/search is a plain `<span>` with no role, it is **not** what's failing those rules, and blindly adding `role="button"`+`tabindex` to it would create a keyboard-inoperable button. So Snippet B:
- adds **only `aria-label`** to native `<a>`/`<button>` controls, and
- promotes a *non-interactive* element to a button **only together with** an Enter/Space → `click()` handler, so it stays keyboard-operable.

**VERIFY method (which controls actually fail):** Chrome DevTools → inspect each icon → open the **Accessibility** pane; if *Name* is blank AND the element is a button/link, it fails `button-name`/`link-name`. Or run in console:

```js
[...document.querySelectorAll('a[href],button,[role=button]')].filter(n =>
  !n.textContent.trim() &&
  !n.getAttribute('aria-label') &&
  !n.getAttribute('aria-labelledby') &&
  !(n.getAttribute('title')||'').trim() &&
  !n.querySelector('img[alt]:not([alt=""])'))
```

**Elfsight caveat:** the WhatsApp widget often renders inside a **shadow root**. axe-core traverses **open** shadow DOM, so a missing name there still costs points; a normal `querySelectorAll` can't reach it, so Snippet B includes a bounded open-shadow-DOM walker. If Elfsight uses a **closed** shadow root or an **`<iframe>`**, *neither axe nor any external script can see or label it* — in that case it is not scored by Lighthouse (so it isn't hurting the number) and the fix is to set the label inside the Elfsight editor, or replace the widget with a plain labeled `<a href="https://wa.me/…" aria-label="Chat on WhatsApp">` (the perf workstream should defer/lazy-load it regardless).

**Delta: +4–8.** (Included in Snippet B.)

---

## 4. Snippet B — consolidated landmark + accessible-names + duplicate-link fixer

**WPCode setup:** New **PHP Snippet** → Insert Method **Auto Insert** → Location **Frontend Only** (this is an execution-scope location; do **not** pick "Site Wide Footer", which is for raw HTML insertion). Then add `tdc-a11y` to FlyingPress's Delay-JS exclude list (see the CRITICAL note above).

```php
/* SNIPPET B — WPCode PHP snippet
   Insert Method: Auto Insert · Location: FRONTEND ONLY  (NOT "Site Wide Footer")
   Accessibility: main landmark, accessible names, duplicate-link differentiation.
   Reversible: deactivate the snippet. FlyingPress: add "tdc-a11y" to the Delay-JS excludes.
   Adds ARIA/labels only; the sole interactivity added is a keyboard handler for icons we
   explicitly promote to role=button, so we never ship a keyboard-inoperable button. */
add_action( 'wp_footer', function () { ?>
<script id="tdc-a11y-fixes">
/* tdc-a11y keep-inline marker (redundant FlyingPress Delay-JS exclude anchor) */
(function () {
  function attr(el, a){ return el.getAttribute(a); }
  // True if the element already has ANY accessible name source.
  function hasName(el){
    if (el.textContent && el.textContent.trim()) return true;
    if (attr(el,'aria-label') || attr(el,'aria-labelledby')) return true;
    if ((attr(el,'title')||'').trim()) return true;
    var img = el.querySelector('img[alt]');
    if (img && (img.getAttribute('alt')||'').trim()) return true;
    if (el.querySelector('svg title, svg [aria-label]')) return true;
    return false;
  }
  function labelIfEmpty(el, label){ if (el && !hasName(el)) el.setAttribute('aria-label', label); }
  function labelAll(sel, label){
    document.querySelectorAll(sel).forEach(function(el){ labelIfEmpty(el, label); });
  }
  // Give an icon control a name. Native <a>/<button> keep their semantics (name only).
  // A non-interactive element is promoted to a real, keyboard-operable button.
  function makeControl(el, label){
    if (!el) return;
    var tag = el.tagName.toLowerCase();
    var native = (tag === 'a' && el.hasAttribute('href')) || tag === 'button' || tag === 'input';
    if (native) { labelIfEmpty(el, label); return; }
    if (!el.hasAttribute('role'))     el.setAttribute('role', 'button');
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
    labelIfEmpty(el, label);
    if (!el.dataset.tdcKbd) {                 // bind keyboard activation exactly once
      el.dataset.tdcKbd = '1';
      el.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          el.click();                          // fires Divi's own click handler (bubbles)
        }
      });
    }
  }
  // Bounded OPEN-shadow-DOM search (only invoked when a WhatsApp/Elfsight node exists).
  function deepQuery(root, sel, out, depth){
    out = out || []; depth = depth || 0; if (depth > 6) return out;
    try { root.querySelectorAll(sel).forEach(function(n){ out.push(n); }); } catch(e){}
    try {
      root.querySelectorAll('*').forEach(function(n){
        if (n.shadowRoot) deepQuery(n.shadowRoot, sel, out, depth+1);
      });
    } catch(e){}
    return out;
  }

  function run(){
   try {
    // (3) main landmark — never create a second main
    var mc = document.getElementById('main-content');
    if (mc && !mc.getAttribute('role') && !document.querySelector('main, [role="main"]')) {
      mc.setAttribute('role', 'main');
    }
    // (3a) mobile hamburger — VERIFY selector/element type against live DOM
    document.querySelectorAll('#et_mobile_nav_menu .mobile_menu_bar').forEach(function(el){
      makeControl(el, 'Open menu');
    });
    // (3b) header search icon — VERIFY
    document.querySelectorAll('#et_search_icon').forEach(function(el){
      makeControl(el, 'Search');
    });
    // (3c) Smart Slider arrows + dots (divs; name only)
    labelAll('.nextArrow, [class*="nextArrow"]', 'Next slide');
    labelAll('.previousArrow, [class*="previousArrow"]', 'Previous slide');
    document.querySelectorAll('.n2-bullet').forEach(function(el,i){
      labelIfEmpty(el, 'Go to slide ' + (i+1));
    });
    // (3d) Divi social-follow icons (only if Divi's hidden text/title is absent)
    document.querySelectorAll('.et_pb_social_media_follow_network a, .et-social-icon a').forEach(function(a){
      if (!hasName(a)) {
        var host = a.closest('[class*="et-social-"], [class*="et_pb_social_media_follow_network_"], li') || a;
        var cls  = ((host.className||'') + ' ' + (a.className||''));
        var m = cls.match(/(?:et-social-|network_)([a-z0-9]+)/i);
        a.setAttribute('aria-label', m ? m[1].charAt(0).toUpperCase()+m[1].slice(1) : 'Social profile');
      }
    });
    // (3e) Elfsight WhatsApp (light + OPEN shadow DOM, best-effort; skip the walk if absent)
    if (document.querySelector('[class*="elfsight"], [class*="whatsapp"], a[href*="wa.me"], a[href*="api.whatsapp.com"]')) {
      deepQuery(document, '[class*="elfsight-app"] a, [class*="whatsapp"] a, a[href*="wa.me"], a[href*="api.whatsapp.com"]')
        .forEach(function(a){ labelIfEmpty(a, 'Chat on WhatsApp'); });
    }
    // (5) duplicate "read more" links -> differentiate by post title
    document.querySelectorAll('a.more-link, .et_pb_post .more-link, .et_pb_blog_grid .more-link').forEach(function(a){
      if (!a.getAttribute('aria-label')) {
        var art = a.closest('article, .et_pb_post');
        var t = art && art.querySelector('.entry-title a, .entry-title, h1 a, h2 a, h3 a');
        if (t && t.textContent.trim())
          a.setAttribute('aria-label', (a.textContent.trim()||'Read more') + ': ' + t.textContent.trim());
      }
    });
   } catch (e) { /* never throw: a console error would cost Best-Practices points */ }
  }

  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
  // Late third-party widgets (Smart Slider init, Elfsight, Trustindex): idempotent retries.
  // Bounded to keep main-thread work low (perf workstream cares about TBT/INP).
  var n = 0, iv = setInterval(function(){ run(); if (++n >= 6) clearInterval(iv); }, 1000);
})();
</script>
<?php }, 99 );
```

Every write is guarded by `hasName()` / `!hasAttribute` / dataset flags, so re-runs are idempotent, listeners bind once, and it never overwrites a name Divi/Smart Slider already provide. Setting `aria-*`/`role`/`tabindex` does **not** trigger layout or paint, so this adds **zero CLS**. It does **not** touch jQuery, Divi init, Smart Slider init, or Gravity Forms; the only behavior it adds is an Enter/Space handler on icons it *explicitly* promotes to buttons, which simply re-fires the click Divi already listens for.

> **Regression test after enabling (do not skip):** Tab to the hamburger → press **Enter** → the mobile menu opens; Tab to search → **Enter** → search opens; mouse-click still works on both. If Enter does nothing, the icon's click handler is bound to a *different* element — point the selector at that element (VERIFY in DevTools) rather than the icon glyph.

---

## 5. Identical links have the same purpose

**Typical causes here:** (a) multiple **“read more”** links from the Divi Blog module pointing to *different* posts (same accessible name, different URL → the axe review flags this); (b) social icons duplicated in header **and** footer; (c) logo link + a “Home” menu item — this one is *same purpose* (both → home) and axe treats it as a **pass**, so leave it.

**Fix (already in Snippet B, item 5):** give each “read more” a unique `aria-label` of `"Read more: <Post Title>"`. That makes each link's accessible name unique and purpose-explicit. For duplicated social icons, the `aria-label` from item 3d (network name) is identical *because the purpose is identical* → axe passes. **Delta: +1–3.**

Alternative native route: in the **Divi Blog module › Content** settings some layouts let you drop the separate read-more and rely on the linked title — but the aria-label approach is safer and needs no layout edit. (Note: `identical-links-same-purpose` is a *review*/manual axe item; its scoring weight is small and it may appear under "Manual checks" rather than moving the number — treat this as headroom, not the main lever.)

---

## 6. Contrast — method + strong Divi defaults (Snippet C)

**Read the exact failing element (do this before trusting any selector below — these are the single biggest lever, so get them right):**
1. In PSI, open **“Background and foreground colors do not have a sufficient contrast ratio” › Show audit** — each failing node shows its HTML snippet and computed selector, plus the current ratio and required ratio.
2. Or Chrome DevTools → **Inspect** the element → **Styles** pane shows a contrast ratio with a ✕; the **Accessibility** pane gives the exact foreground/background and the AA/AAA thresholds. `#767676`-on-white is the WCAG AA cutoff for normal text (~4.54:1) — anything lighter fails.

**SAFETY — read before pasting.** Broad element selectors (`body, p, li, a`) will also recolor text that currently sits on **dark** backgrounds (a dark hero, a dark CTA row, a dark footer), turning previously-passing light text into failing dark-on-dark. The rules below are therefore **scoped to `#main-content`** and **exclude buttons/menus**, and there is a dark-section exemption block. Still treat every selector as **PLACEHOLDER-VERIFY** — confirm it against the PSI node list first.

```css
/* SNIPPET C — Additional CSS (Appearance › Customize › Additional CSS) OR WPCode CSS Snippet
   Accessibility: raise Divi's low-contrast text to WCAG 2.1 AA.
   PLACEHOLDER-VERIFY every selector against the live PSI contrast list, and confirm no
   DARK-BACKGROUND text is caught (see the dark-section exemption block below). */

/* Body / paragraph / list text — scoped to CONTENT (not header/footer menus).
   Divi default #666/#999 -> #3d3d3d (~10.4:1 on white). */
#main-content p,
#main-content li,
#main-content .et_pb_text,
#main-content .et_pb_post p { color: #3d3d3d; }

/* Muted meta/date/caption text — often #999 (fails) -> #595959 (~7:1 on white) */
#main-content .post-meta, #main-content .post-meta a,
#main-content .et_pb_blog_grid .post-meta,
.wp-caption-text, .et_pb_image_caption,
.et_pb_slide_description small { color: #595959; }

/* Content links only — exclude Divi buttons and menus. #0b6b75 teal ~= 5.1:1 on white.
   >>> SWAP for Thrive Downtown's VERIFIED accessible brand shade (must be >= 4.5:1) <<< */
#main-content a:not(.et_pb_button):not(.et_pb_more_button):not(.et_pb_promo_button) { color: #0b6b75; }
#main-content a:not(.et_pb_button):not(.et_pb_more_button):not(.et_pb_promo_button):hover,
#main-content a:not(.et_pb_button):not(.et_pb_more_button):not(.et_pb_promo_button):focus { color: #084f56; }

/* Form placeholders — browser default (~#757575) commonly fails -> #595959.
   Keep these as SEPARATE rules: a single invalid vendor selector would drop the whole group. */
::placeholder { color: #595959; opacity: 1; }
::-webkit-input-placeholder { color: #595959; opacity: 1; }
:-ms-input-placeholder { color: #595959; }

/* Buttons — VERIFY the real button background first, then enable EXACTLY ONE line. */
/* .et_pb_button { color: #ffffff; }   // if the button background is dark / brand */
/* .et_pb_button { color: #0b3d43; }   // if the button background is light / outline */
```

**Dark-section exemption (only if a dark-background section lives INSIDE `#main-content`):**

```css
/* If a dark hero/CTA/row inside content got darkened by the rules above, re-lighten it.
   Replace .your-dark-section with the ACTUAL section/row id or class from DevTools,
   and set the intended light color explicitly (do not rely on inherit). */
/*
.your-dark-section p, .your-dark-section li,
.your-dark-section .et_pb_text, .your-dark-section a { color: #ffffff; }
*/
```

**Footer — VERIFY the footer background color first, then enable ONE block only:**

```css
/* IF the footer background is DARK (#222 etc.) — raise light-grey text toward white */
#main-footer, #footer-widgets .et_pb_widget, #footer-widgets p,
#footer-widgets li, #footer-widgets a, #footer-info, #footer-info a { color: #e8e8e8; }
#footer-info a:hover { color: #ffffff; }

/* IF the footer background is LIGHT — darken instead (do NOT also use the block above)
#main-footer, #footer-widgets .et_pb_widget, #footer-info, #footer-info a { color: #3d3d3d; }
*/
```

**Delta: +6–10** (contrast carries the single heaviest axe weight, usually with multiple failing nodes). Reversible: delete the Additional-CSS block or deactivate the WPCode CSS snippet. **After pasting, clear FlyingPress cache and re-check the dark hero, CTA sections, buttons, and footer visually** — the one real risk with contrast CSS is over-reach onto dark backgrounds.

---

## 7. `aria-hidden` / role mismatch + malformed accessibility tree (fixes “Agentic Browsing 1/2”)

“Accessibility tree is not well formed” almost always reduces to one of three concrete, findable defects on this exact stack. All three locate steps below are **read-only console queries — safe to run**.

> **Before updating ANY plugin in this section:** take an **UpdraftPlus backup** (Backup/Restore → *Backup Now*, include plugins + database) so the update is reversible via *Restore* if a vendor build regresses. Update **one plugin at a time**, then clear FlyingPress cache and re-run PSI before doing the next. Do not batch-update all three at once.

**A. `aria-hidden` ancestor of focusable content (`aria-hidden-focus`)** — the classic Divi/Smart-Slider case: cloned/off-screen slides carry `aria-hidden="true"` but still contain focusable `<a>`/`<button>`.
- **Locate (read-only):**
  ```js
  document.querySelectorAll('[aria-hidden="true"] a[href], [aria-hidden="true"] button, [aria-hidden="true"] input, [aria-hidden="true"] [tabindex]:not([tabindex="-1"])')
  ```
  Any hit is a violation.
- **Fix:** **update Smart Slider 3 Pro to the latest version** (back up first, per the note above) — current builds apply `inert`/`tabindex="-1"` to hidden clones correctly. Do *not* hand-strip `aria-hidden` (it breaks slide semantics for screen readers). For the Divi duplicated mobile menu, ensure only the visible menu is exposed (Divi handles this; a stale child theme can regress it).

**B. Role not allowed / ARIA mismatch (`aria-allowed-role`, `aria-required-attr`)** — most often the **Trustindex Google Reviews** embed or **Elfsight** container declaring a `role`/`aria-*` combination axe rejects (e.g. `role="img"` without a name, or `aria-*` on an element whose role doesn't support it).
- **Locate:** PSI → open the specific audit (“[aria-*] attributes do not match their roles” / “Elements use ARIA roles that are not allowed …”) → **Show** → read the exact node/selector. Confirm in the DevTools Accessibility pane.
- **Fix:** update the offending plugin (Trustindex / Elfsight) first — these are vendor-owned nodes and a vendor update is the correct, reversible fix (back up first). If a wrapper *you* control declares a bad role, remove that attribute; do **not** invent a replacement role.

**C. Duplicate IDs used by ARIA (`duplicate-id-aria`)** — a header/module rendered twice (e.g. search or a contact form in both a desktop and a mobile region) can duplicate an `id` referenced by `aria-labelledby`/`aria-controls`, which malforms the tree.
- **Locate (read-only):**
  ```js
  (function(){var s={},d=[];document.querySelectorAll('[id]').forEach(function(e){s[e.id]?d.push(e.id):s[e.id]=1});return [...new Set(d)];})()
  ```
  Any returned id is duplicated.
- **Fix:** rename/remove the duplicate at its source module (usually a copied Divi section or a form placed twice). This is a content-level edit in the Divi Builder — reversible via the module's own settings / Divi's built-in undo, and captured by your UpdraftPlus backup.

Fixing items 1–6 (real `main`, accessible names, valid lang, differentiated links) plus updating **Smart Slider 3 Pro, Trustindex, and Elfsight** to current versions resolves the malformed-tree finding and flips **Agentic Browsing to 2/2**. **Delta: +2–5.**

---

## Post-change verification checklist

1. **Back up first** (UpdraftPlus) before any plugin update. Each snippet/CSS change is independently reversible (deactivate the WPCode snippet / clear the Additional-CSS field).
2. **Clear caches after each change:** FlyingPress → **Purge Everything** (or the "Purge Cache" item in the admin bar); if WebP Express / a CDN / Cloudflare sits in front, purge those too. Also confirm the `tdc-a11y` Delay-JS exclusion is saved.
3. **Re-run PSI on mobile AND desktop.** Field CWV data lags weeks — for this workstream read the **Lighthouse lab Accessibility** score (synthetic, immediate).
4. **Console spot-checks (read-only):** `document.querySelectorAll('[role="main"]').length` returns exactly `1`; the hamburger/search/arrows report a **Name** in the DevTools Accessibility pane; the duplicate-id and `aria-hidden-focus` queries from §7 return empty.
5. **Regression sanity (do not skip):** mobile menu opens/closes by **mouse and by keyboard (Enter/Space on the hamburger)**; header search opens; Smart Slider advances; Gravity Forms submits; the WhatsApp button opens chat; the dark hero/CTA/footer text is still readable (contrast CSS didn't over-reach). ARIA-only additions can't break event handlers, and the one keyboard handler we add only re-fires Divi's existing click — but confirm anyway.

## Expected outcome

Contrast (+6–10) and accessible names (+4–8) alone typically clear the 78→95 gap; landmark, lang, duplicate-link, and the ARIA-tree cleanups add headroom to land **97–100** on both mobile and desktop, with **Agentic Browsing at 2/2**. Every item is revertible by deactivating the single WPCode snippet or removing the Additional-CSS block.

**Cross-workstream note (flag only, not implemented here):** deferring/lazy-loading the Elfsight WhatsApp and Trustindex widgets for the CLS/perf goal will also shrink the a11y surface those third parties expose — coordinate so the perf Delay/Defer settings **keep the `tdc-a11y` exclusion** in FlyingPress Delay-JS, otherwise Snippet B gets held until interaction and the audit regresses.

**Relevant file paths:** none written — the deliverables are the WPCode snippets (A, B) and the CSS block (C) above, applied via wp-admin as described. (No repository files were created or modified for this advisory task.)