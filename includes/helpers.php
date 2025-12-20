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
 * Get SRS card info for a specific vocabulary item of a user.
 *
 * @param int $user_id  User ID.
 * @param int $vocab_id Vocabulary item ID.
 * @return array|null   Card data array or null if not found.
 */
function dnd_vocab_srs_get_card_info( $user_id, $vocab_id ) {
	$user_id  = (int) $user_id;
	$vocab_id = (int) $vocab_id;

	if ( $user_id <= 0 || $vocab_id <= 0 ) {
		return null;
	}

	$data = dnd_vocab_get_user_srs_data( $user_id );

	if ( isset( $data[ $vocab_id ] ) && is_array( $data[ $vocab_id ] ) ) {
		return $data[ $vocab_id ];
	}

	return null;
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

/* ==========================================================================
   PHASE CONSTANTS AND DEFINITIONS
   ========================================================================== */

/**
 * Card phase constants.
 * 
 * PHASE_NEW: Card has never been studied - uses learning presets
 * PHASE_LEARNING: Card is in first learning step - uses learning presets
 * PHASE_TRANSITION: Card passed learning, preparing for review - uses transition presets
 * PHASE_REVIEW: Card is in review phase - uses FSRS algorithm
 */
define( 'DND_VOCAB_PHASE_NEW', 'new' );
define( 'DND_VOCAB_PHASE_LEARNING', 'learning' );
define( 'DND_VOCAB_PHASE_TRANSITION', 'transition' );
define( 'DND_VOCAB_PHASE_REVIEW', 'review' );

/**
 * Get preset intervals for NEW/LEARNING phase.
 * 
 * These are fixed intervals (in seconds) for brand new cards.
 * 
 * @return array Mapping of rating => interval in seconds
 */
function dnd_vocab_get_learning_intervals() {
	return array(
		1 => 1 * MINUTE_IN_SECONDS,   // Again: 1 minute
		2 => 5 * MINUTE_IN_SECONDS,   // Hard: 5 minutes
		3 => 10 * MINUTE_IN_SECONDS,  // Good: 10 minutes
		4 => 2 * DAY_IN_SECONDS,      // Easy: 2 days (skip to review)
	);
}

/**
 * Get preset intervals for TRANSITION phase.
 * 
 * These are fixed intervals (in seconds) for cards that passed initial learning.
 * After Good/Easy in transition, card moves to REVIEW phase.
 * 
 * @return array Mapping of rating => interval in seconds
 */
function dnd_vocab_get_transition_intervals() {
	return array(
		1 => 5 * MINUTE_IN_SECONDS,   // Again: 5 minutes
		2 => 30 * MINUTE_IN_SECONDS,  // Hard: 30 minutes
		3 => 1 * DAY_IN_SECONDS,      // Good: 1 day (graduates to review)
		4 => 2 * DAY_IN_SECONDS,      // Easy: 2 days (graduates to review)
	);
}

/**
 * Get card phase from card data.
 * 
 * Determines the current phase of a card based on its state.
 * 
 * @param array|null $card Card data or null for new card.
 * @return string Phase constant (DND_VOCAB_PHASE_*)
 */
function dnd_vocab_get_card_phase( $card ) {
	// Null or empty card = new card
	if ( ! is_array( $card ) || empty( $card ) ) {
		return DND_VOCAB_PHASE_NEW;
	}

	// If card has explicit phase field, use it
	if ( isset( $card['phase'] ) && ! empty( $card['phase'] ) ) {
		$phase = $card['phase'];
		// Validate phase
		if ( in_array( $phase, array( DND_VOCAB_PHASE_NEW, DND_VOCAB_PHASE_LEARNING, DND_VOCAB_PHASE_TRANSITION, DND_VOCAB_PHASE_REVIEW ), true ) ) {
			return $phase;
		}
	}

	// Legacy card migration: determine phase from old fields
	// If card has stability field and meaningful value, it's in review
	if ( isset( $card['stability'] ) && (float) $card['stability'] > 0.5 ) {
		// Check if it was migrated from old SM-2 system
		if ( isset( $card['interval'] ) && (int) $card['interval'] >= 1 ) {
			return DND_VOCAB_PHASE_REVIEW;
		}
	}

	// Check old status field for backward compatibility
	if ( isset( $card['status'] ) ) {
		$status = $card['status'];
		if ( 'review' === $status ) {
			return DND_VOCAB_PHASE_REVIEW;
		}
		if ( 'learning' === $status ) {
			return DND_VOCAB_PHASE_LEARNING;
		}
		if ( 'new' === $status ) {
			return DND_VOCAB_PHASE_NEW;
		}
	}

	// Default: if has any review history, assume review phase
	if ( isset( $card['last_review'] ) || isset( $card['last_review_time'] ) ) {
		return DND_VOCAB_PHASE_REVIEW;
	}

	return DND_VOCAB_PHASE_NEW;
}

/**
 * Initialize FSRS state when entering review phase.
 * 
 * Called when card graduates from transition to review phase.
 * Sets initial FSRS values based on the graduation rating.
 * 
 * @param int $rating The rating that triggered graduation (3=Good, 4=Easy)
 * @return array Initial FSRS CardState
 */
function dnd_vocab_init_fsrs_state_for_review( $rating ) {
	if ( $rating === 4 ) {
		// Easy: higher initial stability, lower difficulty
		return array(
			'stability'         => 2.0,
			'difficulty'        => 4.5,
			'last_review_time'  => time() * 1000,
			'lapse_count'       => 0,
			'consecutive_fails' => 0,
		);
	}

	// Good (default): standard initial values
	return array(
		'stability'         => 1.0,
		'difficulty'        => 4.8,
		'last_review_time'  => time() * 1000,
		'lapse_count'       => 0,
		'consecutive_fails' => 0,
	);
}

/**
 * Convert card data to FSRS CardState format.
 *
 * Handles migration from old cards and extracts FSRS-relevant fields.
 * Phase field is handled separately - this only extracts FSRS state for review phase.
 *
 * @param array|null $card Card data or null for new card.
 * @return array FSRS CardState
 */
function dnd_vocab_fsrs_get_card_state( $card ) {
    if ( ! function_exists( 'fsrs_create_initial_card_state' ) ) {
        // FSRS not available, return empty state
        return array(
            'stability'         => 1.0,
            'difficulty'        => 5.0,
            'last_review_time'  => 0,
            'lapse_count'       => 0,
            'consecutive_fails' => 0,
        );
    }

    // If card is null or doesn't exist, return initial state
    if ( ! is_array( $card ) || empty( $card ) ) {
        return fsrs_create_initial_card_state();
    }

    // Check if card already has FSRS format (has stability and difficulty)
    if ( isset( $card['stability'] ) && isset( $card['difficulty'] ) ) {
        // Get last_review_time
        $last_review_time = 0;
        if ( isset( $card['last_review_time'] ) ) {
            $last_review_time = (int) $card['last_review_time'];
        } elseif ( isset( $card['last_review'] ) ) {
            $last_review_time = (int) $card['last_review'] * 1000;
        }

        return array(
            'stability'         => (float) $card['stability'],
            'difficulty'        => (float) $card['difficulty'],
            'last_review_time'  => $last_review_time,
            'lapse_count'       => isset( $card['lapse_count'] ) ? (int) $card['lapse_count'] : 0,
            'consecutive_fails' => isset( $card['consecutive_fails'] ) ? (int) $card['consecutive_fails'] : 0,
        );
    }

    // Migrate from old SM-2 format
    // Estimate stability from interval: interval ≈ -stability * ln(0.9)
    $interval = isset( $card['interval'] ) ? (float) $card['interval'] : 1.0;
    $stability = 1.0;
    if ( $interval > 1.0 ) {
        $stability = max( 1.0, $interval / ( -log( 0.9 ) ) );
    }

    // Estimate difficulty from ease_factor
    $ease_factor = isset( $card['ease_factor'] ) ? (float) $card['ease_factor'] : 2.5;
    $difficulty = 5.0;
    if ( $ease_factor >= 1.3 && $ease_factor <= 2.5 ) {
        $difficulty = 10.0 - ( ( $ease_factor - 1.3 ) / ( 2.5 - 1.3 ) ) * 9.0;
        $difficulty = max( 1.0, min( 10.0, $difficulty ) );
    }

    // Estimate lapse_count
    $lapse_count = isset( $card['lapse_count'] ) ? (int) $card['lapse_count'] : 0;

    // Get last_review_time
    $last_review_time = 0;
    if ( isset( $card['last_review_time'] ) ) {
        $last_review_time = (int) $card['last_review_time'];
        if ( $last_review_time < 946684800000 ) {
            $last_review_time = $last_review_time * 1000;
        }
    } elseif ( isset( $card['last_review'] ) ) {
        $last_review_time = (int) $card['last_review'] * 1000;
    }

    return array(
        'stability'         => $stability,
        'difficulty'        => $difficulty,
        'last_review_time'  => $last_review_time,
        'lapse_count'       => $lapse_count,
        'consecutive_fails' => isset( $card['consecutive_fails'] ) ? (int) $card['consecutive_fails'] : 0,
    );
}

/**
 * Save card state to storage format.
 *
 * Converts card state (with phase) to a format that can be stored in user meta.
 * Includes phase field and FSRS state when applicable.
 *
 * @param array  $fsrs_state FSRS CardState (can be empty for learning phase)
 * @param int    $deck_id    Deck ID
 * @param int    $due_time   Next review timestamp (Unix seconds)
 * @param string $phase      Card phase (DND_VOCAB_PHASE_*)
 * @return array Card data ready for storage
 */
function dnd_vocab_fsrs_save_card_state( $fsrs_state, $deck_id, $due_time, $phase = DND_VOCAB_PHASE_REVIEW ) {
    if ( ! is_array( $fsrs_state ) ) {
        $fsrs_state = array();
    }

    // Calculate interval from stability for compatibility
    $stability = isset( $fsrs_state['stability'] ) ? (float) $fsrs_state['stability'] : 1.0;
    $target_retention = 0.9;
    $interval = max( 1.0, -$stability * log( $target_retention ) );

    // Convert last_review_time to seconds for compatibility
    $last_review_time = isset( $fsrs_state['last_review_time'] ) ? (int) $fsrs_state['last_review_time'] : 0;
    $last_review = 0;
    if ( $last_review_time > 0 ) {
        if ( $last_review_time > 1e12 ) {
            $last_review = (int) ( $last_review_time / 1000 );
        } else {
            $last_review = $last_review_time;
        }
    }

    return array(
        // Phase tracking (primary)
        'phase'             => $phase,
        // FSRS format
        'stability'         => $stability,
        'difficulty'        => isset( $fsrs_state['difficulty'] ) ? (float) $fsrs_state['difficulty'] : 5.0,
        'last_review_time'  => $last_review_time,
        'lapse_count'       => isset( $fsrs_state['lapse_count'] ) ? (int) $fsrs_state['lapse_count'] : 0,
        'consecutive_fails' => isset( $fsrs_state['consecutive_fails'] ) ? (int) $fsrs_state['consecutive_fails'] : 0,
        // Compatibility fields
        'deck_id'           => (int) $deck_id,
        'interval'          => (int) round( $interval ),
        'due'               => (int) $due_time,
        'last_review'       => $last_review,
        'status'            => $phase, // For backward compatibility
    );
}

/**
 * Apply spaced-repetition answer for a vocabulary item (persist to user meta & history).
 *
 * Handles three phases:
 * - NEW/LEARNING: Uses preset intervals
 * - TRANSITION: Uses transition presets, graduates to REVIEW on Good/Easy
 * - REVIEW: Uses FSRS algorithm
 *
 * @param int $user_id  User ID.
 * @param int $vocab_id Vocabulary item ID.
 * @param int $deck_id  Deck ID.
 * @param int $rating   Quality rating (1=Again, 2=Hard, 3=Good, 4=Easy).
 */
function dnd_vocab_srs_apply_answer( $user_id, $vocab_id, $deck_id, $rating ) {
    $user_id  = (int) $user_id;
    $vocab_id = (int) $vocab_id;
    $deck_id  = (int) $deck_id;

    if ( $user_id <= 0 || $vocab_id <= 0 || $deck_id <= 0 ) {
        return;
    }

    // Validate rating (should be 1-4)
    $rating = (int) $rating;
    if ( $rating < 1 || $rating > 4 ) {
        return;
    }

    $now  = current_time( 'timestamp' );
    $data = dnd_vocab_get_user_srs_data( $user_id );

    $existing_card = isset( $data[ $vocab_id ] ) && is_array( $data[ $vocab_id ] ) ? $data[ $vocab_id ] : null;

    // Determine current phase
    $current_phase = dnd_vocab_get_card_phase( $existing_card );

    // Process based on phase
    if ( $current_phase === DND_VOCAB_PHASE_NEW || $current_phase === DND_VOCAB_PHASE_LEARNING ) {
        // NEW/LEARNING PHASE: Use preset intervals
        $card = dnd_vocab_apply_learning_phase( $existing_card, $deck_id, $rating, $now );
    } elseif ( $current_phase === DND_VOCAB_PHASE_TRANSITION ) {
        // TRANSITION PHASE: Use transition presets
        $card = dnd_vocab_apply_transition_phase( $existing_card, $deck_id, $rating, $now );
    } else {
        // REVIEW PHASE: Use FSRS
        $card = dnd_vocab_apply_review_phase( $existing_card, $deck_id, $rating, $now );
    }

    // Save the card
    $data[ $vocab_id ] = $card;
    dnd_vocab_save_user_srs_data( $user_id, $data );

    // Log review to history for heatmap
    $log_phase = isset( $card['phase'] ) ? $card['phase'] : $current_phase;
    dnd_vocab_log_review( $user_id, $vocab_id, $deck_id, $rating, $log_phase );
}

/**
 * Apply learning phase logic (NEW/LEARNING).
 *
 * Uses preset intervals. Moves to TRANSITION after Good, directly to REVIEW after Easy.
 *
 * @param array|null $card    Existing card or null
 * @param int        $deck_id Deck ID
 * @param int        $rating  Rating (1-4)
 * @param int        $now     Current timestamp
 * @return array Updated card
 */
function dnd_vocab_apply_learning_phase( $card, $deck_id, $rating, $now ) {
    $intervals = dnd_vocab_get_learning_intervals();
    $interval_seconds = isset( $intervals[ $rating ] ) ? $intervals[ $rating ] : $intervals[3];

    // Determine next phase based on rating
    if ( $rating === 4 ) {
        // Easy: Skip directly to REVIEW phase
        $fsrs_state = dnd_vocab_init_fsrs_state_for_review( $rating );
        $due_time = $now + $interval_seconds;
        return dnd_vocab_fsrs_save_card_state( $fsrs_state, $deck_id, $due_time, DND_VOCAB_PHASE_REVIEW );
    } elseif ( $rating === 3 ) {
        // Good: Move to TRANSITION phase
        $due_time = $now + $interval_seconds;
        return array(
            'phase'             => DND_VOCAB_PHASE_TRANSITION,
            'deck_id'           => (int) $deck_id,
            'due'               => $due_time,
            'last_review'       => $now,
            'last_review_time'  => $now * 1000,
            'stability'         => 0.5, // Placeholder for learning
            'difficulty'        => 5.0,
            'lapse_count'       => 0,
            'consecutive_fails' => 0,
            'interval'          => 0, // Learning interval, not day-based
            'status'            => DND_VOCAB_PHASE_TRANSITION,
        );
    } else {
        // Again or Hard: Stay in LEARNING phase
        $due_time = $now + $interval_seconds;
        $consecutive_fails = 0;
        if ( is_array( $card ) && isset( $card['consecutive_fails'] ) ) {
            $consecutive_fails = (int) $card['consecutive_fails'];
        }
        if ( $rating === 1 ) {
            $consecutive_fails++;
        }
        
        return array(
            'phase'             => DND_VOCAB_PHASE_LEARNING,
            'deck_id'           => (int) $deck_id,
            'due'               => $due_time,
            'last_review'       => $now,
            'last_review_time'  => $now * 1000,
            'stability'         => 0.5,
            'difficulty'        => 5.0,
            'lapse_count'       => 0,
            'consecutive_fails' => $consecutive_fails,
            'interval'          => 0,
            'status'            => DND_VOCAB_PHASE_LEARNING,
        );
    }
}

/**
 * Apply transition phase logic.
 *
 * Uses transition presets. Graduates to REVIEW on Good/Easy.
 *
 * @param array|null $card    Existing card
 * @param int        $deck_id Deck ID
 * @param int        $rating  Rating (1-4)
 * @param int        $now     Current timestamp
 * @return array Updated card
 */
function dnd_vocab_apply_transition_phase( $card, $deck_id, $rating, $now ) {
    $intervals = dnd_vocab_get_transition_intervals();
    $interval_seconds = isset( $intervals[ $rating ] ) ? $intervals[ $rating ] : $intervals[3];

    // Get existing lapse/fail counts
    $lapse_count = is_array( $card ) && isset( $card['lapse_count'] ) ? (int) $card['lapse_count'] : 0;
    $consecutive_fails = is_array( $card ) && isset( $card['consecutive_fails'] ) ? (int) $card['consecutive_fails'] : 0;

    if ( $rating === 3 || $rating === 4 ) {
        // Good or Easy: Graduate to REVIEW phase
        $fsrs_state = dnd_vocab_init_fsrs_state_for_review( $rating );
        // Carry over lapse counts
        $fsrs_state['lapse_count'] = $lapse_count;
        $fsrs_state['consecutive_fails'] = 0; // Reset on graduation
        $due_time = $now + $interval_seconds;
        return dnd_vocab_fsrs_save_card_state( $fsrs_state, $deck_id, $due_time, DND_VOCAB_PHASE_REVIEW );
    } elseif ( $rating === 1 ) {
        // Again: Back to LEARNING phase with incremented fail count
        $intervals_learning = dnd_vocab_get_learning_intervals();
        $due_time = $now + $intervals_learning[1]; // 1 minute
        return array(
            'phase'             => DND_VOCAB_PHASE_LEARNING,
            'deck_id'           => (int) $deck_id,
            'due'               => $due_time,
            'last_review'       => $now,
            'last_review_time'  => $now * 1000,
            'stability'         => 0.5,
            'difficulty'        => 5.0,
            'lapse_count'       => $lapse_count + 1,
            'consecutive_fails' => $consecutive_fails + 1,
            'interval'          => 0,
            'status'            => DND_VOCAB_PHASE_LEARNING,
        );
    } else {
        // Hard: Stay in TRANSITION phase
        $due_time = $now + $interval_seconds;
        return array(
            'phase'             => DND_VOCAB_PHASE_TRANSITION,
            'deck_id'           => (int) $deck_id,
            'due'               => $due_time,
            'last_review'       => $now,
            'last_review_time'  => $now * 1000,
            'stability'         => 0.5,
            'difficulty'        => 5.0,
            'lapse_count'       => $lapse_count,
            'consecutive_fails' => $consecutive_fails,
            'interval'          => 0,
            'status'            => DND_VOCAB_PHASE_TRANSITION,
        );
    }
}

/**
 * Apply review phase logic using FSRS.
 *
 * Uses FSRS algorithm. On Again, enters relearning (short interval).
 *
 * @param array|null $card    Existing card
 * @param int        $deck_id Deck ID
 * @param int        $rating  Rating (1-4)
 * @param int        $now     Current timestamp
 * @return array Updated card
 */
function dnd_vocab_apply_review_phase( $card, $deck_id, $rating, $now ) {
    // Get existing FSRS state
    $fsrs_state = dnd_vocab_fsrs_get_card_state( $card );

    // Handle Again (lapse) - use relearning interval
    if ( $rating === 1 ) {
        // Relearning: 5-10 minute interval
        $relearn_seconds = 5 * MINUTE_IN_SECONDS;
        $due_time = $now + $relearn_seconds;
        
        // Update FSRS state for lapse
        $new_lapse_count = $fsrs_state['lapse_count'] + 1;
        $new_consecutive_fails = $fsrs_state['consecutive_fails'] + 1;
        
        // Reduce stability on lapse (multiply by 0.5)
        $new_stability = max( 0.5, $fsrs_state['stability'] * 0.5 );
        // Increase difficulty on lapse
        $new_difficulty = min( 10.0, $fsrs_state['difficulty'] + 0.6 );
        
        $updated_state = array(
            'stability'         => $new_stability,
            'difficulty'        => $new_difficulty,
            'last_review_time'  => $now * 1000,
            'lapse_count'       => $new_lapse_count,
            'consecutive_fails' => $new_consecutive_fails,
        );
        
        return dnd_vocab_fsrs_save_card_state( $updated_state, $deck_id, $due_time, DND_VOCAB_PHASE_REVIEW );
    }

    // For Hard/Good/Easy, use FSRS
    if ( ! function_exists( 'fsrs_create_review_event' ) || ! function_exists( 'fsrs_plusplus_review' ) ) {
        // Fallback if FSRS functions not available
        $base_stability = $fsrs_state['stability'];
        if ( $rating === 2 ) {
            $new_stability = $base_stability * 1.0;
        } elseif ( $rating === 3 ) {
            $new_stability = $base_stability * 1.8;
        } else {
            $new_stability = $base_stability * 2.5;
        }
        
        $interval_days = max( 1.0, -$new_stability * log( 0.9 ) );
        $due_time = $now + (int) ( $interval_days * DAY_IN_SECONDS );
        
        $updated_state = array(
            'stability'         => $new_stability,
            'difficulty'        => $fsrs_state['difficulty'],
            'last_review_time'  => $now * 1000,
            'lapse_count'       => $fsrs_state['lapse_count'],
            'consecutive_fails' => 0,
        );
        
        return dnd_vocab_fsrs_save_card_state( $updated_state, $deck_id, $due_time, DND_VOCAB_PHASE_REVIEW );
    }

    // Calculate elapsed days
    $last_review_time = $fsrs_state['last_review_time'];
    if ( $last_review_time > 1e12 ) {
        $last_review_seconds = $last_review_time / 1000;
    } else {
        $last_review_seconds = $last_review_time;
    }
    $elapsed_seconds = $now - $last_review_seconds;
    $elapsed_days = max( 0.0, $elapsed_seconds / DAY_IN_SECONDS );

    // Get scheduled interval
    $scheduled_interval = 0.0;
    if ( is_array( $card ) && isset( $card['interval'] ) && $card['interval'] > 0 ) {
        $scheduled_interval = (float) $card['interval'];
    } elseif ( $fsrs_state['stability'] > 0 ) {
        $scheduled_interval = -$fsrs_state['stability'] * log( 0.9 );
    }

    // Create review event (simplified)
    $review_event = fsrs_create_review_event(
        $rating,
        $elapsed_days,
        $scheduled_interval,
        $elapsed_days,
        $fsrs_state['consecutive_fails'],
        $fsrs_state['lapse_count']
    );

    // Process with FSRS
    $result = fsrs_plusplus_review( $fsrs_state, $review_event );

    // Get results
    $next_review_time = isset( $result['next_review_time'] ) ? (int) $result['next_review_time'] : $now + DAY_IN_SECONDS;
    $updated_state = isset( $result['updated_state'] ) ? $result['updated_state'] : $fsrs_state;

    return dnd_vocab_fsrs_save_card_state( $updated_state, $deck_id, $next_review_time, DND_VOCAB_PHASE_REVIEW );
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
 * Predict next review timestamps for a card for different ratings.
 *
 * Used to show, on the back side of the card, when it will reappear if
 * the user chooses Again (1), Hard (2), Good (3), or Easy (4).
 *
 * Handles all phases:
 * - NEW/LEARNING: Returns preset learning intervals
 * - TRANSITION: Returns preset transition intervals
 * - REVIEW: Returns FSRS-calculated intervals
 *
 * @param int $user_id  User ID.
 * @param int $vocab_id Vocabulary item ID.
 * @param int $deck_id  Deck ID.
 *
 * @return array Array mapping rating => timestamp.
 */
function dnd_vocab_srs_predict_next_reviews( $user_id, $vocab_id, $deck_id ) {
	$user_id  = (int) $user_id;
	$vocab_id = (int) $vocab_id;
	$deck_id  = (int) $deck_id;

	if ( $user_id <= 0 || $vocab_id <= 0 || $deck_id <= 0 ) {
		return array();
	}

	$data = dnd_vocab_get_user_srs_data( $user_id );

	$card = isset( $data[ $vocab_id ] ) && is_array( $data[ $vocab_id ] ) ? $data[ $vocab_id ] : null;

	$now = current_time( 'timestamp' );

	// Determine current phase
	$current_phase = dnd_vocab_get_card_phase( $card );

	// Return intervals based on phase
	if ( $current_phase === DND_VOCAB_PHASE_NEW || $current_phase === DND_VOCAB_PHASE_LEARNING ) {
		// LEARNING PHASE: Return preset intervals
		return dnd_vocab_predict_learning_intervals( $now );
	} elseif ( $current_phase === DND_VOCAB_PHASE_TRANSITION ) {
		// TRANSITION PHASE: Return transition presets
		return dnd_vocab_predict_transition_intervals( $now );
	} else {
		// REVIEW PHASE: Use FSRS
		return dnd_vocab_predict_review_intervals( $card, $now );
	}
}

/**
 * Predict intervals for LEARNING phase.
 *
 * @param int $now Current timestamp
 * @return array Mapping of rating => timestamp
 */
function dnd_vocab_predict_learning_intervals( $now ) {
	$intervals = dnd_vocab_get_learning_intervals();
	return array(
		1 => $now + $intervals[1],  // Again: 1 minute
		2 => $now + $intervals[2],  // Hard: 5 minutes
		3 => $now + $intervals[3],  // Good: 10 minutes
		4 => $now + $intervals[4],  // Easy: 2 days
	);
}

/**
 * Predict intervals for TRANSITION phase.
 *
 * @param int $now Current timestamp
 * @return array Mapping of rating => timestamp
 */
function dnd_vocab_predict_transition_intervals( $now ) {
	$intervals = dnd_vocab_get_transition_intervals();
	return array(
		1 => $now + $intervals[1],  // Again: 5 minutes
		2 => $now + $intervals[2],  // Hard: 30 minutes
		3 => $now + $intervals[3],  // Good: 1 day
		4 => $now + $intervals[4],  // Easy: 2 days
	);
}

/**
 * Predict intervals for REVIEW phase using FSRS.
 *
 * For review phase:
 * - Calculate base interval X from FSRS (Good rating)
 * - Again: relearn (5-10 minutes)
 * - Hard: X × 0.5 (min 1 day)
 * - Good: X
 * - Easy: X × 2
 *
 * @param array|null $card Existing card
 * @param int        $now  Current timestamp
 * @return array Mapping of rating => timestamp
 */
function dnd_vocab_predict_review_intervals( $card, $now ) {
	$result = array();

	// Get FSRS state
	$fsrs_state = dnd_vocab_fsrs_get_card_state( $card );

	// Calculate base interval (for Good rating) using FSRS
	$base_interval_days = dnd_vocab_calculate_fsrs_interval( $fsrs_state, 3, $now, $card );

	// Ensure minimum interval of 1 day for review phase
	$base_interval_days = max( 1.0, $base_interval_days );

	// Again: Relearn interval (5 minutes)
	$relearn_seconds = 5 * MINUTE_IN_SECONDS;
	$result[1] = $now + $relearn_seconds;

	// Hard: X × 0.5 (min 1 day)
	$hard_days = max( 1.0, $base_interval_days * 0.5 );
	$result[2] = $now + (int) ( $hard_days * DAY_IN_SECONDS );

	// Good: X (base interval)
	$result[3] = $now + (int) ( $base_interval_days * DAY_IN_SECONDS );

	// Easy: X × 2
	$easy_days = $base_interval_days * 2.0;
	$result[4] = $now + (int) ( $easy_days * DAY_IN_SECONDS );

	return $result;
}

/**
 * Calculate FSRS interval for a specific rating.
 *
 * @param array      $fsrs_state Current FSRS state
 * @param int        $rating     Rating (1-4)
 * @param int        $now        Current timestamp
 * @param array|null $card       Full card data (for scheduled interval)
 * @return float Interval in days
 */
function dnd_vocab_calculate_fsrs_interval( $fsrs_state, $rating, $now, $card = null ) {
	// If FSRS functions not available, use simple calculation
	if ( ! function_exists( 'fsrs_create_review_event' ) || ! function_exists( 'fsrs_plusplus_review' ) ) {
		$stability = $fsrs_state['stability'];
		$difficulty = $fsrs_state['difficulty'];

		// Apply simple stability multiplier
		if ( $rating === 1 ) {
			$new_stability = $stability * 0.5;
		} elseif ( $rating === 2 ) {
			$new_stability = $stability * 1.0;
		} elseif ( $rating === 3 ) {
			$new_stability = $stability * 1.8;
		} else {
			$new_stability = $stability * 2.5;
		}

		// Apply difficulty scaling
		$new_stability *= ( 11.0 - $difficulty ) / 10.0;

		// Calculate interval
		return max( 1.0, -$new_stability * log( 0.9 ) );
	}

	// Calculate elapsed days
	$last_review_time = $fsrs_state['last_review_time'];
	if ( $last_review_time > 1e12 ) {
		$last_review_seconds = $last_review_time / 1000;
	} else {
		$last_review_seconds = $last_review_time;
	}
	$elapsed_seconds = $now - $last_review_seconds;
	$elapsed_days = max( 0.0, $elapsed_seconds / DAY_IN_SECONDS );

	// Get scheduled interval
	$scheduled_interval = 0.0;
	if ( is_array( $card ) && isset( $card['interval'] ) && $card['interval'] > 0 ) {
		$scheduled_interval = (float) $card['interval'];
	} elseif ( $fsrs_state['stability'] > 0 ) {
		$scheduled_interval = -$fsrs_state['stability'] * log( 0.9 );
	}

	// Create review event
	$review_event = fsrs_create_review_event(
		$rating,
		$elapsed_days,
		$scheduled_interval,
		$elapsed_days,
		$fsrs_state['consecutive_fails'],
		$fsrs_state['lapse_count']
	);

	// Process with FSRS
	$fsrs_result = fsrs_plusplus_review( $fsrs_state, $review_event );

	// Return interval in days
	if ( isset( $fsrs_result['next_interval_days'] ) ) {
		return (float) $fsrs_result['next_interval_days'];
	}

	// Fallback
	return 1.0;
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

/**
 * ------------------------------
 * Review History & Heatmap helpers
 * ------------------------------
 */

/**
 * Log a review to user's review history.
 *
 * @param int    $user_id    User ID.
 * @param int    $vocab_id   Vocabulary item ID.
 * @param int    $deck_id    Deck ID.
 * @param int    $rating     Quality rating (0, 3, 5).
 * @param string $review_type Review type: 'new', 'review', 'learning'.
 */
function dnd_vocab_log_review( $user_id, $vocab_id, $deck_id, $rating, $review_type ) {
    $user_id  = (int) $user_id;
    $vocab_id = (int) $vocab_id;
    $deck_id  = (int) $deck_id;

    if ( $user_id <= 0 || $vocab_id <= 0 || $deck_id <= 0 ) {
        return;
    }

    $now      = current_time( 'timestamp' );
    $date_key = wp_date( 'Y-m-d', $now );

    $history = get_user_meta( $user_id, 'dnd_vocab_review_history', true );

    if ( ! is_array( $history ) ) {
        $history = array();
    }

    if ( ! isset( $history[ $date_key ] ) ) {
        $history[ $date_key ] = array();
    }

    if ( ! isset( $history[ $date_key ][ $vocab_id ] ) ) {
        $history[ $date_key ][ $vocab_id ] = array();
    }

    // Add review entry with timestamp.
    $review_entry = array(
        'deck_id'     => $deck_id,
        'rating'      => (int) $rating,
        'review_type' => sanitize_text_field( $review_type ),
        'timestamp'   => $now,
    );

    // If same card reviewed multiple times in same day, append to array.
    if ( ! isset( $history[ $date_key ][ $vocab_id ]['reviews'] ) ) {
        $history[ $date_key ][ $vocab_id ]['reviews'] = array();
    }

    $history[ $date_key ][ $vocab_id ]['reviews'][] = $review_entry;
    $history[ $date_key ][ $vocab_id ]['deck_id']   = $deck_id;
    $history[ $date_key ][ $vocab_id ]['last_review_type'] = $review_type;

    // Track session start time for time calculation.
    if ( ! isset( $history[ $date_key ]['_session_start'] ) ) {
        $history[ $date_key ]['_session_start'] = $now;
    }
    $history[ $date_key ]['_last_review'] = $now;

    update_user_meta( $user_id, 'dnd_vocab_review_history', $history );
}

/**
 * Get review history for a user within a date range.
 *
 * @param int    $user_id   User ID.
 * @param string $start_date Start date in Y-m-d format.
 * @param string $end_date   End date in Y-m-d format.
 * @return array Review history data.
 */
function dnd_vocab_get_review_history( $user_id, $start_date = '', $end_date = '' ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array();
    }

    $history = get_user_meta( $user_id, 'dnd_vocab_review_history', true );

    if ( ! is_array( $history ) ) {
        return array();
    }

    // If no date range specified, return all.
    if ( empty( $start_date ) && empty( $end_date ) ) {
        return $history;
    }

    $filtered = array();

    foreach ( $history as $date => $reviews ) {
        if ( ! empty( $start_date ) && $date < $start_date ) {
            continue;
        }
        if ( ! empty( $end_date ) && $date > $end_date ) {
            continue;
        }
        $filtered[ $date ] = $reviews;
    }

    return $filtered;
}

/**
 * Get heatmap data for a user (365 days past + 30 days future).
 *
 * @param int $user_id User ID.
 * @return array Heatmap data with date keys and review counts.
 */
function dnd_vocab_get_heatmap_data( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array();
    }

    $now        = current_time( 'timestamp' );
    $today      = wp_date( 'Y-m-d', $now );
    $start_date = wp_date( 'Y-m-d', $now - ( 365 * DAY_IN_SECONDS ) );
    $end_date   = wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) );

    // Get review history for past dates.
    $history = dnd_vocab_get_review_history( $user_id, $start_date, $today );

    // Get SRS data for future due dates.
    $srs_data = dnd_vocab_get_user_srs_data( $user_id );
    $user_decks = get_user_meta( $user_id, 'dnd_vocab_user_decks', true );

    if ( ! is_array( $user_decks ) ) {
        $user_decks = array();
    }

    $user_decks = array_map( 'absint', $user_decks );

    // Initialize heatmap data for all days.
    $heatmap_data = array();

    // Process past dates from history.
    foreach ( $history as $date => $reviews ) {
        if ( $date < $start_date || $date > $end_date ) {
            continue;
        }

        // Count unique cards reviewed on this date.
        $count = count( $reviews );
        $heatmap_data[ $date ] = array(
            'reviewed' => $count,
            'due'      => 0,
            'total'    => $count,
        );
    }

    // Process future dates from SRS due dates.
    foreach ( $srs_data as $vocab_id => $card ) {
        if ( ! is_array( $card ) ) {
            continue;
        }

        $card_deck_id = isset( $card['deck_id'] ) ? (int) $card['deck_id'] : 0;

        if ( ! empty( $user_decks ) && ! in_array( $card_deck_id, $user_decks, true ) ) {
            continue;
        }

        $due_timestamp = isset( $card['due'] ) ? (int) $card['due'] : 0;

        if ( $due_timestamp > 0 ) {
            $due_date = wp_date( 'Y-m-d', $due_timestamp );

            if ( $due_date >= $today && $due_date <= $end_date ) {
                if ( ! isset( $heatmap_data[ $due_date ] ) ) {
                    $heatmap_data[ $due_date ] = array(
                        'reviewed' => 0,
                        'due'      => 0,
                        'total'    => 0,
                    );
                }

                $heatmap_data[ $due_date ]['due']++;
                $heatmap_data[ $due_date ]['total']++;
            }
        }
    }

    ksort( $heatmap_data );

    return $heatmap_data;
}

/**
 * Get human-readable relative time until next review from a timestamp.
 *
 * Output examples (in Vietnamese):
 * - "sau 10 giây"
 * - "sau 5 phút"
 * - "sau 3 giờ"
 * - "sau 2 ngày"
 *
 * @param int $timestamp Next review timestamp.
 * @return string
 */
function dnd_vocab_human_readable_next_review( $timestamp ) {
	$timestamp = (int) $timestamp;

	if ( $timestamp <= 0 ) {
		return '';
	}

	$now  = current_time( 'timestamp' );
	$diff = $timestamp - $now;

	// If due time is in the past, treat it as very soon (1 minute).
	if ( $diff <= 0 ) {
		$diff = MINUTE_IN_SECONDS;
	}

	$seconds = (int) $diff;

	if ( $seconds < MINUTE_IN_SECONDS ) {
		$value = max( 1, $seconds );

		return sprintf(
			_n( '%d giây', '%d giây', $value, 'dnd-vocab' ),
			$value
		);
	}

	$minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );

	if ( $minutes < 60 ) {
		$value = max( 1, $minutes );

		return sprintf(
			_n( '%d phút', '%d phút', $value, 'dnd-vocab' ),
			$value
		);
	}

	$hours = (int) floor( $seconds / HOUR_IN_SECONDS );

	if ( $hours < 24 ) {
		$value = max( 1, $hours );

		return sprintf(
			_n( '%d giờ', '%d giờ', $value, 'dnd-vocab' ),
			$value
		);
	}

	$days = (int) floor( $seconds / DAY_IN_SECONDS );

	if ( $days < 1 ) {
		$days = 1;
	}

	// Dưới 7 ngày: vẫn hiển thị theo ngày.
	if ( $days < 7 ) {
		return sprintf(
			_n( '%d ngày', '%d ngày', $days, 'dnd-vocab' ),
			$days
		);
	}

	// Từ 7 đến dưới 30 ngày: hiển thị theo tuần.
	if ( $days < 30 ) {
		$weeks = (int) max( 1, round( $days / 7 ) );

		return sprintf(
			_n( '%d tuần', '%d tuần', $weeks, 'dnd-vocab' ),
			$weeks
		);
	}

	// Từ 30 đến dưới 365 ngày: hiển thị theo tháng.
	if ( $days < 365 ) {
		$months = (int) max( 1, round( $days / 30 ) );

		return sprintf(
			_n( '%d tháng', '%d tháng', $months, 'dnd-vocab' ),
			$months
		);
	}

	// Từ 1 năm trở lên: hiển thị theo năm.
	$years = (int) max( 1, round( $days / 365 ) );

	return sprintf(
		_n( '%d năm', '%d năm', $years, 'dnd-vocab' ),
		$years
	);
}

/**
 * Calculate current streak for a user.
 *
 * @param int $user_id User ID.
 * @return int Current streak in days.
 */
function dnd_vocab_calculate_streak( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return 0;
    }

    $now   = current_time( 'timestamp' );
    $today = wp_date( 'Y-m-d', $now );

    $history = dnd_vocab_get_review_history( $user_id );

    if ( empty( $history ) ) {
        return 0;
    }

    $streak = 0;
    $check_date = wp_date( 'Y-m-d', $now - DAY_IN_SECONDS ); // Start from yesterday.

    // Count backwards from yesterday.
    while ( true ) {
        if ( isset( $history[ $check_date ] ) && ! empty( $history[ $check_date ] ) ) {
            $streak++;
        } else {
            // Streak broken.
            break;
        }

        $check_timestamp = strtotime( $check_date );
        $check_timestamp -= DAY_IN_SECONDS;
        $check_date = wp_date( 'Y-m-d', $check_timestamp );

        // Limit to prevent infinite loop (max 1000 days).
        if ( $streak > 1000 ) {
            break;
        }
    }

    return $streak;
}

/**
 * Get cards reviewed or due on a specific date.
 *
 * @param int    $user_id User ID.
 * @param string $date    Date in Y-m-d format.
 * @return array Array with 'reviewed' and 'due' keys containing card data.
 */
function dnd_vocab_get_cards_by_date( $user_id, $date ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 || empty( $date ) ) {
        return array(
            'reviewed' => array(),
            'due'      => array(),
        );
    }

    $now   = current_time( 'timestamp' );
    $today = wp_date( 'Y-m-d', $now );

    $result = array(
        'reviewed' => array(),
        'due'      => array(),
    );

    // Get reviewed cards from history.
    $history = dnd_vocab_get_review_history( $user_id );

    if ( isset( $history[ $date ] ) && is_array( $history[ $date ] ) ) {
        foreach ( $history[ $date ] as $vocab_id => $review_data ) {
            $vocab_id = (int) $vocab_id;

            if ( $vocab_id <= 0 ) {
                continue;
            }

            $vocab_post = get_post( $vocab_id );

            if ( ! $vocab_post || 'dnd_vocab_item' !== $vocab_post->post_type ) {
                continue;
            }

            $word = get_post_meta( $vocab_id, 'dnd_vocab_word', true );
            if ( empty( $word ) ) {
                $word = get_the_title( $vocab_post );
            }

            $deck_id = isset( $review_data['deck_id'] ) ? (int) $review_data['deck_id'] : 0;

            $result['reviewed'][] = array(
                'vocab_id'     => $vocab_id,
                'word'         => $word,
                'deck_id'      => $deck_id,
                'review_type'  => isset( $review_data['last_review_type'] ) ? $review_data['last_review_type'] : 'review',
                'review_count' => isset( $review_data['reviews'] ) ? count( $review_data['reviews'] ) : 1,
            );
        }
    }

    // Get due cards from SRS data (only for future dates).
    if ( $date >= $today ) {
        $srs_data   = dnd_vocab_get_user_srs_data( $user_id );
        $user_decks = get_user_meta( $user_id, 'dnd_vocab_user_decks', true );

        if ( ! is_array( $user_decks ) ) {
            $user_decks = array();
        }

        $user_decks = array_map( 'absint', $user_decks );
        $date_timestamp = strtotime( $date . ' 23:59:59' );

        foreach ( $srs_data as $vocab_id => $card ) {
            if ( ! is_array( $card ) ) {
                continue;
            }

            $vocab_id = (int) $vocab_id;

            if ( $vocab_id <= 0 ) {
                continue;
            }

            $card_deck_id = isset( $card['deck_id'] ) ? (int) $card['deck_id'] : 0;

            if ( ! empty( $user_decks ) && ! in_array( $card_deck_id, $user_decks, true ) ) {
                continue;
            }

            $due_timestamp = isset( $card['due'] ) ? (int) $card['due'] : 0;

            if ( $due_timestamp > 0 ) {
                $due_date = wp_date( 'Y-m-d', $due_timestamp );

                if ( $due_date === $date ) {
                    $vocab_post = get_post( $vocab_id );

                    if ( ! $vocab_post || 'dnd_vocab_item' !== $vocab_post->post_type ) {
                        continue;
                    }

                    $word = get_post_meta( $vocab_id, 'dnd_vocab_word', true );
                    if ( empty( $word ) ) {
                        $word = get_the_title( $vocab_post );
                    }

                    $result['due'][] = array(
                        'vocab_id' => $vocab_id,
                        'word'     => $word,
                        'deck_id'  => $card_deck_id,
                    );
                }
            }
        }
    }

    return $result;
}

/**
 * Get today's study statistics (cards reviewed and time spent).
 *
 * @param int $user_id User ID.
 * @return array Array with 'cards' (count) and 'seconds' (time spent).
 */
function dnd_vocab_get_today_stats( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array(
            'cards'   => 0,
            'seconds' => 0,
        );
    }

    $now      = current_time( 'timestamp' );
    $today    = wp_date( 'Y-m-d', $now );
    $history  = dnd_vocab_get_review_history( $user_id );

    $cards_count = 0;
    $time_spent  = 0;

    if ( isset( $history[ $today ] ) && is_array( $history[ $today ] ) ) {
        // Count unique cards reviewed today.
        foreach ( $history[ $today ] as $vocab_id => $review_data ) {
            if ( '_session_start' === $vocab_id || '_last_review' === $vocab_id ) {
                continue;
            }
            $cards_count++;
        }

        // Calculate time spent: difference between first and last review.
        if ( isset( $history[ $today ]['_session_start'] ) && isset( $history[ $today ]['_last_review'] ) ) {
            $session_start = (int) $history[ $today ]['_session_start'];
            $last_review   = (int) $history[ $today ]['_last_review'];
            $time_spent   = max( 1, $last_review - $session_start ); // Minimum 1 second.
        } else {
            // Fallback: estimate time based on review count (average 2 seconds per card).
            $time_spent = max( 1, $cards_count * 2 );
        }
    }

    return array(
        'cards'   => $cards_count,
        'seconds' => $time_spent,
    );
}

/**
 * Get heatmap data for a specific year.
 *
 * @param int $user_id User ID.
 * @param int $year    Year (e.g., 2024).
 * @return array Heatmap data with date keys and review counts.
 */
function dnd_vocab_get_heatmap_data_for_year( $user_id, $year ) {
    $user_id = (int) $user_id;
    $year    = (int) $year;

    if ( $user_id <= 0 || $year < 2000 || $year > 2100 ) {
        return array();
    }

    $start_date = $year . '-01-01';
    $end_date   = $year . '-12-31';

    // Get review history for the year.
    $history = dnd_vocab_get_review_history( $user_id, $start_date, $end_date );

    // Initialize heatmap data.
    $heatmap_data = array();

    // Process dates from history.
    foreach ( $history as $date => $reviews ) {
        if ( $date < $start_date || $date > $end_date ) {
            continue;
        }

        // Count unique cards reviewed on this date.
        $count = 0;
        foreach ( $reviews as $vocab_id => $review_data ) {
            if ( '_session_start' === $vocab_id || '_last_review' === $vocab_id ) {
                continue;
            }
            $count++;
        }

        if ( $count > 0 ) {
            $heatmap_data[ $date ] = array(
                'reviewed' => $count,
                'due'      => 0,
                'total'    => $count,
            );
        }
    }

    ksort( $heatmap_data );

    return $heatmap_data;
}

/**
 * Get year-specific statistics.
 *
 * @param int $user_id User ID.
 * @param int $year    Year (e.g., 2024).
 * @return array Array with statistics: daily_average, days_learned_percent, longest_streak, current_streak.
 */
function dnd_vocab_get_year_stats( $user_id, $year ) {
    $user_id = (int) $user_id;
    $year    = (int) $year;

    if ( $user_id <= 0 || $year < 2000 || $year > 2100 ) {
        return array(
            'daily_average'        => 0,
            'days_learned_percent' => 0,
            'longest_streak'        => 0,
            'current_streak'        => 0,
        );
    }

    $heatmap_data = dnd_vocab_get_heatmap_data_for_year( $user_id, $year );
    $now          = current_time( 'timestamp' );
    $today        = wp_date( 'Y-m-d', $now );
    $current_year = (int) wp_date( 'Y', $now );

    // Calculate daily average.
    $total_reviews = 0;
    $days_with_data = 0;
    foreach ( $heatmap_data as $date => $data ) {
        $total_reviews += (int) $data['total'];
        $days_with_data++;
    }
    $daily_average = $days_with_data > 0 ? round( $total_reviews / $days_with_data ) : 0;

    // Calculate days learned percentage.
    $days_in_year = ( $year === $current_year ) ? (int) wp_date( 'z', $now ) + 1 : ( date( 'L', mktime( 0, 0, 0, 1, 1, $year ) ) ? 366 : 365 );
    $days_learned_percent = $days_in_year > 0 ? round( ( $days_with_data / $days_in_year ) * 100 ) : 0;

    // Get longest streak for the year.
    $longest_streak = dnd_vocab_get_longest_streak( $user_id, $year );

    // Calculate current streak (only if viewing current year).
    $current_streak = 0;
    if ( $year === $current_year ) {
        $current_streak = dnd_vocab_calculate_streak( $user_id );
    } else {
        // For past years, calculate streak ending on Dec 31.
        $history = dnd_vocab_get_review_history( $user_id, $year . '-01-01', $year . '-12-31' );
        $check_date = $year . '-12-31';
        $streak = 0;

        while ( true ) {
            if ( isset( $history[ $check_date ] ) && ! empty( $history[ $check_date ] ) ) {
                $has_data = false;
                foreach ( $history[ $check_date ] as $vocab_id => $review_data ) {
                    if ( '_session_start' !== $vocab_id && '_last_review' !== $vocab_id ) {
                        $has_data = true;
                        break;
                    }
                }
                if ( $has_data ) {
                    $streak++;
                } else {
                    break;
                }
            } else {
                break;
            }

            $check_timestamp = strtotime( $check_date );
            $check_timestamp -= DAY_IN_SECONDS;
            $check_date = wp_date( 'Y-m-d', $check_timestamp );

            // Stop if we've gone past the start of the year.
            if ( substr( $check_date, 0, 4 ) !== (string) $year ) {
                break;
            }

            // Limit to prevent infinite loop.
            if ( $streak > 366 ) {
                break;
            }
        }
        $current_streak = $streak;
    }

    return array(
        'daily_average'        => $daily_average,
        'days_learned_percent' => $days_learned_percent,
        'longest_streak'        => $longest_streak,
        'current_streak'        => $current_streak,
    );
}

/**
 * Get longest streak for a specific year.
 *
 * @param int $user_id User ID.
 * @param int $year    Year (e.g., 2024).
 * @return int Longest streak in days.
 */
function dnd_vocab_get_longest_streak( $user_id, $year ) {
    $user_id = (int) $user_id;
    $year    = (int) $year;

    if ( $user_id <= 0 || $year < 2000 || $year > 2100 ) {
        return 0;
    }

    $history = dnd_vocab_get_review_history( $user_id, $year . '-01-01', $year . '-12-31' );

    if ( empty( $history ) ) {
        return 0;
    }

    $longest_streak = 0;
    $current_streak = 0;
    $start_date = $year . '-01-01';
    $end_date   = $year . '-12-31';

    // Create array of all dates in the year with activity.
    $active_dates = array();
    foreach ( $history as $date => $reviews ) {
        if ( $date < $start_date || $date > $end_date ) {
            continue;
        }

        // Check if there's actual review data (not just metadata).
        foreach ( $reviews as $vocab_id => $review_data ) {
            if ( '_session_start' !== $vocab_id && '_last_review' !== $vocab_id ) {
                $active_dates[] = $date;
                break;
            }
        }
    }

    // Sort dates.
    sort( $active_dates );

    // Find longest consecutive streak.
    if ( ! empty( $active_dates ) ) {
        $current_streak = 1;
        $longest_streak = 1;

        for ( $i = 1; $i < count( $active_dates ); $i++ ) {
            $prev_date = strtotime( $active_dates[ $i - 1 ] );
            $curr_date = strtotime( $active_dates[ $i ] );
            $days_diff = ( $curr_date - $prev_date ) / DAY_IN_SECONDS;

            if ( $days_diff === 1 ) {
                // Consecutive day.
                $current_streak++;
                $longest_streak = max( $longest_streak, $current_streak );
            } else {
                // Streak broken.
                $current_streak = 1;
            }
        }
    }

    return $longest_streak;
}
