# 3 · Keywords, ad-group structure and negatives

## Why restructure

One ad group ("Badge My Auto Ad Group") holds five different themes on broad match, and one broad
keyword ("custom car emblems for sale") takes 51 % of spend while matching to informational
queries. Google can only tick "popular keywords in your headlines" per ad group, and a single RSA
cannot contain every theme. Five tight ad groups fix ad strength, Quality Score and CPC together.

Search volumes below are US monthly (SE Ranking, Aug 2026). CPC is the organic-tool estimate;
your actual average is $1.80, so treat these as *relative* signals.

## Ad groups and keywords

Match types: **phrase** for everything, **exact** for the proven converters. Keep one broad-match
keyword per ad group only after conversion values are fixed (step 1) and Smart Bidding has 30+
valued conversions; broad match on Maximise conversion value is fine, broad match on $1.00
conversions is how you got here.

### AG 1 · Custom Car Emblems  (landing: configurator)
```
"custom car emblems"
"custom car emblem"
[custom car emblems for sale]
"custom metal car emblems"          590/mo · KD 13
"custom metal emblems for cars"     590/mo
"custom auto emblems"
"custom car emblems badges"         390/mo
"custom car emblem badge"           320/mo
"custom vehicle emblems"            110/mo
"custom chrome emblems"             170/mo
```

### AG 2 · Custom Car Badges
```
"custom car badges"
"custom car badge"
"custom badges for cars"
"custom metal car badges"           590/mo · KD 14
[bespoke car badges]
[custom auto badges emblems]
"custom car badges and emblems"     390/mo, rising every month for 12 months
"custom fender badges"              90/mo
```

### AG 3 · Grill Badges  (cheapest cluster, KD 4–10)
```
"custom grill badge"                390/mo
"custom grill badges"               390/mo
"custom grille badge"               390/mo
"custom grill emblem"               390/mo
"custom grill emblems"              390/mo
"custom car grill emblems"          390/mo · KD 4
"custom front grill emblem"         390/mo
```

### AG 4 · Truck Emblems
```
[aftermarket truck emblems]         17.9 % CTR in-account
"custom truck emblems"
"custom truck badges"
"custom emblems for trucks"
"custom badges for trucks"
"custom tailgate emblem"
"truck badges custom"
```

### AG 5 · Metal Decals
```
[custom metal decals for cars]
"custom metal decals for cars"
"metal car decals"                  480/mo
"metal decals for cars"             480/mo
"car decals metal"                  480/mo
"chrome car emblems"                480/mo, up 3× in 12 months
```

### Optional AG 6 · Make-specific  (only if you can legally sell these)
```
"custom chevy badges"               480/mo
"custom ford badges"                260/mo
"custom jeep badges"                210/mo
```
You may **bid** on these terms, but Google's trademark policy will disapprove ad text that uses
"Chevy", "Ford" or "Jeep" unless you are an authorised reseller. Use generic headlines
("Custom Badges For Your Truck") and let DKI stay off in this group.

## Keywords to pause

- `custom auto badges emblems` as **broad** — 4.18 % CTR, lowest in account. Re-add as exact in AG 2.
- Any broad keyword once its phrase/exact twin has 2 weeks of data.

## Campaign-level negative keywords (paste as one list)

Informational / DIY / files:
```
free
meaning
meanings
identify
identification
list
quiz
history
png
svg
vector
clipart
template
font
fonts
3d print
stl
diy
how to make
how to
tutorial
```
Wrong product:
```
name badge
name badges
id badge
employee badge
security badge
police badge
pin
pins
enamel
lanyard
patch
patches
embroidered
sticker
stickers
vinyl
license plate frame
license plate frames
keychain
hat
shirt
```
Wrong intent:
```
jobs
job
salary
wholesale
bulk
supplier
manufacturer china
alibaba
repair
remove
removal
replacement oem
```
Review the **Search terms** report every Monday for 4 weeks and add anything else that is
informational. "car decals" (18,700/mo, CPC $0.27) and "car emblems" (8,000/mo) are cheap but
informational; they should only enter through phrase matches that include "custom" or "metal".

If you *do* sell license-plate frames, remove those two negatives and give them their own ad group.
