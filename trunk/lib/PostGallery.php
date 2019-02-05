<?php namespace Lib;

use Admin\PostGalleryAdmin;
use Admin\PostGalleryThemeCustomizer;
use Elementor\Core\Files\CSS\Post;
use Pub\PostGalleryPublic;

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
    use PostGalleryLegacy;

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

    protected $images;

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

        $this->pluginName = 'postgallery';
        $this->version = '1.0.0';
        $this->options = PostGallery::getOptions();

        $this->loadDependencies();
        $this->setLocale();
        $this->defineAdminHooks();
        $this->definePublicHooks();
        $this->defineElementorHooks();
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
        $this->images = new PostGalleryImages();
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
        $this->loader->addAction( 'customize_register', new PostGalleryThemeCustomizer(), 'actionCustomizeRegister' );

        // add menu page to link to customizer
        $this->loader->addAction( 'admin_menu', $pluginAdmin, 'addAdminPage' );


        $this->loader->addAction( 'add_meta_boxes', $pluginAdmin, 'registerPostSettings' );
        $this->loader->addAction( 'save_post', $pluginAdmin, 'savePostMeta', 10, 2 );

        // Register ajax
        $this->loader->addAction( 'wp_ajax_postgalleryAjaxUpload', $pluginAdmin, 'ajaxUpload' );
        $this->loader->addAction( 'wp_ajax_postgalleryDeleteimage', $pluginAdmin, 'ajaxDelete' );
        $this->loader->addAction( 'wp_ajax_postgalleryRenameimage', $pluginAdmin, 'ajaxRename' );
        $this->loader->addAction( 'wp_ajax_postgalleryGetImageUpload', $pluginAdmin, 'ajaxGetImageUpload' );
        $this->loader->addAction( 'wp_ajax_postgalleryNewGallery', $pluginAdmin, 'ajaxCreateGallery' );
        $this->loader->addAction( 'wp_ajax_postgalleryGetGroupedMedia', $pluginAdmin, 'getGroupedMedia' );

        $this->loader->addFilter( 'sanitize_file_name', $pluginAdmin, 'sanitizeFilename' );

        $this->loader->addFilter( 'attachment_fields_to_edit', $pluginAdmin, 'addMediaFields', 11, 2 );
        $this->loader->addFilter( 'attachment_fields_to_save', $pluginAdmin, 'saveMediaFields', 10, 2 );
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


        $this->loader->addFilter( 'the_content', $pluginPublic, 'addGalleryToContent' );
        add_shortcode( 'postgallery', [ $pluginPublic, 'postgalleryShortcode' ] );
        $this->loader->addAction( 'plugins_loaded', $pluginPublic, 'postgalleryThumb' );
        $this->loader->addAction( 'plugins_loaded', $pluginPublic, 'getThumbList' );

        // Embed headerscript
        $this->loader->addAction( 'wp_head', $pluginPublic, 'insertHeaderscript' );
        // Embed headerstyle
        $this->loader->addAction( 'wp_head', $pluginPublic, 'insertHeaderstyle' );

        // Embed footer-html
        $this->loader->addAction( 'wp_footer', $pluginPublic, 'insertFooterHtml' );

        $this->loader->addFilter( 'post_thumbnail_html', $pluginPublic, 'postgalleryThumbnail', 10, 5 );
        $this->loader->addFilter( 'get_post_metadata', $pluginPublic, 'postgalleryHasPostThumbnail', 10, 5 );

        $this->loader->addAction( 'init', $this, 'addPostTypeGallery' );
        $this->loader->addAction( 'cronPostGalleryDeleteCachedImages', $this, 'postGalleryDeleteCachedImages' );
    }

    /**
     * Init elementor widget
     */
    public function defineElementorHooks() {
        if ( !class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        $pluginAdmin = PostGalleryAdmin::getInstance();

        $this->loader->addAction( 'elementor/editor/before_enqueue_styles', $pluginAdmin, 'enqueueStyles' );
        $this->loader->addAction( 'elementor/editor/before_enqueue_scripts', $pluginAdmin, 'enqueueScripts', 99999 );

        $this->loader->addAction( 'elementor/widgets/widgets_registered', $pluginAdmin, 'registerElementorWidget' );
        $this->loader->addAction( 'elementor/editor/after_save', $pluginAdmin, 'elementorAfterSave' );
        $this->loader->addAction( 'elementor/controls/controls_registered', $pluginAdmin, 'registerElementorControls' );
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
     *
     *
     * @param array $widgets
     * @param $meta
     * @param string $widgetType
     */
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
        file_put_contents( $uploadDir['basedir'] . '/_deleteCache.txt', date( 'd.M.Y H:i:s' ) . "\r\n", FILE_APPEND );

        $cacheFolder = $uploadDir['basedir'] . '/cache';
        if ( file_exists( $cacheFolder ) ) {
            foreach ( scandir( $cacheFolder ) as $file ) {
                if ( !is_dir( $cacheFolder . '/' . $file ) ) {
                    $lastAccess = fileatime( $cacheFolder . '/' . $file );

                    if ( $lastAccess < strtotime( '-1 month' ) ) { // older than 1 month
                        unlink( $cacheFolder . '/' . $file );
                    }
                }
            }
        }
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
     * Returns the foldername for the gallery
     *
     * @param object $wpost
     * @return string
     */
    public static function getImageDir( $wpost ) {
        $postName = empty( $wpost->post_title ) ? 'undefined' : $wpost->post_title;
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

    /**
     * Rename a folder
     *
     * @param $oldDir
     * @param $newDir
     */
    public static function renameDir( $oldDir, $newDir ) {
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
     * Adds post-type gallery
     */
    public function addPostTypeGallery() {
        register_post_type( 'gallery', [
            'labels' => [
                'name' => __( 'Galleries', 'postgallery' ),
                'singular_name' => __( 'Gallery', 'postgallery' ),
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
            'globalPosition' => get_theme_mod( 'postgallery_globalPosition', defined( 'ELEMENTOR_VERSION' ) ? 'custom' : 'bottom' ),

            'maxImageWidth' => get_theme_mod( 'postgallery_maxImageWidth', 2560 ),
            'maxImageHeight' => get_theme_mod( 'postgallery_maxImageHeight', 2560 ),

            'disableScripts' => get_theme_mod( 'postgallery_disableScripts', false ),
            'disableGroupedMedia' => get_theme_mod( 'postgallery_disableGroupedMedia', false ),

            'globalTemplate' => get_theme_mod( 'postgallery_globalTemplate' ),
            'columns' => get_theme_mod( 'postgallery_columns', 'auto' ),
            'noGrid' => get_theme_mod( 'postgallery_noGrid', 0 ),
            'thumbWidth' => get_theme_mod( 'postgallery_thumbWidth', 500 ),
            'thumbHeight' => get_theme_mod( 'postgallery_thumbHeight', 500 ),
            'thumbScale' => get_theme_mod( 'postgallery_thumbScale', '1' ),
            'useSrcset' => get_theme_mod( 'postgallery_useSrcset', false ),
            'imageViewportWidth' => get_theme_mod( 'postgallery_imageViewportWidth', 800 ),
            'columnGap' => get_theme_mod( 'postgallery_columnGap', 0 ),
            'rowGap' => get_theme_mod( 'postgallery_rowGap', 0 ),

            'sliderOwlConfig' => get_theme_mod( 'postgallery_thumbScale', "items: 1,\nnav: 1,\ndots: 1,\nloop: 1," ),
            'stretchImages' => get_theme_mod( 'postgallery_stretchImages', false ),

            'enableLitebox' => get_theme_mod( 'postgallery_enableLitebox', true ),
            'liteboxTemplate' => get_theme_mod( 'postgallery_liteboxTemplate', 'default' ),
            'owlTheme' => get_theme_mod( 'postgallery_owlTheme', 'default' ),
            'clickEvents' => get_theme_mod( 'postgallery_clickEvents', true ),
            'keyEvents' => get_theme_mod( 'postgallery_keyEvents', true ),
            'arrows' => get_theme_mod( 'postgallery_arrows', false ),
            'asBg' => get_theme_mod( 'postgallery_asBg', false ),
            'owlConfig' => get_theme_mod( 'postgallery_owlConfig', 'items: 1' ),
            'owlThumbConfig' => get_theme_mod( 'postgallery_owlThumbConfig', '' ),

            'autoplay' => get_theme_mod( 'postgallery_autoplay', '' ),
            'loop' => get_theme_mod( 'postgallery_loop', '' ),
            'items' => get_theme_mod( 'postgallery_items', '1' ),
            'animateOut' => get_theme_mod( 'postgallery_animateOut', '' ),
            'animateIn' => get_theme_mod( 'postgallery_animateIn', '' ),
            'autoplayTimeout' => get_theme_mod( 'postgallery_autoplayTimeout', '' ),

            'mainColor' => get_theme_mod( 'postgallery_mainColor', '#fff' ),
            'secondColor' => get_theme_mod( 'postgallery_secondColor', '#333' ),

            'masonry' => get_theme_mod( 'postgallery_masonry', false ),
            'equalHeight' => get_theme_mod( 'postgallery_equalHeight', false ),
            'itemRatio' => get_theme_mod( 'postgallery_itemRatio', 0.66 ),
            'imageAnimation' => get_theme_mod( 'postgallery_imageAnimation', false ),
            'imageAnimationDuration' => get_theme_mod( 'postgallery_imageAnimationDuration', 300 ),
            'imageAnimationTimeBetween' => get_theme_mod( 'postgallery_imageAnimationTimeBetween', 200 ),
            'imageAnimationCss' => get_theme_mod( 'postgallery_imageAnimationCss', '' ),
            'imageAnimationCssAnimated' => get_theme_mod( 'postgallery_imageAnimationCssAnimated', '' ),
        ];
    }

    /**
     * Return an single option value
     *
     * @param $key
     * @return mixed|null
     */
    public function option( $key ) {
        $options = array_change_key_case( (array)$this->options, CASE_LOWER );
        $key = strtolower( $key );
        return isset( $options[$key] ) ? $options[$key] : null;
    }

    /**
     * @DEPRECATED
     *
     * use getAttachmentIdByUrl instead
     *
     * Returns post-id for a guid
     *
     * @param $guid
     * @return null|string
     */
    public static function getPostIdFromGuid( $guid ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid=%s", $guid ) );
    }
}
