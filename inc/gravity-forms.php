<?php
/**
 * Gravity Forms defaults.
 *
 * Gravity Forms is the form plugin on every Cozmic WordPress site, covered by
 * the agency licence while Cozmic maintains the site. It ships sensible for a
 * general audience and slightly wrong for this fleet in two specific ways, both
 * fixed here so nobody has to remember them per site.
 *
 * Everything in this file no-ops when Gravity Forms is not installed, so the
 * plugin stays valid on a site that never gets it.
 *
 * @package CozmicCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send notifications from the site, and reply to the customer.
 *
 * Gravity Forms defaults a notification's From address to the address the
 * visitor typed into the form. That was reasonable in 2010 and is now the
 * single most common reason a client stops receiving their leads: the mail
 * claims to come from gmail.com, the sending server has no authority to speak
 * for gmail.com, and SPF and DMARC put it in spam or refuse it outright. The
 * failure is silent on both ends. The client concludes the form is broken; the
 * customer concludes they were ignored.
 *
 * So the From address becomes the site's own domain, which it can prove it owns,
 * and the visitor's address moves to Reply-To, where hitting reply still goes
 * straight back to them. Nothing is lost and the mail arrives.
 *
 * Only rewritten when the From address is a merge tag or sits on another
 * domain. A notification already configured to send from the business's own
 * address is left exactly as it is.
 *
 * @param array<string, mixed> $notification The notification about to send.
 * @return array<string, mixed>
 */
function cozmic_core_gf_notification( array $notification ): array {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./i', '', $host ) ?? $host;
	if ( '' === $host ) {
		return $notification;
	}

	$sender = (string) apply_filters( 'cozmic_core_gf_from_address', 'noreply@' . $host );
	$from   = isset( $notification['from'] ) ? (string) $notification['from'] : '';

	if ( '' === $from ) {
		return $notification;
	}

	$is_merge_tag = str_contains( $from, '{' );
	$from_domain  = '';
	if ( str_contains( $from, '@' ) ) {
		$from_domain = ltrim( (string) strrchr( $from, '@' ), '@' );
	}

	$foreign = $is_merge_tag || ( '' !== $from_domain && 0 !== strcasecmp( $from_domain, $host ) );
	if ( ! $foreign ) {
		return $notification;
	}

	if ( empty( $notification['replyTo'] ) ) {
		$notification['replyTo'] = $from;
	}

	$notification['from'] = $sender;

	if ( empty( $notification['fromName'] ) ) {
		$notification['fromName'] = get_bloginfo( 'name' );
	}

	return $notification;
}
add_filter( 'gform_notification', 'cozmic_core_gf_notification', 10, 1 );

/**
 * Honeypot on by default.
 *
 * A hidden field that only a bot fills in. It is the same spam posture the
 * platform tier takes, and it is chosen over a CAPTCHA for the same reason
 * there: a CAPTCHA taxes every real customer to stop a problem most small local
 * sites do not have at volume. Turning it on costs a visitor nothing and is
 * invisible when it works.
 *
 * Applied to every form rather than only to new ones, because a form's honeypot
 * setting is stored either way and there is no way to tell "left at the default"
 * apart from "deliberately switched off". Filterable per form for the site where
 * that distinction turns out to matter.
 *
 * @param array<string, mixed> $form The form being loaded.
 * @return array<string, mixed>
 */
function cozmic_core_gf_honeypot( array $form ): array {
	$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;

	if ( ! apply_filters( 'cozmic_core_gf_force_honeypot', true, $form_id ) ) {
		return $form;
	}

	$form['enableHoneypot'] = true;

	return $form;
}
add_filter( 'gform_form_post_get_meta', 'cozmic_core_gf_honeypot' );

/**
 * Scroll to the confirmation message.
 *
 * Without this a long form submits and returns the visitor to the top of the
 * page, where nothing appears to have happened, so they submit again.
 */
add_filter( 'gform_confirmation_anchor', '__return_true' );
