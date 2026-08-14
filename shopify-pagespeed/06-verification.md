# Verification — how to measure without fooling yourself

A single PageSpeed run on mobile has enough variance to move the score by ±5 points. If you
measure once after each change, you will draw wrong conclusions and undo changes that worked.

---

## Protocol

**Always:**
1. Test the **published** URL, or a preview URL with `?preview_theme_id=...`. Never test from
   the theme editor — it loads extra tooling and reports garbage.
2. Run each configuration **3 times** and take the **median**, not the best.
3. Test one change at a time, in the stage order (1 → 2 → 3 → 4).
4. Compare **like with like** — mobile against mobile.

**Never:**
- Compare a logged-in admin session against a logged-out one. Shopify injects the admin bar for
  staff, which changes the result.
- Trust a single mobile run that looks great. Re-run it.
- Chase the last point on a metric that is already scoring 95+ — check the weights first.

---

## Command-line runs

PageSpeed Insights API (Google fetches the site, so it works from anywhere):

```bash
# Get a free key at https://developers.google.com/speed/docs/insights/v5/get-started
KEY=your_api_key
URL=https://badgemyauto.com/

for i in 1 2 3; do
  curl -s "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${URL}&strategy=mobile&key=${KEY}" \
  | python3 -c '
import sys, json
d = json.load(sys.stdin)["lighthouseResult"]
a = d["audits"]
print("score", round(d["categories"]["performance"]["score"]*100),
      "| FCP", a["first-contentful-paint"]["displayValue"],
      "| LCP", a["largest-contentful-paint"]["displayValue"],
      "| TBT", a["total-blocking-time"]["displayValue"],
      "| CLS", a["cumulative-layout-shift"]["displayValue"],
      "| SI",  a["speed-index"]["displayValue"])'
done
```

Without a key the shared anonymous quota is frequently exhausted (this is what blocked the live
re-measurement during this audit) — get a key, it takes two minutes and is free.

Local Lighthouse, which is faster to iterate against:

```bash
npx lighthouse https://badgemyauto.com/ --preset=desktop --output=json --output-path=desktop.json
npx lighthouse https://badgemyauto.com/ --form-factor=mobile --output=json --output-path=mobile.json
```

Local runs use your machine's CPU, so absolute numbers differ from PSI. Use them for **relative**
comparisons while iterating, and confirm final numbers with PSI.

---

## Budgeting with the score model

`tools/lighthouse-score-model.py` reproduces Lighthouse's scoring curves. It returns exactly 72
and 45 for the current metrics, so you can use it to answer "if I get TBT to X, what do I score?"
before doing the work.

```bash
python3 tools/lighthouse-score-model.py
```

Edit the `mob` / `desk` dicts at the bottom to model a scenario. This is how the targets in
`00-DIAGNOSIS.md` were derived — use it to decide whether a change is worth doing rather than
guessing.

---

## Track these, not the score

The score is a lagging indicator. Watch the metrics:

| Metric | Now (mobile) | Target | Now (desktop) | Target |
|---|---|---|---|---|
| FCP | 6.3 s | ≤ 1.5 s | 0.5 s | keep |
| Speed Index | 7.2 s | ≤ 3.0 s | 1.0 s | keep |
| LCP | 15.8 s | ≤ 2.2 s | 1.3 s | ≤ 0.9 s |
| TBT | 550 ms | ≤ 150 ms | 640 ms | **≤ 129 ms** |
| CLS | 0 | keep ≤ 0.1 | 0.006 | keep |

**Guard against regressions.** CLS is currently near-perfect on both. It is the metric most
easily broken by the image work in Stages 1 and 3 — every `<img>` needs `width` and `height`
attributes. If CLS rises above ~0.1 you lose up to 25 points and will have gone backwards.

---

## Lab vs. field

PSI shows two things. The top panel — *"Discover what your real users are experiencing"* — is
**field data** from real Chrome users (CrUX). Your report shows **"No Data"** there, meaning the
site has insufficient traffic to qualify.

That matters for expectations:

- Everything in this package targets the **lab** score, which is what your report measures.
- Google's ranking systems use **field** data (Core Web Vitals), not the lab score. Once traffic
  qualifies the site for CrUX, field data will lag the lab improvements by up to 28 days, since
  CrUX reports a rolling 28-day window.
- Field data is usually *kinder* than the lab score, because real users have faster devices and
  warm caches than Lighthouse's throttled Moto G Power simulation.

So: optimise for the lab number you asked about, but judge real-world SEO impact on Core Web
Vitals once it appears.

---

## Rollback

Every change in this package is reversible:

| Change | Rollback |
|---|---|
| Theme edits | Work on a **duplicated** theme; publish only after verifying. Revert by re-publishing the original. |
| `perf-delay-third-party.js` | Remove the `<script>` tag from `theme.liquid`, or append `?nodelay=1` to a URL to bypass it for one page load. |
| App removals | Reinstall. **Export settings first** — some apps lose configuration on uninstall. |
| Script Tag deletions | Not reversible via the API; note the `src` and `event` values before deleting. |

Keep the original theme available and unpublished until the new one has run clean for a few days.
