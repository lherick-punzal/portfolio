<?php
/**
 * Plugin Name: Elementor Pro
 * Description: Elevate your designs and unlock the full power of the Atomic Editor. Gain access to dozens of Pro widgets, Website Templates, Theme Builder, Pop Ups, Forms, reusable Components, and WooCommerce building capabilities.
 * Plugin URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/
 * Version: 4.2.1
 * Author: Elementor.com
 * Author URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/
 * Requires PHP: 7.4
 * Requires at least: 6.8
 * Requires Plugins: elementor
 * Elementor tested up to: 4.2.1-ga
 * Text Domain: elementor-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ELEMENTOR_PRO_VERSION', '4.2.1' );

// GPL Vault: license Elementor Pro and silence its license UI. Additive filters only.
// Updates are delivered centrally, so the my.elementor.com endpoints are short-circuited.
// Do NOT add a 'generation' key below — that arms the plugin's per-feature tier gate.
// Remove down to "END GPL Vault" to restore upstream behaviour.

$_epro_license_key = '1415B451BE1A13C283BA771EA52D38BB';

$_epro_license_data = [
	'success'          => true,
	'status'           => 'ACTIVE',
	'error'            => '',
	'license'          => 'valid',
	'item_id'          => false,
	'item_name'        => 'Elementor Pro',
	'checksum'         => $_epro_license_key,
	'tier'             => 'agency',
	'expires'          => 'lifetime',
	'payment_id'       => '0123456789',
	'customer_email'   => 'license@example.com',
	'customer_name'    => 'Licensed User',
	'license_limit'    => 1000,
	'site_count'       => 1,
	'activations_left' => 999,
	'renewal_url'      => '',
	'features'         => [
		'atomic-custom-attributes',
		'atomic-custom-css',
		'atomic-loop',
		'theme-builder',
		'element-manager-permissions',
		'form-submissions',
		'editor_comments',
		'transitions',
		'pro-interactions',
	],
];

$_epro_license_record = [
	'timeout' => time() + ( 10 * YEAR_IN_SECONDS ),
	'value'   => wp_json_encode( $_epro_license_data ),
];

add_filter( 'pre_option_elementor_pro_license_key', function() use ( $_epro_license_key ) {
	return $_epro_license_key;
} );

add_filter( 'pre_option__elementor_pro_license_v2_data', function() use ( $_epro_license_record ) {
	return $_epro_license_record;
} );

add_filter( 'pre_option__elementor_pro_license_v2_data_fallback', function() use ( $_epro_license_record ) {
	return $_epro_license_record;
} );

function _epro_set_connect_data() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	$connect_data = [
		'user'                => (object) [
			'email' => get_option( 'admin_email', 'admin@localhost' ),
			'name'  => 'Site Admin',
			'id'    => 'pro_' . md5( get_site_url() ),
		],
		'access_level'        => 20,
		'access_token'        => md5( get_site_url() . $user_id . wp_salt() ),
		'access_token_secret' => md5( $user_id . get_site_url() . wp_salt( 'auth' ) ),
		'client_id'           => md5( get_site_url() . wp_salt( 'secure_auth' ) ),
	];

	update_user_option( $user_id, 'elementor_connect_common_data', $connect_data, false );
	update_option( 'elementor_connect_site_key', md5( get_site_url() . wp_salt( 'logged_in' ) ), false );
}

add_action( 'admin_init', function() {
	delete_option( '_elementor_pro_license_data' );
	update_option( 'elementor_one_dismiss_connect_alert', true );
	update_option( 'elementor_one_welcome_screen_completed', true );
	update_option( 'elementor_one_editor_update_notification_dismissed', true );
	_epro_set_connect_data();
}, 20 );

add_action( 'init', function() {
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		_epro_set_connect_data();
	}
}, 20 );
add_action( 'elementor/editor/before_enqueue_scripts', '_epro_set_connect_data', 1 );
add_filter( 'elementor/connect/additional-connect-info', '__return_empty_array', 999 );
add_filter( 'elementor_pro/license/should_show_renew_license_notice', '__return_false' );
add_action( 'admin_menu', function() {
	remove_submenu_page( 'elementor', 'elementor-one-upgrade' );
	remove_submenu_page( 'elementor-home', 'elementor-one-upgrade' );
	remove_submenu_page( 'elementor', 'elementor-connect-account' );
	remove_submenu_page( 'elementor-home', 'elementor-connect-account' );
}, 9999 );
add_action( 'admin_head', function() {
	echo '<style>#adminmenu a[href*="elementor-one-upgrade"]{display:none !important;}</style>';
} );

// Cached template-library fetch; false means fall back to the default request.
function _epro_cdn_get( $path ) {
	$cache_key = '_epro_lib_' . sha1( $path );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}
	$url  = 'https://dl.gplvault.com/file/gplvault/elementor-library/' . ltrim( $path, '/' );
	$resp = wp_remote_get( $url, [ 'timeout' => 20 ] );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return false;
	}
	$body = wp_remote_retrieve_body( $resp );
	if ( '' === $body ) {
		return false;
	}
	set_transient( $cache_key, $body, WEEK_IN_SECONDS );
	return $body;
}

add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) use ( $_epro_license_data ) {
	if ( ! is_string( $url ) ) {
		return $pre;
	}
	if ( strpos( $url, 'my.elementor.com/api/v2/pro/info' ) !== false ||
	     strpos( $url, 'my.elementor.com/api/v1/pro-downloads/' ) !== false ) {
		return [ 'headers' => [], 'body' => '{}', 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/v1/feedback-pro/' ) !== false ) {
		return [ 'headers' => [], 'body' => wp_json_encode( [ 'success' => true ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/v2/license/validate' ) !== false ||
	     strpos( $url, 'my.elementor.com/api/v2/license/activate' ) !== false ) {
		return [ 'headers' => [], 'body' => wp_json_encode( $_epro_license_data ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/v1/licenses/' ) !== false ) {
		return [ 'headers' => [], 'body' => wp_json_encode( $_epro_license_data ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/v2/license/deactivate' ) !== false ) {
		return [ 'headers' => [], 'body' => wp_json_encode( [ 'success' => true ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/connect/v1/activate/disconnect' ) !== false ) {
		return [ 'headers' => [], 'body' => 'true', 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
	}
	if ( strpos( $url, 'my.elementor.com/api/connect/v1/library/templates' ) !== false ) {
		$body = _epro_cdn_get( 'templates.json' );
		if ( false !== $body ) {
			return [ 'headers' => [], 'body' => $body, 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
		}
	}
	if ( strpos( $url, 'my.elementor.com/api/connect/v1/library/get_template_content' ) !== false ) {
		$id = 0;
		if ( isset( $parsed_args['body'] ) ) {
			if ( is_array( $parsed_args['body'] ) && isset( $parsed_args['body']['id'] ) ) {
				$id = (int) $parsed_args['body']['id'];
			} elseif ( is_string( $parsed_args['body'] ) ) {
				parse_str( $parsed_args['body'], $parsed_body );
				$id = isset( $parsed_body['id'] ) ? (int) $parsed_body['id'] : 0;
			}
		}
		if ( $id ) {
			$body = _epro_cdn_get( 'content/' . $id . '.json' );
			if ( false !== $body ) {
				return [ 'headers' => [], 'body' => $body, 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
		}
	}
	return $pre;
}, 99, 3 );

add_filter( 'auto_update_plugin', function( $update, $item ) {
	$plugin  = is_object( $item ) && isset( $item->plugin ) ? $item->plugin : '';
	$slug    = is_object( $item ) && isset( $item->slug ) ? $item->slug : '';
	$package = is_object( $item ) && isset( $item->package ) ? (string) $item->package : '';

	$is_epro = ( defined( 'ELEMENTOR_PRO_PLUGIN_BASE' ) && ELEMENTOR_PRO_PLUGIN_BASE === $plugin )
		|| 'elementor-pro' === $slug;

	if ( $is_epro && ( '' === $package || false !== strpos( $package, 'elementor.com' ) ) ) {
		return false;
	}

	return $update;
}, 99, 2 );

// END GPL Vault.

/**
 * All versions should be `major.minor`, without patch, in order to compare them properly.
 * Therefore, we can't set a patch version as a requirement.
 * (e.g. Core 3.15.0-beta1 and Core 3.15.0-cloud2 should be fine when requiring 3.15, while
 * requiring 3.15.2 is not allowed)
 */
define( 'ELEMENTOR_PRO_REQUIRED_CORE_VERSION', '4.0' );
define( 'ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION', '4.2' );

define( 'ELEMENTOR_PRO__FILE__', __FILE__ );
define( 'ELEMENTOR_PRO_PLUGIN_BASE', plugin_basename( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_PATH', plugin_dir_path( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_PATH', ELEMENTOR_PRO_PATH . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_PATH', ELEMENTOR_PRO_PATH . 'modules/' );
define( 'ELEMENTOR_PRO_URL', plugins_url( '/', ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_URL', ELEMENTOR_PRO_URL . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_URL', ELEMENTOR_PRO_URL . 'modules/' );

/**
 * Load gettext translate for our text domain.
 *
 * @since 1.0.0
 *
 * @return void
 */
function elementor_pro_load_plugin() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'elementor_pro_fail_load' );

		return;
	}

	$core_version = ELEMENTOR_VERSION;
	$core_version_required = ELEMENTOR_PRO_REQUIRED_CORE_VERSION;
	$core_version_recommended = ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION;

	if ( ! elementor_pro_compare_major_version( $core_version, $core_version_required, '>=' ) ) {
		add_action( 'admin_notices', 'elementor_pro_fail_load_out_of_date' );

		return;
	}

	if ( ! elementor_pro_compare_major_version( $core_version, $core_version_recommended, '>=' ) ) {
		add_action( 'admin_notices', 'elementor_pro_admin_notice_upgrade_recommendation' );
	}

	require ELEMENTOR_PRO_PATH . 'plugin.php';
}

function elementor_pro_compare_major_version( $left, $right, $operator ) {
	$pattern = '/^(\d+\.\d+).*/';
	$replace = '$1.0';

	$left  = preg_replace( $pattern, $replace, $left );
	$right = preg_replace( $pattern, $replace, $right );

	return version_compare( $left, $right, $operator );
}

add_action( 'plugins_loaded', 'elementor_pro_load_plugin' );

function print_error( $message ) {
	if ( ! $message ) {
		return;
	}
	// PHPCS - $message should not be escaped
	echo '<div class="error">' . $message . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
/**
 * Show in WP Dashboard notice about the plugin is not activated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function elementor_pro_fail_load() {
	$screen = get_current_screen();
	if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
		return;
	}

	$plugin = 'elementor/elementor.php';

	if ( _is_elementor_installed() ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );

		$message = '<h3>' . esc_html__( 'You\'re not using Elementor Pro yet!', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Activate the Elementor plugin to start using all of Elementor Pro plugin’s features.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Now', 'elementor-pro' ) ) . '</p>';
	} else {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );

		$message = '<h3>' . esc_html__( 'Elementor Pro plugin requires installing the Elementor plugin', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Install and activate the Elementor plugin to access all the Pro features.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Now', 'elementor-pro' ) ) . '</p>';
	}

	print_error( $message );
}

function elementor_pro_fail_load_out_of_date() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

	$message = sprintf(
		'<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
		esc_html__( 'Elementor Pro requires newer version of the Elementor plugin', 'elementor-pro' ),
		esc_html__( 'Update the Elementor plugin to reactivate the Elementor Pro plugin.', 'elementor-pro' ),
		$upgrade_link,
		esc_html__( 'Update Now', 'elementor-pro' )
	);

	print_error( $message );
}

function elementor_pro_admin_notice_upgrade_recommendation() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

	$message = sprintf(
		'<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
		esc_html__( 'Don’t miss out on the new version of Elementor', 'elementor-pro' ),
		esc_html__( 'Update to the latest version of Elementor to enjoy new features, better performance and compatibility.', 'elementor-pro' ),
		$upgrade_link,
		esc_html__( 'Update Now', 'elementor-pro' )
	);

	print_error( $message );
}

if ( ! function_exists( '_is_elementor_installed' ) ) {

	function _is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}
