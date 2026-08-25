# VMS Sponsorships

Premium VMS add-on for sponsorship applications, packages, event/season assignments, sponsor displays, controlled physical banner inventory, asset approvals, notifications, and lightweight reporting.

Version: `0.1.7`

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
- Basic admin dashboard
- Application review workflow with approve/decline/waitlist statuses
- Package management
- Assignment management
- Sponsor asset upload and review records
- Event Plan meta box/card support for common event post types
- Event sponsorship value tier fields
- Public sponsor display shortcodes
- Newsletter-safe sponsor shortcode
- Unsold sponsor placeholder CTA
- Sponsor inquiry landing page shortcode
- Public sponsorship application form
- Lightweight impression/click metrics rollup

## Shortcodes

```text
[vms_sponsor_event event_id="123"]
[vms_sponsor_slot event_id="123" slot="presenting"]
[vms_sponsor_slot event_id="123" slot="bar"]
[vms_sponsor_placeholder event_id="123" slot="presenting"]
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

If VMS Core uses different hook names, map these in the next integration pass.

## Installation

1. Copy `vms-sponsorships/` into `wp-content/plugins/`.
2. Activate **VMS Sponsorships** in WordPress Admin.
3. Visit **Sponsorships → Settings** and confirm the banner cap and placeholder copy.
4. Visit **Sponsorships → Packages** and review the seeded default packages.
5. Add `[vms_sponsor_inquiry]` to a dedicated sponsorship inquiry page and set that page URL in **Sponsorships → Settings**.
6. If you want a standalone form page, add `[vms_sponsor_apply]` there as well, or leave the inquiry URL blank to use the built-in application route.
7. Add `[vms_sponsor_event event_id="123"]` or `[vms_sponsor_slot event_id="123" slot="presenting"]` to pages, layouts, or newsletters.

## Next integration tasks

- Wire sponsor subtype to the actual VMS vendor account/profile model.
- Replace manual Event ID entry with Event Plan picker/search.
- Build sponsor portal dashboard view.
- Add WooCommerce payment link generation.
- Add richer sponsor reporting view/export.
- Add email template settings.
- Add MailPoet-specific rendering/testing if needed.
