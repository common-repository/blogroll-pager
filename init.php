<?php
/*
Plugin Name:	Blogroll Pager
Description:	Blogroll widget, with next & prev controls to load new links via Ajax.
Author:			Hassan Derakhshandeh
Version:		0.1
Author URI:		http://tween.ir/


		* 	Copyright (C) 2011  Hassan Derakhshandeh
		*	http://tween.ir/
		*	hassan.derakhshandeh@gmail.com

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation; either version 2 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * Blogroll Pager widget class
 *
 * @since 0.1
 */
class Blogroll_Pager_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array('description' => __( "Your blogroll with ajax prev & next navigation." ) );
		parent::__construct('blogroll-pager', __('Blogroll Pager'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args, EXTR_SKIP);

		$show_description = isset($instance['description']) ? $instance['description'] : false;
		$show_name = isset($instance['name']) ? $instance['name'] : false;
		$show_rating = isset($instance['rating']) ? $instance['rating'] : false;
		$show_images = isset($instance['images']) ? $instance['images'] : true;
		$category = isset($instance['category']) ? $instance['category'] : false;
		$limit = isset($instance['limit']) ? $instance['limit'] : 5;

		/* for this widget, you must specify the category for it to work */
		if ( ! $category ) {
			return;
		}

		$pager_links = '<a href="#" class="blogroll-pager-next" data-action="next">Next</a><a href="#" class="blogroll-pager-prev" data-action="prev">Prev</a>';
		$data_div = "<div class='blogroll-pager-data' data-limit='{$limit}' data-category='{$category}' data-name='{$show_name}' data-rating='{$show_rating}' data-images='{$show_images}'>";

		$before_widget = preg_replace('/id="[^"]*"/','id="%id"', $before_widget);
		wp_list_bookmarks(apply_filters('widget_links_args', array(
			'title_before' => $before_title, 'title_after' => $after_title,
			'category_before' => $before_widget . $data_div, 'category_after' => '</div>' . $pager_links . $after_widget ,
			'show_images' => $show_images, 'show_description' => $show_description,
			'show_name' => $show_name, 'show_rating' => $show_rating,
			'category' => $category, 'class' => 'linkcat widget',
			'limit' => $limit
		)));
	}

	function update( $new_instance, $old_instance ) {
		$new_instance = (array) $new_instance;
		$instance = array( 'images' => 0, 'name' => 0, 'description' => 0, 'rating' => 0);
		foreach ( $instance as $field => $val ) {
			if ( isset($new_instance[$field]) )
				$instance[$field] = 1;
		}
		$instance['category'] = intval($new_instance['category']);
		$instance['limit'] = intval($new_instance['limit']);

		return $instance;
	}

	function form( $instance ) {

		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'images' => true, 'name' => true, 'description' => false, 'rating' => false, 'category' => false, 'limit' => 5 ) );
		$link_cats = get_terms( 'link_category' );

		require( trailingslashit( dirname( __FILE__ ) ) . 'views/form.php' );
	}

	function register() {
		register_widget( 'Blogroll_Pager_Widget' );
	}

	/**
	 * Queue required scripts and styles
	 * Enable themes to replace the stylesheet for custom styling.
	 *
	 * @since 0.1
	 */
	function queue() {
		wp_enqueue_script( 'blogroll-pager', plugins_url( '/js/blogroll-pager.js', __FILE__ ), array( 'jquery' ) );
		wp_localize_script( 'blogroll-pager', 'links_pager', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );
		if( file_exists( trailingslashit( TEMPLATEPATH ) . 'css/blogroll-pager.css' ) )
			wp_enqueue_style( 'blogroll-pager', trailingslashit( get_template_directory_uri() ) . 'css/blogroll-pager.css' );
		else
			wp_enqueue_style( 'blogroll-pager', plugins_url( '/css/blogroll-pager.css', __FILE__ ) );
	}

	/**
	 * Get all bookmarks
	 *
	 * This is the WP's get_bookmarks function with the addition of 'offset' parameter
	 *
	 */
	function get_bookmarks($args = '') {
		global $wpdb;

		$defaults = array(
			'orderby' => 'name', 'order' => 'ASC',
			'limit' => -1, 'category' => '',
			'category_name' => '', 'hide_invisible' => 1,
			'show_updated' => 0, 'include' => '',
			'exclude' => '', 'search' => '',
			'offset' => 0 // added
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$cache = array();
		$key = md5( serialize( $r ) );
		if ( $cache = wp_cache_get( 'get_bookmarks', 'bookmark' ) ) {
			if ( is_array($cache) && isset( $cache[ $key ] ) )
				return apply_filters('get_bookmarks', $cache[ $key ], $r );
		}

		if ( !is_array($cache) )
			$cache = array();

		$inclusions = '';
		if ( !empty($include) ) {
			$exclude = '';  //ignore exclude, category, and category_name params if using include
			$category = '';
			$category_name = '';
			$inclinks = preg_split('/[\s,]+/',$include);
			if ( count($inclinks) ) {
				foreach ( $inclinks as $inclink ) {
					if (empty($inclusions))
						$inclusions = ' AND ( link_id = ' . intval($inclink) . ' ';
					else
						$inclusions .= ' OR link_id = ' . intval($inclink) . ' ';
				}
			}
		}
		if (!empty($inclusions))
			$inclusions .= ')';

		$exclusions = '';
		if ( !empty($exclude) ) {
			$exlinks = preg_split('/[\s,]+/',$exclude);
			if ( count($exlinks) ) {
				foreach ( $exlinks as $exlink ) {
					if (empty($exclusions))
						$exclusions = ' AND ( link_id <> ' . intval($exlink) . ' ';
					else
						$exclusions .= ' AND link_id <> ' . intval($exlink) . ' ';
				}
			}
		}
		if (!empty($exclusions))
			$exclusions .= ')';

		if ( !empty($category_name) ) {
			if ( $category = get_term_by('name', $category_name, 'link_category') ) {
				$category = $category->term_id;
			} else {
				$cache[ $key ] = array();
				wp_cache_set( 'get_bookmarks', $cache, 'bookmark' );
				return apply_filters( 'get_bookmarks', array(), $r );
			}
		}

		if ( ! empty($search) ) {
			$search = like_escape($search);
			$search = " AND ( (link_url LIKE '%$search%') OR (link_name LIKE '%$search%') OR (link_description LIKE '%$search%') ) ";
		}

		$category_query = '';
		$join = '';
		if ( !empty($category) ) {
			$incategories = preg_split('/[\s,]+/',$category);
			if ( count($incategories) ) {
				foreach ( $incategories as $incat ) {
					if (empty($category_query))
						$category_query = ' AND ( tt.term_id = ' . intval($incat) . ' ';
					else
						$category_query .= ' OR tt.term_id = ' . intval($incat) . ' ';
				}
			}
		}
		if (!empty($category_query)) {
			$category_query .= ") AND taxonomy = 'link_category'";
			$join = " INNER JOIN $wpdb->term_relationships AS tr ON ($wpdb->links.link_id = tr.object_id) INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
		}

		if ( $show_updated && get_option('links_recently_updated_time') ) {
			$recently_updated_test = ", IF (DATE_ADD(link_updated, INTERVAL " . get_option('links_recently_updated_time') . " MINUTE) >= NOW(), 1,0) as recently_updated ";
		} else {
			$recently_updated_test = '';
		}

		$get_updated = ( $show_updated ) ? ', UNIX_TIMESTAMP(link_updated) AS link_updated_f ' : '';

		$orderby = strtolower($orderby);
		$length = '';
		switch ( $orderby ) {
			case 'length':
				$length = ", CHAR_LENGTH(link_name) AS length";
				break;
			case 'rand':
				$orderby = 'rand()';
				break;
			case 'link_id':
				$orderby = "$wpdb->links.link_id";
				break;
			default:
				$orderparams = array();
				foreach ( explode(',', $orderby) as $ordparam ) {
					$ordparam = trim($ordparam);
					$keys = array( 'link_id', 'link_name', 'link_url', 'link_visible', 'link_rating', 'link_owner', 'link_updated', 'link_notes' );
					if ( in_array( 'link_' . $ordparam, $keys ) )
						$orderparams[] = 'link_' . $ordparam;
					elseif ( in_array( $ordparam, $keys ) )
						$orderparams[] = $ordparam;
				}
				$orderby = implode(',', $orderparams);
		}

		if ( empty( $orderby ) )
			$orderby = 'link_name';

		$order = strtoupper( $order );
		if ( '' !== $order && !in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'ASC';

		$visible = '';
		if ( $hide_invisible )
			$visible = "AND link_visible = 'Y'";

		$query = "SELECT * $length $recently_updated_test $get_updated FROM $wpdb->links $join WHERE 1=1 $visible $category_query";
		$query .= " $exclusions $inclusions $search";
		$query .= " ORDER BY $orderby $order";
		if ($limit != -1)
			$query .= " LIMIT $limit";
		$query .= " OFFSET $offset"; // added

		$results = $wpdb->get_results($query);

		$cache[ $key ] = $results;
		wp_cache_set( 'get_bookmarks', $cache, 'bookmark' );

		return apply_filters('get_bookmarks', $results, $r);
	}

	function ajax() {
		$r = array(
			'orderby' => 'name', 'order' => 'ASC',
			'limit' => $_POST['limit'], 'exclude_category' => '',
			'category_name' => '', 'hide_invisible' => 1,
			'show_updated' => 0, 'echo' => 1,
			'categorize' => 1, 'title_li' => '',
			'title_before' => '<h2>', 'title_after' => '</h2>',
			'category_orderby' => 'name', 'category_order' => 'ASC',
			'class' => 'linkcat', 'category_before' => '<li id="%id" class="%class">',
			'category_after' => '</li>',
			'category' => $_POST['category'],
			'offset' => $_POST['offset'],
			'show_rating' => $_POST['show_rating'],
			'show_images' => $_POST['show_images'],
			'show_name' => $_POST['show_name'],
		);

		$bookmarks = self::get_bookmarks($r);
		$output = '';

		if ( !empty($bookmarks) ) {
			$output .= _walk_bookmarks( $bookmarks, $r );
		}
		die( $output );
	}
}
add_action( 'widgets_init', array( 'Blogroll_Pager_Widget', 'register' ) );
add_action( 'template_redirect', array( 'Blogroll_Pager_Widget', 'queue' ) );
if( is_admin() )
	add_action( 'wp_ajax_blogroll_pager_widget', array( 'Blogroll_Pager_Widget', 'ajax' ) );