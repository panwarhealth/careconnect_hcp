<?php

/*
    Plugin Name: Login CSS
*/
function custom_login() {
	$CSS =  '<style> .login #nav { display: none; } </style>';

	echo $CSS;
}
add_action('login_head', 'custom_login');

function child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'wp-spinnr-child-style', 
        get_stylesheet_uri(), 
        array( 'parent-style' ), 
        wp_get_theme()->get('Version') 
    );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles' );


// Check if Class Exists.
if ( ! class_exists( 'WP_Spinnr_Navwalker' ) ) {
	/**
	 * WP_Spinnr_Navwalker class.
	 *
	 * @extends Walker_Nav_Menu
	 */
	class WP_Spinnr_Navwalker extends Walker_Nav_Menu {

		/**
		 * Starts the list before the elements are added.
		 *
		 * @since WP 3.0.0
		 *
		 * @see Walker_Nav_Menu::start_lvl()
		 *
		 * @param string   $output Used to append additional content (passed by reference).
		 * @param int      $depth  Depth of menu item. Used for padding.
		 * @param stdClass $args   An object of wp_nav_menu() arguments.
		 */
		public function start_lvl( &$output, $depth = 0, $args = array() ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = str_repeat( $t, $depth );
			// Default class to add to the file.
			$classes = array( 'dropdown-menu', get_theme_mod('header_class', 'bg-light') );
			/**
			 * Filters the CSS class(es) applied to a menu list element.
			 *
			 * @since WP 4.8.0
			 *
			 * @param array    $classes The CSS classes that are applied to the menu `<ul>` element.
			 * @param stdClass $args    An object of `wp_nav_menu()` arguments.
			 * @param int      $depth   Depth of menu item. Used for padding.
			 */
			$class_names = join( ' ', apply_filters( 'nav_menu_submenu_css_class', $classes, $args, $depth ) );
			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			/*
			 * The `.dropdown-menu` container needs to have a labelledby
			 * attribute which points to it's trigger link.
			 *
			 * Form a string for the labelledby attribute from the the latest
			 * link with an id that was added to the $output.
			 */
			$labelledby = '';
			// Find all links with an id in the output.
			preg_match_all( '/(<a.*?id=\"|\')(.*?)\"|\'.*?>/im', $output, $matches );
			// With pointer at end of array check if we got an ID match.
			if ( end( $matches[2] ) ) {
				// Build a string to use as aria-labelledby.
				$labelledby = 'aria-labelledby="' . esc_attr( end( $matches[2] ) ) . '"';
			}
			$output .= "{$n}{$indent}<div$class_names $labelledby role=\"menu\">{$n}";
		}

		/**
		 * Starts the element output.
		 *
		 * @since WP 3.0.0
		 * @since WP 4.4.0 The {@see 'nav_menu_item_args'} filter was added.
		 *
		 * @see Walker_Nav_Menu::start_el()
		 *
		 * @param string   $output Used to append additional content (passed by reference).
		 * @param WP_Post  $item   Menu item data object.
		 * @param int      $depth  Depth of menu item. Used for padding.
		 * @param stdClass $args   An object of wp_nav_menu() arguments.
		 * @param int      $id     Current item ID.
		 */
		public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

			$classes = empty( $item->classes ) ? array() : (array) $item->classes;

			/*
			 * Initialize some holder variables to store specially handled item
			 * wrappers and icons.
			 */
			$linkmod_classes = array();
			$icon_classes    = array();

			/*
			 * Get an updated $classes array without linkmod or icon classes.
			 *
			 * NOTE: linkmod and icon class arrays are passed by reference and
			 * are maybe modified before being used later in this function.
			 */
			$classes = self::separate_linkmods_and_icons_from_classes( $classes, $linkmod_classes, $icon_classes, $depth );

			// Join any icon classes plucked from $classes into a string.
			$icon_class_string = join( ' ', $icon_classes );

			/**
			 * Filters the arguments for a single nav menu item.
			 *
			 *  WP 4.4.0
			 *
			 * @param stdClass $args  An object of wp_nav_menu() arguments.
			 * @param WP_Post  $item  Menu item data object.
			 * @param int      $depth Depth of menu item. Used for padding.
			 */
			$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

			// Add .dropdown or .active classes where they are needed.
			if ( isset( $args->has_children ) && $args->has_children ) {
				$classes[] = 'dropdown';
			}
			if ( in_array( 'current-menu-item', $classes, true ) || in_array( 'current-menu-parent', $classes, true ) ) {
				$classes[] = 'active';
			}

			// Add some additional default classes to the item.
			$classes[] = 'menu-item-' . $item->ID;
			$classes[] = 'nav-item';

			// Allow filtering the classes.
			$classes = apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth );

			// Form a string of classes in format: class="class_names".
			$class_names = join( ' ', $classes );
			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			/**
			 * Filters the ID applied to a menu item's list item element.
			 *
			 * @since WP 3.0.1
			 * @since WP 4.1.0 The `$depth` parameter was added.
			 *
			 * @param string   $menu_id The ID that is applied to the menu item's `<li>` element.
			 * @param WP_Post  $item    The current menu item.
			 * @param stdClass $args    An object of wp_nav_menu() arguments.
			 * @param int      $depth   Depth of menu item. Used for padding.
			 */
			$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
			$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

			$output .= $indent . '<div' . $id . $class_names . '>';

			// Initialize array for holding the $atts for the link item.
			$atts = array();

			/*
			 * Set title from item to the $atts array - if title is empty then
			 * default to item title.
			 */
			if ( empty( $item->attr_title ) ) {
				$atts['title'] = ! empty( $item->title ) ? strip_tags( $item->title ) : '';
			} else {
				$atts['title'] = $item->attr_title;
			}

			$atts['target'] = ! empty( $item->target ) ? $item->target : '';
			$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
			// If the item has children, add atts to the <a>.
			if ( isset( $args->has_children ) && $args->has_children && 0 === $depth && $args->depth > 1 ) {
				$atts['href']          = '#';
				$atts['data-toggle']   = 'dropdown';
				$atts['aria-haspopup'] = 'true';
				$atts['aria-expanded'] = 'false';
				$atts['class']         = 'dropdown-toggle nav-link '.get_theme_mod('header_link_class', 'text-dark');
				$atts['id']            = 'menu-item-dropdown-' . $item->ID;
			} else {
				$atts['href'] = ! empty( $item->url ) ? $item->url : '#';
				// For items in dropdowns use .dropdown-item instead of .nav-link.
				if ( $depth > 0 ) {
					$atts['class'] = 'dropdown-item '.get_theme_mod('header_link_class', 'text-dark');
				} else {
					$atts['class'] = 'nav-link '.get_theme_mod('header_link_class', 'text-primary');
				}
			}

			$atts['aria-current'] = $item->current ? 'page' : '';

			// Update atts of this item based on any custom linkmod classes.
			$atts = self::update_atts_for_linkmod_type( $atts, $linkmod_classes );
			// Allow filtering of the $atts array before using it.
			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

			// Build a string of html containing all the atts for the item.
			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( ! empty( $value ) ) {
					$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			// Set a typeflag to easily test if this is a linkmod or not.
			$linkmod_type = self::get_linkmod_type( $linkmod_classes );

			// START appending the internal item contents to the output.
			$item_output = isset( $args->before ) ? $args->before : '';

			/*
			 * This is the start of the internal nav item. Depending on what
			 * kind of linkmod we have we may need different wrapper elements.
			 */
			if ( '' !== $linkmod_type ) {
				// Is linkmod, output the required element opener.
				$item_output .= self::linkmod_element_open( $linkmod_type, $attributes );
			} else {
				// With no link mod type set this must be a standard <a> tag.
				$item_output .= '<a' . $attributes . '>';
			}

			/*
			 * Initiate empty icon var, then if we have a string containing any
			 * icon classes form the icon markup with an <i> element. This is
			 * output inside of the item before the $title (the link text).
			 */
			$icon_html = '';
			if ( ! empty( $icon_class_string ) ) {
				// Append an <i> with the icon classes to what is output before links.
				$icon_html = '<i class="' . esc_attr( $icon_class_string ) . '" aria-hidden="true"></i> ';
			}

			/** This filter is documented in wp-includes/post-template.php */
			$title = apply_filters( 'the_title', esc_html( $item->title ), $item->ID );

			/**
			 * Filters a menu item's title.
			 *
			 * @since WP 4.4.0
			 *
			 * @param string   $title The menu item's title.
			 * @param WP_Post  $item  The current menu item.
			 * @param stdClass $args  An object of wp_nav_menu() arguments.
			 * @param int      $depth Depth of menu item. Used for padding.
			 */
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

			// If the .sr-only class was set apply to the nav items text only.
			if ( in_array( 'sr-only', $linkmod_classes, true ) ) {
				$title         = self::wrap_for_screen_reader( $title );
				$keys_to_unset = array_keys( $linkmod_classes, 'sr-only', true );
				foreach ( $keys_to_unset as $k ) {
					unset( $linkmod_classes[ $k ] );
				}
			}

			// Put the item contents into $output.
			$item_output .= isset( $args->link_before ) ? $args->link_before . $icon_html . $title . $args->link_after : '';

			/*
			 * This is the end of the internal nav item. We need to close the
			 * correct element depending on the type of link or link mod.
			 */
			if ( '' !== $linkmod_type ) {
				// Is linkmod, output the required closing element.
				$item_output .= self::linkmod_element_close( $linkmod_type );
			} else {
				// With no link mod type set this must be a standard <a> tag.
				$item_output .= '</a>';
			}

			$item_output .= isset( $args->after ) ? $args->after : '';

			// END appending the internal item contents to the output.
			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}

		/**
		 * Ends the list of after the elements are added.
		 *
		 * @since 3.0.0
		 *
		 * @see Walker::end_lvl()
		 *
		 * @param string   $output Used to append additional content (passed by reference).
		 * @param int      $depth  Depth of menu item. Used for padding.
		 * @param stdClass $args   An object of wp_nav_menu() arguments.
		 */
		public function end_lvl( &$output, $depth = 0, $args = null ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent  = str_repeat( $t, $depth );
			$output .= "$indent</div>{$n}";
		}

		/**
		 * Ends the element output, if needed.
		 *
		 * @since 3.0.0
		 *
		 * @see Walker::end_el()
		 *
		 * @param string   $output Used to append additional content (passed by reference).
		 * @param WP_Post  $item   Page data object. Not used.
		 * @param int      $depth  Depth of page. Not Used.
		 * @param stdClass $args   An object of wp_nav_menu() arguments.
		 */
		public function end_el( &$output, $item, $depth = 0, $args = null ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$output .= "</div>{$n}";
		}

		/**
		 * Traverse elements to create list from elements.
		 *
		 * Display one element if the element doesn't have any children otherwise,
		 * display the element and its children. Will only traverse up to the max
		 * depth and no ignore elements under that depth. It is possible to set the
		 * max depth to include all depths, see walk() method.
		 *
		 * This method should not be called directly, use the walk() method instead.
		 *
		 * @since WP 2.5.0
		 *
		 * @see Walker::start_lvl()
		 *
		 * @param object $element           Data object.
		 * @param array  $children_elements List of elements to continue traversing (passed by reference).
		 * @param int    $max_depth         Max depth to traverse.
		 * @param int    $depth             Depth of current element.
		 * @param array  $args              An array of arguments.
		 * @param string $output            Used to append additional content (passed by reference).
		 */
		public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
			if ( ! $element ) {
				return; }
			$id_field = $this->db_fields['id'];
			// Display this element.
			if ( is_object( $args[0] ) ) {
				$args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] ); }
			parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
		}

		/**
		 * Menu Fallback.
		 *
		 * If this function is assigned to the wp_nav_menu's fallback_cb variable
		 * and a menu has not been assigned to the theme location in the WordPress
		 * menu manager the function with display nothing to a non-logged in user,
		 * and will add a link to the WordPress menu manager if logged in as an admin.
		 *
		 * @param array $args passed from the wp_nav_menu function.
		 */
		public static function fallback( $args ) {
			if ( current_user_can( 'edit_theme_options' ) ) {

				// Get Arguments.
				$container       = $args['container'];
				$container_id    = $args['container_id'];
				$container_class = $args['container_class'];
				$menu_class      = $args['menu_class'];
				$menu_id         = $args['menu_id'];

				// Initialize var to store fallback html.
				$fallback_output = '';

				if ( $container ) {
					$fallback_output .= '<' . esc_attr( $container );
					if ( $container_id ) {
						$fallback_output .= ' id="' . esc_attr( $container_id ) . '"';
					}
					if ( $container_class ) {
						$fallback_output .= ' class="' . esc_attr( $container_class ) . '"';
					}
					$fallback_output .= '>';
				}
				$fallback_output .= '<div';
				if ( $menu_id ) {
					$fallback_output .= ' id="' . esc_attr( $menu_id ) . '"'; }
				if ( $menu_class ) {
					$fallback_output .= ' class="' . esc_attr( $menu_class ) . '"'; }
				$fallback_output .= '>';
				$fallback_output .= '<div class="nav-item"><a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" class="nav-link '.get_theme_mod('header_link_class', 'text-dark').'" title="' . esc_attr__( 'Add a menu', 'wp-spinnr' ) . '">' . esc_html__( 'Add a menu', 'wp-spinnr' ) . '</a></div>';
				$fallback_output .= '</div>';
				if ( $container ) {
					$fallback_output .= '</' . esc_attr( $container ) . '>';
				}

				// If $args has 'echo' key and it's true echo, otherwise return.
				if ( array_key_exists( 'echo', $args ) && $args['echo'] ) {
					echo $fallback_output; // WPCS: XSS OK.
				} else {
					return $fallback_output;
				}
			}
		}

		/**
		 * Find any custom linkmod or icon classes and store in their holder
		 * arrays then remove them from the main classes array.
		 *
		 * Supported linkmods: .disabled, .dropdown-header, .dropdown-divider, .sr-only
		 * Supported iconsets: Font Awesome 4/5, Glypicons
		 *
		 * NOTE: This accepts the linkmod and icon arrays by reference.
		 *
		 * @since 4.0.0
		 *
		 * @param array   $classes         an array of classes currently assigned to the item.
		 * @param array   $linkmod_classes an array to hold linkmod classes.
		 * @param array   $icon_classes    an array to hold icon classes.
		 * @param integer $depth           an integer holding current depth level.
		 *
		 * @return array  $classes         a maybe modified array of classnames.
		 */
		private function separate_linkmods_and_icons_from_classes( $classes, &$linkmod_classes, &$icon_classes, $depth ) {
			// Loop through $classes array to find linkmod or icon classes.
			foreach ( $classes as $key => $class ) {
				/*
				 * If any special classes are found, store the class in it's
				 * holder array and and unset the item from $classes.
				 */
				if ( preg_match( '/^disabled|^sr-only/i', $class ) ) {
					// Test for .disabled or .sr-only classes.
					$linkmod_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^dropdown-header|^dropdown-divider|^dropdown-item-text/i', $class ) && $depth > 0 ) {
					/*
					 * Test for .dropdown-header or .dropdown-divider and a
					 * depth greater than 0 - IE inside a dropdown.
					 */
					$linkmod_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^fa-(\S*)?|^fa(s|r|l|b)?(\s?)?$/i', $class ) ) {
					// Font Awesome.
					$icon_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^glyphicon-(\S*)?|^glyphicon(\s?)$/i', $class ) ) {
					// Glyphicons.
					$icon_classes[] = $class;
					unset( $classes[ $key ] );
				}
			}

			return $classes;
		}

		/**
		 * Return a string containing a linkmod type and update $atts array
		 * accordingly depending on the decided.
		 *
		 * @since 4.0.0
		 *
		 * @param array $linkmod_classes array of any link modifier classes.
		 *
		 * @return string                empty for default, a linkmod type string otherwise.
		 */
		private function get_linkmod_type( $linkmod_classes = array() ) {
			$linkmod_type = '';
			// Loop through array of linkmod classes to handle their $atts.
			if ( ! empty( $linkmod_classes ) ) {
				foreach ( $linkmod_classes as $link_class ) {
					if ( ! empty( $link_class ) ) {

						// Check for special class types and set a flag for them.
						if ( 'dropdown-header' === $link_class ) {
							$linkmod_type = 'dropdown-header';
						} elseif ( 'dropdown-divider' === $link_class ) {
							$linkmod_type = 'dropdown-divider';
						} elseif ( 'dropdown-item-text' === $link_class ) {
							$linkmod_type = 'dropdown-item-text';
						}
					}
				}
			}
			return $linkmod_type;
		}

		/**
		 * Update the attributes of a nav item depending on the limkmod classes.
		 *
		 * @since 4.0.0
		 *
		 * @param array $atts            array of atts for the current link in nav item.
		 * @param array $linkmod_classes an array of classes that modify link or nav item behaviors or displays.
		 *
		 * @return array                 maybe updated array of attributes for item.
		 */
		private function update_atts_for_linkmod_type( $atts = array(), $linkmod_classes = array() ) {
			if ( ! empty( $linkmod_classes ) ) {
				foreach ( $linkmod_classes as $link_class ) {
					if ( ! empty( $link_class ) ) {
						/*
						 * Update $atts with a space and the extra classname
						 * so long as it's not a sr-only class.
						 */
						if ( 'sr-only' !== $link_class ) {
							$atts['class'] .= ' ' . esc_attr( $link_class );
						}
						// Check for special class types we need additional handling for.
						if ( 'disabled' === $link_class ) {
							// Convert link to '#' and unset open targets.
							$atts['href'] = '#';
							unset( $atts['target'] );
						} elseif ( 'dropdown-header' === $link_class || 'dropdown-divider' === $link_class || 'dropdown-item-text' === $link_class ) {
							// Store a type flag and unset href and target.
							unset( $atts['href'] );
							unset( $atts['target'] );
						}
					}
				}
			}
			return $atts;
		}

		/**
		 * Wraps the passed text in a screen reader only class.
		 *
		 * @since 4.0.0
		 *
		 * @param string $text the string of text to be wrapped in a screen reader class.
		 * @return string      the string wrapped in a span with the class.
		 */
		private function wrap_for_screen_reader( $text = '' ) {
			if ( $text ) {
				$text = '<span class="sr-only">' . $text . '</span>';
			}
			return $text;
		}

		/**
		 * Returns the correct opening element and attributes for a linkmod.
		 *
		 * @since 4.0.0
		 *
		 * @param string $linkmod_type a sting containing a linkmod type flag.
		 * @param string $attributes   a string of attributes to add to the element.
		 *
		 * @return string              a string with the openign tag for the element with attribibutes added.
		 */
		private function linkmod_element_open( $linkmod_type, $attributes = '' ) {
			$output = '';
			if ( 'dropdown-item-text' === $linkmod_type ) {
				$output .= '<span class="dropdown-item-text"' . $attributes . '>';
			} elseif ( 'dropdown-header' === $linkmod_type ) {
				/*
				 * For a header use a span with the .h6 class instead of a real
				 * header tag so that it doesn't confuse screen readers.
				 */
				$output .= '<span class="dropdown-header h6"' . $attributes . '>';
			} elseif ( 'dropdown-divider' === $linkmod_type ) {
				// This is a divider.
				$output .= '<div class="dropdown-divider"' . $attributes . '>';
			}
			return $output;
		}

		/**
		 * Return the correct closing tag for the linkmod element.
		 *
		 * @since 4.0.0
		 *
		 * @param string $linkmod_type a string containing a special linkmod type.
		 *
		 * @return string              a string with the closing tag for this linkmod type.
		 */
		private function linkmod_element_close( $linkmod_type ) {
			$output = '';
			if ( 'dropdown-header' === $linkmod_type || 'dropdown-item-text' === $linkmod_type ) {
				/*
				 * For a header use a span with the .h6 class instead of a real
				 * header tag so that it doesn't confuse screen readers.
				 */
				$output .= '</span>';
			} elseif ( 'dropdown-divider' === $linkmod_type ) {
				// This is a divider.
				$output .= '</div>';
			}
			return $output;
		}
	}
}

function custom_wp_spinnr_setup() {
	register_nav_menus( array(
		'primary-alt' => esc_html__( 'Primary Alt', 'wp-spinnr' ),
	));
}
add_action( 'after_setup_theme', 'custom_wp_spinnr_setup' );

if ( ! function_exists( 'create_wp_post_list' ) ) :
function create_wp_post_list( $atts = array(), $content = null ) {
    
    /// set up default parameters
    extract(shortcode_atts(array(
        'id' => '',
        'type' => 'posts',
        'categories' => '',
        'tags' => '',
        'orderby' => 'id',
        'order' => 'desc',
        'display_type' => 'filtered',
        'filter_ui' => 'dropdown',
        'filters' => '',
        'per_page' => 100,
        'offset' => 0,
        'container_class' => '',
        'row_class' => '',
        'column_class' => '',
        'cat_taxonomy_term' => '',
        'tag_taxonomy_term' => '',
    ), $atts));

    $query_params = [
        'orderby' => $orderby,
        'order' => $order,
        'per_page' => $per_page,
        'offset' => $offset,
        'embed' => true,
    ];
    if(!empty($cat_taxonomy_term) && !empty($categories)){
        if($cat_taxonomy_term == 'category'){
            $query_params['categories'] = $categories;
        } else {
            $query_params[$cat_taxonomy_term] = $categories;
        }
    }
    if(!empty($tag_taxonomy_term) && !empty($tags)){
        if($tag_taxonomy_term == 'post_tag'){
            $query_params['tags'] = $tags;
        } else {
            $query_params[$tag_taxonomy_term] = $tags;
        }
    }

    $request = new WP_REST_Request( 'GET', '/wp/v2/'.$type );
    $request->set_query_params($query_params);
    $response = rest_do_request( $request );
    $server = rest_get_server();
    $data = $server->response_to_data( $response, false );
    $html = '';
    
    switch($display_type){
        case "filtered":
            $html .= '<div class="filters wp-post-list-filter-group">';
            foreach(explode(',',$filters) as $filter){
                $terms = get_terms( array(
                    'taxonomy' => $filter,
                    'hide_empty' => true,
                ));
                if($filter_ui == 'list'){
                    $html .= '<ul class="list-filter">';
                    $html .= '<li><a href="#" data-filter="*">All</a></li>';
                    foreach($terms as $f){
                        $html .= '<li><a href="#" data-filter=".'.$f->slug.'">'.$f->name.'</a></li>';
                    }
                    $html .= '</ul>';
                } elseif($filter_ui == 'checkbox') {
                    $html .= '<div class="checkbox-filter">';
                    $html .= '<label class="filter-label">'.get_taxonomy($filter)->label.'</label>';
                    foreach($terms as $f){
                        $html .= '<label><input type="checkbox" name="checkbox-filter[]" value=".'.$f->slug.'" checked="checked" /> '.$f->name.'</label>';
                    }
                    $html .= '</div>';
                } else {
					if($filter != 'application') {
						$html .= '<select class="dropdown-filter">';
						$html .= '<option value="*">'.get_taxonomy($filter)->label.'</option>';
						foreach($terms as $f){
							$html .= '<option value=".'.$f->slug.'">'.$f->name.'</option>';
						}
						$html .= '</select>';
					}
                }
            }
            $html .= '</div>';
            $html .= '<div class="'.$container_class.'"><div id="'.$id.'" class="'.$row_class.'" >';
            foreach($data as $post) {
                $post_filters = [];
                $taxonomy_names = get_post_taxonomies($post['id']);
                foreach($taxonomy_names as $t){
                    $post_terms = get_the_terms($post['id'], $t);
                    if(is_array($post_terms)){
                        foreach($post_terms as $pt){
                            $post_filters[] = $pt->slug;
                        }
                    }
                }
                $html .= '<div class="isotope-element '.$column_class.' '.implode(' ', $post_filters).'">';
                $html .= do_shortcode(replace_tags($content, array_flat($post)));
                $html .= '</div>';
            }
            $html .= '</div></div>';
        break;

        case "carousel":
            $html .= '<div class="'.$container_class.'"><div id="'.$id.'" class="owl-carousel owl-theme">';
            foreach($data as $post) {
                $html .= '<div class="item">';
                $html .= replace_tags($content, array_flat($post));
                $html .= '</div>';
            }
            $html .= '</div></div>';
        break;

        case "paginated":
        case "normal":
            $html .= '<div class="'.$container_class.'"><div id="'.$id.'" class="'.$row_class.'" >';
            foreach($data as $post) {
                $html .= '<div class="'.$column_class.'">';
                $html .= replace_tags($content, array_flat($post));
                $html .= '</div>';
            }
            $html .= '</div></div>';
        break;
    }
    wp_reset_postdata();
    return $html;
}
add_shortcode('wp-post-list', 'create_wp_post_list');
endif;


function javascript_variables(){ ?>
    <script type="text/javascript">
        var ajax_url = '<?php echo admin_url( "admin-ajax.php" ); ?>';
        var ajax_aphra_nonce = '<?php echo wp_create_nonce( "ajax_aphra_call" ); ?>';
    </script><?php
}
add_action ( 'wp_head', 'javascript_variables' );

add_action( 'wp_ajax_nopriv_ajax_aphra_call', 'ajax_aphra_call' );
add_action( 'wp_ajax_ajax_aphra_call', 'ajax_aphra_call' );

function ajax_aphra_call() {
	$return = [
		'success' => false,
		'data' => [],
		'message' => ''
	];
    if ( !wp_verify_nonce( $_REQUEST['ajax_aphra_nonce'], "ajax_aphra_call")) {
		$return['message'] = "An error occurred. Please refresh the page.";

		wp_send_json($return, 500);
    } 
	// add_user_meta(2, 'ahpra_id', 'test', true);
	// add validation if ahpra_id is valid

	if($_REQUEST['ahpra_id'] == '' || !isset($_REQUEST['ahpra_id'])){
		$return['message'] = "Professional Number is required.";
		wp_send_json($return, 422);
	}
	else{
		// if(check_if_aphra_exists_wp($_REQUEST['ahpra_id'])){
		// 	// put aphra cookie
		// 	$return['success'] = true;
		// 	$return['message'] = set_ahpra_cookie($_REQUEST['ahpra_id']);
		// }
		// else{
		
		
			if(in_array(site_url(), ['https://hcp.carepharma.com.au', 'http://hcp.carepharma.com.au'])){
				$ahpra_data = check_if_aphra_exists_soap($_REQUEST['ahpra_id']);
			}
			else{
				$ahpra_data = check_if_aphra_exists_curl($_REQUEST['ahpra_id']);
			}
			wp_send_json($ahpra_data, 422);
			if(isset($ahpra_data) && $ahpra_data['success'] == true){
				// add ahpra_id to database
				// add_user_meta(2, 'ahpra_id', $_REQUEST['ahpra_id'], false);
				// add_user_meta(2, 'ahpra_id_'.$_REQUEST['ahpra_id'], serialize($ahpra_data['data']->ProfessionNumberReplay->Practitioner), true);

				$return['success'] = true;
				// $return['message'] = set_ahpra_cookie($_REQUEST['ahpra_id']);
				$return['message'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Success!";
			}
			else{
				set_ahpra_cookie(null);
				$return['message'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
				wp_send_json($return, 422);
			}
		// }
	}
	


	// if not, do api call to aphra

	// if aphra exists, create a cookie in the browser for it

	wp_send_json($return);
	
	wp_die();
}

function check_if_aphra_exists_soap($ahpra_id){
	ob_start();
	// check if ahpra_id exists on wp_user_meta
	if($ahpra_id == ''){
		return false;
	}

	// AHPRA PIEWS credentials — read from env. Local: docker-compose passes
	// from .env. Prod/staging: putenv() in wp-config.php sets them.
	// See docs/DEPLOY.md "High-risk pending deploys" before shipping.
	$user_id                        = getenv('AHPRA_PIEWS_USER') ?: '';
	$password                       = getenv('AHPRA_PIEWS_PASSWORD') ?: '';

	// Endpoint switches between prod and test at the webservice URL.
	// Test endpoint: https://ws2test.ahpra.gov.au/pie_int/svc/PractitionerRegistrationSearch/2.0.0/FindRegistrationService.svc?wsdl
	$wsdl                           = "https://ws2.ahpra.gov.au/pie/svc/PractitionerRegistrationSearch/2.0.0/FindRegistrationService.svc";
    $ns_addressing                  = "http://www.w3.org/2005/08/addressing";
    $ns_find_registration_request   = "http://ns.ahpra.gov.au/pie/svc/frs/FindRegistration/2.0.0/FindRegistrationPortType/FindRegistrationsRequest";
    $ns_common_core_elements        = "http://ns.ahpra.gov.au/pie/xsd/common/CommonCoreElements/2.0.0";
    $error_message                  = "";
    $result = null;
    try{	
        $client = new SoapClient($wsdl,array(
                    "soap_version" => SOAP_1_2,
                    'exceptions' => true
                ));
                
        $auth = new stdClass();
        $auth->UserId = $user_id;
        $auth->Password = $password;
        $header = [];
        $header[] = new SoapHeader($ns_addressing, 'To',$wsdl, true);
        $header[] = new SoapHeader($ns_addressing, 'Action', $ns_find_registration_request);
        $header[] = new SoapHeader($ns_common_core_elements, 'LoginDetails', $auth);
        
        $prof = new stdClass();
        $prof->ProfessionNumber = [$ahpra_id];
        
        $result = $client->__soapCall("FindRegistrations", [$prof], null, $header);
        
    } catch (SOAPFault $f) {

        $error_message = 'Unexpected error. ('.$f->getMessage().')';
        
        if(isset($f->detail) && isset($f->detail->ServiceMessages)){
            $error_message = $f->detail->ServiceMessages->HighestSeverity.': '.$f->detail->ServiceMessages->ServiceMessage->Reason.' ('.$f->detail->ServiceMessages->ServiceMessage->Status.')';
        }
    }
    ob_get_clean();

	$return = [
		'message' 	=> '',
		'success' 	=> false,
		'data'		=> null
	];
    if($error_message == '' && $result != null){
        $return['success'] 	= true;
		$return['data']		= $result;
    }
    else{
		$return['message'] = $error_message;
    }
    
    
    return $return;
}

// this function is being used for live transaction, since the soap call does not work, only on curl request
function check_if_aphra_exists_curl($ahpra_id){
	if($ahpra_id == ''){
			return false;
		}  
	$user_id                        = getenv('AHPRA_PIEWS_USER') ?: '';
	$password                       = getenv('AHPRA_PIEWS_PASSWORD') ?: '';

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://ws2.ahpra.gov.au/pie/svc/PractitionerRegistrationSearch/2.0.0/FindRegistrationService.svc',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'<soap:Envelope xmlns:soap=\'http://www.w3.org/2003/05/soap-envelope\' xmlns:ns=\'http://ns.ahpra.gov.au/pie/xsd/common/CommonCoreElements/2.0.0\' xmlns:ns1=\'http://ns.ahpra.gov.au/pie/svc/frs/FindRegistration/2.0.0\' xmlns:ns2=\'http://ns.ahpra.gov.au/pie/xsd/frs/FindRegistrationMessages/2.0.0\'>

	<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">
	<To soap:mustUnderstand=\'1\' xmlns="http://www.w3.org/2005/08/addressing">https://ws2.ahpra.gov.au/pie/svc/PractitionerRegistrationSearch/2.0.0/FindRegistrationService.svc</To>
	<ns:LoginDetails>
	<ns:UserId>'.$user_id.'</ns:UserId>
	<ns:Password>'.$password.'</ns:Password>
	</ns:LoginDetails>
	<wsa:Action>http://ns.ahpra.gov.au/pie/svc/frs/FindRegistration/2.0.0/FindRegistrationPortType/FindRegistrationsRequest</wsa:Action>
	</soap:Header>
	<soap:Body>
	<ns1:FindRegistrations>
	<ns2:ProfessionNumber>'.$ahpra_id.'</ns2:ProfessionNumber>
	</ns1:FindRegistrations>
	</soap:Body>
	</soap:Envelope>',
// 		CURLOPT_POSTFIELDS => '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:ns1="http://ns.ahpra.gov.au/pie/xsd/frs/FindRegistrationMessages/2.0.0" xmlns:ns2="http://ns.ahpra.gov.au/pie/svc/frs/FindRegistration/2.0.0" xmlns:ns3="http://www.w3.org/2005/08/addressing" xmlns:ns4="http://ns.ahpra.gov.au/pie/xsd/common/CommonCoreElements/2.0.0">
//   <env:Header>
//     <ns3:To env:mustUnderstand="true" xmlns="http://www.w3.org/2005/08/addressing">https://ws2.ahpra.gov.au/pie/svc/PractitionerRegistrationSearch/2.0.0/FindRegistrationService.svc</ns3:To>
//     <ns4:LoginDetails>
//       <ns4:UserId>'.$user_id.'</ns4:UserId>
//       <ns4:Password>'.$password.'</ns4:Password>
//     </ns4:LoginDetails>
//   </env:Header>
//   <env:Body>
//     <ns2:FindRegistrations>
//       <ns1:ProfessionNumber>'.$ahpra_id.'</ns1:ProfessionNumber>
//     </ns2:FindRegistrations>
//   </env:Body>
// </env:Envelope>',
		CURLOPT_HTTPHEADER => array(
		'Content-Type: application/soap+xml'
		),
	));

	$response = curl_exec($curl);

	ob_get_clean();

		$return = [
			'message' 	=> '',
			'success' 	=> false,
			'data'		=> null
		];

		if (!curl_errno($curl)) {
		$info = curl_getinfo($curl);
		$p    = xml_parser_create();
		xml_parse_into_struct($p, $response, $xml_array, $index);
		
		if($info['http_code'] == 200){
			if(xml_array_lookup_val($xml_array, 'PRACTITIONERCOUNT') > 0){
				$return['success'] 	= true;
				$data = [
					'AuditDetails' => ['PractitionerCount'=>xml_array_lookup_val($xml_array, 'PRACTITIONERCOUNT')],
					'ProfessionNumberReplay' => [
					'Practitioner' => [
						'PractitionerName' => [
						'NameTitle' => xml_array_lookup_val($xml_array, 'NameTitle'),
						'FamilyName' => xml_array_lookup_val($xml_array, 'FamilyName'),
						'GivenName' => xml_array_lookup_val($xml_array, 'GivenName'),
						],
						'Demographics' => [
						'Gender' => xml_array_lookup_val($xml_array, 'Gender'),
						],
						'Qualification' => [
						'QualificationTitle' => xml_array_lookup_val($xml_array, 'QualificationTitle'),
						'AwardingInstitution' => xml_array_lookup_val($xml_array, 'AwardingInstitution'),
						'CountryQualificationObtained' => xml_array_lookup_val($xml_array, 'CountryQualificationObtained'),
						'YearOfQualification' => xml_array_lookup_val($xml_array, 'YearOfQualification'),
						],
						'Profession' => [
						'ProfessionNumber' => xml_array_lookup_val($xml_array, 'ProfessionNumber'),
						'Profession' => xml_array_lookup_val($xml_array, 'Profession'),
						'CountryQualificationObtained' => xml_array_lookup_val($xml_array, 'CountryQualificationObtained'),
						'YearOfQualification' => xml_array_lookup_val($xml_array, 'YearOfQualification'),
						],
					]
					]
				];
				$return['data'] = json_decode(json_encode($data),false);
			}
			else{
				$return['message'] = xml_array_lookup_val($xml_array, 'STATUS') ?? 'No match found.';
			}
			
		}
		else{
			$return['message'] = xml_array_lookup_val($xml_array, 'REASON') ?? 'No match found.';
		}
		}
		else{
			$return['message'] = $error_message;
		}
	return $return;
}

function xml_array_lookup_val($array, $key){
	$match = '';
	if(is_array($array) && count($array)>0){
		foreach($array as $arr){
			if($arr['tag'] == strtoupper($key) && isset($arr['value'])){
			$match = $arr['value'];
			}
		}
	}

	return $match;
}

add_shortcode('show_aphra_login_form', 'aphra_login_form_content');
function aphra_login_form_content(){
	return '';
	// return aphra_login_form();
}

function aphra_login_form(){
	$html = '<div class="login-panel" style="top:40%;bottom:auto">';
	$html .= '	<div class="card">';
	$html .= '		<div class="card-header">';
	$html .= '			Log In with AHPRA';
	$html .= '		</div>';
	$html .= '		<div class="card-body"><div class="alert alert-danger" style="display:none" id="form_message"></div>';
	$html .= '		<form action="" method="post" name="aphraForm">';
	$html .= '			<div class="form-group">';
	$html .= '				<label for="formGroupExampleInput">Professional Number</label>';
	$html .= '				<input type="text" class="form-control rounded-0" id="formGroupExampleInput" value="" name="ahpra_id">';
	$html .= '			</div>';
	$html .= '			<div class="row">';
	// $html .= '				<div class="col-md-6"><a href="#" class="btn btn-block rounded-0 btn-lg btn-secondary">Register</a></div>';
	$html .= '				<div class="col-md-6 offset-md-6"><button type="submit" class="btn btn-block rounded-0 btn-primary text-center bg-primary text-dark border-primary" id="aphra_login_btn"><i class="fa fa-spinner fa-spin d-none"></i> Login</button></div>';
	$html .= '			</div>';
	$html .= '<div class="row mt-4"><div class="col-12"><p class="">Some areas of the CAR-T Central site may only be accessed by health care professionals. You are required to enter your full AHPRA number for access, including all letters and numbers.  This number is stored to enable your ongoing access to the site but no other personal details are retained. To view our full Privacy Policy please click <a href="'.site_url('/privacy-policy').'"><u>here</u></a>.</p></div></div>';
	$html .= '			<input type="hidden" name="action" value="ajax_aphra_call" style="display: none; visibility: hidden; opacity: 0;">';
	$html .= '			</form>';
	$html .= '		</div>';
	$html .= '	</div>';
	$html .= '</div>';
	return $html;
}

add_filter('frm_validate_entry', 'valdidate_aphra_register', 20, 2);
function valdidate_aphra_register($errors, $values) {
	if($values['form_id'] == 2) { // Register Form
		if($_POST['item_meta'][710] == '') {
			$errors['field710'] = 'This field cannot be blank.';
		}
		if($_POST['item_meta']['conf_710'] == '') {
			$errors['fieldconf_710'] = 'This field cannot be blank.';
		}
		if(in_array(site_url(), ['https://hcp.carepharma.com.au', 'http://hcp.carepharma.com.au'])) {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][8]);
		}
		else {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][8]);
		}
		if(isset($ahpra_data) && $ahpra_data['success'] == true && $ahpra_data['data']->AuditDetails->PractitionerCount > 0){
			// add ahpra_id to database
			$_POST['item_meta'][94] = $_POST['item_meta'][8]; // ahpra_id
			$_POST['item_meta'][95] = json_encode($ahpra_data['data']->ProfessionNumberReplay->Practitioner); // ahpra_details
		}
		else {
			$errors['_error'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
		}
	}
	if($values['form_id'] == 129) { // Register with Form RACGP
		return $errors;
		if(in_array(site_url(), ['https://hcp.carepharma.com.au', 'http://hcp.carepharma.com.au'])) {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][3985]);
		}
		else {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][3985]);
		}
		if(isset($ahpra_data) && $ahpra_data['success'] == true && $ahpra_data['data']->AuditDetails->PractitionerCount > 0){
			// add ahpra_id to database
			$_POST['item_meta'][94] = $_POST['item_meta'][3985]; // ahpra_id
			$_POST['item_meta'][95] = json_encode($ahpra_data['data']->ProfessionNumberReplay->Practitioner); // ahpra_details
		}
		else {
			$errors['_error'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
		}		
	
		//$racgp_value = sanitize_text_field($_POST["item_meta"][4273] ?? '');		
		
	}
	else if($values['form_id'] == 22) { // Registration - Hydralyte comp Form
		if($_POST['item_meta'][918] == 'Yes') {
			if($_POST['item_meta'][422] == '') {
				$errors['field422'] = 'This field cannot be blank.';
			}
			if($_POST['item_meta']['conf_422'] == '') {
				$errors['fieldconf_422'] = 'This field cannot be blank.';
			}
		}
		if(in_array(site_url(), ['https://hcp.carepharma.com.au', 'http://hcp.carepharma.com.au'])) {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][358]);
		}
		else {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][358]);
		}
		if(isset($ahpra_data) && $ahpra_data['success'] == true && $ahpra_data['data']->AuditDetails->PractitionerCount > 0){
			// add ahpra_id to database
			$_POST['item_meta'][646] = $_POST['item_meta'][358]; // ahpra_id
			$_POST['item_meta'][662] = json_encode($ahpra_data['data']->ProfessionNumberReplay->Practitioner); // ahpra_details
		}
		else {
			$errors['_error'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
		}
	}
	else if($values['form_id'] == 54) { // Registration - Therapy Area Form
		if($_POST['item_meta'][1286] == 'Yes') {
			if($_POST['item_meta'][1302] == '') {
				$errors['field1302'] = 'This field cannot be blank.';
			}
			if($_POST['item_meta']['conf_1302'] == '') {
				$errors['fieldconf_1302'] = 'This field cannot be blank.';
			}
		}
		if(in_array(site_url(), ['https://hcp.carepharma.com.au', 'http://hcp.carepharma.com.au'])) {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][1222]);
		}
		else {
			$ahpra_data = check_if_aphra_exists_curl($_POST['item_meta'][1222]);
		}
		if(isset($ahpra_data) && $ahpra_data['success'] == true && $ahpra_data['data']->AuditDetails->PractitionerCount > 0){
			// add ahpra_id to database
			$_POST['item_meta'][1526] = $_POST['item_meta'][1222]; // ahpra_id
			$_POST['item_meta'][1542] = json_encode($ahpra_data['data']->ProfessionNumberReplay->Practitioner); // ahpra_details
		}
		else {
			$errors['_error'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
		}
	}
	return $errors;
}

function shortcode_resource_link( $atts = [], $content = null, $tag = '' ) {
	extract( shortcode_atts( array(
		'download' => '',
		'featured_image' => '',
		'featured_media' => '',
		'vimeo' => '',
		'title' => '',
	), $atts ) );
	$lity = '';
	if($atts['vimeo'] && $atts['vimeo'] != '{{ACF.vimeo}}') {
		$url = $atts['vimeo'];
		$lity = 'data-lity ';
	} else {
		$url = $atts['download'];
	}

	if(wp_get_attachment_thumb_url($atts['featured_image'])) {
		$thumb = wp_get_attachment_thumb_url($atts['featured_image']);
	} else {
		$thumb = $atts['featured_media'];
	}

	$o = '<a href="'.$url.'" '.$lity.'target="_blank"><img src="'.$thumb.'" class="mx-auto mb-2"></a>';
	$o .= '<p class="pb-3"><a href="'.$url.'" target="_blank">'.$atts['title'].'</a></p>';

    // return output
    return $o;
}
add_shortcode( 'resource_link', 'shortcode_resource_link' );


add_filter('template_redirect', 'wpse51205_content');
function wpse51205_content() {
	global $post;

	$rcp_user_level = get_post_meta( $post->id, 'rcp_user_level', true );
	if( ! current_user_can( 'read' ) && $rcp_user_level == 'Subscriber' ) {
		wp_redirect( get_home_url() );
        exit();
	}
}

add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
	if (!current_user_can('administrator') && !is_admin()) {
		show_admin_bar(false);
	}
}

add_filter('frm_redirect_url', 'return_page', 9, 3);
function return_page($url, $form, $params){
	if($form->id == 2){ // Register Form
	    if($_POST['item_meta'][2305]==1){
	        $url = $_POST['item_meta'][2321];
	    }else{
    		$customer = rcp_get_customer_by_user_id($_POST['item_meta'][53]); // User ID Field (53)
    		if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
    			$url = "/welcome-pharmacist/";
    		} else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
    			$url = "/welcome/";
    		}
	    }
	} else if($form->id == 22){ // Registration - Hydralyte comp Form
		$customer = rcp_get_customer_by_user_id($_POST['item_meta'][630]); // User ID Field (146)
		if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
			$url = "/welcome-pharmacist/";
		} else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
			$url = "/welcome/";
		}
	} else if($values['form_id'] == 54) { // Registration - Therapy Area Form
		$customer = rcp_get_customer_by_user_id($_POST['item_meta'][1510]); // User ID Field (146)
		if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
			$url = "/welcome-pharmacist/";
		} else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
			$url = "/welcome/";
		}
	}
	return $url;
}
add_action('frm_after_create_entry', 'rcp_register_membership', 30, 2);
function rcp_register_membership($entry_id, $form_id){
	if($form_id == 2){ // Register Form
		// create customer from $user_id
 		if(rcp_get_customer_by_user_id($_POST['item_meta'][53])) {
			$customer = rcp_get_customer_by_user_id($_POST['item_meta'][53]);
			$customer_id = $customer->get_id();
		} else {
			$customer_id = rcp_add_customer( array(
				'user_id' => $_POST['item_meta'][53] // User ID Field (53)
			));
		}
 		
		if( substr(strtolower($_POST['item_meta'][8]), 0, 3) == 'pha' ) { // AHPRA Number Field (8)
			$subscription_id = 54; //Pharmacist
		} else {
			$subscription_id = 22; //HCP
		}
// 		error_log(print_r($_POST['item_meta'][53], true));
// 		error_log(print_r($subscription_id, true));
		// add membership to new customer
		rcp_add_membership( array(
			'customer_id' => $customer_id,
			'object_id'   => $subscription_id,
			'status'      => 'active'
		));
// 		error_log(print_r($test, true));
	} else if($form_id == 22){ // Registration - Hydralyte comp Form
		if($_POST['item_meta'][918] == 'Yes') {
			// create customer from $user_id
// 			$customer_id = rcp_add_customer( array(
// 				'user_id' => $_POST['item_meta'][630] // User ID Field (146)
// 			));
			if(rcp_get_customer_by_user_id($_POST['item_meta'][630])) {
				$customer = rcp_get_customer_by_user_id($_POST['item_meta'][630]);
				$customer_id = $customer->get_id();
			} else {
				$customer_id = rcp_add_customer( array(
					'user_id' => $_POST['item_meta'][630] // User ID Field (53)
				));
			}
			if( substr(strtolower($_POST['item_meta'][358]), 0, 3) == 'pha' ) { // AHPRA Number Field (8)
				$subscription_id = 54; //Pharmacist
			} else {
				$subscription_id = 22; //HCP
			}
			// add membership to new customer
			rcp_add_membership( array(
				'customer_id' => $customer_id,
				'object_id'   => $subscription_id,
				'status'      => 'active'
			));
		}
	} else if($form_id == 54) { // Registration - Therapy Area Form
		if($_POST['item_meta'][1286] == 'Yes') {
			if(rcp_get_customer_by_user_id($_POST['item_meta'][1510])) {
				$customer = rcp_get_customer_by_user_id($_POST['item_meta'][1510]);
				$customer_id = $customer->get_id();
			} else {
				$customer_id = rcp_add_customer( array(
					'user_id' => $_POST['item_meta'][1510] // User ID Field (1510)
				));
			}
			if( substr(strtolower($_POST['item_meta'][1222]), 0, 3) == 'pha' ) { // AHPRA Number Field (8)
				$subscription_id = 54; //Pharmacist
			} else {
				$subscription_id = 22; //HCP
			}
			// add membership to new customer
			rcp_add_membership( array(
				'customer_id' => $customer_id,
				'object_id'   => $subscription_id,
				'status'      => 'active'
			));
		}	
	} else if($form_id == 129){ 
		if(rcp_get_customer_by_user_id($_POST['item_meta'][4065])) {
			$customer = rcp_get_customer_by_user_id($_POST['item_meta'][4065]);
			$customer_id = $customer->get_id();
		} else {
			$customer_id = rcp_add_customer( array(
				'user_id' => $_POST['item_meta'][4065] // User ID Field (53)
			));
		}
 		
		if( substr(strtolower($_POST['item_meta'][3989]), 0, 3) == 'pha' ) { // AHPRA Number Field (8)
			$subscription_id = 54; //Pharmacist
		} else {
			$subscription_id = 22; //HCP
		}
		
		rcp_add_membership( array(
			'customer_id' => $customer_id,
			'object_id'   => $subscription_id,
			'status'      => 'active'
		));
	} else if($form_id == 145){ 
		
		
		$username_field_id  = 4337;
		$password_field_id  = 4353;
		
		// Formidable stores field values in the $args array on this hook.
		$username = $_POST['item_meta'][$username_field_id] ?? '';
		$password = $_POST['item_meta'][$password_field_id] ?? '';

		if ( empty($username) || empty($password) ) {
			// Should not happen if validation passed, but good for safety
			return;
		}
		
		// 3. Attempt to authenticate the user
		// wp_signon returns a WP_User object on success or a WP_Error object on failure.
		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true // Set to true to keep the user logged in
		);
		
		$user = wp_signon( $creds, is_ssl() );

		// 4. Handle the login result
		if ( is_wp_error( $user ) ) {
			// Log the error for debugging purposes if needed
			error_log( 'Formidable Forms Auto-Login Failed: ' . $user->get_error_message() );
			
			// **IMPORTANT:** Do NOT return an error message to the user here.
			// The form submission has already been recorded as successful.
			return;
		}
		
		// 5. Set the current user globally and fire the login action
		// This completes the auto-login process.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		
	}
}

/**
 * Add new export column for "Referrer".
 * 
 * @param array $columns Default column headers.
 * 
 * @return array
 */
function ag_rcp_members_export_referrer_header( $columns ) {
    $columns['ahpra_id'] = __( 'AHPRA Number' );
	$columns['racgp_number'] = __( 'RACGP number' );
	$columns['practice_name'] = __( 'Practice Name' );
	$columns['phone_number'] = __( 'Phone Number' );
	$columns['address'] = __( 'Address' );
	$columns['specialty'] = __( 'Specialty' );
	$columns['hear_us'] = __( 'How did you hear about us?' );
    return $columns;
}
add_filter( 'rcp_export_csv_cols_members', 'ag_rcp_members_export_referrer_header' );

function custom_rcp_login_redirect_url( $redirect, $user ) {
	if(rcp_user_has_active_membership($user->ID)) {
		$customer = rcp_get_customer_by_user_id($user->ID);
	} else {
		return "/welcome/";
	}
	
    if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
		$redirect = "/welcome-pharmacist/";
	} else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
		$redirect = "/welcome/";
	}
	return $redirect;
}
add_filter( 'rcp_login_redirect_url', 'custom_rcp_login_redirect_url', 10, 2 );

function custom_rcp_return_url( $redirect, $user_id ) {
	$customer = rcp_get_customer_by_user_id($user_id);
	
    if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
		$redirect = "/welcome-pharmacist/";
	} else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
		$redirect = "/welcome/";
	}
	return $redirect;
}
add_filter( 'rcp_return_url', 'custom_rcp_return_url', 10, 2 );

/**
 * Adds the custom fields to the registration form and profile editor
 *
 */
function pw_rcp_add_user_fields() {
	
	$ahpra_id = get_user_meta( get_current_user_id(), 'ahpra_id', true );
	$racgp_number = get_user_meta( get_current_user_id(), 'racgp_number', true );
	$practice_name = get_user_meta( get_current_user_id(), 'practice_name', true );
	$phone_number = get_user_meta( get_current_user_id(), 'phone_number', true );
	$specialty = get_user_meta( get_current_user_id(), 'specialty', true );
	$hear_us = get_user_meta( get_current_user_id(), 'hear_us', true );
	$unit_suite_number = get_user_meta( get_current_user_id(), 'unit_suite_number', true );
	$street_address = get_user_meta( get_current_user_id(), 'street_address', true );
	$city = get_user_meta( get_current_user_id(), 'city', true );
	$suburb = get_user_meta( get_current_user_id(), 'suburb', true );
	$state = get_user_meta( get_current_user_id(), 'state', true );
	$zip = get_user_meta( get_current_user_id(), 'zip', true );
	$lat = get_user_meta( get_current_user_id(), 'lat', true );
	$long = get_user_meta( get_current_user_id(), 'long', true );
	$country = get_user_meta( get_current_user_id(), 'country', true );
	?>
	<p>
		<label for="ahpra_id"><?php _e( 'AHPRA Number', 'rcp' ); ?></label>
		<input name="ahpra_id" id="ahpra_id" type="text"  readonly disabled value="<?php echo esc_attr( $ahpra_id ); ?>"/>
	</p>
	<p>
		<label for="racgp_number"><?php _e( 'RACGP Number', 'rcp' ); ?></label>
		<input name="racgp_number" id="racgp_number" type="text"  value="<?php echo esc_attr( $racgp_number ); ?>"/>
	</p>
	<p>
		<label for="practice_name"><?php _e( 'Practice Name', 'rcp' ); ?></label>
		<input name="practice_name" id="practice_name" type="text" value="<?php echo esc_attr( $practice_name ); ?>"/>
	</p>
	<p>
		<label for="phone_number"><?php _e( 'Phone Number', 'rcp' ); ?></label>
		<input name="phone_number" id="phone_number" type="tel" value="<?php echo esc_attr( $phone_number ); ?>"/>
	</p>
	<div class="md:grid grid-cols-2 gap-x-6">
		<p>
			<label for="form-unit-suite-number"><?php _e( 'Unit/suite number', 'rcp' ); ?></label>
			<input name="unit_suite_number" id="form-unit-suite-number" type="text" value="<?php echo esc_attr( $unit_suite_number ); ?>"/>
		</p>
		<p>
			<label for="form-street_address"><?php _e( 'Street Address', 'rcp' ); ?></label>
			<input name="street_address" id="form-street_address" type="text" value="<?php echo esc_attr( $street_address ); ?>"/>
		</p>
	</div>
	<div class="md:grid grid-cols-3 gap-x-6">
		<p>
			<label for="form-suburb"><?php _e( 'Suburb', 'rcp' ); ?></label>
			<input name="suburb" id="form-suburb" type="text" value="<?php echo esc_attr( $suburb ); ?>"/>
		</p>
		<p>
			<label for="form-state"><?php _e( 'State', 'rcp' ); ?></label>
			<input name="state" id="form-state" type="text" value="<?php echo esc_attr( $state ); ?>"/>
		</p>
		<p>
			<label for="form-zip"><?php _e( 'Zip Code', 'rcp' ); ?></label>
			<input name="zip" id="form-zip" type="text" value="<?php echo esc_attr( $zip ); ?>"/>
		</p>
	</div>
	<div class="md:grid grid-cols-2 gap-x-6 sr-only invisible">
		<p>
			<label for="form-lat"><?php _e( 'Lat', 'rcp' ); ?></label>
			<input name="lat" id="form-lat" type="text" value="<?php echo esc_attr( $lat ); ?>"/>
		</p>
		<p>
			<label for="form-long"><?php _e( 'Long', 'rcp' ); ?></label>
			<input name="long" id="form-long" type="text" value="<?php echo esc_attr( $long ); ?>"/>
		</p>
	</div>
	<p>
		<label for="country"><?php _e( 'Country', 'rcp' ); ?></label>
		<input name="country" id="form-country" type="text" value="<?php echo esc_attr( $country ); ?>"/>
	</p>
    <p>
        <label for="specialty"><?php _e( 'Specialty', 'rcp' ); ?></label>
        <select id="specialty" name="specialty">
			<option disabled selected value> -- select an option -- </option>
            <option value="allergist" <?php selected( $specialty, 'allergist'); ?>><?php _e( 'Allergist', 'rcp' ); ?></option>
            <option value="colorectal surgeon" <?php selected( $specialty, 'colorectal surgeon'); ?>><?php _e( 'Colorectal Surgeon', 'rcp' ); ?></option>
            <option value="dietician" <?php selected( $specialty, 'dietician'); ?>><?php _e( 'Dietician', 'rcp' ); ?></option>
            <option value="ent" <?php selected( $specialty, 'ent'); ?>><?php _e( 'ENT', 'rcp' ); ?></option>
			<option value="gastroenterologist" <?php selected( $specialty, 'gastroenterologist'); ?>><?php _e( 'Gastroenterologist', 'rcp' ); ?></option>
			<option value="general practitioner" <?php selected( $specialty, 'general practitioner'); ?>><?php _e( 'General Practitioner', 'rcp' ); ?></option>
			<option value="gynaecologist & obstetrician" <?php selected( $specialty, 'gynaecologist & obstetrician'); ?>><?php _e( 'Gynaecologist & Obstetrician', 'rcp' ); ?></option>
			<option value="midwife" <?php selected( $specialty, 'midwife'); ?>><?php _e( 'Midwife', 'rcp' ); ?></option>
			<option value="nurse" <?php selected( $specialty, 'nurse'); ?>><?php _e( 'Nurse', 'rcp' ); ?></option>
			<option value="paediatrician" <?php selected( $specialty, 'paediatrician'); ?>><?php _e( 'Paediatrician', 'rcp' ); ?></option>
			<option value="physiotherapist" <?php selected( $specialty, 'physiotherapist'); ?>><?php _e( 'Physiotherapist', 'rcp' ); ?></option>
			<option value="practice manager" <?php selected( $specialty, 'practice manager'); ?>><?php _e( 'Practice Manager', 'rcp' ); ?></option>
			<option value="sports physician" <?php selected( $specialty, 'sports physician'); ?>><?php _e( 'Sports Physician', 'rcp' ); ?></option>
        </select>
    </p>
	<p>
        <label for="hear_us"><?php _e( 'How did you hear about us?', 'rcp' ); ?></label>
        <select id="hear_us" name="hear_us">
			<option disabled selected value> -- select an option -- </option>
            <option value="conference" <?php selected( $hear_us, 'conference'); ?>><?php _e( 'Conference', 'rcp' ); ?></option>
			<option value="advertising" <?php selected( $hear_us, 'advertising'); ?>><?php _e( 'Advertising', 'rcp' ); ?></option>
			<option value="direct mail" <?php selected( $hear_us, 'direct mail'); ?>><?php _e( 'Direct Mail', 'rcp' ); ?></option>
			<option value="training" <?php selected( $hear_us, 'training'); ?>><?php _e( 'Training', 'rcp' ); ?></option>
			<option value="other" <?php selected( $hear_us, 'other'); ?>><?php _e( 'Other', 'rcp' ); ?></option>
        </select>
    </p>
	<?php
}
add_action( 'rcp_after_password_registration_field', 'pw_rcp_add_user_fields' );
add_action( 'rcp_profile_editor_after', 'pw_rcp_add_user_fields' );

/**
 * Adds the custom fields to the member edit screen
 *
 */
function pw_rcp_add_member_edit_fields( $user_id = 0 ) {
	
	$ahpra_id = get_user_meta( $user_id, 'ahpra_id', true );
	$racgp_number = get_user_meta( $user_id, 'racgp_number', true );
	$practice_name = get_user_meta( $user_id, 'practice_name', true );
	$phone_number = get_user_meta( $user_id, 'phone_number', true );
	$specialty = get_user_meta( $user_id, 'specialty', true );
	$hear_us = get_user_meta( $user_id, 'hear_us', true );
	$unit_suite_number = get_user_meta( get_current_user_id(), 'unit_suite_number', true );
	$street_address = get_user_meta( get_current_user_id(), 'street_address', true );
	$city = get_user_meta( get_current_user_id(), 'city', true );
	$state = get_user_meta( get_current_user_id(), 'state', true );
	$zip = get_user_meta( get_current_user_id(), 'zip', true );
	$lat = get_user_meta( get_current_user_id(), 'lat', true );
	$long = get_user_meta( get_current_user_id(), 'long', true );
	$country = get_user_meta( get_current_user_id(), 'country', true );
	?>
	<tr valign="top">
		<th scope="row" valign="top">
			<label for="ahpra_id"><?php _e( 'AHPRA Number', 'rcp' ); ?></label>
		</th>
		<td>
			<input name="ahpra_id" id="ahpra_id" type="text" value="<?php echo esc_attr( $ahpra_id ); ?>"/>
			<p class="description"><?php _e( 'The member\'s AHPRA Number', 'rcp' ); ?></p>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" valign="top">
			<label for="racgp_number"><?php _e( 'RCCGP Number', 'rcp' ); ?></label>
		</th>
		<td>
			<input name="racgp_number" id="racgp_number" type="text" value="<?php echo esc_attr( $racgp_number ); ?>"/>
			<p class="description"><?php _e( 'The member\'s RCCGP Number', 'rcp' ); ?></p>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" valign="top">
			<label for="practice_name"><?php _e( 'Practice Name', 'rcp' ); ?></label>
		</th>
		<td>
			<input name="practice_name" id="practice_name" type="text" value="<?php echo esc_attr( $practice_name ); ?>"/>
			<p class="description"><?php _e( 'Practice Name', 'rcp' ); ?></p>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" valign="top">
			<label for="phone_number"><?php _e( 'Phone Number', 'rcp' ); ?></label>
		</th>
		<td>
			<input name="phone_number" id="phone_number" type="text" value="<?php echo esc_attr( $phone_number ); ?>"/>
			<p class="description"><?php _e( 'Phone Number', 'rcp' ); ?></p>
		</td>
	</tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-unit-suite-number"><?php _e( 'Unit/suite number', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="unit_suite_number" id="form-unit-suite-number" type="text" value="<?php echo esc_attr( $unit_suite_number ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-street_address"><?php _e( 'Street Address', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="street_address" id="form-street_address" type="text" value="<?php echo esc_attr( $street_address ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-city"><?php _e( 'City', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="city" id="form-city" type="text" value="<?php echo esc_attr( $city ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-state"><?php _e( 'State', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="state" id="form-state" type="text" value="<?php echo esc_attr( $state ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-zip"><?php _e( 'Zip Code', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="zip" id="form-zip" type="text" value="<?php echo esc_attr( $zip ); ?>"/>
        </td>
    </tr>
	<tr valign="top" class="sr-only invisible">
        <th scope="row" valign="top">
            <label for="form-lat"><?php _e( 'Lat', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="lat" id="form-lat" type="text" value="<?php echo esc_attr( $lat ); ?>"/>
        </td>
    </tr>
	<tr valign="top" class="sr-only invisible">
        <th scope="row" valign="top">
            <label for="form-long"><?php _e( 'Long', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="long" id="form-long" type="text" value="<?php echo esc_attr( $long ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="form-country"><?php _e( 'Country', 'rcp' ); ?></label>
        </th>
        <td>
			<input name="country" id="form-country" type="text" value="<?php echo esc_attr( $country ); ?>"/>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="specialty"><?php _e( 'Specialty', 'rcp' ); ?></label>
        </th>
        <td>
            <select id="specialty" name="specialty">
				<option disabled selected value> -- select an option -- </option>
				<option value="allergist" <?php selected( $specialty, 'allergist'); ?>><?php _e( 'Allergist', 'rcp' ); ?></option>
				<option value="colorectal surgeon" <?php selected( $specialty, 'colorectal surgeon'); ?>><?php _e( 'Colorectal Surgeon', 'rcp' ); ?></option>
				<option value="dietician" <?php selected( $specialty, 'dietician'); ?>><?php _e( 'Dietician', 'rcp' ); ?></option>
				<option value="ent" <?php selected( $specialty, 'ent'); ?>><?php _e( 'ENT', 'rcp' ); ?></option>
				<option value="gastroenterologist" <?php selected( $specialty, 'gastroenterologist'); ?>><?php _e( 'Gastroenterologist', 'rcp' ); ?></option>
				<option value="general practitioner" <?php selected( $specialty, 'general practitioner'); ?>><?php _e( 'General Practitioner', 'rcp' ); ?></option>
				<option value="gynaecologist & obstetrician" <?php selected( $specialty, 'gynaecologist & obstetrician'); ?>><?php _e( 'Gynaecologist & Obstetrician', 'rcp' ); ?></option>
				<option value="midwife" <?php selected( $specialty, 'midwife'); ?>><?php _e( 'Midwife', 'rcp' ); ?></option>
				<option value="nurse" <?php selected( $specialty, 'nurse'); ?>><?php _e( 'Nurse', 'rcp' ); ?></option>
				<option value="paediatrician" <?php selected( $specialty, 'paediatrician'); ?>><?php _e( 'Paediatrician', 'rcp' ); ?></option>
				<option value="physiotherapist" <?php selected( $specialty, 'physiotherapist'); ?>><?php _e( 'Physiotherapist', 'rcp' ); ?></option>
				<option value="practice manager" <?php selected( $specialty, 'practice manager'); ?>><?php _e( 'Practice Manager', 'rcp' ); ?></option>
				<option value="sports physician" <?php selected( $specialty, 'sports physician'); ?>><?php _e( 'Sports Physician', 'rcp' ); ?></option>
            </select>
        </td>
    </tr>
	<tr valign="top">
        <th scope="row" valign="top">
            <label for="hear_us"><?php _e( 'How did you hear about us?', 'rcp' ); ?></label>
        </th>
        <td>
            <select id="hear_us" name="hear_us">
				<option disabled selected value> -- select an option -- </option>
				<option value="conference" <?php selected( $hear_us, 'conference'); ?>><?php _e( 'Conference', 'rcp' ); ?></option>
				<option value="advertising" <?php selected( $hear_us, 'advertising'); ?>><?php _e( 'Advertising', 'rcp' ); ?></option>
				<option value="direct mail" <?php selected( $hear_us, 'direct mail'); ?>><?php _e( 'Direct Mail', 'rcp' ); ?></option>
				<option value="training" <?php selected( $hear_us, 'training'); ?>><?php _e( 'Training', 'rcp' ); ?></option>
				<option value="other" <?php selected( $hear_us, 'other'); ?>><?php _e( 'Other', 'rcp' ); ?></option>
            </select>
        </td>
    </tr>
	<?php
}
add_action( 'rcp_edit_member_after', 'pw_rcp_add_member_edit_fields' );

/**
 * Determines if there are problems with the registration data submitted
 *
 */
function pw_rcp_validate_user_fields_on_register( $posted ) {

	if ( is_user_logged_in() ) {
		return;
	}

	if( empty( $posted['ahpra_id'] ) ) {
		rcp_errors()->add( 'invalid_ahpra_id', __( 'Please enter your AHPRA Number', 'rcp' ), 'register' );
	}
	/*
	if(in_array(site_url(), ['https://carepharma.theteamserver.com', 'http://carepharma.theteamserver.com'])) {
		$ahpra_data = check_if_aphra_exists_soap($posted['ahpra_id']);
	}
	else {
		$ahpra_data = check_if_aphra_exists_curl($posted['ahpra_id']);
	}

	if(isset($ahpra_data) && $ahpra_data['success'] == true && $ahpra_data['data']->AuditDetails->PractitionerCount > 0){
		// add ahpra_id to database
		// $_POST['item_meta'][94] = $_POST['item_meta'][8]; // ahpra_id
		// $_POST['item_meta'][95] = json_encode($ahpra_data['data']->ProfessionNumberReplay->Practitioner); // ahpra_details
	}
	else {
		rcp_errors()->add( 'invalid_ahpra_id', (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!", 'register' );
		// $errors['_error'] = (isset($ahpra_data['message']) && $ahpra_data['message'] != '') ? $ahpra_data['message'] : "Invalid Professional Number!";
	}
	*/
	if( empty( $posted['practice_name'] ) ) {
		rcp_errors()->add( 'invalid_practice_name', __( 'Please enter your Practice Name', 'rcp' ), 'register' );
	}

	if( empty( $posted['phone_number'] ) ) {
		rcp_errors()->add( 'invalid_phone_number', __( 'Please enter your Phone Number', 'rcp' ), 'register' );
	}

	if( empty( $posted['unit_suite_number'] ) ) {
		rcp_errors()->add( 'invalid_unit_suite_number', __( 'Please enter your Unit/suite number', 'rcp' ), 'register' );
	}

	if( empty( $posted['street_address'] ) ) {
		rcp_errors()->add( 'invalid_address', __( 'Please enter your Address', 'rcp' ), 'register' );
	}

	if( empty( $posted['city'] ) ) {
		rcp_errors()->add( 'invalid_city', __( 'Please enter your City', 'rcp' ), 'register' );
	}

	if( empty( $posted['state'] ) ) {
		rcp_errors()->add( 'invalid_state', __( 'Please enter your State', 'rcp' ), 'register' );
	}

	if( empty( $posted['zip'] ) ) {
		rcp_errors()->add( 'invalid_zip', __( 'Please enter your Zip Code', 'rcp' ), 'register' );
	}

	if( empty( $posted['country'] ) ) {
		rcp_errors()->add( 'invalid_country', __( 'Please enter your Country', 'rcp' ), 'register' );
	}
	$specialty_choices = array(
        'allergist',
        'colorectal surgeon',
        'dietician',
        'ent',
		'gastroenterologist',
		'general practitioner',
		'gynaecologist & obstetrician',
		'midwife',
		'nurse',
		'paediatrician',
		'physiotherapist',
		'practice manager',
		'sports physician',
    );

    if ( ! in_array( $posted['specialty'], $specialty_choices ) ) {
        rcp_errors()->add( 'invalid_specialty', __( 'Please select a valid Specialty', 'rcp' ), 'register' );
    }

	$hear_us_choices = array(
        'conference',
        'advertising',
        'direct mail',
        'training',
		'other',
    );

    if ( ! in_array( $posted['hear_us'], $hear_us_choices ) ) {
        rcp_errors()->add( 'invalid_hear_us', __( 'Please select a valid choice on How did you hear about us', 'rcp' ), 'register' );
    }
}
add_action( 'rcp_form_errors', 'pw_rcp_validate_user_fields_on_register', 10 );

/**
 * Stores the information submitted during registration
 *
 */
function pw_rcp_save_user_fields_on_register( $posted, $user_id, $price, $payment_id, $customer, $membership_id ) {

	if( ! empty( $posted['ahpra_id'] ) ) {
		update_user_meta( $user_id, 'ahpra_id', sanitize_text_field( $posted['ahpra_id'] ) );

		// if( substr(strtolower($posted['ahpra_id']), 0, 3) == 'pha' ) {
		// 	$membership_id = 3;
		// } else {
		// 	$membership_id = 3;
		// }
		// rcp_add_membership( array(
		// 	'customer_id' => $customer->get_id(),
		// 	'object_id' => 3,
		// 	'status' => 'active'
		// ) );
	}

	if( ! empty( $posted['practice_name'] ) ) {
		update_user_meta( $user_id, 'practice_name', sanitize_text_field( $posted['practice_name'] ) );
	}

	if( ! empty( $posted['phone_number'] ) ) {
		update_user_meta( $user_id, 'phone_number', sanitize_text_field( $posted['phone_number'] ) );
	}

	if ( ! empty( $posted['address'] ) ) {
        update_user_meta( $user_id, 'address', wp_filter_nohtml_kses( $posted['address'] ) );
    }

	if ( ! empty( $posted['specialty'] ) ) {
        update_user_meta( $user_id, 'specialty', sanitize_text_field( $posted['specialty'] ) );
    }

	if ( ! empty( $posted['hear_us'] ) ) {
        update_user_meta( $user_id, 'hear_us', sanitize_text_field( $posted['hear_us'] ) );
    }

	if ( ! empty( $posted['unit_suite_number'] ) ) {
        update_user_meta( $user_id, 'unit_suite_number', sanitize_text_field( $posted['unit_suite_number'] ) );
    }

	if ( ! empty( $posted['street_address'] ) ) {
        update_user_meta( $user_id, 'street_address', sanitize_text_field( $posted['street_address'] ) );
    }

	if ( ! empty( $posted['city'] ) ) {
        update_user_meta( $user_id, 'city', sanitize_text_field( $posted['city'] ) );
    }

	if ( ! empty( $posted['state'] ) ) {
        update_user_meta( $user_id, 'state', sanitize_text_field( $posted['state'] ) );
    }

	if ( ! empty( $posted['zip'] ) ) {
        update_user_meta( $user_id, 'zip', sanitize_text_field( $posted['zip'] ) );
    }
	
	if ( ! empty( $posted['lat'] ) ) {
        update_user_meta( $user_id, 'lat', sanitize_text_field( $posted['lat'] ) );
    }
	
	if ( ! empty( $posted['long'] ) ) {
        update_user_meta( $user_id, 'long', sanitize_text_field( $posted['long'] ) );
    }
	
	if ( ! empty( $posted['country'] ) ) {
        update_user_meta( $user_id, 'country', sanitize_text_field( $posted['country'] ) );
    }
}
// add_action( 'rcp_form_processing', 'pw_rcp_save_user_fields_on_register', 10, 6 );


/**
 * Stores the information submitted profile update
 *
 */
function pw_rcp_save_user_fields_on_profile_save( $user_id ) {

	if( ! empty( $_POST['ahpra_id'] ) ) {
		update_user_meta( $user_id, 'ahpra_id', sanitize_text_field( $_POST['ahpra_id'] ) );
	}

	//if( ! empty( $_POST['racgp_number'] ) ) {
		update_user_meta( $user_id, 'racgp_number', sanitize_text_field( $_POST['racgp_number'] ) );
	//}

	if( ! empty( $_POST['practice_name'] ) ) {
		update_user_meta( $user_id, 'practice_name', sanitize_text_field( $_POST['practice_name'] ) );
	}

	if( ! empty( $_POST['phone_number'] ) ) {
		update_user_meta( $user_id, 'phone_number', sanitize_text_field( $_POST['phone_number'] ) );
	}

	if ( ! empty( $_POST['unit_suite_number'] ) ) {
        update_user_meta( $user_id, 'unit_suite_number', sanitize_text_field( $_POST['unit_suite_number'] ) );
    }

	if ( ! empty( $_POST['street_address'] ) ) {
        update_user_meta( $user_id, 'street_address', sanitize_text_field( $_POST['street_address'] ) );
    }

	if ( ! empty( $_POST['city'] ) ) {
        update_user_meta( $user_id, 'city', sanitize_text_field( $_POST['city'] ) );
    }

	if ( ! empty( $_POST['state'] ) ) {
        update_user_meta( $user_id, 'state', sanitize_text_field( $_POST['state'] ) );
    }

	if ( ! empty( $_POST['zip'] ) ) {
        update_user_meta( $user_id, 'zip', sanitize_text_field( $_POST['zip'] ) );
    }
	
	if ( ! empty( $_POST['lat'] ) ) {
        update_user_meta( $user_id, 'lat', sanitize_text_field( $_POST['lat'] ) );
    }
	
	if ( ! empty( $_POST['long'] ) ) {
        update_user_meta( $user_id, 'long', sanitize_text_field( $_POST['long'] ) );
    }
	
	if ( ! empty( $_POST['country'] ) ) {
        update_user_meta( $user_id, 'country', sanitize_text_field( $_POST['country'] ) );
    }
	
	$specialty_choices = array(
        'allergist',
        'colorectal surgeon',
        'dietician',
        'ent',
		'gastroenterologist',
		'general practitioner',
		'gynaecologist & obstetrician',
		'midwife',
		'nurse',
		'paediatrician',
		'physiotherapist',
		'practice manager',
		'sports physician',
    );
	if ( isset( $_POST['specialty'] ) && in_array( $_POST['specialty'], $specialty_choices ) ) {
        update_user_meta( $user_id, 'specialty', sanitize_text_field( $_POST['specialty'] ) );
    }

	$hear_us_choices = array(
        'conference',
        'advertising',
        'direct mail',
        'training',
		'other',
    );
	if ( isset( $_POST['hear_us'] ) && in_array( $_POST['hear_us'], $hear_us_choices ) ) {
        update_user_meta( $user_id, 'hear_us', sanitize_text_field( $_POST['hear_us'] ) );
    }
}
add_action( 'rcp_user_profile_updated', 'pw_rcp_save_user_fields_on_profile_save', 10 );
add_action( 'rcp_edit_member', 'pw_rcp_save_user_fields_on_profile_save', 10 );

function get_request_parameter( $key, $default = '' ) {
    // If not request set
    if ( ! isset( $_REQUEST[ $key ] ) || empty( $_REQUEST[ $key ] ) ) {
        return $default;
    }
 
    // Set so process it
    return strip_tags( (string) wp_unslash( $_REQUEST[ $key ] ) );
}

if (! function_exists('resources_fn')) :
	function resources_fn()
	{
		$area = get_request_parameter('area');
		$audience = get_request_parameter('audience');
		$brand = get_request_parameter('brand');
		$id = rcp_get_customer_membership_level_ids();
		$id = isset($id[0]) ? $id[0] : 0;

		ob_start(); ?>
		<div class="grid md:grid-cols-4">
			<div class="md:pr-base">
				<a href="/support" class="w-full block border-b border-stroke">
					<span class="inline-block text-heading pb-base <?php echo (empty($area) && empty($audience) && empty($brand)) ? "border-b-2 border-heading" : "" ?>">View all</span>
				</a>
				<h5>
					Therapy Area
				</h5>
				<ul class="list-none border-b border-stroke mb-base">
					<?php
					$terms = get_terms(array(
						'taxonomy' => 'therapy_area',
						'order' => 'DESC',
						'orderby' => 'ID',
						'hide_empty' => true,
					));
					if ($terms) {
						foreach ($terms as $term) {
							$btn_class = 'text-heading';
							if ($area == $term->slug) {
								$btn_class = 'text-accent';
							}
							echo '<li class=""><a class="inline-block no-underline ' . $btn_class . '" href="/resources/?area=' . $term->slug . '">' . $term->name . '</a></li>';
						}
					}
					?>
				</ul>
				<h5>
					Audience
				</h5>
				<ul class="list-none border-b border-stroke mb-base">
					<?php
					$terms = get_terms(array(
						'taxonomy' => 'audience',
						'order' => 'DESC',
						'orderby' => 'ID',
						'hide_empty' => true,
					));
					if ($terms) {
						foreach ($terms as $term) {
							$btn_class = 'text-heading';
							if ($audience == $term->slug) {
								$btn_class = 'text-accent';
							}
							echo '<li class=""><a class="inline-block no-underline ' . $btn_class . '" href="/resources/?audience=' . $term->slug . '">' . $term->name . '</a></li>';
						}
					}
					?>
				</ul>
				<h5>
					Brand
				</h5>
				<ul class="list-none mb-base">
					<?php
					$terms = get_terms(array(
						'taxonomy' => 'brand',
						'order' => 'DESC',
						'orderby' => 'ID',
						'hide_empty' => true,
					));
					if ($terms) {
						foreach ($terms as $term) {
							$btn_class = 'text-heading';
							if ($brand == $term->slug) {
								$btn_class = 'text-accent';
							}
							echo '<li class=""><a class="inline-block no-underline ' . $btn_class . '" href="/resources/?brand=' . $term->slug . '">' . $term->name . '</a></li>';
						}
					}
					?>
				</ul>
			</div>
			<div class="md:col-span-3">
				<?php
				$args = [
					'post_type' => 'resources',
					'orderby' => 'id',
					'order' => 'desc',
					'post_status' => 'publish',
					'posts_per_page' => -1,
				];

				if ($area) {
					$args['tax_query'] = array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'therapy_area',
							'field' => 'slug',
							'terms' => $area
						)
					);
				}
				if ($audience) {
					$args['tax_query'] = array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'audience',
							'field' => 'slug',
							'terms' => $audience
						)
					);
				}
				if ($brand) {
					$args['tax_query'] = array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'brand',
							'field' => 'slug',
							'terms' => $brand
						)
					);
				}

				$query  = new WP_Query($args);

				if ($query->have_posts()): ?>
					<div id="postgrid" class="grid lg:grid-cols-3 md:grid-cols-2">
						<?php while ($query->have_posts()) {
							$query->the_post();
					
							if( rcp_user_can_access( get_current_user_id(), get_the_ID() ) ) {
								$vidIcon = '';
								$url = '';
								$lity = '';
								$linkName = '';
								$dload = get_field('download');
								$vid = get_field('vimeo');
								$tool = get_field('tool');
								//audience
								$term_obj_list = get_the_terms($query->post->ID, 'audience');
								$audience = join(', ', wp_list_pluck($term_obj_list, 'name'));
								if ($vid) {
									$lity = 'data-lity ';
									$vidIcon = '<svg class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2" width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
												<circle cx="25" cy="25" r="25" fill="white" fill-opacity="0.7"/>
												<path d="M37.4331 24.1265C38.1172 24.5079 38.1172 25.4921 37.4331 25.8735L18.7369 36.2955C18.0703 36.6671 17.25 36.1852 17.25 35.422L17.25 14.578C17.25 13.8148 18.0703 13.3329 18.7369 13.7045L37.4331 24.1265Z" fill="#35B1C9"/>
												</svg>';
									$linkName = 'Watch Video';
									$url = $vid;
								}
								if ($dload) {
									$linkName = 'View Resource';
									$url = $dload['url'];
								}
								if ($tool) {
									$linkName = 'Try Now';
									$url = $tool;
								}

								$thumb = wp_get_attachment_url(get_post_thumbnail_id($query->post->ID));
							?>
								<a class="page-item no-underline hidden" href="<?php echo $url; ?>" <?php echo $lity; ?> target="_blank">
									<div class="card h-full overflow-hidden">
										<div class="bg-secondary p-md h-48 rounded-t relative">
											<?php echo $vidIcon; ?>
											<img src="<?php echo $thumb; ?>" class="h-full object-contain mx-auto" alt="" />
										</div>
										<div class="card-body">
											<div>
												<p class="text-sm text-black"><?php echo $audience; ?></p>
												<h5 class="min-h-14"><?php echo get_the_title(); ?></h5>
											</div>
											<span class="underline text-accent font-semibold"><?php echo $linkName; ?></span>
										</div>
									</div>
								</a>
							<?php }
							  }
							wp_reset_postdata(); 
						?>
					</div>
				<?php endif; ?>
				<div class="text-center">
					<button class="btn cta show-more hidden">
						Show more
					</button>
				</div>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($){
				var container = $("#postgrid");
				var show9 = function() {
					var c = container.children('.hidden');
					if(c.length <= 9) {
						$('.show-more').addClass('hidden')
					} else {
						$('.show-more').removeClass('hidden')
					}
					for(var x=0; x<c.length && x<9; x++){
						$(c[x]).removeClass('hidden');
					}
				}
				show9();
				$('.show-more').click(show9);
			});
		</script>
		<?php return ob_get_clean();
	}
	add_shortcode('resources', 'resources_fn');
endif;

function wpse237762_set_404() {
    if (is_attachment()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
    }
}
// This will show 404 on the attachment page
add_filter('template_redirect', 'wpse237762_set_404');
// This will show 404 instead of redirecting to attachment page when dealing with a trailing slash
add_filter('redirect_canonical', 'wpse237762_set_404', 0);

/*
add_action('admin_init', function () {
    // Only run for admin users and if the GET param is present
    if (! current_user_can('manage_options') || ! isset($_GET['update_usernames'])) {
        return;
    }

    // Get all users EXCEPT administrators and editors
    $exclude_roles = ['administrator', 'editor'];
    $all_users     = get_users();

    foreach ($all_users as $user) {
        if (array_intersect($user->roles, $exclude_roles)) {
            continue;
        }

        $user_id  = $user->ID;
        $ahpra_id = get_user_meta($user_id, 'ahpra_id', true);

        if (empty($ahpra_id)) {
            continue;
        }

        $base_username = sanitize_user($ahpra_id, true);

        global $wpdb;

        // Get all usernames that match base pattern (e.g., 'bob', 'bob-1', 'bob-2')
        $existing_usernames = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_login FROM $wpdb->users WHERE user_login LIKE %s",
                $wpdb->esc_like($base_username) . '%'
            )
        );

        $new_username = $base_username;

        if (in_array($base_username, $existing_usernames) && $user->user_login !== $base_username) {
            $suffix = 1;
            while (in_array($base_username . '-' . $suffix, $existing_usernames)) {
                $suffix++;
            }
            $new_username = $base_username . '-' . $suffix;
        }

        if ($new_username !== $user->user_login) {
            $updated = $wpdb->update(
                $wpdb->users,
                ['user_login' => $new_username],
                ['ID' => $user_id]
            );

            if ($updated !== false) {
                clean_user_cache($user_id);
                error_log("Updated user ID {$user_id} to username '{$new_username}'");
            } else {
                error_log("Failed to update user ID {$user_id}");
            }
        }
    }

    echo '? Done updating usernames (excluding admin/editor).';
    exit;
});

add_action('admin_init', function () {
    // Only run for admin users and if the GET param is present
    if ( ! current_user_can('manage_options') || ! isset($_GET['update_roles']) ) {
        return;
    }

	$users = get_users(array(
        'number' => -1 // Get all users
    ));

    $updated_count = 0;

    // Loop through each user
    foreach ($users as $user) {
        // Check if the user has no roles
        if (empty($user->roles)) {
            // Add subscriber role
            $user->add_role('subscriber');
            
            $updated_count++;
        }
    }

    if ($updated_count == 0) {
        echo '<div class="notice notice-success"><p>No users without a role found.</p></div>';
        return;
    }

    echo '<div class="notice notice-success"><p>Updated ' . $updated_count . ' users to subscriber role.</p></div>';
	exit;
});
*/

/**
 *
 * Usage:
 * [user_status_content show_to="logged_in"] Content for logged-in users [/user_status_content]
 * [user_status_content show_to="logged_out"] Content for logged-out users [/user_status_content]
 *
 */
function custom_user_status_content_shortcode( $atts, $content = null, $tag = '' ) {
    $atts = shortcode_atts(
        array(
            'show_to' => 'logged_in',
        ),
        $atts,
        $tag
    );

    $is_logged_in = is_user_logged_in();
    $show_content = false;

    if ( $atts['show_to'] === 'logged_in' && $is_logged_in ) {
        $show_content = true;
    } elseif ( $atts['show_to'] === 'logged_out' && ! $is_logged_in ) {
        $show_content = true;
    } elseif ( $atts['show_to'] === 'hcp_member' && $is_logged_in ) {
		$user_id = get_current_user_id();
		if($user_id && rcp_user_has_active_membership($user_id)) {
			$customer = rcp_get_customer_by_user_id($user_id);
			if (in_array("HCP", rcp_get_customer_membership_level_names( $customer->get_id() ))) {
				$show_content = true;
			}
		}
    } elseif ( $atts['show_to'] === 'pharmacist' && $is_logged_in ) {
		$user_id = get_current_user_id();
		if($user_id && rcp_user_has_active_membership($user_id)) {
			$customer = rcp_get_customer_by_user_id($user_id);
			if (in_array("Pharmacist", rcp_get_customer_membership_level_names( $customer->get_id() ))) {
				$show_content = true;
			}
		}
    }

    if ( $show_content && ! is_null( $content ) ) {
        return do_shortcode( $content );
    }

    return;
}
add_shortcode( 'user_status_content', 'custom_user_status_content_shortcode' );

function handle_header_menu_shortcode($atts, $content = null, $tag = '') {
	ob_start(); 
	?>
	<div class="">
      <img
        decoding="async"
        src="/wp-content/uploads/2021/10/CARE_CONNECT_LOGO-190.png"
        class="h-base my-base rounded-none"
        alt=""
      />
    </div>
    <div class="hidden lg:block ml-auto">
      <!-- spinnr:eyJpZCI6InBiLWVsZW1lbnQtd3AtbWVudXMtMSIsImxhYmVsIjoid3AtbWVudXMiLCJlbGVtZW50Ijoid3AtbWVudXMiLCJjaGlsZHJlbiI6W10sInBiX2F0dHJpYnV0ZXMiOnsiY2xhc3MiOiJoZWFkZXItbWVudSIsInN0eWxlIjoiIn0sInBiX2NvbXBvbmVudF9kYXRhIjp7ImRpc3BsYXkiOiJkaXYiLCJtZW51IjoyNTgsImxpbmtfY2xhc3MiOiIifSwiZHJhZ2dhYmxlIjp0cnVlLCJkcm9wcGFibGUiOnRydWV9  -->
      <div class="header-menu">
        <?php echo do_shortcode('[wp-menus display="div" menu="258" link_class=""]'); ?>
      </div>
    </div>
	<div class="flex items-center ml-auto md:ml-4xl gap-md">
	<?php echo do_shortcode('[user_status_content show_to="logged_out"]
      <a class="btn ghost m-0" href="/register">Register</a
      ><a class="btn cta m-0" href="/log-in">Login</a>
    [/user_status_content]'); ?>
	<?php echo do_shortcode('[user_status_content show_to="logged_in"]
      <a class="btn ghost m-0" href="/edit-your-profile">Edit Profile</a>
      <a class="btn cta m-0" href="' . wp_logout_url( home_url() ) . '">Logout</a>
    [/user_status_content]'); 
?>
	<?php echo do_shortcode('[user_status_content show_to="hcp_member"]
      <a class="m-0 btn cta" href="/order-samples">Order samples</a>
	  [/user_status_content]'); ?>
	  <?php echo do_shortcode('[user_status_content show_to="pharmacist"]
	  <a class="btn cta m-0" href="/order-patient-demo-kits">Order demo kits</a>
	   [/user_status_content]'); ?>
      <button
        class="btn ghost m-0 lg:hidden"
        onclick="toggleMobileMenu()"
      >
        <svg
          viewbox="0 0 18 18"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          class="w-sm h-sm"
        >
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M1.25 4C1.25 3.44772 1.69772 3 2.25 3H15.75C16.3023 3 16.75 3.44772 16.75 4C16.75 4.55228 16.3023 5 15.75 5H2.25C1.69772 5 1.25 4.55228 1.25 4Z"
            fill="currentColor"
          />
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M1.25 9C1.25 8.44772 1.69772 8 2.25 8H15.75C16.3023 8 16.75 8.44772 16.75 9C16.75 9.55228 16.3023 10 15.75 10H2.25C1.69772 10 1.25 9.55228 1.25 9Z"
            fill="currentColor"
          />
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M1.25 14C1.25 13.4477 1.69772 13 2.25 13H15.75C16.3023 13 16.75 13.4477 16.75 14C16.75 14.5523 16.3023 15 15.75 15H2.25C1.69772 15 1.25 14.5523 1.25 14Z"
            fill="currentColor"
          />
        </svg>
      </button>
    </div>
	<?php return ob_get_clean();
}
add_shortcode( 'handle_header_menu', 'handle_header_menu_shortcode' );

add_action('wp_head', function () {
    if (is_page('rectogesic-3d-model')) {
        ?>
        <script type="text/javascript">
            var stylesheet_directory_uri = "<?php echo esc_js(get_stylesheet_directory_uri()); ?>";
        </script>
		<link rel="modulepreload" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/entry.22957f2a.js">
		<link rel="modulepreload" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/index.d946cda7.js">
		<link rel="modulepreload" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/_plugin-vue_export-helper.c27b6911.js">
		<link rel="modulepreload" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/Modal.60fe48ac.js">
		<link rel="preload" as="style" href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/Modal.24eba80f.css">
		<link rel="prefetch" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/error-404.4f90e9e7.js"><link rel="prefetch" as="script" crossorigin href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/error-500.8494c0a1.js">
		<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/_nuxt/Modal.24eba80f.css">
        <?php
    }
});


// add_action('admin_init', function () {
//     // Only run for admin users and if the GET param is present
//     if ( ! current_user_can('manage_options') || ! isset($_GET['update_roles']) ) {
//         return;
//     }

// 	$users = get_users(array(
//         'number' => -1 // Get all users
//     ));

//     $updated_count = 0;

//     foreach ($users as $user) {
//         if ($user->user_login === 'devtbst') {
//             continue;
//         }
//         $roles = get_user_meta($user->ID, "tbstwp_capabilities");
//         // Add role
//         foreach ($roles[0] as $key => $role) {
//             $user->add_role($key);
//         }
//         $updated_count++;
//     }

//     if ($updated_count == 0) {
//         echo '<div class="notice notice-success"><p>No users without a role found.</p></div>';
//         return;
//     }

//     echo '<div class="notice notice-success"><p>Updated ' . $updated_count . ' users to subscriber role.</p></div>';
// 	exit;
// });

// [frm-login class="frm_style_formidable-style"] OLD Login Form

add_action('wp_enqueue_scripts', 'enqueue_login_popup_assets');
function enqueue_login_popup_assets() {
    $is_excluded_page = is_page( array( 'log-in', 'register', 'forgot-password' ) ) || $GLOBALS['pagenow'] === 'wp-login.php';
    if (!$is_excluded_page) {
        wp_register_script('login-popup-script', '', ['jquery'], null, true);
        wp_enqueue_script('login-popup-script');
        // 2. ONLY localize login_popup_obj for guests
        if (!is_user_logged_in()) {
            wp_localize_script('login-popup-script', 'login_popup_obj', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ajax-login-nonce'),
            ));
        }
        // 3. ALWAYS localize racgp_ajax_vars
        // This makes it available even immediately after a guest becomes a user
        wp_localize_script('login-popup-script', 'racgp_ajax_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('racgp_secure_nonce'),
        ));
    }
}

function ajax_login_handler() {
    // Verify the security nonce
    check_ajax_referer('ajax-login-nonce', 'security');

    // Attempt to sign the user on
    $info = array();
    $info['user_login'] = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
    $info['user_password'] = isset($_POST['password']) ? $_POST['password'] : '';
    $info['remember'] = isset($_POST['rememberme']) && $_POST['rememberme'] == 'forever';
	$info['page_id'] = isset($_POST['page_id']) ? $_POST['page_id'] : '0';

    $user_signon = wp_signon($info, is_ssl());

    // Check for errors
    if (is_wp_error($user_signon)) {
        wp_send_json_error(array('message' => 'Login failed: Invalid username or password.'));
    } else {
		wp_set_current_user($user_signon->ID);
    	wp_set_auth_cookie($user_signon->ID);

		$saved_racgp = get_user_meta($user_signon->ID, 'racgp_number', true);
		
		if(empty($saved_racgp) && ($info['page_id'] == 111793 || $info['page_id'] == 95553 || $info['page_id'] == 108753)){
			wp_send_json_success(array('message' => 'RACGP'));
		} else {
			wp_send_json_success(array('message' => 'Login successful! Reloading...'));
		}
        
    }
}
add_action('wp_ajax_nopriv_popup_login', 'ajax_login_handler');

function compute_content_shortcode( $atts, $content = null ) {
    if ( is_null( $content ) ) {
        return '';
    }
    $sanitized_content = preg_replace( '/[^0-9\.\+\-\*\/\(\)\s]/', '', $content );

    if ( empty( trim( $sanitized_content ) ) ) {
        return '[Error: Invalid Expression]';
    }
    $result = @eval( 'return ' . $sanitized_content . ';' );

    if ( $result === false || is_infinite( $result ) || is_nan( $result ) ) {
        return '[Error: Calculation Failed]';
    }

    return esc_html( $result );
}
add_shortcode( 'compute', 'compute_content_shortcode' );


function courses_gate( $content ) {
    
    $course_id_to_gates = array(95553, 111793, 123191);
	$presurvey_page_id = 98001;
	$form_id = 81;
	$post = get_post( $presurvey_page_id );	
	$footnote_id = 102801;
   

    // Check if we are on a single "sfwd-courses" post and if it's the correct one.
    // If not, return the original content immediately.
    if (!in_array(get_the_ID(), $course_id_to_gates) ){
		 
        return $content;
    }
	
	// Clinical Audit Course
	if(get_the_ID() == 111793 || get_the_ID() == 95553 || get_the_ID() == 123191){
		
		if(current_user_can( 'administrator' )){
			return $content;
		}
		
		$current_user = wp_get_current_user();
		
		// Get RCP membership role
        $customer = rcp_get_customer_by_user_id($current_user->ID);
		
		if ($customer && count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Pharmacist" ) ) )){
			
            $saved_racgp = get_user_meta($current_user->ID, 'racgp_number', true);

            if (empty($saved_racgp)) {
				ob_start();	
			?>
				<div class="section py-7xl">
					<div class="container text-center">
						<p>Please provide your RACGP Number in your profile to access this course.<br /><a class="btn cta editBtn" href="javascript:void(0)">Update Profile</a></p>
					</div>
				</div>
				
				<div id="popup_modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center cursor-pointer">
					<div class="relative bg-white p-8 shadow-2xl w-11/12 max-w-md cursor-default border border-accent rounded-lg">
						<span class="close absolute top-2 right-4 text-gray-400 hover:text-gray-800 text-3xl font-bold cursor-pointer transition-colors">&times;</span>
						
						<h3 class="text-xl font-semibold mb-4 text-center">Required: RACGP Number</h3>
						<p class="text-sm text-gray-600 mb-6 text-center">Please enter your RACGP number to access this activity.</p>
						
						<form method="post" action="">
							<?php wp_nonce_field('update_racgp_direct', 'racgp_nonce_field'); ?>
							
							<div class="mb-6">
								<label for="modal_racgp" class="block text-sm font-medium text-gray-700 mb-1 text-left">RACGP Number*</label>
								<input type="text" name="my_direct_racgp" id="modal_racgp" required 
									class="w-full p-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:outline-none">
							</div>
							
							<button type="submit" name="submit_direct_racgp" 
									class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-bold hover:bg-blue-700 transition-colors btn cta">
								Save & Access Course
							</button>
						</form>
					</div>
				</div>	

				<script type="text/javascript">
						(function($) {
							$(document).ready(function() {
								const modal = $('#popup_modal');
								const closeBtn = $('.close');                
								const editBtn = $('.editBtn');
								
							   editBtn.click(function(){
									modal.removeClass('hidden').hide().fadeIn(400);
								});

								closeBtn.on('click', function() {
									modal.fadeOut(300);
								});

								modal.on('click', function(event) {
									if (event.target.id === 'popup_modal') {
										modal.fadeOut(300);
									}
								});

								// --- Close the modal with the 'Esc' key ---
								$(document).on('keydown', function(event) {
									if (event.key === "Escape") {
										modal.fadeOut(300);
									}
								});
							});
						})(jQuery);
				 </script>
			<?php
				return ob_get_clean();
	
            }else{
				return $content;
			}
        }else{
			
			wp_redirect("/anal-fissures-breaking-the-cycle-and-the-stigma-landing/", 302);
			exit;

			ob_start();	

			?>
				<div class="section py-7xl">
					<div class="container text-center">
						<h4 class="error">You don't have permission to view this page.</h4>	
					</div>
				</div>
			<?php
				return ob_get_clean();
			
		}
		
	}else {
		
		$user_id = get_current_user_id();
		
		if(!$user_id){
			ob_start();	
	?>
		<div class="section py-7xl">
			<div class="container text-center">
				<a href="javascript:void(0);" class="text-accent loginBtn">Login</a> or <a href="javascript:void(0);" class="text-accent registerBtn">Register</a> to view&nbsp;  <i class="-rotate-45 fa-arrow-right fas transform ml-md text-accent"></i>
			</div>
		</div>
		<script type="text/javascript">
			(function($) {
				$(document).ready(function() {
					const loginContent = $('#login-popup-content');
					const registerContent = $('#register-popup-content');
					const modal = $('#popup-overlay');
					$('.loginBtn').click(function(){
							loginContent.removeClass('hidden');
							registerContent.addClass('hidden');
							modal.removeClass('hidden').hide().fadeIn(400);
						});
					$('.registerBtn').click(function(){
							registerContent.removeClass('hidden');
							loginContent.addClass('hidden');
							modal.removeClass('hidden').hide().fadeIn(400);
						});
				});
			})(jQuery);
		</script>
	<?php
			return ob_get_clean();
		}
		
		$entries = FrmEntry::getAll(
			array(
				'user_id' => $user_id,
				'form_id' => (int) $form_id,
			),
			'',
			1   // limit
		);
		
		$current_user = wp_get_current_user();
		$user_roles = $current_user->roles;
		
		//if ( ! empty( $entries ) || $user_roles[0]=='administrator') {
		if ( ! empty( $entries ) ) {
			// user HAS submitted the form
			//return the original LearnDash course content
			// include course footnote
			$footnote = get_post( $footnote_id );	
			return $content. $footnote->post_content;
		} else {
			return do_shortcode($post->post_content);
		}	
	}
}
add_filter( 'the_content', 'courses_gate', 100 );

// Mark quize as complete after submitting the formidable form
function submitted_form_mark_completed_quiz($entry_id, $form_id) {
	
    $target_form_ids = [97, 209, 161];
	
    if (!in_array($form_id, $target_form_ids)) {
        return;
    }    
	
    // Get the current user's ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return; // Stop if user is not logged in
    }
 
    
	// Pre-learning survey form
	if($form_id==97){
		$quiz_id = 100865;
		
	}else if($form_id==209){ // Activity evaluation form
		$quiz_id = 116865;

	}else if($form_id==161){ // Retrospective analysis form
		$lesson_id = 112353;
		$quiz_id = $lesson_id;
	}

    // 3. Check if the quiz is already complete
    if (function_exists('learndash_is_quiz_complete') && learndash_is_quiz_complete($user_id, $quiz_id)) {
        return; // User has already completed this quiz
    }

    // 4. Get the Course ID for better context
    $course_id = 0;
    if (function_exists('learndash_get_course_id')) {
        $course_id = learndash_get_course_id($quiz_id);
    }

    // $force=true bypasses learndash_can_complete_step; form submission is the trust signal.
    if (function_exists('learndash_process_mark_complete')) {
        learndash_process_mark_complete($user_id, $quiz_id, false, $course_id, true);
    }
}
add_action('frm_after_create_entry', 'submitted_form_mark_completed_quiz', 20, 2);

function change_specific_form_error_message( $message, $form ) {
    // This will change the message on ALL forms.
	if ( $form['form']->id == 81 || $form['form']->id == 97 ) {
		$message = 'You must complete all questions to proceed.';
	}
    return $message;
}
//add_filter('frm_invalid_error_message', 'change_specific_form_error_message', 10, 2);


function test_notification(){
	if(isset($_GET["run_notification_email"])){
		$test_user_id = 18549;
		$test_course_id = 95553;
		// ------------------------------

		// Check if the function exists before calling it
		if ( function_exists( 'ld_update_course_access' ) ) {
			
			ld_update_course_access( $test_user_id, $test_course_id );

			// Optional: Print a message to your screen to confirm it ran
			echo '<div style="background:yellow; padding:10px; position:fixed; top:50px; left:50px; z-index:9999;">ENROLLMENT SNIPPET RAN for User ID ' . $test_user_id . '</div>';
		}
	}
}
//add_action( 'wp', 'test_notification' );

function get_csv_cell_by_search($file, $search_column, $search_value, $return_column) {
    $handle = fopen($file, 'r');
 
    $headers = fgetcsv($handle);
    
    $search_col = array_search($search_column, $headers);
    $return_col = array_search($return_column, $headers);
    
    while (($row = fgetcsv($handle)) !== false) {
        if ($row[$search_col] == $search_value) {
            fclose($handle);
            return $row[$return_col];
        }
    }
    
    fclose($handle);
    return false;
}


function check_aphra_regis(){
	if (is_page("register")) {  
        if (isset($_GET['email'])) {
            $email = sanitize_text_field($_GET['email']); 
	   $aphra = get_csv_cell_by_search(get_stylesheet_directory() .'/documents/aphra_latest.csv', 'email', $email, 'aphra');
		
	   echo '<script type="text/javascript"> 
        		var wp_aphra = "'.$aphra.'";
    		</script>';
        }
    }
}

add_action("wp", "check_aphra_regis");

add_filter('frm_validate_field_entry', function($errors, $field, $value){

    $form_id          = 145;
    $username_field   = 4337;
    $password_field   = 4353;
    $racgp_field_id   = 4385;
	
    $membership_roles_require = ['hcp', 'pharmacist'];

    if ($field->form_id != $form_id) {
        return $errors;
    }

    // Only validate RACGP field
    if ($field->id == $racgp_field_id) {

        $username = sanitize_text_field($_POST['item_meta'][$username_field] ?? '');
        $password = $_POST['item_meta'][$password_field] ?? '';

        // Get user by email or username
        $user = is_email($username)
            ? get_user_by('email', $username)
            : get_user_by('login', $username);

        if (!$user) {
            $errors['field'. $field->id] = 'Invalid login details.';
            return $errors;
        }

        // Check password
        if (!wp_check_password($password, $user->data->user_pass, $user->ID)) {
            $errors['field'. $password_field] = 'Incorrect password.';
            return $errors;
        }

        // Get RCP membership role
        $customer = rcp_get_customer_by_user_id($user->ID);
		
		if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Pharmacist" ) ) )){

            $saved_racgp = get_user_meta($user->ID, 'racgp_number', true);

            if (empty($saved_racgp)) {
                $errors['field'. $racgp_field_id] = 'Your account requires a RACGP number.';
                return $errors;
            }

            if ($saved_racgp !== $value) {
                $errors['field'. $racgp_field_id] = 'Incorrect RACGP number.';
            }
        }
		/*if(!$errors){
			
			$creds = array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			);

			$user = wp_signon($creds);
			
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $user->user_login, $user );
		}*/
    }

    return $errors;

}, 10, 3);



function testfunction(){
	if(isset($_GET["christest"])){
		$customer = rcp_get_customer_by_user_id(22353);
		
		//$customer_meta = get_all_rcp_customer_user_meta( 22353 );
		//echo 'testhere'.get_user_meta( 22321, 'ragcp_number', true );
		
		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true // Set to true to keep the user logged in
		);

		$user = wp_signon( $creds, false );

		// 4. Handle the login result
		if ( is_wp_error( $user ) ) {
			// Log the error for debugging purposes if needed
			error_log( 'Formidable Forms Auto-Login Failed: ' . $user->get_error_message() );
			
			// **IMPORTANT:** Do NOT return an error message to the user here.
			// The form submission has already been recorded as successful.
			return;
		}
		
		// 5. Set the current user globally and fire the login action
		// This completes the auto-login process.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		
		/*echo '<pre>';
		print_r($customer);
		echo $saved_racgp = get_user_meta($user->ID, 'racgp_number', true);
		print_r(count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Pharmacist" ) ) ));
		// if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Pharmacist" ) ) )){
		echo '</pre>';*/
	}
}
add_action( 'wp', 'testfunction' );

add_filter( 'learndash_get_label_course_step_next', function( $label, $post_type ) {

    // Match quiz post type
    $quiz_slug = learndash_get_post_type_slug( 'quiz' );

    if ( $post_type === $quiz_slug ) {
	if(112353 == get_the_ID()){
		return 'Start Survey';
	}
        return 'Start Quiz';
    }

    return $label;
}, 20, 2 );

add_action('wp_ajax_save_racgp_data', 'save_racgp_data_callback');
function save_racgp_data_callback() {
    // check_ajax_referer('racgp_secure_nonce', 'security');

    $user_id = get_current_user_id();
    $racgp_num = isset($_POST['racgp_number']) ? sanitize_text_field($_POST['racgp_number']) : '';

    if ($user_id && !empty($racgp_num)) {
        update_user_meta($user_id, 'racgp_number', $racgp_num);
        wp_send_json_success(array('message' => 'Data saved!'));
    }
    
    wp_send_json_error('Invalid request');
}

add_action('init', 'process_direct_racgp_update');
function process_direct_racgp_update() {
    if (isset($_POST['submit_direct_racgp'])) {
        if (!isset($_POST['racgp_nonce_field']) || !wp_verify_nonce($_POST['racgp_nonce_field'], 'update_racgp_direct')) {
            return; 
        }

        $user_id = get_current_user_id();
        $racgp_value = isset($_POST['my_direct_racgp']) ? sanitize_text_field($_POST['my_direct_racgp']) : '';

        if ($user_id && !empty($racgp_value)) {
            update_user_meta($user_id, 'racgp_number', $racgp_value);
            
            $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            
            wp_redirect($current_url);
            exit;
        }
    }
}

/**
 * Injects the missing references and study footnote after the LearnDash course content list
 * specifically for Course ID 95553 using the official hook.
 */
add_action( 'learndash-course-content-list-after', 'add_anal_fissures_references_to_specific_id', 10, 2 );

function add_anal_fissures_references_to_specific_id( $course_id, $user_id ) {
    // Check if the current course ID matches the target course
    if ( (int) $course_id !== 95553 ) {
        return;
    }

    ?>
    <hr class="mt-5xl">
    
    <div class="ld-custom-footer-references mt-xl mb-5xl">
        <p class="text-sm">
            *A prospective, observational, national study including 57 GPs and 1,061 adult patients presenting to a GP office for any reason, conducted in France. Over a period of 2 days, GPs were instructed to assess all adult patients they saw for the presence of anal symptoms (bleeding, pruritus, swelling, discharge or uncontrolled leakage) within the past month, using a self-administered survey completed in the waiting room by the patient. Symptomatic patients were offered an anal examination and a consultation with a proctologist.
        </p>

        <p class="text-sm">
            <b class="">References: 1.</b> Lyle V, Young CJ. <i class="">Aust J Gen Pract</i> 2024;53(1–2):33–35.
            <b class="">2.</b> Ruymbeke H, et al. <i class="">Acta Gastroenterol Belg</i> 2023;86(1):58–67.
            <b class="">3.</b> Newman M, Collie M. <i class="">Br J Gen Pract</i> 2019;69(685):409–10.
            <b class="">4.</b> Sailer M, et al. <i class="">Br J Surg</i> 1998;85(12):1716–9.
            <b class="">5.</b> Chang J, et al. <i class="">Perm J</i> 2016;20(4):115–222.
            <b class="">6.</b> Tournu G, et al. <i class="">BMC Fam Pract</i> 2017;18(1):78.
        </p>
    </div>
    <?php
}

add_filter( 'the_content', 'add_anal_fissures_references_universal' );

function add_anal_fissures_references_universal( $content ) {
    $course_id = 95553;
    $user_id = get_current_user_id();

    // Only apply to the specific course ID
    if ( is_singular( 'sfwd-courses' ) && get_the_ID() == $course_id ) {
        
        // Check if the user DOES NOT have access (visitor/not enrolled)
        if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
            
            $references_html = '<hr class="mt-5xl">
            <div class="ld-custom-footer-references mt-xl mb-5xl">
				<p class="text-sm">
					*A prospective, observational, national study including 57 GPs and 1,061 adult patients presenting to a GP office for any reason, conducted in France. Over a period of 2 days, GPs were instructed to assess all adult patients they saw for the presence of anal symptoms (bleeding, pruritus, swelling, discharge or uncontrolled leakage) within the past month, using a self-administered survey completed in the waiting room by the patient. Symptomatic patients were offered an anal examination and a consultation with a proctologist.
				</p>

				<p class="text-sm">
					<b class="">References: 1.</b> Lyle V, Young CJ. <i class="">Aust J Gen Pract</i> 2024;53(1–2):33–35.
					<b class="">2.</b> Ruymbeke H, et al. <i class="">Acta Gastroenterol Belg</i> 2023;86(1):58–67.
					<b class="">3.</b> Newman M, Collie M. <i class="">Br J Gen Pract</i> 2019;69(685):409–10.
					<b class="">4.</b> Sailer M, et al. <i class="">Br J Surg</i> 1998;85(12):1716–9.
					<b class="">5.</b> Chang J, et al. <i class="">Perm J</i> 2016;20(4):115–222.
					<b class="">6.</b> Tournu G, et al. <i class="">BMC Fam Pract</i> 2017;18(1):78.
				</p>
			</div>';

            return $content . $references_html;
        }
    }

    return $content;
}

add_filter('the_content', 'certificate_redirect');
function certificate_redirect($content) {
    // Check if it's the right page (by ID)
    if (is_page(124151)  && get_the_ID() == 124151 && !is_user_logged_in()) {
		if(!is_user_logged_in()){
			ob_start();	
	?>
		<div class="section py-7xl">
			<div class="container text-center">
				<a href="javascript:void(0);" class="text-accent loginBtn">Login to view the certificate</a>  <i class="-rotate-45 fa-arrow-right fas transform ml-md text-accent"></i>
			</div>
		</div>
		<script type="text/javascript">
			(function($) {
				$(document).ready(function() {
					const loginContent = $('#login-popup-content');
					const registerContent = $('#register-popup-content');
					const modal = $('#popup-overlay');
					$('.loginBtn').click(function(){
							loginContent.removeClass('hidden');
							registerContent.addClass('hidden');
							modal.removeClass('hidden').hide().fadeIn(400);
						});
					$('.registerBtn').click(function(){
							registerContent.removeClass('hidden');
							loginContent.addClass('hidden');
							modal.removeClass('hidden').hide().fadeIn(400);
						});
				});
			})(jQuery);
		</script>
	<?php
			return ob_get_clean();exit;
		}
	}
	else if(is_page(124151) && is_user_logged_in()){
		$url = learndash_get_course_certificate_link($_GET["course"],$_GET["user"]);
		 
		if($url){
			wp_redirect($url, 302);
			exit;
		} else {	
			wp_redirect("/log-in", 302);
			exit;
		}
        

		$custom_content = '<div class="ld-custom-footer-references mt-xl mb-5xl">
				<p class="text-md">No certificate found</p></div>';

        $content = $custom_content . $content;
    	return $content;
    }

    return $content;
}



add_action( 'update_user_metadata', 'enroll_only_on_first_racgp', 10, 5 );

function enroll_only_on_first_racgp( $null, $object_id, $meta_key, $meta_value, $prev_value ) {
    // 1. Only target our specific key
    if ( 'racgp_number' !== $meta_key || empty( $meta_value ) ) {
        return $null;
    }

    // 2. Fetch what is CURRENTLY in the database for this user
    $current_db_value = get_user_meta( $object_id, 'racgp_number', true );

    // 3. Logic: Only proceed if the database currently has NO value
    if ( empty( $current_db_value ) ) {
        
        $trigger_course_id = 95553;

        if ( function_exists( 'ld_update_course_access' ) ) {
            // Standard LearnDash enrollment toggle
            ld_update_course_access( $object_id, $trigger_course_id, true );
            ld_update_course_access( $object_id, $trigger_course_id, false );

            error_log( "FIRST TIME ENROLL: User $object_id added RACGP for the first time. Email triggered." );
        }
    } else {
        // This is just an update, so we do nothing (no enrollment triggered)
        error_log( "UPDATE DETECTED: User $object_id already had an RACGP number. No email sent." );
    }

    return $null; // Continue with the normal save process
}

add_action( 'template_redirect', 'process_user_approval_from_url' );

function process_user_approval_from_url() {
    // 1. Check if we are on the approval path and have a User ID
    if ( strpos( $_SERVER['REQUEST_URI'], 'approve-user' ) !== false && isset( $_GET['uid'] ) ) {
        
        $user_id = intval( $_GET['uid'] );

        // Basic check to ensure the user exists
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_die( 'Error: User not found.', 'User Error', array( 'response' => 404 ) );
        }


        // 4. Redirect Admin to the User List with a confirmation flag
        wp_safe_redirect( admin_url( 'user-edit.php?&user_id=' . $user_id .'#course_progress_details') );
        exit;
    }
}

add_filter('learndash_alert_message', 'custom_alert_message', 10, 3);
function custom_alert_message($message, $type, $icon) {
    // Change the message based on content
    if (strpos($message, 'Great Job') !== false) {

		$current_id = get_the_ID();
		
		
		switch ($current_id) {
			case 95505:
				// Online Learning Module  Part 1: Epidemiology and pathophysiology of anal fissures
				// https://hcp.carepharma.com.au/courses/anal-fissures-breaking-the-cycle-and-the-stigma/lessons/part-1-epidemiology-and-pathophysiology-of-anal-fissures/
				return 'You have now completed Part 1 – please proceed to Part 2.';
			case 99921:
				// Online Learning Module  Part 2: Clinical presentation and assessment
				// https://hcp.carepharma.com.au/courses/anal-fissures-breaking-the-cycle-and-the-stigma/lessons/clinical-presentation-and-assessment/
				return 'You have now completed Part 2 – please proceed to Part 3.';
			case 99809:
				// Online Learning Module  Part 3: Management algorithm for primary anal fissures in adults
				// https://hcp.carepharma.com.au/courses/anal-fissures-breaking-the-cycle-and-the-stigma/lessons/part-3-management-algorithm-for-primary-anal-fissures-in-adults/
				return 'You have now completed Part 3 – please proceed to Part 4.';
			case 101745:
				// Online Learning Module  Part 4: Patient screening and counselling
				// https://hcp.carepharma.com.au/courses/anal-fissures-breaking-the-cycle-and-the-stigma/lessons/part-4-patient-screening-and-counselling/
				return 'You have now completed Part 4 – please proceed to the Summary and Assessment.';
			default:
				// Your requested default return with the ID attached
				return 'You have now completed the Mini Clinical Audit – please proceed to the Activity Evaluation. ';//.$current_id ;
		}


    }
    
    // Always return the message
    return $message;
}


add_action( 'template_redirect', 'custom_learndash_processing', 5 );

function custom_learndash_processing() {
    // 1. Only run if we are on the specific Course page
    // Using is_single() ensures this only triggers on that specific post ID
    $course_id = 111793;

    if ( is_single( $course_id ) ) {
        
        // 2. Only run for logged-in users
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();

            // 3. Check if the user is ALREADY enrolled
            // sfwd-courses is the post type; ld_course_check_user_access returns true/false
            if ( function_exists( 'sfwd_lms_has_access' ) ) {
                $has_access = sfwd_lms_has_access( $course_id, $user_id );

                if ( ! $has_access ) {
                    // 4. Enroll the user if they don't have access
                    if ( function_exists( 'ld_update_course_access' ) ) {
                        // We use 'false' as the 3rd parameter to ADD access (true removes it)
                        ld_update_course_access( $user_id, $course_id, false );
                        
                        // 5. Optional: Refresh the page so LearnDash recognizes the new access immediately
                        wp_safe_redirect( get_permalink( $course_id ) );
                        exit;
                    }
                }
            }
        }
    }
}


// Create a shortcode [current_user_id] to use in emails
add_shortcode( 'current_user_id', function() { 
    return get_current_user_id();
});

add_filter('frm_success_message', 'custom_message_specific_form', 10, 3);
function custom_message_specific_form($message, $form, $entry) { 
    if ($form->id == 209) {
        return 'Thank you for completing the Mini Clinical Audit and the Activity Evaluation survey.<br/><br/>Your audit responses will now be evaluated by the education providers. Provided there are no issues with your responses, you will receive an email within the next four weeks with your Statement of Completion.';
    }
    
    return $message;
}
add_filter('frm_validate_entry', 'change_already_submitted_message', 20, 2);
function change_already_submitted_message($errors, $values){
    
    $target_form_id = 209; 

    if ( (int)$values['form_id'] === $target_form_id ) {
        if ( isset($errors['already_submitted']) ) {
            $errors['already_submitted'] = 'Sorry, you have already submitted this form. Please check your email for confirmation.';
        }
    }
    return $errors;
}


add_filter('frm_main_feedback', 'custom_message_specific_form_main_feedback', 10, 3);
function custom_message_specific_form_main_feedback($message, $form, $entry) {
    if ($form->id != 161) {
        return $message;
    }

    $user_id = is_object($entry) && !empty($entry->user_id) ? (int) $entry->user_id : get_current_user_id();

    global $wpdb;
    $has_eval = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$wpdb->prefix}frm_items WHERE user_id=%d AND form_id=209 AND is_draft=0 LIMIT 1",
        $user_id
    ));

    $js_hide_script = "
        <script type='text/javascript'>
            (function() {
                var target = document.querySelector('.ld-content-actions');
                if (target) {
                    target.style.display = 'none';
                    target.classList.add('hidden');
                }
            })();
        </script>";

    if (!$has_eval) {
        return '
            <div class="frm_message" role="status">Thanks for submitting your audit. Redirecting you to the activity evaluation...</div>
            <div class="mt-10"><a class="btn cta mt-0" href="/courses/mini-clinical-audit/quizzes/activity-evaluation/">Continue to Activity Evaluation</a></div>
            <script>setTimeout(function(){window.location.href="/courses/mini-clinical-audit/quizzes/activity-evaluation/";},3000);</script>
        ' . $js_hide_script;
    }

    return '
        <div class="frm_message" role="status">Your audit responses have been updated. Please expect to hear from the education providers soon.</div>
        <div class="mt-10"><a href="/courses/mini-clinical-audit/">Return to the audit homepage</a></div>
    ' . $js_hide_script;
}
