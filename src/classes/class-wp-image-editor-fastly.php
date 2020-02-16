<?php
/**
 * WordPress Fastly Image Editor
 *
 * @package Fastly_IO
 */

/**
 * WordPress Image Editor Class for Fastly IO-enabled sites
 */

namespace Fastly_IO;

use \WP_Error;
use \WP_Image_Editor;

class WP_Image_Editor_Fastly extends WP_Image_Editor
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

    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

    /**
     * Checks to see if the current environment supports this service.
     * Assumes "yes" if it's installed
     *
     * @param array $args
     * @return bool
     */
    public static function test($args = [])
    {
        return true;
    }

    /**
     * Checks to see if the editor supports the mime type specified.
     *
     * @param string $mime_type
     * @return bool
     */
    public static function supports_mime_type($mime_type)
    {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
                return true;
            default:
                return false;
        }
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

        if (! is_file($this->file) && ! preg_match('|^https?://|', $this->file)) {
            return new WP_Error('error_loading_image', __('File doesn&#8217;t exist?'), $this->file);
        }

        wp_raise_memory_limit('image');

        $this->image = @imagecreatefromstring(file_get_contents($this->file));

        if (! is_resource($this->image)) {
            return new WP_Error('invalid_image', __('File is not an image.'), $this->file);
        }

        $size = @getimagesize($this->file);
        if (! $size) {
            return new WP_Error('invalid_image', __('Could not read image size.'), $this->file);
        }

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
        $saved = $this->_save($this->image, $filename, $mime_type);
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
     * Create an image sub-size and return the image meta data value for it.
     *
     * @param array $size_data
     * @return WP_Error|array
     */
    public function make_subsize($size_data)
    {
        if (! isset($size_data['width']) && ! isset($size_data['height'])) {
            return new WP_Error('image_subsize_create_error', __('Cannot resize the image. Both width and height are not set.'));
        }

        $orig_size = $this->size;
        if (! isset($size_data['width'])) {
            $size_data['width'] = null;
        }
        if (! isset($size_data['height'])) {
            $size_data['height'] = null;
        }
        if (!isset($size_data['crop'])) {
            $size_data['crop'] = false;
        }

        $resized = $this->_resize($size_data['width'], $size_data['height'], $size_data['crop']);

        if (is_wp_error($resized)) {
            $saved = $resized;
        } else {
            $saved = $this->_save($resized);
            imagedestroy($resized);
        }

        $this->size = $orig_size;

        if (! is_wp_error($saved)) {
            unset($saved['path']);
        }

        return $saved;
    }

    /**
     * Simulates an image resize operation.
     *
     * @param int|null $max_w
     * @param int|null $max_h
     * @param bool $crop
     * @return true|WP_Error
     */
    public function resize($max_w, $max_h, $crop = false)
    {
        if ($this->size['width'] == $max_w && $this->size['height'] == $max_h) {
            return true;
        }

        $resized = $this->_resize($max_w, $max_h, $crop);
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
            $resized = $this->resize($size_data['width'], $size_data['height'], $size_data['crop'] ?: false);
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
     * @param int $src_x
     * @param int $src_y
     * @param int $src_w
     * @param int $src_h
     * @param int|null $dst_w
     * @param int|null $dst_h
     * @param bool $src_abs
     * @return true
     */
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        // TODO:
        return true;
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
            return new WP_Error(
                'image_rotate_error',
                __('Fastly IO can only rotate in 90-degree increments.'),
                $this->file
            );
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
     * Stream the contents of $this->image back to the browser.
     *
     * @param string|null $mime_type
     * @return bool
     */
    public function stream($mime_type = null)
    {
        list($filename, $extension, $mime_type) = $this->get_output_format(null, $mime_type);

        switch ($mime_type) {
            case 'image/png':
                header('Content-Type: image/png');
                return imagepng($this->image);
            case 'image/gif':
                header('Content-Type: image/gif');
                return imagegif($this->image);
            default:
                header('Content-Type: image/jpeg');
                return imagejpeg($this->image, null, $this->get_quality());
        }
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
}
