> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. The failing Core Web Vital. Code lives in css-01 + html-01. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

# Workstream: Eliminate Mobile FIELD CLS (0.2 → < 0.1)

**Diagnosis premise (confirmed by your data):** Lab CLS = 0.05 but field CLS = 0.20. A cold Lighthouse load with a throttled CPU actually *under-*fires third‑party widgets (Elfsight, Trustindex) because they lazy-init after `load`, and it renders with a warm-ish font cache. Real Vancouver mobile users on mid-tier Android over LTE get the *late* shifts: the Smart Slider hero expanding after JS init, the Trustindex reviews block popping in, the WhatsApp button (only if it is embedded in-flow), and font-swap reflow. That ~0.15 delta is almost entirely **reserved-space failures on async content**. Every fix below is about *reserving the final height before the async thing arrives*.

**Golden rule for this whole workstream:** a layout shift only counts if a box that already has content moves. `transform:` and `opacity:` changes never cause CLS. A `position:fixed` element appended to `<body>` cannot shift document flow, so it does not cause CLS **unless it is itself animated via layout properties** (`top`/`bottom`/`left`/`margin` — see §6). So the enemy is specifically **in-flow containers that start at height 0 (or a wrong height) and then grow**, pushing everything below them down.

> **Before you change anything — take a backup and set up a revert path (required):**
> - **WPCode:** you will add each change as its **own separate snippet** so any one can be toggled off independently. Note the current state (nothing to back up if you are only *adding* snippets — deactivating = full revert).
> - **Divi Custom CSS:** if you edit **Divi → Theme Options → General → Custom CSS**, copy the existing contents into a text file first. (This workstream puts CSS in WPCode instead, precisely so Divi's box stays untouched.)
> - **Divi layout:** any module you "replace" is **disabled, not deleted** (see §2), so re-enabling restores it.
> - **Staging:** if a staging/clone exists, apply and validate there first. If not, apply one fix at a time and re-test.
> - **After every change:** purge caches and re-test (see the boxed "Cache-clear + retest" note that follows each risky step, and the Verification loop at the end).

---

## Fix priority (by ROI on field CLS)

| # | Fix | Est. CLS reduction | Effort | Reversible |
|---|-----|-------------------|--------|-----------|
| 1 | Reserve Smart Slider hero height at mobile breakpoints (CSS) | **−0.08 to −0.12** | Low | Yes (delete snippet) |
| 2 | Replace hero **slider** with static `<img>` (nuclear option) | **−0.10 to −0.14** + LCP win | Med | Yes (re-enable module) |
| 3 | Reserve Trustindex reviews container min-height | **−0.03 to −0.06** | Low | Yes |
| 4 | Font preload + `size-adjust` fallback metrics | **−0.01 to −0.03** | Low | Yes |
| 5 | FlyingPress "Add width/height to images" | **−0.01 to −0.03** | Trivial | Yes (toggle) |
| 6 | Elfsight WhatsApp: delay + reserve, or replace with static button | **−0.005 to −0.02** | Low/Med | Yes |

Do **1, 3, 4, 5** first (all pure config/CSS, zero destructive risk). Then decide on **2** (biggest single win, small design change) and **6** (smallest CLS win, but big TBT/INP/payload win as a bonus).

> **How to confirm the real culprits before you touch anything** (do this once): open PSI report → **CLS diagnostic → "Layout shift culprits"** (Lighthouse lists the shifting nodes with a score per element). Also run Chrome DevTools → **Performance panel → record a mobile-emulated reload → "Experience" / "Layout Shift" track → click each red bar** to see the exact node and its move distance. That gives you the *real* selectors to drop into the placeholders below. **Everything marked `/* PLACEHOLDER */` or "VERIFY AGAINST LIVE DOM" is a strong default that MUST be reconciled with those two reports and with View Source before you trust it.** The height values must be the *measured final mobile heights* of each block — a `min-height` that is too large just trades a shift for an ugly static whitespace gap.

---

## 1. Smart Slider 3 hero — the primary offender

### Why a slider causes mobile CLS (the actual mechanism)
Smart Slider 3 outputs a wrapper (commonly `<div id="n2-ss-2" class="n2-ss-slider ...">` inside a `.n2-section-smartslider` section). Before its JS runs, that wrapper often has **`height: 0`, or an inline aspect that only resolves after Smart Slider's init measures the viewport**. On desktop the ratio resolves fast; on a throttled mobile CPU the init is delayed behind your "Reduce JavaScript execution time 7.0s", so the hero sits collapsed, the page paints the content *below* it near the top, then the slider snaps to full height and shoves everything down → one large shift.

> Note: the slider's **main animation type (Fade vs Slide) does *not* cause CLS** — those are `opacity`/`transform` transitions between slides. Do not spend effort changing it for CLS reasons. The only thing that matters here is **reserving the container's final height before JS runs.**

### Exact Smart Slider settings to change
Open **Smart Slider 3 → (open the homepage slider)**. Labels vary slightly by build; the **intent** is what matters, and the CSS in §7 backstops all of it. Work through these panels functionally:

1. **Slider settings → Size**
   - **Layout:** set to **Fullwidth** (avoid **Fullpage** — Fullpage recalculates against `100vh` on mobile and shifts when the mobile URL bar collapses/expands; that is a chronic mobile CLS source).
   - **Slider height:** use the **Desktop / Tablet / Mobile** device tabs and give **Mobile an explicit fixed height** (e.g. `420` px, or whatever the first slide needs). Do **not** leave mobile height blank/auto.
   - If your version exposes an **Aspect ratio** field instead of fixed heights, set a fixed ratio (e.g. `16:10`) so the reserved box is deterministic and scales with width.

2. **Slider settings → Optimize (loading behavior)**
   - Find the option that controls **loading the first slide's image before the slider is displayed / loading layer images immediately** and enable it for the **first slide**, so the hero background is present at first paint (helps LCP *and* fills the reserved box quickly). Label wording varies (e.g. "Load first slide's images", "Layer images: Instant"); pick the one that means "don't defer the first slide."
   - If your Pro build shows an explicit **CLS / "reserve space" / "prevent layout shift"** option in this section, turn it **ON**. If it doesn't exist in your version, skip it — the §7 CSS is the guaranteed backstop.

3. **First slide's background image**
   - Open **Slide 1 → Background → Image**. Ensure it is a real uploaded media item (so Smart Slider knows its intrinsic dimensions) and set **Fill** with the fixed slide height so the box is defined before the image decodes.
   - Note: Smart Slider frequently paints the slide background as a CSS `background-image`, which has **no intrinsic dimensions** and therefore **cannot self-reserve height** — this is exactly why the §7 `min-height` CSS is mandatory, not optional.

4. **Controls:** if arrows/bullets are enabled, confirm they are absolutely positioned (Smart Slider default) so they do not add to flow height. No change usually needed.

### CSS to reserve the slider height (backstop — apply regardless of settings)
This is the single most important block. It forces the wrapper to occupy its final height *before* JS runs, so nothing below can shift. **You MUST replace `#n2-ss-2` with your real slider container ID** — find it via View Source, search `n2-ss-`, and target the **outermost** slider wrapper.

```css
/* === Smart Slider hero: reserve height so async init can't shift === */
/* VERIFY AGAINST LIVE DOM: replace #n2-ss-2 with the real container id
   (View Source, search "n2-ss-"). Height = the SS3 Mobile height you set. */

/* Mobile-first: reserve the exact height you set in SS3 Mobile size */
#n2-ss-2,
.n2-ss-slider {              /* VERIFY selector */
  min-height: 420px;         /* PLACEHOLDER: match SS3 Mobile height (measured) */
}

/* If SS3 gives you an aspect-ratio slider instead of fixed px, use this
   INSTEAD of min-height so it scales with width without JS: */
/*
#n2-ss-2 { aspect-ratio: 16 / 10; min-height: 0; }
*/

@media (min-width: 768px) {
  #n2-ss-2,
  .n2-ss-slider { min-height: 520px; }   /* PLACEHOLDER: tablet height */
}
@media (min-width: 981px) {
  #n2-ss-2,
  .n2-ss-slider { min-height: 620px; }   /* PLACEHOLDER: desktop height */
}
```

> **Removed `contain: layout` from this rule on purpose.** It does not reserve space (only `min-height`/`aspect-ratio` does) and it turns the wrapper into a containing block / independent formatting context, which can subtly interfere with Smart Slider's absolutely-positioned layers, arrows, and any overflow it relies on. `min-height` alone is the safe, sufficient tool.

**Expected CLS impact:** **−0.08 to −0.12** on mobile field. This alone likely moves you from 0.20 to ~0.10.

> **Cache-clear + retest after §1:** FlyingPress → purge cache (admin-bar **Purge Cache**, or **FlyingPress → Cache → Purge Everything**). Then hard-reload the homepage (mobile emulation) and confirm the hero box is full-height *before* the slider paints (throttle CPU 4× to reproduce). Deactivating the WPCode CSS snippet fully reverts.

---

## 2. STRONGLY RECOMMENDED — replace the hero *slider* with a static optimized `<img>`

For a counselling homepage the hero is almost always a single background image with a headline and a "Book" button. A carousel there buys little and costs a JS-dependent, shift-prone, LCP-delaying widget. Replacing it with one static image **kills the slider CLS entirely, makes the hero the LCP element with a discoverable URL** (addresses "LCP request discovery flagged"), and removes a chunk of "Reduce JavaScript execution time 7.0s" and "unused JS 407 KiB."

### How to do it reversibly in Divi
1. Edit the homepage in Divi Builder. **Do not delete the slider module.** Right-click the Smart Slider module/row → **Disable** (Divi keeps it greyed-out in the builder and stops rendering it on the front end). **This is your revert path** — right-click → **Enable** restores it exactly.
   - *Clarification:* "Advanced → Visibility → Disable on Phone/Tablet/Desktop" is a **per-device** hide, not a full disable. Use the **right-click → Disable** for a clean, complete, reversible removal.
2. Add an **Image module** (or a one-row section with a **background image + Text + Button**) at the top in its place.
3. Upload the hero at the rendered 2× size for mobile (e.g. 828–1080px wide). The **Divi Image module emits `srcset` + intrinsic `width`/`height` automatically** — which is exactly what reserves the box for CLS. Prefer the Image module over a section *background* image for that reason (a CSS background image has no intrinsic size and needs an explicit `min-height` — see §5/§7).
4. If you must use a section **background** image, set **Section → Design → Sizing → Min Height** with a phone-tab value so the box is reserved.

### Make it the LCP and preload it (optional refinement)
Because it becomes the LCP, you can preload it so mobile discovers it immediately. **Two caveats before you hardcode a preload URL — both can create the "Browser errors were logged to the console" warning that threatens your Best Practices score:**

- **srcset mismatch / double-download:** if the rendered `<img>` uses `srcset` (Divi's does), the browser may pick a *different* candidate than a single preloaded URL, causing a wasted preload **and** a second download. Match the preload to the actual chosen URL, or use `imagesrcset`/`imagesizes` on the preload link.
- **WebP Express rewrite:** if WebP Express serves WebP by **server rewrite** (Accept-header), the HTML may reference a `.jpg` that the server swaps to `.webp`, so preloading `hero.webp` won't match the requested URL. If WebP Express uses **picture tag** mode, the `.webp` is explicit and safe to preload. **Confirm the exact URL in View Source first.**

If those caveats are satisfied, prefer **FlyingPress → Preload → Preload Critical Images / LCP** (if your version exposes it) over hand-rolled PHP. If you hand-roll it, use this WPCode snippet **with the location corrected**:

```php
<?php
/**
 * Preload the homepage hero image (LCP).
 * WPCode: PHP Snippet, Auto Insert, Location "Run Everywhere"
 *   (NOT "Site Wide Header" — this code REGISTERS a wp_head callback, so it
 *    must run before wp_head fires; "Run Everywhere" is the functions.php-
 *    equivalent that registers hooks correctly. The is_front_page() guard
 *    keeps it front-page-only and is false in admin, so admin is unaffected.)
 * Deactivate snippet = full revert.
 */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	// VERIFY AGAINST LIVE DOM: these MUST equal the exact URL rendered in the
	// hero <img> (check View Source, including the srcset candidate actually
	// chosen and the WebP Express mode). Wrong URL => wasted preload + console warning.
	$hero_mobile  = 'https://thrivedowntown.com/wp-content/uploads/hero-mobile.webp';
	$hero_desktop = 'https://thrivedowntown.com/wp-content/uploads/hero-desktop.webp';
	if ( $hero_mobile ) {
		printf(
			'<link rel="preload" as="image" href="%s" media="(max-width: 980px)" fetchpriority="high">' . "\n",
			esc_url( $hero_mobile )
		);
	}
	if ( $hero_desktop ) {
		printf(
			'<link rel="preload" as="image" href="%s" media="(min-width: 981px)" fetchpriority="high">' . "\n",
			esc_url( $hero_desktop )
		);
	}
}, 1 );
```

> Two `fetchpriority="high"` preloads are fine here because the `media` queries are mutually exclusive — only one downloads. If you keep the Divi Image module, the simplest safe option is to just set `fetchpriority="high"` on that one image (Divi Image module → **Advanced → Attributes**, or via the image's own settings) and **skip the manual preload entirely**, avoiding both caveats above.

**Expected impact:** CLS **−0.10 to −0.14** (removes the slider shift class entirely), plus lab **LCP** improvement (toward the 2.8s → <2.0s you need for 95) and a dent in TBT/unused-JS. Highest-total-ROI action; the only cost is losing the carousel.

> **Cache-clear + retest after §2:** purge FlyingPress. Verify the hero renders, is the LCP in a fresh PSI run (LCP element in the report should now be the static image), and that DevTools **Network** shows the hero downloaded **once** (no double download from a mismatched preload). Revert = right-click → Enable the slider, disable/delete the image module, deactivate the preload snippet.

---

## 3. Trustindex "Widgets for Google Reviews" — reserve a min-height container

**Mechanism:** the widget outputs a placeholder `<div>` that is height 0 until Trustindex's async script fetches reviews and injects the cards, then it expands and shoves whatever is below it (footer CTA, next section) down. Classic late in-flow growth. (Shifts far down the page still count fully in field CLS if a real user scrolls to them within the session window.)

**Method to get the real selector:** View Source / DevTools → the Trustindex block is typically an **outer wrapper** like `<div class="ti-widget ...">` or `<div id="ti-widget-...">` or a shortcode wrapper. Identify the **single outermost** wrapper and target only that.

**Preferred fix (cleanest, no selector guessing): reserve the space on the Divi row/section that holds the widget.** In Divi, open that row/section → **Design → Sizing → Min Height**, set the phone-tab value to the widget's measured mobile height and the desktop value to its desktop height. This is stored in the layout and fully reversible (clear the field).

**CSS fallback (if the widget is not inside a Divi module you can edit):**

```css
/* === Trustindex Google Reviews: reserve space so async cards don't shift === */
/* VERIFY AGAINST LIVE DOM: target ONLY the single outermost wrapper.
   Do NOT use a broad [class*="trustindex"] selector — it matches nested
   children too, stacking multiple min-heights and forcing display:block on
   inner nodes can break the widget's own grid/flex card layout. */
#ti-widget-XXXX {              /* PLACEHOLDER: the real outer wrapper id/class */
  min-height: 520px;          /* PLACEHOLDER: measured final MOBILE height */
}
@media (min-width: 981px) {
  #ti-widget-XXXX { min-height: 360px; }  /* PLACEHOLDER: measured desktop height */
}
```

> **Removed the broad `[class*="trustindex"]`, `[id^="ti-widget"]` shotgun and the forced `display:block`** from the draft: those over-match nested nodes, stack reserved heights, and can flatten the widget's internal card layout. Scope to the one outer wrapper only.

**How to measure the value:** DevTools mobile emulation → let the widget fully load → select the outer wrapper → read its rendered height → use that (round up slightly). Because the height depends on how many review cards render, treat this as the common-case value; a tiny residual shift is acceptable.

**Expected CLS impact:** **−0.03 to −0.06**.

> **Cache-clear + retest after §3:** purge FlyingPress; reload with the widget fully loaded and confirm no shift when cards inject and no large empty gap when they don't. Revert = clear the Divi Min Height field / deactivate the CSS snippet.

---

## 4. Web fonts — stop the swap reflow

**Mechanism:** with `font-display: swap`, text first paints in the fallback font, then re-renders in the web font. If the web font's metrics differ (line-height, x-height, advance widths), the text block's height changes → reflow → CLS. Small, but a *guaranteed every-load* shift, so it's worth removing.

### Let FlyingPress do the heavy lifting
In **FlyingPress → Fonts**:
- **Optimize Google Fonts** (self-host) → **ON**. This removes the render-blocking `fonts.googleapis.com` request and serves fonts locally.
- Keep **swap** display behavior — it's correct for FCP; we neutralize its reflow with the fallback metrics below.
- If FlyingPress exposes a **font preload** option, preload only the **1–2 fonts used above the fold** (the H1/body font). Over-preloading hurts.

Divi also loads its own font stack (**Divi → Theme Customizer → Typography**). If the hero H1 uses a specific Google font, that is the one to preload.

### Preload the primary font (only if FlyingPress doesn't expose it)
> **Fragility warning:** FlyingPress self-hosted font filenames are **content-hashed and change whenever the cache is purged/regenerated.** A hardcoded path will go stale and produce a wasted preload + a "preloaded but not used" console warning (hurts Best Practices). **Strongly prefer FlyingPress's own font-preload toggle.** Only hand-roll this if FlyingPress can't, and **re-verify the URL after every cache purge.**

```php
<?php
/**
 * Preload the primary above-the-fold web font.
 * WPCode: PHP Snippet, Auto Insert, Location "Run Everywhere" (see §2 note on why
 *   NOT "Site Wide Header"). Deactivate = revert.
 * VERIFY AGAINST LIVE DOM: the woff2 URL must match what the page actually loads
 *   (find it in page source after enabling Optimize Google Fonts, typically under
 *   /wp-content/cache/flying-press/... or /wp-content/uploads/...). Re-check after
 *   every cache purge — the hash changes.
 */
add_action( 'wp_head', function () {
	$font = 'https://thrivedowntown.com/wp-content/cache/flying-press/fonts/primary.woff2'; // PLACEHOLDER
	if ( $font ) {
		printf(
			'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
			esc_url( $font )
		);
	}
}, 1 );
```
> `crossorigin` is mandatory on font preloads even same-origin (fonts are always fetched in CORS mode); omitting it causes a double fetch.

### `size-adjust` fallback metrics (kills the reflow, not just the FOIT)
Define a fallback `@font-face` whose metrics match the web font, then insert it as the **second** family in the *same* stacks Divi already uses. When the real font swaps in, the box height is already correct → near-zero shift. **Generate the four numbers for YOUR actual primary font** with Malte Ubl's `fontpie` or the `@capsizecss` tools — the values below are illustrative only.

```css
/* === Fallback metric-matching to eliminate font-swap reflow === */
/* PLACEHOLDER values: generate exact numbers for YOUR primary font via
   fontpie / capsize. Illustrative here for an Open-Sans-like font. */
@font-face {
  font-family: "Primary Fallback";
  src: local("Arial");
  size-adjust: 100.06%;      /* PLACEHOLDER */
  ascent-override: 101.9%;   /* PLACEHOLDER */
  descent-override: 27.9%;   /* PLACEHOLDER */
  line-gap-override: 0%;     /* PLACEHOLDER */
}
```

> **Do NOT blanket-override `font-family` on `body, h1, h2, h3, ...` with a single stack.** Divi lets you set *different* fonts for body vs each heading level via the Customizer; a blanket override would flatten them into one font — a visible design regression. Instead: in DevTools, inspect your H1 and body, read their **Computed → font-family**, then re-declare **those same stacks** with `"Primary Fallback"` inserted as the second family. Example (match to your real font names/selectors):

```css
/* VERIFY AGAINST LIVE DOM: use Divi's ACTUAL font names + the selectors Divi
   already applies them to. Insert "Primary Fallback" as the 2nd family only. */
body { font-family: "YourBodyFont", "Primary Fallback", Arial, sans-serif; }
h1, h2, h3 { font-family: "YourHeadingFont", "Primary Fallback", Arial, sans-serif; }
```

**Expected CLS impact:** **−0.01 to −0.03**. Small but free and deterministic. Also nudges FCP/LCP by removing the Google Fonts round-trip ("Font display flagged" clears).

> **Cache-clear + retest after §4:** purge FlyingPress; confirm headings and body still render in the correct (distinct) fonts, and that DevTools Network shows no "preloaded but not used" warning in the Console. Revert = deactivate snippet(s) / toggle FlyingPress font options back.

---

## 5. Images without width/height (incl. Divi background heroes)

### FlyingPress setting (do this — it's a checkbox)
**FlyingPress → Images:**
- **Add width/height attributes** (label varies: "Add missing image dimensions") → **ON**. Injects `width`/`height` on `<img>` that lack them so the browser reserves the box from the aspect ratio before decode. This directly targets "Layout shift culprits / images without dimensions."
- Keep **Lazy load images** ON but ensure the **hero/LCP image is excluded** — FlyingPress has an **"Exclude above-the-fold images"** count; set it to skip the first **1–2** images. A lazy-loaded LCP image both delays LCP and can shift.

### Divi background-image heroes (the trap)
CSS `background-image` has **no intrinsic dimensions**, so *nothing* — not FlyingPress, not the browser — can auto-reserve its height. Any Divi Section/Row using a **background image** as a hero must get an explicit **Min Height** (with a mobile value) or it collapses until the background paints and then shifts content down. Fix per module: **Section/Row → Design → Sizing → Min Height** (set the phone value via the responsive tab), or via the §7 CSS.

**Expected CLS impact:** **−0.01 to −0.03** (more if several dimensionless images are above the fold or come into view on scroll).

> **Cache-clear + retest after §5:** these attributes are written at cache-generation time, so **purge FlyingPress and let it regenerate**, then View Source and confirm `<img>` tags now carry `width`/`height`. Revert = toggle the FlyingPress checkboxes off.

---

## 6. Elfsight WhatsApp Chat floating widget

### The honest mechanism
A *correctly* floating widget is `position: fixed`, appended to `<body>`, and **cannot cause CLS**. So Elfsight is usually **not** your main CLS source; it's a bigger contributor to **TBT/INP and payload** (it pulls a heavy `platform.js` bundle). CLS from Elfsight happens only in these specific cases:
1. Elfsight's **inline placeholder `<div class="elfsight-app-...">`** is embedded *in the content flow* (e.g. dropped into a Divi Code module mid-page) and grows when the app mounts → shifts content below it.
2. The button's **entrance animation uses `top`/`bottom`/`margin`** (layout properties) instead of `transform` → each animated frame is a shift. (Elfsight mostly uses `transform`, so this is rare.)

**So: first confirm in the CLS culprit list whether an `elfsight-app-*` node actually shows a shift score.** If it does, it's case 1 — apply the reserve/relocate fix. If it doesn't, skip the CLS reserve entirely and just do the **delay** (pure performance win) or the **static replacement**.

### Option A — keep Elfsight, neutralize it
1. **Confirm placement.** If the app is inserted via a shortcode/Code module inside the page body, verify (DevTools) whether it leaves an in-flow placeholder that grows 0→N. If it does, relocate the embed to the **footer** so any residual growth happens below everything else.
2. **Neutralize an in-flow placeholder — CASE 1 ONLY, and only after you have confirmed the *visible* widget is `position:fixed`/appended to `<body>` and the in-flow node is an empty stub:**
```css
/* Elfsight inline placeholder collapse — APPLY ONLY IF you have verified the
   real widget renders position:fixed on <body> and THIS node is an empty stub.
   If the widget renders its content INSIDE this node, this rule HIDES the widget. */
/* VERIFY AGAINST LIVE DOM: class is .elfsight-app-<uuid>. */
.elfsight-app-XXXXXXXX {   /* PLACEHOLDER */
  min-height: 0;
  height: 0;
  overflow: hidden;        /* collapse the empty stub */
}
```
   > Do **not** blindly `!important height:0` a node you haven't confirmed is empty. `overflow:visible` does **not** reliably let a fixed child "escape" — a fixed descendant is only trapped if an ancestor has `transform`/`filter`/`contain`, which is a separate issue. Confirm in DevTools before shipping this rule.
3. **Delay its load** so it never competes with LCP/hero paint (also trims TBT). In **FlyingPress → JavaScript → Delay JavaScript**, add these keywords to the **delay** list (they match against the script URL): `elfsight`, `platform.js`, `static.elfsight.com`. Safe — a chat button doesn't need to exist before the first user interaction.

### Option B — replace Elfsight with a static, zero-JS WhatsApp button (perfectionist choice)
This deletes the entire `platform.js` bundle (helps unused-JS 407 KiB, TBT, payload) and makes widget CLS structurally impossible, while staying fully accessible and WCAG-compliant. Add via **WPCode Lite → HTML Snippet** (Auto Insert → **Site Wide Footer**) or a Divi **Code module** in the footer.

```html
<!-- Static click-to-chat WhatsApp button. Zero JS. position:fixed => no CLS. -->
<a class="tw-wa-btn"
   href="https://wa.me/16045551234?text=Hi%20Thrive%20Downtown%2C%20I%27d%20like%20to%20ask%20about%20counselling."
   target="_blank" rel="noopener"
   aria-label="Chat with Thrive Downtown on WhatsApp">
  <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
    <path fill="currentColor" d="M16 3C9 3 3.5 8.5 3.5 15.5c0 2.3.6 4.5 1.8 6.4L3 29l7.3-2.2c1.8 1 3.8 1.5 5.7 1.5 7 0 12.5-5.5 12.5-12.5S23 3 16 3zm0 22.7c-1.7 0-3.4-.5-4.9-1.4l-.35-.2-4.3 1.3 1.3-4.2-.24-.36a10 10 0 0 1-1.6-5.6C5.66 9.9 10.3 5.3 16 5.3s10.3 4.6 10.3 10.2S21.7 25.7 16 25.7zm5.7-7.6c-.3-.15-1.8-.9-2.1-1s-.5-.15-.7.15-.8 1-.98 1.2-.36.22-.66.07a8.3 8.3 0 0 1-2.45-1.5 9.2 9.2 0 0 1-1.7-2.1c-.18-.3 0-.47.13-.62.13-.13.3-.34.44-.5.15-.18.2-.3.3-.5s.05-.37-.02-.52-.66-1.6-.9-2.2c-.24-.57-.48-.5-.66-.5h-.56c-.2 0-.52.07-.8.37s-1.05 1-1.05 2.5 1.08 2.9 1.23 3.1 2.12 3.24 5.14 4.54c.72.3 1.28.48 1.72.62.72.23 1.38.2 1.9.12.58-.08 1.8-.73 2.05-1.44s.25-1.3.18-1.44-.26-.2-.56-.35z"/>
  </svg>
  <span class="tw-wa-label">Chat</span>
</a>

<style>
.tw-wa-btn{
  position:fixed; right:16px; bottom:16px; z-index:9999;
  display:inline-flex; align-items:center; gap:8px;
  padding:12px 16px; border-radius:999px;
  background:#075E54; color:#fff; text-decoration:none;  /* accessible dark WhatsApp green */
  font:600 15px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  box-shadow:0 4px 14px rgba(0,0,0,.25);
}
.tw-wa-btn:hover,.tw-wa-btn:focus{ background:#0a6d5f; }
.tw-wa-btn:focus-visible{ outline:3px solid #1a1a1a; outline-offset:2px; } /* a11y: visible on light page bg */
.tw-wa-label{ white-space:nowrap; }
@media (max-width:480px){ .tw-wa-label{ display:none; } .tw-wa-btn{ padding:12px; } }
@media (prefers-reduced-motion: reduce){ .tw-wa-btn{ transition:none; } }
</style>
```

> **Color-contrast correction (this was a real bug in the draft).** The draft used `background:#25D366` with white text and claimed it "qualifies as large text." Both parts are wrong:
> - White on `#25D366` is **≈ 1.98:1** — it fails WCAG **for any text** and also fails the **3:1 non-text (icon) contrast** rule (WCAG 1.4.11). This would keep Lighthouse Accessibility below your ≥ 95 target on any viewport where the "Chat" label is visible.
> - A **15px bold** label is **not** "large text" (WCAG large text starts at 18.66px bold / 24px regular), and even large text needs **3:1**, which 2:1 misses.
>
> The corrected `#075E54` gives white text/icon **≈ 7.67:1** — passes AA and AAA for text and clears the 3:1 non-text requirement for the icon. `#128C7E` (≈ 4.14:1) is an acceptable lighter alternative **only** if the visible "Chat" label is hidden (icon-only), since 4.14:1 passes the 3:1 graphic rule but not the 4.5:1 needed for that small label. The focus ring is `#1a1a1a` so it stays visible against the site's light page background (adjust if the surrounding area is dark).

After validating the static button on the front end, **deactivate the Elfsight WhatsApp plugin** (revert = reactivate). Replace `wa.me/16045551234` and the pre-filled `text=` with the real WhatsApp number/message. The link is keyboard-focusable, has an `aria-label`, the SVG is `aria-hidden`, and `target="_blank"` carries `rel="noopener"`.

**Expected CLS impact:** **−0.005 to −0.02** (only if it was case 1). The real prize is TBT/INP/payload/unused-JS, serving your Performance-95 goal in the other workstreams.

> **Cache-clear + retest after §6:** purge FlyingPress. If you deactivated Elfsight, re-run PSI and confirm `platform.js` is gone from the network payload. Test the button: keyboard-focus shows the ring, Enter/click opens WhatsApp, and Lighthouse Accessibility shows no color-contrast failure on the button. Revert = reactivate Elfsight, remove the WPCode HTML snippet.

---

## 7. Consolidated production-safe CSS block

Paste into **WPCode Lite → + Add Snippet → "Add Your Custom Code" → CSS Snippet**, Auto Insert → **Site Wide Header** (WPCode places CSS snippets in the head automatically — no `<style>` tag, no PHP, no hook timing to worry about). Alternatively use **Divi → Theme Options → General → Custom CSS** (back it up first). Either is instantly reversible (deactivate snippet / clear the box). **Every selector marked `VERIFY`/`PLACEHOLDER` must be reconciled with View Source + the PSI "Layout shift culprits" list, and every height must be the *measured final mobile height* of that block.**

```css
/* =====================================================================
   Thrive Downtown — CLS space-reservation (mobile field CLS fix)
   Purpose: pre-size every async/late-loading IN-FLOW container so nothing
   below it shifts. transform/opacity are never touched (they don't cause CLS).
   REVERSIBLE: remove this snippet to fully revert.
   NOTE: all VERIFY/PLACEHOLDER selectors must be confirmed vs live DOM,
   and all heights must be MEASURED (too-large => static whitespace gap).
   ===================================================================== */

/* ---- 1. Smart Slider hero -------------------------------------------- */
/* VERIFY: replace #n2-ss-2 with the real OUTERMOST slider container id
   (View Source, search "n2-ss-"). Height = SS3 Mobile size you set. */
#n2-ss-2,
.n2-ss-slider {
  min-height: 420px;            /* PLACEHOLDER: measured mobile hero height */
}
@media (min-width: 768px)  { #n2-ss-2, .n2-ss-slider { min-height: 520px; } } /* PLACEHOLDER */
@media (min-width: 981px)  { #n2-ss-2, .n2-ss-slider { min-height: 620px; } } /* PLACEHOLDER */
/* (No `contain: layout` — see §1: it can interfere with SS layers/controls.) */

/* ---- 2. Divi background-image hero section (no intrinsic dimensions) -- */
/* Only if the hero is a Section/Row BACKGROUND image, not the slider.
   PREFER a STABLE custom class: in Divi set Section → Advanced → CSS ID & Classes
   → CSS Class = "tw-hero", then target .tw-hero. Do NOT rely on Divi's auto-index
   classes like .et_pb_section_0 — they renumber when you add/remove sections. */
.home .tw-hero {                 /* VERIFY: assign this class in Divi */
  min-height: 420px;             /* PLACEHOLDER: mobile */
}
@media (min-width: 981px) {
  .home .tw-hero { min-height: 620px; }  /* PLACEHOLDER: desktop */
}

/* ---- 3. Trustindex Google Reviews async widget ----------------------- */
/* VERIFY: target ONLY the single OUTERMOST wrapper. Do NOT use a broad
   [class*="trustindex"] selector (it stacks min-heights on nested nodes and
   forcing display can break the widget's card grid). */
#ti-widget-XXXX {                /* PLACEHOLDER */
  min-height: 520px;             /* PLACEHOLDER: measured final MOBILE height */
}
@media (min-width: 981px) {
  #ti-widget-XXXX { min-height: 360px; }  /* PLACEHOLDER: measured desktop height */
}

/* ---- 4. Elfsight inline placeholder — CASE 1 ONLY -------------------- */
/* APPLY ONLY if you confirmed the visible widget is position:fixed on <body>
   AND this node is an empty stub. If the widget renders INSIDE it, this HIDES it.
   VERIFY: class is .elfsight-app-<uuid>. Delete this rule if the widget is
   truly fixed and leaves no in-flow stub. */
/*
.elfsight-app-XXXXXXXX {
  min-height: 0;
  height: 0;
  overflow: hidden;
}
*/

/* ---- 5. (REMOVED) generic lazy-image content-visibility -------------- */
/* The draft's `img[loading="lazy"]{content-visibility:auto}` was DELETED:
   without contain-intrinsic-size it collapses off-screen boxes to 0 and expands
   them on scroll — that CREATES layout shift / scrollbar jump, the opposite of
   the goal. The correct fix for lazy-image boxes is width/height attributes
   (FlyingPress → §5), which reserve the box from the aspect ratio. */

/* ---- 6. Font-swap reflow guard (pair with §4 @font-face metrics) ------ */
/* VERIFY: use Divi's ACTUAL font names + the selectors Divi already applies
   them to (DevTools → Computed → font-family). Insert "Primary Fallback" as the
   2nd family ONLY. Do NOT blanket-override headings+body to one stack — that
   flattens Divi's distinct heading/body fonts. */
body { font-family: "YourBodyFont", "Primary Fallback", Arial, sans-serif; }
h1, h2, h3 { font-family: "YourHeadingFont", "Primary Fallback", Arial, sans-serif; }
```

---

## Verification loop (after deploy)

1. **Purge everything first:** FlyingPress cache (admin bar **Purge Cache** or **FlyingPress → Cache → Purge Everything**), plus any WebP Express / CDN / hosting cache. Applied-at-cache-time changes (image dimensions, delay-JS) only take effect after regeneration.
2. **DevTools mobile emulation** (Moto G / 4× CPU throttle, "Fast 3G"): record a reload in the **Performance** panel → the **Layout Shift** track should now be empty or a few tiny bars totalling < 0.05. Click any remaining bar to see the culprit node and reconcile it with the placeholders above.
3. **PSI re-run:** lab CLS should stay ≤ 0.05; then wait for **field** data — CrUX field CLS updates on a **28-day rolling window**, so the *field* pass won't flip immediately. Track the trend in **Search Console → Core Web Vitals** or the CrUX History API; expect the 75th-percentile CLS to fall below 0.10 within ~1–2 weeks of real traffic.
4. **Confirm no functional regressions:**
   - Mobile menu opens/closes.
   - Slider (if kept) still slides; if you delayed JS, the hero still paints (Smart Slider excluded from delay — see below).
   - **Gravity Forms** submit works, including conditional logic and reCAPTCHA (these break if their JS is delayed).
   - WhatsApp button clicks through and is keyboard-focusable.
5. **JS delay guardrails (do NOT hand-defer jQuery — let FlyingPress do it with excludes).** In **FlyingPress → JavaScript → Delay JavaScript**, keep the following on the **exclude-from-delay** list so Divi's above-the-fold behavior and forms keep working (these match against the script URL/handle; FlyingPress already excludes some by default — verify):
   - Divi/jQuery core: `jquery`, `jquery-core`, `jquery-migrate`, `et-core`, `divi-custom-script`, `/js/custom.js`
   - Smart Slider: `n2`, `smartslider`, `nextend`
   - Gravity Forms: `gravityforms`, `gform`, `recaptcha`, `g-recaptcha`
   > Manually deferring/delaying jQuery outside FlyingPress **will** break Divi's menu/sliders and Gravity Forms — do not do it. Keep all JS-timing changes inside FlyingPress with the excludes above so they are toggleable and reversible.

**Net expected result:** stacking fixes 1 (or 2), 3, 4, 5 conservatively removes **~0.10–0.15** of field CLS — taking you from **0.20 → ~0.06–0.09**, i.e. mobile Core Web Vitals **PASSES**. Fix 2 (static hero) additionally buys LCP/JS headroom toward the Performance-95 goal owned by the other workstreams, and fix 6-Option-B removes the Elfsight bundle to help TBT/INP/payload/unused-JS.