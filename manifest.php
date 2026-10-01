<?php if (!defined('FW')) die('Forbidden');

$manifest = array();

/**
 * Changelog
 * -----------------------------------------------------------------------------
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

$manifest['version']     = '2.0.59';
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
