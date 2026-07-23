<?php

defined( 'ABSPATH' ) || exit;

final class TVCD_REST {
	public static function register_routes() {
		$routes = array(
			'/bootstrap'           => array( 'GET', 'bootstrap', 'edit_posts' ),
			'/posts/(?P<type>[a-z0-9_-]+)' => array( 'GET', 'posts', 'edit_posts' ),
			'/post/(?P<id>\d+)'    => array( 'GET', 'post', 'edit_posts' ),
			'/post'                => array( 'POST', 'save_post', 'edit_posts' ),
			'/bulk'                => array( 'POST', 'bulk_action', 'edit_posts' ),
			'/settings'            => array( 'POST', 'save_settings', 'manage_options' ),
			'/site-settings'       => array( 'POST', 'save_site_settings', 'manage_options' ),
		);

		foreach ( $routes as $route => $definition ) {
			register_rest_route(
				'tvcd/v1',
				$route,
				array(
					'methods'             => $definition[0],
					'callback'            => array( __CLASS__, $definition[1] ),
					'permission_callback' => static function () use ( $definition ) {
						return current_user_can( $definition[2] );
					},
				)
			);
		}

		register_rest_route(
			'tvcd/v1',
			'/post/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_post' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_post' ),
					'permission_callback' => static function () {
						return current_user_can( 'delete_posts' );
					},
				),
			)
		);
	}

	public static function editable_post_types() {
		$objects = get_post_types( array( 'show_ui' => true ), 'objects' );
		$hidden  = array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
		return array_filter(
			$objects,
			static function ( $object ) use ( $hidden ) {
				return ! in_array( $object->name, $hidden, true ) && current_user_can( $object->cap->edit_posts );
			}
		);
	}

	public static function bootstrap() {
		$settings = TVCD_Settings::get();
		$settings['appearance'] = wp_parse_args( $settings['appearance'] ?? array(), TVCD_Settings::defaults()['appearance'] );
		$settings['appearance']['logo_url'] = $settings['appearance']['logo_id'] ? esc_url_raw( wp_get_attachment_image_url( $settings['appearance']['logo_id'], 'medium' ) ?: '' ) : '';
		$settings['appearance']['light_logo_url'] = $settings['appearance']['light_logo_id'] ? esc_url_raw( wp_get_attachment_image_url( $settings['appearance']['light_logo_id'], 'medium' ) ?: '' ) : '';
		$types    = array();
		foreach ( self::editable_post_types() as $name => $object ) {
			$fields = self::fields_for_type( $name );
			$config = TVCD_Settings::for_type( $name );
			if ( empty( $config['visible_fields_configured'] ) ) {
				$config['visible_fields'] = array_merge(
					array( '_post_title', '_excerpt', '_status', '_featured_image' ),
					array_values(
						array_map(
							static function ( $field ) {
								return $field['key'];
							},
							array_filter(
								$fields,
								static function ( $field ) {
									return 0 !== strpos( $field['key'], '_' );
								}
							)
						)
					)
				);
			}
			$types[] = array(
				'name'       => $name,
				'label'      => $object->labels->name,
				'singular'   => $object->labels->singular_name,
				'icon'       => $object->menu_icon ?: 'dashicons-admin-post',
				'enabled'    => in_array( $name, $settings['enabled_post_types'], true ),
				'config'     => $config,
				'fields'     => $fields,
				'canCreate'  => current_user_can( $object->cap->create_posts ),
			);
		}
		return rest_ensure_response(
			array(
				'postTypes'   => $types,
				'settings'    => $settings,
				'siteSettings'=> self::site_settings(),
			)
		);
	}

	public static function posts( WP_REST_Request $request ) {
		$type    = sanitize_key( $request['type'] );
		$objects = self::editable_post_types();
		if ( ! isset( $objects[ $type ] ) ) {
			return new WP_Error( 'tvcd_invalid_type', __( 'Invalid post type.', 'tinker-valley-content-dashboard' ), array( 'status' => 400 ) );
		}

		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$config = TVCD_Settings::for_type( $type );
		$sort_by = sanitize_key( (string) $request->get_param( 'sort_by' ) );
		$sort_by = in_array( $sort_by, array( 'date', 'modified', 'title', 'menu_order' ), true ) ? $sort_by : $config['sort_by'];
		$order = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) );
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : $config['sort_order'];
		$query  = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 24,
				'paged'          => $page,
				's'              => $search,
				'orderby'        => $sort_by,
				'order'          => $order,
			)
		);
		$items  = array_map(
			static function ( $post ) use ( $config ) {
				return self::card_data( $post, $config );
			},
			$query->posts
		);

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page'  => $page,
			)
		);
	}

	public static function post( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'tvcd_not_found', __( 'Content not found.', 'tinker-valley-content-dashboard' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::editor_data( $post ) );
	}

	public static function save_post( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		$id   = isset( $request['id'] ) ? (int) $request['id'] : 0;
		$type = sanitize_key( $data['post_type'] ?? 'post' );

		if ( $id && ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'tvcd_forbidden', __( 'You cannot edit this content.', 'tinker-valley-content-dashboard' ), array( 'status' => 403 ) );
		}
		if ( ! $id ) {
			$objects = self::editable_post_types();
			if ( ! isset( $objects[ $type ] ) || ! current_user_can( $objects[ $type ]->cap->create_posts ) ) {
				return new WP_Error( 'tvcd_forbidden', __( 'You cannot create this content.', 'tinker-valley-content-dashboard' ), array( 'status' => 403 ) );
			}
		}

		$postarr = array(
			'ID'           => $id,
			'post_type'    => $type,
		);
		if ( array_key_exists( 'title', $data ) ) {
			$postarr['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( array_key_exists( 'excerpt', $data ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}
		if ( array_key_exists( 'status', $data ) && in_array( $data['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$postarr['post_status'] = $data['status'];
		} elseif ( ! $id ) {
			$postarr['post_status'] = 'draft';
		}
		$result = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		foreach ( (array) ( $data['fields'] ?? array() ) as $key => $value ) {
			$key = sanitize_text_field( $key );
			if ( '_featured_image' === $key ) {
				$attachment_id = absint( $value );
				if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
					set_post_thumbnail( $result, $attachment_id );
				} elseif ( ! $attachment_id ) {
					delete_post_thumbnail( $result );
				}
			} elseif ( 0 === strpos( $key, 'meta:' ) ) {
				$meta_key = substr( $key, 5 );
				if ( self::can_edit_registered_meta( $type, $meta_key, $result ) ) {
					update_post_meta( $result, $meta_key, $value );
				}
			} elseif ( function_exists( 'update_field' ) ) {
				update_field( $key, $value, $result );
			}
		}

		return rest_ensure_response( self::editor_data( get_post( $result ) ) );
	}

	public static function bulk_action( WP_REST_Request $request ) {
		$data   = (array) $request->get_json_params();
		$ids    = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $data['ids'] ?? array() ) ) ) ) );
		$action = sanitize_key( $data['action'] ?? '' );
		if ( ! in_array( $action, array( 'publish', 'draft', 'trash' ), true ) || ! $ids ) {
			return new WP_Error( 'tvcd_invalid_bulk', __( 'Choose content and a valid bulk action.', 'tinker-valley-content-dashboard' ), array( 'status' => 400 ) );
		}
		$updated = array();
		foreach ( $ids as $id ) {
			$allowed = 'trash' === $action ? current_user_can( 'delete_post', $id ) : current_user_can( 'edit_post', $id );
			if ( ! $allowed ) {
				continue;
			}
			$result = 'trash' === $action ? wp_trash_post( $id ) : wp_update_post( array( 'ID' => $id, 'post_status' => $action ), true );
			if ( ! is_wp_error( $result ) && $result ) {
				$updated[] = $id;
			}
		}
		return rest_ensure_response( array( 'updated' => $updated, 'count' => count( $updated ) ) );
	}

	public static function delete_post( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'tvcd_forbidden', __( 'You cannot delete this content.', 'tinker-valley-content-dashboard' ), array( 'status' => 403 ) );
		}
		return rest_ensure_response( array( 'deleted' => (bool) wp_trash_post( $id ) ) );
	}

	public static function save_settings( WP_REST_Request $request ) {
		return rest_ensure_response( TVCD_Settings::update( (array) $request->get_json_params() ) );
	}

	public static function save_site_settings( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		if ( array_key_exists( 'title', $data ) ) {
			update_option( 'blogname', sanitize_text_field( $data['title'] ) );
		}
		if ( array_key_exists( 'tagline', $data ) ) {
			update_option( 'blogdescription', sanitize_text_field( $data['tagline'] ) );
		}
		if ( array_key_exists( 'site_icon_id', $data ) ) {
			$icon_id = absint( $data['site_icon_id'] );
			if ( ! $icon_id || wp_attachment_is_image( $icon_id ) ) {
				update_option( 'site_icon', $icon_id );
			}
		}
		return rest_ensure_response( self::site_settings() );
	}

	private static function site_settings() {
		$icon_id = (int) get_option( 'site_icon', 0 );
		return array(
			'title'        => get_option( 'blogname', '' ),
			'tagline'      => get_option( 'blogdescription', '' ),
			'site_icon_id' => $icon_id,
			'site_icon_url'=> $icon_id ? esc_url_raw( wp_get_attachment_image_url( $icon_id, 'thumbnail' ) ?: '' ) : '',
		);
	}

	private static function fields_for_type( $type, $post_id = 0 ) {
		$fields = array(
			array( 'key' => '_post_title', 'name' => '_post_title', 'label' => __( 'Post title', 'tinker-valley-content-dashboard' ), 'type' => 'text', 'group_key' => '_content', 'group_label' => __( 'Content', 'tinker-valley-content-dashboard' ) ),
			array( 'key' => '_excerpt', 'name' => '_excerpt', 'label' => __( 'Excerpt', 'tinker-valley-content-dashboard' ), 'type' => 'textarea', 'group_key' => '_content', 'group_label' => __( 'Content', 'tinker-valley-content-dashboard' ) ),
			array( 'key' => '_featured_image', 'name' => '_featured_image', 'label' => __( 'Featured image', 'tinker-valley-content-dashboard' ), 'type' => 'image', 'group_key' => '_content', 'group_label' => __( 'Content', 'tinker-valley-content-dashboard' ) ),
		);
		if ( 'page' === $type ) {
			$fields[] = array( 'key' => '_page_screenshot', 'name' => '_page_screenshot', 'label' => __( 'Live page screenshot', 'tinker-valley-content-dashboard' ), 'type' => 'image', 'group_key' => '_content', 'group_label' => __( 'Content', 'tinker-valley-content-dashboard' ) );
		}
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			$groups = acf_get_field_groups( $post_id ? array( 'post_id' => $post_id, 'post_type' => $type ) : array( 'post_type' => $type ) );
			foreach ( $groups as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					$fields[] = self::prepare_field( $field, $group );
				}
			}
		}
		$registered_meta = array_merge( get_registered_meta_keys( 'post', '' ), get_registered_meta_keys( 'post', $type ) );
		foreach ( $registered_meta as $meta_key => $args ) {
			if ( empty( $args['show_in_rest'] ) || 0 === strpos( $meta_key, '_' ) || in_array( $args['type'] ?? 'string', array( 'array', 'object' ), true ) || self::field_name_exists( $fields, $meta_key ) ) {
				continue;
			}
			$fields[] = array(
				'key'          => 'meta:' . $meta_key,
				'name'         => $meta_key,
				'label'        => ucwords( str_replace( array( '-', '_' ), ' ', $meta_key ) ),
				'type'         => self::meta_field_type( $args['type'] ?? 'string' ),
				'required'     => false,
				'instructions' => __( 'Registered by WordPress or another plugin.', 'tinker-valley-content-dashboard' ),
				'group_key'    => '_plugin_meta',
				'group_label'  => __( 'Additional fields', 'tinker-valley-content-dashboard' ),
				'source'       => 'meta',
			);
		}
		return $fields;
	}

	private static function field_name_exists( $fields, $name ) {
		foreach ( $fields as $field ) {
			if ( ( $field['name'] ?? '' ) === $name ) {
				return true;
			}
		}
		return false;
	}

	private static function meta_field_type( $type ) {
		return array(
			'boolean' => 'true_false',
			'integer' => 'number',
			'number'  => 'number',
		)[ $type ] ?? 'text';
	}

	private static function can_edit_registered_meta( $type, $meta_key, $post_id ) {
		$registered = array_merge( get_registered_meta_keys( 'post', '' ), get_registered_meta_keys( 'post', $type ) );
		if ( empty( $registered[ $meta_key ]['show_in_rest'] ) ) {
			return false;
		}
		$callback = $registered[ $meta_key ]['auth_callback'] ?? null;
		return $callback ? (bool) call_user_func( $callback, false, $meta_key, $post_id, get_current_user_id(), 'edit_post_meta', array() ) : current_user_can( 'edit_post_meta', $post_id, $meta_key );
	}

	private static function prepare_field( $field, $group ) {
		$prepared = array(
						'key'          => $field['key'],
						'name'         => $field['name'],
						'label'        => $field['label'],
						'type'         => $field['type'],
						'choices'      => $field['choices'] ?? array(),
						'required'     => ! empty( $field['required'] ),
						'instructions' => $field['instructions'] ?? '',
						'placeholder'  => $field['placeholder'] ?? '',
						'layout'       => $field['layout'] ?? '',
						'wrapper'      => $field['wrapper'] ?? array(),
						'group_key'    => $group['key'],
						'group_label'  => $group['title'],
					);
		if ( ! empty( $field['sub_fields'] ) ) {
			$prepared['sub_fields'] = array_map(
				static function ( $sub_field ) use ( $group ) {
					return self::prepare_field( $sub_field, $group );
				},
				$field['sub_fields']
			);
		}
		return $prepared;
	}

	private static function editor_data( $post ) {
		$fields = self::fields_for_type( $post->post_type, $post->ID );
		$values = array();
		foreach ( $fields as $field ) {
			if ( '_featured_image' === $field['key'] ) {
				$values[ $field['key'] ] = self::editor_value( $field, get_post_thumbnail_id( $post ) );
				continue;
			}
			if ( 0 === strpos( $field['key'], '_' ) ) {
				continue;
			}
			$raw = 'meta' === ( $field['source'] ?? '' ) ? get_post_meta( $post->ID, $field['name'], true ) : ( function_exists( 'get_field' ) ? get_field( $field['key'], $post->ID, false ) : get_post_meta( $post->ID, $field['name'], true ) );
			$values[ $field['key'] ] = self::editor_value( $field, $raw );
		}
		return array(
			'id'        => $post->ID,
			'post_type' => $post->post_type,
			'title'     => self::decode_text( get_the_title( $post ) ),
			'excerpt'   => self::decode_text( $post->post_excerpt ),
			'status'    => $post->post_status,
			'fields'    => $fields,
			'values'    => $values,
			'editUrl'   => get_edit_post_link( $post->ID, 'raw' ),
			'viewUrl'   => get_permalink( $post ),
		);
	}

	private static function editor_value( $field, $raw ) {
		if ( 'group' === $field['type'] && is_array( $raw ) ) {
			$prepared_group = array();
			foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub_field ) {
				$value = $raw[ $sub_field['key'] ] ?? $raw[ $sub_field['name'] ] ?? '';
				$prepared_group[ $sub_field['key'] ] = self::editor_value( $sub_field, $value );
			}
			return $prepared_group;
		}
		if ( 'repeater' === $field['type'] && is_array( $raw ) ) {
			return array_values(
				array_map(
					static function ( $row ) use ( $field ) {
						$prepared_row = array();
						foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub_field ) {
							$value = $row[ $sub_field['key'] ] ?? $row[ $sub_field['name'] ] ?? '';
							$prepared_row[ $sub_field['key'] ] = self::editor_value( $sub_field, $value );
						}
						return $prepared_row;
					},
					$raw
				)
			);
		}
		if ( in_array( $field['type'], array( 'image', 'file' ), true ) && is_numeric( $raw ) ) {
			return array(
				'id'       => (int) $raw,
				'url'      => esc_url_raw( wp_get_attachment_url( (int) $raw ) ?: '' ),
				'filename' => basename( (string) get_attached_file( (int) $raw ) ),
				'type'     => get_post_mime_type( (int) $raw ),
			);
		}
		if ( 'gallery' === $field['type'] && is_array( $raw ) ) {
			return array_values(
				array_map(
					static function ( $id ) {
						return array(
							'id'  => (int) $id,
							'url' => esc_url_raw( wp_get_attachment_image_url( (int) $id, 'medium' ) ?: '' ),
						);
					},
					$raw
				)
			);
		}
		return $raw;
	}

	private static function card_data( $post, $config ) {
		$title       = self::decode_text( (string) self::display_value( $post, $config['title_field'] ) );
		$description = self::decode_text( wp_strip_all_tags( (string) self::display_value( $post, $config['description_field'] ) ) );
		return array(
			'id'          => $post->ID,
			'title'       => $title,
			'description' => wp_trim_words( $description, 24 ),
			'image'       => self::image_value( $post, $config['image_field'] ),
			'status'      => $post->post_status,
			'modified'    => get_the_modified_date( 'M j, Y', $post ),
			'viewUrl'     => get_permalink( $post ),
			'canDelete'   => current_user_can( 'delete_post', $post->ID ),
		);
	}

	private static function decode_text( $value ) {
		$charset = get_bloginfo( 'charset' ) ?: 'UTF-8';
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, $charset );
	}

	private static function display_value( $post, $field ) {
		if ( '_post_title' === $field ) {
			return get_the_title( $post );
		}
		if ( '_excerpt' === $field ) {
			return has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content;
		}
		if ( 0 === strpos( $field, 'meta:' ) ) {
			return get_post_meta( $post->ID, substr( $field, 5 ), true );
		}
		return function_exists( 'get_field' ) ? get_field( $field, $post->ID ) : get_post_meta( $post->ID, $field, true );
	}

	private static function image_value( $post, $field ) {
		if ( '_page_screenshot' === $field ) {
			return esc_url_raw( 'https://s0.wp.com/mshots/v1/' . rawurlencode( get_permalink( $post ) ) . '?w=800' );
		}
		$value = '_featured_image' === $field ? get_post_thumbnail_id( $post ) : self::display_value( $post, $field );
		if ( is_array( $value ) ) {
			return esc_url_raw( $value['sizes']['large'] ?? $value['url'] ?? '' );
		}
		if ( is_numeric( $value ) ) {
			return esc_url_raw( wp_get_attachment_image_url( (int) $value, 'large' ) ?: '' );
		}
		return esc_url_raw( (string) $value );
	}
}
