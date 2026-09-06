<?php
/**
 * The content model.
 *
 * Three custom post types matching the platform's content types (CLAUDE.md
 * section 6). Blog posts are core `post` and products are deferred with the
 * commerce plugin (docs D5), so nothing is registered for either.
 *
 * Registered keys are prefixed (`cozmic_service`) because `service` and `event`
 * are exactly the names a booking or calendar plugin will try to claim, and a
 * collision means one of the two silently loses. What the client and the
 * dashboard actually see stays unprefixed: the rewrite slug is /services/ and
 * the REST base is `services`, both set explicitly below.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COZMIC_CORE_CPT_SERVICE = 'cozmic_service';
const COZMIC_CORE_CPT_EVENT   = 'cozmic_event';
const COZMIC_CORE_CPT_PROJECT = 'cozmic_project';

/**
 * The post types this plugin owns, mapped to the setup flag that gates each.
 *
 * @return array<string, string> Post type key => content type slug.
 */
function cozmic_core_post_types(): array {
	return array(
		COZMIC_CORE_CPT_SERVICE => 'services',
		COZMIC_CORE_CPT_EVENT   => 'events',
		COZMIC_CORE_CPT_PROJECT => 'portfolio',
	);
}

/**
 * Build a full label set from a singular and plural name.
 *
 * WordPress falls back to the "Posts" vocabulary for any label left unset,
 * which is how a client ends up reading "Add New Post" on the Services screen.
 *
 * @param string $singular Singular display name.
 * @param string $plural   Plural display name.
 * @return array<string, string>
 */
function cozmic_core_labels( string $singular, string $plural ): array {
	return array(
		'name'                  => $plural,
		'singular_name'         => $singular,
		'add_new'               => __( 'Add New', 'cozmic-core' ),
		/* translators: %s: singular post type name. */
		'add_new_item'          => sprintf( __( 'Add New %s', 'cozmic-core' ), $singular ),
		/* translators: %s: singular post type name. */
		'edit_item'             => sprintf( __( 'Edit %s', 'cozmic-core' ), $singular ),
		/* translators: %s: singular post type name. */
		'new_item'              => sprintf( __( 'New %s', 'cozmic-core' ), $singular ),
		/* translators: %s: singular post type name. */
		'view_item'             => sprintf( __( 'View %s', 'cozmic-core' ), $singular ),
		/* translators: %s: plural post type name. */
		'view_items'            => sprintf( __( 'View %s', 'cozmic-core' ), $plural ),
		/* translators: %s: plural post type name. */
		'search_items'          => sprintf( __( 'Search %s', 'cozmic-core' ), $plural ),
		/* translators: %s: lowercase plural post type name. */
		'not_found'             => sprintf( __( 'No %s yet.', 'cozmic-core' ), strtolower( $plural ) ),
		/* translators: %s: lowercase plural post type name. */
		'not_found_in_trash'    => sprintf( __( 'No %s in the trash.', 'cozmic-core' ), strtolower( $plural ) ),
		/* translators: %s: plural post type name. */
		'all_items'             => sprintf( __( 'All %s', 'cozmic-core' ), $plural ),
		'archives'              => $plural,
		/* translators: %s: singular post type name. */
		'featured_image'        => sprintf( __( '%s image', 'cozmic-core' ), $singular ),
		'set_featured_image'    => __( 'Set image', 'cozmic-core' ),
		'remove_featured_image' => __( 'Remove image', 'cozmic-core' ),
		'use_featured_image'    => __( 'Use as image', 'cozmic-core' ),
		'menu_name'             => $plural,
	);
}

/**
 * Register the content model.
 *
 * A type that is switched off is simply not registered, which hides it from the
 * admin, the front end, and the menu editor in one move. Nothing is deleted:
 * the rows stay in wp_posts and reappear intact when the type is switched back
 * on. That mirrors the platform, where disabling a content type keeps the
 * content.
 *
 * Capabilities map to `post`, so the client role's ordinary post capabilities
 * cover every type without a second set to keep in sync. Custom capability
 * types would mean every role growing twelve more capabilities per content
 * type, and the first one missed is a client who cannot publish a service with
 * no visible reason why.
 */
function cozmic_core_register_post_types(): void {
	$shared = array(
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => true,
		'show_in_rest'       => true,
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'hierarchical'       => false,

		/*
		 * `custom-fields` is not decoration. The REST controller only exposes
		 * registered meta when the post type declares support for it, so
		 * without this the dashboard's content push could create a service but
		 * never set its CTA, and Block Bindings would read empty. The editor's
		 * Custom Fields panel stays hidden behind a user preference, so nothing
		 * ugly surfaces from turning it on.
		 */
		'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes', 'revisions' ),
	);

	if ( cozmic_core_type_enabled( 'services' ) ) {
		register_post_type(
			COZMIC_CORE_CPT_SERVICE,
			array_merge(
				$shared,
				array(
					'labels'        => cozmic_core_labels( __( 'Service', 'cozmic-core' ), __( 'Services', 'cozmic-core' ) ),
					'description'   => __( 'What the business does, one entry per service.', 'cozmic-core' ),
					'menu_icon'     => 'dashicons-hammer',
					'menu_position' => 21,
					'rest_base'     => 'services',
					'has_archive'   => 'services',
					'rewrite'       => array(
						'slug'       => 'services',
						'with_front' => false,
					),
				)
			)
		);
	}

	if ( cozmic_core_type_enabled( 'events' ) ) {
		register_post_type(
			COZMIC_CORE_CPT_EVENT,
			array_merge(
				$shared,
				array(
					'labels'        => cozmic_core_labels( __( 'Event', 'cozmic-core' ), __( 'Events', 'cozmic-core' ) ),
					'description'   => __( 'Dated happenings, with a start, an end, and a place.', 'cozmic-core' ),
					'menu_icon'     => 'dashicons-calendar-alt',
					'menu_position' => 22,
					'rest_base'     => 'events',
					'has_archive'   => 'events',
					'rewrite'       => array(
						'slug'       => 'events',
						'with_front' => false,
					),
				)
			)
		);
	}

	if ( cozmic_core_type_enabled( 'portfolio' ) ) {
		register_post_type(
			COZMIC_CORE_CPT_PROJECT,
			array_merge(
				$shared,
				array(
					'labels'        => cozmic_core_labels( __( 'Project', 'cozmic-core' ), __( 'Portfolio', 'cozmic-core' ) ),
					'description'   => __( 'Finished work, shown off.', 'cozmic-core' ),
					'menu_icon'     => 'dashicons-portfolio',
					'menu_position' => 23,
					'rest_base'     => 'portfolio',
					'has_archive'   => 'portfolio',
					'rewrite'       => array(
						'slug'       => 'portfolio',
						'with_front' => false,
					),
				)
			)
		);
	}
}
add_action( 'init', 'cozmic_core_register_post_types', 5 );

/**
 * Archive ordering.
 *
 * Services and portfolio follow the client's own hand ordering (`menu_order`,
 * which a dashboard push fills from the platform's `display_order`). Events
 * sort by start date, soonest first, because chronological is the only ordering
 * an events archive can defensibly have.
 *
 * The obvious way to sort by a meta value, `meta_key` plus
 * `orderby => meta_value`, quietly drops every post with no such meta row, so a
 * single undated event would vanish from the archive rather than sort last. The
 * named meta_query clauses below with an EXISTS / NOT EXISTS pair keep them all
 * and still sort by the date where there is one.
 *
 * @param WP_Query $query The query about to run.
 */
function cozmic_core_archive_order( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_post_type_archive( array( COZMIC_CORE_CPT_SERVICE, COZMIC_CORE_CPT_PROJECT ) ) ) {
		$query->set(
			'orderby',
			array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			)
		);
		return;
	}

	if ( $query->is_post_type_archive( COZMIC_CORE_CPT_EVENT ) ) {
		$query->set(
			'meta_query',
			array(
				'relation'  => 'OR',
				'has_start' => array(
					'key'     => 'cozmic_start_date',
					'compare' => 'EXISTS',
				),
				'no_start'  => array(
					'key'     => 'cozmic_start_date',
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set(
			'orderby',
			array(
				'has_start'  => 'ASC',
				'menu_order' => 'ASC',
			)
		);
	}
}
add_action( 'pre_get_posts', 'cozmic_core_archive_order' );
