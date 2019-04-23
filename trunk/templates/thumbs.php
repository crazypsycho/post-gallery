<?php
/**
 * Template Page for the thumbs
 *
 * Follow variables are useable:
 *        $images
 *            -> filename, path, thumbURL
 */
?>
    <figure
            class="gallery pg-theme-thumbs pg-theme-list <?php echo $this->option( 'containerClass' ); ?>">
        <?php foreach ( $images as $image ): ?>
            <?php
            $thumbUrl = \Lib\PostGalleryImage::getThumbUrl( $image['path'],
                [
                    'width' => $this->option( 'thumbWidth' ),
                    'height' => $this->option( 'thumbHeight' ),
                    'scale' => $this->option( 'thumbScale' ),
                ] );
            ?>
            <div class="item" <?php echo $image['imageOptionsParsed']; ?>>
                <a href="<?php echo $image['url'] ?>">
                    <?php if ( $this->option( 'useSrcset' ) ): ?>
                        <img class="post-gallery_thumb"
                                src="<?php echo $image['url'] ?>"
                                data-title="<?php echo $image['title'] ?>"
                                data-desc="<?php echo $image['desc'] ?>"
                                alt="<?php echo $image['alt'] ?>"
                                srcset="<?php echo $image['srcset']; ?>"
                                sizes="<?php echo $srcsetSizes; ?>"
                        />
                    <?php else: ?>
                        <img class="post-gallery_thumb"
                                src="<?php echo $thumbUrl ?>"
                                data-title="<?php echo $image['title'] ?>"
                                data-desc="<?php echo $image['desc'] ?>"
                                alt="<?php echo $image['alt'] ?>"
                                data-scale="<?php echo $this->option( 'thumbScale' ); ?>"/>
                    <?php endif; ?>

                </a>
                <div class="bg-image" style="background-image: url('<?php echo $thumbUrl; ?>');"></div>
            </div>
        <?php endforeach; ?>
    </figure>
<?php if ( $this->option( 'imageAnimation' ) ): ?>
    <script>
      jQuery(function () {
        window.registerPgImageAnimation('<?php echo $id; ?>', <?php echo $this->option( 'imageAnimationTimeBetween' ); ?>);
      });
    </script>
<?php endif; ?>


<?php if ( $this->option( 'connectedWith' ) ): ?>
    <script>
      jQuery('#<?php echo $id; ?>.postgallery-wrapper a').each(function (index, element) {
        element = jQuery(element);
        element.addClass('no-litebox');
        element.on('click', function (e) {
          e.preventDefault();
          document.querySelector('.elementor-element-<?php echo $this->option( 'connectedWith' ); ?> .elementor-main-swiper').swiper.slideTo(element.closest('.item').index() + 1);
        });
      });
    </script>
<?php endif; ?>