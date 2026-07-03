# Digital Marketing Manager: Senior Technical Screen (15 Minutes)

**Client industry:** Home improvement / home services
**Format:** Remote video call, initial screen
**Goal:** Quickly confirm hands-on, senior-level depth in paid media, SEO, analytics, and email for home services lead generation before advancing the candidate to a full interview.

---

## Interview Flow

| Time | Segment |
|------|---------|
| 0:00 to 2:00 | Intro and Question 1 (background and budget filter) |
| 2:00 to 12:30 | Core technical questions (Questions 2 to 8) |
| 12:30 to 15:00 | Candidate questions and wrap-up |

**Interviewer tips:**
- Keep each answer to about 90 seconds. Politely move on once you have a signal.
- Listen for numbers, tool names, and first-person ownership ("I built," "I managed"). Vague, textbook answers are the main red flag at this level.
- If short on time, Questions 2, 5, 6, and 8 are the strongest seniority filters.

---

## Opening Script (30 seconds)

"Thanks for joining. This is a quick 15 minute technical screen for the Digital Marketing Manager role supporting our home improvement client. I will move fast through several hands-on questions, so short, specific answers with real numbers are perfect. We will save a couple of minutes at the end for your questions."

---

## Question 1: Background and Budget Filter (about 1.5 minutes)

**Ask:** "Give me the 60 second version of your digital marketing background, including the largest monthly paid media budget you have personally managed and in what industry."

**Strong answer:**
- Concise and structured, lands in about a minute.
- Names a real monthly budget (typically $10K to $100K+ per month at the senior level) and the channels it covered.
- Mentions lead generation experience, ideally home services, home improvement, or another local services vertical.
- Frames past work in results language: cost per lead, ROAS, booked jobs, revenue.

**Red flags:** Rambles past two minutes, gives no numbers, experience is mostly organic social posting, or the budget was managed entirely by an outside agency without hands-on platform access.

---

## Question 2: Channel Strategy for Home Services (about 1.5 minutes)

**Ask:** "For a home improvement client, how would you split budget between Google Local Services Ads, standard Google Search campaigns, and Meta lead ads, and why?"

**Strong answer:**
- Prioritizes Local Services Ads first for high-intent local demand: pay per lead pricing, Google Guaranteed or Screened badging, and rankings driven by reviews, responsiveness, and answer rate.
- Uses standard Search for service-plus-city keywords that LSA does not fully capture, with tight match types and negative keywords.
- Positions Meta as demand generation and retargeting: seasonal offers, financing promos, before-and-after creative, lookalike audiences.
- Knows the economics differ: LSA leads are often cheaper and pre-qualified, while search CPCs for roofing, remodeling, and similar high-ticket services can run $15 to $80+, with CPLs commonly $50 to $300 depending on trade and market.
- Mentions geo targeting to the actual service area and scheduling ads around the hours the office can answer calls.

**Red flags:** Treats all channels as interchangeable, has never run LSA, or cannot give a single benchmark number.

---

## Question 3: Account Structure and Negative Keywords (about 1.5 minutes)

**Ask:** "Walk me through how you would structure a Google Ads account for a company offering roofing, window replacement, and bathroom remodeling across three metro areas. What role do negative keywords play?"

**Strong answer:**
- Separates campaigns by service line (and often by metro) for budget control and clean reporting, with tightly themed ad groups and dedicated landing pages per service.
- Splits brand from non-brand, and separates repair intent from replacement intent since ticket sizes differ dramatically.
- Sets location targeting to "presence in" the service area rather than "interest in" it.
- Runs a shared negative keyword list from day one: DIY terms, "how to," jobs and careers, cheap and free, rental, and irrelevant materials or services, then mines the search terms report weekly.

**Red flags:** One catch-all campaign, unaware of the presence versus interest location setting, or no ongoing negative keyword process.

---

## Question 4: Local SEO and the Map Pack (about 1.5 minutes)

**Ask:** "What actually drives rankings in the Google local pack for a query like 'bathroom remodeler near me,' and what would you do in your first 30 days to improve it?"

**Strong answer:**
- Names Google's three local factors: relevance, distance, and prominence.
- First 30 days: fully build out the Google Business Profile (primary and secondary categories, services, service areas, photos, posts, Q&A), fix NAP consistency across citations, and stand up a review generation workflow that requests reviews right after job completion, with responses to every review.
- On the website: city and service landing pages with real local content, LocalBusiness and Service schema markup, embedded reviews, and internal linking.
- Tracks results with UTM-tagged GBP links and monitors calls and direction requests, not just rankings.

**Red flags:** Only talks about generic on-page SEO, cannot distinguish local pack from organic results, or has no concrete review generation strategy.

---

## Question 5: Hands-On HTML/CSS (about 1.5 minutes)

**Ask:** "This role requires working HTML and CSS knowledge. Tell me about something you have personally edited in code, and how you would deploy a new tracking pixel or fix a broken heading structure without waiting on a developer."

**Strong answer:**
- Gives concrete, first-person examples: editing title tags and meta descriptions, correcting H1/H2 hierarchy, adding JSON-LD schema, adjusting CSS for mobile layout or Core Web Vitals issues, building HTML email templates, or editing landing pages in WordPress or a page builder.
- For pixels: deploys through Google Tag Manager rather than hard-coding, and can explain tags, triggers, and variables at a basic level.
- Knows their limits and when a developer is genuinely needed (server-side work, site speed engineering, complex templates).

**Red flags:** "I usually just ask the developer," cannot name a single HTML tag or where a pixel goes, or has never opened GTM.

---

## Question 6: Tracking Booked Jobs, Not Just Leads (about 2 minutes)

**Ask:** "A home services client says leads are up but revenue is flat. How do you instrument the funnel so you are reporting on booked jobs and revenue, not just form fills?"

**Strong answer:**
- Captures every lead type: forms, phone calls with dynamic number insertion (CallRail or similar), chats, and LSA leads, all defined as key events in GA4.
- Pushes leads into the CRM (ServiceTitan, Jobber, HubSpot, or similar) and tracks stage progression: lead, appointment set, quote given, job booked, revenue.
- Feeds outcomes back into ad platforms via offline conversion imports or enhanced conversions for leads, so bidding optimizes toward qualified and booked leads instead of raw form fills.
- Reports cost per booked job and ROAS by channel, close rate by source, and average ticket, then reallocates budget toward the sources that book.
- Also checks the non-marketing failure mode: slow speed-to-lead or poor call answering can flatten revenue even when lead volume rises.

**Red flags:** Reporting stops at GA4 form submissions, no concept of offline conversions or lead quality feedback, or blames the sales team with no data.

---

## Question 7: Email Marketing and Nurture (about 1.5 minutes)

**Ask:** "What email automations would you set up for a remodeling company, and how do you protect deliverability?"

**Strong answer:**
- Automations: instant speed-to-lead reply (within five minutes), a 3 to 5 touch quote follow-up sequence, seasonal and maintenance reactivation campaigns to the past-customer list, post-job review requests, and referral offers.
- Segments by service interest, pipeline stage, and past customer versus prospect.
- Deliverability: SPF, DKIM, and DMARC configured on the sending domain, ideally a dedicated sending subdomain, list hygiene with engagement-based sunsetting, and avoiding purchased lists.
- Measures success on replies, appointments, and booked jobs rather than open rates, and knows open rates are inflated by Apple Mail Privacy Protection.

**Red flags:** The whole answer is "send a monthly newsletter," no knowledge of email authentication, or success measured only on opens.

---

## Question 8: Diagnostic Scenario (about 2 minutes)

**Ask:** "Your cost per lead on Google Ads doubled over two weeks. Walk me through your diagnostic process, in order."

**Strong answer:**
- Verifies conversion tracking first: a broken form tag or call tracking number is the most common cause of a phantom CPL spike.
- Reviews the change history for bid strategy, budget, or targeting edits that could have reset learning.
- Checks the search terms report for junk traffic and new negative keyword needs.
- Reviews auction insights for new competitors or aggressive bidders, and considers seasonality for the trade.
- Checks landing page health: page down, slow, or a form change.
- Segments the damage by campaign, device, geography, and time of day to isolate where the spike lives before making changes.
- Only then adjusts bids, budgets, or structure, one variable at a time.

**Red flags:** Jumps straight to "raise the budget" or "rewrite the ads," never mentions checking tracking, or has no ordered process at all.

---

## Backup Quick-Fire Questions (if time allows)

**Q9:** "What goes on the one-page monthly report you send the owner?"
**Strong answer:** Leads by source, cost per lead, cost per booked job, revenue and ROAS, close rate by channel, GBP calls and direction requests, review count and average rating, and one or two priorities for next month. Ties spend to revenue, not vanity metrics.

**Q10:** "The client spends $3K per month on Angi leads and complains they are shared with competitors. Keep it or cut it?"
**Strong answer:** Decide on cost per booked job, not sentiment. Aggregator leads are shared with three or four contractors, so speed-to-lead and call handling determine whether they convert. Do not cut until owned channels (LSA, search, SEO) have ramped enough to replace the volume, then shift budget gradually while monitoring total booked jobs.

---

## Scoring Guide

Rate each area 1 to 5. Advance candidates who score 4+ on Paid Media and Measurement with no red flags.

| Area | Questions | 5 looks like |
|------|-----------|--------------|
| Paid media depth | 1, 2, 3 | Owns budgets, knows LSA, cites real benchmarks |
| SEO and local | 4 | Local pack fluency, concrete 30 day plan |
| Technical hands-on | 5 | Real HTML/CSS examples, GTM fluency |
| Measurement | 6, 8 | Tracks to booked jobs and revenue, ordered diagnostics |
| Email and nurture | 7 | Automation and deliverability fluency |
| Industry fit and communication | All | Home services experience, concise and specific |

**Decision:**
- **Advance:** Specific, numeric, first-person answers across paid, measurement, and at least one of SEO or email.
- **Hold:** Strong on two areas but thin on measurement; consider only if the pipeline is weak.
- **Pass:** Vague or textbook answers, no budget ownership, no tracking depth, or any answer that leans entirely on "the agency handled that."
