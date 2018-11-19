<?php

/**
 * @since 1.0.0
 * @author shennemann
 * @licence MIT
 */
class PostGalleryThemeCustomizer {
    private $sectionId;
    private $textdomain;
    private $fields;
    private $postgalleryAdmin;
    private $postgallery;

    public function __construct() {
        $id = 'postgallery';
        $this->textdomain = 'post-gallery';
        $this->sectionId = $id;

        $this->postgalleryAdmin = \Admin\PostGalleryAdmin::getInstance();

        // slide animations from animate.css
        $sliderAnimations = explode( ',', 'bounce,	flash,	pulse,	rubberBand,
shake,	headShake,	swing,	tada,
wobble,	jello,	bounceIn,	bounceInDown,
bounceInLeft,	bounceInRight,	bounceInUp,	bounceOut,
bounceOutDown,	bounceOutLeft,	bounceOutRight,	bounceOutUp,
fadeIn,	fadeInDown,	fadeInDownBig,	fadeInLeft,
fadeInLeftBig,	fadeInRight,	fadeInRightBig,	fadeInUp,
fadeInUpBig,	fadeOut,	fadeOutDown,	fadeOutDownBig,
fadeOutLeft,	fadeOutLeftBig,	fadeOutRight,	fadeOutRightBig,
fadeOutUp,	fadeOutUpBig,	flipInX,	flipInY,
flipOutX,	flipOutY,	lightSpeedIn,	lightSpeedOut,
rotateIn,	rotateInDownLeft,	rotateInDownRight,	rotateInUpLeft,
rotateInUpRight,	rotateOut,	rotateOutDownLeft,	rotateOutDownRight,
rotateOutUpLeft,	rotateOutUpRight,	hinge,	jackInTheBox,
rollIn,	rollOut,	zoomIn,	zoomInDown,
zoomInLeft,	zoomInRight,	zoomInUp,	zoomOut,
zoomOutDown,	zoomOutLeft,	zoomOutRight,	zoomOutUp,
slideInDown,	slideInLeft,	slideInRight,	slideInUp,
slideOutDown,	slideOutLeft,	slideOutRight,	slideOutUp' );
        array_unshift( $sliderAnimations, '' );

        // need as key-value pair
        $sliderAnimationsKeyValue = [];
        foreach ( $sliderAnimations as $value ) {
            $sliderAnimationsKeyValue[trim( $value )] = trim( $value );
        }
        $sliderAnimations = $sliderAnimationsKeyValue;


        $this->fields = [];

        $this->fields['postgallery-base'] =
            [
                'title' => 'Main-Settings',
                'fields' => [
                    'postgalleryDebugmode' => [
                        'type' => 'radio',
                        'label' => __( 'Debug-Mode', $this->textdomain ),
                        'default' => '0',
                        'choices' => [
                            '1' => __('Yes'),
                            '0' => __('No'),
                        ]
                    ],
                    'sliderType' => [
                        'type' => 'select',
                        'label' => __( 'Slider-Type', $this->textdomain ),
                        'choices' => [
                            'owl' => 'OWL Carousel 2.x',
                            'owl1' => 'OWL Carousel 1.3',
                            'swiper' => 'Swiper (experimental)',
                        ],
                        'default' => 'owl',
                    ],

                    'globalPosition' => [
                        'label' => __( 'Global position', $this->textdomain ),
                        'type' => 'select',
                        'choices' => [
                            'bottom' => __( 'bottom', $this->textdomain ),
                            'top' => __( 'top', $this->textdomain ),
                            'custom' => __( 'custom', $this->textdomain ),
                        ],
                        'default' => 'bottom',
                    ],
                ],
            ];

        $this->fields['postgallery-templateSettings'] =
            [
                'title' => 'Template-Settings',
                'fields' => [
                    'globalTemplate' => [
                        'label' => __( 'Global template', $this->textdomain ),
                        'type' => 'select',
                        'choices' => array_merge(
                            $this->postgalleryAdmin->getCustomTemplates(),
                            $this->postgalleryAdmin->defaultTemplates
                        ),
                    ],

                    'columns' => [
                        'label' => __( 'Columns', $this->textdomain ),
                        'type' => 'select',
                        'choices' => [
                            'auto' => 'Auto',
                            '1' => '1',
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                            '5' => '5',
                            '6' => '6',
                            '7' => '7',
                            '8' => '8',
                            '9' => '9',
                            '10' => '10',
                            '11' => '11',
                            '12' => '12',
                        ],
                        'default' => 'auto'
                    ],

                    'thumbWidth' => [
                        'label' => __( 'Thumb width', $this->textdomain ),
                        'type' => 'text',
                        'default' => 150,
                    ],

                    'thumbHeight' => [
                        'label' => __( 'Thumb height', $this->textdomain ),
                        'type' => 'text',
                        'default' => 150,
                    ],
                    'thumbScale' => [
                        'label' => __( 'Thumb scale', $this->textdomain ),
                        'type' => 'select',
                        'default' => '1',
                        'choices' => [
                            '0' => __( 'crop', $this->textdomain ),
                            '1' => __( 'long edge', $this->textdomain ),
                            '2' => __( 'short edge', $this->textdomain ),
                            '3' => __( 'ignore proportions', $this->textdomain ),
                        ],
                    ],

                    'sliderOwlConfig' => [
                        'type' => 'textarea',
                        'label' => __( 'Owl-Slider-Config (for Slider-Template)', $this->textdomain ),
                        'default' => "items: 1,\nnav: 1,\ndots: 1,\nloop: 1,",
                    ],


                    'stretchImages' => [
                        'label' => __( 'Stretch small images (for watermark)', $this->textdomain ),
                        'type' => 'checkbox',
                    ],
                ],
            ];

        $this->fields['postgallery-liteboxAnimation'] = [
            'title' => 'Animation',

            'fields' => [
                'slideSpeed' => [
                    'id' => 'slideSpeed',
                    'label' => 'Speed (ms)',
                    'type' => 'number',
                    'datasrc' => 'moduldata',
                    //'tooltip' => 'Gibt an wie lange die Animation eines Slides dauert.'
                ],

                'autoplay' => [
                    'id' => 'autoplay',
                    'label' => 'Autoplay',
                    'type' => 'checkbox',
                    'datasrc' => 'moduldata',
                    //'description' => 'Slider wechselt automatisch die Bilder.',
                ],
                'autoplayTimeout' => [
                    'id' => 'autoplayTimeout',
                    'label' => 'Autoplay timeout (ms)',
                    'type' => 'number',
                    'placeholder' => 5000,
                    'datasrc' => 'moduldata',
                    //'description' => 'Gibt an wie lange ein Item angezeigt wird und bis die nächste Animation beginnt.'
                ],
                'animateOut' => [
                    'id' => 'animateOut',
                    'label' => 'Animate out',
                    'type' => 'select',
                    'choices' => $sliderAnimations,
                    'datasrc' => 'moduldata',
                    //'description' => 'Gibt die Animation an mit welcher ein Item ausgeblendet wird',
                ],

                'animateIn' => [
                    'id' => 'animateIn',
                    'label' => 'Einblend-Animation (animateIn)',
                    'type' => 'select',
                    'choices' => $sliderAnimations,
                    'datasrc' => 'moduldata',
                    //'description' => 'Gibt die Animation an mit welcher ein Item eingeblendet wird<br />'
                    //.'Look <a target="_blank" href="https://daneden.github.io/animate.css/">Animate.css</a>',
                ],
            ],
        ];

        $this->fields['postgallery-liteboxSettings'] =
            [
                'title' => 'Litebox-Settings',
                'fields' => [
                    /*'not yet implemented
                    enableLitebox' => [
                        'type' => 'checkbox',
                        'label' => __( 'Enable', $this->textdomain ) . ' Litebox',
                        'default' => true,
                    ],*/
                    'liteboxTemplate' => [
                        'type' => 'select',
                        'default' => 'default',
                        'label' => __( 'Litebox-Template', $this->textdomain ),
                        'choices' => $this->postgalleryAdmin->getLiteboxTemplates(),
                    ],

                    'owlTheme' => [
                        'type' => 'text',
                        'default' => 'default',
                        'label' => __( 'Owl-Theme', $this->textdomain ),
                        'input_attrs' => [ 'list' => 'postgallery-owl-theme' ],
                        'description' => '<datalist id="postgallery-owl-theme"><option>default</option><option>green</option></datalist>',
                    ],
                    'clickEvents' => [
                        'type' => 'checkbox',
                        'label' => __( 'Enable Click-Events', $this->textdomain ),
                        'default' => true,
                    ],
                    'keyEvents' => [
                        'type' => 'checkbox',
                        'label' => __( 'Enable Keypress-Events', $this->textdomain ),
                        'default' => true,
                    ],
                    'arrows' => [
                        'type' => 'checkbox',
                        'label' => __( 'Show arrows', $this->textdomain ),
                        'default' => false,
                    ],
                    'asBg' => [
                        'type' => 'checkbox',
                        'label' => __( 'Images as Background', $this->textdomain ),
                        'default' => false,
                    ],

                    'items' => [
                        'id' => 'items',
                        'label' => 'Items',
                        'type' => 'number',
                        'default' => 1,
                    ],

                    'mainColor' => [
                        'type' => 'text',
                        'label' => __( 'Main-Color', $this->textdomain ),
                        'default' => '#fff',
                    ],
                    'secondColor' => [
                        'type' => 'text',
                        'label' => __( 'Second-Color', $this->textdomain ),
                        'default' => '#333',
                    ],

                    'owlConfig' => [
                        'type' => 'textarea',
                        'label' => __( 'Owl-Litebox-Config', $this->textdomain ),
                        /*'description' => '<b>' . __( 'Presets', $this->textdomain ) . '</b>:'
                            . '<select class="owl-slider-presets">
                                <option value="">Slide (' . __( 'Default', $this->textdomain ) . ')</option>
                                <option value="fade">Fade</option>
                                <option value="slidevertical">SlideVertical</option>
                                <option value="zoominout">Zoom In/out</option>
                                </select>',*/
                        'default' => '',
                    ],

                    'owlThumbConfig' => [
                        'type' => 'textarea',
                        'label' => __( 'Owl-Config for Thumbnail-Slider', $this->textdomain ),
                        'description' => '<b>' . __( 'Presets', $this->textdomain ) . '</b>:'
                            . '<select class="owl-slider-presets">
                                <option value="">Slide (' . __( 'Default', $this->textdomain ) . ')</option>
                                <option value="fade">Fade</option>
                                <option value="slidevertical">SlideVertical</option>
                                <option value="zoominout">Zoom In/out</option>
                                </select>',
                    ],

                    'owlDesc' => [
                        'type' => 'hidden',
                        'label' => __( 'Description', $this->textdomain ),
                        'description' => __( 'You can use these options', $this->textdomain ) . ':<br />' .
                            '<a href="https://owlcarousel2.github.io/OwlCarousel2/docs/api-options.html" target="_blank">
							OwlCarousel Options
						</a>
						<br />' .
                            __( 'You can use these animations', $this->textdomain ) . ':<br />
						<a href="http://daneden.github.io/animate.css/" target="_blank">
							Animate.css
						</a>
					</div>',
                    ],
                ],
            ];
    }

    public function actionCustomizeRegister( $wp_customize ) {
        $prefix = 'postgallery_';
        $wp_customize->add_panel( 'postgallery-panel', [
            'title' => __( 'PostGallery' ),
            'section' => 'postgallery',
        ] );


        foreach ( $this->fields as $sectionId => $section ) {
            $wp_customize->add_section( $sectionId, [
                'title' => __( $section['title'], $this->textdomain ),
                'panel' => 'postgallery-panel',
            ] );

            foreach ( $section['fields'] as $fieldId => $field ) {
                $settingId = $prefix . ( !is_numeric( $fieldId ) ? $fieldId : $field['id'] );
                $controlId = $settingId . '-control';

                $wp_customize->add_setting( $settingId, [
                    'default' => !empty( $field['default'] ) ? $field['default'] : '',
                    'transport' => !empty( $field['transport'] ) ? $field['transport'] : 'refresh',
                ] );

                $wp_customize->add_control( $controlId, [
                    'label' => __( $field['label'], $this->textdomain ),
                    'section' => $sectionId,
                    'type' => !empty( $field['type'] ) ? $field['type'] : 'text',
                    'settings' => $settingId,
                    'description' => !empty( $field['description'] ) ? __( $field['description'], $this->textdomain ) : '',
                    'choices' => !empty( $field['choices'] ) ? $field['choices'] : null,
                    'input_attrs' => !empty( $field['input_attrs'] ) ? $field['input_attrs'] : null,
                ] );
            }
        }
    }
}

/*if( class_exists( 'WP_Customize_Control' ) ) {
    class WP_Customize_Headline_Control extends WP_Customize_Control {
        public $type = 'headline';

        public function render_content() {
            echo '<span class="customize-control-title">' . esc_html( $this->label ) . '</span>';
        }
    }
}*/