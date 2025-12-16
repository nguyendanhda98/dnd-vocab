<?php
/**
 * Helper functions for DND Vocab plugin
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if current user can manage DND Vocab
 *
 * @return bool
 */
function dnd_vocab_user_can_manage() {
    return current_user_can( 'manage_options' );
}

/**
 * Get all DND Tags
 *
 * @param array $args Optional. Arguments for get_terms().
 * @return array|WP_Error
 */
function dnd_vocab_get_tags( $args = array() ) {
    $defaults = array(
        'taxonomy'   => 'dnd_tag',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    return get_terms( $args );
}

/**
 * Get a single DND Tag by ID
 *
 * @param int $term_id The term ID.
 * @return WP_Term|WP_Error|false
 */
function dnd_vocab_get_tag( $term_id ) {
    return get_term( $term_id, 'dnd_tag' );
}

/**
 * Create a new DND Tag
 *
 * @param string $name Tag name.
 * @param string $slug Optional. Tag slug.
 * @param string $description Optional. Tag description.
 * @return array|WP_Error
 */
function dnd_vocab_create_tag( $name, $slug = '', $description = '' ) {
    $args = array();
    
    if ( ! empty( $slug ) ) {
        $args['slug'] = sanitize_title( $slug );
    }
    
    if ( ! empty( $description ) ) {
        $args['description'] = sanitize_textarea_field( $description );
    }
    
    return wp_insert_term( sanitize_text_field( $name ), 'dnd_tag', $args );
}

/**
 * Update an existing DND Tag
 *
 * @param int    $term_id Term ID.
 * @param string $name Tag name.
 * @param string $slug Optional. Tag slug.
 * @param string $description Optional. Tag description.
 * @return array|WP_Error
 */
function dnd_vocab_update_tag( $term_id, $name, $slug = '', $description = '' ) {
    $args = array(
        'name' => sanitize_text_field( $name ),
    );
    
    if ( ! empty( $slug ) ) {
        $args['slug'] = sanitize_title( $slug );
    }
    
    if ( isset( $description ) ) {
        $args['description'] = sanitize_textarea_field( $description );
    }
    
    return wp_update_term( $term_id, 'dnd_tag', $args );
}

/**
 * Delete a DND Tag
 *
 * @param int $term_id Term ID.
 * @return bool|WP_Error
 */
function dnd_vocab_delete_tag( $term_id ) {
    return wp_delete_term( $term_id, 'dnd_tag' );
}

/**
 * Get count of decks using a specific tag
 *
 * @param int $term_id Term ID.
 * @return int
 */
function dnd_vocab_get_tag_deck_count( $term_id ) {
    $term = get_term( $term_id, 'dnd_tag' );
    
    if ( is_wp_error( $term ) || ! $term ) {
        return 0;
    }
    
    return (int) $term->count;
}

/**
 * Display admin notice
 *
 * @param string $message Notice message.
 * @param string $type Notice type: success, error, warning, info.
 */
function dnd_vocab_admin_notice( $message, $type = 'success' ) {
    $class = 'notice notice-' . esc_attr( $type ) . ' is-dismissible';
    printf( '<div class="%1$s"><p>%2$s</p></div>', $class, esc_html( $message ) );
}

