# VMS Sponsorships

Premium VMS add-on for sponsorship applications, packages, event/season assignments, sponsor displays, controlled physical banner inventory, asset approvals, notifications, and lightweight reporting.

When VMS Core is active, Sponsorships registers inside the VMS admin menu. If the VMS parent menu is unavailable, the plugin falls back to its own standalone `Sponsorships` admin menu so operator access is preserved.

Version: `0.1.28`

## 0.1.28 Backstage Venue Manager compatibility

- Prefer current `bvmgr_*` core providers while retaining guarded historical
  `vms_*` fallbacks for older supported installations.
- Register all seven administration pages through Backstage Venue Manager's
  canonical registry without also creating physical fallback menu rows.
- Preserve the standalone administration menu and TEC event-page placement
  fallbacks when the corresponding core providers are unavailable.

## 0.1.27 seasonal assignment scope support

- Added first-class assignment coverage scope so sponsorship assignments can now stay event-specific or cover a season/date range with an optional custom label plus start and end dates, while leaving the existing single-event workflow intact for current records.
- Updated public event-page banner lookup so it now prefers an event-specific active assignment for the slot, falls back to an active seasonal/date-range assignment when the event's start date falls inside that range, and otherwise keeps the simplified unsold banner placeholder.
- Kept existing shortcodes compatible by leaving single-event slot/banner rendering unchanged and limiting the season shortcode to season/date-range assignment records instead of unrelated event-specific records that share a `season_id`.
- Seasonal/date-range event matching is intentionally conservative: it only applies on published events with a resolvable event start date, and cancelled/canceled events flagged through common event-status meta are skipped for public banner matching.
- Added overlap protection so saving a seasonal/date-range assignment or an event-specific assignment with the same slot will be blocked when both would cover the same event date, with especially clear blocking for presenting/primary slots.
- Updated the Assignments admin form and listings to surface coverage scope clearly, including seasonal labels/date ranges in dashboard and assignment rows and keeping Application -> Assignment creation compatible with seasonal sponsorships.

## 0.1.26 unsold banner public copy polish

- Simplified the public unsold sponsor banner so it now renders only a single headline, one concise supporting paragraph, and one CTA instead of stacking extra status-style labels around the offer.
- Removed redundant `Available` and `Sponsorship Inventory` messaging from the unsold event-page banner while preserving the current sponsor-assigned banner treatment and responsive artwork behavior.
- Refreshed the default unsold banner body copy to a cleaner sponsor-facing message and safely updated existing installs only when they were still using the previous built-in default text.

## 0.1.25 dashboard assignment-ready application visibility

- Renamed the dashboard application action metric/panel to focus on all unlinked applications needing operator follow-up, not only new submissions.
- Kept the New / Pending Review group limited to unlinked applications that are still waiting on review, with the precise empty-state copy preserved.
- Added an `Approved / Needs Assignment` dashboard group so approved applications without linked assignments remain visible and clearly actionable without reintroducing already-linked records into the pending queue.

## 0.1.24 application queue and deep-link cleanup

- Replaced the Applications dashboard row deep link with query-based highlighting so operators land on the Applications page with the target row highlighted without the browser panning the table horizontally.
- Added application-row focus handling that scrolls vertically into comfortable view, keeps inline scroll nearest, and resets the Applications table wrapper back to the left edge.
- Removed the duplicate `View Assignment` button on linked applications by keeping the linked-assignment summary in the Assignment column and the single `View Assignment` action in the Action column.
- Updated the dashboard pending-application count/list to exclude applications that already have linked assignments while keeping full application history visible on the Applications page.

## 0.1.23 hardening and trial-readiness pass

- Hardened public application and asset-upload handlers so request data is unslashed before sanitization, public forms now include a honeypot guard, and accidental rapid duplicate submissions are throttled with short-lived duplicate fingerprints.
- Locked public asset uploads to valid linked assignment/application context owned by the current logged-in user unless the operator has Sponsorships management capability, and blocked unsafe default upload types like SVG.
- Added repository-side validation for linked application/assignment asset records so missing or mismatched relationships are rejected before data is saved.
- Tightened admin request handling and asset review output escaping, including explicit escaped attachment filenames and file links on the asset-review screen.

## 0.1.22 assignment search and tracking defaults

- Fixed the assignment screen event picker so typing now rebuilds the visible event list instead of relying on browser-specific hidden-option behavior.
- Expanded event search matching to cover title, multiple date formats, post type/context, and event/post ID while keeping `No linked event` available and preserving the current selection during filtering.
- Clarified assignment-level creative tracking with `Not submitted` / `Not required` defaults, helper text that explains when `Pending review` should be used, and a separate `Review & fulfillment tracking` section.
- Kept new assignment fulfillment defaults at `Not started` with helper copy that makes it clear those delivery states are usually updated later in the sponsorship workflow.

## 0.1.21 application to assignment workflow

- Added direct `Create Assignment` and `Approve & Create Assignment` routes from the Applications review screen so operators can launch assignment creation without copying application details by hand.
- Prefills assignment creation from the source application, including sponsor name, website, requested package, slot/context, linked event, payment/in-kind defaults, and the original intake details in internal notes.
- Replaced the raw event-ID requirement with a searchable event picker on the Assignments screen and preselects the related event when the application already carries event context.
- Detects existing assignments linked to an application, replaces duplicate-create actions with `View Assignment`, and enforces the same duplicate guard during save.
- Keeps application status unchanged during normal assignment creation and only marks the application approved after a successful `Approve & Create Assignment` save.

## 0.1.20 visibility stats workflow

- Replaced the lower generic visibility snapshot dashboard panel with a real visibility-stats preview that shows maintained sponsor proof points, a clear empty state, and freshness guidance.
- Added a dedicated `Visibility Stats` admin workflow with manual proof-point fields, internal notes, public `As of` date handling, and save-time freshness tracking.
- Updated the public sponsorship inquiry visibility section to use the maintained proof points and explicit manually maintained `As of` messaging instead of generic stock copy.

## 0.1.19 dashboard row-link consistency

- Normalized the row interaction pattern across the dashboard’s applications, pending assets, and recent assignments cards so each row now uses the same full-row clickable behavior.
- Kept the shared hover/focus treatment aligned across those cards so the dashboard no longer mixes full-row links with title-only links.

## 0.1.18 dashboard direct-open rows

- Made pending application rows on the dashboard open straight into the matching item on the Applications review page instead of requiring a separate trip through `Full Queue`.
- Applied the same direct-open behavior to populated Pending Assets rows so the dashboard can jump directly into the existing asset-review workflow.
- Added clearer clickable-row hover/focus styling plus highlighted target rows on the review pages to make the destination obvious after the jump.

## 0.1.17 dashboard layout refinement

- Reworked the lower Sponsorships dashboard cards into a balanced two-column desktop grid with uniform card heights for the four detail panels.
- Replaced the dashboard’s wide tables with compact summary rows for pending applications, pending assets, and recent assignments so the overview reads cleanly without horizontal scrolling.
- Added overflow-only fade and `Scroll for more` affordances so operators can tell when a dashboard card contains additional content below the fold.

## 0.1.16 dashboard scroll containment

- Added internal scroll regions to the Sponsorships dashboard panels so long application, asset, assignment, and visibility lists stay contained inside their own cards.
- Kept panel headers and action buttons visible above the scroll area, with sticky table headers where practical for the dashboard tables.
- Allowed horizontal scrolling inside dashboard cards when table content runs wide instead of stretching the full admin page.

## 0.1.15 narrow-layout artwork frame fix

- Fixed the narrow sponsor-banner artwork treatment so a selected square or portrait mobile creative no longer sits inside the oversized wide-desktop artwork frame.
- Added stronger narrow-layout overrides for the mobile artwork classes so the container-width switch now swaps both the image and the surrounding artwork panel treatment.
- Kept image proportions constrained while allowing mobile creative to size naturally inside a narrower, centered artwork frame.

## 0.1.14 constrained artwork proportions

- Tightened the public sponsor-banner artwork sizing rules so images keep their natural proportions instead of stretching to fill the frame.
- Limited full-width fill behavior to intentional wide-banner variants, while standard, portrait, logo-style, and personal-image artwork now centers inside the artwork frame without skewing.
- Kept the existing narrow-container/mobile artwork switching behavior intact while preventing distorted fallback rendering.

## 0.1.13 container-width artwork switching

- Reworked the public unsold sponsor-banner artwork switch so desktop and mobile creative are chosen from the available banner width instead of a viewport-only `<picture>` rule.
- Added container-query behavior so narrow desktop content columns can use the selected mobile artwork when available, even if the browser window is still technically on a desktop device.
- Kept a viewport media-query fallback for browsers without container-query support, while preserving the wide desktop full-width fallback when no mobile artwork exists.
- Verified the active plugin header/version now reflects the latest local banner-switching logic.

## 0.1.12 mobile-readable banner artwork workflow

- Expanded the unsold sponsor-banner artwork settings with clearer mobile-readability guidance for desktop and mobile creative, including stronger recommendation text for separate mobile artwork when desktop banners contain text.
- Added richer selected-image summaries in the admin that show dimensions, aspect ratio, classification, and detected layout type for both desktop and mobile artwork.
- Added an admin warning when a wide desktop banner is selected without a mobile image so operators know the fallback may be hard to read on phones.
- Added an explicit mobile-wide-fallback class on the public banner renderer so wide desktop artwork reused on phones stays full-width and uncropped instead of collapsing into a tiny decorative frame.

## 0.1.11 artwork-aware banner layouts

- Made the unsold sponsor banner renderer inspect WordPress attachment dimensions so wide desktop artwork can render as a larger banner creative instead of being squeezed into the smaller supporting-art frame.
- Added artwork-aware layout classes for wide, standard, and portrait treatments, with wide desktop creatives rendering in a full-width creative area while square and portrait assets stay in the supporting-art layout.
- Kept mobile artwork responsive by reusing the selected mobile image on narrow screens when present and falling back cleanly to the desktop creative when it is not.
- Expanded the settings helper copy to explain how wide desktop creative, square/portrait artwork, and mobile-specific ad creative are rendered.

## 0.1.10 responsive banner artwork

- Split the default unsold sponsor-banner artwork setting into a desktop banner image plus an optional mobile banner image, both managed through the WordPress media picker with previews and clear actions.
- Added upgrade-safe fallback behavior so the older single default banner image is preserved as the new desktop artwork on existing installs.
- Updated the public unsold sponsor banner renderer to use desktop artwork on larger screens, mobile artwork on narrow screens when available, and a deliberate text-only banner when no artwork is selected.
- Refined banner CSS so the ad-style layout handles wide desktop artwork, optional mobile artwork, and no-art text-only states without forcing awkward square crops.

## 0.1.9 nav and banner follow-up fixes

- Moved the Sponsorship local module nav directly below the page header and removed the duplicate button row below the dashboard cards.
- Kept only the main `Sponsorships` entry in the VMS global Marketing & Social nav by using the existing registry `top_nav` metadata for child pages.
- Reworked the default banner image setting to use a normal media picker with hidden stored attachment ID, preview, and selected-image summary.
- Fixed automatic TEC event-page placement so the banner path takes precedence over the legacy same-slot card output when auto placement is enabled.
- Kept the new banner renderer, saved unsold banner settings, and selected default banner image aligned across `[vms_sponsor_banner]`, `layout="banner"`, and automatic placement.

## 0.1.8 event-page sponsor banners

- Added a reusable public sponsor-banner renderer alongside the existing card / placeholder renderer.
- Added configurable default unsold sponsor-banner settings for headline, body, CTA text, CTA URL, optional media, and event-page placement mode.
- Added `[vms_sponsor_banner]` plus `layout="banner"` support on the existing event/slot/placeholder shortcodes so the new ad-style layout is opt-in.
- Added conservative automatic event-page placement on supported The Events Calendar single-event pages through `tribe_events_single_event_after_the_meta`.
- Added duplicate suppression so the same presenting-slot sponsor banner does not render twice on a single event page.

## 0.1.7 sponsorship polish

- Rebalanced the five public sponsorship cards so desktop now lands on a deliberate `2 + 3` layout instead of orphaning the final card on its own row.
- Tightened the public package messaging for `Supporting Sponsor`, `Kids Admission Sponsor`, `Veterans Admission Sponsor`, and `In-Kind / Custom Sponsorship`.
- Kept the inquiry dropdown, package cards, and in-kind form behavior aligned with the same underlying package records.

## 0.1.6 package alignment and sponsor visibility

- Aligned the public sponsorship package cards and application dropdown to the same active package list.
- Added public-facing `Kids Admission Sponsor`, `Veterans Admission Sponsor`, and `In-Kind / Custom Sponsorship` coverage to the inquiry flow.
- Added a lightweight sponsor-visibility section with manual settings for website visibility, social reach, email reach, audience notes, and last-updated copy.
- Added upgrade logic so new default packages and sponsorship settings are available on existing local installs without reseeding everything.

## 0.1.4 trade inquiry support

- Added lightweight trade / in-kind inquiry support to public sponsorship applications.
- Sponsors can now indicate cash, trade / in-kind, cash + trade, or “not sure yet” consideration.
- Added optional trade-offer details and estimated trade value fields to application storage, admin review, and operator notifications.
- Added inquiry-page reassurance that select trade sponsorships can be reviewed before any recognition goes live.

## 0.1.3 inquiry flow

- Added `[vms_sponsor_inquiry]` as a sponsor-facing landing page shortcode that can show event context, package cards, and the application form lower on the page.
- Improved public inquiry/application styling so the sponsor form sits on a readable front-end surface instead of inheriting raw theme backgrounds.
- Preserved event, scope, package, and requested slot context through inquiry-page submissions, including admin/operator visibility.
- Added safe upgrade logic for the seeded default `Presenting Sponsor` and `Supporting Sponsor` descriptions when those records still use the original stock copy.

## 0.1.2 cleanup

- `[vms_sponsor_email]` now renders direct sponsor URLs instead of tracked redirect links.
- Sponsor-facing asset review emails now send on `approved`, `rejected`, and `needs_revision`.
- Public sponsor display queries and approved-logo lookups now use lightweight cached reads with explicit invalidation on assignment, asset, and settings changes.

## First-pass capabilities

- Sponsor vendor subtype registration hook
- Sponsorship package records
- Sponsorship application records
- Sponsorship assignment records
- Event-level physical banner cap logic
- Manual payment status
- Fulfillment checklist generation from package templates
- Operator-facing admin dashboard with VMS menu integration and standalone fallback
- Application review workflow with approve/decline/waitlist statuses
- Package management
- Assignment management
- Sponsor asset upload and review records
- Event Plan meta box/card support for common event post types
- Event sponsorship value tier fields
- Public sponsor display shortcodes
- Public sponsor banner renderer and automatic TEC event-page placement
- Newsletter-safe sponsor shortcode
- Unsold sponsor placeholder CTA
- Sponsor inquiry landing page shortcode
- Public sponsorship application form
- Lightweight impression/click metrics rollup

## Shortcodes

```text
[vms_sponsor_event event_id="123"]
[vms_sponsor_event event_id="123" layout="banner"]
[vms_sponsor_slot event_id="123" slot="presenting"]
[vms_sponsor_slot event_id="123" slot="bar"]
[vms_sponsor_slot event_id="123" slot="bar" layout="banner"]
[vms_sponsor_banner event_id="123" slot="presenting"]
[vms_sponsor_placeholder event_id="123" slot="presenting"]
[vms_sponsor_placeholder event_id="123" slot="presenting" layout="banner"]
[vms_sponsor_email event_id="123" slot="presenting"]
[vms_sponsor_season season_id="2026" slot="presenting"]
[vms_sponsor_inquiry]
[vms_sponsor_apply event_id="123"]
[vms_sponsor_asset_upload assignment_id="456" asset_type="logo"]
```

## Physical banner rule

The default setting is:

```text
Max physical sponsor banners per event: 1
```

This is intentionally conservative so Serenade Range can keep sponsor signage premium and avoid visual clutter.

## Display rules

Public sponsor shortcodes only render assignments with:

- status: `confirmed`, `paid`, or `fulfilled`
- `public_display_enabled = 1`

If no eligible sponsor is assigned and placeholders are enabled, the shortcode renders a tasteful “Sponsor this event” CTA.

The new banner layout is opt-in and does not replace the existing card / placeholder output unless:

- you use `[vms_sponsor_banner]`
- you pass `layout="banner"` to an existing event/slot/placeholder shortcode
- or you enable automatic event-page placement in **Sponsorships → Settings**

Automatic event-page placement currently targets the `presenting` slot. When no active assignment exists for that slot, the configured unsold sponsor banner is shown instead.

The default unsold sponsor banner can optionally use separate desktop and mobile artwork. The renderer now inspects the selected attachment dimensions to choose a layout:

- Wide desktop artwork renders as a larger banner creative instead of a small supporting-image box.
- Square or portrait artwork renders as supporting artwork beside or above the banner copy.
- If no mobile artwork is selected, the desktop image is reused on narrow screens.
- If both artwork fields are empty, the banner still renders as a polished text-only sponsorship CTA.

When the desktop artwork is wide and no mobile-specific image is selected, the admin now shows a readability warning because text-heavy desktop banner creative may still be difficult to read on phones even when the layout remains uncropped.

When both desktop and mobile artwork are selected, the public banner now switches between them based on the banner container width. That means a narrow desktop content column can use the mobile creative without relying on `wp_is_mobile()` or device-class guessing.

## VMS Core integration hooks

The add-on emits:

```php
do_action('vms_register_vendor_type', array(...));
do_action('vms_sponsorships_operator_notification', array(...));
do_action('vms_sponsorships_sponsor_notification', array(...));
```

The Event Plan card listens for:

```php
do_action('vms_event_plan_after_modules', $event_id);
```

Automatic public event-page banner placement currently uses The Events Calendar hook:

```php
do_action('tribe_events_single_event_after_the_meta');
```

An ideal future VMS Core contract would expose a public event marketing hook such as:

```php
do_action('vms_public_event_after_primary_meta', $event_id);
```

That would let marketing add-ons place modules without depending directly on TEC template hooks.

## Installation

1. Copy `vms-sponsorships/` into `wp-content/plugins/`.
2. Activate **VMS Sponsorships** in WordPress Admin.
3. Visit **VMS → Sponsorships → Settings** when VMS Core is active, or **Sponsorships → Settings** if the fallback standalone menu is being used.
4. Visit **VMS → Sponsorships → Packages** and review the seeded default packages.
5. Add `[vms_sponsor_inquiry]` to a dedicated sponsorship inquiry page and set that page URL in **VMS → Sponsorships → Settings**.
6. If you want a standalone form page, add `[vms_sponsor_apply]` there as well, or leave the inquiry URL blank to use the built-in application route.
7. Add `[vms_sponsor_event event_id="123"]` or `[vms_sponsor_slot event_id="123" slot="presenting"]` to pages, layouts, or newsletters.
8. If you want the ad-style treatment manually, use `[vms_sponsor_banner event_id="123"]` or `[vms_sponsor_event event_id="123" layout="banner"]`.
9. If you want the banner to inject automatically on supported TEC single-event pages, enable **Automatic event-page banner** in **VMS → Sponsorships → Settings**.

## Next integration tasks

- Wire sponsor subtype to the actual VMS vendor account/profile model.
- Replace manual Event ID entry with Event Plan picker/search.
- Build sponsor portal dashboard view.
- Add WooCommerce payment link generation.
- Add richer sponsor reporting view/export.
- Add email template settings.
- Add MailPoet-specific rendering/testing if needed.
