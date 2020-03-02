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
    protected $ioQsParams = [];

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
     * Loads image from $this->file into new GD resource
     *
     * @return bool|WP_Error
     */
    public function load()
    {
        if ($this->image) {
            return true;
        }

        if (! \is_file($this->file) && ! \preg_match('|^https?://|', $this->file)) {
            return new WP_Error('error_loading_image', __('File doesn&#8217;t exist?'), $this->file);
        }

        // So we're NOT going to do this for now; instead, we'll lazy load
        // $this->image only if someone requires that resource be available.
        // wp_raise_memory_limit('image');
        // $this->image = @imagecreatefromstring(file_get_contents($this->file));

        // if (! is_resource($this->image)) {
        //     return new WP_Error('invalid_image', __('File is not an image.'), $this->file);
        // }

        $size = @getimagesize($this->file);
        if (! $size) {
            return new WP_Error('invalid_image', __('Could not read image size.'), $this->file);
        }

        // Only difference between this and the parent is that we're not doing
        // any "imagealphablending" or "imagesavealpha" calls here.

        $this->update_size($size[0], $size[1]);
        $this->mime_type = $size['mime'];

        return $this->set_quality();
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

        return true;
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
     * Do multiple resize operations.
     *
     * @deprecated Please use make_subsize instead.
     * @param array $sizes
     * @return array
     */
    public function multi_resize($sizes)
    {
        $metadata = [];
        foreach ($sizes as $size => $size_data) {
            $resized = $this->make_subsize($size_data);
            $metadata[$size] = [
                'width' => $this->size['width'],
                'height' => $this->size['height']
            ];
        }
        return $metadata;
    }

    /**
     * Simulate a crop operation. Not currently supported.
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
        // TODO: add the cropping parameters as a query string.
        // return true;

        // For now, though, use the underlying GD crop feature.
        $this->populateImage();
        return parent::crop($src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs);
    }

    /**
     * Rotate an image by applying an orientation query parameter.
     *
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
                    $dst_h = $src_w;
                    $dst_w = $src_h;
                    break;
                case 180:
                    $this->ioQsParams['orient'] = '3';
                    $dst_h = $src_h;
                    $dst_w = $src_w;
                    break;
                case 270:
                    $this->ioQsParams['orient'] = 'l';
                    $dst_h = $src_w;
                    $dst_w = $src_h;
                    break;
                default:
                    $dst_h = $src_h;
                    $dst_w = $src_w;

            }
            $this->update_size($dst_w, $dst_h);
        } else {
            // Rather than not rotating, let's let GD try.
            // return new WP_Error(
            //     'image_rotate_error',
            //     __('Fastly IO can only rotate in 90-degree increments.'),
            //     $this->file
            // );
            $this->populateImage();
            return parent::rotate($angle);
        }

        return true;
    }

    /**
     * Flip an image along an axis or two by applying an orientation query parameter.
     *
     * @param bool $horz
     * @param bool $vert
     * @return true
     */
    public function flip($horz, $vert)
    {
        // size won't change but we need to add params
        if ($horz && !$vert) {
            $this->ioQsParams['orient'] = '2';
        } elseif ($vert && !$horz) {
            $this->ioQsParams['orient'] = '4';
        } elseif ($horz && $vert) {
            $this->ioQsParams['orient'] = 'hv';
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
    public function generate_filename($suffix = null, $dest_path = null, $extension = null)
    {
        $generated = parent::generate_filename($suffix, $dest_path, $extension);

        // Tack on any additional query parameters
        if (count($this->ioQsParams) > 0) {
            $generated .= "?" . http_build_query($this->ioQsParams);
        }

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
        // (NOT A) OR (NOT B) == NOT (A AND B)
        if (!($width && $height)) {
            $this->populateImage();
        }
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
