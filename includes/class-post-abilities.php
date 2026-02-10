<?php

namespace Content_Abilities;

/**
 * Registers content management abilities for posts.
 *
 * @internal
 */
class Post_Abilities {

	/**
	 * Registers ability categories.
	 */
	public static function register_categories(): void {
		wp_register_ability_category(
			'content',
			array(
				'label'       => __( 'Content', 'content-abilities' ),
				'description' => __( 'Abilities for creating and managing posts and pages.', 'content-abilities' ),
			)
		);
	}

	/**
	 * Registers all post abilities.
	 */
	public static function register(): void {
		self::register_create_post();
		self::register_update_post();
		self::register_get_post();
		self::register_find_posts();
	}

	/**
	 * Registers the content/create-post ability.
	 */
	private static function register_create_post(): void {
		wp_register_ability(
			'content/create-post',
			array(
				'label'               => __( 'Create Post', 'content-abilities' ),
				'description'         => __( 'Create a new WordPress post. Supports title, content (HTML or Gutenberg block markup), excerpt, status, categories, and tags.', 'content-abilities' ),
				'category'            => 'content',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Post type slug. Defaults to "post".', 'content-abilities' ),
							'default'     => 'post',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'The post title.', 'content-abilities' ),
						),
						'content'   => array(
							'type'        => 'string',
							'description' => __( 'The post content. Accepts HTML or Gutenberg block markup.', 'content-abilities' ),
							'default'     => '',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => __( 'The post excerpt.', 'content-abilities' ),
							'default'     => '',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => __( 'Post status. Defaults to "draft".', 'content-abilities' ),
							'enum'        => array( 'draft', 'publish', 'pending', 'private', 'future' ),
							'default'     => 'draft',
						),
						'categories' => array(
							'type'        => 'array',
							'description' => __( 'Array of category IDs to assign.', 'content-abilities' ),
							'items'       => array( 'type' => 'integer' ),
							'default'     => array(),
						),
						'tags'      => array(
							'type'        => 'array',
							'description' => __( 'Array of tag names or IDs to assign.', 'content-abilities' ),
							'items'       => array( 'type' => array( 'string', 'integer' ) ),
							'default'     => array(),
						),
					),
				),
				'output_schema'       => self::post_output_schema(),
				'permission_callback' => function ( $input = array() ): bool {
					$post_type = ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
					$pto       = get_post_type_object( $post_type );
					if ( ! $pto ) {
						return false;
					}
					return current_user_can( $pto->cap->create_posts ?? $pto->cap->edit_posts );
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_type = ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
					$pto       = get_post_type_object( $post_type );
					if ( ! $pto ) {
						return new \WP_Error(
							'content_invalid_post_type',
							sprintf( __( 'Post type "%s" does not exist.', 'content-abilities' ), esc_html( $post_type ) )
						);
					}

					$postarr = array(
						'post_type'    => $post_type,
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => wp_kses_post( $input['content'] ?? '' ),
						'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
						'post_status'  => sanitize_key( $input['status'] ?? 'draft' ),
					);

					if ( ! empty( $input['categories'] ) && is_array( $input['categories'] ) ) {
						$postarr['post_category'] = array_map( 'absint', $input['categories'] );
					}

					$post_id = wp_insert_post( $postarr, true );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					if ( ! empty( $input['tags'] ) && is_array( $input['tags'] ) ) {
						wp_set_post_tags( $post_id, $input['tags'] );
					}

					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error(
							'content_create_failed',
							__( 'Post was created but could not be retrieved.', 'content-abilities' )
						);
					}

					return self::format_post( $post );
				},
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
					),
				),
			)
		);
	}

	/**
	 * Registers the content/update-post ability.
	 */
	private static function register_update_post(): void {
		wp_register_ability(
			'content/update-post',
			array(
				'label'               => __( 'Update Post', 'content-abilities' ),
				'description'         => __( 'Update an existing WordPress post. Only provided fields are modified.', 'content-abilities' ),
				'category'            => 'content',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => __( 'The post ID to update.', 'content-abilities' ),
						),
						'title'   => array(
							'type'        => 'string',
							'description' => __( 'New post title.', 'content-abilities' ),
						),
						'content' => array(
							'type'        => 'string',
							'description' => __( 'New post content. Accepts HTML or Gutenberg block markup.', 'content-abilities' ),
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => __( 'New post excerpt.', 'content-abilities' ),
						),
						'status'  => array(
							'type'        => 'string',
							'description' => __( 'New post status.', 'content-abilities' ),
							'enum'        => array( 'draft', 'publish', 'pending', 'private' ),
						),
						'categories' => array(
							'type'        => 'array',
							'description' => __( 'Array of category IDs to set (replaces existing).', 'content-abilities' ),
							'items'       => array( 'type' => 'integer' ),
						),
						'tags'    => array(
							'type'        => 'array',
							'description' => __( 'Array of tag names or IDs to set (replaces existing).', 'content-abilities' ),
							'items'       => array( 'type' => array( 'string', 'integer' ) ),
						),
					),
				),
				'output_schema'       => self::post_output_schema(),
				'permission_callback' => function ( $input = array() ): bool {
					$post_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( $post_id <= 0 ) {
						return false;
					}
					return current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_id = (int) $input['id'];
					$post    = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error(
							'content_post_not_found',
							__( 'Post not found.', 'content-abilities' )
						);
					}

					$postarr = array( 'ID' => $post_id );

					if ( isset( $input['title'] ) ) {
						$postarr['post_title'] = sanitize_text_field( $input['title'] );
					}
					if ( isset( $input['content'] ) ) {
						$postarr['post_content'] = wp_kses_post( $input['content'] );
					}
					if ( isset( $input['excerpt'] ) ) {
						$postarr['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
					}
					if ( isset( $input['status'] ) ) {
						$postarr['post_status'] = sanitize_key( $input['status'] );
					}
					if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
						$postarr['post_category'] = array_map( 'absint', $input['categories'] );
					}

					$result = wp_update_post( $postarr, true );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
						wp_set_post_tags( $post_id, $input['tags'] );
					}

					$updated_post = get_post( $post_id );
					return self::format_post( $updated_post );
				},
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
				),
			)
		);
	}

	/**
	 * Registers the content/get-post ability.
	 */
	private static function register_get_post(): void {
		wp_register_ability(
			'content/get-post',
			array(
				'label'               => __( 'Get Post', 'content-abilities' ),
				'description'         => __( 'Retrieve a single WordPress post by ID.', 'content-abilities' ),
				'category'            => 'content',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'The post ID to retrieve.', 'content-abilities' ),
						),
					),
				),
				'output_schema'       => self::post_output_schema(),
				'permission_callback' => function ( $input = array() ): bool {
					$post_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( $post_id <= 0 ) {
						return false;
					}
					return current_user_can( 'read_post', $post_id );
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_id = (int) $input['id'];
					$post    = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error(
							'content_post_not_found',
							__( 'Post not found.', 'content-abilities' )
						);
					}

					return self::format_post( $post );
				},
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
				),
			)
		);
	}

	/**
	 * Registers the content/find-posts ability.
	 */
	private static function register_find_posts(): void {
		wp_register_ability(
			'content/find-posts',
			array(
				'label'               => __( 'Find Posts', 'content-abilities' ),
				'description'         => __( 'Search and filter WordPress posts by keyword, post type, and status.', 'content-abilities' ),
				'category'            => 'content',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'      => array(
							'type'        => 'string',
							'description' => __( 'Search keyword to match against title and content.', 'content-abilities' ),
						),
						'post_type'   => array(
							'type'        => 'string',
							'description' => __( 'Post type slug. Defaults to "post".', 'content-abilities' ),
							'default'     => 'post',
						),
						'post_status' => array(
							'type'        => 'string',
							'description' => __( 'Post status to filter by. Defaults to "publish".', 'content-abilities' ),
							'default'     => 'publish',
						),
						'limit'       => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of posts to return (1-50). Defaults to 10.', 'content-abilities' ),
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
						'orderby'     => array(
							'type'        => 'string',
							'description' => __( 'Field to order results by.', 'content-abilities' ),
							'enum'        => array( 'date', 'title', 'modified', 'ID' ),
							'default'     => 'date',
						),
						'order'       => array(
							'type'        => 'string',
							'description' => __( 'Sort direction.', 'content-abilities' ),
							'enum'        => array( 'DESC', 'ASC' ),
							'default'     => 'DESC',
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => self::post_output_schema(),
				),
				'permission_callback' => function (): bool {
					return current_user_can( 'read' );
				},
				'execute_callback'    => function ( $input = array() ) {
					$query_args = array(
						'post_type'      => sanitize_key( $input['post_type'] ?? 'post' ),
						'post_status'    => sanitize_key( $input['post_status'] ?? 'publish' ),
						'posts_per_page' => min( absint( $input['limit'] ?? 10 ), 50 ),
						'orderby'        => sanitize_key( $input['orderby'] ?? 'date' ),
						'order'          => strtoupper( sanitize_key( $input['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
					);

					if ( ! empty( $input['search'] ) ) {
						$query_args['s'] = sanitize_text_field( $input['search'] );
					}

					$query = new \WP_Query( $query_args );
					$posts = array();

					foreach ( $query->posts as $post ) {
						if ( current_user_can( 'read_post', $post->ID ) ) {
							$posts[] = self::format_post( $post );
						}
					}

					return $posts;
				},
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
				),
			)
		);
	}

	/**
	 * Formats a WP_Post into a consistent output array.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Formatted post data.
	 */
	private static function format_post( \WP_Post $post ): array {
		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'status'    => $post->post_status,
			'type'      => $post->post_type,
			'date'      => $post->post_date,
			'modified'  => $post->post_modified,
			'link'      => get_permalink( $post->ID ),
			'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * Returns the shared output schema for a post.
	 *
	 * @return array JSON Schema for post output.
	 */
	private static function post_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string' ),
				'excerpt'   => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'type'      => array( 'type' => 'string' ),
				'date'      => array( 'type' => 'string' ),
				'modified'  => array( 'type' => 'string' ),
				'link'      => array( 'type' => 'string' ),
				'edit_link' => array( 'type' => 'string' ),
			),
		);
	}
}
