<?php
/**
 * The options framework.
 *
 * Two option rows, split by who is allowed to write them - which is the same
 * line D8 draws through the whole product:
 *
 *   cozmic_core_options   site setup. Content types on or off, editing freedom.
 *                         Written by Cozmic (or the dashboard, later). Admin only.
 *   cozmic_core_business  the business itself. Name, phone, address, hours.
 *                         Written by the client, and pushed at provisioning.
 *
 * Both are registered with `show_in_rest`, so Cozmic Connect can read and write
 * them over `/wp/v2/settings` without this plugin growing a custom endpoint.
 *
 * The business keys deliberately mirror the platform's `sites` columns
 * one-for-one (CLAUDE.md section 6), so a provisioning push is a rename-free
 * copy and the LocalBusiness JSON-LD comes out the same shape on both tiers.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COZMIC_CORE_OPTION_SETUP    = 'cozmic_core_options';
const COZMIC_CORE_OPTION_BUSINESS = 'cozmic_core_business';
const COZMIC_CORE_OPTION_ARCHIVES = 'cozmic_core_archives';

/**
 * Site setup defaults.
 *
 * Content types default to OFF, matching the platform, where a fresh site shows
 * Home / About / Contact and nothing else until the client asks for more. An
 * empty "Services" archive in the menu looks broken; an absent one looks
 * finished.
 *
 * Filterable so a child theme or a provisioning mu-plugin can ship a different
 * baseline (a restaurant template wanting events on from the first boot) without
 * anyone editing a row in the database. The docs' rule is that a plan upgrade
 * must never be a code edit and deploy per client, so the *option* is what the
 * dashboard flips; the filter only moves the default under it.
 *
 * @return array<string, bool>
 */
function cozmic_core_setup_defaults(): array {
	return (array) apply_filters(
		'cozmic_core_setup_defaults',
		array(
			'services_enabled'  => false,
			'events_enabled'    => false,
			'portfolio_enabled' => false,
			'blog_enabled'      => false,
			'press_enabled'     => false,

			/*
			 * Full editing freedom (docs D8). False is the rented-site posture:
			 * pattern internals stay locked, and styling changes route through
			 * Cozmic. Purchased sites get this on along with an administrator
			 * account, which is what actually unlocks Global Styles - core gates
			 * both on `edit_theme_options`, so this flag only governs the block
			 * unlock affordance.
			 */
			'full_editing'      => false,
		)
	);
}

/**
 * Archive page defaults.
 *
 * Empty strings across the board: no banner image, no introduction, and the
 * layout each archive template already ships. A site that never opens the
 * screen looks exactly as it did before the screen existed.
 *
 * @return array<string, string>
 */
function cozmic_core_archive_defaults(): array {
	$defaults = array();

	foreach ( cozmic_core_post_types() as $type ) {
		$defaults[ $type . '_image' ]  = '';
		$defaults[ $type . '_intro' ]  = '';
		$defaults[ $type . '_layout' ] = '';
	}

	return (array) apply_filters( 'cozmic_core_archive_defaults', $defaults );
}

/**
 * Business profile defaults.
 *
 * @return array<string, mixed>
 */
function cozmic_core_business_defaults(): array {
	return (array) apply_filters(
		'cozmic_core_business_defaults',
		array(
			'business_name'    => '',
			'phone'            => '',
			'email'            => '',
			'address'          => '',
			'hide_address'     => false,
			'service_area'     => '',
			'price_range'      => '',
			'hours'            => '',
			'google_maps_link' => '',
			'social_links'     => array(),
		)
	);
}

/**
 * Read one site-setup value.
 *
 * Always merged over the defaults, so a key added in a later version reads
 * correctly on a site whose stored row predates it. Nothing in this plugin
 * should ever read the raw option.
 */
function cozmic_core_setup( string $key, mixed $fallback = null ): mixed {
	$defaults = cozmic_core_setup_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_SETUP, array() );
	$options  = array_merge( $defaults, is_array( $stored ) ? $stored : array() );

	return $options[ $key ] ?? $fallback;
}

/**
 * Read one business-profile value.
 */
function cozmic_core_business( string $key, mixed $fallback = null ): mixed {
	$defaults = cozmic_core_business_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_BUSINESS, array() );
	$options  = array_merge( $defaults, is_array( $stored ) ? $stored : array() );

	$value = $options[ $key ] ?? $fallback;

	// The business name falls back to the site title rather than to empty, so
	// the JSON-LD and the footer say something sensible before anyone fills the
	// form in.
	if ( 'business_name' === $key && ( ! is_string( $value ) || '' === trim( $value ) ) ) {
		return get_bloginfo( 'name' );
	}

	return $value;
}

/**
 * Whether a content type is switched on.
 *
 * The one place that answers this question. Post type registration, admin menu
 * tidying, and the settings screen all defer to it, so a type can never be half
 * on - visible in the menu but 404ing on the front end.
 */
function cozmic_core_type_enabled( string $type ): bool {
	return (bool) cozmic_core_setup( $type . '_enabled', false );
}

/**
 * Read one archive-page setting for a content type.
 *
 * An archive is nobody's post: it has no featured image to set, no body to
 * write an introduction in, and no sidebar to configure. These three values fill
 * that gap, and the theme reads them when it renders the archive.
 *
 * Keys are flat (`press_image`, `press_intro`, `press_layout`) so the existing
 * settings form, merge and sanitise path carry them with no special cases.
 *
 * @param string $type Content type slug, such as `press`.
 * @param string $key  Setting name: `image`, `intro`, or `layout`.
 */
function cozmic_core_archive( string $type, string $key ): string {
	$defaults = cozmic_core_archive_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_ARCHIVES, array() );
	$options  = array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	$value    = $options[ $type . '_' . $key ] ?? '';

	return is_string( $value ) ? $value : '';
}

/**
 * Seed both rows on activation.
 *
 * `add_option` rather than `update_option`: activation runs again on every
 * plugin update, and this must never overwrite a live site's settings.
 */
function cozmic_core_seed_options(): void {
	add_option( COZMIC_CORE_OPTION_SETUP, cozmic_core_setup_defaults() );
	add_option( COZMIC_CORE_OPTION_BUSINESS, cozmic_core_business_defaults() );
	add_option( COZMIC_CORE_OPTION_ARCHIVES, cozmic_core_archive_defaults() );
}

/**
 * Rewrite rules after a content type is toggled.
 *
 * Turning a type on changes which post types exist, and the rewrite rules
 * cached in the database still describe the old set - so /services/ 404s until
 * someone thinks to re-save permalinks. Flushing inside the `update_option`
 * hook would be too early (post types for the *new* value have not been
 * registered yet in that request), so a flag is set here and consumed on the
 * next `init` after registration.
 */
function cozmic_core_flag_rewrite_flush(): void {
	update_option( 'cozmic_core_flush_rewrites', 1, false );
}
add_action( 'update_option_' . COZMIC_CORE_OPTION_SETUP, 'cozmic_core_flag_rewrite_flush' );
add_action( 'add_option_' . COZMIC_CORE_OPTION_SETUP, 'cozmic_core_flag_rewrite_flush' );

/**
 * Consume the flag. Priority 20 so every post type is registered first.
 */
function cozmic_core_maybe_flush_rewrites(): void {
	if ( ! get_option( 'cozmic_core_flush_rewrites' ) ) {
		return;
	}

	delete_option( 'cozmic_core_flush_rewrites' );
	flush_rewrite_rules( false );
}
add_action( 'init', 'cozmic_core_maybe_flush_rewrites', 20 );
