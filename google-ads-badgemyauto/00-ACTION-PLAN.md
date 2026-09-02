# Badge My Auto — Google Ads fix plan

Campaign: **Badge My Auto** (Search, campaign ID 24159074291). Window reviewed: Aug 26 – Sep 1, 2026.
Prepared as a paste-ready package: every headline, description, callout and snippet below has been
length-checked against Google's limits. Do the steps in order; each one maps to a screen in the
Google Ads UI (Chrome, `ads.google.com`).

## What the account is telling us

| Metric (7 days) | Value | Read |
|---|---|---|
| Clicks / Impressions | 480 / 5,165 | CTR 9.29 % is strong for Search |
| Avg. CPC / Cost | $1.80 / $863 | ~$123/day spent of a $180/day budget, so **not budget-limited** |
| Conversions / Conv. value | 107 / 107.00 | **Every conversion is worth exactly $1.00** → revenue is not being passed. Smart Bidding is flying blind |
| Conv. rate | 22 % | Far too high for a purchase. The primary conversion is almost certainly a soft action (page view, add-to-cart, "preview") |
| Devices | 85.6 % of cost on mobile | The landing page and the emblem configurator must be fast on phones |
| Networks | Search partners = 29 % of clicks, 11.8 % of cost, $0.73 CPC | Cheap traffic; keep only if its conversion rate holds up (see step 7) |
| Structure | 1 ad group, 1 RSA, ad strength "Good" | One ad group for 5 different keyword themes is the root cause of the "Good" strength and of wasted spend |
| Optimization score | 95.1 % | The two missing +2.5 % items are **Price assets** and **Structured snippets** (both fixed in step 4) |

Keyword spend, 7 days:

| Keyword | Cost | Clicks | CTR |
|---|---|---|---|
| custom car emblems for sale | $438.23 | 192 | 14.92 % |
| custom metal decals for cars | $113.87 | 62 | 6.37 % |
| aftermarket truck emblems | $80.35 | 87 | 17.90 % |
| bespoke car badges | $60.67 | 29 | 9.60 % |
| custom auto badges emblems | $39.58 | 18 | 4.18 % |

Half of all spend sits on one broad-match keyword. The search-terms panel shows it matching to
"car decals", "car emblems", "custom license plate frame", "custom badges" — several of those are
informational or off-catalogue.

Top bidding signals Google reported: **mobile + weekdays 6–10 AM** and **Virginia / Florida** convert
better; **weekdays 12–6 PM, weekends 10 AM–6 PM, Georgia and tablets** convert worse.

## Priority order (highest impact first)

1. **Fix conversion tracking values** — `01-conversion-tracking.md`. Without real purchase values nothing else compounds.
2. **Rebuild the responsive search ad to "Excellent"** — `02-rsa-copy.md`. Adds the "popular keywords in headlines" check that is currently the only unticked item.
3. **Split the single ad group into five themed ad groups** — `03-keywords-and-negatives.md`. Each theme gets its own RSA so Google can insert the matching keyword.
4. **Add the missing assets** (price, structured snippets, better callouts, sitelinks, business name + logo) — `04-assets.md`. Clears both open recommendations and lifts the optimization score to 100 %.
5. **Bidding, schedule, location, network** — `05-bidding-schedule-network.md`.
6. **Landing page** — `06-landing-page.md`. Display path currently reads `badgemyauto.com/shop/about`; the ad should land on the configurator, not an About page.

## 30-minute checklist (do this first in Chrome)

- [ ] Goals → Conversions → Summary: confirm which action is *Primary*. Make **Purchase** the only primary, everything else Secondary. (`01`)
- [ ] Open the ad (Ads → the enabled RSA → Edit). Replace the headlines and descriptions with the set in `02`. Save. Ad strength should read **Excellent** with all four checks ticked.
- [ ] Assets → + Structured snippet → header "Types" → paste the six values. (`04`)
- [ ] Assets → + Price → paste the three price rows once you fill in the real prices. (`04`)
- [ ] Assets → Callouts: delete the four near-duplicate "OEM … fits in secs" callouts and paste the eight in `04`.
- [ ] Assets → Business name & Business logo: the preview still says "Business Name". Set it to **Badge My Auto** and upload the square logo. (`04`)
- [ ] Ad → Display path: change `shop / about` to `Custom-Emblems / Design-Online`. (`06`)
- [ ] Keywords → Negative keywords → paste the campaign-level negative list. (`03`)
- [ ] Settings → Locations → Location options → **Presence: People in or regularly in your targeted locations**. (`05`)

Then, within the week, do the ad-group split in `03` and the bidding change in `05`.
