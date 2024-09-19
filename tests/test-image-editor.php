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
		$editor = \wp_get_image_editor( $this->jpg );

		$this->assertInstanceOf( 'Fastly_IO\WP_Image_Editor_Fastly', $editor );
	}

	public function test_make_subsizes() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->jpg );

		$meta       = \wp_get_attachment_metadata( $attachment_id );
		$upload_dir = \wp_upload_dir();
		$sizes      = \wp_get_registered_image_subsizes();

		$file_path = \trailingslashit( $upload_dir['basedir'] ) . $meta['file'];

		// Ensure the image was uploaded.
		$this->assertFileExists( $file_path, 'The image was not uploaded' );

		foreach ( $sizes as $size => $size_data ) {
			if ( isset( $meta['sizes'][ $size ] ) ) {
				// Ensure the thumbnail image did not get generated.
				$file_subpath = str_replace( '.jpg', "-{$size_data['width']}x{$size_data['height']}.jpg", $file_path );
				$this->assertFileDoesNotExist( $file_subpath, 'The thumbnail was locally created' );

				$query_str = parse_url( $meta['sizes'][ $size ]['file'], PHP_URL_QUERY );
				$params = \wp_parse_args( $query_str );

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
