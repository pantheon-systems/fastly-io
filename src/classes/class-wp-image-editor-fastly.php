<?php
/**
 * WordPress Fastly Image Editor
 *
 * @package Fastly_IO
 */

/**
 * WordPress Image Editor Class for Fastly IO-enabled sites
 * Based on the GD image editor
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
        if (! \extension_loaded('gd') || ! \function_exists('gd_info')) {
            return false;
        }

        return true;
    }

    /**
     * Simulates saving in-memory image to file.
     * Doesn't actually save to disk but does let the ML _think_ it did.
     *
     * @param string|null $filename
     * @param string|null $mime_type
     * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
     */
    public function save($filename = null, $mime_type = null)
    {
        $saved = $this->_save(null, $filename, $mime_type);
        $this->file = $saved['path'];
        $this->mime_type = $saved['mime-type'];
        return $saved;
    }

    /**
     * Does the grunt work of not-actually-saving the file.
     *
     * @param resource $_image
     * @param string|null $_filename
     * @param string|null $_mime_type
     * @return WP_Error|array
     */
    protected function _save($_image, $_filename = null, $_mime_type = null)
    {
        list($filename, $extension, $mime_type) = $this->get_output_format($_filename, $_mime_type);
        if (! $filename) {
            $filename = $this->generate_filename(null, null, $extension);
        }

        // Don't need to call it here but $this->make_image(...) will always
        // return true if it does get called somewhere.

        // Return the array that the media library needs.
        return [
            'path' => $filename,
            'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)),
            'width' => $this->size['width'],
            'height' => $this->size['height'],
            'mime-type' => $mime_type,
        ];
    }

    /**
     * Placeholder method to simulate saving/streaming a file.
     *
     * @param string|stream $filename
     * @param callable $function
     * @param array $arguments
     * @return bool
     */
    protected function make_image($filename, $function, $arguments)
    {
        // The GD library uses 'imagegif', 'imagepng', and 'imagejpeg' which
        // save the file to disk and then return a boolean indicating the file
        // was saved properly.

        // noop
        return true;
    }

    /**
     * Simulates an image resize operation.
     *
     * @param int|null $max_w
     * @param int|null $max_h
     * @param bool $crop
     * @return true
     */
    public function resize($max_w, $max_h, $crop = false)
    {
        if ($this->size['width'] == $max_w && $this->size['height'] == $max_h) {
            return true;
        }

        $resized = $this->_resize($max_w, $max_h, $crop);
        
        // The parent library would do the resize here and check if the result was
        // an image resource. We're assuming the resize happened.

        return $resized;
    }

    /**
     * Do the grunt work of "resizing" an image.
     *
     * @param int|null $max_w
     * @param int|null $max_h
     * @param bool $crop
     * @return resource|WP_Error
     */
    protected function _resize($max_w, $max_h, $crop = false)
    {
        if (! $dims = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop)) {
            return new WP_Error(
                'error_getting_dimensions',
                __('Could not calculate resized image dimensions'),
                $this->file
            );
        }

        list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;
        $this->update_size($dst_w, $dst_h);
        return $this->image;
    }

    /**
     * Simulate a crop operation.
     *
     * @param int  $src_x   The start x position to crop from.
     * @param int  $src_y   The start y position to crop from.
     * @param int  $src_w   The width to crop.
     * @param int  $src_h   The height to crop.
     * @param int  $dst_w   Optional. The destination width.
     * @param int  $dst_h   Optional. The destination height.
     * @param bool $src_abs Optional. If the source crop points are absolute.
     * @return true
     */
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        /*
         * If destination width/height isn't specified,
         * use same as width/height from source.
         */
        if ( ! $dst_w ) {
            $dst_w = $src_w;
        }
        if ( ! $dst_h ) {
            $dst_h = $src_h;
        }

        if ( $src_abs ) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }

        foreach ( array( $src_w, $src_h, $dst_w, $dst_h ) as $value ) {
            if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
                return new WP_Error( 'image_crop_error', __( 'Image crop failed.' ), $this->file );
            }
        }

        $this->ioQsParams['crop'] = sprintf( '%d,%d,x%d,y%d,safe', $src_w, $src_h, $src_x, $src_y );

        if ( $dst_w ) {
            $this->ioQsParams['width'] = $dst_w;
        }

        if ( $dst_h ) {
            $this->ioQsParams['height'] = $dst_h;
        }
    }

    /**
     * Rotate an image by applying an orientation query parameter.
     *
     * @see https://www.fastly.com/documentation/reference/io/orient/
     * @param float $angle The angle to use to rotate. Should be in 90-degree increments.
     * @return true|WP_Error
     */
    public function rotate($angle)
    {
        // Fastly's a little limited on rotation - can only do 90 degree increments
        if ($angle % 90 == 0) {
            switch ($angle) {
                case 90:
                    $this->ioQsParams['orient'] = 'r';
                    break;
                case 180:
                    $this->ioQsParams['orient'] = 'hv';
                    break;
                case 270:
                    $this->ioQsParams['orient'] = 'l';
                    break;
            }
        } else {
            $this->populateImage();
            return parent::rotate($angle);
        }

        return true;
    }

    /**
     * Flip an image along an axis or two by applying an orientation query parameter.
     *
     * @see https://www.fastly.com/documentation/reference/io/orient/
     * @param bool $horz
     * @param bool $vert
     * @return true
     */
    public function flip($horz, $vert)
    {
        $orient = '';

        if ( $horz ) {
            $orient .= 'h';
        }

        if ( $vert ) {
            $orient .= 'v';
        }

        if ( $orient ) {
            $this->ioQsParams['orient'] = $orient;
        }

        return true;
    }

    /**
     * Generate a filename with any IO-specific query parmameters.
     *
     * @param string|null $suffix
     * @param string|null $dest_path
     * @param string|null $extension
     * @return string
     */
    public function generate_filename($suffix = null, $dest_path = null, $extension = null) {
        // $suffix will be appended to the destination filename, just before the extension.
        if ( $suffix ) {
            $generated = parent::generate_filename($suffix, $dest_path, $extension);
        } else {
            $dir = pathinfo( $this->file, PATHINFO_DIRNAME );
            $ext = pathinfo( $this->file, PATHINFO_EXTENSION );

            $name    = wp_basename( $this->file, ".$ext" );
            $new_ext = strtolower( $extension ? $extension : $ext );

            if ( ! is_null( $dest_path ) ) {
                if ( ! wp_is_stream( $dest_path ) ) {
                    $_dest_path = realpath( $dest_path );
                    if ( $_dest_path ) {
                        $dir = $_dest_path;
                    }
                } else {
                    $dir = $dest_path;
                }
            }

            $generated = trailingslashit( $dir ) . "{$name}.{$new_ext}";
        }

        // Tack on any additional query parameters
        $generated = add_query_arg( $this->ioQsParams, $generated );

        return $generated;
    }

    /**
     * Override the GD library's handling of update_size to ensure
     * that the image resource is created if $width or $height is empty.
     *
     * @param int|boolean $width
     * @param int|boolean $height
     * @return boolean
     */
    protected function update_size($width = false, $height = false)
    {
        $this->ioQsParams['width']  = $width ?: $this->ioQsParams['width'];
        $this->ioQsParams['height'] = $height ?: $this->ioQsParams['height'];

        return parent::update_size($width, $height);
    }

    /**
     * Override the GD library's handling of stream to ensure
     * that the image resource is available.
     *
     * @param string $mime_type
     * @return boolean
     */
    public function stream($mime_type = null)
    {
        $this->populateImage();
        return parent::stream($mime_type);
    }

    /**
     * Internal-only method to load the image into memory if necessary.
     *
     * @return boolean|WP_Error
     */
    protected function populateImage()
    {
        if (!$this->image) {
            wp_raise_memory_limit('image');
            $this->image = @imagecreatefromstring(file_get_contents($this->file));
            if (!is_resource($this->image)) {
                return new WP_Error('invalid_image', __('File is not an image.'), $this->file);
            }
        }
        return true;
    }
}
