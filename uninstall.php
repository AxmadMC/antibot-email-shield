<?php
/**
 * Remove plugin data when the plugin is deleted from the Plugins screen.
 *
 * @package AntibotEmailShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's settings and rate-limit data for the current site.
 */
function antibot_email_shield_uninstall_site() {
	global $wpdb;
	delete_option( 'antibot_email_shield_settings' );
	// Rate-limit counters are transients that expire on their own; clear any left over.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_antibot_email_shield_rl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_antibot_email_shield_rl_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $antibot_email_shield_site_id ) {
		switch_to_blog( $antibot_email_shield_site_id );
		antibot_email_shield_uninstall_site();
		restore_current_blog();
	}
} else {
	antibot_email_shield_uninstall_site();
}
