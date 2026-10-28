<?php if (!defined('FW')) die('Forbidden');

$manifest = array();

/**
 * Changelog
 * -----------------------------------------------------------------------------
 * 2.0.85 - One form style for every form on the site (Forms roadmap Phase 1, last task). Form
 *          Styles presets now reach the Newsletter element (its new Form Style option: the
 *          design's own fields, the site's default style, or any preset) and Membership's login
 *          form (the site's default style; filter unysonplus_membership_form_style), with one
 *          preset CSS whose scope is :is(.wrap-forms, .fw-nl__form, .fw-mbr-login) - the same
 *          specificity as before (filter unysonplus_forms_style_targets for more containers).
 *          FW_Forms_Style_Presets::resolve( 'default' | class ) for elements that offer the
 *          choice. The Site Converter now turns a source form's field and label skin into a
 *          Form Styles preset the form wears, instead of a copy in the element's Custom CSS.
 *
 * 2.0.84 - Notifications routed by answer (Forms roadmap Phase 2, last task). A Notifications
 *          tab on the contact form: "send it to someone else, by answer" (when a field is /
 *          is not / contains / is empty / is not empty a value: instead of the main recipient,
 *          the first match winning, or as well as it), "more notifications" (each with its own
 *          recipients - answer tags such as {email_1} work - subject and message, optionally
 *          only when an answer matches; sent even with the main notification off), and "a
 *          different after sending, by answer" (a message or an address). Rules name fields by
 *          label and compare like conditional fields; a field that cannot be found never
 *          matches, so a typo cannot reroute mail. FW_Forms_Notifications; setting keys
 *          email_routes, extra_notifications, confirmation_routes (saved forms carry them).
 *
 * 2.0.83 - Senders edit their own entry (Forms roadmap Phase 9, last part). Settings -> On the
 *          site -> "Senders can edit their entry": a logged-in sender gets an Edit link in an Entry
 *          View, which opens the form page (?fw_edit_entry=&k=, signed) filled with the stored
 *          answers (entries keep them in the new raw_values column). Sending it updates that entry -
 *          no notification, no actions, a "change" note with old -> new - and on a form that
 *          approves first the entry waits for approval again. Editing stops once approved, after N
 *          days, or never (edit_lock). Limits and closed dates do not block an edit. New filter
 *          fw_ext_forms_pre_process (handle a submission before the form type does) and action
 *          fw_ext_forms_entry_edited.
 *
 * 2.0.82 - Entries on the site, with approval (Forms roadmap Phase 9). New Entry View element
 *          (entry_view): a form's entries as a table, cards, a list, a map (a "lat, lng" field,
 *          Leaflet) or a month calendar (a date field), with search, a filter field, pages and a
 *          page per entry; "only the visitor's own entries" for logged-in senders. Only forms that
 *          allow it (Settings -> On the site) and only the chosen fields are shown or searched;
 *          uploaded files never; spam, unfinished and archived entries never. With "Approve each
 *          entry" (on by default) new entries are pending and the approvers get an email with
 *          signed Approve / Reject links (30 days, no login); the entry screen has the same buttons
 *          and the list a "To approve" view. Entries schema 1.2.0 (user_id, approval, raw_values);
 *          FW_Forms_Entries::set_approval(), query args approval / user_id; action
 *          fw_ext_forms_entry_approval.
 *
 * 2.0.81 - Working with entries and scheduled export (Forms roadmap Phase 8, last batch). On an
 *          entry: a star, an assignee (who gets an email), team notes, a reply to the sender by
 *          email, and corrections to an answer - all recorded in the entry's history (table
 *          fw_form_entry_notes); the list gets a star column and the views "Starred" and
 *          "Assigned to me". Forms Settings -> Entries can email a CSV of the new entries every
 *          day or week (about 7:00; Mondays), and a form can keep its own retention period
 *          (Settings -> Options -> "Delete this form's entries after"), which wins over the
 *          site-wide one. Entries table schema 1.1.0 (starred, assigned_to); new hook
 *          fw_ext_forms_entries_deleted; FW_Forms_Entries_Admin::write_csv() is shared.
 *
 * 2.0.80 - Form analytics and unfinished entries (Forms roadmap Phase 8, first batch).
 *          static/js/track.js counts, per form and day, views (half the form on screen), starts
 *          (the first answer) and abandons (the page left with a started, unsent form, with that
 *          last field's name); submits are counted on the server. Counts only - no cookies, no
 *          answers; bots and logged-in editors are not counted (Forms Settings -> Entries ->
 *          analytics). Form Results gets a "How the form performs" card (saw / started / sent,
 *          where people gave up) and links to Visitor Insights -> Forms when it is active.
 *          Unfinished entries (Settings -> Options, off by default, with a notice under the form):
 *          what a visitor typed is kept as an entry with the status "Unfinished" (one per visit,
 *          updated as they type, deleted if they send the form), left out of "All", counts,
 *          limits, results and the CRM.
 *
 * 2.0.79 - Save and continue later, PDF copies, Google Sheets (Forms roadmap Phase 7, last batch).
 *          Settings -> Options: a "Save and continue later" button (static/js/drafts.js) emails a
 *          link that fills the form in again (table fw_form_drafts, token stored hashed, kept N
 *          days, deleted when the form is sent; passwords, files and signatures are never saved;
 *          REST /fw-forms/v1/drafts, rate-limited). PDF copies: a PDF writer with no library
 *          (A4, Helvetica, wrapped and paginated, a drawn signature as an image) - "Download
 *          PDF" on an entry, and optionally attached to the notification and the sender's
 *          confirmation (filters fw_ext_forms_notification_attachments and
 *          fw_ext_forms_autoresponder_attachments). New action "Send to a spreadsheet": Google
 *          Sheets through a service account (RS256 JWT, no SDK), header row kept in step, rows
 *          appended as plain values (RAW). process_form() now also receives `uploads`.
 *
 * 2.0.78 - Quiz scoring and survey results (Forms roadmap Phase 7, second batch). Settings -> Quiz:
 *          points per answer ("Paris = 1", or "field_name: answer = points" for one question;
 *          typed answers score too, case ignored), results by score range (title, message, a page
 *          to go to, a CRM tag for the sender's contact). Tags {quiz_score}, {quiz_max},
 *          {quiz_percent}, {quiz_result}, {quiz_message}; the entry gets a "Quiz score" row.
 *          Unyson+ -> Form Results: per form and period, answer counts with bars for choice
 *          fields, the average and NPS for ratings, a table per survey-grid statement, averages
 *          for numbers, quiz averages and result spread, latest answers to text questions, and
 *          entries per week; plain HTML, no chart library. New filter
 *          fw_ext_forms_confirmation_url; rating rows in entries keep their scale.
 *
 * 2.0.77 - Limits, signatures, webhooks and posts (Forms roadmap Phase 7, first batch). Settings ->
 *          Limits closes a form after a number of entries, before an opening date or after a
 *          closing date (the message replaces the form; a late submission is refused), and gives
 *          choices places ("Saturday workshop | 20": "3 places left", full choices switched off and
 *          refused; counted from stored entries). New Signature field: drawn (pointer events) or a
 *          typed name, the drawing checked as a real PNG and stored privately like a File Upload.
 *          New actions: Send to a webhook (JSON or form-encoded, optional HMAC-SHA256 signature
 *          header, wp_safe_remote_post) and Create a post (any type, draft by default, answer tags
 *          in title and content cleaned with wp_kses_post, custom fields, a featured image from a
 *          File Upload or Signature field). FW_Forms_Action::lines() reads textarea options, whose
 *          line breaks the page builder stores as <br />.
 *
 * 2.0.76 - Coupons and recurring payments (Forms roadmap Phase 6, last batch). Coupons are kept
 *          in Forms Settings -> Payments -> Coupons (percent or fixed, the first payment or every
 *          payment, a last day, a number of uses); a Coupon field checks a code live
 *          (GET /wp-json/fw-forms/v1/coupon, rate-limited) and the order gets a minus line worked out
 *          on the server; a use is counted when the payment is recorded and given back if it is
 *          cancelled or fails unpaid. A Product can be billed every week, month or year: the order
 *          then charges the one-off part plus the first period now and starts a subscription
 *          (table fw_form_subscriptions; statuses pending, active, past_due, cancelled, ended).
 *          Stripe: Checkout in subscription mode, the coupon as a Stripe coupon, renewals from
 *          invoice.paid. PayPal: a catalog product and a billing plan (setup fee / one-cycle trial
 *          for the discount), renewals from PAYMENT.SALE.COMPLETED. Every renewal is a new paid
 *          payment on the entry; cancel from the entry or with the payer's link
 *          ({subscription_cancel_link}); tags {subscription_ref}, {subscription_price}.
 *
 * 2.0.75 - Card payments: Stripe Checkout and PayPal (Forms roadmap Phase 6, second batch).
 *          The Take payment action sends the payer to the service's own page (Forms core follows
 *          FW_Forms_Payments::take_redirect() instead of the form's redirect; the success message
 *          waits) and back to the form's page, where the return is confirmed with the service and
 *          a notice shows ("Payment received… reference F-…", or "not completed… try again" with a
 *          link that starts a fresh payment page). Notifications at /wp-json/fw-forms/v1/webhooks/
 *          {stripe|paypal}, verified (Stripe signing secret with a 5-minute window; PayPal's
 *          verify-webhook-signature): paid, failed, refunded. Refund online from the entry. New
 *          Payment Method field (Card / PayPal / Pay later, only the ready ones shown, checked again
 *          on the server). Keys in Forms Settings → Payments, falling back to the ones saved in
 *          Membership → Payments. New tag {payment_link}; each payment has a secret key for its links.
 *
 * 2.0.74 - Prices, totals and Pay later (Forms roadmap Phase 6, first batch). New fields: Product
 *          (one item, or options with their own prices; bought always, when ticked, or in a typed
 *          quantity), Amount (suggested amounts and / or their own, with a minimum and maximum) and
 *          Calculated (a safe formula over other answers, {field} + - * / brackets round ceil floor
 *          min max, shown live by static/js/calc.js and worked out again on the server; as money it
 *          can be added to the total). FW_Forms_Order builds the order from the form's definition and
 *          the raw posted values, never from a browser figure. The "Take payment" action records a
 *          payment against the entry (table fw_form_payments: reference, method, status, lines);
 *          the first method is Pay later (bank transfer, pay on arrival), marked paid on the entry.
 *          Answer tags {order_total}, {order_summary}, {payment_ref}, {payment_method},
 *          {payment_instructions}; a Payment column and panel on Form Entries; Forms Settings →
 *          Payments → Currency; Order form and Donation starters. Items now receive their own
 *          definition and every posted value in get_value_from_item(); entries screens gained the
 *          hooks fw_ext_forms_entries_columns / _column, fw_ext_forms_entry_panels and
 *          fw_ext_forms_entry_action.
 *
 * 2.0.73 - "Send the notification email" switch on the contact form (Settings, email_notify,
 *          default on; travels with saved forms). Off, the form only stores the entry and runs
 *          its actions, for forms whose action already tells the owner (a booking action with
 *          its own alert) or where stored entries are enough.
 *
 * 2.0.72 - Pre-fill, answer piping, floating labels, one-question layout and a Repeating Group.
 *          Fields can be pre-filled from the logged-in user, their CRM contact or a URL
 *          parameter named after the field (filter fw_ext_forms_prefill_values). A label, legend,
 *          section or help text holding {field_name} shows that answer live as the visitor
 *          types. Form Styles adds a Label position option and a Floating Labels preset. The
 *          contact form's Layout option "One question at a time" (static/js/conversational.js)
 *          shows one field per step, with Enter moving on. The new Repeating Group field lets a
 *          visitor add copies of a few sub-fields ("Add another guest") within a minimum and
 *          maximum, checked on the server and stored as "1) Name: Ana, Age: 30; 2) ...".
 *
 * 2.0.71 - Longer forms and richer fields. New field types: Name (full, or first and last),
 *          Phone (type tel, 6-20 digits), Address (street, line 2, city, region, postcode,
 *          country, each with its autocomplete token; required = street, city, country),
 *          Section (heading + text, collects nothing), Page Break (splits a form into steps:
 *          static/js/steps.js adds a progress bar and Back / Next, checks each step before
 *          moving on and opens the step holding a server error), Range slider (bounds and step
 *          re-checked on the server), Picture Choices (image cards over radios / checkboxes),
 *          Ranking (position dropdowns that static/js/fields.js turns into Move up / Move down)
 *          and Survey Grid (Likert: statements x one scale). Every multi-part value is stored as
 *          one readable string, so entries, emails and answer tags read naturally. submit.js now
 *          fires fw-forms:errors and fw-forms:sent on the form. Form Styles presets style every
 *          field fieldset legend like a label.
 *
 * 2.0.70 - The form library. Unyson+ -> Forms lists saved forms (private post type fw_form,
 *          includes/library/): build a form once, with the same builder and Settings, and show it
 *          from any Contact Form element (Form Fields -> Saved Form, att library_form). The element
 *          keeps its own Style; every page showing the form changes when it is edited, and its
 *          entries group under one form id (lib-<ID>, titled with the form's name). The list shows
 *          fields, entries and the pages a form is placed on, with Duplicate, Export (JSON) and
 *          Import. The definition is mirrored into post_content on save, so WordPress revisions
 *          keep its history and a restore puts it back. In the page builder, the picker lists the
 *          saved forms, a linked element shows a notice instead of its own fields, and "Save to
 *          the library" turns an element's form into a saved one (AJAX fw_forms_library_save).
 *
 * 2.0.69 - Choose a form type first. An empty form builder opens on a picker
 *          (includes/picker/class-fw-forms-picker.php + contact-form static/js/starter-picker.js):
 *          the starters grouped (enquiries, bookings, events, applications, surveys, marketing),
 *          plus Blank and, with an AI provider connected through WordPress's AI Client, a box to
 *          describe the form (FW_Forms_AI_Generate: the reply is checked field by field and run
 *          through each item class; actions are never set). Picking fills the builder and the
 *          subject, button and thank-you text where they are still defaults; "Choose a form type"
 *          in the builder header reopens it. Six new starters: callback request, product enquiry,
 *          RSVP, job application, volunteer sign-up, download (lead magnet). Starters gain desc,
 *          icon, group and settings (filter fw_ext_forms_starters). The Thank-you Page choice reads
 *          pages straight from the database: get_pages() recursed while element options were
 *          built and exhausted memory on the page builder's options request.
 *
 * 2.0.68 - Better sending. Forms now send in the background (static/js/submit.js posts with
 *          X-Requested-With, which FW_Form already answers as JSON) and show the result in place;
 *          without the script, or on any unexpected reply, the ordinary reload still works.
 *          Errors follow WCAG 2.2: each message sits next to its field and is linked to it
 *          (aria-describedby), the field is marked aria-invalid, several problems get a summary
 *          that takes focus, and the error list the server prints after a reload is turned into
 *          the same pattern. New on the Contact Form: After Sending (keep the form, replace it
 *          with the message, go to a page, go to an address), CC and BCC, an empty recipient
 *          falling back to the admin email, and answer tags ({text_name}, {all_fields},
 *          {site_name}, {page_title}, {page_url}, {date}) in the subject, success message and
 *          address (FW_Forms_Merge_Tags, filter fw_ext_forms_merge_tags). New action "Email the
 *          sender" (a confirmation email; the visitor's answers only with an explicit switch).
 *          Name, email, phone, company and address fields declare autocomplete hints
 *          (FW_Forms_Autocomplete, filter fw_ext_forms_autocomplete_hint). New Turnstile and
 *          hCaptcha fields (keys under Forms -> Settings; a field without keys never blocks a
 *          visitor). Spam filtering under Forms -> Settings (Akismet, blocked words, blocked email
 *          domains, a link limit): a caught submission sees the normal success message, sends
 *          nothing, runs no action and is kept under Form Entries -> Spam (filter
 *          fw_ext_forms_spam_check). Entry titles drop answer tags from the subject.
 *
 * 2.0.67 - Form Styles presets. Theme Settings -> Components -> Form Styles is a library of
 *          reusable field looks (Standard, Soft, Underline, Rounded, On Dark ship), each a
 *          .formp-{slug} class: field colours, border, radius, height, padding, text size,
 *          focus colour and ring, placeholder, label, help, error and choice colours, label
 *          size / weight / case, dark-mode colours and {{SELECTOR}} custom CSS. The Contact
 *          Form's Style tab gains a Form Style dropdown (default: the first preset); its own
 *          field settings now act as "this form only" overrides and still win. Standard
 *          reproduces the previous field look exactly, so existing forms do not change. The
 *          tab and the CSS come from the Forms extension through the
 *          unysonplus_components_settings_options and unysonplus_global_css filters
 *          (includes/styles/class-fw-forms-style-presets.php). The per-form focus colour,
 *          which never outranked the stylesheet's own focus rule, now applies.
 *
 * 2.0.66 - Form actions can refuse a submission, and fields can be secret. A new
 *          fw_ext_forms_validate_submission filter runs once the fields are valid;
 *          FW_Forms_Action gains an optional validate( $ctx ) that returns a message
 *          shown to the visitor instead of the success message (nothing is sent,
 *          stored or run). New fw_ext_forms_secret_field_types filter: fields of those
 *          types (a password) are never stored in Entries, never in the notification
 *          email and never among an action's fields; an action reads them from the
 *          raw payload. FW_Forms_Actions::context() builds the shared action context.
 *
 * 2.0.64 - The forms-add AI ability sets a form's actions: store (keep entries),
 *          subscribe { list, tags, name_field } for the Newsletter CRM, and the
 *          failure message, so a connected AI app can build a complete lead form.
 *
 * 2.0.59 - Form fields: the per-field help line is reachable, correct and announced.
 *          Every field type already offered this option and all eleven field views already
 *          rendered it, but it was labelled "Instructions for Users" and described as a
 *          tooltip, which no view has ever drawn - it renders as a line under the control.
 *          Relabelled "Help Text" with an accurate description. The rendered line now carries
 *          `class="field-info"` and an id, and the control points at it with
 *          `aria-describedby`, so the hint is announced with the field instead of floating
 *          unattached; the nine field types that echoed the value raw now escape it, matching
 *          date and rating. The Site Converter fills this option from a source's own field
 *          hint, which previously fell out of the form and rendered as a loose paragraph.
 *
 * 2.0.56 - AI Assistant abilities. With the AI Assistant extension active, the AI can
 *         work with forms: forms-describe and forms-add (a contact form from a starter and / or
 *         simple field definitions, normalised by each field's own item class; no
 *         access to entries) — undoable through undo_change / page revisions.
 *         See includes/ai-abilities.php.
 *
 * 2.0.54 - Starter forms. The form builder now has the framework's Templates
 *          panel (save, load, delete, JSON export/import — the same component
 *          the page and email builders use, switched on with `template_saving`)
 *          plus eight starters: Contact, Booking / Appointment, Quote request,
 *          Event registration, Support ticket, Feedback (CSAT), NPS survey and
 *          Newsletter signup. Authored as PHP rather than pasted JSON, and each
 *          item is normalised through its own item class, because a template
 *          stores option values and rots silently when a field's options
 *          change. A newsletter opt-in is always a separate, optional Consent
 *          box, never bundled with the terms box; no starter switches on an
 *          Action, so subscribing stays the site owner's decision.
 * 2.0.53 - Conditional visibility: every field gains a "Show only when…" rule
 *          (another field is / is not / contains / has any value / is empty a
 *          value), the single feature people most miss from dedicated form
 *          plugins. Fields toggle live as the visitor types, but the server is
 *          the authority: a hidden field is neither validated — a required field
 *          the visitor never saw cannot be required of them — nor collected, so
 *          a stale value posted from an old tab is never stored or emailed. The
 *          controller is named by its label, the only thing the form author can
 *          see, and a rule that watches a field which does not exist resolves to
 *          visible, because hiding a field over a typo would silently lose data.
 * 2.0.52 - Actions: what a form does after a successful submit, per form, on a
 *          new Settings → Actions tab of the contact-form element. Until now
 *          "email the admin" was the only thing a form could do, hard-coded;
 *          an Action is the general form of that, registered through
 *          `fw_ext_forms_actions` so other extensions add theirs without Forms
 *          knowing they exist. Forms ships Store entry (default on); the
 *          Newsletter CRM contributes Subscribe to the newsletter. Actions run
 *          after the entry is stored so they can reference it, and are isolated
 *          from each other and from the visitor — a failing action is reported
 *          through `fw_ext_forms_action_failed`, never shown on the form.
 * 2.0.51 - Four field types: Date / Time, Rating, Hidden and Consent — the ones a
 *          booking form, a quote request or a satisfaction survey cannot do
 *          without. Date / Time renders the browser's own date, time or
 *          datetime-local input rather than a picker library, with earliest /
 *          latest bounds (ISO or the word "today") and blackout weekdays; the
 *          browser enforces the bounds as a convenience and the server as the
 *          rule. Rating is a native radio group styled as 1–5 stars or 0–10 /
 *          1–10 pills (NPS), storing the integer. Hidden carries a fixed value,
 *          a URL parameter, or the page URL / title — context, never authority.
 *          Consent is a dedicated type, not a one-choice Checkboxes, so the
 *          Newsletter CRM's subscribe action can find consent BY TYPE and never
 *          by guessing at a label; its `purpose` (terms / newsletter / other)
 *          rides into the stored entry, a newsletter opt-in is forced optional
 *          because it must be a free choice, and an unticked box is stored as
 *          empty rather than dropped so the entry records the question was
 *          asked and declined. The four share one PHP base and one canvas
 *          factory (`_shared/`), so each is only its own options, render and
 *          validate; the original items are untouched.
 * 2.0.50 - Entries: every form submission is now stored, not just emailed.
 *          Until now a submission existed only as an email in someone's inbox —
 *          lost if the mail server hiccuped, unsearchable, no record — which is
 *          the single thing that stopped a contact form from being a booking
 *          form, a quote request or a survey. A new `fw_form_entries` table
 *          keeps each submission with its fields as JSON, one row per
 *          submission, the LABEL captured at submit time so editing a form never
 *          makes old entries unreadable, and an entry is written whether or not
 *          the email went out. Unyson+ → Form Entries lists them (per-form
 *          filter, New / Read / Archived views, date window, search across
 *          field values, bulk archive/delete, single-entry view, CSV export of
 *          the current view with formula-injection guarding). Settings: store
 *          on/off (default on), record IP (default off — personal data), and a
 *          retention window enforced daily; entries are registered with the
 *          WordPress Export / Erase Personal Data tools by email.
 *          Built on the submit hook rather than inside the pipeline:
 *          `fw_ext_forms_frontend_submit` now carries `form_values` and
 *          `attachments`, without which a listener could see that a form was
 *          submitted but not what — the same seam the Newsletter CRM's consent
 *          -based subscribe action will use next. Entries are submissions, not
 *          people: nothing here creates a subscriber.
 */

$manifest['name']        = __( 'Forms', 'fw' );
$manifest['slug']        = 'unysonplus-forms';
$manifest['description'] = __(
	'This extension adds the possibility to create a contact form. Use the drag & drop form builder to create any contact form you\'ll ever want or need.',
	'fw'
);

$manifest['version']     = '2.0.92';
$manifest['display']     = true;
$manifest['standalone']  = true;

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-Forms-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-Forms-Extension';
$manifest['github_branch'] = 'master';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Requirements
$manifest['requirements'] = array(
	'extensions' => array(
		'builder' => array(),
	),
);

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '5.8';
