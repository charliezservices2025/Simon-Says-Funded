# 5 · Bidding, schedule, location, network

## Bidding

Today: Smart Bidding on $1.00-value conversions (see `01`).

1. **Until purchase values are flowing**: leave the current strategy alone. Changing bid
   strategy now resets learning on bad data.
2. **Once the Purchase action has ≥ 30 valued conversions in 30 days**: switch to
   **Maximise conversion value** with no target for 2–3 weeks, then set a **Target ROAS** at
   roughly 80 % of the achieved ROAS and raise it 10–15 % every 2 weeks while volume holds.
3. Budget: $180/day is not the constraint (spend ≈ $123/day). Do not raise it until the switch
   above is done; then raise 20 % per week while ROAS ≥ target.

## Ad schedule and device

Google's signal panel already reports that **mobile + weekdays 6–10 AM** convert best and
**weekdays 12–6 PM / weekends 10 AM–6 PM** convert worst. With Smart Bidding, manual schedule
bid adjustments are ignored (only the −100 % device adjustment is honoured), so:

- Do **not** add hourly bid modifiers; the strategy is already using these signals.
- Keep the schedule 24/7. Purchases of custom parts happen in the evening; cutting hours removes
  cheap conversions Smart Bidding is already discounting.
- Tablets: 1.5 % of cost, negative signal. Leave at 0 %; the spend is immaterial.

## Location

- Keep **United States**.
- Settings → Locations → **Location options** → choose **Presence** ("People in or regularly in
  your targeted locations"). The default "Presence or interest" lets people outside the US
  trigger ads.
- Do **not** exclude Georgia yet on one week of data. Segment by state after 30 days of valued
  conversions and exclude any state with spend > 3 × CPA and zero purchases.
- Add state-level bid adjustments only if you move to a manual/eCPC strategy; under Smart Bidding
  they are ignored.

## Networks

Search partners deliver 29 % of clicks at $0.73 CPC versus $2.23 on Google Search.

- Segment the campaign by Network (Campaign → Segment → Network with search partners).
- Keep partners if their cost/conversion is ≤ Google Search's. If it is > 1.5 ×, untick
  **Include Google search partners** in Settings → Networks.
- **Display Network** must be unticked on this Search campaign (verify in Settings → Networks).

## Audience signals (observation, no bid changes)

Add these as **Observation** audiences so the bidding engine can learn from them:

- In-market: Auto Parts & Accessories; Motor Vehicles (Used); Trucks & SUVs
- Affinity: Auto Enthusiasts; Performance & Luxury Vehicle Enthusiasts
- Your data: all site visitors 30 d, cart abandoners 7 d, past purchasers 540 d
  (past purchasers can later be excluded from prospecting or targeted with a "second emblem" ad)

## Weekly routine (15 minutes)

1. Search terms report → add negatives, promote converting terms to exact match.
2. Ad strength per ad group → keep every RSA at Excellent; replace the lowest-performing
   headline (Asset details → Performance "Low") with a new keyword headline.
3. Recommendations tab → apply only: assets, keyword additions you recognise, and fixing
   disapprovals. Dismiss "Raise budget", "Add broad match", and "Use Performance Max" until ROAS
   is stable.
