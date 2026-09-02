# 1 · Conversion tracking (do this before anything else)

## The problem

The Conversion paths card shows **107 conversions with a conversion value of 107.00**, i.e. every
conversion is recorded at a flat $1.00. A second action ("Badge My Auto ×2") shows 6 conversions
worth 5.00. Two consequences:

- Smart Bidding cannot tell a $29 emblem from a $189 set, so it optimises for *count*, not revenue.
- A 22 % conversion rate on 480 clicks means the primary action is not a purchase. Google is
  bidding up traffic that performs the soft action, not traffic that buys.

## What to set up (Chrome → Goals → Conversions → Summary)

1. Open each conversion action. Identify the one that fires on the **order confirmation / thank-you
   page**. If none does, create one:
   - Category: **Purchase**
   - Value: **Use different values for each conversion** (dynamic). Default value = your average
     order value, only as a fallback.
   - Count: **Every**
   - Attribution: Data-driven (or Last click if DDA is not offered yet)
   - Click-through window: 30 days
2. Set that Purchase action to **Primary**. Set every other action (page view, add to cart, begin
   checkout, "preview design", form) to **Secondary** so they are observed but not bid on.
3. Pass the real order value:
   - **Shopify**: Sales channels → Google & YouTube → Settings → Conversion tracking → connect the
     Ads account. Shopify sends dynamic purchase value automatically.
   - **WooCommerce**: use "Google Listings & Ads" or the GTM4WP plugin; in GTM fire the Google Ads
     Conversion tag on the `purchase` event with `value` = `{{ecommerce.value}}` and
     `transaction_id` = `{{ecommerce.transaction_id}}`.
   - Any other stack: the conversion snippet on the thank-you page must include
     `'value': <order total>, 'currency': 'USD', 'transaction_id': '<order id>'`.
4. Turn on **Enhanced conversions** (Goals → Conversions → Settings → Enhanced conversions) so
   Safari/iOS purchases from that 85 % mobile traffic still get attributed.
5. Link **Google Analytics 4** if not already linked and import the GA4 `purchase` event as a
   backup, but keep only one purchase action Primary to avoid double counting.

## How to verify

- Google Tag Assistant (Chrome extension) → place a test order → the conversion tag should fire
  with the correct `value`.
- 24–48 h later, Conversions column in the campaign should show far fewer than 107/week, and
  Conv. value should show dollar amounts that match your store's revenue for the period.

## Why it matters for the rest of the plan

Every bidding recommendation in `05` assumes the account is optimising for **purchase value**.
Switching to Maximise conversion value / target ROAS before this is fixed would make things worse.
