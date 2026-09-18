<?php
/**
 * Plugin Name:       Cozmic Core
 * Plugin URI:        https://github.com/hellocozmic/cozmic-core
 * Description:       The floor every Cozmic client site stands on: content model, client role, options, governance, and structural SEO. Never optional - deactivating it collapses the content model.
 * Version:           0.4.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Cozmic Online
 * Author URI:        https://cozmiconline.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cozmic-core
 * Update URI:        https://github.com/hellocozmic/cozmic-core
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The boundary this plugin owns (docs D9, "what breaks if this is removed?"):
 *
 *   Cozmic Core   what exists and what it does - post types, meta, roles,
 *                 options, governance, structural SEO hooks. NEVER removable.
 *   Parent theme  how it looks and where it goes - theme.json, templates,
 *                 patterns, block styles.
 *   Cozmic Connect  talking to the Cozmic dashboard - REST, telemetry, form
 *                 forwarding. Removable; the site does not care.
 *
 * The test for a new file: if removing it would cost the client *content*, it
 * belongs here. If it would only change how that content looks, it belongs in
 * the theme.
 *
 * Naming: plugin slug and text domain are `cozmic-core` (WordPress's
 * just-in-time translation loading requires the text domain to match the
 * directory), the PHP prefix stays the shorter `cozmic_core_`, and patterns
 * registered from here use the Cozmic-wide `cozmic/` namespace shared with the
 * theme.
 */

define( 'COZMIC_CORE_VERSION', '0.4.0' );
define( 'COZMIC_CORE_FILE', __FILE__ );
define( 'COZMIC_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'COZMIC_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * No file editing from wp-admin, on any site, for any role.
 *
 * The theme and plugin editors are a live-code-execution surface reachable by
 * anyone who gets an administrator session, and on a managed fleet nothing is
 * ever legitimately edited there - deploys come from git and from Plugin Update
 * Checker. Defined rather than filtered because that is the only thing core
 * reads. Left overridable from wp-config.php, which loads first.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

require_once COZMIC_CORE_DIR . 'inc/options.php';
require_once COZMIC_CORE_DIR . 'inc/post-types.php';
require_once COZMIC_CORE_DIR . 'inc/meta.php';
require_once COZMIC_CORE_DIR . 'inc/bindings.php';
require_once COZMIC_CORE_DIR . 'inc/roles.php';
require_once COZMIC_CORE_DIR . 'inc/admin.php';
require_once COZMIC_CORE_DIR . 'inc/settings.php';
require_once COZMIC_CORE_DIR . 'inc/seo.php';
require_once COZMIC_CORE_DIR . 'inc/gravity-forms.php';
require_once COZMIC_CORE_DIR . 'inc/updates.php';

/**
 * Activation.
 *
 * Post types are registered on `init`, which has already run by the time an
 * activation hook fires, so registration is repeated here before flushing -
 * otherwise the flush writes rewrite rules that know nothing about the CPTs and
 * every archive 404s until the next permalink save.
 */
function cozmic_core_activate(): void {
	cozmic_core_seed_options();
	cozmic_core_sync_role();
	cozmic_core_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cozmic_core_activate' );

/**
 * Deactivation.
 *
 * Rewrite rules are flushed so the CPT archive URLs stop resolving, but the
 * role is deliberately left in place: removing it would strip every client user
 * of their capabilities the moment the plugin is toggled off, which turns a
 * reversible mistake into a lockout. Content is never touched, and there is no
 * uninstall.php for the same reason - a client's posts outliving the plugin is
 * the correct behaviour, not an oversight.
 */
function cozmic_core_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cozmic_core_deactivate' );
