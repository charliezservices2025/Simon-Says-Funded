# Stage 2 — JavaScript and Total Blocking Time (desktop: +23 points)

**Target:** TBT 640 ms desktop → **≤ 129 ms**, 550 ms mobile → **≤ 150 ms**.

This is the only thing standing between desktop and a 95. It is also the riskiest stage,
because it touches tracking and app behaviour. Work through it in order and verify after each
step — do not apply §4 before you have done §1–§3.

---

## 1. Find out who is actually spending the time (do this first)

Do not optimise blind. Two sources, ten minutes:

**A. The PSI report you already have.** Expand these three sections and save them:
- **"3rd parties"** — groups blocking time by vendor. This is the ranked hit list.
- **"Reduce unused JavaScript"** (531 KiB) — names each file and its waste.
- **"Avoid long main-thread tasks"** (11 tasks) — names the URL owning each long task.

**B. Chrome DevTools, for ground truth.**
1. Incognito → DevTools → **Performance** → throttle to *4× CPU slowdown* → record a reload.
2. Open **Bottom-Up**, group by **Product** or by URL.
3. Anything over ~50 ms of self time is a target.

Write the list down before changing anything. On a Shopify store the usual occupants are:

| Source | Typical TBT cost | Removable? |
|---|---|---|
| Chat widget (Tidio, Gorgias, Crisp) | 100–300 ms | Yes — defer to interaction |
| Reviews (Judge.me, Loox, Okendo) | 80–250 ms | Yes on homepage; needed on product pages |
| Popups / email capture (Klaviyo, Privy) | 60–200 ms | Yes — defer |
| Upsell / bundle apps | 80–200 ms | Often homepage-unnecessary |
| Meta / TikTok / Google pixels | 50–150 ms each | Defer, or move to server-side |
| Shopify Web Pixels Manager | 50–150 ms | Reduce pixel count only |
| Shopify `trekkie` / analytics | 40–120 ms | **No — leave alone** |
| Theme JS (sliders, jQuery) | 50–200 ms | Yes — trim |

For a **TikTok Shop** store specifically, expect TikTok Pixel + Meta Pixel + GA4 to be present
simultaneously. Three pixel libraries on first paint is 150–400 ms of TBT on its own.

---

## 2. Delete before you defer

The cheapest millisecond is one you never load. In order:

1. **Uninstall apps you no longer use.** Shopify Admin → Apps.
2. **Clean up the leftovers.** This is the step everyone skips, and it is the most common
   source of mystery JS. Uninstalled apps routinely leave code behind in two places:
   - **`theme.liquid` and section files** — search the theme for the vendor's name and for
     stray `<script src=` tags. Delete orphaned blocks.
   - **The Script Tags API** — invisible in the theme editor. List them with:
     ```
     curl -s "https://badge-my-auto-tiktok-shop-6c6f8.myshopify.com/admin/api/2026-01/script_tags.json" \
       -H "X-Shopify-Access-Token: $SHOPIFY_ADMIN_TOKEN"
     ```
     Delete any belonging to apps you have removed:
     ```
     curl -X DELETE ".../admin/api/2026-01/script_tags/<ID>.json" -H "X-Shopify-Access-Token: $TOKEN"
     ```
3. **Turn off app embeds per-template.** Theme editor → **App embeds** (bottom of the left
   panel). Many apps expose a toggle; several support template targeting. A reviews widget does
   not need to load on the homepage.

Re-measure after this step. On stores with app churn, deletion alone often recovers 150–300 ms
of TBT with zero risk.

---

## 3. Trim your own theme's JavaScript

- Confirm **every** `<script>` in the theme carries `defer`. A blocking theme script costs FCP
  and TBT simultaneously.
- **Drop jQuery** if only one or two snippets use it — it is ~30 KB parsed plus execution, and
  the usual uses (`$('.x').on('click')`) are one-liners in modern DOM APIs.
- The `Legacy JavaScript` audit (12–13 KiB) means ES5 bundles and polyfills are being shipped to
  browsers that don't need them. Worth ~0 score points on its own — do it only if your theme
  makes it easy.
- **Forced reflow** (flagged on both reports) is a script reading layout (`offsetHeight`,
  `getBoundingClientRect`) and writing style in the same loop. Find it via DevTools →
  Performance → look for purple "Layout" bars attributed to a script. Common in sticky headers
  and carousels. Fix by batching reads before writes, or by using `ResizeObserver` /
  `IntersectionObserver` instead of measuring on scroll.
- **42 user timing marks** indicate an app instrumenting heavily. Harmless to the score
  directly, but a reliable fingerprint of a heavy app — check which vendor emits them.

---

## 4. Defer the remaining third-party scripts until interaction

After §2–§3 you will still have pixels and widgets that must exist but do not need to run during
the first paint. Deferring them until the user's first interaction is what collapses TBT,
because Lighthouse only measures blocking time up to the point the page becomes interactive.

`snippets/perf-delay-third-party.js` implements this. Read its header before deploying — it
carries real trade-offs, summarised here:

**How it works.** It patches `appendChild` / `insertBefore` to hold `<script>` elements whose
`src` matches an explicit blocklist, then releases them on first user interaction
(pointer/touch/key/scroll) or after a timeout following `load`.

**What it will and won't catch.** It intercepts *dynamically injected* scripts — which is how
Shopify app embeds and pixels load, so coverage is good. It does **not** catch scripts written
directly into the HTML source by the parser. Those must be handled in §2/§3 instead.

**The trade-offs you are accepting — read these properly:**

- **Analytics attribution.** A bounced visitor who never interacts may not be recorded. For most
  pixels the interaction release fires well within the session, but the timeout fallback exists
  precisely so that non-interacting sessions still report. If marketing attribution is
  business-critical, **exclude your pixels from the blocklist** and accept the TBT cost — a
  correct pixel is worth more than 10 Lighthouse points. This is a business decision, not a
  technical one.
- **Consent tooling.** If you run a GDPR/CCPA consent banner, never defer it — deferring consent
  logic can leave you loading trackers before consent is recorded. Keep it out of the blocklist.
- The script excludes `/checkout`, `/cart`, and account pages, and disables itself in the theme
  editor (`Shopify.designMode`). Do not remove those guards.
- Use an **explicit allowlist-by-omission**: only ever add specific vendors you have tested.
  Never write a catch-all pattern.

**Deploy it staged:** add one vendor to the blocklist, verify that vendor still works (widget
opens, pixel fires in the vendor's own dashboard/debugger), then add the next. A batch rollout
makes it impossible to tell which vendor broke.

---

## 5. Reduce pixel count at the source

Better than deferring a pixel is not loading three of them. Options, in order of preference:

1. **Shopify's server-side Customer Events / Conversions API** for Meta and TikTok, instead of
   browser pixels. Zero main-thread cost, and more resilient to ad blockers and ITP.
2. **Consolidate custom pixels** in Admin → Settings → Customer events. Each custom pixel is a
   separate sandbox with its own cost.
3. If you must keep browser pixels, they are the best candidates for §4's blocklist.

---

## Expected result

| | Before | After Stage 2 |
|---|---|---|
| Desktop TBT | 640 ms | 100–180 ms |
| Mobile TBT | 550 ms | 100–200 ms |
| Desktop score | 72 | **93–97** |
| Mobile score | ~75 (post-Stage 1) | **88–96** |

Desktop 95 lands here or does not land at all — if desktop TBT is still above ~200 ms after
this stage, go back to §1 and re-read the Bottom-Up profile. Something is still executing during
load, and it will be named in that panel.

Whether mobile clears 95 depends on how much of §2 you were willing to do. The gap between
"good" and "95" on mobile is roughly one chat widget.
