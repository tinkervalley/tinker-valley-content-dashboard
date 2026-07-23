<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Content Dashboard', 'tinker-valley-content-dashboard' ); ?> · <?php bloginfo( 'name' ); ?></title>
	<meta name="theme-color" content="#0c084a">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<link rel="manifest" href="<?php echo esc_url( home_url( '/dashboard/manifest.webmanifest' ) ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( TVCD_URL . 'assets/icons/content-dashboard-192.png' ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( TVCD_URL . 'assets/vendor/fontawesome/css/all.min.css?ver=7.3.1' ); ?>">
	<?php
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_media();
	wp_print_styles();
	?>
	<link rel="stylesheet" href="<?php echo esc_url( TVCD_URL . 'assets/dashboard.css?ver=' . TVCD_VERSION ); ?>">
</head>
<body class="wp-core-ui">
	<div id="tvcd-app"></div>
	<script>window.TVCD_BOOT = <?php echo wp_json_encode( $boot ); ?>;</script>
	<?php
	wp_print_footer_scripts();
	if ( function_exists( 'wp_print_media_templates' ) ) {
		wp_print_media_templates();
	}
	?>
	<script src="<?php echo esc_url( TVCD_URL . 'assets/dashboard.js?ver=' . TVCD_VERSION ); ?>"></script>
</body>
</html>
