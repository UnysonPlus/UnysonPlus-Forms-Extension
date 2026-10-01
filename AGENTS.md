# Forms extension — agent notes

The drag-and-drop form builder (`form-builder` option type), the `contact-forms` sub-extension
that renders and mails a form, and — since 2.0.50 — **Entries**, the stored record of every
submission. This file holds the rules that are load-bearing; the kit doc
(`UnysonPlus-AI-Dev-Kit/docs/extensions/forms.md`) is the reference.

## The submit pipeline, and the one seam everything hangs off

`FW_Extension_Forms::_frontend_form_save()` validates, collects uploads, calls the form type's
`process_form()` (the contact form sends the email there), then fires **one** action:

```php
do_action( 'fw_ext_forms_frontend_submit', array(
    'id', 'type', 'instance', 'process_data', 'shortcode_to_item', 'builder_value',
    'form_values',   // shortcode => submitted value      (since 2.0.50)
    'attachments',   // absolute paths of uploaded files  (since 2.0.50)
) );
```

`form_values` and `attachments` were added for Entries. Before them a listener could see *that* a
form was submitted but not *what* — which is why nothing could be stored from this hook. Anything
that wants to react to a submission (Entries today; the CRM's subscribe action and webhooks later)
listens **here** and nowhere else. Do not thread new behaviour into the pipeline itself.

## Entries — stored submissions

`includes/entries/`, booted from `_init()`:

| File | Role |
|---|---|
| `class-fw-forms-entries-installer.php` | `dbDelta` schema + version (`fw_ext_forms_entries_db_version`). Same contract as the CRM installer: two spaces after `PRIMARY KEY`, `KEY` not `INDEX`, `$wpdb->prefix`. |
| `class-fw-forms-entries.php` | **Repository** — the only code that writes SQL. insert / find / query / each (chunked) / counts / forms / set_status / delete / delete_by_email / delete_older_than. |
| `class-fw-forms-entries-capture.php` | Listener on the submit hook; retention cron; WP privacy exporter + eraser. |
| `class-fw-forms-entries-list-table.php` | `WP_List_Table` (views, per-form filter, date window, search, bulk). |
| `class-fw-forms-entries-admin.php` | Unyson+ → **Form Entries**: list, single view, CSV export. Admin-only require. |

Rules:

- **One table, fields as JSON.** Every form has a different shape; a column per field would grow a
  column per form. The indexed things (`email`, `form_id`, `status`, `created_at`) are lifted out.
- **The label is captured at submit time**, per field (`{ id, type, label, value }`). That is what
  keeps an old entry readable after a form is edited — a renamed or removed field still shows what
  the person was actually asked. Never "resolve" labels from the current builder at read time.
- **An entry is stored whether or not the email went out.** Losing a booking because the mail server
  hiccuped is exactly the failure entries exist to prevent.
- **Honeypot / reCAPTCHA / header items are not fields** — `fields_from_submission()` skips them.
- **Form title comes through a filter**, `fw_ext_forms_entry_form_title`. Forms core only knows the
  id; each form type answers with something human — the contact form gives its email subject —
  because only it knows where that is stored. Forms core never reaches into a child's storage.
- **`current_post_id()` must not use `wp_get_referer()`**: it returns `false` whenever the referer is
  the current page, which a form posting to its own page always is. Raw referer, then request URI.
- **Every admin action runs on `load-{hook}` and redirects** (PRG) — bulk, single, and the CSV
  export, which streams and exits. Never act during render.
- **CSV cells beginning with `= + - @ \t` are prefixed with `'`.** Form values are attacker-controlled
  text and spreadsheets execute leading-`=` cells as formulas.
- **IP is off by default** (`entries_store_ip`). It is personal data under GDPR and most sites have
  no use for it. Retention (`entries_retention_days`, 0 = keep) runs daily on
  `fw_ext_forms_entries_retention`. Entries are registered with WordPress's Export / Erase Personal
  Data tools keyed by email, so a data request covers them.
- **The page builder re-derives every att from the current options at render** — this matters for
  anything storing builder values, and is why Entries store the *submitted* values with their
  labels rather than referencing the builder.

Hooks: `fw_ext_forms_store_entries` (bool), `fw_ext_forms_entry_before_insert` (return `array()` to
skip), `fw_ext_forms_entry_stored( $id, $entry, $payload )`, `fw_ext_forms_entry_form_title`,
`fw_ext_forms_entries_capability` (default `manage_options`).

## Field types — the newer items share one base and one canvas factory

`date`, `rating`, `hidden`, `consent` extend `_shared/class-fw-option-type-form-builder-item-simple.php`
and register on the canvas through `_shared/js/simple-item.js` (`fwFormBuilderSimpleItem(builder,
type, { preview })`). The original items each carry ~200 lines of identical plumbing; the base owns
that once, so a new field is only its options, render and validate. Existing items are untouched.

- **Date / Time** renders the NATIVE `<input type="date|time|datetime-local">` — no picker library.
  `min`/`max` accept ISO values or the word `today`; blackout weekdays ride on `data-blackout-days`.
  The browser enforces min/max as a convenience; **the server is the authority** and validates
  shape, bounds and blackout days. Values are ISO strings, which compare as plain strings.
- **Rating** is a radio group (one native radio per point), styled in CSS — stars (1–5) or pills
  (0–10 NPS, 1–10). Stars are emitted **highest first** and flipped with `flex-direction: row-reverse`
  so "every star up to the chosen one" is the checked input's LATER siblings; in row-reverse,
  `justify-content: flex-end` is the visual LEFT. The base `.wrap-forms label { display:block;
  width:100% }` rule must be reset on `.fw-rating__point` or every point wraps onto its own row.
  Stored value is the integer.
- **Hidden** carries a static value, a URL parameter, the page URL or the page title. A hidden
  value is **context, not authority** — sanitised on the way in, never a basis for a security
  decision. `get_value_from_item()` has no item context, so nothing is re-derived server-side.
- **Consent** is a dedicated TYPE (not a one-choice Checkboxes) so the CRM's subscribe action can
  find consent **by type, never by label**. `purpose` = terms / newsletter / other. A
  `purpose=newsletter` box is **forced optional** (`fix_options()`) — a newsletter opt-in must be a
  free choice. Unticked is stored as `''`, not dropped, so an entry records that the question was
  asked and declined. Entries record `purpose` on consent rows;
  `FW_Option_Type_Form_Builder_Item_Consent::newsletter_consent_given( $fields )` is the ONE way to
  ask "did they opt in" — an email field alone is never consent.
- The consent statement allows `<a>`/`<strong>`/`<em>`/`<br>` (privacy-policy link); its validation
  error uses the tag-stripped text.

Front-end styles for rating and consent live in `static/css/frontend.css`. When testing on a site
with the Asset Optimizer active, **purge `uploads/unysonplus/asset-optimizer/combined-*.css`** first —
a stale combine silently omits new rules (this cost time twice). Also: a 150 ms colour transition
means `getComputedStyle()` read immediately after a click returns the OLD colour.

## Actions — what a form does after a successful submit

`includes/actions/`. `FW_Forms_Action` (abstract: id, title, options, run) + `FW_Forms_Actions`
(registry via the `fw_ext_forms_actions` filter; runner on the submit hook at priority **20**, after
the entry capture at 10, so actions see `entry_id`). Forms ships **Store entry** (default ON — the
capture consults its per-form switch); the Newsletter CRM contributes **Subscribe to the newsletter**
(default OFF). The contact-form element gets a **Settings → Actions** tab built from every action's
options, each in its own group; option ids are prefixed `action_{id}_` so they never collide.

- A form type answers `fw_ext_forms_form_settings` with its saved element atts (contact-forms →
  its option row). Forms core never reaches into a child's storage — same seam as the entry title.
- **Actions are isolated**: an exception or WP_Error in one is caught, reported through
  `fw_ext_forms_action_failed`, and never stops the next action or reaches the visitor. The visitor
  already succeeded; what happens afterwards is the site's business.
- **The subscribe action's gate is consent BY TYPE** —
  `FW_Option_Type_Form_Builder_Item_Consent::newsletter_consent_given( $fields )`. Not an email
  field, not a label, not the action being on. It follows the CRM's double opt-in setting (pending +
  confirmation email when on), records `source=form`, the consent statement, and `form_id` /
  `entry_id` in meta, applies the chosen list (or the default) and the form's tags.

## Conditional visibility — one rule per field, enforced on the server

`includes/conditions/class-fw-forms-conditions.php`. Every field type gets a **"Show only when…"**
group (field label · is / is not / contains / has any value / is empty · value) through the item
base's `get_extra_options()` — the one method all items already call, so no item can be built
without it. The Hidden field opts out (`supports_conditions()`); header/reCAPTCHA never call it.

- **Two halves that must agree.** `static/js/conditions.js` toggles fields live from the rules the
  form emits as `<script type="application/json" class="fw-form-conditions">` (inside the form, so
  it is scoped to that form). The builder's `frontend_validate()` and `frontend_get_value_from_items()`
  call `FW_Forms_Conditions::is_visible()` — a hidden field is **neither validated** (a hidden required
  field is not required) **nor collected** (so a stale value from an old tab is never stored or
  emailed). The script is convenience; the server is the rule.
- **The controller is named by LABEL** (trimmed, tag-stripped, case-insensitive) — the only thing the
  form author can see. A rule whose controller cannot be found resolves **visible**: hiding a field
  because of a typo would silently lose data; showing one that should be hidden is the recoverable
  mistake. Chains resolve recursively (a hidden controller's value does not count).
- Client rules are keyed by the dependent's shortcode and point at the controller's **shortcode**
  (resolved server-side), so the script never has to match labels.

## Starter forms — the framework's template library, our starters

`FW_Option_Type_Form_Builder::_get_defaults()` sets **`template_saving => true`**: the Templates
panel, save-as-template, load, delete and JSON export/import are the builder extension's, scoped by
builder type (`fw:bt:f:form-builder:…`). Do not write a second library. Eight starters ride
`fw_ext_builder:predefined_templates:form-builder:full` from `includes/class-fw-forms-starters.php`:
Contact, Booking / Appointment, Quote request, Event registration, Support ticket, Feedback (CSAT),
NPS survey, Newsletter signup.

- **Authored as PHP, not frozen JSON**, and every item runs through its own item class
  (`get_value_from_attributes()`) in `normalize()` — the same code that loads a saved form — so a
  starter follows the option schema instead of becoming the one shape nothing else produces.
- **A newsletter opt-in is always a separate, optional Consent box with purpose=newsletter**, never
  bundled into the terms box and never pre-ticked. Starters never set a form's Actions — subscribing
  stays a decision the site owner makes on the Actions tab.
- Every starter begins with the `form-header-title` item (the builder expects it) and has an email
  field. The suite asserts all of this.

## What is deliberately NOT here

Entries are **submissions**, not people. A form submission never creates a Newsletter CRM subscriber
by itself — that needs an explicit consent field and the CRM's own subscribe action (Phase 3 of the
Forms plan), through double opt-in. Merging the two would put non-consenting addresses on a mailing
list. The CRM may *read* entries by email for a subscriber's activity panel; it does not write them.

## Testing

Regression fixture: `forms-entries.php` (scratchpad; run via `wp eval-file` against `localhost/`).
It drives the real `fw_ext_forms_frontend_submit` hook, isolates by `t_`-prefixed form ids and
`@example.test` emails, and cleans up after itself. A real HTTP submission (curl: fetch the page for
the nonce, POST the fields, expect 302) is the end-to-end check — Playwright fights the marketing
theme on `localhost/`; curl does not.
