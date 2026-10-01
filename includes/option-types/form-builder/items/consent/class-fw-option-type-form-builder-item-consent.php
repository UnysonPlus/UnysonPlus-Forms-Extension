<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Consent checkbox — "I agree to…" / "Also subscribe me to the newsletter".
 *
 * A dedicated TYPE rather than a one-choice Checkboxes field, on purpose: the
 * Newsletter CRM's subscribe action (Forms plan, Phase 3) must find consent by
 * type, never by guessing at a label. A form can carry more than one — a terms
 * box and a newsletter box are different questions — and `purpose` tells them
 * apart.
 *
 * The stored value is 'yes' when ticked and '' when not. Unticked is stored
 * too, so an entry shows the question was asked and declined — which is the
 * record you want when someone later asks why they were (or were not) mailed.
 */
class FW_Option_Type_Form_Builder_Item_Consent extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'consent';
	}

	protected function title() {
		return __( 'Consent', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Consent checkbox (terms, privacy, newsletter opt-in)', 'fw' );
	}

	public function get_options() {
		return array(
			array(
				'g_consent' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'textarea',
								'label' => __( 'Statement', 'fw' ),
								'desc'  => __( 'The text beside the checkbox. Links are allowed, e.g. to your privacy policy.', 'fw' ),
								'value' => __( 'I agree to the privacy policy.', 'fw' ),
							),
						),
						array(
							'required' => array(
								'type'  => 'switch',
								'label' => __( 'Mandatory', 'fw' ),
								'desc'  => __( 'The form cannot be sent unless this is ticked. Right for terms; wrong for a newsletter opt-in, which must be a free choice.', 'fw' ),
								'value' => true,
							),
						),
						array(
							'purpose' => array(
								'type'    => 'radio',
								'label'   => __( 'This consent is for', 'fw' ),
								'value'   => 'terms',
								'choices' => array(
									'terms'      => __( 'Terms / privacy policy', 'fw' ),
									'newsletter' => __( 'Newsletter — a subscribe action can use this', 'fw' ),
									'other'      => __( 'Something else', 'fw' ),
								),
								'desc'    => __( 'Lets other features recognise what was agreed to. Nothing subscribes anyone by itself.', 'fw' ),
							),
						),
					),
				),
			),
			$this->get_extra_options(),
		);
	}

	protected function fix_options( array $attributes ) {
		// A newsletter opt-in must be a free choice: force it optional.
		if ( ( $attributes['options']['purpose'] ?? '' ) === 'newsletter' ) {
			$attributes['options']['required'] = false;
		}

		return $attributes;
	}

	public function frontend_render( array $item, $input_value ) {
		$attr = array(
			'type'  => 'checkbox',
			'name'  => $item['shortcode'] ?? '',
			'value' => 'yes',
			'id'    => 'id-' . fw_unique_increment(),
		);

		if ( 'yes' === $input_value ) {
			$attr['checked'] = 'checked';
		}

		if ( ! empty( $item['options']['required'] ) && ( $item['options']['purpose'] ?? '' ) !== 'newsletter' ) {
			$attr['required'] = 'required';
		}

		return $this->view( $item, $attr );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o = $item['options'];

		if ( ! empty( $o['required'] ) && ( $o['purpose'] ?? '' ) !== 'newsletter' && 'yes' !== $input_value ) {
			// The statement is HTML (it carries the policy link); the error is text.
			$plain = trim( wp_strip_all_tags( (string) ( $o['label'] ?? '' ) ) );

			return str_replace( '{label}', '' !== $plain ? $plain : $this->title(), __( 'You need to tick "{label}" to continue', 'fw' ) );
		}

		return null;
	}

	/**
	 * Exactly 'yes' or ''. An unticked box is stored as '' (not dropped), so
	 * the entry records that the question was asked and declined.
	 */
	public function get_value_from_item( $value ) {
		return 'yes' === $value ? 'yes' : '';
	}

	/**
	 * A newsletter opt-in in a submission's field list, or null.
	 *
	 * The CRM's subscribe action (Phase 3) is the intended caller: it must never
	 * infer consent from a label or from the presence of an email. Only a
	 * consent field with purpose=newsletter that was ticked counts.
	 *
	 * @param array $fields Entry-style [ { id, type, label, value } ] rows.
	 *
	 * @return bool
	 */
	public static function newsletter_consent_given( array $fields ) {
		foreach ( $fields as $f ) {
			if ( 'consent' === ( $f['type'] ?? '' ) && 'yes' === ( $f['value'] ?? '' ) && 'newsletter' === ( $f['purpose'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Consent' );
