<?php
/**
 * Structural SEO.
 *
 * The parts of a site's search presence that are *structure*, not opinion: a
 * description tag, and machine-readable data describing the business, its
 * events, and its FAQs. It lives in Cozmic Core rather than the theme because
 * it must survive a theme switch, and because the platform tier emits exactly
 * the same markup - a WordPress client should not get a worse audit result than
 * a platform client for a reason nobody can see.
 *
 * Everything here stands down when a real SEO plugin is active. Two plugins
 * both emitting LocalBusiness is worse than neither doing it, and a client who
 * installs Yoast has made a choice worth respecting. The two exceptions are
 * documented at their functions: no SEO plugin builds FAQ data out of core
 * Details blocks, and none reads Cozmic event meta, so those keep running.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a dedicated SEO plugin is handling the head.
 *
 * Detection is by the constant each plugin defines, which is stable across
 * their versions and needs no file to exist at a guessed path.
 */
function cozmic_core_seo_plugin_active(): bool {
	$active = defined( 'WPSEO_VERSION' )                // Yoast SEO.
		|| defined( 'RANK_MATH_VERSION' )               // Rank Math.
		|| defined( 'AIOSEO_VERSION' )                  // All in One SEO.
		|| defined( 'SEOPRESS_VERSION' )                // SEOPress.
		|| defined( 'SLIM_SEO_VER' )                    // Slim SEO.
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' );      // The SEO Framework.

	return (bool) apply_filters( 'cozmic_core_seo_plugin_active', $active );
}

/**
 * Print a JSON-LD block.
 *
 * Slashes are left escaped rather than passing JSON_UNESCAPED_SLASHES, so a
 * closing script tag inside any string value cannot break out of the element.
 *
 * @param array<string, mixed> $data Schema.org data.
 */
function cozmic_core_print_json_ld( array $data ): void {
	$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return;
	}

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}

/**
 * The meta description.
 *
 * Falls back to a hand-written excerpt but never to an auto-generated one. An
 * excerpt WordPress trims out of the first 55 words is a worse snippet than the
 * one Google would have written from the whole page, so where there is nothing
 * deliberate to say, this says nothing. The audit flags the gap either way, and
 * a flagged gap is more useful than a filled one nobody meant.
 */
function cozmic_core_meta_description(): void {
	if ( cozmic_core_seo_plugin_active() ) {
		return;
	}

	$description = '';

	if ( is_front_page() ) {
		$description = (string) get_bloginfo( 'description' );
	} elseif ( is_singular() ) {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$description = cozmic_core_field( 'cozmic_meta_description', $post->ID );

			if ( '' === $description ) {
				$description = (string) $post->post_excerpt;
			}
		}
	}

	$description = trim( wp_strip_all_tags( $description ) );
	if ( '' === $description ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'cozmic_core_meta_description', 1 );

/**
 * LocalBusiness data for the business itself, on every page.
 *
 * Built from the business profile in Site Setup, and only from the fields that
 * are actually filled in. A private address is omitted entirely while the
 * service area is still published, which is the schema-correct shape for a
 * business that travels to its customers rather than one that has no premises.
 */
function cozmic_core_local_business_json_ld(): void {
	if ( cozmic_core_seo_plugin_active() ) {
		return;
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'name'     => cozmic_core_business( 'business_name' ),
		'url'      => home_url( '/' ),
	);

	$phone = (string) cozmic_core_business( 'phone' );
	if ( '' !== $phone ) {
		$data['telephone'] = $phone;
	}

	$email = (string) cozmic_core_business( 'email' );
	if ( '' !== $email ) {
		$data['email'] = $email;
	}

	$address = (string) cozmic_core_business( 'address' );
	if ( '' !== $address && ! cozmic_core_business( 'hide_address' ) ) {
		$data['address'] = $address;
	}

	$service_area = (string) cozmic_core_business( 'service_area' );
	if ( '' !== $service_area ) {
		$data['areaServed'] = $service_area;
	}

	$price_range = (string) cozmic_core_business( 'price_range' );
	if ( '' !== $price_range ) {
		$data['priceRange'] = $price_range;
	}

	$logo = get_site_icon_url();
	if ( has_custom_logo() ) {
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		if ( $logo_src ) {
			$logo = $logo_src;
		}
	}
	if ( $logo ) {
		$data['logo']  = $logo;
		$data['image'] = $logo;
	}

	$social = cozmic_core_business( 'social_links' );
	if ( is_array( $social ) && ! empty( $social ) ) {
		$data['sameAs'] = array_values( $social );
	}

	cozmic_core_print_json_ld( $data );
}
add_action( 'wp_head', 'cozmic_core_local_business_json_ld', 5 );

/**
 * Collect question and answer pairs from Details blocks.
 *
 * Walks nested blocks, because a Details block inside a Columns inside a Group
 * is still an FAQ entry and the pattern library nests exactly that deep.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @return array<int, array<string, string>>
 */
function cozmic_core_collect_faq_pairs( array $blocks ): array {
	$pairs = array();

	foreach ( $blocks as $block ) {
		if ( ( $block['blockName'] ?? '' ) === 'core/details' ) {
			$question = trim( (string) ( $block['attrs']['summary'] ?? '' ) );

			$answer = '';
			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
				$answer .= ' ' . wp_strip_all_tags( render_block( $inner ) );
			}
			$answer = trim( preg_replace( '/\s+/', ' ', $answer ) ?? '' );

			if ( '' !== $question && '' !== $answer ) {
				$pairs[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$pairs = array_merge( $pairs, cozmic_core_collect_faq_pairs( (array) $block['innerBlocks'] ) );
		}
	}

	return $pairs;
}

/**
 * FAQPage data for a page carrying an FAQ.
 *
 * This is the hook the theme's FAQ pattern is written against. Core's Details
 * block gives the right markup and no structured data, so without this the
 * pattern looks correct and is invisible to anything reading the page
 * mechanically. Google narrowed the visible FAQ rich result to health and
 * government sites in 2023, but the markup is still valid and AI answer engines
 * increasingly read it, so it stays worth emitting.
 *
 * Two pairs is the floor. One Details block is an expandable note, not an FAQ,
 * and claiming otherwise in structured data is the kind of small overstatement
 * that costs trust with a crawler.
 *
 * Runs even with an SEO plugin active: none of them build this from core blocks,
 * so there is nothing to collide with.
 */
function cozmic_core_faq_json_ld(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post || ! str_contains( $post->post_content, 'wp:details' ) ) {
		return;
	}

	$pairs = cozmic_core_collect_faq_pairs( parse_blocks( $post->post_content ) );
	if ( count( $pairs ) < 2 ) {
		return;
	}

	$entities = array();
	foreach ( $pairs as $pair ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $pair['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $pair['answer'],
			),
		);
	}

	cozmic_core_print_json_ld(
		array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		)
	);
}
add_action( 'wp_head', 'cozmic_core_faq_json_ld', 6 );

/**
 * Event data on a single event.
 *
 * Also runs alongside an SEO plugin, for the same reason as the FAQ data: none
 * of them know about Cozmic event meta, so nothing duplicates.
 */
function cozmic_core_event_json_ld(): void {
	if ( ! is_singular( COZMIC_CORE_CPT_EVENT ) ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	/*
	 * ISO 8601 with a real UTC offset, not the bare stored string.
	 *
	 * schema.org reads an offset-less datetime as local time, but leaves
	 * "local to whom" for the consumer to guess, so the value here and the
	 * date rendered on the page could disagree with nothing to reconcile
	 * them. `c` on a value parsed in the site's timezone pins it.
	 */
	$start_at = cozmic_core_parse_field_date( cozmic_core_field( 'cozmic_start_date', $post->ID ) );
	if ( null === $start_at ) {
		return;
	}

	$data = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Event',
		'name'      => get_the_title( $post ),
		'url'       => (string) get_permalink( $post ),
		'startDate' => $start_at->format( 'c' ),
	);

	$end_at = cozmic_core_parse_field_date( cozmic_core_field( 'cozmic_end_date', $post->ID ) );
	if ( null !== $end_at ) {
		$data['endDate'] = $end_at->format( 'c' );
	}

	$location = cozmic_core_field( 'cozmic_location', $post->ID );
	if ( '' !== $location ) {
		$data['location'] = array(
			'@type' => 'Place',
			'name'  => $location,
		);
	}

	$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
	if ( '' !== $excerpt ) {
		$data['description'] = $excerpt;
	}

	$image = get_the_post_thumbnail_url( $post, 'full' );
	if ( $image ) {
		$data['image'] = $image;
	}

	$data['organizer'] = array(
		'@type' => 'Organization',
		'name'  => cozmic_core_business( 'business_name' ),
		'url'   => home_url( '/' ),
	);

	cozmic_core_print_json_ld( $data );
}
add_action( 'wp_head', 'cozmic_core_event_json_ld', 7 );
