# Changelog

## 0.1.7

Local-only sponsorship polish for the public inquiry page layout and sales copy.

### Changed

- Rebalanced the five sponsorship package cards into a deliberate desktop `2 + 3` layout so the final `In-Kind / Custom Sponsorship` card no longer sits alone on its own row.
- Refined the `Supporting Sponsor` description to read as the entry-level general visibility package instead of a reduced option.
- Strengthened the `Kids Admission Sponsor` and `Veterans Admission Sponsor` descriptions with clearer community-impact wording.
- Tightened the `In-Kind / Custom Sponsorship` description with concise examples such as food or beverage support, raffle items, signage, and service-based contributions.

## 0.1.6

Aligned the public sponsorship package flow and added lightweight sponsor-visibility messaging.

### Changed

- Unified the inquiry-page package cards and application dropdown to the same active public package list instead of mixing scope-filtered cards with an all-packages form dropdown.
- Added refreshed public copy for `Kids Admission Sponsor` and `Veterans Admission Sponsor`, plus a seeded `In-Kind / Custom Sponsorship` package for trade/custom inquiries.
- Added a manual sponsor-visibility section and settings for website reach, social reach, email reach, audience notes, and last-updated copy.
- Added upgrade logic so missing default packages and new settings are created on existing installs without overwriting customized package records.

## 0.1.4

Focused inquiry-form update for lightweight trade and in-kind sponsorship intake.

### Changed

- Added public application fields for sponsorship consideration type, trade / in-kind offer details, and optional estimated trade value.
- Persisted the new trade-interest fields on sponsorship applications for admin review and operator notification emails.
- Added inquiry-page copy clarifying that Serenade Range may review trade or in-kind sponsorships before any public recognition goes live.

## 0.1.3

Focused inquiry-page release candidate for sponsor CTA handoff, sales copy, and context preservation.

### Changed

- Added `[vms_sponsor_inquiry]` as a sponsor-facing landing page between unsold event CTAs and the application form.
- Refined the inquiry hero, reassurance copy, package cards, and front-end form styling so the flow reads like a sponsorship pitch instead of a raw form handoff.
- Persisted the requested slot key on sponsorship applications so event/slot/scope/package context survives into admin review.
- Added a safe upgrade for seeded `Presenting Sponsor` and `Supporting Sponsor` descriptions when those package records still use the original stock copy.

## 0.1.2

Focused cleanup release for email-safe rendering, asset review notifications, and sponsor display caching.

### Fixed

- Switched `[vms_sponsor_email]` to direct sponsor URLs so email-safe output no longer emits tracked redirect links or click-tracking endpoints.
- Added sponsor-facing notifications for asset review outcomes on `approved`, `rejected`, and `needs_revision`.
- Added lightweight cached reads for public assignment and approved-logo lookups, with cache invalidation on assignment, asset, and placeholder/settings updates.

## 0.1.1

Staging cleanup release based on the first functional/load test report.

### Fixed

- Changed the blank sponsor placeholder CTA fallback so it routes to the built-in sponsorship application page instead of adding query args to the event permalink.
- Standardized sponsor application CTA query args on namespaced `vms_sponsor_*` parameters and kept legacy `event_id`/`scope` handling for configured inquiry pages.
- Cleared `decline_reason_private` when an application moves from `declined` to another status, preventing stale private decline notes from remaining on approved/waitlisted applications.
- Added `declined` to assignment statuses as a non-display state and excluded declined assignments from physical banner-cap counts.

## 0.1.0

Initial foundation release.
