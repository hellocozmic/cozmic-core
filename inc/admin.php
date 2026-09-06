<?php
/**
 * Admin governance.
 *
 * What a client sees when they log in. Everything here is presentation of the
 * admin itself, and none of it is a security boundary - capabilities are, and
 * they are set in inc/roles.php. `remove_menu_page()` hides a link; it does not
 * stop anyone typing the URL. Strip the capability first, hide the menu second,
 * and never the other way round.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The block unlock affordance (docs D4, D8).
 *
 * Locks in a pattern are guardrails, not security: they live in block markup and
 * anyone who can reach the code editor can undo them. This filter governs
 * whether the *UI* offers to. Core already gates it on `edit_theme_options`, so
 * a client on the stripped role never sees it; this adds the per-site switch on
 * top, for a site where the client holds a stronger role but the structure
 * should still hold together.
 *
 * Deliberately not applied to anyone who can `manage_options`. Cozmic's own
 * account has to be able to unlock a component to fix it, and an admin locked
 * out of their own guardrails would just use the code editor anyway, slower and
 * with more chance of breaking something.
 *
 * @param array<string, mixed> $settings Block editor settings.
 * @return array<string, mixed>
 */
function cozmic_core_block_editor_settings( array $settings ): array {
	if ( cozmic_core_setup( 'full_editing' ) || current_user_can( 'manage_options' ) ) {
		return $settings;
	}

	$settings['canLockBlocks'] = false;

	return $settings;
}
add_filter( 'block_editor_settings_all', 'cozmic_core_block_editor_settings' );

/**
 * Tidy the client's admin menu.
 *
 * Only two things need hiding. Everything else a client should not reach is
 * already gone, because the capability behind it is not on the role and
 * WordPress does not render menus for capabilities a user lacks.
 *
 * Posts and Comments are the exception: they are core, they cannot be
 * unregistered without collateral damage, and their capabilities are the same
 * ones the client legitimately needs for pages. So when the blog is switched
 * off, they are hidden. The front end is unaffected - if a blog archive is
 * reachable it stays reachable, which is intentional, because a site that had a
 * blog yesterday should not start 404ing its old posts today.
 */
function cozmic_core_tidy_admin_menu(): void {
	if ( ! cozmic_core_is_client() ) {
		return;
	}

	if ( ! cozmic_core_setup( 'blog_enabled' ) ) {
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'edit-comments.php' );
	}
}
add_action( 'admin_menu', 'cozmic_core_tidy_admin_menu', 999 );

/**
 * Quieten the dashboard for clients.
 *
 * WordPress news and Quick Draft are noise on a business site: one is a feed of
 * release notes the client has no use for, the other publishes a blog post from
 * a box they did not mean to type in. At a Glance and Activity stay, because
 * "you have 12 pages and 3 drafts" is genuinely useful.
 */
function cozmic_core_tidy_dashboard(): void {
	if ( ! cozmic_core_is_client() ) {
		return;
	}

	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
}
add_action( 'wp_dashboard_setup', 'cozmic_core_tidy_dashboard' );

/**
 * Say who looks after the site.
 *
 * Replaces the "Thank you for creating with WordPress" line for clients only.
 * On a managed site the useful information in that spot is who to ask for help.
 */
function cozmic_core_admin_footer( string $text ): string {
	if ( ! cozmic_core_is_client() ) {
		return $text;
	}

	$url  = (string) apply_filters( 'cozmic_core_support_url', 'https://cozmiconline.com/support' );
	$link = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( $url ),
		esc_html__( 'Cozmic Online', 'cozmic-core' )
	);

	/* translators: %s: link to Cozmic Online. */
	return sprintf( esc_html__( 'Website managed by %s.', 'cozmic-core' ), $link );
}
add_filter( 'admin_footer_text', 'cozmic_core_admin_footer' );
