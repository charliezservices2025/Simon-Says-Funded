# Stage 4 — Accessibility 94, Best Practices 73, SEO 92

These do not affect the performance score, but they are the cheapest wins in the whole report
and two of them are real user-facing defects. Roughly 30 minutes total.

---

## SEO 92 → 100

**One failing audit: `Document does not have a meta description`.**

Two-part fix:

1. Install `snippets/perf-meta-description.liquid` (guarantees the tag always renders).
2. Write real descriptions — the snippet is only a fallback:
   - **Homepage:** Online Store → Preferences → *Meta description*
   - **Products / collections / pages:** each item's *Search engine listing* → Edit

140–160 characters, written to earn a click. The homepage one matters most; it is the page being
audited here.

---

## Accessibility 94 → 100

### `Background and foreground colors do not have a sufficient contrast ratio`

WCAG AA requires **4.5:1** for normal text, **3:1** for large text (≥18.66px bold or ≥24px).

The hero on badgemyauto.com is light text over a dark photographic background — the classic
source of this failure, because contrast varies across the image.

1. Expand the audit in the PSI report; it names each failing element and its current ratio.
2. Check candidate colors with the WebAIM contrast checker.
3. Fix without changing your design:

```css
/* Text over a photo: a scrim raises contrast without altering the palette or layout. */
.hero__content {
  position: relative;
}
.hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,.15) 0%, rgba(0,0,0,.55) 100%);
  pointer-events: none;
}
.hero__content { z-index: 1; }

/* Or, for a specific failing element, darken/lighten only that token. */
```

A scrim is the standard fix here: it keeps the photograph and the type exactly as designed while
guaranteeing the ratio. Prefer it over changing brand colors.

Also common on storefronts: muted secondary text (`#767676` on white is 4.54:1 — passing;
`#999` on white is 2.85:1 — failing) and low-contrast button labels.

### `<frame> or <iframe> elements do not have a title`

Screen reader users hear "iframe" with no indication of what it contains. Almost always an
embedded video or an app widget.

**Best fix** — if the iframe is in your theme, add a real title:

```liquid
<iframe src="..." title="Product demo video" loading="lazy"></iframe>
```

**Fallback** — if the iframe is injected by an app you don't control, use
`snippets/perf-a11y-fixes.js`. Note that it labels iframes generically; a specific title is
better whenever you own the markup.

### The other listed items

`Additional items to manually check (10)` and the audio/video caption entry are **manual-check**
items — Lighthouse does not score them. They do not cost you points. Worth reviewing for real
users, but they are not blocking 100.

---

## Best Practices 73 → ~100

Three failing audits, and they share one cause.

### `Uses deprecated APIs — 3 warnings found`

Expand the audit; it names the API and the calling script. On Shopify storefronts these are
nearly always third-party — typical offenders are `unload` event listeners, `StorageType.persistent`,
and third-party cookie access.

If the caller is an app: report it to the vendor, or remove the app. You cannot patch their
bundle. If the caller is your theme, fix it directly.

### `Browser errors were logged to the console`

Every console error is a real bug — usually a `404` on a missing asset, or a broken app script.
Reproduce in an incognito window with DevTools open, then work through them. Two frequent causes
on Shopify: a deleted asset still referenced by a section, and an app whose script 404s after
uninstall (see `03-javascript-and-tbt.md` §2 on leftover Script Tags).

### `Issues were logged in the Chrome DevTools Issues panel`

Open DevTools → **Issues** tab for the itemised list. Typically third-party cookie deprecation
warnings and mixed content.

### The common cause

All three categories are dominated by third-party app code. **Do Stage 2 first** — removing and
deferring apps usually lifts Best Practices from 73 into the 90s with no separate work. Come back
here afterwards and fix whatever remains.

---

## Expected result

| Category | Before | After |
|---|---|---|
| SEO | 92 | 100 |
| Accessibility | 94 | 100 |
| Best Practices | 73 | 92–100 (depends on what Stage 2 removed) |
