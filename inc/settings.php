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

const COZMIC_CORE_MENU_SLUG  = 'cozmic-core';
const COZMIC_CORE_SETUP_SLUG = 'cozmic-core-setup';

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
