/**
 * Review Heatmap JavaScript
 * Handles interactions and AJAX calls
 */

(function($) {
	'use strict';

	var Heatmap = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Click on heatmap day
			$(document).on('click', '.dnd-vocab-heatmap__day:not(.dnd-vocab-heatmap__day--future)', function(e) {
				e.preventDefault();
				var $day = $(this);
				var date = $day.data('date');
				var count = $day.data('count') || 0;

				if (!date) {
					return;
				}

				self.showModal(date, count);
			});

			// Year navigation
			$(document).on('click', '.dnd-vocab-heatmap__nav-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				
				if ($btn.prop('disabled')) {
					return;
				}

				var year = $btn.data('year');
				if (!year) {
					return;
				}

				self.navigateToYear(year);
			});

			// Close modal
			$(document).on('click', '.dnd-vocab-heatmap__modal-close, .dnd-vocab-heatmap__modal-overlay', function(e) {
				e.preventDefault();
				self.hideModal();
			});

			// Close on Escape key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('.dnd-vocab-heatmap__modal.active').length > 0) {
					self.hideModal();
				}
			});
		},

		navigateToYear: function(year) {
			var self = this;
			var $heatmap = $('.dnd-vocab-heatmap');
			
			if (!$heatmap.length) {
				return;
			}

			// Update URL parameter
			var url = new URL(window.location.href);
			url.searchParams.set('heatmap_year', year);
			window.location.href = url.toString();
		},

		showModal: function(date, count) {
			var self = this;
			var $modal = $('#dnd-vocab-heatmap-modal');
			var $title = $modal.find('.dnd-vocab-heatmap__modal-title');
			var $loading = $modal.find('.dnd-vocab-heatmap__modal-loading');
			var $content = $modal.find('.dnd-vocab-heatmap__modal-content-inner');
			var $reviewedList = $modal.find('.dnd-vocab-heatmap__modal-list--reviewed');
			var $dueList = $modal.find('.dnd-vocab-heatmap__modal-list--due');

			// Format date for display - match tooltip format: "X reviews on [Day] [Month] [Date], [Year]"
			var dateObj = new Date(date + 'T00:00:00');
			var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
			var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
			
			var dayName = dayNames[dateObj.getDay()];
			var monthName = monthNames[dateObj.getMonth()];
			var dayNumber = dateObj.getDate();
			var year = dateObj.getFullYear();
			
			var dateStr = count + ' reviews on ' + dayName + ' ' + monthName + ' ' + dayNumber + ', ' + year;

			$title.text(dateStr);
			$modal.addClass('active');
			$loading.show();
			$content.hide();
			$reviewedList.empty();
			$dueList.empty();

			// AJAX request
			$.ajax({
				url: dndVocabHeatmap.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dnd_vocab_get_cards_by_date',
					nonce: dndVocabHeatmap.nonce,
					date: date
				},
				success: function(response) {
					$loading.hide();

					if (response.success && response.data) {
						self.renderCards($reviewedList, response.data.reviewed || [], 'reviewed');
						self.renderCards($dueList, response.data.due || [], 'due');
						$content.show();
					} else {
						self.showError($content, response.data && response.data.message ? response.data.message : dndVocabHeatmap.i18n.error);
					}
				},
				error: function() {
					$loading.hide();
					self.showError($content, dndVocabHeatmap.i18n.error);
				}
			});
		},

		renderCards: function($list, cards, type) {
			if (!cards || cards.length === 0) {
				$list.append('<li class="dnd-vocab-heatmap__modal-list-item dnd-vocab-heatmap__modal-list-item--empty">' + 
					(dndVocabHeatmap.i18n.noCards || 'Không có cards nào') + 
					'</li>');
				return;
			}

			cards.forEach(function(card) {
				var $item = $('<li class="dnd-vocab-heatmap__modal-list-item"></li>');
				var word = card.word || 'N/A';
				var vocabId = card.vocab_id || 0;
				var deckId = card.deck_id || 0;

				// Create link to vocab detail if possible
				var link = '';
				if (vocabId > 0 && deckId > 0) {
					// Try to construct link to vocab detail page
					var currentUrl = window.location.href;
					var separator = currentUrl.indexOf('?') > -1 ? '&' : '?';
					link = currentUrl + separator + 'deck_id=' + deckId + '&vocab_id=' + vocabId;
				}

				var html = '';
				if (link) {
					html = '<a href="' + link + '">' + word + '</a>';
				} else {
					html = '<span>' + word + '</span>';
				}

				// Add review type or due indicator
				if (type === 'reviewed' && card.review_type) {
					var typeLabel = '';
					switch(card.review_type) {
						case 'new':
							typeLabel = ' (Mới)';
							break;
						case 'learning':
							typeLabel = ' (Đang học)';
							break;
						case 'review':
							typeLabel = ' (Ôn tập)';
							break;
					}
					html += '<span style="color: #666; font-size: 0.9em;">' + typeLabel + '</span>';
				}

				if (type === 'reviewed' && card.review_count > 1) {
					html += ' <span style="color: #666; font-size: 0.9em;">(' + card.review_count + 'x)</span>';
				}

				$item.html(html);
				$list.append($item);
			});
		},

		showError: function($container, message) {
			$container.html('<div class="dnd-vocab-heatmap__modal-loading" style="color: #d1242f;">' + 
				(message || dndVocabHeatmap.i18n.error) + 
				'</div>').show();
		},

		hideModal: function() {
			$('#dnd-vocab-heatmap-modal').removeClass('active');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		Heatmap.init();
	});

})(jQuery);

