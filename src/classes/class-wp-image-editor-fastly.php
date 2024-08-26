<?php
/**
 * WordPress Fastly Image Editor
 * 
 * WordPress Image Editor Class for Fastly IO-enabled sites
 * Based on the GD image editor
 *
 * @package Fastly_IO
 */

namespace Fastly_IO;

use \WP_Error;
use \WP_Image_Editor_GD;

class WP_Image_Editor_Fastly extends WP_Image_Editor_GD
{

    /**
     * GD Resource
     *
     * @var resource
     */
    protected $image;

    /**
     * Additional image optimization parameters to include in the filename
     *
     * @var array
     */
    protected $ioQsParams = [
        'width'  => false,
        'height' => false,
        'crop'   => false,
        'orient' => false,
    ];

    /**
     * Checks to see if the current environment supports this service.
     * Can do everything GD can but can also rotate, so override this method.
     *
     * @param array $args
     * @return bool
     */
    public static function test($args = [])
    {
        // Ideally we should check if the Fastly Image I/O service is enabled.
        // But there may not be a server-side way to do that.
        return true;
    }

    /**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @since 1.1.0
     * @see https://www.fastly.com/documentation/reference/io/#limitations-and-constraints
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		$fastly_mime_types = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
		
        return in_array( $mime_type, $fastly_mime_types );
	}

    /**
	 * Create an image sub-size and return the image meta data value for it.
	 *
	 * @since 1.1.0
	 *
	 * @param array $size_data {
	 *     Array of size data.
	 *
	 *     @type int        $width  The maximum width in pixels.
	 *     @type int        $height The maximum height in pixels.
	 *     @type bool|array $crop   Whether to crop the image to exact dimensions.
	 * }
	 * @return array|WP_Error The image data array for inclusion in the `sizes` array in the image meta,
	 *                        WP_Error object on error.
	 */
	public function make_subsize( $size_data ) {
		if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
		}

        $size_data['width']  = $size_data['width'] ?: false;
        $size_data['height'] = $size_data['height'] ?: false;
        $size_data['crop']   = $size_data['crop'] ?: false;

        $new_dims = image_resize_dimensions( 
            $this->size['width'], 
            $this->size['height'], 
            $size_data['width'], 
            $size_data['height'], 
            $size_data['crop'] 
        );

        if ( ! $new_dims ) {
            return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. New dimensions are empty.' ) );
        }

        $this->update_size( $new_dims[4], $new_dims[5] );

        $params = [
            'width'  => $new_dims[4],
            'height' => $new_dims[4],
        ];

        if ( $size_data['crop'] ) {
            $gcd = $this->_find_gcd( $new_dims[4], $new_dims[5] );
            $params['crop'] = sprintf( '%d:%d', $new_dims[4] / $gcd, $new_dims[5] / $gcd );
        }

        $small_file = add_query_arg( $params, $this->file );

        $data = [
            'file'   => $small_file,
            'width'  => $size_data['width'],
            'height' => $size_data['height'],
            'mime-type' => $this->mime_type,
        ];

        return $data;
	}

    /**
     * Find the greatest common divisor of two positive numbers.
     * 
     * @param int $num1
     * @param int $num2
     * @return int
     */
    private function _find_gcd( $num1, $num2 ) {
        return ($num1 % $num2) ? $this->_find_gcd($num2, $num1 % $num2) : $num2;
    }
}
