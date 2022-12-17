<?php
/**
 * Class BlockEditor.
 */

namespace Fathanfi\WpCollaborativeEditing\Interfaces;

/**
 * NetworkProvider Interface.
 *
 * @package Fathanfi\WpCollaborativeEditing\Interfaces
 */
interface ConnectionProvider {
	/**
	 * Register network service requirements.
	 *
	 * @return void
	 */
	public function register(): void;
}
