<?php namespace Pub;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/RTO-Websites/post-gallery
 * @since      1.0.0
 *
 * @package    PostGallery
 * @subpackage PostGallery/public
 */

use Elementor\Core\Files\CSS\Post;
use Inc\PostGallery;
use Thumb\Thumb;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    PostGallery
 * @subpackage PostGallery/public
 * @author     RTO GmbH
 */
class PostGalleryPublic {

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

    private $textdomain;

    public static $instance;


    /**
     * The options from admin-page
     *
     * @since       1.0.3
     * @access      private
     * @var         array[]
     */
    private $options;

    public $sliderClass = '';
    public $jsFunction = 'owlCarousel';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string $pluginName The name of the plugin.
     * @param      string $version The version of this plugin.
     */
    public function __construct( $pluginName, $version ) {
        if ( is_admin() && !class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        $this->pluginName = $pluginName;
        $this->textdomain = $pluginName;
        $this->version = $version;
        self::$instance = $this;

        $this->options = PostGallery::getOptions();

        $sliderType = !empty( $this->options['sliderType'] ) ? $this->options['sliderType'] : 'owl';


        switch ( $sliderType ) {
            case 'swiper':
                $this->sliderClass = ' swiper-container';
                break;
            default:
                $this->sliderClass = ' owl-carousel owl-theme';
                break;
        }

        new SliderShortcodePublic( $pluginName, $version );


        add_filter( 'the_content', array( $this, 'addGalleryToContent' ) );
        add_shortcode( 'postgallery', array( $this, 'postgalleryShortcode' ) );
        add_action( 'plugins_loaded', array( $this, 'postgalleryThumb' ) );
        add_action( 'plugins_loaded', array( $this, 'getThumbList' ) );

        // Embed headerscript
        add_action( 'wp_head', array( $this, 'insertHeaderscript' ) );

        // Embed footer-html
        add_action( 'wp_footer', array( $this, 'insertFooterHtml' ) );

        add_filter( 'post_thumbnail_html', array( $this, 'postgalleryThumbnail' ), 10, 5 );
        add_filter( 'get_post_metadata', array( $this, 'postgalleryHasPostThumbnail' ), 10, 5 );
        //add_filter( 'script_loader_tag', array( $this, 'addAsyncAttribute' ), 10, 2 );


        // hook wp-gallery
        if ( !empty( $this->options['hookWpGallery'] ) ) {
            add_filter( 'get_attached_file', array( $this, 'getAttachedFileHook' ), 10, 5 );
            add_filter( 'get_attached_media', array( $this, 'getAttachedMediaHook' ), 10, 5 );
            add_filter( 'get_post_gallery', array( $this, 'getPostGalleryHook' ), 10, 5 );
            add_filter( 'post_gallery', array( $this, 'wpPostGalleryHook' ), 10, 2 );
            add_filter( 'posts_pre_query', array( $this, 'wpPreGetPostsHook' ), 10, 2 );
            add_filter( 'wp_get_attachment_url', array( $this, 'wpGetAttachmentUrlHook' ), 10, 5 );
            //add_filter( 'posts_results', array( $this, 'wpPostResultsHook' ), 10, 2 );
        }
    }

    /**
     * Check if attachment-id is splitable and load postgallery-image
     *
     * @param $url
     * @param $attachmentId
     * @return mixed
     */
    public function wpGetAttachmentUrlHook( $url, $attachmentId ) {
        $split = explode( '-', $attachmentId );
        if ( count( $split ) !== 2 ) {
            return $url;
        }

        $images = PostGallery::getImages( $split[0] );

        if ( empty( $images[$split[1]] ) ) {
            return $url;
        }

        return $images[$split[1]]['url'];
    }


    /**
     * Add fake image-posts to attachment-query on pre_get_posts
     *
     * @param array $posts
     * @param $query
     * @return array
     */
    public function wpPreGetPostsHook( $posts = [], $query ) {
        if ( empty( $query->query['post_type'] ) || $query->query['post_type'] !== 'attachment' ) {
            return $posts;
        }

        if ( empty( $query->query['include'] ) ) {
            return $posts;
        }


        $postids = explode( ',', $query->query['include'] );
        foreach ( $postids as $postid ) {
            $split = explode( '-', $postid );
            if ( count( $split ) !== 2 ) {
                $posts[$split[0]] = get_post( $split[0] );
                continue;
            }
            $post = get_post( $split[0] );

            $images = PostGallery::getImages( $postid );
            if ( empty( $images ) ) {
                continue;
            }

            $count = 0;
            foreach ( $images as $image ) {
                $newPost = json_decode( json_encode( $post ) ); // clone current post-object
                $newId = $post->ID . '-' . $count;
                $newPost->ID = $newId;
                $newPost->post_content = '';
                $newPost->post_title = '';
                $newPost->permalink = '';
                $newPost->link = 'none';
                $newPost->post_type = 'attachment';
                $newPost->post_mime_type = 'image/jpg';
                $newPost->guid = $image['url'];
                $posts[$newId] = $newPost;
                $count += 1;
            }
        }

        return $posts;
    }

    /* public function wpPostResultsHook( $posts, $query ) {

        return $posts;
    } */

    /**
     * Hook wp-gallery-shortcode to add images from post-gallery
     *
     * @param $output
     * @param $attr
     * @return array
     */
    public function wpPostGalleryHook( $output = '', $attr = [], $instance = null  ) {
        include_once 'postgallery-gallery-shortcode.php';
        return \customWpGalleryShortcode( $output, $attr, $instance );
    }

    public function getPostGalleryHook( $gallery = null, $post = null, $galleries = null ) {
        return $galleries;
    }

    /**
     * Adds post-gallery images to wordpress-media-list
     *
     * @param $children
     * @param $type
     * @param $post
     *
     * @return array
     */
    public function getAttachedMediaHook( $children = null, $type = null, $post = null ) {
        if ( empty( $post ) ) {
            return $children;

        }
        $images = PostGallery::getImages( $post->ID );
        if ( empty( $images ) ) {
            return $children;
        }

        $count = 0;
        foreach ( $images as $image ) {
            $newPost = json_decode( json_encode( $post ) ); // clone current post-object
            $newId = $post->ID . '-' . $count;
            $newPost->ID = $newId;
            $newPost->post_content = '';
            $children[$newId] = $newPost;
            $count += 1;
        }

        return $children;
    }

    /**
     * Returns postgallery-image-url if attachment-id is explodeable
     *
     * @param $file
     * @param $attachment_id
     * @return mixed
     */
    public function getAttachedFileHook( $file, $attachment_id ) {
        $split = explode( '-', $attachment_id );

        //var_dump( $attachment_id );

        if ( count( $split ) !== 2 ) {
            return $file;
        }

        $images = PostGallery::getImages( $attachment_id );

        if ( !empty( $images[$split[1]] ) ) {
            return $images[$split[1]]['url'];
        }
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueStyles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in PostGalleryLoader as all of the hooks are defined
         * in that particular class.
         *
         * The PostGalleryLoader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        $buildPath = plugin_dir_url( __FILE__ ) . '../build';


        switch ( $this->options['sliderType'] ) {
            case 'owl1':
                // owl 1
                wp_enqueue_style( 'owl.carousel', $buildPath . '/css/owl.carousel-v1.css' );
                wp_enqueue_style( 'owl.carousel.theme', $buildPath . '/css/owl.theme-v1.css' );
                wp_enqueue_style( 'owl.carousel.transitions', $buildPath . '/css/owl.transition-v1.css' );
                break;

            case 'swiper':
                wp_enqueue_style( 'swiper', $buildPath . '/css/swiper.min.css' );
                break;

            case 'owl':
                // nobreak
            default:
                // owl 2
                wp_enqueue_style( 'owl.carousel', $buildPath . '/css/owl.carousel.min.css' );
                wp_enqueue_style( 'owl.carousel.theme', $buildPath . '/css/owl.theme.default.min.css' );
                wp_enqueue_style( 'animate.css', $buildPath . '/css/animate.min.css' );
                break;
        }


        wp_enqueue_style( $this->pluginName, plugin_dir_url( __FILE__ ) . 'css/post-gallery-public.css', array(), $this->version, 'all' );
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueScripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in PostGalleryLoader as all of the hooks are defined
         * in that particular class.
         *
         * The PostGalleryLoader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        $buildPath = plugin_dir_url( __FILE__ ) . '../build';

        switch ( $this->options['sliderType'] ) {
            case 'owl1':
                wp_enqueue_script( 'owl.carousel', $buildPath . '/js/owl.carousel-v1.min.js', array( 'jquery' ) );
                break;

            case 'swiper':
                wp_enqueue_script( 'swiper', $buildPath . '/js/swiper.jquery.min.js', array( 'jquery' ) );
                break;

            case 'owl':
                // nobreak
            default:
                wp_enqueue_script( 'owl.carousel', $buildPath . '/js/owl.carousel.min.js', array( 'jquery' ) );
                break;
        }

        if ( !empty( $this->options['debugmode'] ) ) {
            wp_enqueue_script( $this->pluginName, plugin_dir_url( __FILE__ ) . 'js/post-gallery-public.js', null, $this->version, true );
            wp_enqueue_script( $this->pluginName . '-litebox', plugin_dir_url( __FILE__ ) . 'js/litebox-gallery.class.js', null, $this->version, true );

            wp_enqueue_script( 'owl-post-gallery', $buildPath . '/js/owl.postgallery.js', array( 'jquery', $this->pluginName . '-litebox' ) );

            wp_enqueue_script( 'swiper-post-gallery', $buildPath . '/js/swiper.postgallery.js', array( 'jquery', $this->pluginName . '-litebox' ) );
        } else {
            wp_enqueue_script( $this->pluginName, $buildPath . '/js/postgallery.min.js', null, $this->version, true );
        }
    }

    /**
     * Load scripts async
     *
     * @param $tag
     * @param $handle
     * @return mixed
     */
    public function addAsyncAttribute( $tag, $handle ) {
        if ( strpos( $handle, 'post-gallery' ) === false ) {
            return $tag;
        }

        return str_replace( ' src', ' async="async" src', $tag );
    }


    /**
     * Register request for thumbnails
     */
    public function postgalleryThumb() {
        if ( isset( $_REQUEST['loadThumb'] ) ) {
            Thumb::theThumb();
            exit();
        }
    }


    /**
     * Hooks has_post_thumbnail and return true if a gallery-image exists
     *
     * @param $null
     * @param $object_id
     * @param $meta_key
     * @param $single
     * @return bool|null
     */
    public function postgalleryHasPostThumbnail( $null, $object_id, $meta_key, $single ) {
        if ( $meta_key == '_thumbnail_id' ) {
            $meta_type = 'post';

            $meta_cache = wp_cache_get( $object_id, $meta_type . '_meta' );

            if ( !$meta_cache ) {
                $meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
                $meta_cache = $meta_cache[$object_id];
            }

            if ( !$meta_key ) {
                return $meta_cache;
            }

            if ( isset( $meta_cache[$meta_key] ) ) {
                if ( $single )
                    return maybe_unserialize( $meta_cache[$meta_key][0] );
                else
                    return array_map( 'maybe_unserialize', $meta_cache[$meta_key] );
            }

            if ( count( PostGallery::getImages( $object_id ) ) )
                return true;
            if ( $single )
                return '';
            else
                return array();
        }
    }

    /**
     * Hooks the_post_thumbnail() and loads first gallery-image if post-thumb is empty
     *
     * @param $html
     * @param $post_id
     * @param $post_thumbnail_id
     * @param $size
     * @param $attr
     * @return string
     */
    public function postgalleryThumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( '' == $html ) {

            $image = PostGallery::getFirstImage( $size, $post_id );

            if ( empty( $image ) || empty( $image['url'] ) ) {
                return '';
            }

            $html = '<img width="' . $image['width'] . '" height="' . $image['height'] . '" src="'
                . $image['url']
                . '" alt="" class="attachment-' . $image['size'] . $image['orientation'] . ' wp-post-image  post-image-from-postgallery" />';
        }

        return $html;
    }


    /**
     * Adds the gallery to the_content
     *
     * @param type $content
     * @return string
     */
    public function addGalleryToContent( $content ) {
        $position = get_post_meta( $GLOBALS['post']->ID, 'postgalleryPosition', true );
        $template = get_post_meta( $GLOBALS['post']->ID, 'postgalleryTemplate', true );
        if ( empty( $position ) || $position == 'global' ) {
            $position = ( !empty( $this->options['globalPosition'] ) ? $this->options['globalPosition'] : 'bottom' );
        }

        // from global
        if ( empty( $template ) || $template == 'global' ) {
            $template = ( !empty( $this->options['globalTemplate'] ) ? $this->options['globalTemplate'] : 'thumbs' );
        }

        if ( $position === 'top' ) {
            $content = $this->returnGalleryHtml( $template ) . $content;
        } else if ( $position === 'bottom' ) {
            $content = $content . $this->returnGalleryHtml( $template );
        }

        return $content;
    }

    /**
     * Return the gallery-html
     *
     * @param string $template
     * @param int $postid
     * @param array $args
     * @return string
     */
    public function returnGalleryHtml( $template = '', $postid = 0, $args = array() ) {
        $templateDirs = array(
            get_stylesheet_directory() . '/post-gallery',
            get_stylesheet_directory() . '/plugins/post-gallery',
            get_stylesheet_directory() . '/postgallery',
            POSTGALLERY_DIR . '/templates',
        );

        $images = PostGallery::getImages( $postid );

        if ( empty( $images ) ) {
            return '<!--postgallery: no images found for ' . $postid . '-->';
        }

        if ( empty( $template ) || $template == 'global' ) {
            $template = $this->options['globalTemplate'];
        }

        if ( empty( $template ) ) {
            $template = 'thumbs';
        }

        ob_start();
        echo '<!--postgallery: template: ' . $template . ';postid:' . $postid . '-->';
        foreach ( $templateDirs as $tplDir ) {
            if ( file_exists( $tplDir . '/' . $template . '.php' ) ) {
                require( $tplDir . '/' . $template . '.php' );
                break;
            }
        }
        echo '<!--end postgallery-->';

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Add html to footer
     *
     * @param string $footer
     */
    public function insertFooterHtml( $footer ) {
        $options = $this->options;
        $template = $options['liteboxTemplate'];

        $customTemplateDir = get_stylesheet_directory() . '/litebox';
        $defaultTemplateDir = POSTGALLERY_DIR . '/litebox-templates';

        if ( file_exists( $customTemplateDir . '/' . $template . '.php' ) ) {
            require( $customTemplateDir . '/' . $template . '.php' );
        } else if ( file_exists( $defaultTemplateDir . '/' . $template . '.php' ) ) {
            require( $defaultTemplateDir . '/' . $template . '.php' );
        }
    }

    /**
     * Adds shortcode for custom gallery-position
     *
     * @param type $args
     * @param type $content
     * @return string
     */
    public function postgalleryShortcode( $args, $content = '' ) {
        if ( empty( $args['template'] ) ) {
            $template = get_post_meta( $GLOBALS['post']->ID, 'postgalleryTemplate', true );
        } else {
            $template = $args['template'];
        }
        $postid = 0;
        if ( !empty( $args['post'] ) ) {
            if ( is_numeric( $args['post'] ) ) {
                $postid = $args['post'];
            } else {
                $postid = url_to_postid( $args['post'] );
            }
        }

        if ( empty( $postid ) && empty( $args['post'] ) ) {
            $postid = $GLOBALS['post']->ID;
        }

        return $this->returnGalleryHtml( $template, $postid, $args );
    }


    /**
     * Gives a url from cache
     */
    public function getThumbList() {
        if ( isset( $_REQUEST['getFullsizeThumbs'] ) || isset( $_REQUEST['getThumbList'] ) ) {

            $_SESSION['postGalleryWindowSize'] = array(
                'width' => $_REQUEST['width'],
                'height' => $_REQUEST['height'],
            );

            if ( empty( $_REQUEST['pics'] ) ) {
                die( '{}' );
            }
            $pics = ( $_REQUEST['pics'] );

            if ( !empty( $pics ) ) {
                $pics = PostGallery::getPicsResized( $pics, array(
                    'width' => $_REQUEST['width'],
                    'height' => $_REQUEST['height'],
                    'scale' => ( !isset( $_REQUEST['scale'] ) ? 1 : $_REQUEST['scale'] ),
                ) );
            }
            echo json_encode( $pics );

            exit();
        }
    }

    public function insertHeaderscript( $header ) {
        $args = $this->options;
        $sliderType = $this->options['sliderType'];
        $oldOwl = $this->options['sliderType'] == 'owl1' ? 'owlVersion: 1,' : '';
        $asBg = !empty( $this->options['asBg'] ) ? 'asBg: 1,' : '';
        $clickEvents = !empty( $this->options['clickEvents'] ) ? 'clickEvents: 1,' : '';
        $keyEvents = !empty( $this->options['keyEvents'] ) ? 'keyEvents: 1,' : '';
        $customSliderConfig = $this->options['owlConfig'];
        $owlThumbConfig = $this->options['owlThumbConfig'];
        $debug = !empty( $this->options['debugmode'] );
        $sliderConfig = '';

        // minify
        $customSliderConfig = preg_replace( "/^\s{2,}?([^,]+?),?$/m", ',', $customSliderConfig );
        $customSliderConfig = preg_replace( "/(\r?\n?)*/", '', $customSliderConfig );

        $sliderConfig .= ( !empty( $args['autoplay'] ) || in_array( 'autoplay', $args, true ) ? 'autoplay: true,' : '' );
        $sliderConfig .= ( !empty( $args['loop'] ) || in_array( 'loop', $args, true ) ? 'loop: true,' : '' );
        $sliderConfig .= ( !empty( $args['animateOut'] ) ? 'animateOut: "' . $args['animateOut'] . '",' : '' );
        $sliderConfig .= ( !empty( $args['animateIn'] ) ? 'animateIn: "' . $args['animateIn'] . '",' : '' );
        $sliderConfig .= ( !empty( $args['autoplayTimeout'] ) ? 'autoplayTimeout: ' . $args['autoplayTimeout'] . ',' : '' );
        $sliderConfig .= ( !empty( $args['items'] ) ? 'items: ' . $args['items'] . ',' : 'items: 1,' );

        $sliderConfig .= $customSliderConfig;


        $owlThumbConfig = preg_replace( "/^\s{2,}?([^,]+?),?$/m", ',', $owlThumbConfig );
        $owlThumbConfig = preg_replace( "/(\r?\n?)*/", '', $owlThumbConfig );

        // script for websiteurl
        $script = PHP_EOL . '<script>';
        $script .= 'window.pgConfig = { websiteUrl: "' . get_bloginfo( 'wpurl' ) . '",';
        $script .= 'liteboxArgs: {
            sliderType: "' . $sliderType . '",'
            . $asBg . $clickEvents . $keyEvents . $oldOwl
            . 'sliderArgs: {' . $sliderConfig . '},'
            . 'owlThumbArgs: {' . $owlThumbConfig . '}'
            . ( $debug ? ',debug: true,' : '' )
            . '}};';
        $script .= '</script>' . PHP_EOL;

        $header = $header . $script;

        echo $header;
    }

    /**
     * Returns a single option, or all options if property is null
     *
     * @param null $property
     * @return array|\array[]|\mixed[]|null
     */
    public function option( $property = null ) {
        if ( !empty( $property ) ) {
            return isset( $this->options[$property] ) ? $this->options[$property] : null;
        }

        return $this->options;
    }

    /**
     * Sets an option
     *
     * @param $property
     * @param $value
     */
    public function setOption( $property, $value ) {
        $this->options[$property] = $value;
    }

    static function getInstance() {
        // If the single instance hasn't been set, set it now.
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
