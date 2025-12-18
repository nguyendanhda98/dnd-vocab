<?php
/**
 * FSRS++ Algorithm Implementation
 * 
 * An extension of the FSRS algorithm for spaced repetition.
 * Predicts when a user will forget an item based on observed recall behavior.
 * 
 * Core concepts:
 * - Uses memory stability (S) and difficulty (D) instead of ease factor
 * - Probabilistic scheduling based on forgetting curve
 * - Per-user, per-card memory state
 * - All modifiers are multiplicative
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
 * Create initial CardState with safe defaults.
 * 
 * CardState represents the memory state for a specific (user × card) pair.
 * 
 * @return array CardState with default values
 */
function fsrs_create_initial_card_state() {
    return array(
        'stability'         => 1.0,   // S: how long the memory lasts (days)
        'difficulty'        => 5.0,   // D: scale 1 (easy) → 10 (hard)
        'last_review_time'  => 0,     // timestamp (ms or seconds)
        'lapse_count'       => 0,     // total number of lapses
        'consecutive_fails' => 0,     // current streak of failures
    );
}

/**
 * Create a ReviewEvent from observed behaviors.
 * 
 * All values must be observed passively, never asked from the user.
 * 
 * @param int   $rating                   1=forgot, 2=hard, 3=remember
 * @param float $elapsed_days             Days since last review
 * @param float $scheduled_interval       Originally scheduled interval (days)
 * @param float $actual_interval          Actual interval that passed (days)
 * @param int   $response_time_ms         Time to respond in milliseconds
 * @param int   $consecutive_fails        Current consecutive failure count
 * @param int   $lapse_count              Total lapse count
 * @param int   $review_hour              Hour of review (0-23)
 * @param float $review_consistency_score Consistency score (0-1)
 * @param bool  $skip_or_abort            Whether user skipped/aborted
 * @param float $improvement_trend        Trend indicator (-1 to +1)
 * @return array ReviewEvent
 */
function fsrs_create_review_event(
    $rating,
    $elapsed_days,
    $scheduled_interval,
    $actual_interval,
    $response_time_ms,
    $consecutive_fails,
    $lapse_count,
    $review_hour,
    $review_consistency_score,
    $skip_or_abort,
    $improvement_trend
) {
    return array(
        'rating'                   => $rating,
        'elapsed_days'             => $elapsed_days,
        'scheduled_interval'       => $scheduled_interval,
        'actual_interval'          => $actual_interval,
        'response_time_ms'         => $response_time_ms,
        'consecutive_fails'        => $consecutive_fails,
        'lapse_count'              => $lapse_count,
        'review_hour'              => $review_hour,
        'review_consistency_score' => $review_consistency_score,
        'skip_or_abort'            => $skip_or_abort,
        'improvement_trend'        => $improvement_trend,
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
 * Linear interpolation between two values.
 * 
 * @param float $a Start value
 * @param float $b End value
 * @param float $t Interpolation factor (0-1)
 * @return float Interpolated value
 */
function fsrs_lerp( $a, $b, $t ) {
    return $a + ( $b - $a ) * $t;
}

/**
 * Compute retrievability using exponential forgetting curve.
 * 
 * Formula: R = exp(-elapsed_days / stability)
 * 
 * Ref: Section 4.3 - Forgetting Curve
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
 * Ref: Section 5.1 - Difficulty Update
 * 
 * Rules:
 * - rating === 1 (forgot):    difficulty += 0.6
 * - rating === 2 (hard):      difficulty += 0.2
 * - rating === 3 (remember):  difficulty -= 0.3
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
        $difficulty += 0.6;
    } elseif ( $rating === 2 ) {
        $difficulty += 0.2;
    } else {
        // rating === 3
        $difficulty -= 0.3;
    }

    return fsrs_clamp( $difficulty, 1.0, 10.0 );
}

/* ==========================================================================
   SECTION 5.2: BASE STABILITY UPDATE (FSRS)
   ========================================================================== */

/**
 * Update stability using base FSRS logic.
 * 
 * Ref: Section 5.2 - Stability Update (BASE FSRS)
 * 
 * Rules:
 * - rating === 1: multiplier = 0.5
 * - rating === 2: multiplier = 1.2
 * - rating === 3: multiplier = 1.8
 * - Apply difficulty scaling: (11 - difficulty) / 10
 * 
 * @param array $state         CardState
 * @param array $event         ReviewEvent
 * @param float $new_difficulty Updated difficulty value
 * @return float Updated stability (before FSRS++ modifiers)
 */
function fsrs_update_stability_base( $state, $event, $new_difficulty ) {
    $stability = $state['stability'];
    $rating    = $event['rating'];

    // Determine base multiplier based on rating
    if ( $rating === 1 ) {
        $stability_multiplier = 0.5;
    } elseif ( $rating === 2 ) {
        $stability_multiplier = 1.2;
    } else {
        // rating === 3
        $stability_multiplier = 1.8;
    }

    // Apply rating multiplier
    $stability *= $stability_multiplier;

    // Apply difficulty scaling: easier cards grow stability faster
    $stability *= ( 11.0 - $new_difficulty ) / 10.0;

    return $stability;
}

/* ==========================================================================
   SECTION 6: FSRS++ EXTENSIONS (BEHAVIOR MODIFIERS)
   ========================================================================== */

/**
 * Apply all FSRS++ behavior modifiers to stability.
 * 
 * All modifiers are multiplicative as per spec.
 * 
 * Ref: Section 6 - FSRS++ Extensions
 * 
 * @param float $stability Current stability after base update
 * @param array $event     ReviewEvent with all behavior signals
 * @return float Modified stability
 */
function fsrs_apply_modifiers_to_stability( $stability, $event ) {
    $rating = $event['rating'];

    // 6.1 Response Time Modifier
    // Fast confident recall = stronger memory
    // Slow recall even if correct = weaker memory
    if ( $event['response_time_ms'] < 2000 && $rating === 3 ) {
        $stability *= 1.15;
    }
    if ( $event['response_time_ms'] > 7000 && $rating === 3 ) {
        $stability *= 0.9;
    }

    // 6.2 Early / Late Review Modifier
    // If reviewed late and still remembered = stronger memory
    // If reviewed early and remembered = slightly weaker (overlearning penalty)
    if ( $event['scheduled_interval'] > 0 ) {
        $lateness_ratio = $event['actual_interval'] / $event['scheduled_interval'];
        
        if ( $lateness_ratio > 1.2 && $rating === 3 ) {
            $stability *= 1.1;
        }
        if ( $lateness_ratio < 0.8 && $rating === 3 ) {
            $stability *= 0.95;
        }
    }

    // 6.3 Consecutive Fails Penalty
    // Each consecutive fail compounds the penalty
    $stability *= pow( 0.85, $event['consecutive_fails'] );

    // 6.4 Lapse History Penalty
    // More total lapses = harder card, slower stability growth
    $stability *= 1.0 / ( 1.0 + 0.1 * $event['lapse_count'] );

    // 6.5 Review Consistency Modifier
    // Consistent reviewers get a bonus
    $consistency_multiplier = fsrs_lerp( 0.9, 1.05, $event['review_consistency_score'] );
    $stability *= $consistency_multiplier;

    // 6.6 Skip / Abort Penalty
    // Skipping or aborting indicates weak memory
    if ( $event['skip_or_abort'] ) {
        $stability *= 0.8;
    }

    // 6.7 Time-of-Day Adjustment (Light)
    // Late night / early morning reviews are less reliable
    $review_hour = $event['review_hour'];
    if ( $review_hour >= 22 || $review_hour <= 5 ) {
        $stability *= 0.95;
    }

    // 6.8 Improvement Trend Modifier
    // Positive trend = user is getting better at this card
    // Negative trend = user is struggling
    if ( $event['improvement_trend'] > 0 ) {
        $stability *= 1.1;
    }
    if ( $event['improvement_trend'] < 0 ) {
        $stability *= 0.9;
    }

    return $stability;
}

/* ==========================================================================
   SECTION 7: NEXT INTERVAL CALCULATION
   ========================================================================== */

/**
 * Compute the next review interval in days.
 * 
 * Ref: Section 7 - Next Interval Calculation
 * 
 * Formula: next_interval_days = -stability * ln(target_retention)
 * 
 * We schedule the next review when retrievability drops to target_retention.
 * 
 * @param float $stability        Current stability value
 * @param float $target_retention Target retention probability (default 0.9)
 * @param float $max_interval     Maximum interval in days (default 3650 = ~10 years)
 * @return float Next interval in days, clamped to [1, max_interval]
 */
function fsrs_compute_next_interval_days( $stability, $target_retention = 0.9, $max_interval = 3650.0 ) {
    // Solve for t when R = target_retention:
    // target_retention = exp(-t / stability)
    // ln(target_retention) = -t / stability
    // t = -stability * ln(target_retention)
    $next_interval_days = -$stability * log( $target_retention );

    return fsrs_clamp( $next_interval_days, 1.0, $max_interval );
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
 * This is the main entry point for the FSRS++ algorithm.
 * 
 * Ref: Section 9 - Output
 * 
 * @param array $state            Current CardState for this (user × card)
 * @param array $event            ReviewEvent with all observed behaviors
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
    // Step 1: Update difficulty (Section 5.1)
    $new_difficulty = fsrs_update_difficulty( $state, $event );

    // Step 2: Update stability using base FSRS (Section 5.2)
    $new_stability = fsrs_update_stability_base( $state, $event, $new_difficulty );

    // Step 3: Apply all FSRS++ behavior modifiers (Section 6)
    $new_stability = fsrs_apply_modifiers_to_stability( $new_stability, $event );

    // Step 4: Update lapse tracking based on rating
    $new_lapse_count       = $state['lapse_count'];
    $new_consecutive_fails = $state['consecutive_fails'];

    if ( $event['rating'] === 1 ) {
        // User forgot - increment both counters
        $new_lapse_count++;
        $new_consecutive_fails++;
    } else {
        // User remembered (rating 2 or 3) - reset consecutive fails
        $new_consecutive_fails = 0;
    }

    // Step 5: Compute next interval (Section 7)
    $next_interval_days = fsrs_compute_next_interval_days(
        $new_stability,
        $target_retention,
        $max_interval
    );

    // Step 6: Compute next review timestamp
    $next_review_time = fsrs_compute_next_review_time( $next_interval_days );

    // Build updated state
    $updated_state = array(
        'stability'         => $new_stability,
        'difficulty'        => $new_difficulty,
        'last_review_time'  => time() * 1000, // Store in milliseconds for consistency
        'lapse_count'       => $new_lapse_count,
        'consecutive_fails' => $new_consecutive_fails,
    );

    // Return output as specified in Section 9
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

