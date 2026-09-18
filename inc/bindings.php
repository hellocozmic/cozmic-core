<?php
/**
 * A Block Bindings source for Cozmic fields.
 *
 * Core already ships `core/post-meta`, and for a plain string that is the right
 * tool - patterns should use it. This source exists for the case core cannot
 * cover: a stored value that must be *formatted* before it is shown. An event
 * start date is stored as `2026-09-12T18:30` so it sorts and compares
 * correctly, and nobody wants to read that on a page.
 *
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{
 *          "source":"cozmic/field",
 *          "args":{"key":"cozmic_start_date","format":"datetime"}
 *        }}}} -->
 *
 * Bindings only reach the attributes core supports: paragraph and heading
 * content, image url/alt/title, and button text/url/linkTarget/rel. Anything
 * else in a pattern stays ordinary block markup.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a stored datetime as the wall-clock time it claims to be.
 *
 * Field values are stored as `Y-m-d\TH:i` with no offset, because that is what
 * the editor's `datetime-local` input produces and what somebody means when
 * they type "6:00 pm". WordPress sets PHP's default timezone to UTC for the
 * whole request, so `strtotime()` on such a string produces a *UTC* moment;
 * `wp_date()` then renders it in the site's timezone and the time moves. On a
 * Michigan site that is four or five hours, and an all-day event stored as
 * `00:00` renders on the previous day - silently, and only on sites that are
 * not on UTC, which is why it survives a casual check.
 *
 * Parsing against `wp_timezone()` is what makes the stored string mean what it
 * says. A value that carries its own offset keeps it: PHP ignores the supplied
 * timezone in that case, which is the behaviour we want.
 *
 * @param string $value Stored value.
 * @return DateTimeImmutable|null Null when the value is empty or unparseable.
 */
function cozmic_core_parse_field_date( string $value ): ?DateTimeImmutable {
	if ( '' === $value ) {
		return null;
	}

	try {
		return new DateTimeImmutable( $value, wp_timezone() );
	} catch ( Exception ) {
		return null;
	}
}

/**
 * Format a stored field value for display.
 *
 * Dates run through `wp_date()`, so they follow the site's own date and time
 * format settings and its timezone rather than a format hardcoded here. A value
 * that will not parse is returned untouched: showing the raw string is a better
 * failure than showing "January 1, 1970".
 *
 * @param string $value  Stored value.
 * @param string $format One of date, time, datetime, or an empty string.
 */
function cozmic_core_format_field( string $value, string $format ): string {
	if ( '' === $value || '' === $format ) {
		return $value;
	}

	$moment = cozmic_core_parse_field_date( $value );
	if ( null === $moment ) {
		return $value;
	}

	$pattern = match ( $format ) {
		'date'     => (string) get_option( 'date_format' ),
		'time'     => (string) get_option( 'time_format' ),
		'datetime' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		default    => '',
	};

	if ( '' === $pattern ) {
		return $value;
	}

	return (string) wp_date( $pattern, $moment->getTimestamp() );
}

/**
 * Format one moment for display.
 *
 * A time of exactly midnight is shown as a date alone, because that is what an
 * all-day entry looks like once the editor's date-only input has been
 * normalised. An event that genuinely starts at midnight loses its "12:00 am",
 * which is a trade worth making: the all-day case is common and the midnight
 * case is nearly theoretical.
 *
 * The midnight test reads the *local* clock, not `gmdate()` of the timestamp.
 * Testing UTC there was half of the timezone bug: it correctly recognised an
 * all-day entry and then printed it on the day before.
 *
 * @param string $value Stored datetime.
 */
function cozmic_core_format_moment( string $value ): string {
	$moment = cozmic_core_parse_field_date( $value );
	if ( null === $moment ) {
		return '';
	}

	$date = (string) get_option( 'date_format' );
	$time = (string) get_option( 'time_format' );

	if ( '00:00' === $moment->format( 'H:i' ) ) {
		return (string) wp_date( $date, $moment->getTimestamp() );
	}

	return (string) wp_date( $date . ' ' . $time, $moment->getTimestamp() );
}

/**
 * When an event happens, as one readable phrase.
 *
 * An end time on the same day is shown as a time alone, so a single afternoon
 * reads "September 12, 2026 6:30 pm to 8:00 pm" rather than repeating the date
 * twice.
 *
 * @param int $post_id Event post ID.
 */
function cozmic_core_event_when( int $post_id ): string {
	$start = cozmic_core_field( 'cozmic_start_date', $post_id );
	if ( '' === $start ) {
		return '';
	}

	$when = cozmic_core_format_moment( $start );
	$end  = cozmic_core_field( 'cozmic_end_date', $post_id );

	if ( '' === $end || '' === $when ) {
		return $when;
	}

	$start_at = cozmic_core_parse_field_date( $start );
	$end_at   = cozmic_core_parse_field_date( $end );
	if ( null === $end_at || null === $start_at || $end_at <= $start_at ) {
		return $when;
	}

	$same_day = $start_at->format( 'Y-m-d' ) === $end_at->format( 'Y-m-d' );
	$tail     = $same_day
		? (string) wp_date( (string) get_option( 'time_format' ), $end_at->getTimestamp() )
		: cozmic_core_format_moment( $end );

	return '' === $tail ? $when : $when . ' to ' . $tail;
}

/**
 * Values composed from several fields rather than stored.
 *
 * These exist because a block template has no conditionals. Binding a date and
 * a location to two separate paragraphs means an event with no end time renders
 * a stray label, or a placeholder written for the editor leaks onto the live
 * page. Composing the whole line in PHP puts the "if" where ifs can go, and the
 * template carries one block with one sensible fallback.
 *
 * @param string $key     Computed key.
 * @param int    $post_id Post ID.
 */
function cozmic_core_computed_field( string $key, int $post_id ): ?string {
	if ( 'cozmic_event_details' === $key ) {
		$parts = array_filter(
			array(
				cozmic_core_event_when( $post_id ),
				cozmic_core_field( 'cozmic_location', $post_id ),
			)
		);

		return empty( $parts ) ? null : implode( ' · ', $parts );
	}

	/*
	 * Where a mention ran, and when. The date is the post's own published date
	 * rather than another field: an entry gets added when the piece appears, so
	 * the two agree by default, and an Editor catching up on older coverage can
	 * set the date in the Publish panel they already know. The archive sorts by
	 * that same date, so the list and the line can never disagree.
	 */
	if ( 'cozmic_press_details' === $key ) {
		$parts = array_filter(
			array(
				cozmic_core_field( 'cozmic_publication', $post_id ),
				(string) get_the_date( (string) get_option( 'date_format' ), $post_id ),
			)
		);

		return empty( $parts ) ? null : implode( ' · ', $parts );
	}

	return null;
}

/**
 * Register the source.
 *
 * Returning null rather than an empty string when a field is unset is
 * deliberate: core then leaves the block's own content in place, so a pattern
 * whose button is bound to a CTA label still renders its placeholder label on a
 * service that has none, instead of collapsing to an empty button. Templates
 * lean on that - their fallback content is written to be a good default, not a
 * hint to the editor.
 */
function cozmic_core_register_bindings(): void {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'cozmic/field',
		array(
			'label'              => __( 'Cozmic field', 'cozmic-core' ),
			'uses_context'       => array( 'postId', 'postType' ),
			'get_value_callback' => static function ( array $source_args, $block_instance ): ?string {
				$key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
				if ( '' === $key ) {
					return null;
				}

				$post_id = $block_instance->context['postId'] ?? get_the_ID();
				if ( ! $post_id ) {
					return null;
				}

				$computed = cozmic_core_computed_field( $key, (int) $post_id );
				if ( null !== $computed ) {
					return $computed;
				}

				$value = get_post_meta( (int) $post_id, $key, true );
				if ( ! is_string( $value ) || '' === $value ) {
					return null;
				}

				$format = isset( $source_args['format'] ) ? (string) $source_args['format'] : '';

				return cozmic_core_format_field( $value, $format );
			},
		)
	);
}
add_action( 'init', 'cozmic_core_register_bindings', 11 );
