# FY28 deck: pending edits (apply all at once, then save as the next draft)

Base: draft v7 (11 Nov 2026). **Edits 1-3 APPLIED in draft v8 (24 slides).** New edits go below as v8 -> v9.

1. **Slide 3 scorecard, Portal traffic row**
   - Target: `+4% on FY26` (drop the 5,614: quarterly users can't be summed to a yearly total)
   - Result: `Up on last year every quarter: AMJ 1,482 users (+74%), JAS 3,605 users (+198%)`
   - Status: Ahead (unchanged)
   - Talk track (not on slide): driven by audit eDMs, Zaditen chart, site opened to Google 23 Jul; don't promise the quarterly rate holds for the full year.

2. **Slide 3 scorecard, Average engaged time row**
   - Result: `~2m 22s across AMJ + JAS`, with a grey italic sub-line (same style as the CTR row): `4m 21s in AMJ (audit), 1m 33s in JAS (quick look-ups)`
   - Status: `Ahead` (Rob, 11 Nov)
   - Calc: user-weighted from published QBR figures: (1,482 x 261.3s + 3,605 x 92.8s) / 5,087 = 142s. Footnote: "Engaged time combined from the AMJ and JAS results, weighted by users."
   - Knock-on: title becomes "all four KPIs ahead of target at the half-way mark".

3. **Remove recap slides 7-14 except 12** (v7 numbering; QBR repeats). Remove: 7 weekly users (jas47), 8 DB growth (jas50), 9 traffic (jas49), 10 eDM learnings (new), 11 IQVIA bots (jas15), 13 audit funnel (jas42), 14 module feedback (amj32). Keep 12 "What HCPs valued most". Deck goes 31 -> 24 slides.
   - Open: optionally move the "8.4% vs 0.5%" CPD-vs-content sign-up conversion stat from slide 10 into slide 12 (ask Rob).

4. **[APPLIED v9]** **Take the Princeton Desk Set out of the Care Connect recap** (QBRs file it under "Activations outside of the portal": third-party app, Princeton's own figures).
   - Slide 3 objectives box 1: replace "GP Desk Set: 16,257 engagements; new Sick Day Plan, AR and AC Treatments Charts" with a Care Connect-only line, e.g. "New Sick Day Plan, AR and AC Treatments Charts on Care Connect".
   - Slide 7 "What HCPs valued most": swap the GP Desk Set and Gastro Desk Set cards for Care Connect results, e.g. "8.4% vs 0.5%" (sign-up conversion, TMR audit eDM vs FESS video eDMs) and audit enrolments (184 since 19 Mar). Keep the takeaway lines consistent.

5. **[APPLIED v10]** Rob: it's a BRAND plan, Care Connect is one channel. Off-portal results stay, labelled. Slide 7 split into "On Care Connect" (5 cards) and "Outside the portal · partner-reported" (AJP CPD 3,472, GP Desk Set 16,257, Gastro Desk Set 4,080). Slide 3 box 1 restores the Desk Set as "Outside the portal". Platform slide subtitle adds "3,472 pharmacists completed Care's CPD on AJP's site; our own course platform brings that CPD home".

6. **[APPLIED v11]** **Platform slide title (v10 slide 9, "Beyond the fundamentals")**: change to `BEYOND THE FUNDAMENTALS: what else we fixed and improved in FY27`.

7. **[APPLIED v11]** **Same slide, "Also delivered" box**: remove the 4 micro bullets; replace with one line: `Theme taken in-house (Jul 2026): Care Connect no longer depends on the previous supplier for its theme, and the ongoing theme licence fee is gone.` Also trim the "Licences on Care's accounts" card note to "paid plugins (Formidable, LearnDash, Imagify) moved off previous-supplier accounts" (avoid saying the fee twice).

8. **[APPLIED v11]** **"Still to come in FY27" slide, JFM '27 Care Connect**: remove the line `Platform rebuild scoped, ready to start in April`.

9. **[APPLIED v11]** **Rename "Platform rebuild" -> "Bringing CPD in-house" (short: "In-house CPD platform")** everywhere; "rebuild" is too scary (Rob: it's bringing LMS + RCP in-house, removing costly third-party plugins, invisible architecture refactor, SCORM + faster CPD course delivery).
   - Description line for the pitch slide subtitle/intro: "We bring the course system and membership gating in-house, replacing costly third-party plugins, and tidy up the code underneath so the site keeps running reliably. Same site for HCPs; faster CPD courses, including SCORM, for Care."
   - Replace: pitch "Why now" ("The rebuild replaces it" -> "Bringing it in-house replaces it"); costs slide title + "WITH THE REBUILD" column -> "IN-HOUSE"; how slide title; budget row; roadmap bar label; licences slide LearnDash status + "No longer paying for" box; any other "rebuild" text (grep before applying).
   - Also: path-strip FY28 box "sign-up rebuild" -> "sign-up rework"; pitch "Why now" "rebuilds sign-up" -> "reworks sign-up".
   - FIX (factual): licences slide "No longer paying for" box says "Restrict Content Pro is retired by its maker" -> WRONG (renamed to Kadence Memberships Pro, not retired). Change to "Restrict Content Pro is no longer sold on its own; we bring membership gating in-house."

10. **[APPLIED v11]** **BAU slide, "Account + project co-ordination" row**: "Status, briefs, run-throughs, Trello, approvals" -> "Status, briefs, run-throughs, reviews, approvals".

11. **[APPLIED v11]** **Email platform for the CRM: Panwar decides and runs it** (Rob, 11 Nov: either is fine). Lean: Panwar eDM platform (panwar_api Edm module, Azure email; consent sync via marketing-contacts endpoints); fallback Care's existing Mailchimp ("HCP signups" list; Formidable->Mailchimp action failing 401; list must be rebuilt under new consent either way).
   - Licences slide row "Email platform for CRM | ... | TBC | To confirm platform" -> "Email platform for CRM | Automated plays (Recommendation 1) | Usage-based, low | Provided and run by Panwar". Remove the amber "to confirm" highlight on that row.
   - Budget slide licences line: drop "CRM email platform TBC".
   - CRM recommendation badge (caph0068 slide 5 badge): "Needs consent live + an email platform" -> "Needs consent live".

12. **[APPLIED v11]** **Redesign slide 17 (CPD pitch)**: path strip above benefit columns reads as per-FY statuses (Rob confused).
   - Title: "BRINGING CPD IN-HOUSE: a purpose-built course and membership system for Care Connect"
   - Subtitle: "We replace the third-party course and membership plugins with our own, purpose-built for Care Connect, and tidy the code underneath. Same site for HCPs; faster, easier CPD for Care." (keep the 135/249 + AJP 3,472 evidence somewhere short, e.g. a line in Why now or the source)
   - Top: heading "WHAT CARE GETS FROM THE FY28 PROPOSAL" + 3 benefit boxes (Smoother for HCPs / Easier CPD courses / Less reliance on third parties).
   - Middle: Why now box + ~80 hrs badge.
   - Bottom: slim chevron timeline "WHERE THIS SITS": FY27 done (theme, videos, licences) > FY28 course + membership system in-house > FY29+ remaining code tidy, forms review. Different visual style from the cards.
   - Deck-wide wording rule: never "rebuild"; use "bring in-house", "purpose-built", "replace third-party plugins". (Supersedes edit 9's "In-house CPD platform" naming where wording differs.)
   - Add to the "Smoother for HCPs" box: "More stable: fewer moving parts, built and tested for exactly what Care Connect does, errors caught by our monitoring before HCPs notice." (Rob wanted "low to 0 bugs"; don't promise zero bugs.)
   - TITLE (overrides above): "FUTURE-PROOFING CARE CONNECT: our own purpose-built CPD platform and registration gate"
   - Deck-wide terminology (Rob): it's a "registration gate", not a "membership system" / "membership gating". Replace in budget row, roadmap bar, path strip, Why now box, subtitle, costs slide ("Membership bundle" row can stay as the vendor's product name but label "(registration gate)").

13. **[APPLIED v11]** **Slide 18 (costs/course creation) title**: "BRINGING CPD IN-HOUSE: easier CPD courses, full control of look and feel, lower running costs" (Rob rejected "without custom code" and the SCORM-editor variant).
   - Add to the In-house column: "Course pages designed to match Care Connect, not a plugin's templates".
   - Add to slide 17 "Smoother for HCPs" box: "A consistent Care Connect look from sign-up to certificate".

14. **[APPLIED v11]** **"Untangling the inherited architecture" as a benefit** (Rob).
   - Slide 17: 4th benefit box "CLEANER FOUNDATIONS (behind the scenes)": "Untangles the inherited code: course, registration and sign-up logic split out of one 3,900-line file into tidy, documented parts" / "Fixes found once, not patched around" / "Every future change is quicker and safer". (Move "Cleaner code makes every future change quicker" out of the Less reliance box to avoid repeating.)
   - Slide 18 "Today" column: add "Course logic tangled with registration and site code".
   - Add to CLEANER FOUNDATIONS box: "Sets up a clean codebase for everything Care Connect adds next" (Rob).

15. **[APPLIED v11]** **Slide 18 rework: show the dev-cost numbers** (Rob: make clear the inherited LMS was badly implemented by previous contributors; new courses take this long; we keep paying third-party licences; no SCORM).
   - Title per edit 13.
   - Left (main) "ADDING A NEW CPD COURSE": big numbers Today ~26-32 hrs/course vs In-house ~4-5 hrs/course; yearly table 2 courses ~60 vs ~10 hrs, 4 courses ~120 vs ~20 hrs. Caption: "The LMS setup inherited from the previous agency wires three third-party plugins together with custom code, so every new course needs a developer to rewire and retest it."
   - Right "WHAT WE KEEP PAYING FOR TODAY": LearnDash US$350/yr + membership plugin bundle for the registration gate; no SCORM without another paid LearnDash add-on; look and feel locked to plugin templates.
   - Bottom strip: "At 2+ new courses a year, bringing CPD in-house pays back its ~80 hrs in the first year, before licence savings."
   - Footnote: "Hours are Panwar Health estimates based on the 2026 Clinical Audit build (CAPH0150)." Replace with real CAPH0150 hours if Rob supplies them.
   - Per-course estimate breakdown (today / in-house): build lessons+quizzes 4 / Maria in SCORM editor; surveys+eval 4 / in course; glue code + access rules 8-12 / 0; cert + emails 2 / 1; testing 6-8 / 2-3; deploy + DB 2 / 1.
   - VERIFIED 11 Nov (web): LearnDash core has no SCORM; needs an add-on (Tin Canny / GrassBlade per LearnDash docs; WooNinjas add-on US$79/yr; some free plugins exist, so don't say 'paid only'). Slide line: "No SCORM built in: LearnDash needs yet another add-on to run SCORM courses (e.g. ~US$79+/yr), one more third-party plugin to license and maintain."

16. **[APPLIED v11]** **Make Bringing CPD in-house = RECOMMENDATION 1; renumber the CAPH0068 ones 2-4.**
   - Slide 17 title prefix "RECOMMENDATION 1:" (title text per edit 12: Future-proofing Care Connect...). Consider same style as CAPH0068 ("RECOMMENDATION 1 — ..." uses an em dash in the source; ours use a colon).
   - Manifest replacements on caph0068 slides 5/6/7: "RECOMMENDATION 1" -> "RECOMMENDATION 2", "RECOMMENDATION 2" -> "RECOMMENDATION 3", "RECOMMENDATION 3" -> "RECOMMENDATION 4".
   - Budget table kind column: in-house CPD "Recommendation 1"; CRM "Recommendation 2"; Personalised "Recommendation 3"; Mobile "Recommendation 4". Drop "Platform must-do".

17. **[APPLIED v11]** **Slide 20 (CAPH0068 CRM rec) says nothing about email** (Rob). Manifest text replacements on caph0068 slide 5:
   - "Three automated plays to establish presence, retain new sign-ups, and re-engage dormant HCPs." -> "Automated emails from Care Connect, triggered by what HCPs do (a new sign-up, new content, going quiet) and sent only to HCPs who've opted in, matched to the topics they chose."
   - "New article / resource auto-fire" -> "New article / resource email"; "7-day onboarding sequence" -> "7-day onboarding emails"; "Dormancy re-engagement" -> "Re-engagement email".
   - Check exact source strings (curly quotes, trailing spaces) in caph0068.pptx before applying.

18. **[APPLIED v11]** **Roadmap (v10 slide 23)**: row label "Platform rebuild" -> "Bringing CPD in-house"; bar "CPD + gating · ~80 hrs" -> "CPD platform + registration gate · ~80 hrs"; keep it first after BAU (Recommendation 1). Final check: grep the built deck text for "rebuild" (case-insensitive) = 0 hits.

19. **[APPLIED v12]** **Slide 18 bottom strip** (Rob: payback isn't only hours): "Pays back from day one in a better HCP experience, SCORM support and a cleaner, untangled codebase. In dev time alone, it covers its ~80 hrs in about a year at 3–4 new courses a year." (Rob: drop the 2-course case, confusing.)

20. **[APPLIED v12]** **Slide 6 "What we delivered" enabler strip**: fundamentals aren't done yet. Change to "ENABLER: CARE CONNECT OPTIMISATION  SEO go-live, sample-order fix, video hosting, licences and theme brought in-house. The 4 fundamentals are on track to be complete by 31 March 2027."

21. **[APPLIED v12]** **Budget slide cost column** (Rob OK'd $140/hr, BAU-implied): R1 ~$11.2K, R2 ~$14.0K, R3 ~$12.6K, R4 ~$9.8K; growth total ~340 hrs = ~$47.6K (inside the ~$51K pot, ~$3.4K spare). Source line: "Costs at ~$140/hr; to be confirmed."

22. **[APPLIED v12]** Layout audit: grid (content top 1.45 / 1.85 with subtitle, bottom 6.70, 0.15 gaps), cards sized to content, centred band text, tag/badge right-aligned to content edge (assemble.ps1 30pt), slide 18 title shortened to one line ("full design control").

23. **[APPLIED v13]** **Budget slide total row** (Rob: confusing that it only totals growth): make it a grand TOTAL of every row: label "TOTAL", covers "Within the $103K budget", hours ~696 (356 BAU + 340 recs), cost ~$99.6K ($50K + ~$2K + ~$47.6K).

24. **[APPLIED v13]** **Reorder FY28 section** (Rob: budget after the recommendations): divider > Opportunity > Rec 1 (pitch, costs, how) > Recs 2-4 (caph0068 5/6/7) > roadmap > budget on a page > BAU > licences > thank you. Manifest order change only.

25. **[APPLIED v13]** **Licences slide "No longer paying for" box, last sentence** -> "Restrict Content Pro is no longer sold on its own; Recommendation 1 (Bringing CPD in-house) replaces it with our own registration gate." Also LearnDash status cell -> "Paid to Oct 2027; not renewed after Recommendation 1".

26. **[APPLIED v13]** **Slide 17 (Rec 1 pitch) subtitle last sentence**: "Same site for HCPs; faster, easier CPD for Care." -> "HCPs get a smoother, more reliable site; Care gets new CPD courses faster and at lower cost."

27. **[APPLIED v13]** **Slide 17 (Rec 1 pitch) benefit boxes** (Rob):
   - Replace "SMOOTHER / for HCPs" box with "MORE CONTROL / over the whole experience": "Full control of the look and feel, from sign-up to certificate" / "Full control of the code: changes made directly, not worked around a plugin" / "Fewer bugs: no more fragile third-party plugins wired together" / "One \"My CPD\" page for HCPs' progress and certificates".
   - "EASIER CPD COURSES" sub "for Care and Maria" -> "for Care". No Maria name in that box.
   - EASIER CPD COURSES box: replace "Course pages designed to match Care Connect" with "Quicker to develop new courses: no more time lost working around the hacks and patches holding the current LMS together" (Rob).

28. **[APPLIED v13]** **Slide 17 (Rec 1 pitch) boxes** (Rob):
   - CLEANER FOUNDATIONS bullet 1: drop the "3,900-line file" -> "Untangles the inherited code so the course, registration and sign-up parts each stand on their own, and a fix in one is far less likely to break another".
   - Increase bullet line spacing in all 4 boxes (~1.1 line spacing, gap 3 -> 6 pt). Check the render; grow card height (shrink Why now padding) if text gets tight.

29. **[APPLIED v13]** **Slide 17 Why now: add the "it gets worse as CPD grows" point** (Rob): "The inherited course setup is patched together, and every new CPD course built on it adds more workarounds and more risk. The more CPD we add, the bigger the problem gets." Keep previous-agency name off the slide. Check fit; trim other Why now text if needed.

30. **[APPLIED v13]** **Slide 18: make clear the hours are website build only** (Rob: excludes the CPD team writing the course).
   - Label "ADDING A NEW CPD COURSE" -> "WEBSITE BUILD TIME PER NEW CPD COURSE".
   - Stat card notes "per new course" -> "developer hours to put it on the site".
   - Table header "Hours saved" -> "Dev hours saved".
   - New bold line under the table (before the inherited-LMS caption): "These are developer hours only, to build each course into Care Connect. Writing, reviewing and accrediting the course content is separate and not included." (No Maria name.)
   - Footnote: "Hours are Panwar Health estimates of website build time, based on the 2026 Clinical Audit build (CAPH0150)..."

31. **[APPLIED v13]** **Remove every personal name from the slides; say "Panwar staff"** (Rob).
   - Slide 19 step 3: "Panwar tests every HCP journey; Maria signs off audit approval and certificates." -> "Panwar staff test every HCP journey and sign off audit approval and certificates."
   - Slide 19 footnote: "per Maria's plan" -> "per Panwar staff's CPD plan".
   - Budget footnote: "(per Maria's recommendation)" -> "(recommended by Panwar staff)".
   - Roadmap CPD row: "Scope from Maria (19 Nov); published on the new platform" -> "Scope from Panwar staff; published on the new platform".
   - Final check: grep built deck text for "Maria" = 0 hits. (Other names, e.g. Dr Kwong / Dr Tattersall / Dr Jonny Taitz / Deb Hawthorne, are KOLs in the QBR content: leave unless Rob says otherwise.)

32. **[APPLIED v13]** **Slide 19 green box**: remove ", because colleges can audit them" -> "CPD records are protected. Certificates, completion dates and CPD hours must match exactly. The current system stays in place until the new one matches it 1:1."

33. **[APPLIED v13]** **Mobile story (Rec 4)** (Rob: expand why it matters).
   - Insert CAPH0068 slide 3 ("Current Device Engagement FY26") immediately before the Rec 4 slide; tag "FY26  ·  APR 2025 TO MAR 2026".
   - Rec 4 (caph0068 slide 7) subtitle replacement: "An ‘audit-then-fix’ sprint to close the gap between mobile and desktop to improve engagement at scale." -> "Mobile HCPs stay half as long and see half the pages of desktop users, often after hours. A short audit-then-fix sprint closes that gap, so every eDM, CRM email and CPD course works properly on a phone." (check exact source string incl. curly quotes)
   - Optional (awaiting Rob): FY27-to-date GA4 device pull, labelled as new GA4 figures.

34. **[APPLIED v14]** Line-spacing pass (Rob: text bunched): airy() post-pass on every built slide = body 1.2 line spacing, table cells 1.1, +2pt between multi-paragraph items; title/source untouched. Slide 6 bullets 11pt to fit; slide 11 group spacing retuned.

35. **[APPLIED v15]** **Slide 7 "What HCPs valued most": drop the "CPD vs content eDMs" card** (Rob: option 3). "On Care Connect" goes to 4 cards (Zaditen, Sick Day Plan, Clinical Audit, Clinical Bites), widths re-spread across 12.5". Keep the band line "CPD is the sign-up driver: audit eDMs convert at 8.4%..." as is. Slide 10 card unchanged. Supersedes the open notes in items 18/22 about moving or adding this stat.

36. **[APPLIED v15]** **Slide 15 (Rec 1 costs) green "In-house" box: spell out what bringing CPD in-house means** (Rob: writing our own LMS module and removing LearnDash + third-party plugins). Slide 14 subtitle unchanged.
   - Old: "In-house: $0 licences, SCORM built in, course pages designed to match Care Connect. Saves ~A$1,000-1,300 a year in licences."
   - New, two paragraphs:
     - "What in-house means: we write our own LMS module for Care Connect and remove LearnDash and the other third-party course and registration plugins, including Restrict Content Pro."
     - "The result: $0 licences, SCORM built in, course pages designed to match Care Connect. Saves ~A$1,000-1,300 a year in licences." (saving figure pending Rob checking the Liquid Web portal: if Kadence Memberships is already in the US$350 bundle, the "Registration gate plugin ~US$300+" line goes and the saving drops)
   - Box has spare room; lead-in bold as now.

37. **[APPLIED v16]** **Explain "in-house" where it is first pitched** (Rob: chicken before the egg). Slide 14 subtitle -> "We write our own LMS module for Care Connect and remove LearnDash and the other third-party course and registration plugins, including Restrict Content Pro. HCPs get a smoother, more reliable site; Care gets new CPD courses faster and at lower cost." Slide 15 green box reverted to the single "In-house: $0 licences..." paragraph (undoes 36).

38. **[APPLIED v17]** **From Care's FY28 brief (CM/AJ notes, 8 Nov):**
   - New slide 14 "FY28 TARGETS" (after the CAPH0068 Opportunity slide): CTR 5%, traffic +5% YoY, registrations 349, CPD/stickiness/return visits tracked from Apr 2027, each mapped to the recommendations.
   - New slide 22 "RECOMMENDATION 5: Opening Care Connect to dietitians" (after Rec 4): tickbox swaps AHPRA for APD Registration ID; pending account; admin page, staff check the Dietitians Australia APD register by name + state and match the ID; approve = welcome email, decline = account + details deleted. ~12 hrs (form 2, pending 3, admin page + emails 4, role + section 1, testing 2), JAS '27 after Rec 1. To confirm with Care: content dietitians may see, CPD accreditation for dietitians, register used only to verify.
   - Budget: Rec 5 row ~12 / ~$1.7K; TOTAL ~708 hrs / ~$101.3K. Roadmap: Dietitian access row in JAS '27.
   - Not done (Rob to decide): conference pre/post emails line on the CRM slide.

39. **[APPLIED v18]** **Slide 14 FY28 targets: "What drives it" reads as something already running** (Rob). Rename column to "How we'll hit it in FY28" and reword rows as FY28 actions:
   - CTR: "Match each eDM to the right publisher; CRM emails (Rec 2)"
   - Traffic: "Personalised landing page (Rec 3); CRM emails (Rec 2)"
   - Registrations: "CPD in-house (Rec 1); audit eDMs; open sign-up to dietitians (Rec 5)"
   - CPD/stickiness/return visits: "CPD in-house (Rec 1); CRM plays (Rec 2); mobile fixes (Rec 4)"
   - Rest of the slide unchanged.

40. **[APPLIED v18]** **Slide 22 (Rec 5) subtitle: give the reason, not "the brief asks"** (Rob). New: "Dietitians are a new FY28 audience for Care and key to Hydralyte's GLP-1 and diabetes education. They have no AHPRA number, so we add a dietitian sign-up that Panwar staff check by hand against the Dietitians Australia register." (Brief: dietitians in expanded target audience; Hydralyte priority = own GLP-1; Dietitian Connection partner opportunity.)

41. **[APPLIED v18]** **Slide 22 (Rec 5): no on-hold, let dietitians straight in, review after** (Rob). Steps become:
   1. "Tick the box" (unchanged).
   2. "Straight in": "No AHPRA check. They can use Care Connect straight away, like any other HCP."
   3. "We check the register": "New dietitian sign-ups appear on an admin page. Panwar staff search the APD register by name and state and match the ID."
   4. "Remove bad actors": "No match: the account and details are deleted. If the system is ever abused, new sign-ups switch to approval-first."
   - Hours ~12 -> ~11 (form 2, skip AHPRA check 2, review page + delete email 3, role + section 1, approval-first switch 1, testing 2). Source line updated to match.
   - Budget row ~11 / ~$1.5K; TOTAL ~707 hrs / ~$101.2K. Roadmap bar "Sign-up + review · ~11 hrs".
   - Also v18: slide 14 title "what drives each one" -> "how we'll hit each one" (same reason as 39). TOTAL renders ~$101.1K (exact: 50 + 2 + 351 x $140).

42. **[APPLIED v19]** **Slide 22 (Rec 5) fixes** (Rob: Panwar is compliance, medical writers sit with us; step 2 too aggressive; step 4 must mention paused status; drop the register terms line).
   - What dietitians get: "Content Care's compliance team approves for them (GLP-1 and Hydralyte are the obvious start)" -> "Content approved for them by Panwar's medical team (GLP-1 and Hydralyte are the obvious start)".
   - To confirm: "Which content dietitians may see: some product content may be limited to certain health professionals" -> "Which content dietitians can see: Panwar's medical team to recommend, Care to agree".
   - To confirm: DELETE "The register is used only to verify, never for marketing (its terms of use)".
   - Step 2: "Straight in" / "No AHPRA check. They can use Care Connect straight away, like any other HCP." -> "Access from day one" / "A dietitian gets the same access as any other HCP when they sign up, and every new sign-up is checked (step 3)."
   - Step 4: "No match: the account and details are deleted. If the system is ever abused, new sign-ups switch to approval-first." -> "No match: the account and details are deleted. If sign-ups are ever abused, new dietitian accounts start paused, with no access until we approve them."
   - Source line: "approval-first switch ~1" -> "paused-account switch ~1".

43. **[APPLIED v20]** **Slide 22 (Rec 5): no "medical team"** (Rob: Panwar has no medical team; "recommend / agree" wording messy).
   - What dietitians get: "Content approved for them by Panwar's medical team (GLP-1 and Hydralyte are the obvious start)" -> "Content chosen for them by Panwar (GLP-1 and Hydralyte are the obvious start)".
   - To confirm: "Which content dietitians can see: Panwar's medical team to recommend, Care to agree" -> "Which content dietitians can see".
   - (Rob: "to confirm with Care" sounds AI) Heading "TO CONFIRM WITH CARE" -> "QUESTIONS FOR CARE"; bullets become questions: "Which content can dietitians see?" and "Do we want CPD for dietitians? It needs separate accreditation, as they log CPD with Dietitians Australia." (replaces the CPD bullet and the content bullet above).
