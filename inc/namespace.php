<?php
/**
 * Namespace functions.
 */

namespace Fathanfi\WpCollaborativeEditing;

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function bootstrap(): void {
	( new Admin\Connection() )->register();
	( new Admin\BlockEditorBinding() )->register();
}
