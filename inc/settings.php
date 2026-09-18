<?php
/**
 * The settings screens.
 *
 * Two pages under one "Cozmic" menu, split by who may write them, which is the
 * same D8 line the role draws:
 *
 *   Business info  the client's own details. Capability `edit_pages`, so the
 *                  client role reaches it.
 *   Site setup     which content types exist, and how much editing freedom the
 *                  site has. Capability `manage_options`, so only Cozmic does.
 *
 * Both options are registered with `show_in_rest`, which is what lets Cozmic
 * Connect push a business profile at provisioning over `/wp/v2/settings` instead
 * of this plugin growing a bespoke endpoint with its own auth to get wrong.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COZMIC_CORE_MENU_SLUG    = 'cozmic-core';
const COZMIC_CORE_SETUP_SLUG   = 'cozmic-core-setup';
const COZMIC_CORE_ARCHIVE_SLUG = 'cozmic-core-archives';

/**
 * The layout choices an archive offers.
 *
 * Deliberately few. These are the shapes the theme's archive templates can
 * actually take without new markup: a count of columns, or rows with the image
 * beside the text, which is what a list of press mentions or long titles wants.
 *
 * @return array<string, string>
 */
function cozmic_core_archive_layouts(): array {
	return array(
		''     => __( 'Default for this content type', 'cozmic-core' ),
		'list' => __( 'List, image beside the text', 'cozmic-core' ),
		'2'    => __( 'Two across', 'cozmic-core' ),
		'3'    => __( 'Three across', 'cozmic-core' ),
		'4'    => __( 'Four across', 'cozmic-core' ),
	);
}

/**
 * Archive page fields, grouped by content type.
 *
 * Built from whichever types are switched on, so the screen never offers to set
 * a banner for an archive that does not exist. A `section` row is a heading, not
 * an input: it carries no value and nothing reads it back.
 *
 * @return array<string, array<string, mixed>>
 */
function cozmic_core_archive_fields(): array {
	$fields = array();

	foreach ( cozmic_core_post_types() as $post_type => $type ) {
		if ( ! cozmic_core_type_enabled( $type ) ) {
			continue;
		}

		$object = get_post_type_object( $post_type );
		$label  = $object instanceof WP_Post_Type ? $object->labels->name : ucfirst( $type );

		$fields[ $type . '_section' ] = array(
			'label' => $label,
			'type'  => 'section',
		);

		$fields[ $type . '_image' ]  = array(
			'label' => __( 'Banner image', 'cozmic-core' ),
			'type'  => 'media',
			'help'  => __( 'Sits behind the title at the top of the page. Left empty, the banner is a plain colour band.', 'cozmic-core' ),
		);
		$fields[ $type . '_intro' ]  = array(
			'label' => __( 'Introduction', 'cozmic-core' ),
			'type'  => 'textarea',
			'help'  => __( 'A sentence or two above the list. Left empty, the list starts straight away.', 'cozmic-core' ),
		);
		$fields[ $type . '_layout' ] = array(
			'label'   => __( 'Layout', 'cozmic-core' ),
			'type'    => 'select',
			'options' => cozmic_core_archive_layouts(),
		);
	}

	return $fields;
}

/**
 * Business profile fields, in display order.
 *
 * Keys mirror the platform's `sites` columns one for one, so a provisioning push
 * is a copy rather than a translation, and the LocalBusiness JSON-LD comes out
 * the same shape on both product tiers.
 *
 * @return array<string, array<string, string>>
 */
function cozmic_core_business_fields(): array {
	return array(
		'business_name'    => array(
			'label' => __( 'Business name', 'cozmic-core' ),
			'type'  => 'text',
			'help'  => __( 'Left empty, the site title is used.', 'cozmic-core' ),
		),
		'phone'            => array(
			'label' => __( 'Phone', 'cozmic-core' ),
			'type'  => 'text',
		),
		'email'            => array(
			'label' => __( 'Email', 'cozmic-core' ),
			'type'  => 'email',
		),
		'address'          => array(
			'label' => __( 'Address', 'cozmic-core' ),
			'type'  => 'textarea',
		),
		'hide_address'     => array(
			'label' => __( 'Keep the address private', 'cozmic-core' ),
			'type'  => 'checkbox',
			'help'  => __( 'For a business that works out of a home or travels to customers. The address stays out of the page and out of the search engine data.', 'cozmic-core' ),
		),
		'service_area'     => array(
			'label' => __( 'Service area', 'cozmic-core' ),
			'type'  => 'text',
			'help'  => __( 'The towns or region you cover. Safe to fill in even with the address hidden.', 'cozmic-core' ),
		),
		'price_range'      => array(
			'label' => __( 'Price range', 'cozmic-core' ),
			'type'  => 'text',
			'help'  => __( 'Shown to search engines, not on the page. Dollar signs, such as $$.', 'cozmic-core' ),
		),
		'hours'            => array(
			'label' => __( 'Hours', 'cozmic-core' ),
			'type'  => 'textarea',
		),
		'google_maps_link' => array(
			'label' => __( 'Google Maps link', 'cozmic-core' ),
			'type'  => 'url',
		),
		'social_links'     => array(
			'label' => __( 'Social profiles', 'cozmic-core' ),
			'type'  => 'lines',
			'help'  => __( 'One address per line.', 'cozmic-core' ),
		),
	);
}

/**
 * Site setup fields, in display order.
 *
 * @return array<string, array<string, string>>
 */
function cozmic_core_setup_fields(): array {
	return array(
		'services_enabled'  => array(
			'label' => __( 'Services', 'cozmic-core' ),
			'type'  => 'checkbox',
		),
		'events_enabled'    => array(
			'label' => __( 'Events', 'cozmic-core' ),
			'type'  => 'checkbox',
		),
		'portfolio_enabled' => array(
			'label' => __( 'Portfolio', 'cozmic-core' ),
			'type'  => 'checkbox',
		),
		'blog_enabled'      => array(
			'label' => __( 'Blog', 'cozmic-core' ),
			'type'  => 'checkbox',
			'help'  => __( 'Blog posts are built into WordPress, so switching this off hides them from the menu rather than removing them.', 'cozmic-core' ),
		),
		'press_enabled'     => array(
			'label' => __( 'In The News', 'cozmic-core' ),
			'type'  => 'checkbox',
			'help'  => __( 'Coverage by somebody else. Each entry links out to the article, and they collect on one page.', 'cozmic-core' ),
		),
		'full_editing'      => array(
			'label' => __( 'Full editing control', 'cozmic-core' ),
			'type'  => 'checkbox',
			'help'  => __( 'Lets the client unlock the inside of a section. Purchased sites get this along with an administrator account.', 'cozmic-core' ),
		),
	);
}

/**
 * Merge submitted values over what is already stored.
 *
 * Merging over the *stored* row rather than over the defaults is what makes a
 * partial write safe: Connect can PATCH one field over REST without silently
 * resetting the nine it did not send. The form pairs every checkbox with a
 * hidden zero, so an unticked box still arrives and can still turn something
 * off.
 *
 * @param mixed                             $input     Submitted values.
 * @param string                            $option    Option name.
 * @param array<string, array<string, string>> $fields  Field definitions.
 * @param array<string, mixed>              $defaults  Option defaults.
 * @return array<string, mixed>
 */
function cozmic_core_merge_settings( mixed $input, string $option, array $fields, array $defaults ): array {
	$stored = get_option( $option, array() );
	$values = array_merge( $defaults, is_array( $stored ) ? $stored : array() );

	if ( ! is_array( $input ) ) {
		return $values;
	}

	foreach ( $fields as $key => $field ) {
		if ( ! array_key_exists( $key, $input ) ) {
			continue;
		}

		$raw = $input[ $key ];

		switch ( $field['type'] ?? 'text' ) {
			case 'checkbox':
				$values[ $key ] = (bool) $raw;
				break;

			case 'email':
				$values[ $key ] = is_scalar( $raw ) ? sanitize_email( (string) $raw ) : '';
				break;

			case 'url':
				$values[ $key ] = is_scalar( $raw ) ? esc_url_raw( trim( (string) $raw ) ) : '';
				break;

			case 'textarea':
				$values[ $key ] = is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';
				break;

			case 'media':
				$values[ $key ] = is_scalar( $raw ) ? esc_url_raw( trim( (string) $raw ) ) : '';
				break;

			case 'select':
				$allowed        = is_array( $field['options'] ?? null ) ? $field['options'] : array();
				$values[ $key ] = is_scalar( $raw ) && array_key_exists( (string) $raw, $allowed ) ? (string) $raw : '';
				break;

			case 'lines':
				$lines          = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
				$values[ $key ] = array_values(
					array_filter(
						array_map(
							static fn( $line ) => esc_url_raw( trim( (string) $line ) ),
							(array) $lines
						)
					)
				);
				break;

			default:
				$values[ $key ] = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
		}
	}

	return $values;
}

/**
 * Register both options.
 *
 * On `init` rather than `admin_init` so the REST settings controller sees them
 * too - registering only for the admin is the usual reason a settings PUT comes
 * back with "invalid parameter" and no further explanation.
 */
function cozmic_core_register_settings(): void {
	register_setting(
		'cozmic_core_business_group',
		COZMIC_CORE_OPTION_BUSINESS,
		array(
			'type'              => 'object',
			'default'           => cozmic_core_business_defaults(),
			'sanitize_callback' => static fn( $input ) => cozmic_core_merge_settings(
				$input,
				COZMIC_CORE_OPTION_BUSINESS,
				cozmic_core_business_fields(),
				cozmic_core_business_defaults()
			),
			'show_in_rest'      => array(
				'name'   => 'cozmic_business',
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'business_name'    => array( 'type' => 'string' ),
						'phone'            => array( 'type' => 'string' ),
						'email'            => array( 'type' => 'string' ),
						'address'          => array( 'type' => 'string' ),
						'hide_address'     => array( 'type' => 'boolean' ),
						'service_area'     => array( 'type' => 'string' ),
						'price_range'      => array( 'type' => 'string' ),
						'hours'            => array( 'type' => 'string' ),
						'google_maps_link' => array( 'type' => 'string' ),
						'social_links'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
			),
		)
	);

	register_setting(
		'cozmic_core_archives_group',
		COZMIC_CORE_OPTION_ARCHIVES,
		array(
			'type'              => 'object',
			'default'           => cozmic_core_archive_defaults(),
			'sanitize_callback' => static fn( $input ) => cozmic_core_merge_settings(
				$input,
				COZMIC_CORE_OPTION_ARCHIVES,
				cozmic_core_archive_fields(),
				cozmic_core_archive_defaults()
			),
			/*
			 * The keys are built from whichever content types exist, so the
			 * schema takes any string value rather than naming each one and
			 * going stale the moment a type is added.
			 */
			'show_in_rest'      => array(
				'name'   => 'cozmic_archives',
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'string' ),
				),
			),
		)
	);

	register_setting(
		'cozmic_core_setup_group',
		COZMIC_CORE_OPTION_SETUP,
		array(
			'type'              => 'object',
			'default'           => cozmic_core_setup_defaults(),
			'sanitize_callback' => static fn( $input ) => cozmic_core_merge_settings(
				$input,
				COZMIC_CORE_OPTION_SETUP,
				cozmic_core_setup_fields(),
				cozmic_core_setup_defaults()
			),
			'show_in_rest'      => array(
				'name'   => 'cozmic_setup',
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'services_enabled'  => array( 'type' => 'boolean' ),
						'events_enabled'    => array( 'type' => 'boolean' ),
						'portfolio_enabled' => array( 'type' => 'boolean' ),
						'blog_enabled'      => array( 'type' => 'boolean' ),
						'press_enabled'     => array( 'type' => 'boolean' ),
						'full_editing'      => array( 'type' => 'boolean' ),
					),
				),
			),
		)
	);
}
add_action( 'init', 'cozmic_core_register_settings' );

/**
 * Let the client save the business page.
 *
 * `options.php` demands `manage_options` for every settings group unless told
 * otherwise, so without this the page renders for a client and then rejects the
 * save with a bare permissions error.
 */
function cozmic_core_business_capability(): string {
	return 'edit_pages';
}
add_filter( 'option_page_capability_cozmic_core_business_group', 'cozmic_core_business_capability' );
add_filter( 'option_page_capability_cozmic_core_archives_group', 'cozmic_core_business_capability' );

/**
 * The admin menu.
 */
function cozmic_core_admin_menu(): void {
	add_menu_page(
		__( 'Cozmic', 'cozmic-core' ),
		__( 'Cozmic', 'cozmic-core' ),
		'edit_pages',
		COZMIC_CORE_MENU_SLUG,
		'cozmic_core_render_business_page',
		'dashicons-admin-site-alt3',
		3
	);

	add_submenu_page(
		COZMIC_CORE_MENU_SLUG,
		__( 'Business Info', 'cozmic-core' ),
		__( 'Business Info', 'cozmic-core' ),
		'edit_pages',
		COZMIC_CORE_MENU_SLUG,
		'cozmic_core_render_business_page'
	);

	add_submenu_page(
		COZMIC_CORE_MENU_SLUG,
		__( 'Content Pages', 'cozmic-core' ),
		__( 'Content Pages', 'cozmic-core' ),
		'edit_pages',
		COZMIC_CORE_ARCHIVE_SLUG,
		'cozmic_core_render_archives_page'
	);

	add_submenu_page(
		COZMIC_CORE_MENU_SLUG,
		__( 'Site Setup', 'cozmic-core' ),
		__( 'Site Setup', 'cozmic-core' ),
		'manage_options',
		COZMIC_CORE_SETUP_SLUG,
		'cozmic_core_render_setup_page'
	);
}
add_action( 'admin_menu', 'cozmic_core_admin_menu' );

/**
 * Draw one field.
 *
 * @param string               $option Option name, used as the input prefix.
 * @param string               $key    Field key.
 * @param array<string, string> $field  Field definition.
 * @param mixed                $value  Current value.
 */
function cozmic_core_render_field( string $option, string $key, array $field, mixed $value ): void {
	$name  = sprintf( '%s[%s]', $option, $key );
	$id    = $option . '-' . $key;
	$type  = $field['type'] ?? 'text';
	$help  = $field['help'] ?? '';
	$label = $field['label'] ?? $key;

	// A heading between groups of fields, rather than a field.
	if ( 'section' === $type ) {
		echo '<tr><th colspan="2" style="padding-bottom:0"><h2 class="title" style="margin-bottom:0">' . esc_html( $label ) . '</h2></th></tr>';

		return;
	}

	echo '<tr>';
	echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
	echo '<td>';

	switch ( $type ) {
		case 'checkbox':
			// The hidden zero is what makes unticking a box mean something: an
			// unchecked checkbox posts nothing at all, so without it the value
			// would never travel and the setting could only ever be turned on.
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' />';
			break;

		case 'textarea':
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text">' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>';
			break;

		case 'lines':
			$lines = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text code">' . esc_textarea( $lines ) . '</textarea>';
			break;

		case 'select':
			$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $option_value => $option_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( (string) $option_value ),
					selected( (string) $value, (string) $option_value, false ),
					esc_html( (string) $option_label )
				);
			}
			echo '</select>';
			break;

		/*
		 * A URL with a picker in front of it. The field stores the URL rather
		 * than an attachment ID for the same reason the editor's media field
		 * does: every reader wants something it can render without a second
		 * lookup. Typing or pasting a URL keeps working if the picker's script
		 * never loads.
		 */
		case 'media':
			$url = is_scalar( $value ) ? (string) $value : '';
			echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $url ) . '" class="regular-text cozmic-core-media-field" />';
			echo ' <button type="button" class="button cozmic-core-media-pick" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Choose image', 'cozmic-core' ) . '</button>';
			echo '<div class="cozmic-core-media-preview" style="margin-top:8px">';
			if ( '' !== $url ) {
				echo '<img src="' . esc_url( $url ) . '" alt="" style="max-width:240px;height:auto;border-radius:4px" />';
			}
			echo '</div>';
			break;

		default:
			$input_type = in_array( $type, array( 'email', 'url' ), true ) ? $type : 'text';
			echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="regular-text" />';
	}

	if ( $help ) {
		echo '<p class="description">' . esc_html( $help ) . '</p>';
	}

	echo '</td></tr>';
}

/**
 * Shared page shell.
 *
 * @param string                              $title    Page heading.
 * @param string                              $intro    Short description under the heading.
 * @param string                              $group    Settings group.
 * @param string                              $option   Option name.
 * @param array<string, array<string, string>> $fields  Field definitions.
 * @param array<string, mixed>                $values   Current values.
 */
function cozmic_core_render_settings_page( string $title, string $intro, string $group, string $option, array $fields, array $values ): void {
	echo '<div class="wrap">';
	echo '<h1>' . esc_html( $title ) . '</h1>';
	echo '<p class="description">' . esc_html( $intro ) . '</p>';
	echo '<form method="post" action="options.php">';

	settings_fields( $group );

	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $key => $field ) {
		cozmic_core_render_field( $option, $key, $field, $values[ $key ] ?? '' );
	}
	echo '</tbody></table>';

	submit_button();

	echo '</form></div>';
}

/**
 * Business info screen.
 */
function cozmic_core_render_business_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$defaults = cozmic_core_business_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_BUSINESS, array() );

	cozmic_core_render_settings_page(
		__( 'Business Info', 'cozmic-core' ),
		__( 'Used in your footer, your contact details, and the business data search engines read.', 'cozmic-core' ),
		'cozmic_core_business_group',
		COZMIC_CORE_OPTION_BUSINESS,
		cozmic_core_business_fields(),
		array_merge( $defaults, is_array( $stored ) ? $stored : array() )
	);
}

/**
 * Content pages screen.
 *
 * The banner, introduction and layout of each content type's listing page. A
 * client edits this, hence `edit_pages`: it is content about their content, not
 * a structural switch.
 *
 * With no content types switched on there is nothing to configure, and the
 * screen says so rather than rendering an empty form with a Save button.
 */
function cozmic_core_render_archives_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$fields = cozmic_core_archive_fields();

	if ( empty( $fields ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Content Pages', 'cozmic-core' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Nothing to set up yet. Switch on a content type such as Services or In The News, and its listing page appears here.', 'cozmic-core' ) . '</p></div>';

		return;
	}

	$defaults = cozmic_core_archive_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_ARCHIVES, array() );

	cozmic_core_render_settings_page(
		__( 'Content Pages', 'cozmic-core' ),
		__( 'The page that lists everything of one kind: its banner, an introduction, and how the entries are arranged.', 'cozmic-core' ),
		'cozmic_core_archives_group',
		COZMIC_CORE_OPTION_ARCHIVES,
		$fields,
		array_merge( $defaults, is_array( $stored ) ? $stored : array() )
	);
}

/**
 * The media picker on the Content Pages screen.
 *
 * Plain wp.media against the library core already loads in the admin, for the
 * same reason the editor panel avoids a build step. The field is a URL input
 * either way, so a browser that never runs this still has a working form.
 */
function cozmic_core_enqueue_media_picker( string $hook ): void {
	if ( ! str_contains( $hook, COZMIC_CORE_ARCHIVE_SLUG ) ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script(
		'media-editor',
		<<<'JS'
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.cozmic-core-media-pick' );
			if ( ! button || ! window.wp || ! window.wp.media ) {
				return;
			}

			event.preventDefault();

			var input = document.getElementById( button.dataset.target );
			var frame = window.wp.media( {
				title: 'Choose image',
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				var url = ( image.sizes && image.sizes.large ? image.sizes.large.url : image.url ) || '';
				input.value = url;

				var preview = button.parentNode.querySelector( '.cozmic-core-media-preview' );
				if ( preview ) {
					preview.innerHTML = url ? '<img src="" alt="" style="max-width:240px;height:auto;border-radius:4px" />' : '';
					if ( url ) {
						preview.querySelector( 'img' ).src = url;
					}
				}
			} );

			frame.open();
		} );
		JS
	);
}
add_action( 'admin_enqueue_scripts', 'cozmic_core_enqueue_media_picker' );

/**
 * Site setup screen.
 */
function cozmic_core_render_setup_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$defaults = cozmic_core_setup_defaults();
	$stored   = get_option( COZMIC_CORE_OPTION_SETUP, array() );

	cozmic_core_render_settings_page(
		__( 'Site Setup', 'cozmic-core' ),
		__( 'Which content types this site uses, and how much of the page structure the client can take apart.', 'cozmic-core' ),
		'cozmic_core_setup_group',
		COZMIC_CORE_OPTION_SETUP,
		cozmic_core_setup_fields(),
		array_merge( $defaults, is_array( $stored ) ? $stored : array() )
	);
}

/**
 * Settings link on the Plugins screen.
 *
 * @param array<int, string> $links Existing action links.
 * @return array<int, string>
 */
function cozmic_core_plugin_action_links( array $links ): array {
	$slug = current_user_can( 'manage_options' ) ? COZMIC_CORE_SETUP_SLUG : COZMIC_CORE_MENU_SLUG;

	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . $slug ) ),
			esc_html__( 'Settings', 'cozmic-core' )
		)
	);

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( COZMIC_CORE_FILE ), 'cozmic_core_plugin_action_links' );
