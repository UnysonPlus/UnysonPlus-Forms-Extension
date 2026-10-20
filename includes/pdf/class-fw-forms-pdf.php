<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * PDF copies of entries: a receipt, an application, a signed waiver.
 *
 *   - "Download PDF" on a single entry (Form Entries).
 *   - Attached to the notification email and / or the "Email the sender" confirmation
 *     (contact form Settings → Options → PDF copy: pdf_notification, pdf_autoresponder, pdf_title).
 *
 * The PDF is written here, with no library: A4, the built-in Helvetica (WinAnsi: Latin letters,
 * accents and the euro sign; characters outside it, such as Japanese, are left out), every answer as a label and a value, wrapped and paginated, page
 * numbers, and a drawn signature placed as an image (re-encoded to JPEG with GD on a white
 * background). Files and personal data never leave the site except as the attachment you choose.
 */
class FW_Forms_PDF {

	const KEYS = array( 'pdf_notification', 'pdf_autoresponder', 'pdf_title' );

	/** Helvetica advance widths (1/1000 em) for ASCII 32–126. */
	const W = array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 );

	/** @var string[] Temporary files to delete once the request is over. */
	private static $temp = array();

	public static function init() {
		add_action( 'fw_ext_forms_entry_panels', array( __CLASS__, '_entry_button' ), 1 );
		add_action( 'admin_post_fw_forms_entry_pdf', array( __CLASS__, '_download' ) );
		add_filter( 'fw_ext_forms_notification_attachments', array( __CLASS__, '_notification' ), 10, 3 );
		add_filter( 'fw_ext_forms_autoresponder_attachments', array( __CLASS__, '_autoresponder' ), 10, 2 );
		add_action( 'shutdown', array( __CLASS__, '_cleanup' ) );
	}

	/** Settings → Options → PDF copy. */
	public static function options() {
		$sw = function ( $label, $desc ) {
			return array( 'type' => 'switch', 'label' => $label, 'desc' => $desc, 'value' => 'no', 'left-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ), 'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ) );
		};
		return array(
			'type'    => 'group',
			'options' => array(
				'pdf_notification'  => $sw( __( 'Attach a PDF copy to the notification email', 'fw' ), __( 'Every answer, and a drawn signature, as a PDF. A PDF can always be downloaded from the entry.', 'fw' ) ),
				'pdf_autoresponder' => $sw( __( 'Attach a PDF copy to the sender\'s confirmation email', 'fw' ), __( 'Needs Settings → Actions → Email the sender. The sender gets their own answers, so use it for receipts and signed forms.', 'fw' ) ),
				'pdf_title'         => array( 'type' => 'text', 'label' => __( 'PDF title', 'fw' ), 'desc' => __( 'Answer tags work, e.g. Waiver signed by {text_name}.', 'fw' ), 'value' => '' ),
			),
		);
	}

	/* ------------------------------------------------------------------ Text */

	private static function enc( $s ) {
		$s = str_replace( array( "\r\n", "\r" ), "\n", (string) $s );
		$t = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $t ? preg_replace( '/[^\x0A\x20-\x7E]/', '?', $s ) : $t;
	}

	private static function esc( $s ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s );
	}

	private static function width( $s, $size, $bold = false ) {
		$w = 0;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$c  = ord( $s[ $i ] );
			$w += ( $c >= 32 && $c <= 126 ) ? self::W[ $c - 32 ] : 556;
		}
		return $w * $size / 1000 * ( $bold ? 1.06 : 1 );
	}

	/** Wrap WinAnsi text to a width. @return string[] */
	private static function wrap( $s, $size, $max, $bold = false ) {
		$out = array();
		foreach ( explode( "\n", $s ) as $para ) {
			$line = '';
			foreach ( preg_split( '/ /', $para ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( self::width( $try, $size, $bold ) <= $max ) {
					$line = $try;
					continue;
				}
				if ( '' !== $line ) {
					$out[] = $line;
				}
				while ( self::width( $word, $size, $bold ) > $max && strlen( $word ) > 1 ) { // a very long word
					$cut = strlen( $word );
					while ( $cut > 1 && self::width( substr( $word, 0, $cut ), $size, $bold ) > $max ) {
						$cut--;
					}
					$out[] = substr( $word, 0, $cut );
					$word  = substr( $word, $cut );
				}
				$line = $word;
			}
			$out[] = $line;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ The document */

	/**
	 * Write a PDF.
	 *
	 * @param array $doc { title, subtitle (string[]), rows: [ { label, value, image?: { jpeg, w, h } } ], footer }
	 * @return string The PDF bytes.
	 */
	public static function render( array $doc ) {
		$W = 595.28; $H = 841.89; $M = 56; $max = $W - 2 * $M;
		$pages  = array();
		$images = array();
		$ops    = '';
		$y      = $H - $M;
		$newpage = function () use ( &$pages, &$ops, &$y, $H, $M ) {
			$pages[] = $ops;
			$ops     = '';
			$y       = $H - $M;
		};
		$text = function ( $s, $size, $bold, $x, $gray = 0 ) use ( &$ops, &$y ) {
			$ops .= sprintf( "BT %s rg /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $gray ? '0.4 0.4 0.4' : '0 0 0', $bold ? 'F2' : 'F1', $size, $x, $y, self::esc( $s ) );
		};
		$need = function ( $h ) use ( &$y, $M, $newpage ) {
			if ( $y - $h < $M + 20 ) {
				$newpage();
			}
		};

		// Heading.
		$site = self::enc( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$text( $site, 9, false, $M, 1 );
		$y -= 26;
		foreach ( self::wrap( self::enc( (string) ( $doc['title'] ?? '' ) ), 18, $max, true ) as $l ) {
			$text( $l, 18, true, $M );
			$y -= 22;
		}
		foreach ( (array) ( $doc['subtitle'] ?? array() ) as $sub ) {
			$text( self::enc( $sub ), 9.5, false, $M, 1 );
			$y -= 14;
		}
		$y   -= 6;
		$ops .= sprintf( "0.85 0.85 0.85 RG 0.6 w %.2F %.2F m %.2F %.2F l S\n", $M, $y, $W - $M, $y );
		$y   -= 22;

		foreach ( (array) ( $doc['rows'] ?? array() ) as $row ) {
			$label = self::enc( (string) ( $row['label'] ?? '' ) );
			$lines = self::wrap( self::enc( (string) ( $row['value'] ?? '' ) ), 11, $max );
			$need( 14 + 15 * min( 3, count( $lines ) ) );
			$text( $label, 9, true, $M, 1 );
			$y -= 15;
			if ( ! empty( $row['image'] ) ) {
				$img = $row['image'];
				$w   = min( 240, $img['w'] );
				$h   = $img['h'] * $w / max( 1, $img['w'] );
				$need( $h + 10 );
				$n        = count( $images ) + 1;
				$images[] = $img;
				$ops     .= sprintf( "q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n", $w, $h, $M, $y - $h + 10, $n );
				$y       -= $h + 4;
			}
			foreach ( $lines as $l ) {
				$need( 15 );
				$text( $l, 11, false, $M );
				$y -= 15;
			}
			$y -= 9;
		}
		$pages[] = $ops;

		// Objects: 1 catalog, 2 pages, 3 F1, 4 F2, then images, then page + content pairs.
		$objs    = array();
		$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$next    = 5;
		$xobj    = array();
		foreach ( $images as $i => $img ) {
			$objs[ $next ] = array( sprintf( '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>', $img['w'], $img['h'], strlen( $img['jpeg'] ) ), $img['jpeg'] );
			$xobj[]        = sprintf( '/Im%d %d 0 R', $i + 1, $next );
			$next++;
		}
		$res   = '<< /Font << /F1 3 0 R /F2 4 0 R >>' . ( $xobj ? ' /XObject << ' . implode( ' ', $xobj ) . ' >>' : '' ) . ' >>';
		$kids  = array();
		$total = count( $pages );
		$foot  = self::enc( (string) ( $doc['footer'] ?? '' ) );
		foreach ( $pages as $i => $content ) {
			$label    = sprintf( 'Page %d of %d', $i + 1, $total );
			$content .= sprintf( "BT 0.4 0.4 0.4 rg /F1 8 Tf %.2F %.2F Td (%s) Tj ET\n", $W - $M - self::width( $label, 8 ), 30, self::esc( $label ) );
			if ( '' !== $foot ) {
				$content .= sprintf( "BT 0.4 0.4 0.4 rg /F1 8 Tf %.2F %.2F Td (%s) Tj ET\n", $M, 30, self::esc( $foot ) );
			}
			$stream        = gzcompress( $content );
			$page_id       = $next++;
			$content_id    = $next++;
			$objs[ $page_id ]    = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', $W, $H, $res, $content_id );
			$objs[ $content_id ] = array( sprintf( '<< /Length %d /Filter /FlateDecode >>', strlen( $stream ) ), $stream );
			$kids[]        = $page_id . ' 0 R';
		}
		$objs[2] = sprintf( '<< /Type /Pages /Kids [%s] /Count %d >>', implode( ' ', $kids ), $total );
		ksort( $objs );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objs as $id => $o ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . ( is_array( $o ) ? $o[0] . "\nstream\n" . $o[1] . "\nendstream" : $o ) . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= 'xref' . "\n0 " . ( count( $objs ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$pdf .= sprintf( "%010d 00000 n \n", $off );
		}
		$pdf .= 'trailer' . "\n" . sprintf( '<< /Size %d /Root 1 0 R >>', count( $objs ) + 1 ) . "\nstartxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}

	/** A drawn signature (PNG with transparency) as a JPEG on white, for the PDF. */
	public static function image( $path ) {
		if ( ! function_exists( 'imagecreatefrompng' ) || ! is_file( $path ) ) {
			return null;
		}
		$src = @imagecreatefrompng( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $src ) {
			return null;
		}
		$w   = imagesx( $src );
		$h   = imagesy( $src );
		$dst = imagecreatetruecolor( $w, $h );
		imagefill( $dst, 0, 0, imagecolorallocate( $dst, 255, 255, 255 ) );
		imagecopy( $dst, $src, 0, 0, 0, 0, $w, $h );
		ob_start();
		imagejpeg( $dst, null, 88 );
		$jpeg = ob_get_clean();
		imagedestroy( $src );
		imagedestroy( $dst );
		// Drawn at 2x for sharp screens: halve the size on paper.
		return array( 'jpeg' => $jpeg, 'w' => $w, 'h' => $h );
	}

	/**
	 * The document for a set of entry rows.
	 *
	 * @param array  $rows  entry fields [ { id, type, label, value, file? } ]
	 * @param string $title
	 * @param array  $meta  subtitle lines
	 * @param array  $paths field id => absolute file path (signatures)
	 */
	public static function doc( array $rows, $title, array $meta, array $paths = array() ) {
		$out = array();
		foreach ( $rows as $f ) {
			$val = $f['value'] ?? '';
			$val = is_array( $val ) ? implode( ', ', array_map( 'strval', $val ) ) : (string) $val;
			$row = array( 'label' => (string) ( $f['label'] ?? '' ), 'value' => $val );
			if ( 'signature' === ( $f['type'] ?? '' ) && ! empty( $paths[ $f['id'] ?? '' ] ) ) {
				$img = self::image( $paths[ $f['id'] ] );
				if ( $img ) {
					$row['image'] = $img;
					$row['value'] = '';
				}
			}
			if ( '' === trim( $row['value'] ) && empty( $row['image'] ) ) {
				continue;
			}
			$out[] = $row;
		}
		return array( 'title' => $title, 'subtitle' => $meta, 'rows' => $out, 'footer' => home_url( '/' ) );
	}

	/** The PDF of a stored entry. */
	public static function for_entry( $entry ) {
		$paths = array();
		foreach ( (array) $entry->fields as $f ) {
			if ( ! empty( $f['file'] ) ) {
				$paths[ $f['id'] ] = FW_Forms_Entries::upload_path( $f['file'] );
			}
		}
		$meta = array( sprintf( __( 'Sent %s', 'fw' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ), sprintf( __( 'Entry #%d', 'fw' ), $entry->id ) );
		if ( class_exists( 'FW_Forms_Payments' ) ) {
			foreach ( FW_Forms_Payments::for_entry( (int) $entry->id ) as $p ) {
				$meta[] = sprintf( __( 'Payment %1$s: %2$s, %3$s', 'fw' ), $p->reference, FW_Forms_Money::format( $p->amount, $p->currency ), FW_Forms_Payments::labels()[ $p->status ] ?? $p->status );
			}
		}
		return self::render( self::doc( (array) $entry->fields, '' !== $entry->form_title ? $entry->form_title : __( 'Form entry', 'fw' ), $meta, $paths ) );
	}

	/**
	 * A PDF of the submission being sent (before the entry exists), written to a temporary file.
	 *
	 * @return string The file path, or ''.
	 */
	public static function temp_file( array $values, array $items, array $uploads, array $form ) {
		if ( ! class_exists( 'FW_Forms_Entries_Capture' ) ) {
			return '';
		}
		$rows  = FW_Forms_Entries_Capture::fields_from_submission( $values, $items );
		$paths = array();
		foreach ( $uploads as $sc => $u ) {
			if ( ! empty( $u['file'] ) ) {
				$paths[ $sc ] = (string) $u['file'];
			}
		}
		$title = trim( (string) ( $form['pdf_title'] ?? '' ) );
		if ( '' !== $title && class_exists( 'FW_Forms_Merge_Tags' ) ) {
			$title = FW_Forms_Merge_Tags::replace( $title, $values, $items, 'text' );
		}
		if ( '' === $title ) {
			$title = __( 'Your submission', 'fw' );
		}
		$pdf  = self::render( self::doc( $rows, $title, array( sprintf( __( 'Sent %s', 'fw' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ), $paths ) );
		$dir  = FW_Forms_Entries::upload_dir()['path'];
		$name = sanitize_file_name( $title ) . '.pdf';
		$sub  = $dir . '/pdf-' . wp_generate_password( 16, false, false );
		if ( ! wp_mkdir_p( $sub ) || false === file_put_contents( $sub . '/' . $name, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return '';
		}
		self::$temp[] = $sub;
		return $sub . '/' . $name;
	}

	/** @var string The PDF made for this submission (shared by the two emails). */
	private static $made = '';

	private static function made( array $values, array $items, array $uploads, array $form ) {
		if ( '' === self::$made ) {
			self::$made = self::temp_file( $values, $items, $uploads, $form );
		}
		return self::$made;
	}

	/** @internal */
	public static function _notification( $attachments, $form, $data ) {
		if ( 'yes' !== ( $form['pdf_notification'] ?? 'no' ) ) {
			return $attachments;
		}
		$f = self::made( (array) ( $data['form_values'] ?? array() ), (array) ( $data['shortcode_to_item'] ?? array() ), (array) ( $data['uploads'] ?? array() ), (array) $form );
		if ( '' !== $f ) {
			$attachments[] = $f;
		}
		return $attachments;
	}

	/** @internal */
	public static function _autoresponder( $attachments, $ctx ) {
		$form = (array) ( $ctx['form'] ?? array() );
		if ( 'yes' !== ( $form['pdf_autoresponder'] ?? 'no' ) ) {
			return $attachments;
		}
		$p = (array) ( $ctx['payload'] ?? array() );
		$f = self::made( (array) ( $p['form_values'] ?? array() ), (array) ( $p['shortcode_to_item'] ?? array() ), (array) ( $p['uploads'] ?? array() ), $form );
		if ( '' !== $f ) {
			$attachments[] = $f;
		}
		return $attachments;
	}

	/** @internal */
	public static function _cleanup() {
		foreach ( self::$temp as $dir ) {
			foreach ( (array) glob( $dir . '/*' ) as $f ) {
				@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		self::$temp = array();
	}

	/* ------------------------------------------------------------------ Form Entries */

	public static function download_url( $entry_id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'fw_forms_entry_pdf', 'entry' => (int) $entry_id ), admin_url( 'admin-post.php' ) ), 'fw_forms_entry_pdf_' . (int) $entry_id );
	}

	/** @internal */
	public static function _entry_button( $entry ) {
		echo '<p class="fw-entries__actions"><a class="button" href="' . esc_url( self::download_url( $entry->id ) ) . '">' . esc_html__( 'Download PDF', 'fw' ) . '</a></p>';
	}

	/** @internal */
	public static function _download() {
		$id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'fw_forms_entry_pdf_' . $id );
		if ( ! current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fw' ) );
		}
		$entry = FW_Forms_Entries::find( $id );
		if ( ! $entry ) {
			wp_die( esc_html__( 'That entry no longer exists.', 'fw' ) );
		}
		$pdf = self::for_entry( $entry );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="entry-' . $id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
