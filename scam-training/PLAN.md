# Scam Awareness Trainer: Plan

Working name: **Lure** (placeholder, check trademark before using).

Status: draft for discussion, 15 Oct 2026.

---

## 0. Before building: assumptions worth challenging

These are the points I would want settled before writing code, because each one changes the product.

### 0.1 "Training people to spot scams" has weaker evidence than the industry suggests

Two large field studies should shape the design:

- **ETH Zurich (Lain, Kostiainen, Čapkun, IEEE S&P 2022)**: 14,000+ employees over 15 months. Embedded training shown right after someone clicked a simulated phish did *not* reduce susceptibility and in some conditions made people *more* likely to click. What did work: a one-click reporting button, where colleagues collectively flagged real attacks quickly.
- **UC San Diego Health (Ho et al., IEEE S&P 2025)**: ~19,500 employees. Annual awareness training showed no meaningful link to click rates; embedded training improved outcomes by roughly 2 percentage points.

What this means for us: a product that measures "did they click the fake email" and hands out badges for it risks optimising a number that does not translate into real-world protection. The plan below therefore treats **reporting behaviour, calibrated judgement, and low false-alarm rates** as the primary outcomes, with click rate secondary.

### 0.2 "Spot the typo" is dead

LLM-written scams are grammatically clean, localised and personalised. Teaching surface cues (bad spelling, odd logos) trains people for 2015-era attacks. The curriculum must centre on cues that survive AI polish:

- the *request* (urgency, secrecy, payment change, credential entry, gift cards, remote-access apps)
- the *channel* (why is my bank texting me a link? why is the CEO on WhatsApp?)
- the *verification step* (call back on a number you already have, open the app directly, never via the message)

### 0.3 Terminology check

You wrote "phishing to fishing to smishing". I assume "fishing" meant **vishing** (voice/phone scams). The full family worth planning for: phishing (email), spear phishing / BEC (business email compromise), smishing (SMS), vishing (voice, including AI voice clones), quishing (QR codes), and messenger scams (WhatsApp "Hi Mum", fake marketplace buyers). If you meant something else, tell me.

### 0.4 Who is this for? This is the biggest open decision

The plan works for any audience, but pricing, legal exposure and content differ a lot:

| Audience | Pays? | Competition | Legal weight | Notes |
|---|---|---|---|---|
| Individuals (B2C) | Rarely | Free quizzes (Google/Jigsaw, banks, police) | Low | Hard to monetise; good for reach |
| Seniors / families | Rarely directly; partners might | Low quality, fragmented | Low to medium | Highest real-world losses per victim; strong partner fit (Pro Senectute, banks, cantonal police) |
| SMEs (B2B) | Yes, per seat | KnowBe4, Hoxhunt, Proofpoint, SoSafe, plus Swiss players | High (employee data) | Money is here, so is the crowd |
| Schools | Through institutions | Moderate | High (minors) | Slow procurement |

**My recommendation**: build the core as a consumer-grade training game (cheapest, lowest legal risk, fastest feedback), but architect for multi-tenant teams from day one so B2B can come in phase 2 without a rewrite. A Swiss / multilingual (DE, FR, IT, EN) angle is a genuine gap: most incumbents treat Swiss German and Swiss-specific scams (Post, SBB, PostFinance, TWINT, eBill, fake police/"Kapo" calls) as an afterthought.

### 0.5 Bias check

- **Builder bias (yours and mine)**: it is easy to assume a polished app is the bottleneck. In this market, the bottleneck is distribution and content quality. Budget accordingly.
- **Gamification optimism**: points and streaks raise engagement, but engagement is not learning. We should test that the game moves a real outcome, not only session counts.
- **My default toward SaaS + LLM features**: I lean toward "add AI generation" by reflex. Here it is a misuse liability (see 3.1), so I have deliberately kept it out of the user-facing product.
- **Victim-blaming bias**: scam training can imply "if you got scammed, you failed the test". Messaging must avoid shame; shame reduces reporting, which is the behaviour we most want.

---

## 1. Product concept

A web app (installable as a PWA) where people work through **realistic but fully contained** scam scenarios inside a simulated environment: a fake inbox, a fake phone with SMS and messenger threads, later a fake call screen. Nothing is ever sent to a real inbox or phone in the early phases.

### 1.1 Core loop (one scenario, ~60 to 90 seconds)

1. A message appears in the simulated inbox. Some are scams, some are **legitimate** (this matters: see 1.3).
2. The user can inspect it the way they would in real life: hover/long-press links to reveal the true URL, view sender details, expand headers in advanced levels.
3. They decide: **Report as scam**, **Safe, act on it**, or **Verify first** (and pick how: call known number, open app directly, ask colleague).
4. They state confidence: *guessing / fairly sure / certain*.
5. Immediate debrief: annotated message showing the cues, what the attacker wanted, and the real-world verification move.

### 1.2 Progression

- **Track 1, Phishing (MVP)**: credential harvesting, fake invoices, parcel notices, account suspension, prize/refund lures, attachment lures.
- **Track 2, Targeted email**: spear phishing, BEC (payment detail change, CEO fraud), thread hijacking (reply inside a real conversation), lookalike domains.
- **Track 3, Smishing and messengers**: parcel/toll/tax SMS, "Hi Mum" WhatsApp, marketplace buyer scams, TWINT/payment-request tricks.
- **Track 4, Quishing**: QR codes on parking meters, letters, emails.
- **Track 5, Vishing**: scripted audio calls (fake bank security, fake police, tech support), AI voice-clone awareness. Synthetic voices are labelled as synthetic.
- **Track 6, Mixed channel**: attacks that start by email and continue by phone, the hardest and most realistic tier.

Difficulty within each track rises along: obviousness of the request → sender plausibility → personalisation → channel switching → time pressure.

### 1.3 Scoring: reward judgement, not paranoia

A naive score ("caught the scam = +10") trains people to report everything, which in a workplace floods IT and in private life means missing a real parcel or bank message. Score with **signal detection** instead:

- **Sensitivity**: how well the person separates scams from legitimate messages.
- **Bias**: whether they lean "everything is fine" or "everything is a scam".
- **Calibration**: whether "certain" answers are actually right more often than "guessing" ones.

Shown to users in plain words ("You catch most scams but flag 1 in 4 real messages too. Here is how to check faster instead of rejecting.").

### 1.4 Gamification that supports the learning

Keep:
- **Levels and unlockable tracks** tied to demonstrated skill, not time spent.
- **Spaced repetition**: missed cue types return days later in new disguises.
- **Daily "one message" challenge**: short, habit-friendly.
- **Streaks with forgiveness** (a missed day does not wipe progress; punitive streaks drive anxiety and churn).
- **Collection mechanic**: "field guide" of scam patterns you have correctly identified, with real-world examples.
- **Team challenges (B2B)**: aggregate team score, no individual ranking visible to managers by default.

Avoid or make opt-in:
- **Public leaderboards**: they demotivate the bottom half, who are the people we most need. Opt-in, friends-only or league-style (small groups of similar level) at most.
- **Shaming on failure**: no red "YOU GOT PHISHED" screens. Neutral, explanatory debriefs.
- **Speed bonuses**: rewarding fast decisions directly contradicts the "slow down and verify" lesson.

---

## 2. Technology

### 2.1 Recommended stack

Chosen to match what you already run (`swiss-events` is Laravel on Infomaniak), so you are not learning a new stack while learning a new domain.

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel (PHP 8.4+) | Your existing skill set; mature auth, queues, policies, rate limiting |
| Frontend | Inertia + Vue or React, or Livewire | The simulated inbox/phone is interactive; Inertia gives SPA feel with Laravel routing. Livewire is simpler if you prefer less JS |
| Styling | Tailwind | Already in your toolchain |
| Database | PostgreSQL preferred; MariaDB acceptable | JSONB for scenario metadata and event logs is convenient; MariaDB works if staying on shared hosting |
| Cache / queues | Redis | Sessions, rate limits, spaced-repetition scheduling |
| Hosting | Infomaniak (Swiss data residency) | Data stays in CH, a selling point for Swiss/EU B2B. **Caveat**: shared hosting limits long-running queue workers; plan on Infomaniak Cloud Server or Jelastic once there are background jobs |
| Auth | **Passkeys (WebAuthn)** primary, email + password + TOTP fallback | Do *not* use magic links: a scam-training app teaching people to click login links in emails is self-defeating |
| Analytics | Self-hosted Matomo or Plausible, no third-party trackers | Privacy is part of the pitch |
| Audio (vishing, later) | Pre-rendered audio files, served statically | No live voice generation in the product (see misuse) |
| Content | Versioned scenario files (YAML/JSON + assets) in the repo, with review workflow | Treat scenarios like code: reviewed, tested, versioned |
| CI | GitHub Actions (you already have it) | Add content linting: every link defanged, every brand reference approved, every scenario has a debrief |

### 2.2 Scenario rendering, the security-critical piece

Scenarios look like phishing by design, so rendering must make them inert:

- Render message bodies as **static, pre-sanitised HTML inside a sandboxed iframe** (`sandbox` without `allow-scripts`, `allow-forms`, `allow-top-navigation`), served from a separate content origin.
- Strict **CSP**: no inline scripts, no external fetches, no form submissions from scenario content.
- **All links rewritten** to an internal handler that shows "this would have taken you to `paypa1-secure.example`" and never navigates out.
- **No credential fields that accept input** in fake login pages. Show a screenshot-style mock with a lesson overlay instead. Never store anything typed.
- Use **reserved or owned domains** for all fake URLs (`.example`, `.test`, or domains you own and park), never real-looking domains you do not control.

### 2.3 Data model sketch

- `users`, `teams` (nullable, for B2B), `memberships`
- `scenarios` (id, version, track, difficulty, channel, is_scam, cues[], locale, review_status)
- `attempts` (user, scenario_version, decision, confidence, inspected_actions[], time_taken, created_at)
- `skill_state` (user, cue_type, strength, next_review_at) for spaced repetition
- `achievements`, `streaks`

Keep `attempts` free of anything personal beyond the user id; aggregate before showing to team admins.

---

## 3. Secure by default: preventing misuse

The real misuse risk is that the platform becomes a **phishing kit**: a library of high-quality scam templates, a sender, and analytics on who clicked. Every commercial phishing-simulation vendor carries this risk. Design choices:

### 3.1 Product-level controls

1. **No outbound sending in phases 1 to 2.** Everything happens inside the app. This removes the largest abuse vector entirely.
2. **No user-prompted AI generation.** Scenarios are authored (possibly with AI help) by your team, reviewed, then published. Users cannot type "write me a scam email for Bank X". An LLM generator exposed to users is a free phishing copywriter.
3. **Templates are not exportable in usable form.** No "download HTML", no raw source view. Advanced "view headers" levels show annotated, defanged versions.
4. **Content is watermarked**: visible "TRAINING" markers in the simulated UI chrome and invisible markers in the HTML, so screenshots lifted out are less convincing and traceable.
5. **Real brands handled carefully** (see legal 5.3): prefer fictional lookalike brands ("SwissPost" → "HelvetiPost"), or real brands only with permission or in clearly educational, non-interactive screenshots.

### 3.2 If outbound simulation is ever added (phase 3+, B2B only)

- Tenant must **prove domain ownership** (DNS TXT record) for every recipient domain; sending to any other domain is impossible at the code level, not just policy.
- Admin identity verified (business registration check), contract signed, named responsible person.
- Sending only through your infrastructure with your own sending domains, **landing pages on your domains only**, never tenant-supplied URLs.
- Coordinate with the client's mail security (allowlisting) instead of trying to evade filters. Never offer filter-evasion features.
- Hard rate limits, kill switch per tenant, full audit log.
- Consider whether you need this at all: given the ETH findings, a **report button + in-app training** may beat surprise simulations.

### 3.3 Platform security baseline

- OWASP ASVS Level 2 as the target; Laravel defaults plus explicit checks.
- Passkeys, mandatory MFA for admin and team-admin roles.
- Least-privilege roles: learner, team admin (aggregates only), content author, content reviewer, platform admin.
- Encrypted backups, secrets in a manager not in `.env` on disk long term.
- Dependency scanning and secret scanning in CI.
- `security.txt` and a simple vulnerability disclosure policy from launch. A security-training app with a public vuln is reputationally fatal.
- Annual external pentest once there are paying customers.

---

## 4. Technical pitfalls

| Pitfall | Why it bites | Mitigation |
|---|---|---|
| **Your own domain gets flagged as phishing** | Google Safe Browsing, Microsoft SmartScreen and mail filters scan content. A site full of fake login pages and brand lookalikes looks exactly like a phishing site | No functional login forms in scenarios, content on separate origin, images over live HTML where possible, register with Safe Browsing/SmartScreen as a training site, monitor with Search Console |
| **Transactional email from your app lands in spam** | Same reason, plus new domain reputation | Separate domain for app mail, SPF/DKIM/DMARC from day one, keep emails plain and link-light |
| **Content goes stale fast** | Scam tactics shift monthly (TWINT, toll SMS, AI voice) | Budget ongoing content work; source from BACS/NCSC weekly reviews, cantonal police warnings, bank advisories |
| **Mobile realism** | Smishing training on a desktop mock feels fake | Mobile-first PWA, realistic phone chrome for SMS/messenger tracks |
| **Localisation is not translation** | A German scam translated to French misses Romandie-specific lures | Native authors per language, region-specific scenarios |
| **Accessibility** | Scam cues are often visual (URL shapes, logos); screen reader users get scammed too | WCAG 2.2 AA; scenarios must expose cues to assistive tech, and include audio-first tracks |
| **Measuring transfer** | In-app scores do not prove real-world behaviour | Optional periodic "unannounced" in-app checks after gaps; for B2B, correlate with real report-button data if the client shares it |
| **Shared-hosting limits** | Queue workers, Redis, WebSockets constrained | Start on shared if MVP is simple, move to Infomaniak cloud server before B2B |

---

## 5. Legal (Swiss-centric, check with a lawyer)

I am not a lawyer; this is a map of what to ask about, not advice.

### 5.1 Data protection

- **Swiss nFADP (revDSG, in force since Sept 2023)** applies. Privacy by design and default is a legal duty, records of processing, data breach notification to the FDPIC, and a data protection impact assessment if processing is high-risk.
- **GDPR** applies as soon as you target EU users (likely, given DE/FR/IT). May require an EU representative.
- Training performance is behavioural data. In B2B it is **employee data**, which raises the stakes.

### 5.2 Employee monitoring (B2B)

- Swiss **ArGV 3 Art. 26** prohibits monitoring systems that surveil employee *behaviour*. Individual phishing-click tracking can collide with this. Aggregated, anonymised team reporting plus individual data visible only to the employee is the safer default.
- **Germany**: introducing such a tool usually triggers works council co-determination (BetrVG §87(1) no. 6). **France, Italy, Austria** have their own works council / union consultation rules. This slows B2B sales in DACH and must be designed for (configurable data visibility, DPIA templates for customers).
- Make "managers cannot see individual results" the default setting. It is both legally safer and supported by the research on reporting culture.

### 5.3 Brands and trademarks

- Using real logos (PostFinance, Swiss Post, UBS, Microsoft) in fake messages risks trademark and unfair competition claims (Swiss UWG), and the brands' abuse teams may file takedowns against your domain.
- Options: fictional brands (safest), real brands only in static, clearly labelled screenshots of *actual* reported scams (stronger educational/quotation argument, still get advice), or partnerships with the brands (best: banks and Swiss Post run their own awareness campaigns and may co-fund).

### 5.4 AI-generated voices and media

- **EU AI Act Art. 50** transparency obligations for synthetic audio/deepfakes start applying from August 2026 (verify current status; parts of the Act have been subject to delay proposals). Label synthetic voices clearly.
- Never clone a real person's voice without written consent, including for the "CEO voice" scenario. Use synthetic voices of no real person.

### 5.5 Minors

If schools are a target: parental consent rules, stricter data minimisation, no behavioural profiling. I would leave this out until later.

### 5.6 Liability and claims

- Do not promise "you will not get scammed". Terms should state the service is educational.
- If someone completes training and later loses money to a scam, the main risk is reputational more than legal, but clear wording matters.
- Consider professional indemnity and cyber insurance once B2B.

### 5.7 Outbound simulations (if ever)

Sending deceptive emails to people who have not individually agreed can raise issues under telecom/unsolicited communication law and, in the wrong hands, criminal law (fraud, unauthorised access if credentials are captured). Contractual consent from the employer is necessary but may not be sufficient everywhere. Another reason to stay in-app.

---

## 6. Logistics

- **Content pipeline is the product.** Expect 60 to 100 quality scenarios for an MVP track in one language. Each needs: author, cue annotations, debrief text, legitimate counterpart messages, reviewer sign-off, accessibility check.
- **Sources for real-world scams**: BACS (Federal Office for Cybersecurity, formerly NCSC) weekly reports and reporting portal, cantonal police (e.g. "Cybercrimepolice"), bank and Swiss Post warning pages, user submissions (later, with strict sanitisation and consent).
- **People you will need** (even part-time): a content author with security background, native reviewers per language, a UX designer for the simulated environments, a lawyer for one-off setup review, later a sales person for B2B.
- **Partnerships worth exploring early**: Pro Senectute (seniors), cantonal police prevention units, banks and insurers (they lose money to fraud and fund prevention), SME associations (e.g. cantonal chambers of commerce), digitalswitzerland and the iBarry initiative for SME cyber awareness.
- **Feedback loop**: in-app "was this realistic?" and "report a new scam you have seen" from launch.

---

## 7. Financial

Rough orders of magnitude in CHF; replace with quotes before deciding.

### 7.1 Costs

| Item | MVP (first 6 months) | Ongoing / year |
|---|---|---|
| Hosting (Infomaniak, then cloud server) | 20 to 100 / month | 1,200 to 4,000 |
| Domains, email, monitoring | ~300 | ~500 |
| Content authoring (if outsourced) | 8,000 to 25,000 for ~80 scenarios in one language | Similar per new track/language |
| Translation/localisation per extra language | 3,000 to 10,000 | Ongoing with new content |
| Legal setup (privacy policy, terms, DPA template, trademark check) | 3,000 to 10,000 | 1,000 to 3,000 |
| Design/UX | 0 if DIY, 5,000 to 20,000 if contracted | Variable |
| Insurance (once B2B) | n/a | 1,000 to 3,000 |
| Pentest (once B2B) | n/a | 5,000 to 15,000 |
| Synthetic voice (vishing track) | n/a | Low; pre-rendered once |

The biggest single cost is **your time**, followed by **content**. Infrastructure is negligible.

### 7.2 Revenue models

- **B2C freemium**: free core track, paid advanced tracks or family plan. Expect low conversion; mostly a reach and brand play.
- **Partner-funded B2C**: a bank, insurer or canton sponsors free access for its customers/residents. In my view the most promising route for consumer and senior audiences.
- **B2B per seat**: incumbents charge in the low single-digit CHF/USD per user per month range, with heavy discounting at scale. Competing on price against KnowBe4 is a losing game; compete on local language, Swiss data residency, privacy-respecting design and a reporting-first approach.
- **Grants**: Innosuisse (innovation projects need a research partner, e.g. a university of applied sciences; fits well with an effectiveness study), cantonal innovation funds.

### 7.3 Financial pitfalls

- Spending on many languages and tracks before one track proves people return and improve.
- Underpricing B2B and then being unable to fund content updates.
- Assuming B2C users will pay: most will not, and that is fine if partners do.

---

## 8. Phased roadmap

### Phase 0, Discovery (2 to 4 weeks)
- Pick primary audience (see 0.4).
- Talk to 10 to 15 potential users/buyers: an SME owner, an IT admin, a Pro Senectute contact, someone in a bank fraud team.
- Collect 50 real scam examples from Swiss sources.
- Decide fictional vs real brands.
- **Exit criterion**: a named audience and at least one potential partner or pilot customer interested.

### Phase 1, Phishing MVP (8 to 12 weeks part-time)
- Simulated inbox, sandboxed rendering, link inspection.
- ~60 email scenarios (scam + legitimate), one language.
- Signal-detection scoring, confidence rating, debriefs.
- Levels, forgiving streaks, field-guide collection.
- Passkey auth, privacy policy, Swiss hosting.
- **Exit criterion**: in a pilot of 30 to 100 users, measurable improvement in sensitivity on unseen scenarios after two weeks, without a rise in false alarms.

### Phase 2, Breadth and teams (3 to 6 months)
- BEC/spear phishing track, smishing + messenger track with phone UI, quishing.
- Second and third language.
- Spaced-repetition engine.
- Team accounts with aggregate-only dashboards, DPIA template for customers.
- **Exit criterion**: first paying customer or funded partner.

### Phase 3, Advanced (6+ months)
- Vishing track with labelled synthetic audio.
- Mixed-channel scenarios.
- Optional: report-button integration for Outlook/Gmail for B2B (likely more valuable than outbound simulation).
- Optional and only with the controls in 3.2: outbound simulations.

---

## 9. Decisions I need from you

1. Primary audience for phase 1 (individuals, seniors via partners, or SMEs)?
2. Language for the first track (DE, FR, IT or EN)?
3. Fictional brands or pursue real-brand partnerships?
4. Solo build or will you bring in a content author early?
5. Is this a side project, a social-impact project, or a business? It changes how much of section 7 matters.

## 10. Key references

- Lain, Kostiainen, Čapkun. *Phishing in Organizations: Findings from a Large-Scale and Long-Term Study.* IEEE S&P 2022.
- Ho et al. *Understanding the Efficacy of Phishing Training in Practice.* IEEE S&P 2025.
- BACS (Swiss Federal Office for Cybersecurity): weekly reviews and reporting portal.
- OWASP ASVS 4.x / 5.0.
- Swiss nFADP; FDPIC guidance on employee monitoring; ArGV 3 Art. 26.
- EU AI Act, Article 50.
