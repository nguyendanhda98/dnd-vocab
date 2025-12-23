<?php
/**
 * FSRS Algorithm Implementation (Simplified)
 * 
 * A simplified FSRS algorithm for spaced repetition.
 * Predicts when a user will forget an item based on observed recall behavior.
 * 
 * Core concepts:
 * - Uses memory stability (S) and difficulty (D) instead of ease factor
 * - Probabilistic scheduling based on forgetting curve
 * - Per-user, per-card memory state
 * - NO behavior modifiers (response time, time of day, etc.)
 * 
 * @package DND_Vocab
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   SECTION 2: DATA STRUCTURES
   ========================================================================== */

/**
 * Create initial CardState with FSRS-style defaults.
 * 
 * CardState represents the memory state for a specific (user × card) pair.
 * Initial values: S₀ = 0.20 days, D₀ = 5.0
 * 
 * @return array CardState with default values
 */
function fsrs_create_initial_card_state() {
    return array(
        'stability'         => 0.20,  // S₀: initial stability (days)
        'difficulty'        => 5.0,   // D₀: scale 1 (easy) → 10 (hard)
        'last_review_time'  => 0,     // timestamp (ms or seconds)
        'lapse_count'       => 0,     // total number of lapses
        'consecutive_fails' => 0,     // current streak of failures
    );
}

/**
 * Create a ReviewEvent from observed behaviors.
 * 
 * Simplified version - only uses essential parameters for FSRS core.
 * 
 * @param int   $rating             1=Again, 2=Hard, 3=Good, 4=Easy
 * @param float $elapsed_days       Days since last review
 * @param float $scheduled_interval Originally scheduled interval (days)
 * @param float $actual_interval    Actual interval that passed (days)
 * @param int   $consecutive_fails  Current consecutive failure count
 * @param int   $lapse_count        Total lapse count
 * @return array ReviewEvent
 */
function fsrs_create_review_event(
    $rating,
    $elapsed_days,
    $scheduled_interval,
    $actual_interval,
    $consecutive_fails,
    $lapse_count
) {
    return array(
        'rating'             => $rating,
        'elapsed_days'       => $elapsed_days,
        'scheduled_interval' => $scheduled_interval,
        'actual_interval'    => $actual_interval,
        'consecutive_fails'  => $consecutive_fails,
        'lapse_count'        => $lapse_count,
    );
}

/* ==========================================================================
   SECTION 3: MATH UTILITIES
   ========================================================================== */

/**
 * Clamp a value between min and max.
 * 
 * @param float $value Value to clamp
 * @param float $min   Minimum bound
 * @param float $max   Maximum bound
 * @return float Clamped value
 */
function fsrs_clamp( $value, $min, $max ) {
    return max( $min, min( $max, $value ) );
}

/**
 * Compute retrievability using exponential forgetting curve.
 * 
 * Formula: R = exp(-elapsed_days / stability)
 * 
 * @param float $elapsed_days Days since last review
 * @param float $stability    Current stability value
 * @return float Retrievability (0-1)
 */
function fsrs_compute_retrievability( $elapsed_days, $stability ) {
    if ( $stability <= 0 ) {
        return 0.0;
    }
    return exp( -$elapsed_days / $stability );
}

/* ==========================================================================
   SECTION 5.1: DIFFICULTY UPDATE
   ========================================================================== */

/**
 * Update difficulty based on rating.
 * 
 * Rules:
 * - rating === 1 (Again): difficulty += 0.6
 * - rating === 2 (Hard):  difficulty += 0.2
 * - rating === 3 (Good):  difficulty -= 0.3
 * - rating === 4 (Easy):  difficulty -= 0.5
 * - Clamp to [1, 10]
 * 
 * @param array $state CardState
 * @param array $event ReviewEvent
 * @return float Updated difficulty
 */
function fsrs_update_difficulty( $state, $event ) {
    $difficulty = $state['difficulty'];
    $rating     = $event['rating'];

    if ( $rating === 1 ) {
        // Again - forgot completely
        $difficulty += 0.6;
    } elseif ( $rating === 2 ) {
        // Hard - remembered but difficult
        $difficulty += 0.2;
    } elseif ( $rating === 3 ) {
        // Good - remembered well
        $difficulty -= 0.3;
    } else {
        // rating === 4 (Easy) - very easy
        $difficulty -= 0.5;
    }

    return fsrs_clamp( $difficulty, 1.0, 10.0 );
}

/* ==========================================================================
   SECTION 5.2: BASE STABILITY UPDATE (FSRS)
   ========================================================================== */

/**
 * Update stability using FSRS-style logic.
 * 
 * Rating factors (r_factor):
 * - rating === 1 (Again): 0.30 - forgot, reduce stability significantly
 * - rating === 2 (Hard):  1.20 - remembered but weak
 * - rating === 3 (Good):  2.00 - remembered as expected
 * - rating === 4 (Easy):  3.50 - very easy, increase stability a lot
 * 
 * Formula: S_new = S_old × r_factor
 * 
 * @param array $state          CardState
 * @param array $event          ReviewEvent
 * @param float $new_difficulty Updated difficulty value (unused in simplified formula)
 * @return float Updated stability
 */
function fsrs_update_stability_base( $state, $event, $new_difficulty ) {
    $stability = $state['stability'];
    $rating    = $event['rating'];

    // Determine rating factor (r_factor) based on rating
    // Custom FSRS-style algorithm with manually designed r_factors
    if ( $rating === 1 ) {
        // Again - forgot, reduce stability significantly
        $r_factor = 0.30;
    } elseif ( $rating === 2 ) {
        // Hard - remembered but weak
        $r_factor = 1.20;
    } elseif ( $rating === 3 ) {
        // Good - remembered as expected
        $r_factor = 2.00;
    } else {
        // rating === 4 (Easy) - very easy, increase stability a lot
        $r_factor = 3.50;
    }

    // Apply rating factor: S_new = S_old × r_factor
    $stability *= $r_factor;

    return $stability;
}

/* ==========================================================================
   SECTION 7: NEXT INTERVAL CALCULATION
   ========================================================================== */

/**
 * Compute the next review interval in days.
 * 
 * Formula: next_interval_days = -stability * ln(target_retention)
 * 
 * We schedule the next review when retrievability drops to target_retention.
 * Note: ln(0.9) ≈ -0.10536, so I_next ≈ stability × 0.10536
 * 
 * @param float $stability        Current stability value
 * @param float $target_retention Target retention probability (default 0.9)
 * @param float $max_interval     Maximum interval in days (default 3650 = ~10 years)
 * @param float $min_interval     Minimum interval in days (default 1/1440 = ~1 minute)
 * @return float Next interval in days, clamped to [min_interval, max_interval]
 */
function fsrs_compute_next_interval_days( $stability, $target_retention = 0.9, $max_interval = 3650.0, $min_interval = 0.000694 ) {
    // Solve for t when R = target_retention:
    // target_retention = exp(-t / stability)
    // ln(target_retention) = -t / stability
    // t = -stability * ln(target_retention)
    $next_interval_days = -$stability * log( $target_retention );

    // Allow sub-day intervals for early reviews (minimum ~1 minute = 1/1440 days)
    return fsrs_clamp( $next_interval_days, $min_interval, $max_interval );
}

/**
 * Convert interval days to Unix timestamp for next review.
 * 
 * @param float $interval_days Interval in days
 * @param int   $from_time     Starting timestamp (defaults to current time)
 * @return int Unix timestamp for next review
 */
function fsrs_compute_next_review_time( $interval_days, $from_time = null ) {
    if ( $from_time === null ) {
        $from_time = time();
    }
    
    // Convert days to seconds and add to from_time
    $seconds = $interval_days * 24 * 60 * 60;
    
    return (int) ( $from_time + $seconds );
}

/* ==========================================================================
   SECTION 9: MAIN ENTRY FUNCTION
   ========================================================================== */

/**
 * Process a review event and return updated state.
 * 
 * This is the main entry point for the FSRS algorithm.
 * Used ONLY in Review Phase - NOT for learning/transition phases.
 * 
 * @param array $state            Current CardState for this (user × card)
 * @param array $event            ReviewEvent with observed behaviors
 * @param float $target_retention Target retention probability (default 0.9)
 * @param float $max_interval     Maximum interval in days (default 3650)
 * @return array {
 *     @type float $updated_stability   New stability value
 *     @type float $updated_difficulty  New difficulty value
 *     @type int   $next_review_time    Unix timestamp for next review
 *     @type float $next_interval_days  Interval in days (for reference)
 *     @type array $updated_state       Full updated CardState
 * }
 */
function fsrs_plusplus_review( $state, $event, $target_retention = 0.9, $max_interval = 3650.0 ) {
    // Step 1: Update difficulty
    $new_difficulty = fsrs_update_difficulty( $state, $event );

    // Step 2: Update stability using base FSRS
    $new_stability = fsrs_update_stability_base( $state, $event, $new_difficulty );

    // Step 3: Update lapse tracking based on rating
    $new_lapse_count       = $state['lapse_count'];
    $new_consecutive_fails = $state['consecutive_fails'];

    if ( $event['rating'] === 1 ) {
        // Again - user forgot, increment both counters
        $new_lapse_count++;
        $new_consecutive_fails++;
    } else {
        // Hard (2), Good (3), or Easy (4) - user remembered, reset consecutive fails
        $new_consecutive_fails = 0;
    }

    // Step 4: Compute next interval
    $next_interval_days = fsrs_compute_next_interval_days(
        $new_stability,
        $target_retention,
        $max_interval
    );

    // Step 5: Compute next review timestamp
    $next_review_time = fsrs_compute_next_review_time( $next_interval_days );

    // Build updated state
    $updated_state = array(
        'stability'         => $new_stability,
        'difficulty'        => $new_difficulty,
        'last_review_time'  => time() * 1000, // Store in milliseconds for consistency
        'lapse_count'       => $new_lapse_count,
        'consecutive_fails' => $new_consecutive_fails,
    );

    // Return output
    return array(
        'updated_stability'  => $new_stability,
        'updated_difficulty' => $new_difficulty,
        'next_review_time'   => $next_review_time,
        'next_interval_days' => $next_interval_days,
        'updated_state'      => $updated_state,
    );
}

/* ==========================================================================
   UTILITY FUNCTIONS FOR INTEGRATION
   ========================================================================== */

/**
 * Get current retrievability for a card.
 * 
 * Useful for displaying memory strength to users.
 * 
 * @param array $state        CardState
 * @param int   $current_time Current timestamp (defaults to time())
 * @return float Retrievability (0-1)
 */
function fsrs_get_current_retrievability( $state, $current_time = null ) {
    if ( $current_time === null ) {
        $current_time = time();
    }

    // Convert last_review_time from ms to seconds if needed
    $last_review = $state['last_review_time'];
    if ( $last_review > 1e12 ) {
        // Looks like milliseconds, convert to seconds
        $last_review = $last_review / 1000;
    }

    $elapsed_seconds = $current_time - $last_review;
    $elapsed_days    = $elapsed_seconds / ( 24 * 60 * 60 );

    return fsrs_compute_retrievability( $elapsed_days, $state['stability'] );
}

/**
 * Check if a card is due for review.
 * 
 * @param array $state            CardState
 * @param float $target_retention Target retention threshold (default 0.9)
 * @param int   $current_time     Current timestamp (defaults to time())
 * @return bool True if card should be reviewed
 */
function fsrs_is_due_for_review( $state, $target_retention = 0.9, $current_time = null ) {
    $retrievability = fsrs_get_current_retrievability( $state, $current_time );
    return $retrievability <= $target_retention;
}

