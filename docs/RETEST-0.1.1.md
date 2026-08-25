# VMS Sponsorships 0.1.1 Retest Plan

Use this after installing `vms-sponsorships-0.1.1-cleanup.zip` on staging.

## 1. Version and activation

- Confirm plugin activates cleanly.
- Confirm `wp option get vms_sponsorships_version` returns `0.1.1` after activation.

## 2. Placeholder CTA fallback

1. Clear `vms_sponsorships_inquiry_page_url`.
2. Open a page using `[vms_sponsor_event event_id="EVENT_ID"]` with no eligible sponsor.
3. Confirm the CTA URL uses `?vms_sponsor_apply=1&vms_sponsor_event_id=EVENT_ID`.
4. Click the CTA.
5. Confirm the built-in Sponsorship Application page renders the form without raw shortcode text.
6. Submit the form and confirm an application is created.

## 3. Configured inquiry page URL

1. Set `vms_sponsorships_inquiry_page_url` to a page containing `[vms_sponsor_apply]`.
2. Click an unsold sponsor CTA.
3. Confirm the URL includes `vms_sponsor_event_id`, `vms_sponsor_slot`, and `vms_sponsor_scope`.
4. Confirm the form hidden `event_id` matches the event from the CTA.

## 4. Decline note cleanup

1. Decline an application with a private reason.
2. Change the same application to `approved` or `waitlisted`.
3. Confirm `decline_reason_private` is now blank.

## 5. Assignment declined state

1. Create or edit an assignment and set status to `declined`.
2. Confirm the assignment does not render publicly.
3. Confirm a declined physical-banner assignment does not consume banner capacity.
4. Confirm a new confirmed physical-banner assignment can still be created if the only prior banner assignment is declined.

## 6. Regression checks

- Confirm confirmed/paid/fulfilled assignments still render.
- Confirm cancelled/archived/declined assignments do not render.
- Confirm sponsor impression/click metrics still use daily rollups.
- Confirm no VMS Sponsorships fatals or warnings appear in `wp-content/debug.log`.
