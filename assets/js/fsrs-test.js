(function($) {
	'use strict';

	var FSRSTest = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Rating button clicks
			$('.dnd-vocab-fsrs-test-rating-btn').on('click', function() {
				var $btn = $(this);
				var rating = parseInt($btn.data('rating'), 10);
				self.processReview(rating, $btn);
			});

			// Reset button click
			$('#dnd-vocab-fsrs-test-reset').on('click', function() {
				if (confirm(dndVocabFsrsTest.i18n.confirmReset || 'Are you sure you want to reset? This will clear all review history.')) {
					self.resetTest();
				}
			});
		},

		processReview: function(rating, $btn) {
			var self = this;

			// Disable all buttons
			$('.dnd-vocab-fsrs-test-rating-btn').prop('disabled', true);
			$btn.addClass('processing');

			// Show loading state
			var originalText = $btn.find('.rating-label').text();
			$btn.find('.rating-label').text(dndVocabFsrsTest.i18n.loading || 'Processing...');

			$.ajax({
				url: dndVocabFsrsTest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dnd_vocab_fsrs_test_review',
					nonce: dndVocabFsrsTest.nonce,
					rating: rating
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateMetrics(response.data.state);
						self.addLogEntry(response.data.log_entry, response.data.history_count);
						if (response.data.predicted_intervals) {
							self.updatePredictedIntervals(
								response.data.predicted_intervals,
								response.data.predicted_intervals_formatted
							);
						}
						// Reload page to reflect the new simulated time (automatically advanced)
						location.reload();
					} else {
						alert(response.data && response.data.message ? response.data.message : (dndVocabFsrsTest.i18n.error || 'An error occurred'));
					}
				},
				error: function() {
					alert(dndVocabFsrsTest.i18n.error || 'An error occurred');
				},
				complete: function() {
					// Re-enable buttons
					$('.dnd-vocab-fsrs-test-rating-btn').prop('disabled', false);
					$btn.removeClass('processing');
					$btn.find('.rating-label').text(originalText);
				}
			});
		},

		updateMetrics: function(state) {
			// Update stability
			$('#dnd-vocab-fsrs-test-stability').text(parseFloat(state.stability).toFixed(2));
			this.animateValueChange('#dnd-vocab-fsrs-test-stability');

			// Update difficulty
			$('#dnd-vocab-fsrs-test-difficulty').text(parseFloat(state.difficulty).toFixed(2));
			this.animateValueChange('#dnd-vocab-fsrs-test-difficulty');

			// Update retrievability
			$('#dnd-vocab-fsrs-test-retrievability').text((parseFloat(state.retrievability) * 100).toFixed(1) + '%');
			this.animateValueChange('#dnd-vocab-fsrs-test-retrievability');

			// Update interval
			if (state.next_interval) {
				$('#dnd-vocab-fsrs-test-interval').text(parseFloat(state.next_interval).toFixed(1));
			} else {
				$('#dnd-vocab-fsrs-test-interval').text('—');
			}
			this.animateValueChange('#dnd-vocab-fsrs-test-interval');
		},

		animateValueChange: function(selector) {
			var $el = $(selector);
			$el.addClass('value-changed');
			setTimeout(function() {
				$el.removeClass('value-changed');
			}, 1000);
		},

		updatePredictedIntervals: function(intervals, formattedIntervals) {
			var self = this;
			// Update interval for each rating button
			$('.dnd-vocab-fsrs-test-rating-btn').each(function() {
				var $btn = $(this);
				var rating = parseInt($btn.data('rating'), 10);
				if (intervals[rating] !== undefined) {
					var $intervalEl = $btn.find('.rating-interval');
					
					// Use formatted interval if available, otherwise create the element
					if (formattedIntervals && formattedIntervals[rating]) {
						if (!$intervalEl.length) {
							// Create interval element if it doesn't exist
							$btn.append('<span class="rating-interval"></span>');
							$intervalEl = $btn.find('.rating-interval');
						}
						$intervalEl.text(formattedIntervals[rating]);
						$intervalEl.addClass('interval-updated');
						setTimeout(function() {
							$intervalEl.removeClass('interval-updated');
						}, 1000);
					}
					
					// Update data attribute
					$btn.data('interval', intervals[rating]);
				}
			});
		},

		addLogEntry: function(entry, reviewNumber) {
			var $logBody = $('#dnd-vocab-fsrs-test-log-body');
			var $logContainer = $('.dnd-vocab-fsrs-test-log-container');
			var $emptyMessage = $('.dnd-vocab-fsrs-test-log-empty');

			// Remove empty message if exists
			if ($emptyMessage.length) {
				$emptyMessage.remove();
			}

			// Create table if it doesn't exist
			if (!$logBody.length) {
				var tableHtml = '<table class="widefat fixed striped">' +
					'<thead>' +
					'<tr>' +
					'<th>Review #</th>' +
					'<th>Timestamp</th>' +
					'<th>Rating</th>' +
					'<th>Stability</th>' +
					'<th>Difficulty</th>' +
					'<th>Retrievability</th>' +
					'<th>Next Interval</th>' +
					'<th>Elapsed Days</th>' +
					'</tr>' +
					'</thead>' +
					'<tbody id="dnd-vocab-fsrs-test-log-body"></tbody>' +
					'</table>';
				$logContainer.html(tableHtml);
				$logBody = $('#dnd-vocab-fsrs-test-log-body');
			}

			// Format timestamp - use pre-formatted from PHP if available, otherwise format in JavaScript
			var timestampStr = entry.timestamp_formatted;
			if (!timestampStr && entry.timestamp) {
				var timestamp = new Date(entry.timestamp * 1000);
				timestampStr = timestamp.toLocaleString('en-US', {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				});
			}

			// Create row (newest at top, so this is review #reviewNumber)
			var rowHtml = '<tr class="log-entry-new">' +
				'<td>' + reviewNumber + '</td>' +
				'<td>' + timestampStr + '</td>' +
				'<td><span class="rating-badge rating-' + entry.rating_name + '">' + entry.rating_label + '</span></td>' +
				'<td>' + parseFloat(entry.stability_before).toFixed(2) + ' → ' + parseFloat(entry.stability_after).toFixed(2) + '</td>' +
				'<td>' + parseFloat(entry.difficulty_before).toFixed(2) + ' → ' + parseFloat(entry.difficulty_after).toFixed(2) + '</td>' +
				'<td>' + (parseFloat(entry.retrievability) * 100).toFixed(1) + '%</td>' +
				'<td>' + parseFloat(entry.next_interval).toFixed(1) + ' days</td>' +
				'<td>' + parseFloat(entry.elapsed_days).toFixed(2) + '</td>' +
				'</tr>';

			// Prepend new row (newest at top)
			$logBody.prepend(rowHtml);

			// Animate new row
			setTimeout(function() {
				$('.log-entry-new').removeClass('log-entry-new');
			}, 100);
		},

		resetTest: function() {
			var self = this;

			$.ajax({
				url: dndVocabFsrsTest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dnd_vocab_fsrs_test_reset',
					nonce: dndVocabFsrsTest.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						// Update predicted intervals before reload
						if (response.data.predicted_intervals) {
							self.updatePredictedIntervals(response.data.predicted_intervals);
						}
						// Reload page to show reset state
						location.reload();
					} else {
						alert(response.data && response.data.message ? response.data.message : (dndVocabFsrsTest.i18n.error || 'An error occurred'));
					}
				},
				error: function() {
					alert(dndVocabFsrsTest.i18n.error || 'An error occurred');
				}
			});
		},

	};

	// Initialize when document is ready
	$(document).ready(function() {
		FSRSTest.init();
	});

})(jQuery);

