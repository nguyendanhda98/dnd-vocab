<?php
/**
 * Register Custom Post Types and Taxonomies
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the custom post types
 */
function dnd_vocab_register_post_types() {
    // Deck CPT.
    $deck_labels = array(
        'name'                  => _x( 'Decks', 'Post type general name', 'dnd-vocab' ),
        'singular_name'         => _x( 'Deck', 'Post type singular name', 'dnd-vocab' ),
        'menu_name'             => _x( 'Decks', 'Admin Menu text', 'dnd-vocab' ),
        'name_admin_bar'        => _x( 'Deck', 'Add New on Toolbar', 'dnd-vocab' ),
        'add_new'               => __( 'Add New', 'dnd-vocab' ),
        'add_new_item'          => __( 'Add New Deck', 'dnd-vocab' ),
        'new_item'              => __( 'New Deck', 'dnd-vocab' ),
        'edit_item'             => __( 'Edit Deck', 'dnd-vocab' ),
        'view_item'             => __( 'View Deck', 'dnd-vocab' ),
        'all_items'             => __( 'All Decks', 'dnd-vocab' ),
        'search_items'          => __( 'Search Decks', 'dnd-vocab' ),
        'parent_item_colon'     => __( 'Parent Decks:', 'dnd-vocab' ),
        'not_found'             => __( 'No decks found.', 'dnd-vocab' ),
        'not_found_in_trash'    => __( 'No decks found in Trash.', 'dnd-vocab' ),
        'featured_image'        => _x( 'Deck Cover Image', 'Overrides the "Featured Image" phrase', 'dnd-vocab' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'dnd-vocab' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'dnd-vocab' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'dnd-vocab' ),
        'archives'              => _x( 'Deck archives', 'The post type archive label', 'dnd-vocab' ),
        'insert_into_item'      => _x( 'Insert into deck', 'Overrides the "Insert into post" phrase', 'dnd-vocab' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this deck', 'Overrides the "Uploaded to this post" phrase', 'dnd-vocab' ),
        'filter_items_list'     => _x( 'Filter decks list', 'Screen reader text', 'dnd-vocab' ),
        'items_list_navigation' => _x( 'Decks list navigation', 'Screen reader text', 'dnd-vocab' ),
        'items_list'            => _x( 'Decks list', 'Screen reader text', 'dnd-vocab' ),
    );

    $deck_args = array(
        'labels'             => $deck_labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => false, // We'll add it to our custom menu
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'dnd-deck' ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'dnd_deck', $deck_args );

    // Vocabulary Item CPT.
    $vocab_labels = array(
        'name'                  => _x( 'Vocabulary Items', 'Post type general name', 'dnd-vocab' ),
        'singular_name'         => _x( 'Vocabulary Item', 'Post type singular name', 'dnd-vocab' ),
        'menu_name'             => _x( 'Vocabulary', 'Admin Menu text', 'dnd-vocab' ),
        'name_admin_bar'        => _x( 'Vocabulary Item', 'Add New on Toolbar', 'dnd-vocab' ),
        'add_new'               => __( 'Add New', 'dnd-vocab' ),
        'add_new_item'          => __( 'Add New Vocabulary Item', 'dnd-vocab' ),
        'new_item'              => __( 'New Vocabulary Item', 'dnd-vocab' ),
        'edit_item'             => __( 'Edit Vocabulary Item', 'dnd-vocab' ),
        'view_item'             => __( 'View Vocabulary Item', 'dnd-vocab' ),
        'all_items'             => __( 'All Vocabulary Items', 'dnd-vocab' ),
        'search_items'          => __( 'Search Vocabulary Items', 'dnd-vocab' ),
        'parent_item_colon'     => __( 'Parent Vocabulary Items:', 'dnd-vocab' ),
        'not_found'             => __( 'No vocabulary items found.', 'dnd-vocab' ),
        'not_found_in_trash'    => __( 'No vocabulary items found in Trash.', 'dnd-vocab' ),
        'featured_image'        => _x( 'Image', 'Overrides the \"Featured Image\" phrase', 'dnd-vocab' ),
        'set_featured_image'    => _x( 'Set image', 'Overrides the \"Set featured image\" phrase', 'dnd-vocab' ),
        'remove_featured_image' => _x( 'Remove image', 'Overrides the \"Remove featured image\" phrase', 'dnd-vocab' ),
        'use_featured_image'    => _x( 'Use as image', 'Overrides the \"Use as featured image\" phrase', 'dnd-vocab' ),
        'archives'              => _x( 'Vocabulary archives', 'The post type archive label', 'dnd-vocab' ),
        'insert_into_item'      => _x( 'Insert into vocabulary item', 'Overrides the \"Insert into post\" phrase', 'dnd-vocab' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this vocabulary item', 'Overrides the \"Uploaded to this post\" phrase', 'dnd-vocab' ),
        'filter_items_list'     => _x( 'Filter vocabulary items list', 'Screen reader text', 'dnd-vocab' ),
        'items_list_navigation' => _x( 'Vocabulary items list navigation', 'Screen reader text', 'dnd-vocab' ),
        'items_list'            => _x( 'Vocabulary items list', 'Screen reader text', 'dnd-vocab' ),
    );

    $vocab_args = array(
        'labels'             => $vocab_labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => false, // Managed via Deck pages / custom links.
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'dnd-vocab-item' ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'dnd_vocab_item', $vocab_args );
}
add_action( 'init', 'dnd_vocab_register_post_types' );

/**
 * Register the Tag taxonomy for Decks
 */
function dnd_vocab_register_taxonomies() {
    $labels = array(
        'name'                       => _x( 'Tags', 'taxonomy general name', 'dnd-vocab' ),
        'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'dnd-vocab' ),
        'search_items'               => __( 'Search Tags', 'dnd-vocab' ),
        'popular_items'              => __( 'Popular Tags', 'dnd-vocab' ),
        'all_items'                  => __( 'All Tags', 'dnd-vocab' ),
        'parent_item'                => null,
        'parent_item_colon'          => null,
        'edit_item'                  => __( 'Edit Tag', 'dnd-vocab' ),
        'update_item'                => __( 'Update Tag', 'dnd-vocab' ),
        'add_new_item'               => __( 'Add New Tag', 'dnd-vocab' ),
        'new_item_name'              => __( 'New Tag Name', 'dnd-vocab' ),
        'separate_items_with_commas' => __( 'Separate tags with commas', 'dnd-vocab' ),
        'add_or_remove_items'        => __( 'Add or remove tags', 'dnd-vocab' ),
        'choose_from_most_used'      => __( 'Choose from the most used tags', 'dnd-vocab' ),
        'not_found'                  => __( 'No tags found.', 'dnd-vocab' ),
        'menu_name'                  => __( 'Tags', 'dnd-vocab' ),
        'back_to_items'              => __( '← Back to Tags', 'dnd-vocab' ),
    );

    $args = array(
        'hierarchical'          => false,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_in_menu'          => false, // We manage tags in our settings page
        'show_admin_column'     => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'dnd-tag' ),
        'show_in_rest'          => true,
    );

    register_taxonomy( 'dnd_tag', array( 'dnd_deck' ), $args );
}
add_action( 'init', 'dnd_vocab_register_taxonomies' );

