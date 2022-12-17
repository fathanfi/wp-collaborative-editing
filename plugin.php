<?php
/**
 * Wordpress Block Collaborative Editing.
 *
 * @package Fathanfi/WpCollaborativeEditing
 */

/**
 * Plugin Name: Block Editor Collaborative Editing
 * Description: Allows multiple users to collaborate on the block editor.
 * Version:     0.0.1
 * Plugin URI:  https://github.com/fathanfi/wp-collaborative-editing
 * Author:      Fathan Fisabilillah
 * Author URI:  https://fathanfi.com
 * Text Domain: ffce
 */

namespace Fathanfi\WpCollaborativeEditing;

// Entry Point.
require_once __DIR__ . '/inc/namespace.php';

// Vite.
require_once __DIR__ . '/inc/vite/namespace.php';

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

// Kick it off.
bootstrap();
