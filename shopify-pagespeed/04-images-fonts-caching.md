# Stage 3 — Images, fonts and payload

Supports FCP and Speed Index on mobile (10 points each, currently earning 0.3 and 3.0).
**Risk: low.** No layout changes.

---

## 1. Images — `Improve image delivery` (194 KiB desktop / 150 KiB mobile)

Page weight is **2,522 KiB desktop / 2,341 KiB mobile** (`Avoid enormous network payloads`).
Target under 1,000 KiB.

### Always use `image_url` with an explicit width

Shopify's CDN generates resized derivatives and negotiates WebP/AVIF automatically — but only
for the widths you ask for. Serving a 2400px master into a 400px phone viewport is the usual
cause of this audit.

```liquid
{%- comment -%} Wrong: full-size master, no responsive candidates {%- endcomment -%}
<img src="{{ product.featured_image | image_url }}">

{%- comment -%} Right {%- endcomment -%}
<img
  src="{{ product.featured_image | image_url: width: 800 }}"
  srcset="{{ product.featured_image | image_url: width: 400 }} 400w,
          {{ product.featured_image | image_url: width: 600 }} 600w,
          {{ product.featured_image | image_url: width: 800 }} 800w"
  sizes="(min-width: 990px) 25vw, 50vw"
  width="{{ product.featured_image.width }}"
  height="{{ product.featured_image.height }}"
  alt="{{ product.featured_image.alt | escape }}"
  loading="lazy"
  decoding="async">
```

### Rules

| Rule | Reason |
|---|---|
| `loading="lazy"` on everything **below** the fold | Frees bandwidth for the LCP image |
| `loading="eager"` + `fetchpriority="high"` on the hero **only** | See `02-lcp-and-render-blocking.md` |
| Always set `width` + `height` attributes | Keeps CLS at its current 0.006 / 0 |
| `sizes` must reflect the real rendered width | A wrong `sizes` makes `srcset` useless |
| Upload masters at ≤ 2400px | Larger is wasted; smaller risks upscaling |

### Don't bother converting to WebP by hand

Shopify's CDN already serves WebP to browsers that accept it. Manual conversion adds work and
gains nothing. Focus on requesting correct **widths** instead.

---

## 2. Fonts — `Font display` (10 ms, mobile)

Small in points, near-zero in effort.

### Use Shopify's font picker where you can

`font_face` accepts a display setting directly:

```liquid
{{ settings.type_body_font | font_face: font_display: 'swap' }}
{{ settings.type_header_font | font_face: font_display: 'swap' }}
```

`font-display: swap` renders text immediately in a fallback and swaps when the webfont arrives,
instead of leaving text invisible. It converts an FCP delay into a font swap.

### If you self-host or use a third-party font

```css
@font-face {
  font-family: 'YourFont';
  src: url('{{ "yourfont.woff2" | asset_url }}') format('woff2');
  font-display: swap;   /* never leave this out */
  font-weight: 400;
  font-style: normal;
}
```

Preload **only** fonts used above the fold — usually one weight of the heading font:

```html
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="{{ 'yourfont.woff2' | asset_url }}">
```

The `crossorigin` attribute is mandatory on font preloads. Without it the browser fetches the
font twice.

### Rules

- Preload at most **one or two** font files. You are already over the connection budget.
- Ship `woff2` only. `woff`/`ttf` fallbacks are dead weight for any browser from the last decade.
- Every additional weight/style is another file. Two weights covers most storefronts.
- Prefer system fonts for body text if brand allows — that is 0 KB and 0 ms.

---

## 3. `Use efficient cache lifetimes` — mostly ignore this one

Reported as 745 KiB desktop / 145 KiB mobile. Before spending time here, understand what it is:

- **Shopify CDN assets already carry a 1-year immutable TTL.** You cannot improve them, and you
  do not control the cache headers on `cdn.shopify.com`.
- The flagged bytes are therefore almost entirely **third-party scripts** (pixels, chat, review
  widgets) which deliberately ship short TTLs so vendors can push updates.

You cannot fix another origin's cache headers. The only real lever is loading fewer third-party
scripts — which is Stage 2. **Treat this audit as a symptom, not a task.** It contributes no
score points directly.

---

## 4. `Legacy JavaScript` (12–13 KiB) and `Minify JavaScript` (14 KiB)

Both are small enough to be worth ~0 points. Address them only if trivial:

- **Legacy JS** is ES5 transpilation and polyfills shipped to browsers that don't need them. If
  it comes from an app, you cannot fix it. If it comes from your theme's build, target modern
  browsers.
- **Minify JS** on a Shopify store almost always points at app-owned files. Your own theme
  assets are served minified if you minify them at build time.

Do not spend an afternoon here. The points are in Stages 1 and 2.
