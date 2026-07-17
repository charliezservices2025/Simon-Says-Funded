# Snippets index — where each file goes

All snippets are **reversible** (deactivate the WPCode snippet / remove the block = full revert) and contain `VERIFY` markers where a value must be confirmed against the live site. Apply them in the order given by [`../01-IMPLEMENTATION-CHECKLIST.md`](../01-IMPLEMENTATION-CHECKLIST.md), not top-to-bottom here.

| File | Type | WPCode insert location | What it does | Must-verify |
|---|---|---|---|---|
| `wpcode-01-remove-emoji-embed-migrate.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Removes emoji script, wp-embed, jQuery Migrate | QA menu/slider/form with console open (Migrate) |
| `wpcode-02-diagnostic-list-js-handles.php` | PHP | Auto Insert · Run Everywhere · **TEMP, then delete** | Prints real JS handle names for #03 / #08 | View source logged-out |
| `wpcode-03-conditional-js-trim.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Dequeues Shared Counts / Mail Mint / Smart Slider off routes that don't use them | Replace `VERIFY` handles from #02 |
| `wpcode-04-optional-drop-wp-polyfill.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Optional: drop WP core polyfills | Test block UIs |
| `wpcode-05-lcp-hero-preload.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Preload the LCP hero (mainly for a **CSS-background** hero) | Real hero URL; `<img>` vs CSS-bg form; only ONE preload |
| `wpcode-06-html-lang-fallback.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Fallback `<html lang>` (do the native Settings fix first) | — |
| `wpcode-07-accessibility-fixes.php` | PHP | Auto Insert · **Frontend Only** | main landmark + accessible names + duplicate-link fix | Add `tdc-a11y` to FlyingPress Delay-JS excludes; QA keyboard menu |
| `wpcode-08-scope-smart-slider.php` | PHP | Auto Insert · Run Everywhere (Frontend) | Optional belt-and-suspenders slider scoping | Real handles from #02; test subpage + homepage |
| `css-01-cls-space-reservation.css` | CSS | CSS Snippet · Site Wide Header (or Divi Custom CSS) | Reserves height for slider / reviews / fonts to kill CLS | Real selectors + **measured** heights |
| `css-02-accessibility-contrast.css` | CSS | Additional CSS (Customizer) or CSS Snippet · Header | Raises low-contrast text to WCAG AA | Real failing nodes from PSI; re-check dark sections |
| `html-01-static-whatsapp-button.html` | HTML | HTML Snippet · Site Wide Footer | Accessible zero-JS WhatsApp button (replaces Elfsight) | Real WhatsApp number; publish before deactivating Elfsight |
| `htaccess-01-static-caching.txt` | Apache | site-root `.htaccess` (above `# BEGIN WordPress`) | Long browser cache + Brotli/gzip | Apache only; backup first; verify `Vary: Accept` still present |

## Golden rules

- **Back up first** (UpdraftPlus: files + database) before any plugin change or `.htaccess` edit.
- **One change at a time**, then **FlyingPress → Purge Everything**, reload **incognito**, and re-test.
- **Never hand-defer/delay jQuery** in code — let FlyingPress own JS timing with its exclude list.
- Re-run PageSpeed Insights **twice** after a purge and read the **second** run (the first is cold).
- Field Core Web Vitals (the mobile CLS "pass") updates on CrUX's **28-day rolling window** — lab scores move immediately, the field badge follows within ~1–2 weeks of real traffic.
