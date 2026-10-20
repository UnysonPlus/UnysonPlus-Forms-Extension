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

## Form Styles presets — field looks live on a preset, not on the element

`includes/styles/class-fw-forms-style-presets.php` (`FW_Forms_Style_Presets`, booted from `_init()`).
Theme Settings → Components → **Form Styles** is an `addable-box` stored under `form_style_presets` in the
theme-scoped Theme Settings store (read with `unysonplus_preset_store_get()`, like every Components library).
Each preset becomes `.formp-{slug}`; the Contact Form picks one with `form_style` (Style tab).

- **Forms owns all of it, through two filters** — `unysonplus_components_settings_options` adds the tab after
  Buttons, `unysonplus_global_css` appends the rules to the shared presets stylesheet. That filter is also an
  input of `unysonplus_preset_css_hash()`, so a preset edit writes a new presets file; nothing in core lists
  form presets. Forms off → no tab, no CSS, the element falls back to the stylesheet's own look.
- **Standard reproduces `static/css/frontend.css` exactly** (white, `#dbdfe4` border, 10px radius, 48px,
  16px sides, `#14161a` focus with an 8% ring), and the element's `form_style` defaults to the first preset.
  Atts are re-derived from the option defaults at render, so every existing form now wears Standard — which is
  why Standard must stay pixel-identical to the plain look. Change Standard's shipped values and every old form
  on every site changes with them.
- **Specificity ladder** (never rely on stylesheet order): frontend.css `.wrap-forms input:not()×4` (0,5,1) <
  preset `.formp-x .wrap-forms input:not()×4` (0,6,1), focus (0,7,1) < the element's one-off overrides, emitted
  with the unique class tripled (`.cf-x.cf-x.cf-x .wrap-forms …`, 0,8,1; labels quadrupled to beat the preset's
  main-label rule at 0,4,2). The overrides are the legacy per-form settings and still win when set.
- **Main label** = `div[class^="field-"]:not(.field-consent) > label` plus `.field-rating > legend`; choice
  labels sit deeper (`.options label`) and only take the colour. The validation script writes its error colour
  inline, so the preset's error colour is the one `!important` here.
- **Dark mode** colours are emitted again under `:root[data-bs-theme="dark"] .formp-x`; a form on a dark
  section uses a dark-ready preset (On Dark) instead — sections and site dark mode are different things.
- A stored style that no longer exists (renamed/deleted preset) is dropped at render (`is_valid()`), so the
  form falls back to the plain look rather than carrying a class nothing styles.

## Sending — background submit, accessible errors, after-sending, answer tags (2.0.68)

- **`static/js/submit.js` rides FW_Form's own JSON mode.** `FW_Form::_validate_and_save()` already answers a
  request with `X-Requested-With: XMLHttpRequest` as `{ success, data: { errors | save_data, flash_messages } }`
  instead of redirecting, so the script only posts `FormData` to the form's own page. A failed validation is
  `success:false` + `errors` (name → message); a mail failure is `success:true` with an `error` flash. Any
  unexpected reply falls back to `HTMLFormElement.prototype.submit` with `data-fw-no-ajax="1"` — never lose a
  submission to the script. The native `checkValidity()` runs first; server errors cover the rest.
- **One error pattern for both paths.** The same script adopts the server's `ul.fw-form-errors` after a reload.
  Inline `p.form-error` inside the field's `div.field-*`, `aria-invalid` on every control of the field,
  `aria-describedby` → the message, `.fw-form-error-summary` (role=alert, focused) for 2+ problems or a problem
  with no field; a single problem focuses its field. Editing a field clears its error. Colour comes from
  `--fw-form-error` (a Form Styles preset's error colour sets it).
- **Several forms on one page share ONE FW_Form (`fw_form`).** So `is_submitted()` is true for every one of
  them. `_frontend_form_render()` therefore (a) repopulates POST values only into the form whose
  `fw_ext_forms_form_id` + type were posted, and (b) reads `get_errors()` itself — which stops FW_Form printing
  its list above whichever form renders FIRST — and prints the list inside the sent form only. Without this the
  errors and the typed values landed on the wrong form (field names repeat across forms).
- **After Sending** (`confirmation_type` message | hide | page | url). `process_form()` returns
  `[ 'redirect' => url ]` for page/url, which the reload follows and JSON carries as `save_data.redirect`;
  "hide" is read client-side from `data-fw-confirm` on `.fw-contact-form` (views/form.php, from `extra_data`).
  The address is answer-tagged in URL mode and passed through `wp_validate_redirect()`.
- **Answer tags** (`includes/notifications/class-fw-forms-merge-tags.php`): `{shortcode}`, `{all_fields}`,
  `{site_name}`, `{page_title}`, `{page_url}`, `{date}`; escaped per destination (text / html / url); unknown
  tags stay visible; secret fields never fill a tag. Used by the subject, success message, address and the
  sender email. **Entry titles strip tags** so a form keeps one title.
- **"Email the sender"** (`class-fw-forms-action-autoresponder.php`): a form emails whatever address is typed
  in, so by default only NAME fields reach its tags (≤40 chars, links removed); the visitor's full answers need
  the explicit `action_autoresponder_answers` switch. The message arrives either as text or (through the
  builder) as HTML paragraphs — detect and treat each correctly; never `esc_html()` HTML.
- **Autofill hints** (`class-fw-forms-autocomplete.php`): email fields → `email`; text fields matched on the
  START of the label (anchored regexes) so "Event name" / "Email address" / "Name of your pet" get no person hint.

## Human checks and spam (2.0.68)

- **Turnstile / hCaptcha** share `_shared/class-fw-option-type-form-builder-item-captcha.php`: explicit render
  via `_shared/js/captcha.js` (`fwFormsCaptchaReady` onload; widget id kept in `data-fw-captcha-widget` so
  submit.js can `reset()` after a failure — tokens are single-use), keys in Forms settings as
  `{turnstile|hcaptcha}-keys`. **No keys → render nothing (an admin note), validate passes**; an unreachable
  provider also passes (the honeypot + rate limit remain). Unlike the old reCAPTCHA field, which blocks.
  Cloudflare's public test keys (`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`, fail:
  `2x…AA`) verify the whole path. Turnstile keeps the network busy: wait for `load`, not `networkidle`.
- **Spam** (`includes/spam/class-fw-forms-spam-filter.php`): verdict at `fw_ext_forms_validate_submission`
  priority 5 (before the form type mails), read with `is_spam( $form_id )`. A caught submission is **not
  refused**: success message, no notification (contact-forms checks before mailing), no actions (runner checks),
  entry stored with status `spam` + a "Marked as spam" reason row. `spam` is a real status: "All" and its
  count exclude it; bulk "Mark as spam" / "Not spam".

## The form-type picker and "describe a form" (2.0.69)

- `includes/picker/class-fw-forms-picker.php` enqueues `contact-form/static/js/starter-picker.js` on the
  builder's own `fw_ext_builder:option_type:builder:enqueue` (form-builder only) **once per page and never in
  AJAX** — the page builder runs that hook per element and inside its options request; building the starters there
  each time is what you must not do.
- **Getting the builder instance:** options modals build their content OFF-PAGE (the page builder prepares them
  long before they open), so `fw:option-type:builder:init` triggered on the element never reaches `document.body`.
  The script keeps builders from `fwEvents 'fw-builder:form-builder:items-loaded'` and sets each up when a
  MutationObserver sees its element on the page.
- **Loading = `rootItems.reset(items)` + `rootItems.trigger('builder:change')`** — reset alone never writes the
  hidden input. Settings (subject / button / thank-you) live on a lazily built tab: they wait until the field
  exists, and only replace a value still at its default.
- "Empty" = only the `form-header-title` item. Starters carry `desc`, `icon` (dashicon), `group`, `settings`.
- `includes/ai/class-fw-forms-ai-generate.php`: `wp_ai_client_prompt()` (availability cached 10 min in the
  `fw_forms_ai_ready` transient); `shape()` drops unknown types, choice fields with < 2 choices, passwords; forces a
  newsletter consent optional; normalises through `FW_Forms_Starters::normalize()` (now public).
- **Never call `get_pages()` / `WP_Query` inside an element's `options.php`.** It runs query hooks while options are
  being built, those hooks can build options again, and the page builder's `fw_ext_wp_shortcodes_data` request then
  recursed until PHP ran out of memory (every element modal on the site failed to open). Read the table directly
  (`fw_ext_contact_form_page_choices()`).

## The form library (2.0.70)

`includes/library/`: `FW_Forms_Library` (post type `fw_form`, private, `show_in_menu` = `fw-extensions`) and
`FW_Forms_Library_Admin` (list columns, Duplicate / Export / Import on admin-post, `wp_ajax_fw_forms_library_save`).

- **The editor IS the element's options.** `fw_post_options` attaches the contact form's `builder` and `settings`
  tabs (taken from `get_shortcode('contact_form')->get_options()`, minus `library_form`) to the post. So a saved
  form is edited with the same builder, Actions and texts, and a new one opens on the picker.
- **Element:** `library_form` (Form Fields tab). `FW_Forms_Library::apply_to_atts()` at the top of the shortcode's
  `_render()` merges the definition (`form` + `SETTING_KEYS` + every `action_*`) over the atts and sets
  `id = lib-<ID>`; Style / Advanced stay the element's. A deleted or empty saved form falls back to the element's
  own fields. Choices come from a direct `$wpdb` read — never WP_Query in options (see the picker section).
- **History:** `_mirror()` on `fw_post_options_update` writes `export_shape()` JSON to `post_content` (so WP
  revisions keep it); `wp_restore_post_revision` → `import_into()`. Re-entry is avoided by removing the hook around
  its own writes. Imports and AJAX saves run fields through `FW_Forms_Starters::normalize()`.
- **"Placed on"** is a LIKE over `fw:opt:ext:pb:page-builder:json` for `"library_form":"<ID>"`.
- The picker JS links instead of copying: a saved-form card sets the modal's `library_form` select; a linked
  element hides its builder behind `.fw-forms-linked`; "Save to the library" posts the builder JSON + the settings
  fields already rendered.

## Richer fields and steps (2.0.71)

All on the simple-item base (`_shared/`): `name`, `phone`, `address`, `section`, `page-break`, `range`,
`image-choice`, `ranking`, `likert`.

- **Multi-part values are stored as ONE readable string** (`get_value_from_item()`): name "First Last", address
  "street, city, …, country", ranking "1. A, 2. B", likert "Statement: answer; …". Entries, the email, answer tags and
  the spam filter then need nothing new. The parts are posted as `shortcode[part]`; `get_value_from_item()` has no
  item context, so anything it needs must be IN the posted keys — ranking and likert key by option TEXT (brackets
  turned into parentheses so they cannot split the array), never by index.
- `section` and `page-break` collect nothing and force width `1_1` (`fix_options`); they are on the skip lists in
  entries capture and the email view.
- **Steps** (`static/js/steps.js`): rows (`.wrap-forms > row`) are grouped between `.fw-form-step-break` rows; the
  header row stays above every step. Rows are hidden with `[hidden]` — the grid row's `display:flex` beat it until
  `.wrap-forms > [hidden] { display:none !important }`. Next = the browser's `checkValidity()` on the step's visible
  controls; the server still validates everything. `submit.js` fires `fw-forms:errors` / `fw-forms:sent`; steps
  open the step holding the first `[aria-invalid]`, and go back to step 1 after sending.
- **Field-look rules match every non-choice `input`** (base + Form Styles). A control that is not a text box (the
  range slider) needs an explicit `!important` reset including `appearance:auto`, or it is drawn as a text field or
  loses its track.
- Ranking without JS = a position dropdown per option; `fields.js` hides them behind Move up / Move down (live
  region announces the new position). Image choices: radios / checkboxes visually hidden, card is the label.

## Pre-fill, piping, floating labels, one question at a time, repeater (2.0.72)

- **Pre-fill** (`includes/prefill/class-fw-forms-prefill.php`, `FW_Forms_Prefill::values( $items )`): logged-in
  user → their CRM contact (`FW_CRM_Contacts::find_by_user`, if the CRM is active) → `$_GET[<field shortcode>]`, then
  filter `fw_ext_forms_prefill_values`. Used only when the form was NOT just posted (posted values win), and only
  for the form being rendered — `_frontend_form_render` gates on `$is_sent` so two forms on a page never swap values.
- **Answer piping** (`fields.js` `initPiping`): `{field_shortcode}` in a label / legend / section / help text shows
  that answer live. Text nodes only, inserted as text (never markup). Unknown `{tokens}` are left alone.
- **Floating labels** = Form Styles `label_position: floating` (preset "Floating Labels"). Detects "empty" with
  `:placeholder-shown`, so `fields.js` gives placeholder-less text boxes a single-space placeholder. The padding
  rule must outrank the preset's own `input:not()×4` padding (0,6,1), or the raised label overlaps the value.
- **One question at a time** = contact form `form_layout: conversational` → `data-fw-layout` on the wrapper (views/
  form.php) → `static/js/conversational.js` runs BEFORE `steps.js`: one answerable column per row, a step break
  between rows, hidden / honeypot inputs ride with the previous question, existing Page Breaks dropped. Enter =
  Next (a textarea still gets its newline). Without JS it is a normal form.
- **Repeating Group** (`items/repeater/`): posts `shortcode[n][<sub-field LABEL>]` (label keyed, brackets made
  parentheses — `get_value_from_item()` has no item context). `fields.js` adds from the `<template>`, removes,
  renumbers names / ids / "Guest n" so the server always sees 0…n-1, and hides Add at the maximum. Server checks the
  count, required sub-fields and each sub-field type. Stored as `1) Name: Ana, Age: 30; 2) …`.

## Notification switch, and booking-style add-ons (2.0.73)

- Contact form `email_notify` (`'yes'` default; in `FW_Forms_Library::SETTING_KEYS`): `'no'` skips the mail in
  `process_form()` but still flashes success, so the entry is stored and actions run.
- **A field + action pair from another plugin** (a booking engine's "Stay dates" + "Reserve the dates" is the
  reference): actions only receive each field's `get_value_from_item()` TEXT, so the field keeps its parsed value
  in a static during `frontend_validate()` (validate always runs before the save in the same request). The success
  message is built in `process_form()` BEFORE the actions run (priority 20 on `fw_ext_forms_frontend_submit`), so
  a reference meant for `{tags}` must be pre-allocated in the `fw_ext_forms_merge_tags` filter and reused by the
  action's `run()`.

## Prices, totals and payments (2.0.74, Phase 6 first batch)

Files: `includes/payments/` (`class-fw-forms-money.php`, `-formula.php`, `-order.php`, `-payments.php`,
`gateways/`), `includes/actions/class-fw-forms-action-payment.php`, items `product`, `amount`, `calculation`,
`static/js/calc.js`.

- **Never trust a browser figure.** `FW_Forms_Order::build( $items, $_POST )` works every line out from the form's
  definition: Product = price × qty (choices keyed by NAME), Amount = a suggested amount or their own (checked
  against min / max), Calculated with `add_to_total` = its formula. The Calculated field's hidden input is posted
  empty on purpose. `calc.js` mirrors the rules for the live display only.
- **Items get context** (since 2.0.74): `get_value_from_item( $value, $item, $all_posted )` — the builder passes the
  item and every posted value; old items declare only `$value` and ignore them. `FW_Forms_Order::use_items()` is
  called in `_frontend_form_validate()` / `_frontend_form_save()` so formulas can reach other fields.
- **Formula** (`FW_Forms_Formula`, same grammar in `calc.js`): numbers, `{field}`, `+ - * /`, brackets, `round(x[,d])
  ceil floor abs min max`. Hand-written parser; anything else = 0; division by zero = 0; loops stop (a formula that
  refers to itself counts 0). A field's number: Product line total, Amount, Calculated result, else the LAST number in
  its text (ticked boxes add up); a field hidden by a rule counts 0 (`aria-hidden="true"` in JS).
- **Payments table** `{prefix}fw_form_payments`: `order_lines` (NOT `lines`: a reserved word in MySQL; the
  installer stamps its version only once the table exists, so a failed create is retried).
  `FW_Forms_Payments::create / find / find_by_reference / find_by_gateway_ref / for_entry / set_status` (fires
  `fw_ext_forms_payment_status`, `fw_ext_forms_payment_paid`). Reference `F-XXXXX` is reserved in the action's
  `validate()` via `FW_Forms_Payments::prepare()` — the success message is built before actions run.
- **Gateways** (`FW_Forms_Gateway`, filter `fw_ext_forms_gateways`): `offline` (Pay later) now; `start()` returns
  `{ status: pending|paid|redirect }`. Admin "Mark paid / cancelled / refunded" on the entry (entries hooks
  `fw_ext_forms_entries_columns`, `fw_ext_forms_entries_column`, `fw_ext_forms_entry_panels`,
  `fw_ext_forms_entry_action`).
- Currency: Forms Settings → Payments (`payments_currency`), filter `fw_ext_forms_currency`.
- **Card payments (2.0.75):** `stripe` (Checkout Session, `payment` mode, one line "Order F-…", metadata
  `fw_form_payment`) and `paypal` (Orders v2 CAPTURE, `custom_id` = payment id). `start()` returns
  `{ status: redirect, redirect }`; the action calls `FW_Forms_Payments::set_redirect()` and Forms core, after the
  submit actions, swaps the form's redirect for it (`take_redirect()`, keeping the old one in `meta.after` for after
  paying) and clears the flash messages. Links carry `meta.key`: `?fw_form_payment=REF&k=KEY&do=success|cancel|pay`
  (`_return()` on `template_redirect`; `success` → `confirm_return()`, `pay` → a fresh `start()`; idempotency keys
  include `meta.attempts`). Webhooks `POST /wp-json/fw-forms/v1/webhooks/{gateway}` → `$gateway->webhook()`; Stripe
  signature check is the same as Membership's. Settings `payments_stripe_*` / `payments_paypal_*` fall back to
  Membership's `fw_ext_membership_payments` gateway settings (`FW_Forms_Payments::setting()`).
- **Coupons (2.0.76):** `FW_Forms_Coupons` (settings `payments_coupons` addable-box; `check()` returns the coupon or a
  message; uses in option `fw_ext_forms_coupon_uses`, counted on `fw_ext_forms_payment_created` from `meta.coupon`,
  released on cancelled / failed-unpaid). `FW_Forms_Order::build()` adds a `kind: discount` line from the visible Coupon
  field; a coupon's NUMBER in a formula is the discount, and it counts 0 while the order itself is being built
  (`$building` guard - otherwise a formula that uses the coupon and is added to the total would loop).
- **Recurring (2.0.76):** Product option `billing` (week|month|year) puts `interval` on its line; the order gets
  `intervals` (more than one = refused by the action) and `recurring { interval, amount, before }` (`amount` after a
  "forever" coupon). The action reserves the subscription reference in `validate()` (`add_to_pending('subscription')`),
  creates the payment (first charge = one-off + first period - discount) and a `FW_Forms_Subscriptions` row, then
  `start()` sees `meta.subscription_id`. Renewals are `FW_Forms_Subscriptions::renewal()` (idempotent by
  `meta.charge_ref`; created pending then set paid so the paid hooks fire). PayPal: the setup fee and first period can
  come as two sales - both count toward the opening payment until its amount is covered (`meta.sales_total`).
- **Testing card flows without keys:** a temporary mu-plugin answering `pre_http_request` for `api.stripe.com` /
  `api-m.sandbox.paypal.com` (the session URL = the success URL) runs the whole round trip in a browser; never leave
  it installed.

## Limits, signatures, webhook and create-post (2.0.77, Phase 7 first batch)

- **Textarea options arrive with `<br />`** for line breaks (the page builder encodes them in the element atts).
  Split them with `FW_Forms_Action::lines()`, never `preg_split( "\n" )` alone - the first seat rule silently
  failed to match until this was fixed.
- **Limits** (`includes/limits/`, keys `limit_*`, in `FW_Forms_Library::SETTING_KEYS`): `closed_reason( $form,
  $form_id )` = `not_open` | `closed` | `full` | ''. The contact-form shortcode saves the form settings
  (`_set_form_db_data`) BEFORE returning the closed message, so a submission from a page opened earlier is checked
  against the current settings. Seats are tallied from stored entries (transient per form, flushed on
  `fw_ext_forms_entry_stored`), exact choice text only; the form wrapper carries `data-fw-seats` for `fields.js`.
- **Signature**: posts `[image]` (PNG data URL, at most 400 KB, magic bytes + getimagesizefromstring checked) and
  `[typed]`; `_collect()` on `fw:ext:forms:collect-uploads` writes it to the private upload folder, so entries
  capture (`'file'` row), downloads and email attachments work as for File Upload.
- **Webhook** payload: `event`, `form_id`, `entry_id`, `submitted_at`, `page_url`, `answers`, `fields`, `order`;
  header `X-Unysonplus-Signature: sha256=` HMAC of the raw body; filter `fw_ext_forms_webhook_payload`.
- **Create a post**: meta keys starting with `_` are refused; the post carries `_fw_form_source { form_id,
  entry_id }`; action `fw_ext_forms_post_created`.

## Quiz scoring and Form Results (2.0.78, Phase 7 second batch)

- **Quiz** (`includes/quiz/`, keys `quiz_*` in `FW_Forms_Library::SETTING_KEYS`): `FW_Forms_Quiz::score( $form, $items,
  $posted )` -> `{ score, max, percent, outcome }`, cached per request in `current()`. Field-scoped rules win over
  global ones; the best possible score is one choice per single-choice field, every positive choice for checkboxes,
  and a typed question's best own rule. The redirect rides the new `fw_ext_forms_confirmation_url` filter (contact
  forms' `confirmation()`); the entry row comes from `fw_ext_forms_entry_before_insert` (type `quiz`, value
  "3 / 4 (75%) · Result"); the CRM tag runs at priority 30 on `fw_ext_forms_frontend_submit`, after the actions, and
  only tags a contact that exists.
- **Form Results** (`includes/reports/`, admin only, page `fw-form-results`): built from stored entries
  (`FW_Forms_Entries::each`), spam skipped. Choice-like types, `rating` (scale from the entry row's `scale`, else
  inferred; NPS for 0-10), `likert` (parsed back from "Statement: answer; …"), numbers, `quiz`, and the latest 5
  `text` / `textarea` answers. Personal fields (name, email, phone, address, files) are never summarised.

## Drafts, PDF copies, Google Sheets (2.0.79, Phase 7 last batch)

- **Drafts** (`includes/drafts/`, `static/js/drafts.js`, keys `drafts_enabled`, `drafts_days`): the form wrapper gets
  `data-fw-drafts`; answers are collected in the browser (no `fw_*` / `_wp*` / nonce / password / file / `[image]`
  names), POSTed to `/fw-forms/v1/drafts` (the form must have drafts on; 10 saves / hour / address; at most 64 KB),
  stored with `sha256(token)`; the emailed link `?fw_draft=<token>` refills by name (it presses a repeating group's
  Add until a copy exists) and adds a hidden `fw_draft_token`, which deletes the draft on `fw_ext_forms_frontend_submit`.
  Daily cron `fw_forms_drafts_cleanup`.
- **PDF** (`includes/pdf/`, keys `pdf_*`): `FW_Forms_PDF::render( { title, subtitle[], rows[ { label, value, image? } ],
  footer } )` writes a PDF 1.4 by hand (Helvetica / Helvetica-Bold, WinAnsi via iconv TRANSLIT//IGNORE, width table
  for wrapping, FlateDecode content, DCTDecode JPEG images; signatures re-encoded to JPEG on white with GD). Email
  PDFs are written to a temporary private folder and removed on `shutdown`; one PDF serves both emails. Verify a
  change by opening the file with PyMuPDF (`fitz`) and rendering a page to PNG - text extraction alone can look
  garbled in a Windows console even when the PDF is right.
- **Sheets** (`class-fw-forms-action-sheets.php`, setting `sheets_service_account`): token cached 50 min
  (`fw_forms_gsheets_*`); `GET '<tab>'!1:1` -> missing labels appended to the header (`PUT … valueInputOption=RAW`)
  -> `POST …:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS`. RAW, never USER_ENTERED: an answer starting
  with "=" must not become a formula.

## Analytics, unfinished entries, working with entries, scheduled export (2.0.80–2.0.81, Phase 8)

- **Analytics** (`includes/analytics/`, `static/js/track.js`): `POST /fw-forms/v1/track { f, e: v|s|a, l }` (text/plain
  JSON via sendBeacon) -> `FW_Forms_Analytics::bump()` (INSERT … ON DUPLICATE KEY into `fw_form_stats` /
  `fw_form_stat_fields`); submits from `fw_ext_forms_frontend_submit` (not spam). Refused: unknown form ids, bots
  (UA regex - **a headless test browser counts as a bot**: give Playwright a normal `userAgent`), logged-in editors,
  > 120 events / 10 min / address. Visitor Insights keys forms by builder item and counts page views, so its numbers
  are linked to, not merged.
- **Unfinished entries** (`class-fw-forms-partial-entries.php`, keys `partial_enabled`, `partial_notice`): status
  `partial` in the entries table (`STATUSES`); the default "All" query excludes `spam` and `partial`, and counts()
  ['all'] too, so limits / results / seats ignore them. `POST /fw-forms/v1/partial { form_id, token (24), answers }`
  rebuilds `$_POST` with `parse_str()` and runs the form builder's own value step; the visit's token maps to the
  entry id (transient); a real submission carries `fw_partial_token` and deletes it. Inserted without
  `fw_ext_forms_entry_stored`, so the CRM never sees it.
- **Working with entries** (`class-fw-forms-entry-notes.php`): columns `starred`, `assigned_to` (entries schema 1.1.0),
  table `fw_form_entry_notes`, all actions through `admin-post.php?action=fw_forms_entry_work&do=star|assign|note|
  reply|change` (nonce `fw_forms_entry_work_<id>`). Query args `starred`, `assigned`, `since`. Notes go with their
  entry (`fw_ext_forms_entries_deleted`). Playwright on this admin: pass `reducedMotion: 'reduce'` - the admin skin's
  view transitions freeze a headless page after a redirect.
- **Scheduled export** (`class-fw-forms-entries-schedule.php`): hourly cron `fw_forms_entries_digest`, `due()` = 7:00+
  site time, once per day (weekly: Mondays), option `fw_ext_forms_entries_digest_last`; it loads the entries-admin
  class itself (cron requests do not). Per-form `retention_days` beats the site-wide period in `_action_retention()`.

## Entries on the site: views, approval, editing (2.0.82–2.0.83, Phase 9)

- **Entry View** element `entry_view` (`extensions/contact-forms/shortcodes/entry-view/`, rendering in
  `includes/views/class-fw-forms-views.php`). Atts: `form_id`, `fields` (csv of field names), `title_field`,
  `layout` table|grid|list|map|calendar, `map_field`, `map_height`, `calendar_field`, `search`, `filter_field`,
  `detail`, `only_mine`, `per_page`, `order`, `empty_text`. Request params are per element: `fwv<n>_q|f|p|e|m`.
  **Privacy rules (keep them):** nothing shows unless the form's `views_allowed` is on; search and filter run in
  PHP over the shown fields only (a search must never match a hidden field); file fields are never shown; spam /
  partial / archived never; approved-only when the form approves. `only_mine` shows the sender's own entries
  whatever the approval (marked), except rejected. Map = Leaflet from unpkg (same version as the Map element),
  plus a text list of the same entries.
- **Approval** (`includes/workflow/class-fw-forms-approval.php`, keys `views_allowed`, `views_approval`,
  `views_approvers`): column `approval` ('' | pending | approved | rejected), set to pending in
  `fw_ext_forms_entry_before_insert`; the email links are `?fw_entry_approval=&do=&exp=&sig=` (HMAC over
  `id|do|exp`), handled on `template_redirect`; admin buttons go through `admin-post.php?action=fw_forms_entry_approval`.
  `decide()` writes an `approval` note and fires `fw_ext_forms_entry_approval`. List view `&approve=1`.
- **Editing** (`includes/views/class-fw-forms-entry-edit.php`, keys `edit_own`, `edit_lock`, `edit_days`): the
  entry keeps the posted answers in `raw_values` (no secrets, files, signatures, captchas); the link is
  `<entry page>?fw_edit_entry=<id>&k=<sig>`, and it only works for the logged-in sender. The submit is caught by
  the `fw_ext_forms_pre_process` filter (before `process_form`, so no email and no actions) and redirects to the
  same address; the form view marks the wrapper `data-fw-editing` so submit.js does not reset the form (a reset
  would bring the OLD answers back and a second send would undo the edit). `FW_Forms_Entry_Edit::editing()`
  turns off limits, closed dates, drafts and unfinished-entry tracking for that request.

## Notifications routed by answer, one form style everywhere (2.0.84–2.0.85, Phases 1–2 done)

- **Routing** (`includes/notifications/class-fw-forms-notifications.php`, Settings → Notifications, keys
  `email_routes`, `extra_notifications`, `confirmation_routes`, all addable-boxes of `{ field (label), op, value, … }`).
  Called from contact-forms `process_form()`: `route()` rewrites the main recipients (mode `replace` = first match
  wins, `add` = every match adds), `confirmation()` overrides After Sending for the request (message or url), and
  `send_extra()` runs after the spam check and BEFORE the "Send the notification email" switch (extras go even with the
  main one off). Matching reuses `FW_Forms_Conditions::matches()` / `norm()`. A rule whose field label is not found
  never holds — a typo must not reroute mail (the opposite of conditional visibility, where unknown = visible).
- **Shared form style** (`includes/styles/class-fw-forms-style-presets.php`): `targets()` (filter
  `unysonplus_forms_style_targets`, default `.wrap-forms`, `.fw-nl__form`, `.fw-mbr-login`) → the preset CSS scope is
  `:is(…)`, the same specificity as the old `.wrap-forms`. The classes are listed in Forms rather than added by each
  element because the presets stylesheet is generated before every element has loaded. `resolve( $choice )` turns
  `'default'` into the first preset. The Newsletter element has `form_style` (Design tab); Membership's
  `login_form()` wraps itself in `.fw-mbr-login-style .formp-*` (filter `unysonplus_membership_form_style`, default
  `'default'`). A preset's Custom CSS has `<` and `>` stripped: write descendant selectors.

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
