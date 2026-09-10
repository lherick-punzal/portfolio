<?php
namespace ElementsKit_Lite\Widgets\Init;

use ElementsKit_Lite\Modules\Header_Footer\Activator;
use ElementsKit_Lite\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Preloads assets for Elementor documents rendered by ElementsKit.
 */
class Nested_Document_Assets {

	/**
	 * Widget Area documents, keyed by exact mega-menu title match.
	 * @var array|null
	 */
	private $mega_menu_documents;

	/**
	 * Widget Area documents whose titles use the "dynamic-content-widget-{key}-*" prefix form.
	 * Each entry is [ 'suffix' => string after the shared prefix, 'id' => int ].
	 * @var array|null
	 */
	private $widget_area_candidates;

	/**
	 * Per-request cache of resolved widget style/script dependencies, keyed by widget type and settings.
	 * Avoids rebuilding equivalent widget instances that repeat on a page.
	 * @var array
	 */
	private $widget_dependency_cache = array();

	/**
	 * Per-request cache of nav menu items, keyed by menu id/slug/object.
	 * @var array
	 */
	private $nav_menu_items_cache = array();

	/**
	 * Enqueue dependencies before wp_head prints styles.
	 * @since 4.0.3
	 * @return void
	 */
	public function enqueue() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}

		$document_ids = array_filter( array( get_queried_object_id() ) );

		if ( class_exists( Activator::class ) ) {
			$document_ids = array_merge( $document_ids, (array) Activator::template_ids() );
		}

		$checked = array();
		$queue   = array_values( array_unique( array_map( 'absint', array_filter( $document_ids ) ) ) );

		while ( ! empty( $queue ) ) {
			$document_id = array_shift( $queue );
			if ( isset( $checked[ $document_id ] ) ) {
				continue;
			}

			$checked[ $document_id ] = true;
			$elements                = $this->get_document_elements( $document_id );
			if ( empty( $elements ) ) {
				continue;
			}

			Utils::render_elementor_content_css( $document_id );
			$widget_area_keys = array();
			$this->enqueue_element_dependencies( $elements, $widget_area_keys );

			foreach ( $this->get_widget_area_document_ids( $widget_area_keys ) as $nested_id ) {
				if ( ! isset( $checked[ $nested_id ] ) ) {
					$queue[] = $nested_id;
				}
			}
		}
	}

	/**
	 * Get the saved Elementor element tree for a document.
	 * @since 4.0.3
	 * @param int $document_id Elementor document ID.
	 * @return array
	 */
	private function get_document_elements( $document_id ) {
		$data = get_post_meta( $document_id, '_elementor_data', true );
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Walk an Elementor tree and enqueue every registered widget dependency.
	 * @since 4.0.3
	 * @param array $elements         Elementor elements.
	 * @param array $widget_area_keys Dynamic-content lookup keys.
	 * @return void
	 */
	private function enqueue_element_dependencies( $elements, &$widget_area_keys ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ! empty( $element['id'] ) ) {
				$widget_area_keys[] = (string) $element['id'];
			}

			if ( ! empty( $element['widgetType'] ) ) {
				$this->enqueue_widget_dependencies( $element );
			}

			if ( ! empty( $element['settings']['elementskit_nav_menu'] ) ) {
				foreach ( $this->get_nav_menu_items( $element['settings']['elementskit_nav_menu'] ) as $menu_item ) {
					if ( ! is_object( $menu_item ) || empty( $menu_item->ID ) ) {
						continue;
					}

					$widget_area_keys[] = 'megamenu-menuitem' . absint( $menu_item->ID );
				}
			}

			if ( ! empty( $element['settings'] ) ) {
				array_walk_recursive(
					$element['settings'],
					static function ( $value ) use ( &$widget_area_keys ) {
						if ( is_string( $value ) && preg_match( '/^([A-Za-z0-9_-]+)(?:\\*\\*\\*|$)/', $value, $matches ) ) {
							$widget_area_keys[] = $matches[1];
						}
					}
				);
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->enqueue_element_dependencies( $element['elements'], $widget_area_keys );
			}
		}
	}

	/**
	 * Resolve and enqueue a widget instance's style/script dependencies, caching
	 * the lookup for widgets with the same type and settings.
	 * @since 4.0.3
	 * @param array $element Saved Elementor widget data.
	 * @return void
	 */
	private function enqueue_widget_dependencies( $element) {
		//empty check for widgetType, because some widgets are not registered in Elementor and they don't have widgetType. So we need to check if widgetType is empty or not.
		if ( empty( $element['widgetType'] ) ) {
			return;
		}

		$widget_type = $element['widgetType'];
		$settings    = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$cache_key   = $widget_type . ':' . md5( wp_json_encode( $settings ) );

		if ( ! isset( $this->widget_dependency_cache[ $cache_key ] ) ) {
			$dependencies = array(
				'styles'  => array(),
				'scripts' => array(),
			);

			/*
			* Elementor's registered widget objects are prototypes and may not
			* contain initialized settings. Create a real instance from the saved
			* Elementor element data before resolving conditional dependencies.
			*/
			$element['elType']   = 'widget';
			$element['settings'] = $settings;
			$widget              = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
			if ( $widget instanceof \Elementor\Widget_Base ) {

				$dependencies['styles']  = (array) $widget->get_style_depends();
				$dependencies['scripts'] = (array) $widget->get_script_depends();
			}

			$this->widget_dependency_cache[ $cache_key ] = $dependencies;
		}

		foreach ( $this->widget_dependency_cache[ $cache_key ]['styles'] as $handle ) {
			if ( ! empty( $handle ) ) {
				wp_enqueue_style( $handle );
			}
		}

		foreach ( $this->widget_dependency_cache[ $cache_key ]['scripts'] as $handle ) {
			if ( ! empty( $handle ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	/**
	 * Get nav menu items for a menu, caching per menu so the same menu
	 * referenced multiple times in a tree is only fetched once.
	 * @since 4.0.3
	 * @param mixed $menu Menu ID, slug, or object.
	 * @return array
	 */
	private function get_nav_menu_items( $menu ) {
		$cache_key = is_scalar( $menu ) ? (string) $menu : md5( wp_json_encode( $menu ) );

		if ( ! isset( $this->nav_menu_items_cache[ $cache_key ] ) ) {
			$menu_items = wp_get_nav_menu_items( $menu );
			$this->nav_menu_items_cache[ $cache_key ] = is_array( $menu_items )
				? array_filter( $menu_items, 'is_object' )
				: array();
		}
		return $this->nav_menu_items_cache[ $cache_key ];
	}
	
	/**
	 * Resolve saved Widget Area and Mega Menu documents linked by the element tree.
	 * @since 4.0.3
	 * @param array $keys Widget IDs and dynamic-content keys.
	 * @return int[]
	 */
	private function get_widget_area_document_ids( $keys ) {
		$keys = array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) );
		if ( empty( $keys ) ) {
			return array();
		}

		$this->load_widget_area_documents();

		$ids = array();

		// Mega menu titles are exact matches, so this is an O(1) lookup per key
		// instead of a linear scan over every candidate document.
		foreach ( $keys as $key ) {
			if ( isset( $this->mega_menu_documents[ 'dynamic-content-' . $key ] ) ) {
				$ids[] = $this->mega_menu_documents[ 'dynamic-content-' . $key ];
			}
		}

		// Widget area titles carry an arbitrary suffix after the key, so a prefix
		// check is still required, but it now only scans the smaller, pre-filtered
		// widget-area candidate list rather than every elementskit_content post.
		foreach ( $this->widget_area_candidates as $candidate ) {
			foreach ( $keys as $key ) {
				if ( 0 === strpos( $candidate['suffix'], $key . '-' ) ) {
					$ids[] = $candidate['id'];
					break;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Load and index elementskit_content documents once per request.
	 *
	 * Skips found-row counting and meta/term cache priming (unneeded for this
	 * lookup) and splits results into an exact-match hash map for mega menus
	 * and a smaller candidate list for widget-area prefix matching.
	 * @since 4.0.3
	 * @return void
	 */
	private function load_widget_area_documents() {
		if ( null !== $this->mega_menu_documents ) {
			return;
		}

		$this->mega_menu_documents    = array();
		$this->widget_area_candidates = array();

		$rows = get_posts(
			array(
				'post_type'              => 'elementskit_content',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$widget_prefix        = 'dynamic-content-widget-';
		$widget_prefix_length = strlen( $widget_prefix );

		foreach ( (array) $rows as $row ) {
			$id    = (int) $row->ID;
			$title = (string) $row->post_title;

			if ( 0 === strpos( $title, $widget_prefix ) ) {
				$this->widget_area_candidates[] = array(
					'suffix' => substr( $title, $widget_prefix_length ),
					'id'     => $id,
				);
			} else {
				$this->mega_menu_documents[ $title ] = $id;
			}
		}
	}
}
