<?php
/**
 * Wordpress Block Collaborative Editing.
 *
 * @package Fathanfi/WpCollaborativeEditing
 */

/**
 * Plugin Name: Block Editor Collaborative Editing
 * Description: Allows multiple users to collaborate on the block editor.
 * Version:     0.1.0
 * Plugin URI:  https://github.com/fathanfi/wp-collaborative-editing
 * Author:      Fathan Fisabilillah
 * Author URI:  https://fathanfi.com
 * Text Domain: ffce
 */

namespace Fathanfi\WpCollaborativeEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	exit;
}

// Entry Point.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/namespace.php';

// Vite.
require_once __DIR__ . '/inc/vite/namespace.php';

// Kick it off.
bootstrap();
