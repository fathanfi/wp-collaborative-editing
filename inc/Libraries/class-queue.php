<?php
/**
 * Class Queue Implementation with WP Cache.
 */

namespace Fathanfi\WpCollaborativeEditing\Libraries;

use ArrayAccess;
use Countable;

/**
 * Queue Class
 */
class Queue implements ArrayAccess, Countable {

	/**
	 * Redis cache key
	 *
	 * @var string
	 */
	protected $cache_key = '';

	/**
	 * Redis cache group
	 *
	 * @var string
	 */
	protected $cache_group = '';

	/**
	 * Constructor
	 *
	 * @param string $key   Cache Key
	 * @param string $group Cache Group
	 */
	public function __construct( string $key, string $group = '' ) {
		$this->cache_key = $key;
		$this->cache_group = $group;

		if ( ! $this->exists() ) {
			$this->allocate();
		}
	}

	/**
	 * Count the items in the queue
	 */
	public function count(): int {
		if ( $this->is_empty() ) {
			return 0;
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );

		return count( $queue );
	}

	/**
	 * Is cache empty or not
	 *
	 * @return bool True if cache is empty, false otherwise
	 */
	public function is_empty(): bool {
		$cache = wp_cache_get( $this->cache_key, $this->cache_group );

		return empty( $cache );
	}

	/**
	 * Get the cache existence
	 *
	 * @return bool True if cache exists, false otherwise.
	 */
	public function exists(): bool {
		wp_cache_get( $this->cache_key, $this->cache_group, false, $found );

		return (bool) $found;
	}

	/**
	 * Allocate the queue inside the cache
	 *
	 * This will override the current queue in the cache.
	 *
	 * @return void
	 */
	public function allocate(): void {
		wp_cache_set( $this->cache_key, [], $this->cache_group );
	}

	/**
	 * Clear the queue inside the cache
	 *
	 * @return void
	 */
	public function clear(): void {
		wp_cache_delete( $this->cache_key, $this->cache_group );
		$this->allocate();
	}

	/**
	 * Removes and returns the value at the front of the queue
	 *
	 * @return mixed Item to retrieve from the queue.
	 */
	public function pop() {
		if ( $this->is_empty() ) {
			return [];
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );
		$item = reset( $queue );
		array_shift( $queue );

		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		wp_cache_set( $this->cache_key, $queue, $this->cache_group );

		return $item;
	}

	/**
	 * Pushes values into the queue
	 *
	 * @param mixed $item Item to push into the queue
	 *
	 * @return void
	 */
	public function push( $item ): void {
		if ( ! $this->exists() ) {
			$this->allocate();
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );
		$queue[] = $item;
		wp_cache_set( $this->cache_key, array_unique( $queue ), $this->cache_group );
	}

	/**
	 * Whether an offset exists
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset An offset to check for.
	 *
	 * @return bool true on success or false on failure.
	 */
	public function offsetExists( $offset ): bool {
		if ( ! $this->exists() ) {
			$this->allocate();
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );

		return in_array( $offset, $queue, true );
	}

	/**
	 * Offset to retrieve
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return array Get array items from offset.
	 */
	public function offsetGet( $offset ): array {
		if ( ! $this->exists() ) {
			$this->allocate();
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );

		return $queue[ $offset ] ?? [];
	}

	/**
	 * Offset to set
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value  The value to set.
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value ) {
		if ( ! $this->exists() ) {
			$this->allocate();
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );
		$queue[ $offset ] = $value;
		wp_cache_set( $this->cache_key, array_unique( $queue ), $this->cache_group );
	}

	/**
	 * Offset to unset
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset The offset to unset.
	 * @return void
	 */
	public function offsetUnset( $offset ) {
		if ( ! $this->exists() ) {
			$this->allocate();
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );
		unset( $queue[ $offset ] );
		wp_cache_set( $this->cache_key, $queue, $this->cache_group );
	}

	/**
	 * Converts the queue to an array.
	 *
	 * @return array An array containing all the values in the same order as the queue.
	 */
	public function toArray(): array {
		if ( $this->is_empty() ) {
			return [];
		}

		$queue = wp_cache_get( $this->cache_key, $this->cache_group );

		if ( ! is_array( $queue ) ) {
			return [];
		}

		return $queue;
	}
}
