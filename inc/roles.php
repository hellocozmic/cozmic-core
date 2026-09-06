<?php
/**
 * The client role (docs D8).
 *
 * The default client login is a tuned editor, not an administrator. That single
 * decision draws the product boundary, because WordPress gates Global Styles,
 * the Site Editor, and Gutenberg's block-unlock affordance all on the same
 * capability: `edit_theme_options`. Withholding it is what makes a rented site
 * rented. Granting it, along with a real Administrator account, is what a client
 * buys when they buy their site outright.
 *
 * Known consequence, stated plainly because it will come up: on a block theme
 * the navigation menu lives in a template part, so a client on this role cannot
 * edit their own menu. There is no partial grant that helps - the capability is
 * shared with everything else above, and remapping it would unlock the Site
 * Editor with it. Menu changes route through Cozmic on rented sites, exactly
 * like styling. A purchased site gets an administrator and the question
 * disappears.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COZMIC_CORE_ROLE = 'cozmic_client';

/**
 * Bump when the capability list below changes.
 *
 * `add_role()` writes once and no-ops forever after, so without a version to
 * compare against, a capability added in a later release would reach new sites
 * only and the fleet would drift apart silently, one release at a time.
 */
const COZMIC_CORE_ROLE_VERSION = 1;

/**
 * Every capability the client role holds.
 *
 * An editor's content capabilities, minus everything that can change what the
 * site *is*: no `edit_theme_options`, `manage_options`, `switch_themes`,
 * `activate_plugins`, `install_*`, `update_*`, `edit_users`, `export`, or
 * `import`.
 *
 * `unfiltered_html` stays. It is the capability that lets someone paste a
 * booking widget or a review embed into a Custom HTML block, which clients do
 * constantly, and withholding it produces the mystifying failure where a pasted
 * snippet saves and then quietly disappears.
 *
 * @return array<string, bool>
 */
function cozmic_core_role_caps(): array {
	$caps = array(
		'read',
		'upload_files',
		'unfiltered_html',

		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_private_posts',
		'publish_posts',
		'read_private_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',

		'edit_pages',
		'edit_others_pages',
		'edit_published_pages',
		'edit_private_pages',
		'publish_pages',
		'read_private_pages',
		'delete_pages',
		'delete_others_pages',
		'delete_published_pages',
		'delete_private_pages',

		'manage_categories',
		'moderate_comments',
	);

	$caps = (array) apply_filters( 'cozmic_core_role_caps', $caps );

	return array_fill_keys( $caps, true );
}

/**
 * Create the role, or bring an existing one back in line.
 *
 * Capabilities are reconciled in place rather than by removing and re-adding the
 * role. `remove_role()` would leave every user holding it with no capabilities
 * for the rest of the request, and if anything fataled between the two calls,
 * the client would be locked out of their own site.
 *
 * Reconciliation is authoritative in both directions: capabilities missing from
 * the list are added, and capabilities present on the role but absent from the
 * list are removed. A managed fleet converging on one definition is the whole
 * point, so a hand-edited role is reset rather than respected.
 */
function cozmic_core_sync_role(): void {
	$wanted = cozmic_core_role_caps();
	$role   = get_role( COZMIC_CORE_ROLE );

	if ( ! $role instanceof WP_Role ) {
		add_role( COZMIC_CORE_ROLE, __( 'Cozmic Client', 'cozmic-core' ), $wanted );
		update_option( 'cozmic_core_role_version', COZMIC_CORE_ROLE_VERSION, false );
		return;
	}

	foreach ( array_keys( $wanted ) as $cap ) {
		if ( ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
		}
	}

	foreach ( array_keys( (array) $role->capabilities ) as $cap ) {
		if ( ! isset( $wanted[ $cap ] ) ) {
			$role->remove_cap( (string) $cap );
		}
	}

	update_option( 'cozmic_core_role_version', COZMIC_CORE_ROLE_VERSION, false );
}

/**
 * Re-apply the role definition after a plugin update.
 *
 * One autoloaded option read per request, and a write only when the version
 * moves. Activation covers a fresh install; this covers every site that updates
 * in place, which after the first release is all of them.
 */
function cozmic_core_maybe_sync_role(): void {
	if ( (int) get_option( 'cozmic_core_role_version', 0 ) === COZMIC_CORE_ROLE_VERSION ) {
		return;
	}

	cozmic_core_sync_role();
}
add_action( 'init', 'cozmic_core_maybe_sync_role', 1 );

/**
 * Whether a user is on the stripped client role.
 *
 * Used by the admin governance in inc/admin.php. Administrators are never
 * treated as clients, so Cozmic's own account keeps the full screen even on a
 * site where the client's is trimmed back.
 *
 * @param int|null $user_id User to test, or null for the current user.
 */
function cozmic_core_is_client( ?int $user_id = null ): bool {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	return in_array( COZMIC_CORE_ROLE, (array) $user->roles, true );
}
