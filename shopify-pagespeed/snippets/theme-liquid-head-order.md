# `theme.liquid` — correct `<head>` order

Order in `<head>` decides what the browser requests first. This layout puts the hero image ahead
of app JavaScript, which is the single structural change behind the mobile LCP fix.

```liquid
<!doctype html>
<html class="no-js" lang="{{ request.locale.iso_code }}">
<head>
  {%- comment -%} 1. Nothing may precede charset/viewport. {%- endcomment -%}
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  {%- comment -%} 2. Title + meta description (SEO 92 -> 100). {%- endcomment -%}
  <title>{{ page_title }}{% if current_page != 1 %} &ndash; Page {{ current_page }}{% endif %}{% unless page_title contains shop.name %} &ndash; {{ shop.name }}{% endunless %}</title>
  {%- render 'perf-meta-description' -%}
  <link rel="canonical" href="{{ canonical_url }}">

  {%- comment -%}
    3. Preconnect — MAXIMUM 4, and only origins needed in the first second.
    Your report warns: "More than 6 preconnect/preload connections were found."
    Each one costs a TCP+TLS handshake that competes with the hero image on slow connections.
  {%- endcomment -%}
  <link rel="preconnect" href="https://cdn.shopify.com" crossorigin>
  {%- comment -%} Use dns-prefetch (cheap, no connection) for anything needed AFTER first paint {%- endcomment -%}
  {%- comment -%} <link rel="dns-prefetch" href="https://your-app-cdn.example"> {%- endcomment -%}

  {%- comment -%} 4. LCP preload — BEFORE content_for_header so it wins the queue. {%- endcomment -%}
  {%- render 'perf-lcp-preload' -%}

  {%- comment -%} 5. Font preload — only fonts used above the fold. See 04-images-fonts-caching.md {%- endcomment -%}
  {%- comment -%} 6. CSS. Inline critical CSS here if you have it; otherwise the main stylesheet. {%- endcomment -%}
  {{ 'base.css' | asset_url | stylesheet_tag }}

  {%- comment -%}
    7. The script deferral engine. Must be a plain blocking script and must run BEFORE
       content_for_header, or the app scripts it targets will already have been injected.
       It is ~1 KB and executes in under 1 ms.
  {%- endcomment -%}
  <script src="{{ 'perf-delay-third-party.js' | asset_url }}"></script>

  {%- comment -%}
    8. Shopify's header. REQUIRED — never remove, never move above the preload.
  {%- endcomment -%}
  {{ content_for_header }}

  {%- comment -%} 9. Your own theme JS — ALWAYS defer. {%- endcomment -%}
  <script src="{{ 'global.js' | asset_url }}" defer></script>
</head>
```

## Rules

| Rule | Why |
|---|---|
| Every `<script>` in `<head>` has `defer` or `async` | One blocking script holds FCP at 6 s on throttled mobile |
| `{{ content_for_header }}` stays, and stays after the preload | Shopify requires it; moving it earlier lets app JS outrank the hero |
| At most 4 `preconnect` | Your report flags >6; each competes for bandwidth |
| Preload only what this template uses | An unused preload makes LCP worse, not better |
| `perf-delay-third-party.js` before `content_for_header` | It cannot intercept scripts already injected |

## Before you edit

Duplicate the live theme (**Online Store → Themes → ⋯ → Duplicate**) and make every change on
the copy. Preview, verify, then publish. Never edit a published theme directly.
