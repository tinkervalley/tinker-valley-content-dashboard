<?php

defined( 'ABSPATH' ) || exit;

final class TVCD_Plugin {
	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 99 );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'parse_request', array( $this, 'intercept_icon_request' ) );
		add_action( 'template_redirect', array( $this, 'render_dashboard' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'redirect_admin_page' ) );
		add_action( 'rest_api_init', array( 'TVCD_REST', 'register_routes' ) );
	}

	public static function activate() {
		self::instance()->add_rewrite_rule();
		flush_rewrite_rules();
		update_option( 'tvcd_version', TVCD_VERSION, false );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function add_rewrite_rule() {
		add_rewrite_rule( '^dashboard/manifest\.webmanifest$', 'index.php?tvcd_manifest=1', 'top' );
		add_rewrite_rule( '^dashboard/sw\.js$', 'index.php?tvcd_service_worker=1', 'top' );
		add_rewrite_rule( '^dashboard/icon-(180|192|512)\.png$', 'index.php?tvcd_icon=$matches[1]', 'top' );
		add_rewrite_rule( '^dashboard/apple-touch-icon\.png$', 'index.php?tvcd_icon=180', 'top' );
		add_rewrite_rule( '^dashboard/?$', 'index.php?tvcd_dashboard=1', 'top' );
		add_rewrite_rule( '^content-dashboard/?$', 'index.php?tvcd_legacy_dashboard=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = 'tvcd_dashboard';
		$vars[] = 'tvcd_manifest';
		$vars[] = 'tvcd_service_worker';
		$vars[] = 'tvcd_icon';
		$vars[] = 'tvcd_legacy_dashboard';
		return $vars;
	}

	public function maybe_upgrade() {
		$installed_version = get_option( 'tvcd_version' );
		if ( $installed_version !== TVCD_VERSION ) {
			if ( ! $installed_version || version_compare( $installed_version, '0.8.13', '<' ) ) {
				TVCD_Settings::migrate_featured_image_visibility();
			}
			if ( ! $installed_version || version_compare( $installed_version, '0.9.2', '<' ) ) {
				TVCD_Settings::migrate_post_content_visibility();
			}
			flush_rewrite_rules( false );
			update_option( 'tvcd_version', TVCD_VERSION, false );
		}
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Content Dashboard', 'tinker-valley-content-dashboard' ),
			__( 'Content Dashboard', 'tinker-valley-content-dashboard' ),
			'edit_posts',
			'tvcd-dashboard',
			array( $this, 'admin_fallback' ),
			'dashicons-layout',
			3
		);
	}

	public function admin_fallback() {
		echo '<div class="wrap"><p>' . esc_html__( 'Opening Content Dashboard…', 'tinker-valley-content-dashboard' ) . '</p></div>';
	}

	public function redirect_admin_page() {
		if ( isset( $_GET['page'] ) && 'tvcd-dashboard' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_safe_redirect( home_url( '/dashboard/' ) );
			exit;
		}
	}

	public function render_dashboard() {
		if ( get_query_var( 'tvcd_legacy_dashboard' ) ) {
			wp_safe_redirect( home_url( '/dashboard/' ), 301 );
			exit;
		}
		if ( get_query_var( 'tvcd_manifest' ) ) {
			$this->render_manifest();
		}
		if ( get_query_var( 'tvcd_service_worker' ) ) {
			$this->render_service_worker();
		}
		if ( get_query_var( 'tvcd_icon' ) ) {
			$this->render_app_icon( (int) get_query_var( 'tvcd_icon' ) );
		}
		if ( ! get_query_var( 'tvcd_dashboard' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$error = '';
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['tvcd_login_nonce'] ) ) {
				if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tvcd_login_nonce'] ) ), 'tvcd_login' ) ) {
					$error = __( 'Your session expired. Please try again.', 'tinker-valley-content-dashboard' );
				} else {
					$credentials = array(
						'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
						'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ),
						'remember'      => ! empty( $_POST['rememberme'] ),
					);
					$user = wp_signon( $credentials, is_ssl() );
					if ( is_wp_error( $user ) ) {
						$error = wp_strip_all_tags( $user->get_error_message() );
					} elseif ( ! user_can( $user, 'edit_posts' ) ) {
						wp_logout();
						$error = __( 'Your account cannot access the content dashboard.', 'tinker-valley-content-dashboard' );
					} else {
						wp_safe_redirect( home_url( '/dashboard/' ) );
						exit;
					}
				}
			}
			status_header( 200 );
			nocache_headers();
			$appearance = wp_parse_args( TVCD_Settings::get()['appearance'] ?? array(), TVCD_Settings::defaults()['appearance'] );
			$logo_url   = ! empty( $appearance['logo_id'] ) ? wp_get_attachment_image_url( $appearance['logo_id'], 'medium' ) : '';
			$light_logo_url = ! empty( $appearance['light_logo_id'] ) ? wp_get_attachment_image_url( $appearance['light_logo_id'], 'medium' ) : '';
			include TVCD_PATH . 'templates/login.php';
			exit;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access the content dashboard.', 'tinker-valley-content-dashboard' ), 403 );
		}

		status_header( 200 );
		nocache_headers();

		$boot = array(
			'restUrl' => esc_url_raw( rest_url( 'tvcd/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'site'    => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
			'user'    => array(
				'name'   => wp_get_current_user()->display_name,
				'avatar' => get_avatar_url( get_current_user_id(), array( 'size' => 96 ) ),
			),
			'canManage' => current_user_can( 'manage_options' ),
			'adminUrl'  => admin_url(),
			'swUrl'     => add_query_arg( 'tvcd_service_worker', 1, home_url( '/dashboard/' ) ),
			'version'   => TVCD_VERSION,
		);

		$appearance = $this->appearance();
		$icon_hash  = $this->appearance_hash( $appearance );
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
		include TVCD_PATH . 'templates/dashboard.php';
		exit;
	}

	public function intercept_icon_request() {
		$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( is_string( $request_path ) && preg_match( '#/dashboard/(?:apple-touch-icon|icon-(180|192|512))\.png$#', untrailingslashit( $request_path ), $icon_match ) ) {
			$this->render_app_icon( empty( $icon_match[1] ) ? 180 : (int) $icon_match[1] );
		}
	}

	private function render_manifest() {
		$appearance = $this->appearance();
		$icon_hash  = $this->appearance_hash( $appearance );
		$manifest = array(
			'name'             => get_bloginfo( 'name' ) . ' Content Dashboard',
			'short_name'       => __( 'Content', 'tinker-valley-content-dashboard' ),
			'description'      => __( 'Edit and manage website content.', 'tinker-valley-content-dashboard' ),
			'start_url'        => home_url( '/dashboard/' ),
			'scope'            => home_url( '/dashboard/' ),
			'display'          => 'standalone',
			'background_color' => $appearance['paper'],
			'theme_color'      => $appearance['brand_dark'],
			'icons'            => array(
				array( 'src' => $this->app_icon_url( 192, $icon_hash ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ),
				array( 'src' => $this->app_icon_url( 512, $icon_hash ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
			),
		);
		status_header( 200 );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES );
		exit;
	}

	private function render_service_worker() {
		$dashboard = home_url( '/dashboard/' );
		$appearance = $this->appearance();
		$icon_hash  = $this->appearance_hash( $appearance );
		$assets = array(
			TVCD_URL . 'assets/dashboard.css?ver=' . TVCD_VERSION,
			TVCD_URL . 'assets/dashboard.js?ver=' . TVCD_VERSION,
			$this->app_icon_url( 192, $icon_hash ),
			$this->app_icon_url( 512, $icon_hash ),
			TVCD_URL . 'assets/vendor/fontawesome/css/all.min.css?ver=7.3.1',
			TVCD_URL . 'assets/vendor/fontawesome/webfonts/fa-solid-900.woff2',
			TVCD_URL . 'assets/vendor/fontawesome/webfonts/fa-regular-400.woff2',
			TVCD_URL . 'assets/vendor/fontawesome/webfonts/fa-brands-400.woff2',
			TVCD_URL . 'assets/vendor/fontawesome/webfonts/fa-v4compatibility.woff2',
		);
		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . wp_parse_url( $dashboard, PHP_URL_PATH ) );
		header( 'Cache-Control: no-cache' );
		?>
const CACHE = <?php echo wp_json_encode( 'tvcd-' . TVCD_VERSION . '-' . $icon_hash ); ?>;
const ASSETS = <?php echo wp_json_encode( $assets, JSON_UNESCAPED_SLASHES ); ?>;
self.addEventListener('install', event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('tvcd-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())));
self.addEventListener('fetch', event => {
	if (event.request.method !== 'GET') return;
	if (event.request.mode === 'navigate') {
		event.respondWith(fetch(event.request).catch(() => new Response('<!doctype html><meta name="viewport" content="width=device-width"><style>body{font:16px system-ui;background:#f6f6fd;color:#020210;display:grid;place-items:center;min-height:100vh;margin:0}.card{background:#fff;padding:32px;border-radius:18px;text-align:center;box-shadow:0 18px 45px #0c084a22}button{background:#5850ec;color:#fff;border:0;border-radius:9px;padding:10px 16px}</style><div class="card"><h1>You’re offline</h1><p>Reconnect to load and save website content.</p><button onclick="location.reload()">Try again</button></div>', {headers:{'Content-Type':'text/html'}})));
		return;
	}
	event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
		if (response.ok && new URL(event.request.url).origin === location.origin) caches.open(CACHE).then(cache => cache.put(event.request, response.clone()));
		return response;
	})));
});
		<?php
		exit;
	}

	private function appearance() {
		return wp_parse_args( TVCD_Settings::get()['appearance'] ?? array(), TVCD_Settings::defaults()['appearance'] );
	}

	private function appearance_hash( $appearance ) {
		return substr( md5( $appearance['brand'] . '|' . $appearance['brand_dark'] . '|' . $appearance['paper'] ), 0, 10 );
	}

	private function app_icon_url( $size, $hash ) {
		return add_query_arg(
			array(
				'tvcd_icon' => (int) $size,
				'colors'    => $hash,
			),
			home_url( '/dashboard/' )
		);
	}

	private function render_app_icon( $size ) {
		$size = in_array( $size, array( 180, 192, 512 ), true ) ? $size : 192;
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
			$fallback_size = 512 === $size ? 512 : 192;
			wp_safe_redirect( TVCD_URL . 'assets/icons/content-dashboard-' . $fallback_size . '.png' );
			exit;
		}

		$appearance = $this->appearance();
		$image      = imagecreatetruecolor( $size, $size );
		$paper      = $this->image_color( $image, $appearance['paper'] );
		$brand      = $this->image_color( $image, $appearance['brand'] );
		$brand_dark = $this->image_color( $image, $appearance['brand_dark'] );
		$white      = imagecolorallocate( $image, 255, 255, 255 );
		imagefill( $image, 0, 0, $paper );

		$scale = $size / 512;
		$this->rounded_rectangle( $image, 104 * $scale, 124 * $scale, 370 * $scale, 344 * $scale, 42 * $scale, $brand_dark );
		$this->rounded_rectangle( $image, 126 * $scale, 101 * $scale, 392 * $scale, 321 * $scale, 42 * $scale, $brand );
		$this->rounded_rectangle( $image, 148 * $scale, 78 * $scale, 414 * $scale, 298 * $scale, 42 * $scale, $white );
		$this->rounded_rectangle( $image, 180 * $scale, 119 * $scale, 382 * $scale, 150 * $scale, 15 * $scale, $brand );
		$this->rounded_rectangle( $image, 180 * $scale, 174 * $scale, 345 * $scale, 191 * $scale, 8 * $scale, $brand_dark );
		$this->rounded_rectangle( $image, 180 * $scale, 213 * $scale, 319 * $scale, 230 * $scale, 8 * $scale, $brand_dark );

		status_header( 200 );
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		imagepng( $image, null, 9 );
		imagedestroy( $image );
		exit;
	}

	private function image_color( $image, $hex ) {
		$hex = ltrim( sanitize_hex_color( $hex ) ?: '#000000', '#' );
		return imagecolorallocate( $image, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	private function rounded_rectangle( $image, $x1, $y1, $x2, $y2, $radius, $color ) {
		$x1 = (int) round( $x1 );
		$y1 = (int) round( $y1 );
		$x2 = (int) round( $x2 );
		$y2 = (int) round( $y2 );
		$radius = max( 1, (int) round( $radius ) );
		imagefilledrectangle( $image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color );
		imagefilledrectangle( $image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color );
		imagefilledellipse( $image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
	}
}
