<?php
/**
 * FSRS Test Page
 *
 * A test page for the FSRS algorithm where users can test how the algorithm
 * responds to different rating choices.
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize session if not already started
 */
function dnd_vocab_fsrs_test_init_session() {
	if ( ! session_id() ) {
		session_start();
	}
}

/**
 * Get or initialize FSRS test state from session
 *
 * @return array Current card state
 */
function dnd_vocab_fsrs_test_get_state() {
	dnd_vocab_fsrs_test_init_session();

	if ( ! isset( $_SESSION['dnd_vocab_fsrs_test_state'] ) ) {
		$state = fsrs_create_initial_card_state();
		// Add phase field for phase-based interval calculation
		$state['phase'] = DND_VOCAB_PHASE_NEW;
		$_SESSION['dnd_vocab_fsrs_test_state'] = $state;
	}

	return $_SESSION['dnd_vocab_fsrs_test_state'];
}

/**
 * Save FSRS test state to session
 *
 * @param array $state Card state to save
 */
function dnd_vocab_fsrs_test_save_state( $state ) {
	dnd_vocab_fsrs_test_init_session();
	$_SESSION['dnd_vocab_fsrs_test_state'] = $state;
}

/**
 * Get review history from session
 *
 * @return array Review history
 */
function dnd_vocab_fsrs_test_get_history() {
	dnd_vocab_fsrs_test_init_session();

	if ( ! isset( $_SESSION['dnd_vocab_fsrs_test_history'] ) ) {
		$_SESSION['dnd_vocab_fsrs_test_history'] = array();
	}

	return $_SESSION['dnd_vocab_fsrs_test_history'];
}

/**
 * Add entry to review history
 *
 * @param array $entry Review entry to add
 */
function dnd_vocab_fsrs_test_add_history( $entry ) {
	dnd_vocab_fsrs_test_init_session();

	if ( ! isset( $_SESSION['dnd_vocab_fsrs_test_history'] ) ) {
		$_SESSION['dnd_vocab_fsrs_test_history'] = array();
	}

	$_SESSION['dnd_vocab_fsrs_test_history'][] = $entry;
}

/**
 * Reset FSRS test state and history
 */
function dnd_vocab_fsrs_test_reset() {
	dnd_vocab_fsrs_test_init_session();
	unset( $_SESSION['dnd_vocab_fsrs_test_state'] );
	unset( $_SESSION['dnd_vocab_fsrs_test_history'] );
	unset( $_SESSION['dnd_vocab_fsrs_test_simulated_time'] );
}

/**
 * Get simulated time from session
 *
 * @return int|null Simulated timestamp or null if not set
 */
function dnd_vocab_fsrs_test_get_simulated_time() {
	dnd_vocab_fsrs_test_init_session();
	if ( isset( $_SESSION['dnd_vocab_fsrs_test_simulated_time'] ) ) {
		return intval( $_SESSION['dnd_vocab_fsrs_test_simulated_time'] );
	}
	return null;
}

/**
 * Set simulated time in session
 *
 * @param int $timestamp Unix timestamp
 */
function dnd_vocab_fsrs_test_set_simulated_time( $timestamp ) {
	dnd_vocab_fsrs_test_init_session();
	$_SESSION['dnd_vocab_fsrs_test_simulated_time'] = intval( $timestamp );
}

/**
 * Reset simulated time to null (use real time)
 */
function dnd_vocab_fsrs_test_reset_simulated_time() {
	dnd_vocab_fsrs_test_init_session();
	unset( $_SESSION['dnd_vocab_fsrs_test_simulated_time'] );
}

/**
 * Get current time (simulated if set, otherwise real time)
 *
 * @return int Current timestamp
 */
function dnd_vocab_fsrs_test_get_current_time() {
	$simulated = dnd_vocab_fsrs_test_get_simulated_time();
	return $simulated !== null ? $simulated : current_time( 'timestamp' );
}

/**
 * Get current phase from test state
 *
 * Two-phase system: NEW → REVIEW
 *
 * @param array $state Current card state
 * @return string Phase constant (DND_VOCAB_PHASE_NEW or DND_VOCAB_PHASE_REVIEW)
 */
function dnd_vocab_fsrs_test_get_phase( $state ) {
	// If phase is explicitly set, use it
	if ( isset( $state['phase'] ) && ! empty( $state['phase'] ) ) {
		$phase = $state['phase'];
		
		// NEW phase
		if ( DND_VOCAB_PHASE_NEW === $phase ) {
			return DND_VOCAB_PHASE_NEW;
		}
		
		// REVIEW phase (or legacy LEARNING/TRANSITION → treated as REVIEW)
		if ( in_array( $phase, array( DND_VOCAB_PHASE_REVIEW, DND_VOCAB_PHASE_LEARNING, DND_VOCAB_PHASE_TRANSITION ), true ) ) {
			return DND_VOCAB_PHASE_REVIEW;
		}
	}

	// Determine phase from state
	// If never reviewed, it's NEW
	if ( ! isset( $state['last_review_time'] ) || $state['last_review_time'] == 0 ) {
		return DND_VOCAB_PHASE_NEW;
	}

	// If has any stability, it's in REVIEW
	return DND_VOCAB_PHASE_REVIEW;
}

/**
 * Calculate predicted intervals based on current phase
 *
 * Two-phase system:
 * - NEW: Hard-coded intervals (1m, 5m, 10m, 3d)
 * - REVIEW: FSRS calculation with rating factors
 *
 * @param array $state Current card state
 * @param int   $now   Current timestamp
 * @return array Mapping of rating => array('days' => float, 'timestamp' => int, 'formatted' => string)
 */
function dnd_vocab_fsrs_test_predict_intervals( $state, $now ) {
	$result = array();
	$phase = dnd_vocab_fsrs_test_get_phase( $state );

	// Get predicted timestamps based on phase
	$predicted_timestamps = array();
	
	if ( $phase === DND_VOCAB_PHASE_NEW ) {
		// NEW PHASE: Use preset hard-coded intervals
		if ( function_exists( 'dnd_vocab_predict_learning_intervals' ) ) {
			$predicted_timestamps = dnd_vocab_predict_learning_intervals( $now );
		} else {
			// Fallback: use learning intervals directly
			$intervals = dnd_vocab_get_learning_intervals();
			$predicted_timestamps = array(
				1 => $now + $intervals[1],  // Again: 1 minute
				2 => $now + $intervals[2],  // Hard: 5 minutes
				3 => $now + $intervals[3],  // Good: 10 minutes
				4 => $now + $intervals[4],  // Easy: 3 days
			);
		}
	} else {
		// REVIEW PHASE: Use FSRS calculation for each rating independently
		// Convert state to card format for dnd_vocab_predict_review_intervals
		$card = array(
			'stability' => $state['stability'],
			'difficulty' => $state['difficulty'],
			'last_review_time' => $state['last_review_time'],
			'lapse_count' => isset( $state['lapse_count'] ) ? $state['lapse_count'] : 0,
			'consecutive_fails' => isset( $state['consecutive_fails'] ) ? $state['consecutive_fails'] : 0,
			'phase' => DND_VOCAB_PHASE_REVIEW,
		);
		
		if ( function_exists( 'dnd_vocab_predict_review_intervals' ) ) {
			$predicted_timestamps = dnd_vocab_predict_review_intervals( $card, $now );
		}
	}

	// Format intervals
	if ( function_exists( 'dnd_vocab_human_readable_next_review' ) ) {
		foreach ( $predicted_timestamps as $rating => $future_timestamp ) {
			$interval_seconds = $future_timestamp - $now;
			$interval_days = $interval_seconds / DAY_IN_SECONDS;
			
			$formatted = dnd_vocab_human_readable_next_review( (int) $future_timestamp );
			
			$result[ $rating ] = array(
				'days' => $interval_days,
				'timestamp' => $future_timestamp,
				'formatted' => $formatted ? $formatted : '',
			);
		}
	} else {
		// Fallback: just calculate days
		foreach ( $predicted_timestamps as $rating => $future_timestamp ) {
			$interval_seconds = $future_timestamp - $now;
			$interval_days = $interval_seconds / DAY_IN_SECONDS;
			
			$result[ $rating ] = array(
				'days' => $interval_days,
				'timestamp' => $future_timestamp,
				'formatted' => '',
			);
		}
	}

	return $result;
}

/**
 * Calculate predicted next interval for a given rating
 * (Legacy function - kept for backward compatibility but should use dnd_vocab_fsrs_test_predict_intervals instead)
 *
 * @param array $state Current card state
 * @param int   $rating Rating (1=Again, 2=Hard, 3=Good, 4=Easy)
 * @return float Predicted next interval in days (raw value, not clamped)
 */
function dnd_vocab_fsrs_test_calculate_predicted_interval( $state, $rating ) {
	// Calculate elapsed days
	$current_time = dnd_vocab_fsrs_test_get_current_time();
	$last_review_time = $state['last_review_time'];
	if ( $last_review_time > 1e12 ) {
		$last_review_seconds = $last_review_time / 1000;
	} else {
		$last_review_seconds = $last_review_time;
	}

	$elapsed_seconds = $last_review_time > 0 ? ( $current_time - $last_review_seconds ) : 0;
	$elapsed_days = max( 0.0, $elapsed_seconds / ( 24 * 60 * 60 ) );

	// Get scheduled interval
	$scheduled_interval = 0.0;
	if ( $state['last_review_time'] > 0 && $state['stability'] > 0 ) {
		$scheduled_interval = -$state['stability'] * log( 0.9 );
	}

	// Create review event with the given rating
	$review_event = fsrs_create_review_event(
		$rating,
		$elapsed_days,
		$scheduled_interval,
		$elapsed_days,
		$state['consecutive_fails'],
		$state['lapse_count']
	);

	// Step 1: Update difficulty
	$new_difficulty = fsrs_update_difficulty( $state, $review_event );

	// Step 2: Update stability using base FSRS
	$new_stability = fsrs_update_stability_base( $state, $review_event, $new_difficulty );

	// Step 3: Calculate next interval directly (without clamping for prediction)
	// Use the same formula as fsrs_compute_next_interval_days but without clamp
	$target_retention = 0.9;
	$predicted_interval = -$new_stability * log( $target_retention );

	// Return the predicted next interval (raw value, may be < 1.0)
	return max( 0.01, $predicted_interval ); // Minimum 0.01 to avoid display issues
}

/**
 * Render FSRS test page
 */
function dnd_vocab_fsrs_test_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dnd-vocab' ) );
	}

	// Enqueue assets
	wp_enqueue_style(
		'dnd-vocab-fsrs-test',
		DND_VOCAB_PLUGIN_URL . 'assets/css/fsrs-test.css',
		array(),
		DND_VOCAB_VERSION
	);

	wp_enqueue_script(
		'dnd-vocab-fsrs-test',
		DND_VOCAB_PLUGIN_URL . 'assets/js/fsrs-test.js',
		array( 'jquery' ),
		DND_VOCAB_VERSION,
		true
	);

	// Get current state
	$state = dnd_vocab_fsrs_test_get_state();
	$history = dnd_vocab_fsrs_test_get_history();

	// Calculate current retrievability
	// Use simulated time if set, otherwise current_time('timestamp')
	$current_time = dnd_vocab_fsrs_test_get_current_time();
	$retrievability = 0;
	if ( $state['last_review_time'] > 0 ) {
		$last_review_seconds = $state['last_review_time'] > 1e12 ? $state['last_review_time'] / 1000 : $state['last_review_time'];
		$elapsed_seconds = $current_time - $last_review_seconds;
		$elapsed_days = max( 0.0, $elapsed_seconds / ( 24 * 60 * 60 ) );
		$retrievability = fsrs_compute_retrievability( $elapsed_days, $state['stability'] );
	} else {
		$retrievability = 1.0; // First time, assume perfect recall
	}

	// Calculate predicted intervals for each rating using phase-based logic
	$predicted_intervals = array();
	$predicted_intervals_formatted = array();
	
	$predicted_data = dnd_vocab_fsrs_test_predict_intervals( $state, $current_time );
	
	// Ensure we have data for all 4 ratings
	if ( empty( $predicted_data ) ) {
		// Fallback: use learning intervals if prediction failed
		if ( function_exists( 'dnd_vocab_predict_learning_intervals' ) ) {
			$learning_timestamps = dnd_vocab_predict_learning_intervals( $current_time );
			foreach ( $learning_timestamps as $rating => $future_timestamp ) {
				$interval_seconds = $future_timestamp - $current_time;
				$interval_days = $interval_seconds / DAY_IN_SECONDS;
				$formatted = function_exists( 'dnd_vocab_human_readable_next_review' ) ? dnd_vocab_human_readable_next_review( (int) $future_timestamp ) : '';
				$predicted_data[ $rating ] = array(
					'days' => $interval_days,
					'timestamp' => $future_timestamp,
					'formatted' => $formatted ? $formatted : '',
				);
			}
		}
	}
	
	foreach ( $predicted_data as $rating => $data ) {
		$predicted_intervals[ $rating ] = isset( $data['days'] ) ? $data['days'] : 0;
		$predicted_intervals_formatted[ $rating ] = isset( $data['formatted'] ) ? $data['formatted'] : '';
	}
	
	// Ensure all 4 ratings have values (fill missing ones)
	for ( $rating = 1; $rating <= 4; $rating++ ) {
		if ( ! isset( $predicted_intervals[ $rating ] ) ) {
			$predicted_intervals[ $rating ] = 0;
		}
		if ( ! isset( $predicted_intervals_formatted[ $rating ] ) ) {
			$predicted_intervals_formatted[ $rating ] = '';
		}
	}

	// Localize script
	wp_localize_script(
		'dnd-vocab-fsrs-test',
		'dndVocabFsrsTest',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dnd_vocab_fsrs_test_ajax' ),
			'i18n'    => array(
				'loading'      => __( 'Processing...', 'dnd-vocab' ),
				'error'        => __( 'An error occurred. Please try again.', 'dnd-vocab' ),
				'confirmReset' => __( 'Are you sure you want to reset? This will clear all review history.', 'dnd-vocab' ),
			),
		)
	);

	?>
	<div class="wrap dnd-vocab-fsrs-test">
		<h1><?php esc_html_e( 'FSRS Algorithm Test', 'dnd-vocab' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Test the FSRS algorithm with a dummy vocabulary item "Vocabulary A". Select a rating for each review and see how the algorithm adjusts stability, difficulty, and intervals.', 'dnd-vocab' ); ?>
		</p>

		<div class="dnd-vocab-fsrs-test-header">
			<h2><?php esc_html_e( 'Vocabulary A', 'dnd-vocab' ); ?></h2>
			<button type="button" id="dnd-vocab-fsrs-test-reset" class="button">
				<?php esc_html_e( 'Reset', 'dnd-vocab' ); ?>
			</button>
		</div>

		<div class="dnd-vocab-fsrs-test-metrics">
			<div class="dnd-vocab-fsrs-test-metric-card" data-metric="stability">
				<div class="dnd-vocab-fsrs-test-metric-label"><?php esc_html_e( 'Stability', 'dnd-vocab' ); ?></div>
				<div class="dnd-vocab-fsrs-test-metric-value" id="dnd-vocab-fsrs-test-stability">
					<?php echo esc_html( number_format( $state['stability'], 2 ) ); ?>
				</div>
				<div class="dnd-vocab-fsrs-test-metric-unit"><?php esc_html_e( 'days', 'dnd-vocab' ); ?></div>
			</div>

			<div class="dnd-vocab-fsrs-test-metric-card" data-metric="difficulty">
				<div class="dnd-vocab-fsrs-test-metric-label"><?php esc_html_e( 'Difficulty', 'dnd-vocab' ); ?></div>
				<div class="dnd-vocab-fsrs-test-metric-value" id="dnd-vocab-fsrs-test-difficulty">
					<?php echo esc_html( number_format( $state['difficulty'], 2 ) ); ?>
				</div>
				<div class="dnd-vocab-fsrs-test-metric-unit"><?php esc_html_e( '1-10', 'dnd-vocab' ); ?></div>
			</div>

			<div class="dnd-vocab-fsrs-test-metric-card" data-metric="retrievability">
				<div class="dnd-vocab-fsrs-test-metric-label"><?php esc_html_e( 'Retrievability', 'dnd-vocab' ); ?></div>
				<div class="dnd-vocab-fsrs-test-metric-value" id="dnd-vocab-fsrs-test-retrievability">
					<?php echo esc_html( number_format( $retrievability * 100, 1 ) ); ?>%
				</div>
				<div class="dnd-vocab-fsrs-test-metric-unit"><?php esc_html_e( '0-100%', 'dnd-vocab' ); ?></div>
			</div>

			<div class="dnd-vocab-fsrs-test-metric-card" data-metric="interval">
				<div class="dnd-vocab-fsrs-test-metric-label"><?php esc_html_e( 'Next Interval', 'dnd-vocab' ); ?></div>
				<div class="dnd-vocab-fsrs-test-metric-value" id="dnd-vocab-fsrs-test-interval">
					<?php
					if ( $state['last_review_time'] > 0 ) {
						$next_interval = fsrs_compute_next_interval_days( $state['stability'] );
						echo esc_html( number_format( $next_interval, 1 ) );
					} else {
						echo '—';
					}
					?>
				</div>
				<div class="dnd-vocab-fsrs-test-metric-unit"><?php esc_html_e( 'days', 'dnd-vocab' ); ?></div>
			</div>
		</div>

		<div class="dnd-vocab-fsrs-test-actions">
			<h3><?php esc_html_e( 'Select Rating', 'dnd-vocab' ); ?></h3>
			<div class="dnd-vocab-fsrs-test-rating-buttons">
				<button type="button" class="dnd-vocab-fsrs-test-rating-btn rating-again" data-rating="1" data-interval="<?php echo esc_attr( $predicted_intervals[1] ); ?>">
					<span class="rating-label"><?php esc_html_e( 'Again', 'dnd-vocab' ); ?></span>
					<span class="rating-desc"><?php esc_html_e( 'Forgot completely', 'dnd-vocab' ); ?></span>
					<?php if ( ! empty( $predicted_intervals_formatted[1] ) ) : ?>
						<span class="rating-interval">
							<?php echo esc_html( $predicted_intervals_formatted[1] ); ?>
						</span>
					<?php endif; ?>
				</button>
				<button type="button" class="dnd-vocab-fsrs-test-rating-btn rating-hard" data-rating="2" data-interval="<?php echo esc_attr( $predicted_intervals[2] ); ?>">
					<span class="rating-label"><?php esc_html_e( 'Hard', 'dnd-vocab' ); ?></span>
					<span class="rating-desc"><?php esc_html_e( 'Remembered with difficulty', 'dnd-vocab' ); ?></span>
					<?php if ( ! empty( $predicted_intervals_formatted[2] ) ) : ?>
						<span class="rating-interval">
							<?php echo esc_html( $predicted_intervals_formatted[2] ); ?>
						</span>
					<?php endif; ?>
				</button>
				<button type="button" class="dnd-vocab-fsrs-test-rating-btn rating-good" data-rating="3" data-interval="<?php echo esc_attr( $predicted_intervals[3] ); ?>">
					<span class="rating-label"><?php esc_html_e( 'Good', 'dnd-vocab' ); ?></span>
					<span class="rating-desc"><?php esc_html_e( 'Remembered well', 'dnd-vocab' ); ?></span>
					<?php if ( ! empty( $predicted_intervals_formatted[3] ) ) : ?>
						<span class="rating-interval">
							<?php echo esc_html( $predicted_intervals_formatted[3] ); ?>
						</span>
					<?php endif; ?>
				</button>
				<button type="button" class="dnd-vocab-fsrs-test-rating-btn rating-easy" data-rating="4" data-interval="<?php echo esc_attr( $predicted_intervals[4] ); ?>">
					<span class="rating-label"><?php esc_html_e( 'Easy', 'dnd-vocab' ); ?></span>
					<span class="rating-desc"><?php esc_html_e( 'Very easy', 'dnd-vocab' ); ?></span>
					<?php if ( ! empty( $predicted_intervals_formatted[4] ) ) : ?>
						<span class="rating-interval">
							<?php echo esc_html( $predicted_intervals_formatted[4] ); ?>
						</span>
					<?php endif; ?>
				</button>
			</div>
		</div>

		<div class="dnd-vocab-fsrs-test-log">
			<h3><?php esc_html_e( 'Review History', 'dnd-vocab' ); ?></h3>
			<div class="dnd-vocab-fsrs-test-log-container">
				<?php if ( empty( $history ) ) : ?>
					<p class="dnd-vocab-fsrs-test-log-empty">
						<?php esc_html_e( 'No reviews yet. Select a rating to start testing.', 'dnd-vocab' ); ?>
					</p>
				<?php else : ?>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Review #', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Timestamp', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Rating', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Stability', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Difficulty', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Retrievability', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Next Interval', 'dnd-vocab' ); ?></th>
								<th><?php esc_html_e( 'Elapsed Days', 'dnd-vocab' ); ?></th>
							</tr>
						</thead>
						<tbody id="dnd-vocab-fsrs-test-log-body">
							<?php
							// Display newest first
							$history_reversed = array_reverse( $history );
							foreach ( $history_reversed as $index => $entry ) :
								$review_number = count( $history ) - $index;
							?>
								<tr>
									<td><?php echo esc_html( $review_number ); ?></td>
									<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $entry['timestamp'] ) ); ?></td>
									<td>
										<span class="rating-badge rating-<?php echo esc_attr( $entry['rating_name'] ); ?>">
											<?php echo esc_html( $entry['rating_label'] ); ?>
										</span>
									</td>
									<td>
										<?php
										echo esc_html( number_format( $entry['stability_before'], 2 ) );
										echo ' → ';
										echo esc_html( number_format( $entry['stability_after'], 2 ) );
										?>
									</td>
									<td>
										<?php
										echo esc_html( number_format( $entry['difficulty_before'], 2 ) );
										echo ' → ';
										echo esc_html( number_format( $entry['difficulty_after'], 2 ) );
										?>
									</td>
									<td><?php echo esc_html( number_format( $entry['retrievability'] * 100, 1 ) ); ?>%</td>
									<td><?php echo esc_html( number_format( $entry['next_interval'], 1 ) ); ?> <?php esc_html_e( 'days', 'dnd-vocab' ); ?></td>
									<td><?php echo esc_html( number_format( $entry['elapsed_days'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * AJAX handler for processing a review
 */
function dnd_vocab_ajax_fsrs_test_review() {
	check_ajax_referer( 'dnd_vocab_fsrs_test_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'dnd-vocab' ) ) );
	}

	$rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;

	if ( ! in_array( $rating, array( 1, 2, 3, 4 ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid rating.', 'dnd-vocab' ) ) );
	}

	// Get current state
	$state = dnd_vocab_fsrs_test_get_state();

	// Calculate elapsed days
	// Use simulated time if set, otherwise current_time('timestamp')
	$current_time = dnd_vocab_fsrs_test_get_current_time();
	$last_review_time = $state['last_review_time'];
	if ( $last_review_time > 1e12 ) {
		$last_review_seconds = $last_review_time / 1000;
	} else {
		$last_review_seconds = $last_review_time;
	}

	$elapsed_seconds = $last_review_time > 0 ? ( $current_time - $last_review_seconds ) : 0;
	$elapsed_days = max( 0.0, $elapsed_seconds / ( 24 * 60 * 60 ) );

	// Store before values
	$stability_before = $state['stability'];
	$difficulty_before = $state['difficulty'];

	// Calculate retrievability before review
	$retrievability_before = 1.0;
	if ( $state['last_review_time'] > 0 ) {
		$retrievability_before = fsrs_compute_retrievability( $elapsed_days, $state['stability'] );
	}

	// Get predicted interval for the selected rating BEFORE processing review
	// This is the interval that was shown to the user, which we'll use to advance time
	$predicted_data_before = dnd_vocab_fsrs_test_predict_intervals( $state, $current_time );
	$selected_rating_interval_days = 0;
	if ( isset( $predicted_data_before[ $rating ] ) && isset( $predicted_data_before[ $rating ]['days'] ) ) {
		$selected_rating_interval_days = $predicted_data_before[ $rating ]['days'];
	}

	// Get current phase (2-phase system: NEW or REVIEW)
	$current_phase = dnd_vocab_fsrs_test_get_phase( $state );
	$new_phase = $current_phase;
	$new_state = $state;
	$next_interval_days = 0;

	// Process review based on phase
	if ( $current_phase === DND_VOCAB_PHASE_NEW ) {
		// NEW PHASE: Use preset hard-coded intervals, then move to REVIEW
		$intervals = dnd_vocab_get_learning_intervals();
		$interval_seconds = isset( $intervals[ $rating ] ) ? $intervals[ $rating ] : $intervals[3];
		$next_interval_days = $interval_seconds / DAY_IN_SECONDS;

		// Initialize FSRS state for REVIEW phase (S₀ = 0.20, D₀ = 5.0)
		if ( function_exists( 'dnd_vocab_init_fsrs_state_for_review' ) ) {
			$new_state = dnd_vocab_init_fsrs_state_for_review( $rating );
			$new_state['last_review_time'] = $current_time * 1000;
		}

		// Track lapse count for Again rating
		if ( $rating === 1 ) {
			$new_state['lapse_count'] = 1;
			$new_state['consecutive_fails'] = 1;
		}

		// All ratings move directly to REVIEW phase after first review
		$new_phase = DND_VOCAB_PHASE_REVIEW;
	} else {
		// REVIEW PHASE: Use FSRS calculation
		// Get scheduled interval
		$scheduled_interval = 0.0;
		if ( $state['last_review_time'] > 0 && $state['stability'] > 0 ) {
			$scheduled_interval = -$state['stability'] * log( 0.9 );
		}

		// Create review event
		$review_event = fsrs_create_review_event(
			$rating,
			$elapsed_days,
			$scheduled_interval,
			$elapsed_days,
			$state['consecutive_fails'],
			$state['lapse_count']
		);

		// Process review with FSRS
		$result = fsrs_plusplus_review( $state, $review_event );
		$new_state = $result['updated_state'];
		$next_interval_days = $result['next_interval_days'];
		$new_phase = DND_VOCAB_PHASE_REVIEW;
	}

	// Update state with phase
	$new_state['phase'] = $new_phase;
	dnd_vocab_fsrs_test_save_state( $new_state );

	// Automatically advance simulated time by the predicted interval for the selected rating
	// This is the interval that was shown to the user before the review
	// This ensures the next test will be at the correct time (e.g., after 10m if Good was selected showing 10m)
	if ( $selected_rating_interval_days > 0 ) {
		$interval_seconds = $selected_rating_interval_days * DAY_IN_SECONDS;
		$new_simulated_time = $current_time + (int) $interval_seconds;
		dnd_vocab_fsrs_test_set_simulated_time( $new_simulated_time );
	}

	// Rating labels
	$rating_labels = array(
		1 => __( 'Again', 'dnd-vocab' ),
		2 => __( 'Hard', 'dnd-vocab' ),
		3 => __( 'Good', 'dnd-vocab' ),
		4 => __( 'Easy', 'dnd-vocab' ),
	);

	$rating_names = array(
		1 => 'again',
		2 => 'hard',
		3 => 'good',
		4 => 'easy',
	);

	// Get updated stability and difficulty for history
	$stability_after = isset( $new_state['stability'] ) ? $new_state['stability'] : $stability_before;
	$difficulty_after = isset( $new_state['difficulty'] ) ? $new_state['difficulty'] : $difficulty_before;

	// Add to history
	$history_entry = array(
		'timestamp'          => $current_time,
		'rating'             => $rating,
		'rating_label'       => $rating_labels[ $rating ],
		'rating_name'        => $rating_names[ $rating ],
		'stability_before'    => $stability_before,
		'stability_after'     => $stability_after,
		'difficulty_before'   => $difficulty_before,
		'difficulty_after'    => $difficulty_after,
		'retrievability'      => $retrievability_before,
		'next_interval'       => $next_interval_days,
		'elapsed_days'        => $elapsed_days,
	);

	dnd_vocab_fsrs_test_add_history( $history_entry );

	// Calculate new retrievability (should be 1.0 after review)
	$retrievability_after = 1.0;

	// Calculate predicted intervals for each rating with new state using phase-based logic
	$new_predicted_intervals = array();
	$new_predicted_intervals_formatted = array();
	
	$predicted_data = dnd_vocab_fsrs_test_predict_intervals( $new_state, $current_time );
	
	foreach ( $predicted_data as $rating_key => $data ) {
		$new_predicted_intervals[ $rating_key ] = $data['days'];
		$new_predicted_intervals_formatted[ $rating_key ] = $data['formatted'];
	}

	// Return updated state and log entry
	wp_send_json_success(
		array(
			'state'        => array(
				'stability'         => $stability_after,
				'difficulty'        => $difficulty_after,
				'retrievability'    => $retrievability_after,
				'next_interval'     => $next_interval_days,
				'lapse_count'       => isset( $new_state['lapse_count'] ) ? $new_state['lapse_count'] : 0,
				'consecutive_fails' => isset( $new_state['consecutive_fails'] ) ? $new_state['consecutive_fails'] : 0,
			),
			'log_entry'    => $history_entry,
			'history_count' => count( dnd_vocab_fsrs_test_get_history() ),
			'predicted_intervals' => $new_predicted_intervals,
			'predicted_intervals_formatted' => $new_predicted_intervals_formatted,
		)
	);
}
add_action( 'wp_ajax_dnd_vocab_fsrs_test_review', 'dnd_vocab_ajax_fsrs_test_review' );

/**
 * AJAX handler for resetting test state
 */
function dnd_vocab_ajax_fsrs_test_reset() {
	check_ajax_referer( 'dnd_vocab_fsrs_test_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'dnd-vocab' ) ) );
	}

	dnd_vocab_fsrs_test_reset();

	// Reset simulated time to null (real time) when resetting test
	dnd_vocab_fsrs_test_reset_simulated_time();

	// Get initial state
	$initial_state = fsrs_create_initial_card_state();
	// Add phase field for phase-based interval calculation
	$initial_state['phase'] = DND_VOCAB_PHASE_NEW;

	// For initial state, use learning intervals (same as study page)
	// Use simulated time if set, otherwise current_time('timestamp')
	$current_time = dnd_vocab_fsrs_test_get_current_time();
	$predicted_intervals = array();
	$predicted_intervals_formatted = array();
	
	// Use phase-based prediction
	$predicted_data = dnd_vocab_fsrs_test_predict_intervals( $initial_state, $current_time );
	
	foreach ( $predicted_data as $rating => $data ) {
		$predicted_intervals[ $rating ] = $data['days'];
		$predicted_intervals_formatted[ $rating ] = $data['formatted'];
	}

	wp_send_json_success(
		array(
			'state'              => $initial_state,
			'predicted_intervals' => $predicted_intervals,
			'predicted_intervals_formatted' => $predicted_intervals_formatted,
		)
	);
}
add_action( 'wp_ajax_dnd_vocab_fsrs_test_reset', 'dnd_vocab_ajax_fsrs_test_reset' );

/**
 * AJAX handler for setting simulated time
 * 
 * NOTE: This handler is disabled as time simulation UI has been removed.
 * Time is now automatically advanced after each review based on the selected rating's interval.
 */
/*
function dnd_vocab_ajax_fsrs_test_set_time() {
	check_ajax_referer( 'dnd_vocab_fsrs_test_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'dnd-vocab' ) ) );
	}

	$simulated_time = null;
	$add_days = null;

	// Check if setting a specific timestamp
	if ( isset( $_POST['simulated_time'] ) && ! empty( $_POST['simulated_time'] ) ) {
		$simulated_time = intval( $_POST['simulated_time'] );
		if ( $simulated_time <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid timestamp.', 'dnd-vocab' ) ) );
		}
		dnd_vocab_fsrs_test_set_simulated_time( $simulated_time );
	} elseif ( isset( $_POST['add_days'] ) ) {
		// Add days to current simulated time (or real time if not set)
		$add_days = floatval( $_POST['add_days'] );
		$current_simulated = dnd_vocab_fsrs_test_get_simulated_time();
		$base_time = $current_simulated !== null ? $current_simulated : current_time( 'timestamp' );
		$new_time = $base_time + ( $add_days * DAY_IN_SECONDS );
		dnd_vocab_fsrs_test_set_simulated_time( $new_time );
		$simulated_time = $new_time;
	} elseif ( isset( $_POST['reset_time'] ) && $_POST['reset_time'] === '1' ) {
		// Reset to real time
		dnd_vocab_fsrs_test_reset_simulated_time();
		$simulated_time = null;
	} else {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'dnd-vocab' ) ) );
	}

	// Get current time (simulated or real)
	$current_time = dnd_vocab_fsrs_test_get_current_time();
	$is_simulated = dnd_vocab_fsrs_test_get_simulated_time() !== null;

	// Format time for display
	$formatted_time = date_i18n( 'Y-m-d H:i:s', $current_time );

	wp_send_json_success(
		array(
			'current_time'     => $current_time,
			'formatted_time'   => $formatted_time,
			'is_simulated'     => $is_simulated,
		)
	);
}
add_action( 'wp_ajax_dnd_vocab_fsrs_test_set_time', 'dnd_vocab_ajax_fsrs_test_set_time' );
*/

