<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Content Dashboard Login', 'tinker-valley-content-dashboard' ); ?> · <?php bloginfo( 'name' ); ?></title>
	<meta name="theme-color" content="<?php echo esc_attr( $appearance['brand_dark'] ); ?>">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<link rel="manifest" href="<?php echo esc_url( home_url( '/dashboard/manifest.webmanifest' ) ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( TVCD_URL . 'assets/icons/content-dashboard-192.png' ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( TVCD_URL . 'assets/dashboard.css?ver=' . TVCD_VERSION ); ?>">
	<style>:root{--brand:<?php echo esc_attr( $appearance['brand'] ); ?>;--brand-dark:<?php echo esc_attr( $appearance['brand_dark'] ); ?>;--ink:<?php echo esc_attr( $appearance['ink'] ); ?>;--paper:<?php echo esc_attr( $appearance['paper'] ); ?>}</style>
</head>
<body class="tvcd-login-page">
	<main class="tvcd-login">
		<div class="tvcd-login-brand">
			<?php if ( $light_logo_url || $logo_url ) : ?>
				<img src="<?php echo esc_url( $light_logo_url ?: $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<div class="tvcd-mark">TV</div>
			<?php endif; ?>
			<div><strong><?php bloginfo( 'name' ); ?></strong><small><?php esc_html_e( 'Content Dashboard', 'tinker-valley-content-dashboard' ); ?></small></div>
		</div>
		<div class="tvcd-login-card">
			<p class="tvcd-eyebrow"><?php esc_html_e( 'Welcome back', 'tinker-valley-content-dashboard' ); ?></p>
			<h1><?php esc_html_e( 'Sign in to manage content', 'tinker-valley-content-dashboard' ); ?></h1>
			<?php if ( $error ) : ?><div class="tvcd-login-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'tvcd_login', 'tvcd_login_nonce' ); ?>
				<div class="tvcd-field"><label for="tvcd-user"><?php esc_html_e( 'Username or email', 'tinker-valley-content-dashboard' ); ?></label><input id="tvcd-user" name="log" type="text" autocomplete="username" required autofocus></div>
				<div class="tvcd-field"><label for="tvcd-password"><?php esc_html_e( 'Password', 'tinker-valley-content-dashboard' ); ?></label><input id="tvcd-password" name="pwd" type="password" autocomplete="current-password" required></div>
				<label class="tvcd-choice"><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Keep me signed in', 'tinker-valley-content-dashboard' ); ?></label>
				<button class="tvcd-btn primary tvcd-login-submit" type="submit"><?php esc_html_e( 'Sign in', 'tinker-valley-content-dashboard' ); ?></button>
			</form>
			<a class="tvcd-forgot" href="<?php echo esc_url( wp_lostpassword_url( home_url( '/dashboard/' ) ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'tinker-valley-content-dashboard' ); ?></a>
		</div>
	</main>
</body>
</html>
