<?php

defined( 'ABSPATH' ) || exit;

final class TVCD_Updater {
	const REPOSITORY = 'tinkervalley/tinker-valley-content-dashboard';
	const ASSET_NAME = 'tinker-valley-content-dashboard.zip';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'allow_auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( TVCD_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'admin_action_tvcd_toggle_auto_updates', array( $this, 'toggle_auto_updates' ) );
		add_action( 'admin_action_tvcd_check_updates', array( $this, 'check_updates_now' ) );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_release_cache' ) );
	}

	public function allow_auto_update( $update, $item ) {
		if ( ! empty( $item->slug ) && 'tinker-valley-content-dashboard' === $item->slug ) {
			return ! empty( TVCD_Settings::get()['auto_updates'] );
		}
		return $update;
	}

	public function plugin_action_links( $links ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}
		$enabled = ! empty( TVCD_Settings::get()['auto_updates'] );
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'tvcd_toggle_auto_updates',
					'enabled' => $enabled ? 0 : 1,
				),
				admin_url( 'admin.php' )
			),
			'tvcd_toggle_auto_updates'
		);
		$links['tvcd_auto_updates'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $enabled ? __( 'Disable automatic updates', 'tinker-valley-content-dashboard' ) : __( 'Enable automatic updates', 'tinker-valley-content-dashboard' ) )
		);
		$check_url = wp_nonce_url(
			add_query_arg( 'action', 'tvcd_check_updates', admin_url( 'admin.php' ) ),
			'tvcd_check_updates'
		);
		$links['tvcd_check_updates'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $check_url ),
			esc_html__( 'Check for updates', 'tinker-valley-content-dashboard' )
		);
		return $links;
	}

	public function toggle_auto_updates() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to change automatic updates.', 'tinker-valley-content-dashboard' ), 403 );
		}
		check_admin_referer( 'tvcd_toggle_auto_updates' );
		$enabled = isset( $_GET['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['enabled'] ) );
		TVCD_Settings::set_auto_updates( $enabled );
		wp_safe_redirect( add_query_arg( 'tvcd-auto-updates', $enabled ? 'enabled' : 'disabled', admin_url( 'plugins.php' ) ) );
		exit;
	}

	public function check_updates_now() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to check for updates.', 'tinker-valley-content-dashboard' ), 403 );
		}
		check_admin_referer( 'tvcd_check_updates' );
		$this->clear_release_cache();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	public function clear_release_cache() {
		delete_site_transient( 'tvcd_github_release' );
	}

	public function update_status( $force = false ) {
		if ( $force ) {
			$this->clear_release_cache();
		}
		$release = $this->get_release();
		$latest  = $release['version'] ?? TVCD_VERSION;
		return array(
			'current'   => TVCD_VERSION,
			'latest'    => $latest,
			'available' => (bool) ( $release && version_compare( $latest, TVCD_VERSION, '>' ) ),
		);
	}

	public function install_update() {
		$status = $this->update_status( true );
		if ( empty( $status['available'] ) ) {
			return array_merge( $status, array( 'updated' => false, 'message' => __( 'The plugin is already up to date.', 'tinker-valley-content-dashboard' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$plugin         = plugin_basename( TVCD_FILE );
		$was_active     = is_plugin_active( $plugin );
		$was_network    = is_multisite() && is_plugin_active_for_network( $plugin );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $plugin, array( 'clear_update_cache' => true ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'tvcd_update_failed', __( 'WordPress could not install the plugin update. Check filesystem permissions and try again.', 'tinker-valley-content-dashboard' ), array( 'status' => 500 ) );
		}
		if ( $was_active || $was_network ) {
			$activation = activate_plugin( $plugin, '', $was_network, true );
			if ( is_wp_error( $activation ) ) {
				return new WP_Error(
					'tvcd_reactivation_failed',
					sprintf( __( 'The update installed, but WordPress could not reactivate the plugin: %s', 'tinker-valley-content-dashboard' ), $activation->get_error_message() ),
					array( 'status' => 500 )
				);
			}
		}
		return array(
			'current'   => $status['latest'],
			'latest'    => $status['latest'],
			'available' => false,
			'updated'   => true,
			'message'   => __( 'Plugin updated successfully. Reloading…', 'tinker-valley-content-dashboard' ),
		);
	}

	public function check_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( empty( $plugin_data['UpdateURI'] ) || 'https://github.com/' . self::REPOSITORY !== untrailingslashit( $plugin_data['UpdateURI'] ) ) {
			return $update;
		}
		$release = $this->get_release();
		if ( ! $release || empty( $release['version'] ) || version_compare( $release['version'], TVCD_VERSION, '<=' ) ) {
			return false;
		}

		return array(
			'id'           => 'https://github.com/' . self::REPOSITORY,
			'slug'         => 'tinker-valley-content-dashboard',
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'url'          => 'https://github.com/' . self::REPOSITORY,
			'package'      => $release['package'],
			'icons'        => array(
				'1x' => TVCD_URL . 'assets/icons/content-dashboard-192.png',
				'2x' => TVCD_URL . 'assets/icons/content-dashboard-512.png',
			),
			'requires_php' => '7.4',
			'requires'     => '6.4',
			'tested'       => get_bloginfo( 'version' ),
		);
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'tinker-valley-content-dashboard' !== $args->slug ) {
			return $result;
		}
		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Tinker Valley Content Dashboard',
			'slug'          => 'tinker-valley-content-dashboard',
			'version'       => $release['version'],
			'author'        => '<a href="https://tinkervalley.ca">Tinker Valley</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'requires'      => '6.4',
			'requires_php'  => '7.4',
			'download_link' => $release['package'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => 'A modern, mobile-friendly content dashboard for WordPress and Advanced Custom Fields.',
				'changelog'   => wp_kses_post( nl2br( esc_html( $release['notes'] ) ) ),
			),
		);
	}

	private function get_release() {
		$cached = get_site_transient( 'tvcd_github_release' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Tinker-Valley-Content-Dashboard/' . TVCD_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( 'tvcd_github_release', array(), 2 * MINUTE_IN_SECONDS );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return false;
		}
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( self::ASSET_NAME === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] );
				break;
			}
		}
		if ( ! $package ) {
			return false;
		}

		$release = array(
			'version'      => ltrim( sanitize_text_field( $data['tag_name'] ), 'v' ),
			'package'      => $package,
			'notes'        => (string) ( $data['body'] ?? '' ),
			'published_at' => sanitize_text_field( $data['published_at'] ?? '' ),
		);
		set_site_transient( 'tvcd_github_release', $release, 10 * MINUTE_IN_SECONDS );
		return $release;
	}
}
