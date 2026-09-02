# 7 · "Upgrade your measurement with Google tag gateway" — why it fails and what to do instead

Google tag: AW-18362720235 / Google tag ID **GT-NS4JDHV7**. Cloudflare account in use: `928cdf1e…`.
Site host: **Lovable** (custom domain pointing at Lovable's edge IP `185.158.133.1`).

## Root cause

Google tag gateway on Cloudflare works by having **your** Cloudflare zone proxy the site
(orange cloud) and rewrite tag requests to a first-party path. Lovable's custom-domain setup
requires the A records for `@` and `www` to be **DNS only** (grey cloud) pointing at
`185.158.133.1`; Lovable terminates TLS and serves the site from its own infrastructure.
With the proxy off, Cloudflare has no domain "running the Google tag and Cloudflare", so the
Google wizard's "Sign into Cloudflare" step cannot complete. Turning the proxy on breaks the
Lovable domain (verification fails, SSL errors). The recommendation is therefore **not
applicable** to this site.

## What to do

1. In Google Ads → Recommendations, open the card's ⋮ menu → **Dismiss** → reason
   "Not relevant". It carries no optimization-score uplift, so dismissing it costs nothing.
2. Get the same benefit (recovering conversions dropped by iOS/Safari tracking prevention)
   through **Enhanced conversions** and a correct purchase tag, which the Lovable site needs
   anyway for step `01`.

## Purchase tag for the Lovable site

Paste this prompt into the Lovable project chat. Replace the two placeholders first.

```
Add Google Ads conversion tracking to this site.

1. In index.html <head>, add the Google tag:
   <script async src="https://www.googletagmanager.com/gtag/js?id=GT-NS4JDHV7"></script>
   <script>
     window.dataLayer = window.dataLayer || [];
     function gtag(){dataLayer.push(arguments);}
     gtag('js', new Date());
     gtag('config', 'GT-NS4JDHV7', { allow_enhanced_conversions: true });
   </script>

2. On the order-confirmation / checkout-success page, after the order is confirmed, fire:
   gtag('set', 'user_data', { email: ORDER_EMAIL, phone_number: ORDER_PHONE_E164 });
   gtag('event', 'conversion', {
     send_to: 'AW-18362720235/<CONVERSION_LABEL>',
     value: ORDER_TOTAL_NUMBER,
     currency: 'USD',
     transaction_id: ORDER_ID
   });
   Use the real order total (number, not string), the order id, and the buyer's email
   from the completed order. Fire it exactly once per order (guard with the order id in
   sessionStorage so a refresh does not re-fire).

3. Do not fire the conversion event on add-to-cart, preview, or checkout start.
```

`<CONVERSION_LABEL>` comes from Google Ads → Goals → Conversions → the Purchase action →
Tag setup → "Use Google tag" → the `send_to` value.

If checkout is Stripe Checkout, the success URL must return to a page on `badgemyauto.com`
(e.g. `/order-complete?session_id={CHECKOUT_SESSION_ID}`) and that page must load the order
total and email before firing the event.

## Verify

- Chrome → Tag Assistant → place a test order → the conversion tag shows `value` and
  `transaction_id`.
- Google Ads → Goals → Conversions → the Purchase action → **Enhanced conversions** shows
  "Recording" within 48 h.
- Campaign Conv. value stops reading `1.00 × conversions`.

## If you later move off Lovable

On any host where your own Cloudflare zone can proxy the site, enable the gateway from
Cloudflare: Websites → domain → **Google tag gateway** → toggle "Turn on and configure Google
tag gateway" → Google tag ID `GT-NS4JDHV7` → Measurement path e.g. `/metrics` → Save. Needs
Super Administrator / Administrator / Zaraz Admin on the Cloudflare account.
