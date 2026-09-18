<?php
/**
 * Structured fields (docs D3, the hybrid content model).
 *
 * Bodies are serialized block markup in `post_content`; the scalar fields that
 * sit beside a body live here as post meta. The split follows a real capability
 * line rather than taste: Block Bindings can bind a heading, a paragraph, an
 * image, and a button to meta, and cannot express a page body or a repeating
 * group. Fields stay individually re-readable, so "update just this event's
 * date" is one meta write instead of regenerating the page.
 *
 * `cozmic_core_field_schema()` is the single source of truth. Registration, the
 * editor sidebar panel, and the block bindings source all read from it, so a new
 * field is one entry here and appears in all three. Nothing else in this plugin
 * should hardcode a meta key.
 *
 * Keys carry no leading underscore, deliberately. WordPress treats an
 * underscore-prefixed key as protected, and core's `core/post-meta` binding
 * source refuses to read protected meta - a pattern bound to `_cozmic_cta_label`
 * renders blank forever with no error anywhere. The `cozmic_` prefix gives the
 * namespacing without the side effect.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fields that apply to every public post type.
 *
 * @return array<string, array<string, string>>
 */
function cozmic_core_shared_fields(): array {
	return array(
		'cozmic_meta_description' => array(
			'label' => __( 'Search description', 'cozmic-core' ),
			'type'  => 'textarea',
			'help'  => __( 'The snippet under your link in Google. Aim for 150 characters or so. Left empty, search engines pick their own.', 'cozmic-core' ),
		),
	);
}

/**
 * The page banner fields.
 *
 * A block template has no conditionals, so a page cannot choose its own header
 * the way the platform's `header_variant` does. These three fields are that
 * choice, expressed as data the theme reads when it renders the banner in
 * page.html. They live here rather than in the theme because they are content:
 * a client who switches themes keeps them, and a theme that knows nothing about
 * them simply renders its own header.
 *
 * A template per variant would have been the obvious alternative and is the
 * wrong one - only administrators may swap a page's template, so the clients
 * who actually edit pages, who are Editors, could never reach it. A sidebar
 * field is available to anyone who can edit the page.
 *
 * @return array<string, array<string, mixed>>
 */
function cozmic_core_page_banner_fields(): array {
	return array(
		'cozmic_banner_style' => array(
			'label'   => __( 'Banner', 'cozmic-core' ),
			'type'    => 'select',
			'options' => array(
				''     => __( 'Standard', 'cozmic-core' ),
				'tall' => __( 'Tall', 'cozmic-core' ),
				'full' => __( 'Full height', 'cozmic-core' ),
				'none' => __( 'Hidden', 'cozmic-core' ),
			),
			'help'    => __( 'The band behind the page title, which shows the featured image. Hidden lets the page start with its own content instead.', 'cozmic-core' ),
		),
		'cozmic_banner_focus' => array(
			'label'   => __( 'Image position', 'cozmic-core' ),
			'type'    => 'select',
			'options' => array(
				''       => __( 'Center', 'cozmic-core' ),
				'top'    => __( 'Top', 'cozmic-core' ),
				'bottom' => __( 'Bottom', 'cozmic-core' ),
			),
			'help'    => __( 'Which part of the image survives the crop. Choose Top when the faces sit high in the photo.', 'cozmic-core' ),
		),
		'cozmic_banner_video' => array(
			'label' => __( 'Banner video', 'cozmic-core' ),
			'type'  => 'media',
			'help'  => __( 'Plays behind the title instead of the image, silently and on a loop. Keep it short, and a few MB at most.', 'cozmic-core' ),
		),
	);
}

/**
 * The field schema, by post type.
 *
 * Types are `text`, `textarea`, `url`, `datetime`, `select`, or `media`, and
 * each one determines the sanitiser on the way in and the control drawn in the
 * editor sidebar. A `select` carries its own `options` map, which doubles as
 * the list its value is validated against.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function cozmic_core_field_schema(): array {
	$cta = array(
		'cozmic_cta_label' => array(
			'label' => __( 'Button label', 'cozmic-core' ),
			'type'  => 'text',
			'help'  => __( 'For example: Get a quote.', 'cozmic-core' ),
		),
		'cozmic_cta_link'  => array(
			'label' => __( 'Button link', 'cozmic-core' ),
			'type'  => 'url',
			'help'  => __( 'Where the button goes. A path like /contact works.', 'cozmic-core' ),
		),
	);

	$schema = array(
		COZMIC_CORE_CPT_SERVICE => $cta,
		COZMIC_CORE_CPT_EVENT   => array_merge(
			array(
				'cozmic_start_date' => array(
					'label' => __( 'Starts', 'cozmic-core' ),
					'type'  => 'datetime',
					'help'  => __( 'Also what the events archive sorts by.', 'cozmic-core' ),
				),
				'cozmic_end_date'   => array(
					'label' => __( 'Ends', 'cozmic-core' ),
					'type'  => 'datetime',
				),
				'cozmic_location'   => array(
					'label' => __( 'Location', 'cozmic-core' ),
					'type'  => 'text',
					'help'  => __( 'Venue name, or an address.', 'cozmic-core' ),
				),
			),
			$cta
		),
		COZMIC_CORE_CPT_PROJECT => array_merge(
			array(
				'cozmic_project_url' => array(
					'label' => __( 'Project link', 'cozmic-core' ),
					'type'  => 'url',
					'help'  => __( 'The live site or listing for this project, if there is one.', 'cozmic-core' ),
				),
			),
			$cta
		),
		COZMIC_CORE_CPT_PRESS   => array(
			'cozmic_publication' => array(
				'label' => __( 'Publication', 'cozmic-core' ),
				'type'  => 'text',
				'help'  => __( 'Who ran the piece: a newspaper, a newsletter, a podcast.', 'cozmic-core' ),
			),
			'cozmic_source_url'  => array(
				'label' => __( 'Link to the article', 'cozmic-core' ),
				'type'  => 'url',
				'help'  => __( 'Where the piece lives. Every card links straight here, and so does this entry\'s own address.', 'cozmic-core' ),
			),
		),
		'post'                  => array(),
		'page'                  => cozmic_core_page_banner_fields(),
	);

	$shared = cozmic_core_shared_fields();
	foreach ( $schema as $post_type => $fields ) {
		$schema[ $post_type ] = array_merge( $fields, $shared );
	}

	return (array) apply_filters( 'cozmic_core_field_schema', $schema );
}

/**
 * Sanitise a value according to its declared field type.
 *
 * A `select` is checked against its own options rather than merely escaped: the
 * values are switches the theme renders from, so an unknown one should fall
 * back to the default rather than reach a template as a stray class name.
 *
 * @param mixed                $value   Raw incoming value.
 * @param string               $type    Field type from the schema.
 * @param array<string, string> $options Allowed values, for `select` only.
 */
function cozmic_core_sanitize_field( mixed $value, string $type, array $options = array() ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = (string) $value;

	return match ( $type ) {
		'url', 'media' => esc_url_raw( trim( $value ) ),
		'textarea'     => sanitize_textarea_field( $value ),
		'datetime'     => cozmic_core_sanitize_datetime( $value ),
		'select'       => array_key_exists( $value, $options ) ? $value : '',
		default        => sanitize_text_field( $value ),
	};
}

/**
 * Normalise a datetime to `Y-m-d\TH:i`, or reject it.
 *
 * The editor's datetime-local control submits exactly that shape, but a REST
 * push from the dashboard may send a full ISO 8601 string with seconds and a
 * zone. Both are accepted and stored in the same form so display formatting and
 * archive sorting never have to guess. Anything unparseable is stored as an
 * empty string rather than kept, because a half-valid date sorts an events
 * archive into nonsense.
 *
 * Stored values are *local wall-clock* time, so both the parse and the format
 * happen in the site's timezone. Reading a zoned string and then writing it out
 * with `gmdate()` was the write half of the same timezone bug the display side
 * had: a dashboard push of `2026-05-08T18:00:00-04:00` came back out as
 * `2026-05-08T22:00` and the event moved four hours.
 *
 * @param string $value Incoming datetime string.
 */
function cozmic_core_sanitize_datetime( string $value ): string {
	$moment = cozmic_core_parse_field_date( trim( $value ) );
	if ( null === $moment ) {
		return '';
	}

	// A date with no time reads as midnight, which is correct for an all-day
	// entry and harmless for anything else.
	return $moment->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' );
}

/**
 * Whether the current user may write this meta on this post.
 *
 * Meta permissions are per post, not global: an author who may edit their own
 * service must not be able to set the CTA on someone else's through the REST
 * API. `edit_post` is the capability that already encodes that whole question.
 *
 * @param bool $allowed   Default permission.
 * @param string $meta_key  Meta key being written.
 * @param int    $object_id Post ID.
 * @param int    $user_id   User attempting the write.
 */
function cozmic_core_meta_auth( bool $allowed, string $meta_key, int $object_id, int $user_id ): bool {
	unset( $allowed, $meta_key );

	return user_can( $user_id, 'edit_post', $object_id );
}

/**
 * Register every field in the schema.
 *
 * Runs at `init` priority 10, after post type registration at 5, so a type that
 * is switched off never has meta registered against it.
 */
function cozmic_core_register_meta(): void {
	foreach ( cozmic_core_field_schema() as $post_type => $fields ) {
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}

		foreach ( $fields as $key => $field ) {
			$type    = $field['type'] ?? 'text';
			$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();

			register_post_meta(
				$post_type,
				$key,
				array(
					'single'            => true,
					'type'              => 'string',
					'default'           => '',
					'show_in_rest'      => true,
					'description'       => $field['label'] ?? $key,
					'sanitize_callback' => static fn( $value ) => cozmic_core_sanitize_field( $value, $type, $options ),
					'auth_callback'     => 'cozmic_core_meta_auth',
				)
			);
		}
	}
}
add_action( 'init', 'cozmic_core_register_meta', 10 );

/**
 * Read one field off a post, with the empty string as the floor.
 *
 * @param string   $key  Meta key.
 * @param int|null $post Post ID, or null for the current post.
 */
function cozmic_core_field( string $key, ?int $post = null ): string {
	$post_id = $post ?? get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$value = get_post_meta( (int) $post_id, $key, true );

	return is_string( $value ) ? $value : '';
}

/**
 * The editor sidebar panel.
 *
 * Registered fields with no interface are a database schema, not a content
 * model: the client would have no way to set an event's date. Core's Custom
 * Fields panel is hidden behind a user preference and looks like a debugging
 * tool when it is found, so this draws a proper panel in the document sidebar.
 *
 * The script is plain JavaScript against the `wp.*` globals rather than JSX,
 * because neither this plugin nor the theme has a build step and adding one to
 * carry a single panel is a bad trade. The field definitions are handed over as
 * data from the same schema PHP registers from, so the panel cannot drift out of
 * step with what exists.
 */
function cozmic_core_enqueue_editor_fields(): void {
	$screen = get_current_screen();
	if ( ! $screen || ! $screen->is_block_editor() ) {
		return;
	}

	$schema = cozmic_core_field_schema();
	$fields = $schema[ $screen->post_type ] ?? array();
	if ( empty( $fields ) ) {
		return;
	}

	wp_enqueue_script(
		'cozmic-core-editor-fields',
		COZMIC_CORE_URL . 'assets/js/editor-fields.js',
		array( 'wp-plugins', 'wp-editor', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
		COZMIC_CORE_VERSION,
		true
	);

	wp_add_inline_script(
		'cozmic-core-editor-fields',
		'window.cozmicCoreFields = ' . wp_json_encode(
			array(
				'postType' => $screen->post_type,
				'title'    => __( 'Details', 'cozmic-core' ),
				'fields'   => $fields,
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'cozmic_core_enqueue_editor_fields' );
