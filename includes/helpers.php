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
 * Replace Anki-style cloze deletions (e.g. {{c1::afraid}}) with "_ _ _".
 *
 * Supports multiple cloze indices (c1, c2, c3, ...).
 *
 * @param string $text Input text.
 * @return string Text with cloze content removed.
 */
function dnd_vocab_strip_cloze( $text ) {
    if ( empty( $text ) || ! is_string( $text ) ) {
        return $text;
    }

    return preg_replace( '/\{\{c\d+::.*?\}\}/u', '_ _ _', $text );
}

/**
 * Generate suggestion string from a vocabulary word.
 *
 * Rules:
 * - Always keep first character.
 * - Always keep last character if length > 1.
 * - Keep at least one consonant in the middle (more if desired).
 * - Replace other characters with underscore "_".
 * - Preserve original casing; suggestion length equals original length.
 *
 * @param string $word The original word.
 * @return string The generated suggestion.
 */
function dnd_vocab_generate_suggestion( $word ) {
    $word = (string) $word;

    // Trim spaces; if empty or 1 char, just return the word itself.
    $trimmed = trim( $word );
    $length  = strlen( $trimmed );

    if ( 0 === $length || 1 === $length ) {
        return $trimmed;
    }

    // We'll work on a byte basis; for non‑ASCII you may want mb_* functions.
    $chars = str_split( $trimmed );

    // Positions we must keep.
    $keep = array_fill( 0, $length, false );
    $keep[0]           = true;
    $keep[ $length-1 ] = true;

    // Define vowels set (lowercase for comparison).
    $vowels = array( 'a', 'e', 'i', 'o', 'u', 'y' );

    $candidate_consonants = array();

    // Collect consonant positions between first and last.
    for ( $i = 1; $i < $length - 1; $i++ ) {
        $ch_lower = strtolower( $chars[ $i ] );

        // Only letters are considered; digits/punct will be treated as non-consonant here.
        if ( ctype_alpha( $ch_lower ) && ! in_array( $ch_lower, $vowels, true ) ) {
            $candidate_consonants[] = $i;
        }
    }

    // Always keep at least one middle consonant if exists.
    if ( ! empty( $candidate_consonants ) ) {
        // Take the "middle" consonant for a stable pattern.
        $middle_index = (int) floor( count( $candidate_consonants ) / 2 );
        $keep[ $candidate_consonants[ $middle_index ] ] = true;

        // Optionally, keep one more consonant to make it slightly easier for learners.
        if ( count( $candidate_consonants ) >= 3 ) {
            $extra_index = 0; // first consonant.
            $keep[ $candidate_consonants[ $extra_index ] ] = true;
        }
    }

    // Build raw suggestion (no spaces).
    $suggestion = '';
    for ( $i = 0; $i < $length; $i++ ) {
        if ( $keep[ $i ] ) {
            $suggestion .= $chars[ $i ];
        } else {
            $suggestion .= '_';
        }
    }

    return $suggestion;
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

/**
 * Handle delete vocabulary item action from Deck Vocabulary page.
 */
function dnd_vocab_handle_delete_item_action() {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! isset( $_GET['action'], $_GET['item_id'] ) || 'dnd_vocab_delete_item' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $item_id = absint( $_GET['item_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $deck_id = isset( $_GET['deck_id'] ) ? absint( $_GET['deck_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dnd_vocab_delete_item_' . $item_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    if ( $item_id && 'dnd_vocab_item' === get_post_type( $item_id ) ) {
        wp_trash_post( $item_id );
    }

    $redirect = add_query_arg(
        array(
            'page'              => 'dnd-vocab-deck-items',
            'deck_id'           => $deck_id,
            'dnd_vocab_message' => 'deleted',
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_init', 'dnd_vocab_handle_delete_item_action' );


