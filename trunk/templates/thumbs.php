<?php
/**
 * Template Page for the thumbs
 *
 * Follow variables are useable:
 *        $images
 *            -> filename, path, thumbURL
 */
?>
    <figure class="gallery pg-theme-thumbs pg-theme-list gallery-columns-<?php echo $this->option( 'columns' ); ?>"
        <?php echo !empty( $this->option( 'masonry' ) ) ? ' data-pgmasonry="' . $this->option( 'masonry' ) . '" ' : ''; ?>>
        <?php foreach ( $images as $image ) { ?>
            <?php
            $thumbUrl = \Inc\PostGallery::getThumbUrl( $image['path'],
                [
                    'width' => $this->option( 'thumbWidth' ),
                    'height' => $this->option( 'thumbHeight' ),
                    'scale' => $this->option( 'thumbScale' ),
                ] );
            ?>
            <div class="gallery-item">
                <a href="<?php echo $image['url'] ?>">
                    <img class="post-gallery_thumb"
                            src="<?php echo $thumbUrl ?>"
                            data-title="<?php echo $image['title'] ?>"
                            data-desc="<?php echo $image['desc'] ?>"
                            alt="<?php echo $image['alt'] ?>"
                            data-scale="<?php echo $this->option( 'thumbScale' ); ?>"/>
                </a>
                <div class="bg-image" style="background-image: url('<?php echo $thumbUrl; ?>');"></div>
            </div>
        <?php } ?>
    </figure>
<?php if ( $this->option( 'masonry' ) ) { ?>
    <script>
      jQuery(function () {
        window.pgInitMasonry();
      });
    </script>
<?php } ?>