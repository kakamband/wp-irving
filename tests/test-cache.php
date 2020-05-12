<?php
/**
 * Class Cache_Tests
 *
 * @package WP_Irving
 */

/**
 * Tests for integration with WPCOM Legacy Redirector.
 */
class Cache_Tests extends WP_UnitTestCase {

	/**
	 * Helpers class instance.
	 *
	 * \WP_Irving_Test_Helpers
	 */
	static $helpers;

	/**
	 * Helpers class instance.
	 *
	 * \WP_Irving\Cache
	 */
	static $cache;

	/**
	 * Components endpoint instance.
	 *
	 * \WP_Irving\REST_API\Components_Endpoint
	 */
	static $components_endpoint;

	/**
	 * Test suite setup.
	 */
	public static function setUpBeforeClass() {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		self::$helpers = new \WP_Irving\Test_Helpers();
		self::$cache   = \WP_Irving\Cache::instance();
	}

	/**
	 * Test post purge urls.
	 */
	public function test_get_post_purge_urls() {
		$current_user = $this->factory->user->create_and_get(
			[
				'user_login'  => 'alley',
			]
		);
		$current_post = $this->factory->post->create_and_get(
			[
				'post_title'  => rand_str(),
				'post_date'   => '2020-01-01 00:00:00',
				'post_author' => $current_user->ID,
			]
		);
		$current_term = $this->factory->term->create_and_get(
			[
				'name'     => 'Test',
				'taxonomy' => 'post_tag',
			]
		);
		// print_r( $current_term );
		$this->factory->term->add_post_terms( $current_post->ID, [ $current_term->slug ], 'post_tag' );

		$this->assertEquals(
			self::$cache->get_post_purge_urls( $current_post->ID ),
			[
				'http://example.org/2020/01/01/' . $current_post->post_title . '/',
				'http://example.org/',
				'http://example.org/?cat=1',
				'http://example.org/?cat=1/feed/',
				'http://example.org/?tag=' . $current_term->slug,
				'http://example.org/?tag=' . $current_term->slug . '/feed/',
				'http://example.org/author/' . $current_user->data->user_login . '/',
				'http://example.org/author/' . $current_user->data->user_login . '/feed/',
				'http://example.org/feed/rdf/',
				'http://example.org/feed/rss/',
				'http://example.org/feed/',
				'http://example.org/feed/atom/',
				'http://example.org/comments/feed/atom/',
				'http://example.org/comments/feed/',
				'http://example.org/2020/01/01/' . $current_post->post_title . '/feed/',
			]
		);
	}

	/**
	 * Test term purge urls.
	 */
	public function test_get_term_purge_urls() {
		$current_term = $this->factory->term->create_and_get(
			[
				'name'     => 'Test',
				'taxonomy' => 'category',
			]
		);

		$this->assertEquals(
			self::$cache->get_term_purge_urls( $current_term->term_id ),
			[
				'http://example.org/?cat=' . $current_term->term_id,
				'http://example.org/?cat=' . $current_term->term_id . '/feed/',
			]
		);
	}

	/**
	 * Test user purge urls.
	 */
	public function test_get_user_purge_urls() {
		$current_user = $this->factory->user->create_and_get(
			[
				'user_login'  => 'alley',
			]
		);

		$this->assertEquals(
			self::$cache->get_user_purge_urls( $current_user ),
			[
				'http://example.org/author/' . $current_user->data->user_login . '/',
				'http://example.org/author/' . $current_user->data->user_login . '/feed/',
			]
		);
	}
}
