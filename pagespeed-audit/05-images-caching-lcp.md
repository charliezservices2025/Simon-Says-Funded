> **Part of the [Thrive Downtown PageSpeed remediation package](00-AUDIT-REPORT.md).** Follow the ordered plan in [`01-IMPLEMENTATION-CHECKLIST.md`](01-IMPLEMENTATION-CHECKLIST.md); this file is the detailed reference for one workstream. WebP Express + FlyingPress + htaccess-01 + snippet 05. Every `VERIFY`/`PLACEHOLDER` marker must be confirmed against the live site — see [`08-residual-risks-and-verification.md`](08-residual-risks-and-verification.md).

---

# Workstream C — Payload, Image Delivery, Cache Lifetimes & LCP

Scope: cut total transfer (mobile 3,350 KiB / desktop 2,899 KiB), fix **Improve image delivery** (mobile 161 / desktop 557 KiB), fix **Use efficient cache lifetimes** (~372 KiB), and speed **LCP**. All steps are reversible and prefer the already-installed plugins (WebP Express, FlyingPress, WPCode, WP File Manager).

> **Read-before-you-edit rule:** several fixes reference the *exact* LCP element, hero filename(s), and the count of above-the-fold images. Those can only be known from the live PSI "show details" and the live DOM. Where a selector, URL, or count is a placeholder, it is marked **VERIFY AGAINST LIVE DOM** — extract the real value first, then apply.

## ROI-ordered fix list (do in this order)

| # | Fix | Primary metric moved | Rough magnitude | Tool |
|---|-----|---------------------|-----------------|------|
| 1 | WebP conversion (whole library) + right-size the hero | Image delivery, payload, LCP | −0.6 to −1.4 MB payload; image-delivery audit → passed | WebP Express |
| 2 | Preload LCP hero + `fetchpriority=high` + exclude from lazy-load | LCP (lab 2.8s → ~1.6–1.9s) | −0.6 to −1.0s LCP mobile | FlyingPress, then WPCode fallback |
| 3 | Right-size **desktop** hero specifically (the 557 KiB tell) | Desktop image delivery, LCP | −400 to −550 KiB desktop | Re-export + WebP |
| 4 | `.htaccess` browser-cache headers (immutable + long max-age) + compression | "Efficient cache lifetimes" (first-party ~), repeat-visit | Fixes first-party slice of the 372 KiB; audit → passed for own assets | WP File Manager |
| 5 | Reduce third-party payload (Elfsight / Trustindex / Divi / Smart Slider / fonts) | Payload, TBT, LCP | −0.5 to −1.5 MB + TBT | FlyingPress (cross-ref JS workstream) |
| 6 | Self-host / delay gtag + fbevents for the third-party cache slice | Remaining "cache lifetimes" bytes | Cross-ref JS workstream | WPCode / FlyingPress |

Total realistic payload after 1–5: **mobile ≈ 1.1–1.5 MB, desktop ≈ 1.2–1.6 MB** — enough to clear the "enormous payload" and "image delivery" diagnostics and push Performance toward 95.

---

## 1. WebP Express — configuration

Path: **WordPress admin → Settings → WebP Express**.

> **Reversibility for the whole plugin:** deactivating WebP Express removes its `.htaccess` marker block and the site serves the untouched original JPEG/PNG files again. Originals are never modified or deleted; WebP copies live in a separate directory. This is why it is the lowest-risk way to get WebP on a Divi build.

### 1.1 Converter (conversion method)
Section (label may read slightly differently by version): the **conversion-method / converter priority** list under the main conversion options. WebP Express probes local converters in a preferred order. Set the order to:

1. **cwebp** (preferred) — Google's reference binary, best quality-per-byte and honours `near-lossless`. WebP Express ships precompiled cwebp binaries, so it usually works even without a system install.
2. **imagick** (fallback) — if the host has ImageMagick with the WebP delegate.
3. **vips** (fallback) — fast, low memory, if available.
4. **gd** (last resort) — always present but lowest quality and no alpha-quality control.

> Verify each converter row shows a green "working"/"operational" status. Leave cwebp at the top. Do **not** promote `gd` unless cwebp/imagick both fail — gd produces larger files and ignores the alpha-quality setting.

### 1.2 Encoding & quality
Section: the **conversion options** (encoding / quality / metadata).

- **Encoding:** set to **`auto`**. Auto encodes both lossy and lossless per image and keeps whichever is smaller — ideal for photos (lossy) and flat PNG/logos (lossless) without sorting them by hand.
- **Quality (lossy):** **`72`** (set the quality mode to a fixed value rather than "auto"; "auto" quality-detection needs imagick/gmagick on the source and can silently fall back). Rationale: 72 is the sweet spot for photographic content — visually indistinguishable from 80+ at typical DPR but ~15–25% smaller, and it avoids banding you get below ~65. If any hero looks soft after conversion, bump that single image, not the global value.
- **Alpha channel quality:** **`85`** (only relevant when a transparent PNG is stored lossy). Keeps edges of transparent logos/icons crisp while still shrinking.
- **Near-lossless:** **`60`** — applies on the lossless path; squeezes flat graphics further with no visible loss.
- **Metadata:** **`none`** — strips EXIF/ICC from the WebP copy (privacy + a few KB per image). Originals untouched.

**Lossy vs lossless for transparent PNG:** don't hard-set lossless globally. With **Encoding = auto**, WebP Express tries both per image and keeps the smaller — a photographic PNG-with-alpha usually wins as lossy@72 + alpha@85; a flat logo wins as lossless/near-lossless. Strictly better than forcing one mode.

### 1.3 Operation mode + "Alter HTML" — the Divi/FlyingPress-safe choice
Section: **Operation mode** and the **Alter HTML?** option in the general settings.

**Recommendation: Operation mode = "Varied image responses", and leave "Alter HTML?" = OFF.**

Why this is the safest option on *this* stack (Divi + Smart Slider + FlyingPress), not just a default:

- **Divi heroes are usually CSS `background-image` on the Section/Row (inline `style="background-image:url(...)"`), not `<img>` tags.** The `<picture>`-tag method and the img-`src` URL-replacement method **only rewrite `<img>` elements** — they cannot convert a CSS background. **"Varied image responses" converts *everything*** — `<img>`, CSS backgrounds, slider images, favicons — because the browser requests the *same original URL* (`photo.jpg`) and the server returns `image/webp` bytes via an `.htaccess` content-negotiation redirect keyed on the `Accept` header. So the Divi hero background gets WebP automatically.
- **Zero DOM changes** → it cannot break Smart Slider init, the Divi mobile menu, or Gravity Forms markup, because the HTML the browser sees is byte-identical to before.
- **Fully reversible:** deactivate WebP Express → the `.htaccess` markers are removed → originals serve again.

> **Scope check (required):** confirm the WebP Express **"scope of conversion" / directories** setting covers `wp-content/uploads` (where Divi-builder and Smart Slider images are stored) and `wp-content/themes` if any hero background is a theme asset. If a directory is out of scope, its images stay JPEG even in Varied mode.

**The one caveat and when to switch:** "Varied image responses" sends `Vary: Accept` on images, which an *image-caching CDN* (Cloudflare Polish, BunnyCDN Optimizer) can cache incorrectly (one variant served to all). This stack shows **no image CDN** (FlyingPress is a page cache; WebP Express is local), so the caveat does not currently apply. **If** you later put images behind a CDN that can't vary on `Accept`, switch to:

- **Operation mode = "CDN friendly"** + **Alter HTML? = ON** → **Replacement method = "Use `<picture>` tags"** (each URL then returns exactly one content-type, CDN-safe), and enable the **"also replace URLs in inline styles"** option so Divi CSS backgrounds are still converted. Picture tags coexist with FlyingPress lazy-load. Avoid the plain "replace image URLs" method with Divi unless inline-style replacement is confirmed on, or hero backgrounds stay JPEG.

### 1.4 Bulk-convert the existing library
Section: **Bulk conversion** (bottom of the WebP Express page).

1. Click **"Convert"** under Bulk conversion. Leave **"Only convert images that are missing"** (or the equivalently-worded "skip already-converted") checked on re-runs.
2. Let it finish (counter shows converted/skipped/failed). Originals are **not** modified; WebP copies land under `…/wp-content/webp-express/webp-images/…`.
3. If it times out on a large library, re-run — it resumes. On very large libraries, raise PHP `max_execution_time` temporarily (host panel or php.ini via WP File Manager), then revert.

Expected: the single biggest payload cut — every existing hero, review avatar, blog image, and Divi background starts serving as WebP.

### 1.5 AVIF (honest note)
**WebP Express does not produce AVIF** (WebP only, in current builds). AVIF would add another ~15–25% over WebP on photos, but requires either:
- swapping to the **"Converter for Media"** plugin (which supports **WebP + AVIF** with a similar redirect/`<picture>` approach) — a plugin swap, **test on staging first**, **or**
- an image CDN with AVIF (Bunny Optimizer / Cloudflare Polish Pro).

Given the "prefer installed plugins / reversible" constraint, **ship WebP now via WebP Express** and treat AVIF as an optional later swap — the WebP win captures the large majority of the savings.

### 1.6 Verify WebP is actually being served
Do at least two of these:

- **DevTools:** Chrome → Network → filter **Img** → reload → the **Type** column reads `webp` for content images and the Divi hero. Click the hero request → Response Headers → `Content-Type: image/webp` (and `Vary: Accept` in Varied mode).
- **curl (from any machine that can reach the site):**
  ```bash
  curl -s -o /dev/null -D - -H "Accept: image/webp" \
    "https://thrivedowntown.com/wp-content/uploads/YOUR-HERO.jpg" | grep -i "content-type\|vary"
  # Expect: Content-Type: image/webp   and   Vary: Accept
  ```
  Then request the same URL **without** the Accept header → should return `image/jpeg` (proves graceful fallback).
- **Disk check (WP File Manager):** confirm `…/wp-content/webp-express/webp-images/doc-root/wp-content/uploads/…` is populated.
- WebP Express also has a **"Live test"** panel that reports whether conversion + serving works end-to-end.

---

## 2. LCP optimization

### 2.1 Identify the real LCP element (method, don't guess) — VERIFY AGAINST LIVE DOM
Do this first — the fix targets one specific URL:

1. PageSpeed Insights → mobile report → **"Largest Contentful Paint element"** diagnostic → expand → it names the element (usually the Smart Slider first-slide image or the Divi hero Section background).
2. Confirm the exact fetched URL: Chrome DevTools → **Performance** panel → record a mobile-emulated reload → find the **LCP** marker → the related image request shows the resolved URL and rendered size. Alternatively DevTools → Network → sort by time; the hero image is the large early image request.
3. **Also record: is the element an `<img>` or a CSS `background-image`?** This determines which preload form in §2.3 is correct.

Likely candidates on this build, in order: **(a) Smart Slider 3 first-slide image**, **(b) Divi hero Section `background-image`**, **(c)** a headline `<img>`. The `<img>` vs CSS-background distinction is load-bearing — do not skip step 3.

### 2.2 Preload + fetchpriority (do it via FlyingPress first)
**FlyingPress → Images** (labels are version-robust; match by function):

- The **above-the-fold / critical-image handling**: FlyingPress excludes the first *N* images from lazy-load and boosts them with `fetchpriority="high"`. Find the field that sets that count — commonly **"Exclude Above the Fold Images"** (a number). **Start at `2`** (hero + logo). Raise only if PSI still reports the LCP image was lazy-loaded. If your version also exposes a distinct **"Preload Critical Images"** toggle, enable it; if it does not, the above-the-fold count is what does the priority boost.

This covers `<img>`-based LCP (including Smart Slider `<img>` slides) automatically and is fully reversible (toggle/number back).

### 2.3 Manual preload fallback (WPCode) — for a CSS-background hero or to force the exact URL
If the LCP element is a **Divi Section `background-image`** (FlyingPress does not auto-preload arbitrary CSS backgrounds), or PSI still flags "LCP request discovery," add this **WPCode PHP snippet**. It is front-page-only, guarded, and reversible (deactivate the snippet = gone).

> **URL rule (WebP Express "Varied image responses"):** preload the **original image URL exactly as it appears in the HTML/CSS** (e.g. `…/hero.jpg`). The server returns WebP for it via content negotiation. Do **not** point the preload at a `.webp` file. (Only if you switched to "CDN friendly"/URL-replacement should the preload target the `.webp` URL.)
>
> **`<img>` vs CSS background — pick the right form (avoids a double-download):** a responsive preload with `imagesrcset`/`imagesizes` is **only** correct when the LCP is an `<img>` and you match its `srcset`/`sizes` exactly. A Divi **CSS `background-image` requests one single URL** — if you preload a different `imagesrcset` variant, the browser downloads that variant *and then* downloads the CSS-requested file, hurting LCP. For a CSS-background hero use the **single-URL** variant shown below and preload the exact URL the CSS requests.

WPCode → **Add Snippet → Add Your Custom Code (New Snippet) → PHP Snippet**. Insertion: **Auto Insert**, run on **front-end** (Site-Wide). In WPCode's PHP editor you may leave the leading `<?php` in place (WPCode tolerates it) or delete it — but **never add a closing `?>`**. Paste:

```php
<?php
/**
 * Preload the homepage LCP hero image with high priority.
 * Reversible: deactivate this WPCode snippet to fully revert.
 * SAFE: front-end + front-page only, escaped output, no side effects,
 *       and it self-disables while the placeholder URL is unedited.
 */
if ( ! function_exists( 'tdc_preload_lcp_hero' ) ) {
	function tdc_preload_lcp_hero() {
		// Front-end homepage only.
		if ( is_admin() || ! is_front_page() || is_paged() ) {
			return;
		}

		/* =======================================================================
		 * REPLACE the URL(s) below with the REAL hero image URL from the PSI
		 * "Largest Contentful Paint element" report (§2.1). Use the ORIGINAL
		 * extension (.jpg/.png) — WebP Express "Varied image responses" serves
		 * WebP for it automatically.
		 * - <img> LCP: set both to the mobile + desktop srcset URLs and keep the
		 *   responsive printf.
		 * - CSS-background LCP: set $hero_mobile to the exact background URL and
		 *   use the SINGLE-URL printf in the notes below (delete the responsive one).
		 * ===================================================================== */
		$hero_mobile  = 'https://thrivedowntown.com/wp-content/uploads/REPLACE-hero-800.jpg';
		$hero_desktop = 'https://thrivedowntown.com/wp-content/uploads/REPLACE-hero-1600.jpg';

		// Safety: never emit a preload while the placeholder is unedited
		// (prevents shipping a 404 preload to production).
		if ( empty( $hero_mobile ) || false !== strpos( $hero_mobile, 'REPLACE-' ) ) {
			return;
		}

		// RESPONSIVE preload — ONLY for an <img> LCP whose srcset/sizes you match.
		printf(
			'<link rel="preload" as="image" href="%1$s" imagesrcset="%1$s 800w, %2$s 1600w" imagesizes="100vw" fetchpriority="high">' . "\n",
			esc_url( $hero_mobile ),
			esc_url( $hero_desktop )
		);
	}
	// Priority 1 so it lands near the very top of <head>, before stylesheets.
	add_action( 'wp_head', 'tdc_preload_lcp_hero', 1 );
}
```

**CSS-background hero variant** — replace the responsive `printf` above with this single-URL form (no `imagesrcset`), preloading the exact URL the CSS `background-image` requests:

```php
		// CSS-BACKGROUND hero: preload the one exact URL the CSS requests.
		printf(
			'<link rel="preload" as="image" href="%1$s" fetchpriority="high">' . "\n",
			esc_url( $hero_mobile )
		);
```

Notes:
- If Divi serves a **different background image per breakpoint** (desktop/tablet/phone set separately in the builder), a single preload can only target one. Preload the **phone** URL (mobile LCP/CLS is the failing metric), or emit two `<link … media="(max-width:980px)">` / `media="(min-width:981px)">` preloads with the correct per-breakpoint URLs. **VERIFY AGAINST LIVE CSS** which URLs each breakpoint actually loads.
- Keep **only one** preload for the LCP image. If FlyingPress already preloads the same `<img>`, don't also run this snippet for that URL (double preload = wasted bytes + a console warning). Use this snippet specifically for the **CSS-background** case FlyingPress misses.

### 2.4 Exclude the LCP image from lazy-load
- Covered by the FlyingPress above-the-fold count (§2.2).
- If the LCP image is a **Smart Slider slide** that still lazy-loads: in **Smart Slider → the slider**, find the first slide's image **load/optimize** setting and set slide 1 to load **eagerly** (Smart Slider labels this as the image "Load" mode / an "Optimize" toggle — **VERIFY the exact control in your version**). Then, if FlyingPress exposes a lazy-load **exclude-by-keyword** field, add a token that matches the hero filename or the slider container (`n2-ss-`, `smartslider`, or the hero filename stem). If your FlyingPress version has no keyword-exclude field, rely on the above-the-fold count plus the Smart Slider eager setting. All reversible (remove the token / revert the count).
- Divi CSS-background heroes are **not** `loading="lazy"` by default, so this is mainly the Smart Slider concern.

### 2.5 Correct responsive `srcset`/`sizes` — VERIFY AGAINST LIVE DOM
- Divi and WordPress core add `srcset`/`sizes` to `<img>` automatically. Confirm the hero `<img>` (if it is an `<img>`) actually has a `srcset` in View Source; if a plugin stripped it, that's the fix.
- For a **`<img>` preload** to be *used*, `imagesizes` must equal the rendered CSS width **and** the `imagesrcset` URLs must match the `<img>`'s real `srcset` URLs — otherwise the browser downloads the preload and then the real image (double download). A full-width hero is `100vw` (used above). If the hero sits in a max-width container, set `imagesizes` to that (e.g. `(max-width: 1080px) 100vw, 1080px`).
- **Divi CSS-background heroes have no srcset** (CSS can't). That's why right-sizing the source file (§3) matters most for them.

**Expected impact (fixes 2.2–2.5 combined):** lab LCP **2.8s → ~1.6–1.9s** mobile; clears "LCP request discovery" and "LCP image was lazily loaded" flags. Directly supports Performance ≥ 95 and reduces the late hero paint that feeds mobile field CLS.

---

## 3. Responsive sizing — stop serving images larger than displayed

The **desktop image-delivery savings (557 KiB) being ~3.5× mobile's (161 KiB)** is the diagnostic signature of an **oversized desktop hero** — a hero exported at ~2560px+ (or a raw camera JPEG) served into a ~1200–1440px column, and/or under-compressed. Mobile is smaller only because `srcset` hands phones a smaller variant; the desktop path gets the giant original.

Fixes:

1. **Re-export the hero at sane intrinsic dimensions.** For a full-width hero, cap the **desktop source at 1920px wide** (covers 1080p/1440p; 2560px doubles bytes for marginal sharpness). Provide a **~800–1000px mobile** variant. Save as JPEG quality ~78 before WebP Express re-converts (WebP@72 shrinks it further). Re-upload and re-point the hero. This is the single biggest lever on the 557 KiB. **Keep the old file until the new one is confirmed live** (revert = re-point to the original).
2. **Smart Slider:** open the slider → **slider settings → Size** and set a realistic max width (don't leave a 2560px canvas). Smart Slider Pro can generate responsive image variants — ensure the slider's **image-optimize** option is on so it doesn't ship the master file (**VERIFY the exact toggle label in your version**). Set each slide's background image to the optimized size.
3. **Divi backgrounds:** upload background images at the Section's real rendered width; Divi won't downscale a CSS background for you. For retina, ~1.5× displayed width is a good ceiling — 2× inflates bytes for little perceived gain on a background.
4. **FlyingPress → Images:**
   - **Add width/height to images** (the toggle that writes missing `width`/`height` onto `<img>`) → **ON** — prevents reflow; also helps the CLS workstream.
   - **Lazy Load Images** → **ON** (defers below-the-fold; hero excluded per §2).
   - **Adaptive Images:** note this only *resizes* when paired with an image CDN (FlyingCDN/Bunny). Without a CDN it won't shrink the desktop hero — so **right-sizing the source (step 1) is the real fix**, not this toggle.
5. **Verify:** DevTools → Network → click the hero → compare **"Intrinsic size"** vs **"Rendered size"**. Rendered should be ≥ ~0.9× intrinsic. If intrinsic is 2–3× rendered, the source is still too big.

**Expected impact:** desktop image-delivery **−400 to −550 KiB**, desktop payload 2,899 → ~2,300 KiB before the other cuts; faster desktop LCP; contributes to desktop Performance ≥ 95.

---

## 4. Cache lifetimes ("Use efficient cache lifetimes", ~372 KiB)

**Key correction up front:** this PSI audit measures the **HTTP `Cache-Control: max-age`** on static asset responses. **FlyingPress's page cache does not set browser `max-age` on your CSS/JS/images/fonts** — that's the web server's job. So the fix for the **first-party** slice is an **`.htaccess`** block (Apache) or server config; the **third-party** slice (gtag, fbevents) is *unfixable by headers* and only improves by self-hosting.

### 4.1 FlyingPress (page cache + preload)
FlyingPress → **Cache**:
- Leave the full-page cache enabled; FlyingPress auto-invalidates on content update, so a long/natural lifespan is correct — you don't need a short expiry. (If your version exposes a **cache-lifespan / expiry** field, the default or a long value is fine; content edits and the preloader keep it fresh.)

FlyingPress → **Preload**:
- **Preload pages** (sitemap-based warming) → ON.
- **Preload links** (fetch on hover/mousedown for near-instant navigation) → ON.
- **Preload fonts** → add the 1–2 above-the-fold web fonts (see §5).

This addresses TTFB/repeat-view but **not** the PSI cache-lifetime audit — that's §4.2.

### 4.2 `.htaccess` — browser cache headers + compression (the actual audit fix)

> **Apache only.** This block uses `mod_expires`/`mod_headers`/`mod_brotli`/`mod_deflate`. If the host runs **LiteSpeed** (use LiteSpeed Cache's static rules) or **Nginx** (use the server `location`/`add_header` config), this `.htaccess` block will not apply — request the equivalent for that server type. Each directive is wrapped in `<IfModule>`, so on Apache a missing module is a harmless no-op rather than a 500.

**Backup + reversal (do this first):** in **WP File Manager**, download a copy of the current site-root `.htaccess` before editing (this is your revert). Placement: put the block **above** the `# BEGIN WordPress` marker, and **do not** edit inside the `# BEGIN WebP Express` / `# BEGIN FlyingPress` marker blocks (they manage themselves). To revert: delete this whole marked block, or restore the backup. **After saving, immediately load the homepage and one inner page** — a bad directive yields an HTTP 500; if so, restore the backup at once.

```apache
# BEGIN Thrive Downtown Static Caching + Compression
# Reversible: delete this whole block (or restore the .htaccess backup) to revert.

<IfModule mod_expires.c>
  ExpiresActive On
  # Fallback default
  ExpiresDefault                          "access plus 1 month"

  # Images (incl. WebP/AVIF/SVG)
  ExpiresByType image/jpeg                "access plus 1 year"
  ExpiresByType image/png                 "access plus 1 year"
  ExpiresByType image/gif                 "access plus 1 year"
  ExpiresByType image/webp                "access plus 1 year"
  ExpiresByType image/avif                "access plus 1 year"
  ExpiresByType image/svg+xml             "access plus 1 year"
  ExpiresByType image/x-icon              "access plus 1 year"
  ExpiresByType image/vnd.microsoft.icon  "access plus 1 year"

  # Fonts
  ExpiresByType font/woff2                "access plus 1 year"
  ExpiresByType font/woff                 "access plus 1 year"
  ExpiresByType application/font-woff     "access plus 1 year"
  ExpiresByType application/vnd.ms-fontobject "access plus 1 year"

  # CSS / JS
  ExpiresByType text/css                  "access plus 1 year"
  ExpiresByType application/javascript    "access plus 1 year"
  ExpiresByType text/javascript           "access plus 1 year"

  # HTML must stay short so page-cache updates are seen
  ExpiresByType text/html                 "access plus 0 seconds"
</IfModule>

<IfModule mod_headers.c>
  # Long-lived, immutable for versioned/static assets (see caveat below).
  <FilesMatch "\.(?:css|js|mjs|woff2?|ttf|otf|eot|jpe?g|png|gif|webp|avif|svg|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
  # Never cache HTML documents in the browser.
  <FilesMatch "\.(?:html|htm|php)$">
    Header set Cache-Control "no-cache, must-revalidate, max-age=0"
  </FilesMatch>
</IfModule>

# ---- Compression ----
# Prefer Brotli when the host provides it (better ratio than gzip):
<IfModule mod_brotli.c>
  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml application/rss+xml image/svg+xml application/vnd.ms-fontobject font/ttf font/otf
</IfModule>
# Gzip ONLY when Brotli is absent — the !mod_brotli guard prevents both
# output filters running on the same response (which can double-compress
# and corrupt output when both modules are loaded).
<IfModule !mod_brotli.c>
  <IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml application/rss+xml image/svg+xml application/vnd.ms-fontobject font/ttf font/otf
  </IfModule>
</IfModule>
# Note: woff2/webp/avif/jpg/png are already compressed and are intentionally
# NOT in the compress lists above.

# END Thrive Downtown Static Caching + Compression
```

Notes / correctness:
- **`immutable` caveat (real, must understand):** `immutable` tells browsers never to revalidate for the cache lifetime (1 year). This is safe **only** for assets whose URL changes when their content changes — WordPress core, Divi, and FlyingPress add `?ver=` query strings or hashed/rebuilt filenames, so an updated asset gets a new URL. **The risk:** if you ever replace a CSS/JS/font/SVG/image file *in place* with the *same filename and no version change* (e.g. overwrite `logo.svg`), returning visitors may keep the stale version for up to a year. Workaround when you do such an in-place swap: change the filename or append a version query. If this footgun is unacceptable, drop the word `immutable` (keep `public, max-age=31536000`) — the PSI audit still passes on `max-age` alone.
- **Do not let this block break WebP Express negotiation:** the image extensions in the `mod_headers` `FilesMatch` set `Cache-Control` on image responses, and WebP Express's Varied mode relies on `Vary: Accept`. `Header set Cache-Control` only touches `Cache-Control`, not `Vary`, so it should coexist — **but verify** (below) that image responses still send `Vary: Accept` after applying. **If `Vary: Accept` is missing**, remove the image extensions (`jpe?g|png|gif|webp|avif|svg|ico`) from the `mod_headers` `FilesMatch` and rely on the `mod_expires` `image/*` lines for image caching (they satisfy the audit); leave only `css|js|mjs|woff2?|ttf|otf|eot` under `immutable`.
- **Managed hosts often Brotli/gzip at the edge already** — then the compression blocks are harmless no-ops (guarded by `IfModule`).
- **Optional MIME safety:** if `.webp`/`.avif` files 404 or serve with the wrong `Content-Type` on this host, add `AddType image/webp .webp` and `AddType image/avif .avif` (WebP Express's own block usually already handles webp).
- **Verify:**
  ```bash
  curl -sI "https://thrivedowntown.com/wp-content/…/style.css" | grep -i "cache-control\|content-encoding"
  # Expect: Cache-Control: public, max-age=31536000, immutable   and   Content-Encoding: br (or gzip)

  curl -sI -H "Accept: image/webp" "https://thrivedowntown.com/wp-content/uploads/YOUR-HERO.jpg" | grep -i "vary\|content-type\|cache-control"
  # Expect: Vary: Accept  +  Content-Type: image/webp  (negotiation still intact)
  ```
  Then re-run PSI → "Efficient cache lifetimes" should drop your first-party assets off the list.

### 4.3 Third-party slice (headers can't fix this)
The stubborn part of the 372 KiB is **cross-origin**: `www.googletagmanager.com/gtag`, `google-analytics.com`, `connect.facebook.net/fbevents.js` (Meta Pixel), Elfsight/Trustindex platform scripts. You **cannot** set `Cache-Control` on another origin's response. The only fixes are **self-hosting** gtag/fbevents (served from your domain with your long `max-age`) and/or **delaying** them so they don't count against first load. That work lives in the **JavaScript workstream** — flagged here so it isn't double-owned. Expect the cache-lifetime audit to *still list gtag/fbevents* until that self-hosting is done; the `.htaccess` block clears everything you actually control.

---

## 5. Enormous network payload — concrete offenders & the fix for each

Enumerated for *this* stack, largest-first, with the lever and the metric it moves. Items marked **(JS)** are owned by the JavaScript workstream — listed here only so the payload accounting is complete.

| Offender | Why it's heavy | Fix | Owner / metric |
|---|---|---|---|
| **Hero + content images (JPEG/PNG, oversized)** | Biggest single chunk; desktop hero oversized (the 557 KiB tell) | §1 WebP@72 whole-library + §3 right-size hero | This WS · payload −0.6–1.4 MB, LCP |
| **Smart Slider 3 Pro** (CSS/JS + slide images) | Ships slider engine + master-size images site-wide | Optimize slider images (§3.2); load slider only on pages that use it; disable unused slider features. FlyingPress **Delay JavaScript** for `n2-ss`/`smartslider` **(JS)** | Split · payload + TBT |
| **Elfsight WhatsApp Chat** (`platform.js` from apps.elfsight.com) | Third-party embed pulls a large bundle for a floating button | **Highest-value swap:** replace the Elfsight widget with a lightweight static click-to-chat link (`https://wa.me/<number>`) styled as a floating button — removes the entire third-party bundle. Reversible (re-add the Elfsight embed). This changes a visible UI element, so preview before shipping. If keeping Elfsight: FlyingPress **Delay JavaScript** until interaction **(JS)** | Split · payload −100–300 KB, TBT, LCP |
| **Trustindex Google Reviews** (external JS + avatar images) | Loads a script + many small review-avatar images | FlyingPress **Delay JavaScript** for the Trustindex script; ensure its avatars lazy-load (below fold). Consider the widget's "static/cached" render mode if available **(JS + this WS)** | Split · payload + TBT |
| **Divi core CSS/JS** (`et-core`, Divi styles, `custom.js`) | Framework ships lots of unused CSS/JS | FlyingPress **Used CSS** (remove unused) + **Minify**; defer non-critical JS with proper excludes **(JS)** | JS WS · unused CSS 12/27 KiB, TBT |
| **gtag.js + Google Analytics + Meta Pixel (fbevents)** | Third-party JS + short cache | Delay + self-host **(JS)** — see §4.3 | JS WS · payload, cache |
| **Web fonts** (Divi icon fonts + any Google Fonts) | Font files + swap reflow | FlyingPress **Fonts** (self-host Google Fonts + `font-display: swap`), **Preload** the 1–2 above-the-fold fonts; drop unused Divi icon-font weights | This WS · payload −50–150 KB, CLS (font swap), LCP |
| **Gravity Forms CSS/JS** | Loads even on pages without a form | Gravity Forms → output only where needed / dequeue on non-form pages **(JS)** | JS WS · payload |
| **Legacy JS ~11 KiB** | Divi/plugin polyfills for old browsers | Minor; FlyingPress minify. Don't hand-strip Divi's — not worth the risk | JS WS · minor |

**Fonts detail (this workstream owns it):** in **FlyingPress → Fonts**, enable the **"optimize / self-host Google Fonts"** option (pulls Google Fonts local, kills the `fonts.googleapis.com` round-trip and applies `font-display: swap`), then add the hero heading font under **Preload Fonts**. This removes a render-blocking third-party request and reduces the font-swap reflow that feeds mobile field CLS. **Also check Divi → Theme Options → General/Fonts:** if Divi is loading Google Fonts through its own mechanism, disable Divi's Google-font loading so fonts aren't fetched twice (once by Divi, once by FlyingPress). **VERIFY** in View Source that only the self-hosted font URLs remain.

---

## 6. Divi safety guardrails (so nothing here breaks the build)

- **Never** hand-defer or delay **jQuery** — Divi's `divi-custom-script`, the mobile menu, and Smart Slider init depend on jQuery being present at run time. Let **FlyingPress handle JS defer/delay** with excludes (that config lives in the JS workstream). If you touch FlyingPress **Delay JavaScript**, keep these **excluded from delay**: `jquery`, `jquery-core`, `jquery-migrate`, `et-core`, `divi-custom-script`, `smartslider`, `n2-ss`, `gform`, `gravityforms`. Delaying any of those breaks the menu/slider/forms. After changing delay/defer settings, clear the FlyingPress cache and manually test the slider, hamburger menu, and a form submit.
- WebP Express in **"Varied image responses"** makes **no HTML/DOM change**, so it's the risk-free way to get WebP under Divi.
- Every change here is reversible: WebP Express (deactivate → originals), FlyingPress toggles (flip back), WPCode preload snippet (deactivate), `.htaccess` block (delete the marked block / restore backup).

---

## 7. Verification checklist (after applying 1–4)

Run **after clearing the FlyingPress cache** (FlyingPress → clear cache) so you're testing fresh output:

1. **WebP serving:** DevTools Network → hero Type = `webp`; `curl -sI -H "Accept: image/webp" <hero.jpg>` → `Content-Type: image/webp` **and `Vary: Accept` still present** (WebP negotiation not broken by §4.2).
2. **LCP:** DevTools Performance mobile trace → LCP marker < ~1.9s; PSI no longer flags "LCP image lazily loaded" / "LCP request discovery."
3. **Preload:** View Source → exactly **one** `<link rel="preload" as="image" fetchpriority="high">` for the hero, pointing at the **original** URL (not `.webp`), with the correct form (responsive only if the LCP is an `<img>`).
4. **Cache headers:** `curl -sI` a CSS/JS/font/webp URL → `Cache-Control: public, max-age=31536000, immutable` (or without `immutable` if you dropped it) + `Content-Encoding: br|gzip`.
5. **Payload:** re-run PSI → total transfer down to the ~1.1–1.6 MB range; "Improve image delivery" and "Avoid enormous network payloads" cleared or near-cleared; "Efficient cache lifetimes" shows only remaining third-party (gtag/fbevents) items → hand off to JS workstream.
6. **No regressions (mandatory):** load homepage + one inner page (no HTTP 500 from `.htaccess`), homepage slider animates, mobile hamburger menu opens, a Gravity Form submits.

**Files/locations touched (all reversible):** WebP Express settings; FlyingPress → Images / Preload / Fonts; one WPCode PHP snippet (`tdc_preload_lcp_hero`, front-page only); site-root `.htaccess` (`# BEGIN Thrive Downtown Static Caching + Compression` block, edited via WP File Manager, **backup taken first**).

**Cross-references to the JavaScript workstream (not owned here):** self-hosting/delaying gtag + fbevents (§4.3), FlyingPress Used CSS / defer / Delay JavaScript with the Divi exclude list (§6), Elfsight/Trustindex script delay (§5).