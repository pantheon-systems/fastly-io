<?php
/**
 * Class Image_Editor_Test
 *
 * @package Fastly_IO
 */

namespace Fastly_IO;

use WP_UnitTestCase;

/**
 * Sample test case.
 */
class Image_Editor_Test extends WP_UnitTestCase {
	protected $jpg = __DIR__ . '/assets/image.jpg';

	/**
	 * A single example test.
	 */
	public function test_get_image_editor() {
		$editor = wp_get_image_editor( $this->jpg );

		$this->assertInstanceOf( 'Fastly_IO\WP_Image_Editor_Fastly', $editor );
	}

	public function test_make_subsizes() {
		$attachment_id = $this->factory->attachment->create_object( [ 'file' => $this->jpg ] );

		$meta = wp_create_image_subsizes( $this->jpg, $attachment_id );

		$sizes = wp_get_registered_image_subsizes();

		foreach ( $sizes as $size => $size_data ) {
			if ( isset( $meta['sizes'][ $size ] ) ) {
				$query_str = parse_url( $meta['sizes'][ $size ]['file'], PHP_URL_QUERY );
				$params = wp_parse_args( $query_str );

				// If width is non-zero, test they're equal.
				if ( $size_data['width'] ) {
					$this->assertEquals( $size_data['width'], $params['width'] );
				}

				// If height is non-zero, test they're equal.
				if ( $size_data['height'] ) {
					$this->assertEquals( $size_data['height'], $params['height'] );
				}

				// If the image size is cropped, test it's set.
				if ( $size_data['crop'] ) {
					$this->assertStringMatchesFormat( '%d:%d', $params['crop'] );
				}
			}
		}
	}
}
