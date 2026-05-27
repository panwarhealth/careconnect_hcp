# Rectogesic CPD Driver Pop-up — Implementation Brief

Scoped 2026-05-19. 12 hours across 6 tickets.

**Purpose:** Drive logged-in HCPs to the Rectogesic Mini Clinical Audit (LearnDash course 111793) via a one-time promotional pop-up.

---

## Scope and hours

| Ticket | Hrs |
|---|---|
| Pop-up manager — priority logic, consent modal integration | 2 |
| CPD driver pop-up component — HTML/CSS/JS, image slot, CTA, dismiss | 2 |
| Show-once logic — user meta tracking, logged-in gate | 2 |
| Copy — draft + iteration rounds | 2 |
| Design + layout | 2 |
| Staging test — priority rules, show-once, cross-pop-up interaction | 2 |
| **Total** | **12** |

---

## Rules

- **Logged-in users only.** Never fires for guests.
- **Shows once per user, ever.** Dismiss counts as shown — "Remind me later" is cosmetic copy, not a snooze.
- **Defers to consent modal.** If the user still needs to set their marketing consent preference, consent modal takes priority. CPD pop-up is skipped entirely and becomes eligible on next page load (by which point consent is resolved).
- Pop-up manager handles priority — see `CONSENT_IMPLEMENTATION.md` → Pop-up manager section.

---

## Show-once tracking

User meta key: `hcp_popup_cpd_driver_shown`
Value: timestamp when pop-up was displayed.
Set on render, not on CTA click.

---

## Draft copy

```
Heading:    Your Rectogesic CPD Audit is waiting
Subhead:    Earn RACGP CPD points at your own pace
Body:       Complete the Rectogesic Mini Clinical Audit through Care Connect.
            Reflect on your patient outcomes and submit your findings — all in one place.
CTA:        Start the Audit →
Dismiss:    Remind me later
```

Copy subject to client feedback round. Condition name (anal fissures) deliberately omitted — brand name only.

---

## Design notes

- Marketty/promotional feel — distinct from the plain consent modal.
- Includes an image slot (product or course hero image — to be supplied by client).
- CTA links to course page: `/courses/mini-clinical-audit/` (confirm slug before build).
- Dismiss link closes the modal and sets the shown meta — user never sees it again.

---

## Dependencies

- Pop-up manager must be built as part of the consent modal work (see `CONSENT_IMPLEMENTATION.md`). Do not build this pop-up without the manager in place.
- Staging test must cover: consent modal takes priority when both are eligible, CPD pop-up fires correctly when only it is eligible, never fires twice.
