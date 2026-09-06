<?php
/**
 * Self-hosted updates via GitHub Releases.
 *
 * Identical machinery to the parent theme's, pointed at this repository. Plugin
 * Update Checker makes WordPress treat Cozmic Core exactly like a plugin from
 * wordpress.org: the update appears on the Plugins screen and WordPress's own
 * auto-update toggle works on it.
 *
 * PUC prefers the latest GitHub *Release* over tags or branch tips, which is
 * what makes tagging the deliberate gate. Commit and push as freely as you like;
 * nothing reaches a client site until a Release exists. Until the first one is
 * tagged, PUC finds nothing, and that is expected rather than broken.
 *
 * The version PUC compares against is the `Version:` header in cozmic-core.php,
 * so that header and COZMIC_CORE_VERSION must move together with the tag. A
 * forgotten bump means the update is silently never offered.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wire the update checker.
 *
 * Only the admin and cron ever consult update transients, so the front end
 * never pays to load roughly forty classes it will not use. On a fleet of local
 * business sites that is a real saving on every page render.
 */
function cozmic_core_init_update_checker(): void {
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}

	require_once COZMIC_CORE_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

	\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/hellocozmic/cozmic-core/',
		COZMIC_CORE_FILE,
		'cozmic-core'
	);
}
add_action( 'init', 'cozmic_core_init_update_checker' );
