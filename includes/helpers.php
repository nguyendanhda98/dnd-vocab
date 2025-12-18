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

/**
 * ------------------------------
 * Spaced Repetition (SRS) helpers
 * ------------------------------
 */

/**
 * Get SRS data for a user.
 *
 * @param int $user_id User ID.
 * @return array
 */
function dnd_vocab_get_user_srs_data( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array();
    }

    $data = get_user_meta( $user_id, 'dnd_vocab_srs_data', true );

    if ( ! is_array( $data ) ) {
        $data = array();
    }

    return $data;
}

/**
 * Save SRS data for a user.
 *
 * @param int   $user_id User ID.
 * @param array $data    SRS data.
 */
function dnd_vocab_save_user_srs_data( $user_id, $data ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return;
    }

    if ( ! is_array( $data ) ) {
        $data = array();
    }

    update_user_meta( $user_id, 'dnd_vocab_srs_data', $data );
}

/**
 * Apply spaced-repetition answer for a vocabulary item.
 *
 * @param int $user_id  User ID.
 * @param int $vocab_id Vocabulary item ID.
 * @param int $deck_id  Deck ID.
 * @param int $rating   Quality rating (0, 3, 5).
 */
function dnd_vocab_srs_apply_answer( $user_id, $vocab_id, $deck_id, $rating ) {
    $user_id  = (int) $user_id;
    $vocab_id = (int) $vocab_id;
    $deck_id  = (int) $deck_id;

    if ( $user_id <= 0 || $vocab_id <= 0 || $deck_id <= 0 ) {
        return;
    }

    $quality = (int) $rating;

    if ( $quality < 0 ) {
        $quality = 0;
    } elseif ( $quality > 5 ) {
        $quality = 5;
    }

    $now  = current_time( 'timestamp' );
    $data = dnd_vocab_get_user_srs_data( $user_id );

    if ( isset( $data[ $vocab_id ] ) && is_array( $data[ $vocab_id ] ) ) {
        $card = $data[ $vocab_id ];
    } else {
        $card = array(
            'deck_id'      => $deck_id,
            'interval'     => 1,
            'repetitions'  => 0,
            'ease_factor'  => 2.5,
            'due'          => $now,
            'status'       => 'new',
        );
    }

    $interval    = isset( $card['interval'] ) ? (int) $card['interval'] : 1;
    $repetitions = isset( $card['repetitions'] ) ? (int) $card['repetitions'] : 0;
    $ease_factor = isset( $card['ease_factor'] ) ? (float) $card['ease_factor'] : 2.5;

    if ( $quality < 3 ) {
        // Again / failed.
        $repetitions = 0;
        $interval    = 1;
        $ease_factor -= 0.2;

        if ( $ease_factor < 1.3 ) {
            $ease_factor = 1.3;
        }

        $card['status'] = 'learning';
    } else {
        // Passed.
        $repetitions++;

        if ( 1 === $repetitions ) {
            $interval = 1;
        } elseif ( 2 === $repetitions ) {
            $interval = 6;
        } else {
            $interval = max( 1, (int) round( $interval * $ease_factor ) );
        }

        // SM-2 ease factor update.
        $ease_factor = $ease_factor + ( 0.1 - ( 5 - $quality ) * ( 0.08 + ( 5 - $quality ) * 0.02 ) );

        if ( $ease_factor < 1.3 ) {
            $ease_factor = 1.3;
        }

        $card['status'] = 'review';
    }

    $card['deck_id']      = $deck_id;
    $card['interval']     = $interval;
    $card['repetitions']  = $repetitions;
    $card['ease_factor']  = $ease_factor;
    $card['last_review']  = $now;
    $card['due']          = $now + ( $interval * DAY_IN_SECONDS );

    $data[ $vocab_id ] = $card;

    dnd_vocab_save_user_srs_data( $user_id, $data );
}

/**
 * Get all due vocabulary item IDs for a user within the given decks.
 *
 * @param int   $user_id  User ID.
 * @param array $deck_ids Allowed deck IDs.
 * @return array List of vocabulary IDs due for review.
 */
function dnd_vocab_srs_get_due_items( $user_id, $deck_ids ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array();
    }

    if ( ! is_array( $deck_ids ) || empty( $deck_ids ) ) {
        return array();
    }

    $deck_ids = array_map( 'absint', $deck_ids );
    $deck_ids = array_filter( $deck_ids );

    if ( empty( $deck_ids ) ) {
        return array();
    }

    $data = dnd_vocab_get_user_srs_data( $user_id );

    if ( empty( $data ) ) {
        return array();
    }

    $now       = current_time( 'timestamp' );
    $due_items = array();

    foreach ( $data as $vocab_id => $card ) {
        $vocab_id = (int) $vocab_id;

        if ( $vocab_id <= 0 || ! is_array( $card ) ) {
            continue;
        }

        $card_deck_id = isset( $card['deck_id'] ) ? (int) $card['deck_id'] : 0;

        if ( ! in_array( $card_deck_id, $deck_ids, true ) ) {
            continue;
        }

        $due_timestamp = isset( $card['due'] ) ? (int) $card['due'] : 0;

        if ( $due_timestamp > 0 && $due_timestamp <= $now ) {
            $due_items[ $vocab_id ] = $due_timestamp;
        }
    }

    if ( empty( $due_items ) ) {
        return array();
    }

    // Sort by due time ascending so that older items are reviewed first.
    asort( $due_items, SORT_NUMERIC );

    return array_keys( $due_items );
}

/**
 * Pick a new (never studied) vocabulary item for a user within given decks.
 *
 * @param int   $user_id  User ID.
 * @param array $deck_ids Allowed deck IDs.
 * @return int Vocabulary item ID or 0 if none.
 */
function dnd_vocab_srs_pick_next_new_item( $user_id, $deck_ids ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return 0;
    }

    if ( ! is_array( $deck_ids ) || empty( $deck_ids ) ) {
        return 0;
    }

    $deck_ids = array_map( 'absint', $deck_ids );
    $deck_ids = array_filter( $deck_ids );

    if ( empty( $deck_ids ) ) {
        return 0;
    }

    $data      = dnd_vocab_get_user_srs_data( $user_id );
    $known_ids = array_keys( $data );

    $args = array(
        'post_type'      => 'dnd_vocab_item',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'rand',
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_dnd_vocab_deck_id',
                'value'   => $deck_ids,
                'compare' => 'IN',
            ),
        ),
    );

    if ( ! empty( $known_ids ) ) {
        $args['post__not_in'] = array_map( 'absint', $known_ids );
    }

    $posts = get_posts( $args );

    if ( empty( $posts ) || ! is_array( $posts ) ) {
        return 0;
    }

    return (int) $posts[0];
}
