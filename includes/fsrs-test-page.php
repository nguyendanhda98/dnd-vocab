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
}

/**
 * Get current phase from test state
 *
 * @param array $state Current card state
 * @return string Phase constant (DND_VOCAB_PHASE_*)
 */
function dnd_vocab_fsrs_test_get_phase( $state ) {
	// If phase is explicitly set, use it
	if ( isset( $state['phase'] ) && ! empty( $state['phase'] ) ) {
		$phase = $state['phase'];
		// Validate phase
		if ( in_array( $phase, array( DND_VOCAB_PHASE_NEW, DND_VOCAB_PHASE_LEARNING, DND_VOCAB_PHASE_TRANSITION, DND_VOCAB_PHASE_REVIEW ), true ) ) {
			return $phase;
		}
	}

	// Determine phase from state
	// If never reviewed, it's NEW
	if ( ! isset( $state['last_review_time'] ) || $state['last_review_time'] == 0 ) {
		return DND_VOCAB_PHASE_NEW;
	}

	// If stability is meaningful (> 0.5), likely in REVIEW phase
	if ( isset( $state['stability'] ) && (float) $state['stability'] > 0.5 ) {
		return DND_VOCAB_PHASE_REVIEW;
	}

	// Default to LEARNING for cards with low stability
	return DND_VOCAB_PHASE_LEARNING;
}

/**
 * Calculate predicted intervals based on current phase
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
	
	if ( $phase === DND_VOCAB_PHASE_NEW || $phase === DND_VOCAB_PHASE_LEARNING ) {
		// LEARNING PHASE: Use preset learning intervals
		if ( function_exists( 'dnd_vocab_predict_learning_intervals' ) ) {
			$predicted_timestamps = dnd_vocab_predict_learning_intervals( $now );
		} else {
			// Fallback: use learning intervals directly
			$intervals = dnd_vocab_get_learning_intervals();
			$predicted_timestamps = array(
				1 => $now + $intervals[1],  // Again: 1 minute
				2 => $now + $intervals[2],  // Hard: 5 minutes
				3 => $now + $intervals[3],  // Good: 10 minutes
				4 => $now + $intervals[4],  // Easy: 2 days
			);
		}
	} elseif ( $phase === DND_VOCAB_PHASE_TRANSITION ) {
		// TRANSITION PHASE: Use preset transition intervals
		if ( function_exists( 'dnd_vocab_predict_transition_intervals' ) ) {
			$predicted_timestamps = dnd_vocab_predict_transition_intervals( $now );
		} else {
			// Fallback: use transition intervals directly
			$intervals = dnd_vocab_get_transition_intervals();
			$predicted_timestamps = array(
				1 => $now + $intervals[1],  // Again: 5 minutes
				2 => $now + $intervals[2],  // Hard: 30 minutes
				3 => $now + $intervals[3],  // Good: 1 day
				4 => $now + $intervals[4],  // Easy: 2 days
			);
		}
	} else {
		// REVIEW PHASE: Use FSRS calculation
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
	$current_time = time();
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
	// Use current_time('timestamp') to match dnd_vocab_human_readable_next_review()
	$current_time = current_time( 'timestamp' );
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
	// Use current_time('timestamp') to match dnd_vocab_human_readable_next_review()
	$current_time = current_time( 'timestamp' );
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

	// Get current phase
	$current_phase = dnd_vocab_fsrs_test_get_phase( $state );
	$new_phase = $current_phase;
	$new_state = $state;
	$next_interval_days = 0;

	// Process review based on phase
	if ( $current_phase === DND_VOCAB_PHASE_NEW || $current_phase === DND_VOCAB_PHASE_LEARNING ) {
		// LEARNING PHASE: Use preset intervals and update phase
		$intervals = dnd_vocab_get_learning_intervals();
		$interval_seconds = isset( $intervals[ $rating ] ) ? $intervals[ $rating ] : $intervals[3];
		$next_interval_days = $interval_seconds / DAY_IN_SECONDS;

		if ( $rating === 4 ) {
			// Easy: Skip directly to REVIEW phase
			if ( function_exists( 'dnd_vocab_init_fsrs_state_for_review' ) ) {
				$new_state = dnd_vocab_init_fsrs_state_for_review( $rating );
				$new_state['last_review_time'] = $current_time * 1000;
			}
			$new_phase = DND_VOCAB_PHASE_REVIEW;
		} elseif ( $rating === 3 ) {
			// Good: Move to TRANSITION phase
			$new_state['last_review_time'] = $current_time * 1000;
			$new_state['stability'] = 0.5; // Placeholder for learning
			$new_state['difficulty'] = 5.0;
			$new_phase = DND_VOCAB_PHASE_TRANSITION;
		} else {
			// Again or Hard: Stay in LEARNING phase
			$new_state['last_review_time'] = $current_time * 1000;
			$new_state['stability'] = 0.5;
			$new_state['difficulty'] = 5.0;
			if ( $rating === 1 ) {
				$new_state['consecutive_fails'] = isset( $new_state['consecutive_fails'] ) ? $new_state['consecutive_fails'] + 1 : 1;
			}
			$new_phase = DND_VOCAB_PHASE_LEARNING;
		}
	} elseif ( $current_phase === DND_VOCAB_PHASE_TRANSITION ) {
		// TRANSITION PHASE: Use preset intervals and update phase
		$intervals = dnd_vocab_get_transition_intervals();
		$interval_seconds = isset( $intervals[ $rating ] ) ? $intervals[ $rating ] : $intervals[3];
		$next_interval_days = $interval_seconds / DAY_IN_SECONDS;

		if ( $rating === 3 || $rating === 4 ) {
			// Good or Easy: Graduate to REVIEW phase
			if ( function_exists( 'dnd_vocab_init_fsrs_state_for_review' ) ) {
				$new_state = dnd_vocab_init_fsrs_state_for_review( $rating );
				$new_state['last_review_time'] = $current_time * 1000;
				// Carry over lapse counts
				if ( isset( $state['lapse_count'] ) ) {
					$new_state['lapse_count'] = $state['lapse_count'];
				}
				$new_state['consecutive_fails'] = 0; // Reset on graduation
			}
			$new_phase = DND_VOCAB_PHASE_REVIEW;
		} elseif ( $rating === 1 ) {
			// Again: Back to LEARNING phase
			$intervals_learning = dnd_vocab_get_learning_intervals();
			$next_interval_days = $intervals_learning[1] / DAY_IN_SECONDS;
			$new_state['last_review_time'] = $current_time * 1000;
			$new_state['stability'] = 0.5;
			$new_state['difficulty'] = 5.0;
			$new_state['lapse_count'] = isset( $state['lapse_count'] ) ? $state['lapse_count'] + 1 : 1;
			$new_state['consecutive_fails'] = isset( $state['consecutive_fails'] ) ? $state['consecutive_fails'] + 1 : 1;
			$new_phase = DND_VOCAB_PHASE_LEARNING;
		} else {
			// Hard: Stay in TRANSITION phase
			$new_state['last_review_time'] = $current_time * 1000;
			$new_phase = DND_VOCAB_PHASE_TRANSITION;
		}
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

	// Get initial state
	$initial_state = fsrs_create_initial_card_state();
	// Add phase field for phase-based interval calculation
	$initial_state['phase'] = DND_VOCAB_PHASE_NEW;

	// For initial state, use learning intervals (same as study page)
	// Use current_time('timestamp') to match dnd_vocab_human_readable_next_review()
	$current_time = current_time( 'timestamp' );
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

