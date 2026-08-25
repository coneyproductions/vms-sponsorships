# Changelog

## 0.1.27

Seasonal sponsorship assignment scope support before release-candidate packaging.

### Changed

- Added assignment coverage scope support so sponsorship assignments can now be saved as either a single-event placement or a season/date-range placement with an optional label plus start and end dates.
- Updated Applications -> Assignments, the assignment edit form, and assignment listings/dashboard summaries to show scope clearly while keeping the existing event picker flow for single-event assignments.
- Reworked public event-page banner assignment lookup to prefer an event-specific active assignment first, then fall back to an active seasonal/date-range assignment whose saved range covers the event's start date, and finally use the simplified unsold banner.
- Kept existing shortcodes compatible by leaving event-specific rendering in place and restricting the season shortcode to season/date-range assignment records.
- Kept seasonal/date-range matching conservative by limiting it to published events with a resolvable start date and skipping events marked cancelled/canceled through common event-status meta during public lookup.
- Added overlap blocking so event-specific and seasonal/date-range assignments with the same slot cannot silently cover the same event date, with especially explicit blocking for presenting/primary slots.

## 0.1.26

Final public-facing polish pass for the unsold sponsor banner.

### Changed

- Removed the unsold banner's redundant `Available` badge, `Sponsorship Inventory` eyebrow, and slot-availability status line so the public treatment now reads as a clean sponsorship opportunity instead of internal inventory UI.
- Kept the unsold banner focused on one headline, one concise supporting paragraph, and one CTA while preserving existing sponsor-assigned banner behavior and the current responsive artwork handling.
- Updated the default unsold banner supporting copy to the cleaner sponsor-facing message and safely refreshed existing installs only when they were still using the previous built-in default text.

## 0.1.25

Dashboard queue visibility follow-up for approved applications that still need assignment creation.

### Changed

- Updated the dashboard application metric and main queue panel to focus on all unlinked applications needing action instead of only new or pending review items.
- Preserved the `New / Pending Review` queue as an unlinked review-only group with the precise empty-state copy when nothing is waiting on review.
- Added an `Approved / Needs Assignment` dashboard group so approved applications without linked assignments stay visible and actionable without bringing already-linked records back into the pending queue.

## 0.1.24

Application queue, deep-link, and linked-assignment cleanup for release-candidate testing.

### Changed

- Replaced dashboard-to-Applications row links that relied on fragment targeting with query-based application highlighting so the target row can be scrolled into vertical view without the browser shifting the table horizontally.
- Added reusable Applications-table focus handling and a dedicated horizontal-scroll wrapper so highlighted rows land with the left side of the table visible while keeping a subtle highlight state.
- Removed the duplicate `View Assignment` button for linked applications by leaving the linked-assignment summary in the Assignment column and keeping the single navigation button in the Action column.
- Adjusted pending-application dashboard counts and lists to exclude applications that already have linked assignments, while preserving the full Applications queue for recordkeeping and status updates.

## 0.1.23

Hardening and release-readiness pass for real-world Sponsorships trial use.

### Changed

- Normalized touched admin and public request handling so slashed superglobal input is unslashed before sanitization, nonce verification is explicit in the mutation handlers, and invalid application/asset IDs no longer report success.
- Added a low-risk public honeypot plus short-lived duplicate-submission guards for sponsorship applications and front-end asset uploads to reduce spam and accidental double posts without changing the workflow.
- Tightened front-end asset uploads to require valid linked sponsorship context owned by the current user or an operator, removed SVG from the default allowed file types, and validated uploaded attachments before they are linked into Sponsorships records.
- Added repository-level validation for application-linked assignments and asset context integrity so missing or mismatched relationships are blocked even if another caller bypasses the current UI.
- Escaped surfaced asset filenames and direct file URLs on the Asset Review admin screen while keeping the existing preview/review workflow intact.

## 0.1.22

Assignment-screen event search fix plus clearer review/fulfillment defaults for sponsorship operators.

### Changed

- Reworked the assignment event picker search so typing now rebuilds the selectable list, keeps `No linked event` available, preserves the current selection during filtering, and shows an explicit no-results state instead of depending on hidden native `<option>` support.
- Expanded event search matching to include event title, multiple date formats, post type/context labels, and event/post IDs so operators can reach the right event without scrolling through the full list.
- Split assignment-level creative tracking from uploaded-asset review statuses so new assignments can safely default to `not_submitted` or `not_required` without polluting the actual Asset Review workflow.
- Added a dedicated `Review & fulfillment tracking` section on the assignment form, with helper copy clarifying that `Asset status` is for submitted sponsor artwork/logo review and `Fulfillment status` is usually updated later after placements or reporting are complete.
- Updated assignment defaults so `pending_review` is only used when the source application already has uploaded creative, while new fulfillment tracking continues to start at `not_started`.

## 0.1.21

Application-to-assignment workflow upgrade for Sponsorships intake operations.

### Changed

- Added direct `Create Assignment` and `Approve & Create Assignment` actions to the Applications review queue, with linked-assignment detection that swaps duplicate-create actions for `View Assignment`.
- Prefilled assignment creation from the source application so sponsor name, requested package, slot/context, event link, website, consideration defaults, in-kind value, and intake notes no longer need to be copied by hand.
- Replaced the raw event-ID entry requirement on the Assignments screen with a searchable event picker that preselects the application’s related event when available.
- Preserved application records during assignment creation, surfaced linked assignment status back on the Applications page, and limited automatic approval changes to the explicit `Approve & Create Assignment` path after successful assignment creation.
- Added repository-level duplicate guards for application-linked assignments and adjusted assignment save handling so hidden/non-rendered fields are no longer unintentionally cleared during normal edits.

## 0.1.20

Visibility-stats workflow and dashboard proof-point upgrade for Sponsorships.

### Changed

- Replaced the lower generic dashboard visibility snapshot with a maintained proof-point preview that shows live sponsor-facing stats, clear empty-state messaging, and freshness guidance instead of generic marketing copy.
- Added a dedicated `Visibility Stats` admin page with manual sponsor proof-point fields, internal notes, public `As of` date control, and save-time freshness tracking with `Updated recently`, `Needs review soon`, `Stale`, and `Not configured` states.
- Updated the public sponsor inquiry visibility section to use the maintained proof points plus explicit manually maintained `As of` messaging so sponsor-facing output no longer implies real-time platform analytics.

## 0.1.19

Dashboard row-link consistency follow-up for Sponsorships operator cards.

### Changed

- Normalized the row interaction pattern across dashboard applications, pending assets, and recent assignments so each row now uses the same full-row clickable treatment instead of mixing title-only links with row-level links.
- Kept the shared hover and keyboard-focus states aligned across those dashboard cards so the click target feels consistent anywhere operators scan the row-based detail panels.

## 0.1.18

Dashboard direct-open follow-up for Sponsorships application and asset rows.

### Changed

- Made each `New / Pending Applications` dashboard row a direct link to the matching row on the full Applications review page so operators can open visible pending items without taking an extra detour through `Full Queue`.
- Applied the same direct-open pattern to populated `Pending Assets` dashboard rows, linking each row into the existing Asset Review page instead of leaving only the queue button as a route into review work.
- Added clearer hover and keyboard-focus states plus target-row highlighting on the Applications and Asset Review pages so linked dashboard rows visibly open into the correct review context.

## 0.1.17

Dashboard layout and overflow-affordance refinement for Sponsorships operator cards.

### Changed

- Reworked the lower Sponsorships dashboard area into a deliberate two-column desktop grid so the four detail cards stay balanced instead of drifting into uneven rows.
- Replaced the dashboard-only applications, assets, and assignments tables with compact summary rows that surface the identifying details operators need without depending on horizontal scrolling inside cards.
- Added equal-height desktop detail cards plus overflow-only fade and `Scroll for more` cues so vertical scrolling is obvious when needed and visually absent when the card body fits.

## 0.1.16

Dashboard scroll-containment polish for Sponsorships operator cards.

### Changed

- Added internal scroll regions to the Sponsorships dashboard panels for pending applications, pending assets, active assignments, and visibility stats so a single long card no longer stretches the full dashboard vertically.
- Kept each dashboard panel header and action button row fixed above the scrollable body area, preserving access to `Full Queue`, `Open Review`, `All Assignments`, and settings links while operators review longer lists.
- Added sticky table headers plus internal horizontal scrolling for dashboard tables so dense assignment and application content stays readable without widening the full admin page.

## 0.1.15

Narrow-layout sponsor-banner artwork follow-up for the unsold sponsor banner system.

### Changed

- Fixed the narrow/mobile artwork frame so square and portrait creative no longer inherit the oversized wide-desktop artwork panel when the container-width switch selects the mobile asset.
- Added stronger container-query and viewport-fallback overrides for the mobile artwork classes, keeping the selected mobile creative centered inside a narrower frame instead of leaving a giant empty banner panel.
- Preserved constrained image proportions while keeping wide-banner creative full-width only when the layout is intentionally using a wide asset.

## 0.1.14

Final artwork-proportion follow-up for the unsold sponsor banner system.

### Changed

- Updated the public banner artwork sizing rules so images preserve their original proportions instead of stretching to fill the banner frame.
- Kept full-width fill behavior only for intentional wide-banner creative, while standard, portrait, logo, and personal-image artwork now centers inside the frame without skewing.
- Preserved the container-width artwork switching and wide-desktop fallback behavior introduced in the prior local iteration.

## 0.1.13

Container-width artwork switching follow-up for the unsold sponsor banner system.

### Changed

- Replaced the viewport-only public banner artwork swap with container-width behavior so desktop and mobile creative are chosen from the actual available banner width.
- Added CSS container-query rules for the sponsor banner, with a viewport media-query fallback for browsers that do not support container queries yet.
- Kept the mobile artwork visible only when the banner is narrow, while leaving wide desktop fallback artwork full-width and uncropped when no mobile image exists.
- Updated local versioning/documentation so the active plugin header reflects the latest sponsor-banner artwork switching behavior.

## 0.1.12

Mobile-readability workflow follow-up for the unsold sponsor banner system.

### Changed

- Expanded the default unsold banner helper copy so operators are told to use wide desktop creative, square or vertical mobile creative, and a mobile-specific asset whenever the desktop banner contains small text.
- Added richer selected-image summaries in Sponsorship settings showing image dimensions, aspect ratio, classification, and detected layout type for both desktop and mobile artwork.
- Added an inline admin warning when a wide desktop banner is selected without a mobile image so operators know the desktop fallback may be hard to read on phones.
- Added a dedicated `vms-sponsor-banner--mobile-wide-fallback` class so wide desktop artwork reused on narrow screens stays full-width and uncropped without changing shortcode names or duplicate-prevention behavior.

## 0.1.11

Artwork-aware layout follow-up for the unsold sponsor banner system.

### Changed

- Updated the public unsold sponsor banner renderer to inspect attachment width, height, and aspect ratio from WordPress attachment metadata before choosing the banner-artwork layout.
- Added wide, standard, and portrait artwork layout classes so wide desktop ad creative renders in a larger banner-creative area while square and portrait assets stay in the supporting-art frame.
- Kept mobile rendering responsive by using the selected mobile artwork on narrow screens when present and falling back cleanly to the desktop creative when it is not.
- Expanded the Sponsorship settings helper copy so operators know that wide desktop creative renders larger and that a mobile-specific asset is recommended when the desktop artwork contains small text.

## 0.1.10

Responsive artwork follow-up for the unsold sponsor banner system.

### Changed

- Replaced the single default unsold banner image setting with separate desktop and optional mobile artwork fields, both using hidden attachment IDs, media-picker previews, and explicit select/replace and clear controls.
- Added upgrade-safe fallback logic so an older saved single banner image automatically becomes the new desktop artwork on existing installs.
- Updated the public unsold sponsor banner renderer to use desktop artwork on larger screens, switch to the mobile artwork on narrow screens when present, and fall back to a text-only banner when no artwork is selected.
- Refined the sponsor banner CSS so desktop-only, desktop-plus-mobile, and no-artwork states all render intentionally without forcing wide artwork into a square crop.

## 0.1.9

Follow-up fixes for Sponsorship navigation placement and event-page banner behavior.

### Changed

- Moved the Sponsorship local nav directly under the page header and removed the duplicate dashboard button row below the status cards.
- Used the existing VMS admin registry `top_nav` metadata so only the main `Sponsorships` page stays in the Marketing & Social global nav while child pages remain registered and routable.
- Replaced the visible banner image attachment-ID editing experience with a standard media picker, hidden stored ID, preview, selected-image summary, and explicit select/replace and clear controls.
- Fixed automatic TEC event-page placement so the banner renderer wins over the legacy same-slot card output when automatic placement is enabled for the current event page.
- Kept the same saved unsold banner headline, body, CTA, and selected default image aligned across automatic placement, `[vms_sponsor_banner]`, and `layout="banner"`.

## 0.1.8

Admin/menu integration plus public event-page sponsor banner placement.

### Changed

- Registered Sponsorships under the existing VMS admin menu and VMS page registry when those core hooks are available.
- Kept a top-level `Sponsorships` fallback menu only for environments where the VMS parent menu is unavailable.
- Preserved the existing Sponsorships admin page slugs for dashboard, applications, assignments, packages, assets, and settings.
- Reworked the Sponsorships dashboard into an operator-facing overview with pending applications, pending assets, active assignments, sponsor visibility freshness, and quick links.
- Added a reusable public sponsor-banner renderer while preserving the existing card / placeholder shortcode output unless banner layout is explicitly enabled.
- Added default unsold sponsor-banner settings for headline, body, CTA text, CTA URL, optional media ID, and event-page placement mode.
- Added `[vms_sponsor_banner]` and `layout="banner"` support on the existing event/slot/placeholder shortcodes.
- Added conservative automatic placement on supported The Events Calendar single-event pages through `tribe_events_single_event_after_the_meta`, with duplicate suppression for the presenting-slot banner.
- Kept the implementation fully inside the Sponsorships plugin and documented the lack of a dedicated VMS Core public event marketing hook.

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
