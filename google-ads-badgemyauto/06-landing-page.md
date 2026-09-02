# 6 · Landing page

The ad's display path currently reads `www.badgemyauto.com/shop/about`. Whatever the real final
URL is, an About page loses the click: 85 % of traffic is on a phone and a person searching
"custom car emblems for sale" wants to start designing.

## Final URL

- Send every ad group to the **emblem configurator** or the matching collection page:
  - Custom Car Emblems / Custom Car Badges → configurator
  - Grill Badges → grill badge product or collection
  - Truck Emblems → truck collection
  - Metal Decals → decal product
- Add `?utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_content={adgroupid}&utm_term={keyword}`
  as the campaign-level **Final URL suffix** (Settings → Additional settings → Campaign URL
  options) so GA4 can split revenue by ad group.

## Page requirements that move CPA

| Requirement | Why |
|---|---|
| Mobile LCP < 2.5 s, CLS < 0.1 | 85 % of cost is mobile; Google's Quality Score uses landing-page experience |
| Configurator visible above the fold on a 390 px-wide phone | The "preview" USP in the ad must be true in the first second |
| Price, finish options and "fits in seconds" repeated on the page | Message match with headlines 9–12 |
| Real customer photos (gallery) | Supports the "Customer Gallery" sitelink and the trust callouts |
| Shipping time and returns policy in one line near the buy button | Only make "Free Shipping" a callout if this line says it |
| Thank-you page URL is stable and unique | Conversion tracking in `01` depends on it |

If the store is WordPress/WooCommerce, the `pagespeed-audit/` folder in this repo already contains
a PageSpeed remediation checklist that can be reused as-is for the emblem site.

## Ad-to-page message match test

Open the ad preview and the landing page side by side on a phone. Every one of these should be
findable without scrolling: "custom", the product name (emblem / badge), "preview", a price, a
"Start designing" button. If any is missing, fix the page before raising budget.
