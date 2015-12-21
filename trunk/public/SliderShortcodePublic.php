<?php
namespace Pub;

use Inc\PostGallery;
use MagicAdminPage\MagicAdminPage;
use Thumb\Thumb;


class SliderShortcodePublic {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $pluginName The ID of this plugin.
     */
    private $pluginName;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;


    /**
     * Textdomain of the plugin
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $textdomain;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string $pluginName The name of this plugin.
     * @param      string $version The version of this plugin.
     */
    public function __construct( $pluginName, $version ) {
        $this->textdomain = $pluginName;
        $this->pluginName = $pluginName;
        $this->version = $version;

        add_shortcode( 'postslider', array( $this, '_shortcode' ) );
        add_shortcode( 'slider', array( $this, '_shortcode' ) );
    }

    public function _shortcode( $args, $content ) {
        if ( empty( $args[0] ) ) {
            return;
        }

        $sliderid = $args[0];

        // get settings from post-meta
        $loadFrom = get_post_meta( $sliderid, 'sliderLoadFrom', true );
        $width = get_post_meta( $sliderid, 'sliderWidth', true );
        $height = get_post_meta( $sliderid, 'sliderHeight', true );
        $scale = get_post_meta( $sliderid, 'sliderScale', true );
        $owlConfig = get_post_meta( $sliderid, 'sliderOwlConfig', true );

        $imgWidth = get_post_meta( $sliderid, 'sliderImageWidth', true );
        $imgHeight = get_post_meta( $sliderid, 'sliderImageHeight', true );

        $images = PostGallery::getImages( $sliderid );

        // get from sgortcode-arguments
        if ( !empty( $args['width'] ) ) {
            $width = $args['width'];
        }
        if ( !empty( $args['height'] ) ) {
            $height = $args['height'];
        }
        if ( !empty( $args['imgwidth'] ) ) {
            $imgWidth = $args['imgwidth'];
        }
        if ( !empty( $args['imgheight'] ) ) {
            $imgHeight = $args['imgheight'];
        }
        if ( !empty( $args['scale'] ) ) {
            $scale = $args['scale'];
        }

        // use slider-width/height as maximun for image-scaling
        if ( empty( $imgWidth ) ) {
            $imgWidth = $width;
        }
        if ( empty( $imgHeight ) ) {
            $imgHeight = $height;
        }

        // load images from other posts
        if ( !empty( $loadFrom ) && is_array( $loadFrom ) ) {
            // load from other posts
            foreach ( $loadFrom as $loadId ) {
                $images = array_merge( $images, PostGallery::getImages( $loadId ) );
            }
        }

        // resize images
        if ( !empty( $imgWidth ) || !empty( $imgHeight ) ) {
            $images = PostGallery::getPicsResized( $images, array(
                'width' => !empty( $imgWidth ) ? $imgWidth : '9999',
                'height' => !empty( $imgHeight ) ? $imgHeight : '9999',
                'scale' => is_null( $scale ) ? 0 : $scale,
            ) );
        }

        // set style
        $style = '';
        $style .= !empty( $width ) ? 'width:' . $width . 'px;' : '';
        $style .= !empty( $height ) ? 'height:' . $height . 'px;' : '';

        // output html
        echo '<div class="pg-slider-' . $sliderid . ' postgallery-slider owl-carousel owl-theme" style="' . $style . '">';
        foreach ( $images as $image ) {
            echo '<div class="slider-image">
                <img width="' . $image['width'] . '" height="' . $image['height']
                . '" class="lazyload" data-src="' . $image['url'] . '" alt="' . $image['alt'] . '" />';


            if ( !empty( $image['title'] ) ) {
                echo '<div class="slider-image-title">' . $image['title'].'</div>';
            }
            if ( !empty( $image['desc'] ) ) {
                echo '<div class="slider-image-desc">' . $image['desc'].'</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        // output script
        echo '<script>
            jQuery(".pg-slider-' . $sliderid . '").owlCarousel({' . $owlConfig . '});
            </script>';

    }
}