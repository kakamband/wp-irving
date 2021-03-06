<?php
/**
 * Context store for use in template hydration.
 *
 * @package WP_Irving
 */

namespace WP_Irving\Components;

/**
 * Context store for use in template hydration.
 */
class Context_Store {

	/**
	 * List of context objects.
	 *
	 * Each time context is added to
	 *
	 * @var array
	 */
	private $context = [];

	/**
	 * Returns the current context values.
	 *
	 * @return array The current context array.
	 */
	public function get_context(): array {
		return array_values( $this->context )[0] ?? [];
	}

	/**
	 * Get the value of a context key.
	 *
	 * @param string $key The context key to retrieve.
	 * @return mixed The value being returned. Null if not found.
	 */
	public function get( string $key ) {
		return $this->get_context()[ $key ] ?? null;
	}

	/**
	 * Return the context to it's previous state.
	 *
	 * @return true
	 */
	public function reset(): bool {
		array_shift( $this->context );

		return true;
	}

	/**
	 * Set a context value for a specific key.
	 *
	 * @param array $context A map of context keys and values to set.
	 * @return bool
	 */
	public function set( array $context ): bool {
		array_unshift( $this->context, array_merge( $this->get_context(), $context ) );

		return true;
	}
}
