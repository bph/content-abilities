<?php
/**
 * Plugin Name: Content Abilities
 * Description: Registers post creation and editing abilities for the WordPress Abilities API, exposable via MCP.
 * Version:     0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: mcp-adapter
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Content Abilities requires WordPress 6.9+ with the Abilities API.', 'content-abilities' );
		echo '</p></div>';
	} );
	return;
}

require_once __DIR__ . '/includes/class-post-abilities.php';

add_action( 'wp_abilities_api_categories_init', array( 'Content_Abilities\Post_Abilities', 'register_categories' ) );
add_action( 'wp_abilities_api_init', array( 'Content_Abilities\Post_Abilities', 'register' ) );
