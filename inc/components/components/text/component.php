<?php
/**
 * Text.
 *
 * Output text.
 *
 * @package WP_Irving
 */

namespace WP_Irving\Components;

/**
 * Register the component and callback.
 */
register_component_from_config( __DIR__ . '/component' );

/**
 * Output the content config value as a text node instead of a component.
 *
 * @param array $component Component as an array.
 * @return array|string
 */
function serialize_text_component( array $component ) {
	return $component;
}
add_filter( 'wp_irving_serialize_component_array', __NAMESPACE__ . '\serialize_text_component' );
