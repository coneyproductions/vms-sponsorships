# VMS Sponsorships Implementation Checklist

## Phase 1 — Foundation

- [x] Add premium add-on scaffold
- [x] Add database tables
- [x] Seed starter sponsorship packages
- [x] Add sponsor vendor subtype registration hook
- [x] Add sponsorship application model
- [x] Add sponsorship package model
- [x] Add sponsorship assignment model
- [x] Add sponsor asset model
- [x] Add sponsor metrics model
- [x] Add fulfillment checklist model
- [x] Add physical banner cap enforcement
- [x] Add manual payment status fields
- [x] Add admin dashboard
- [x] Add admin package screen
- [x] Add admin application review screen
- [x] Add admin assignment screen
- [x] Add admin asset review screen
- [x] Add settings screen

## Phase 2 — Public display and shortcodes

- [x] Add `[vms_sponsor_event]`
- [x] Add `[vms_sponsor_slot]`
- [x] Add `[vms_sponsor_placeholder]`
- [x] Add `[vms_sponsor_email]`
- [x] Add `[vms_sponsor_season]`
- [x] Add placeholder CTA rendering
- [x] Add approved sponsor rendering
- [x] Add lightweight impression tracking
- [x] Add click redirect tracking

## Phase 3 — Applications and assets

- [x] Add `[vms_sponsor_apply]`
- [x] Add public application form
- [x] Add operator application notification
- [x] Add sponsor application received email
- [x] Add approval/decline/waitlist email notifications
- [x] Add `[vms_sponsor_asset_upload]`
- [x] Add asset review workflow

## Phase 4 — Event Plan integration

- [x] Add Event Plan meta box for common event post types
- [x] Add sponsorship value tier field
- [x] Add expected attendance field
- [x] Add pricing multiplier field
- [x] Show physical banner count in event meta box
- [x] Show event sponsor shortcode in event meta box
- [ ] Replace hard-coded event post type list with VMS Core config if available
- [ ] Replace manual Event ID entry with VMS Event Plan picker

## Phase 5 — Sponsor portal

- [ ] Add sponsor portal route/page
- [ ] Show current sponsorships
- [ ] Show application status
- [ ] Show asset requirements
- [ ] Show payment status
- [ ] Show reports
- [ ] Add sponsor renewal/sponsor-another-event CTA

## Phase 6 — Payments

- [x] Manual payment status fields
- [ ] WooCommerce payment product/link generation
- [ ] Woo order to sponsorship assignment sync
- [ ] Payment confirmation notifications
- [ ] Refund/cancel handling

## Phase 7 — Reporting

- [x] Metrics table
- [x] Impression metric rollup
- [x] Click metric rollup
- [ ] Sponsor-facing report page
- [ ] Operator report builder
- [ ] Report export/share link
- [ ] GA4 optional integration

## Guardrails to preserve

- Sponsors require approval before payment links or public display.
- Declined sponsors never display publicly.
- Internal decline reasons never display publicly.
- Sponsor assets require approval before public use.
- Physical banners are capped and operator-controlled.
- Higher event tiers raise sponsorship value/pricing, not banner clutter.
- Reporting should describe visibility, not guaranteed business results.


## 0.1.1 staging cleanup completed

- [x] Blank placeholder CTA falls back to built-in sponsorship application route.
- [x] Application `decline_reason_private` clears when status changes away from `declined`.
- [x] Assignment status model includes `declined` as a non-display state.
- [x] Declined assignments do not count against physical banner capacity.
