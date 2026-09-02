# 7 · Google tag gateway (Cloudflare) — when "Sign into Cloudflare" will not connect

Google tag: AW-18362720235 / Google tag ID **GT-NS4JDHV7**. Cloudflare account in use: `928cdf1e…`.

## Diagnosis

`badgemyauto.com` and `www.badgemyauto.com` resolve to an IP inside Cloudflare's network (AS13335),
so the zone is already on Cloudflare and proxied. The failure is in the Google → Cloudflare
OAuth handoff, not in DNS. Google's own help page says: if the error persists, **set it up from the
Cloudflare interface instead**. That is the professional fix; the Google-side wizard is optional.

## Fix A · Set it up from Cloudflare (5 minutes)

Requirements: the Cloudflare login must be **Super Administrator, Administrator or Zaraz Admin**
on the account that holds the `badgemyauto.com` zone (or Domain Administrator if roles are
domain-scoped). The feature is free on every plan.

1. `dash.cloudflare.com` → **Websites** → confirm `badgemyauto.com` is listed and **Active**.
   If it is not listed, the zone lives in a different Cloudflare account (often a web developer's).
   Get added to *that* account as Administrator; do not create a second account or move DNS.
2. **DNS → Records**: `badgemyauto.com` (A/CNAME) and `www` must be **Proxied** (orange cloud).
3. Left nav → **Google tag gateway** (Cloudflare's "Google Tag Gateway" page).
4. Toggle **Turn on and configure Google tag gateway**.
5. **Google tag ID**: `GT-NS4JDHV7`
6. **Measurement path**: an unused path on the site, e.g. `/metrics`. Do not use `/gtm`, `/gtag`
   or any real store route.
7. **Save**. Configuration applies to every hostname in the zone.
8. Back in Google Ads → Tools → Data manager → Google tag → **Google tag gateway**: the domain
   should now show as connected/active. If it shows "Paused" or "No domains", open the Google tag
   → Admin → Google tag gateway and confirm the domain there.

## Fix B · If you prefer the Google wizard

The wizard fails most often for one of these. Work down the list:

| Cause | Fix |
|---|---|
| Multiple Google logins (`authuser=6` in the URL) | Open Chrome **Incognito**, sign in to *only* the Google Ads account, then the Cloudflare account, and re-run the wizard |
| Popup / third-party cookies blocked | `chrome://settings/content/popups` → allow `ads.google.com`; allow third-party cookies for `[*.]google.com` and `dash.cloudflare.com`; pause uBlock/Privacy Badger/Brave shields on both |
| Cloudflare login lacks role | Cloudflare → Manage account → Members: must be Super Administrator / Administrator / Zaraz Admin |
| Zone is in another Cloudflare account | See Fix A step 1 |
| Cloudflare account already bound to a different Google/GTM account | Binding is permanent; use Fix A |

## Verify (2 minutes)

- Open `badgemyauto.com` → Chrome DevTools → Network → filter `metrics` (your path). The
  `gtag/js?id=GT-NS4JDHV7` request should load from `badgemyauto.com/metrics/…`, not from
  `googletagmanager.com`.
- Tag Assistant: the Google Ads tag still fires; conversion pings go through the first-party path.
- The "Upgrade your measurement with Google tag gateway" recommendation clears within 24–48 h.

## Do not

- Do not change nameservers, turn off the proxy, or create a new Cloudflare account.
- Do not add a Cache Rule, Page Rule, WAF rule or redirect that touches the measurement path.
- Do not put a Cloudflare Access policy on that path.
