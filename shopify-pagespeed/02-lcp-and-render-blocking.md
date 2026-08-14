# Stage 1 — LCP and render-blocking (mobile: up to +25 points)

**Target:** mobile LCP 15.8 s → ≤ 2.2 s, FCP 6.3 s → ≤ 1.5 s. Desktop LCP 1.3 s → ~0.9 s
(buys TBT headroom: see the table in `00-DIAGNOSIS.md` §2).

**Risk: low.** Nothing here changes your layout or design. It changes *how* the same hero is
delivered to the browser.

---

## 1. Identify the LCP element (do this first — 2 minutes)

Everything below depends on knowing what the LCP element actually is.

1. Open the mobile PSI report → **Largest Contentful Paint** metric → expand **"LCP breakdown"**.
2. It names the element and splits the time into four phases: TTFB, Load Delay, Load Time,
   Render Delay.

Read the split like this:

| Phase dominant | Meaning | Fix |
|---|---|---|
| **Load Delay** large | Browser found the image late | §2 + §3 — this is almost certainly your case |
| **Load Time** large | Image is too heavy | §4 |
| **Render Delay** large | JS blocked painting after load | Stage 2 (`03-javascript-and-tbt.md`) |
| **TTFB** large | Server/theme response slow | §6 |

A 9.5 s gap between FCP and LCP means **Load Delay** dominates. The rest of this document
assumes that; if the breakdown says otherwise, follow the table instead.

---

## 2. Render the hero as a real `<img>` in the HTML

The browser's preload scanner only requests images it can see in the HTML as it streams in. It
cannot see:

- a `background-image` in CSS or in a `style` attribute,
- an image inserted by a slideshow/carousel script after JS runs,
- an image with `loading="lazy"` (it is deliberately deferred).

**All three are the standard causes of a 15 s LCP on a Shopify homepage**, and the hero on
badgemyauto.com — a full-bleed image with "GIVE YOUR CAR THE NAME IT DESERVES" over it — is
exactly the shape that triggers this.

### The rule for the first slide / hero

```liquid
{%- comment -%}
  The FIRST slide only. Every other slide keeps loading="lazy".
  Never put loading="lazy" or fetchpriority="low" on the LCP image.
{%- endcomment -%}
<img
  src="{{ section.settings.image | image_url: width: 1500 }}"
  srcset="{{ section.settings.image | image_url: width:  600 }}  600w,
          {{ section.settings.image | image_url: width:  900 }}  900w,
          {{ section.settings.image | image_url: width: 1200 }} 1200w,
          {{ section.settings.image | image_url: width: 1800 }} 1800w"
  sizes="100vw"
  width="{{ section.settings.image.width }}"
  height="{{ section.settings.image.height }}"
  alt="{{ section.settings.image.alt | escape }}"
  loading="eager"
  fetchpriority="high"
  decoding="async"
  class="hero__image">
```

Notes that matter:

- `width` / `height` must be the **intrinsic** attributes (not CSS) so the browser reserves
  space. Your CLS is already 0.006 / 0 — keep it that way by never removing these.
- Style it with CSS (`object-fit: cover; width: 100%; height: 100%`), not by dropping the
  attributes.
- `fetchpriority="high"` is what moves it ahead of the app scripts in the request queue. This
  single attribute is frequently worth several seconds of Load Delay.

### If the hero is currently a CSS background

Replace the background with the `<img>` above plus a wrapper:

```css
.hero { position: relative; }
.hero__image {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}
.hero__content { position: relative; z-index: 1; }   /* text stays on top */
```

Visually identical, but now discoverable. **This preserves your current layout exactly** — it is
the same pixels, delivered by a tag the preload scanner can read.

### If the hero is a slideshow

Render slide 1 statically in the Liquid markup and let the carousel script take over on
initialisation. Do not let the script create slide 1. If the slideshow is an app rather than a
theme section, either replace it with a static hero on the homepage or accept that mobile 95 is
out of reach — a JS-built hero cannot produce a 2.2 s LCP on throttled mobile.

### If the hero is a video

Give it a `poster` image (which becomes the LCP element), `preload="none"`, and load the video
after the `load` event. An autoplaying hero video is incompatible with a 2.2 s mobile LCP.

---

## 3. Preload the hero from `<head>`

Belt and braces — this makes the request start before the body is even parsed. Use
`snippets/perf-lcp-preload.liquid`, rendered from `theme.liquid` **only on the template that
needs it**. Preloading an image that isn't used on the current page actively hurts (it competes
for bandwidth), which is why the snippet is template-scoped.

The `imagesrcset` / `imagesizes` values **must byte-for-byte match** the `srcset` / `sizes` on
the `<img>`, or the browser downloads the image twice.

---

## 4. Keep the hero small

- Mobile hero: aim **≤ 120 KB**. Desktop: ≤ 250 KB.
- Shopify's CDN serves WebP automatically via `image_url` when the browser accepts it — you do
  not need to convert files by hand, but you **do** need to request sensible widths. Requesting
  a 4000px master for a 400px viewport is the usual cause of `Improve image delivery`
  (194 KiB desktop / 150 KiB mobile in your report).
- Upload the master at ~2400px wide max. Anything larger is wasted storage and risks the CDN
  serving oversized derivatives if `srcset` is missing.

---

## 5. Remove render-blocking from `<head>` (mobile: 700 ms)

The mobile report flags **700 ms** of render-blocking requests (desktop only 40 ms — the
difference is CPU/network throttling amplifying the same chain).

Order of `<head>` matters. See `snippets/theme-liquid-head-order.md` for the exact layout. The
short version:

1. `<meta charset>`, viewport, title/meta description
2. Preconnects (**max 4** — you are over the limit; see below)
3. LCP preload
4. Critical CSS (inline) or the theme's main stylesheet
5. `{{ content_for_header }}` — Shopify requires this; do not move it above the preload
6. Everything else, deferred

**Every `<script>` in `<head>` must carry `defer`** (or `async` for genuinely independent
scripts). A single non-deferred app script in `<head>` is enough to hold FCP at 6 s on
throttled mobile.

### The preconnect warning

Your report says *"More than 6 preconnect/preload connections were found. These should be used
sparingly and only to the most important origins."* Each preconnect opens a TCP+TLS connection
that competes with the hero image for bandwidth on a slow connection — so an excess of
preconnects directly harms LCP.

Keep at most four, and only for origins needed in the first second:

```html
<link rel="preconnect" href="https://cdn.shopify.com" crossorigin>
```

Delete theme-added preconnects for anything loaded after first paint; use `dns-prefetch` for
those instead (cheap, no connection cost). App-added preconnects come from app embeds — they go
away when you defer or remove the app in Stage 2.

---

## 6. TTFB

If the LCP breakdown shows TTFB above ~600 ms on mobile, the cause is usually a heavy
`sections` render — typically an app block or a collection loop pulling many products with
metafields. Shopify's platform TTFB itself is normally 100–300 ms.

Check with:

```
curl -s -o /dev/null -w "ttfb=%{time_starttransfer}s total=%{time_total}s\n" https://badgemyauto.com/
```

If it is slow, look for `{% for product in collections.all.products %}` style loops on the
homepage and cap them with `limit:`.

---

## Verify this stage

Re-run PSI mobile. You should see LCP fall from 15.8 s into the 2–4 s range and FCP into the
1.5–2.5 s range. Expect roughly **mobile 45 → 70–80** from this stage alone, with TBT still
unfixed. If LCP has not moved, the LCP element is still being created by JavaScript — go back
to §1 and re-read the breakdown before starting Stage 2.
