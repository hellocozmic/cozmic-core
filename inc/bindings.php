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

	$timestamp = strtotime( $value );
	if ( false === $timestamp ) {
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

	return (string) wp_date( $pattern, $timestamp );
}

/**
 * Register the source.
 *
 * Returning null rather than an empty string when a field is unset is
 * deliberate: core then leaves the block's own content in place, so a pattern
 * whose button is bound to a CTA label still renders its placeholder label on a
 * service that has none, instead of collapsing to an empty button.
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
