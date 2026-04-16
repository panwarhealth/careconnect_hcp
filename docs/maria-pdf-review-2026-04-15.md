# Maria's PDF review — 2026-04-15

Source PDF: `AUDIT UPDATES - URGENT 27.03.26 - reviewed 13.04.26.pdf`

Maria (Panwar Health CPD rep) annotated a walkthrough of the Mini Clinical Audit course and marked up every piece of copy / button / link that needs to change. 19 annotations total, 4 of which are her own "ACTIONED" resolution notes (not new work for us).

**Working agreement with Rob:** we do these **one at a time**. For each item:
1. Claude applies the fix (code or wp-admin config)
2. Claude describes the exact test Rob does on local to confirm
3. Rob confirms on the site + cross-checks against the PDF
4. Mark status `DONE`, move to next

Status legend: `TODO` · `IN PROGRESS` · `DONE` · `SKIPPED` (with reason)

---

## 1. Activity Evaluation — success message body copy

**Page 1, Highlight annotation.** When a user submits form 209 (Activity Evaluation survey) they should see:

> "Thank you for completing the Mini Clinical Audit and the Activity Evaluation survey. Your audit responses will now be evaluated by the education providers. Provided there are no issues with your responses, you will receive an email within the next four weeks with your Statement of Completion."

**Status: TODO (maybe already ACTIONED in code — needs verification)**

Copy already exists at `site/wp-content/themes/wp-spinnr-child/functions.php:3239` in a `frm_success_message` filter scoped to form 209. Need to verify it renders end-to-end on local.

**Fix:** verify the filter fires (no code change if it works).

**Test steps:** *[to be filled in when we get to this item]*

---

## 2. Activity Evaluation — success page button

**Page 1, Square annotation.** Replace whatever button is currently shown with:

> Button text: **"Back to course homepage"**
> Button links to: `https://hcp.carepharma.com.au/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/`

**Status: TODO**

---

## 3. Activity Evaluation — delete the "Back to Course" link

**Page 1, Line annotation.** "Delete this based on updating the button to the left" — remove the existing "Back to Course" link because item #2's new button replaces it.

**Status: TODO**

---

## 4. Course page — "Back to course homepage" hyperlink in state B

**Page 2, Text annotation.** When the user is on the MCA course overview page in state B (audit submitted but not yet approved), add a hyperlink:

> Link text: **"Back to course homepage"**
> Links to: `https://hcp.carepharma.com.au/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/`

**Status: TODO**

Uses the new state machine (`hcp_mca_get_state`) to detect state=B before injecting.

---

## 5. Audit form — remove "Start Survey" button from every step

**Page 3, Line annotation.** "Please remove this 'Start Survey' button from all steps of the audit as it should already be completed."

The Start Survey button is a LearnDash next-step navigation element rendered at the bottom of the audit lesson. Shouldn't appear inside the audit form steps.

**Status: TODO**

---

## 6. Audit form — add "Save and Continue Later" button on every step

**Page 3, Text annotation.** Users need to be able to save partial progress without walking the whole 5-step form to the end.

**Status: TODO (likely requires Formidable Save Drafts add-on — check license)**

This is a Formidable admin config change (no code), contingent on whether the Save Drafts add-on is licensed and installed.

---

## 7. Audit form — rename "Update" button to "Resubmit"

**Page 4, Square annotation.** "To avoid confusion, please change this to 'Resubmit'."

The label on the final submit button when the user is editing an already-submitted audit (their "resubmit" action).

**Status: TODO**

**Fix:** Formidable admin config — wp-admin → Formidable → Forms → form 161 → Settings → button label. No code change.

---

## 8. Audit form — state-B banner copy

**Page 4, Square annotation.** "Once the audit has been submitted, please have this copy change to:"

> "Your audit has been submitted for review. Provided there are no issues with your responses, you will receive an email within four weeks with your Statement of Completion. If you would like to make changes to your responses, do this directly in the form then click the 'Resubmit' button."

**Status: TODO**

---

## 9. Post-Resubmit — confirmation copy

**Page 5, Highlight annotation.** After user clicks the Resubmit button, show:

> "Your audit responses have been updated. Please expect to hear from the education providers soon."

Copy already exists at `functions.php:3265` in the `frm_main_feedback` filter for form 161.

**Status: TODO (verify existing filter works)**

---

## 10. Post-Resubmit — delete "Start Survey" button

**Page 5, Line annotation.** Same as item #5 but on the confirmation screen specifically. Existing JS at `functions.php:3270-3279` hides the LearnDash action bar via `display:none` — need to verify it works and extend the selector if needed.

**Status: TODO**

---

## 11. Post-Resubmit — add "Return to the audit homepage" hyperlink

**Page 5, Text annotation.** Add:

> Link text: **"Return to the audit homepage"**
> Links to: `https://hcp.carepharma.com.au/courses/mini-clinical-audit/`

Already exists at `functions.php:3266`. Verify.

**Status: TODO (verify)**

---

## 12. Post-Resubmit — delete "Back to Course" link

**Page 5, Line annotation.** "Delete based on adding a button above." Remove the LearnDash "Back to Course" link since #11's hyperlink replaces it.

**Status: TODO**

---

## 13–16. "ACTIONED" × 4 on Page 5

These are Maria's own notes marking items she considered already done on her end. **No new work for us** — but worth confirming with Maria on next call what exactly she meant.

**Status: N/A (her notes, not our items)**

---

## 17. Activity Evaluation revisit — "already completed" copy

**Page 6, Highlight annotation.** When a user clicks "Activity Evaluation" after they've already completed it, show:

> "This survey has already been completed."

**Status: TODO**

State-aware: fires when `has_eval_entry=true` on the user's state snapshot.

---

## 18. Activity Evaluation revisit — button change

**Page 6, Square annotation.** Change the button on the "already completed" page to:

> Button text: **"Return to the audit homepage"**
> Links to: `https://hcp.carepharma.com.au/courses/mini-clinical-audit/`

**Status: TODO**

---

## 19. Activity Evaluation revisit — delete "Back to Course" link

**Page 6, Line annotation.** Delete the "Back to Course" link since #18's new button replaces it.

**Status: TODO**

---

## Summary

| Item | Type | Status |
|---|---|---|
| 1 | Copy (existing filter — verify) | TODO |
| 2 | Copy (new button) | TODO |
| 3 | UI (delete link) | TODO |
| 4 | State-aware banner | TODO |
| 5 | UI (hide button) | TODO |
| 6 | Formidable admin config + Save Drafts add-on | TODO |
| 7 | Formidable admin config (button rename) | TODO |
| 8 | State-aware copy | TODO |
| 9 | Copy (existing — verify) | TODO |
| 10 | UI (existing JS — verify) | TODO |
| 11 | Link (existing — verify) | TODO |
| 12 | UI (delete link) | TODO |
| 13–16 | Maria's notes | N/A |
| 17 | State-aware copy | TODO |
| 18 | Link (new button) | TODO |
| 19 | UI (delete link) | TODO |

15 actionable items. 4 of those are Formidable admin clicks or existing-code verifications; the remaining 11 are new code (messaging + ui-cleanup in the `hcp-mca-review-workflow` plugin).

---

## Other findings (not from Maria's PDF — caught during dev/testing)

### C. Approve UX — separate "Submission Approved" checkbox on user profile

Maria currently ticks the Mini Clinical Audit (lesson) checkbox on the user profile to "approve" — but with our auto-completion changes, that checkbox now auto-ticks when the user submits form 161, so it can't double as her approval signal.

**Plan for Step 6 build:**
- Lesson + quiz checkboxes stay LearnDash-managed (auto-tick on respective form submits).
- Add a NEW custom checkbox to the user profile labelled **"Submission Approved"** (or "Audit reviewed and approved").
- When Maria ticks it: writes `hcp_mca_approved_at` / `hcp_mca_approved_by` / `hcp_mca_approved_audit_entry_id` usermeta → completion-guard releases → `learndash_process_mark_complete()` for course 111793 → cert + congrats email fire.
- Unticking should revert (delete the meta + revert course activity_status).
- Show greyed-out / disabled when user is not yet in state B (no audit + eval pair to approve).

### B. Quiz 116865 doesn't actually mark complete on form 209 submit

Confirmed live: after submitting form 209 the progress bar stays at 50% (audit lesson done, quiz still showing as not done).

The Formidable hook `submitted_form_mark_completed_quiz` IN `functions.php:2706` calls `learndash_process_mark_complete($user_id, 116865, false, $course_id)` for form 209. That function works for **lessons** but for **quizzes** LearnDash requires a `learndash_pro_quiz_statistic` row (a recorded quiz attempt) before considering the quiz "complete" for course-progress math. Just calling mark_complete on the quiz post id doesn't register an attempt → progress doesn't tick over.

**Effect:**
- Progress bar stays at 50% forever after both forms submitted.
- LearnDash never thinks course 111793 is fully done → wouldn't auto-complete even WITHOUT our guard.
- (Our guard is still useful for safety, but explains why so few prod users got auto-certed: the broken quiz-complete blocked the cascade for most.)

**Fix options (deferred, will do as part of step-7/8 retro work):**
- Manually create a `learndash_pro_quiz_statistic` row for the user on form 209 submit — registers a "passed" attempt with score 100%.
- Or simpler: bypass quiz completion entirely. Maria's Approve handler can mark quiz complete, lesson complete, course complete in one go; until then leave the quiz at 50% (not user-facing painful since they're heading to "submitted, awaiting review").

### A. Progress bar weights the eval quiz the same as the audit lesson

Course 111793 has two LearnDash items: lesson 112353 (the audit — 5 steps, 397 fields, hours of work) and quiz 116865 (the eval survey — a few quick questions). LearnDash's stock progress bar treats them as 1 of 2 / 2 of 2 = 50% each, regardless of actual content size.

**Effect:** a user who's done the audit but not the eval sees 50% — feels like they're halfway, when really they've done >95% of the work.

**Fix options (deferred):**
- Custom progress shortcode that weights by field count or by step count.
- Or: split the audit lesson into N child topics so it counts as N items in the LearnDash math.
- Or: leave it alone and accept the cosmetic weirdness.

Discuss when we get to UX polish; not blocking.
