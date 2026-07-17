> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. Biggest Performance lever. Code lives in snippets 01–04. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

# JavaScript Workstream — Thrive Downtown (Divi + FlyingPress)

Goal for this workstream: collapse **JS execution 7.0s → ~2s**, **main-thread 3.9s → ~2s**, **TBT 560ms → ~120ms**, and neutralize the **~407 KiB unused / ~11 KiB legacy** JS. The single biggest lever is FlyingPress **Delay JavaScript**; everything else is supporting cleanup. All PHP is WPCode-ready, guarded, and reverts cleanly by toggling the snippet off.

> **Global safety rule for this whole workstream.** After *every* change below (a FlyingPress toggle, an exclude-list edit, or a WPCode snippet), do this before trusting a result:
> 1. **FlyingPress → Cache → Purge Cache** (purge everything), and if WebP Express/host page cache is layered, purge those too.
> 2. Load the affected page **twice** in a fresh incognito window (first load may be uncached).
> 3. Re-run PageSpeed Insights **twice** and read the *second* run (first PSI run after a purge is cold).
> 4. Manually QA the mobile menu, the homepage Smart Slider hero, and a **real Gravity Forms submit** on the contact/booking page.
>
> Every item is reversible: FlyingPress items are toggles; every code change is one WPCode snippet you deactivate to revert. No instruction here edits a theme file, jQuery core, or Gravity Forms execution.

---

## 0. How FlyingPress actually models JS (this changes the whole "include list" question)

FlyingPress is **not** WP Rocket. There is **no "delay only these scripts" include list**. Its model (FlyingPress **Settings → JavaScript** tab; label wording can shift slightly between versions, so match by function, not by an exact string):

- **Minify** — strips whitespace/comments from `.js`.
- **Defer** — adds `defer` so scripts don't block the parser; **relative execution order is preserved**, so jQuery-dependent Divi code stays correct.
- **Delay** — delays **ALL scripts and inline handlers globally** (both `<script src>` and inline `fbq(...)`, `gtag(...)`, Divi inline init) until the **first user interaction** (roughly: scroll, mouse move, touch, key, or click). You do **not** list what to delay — everything is delayed. You only maintain an **exclude keywords** list for the few scripts that must run *immediately*.

**How the exclude-keyword match works (so your lists are reliable):** FlyingPress matches each keyword against the rendered `<script>` tag. WordPress prints every enqueued script with `id="{handle}-js"` (and inline `-js-before` / `-js-after` / `-js-extra` variants), and the tag also contains the `src` URL. **So both a handle name (`divi-custom-script`) and a filename fragment (`custom.min.js`) are valid keywords** — one matches the `id`, the other matches the `src`. When in doubt, list **both** forms.

So the deliverable is: **turn Delay on** (it captures Meta Pixel, gtag, Elfsight, Trustindex, Shared Counts, Mail Mint, Smart Slider, Divi, jQuery automatically), **then curate the exclude list** so nothing above the fold breaks. That is the correct senior framing for this plugin.

---

## 1. FlyingPress → Settings → JavaScript (target states)

Match these by the **toggle's function and its position in the JavaScript tab**, not by a memorized label string.

| Setting (functional) | State | Why |
|---|---|---|
| **Minify JS** | **ON** | Free byte reduction on every JS file. No Divi risk (order/scope preserved). |
| **Add defer attribute** | **ON** | Removes render-blocking JS → helps FCP/LCP. FlyingPress preserves execution order, so Divi's jQuery chain (`jquery` → `jquery-migrate` → `divi-custom-script`) stays intact. |
| **Delay JavaScript** | **ON** | The main lever. Moves ~all JS execution off the initial load until interaction → TBT/JS-execution/main-thread all drop hard. |

### 1a. Defer — Exclude Keywords
Leave **empty** to start. Divi + Gravity Forms + Smart Slider tolerate defer because order is preserved. **Only if** the mobile menu, sliders, or a form misbehave after enabling Defer, add:

```
divi-custom-script
custom.min.js
```

(That un-defers Divi's core script — matched by handle-id *and* filename — while everything else stays deferred.)

### 1b. Delay — Exclude Keywords (tuned for THIS stack)

You have two viable configurations. Read both, then pick.

**Tier A — Maximum score (recommended target for ≥95 mobile).**
Delay **everything**, exclude **nothing** except what's needed to keep the hero and forms from breaking. To realistically clear **7.0s execution** and **560ms TBT** on mobile you must delay Divi + jQuery too — third-party scripts alone are not enough, because Divi's own bundle is a large slice of the execution time.

Exclude list (Tier A):
```
(leave empty)
```

**Mandatory Tier A safety exception — reCAPTCHA.** If Gravity Forms uses **reCAPTCHA v3** (or v2 invisible), that widget runs *on page load* to mint a validation token **before** any user interaction. Delaying it can make the token never generate and cause **silent submit failures** on the booking/contact form. If reCAPTCHA is in use, this is not optional — add:
```
recaptcha
gstatic.com/recaptcha
google.com/recaptcha
```
Confirm whether reCAPTCHA is enabled in **Forms → Settings → reCAPTCHA** (or on the specific form) before shipping Tier A. If you cannot confirm, add these keywords defensively — they cost ~0 score and protect a business-critical form.

Behavior to expect and communicate: the hamburger menu / slider become interactive on first interaction. FlyingPress triggers on scroll/mouse/touch, so on a real scroll-first mobile visit the scripts are usually already "warmed" before the user reaches the menu; FlyingPress also attempts to re-dispatch the triggering event to its target after scripts load, so the first tap often still registers — but this is timing-dependent, so **QA on a real device**. First-tap-to-open on a cold, no-scroll interaction is the known trade-off. **Coordinate with the CLS workstream: reserve the Smart Slider hero container height** so the delayed slider init cannot shift layout — that is what protects your failing mobile field CLS (0.2). Do not exclude the slider unless the hero visibly breaks.

**Tier B — Balanced (fallback if the client rejects the first-tap menu behavior).**
Un-delay Divi + jQuery + the hero slider so the UI is instant; keep every marketing/third-party script delayed. You still remove the biggest *unused* JS offenders (Pixel, gtag, Elfsight, Trustindex, Shared Counts, Mail Mint) from the critical path.

Exclude list (Tier B):
```
jquery.min.js
jquery-core
jquery-migrate
divi-custom-script
custom.min.js
et-core
smartslider
nextend
n2-
```
Expected: UX identical to today, TBT lands ~200–300ms (not ~120ms), Performance likely ~88–92 mobile rather than ≥95. Use only if Tier A's menu behavior is unacceptable.

> Recommendation: **ship Tier A** (with the reCAPTCHA exception if applicable), fix hero height in the CLS workstream, and keep Tier B on hand as the one-paste rollback if QA flags menu feel.

### 1c. Third-party scripts — all covered by Delay automatically
With Delay ON, these are already delayed (no include list needed). The keyword fragments matter only if you ever need to **force-delay** a stubborn handler or, inversely, **exclude** one — they are the canonical handle/URL fragments for this stack:

| Vendor | Match fragments | Action |
|---|---|---|
| Meta Pixel | `fbevents.js`, `connect.facebook.net`, `fbq(` | **Delay** (default). See §3 re its console-deprecation warning. |
| Google / Site Kit (gtag) | `googletagmanager.com/gtag/js`, `gtag(`, `google-analytics.com`, `googlesitekit` | **Delay** (default). |
| Elfsight WhatsApp | `static.elfsight.com`, `apps.elfsight.com`, `elfsight`, `platform.js` | **Delay** (default). Heavy widget — big win. |
| Trustindex Google Reviews | `cdn.trustindex.io`, `trustindex` | **Delay** (default). |
| Shared Counts | `shared-counts`, `sharedcounts` | **Delay** + conditionally dequeue (§4). |
| Mail Mint (front-end) | `mail-mint`, `mailmint`, `mrm` | **Delay** + conditionally dequeue (§4). |

Nothing here should be *excluded* from delay — none is required for first paint. (reCAPTCHA is the one exception, and it lives with the *form*, not this table — see §1b.)

**Expected impact of §1 (Delay ON, Tier A):** TBT 560 → ~100–150ms; JS execution 7.0 → ~2.0–2.5s; main-thread 3.9 → ~2.0s; "unused JS" runtime cost effectively removed from the initial load; **Performance +10–15 points mobile**, +5–8 desktop (estimates — verify by re-testing). This is the highest-ROI action in the entire engagement.

---

## 2. Divi safety — what must NOT be broken, and how FlyingPress protects it

- **`DOMContentLoaded` / `load` still fire for delayed code.** When scripts are delayed past the real `DOMContentLoaded`, FlyingPress re-dispatches synthetic `DOMContentLoaded` and `load` events after it runs the delayed bundle, so Divi's and jQuery's on-ready initializers still execute. This is why "delay all" doesn't leave the menu/slider permanently dead.
- **jQuery execution order.** Both Defer and Delay preserve relative order, and Delay moves jQuery + all dependents together, so inline jQuery-dependent Divi snippets don't fire before jQuery exists. This is why "delay all" is safe on Divi where naive manual `defer` would break the menu/slider. **Do not** hand-roll `defer`/`async` on jQuery in a snippet — let FlyingPress own it (per constraint 3).
- **Mobile menu / sticky header.** These live in `divi-custom-script`. In Tier A they wake on first interaction; in Tier B they're excluded and instant. Either is safe — neither *dequeues* them.
- **Smart Slider hero (LCP).** Smart Slider 3 renders the **first slide as server-side HTML**, so the LCP image can paint without JS even while the slider *script* is delayed — delaying it should **not** delay LCP (field LCP 0.9s already good). Confirm the LCP element in the PSI "LCP" detail. The real risk is **layout shift / blank hero** if JS-driven height/arrows/dots initialize into an un-reserved container. Solve with height reservation in the CLS workstream, **not** by excluding the slider, so you keep the JS win.
- **Gravity Forms.** GF 2.5+ enqueues its JS only on pages that contain a form, and those scripts are delayed-until-interaction like everything else. A user cannot submit without interacting, so the form is generally live by the time it's touched. **Do not force-exclude GF from delay** and **do not blanket-dequeue GF** (see §4 caution). **But** because this is a counselling practice whose booking/contact form is business-critical, treat form QA as a **blocking gate before go-live**: on the contact/booking page, verify (a) one live submit lands in Gravity Forms → Entries and in the FluentSMTP/Mail Mint delivery log, (b) conditional-logic fields show/hide correctly, (c) multi-page next/prev works if used, (d) any date picker opens, and (e) reCAPTCHA (if enabled) passes — see the §1b reCAPTCHA exclusion. If any fail, add the specific script's keyword to the **Delay** exclude list (never dequeue GF).

---

## 3. Self-hosting third-party scripts / "efficient cache lifetimes" (~372 KiB) — the honest version

The cache-lifetime penalty is driven by `gtag.js` (Google serves it with a short TTL) and `fbevents.js`.

- **FlyingPress has no generic "self-host third-party scripts" toggle** in current versions — do not go looking for one and don't claim it. Two accurate paths:

- **Path A (recommended, no cost, no extra plugin):** because Delay (§1) removes these from the initial execution path, their **runtime cost is already gone**. Critically, **"Use efficient cache lifetimes" (`uses-long-cache-ttl`) is a scoreless/informative diagnostic in Lighthouse 10/11** — it carries **0 weight in the Performance score**. So for the ≥95 goal you can leave it. State this to the client so nobody burns time chasing a metric that doesn't move the number.

- **Path B (only if you want the diagnostic itself to disappear for repeat-visit TTFB):** self-host **gtag** with **CAOS – Host Google Analytics Locally**. **Caution:** Site Kit by Google is installed on this site and injects its own gtag; CAOS and Site Kit can both try to own the gtag tag and **conflict** (double-tracking or CAOS's local copy being bypassed). If you go this route, test in a staging/incognito session that GA still receives events (GA4 Realtime), and be ready to deactivate CAOS to revert. **Do not self-host `fbevents.js`** — Meta rotates it and self-hosting silently degrades event delivery; leave the Pixel delayed instead. Net: adds one plugin to clear one non-scored diagnostic — worth it only if the client explicitly wants a clean report, otherwise skip.

- **Meta Pixel console warning (Best Practices: "Browser errors were logged to the console").** **First, identify the actual error — do not assume.** Open the live page in Chrome DevTools → **Console**, reload, and read the logged error text; note its source file. The Pixel's deprecation warning is a *likely* source, but the console may also carry a different error (a 404 asset, a third-party widget, a mixed-content warning). Fix what the console actually shows. If the logged error is the Pixel deprecation and the Pixel isn't actively driving ad campaigns, the cleanest fix is to **deactivate/remove the "Meta Pixel for WordPress" plugin** (reversible — reinstall and re-enter the Pixel ID later, or deploy the Pixel through Site Kit/GTM). **Flag to the marketing owner before removing**, and record the Pixel ID first.

**Expected impact:** Path A: 0 score points, but honest and free. Path B: clears the cache-lifetime diagnostic + faster repeat visits; still ~0 cold-load points.

---

## 4. Plugin-level JS trimming — conditional dequeue (structural unused-JS removal)

Delay hides unused JS from the runtime; **dequeuing removes it from the page entirely** on routes that don't use it — this is what actually shrinks the ~407 KiB "unused JS" and the 3,350 KiB payload on those routes.

### 4a. First, get the EXACT handles (method — do not guess)
Handles vary per install. Temporarily add this WPCode snippet, then view the front-end **in an incognito window as a logged-out user** (the snippet suppresses itself for logged-in users) on a page with no slider/share/form, then read the HTML source comment near the bottom, then **deactivate the snippet**:

```php
// TEMP DIAGNOSTIC — lists every front-end script handle actually printed, then REMOVE this snippet.
// View the page logged-OUT (incognito); the guard hides it from logged-in users.
add_action( 'wp_print_footer_scripts', function () {
    if ( is_admin() || is_user_logged_in() ) { return; }
    global $wp_scripts;
    if ( empty( $wp_scripts ) || empty( $wp_scripts->done ) ) { return; }
    // ->done is the authoritative list of everything actually output,
    // including dependencies (jquery, etc.) that ->queue would miss.
    $handles = array_values( array_unique( (array) $wp_scripts->done ) );
    sort( $handles );
    echo "\n<!-- ENQUEUED JS HANDLES (" . count( $handles ) . "): " . esc_html( implode( ', ', $handles ) ) . " -->\n";
}, PHP_INT_MAX );
```

Map the vendor to the handle you see (e.g. Shared Counts often `shared-counts`; Smart Slider `smartslider-frontend` / `nextend-frontend`; Mail Mint `mailmint` / `mint-mail-public` / `mrm`). Use the **real** handles in §4b.

### 4b. The safe, guarded conditional-dequeue snippet
Every removal is a no-op if the handle isn't present, guarded against admin/AJAX/REST/Divi-builder contexts, and fully reversible by deactivating the snippet. **Replace each `VERIFY` handle with what §4a reported.** Leave any line you're unsure about commented out.

```php
/**
 * Thrive Downtown — conditional front-end JS trimming.
 * Reversible: deactivate this WPCode snippet to restore all scripts.
 * Every wp_dequeue_script() is a no-op if the handle isn't enqueued, so a
 * wrong/absent handle cannot fatal the page. Replace every 'VERIFY' handle
 * with what the §4a diagnostic reported for THIS install.
 */
add_action( 'wp_enqueue_scripts', 'tdc_conditional_js_trim', 100 );
function tdc_conditional_js_trim() {

    // Never touch admin, AJAX, REST, feeds, or the Divi Visual Builder.
    if ( is_admin() || wp_doing_ajax()
        || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
        || is_feed() ) {
        return;
    }
    if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
        return;
    }

    global $post;
    $content = ( $post instanceof WP_Post ) ? (string) $post->post_content : '';

    // ---- Shared Counts: only where share buttons actually render ----
    // Default: blog posts. Also keep it if a page/CPT embeds the shortcode/block.
    $has_share = is_singular( 'post' )
              || ( '' !== $content && (
                     false !== strpos( $content, 'shared_counts' )
                  || false !== strpos( $content, 'shared-counts' )
                 ) );
    if ( ! $has_share ) {
        wp_dequeue_script( 'shared-counts' );        // VERIFY handle via §4a
    }

    // ---- Mail Mint front-end: only where a Mail Mint form/popup is placed ----
    $has_mailmint = ( '' !== $content && (
                        false !== strpos( $content, 'mailmint' )
                     || false !== strpos( $content, 'mrm' )
                    ) )
                 || has_shortcode( $content, 'mailmint_form' );
    if ( ! $has_mailmint ) {
        wp_dequeue_script( 'mailmint' );             // VERIFY handle via §4a
        wp_dequeue_script( 'mint-mail-public' );     // VERIFY handle via §4a
    }

    // ---- Smart Slider ----
    // CAUTION: post_content detection MISSES sliders placed via a Divi Theme
    // Builder template, a widget, PHP, or a Divi module that references the
    // slider by numeric ID (the string "smartslider" may not appear in content).
    // Keep the script enqueued unless you have CONFIRMED no slider renders on the
    // route (view source, look for the slider markup). Default keeps the homepage hero.
    $has_slider = is_front_page()
               || ( '' !== $content && false !== strpos( $content, 'smartslider' ) )
               || has_shortcode( $content, 'smartslider3' );
    if ( ! $has_slider ) {
        wp_dequeue_script( 'smartslider-frontend' ); // VERIFY handle via §4a
        wp_dequeue_script( 'nextend-frontend' );     // VERIFY handle via §4a
    }

    // ---- OPTIONAL: Gravity Forms — ONLY if §4a proves GF JS leaks onto a
    // genuinely form-less page. Handles vary by GF version; verify via §4a and
    // test a LIVE form submit afterward. Left commented out (safe) by default.
    // if ( $post instanceof WP_Post
    //     && ! has_shortcode( $content, 'gravityform' )
    //     && ! has_block( 'gravityforms/form', $post ) ) {
    //     wp_dequeue_script( 'gform_gravityforms_theme' ); // VERIFY handle via §4a
    //     wp_dequeue_script( 'gform_gravityforms' );       // VERIFY handle via §4a
    // }
}
```

**On Gravity Forms — deliberately kept commented out.** GF 2.5+ already loads its JS only on pages containing a form, so a blanket dequeue is redundant and risks silently killing multi-page forms, conditional logic, or reCAPTCHA. Enable the GF block only if §4a *proves* GF JS appears on a form-less page, and re-test a live submit after enabling. Note the handle set changed across GF versions (`gform_gravityforms`, `gform_gravityforms_theme`, `gform_gravityforms_utils`, …) — dequeue exactly what §4a shows.

**Robustness note:** the dequeue runs at `wp_enqueue_scripts` priority 100, which catches scripts enqueued the normal way (including `in_footer` ones). If §4a shows a handle that *still* prints after this snippet, that plugin is enqueuing late (during `wp_footer`); dequeue that specific handle again on `wp_print_footer_scripts` at priority 0. Don't add that complexity pre-emptively.

**After enabling:** purge FlyingPress cache, hard-reload the affected routes incognito, and confirm the removed widget is genuinely absent (not just cached away) on the pages that shouldn't have it, and still present on the pages that should.

**Expected impact:** on non-blog / non-slider routes, ~100–250 KiB unused JS removed outright, payload down, and another ~50–150ms TBT off those pages. Cumulative with §1 for the routes Lighthouse tests.

---

## 5. Legacy JavaScript / polyfills (~11 KiB) — set expectations, then the small win

- The legacy bundles are **transpiled `core-js`/`regenerator-runtime` inside third-party plugins** (Elfsight, Trustindex, Smart Slider Pro). You cannot strip them from vendor files, and FlyingPress does not transpile or "modernize" JS. **Delaying them (§1) removes their runtime cost**; the 11 KiB diagnostic is minor and largely cosmetic — do not over-invest.
- The one thing you *can* remove is WordPress core's `wp-polyfill` (core-js) if no front-end block requires it. Low ROI, so make it optional and test block-driven pages afterward:

```php
// OPTIONAL — drop WP core polyfills on the front-end if no block needs them.
// Reversible: deactivate this snippet to restore. Test any page using WP blocks /
// interactive block UI (forms, galleries, embeds) after enabling.
add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() ) { return; }
    wp_dequeue_script( 'wp-polyfill' );
    wp_dequeue_script( 'regenerator-runtime' );
}, 100 );
```

**Expected impact:** a few KiB and a couple ms; keep it only if it doesn't disturb any block UI.

---

## 6. Emoji + wp-embed + jQuery Migrate removal (safe WPCode snippet)

FlyingPress does not expose emoji/embed/Migrate toggles, so handle these in WPCode. All reversible by deactivating the snippet.

```php
/**
 * Thrive Downtown — remove WP emoji script, wp-embed, and jQuery Migrate.
 * Front-end only. Reversible: deactivate this snippet to restore everything.
 */

// --- Disable the emoji detection script + styles ---
add_action( 'init', function () {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    add_filter( 'tiny_mce_plugins', function ( $plugins ) {
        return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
    } );
    add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, function ( $u ) {
                $href = is_array( $u ) ? ( isset( $u['href'] ) ? $u['href'] : '' ) : $u;
                return false === strpos( (string) $href, 's.w.org' );
            } );
        }
        return $urls;
    }, 10, 2 );
}, 20 );

// --- Drop wp-embed.min.js on the front-end (oEmbed of THIS site into others) ---
// Runs on wp_footer priority 10, before print_footer_scripts (priority 20), so
// the dequeue lands before wp-embed is printed.
add_action( 'wp_footer', function () {
    if ( ! is_admin() ) {
        wp_dequeue_script( 'wp-embed' );
    }
} );

// --- Remove jQuery Migrate but KEEP jQuery core ---
// wp_default_scripts is an action; $scripts is passed by reference, so mutating
// the object here persists. No return value is needed.
add_action( 'wp_default_scripts', function ( $scripts ) {
    if ( is_admin() ) { return; }
    if ( ! empty( $scripts->registered['jquery'] ) ) {
        $scripts->registered['jquery']->deps = array_diff(
            (array) $scripts->registered['jquery']->deps,
            array( 'jquery-migrate' )
        );
    }
} );
```

**jQuery Migrate caution (Divi-specific):** modern Divi does not need Migrate, but an older Divi build or a legacy plugin can rely on deprecated jQuery APIs. After enabling, **QA the mobile menu, Smart Slider, and a Gravity Forms submit with the browser console open** — if you see `$ is not a function` / `jQuery.fn.X is deprecated` errors or broken behavior, deactivate this one snippet to restore Migrate. Fully reversible.

**Expected impact:** ~30–50 KiB and one to two fewer requests, marginal TBT, cleaner console. Small but free.

---

## 7. Final ordered priority list (by ROI)

1. **FlyingPress → JavaScript → Delay = ON, Tier A exclude list (empty, plus the reCAPTCHA keywords if GF uses reCAPTCHA).** Biggest lever. TBT 560→~120ms, JS exec 7.0→~2.2s, main-thread 3.9→~2.0s. **+10–15 pts mobile** (estimate). Requires the CLS workstream to reserve the Smart Slider hero height so delayed init can't shift. **Blocking gate: pass the §2 Gravity Forms QA checklist before go-live.**
2. **FlyingPress → Defer = ON** (exclude empty; add `divi-custom-script`,`custom.min.js` only if something breaks). Render-blocking removed. **+3–6 pts.**
3. **FlyingPress → Minify JS = ON.** Free bytes, zero risk.
4. **Conditional dequeue snippet (§4)** after pulling real handles via the §4a diagnostic. Structurally removes ~100–250 KiB unused JS on non-blog/non-slider routes. **+2–5 pts on those routes.**
5. **Emoji / wp-embed / jQuery Migrate removal (§6).** ~30–50 KiB, cleaner console, minor TBT. Free.
6. **gtag/fbevents cache-lifetime (§3).** Default to Path A (do nothing — it's a scoreless diagnostic). Only run CAOS for gtag if the client wants a spotless report, and test against the installed Site Kit. ~0 cold-load points.
7. **wp-polyfill / legacy JS (§5).** Optional, few KiB, test block pages. Lowest ROI.

**Rollback map:** every FlyingPress item is a toggle; every code change is one WPCode snippet — deactivate to revert. Tier B (§1b) is the paste-in fallback if Delay-all's first-tap menu behavior is rejected. Nothing here touches theme files, jQuery core, or Gravity Forms execution, so there is no fatal-edge path.

**Cross-workstream dependency to flag:** the mobile **field CLS 0.2** will only pass if the delayed Smart Slider (and the Elfsight/Trustindex late embeds) have reserved space — that reservation is owned by the CLS/layout workstream. This JS workstream deliberately delays those scripts *and assumes* their containers are height-reserved; do not exclude the slider from delay to "fix" CLS, as that would forfeit the JS win. Smart Slider's first slide is server-rendered, so delaying its JS should not delay LCP — confirm the LCP element in PSI's LCP detail after shipping.