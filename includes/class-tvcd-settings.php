<?php

defined( 'ABSPATH' ) || exit;

final class TVCD_Settings {
	const OPTION = 'tvcd_settings';

	public static function defaults() {
		return array(
			'enabled_post_types' => array( 'page', 'post' ),
			'post_types'         => array(),
			'auto_updates'       => false,
			'appearance'         => array(
				'brand'      => '#5850ec',
				'brand_dark' => '#0c084a',
				'ink'        => '#020210',
				'paper'      => '#f6f6fd',
				'header'     => '#f6f6fd',
				'card'       => '#ffffff',
				'input'      => '#ffffff',
				'nav_text'   => '#b9b8cb',
				'logo_id'    => 0,
				'light_logo_id' => 0,
			),
		);
	}

	public static function get() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function update( $input ) {
		$available = TVCD_REST::editable_post_types();
		$enabled   = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) ( $input['enabled_post_types'] ?? array() ) ),
				static function ( $type ) use ( $available ) {
					return isset( $available[ $type ] );
				}
			)
		);

		$post_types = array();
		foreach ( (array) ( $input['post_types'] ?? array() ) as $type => $config ) {
			$type = sanitize_key( $type );
			if ( ! isset( $available[ $type ] ) ) {
				continue;
			}
			$post_types[ $type ] = array(
				'view'             => in_array( $config['view'] ?? '', array( 'grid', 'list' ), true ) ? $config['view'] : 'grid',
				'image_field'      => sanitize_text_field( $config['image_field'] ?? '_featured_image' ),
				'title_field'      => sanitize_text_field( $config['title_field'] ?? '_post_title' ),
				'description_field'=> sanitize_text_field( $config['description_field'] ?? '_excerpt' ),
				'show_new'         => ! empty( $config['show_new'] ),
				'actions'          => array_values( array_intersect( (array) ( $config['actions'] ?? array( 'edit', 'view' ) ), array( 'edit', 'view', 'delete' ) ) ),
				'sort_by'          => in_array( $config['sort_by'] ?? '', array( 'date', 'modified', 'title', 'menu_order' ), true ) ? $config['sort_by'] : 'modified',
				'sort_order'       => in_array( strtoupper( $config['sort_order'] ?? '' ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $config['sort_order'] ) : 'DESC',
				'visible_fields'   => array_values( array_map( 'sanitize_text_field', (array) ( $config['visible_fields'] ?? array() ) ) ),
				'visible_fields_configured' => ! empty( $config['visible_fields_configured'] ),
				'icon'             => self::sanitize_icon( $config['icon'] ?? '', $type ),
				'menu_label'       => sanitize_text_field( $config['menu_label'] ?? '' ),
			);
		}

		$value = array(
			'enabled_post_types' => $enabled,
			'post_types'         => $post_types,
			'appearance'         => self::sanitize_appearance( (array) ( $input['appearance'] ?? array() ) ),
			'auto_updates'       => ! empty( $input['auto_updates'] ),
		);
		update_option( self::OPTION, $value, false );
		self::sync_core_auto_updates( $value['auto_updates'] );
		return $value;
	}

	public static function set_auto_updates( $enabled ) {
		$settings = self::get();
		$settings['auto_updates'] = (bool) $enabled;
		update_option( self::OPTION, $settings, false );
		self::sync_core_auto_updates( $settings['auto_updates'] );
		return $settings['auto_updates'];
	}

	public static function migrate_featured_image_visibility() {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || empty( $settings['post_types'] ) ) {
			return;
		}
		$changed = false;
		foreach ( $settings['post_types'] as &$config ) {
			if ( ! empty( $config['visible_fields_configured'] ) && ! in_array( '_featured_image', (array) ( $config['visible_fields'] ?? array() ), true ) ) {
				$config['visible_fields'][] = '_featured_image';
				$changed = true;
			}
		}
		unset( $config );
		if ( $changed ) {
			update_option( self::OPTION, $settings, false );
		}
	}

	public static function migrate_post_content_visibility() {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || empty( $settings['post_types'] ) ) {
			return;
		}
		$changed = false;
		foreach ( $settings['post_types'] as &$config ) {
			if ( ! empty( $config['visible_fields_configured'] ) && ! in_array( '_post_content', (array) ( $config['visible_fields'] ?? array() ), true ) ) {
				$config['visible_fields'][] = '_post_content';
				$changed = true;
			}
		}
		unset( $config );
		if ( $changed ) {
			update_option( self::OPTION, $settings, false );
		}
	}

	private static function sync_core_auto_updates( $enabled ) {
		$plugin = plugin_basename( TVCD_FILE );
		$list   = (array) get_site_option( 'auto_update_plugins', array() );
		$list   = array_values( array_diff( $list, array( $plugin ) ) );
		if ( $enabled ) {
			$list[] = $plugin;
		}
		update_site_option( 'auto_update_plugins', array_values( array_unique( $list ) ) );
	}

	private static function default_icon( $type ) {
		return 'page' === $type ? 'fa-solid fa-file-lines' : ( 'post' === $type ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-table-cells-large' );
	}

	private static function sanitize_icon( $icon, $type ) {
		$classes = preg_split( '/\s+/', trim( sanitize_text_field( $icon ) ) );
		$classes = array_filter(
			$classes,
			static function ( $class ) {
				return (bool) preg_match( '/^fa[a-z0-9-]*$/', $class );
			}
		);
		return $classes ? implode( ' ', array_slice( $classes, 0, 4 ) ) : self::default_icon( $type );
	}

	private static function sanitize_appearance( $appearance ) {
		$defaults = self::defaults()['appearance'];
		return array(
			'brand'      => sanitize_hex_color( $appearance['brand'] ?? '' ) ?: $defaults['brand'],
			'brand_dark' => sanitize_hex_color( $appearance['brand_dark'] ?? '' ) ?: $defaults['brand_dark'],
			'ink'        => sanitize_hex_color( $appearance['ink'] ?? '' ) ?: $defaults['ink'],
			'paper'      => $defaults['paper'],
			'header'     => $defaults['header'],
			'card'       => $defaults['card'],
			'input'      => $defaults['input'],
			'nav_text'   => sanitize_hex_color( $appearance['nav_text'] ?? '' ) ?: $defaults['nav_text'],
			'logo_id'    => absint( $appearance['logo_id'] ?? 0 ),
			'light_logo_id' => absint( $appearance['light_logo_id'] ?? 0 ),
		);
	}

	public static function for_type( $type ) {
		$settings = self::get();
		return wp_parse_args(
			$settings['post_types'][ $type ] ?? array(),
			array(
				'view'              => 'grid',
				'image_field'       => '_featured_image',
				'title_field'       => '_post_title',
				'description_field' => '_excerpt',
				'show_new'          => true,
				'actions'           => array( 'edit', 'view' ),
				'sort_by'           => 'modified',
				'sort_order'        => 'DESC',
				'visible_fields'    => array(),
				'visible_fields_configured' => false,
				'icon'              => self::default_icon( $type ),
				'menu_label'        => '',
			)
		);
	}
}
