<?php namespace Inc;

use Admin\PostGalleryAdmin;
use PostGalleryWidget\Widgets\PostGalleryElementorWidget;
use Pub\PostGalleryPublic;
use Thumb\Thumb;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/RTO-Websites/post-gallery
 * @since      1.0.0
 *
 * @package    PostGallery
 * @subpackage PostGallery/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    PostGallery
 * @subpackage PostGallery/includes
 * @author     RTO GmbH
 */
class PostGallery {

    static $cachedImages = [];
    static $cachedFolders = [];

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      PostGalleryLoader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $pluginName The string used to uniquely identify this plugin.
     */
    protected $pluginName;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    protected $options;

    protected $textdomain;


    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {

        $this->pluginName = 'post-gallery';
        $this->textdomain = 'post-gallery';
        $this->version = '1.0.0';
        $this->options = PostGallery::getOptions();

        $this->loadDependencies();
        $this->setLocale();
        $this->defineAdminHooks();
        $this->definePublicHooks();

        $this->initElementor();

        add_action( 'init', [ $this, 'addPostTypeGallery' ] );

        add_action( 'cronPostGalleryDeleteCachedImages', [ $this, 'postGalleryDeleteCachedImages' ] );
    }

    /**
     * Init elementor widget
     */
    public function initElementor() {
        if ( !class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        add_action( 'elementor/editor/before_enqueue_styles', [ PostGalleryAdmin::getInstance(), 'enqueueStyles' ] );
        add_action( 'elementor/editor/before_enqueue_scripts', [ PostGalleryAdmin::getInstance(), 'enqueueScripts' ], 99999 );

        require_once( 'PostGalleryElementorControl.php' );

        add_action( 'elementor/widgets/widgets_registered', function () {
            require_once( 'PostGalleryElementorWidget.php' );

            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new PostGalleryElementorWidget() );
        } );

        add_action( 'elementor/editor/after_save', function ( $post_id, $editor_data = null ) {
            $meta = json_decode( get_post_meta( $post_id, '_elementor_data' )[0], true );

            // fetch elements
            $widgets = [];
            self::getAllWidgets( $widgets, $meta, 'postgallery' );

            foreach ( $widgets as $widget ) {
                $pgSort = self::arraySearch( $widget, 'pgsort' );
                $pgTitles = self::arraySearch( $widget, 'pgimgtitles' );
                $pgDescs = self::arraySearch( $widget, 'pgimgdescs' );
                $pgAlts = self::arraySearch( $widget, 'pgimgalts' );
                $pgOptions = self::arraySearch( $widget, 'pgimgoptions' );
                $pgPostId = self::arraySearch( $widget, 'pgimgsource' );

                if ( empty( $pgPostId ) ) {
                    $pgPostId = $post_id;
                } else {
                    $pgPostId = $pgPostId[0];
                }


                if ( !empty( $pgSort ) ) {
                    update_post_meta( $pgPostId, 'postgalleryImagesort', $pgSort[0] );
                }
                if ( !empty( $pgTitles ) ) {
                    update_post_meta( $pgPostId, 'postgalleryTitles', json_decode( $pgTitles[0], true ) );
                }
                if ( !empty( $pgDescs ) ) {
                    update_post_meta( $pgPostId, 'postgalleryDescs', json_decode( $pgDescs[0], true ) );
                }
                if ( !empty( $pgAlts ) ) {
                    update_post_meta( $pgPostId, 'postgalleryAltAttributes', json_decode( $pgAlts[0], true ) );
                }
                if ( !empty( $pgOptions ) ) {
                    update_post_meta( $pgPostId, 'postgalleryImageOptions', json_decode( $pgOptions[0], true ) );
                }
            }
        } );
    }

    public static function getAllWidgets( &$widgets = [], $meta, $widgetType = '' ) {
        // fetch elements
        foreach ( $meta as $data ) {
            if ( $data['elType'] == 'widget' && ( !empty( $widgetType ) && $widgetType == $data['widgetType'] ) ) {
                $widgets[] = $data;
            }
            if ( !empty( $data['elements'] ) ) {
                self::getAllWidgets( $widgets, $data['elements'], $widgetType );
            }
        }
    }

    /**
     * Helper function, find value in mutlidimensonal array
     *
     * @param $array
     * @param $key
     * @return array
     */
    public static function arraySearch( $array, $key ) {
        $results = [];

        if ( is_array( $array ) ) {
            if ( isset( $array[$key] ) ) {
                $results[] = $array[$key];
            }

            foreach ( $array as $subarray ) {
                $results = array_merge( $results, self::arraySearch( $subarray, $key ) );
            }
        }

        return $results;
    }


    /**
     * Cron-Task: Delete cache images with no access for a month
     */
    public function postGalleryDeleteCachedImages() {
        $uploadDir = wp_upload_dir();
        file_put_contents( $uploadDir['path'] . '/_deleteCache.txt', date( 'd.M.Y H:i:s' ) . "\r\n", FILE_APPEND );

        $cacheFolder = $uploadDir['path'] . '/cache';
        foreach ( scandir( $cacheFolder ) as $file ) {
            if ( !is_dir( $cacheFolder . '/' . $file ) ) {
                $lastAccess = fileatime( $cacheFolder . '/' . $file );

                if ( $lastAccess < strtotime( '-1 month' ) ) { // older than 1 month
                    unlink( $cacheFolder . '/' . $file );
                }
            }
        }
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - PostGalleryLoader. Orchestrates the hooks of the plugin.
     * - PostGalleryI18n. Defines internationalization functionality.
     * - PostGalleryAdmin. Defines all hooks for the admin area.
     * - PostGalleryPublic. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function loadDependencies() {

        $this->loader = new PostGalleryLoader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the PostGalleryI18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setLocale() {

        $pluginI18n = new PostGalleryI18n();
        $pluginI18n->setDomain( $this->getPostGallery() );

        $this->loader->addAction( 'plugins_loaded', $pluginI18n, 'loadPluginTextdomain' );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function defineAdminHooks() {

        $pluginAdmin = new PostGalleryAdmin( $this->getPostGallery(), $this->getVersion() );

        $this->loader->addAction( 'admin_enqueue_scripts', $pluginAdmin, 'enqueueStyles' );
        $this->loader->addAction( 'admin_enqueue_scripts', $pluginAdmin, 'enqueueScripts' );


        // add options to customizer
        add_action( 'customize_register', [ new \PostGalleryThemeCustomizer(), 'actionCustomizeRegister' ] );

        // add menu page to link to customizer
        add_action( 'admin_menu', function () {
            $returnUrl = urlencode( $_SERVER['REQUEST_URI'] );
            \add_menu_page(
                'PostGallery',
                'PostGallery',
                'edit_theme_options',
                'customize.php?return=' . $returnUrl . '&autofocus[panel]=postgallery-panel',
                null,
                'dashicons-format-gallery'
            );
        } );


        add_action( 'add_meta_boxes', [ $pluginAdmin, 'registerPostSettings' ] );
        add_action( 'save_post', [ $pluginAdmin, 'savePostMeta' ], 10, 2 );

        // Register ajax
        add_action( 'wp_ajax_postgalleryUpload', [ $pluginAdmin, 'ajaxUpload' ] );
        add_action( 'wp_ajax_postgalleryDeleteimage', [ $pluginAdmin, 'ajaxDelete' ] );
        add_action( 'wp_ajax_postgalleryGetImageUpload', [ $pluginAdmin, 'ajaxGetImageUpload' ] );
        add_action( 'wp_ajax_postgalleryNewGallery', [ $pluginAdmin, 'ajaxCreateGallery' ] );
        add_action( 'wp_ajax_postgalleryGetGroupedMedia', [ $pluginAdmin, 'getGroupedMedia' ] );

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function definePublicHooks() {

        $pluginPublic = new PostGalleryPublic( $this->getPostGallery(), $this->getVersion() );

        $this->loader->addAction( 'wp_enqueue_scripts', $pluginPublic, 'enqueueStyles' );
        $this->loader->addAction( 'wp_enqueue_scripts', $pluginPublic, 'enqueueScripts' );


        add_filter( 'the_content', [ $pluginPublic, 'addGalleryToContent' ] );
        add_shortcode( 'postgallery', [ $pluginPublic, 'postgalleryShortcode' ] );
        add_action( 'plugins_loaded', [ $pluginPublic, 'postgalleryThumb' ] );
        add_action( 'plugins_loaded', [ $pluginPublic, 'getThumbList' ] );

        // Embed headerscript
        add_action( 'wp_head', [ $pluginPublic, 'insertHeaderscript' ] );

        // Embed footer-html
        add_action( 'wp_footer', [ $pluginPublic, 'insertFooterHtml' ] );

        add_filter( 'post_thumbnail_html', [ $pluginPublic, 'postgalleryThumbnail' ], 10, 5 );
        add_filter( 'get_post_metadata', [ $pluginPublic, 'postgalleryHasPostThumbnail' ], 10, 5 );
        //add_filter( 'script_loader_tag', [ $this, 'addAsyncAttribute' ], 10, 2 );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function getPostGallery() {
        return $this->pluginName;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    PostGalleryLoader    Orchestrates the hooks of the plugin.
     */
    public function getLoader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function getVersion() {
        return $this->version;
    }


    /**
     * Sorting an image-array
     *
     * @param {array} $images
     * @return {array}
     */
    public static function sortImages( $images, $postid ) {
        // get post in default language
        $orgPost = PostGallery::getOrgPost( $postid );
        if ( !empty( $orgPost ) ) {
            $post = $orgPost;
            $postid = $orgPost->ID;
        }
        $sort = get_post_meta( $postid, 'postgalleryImagesort', true );

        // sort by elementor-widget
        if ( class_exists( '\Elementor\Plugin' ) && !empty( $GLOBALS['elementorWidgetSettings'] ) ) {
            if ( !empty( $GLOBALS['elementorWidgetSettings']['pgsort'] ) ) {
                $sort = $GLOBALS['elementorWidgetSettings']['pgsort'];
            }
        }

        $sortimages = [];

        if ( !empty( $sort ) ) {
            $count = 0;
            $sort_array = explode( ',', $sort );
            foreach ( $sort_array as $key ) {
                if ( !empty( $images[$key] ) ) {
                    $sortimages[$key] = $images[$key];
                    unset( $images[$key] );
                }
                $count += 1;
            }
        }
        $sortimages = array_merge( $sortimages, $images );

        return $sortimages;
    }

    /**
     * Return an image-array
     *
     * @param int $postid
     * @return array
     */
    public static function getImages( $postid = null ) {
        if ( empty( $postid ) && empty( $GLOBALS['post'] ) ) {
            return;
        }
        if ( empty( $postid ) ) {
            $postid = $GLOBALS['post']->ID;
            $post = $GLOBALS['post'];
        }

        // check if image list is in cache
        if ( isset( PostGallery::$cachedImages[$postid] ) ) {
            return PostGallery::$cachedImages[$postid];
        }

        if ( empty( $post ) ) {
            $post = get_post( $postid );
        }
        // get post in default language
        $orgPost = PostGallery::getOrgPost( $postid );
        if ( !empty( $orgPost ) ) {
            $post = $orgPost;
            $postid = $orgPost->ID;
            if ( isset( PostGallery::$cachedImages[$postid] ) ) {
                // check if image list is in cache
                return PostGallery::$cachedImages[$postid];
            }
        }

        if ( empty( $post ) || $post->post_type === 'attachment' ) {
            return;
        }

        $uploads = wp_upload_dir();

        //$imageDir = strtolower(str_replace('http://', '', esc_url($post->post_title)));
        $imageDir = PostGallery::getImageDir( $post );
        $uploadDir = $uploads['basedir'] . '/gallery/' . $imageDir;
        $uploadFullUrl = $uploads['baseurl'] . '/gallery/' . $imageDir;
        $uploadUrl = str_replace( get_bloginfo( 'wpurl' ), '', $uploadFullUrl );
        $images = [];

        if ( file_exists( $uploadDir ) && is_dir( $uploadDir ) ) {
            $dir = scandir( $uploadDir );

            foreach ( $dir as $file ) {
                if ( !is_dir( $uploadDir . '/' . $file ) ) {
                    $fullUrl = $uploadFullUrl . '/' . $file;
                    $path = $uploadUrl . '/' . $file;

                    if ( self::urlIsThumbnail( $fullUrl ) ) {
                        continue;
                    }

                    $alt = '';
                    $imageTitle = '';
                    $imageOptions = '';
                    $imageDesc = '';
                    $attachmentId = self::checkForAttachmentData( $fullUrl, $path, $postid );
                    if ( !empty( $attachmentId ) ) {
                        $attachment = get_post( $attachmentId );
                        $alt = get_post_meta( $attachmentId, '_wp_attachment_image_alt', true );
                        $imageTitle = $attachment->post_title;
                        $imageOptions = get_post_meta( $attachmentId, '_postgallery-image-options', true );
                        $imageDesc = $attachment->post_content;
                    }

                    $images[$file] = [
                        'filename' => $file,
                        'path' => $path,
                        'url' => $fullUrl,
                        'thumbURL' => get_bloginfo( 'wpurl' ) . '/?loadThumb&amp;path=' . $uploadUrl . '/' . $file,
                        'title' => $imageTitle,
                        'desc' => $imageDesc,
                        'alt' => $alt,
                        'post_id' => $postid,
                        'post_title' => get_the_title( $postid ),
                        'imageOptions' => $imageOptions,
                        'attachmentId' => $attachmentId,
                    ];
                }
            }
        }

        $images = PostGallery::sortImages( $images, $postid );
        PostGallery::$cachedImages[$postid] = $images;
        return $images;
    }

    /**
     * Creates an attachment-post if not exists
     *
     * @param $fullUrl
     * @param $path
     * @param $parentId
     * @return int|null|string|\WP_Error
     */
    public static function checkForAttachmentData( $fullUrl, $path, $parentId ) {
        $attachmentId = self::getPostIdFromGuid( $fullUrl );

        if ( !empty( $attachmentId ) ) {
            return $attachmentId;
        }

        // no attachment exists, create new

        // Check the type of file. We'll use this as the 'post_mime_type'.
        $filetype = wp_check_filetype( basename( $path ), null );


        // get old data
        $titles = get_post_meta( $parentId, 'postgalleryTitles', true );
        $descs = get_post_meta( $parentId, 'postgalleryDescs', true );
        $alts = get_post_meta( $parentId, 'postgalleryAltAttributes', true );
        $imageOptions = get_post_meta( $parentId, 'postgalleryImageOptions', true );

        $pathSplit = explode( '/', $path );
        $filename = array_pop( $pathSplit );

        if ( !is_array( $titles ) ) {
            $titles = json_decode( json_encode( $titles ), true );
        }
        if ( !is_array( $descs ) ) {
            $descs = json_decode( json_encode( $descs ), true );
        }
        if ( !is_array( $alts ) ) {
            $alts = json_decode( json_encode( $alts ), true );
        }
        if ( !is_array( $imageOptions ) ) {
            $imageOptions = json_decode( json_encode( $imageOptions ), true );
        }

        $imageTitle = !empty( $titles[$filename] )
            ? $titles[$filename]
            : preg_replace( '/\.[^.]+$/', '', basename( $path ) );

        $imageDesc = !empty( $descs[$filename] ) ? $descs[$filename] : '';

        // Prepare an array of post data for the attachment.
        $attachment = array(
            'guid' => $fullUrl,
            'post_mime_type' => $filetype['type'],
            'post_title' => $imageTitle,
            'post_content' => $imageDesc,
            'post_status' => 'inherit',
        );

        // Insert the attachment.
        $attachmentId = wp_insert_attachment( $attachment, $path, $parentId );

        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Generate the metadata for the attachment, and update the database record.
        $attachData = wp_generate_attachment_metadata( $attachmentId, $path );
        wp_update_attachment_metadata( $attachmentId, $attachData );

        if ( !empty( $alts[$filename] ) ) {
            update_post_meta( $attachmentId, '_wp_attachment_image_alt', $alts[$filename] );
        }
        if ( !empty( $imageOptions[$filename] ) ) {
            update_post_meta( $attachmentId, '_postgallery-image-options', $imageOptions[$filename] );
        }

        return $attachmentId;
    }

    /**
     * Return an image-array with resized images
     *
     * @param int $postid
     * @param array $args
     * @return array
     */
    public static function getImagesResized( $postid = 0, $args = [] ) {
        $images = PostGallery::getImages( $postid );

        return PostGallery::getPicsResized( $images, $args );
    }

    /**
     * Returns a comma seperated list with images
     *
     * @param {int} $postid
     * @param {array} $args (singlequotes, quotes)
     * @return {string}
     */
    public static function getImageString( $postid = null, $args = [] ) {
        if ( empty( $postid ) ) {
            global $postid;
        }
        $images = PostGallery::getImages( $postid );
        if ( empty( $images ) ) {
            return '';
        }
        $imageList = [];
        foreach ( $images as $image ) {
            $imageList[] = $image['path'];
        }
        $imageString = '';
        if ( !empty( $args['quotes'] ) ) {
            $imageString = '"' . implode( '","', $imageList ) . '"';
        } elseif ( !empty( $args['singlequotes'] ) ) {
            $imageString = "'" . implode( "','", $imageList ) . "'";
        } else {
            $imageString = implode( ',', $imageList );
        }

        return $imageString;
    }

    /**
     * Returns a post in default language
     *
     * @param {int} $post_id
     * @return boolean|object
     */
    public static function getOrgPost( $currentPostId ) {
        if ( class_exists( 'SitePress' ) ) {
            global $locale, $sitepress;

            $orgPostId = icl_object_id( $currentPostId, 'any', true, $sitepress->get_default_language() );
            //icl_ob
            if ( $currentPostId !== $orgPostId ) {
                $mainLangPost = get_post( $orgPostId );
                return $mainLangPost;
            }
        }
        return false;
    }

    /**
     * Get path to thumb.php
     *
     * @param string $filepath
     * @param array $args
     * @return string
     */
    static function getThumbUrl( $filepath, $args = [] ) {
        $thumb = PostGallery::getThumb( $filepath, $args );
        $thumbUrl = ( !empty( $thumb['url'] ) ? $thumb['url'] : get_bloginfo( 'wpurl' ) . '/' . $thumb['path'] );
        $thumbUrl = str_replace( '//wp-content', '/wp-content', $thumbUrl );

        return $thumbUrl;
    }

    /**
     * Get thumb (wrapper for Thumb->getThumb()
     *
     * @param string $filepath
     * @param array $args
     * @return array
     */
    static function getThumb( $filepath, $args = [] ) {
        if ( empty( $args['width'] ) ) {
            $args['width'] = 1000;
        }
        if ( empty( $args['height'] ) ) {
            $args['height'] = 1000;
        }
        if ( !isset( $args['scale'] ) ) {
            $args['scale'] = 1;
        }
        $args['path'] = str_replace( get_bloginfo( 'wpurl' ), '', $filepath );

        $thumbInstance = Thumb::getInstance();
        $thumb = $thumbInstance->getThumb( $args );

        return $thumb;
    }

    /**
     * Returns the foldername for the gallery
     *
     * @param object $wpost
     * @return string
     */
    static function getImageDir( $wpost ) {
        $postName = $wpost->post_title;
        $postId = $wpost->ID;

        $blockedPostTypes = [
            'revision',
            'attachment',
            'mgmlp_media_folder',
        ];

        if ( in_array( $wpost->post_type, $blockedPostTypes, true ) ) {
            return;
        }

        if ( isset( PostGallery::$cachedFolders[$postId] ) ) {
            return PostGallery::$cachedFolders[$postId];
        }

        $search = [ 'ä', 'ü', 'ö', 'Ä', 'Ü', 'Ö', '°', '+', '&amp;', '&', '€', 'ß', '–' ];
        $replace = [ 'ae', 'ue', 'oe', 'ae', 'ue', 'oe', '', '-', '-', '-', 'E', 'ss', '-' ];

        $postName = str_replace( $search, $replace, $postName );

        $uploads = wp_upload_dir();
        $oldImageDir = strtolower( str_replace( 'http://', '', esc_url( $postName ) ) );
        $newImageDir = strtolower(
            sanitize_file_name( str_replace( '&amp;', '-', $postName )
            )
        );

        $baseDir = $uploads['basedir'] . '/gallery/';

        if ( empty( $newImageDir ) ) {
            return false;
        }

        // for very old postgallery who used wrong dir
        PostGallery::renameDir( $baseDir . $oldImageDir, $baseDir . $newImageDir );

        // for old postgallery who dont uses post-id in folder
        $oldImageDir = $newImageDir;
        $newImageDir = $newImageDir . '_' . $postId;
        PostGallery::renameDir( $baseDir . $oldImageDir, $baseDir . $newImageDir );

        PostGallery::$cachedFolders[$postId] = $newImageDir;

        return $newImageDir;
    }

    static function renameDir( $oldDir, $newDir ) {
        if ( $newDir == $oldDir ) {
            return;
        }
        if ( is_dir( $oldDir ) && !is_dir( $newDir ) ) {
            //rename($old_dir, $new_dir);
            if ( file_exists( $oldDir ) ) {
                $files = scandir( $oldDir );
                @mkdir( $newDir );
                @chmod( $newDir, octdec( '0777' ) );

                foreach ( $files as $file ) {
                    if ( !is_dir( $oldDir . '/' . $file ) ) {
                        copy( $oldDir . '/' . $file, $newDir . '/' . $file );
                        unlink( $oldDir . '/' . $file );
                    }
                }
                @rmdir( $oldDir );

                return $newDir;
            }
        }

        // fail
        return $oldDir;
    }


    /**
     * Generate thumb-path for an array of pics
     *
     * @param array $pics
     * @param array $args
     * @return array
     */
    static function getPicsResized( $pics, $args ) {
        if ( !is_array( $pics ) ) {
            return $pics;
        }
        $newPics = [];
        foreach ( $pics as $pic ) {
            // create resized image
            if ( is_array( $pic ) ) {
                if ( !empty( $pic['url'] ) ) {
                    $newPic = PostGallery::getThumb( $pic['url'], $args );
                } else if ( !empty( $pic['path'] ) ) {
                    $newPic = PostGallery::getThumb( $pic['path'], $args );
                }
            } else {
                $newPic = PostGallery::getThumb( $pic, $args );
            }
            if ( !empty( $newPic ) && is_array( $pic ) ) {
                // add info (title and description)
                $newPics[] = array_merge( $pic, $newPic );
            } else if ( !empty( $newPic ) ) {
                $newPics[] = $newPic;
            } else {
                $newPics[] = $pic;
            }
        }

        return $newPics;
    }

    /**
     * Check if post has a thumb or a postgallery-image
     *
     * @param int $postid
     * @return int
     */
    static function hasPostThumbnail( $postid = 0 ) {
        if ( empty( $postid ) && empty( $GLOBALS['post'] ) ) {
            return;
        }
        if ( empty( $postid ) ) {
            $postid = $GLOBALS['post']->ID;
        }

        if ( empty( $postid ) ) {
            return false;
        }

        if ( has_post_thumbnail( $postid ) || is_admin() ) {
            return has_post_thumbnail( $postid );
        } else {
            return count( PostGallery::getImages( $postid ) );
        }
    }

    /**
     * Gets first image (for example to use as post_thumbnail)
     *
     * @param string $size
     * @param null|int $post_id
     * @return bool|array(width, height, size, url, orientation)
     * @throws \ImagickException
     */
    static function getFirstImage( $size = 'post-thumbnail', $post_id = null ) {
        if ( empty( $post_id ) ) {
            $post_id = $GLOBALS['post']->ID;
        }
        // get id from main-language post
        if ( class_exists( 'SitePress' ) ) {
            global $sitepress;

            $post_id = icl_object_id( $post_id, 'any', true, $sitepress->get_default_language() );
        }

        $postGalleryImages = PostGallery::getImages( $post_id );
        if ( !count( $postGalleryImages ) ) {
            return false;
        }

        $firstThumb = array_shift( $postGalleryImages );

        if ( empty( $size ) ) {
            $size = 'post-thumbnail';
        }

        // get width of thumbnail
        $width = intval( get_option( "{$size}_size_w" ) );
        $height = intval( get_option( "{$size}_size_h" ) );
        $crop = intval( get_option( "{$size}_crop" ) );

        if ( empty( $width ) && empty( $height ) ) {
            global $_wp_additional_image_sizes;
            if ( !empty( $_wp_additional_image_sizes ) &&
                !empty( $_wp_additional_image_sizes[$size] )
            ) {
                $width = $_wp_additional_image_sizes[$size]['width'];
                $height = $_wp_additional_image_sizes[$size]['height'];
            }
        }

        if ( empty( $width ) ) {
            $width = '1920';
        }
        if ( empty( $height ) ) {
            $height = '1080';
        }

        $path = $firstThumb['path'];
        $path = explode( '/wp-content/', $path );
        $path = '/wp-content/' . array_pop( $path );

        if ( $size !== 'full' ) {
            $thumbInstance = new Thumb();
            $thumb = $thumbInstance->getThumb( [
                'path' => $path,
                'width' => $width,
                'height' => $height,
                'scale' => 2,
            ] );
        } else {
            $filesize = getimagesize( ABSPATH . $path );
            $thumb = [
                'width' => $filesize[0],
                'height' => $filesize[1],
                'url' => get_bloginfo( 'wpurl' ) . $path,
            ];
        }

        $width = $height = 'auto';

        $orientation = ' wide';

        if ( $thumb['width'] >= $thumb['height'] ) {
            $width = $thumb['width'];
        } else {
            $height = $thumb['height'];
            $orientation = ' upright';
        }

        return [
            'width' => $width,
            'height' => $height,
            'orientation' => $orientation,
            'thumb' => $thumb,
            'url' => $thumb['url'],
            'orgPath' => $path,
            'size' => $size,
        ];
    }

    /**
     * Adds post-type gallery
     */
    public function addPostTypeGallery() {
        register_post_type( 'gallery', [
            'labels' => [
                'name' => __( 'Galleries', $this->textdomain ),
                'singular_name' => __( 'Gallery', $this->textdomain ),
            ],
            'taxonomies' => [ 'category' ],
            'menu_icon' => 'dashicons-format-gallery',
            'public' => true,
            'has_archive' => true,
            'show_in_nav_menus' => true,
            'show_ui' => true,
            'capability_type' => 'post',
            'hierarchical' => true,
            'supports' => [
                'title',
                'author',
                'editor',
                'thumbnail',
                'trackbacks',
                'custom-fields',
                'revisions',
            ],
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'excerpt' => true,
        ] );
    }

    public static function getOptions() {
        return [
            'debugmode' => get_theme_mod( 'postgallery_postgalleryDebugmode', false ),
            'sliderType' => get_theme_mod( 'postgallery_sliderType', 'owl' ),
            'globalPosition' => get_theme_mod( 'postgallery_globalPosition', 'bottom' ),

            'globalTemplate' => get_theme_mod( 'postgallery_globalTemplate' ),
            'thumbWidth' => get_theme_mod( 'postgallery_thumbWidth', 150 ),
            'thumbHeight' => get_theme_mod( 'postgallery_thumbHeight', 150 ),
            'thumbScale' => get_theme_mod( 'postgallery_thumbScale', '1' ),
            'sliderOwlConfig' => get_theme_mod( 'postgallery_thumbScale', "items: 1,\nnav: 1,\ndots: 1,\nloop: 1," ),
            'stretchImages' => get_theme_mod( 'postgallery_stretchImages', false ),
            'hookWpGallery' => get_theme_mod( 'postgallery_hookWpGallery', false ),

            'enableLitebox' => get_theme_mod( 'postgallery_enableLitebox', true ),
            'liteboxTemplate' => get_theme_mod( 'postgallery_liteboxTemplate', 'default' ),
            'owlTheme' => get_theme_mod( 'postgallery_owlTheme', 'default' ),
            'clickEvents' => get_theme_mod( 'postgallery_clickEvents', true ),
            'keyEvents' => get_theme_mod( 'postgallery_keyEvents', true ),
            'asBg' => get_theme_mod( 'postgallery_asBg', false ),
            'owlConfig' => get_theme_mod( 'postgallery_owlConfig', 'items: 1' ),
            'owlThumbConfig' => get_theme_mod( 'postgallery_owlThumbConfig', '' ),

            'autoplay' => get_theme_mod( 'postgallery_autoplay', '' ),
            'loop' => get_theme_mod( 'postgallery_loop', '' ),
            'items' => get_theme_mod( 'postgallery_items', '1' ),
            'animateOut' => get_theme_mod( 'postgallery_animateOut', '' ),
            'animateIn' => get_theme_mod( 'postgallery_animateIn', '' ),
            'autoplayTimeout' => get_theme_mod( 'postgallery_autoplayTimeout', '' ),
        ];
    }


    /**
     * Returns post-id for a guid
     *
     * @param $guid
     * @return null|string
     */
    public static function getPostIdFromGuid( $guid ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid ) );
    }

    public static function urlIsThumbnail( $attachmentUrl = '' ) {

        global $wpdb;
        //$attachmentId = false;

        // If there is no url, return.
        if ( '' == $attachmentUrl )
            return true;

        // Get the upload directory paths
        $upload_dir_paths = wp_upload_dir();

        // Make sure the upload path base directory exists in the attachment URL, to verify that we're working with a media library image
        if ( false !== strpos( $attachmentUrl, $upload_dir_paths['baseurl'] ) ) {

            // If this is the URL of an auto-generated thumbnail, get the URL of the original image
            $attachmentUrlNew = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachmentUrl );
            if ( strcmp( $attachmentUrlNew, $attachmentUrl ) === 0 ) {
                return false;
            }

            // Remove the upload path base directory from the attachment URL
            //$attachmentUrl = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachmentUrl );

            // Finally, run a custom database query to get the attachment ID from the modified attachment URL
            //$attachmentId = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'", $attachmentUrl ) );

        }

        return true;
    }
}
