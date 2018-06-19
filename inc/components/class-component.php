<?php
/**
 * Parent class file for Irving's Components.
 *
 * @package WP_Irving
 */

namespace WP_Irving\Component;

/**
 * Defines the general component class.
 */
class Component {

	/**
	 * Unique component slug.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Component config.
	 *
	 * @var array
	 */
	public $config = [];

	/**
	 * Component children.
	 *
	 * @var array
	 */
	public $children = [];

	/**
	 * Component constructor.
	 *
	 * @param string $name     Unique component slug or array of name, config,
	 *                         and children value.
	 * @param array  $config   Component config.
	 * @param array  $children Component children.
	 */
	public function __construct( $name = '', array $config = [], array $children = [] ) {

		// Allow $name to be passed as a config array.
		if ( is_array( $name ) ) {
			$data     = $name;
			$name     = $data['name'] ?? '';
			$config   = $data['config'] ?? [];
			$children = $data['children'] ?? [];
		}

		// Store in class vars unless overridden by extended classes.
		$this->name     = ! empty( $this->name ) ? $this->name : $name;
		$this->config   = ! empty( $this->config ) ? $this->config : $config;
		$this->children = ! empty( $this->children ) ? $this->children : $children;

		// Conform config.
		$this->config = wp_parse_args( $this->config, $this->default_config() );
	}

	/**
	 * Define a default config shape.
	 *
	 * @return array Default config.
	 */
	public function default_config() {
		return [];
	}

	/**
	 * Helper to set a top level config value.
	 *
	 * @param  string $key   Config key.
	 * @param  mixed  $value Config value.
	 * @return mixed An instance of this class.
	 */
	public function set_config( $key, $value ) {
		$this->config[ $key ] = $value;
		return $this;
	}

	/**
	 * Helper to set children components.
	 *
	 * @param  array $children Children for this component.
	 * @return mixed An instance of this class.
	 */
	public function set_children( array $children ) {
		$this->children = array_filter( $children );
		return $this;
	}

	/**
	 * Helper to output this class as an array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		return [
			'name'     => $this->name,
			'config'   => $this->config,
			'children' => $this->children,
		];
	}
}

/**
 * Helper to generate a generic component.
 *
 * @param  string $name     Component name or array of properties.
 * @param  array  $config   Component config.
 * @param  array  $children Component children.
 * @return Component An instance of the Component class.
 */
function component( $name = '', array $config = [], array $children = [] ) {
	return new Component( $name, $config, $children );
}
