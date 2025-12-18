<?php
/**
 * Frontend shortcodes for DND Vocab plugin.
 *
 * @package DND_Vocab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main library shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function dnd_vocab_render_deck_list_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(),
		$atts,
		'dnd_vocab_deck_list'
	);

	$message = '';

	// Handle add/remove deck actions.
	if ( 'POST' === $_SERVER['REQUEST_METHOD']
		&& isset( $_POST['dnd_vocab_deck_action'], $_POST['dnd_vocab_deck_id'] )
	) {
		$action  = sanitize_text_field( wp_unslash( $_POST['dnd_vocab_deck_action'] ) );
		$deck_id = absint( $_POST['dnd_vocab_deck_id'] );

		if ( isset( $_POST['dnd_vocab_deck_nonce'] )
			&& in_array( $action, array( 'add', 'remove' ), true )
			&& $deck_id
			&& is_user_logged_in()
			&& wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['dnd_vocab_deck_nonce'] ) ),
				'dnd_vocab_update_deck_collection'
			)
		) {
			$user_id    = get_current_user_id();
			$user_decks = get_user_meta( $user_id, 'dnd_vocab_user_decks', true );

			if ( ! is_array( $user_decks ) ) {
				$user_decks = array();
			}

			if ( 'add' === $action ) {
				if ( ! in_array( $deck_id, $user_decks, true ) ) {
					$user_decks[] = $deck_id;
				}
				$message = __( 'Đã thêm deck vào bộ từ vựng cá nhân của bạn.', 'dnd-vocab' );
			} else {
				$user_decks = array_diff( $user_decks, array( $deck_id ) );
				$message    = __( 'Đã gỡ deck khỏi bộ từ vựng cá nhân của bạn.', 'dnd-vocab' );
			}

			$user_decks = array_map( 'absint', $user_decks );
			$user_decks = array_values( array_unique( $user_decks ) );

			update_user_meta( $user_id, 'dnd_vocab_user_decks', $user_decks );
		} else {
			$message = __( 'Không thể cập nhật bộ deck cá nhân. Vui lòng thử lại.', 'dnd-vocab' );
		}
	}

	// Load user collection.
	$current_user_decks = array();
	if ( is_user_logged_in() ) {
		$current_user_decks = get_user_meta( get_current_user_id(), 'dnd_vocab_user_decks', true );
		if ( ! is_array( $current_user_decks ) ) {
			$current_user_decks = array();
		}
		$current_user_decks = array_map( 'absint', $current_user_decks );
	}

	// View routing.
	$deck_id  = isset( $_GET['deck_id'] ) ? absint( $_GET['deck_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$vocab_id = isset( $_GET['vocab_id'] ) ? absint( $_GET['vocab_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	ob_start();
	?>
	<div class="dnd-vocab-library">
		<?php if ( $message ) : ?>
			<div class="dnd-vocab-library__notice">
				<?php echo esc_html( $message ); ?>
			</div>
		<?php endif; ?>

		<?php
		if ( $deck_id && $vocab_id ) {
			echo dnd_vocab_render_vocab_detail_view( $deck_id, $vocab_id );
		} elseif ( $deck_id ) {
			echo dnd_vocab_render_deck_detail_view( $deck_id, $current_user_decks );
		} else {
			echo dnd_vocab_render_deck_list_view( $current_user_decks );
		}
		?>
	</div>
	<?php

	return ob_get_clean();
}
add_shortcode( 'dnd_vocab_deck_list', 'dnd_vocab_render_deck_list_shortcode' );

/**
 * Render deck list view.
 *
 * @param array $current_user_decks Current user's saved decks.
 * @return string
 */
function dnd_vocab_render_deck_list_view( $current_user_decks ) {
	$decks = get_posts(
		array(
			'post_type'      => 'dnd_deck',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$base_url = get_permalink();

	ob_start();
	?>
	<div class="dnd-vocab-library__deck-list">
		<h2><?php esc_html_e( 'Tất cả deck', 'dnd-vocab' ); ?></h2>

		<?php if ( empty( $decks ) ) : ?>
			<p><?php esc_html_e( 'Hiện chưa có deck nào.', 'dnd-vocab' ); ?></p>
		<?php else : ?>
			<ul class="dnd-vocab-library__deck-items">
				<?php foreach ( $decks as $deck ) : ?>
					<?php
					$deck_id = $deck->ID;
					$tags    = wp_get_object_terms(
						$deck_id,
						'dnd_tag',
						array(
							'fields' => 'names',
						)
					);
					$tag_list = ( ! empty( $tags ) && ! is_wp_error( $tags ) ) ? implode( ', ', $tags ) : '';

					$vocab_ids = get_posts(
						array(
							'post_type'      => 'dnd_vocab_item',
							'post_status'    => 'publish',
							'fields'         => 'ids',
							'meta_key'       => '_dnd_vocab_deck_id',
							'meta_value'     => $deck_id,
							'posts_per_page' => -1,
						)
					);
					$vocab_count  = is_array( $vocab_ids ) ? count( $vocab_ids ) : 0;
					$detail_url   = add_query_arg(
						array(
							'deck_id' => $deck_id,
						),
						$base_url
					);
					$in_collection = in_array( $deck_id, $current_user_decks, true );
					?>
					<li class="dnd-vocab-library__deck-item">
						<h3 class="dnd-vocab-library__deck-title">
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<?php echo esc_html( get_the_title( $deck ) ); ?>
							</a>
						</h3>

						<?php if ( $tag_list ) : ?>
							<p class="dnd-vocab-library__deck-tags">
								<?php esc_html_e( 'Tags:', 'dnd-vocab' ); ?>
								<?php echo ' ' . esc_html( $tag_list ); ?>
							</p>
						<?php endif; ?>

						<p class="dnd-vocab-library__deck-count">
							<?php
							printf(
								/* translators: %d: vocabulary count */
								esc_html__( '%d từ vựng', 'dnd-vocab' ),
								intval( $vocab_count )
							);
							?>
						</p>

						<?php if ( $in_collection && is_user_logged_in() ) : ?>
							<p class="dnd-vocab-library__deck-status">
								<?php esc_html_e( 'Đã có trong bộ từ vựng của bạn.', 'dnd-vocab' ); ?>
							</p>
						<?php endif; ?>

						<p>
							<a class="dnd-vocab-library__deck-link" href="<?php echo esc_url( $detail_url ); ?>">
								<?php esc_html_e( 'Xem chi tiết deck', 'dnd-vocab' ); ?>
							</a>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Render deck detail view.
 *
 * @param int   $deck_id Deck ID.
 * @param array $current_user_decks Current user's saved decks.
 * @return string
 */
function dnd_vocab_render_deck_detail_view( $deck_id, $current_user_decks ) {
	$deck = get_post( $deck_id );

	if ( ! $deck || 'dnd_deck' !== $deck->post_type || 'publish' !== $deck->post_status ) {
		ob_start();
		?>
		<p><?php esc_html_e( 'Deck không tồn tại hoặc không khả dụng.', 'dnd-vocab' ); ?></p>
		<p>
			<a href="<?php echo esc_url( get_permalink() ); ?>">
				<?php esc_html_e( 'Quay lại danh sách deck', 'dnd-vocab' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	$tags = wp_get_object_terms(
		$deck_id,
		'dnd_tag',
		array(
			'fields' => 'names',
		)
	);
	$tag_list = ( ! empty( $tags ) && ! is_wp_error( $tags ) ) ? implode( ', ', $tags ) : '';

	$items = get_posts(
		array(
			'post_type'      => 'dnd_vocab_item',
			'post_status'    => 'publish',
			'meta_key'       => '_dnd_vocab_deck_id',
			'meta_value'     => $deck_id,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		)
	);

	$vocab_count   = is_array( $items ) ? count( $items ) : 0;
	$base_url      = get_permalink();
	$back_to_list  = $base_url;
	$in_collection = in_array( $deck_id, $current_user_decks, true );

	ob_start();
	?>
	<div class="dnd-vocab-library__deck-detail">
		<p>
			<a href="<?php echo esc_url( $back_to_list ); ?>">
				<?php esc_html_e( '← Quay lại danh sách deck', 'dnd-vocab' ); ?>
			</a>
		</p>

		<h2 class="dnd-vocab-library__deck-title">
			<?php echo esc_html( get_the_title( $deck ) ); ?>
		</h2>

		<?php if ( $tag_list ) : ?>
			<p class="dnd-vocab-library__deck-tags">
				<?php esc_html_e( 'Tags:', 'dnd-vocab' ); ?>
				<?php echo ' ' . esc_html( $tag_list ); ?>
			</p>
		<?php endif; ?>

		<p class="dnd-vocab-library__deck-count">
			<?php
			printf(
				/* translators: %d: vocabulary count */
				esc_html__( '%d từ vựng trong deck này.', 'dnd-vocab' ),
				intval( $vocab_count )
			);
			?>
		</p>

		<?php if ( is_user_logged_in() ) : ?>
			<form method="post" class="dnd-vocab-library__deck-form">
				<?php wp_nonce_field( 'dnd_vocab_update_deck_collection', 'dnd_vocab_deck_nonce' ); ?>
				<input type="hidden" name="dnd_vocab_deck_id" value="<?php echo esc_attr( $deck_id ); ?>">
				<input type="hidden" name="dnd_vocab_deck_action" value="<?php echo $in_collection ? 'remove' : 'add'; ?>">
				<button type="submit" class="dnd-vocab-library__deck-button">
					<?php
					if ( $in_collection ) {
						esc_html_e( 'Gỡ deck này khỏi bộ từ vựng của tôi', 'dnd-vocab' );
					} else {
						esc_html_e( 'Thêm deck này vào bộ từ vựng của tôi', 'dnd-vocab' );
					}
					?>
				</button>
			</form>
		<?php else : ?>
			<p class="dnd-vocab-library__login-hint">
				<?php esc_html_e( 'Hãy đăng nhập để lưu deck vào bộ từ vựng cá nhân của bạn.', 'dnd-vocab' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( empty( $items ) ) : ?>
			<p><?php esc_html_e( 'Deck này chưa có từ vựng nào.', 'dnd-vocab' ); ?></p>
		<?php else : ?>
			<table class="dnd-vocab-library__vocab-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Từ vựng', 'dnd-vocab' ); ?></th>
						<th><?php esc_html_e( 'IPA', 'dnd-vocab' ); ?></th>
						<th><?php esc_html_e( 'Nghĩa ngắn', 'dnd-vocab' ); ?></th>
						<th><?php esc_html_e( 'Chi tiết', 'dnd-vocab' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$word       = get_post_meta( $item->ID, 'dnd_vocab_word', true );
						$ipa        = get_post_meta( $item->ID, 'dnd_vocab_ipa', true );
						$short_vi   = get_post_meta( $item->ID, 'dnd_vocab_short_vietnamese', true );
						$detail_url = add_query_arg(
							array(
								'deck_id'  => $deck_id,
								'vocab_id' => $item->ID,
							),
							$base_url
						);
						?>
						<tr>
							<td><strong><?php echo esc_html( $word ? $word : get_the_title( $item ) ); ?></strong></td>
							<td><?php echo esc_html( $ipa ); ?></td>
							<td><?php echo esc_html( $short_vi ); ?></td>
							<td>
								<a href="<?php echo esc_url( $detail_url ); ?>">
									<?php esc_html_e( 'Xem chi tiết', 'dnd-vocab' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Render vocabulary detail view.
 *
 * @param int $deck_id  Deck ID.
 * @param int $vocab_id Vocabulary item ID.
 * @return string
 */
function dnd_vocab_render_vocab_detail_view( $deck_id, $vocab_id ) {
	$deck  = get_post( $deck_id );
	$vocab = get_post( $vocab_id );

	$base_url = get_permalink();

	if ( ! $deck || 'dnd_deck' !== $deck->post_type || 'publish' !== $deck->post_status ) {
		ob_start();
		?>
		<p><?php esc_html_e( 'Deck không tồn tại hoặc không khả dụng.', 'dnd-vocab' ); ?></p>
		<p>
			<a href="<?php echo esc_url( $base_url ); ?>">
				<?php esc_html_e( 'Quay lại danh sách deck', 'dnd-vocab' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	if ( ! $vocab || 'dnd_vocab_item' !== $vocab->post_type || 'publish' !== $vocab->post_status ) {
		ob_start();
		?>
		<p><?php esc_html_e( 'Từ vựng không tồn tại hoặc không khả dụng.', 'dnd-vocab' ); ?></p>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'deck_id', $deck_id, $base_url ) ); ?>">
				<?php esc_html_e( 'Quay lại deck', 'dnd-vocab' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	$linked_deck_id = (int) get_post_meta( $vocab_id, '_dnd_vocab_deck_id', true );
	if ( $linked_deck_id !== (int) $deck_id ) {
		ob_start();
		?>
		<p><?php esc_html_e( 'Từ vựng này không thuộc deck đã chọn.', 'dnd-vocab' ); ?></p>
		<p>
			<a href="<?php echo esc_url( $base_url ); ?>">
				<?php esc_html_e( 'Quay lại danh sách deck', 'dnd-vocab' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	$word             = get_post_meta( $vocab_id, 'dnd_vocab_word', true );
	$ipa              = get_post_meta( $vocab_id, 'dnd_vocab_ipa', true );
	$definition       = get_post_meta( $vocab_id, 'dnd_vocab_definition', true );
	$example          = get_post_meta( $vocab_id, 'dnd_vocab_example', true );
	$image_url        = get_post_meta( $vocab_id, 'dnd_vocab_image', true );
	$word_sound       = get_post_meta( $vocab_id, 'dnd_vocab_word_sound', true );
	$definition_sound = get_post_meta( $vocab_id, 'dnd_vocab_definition_sound', true );
	$example_sound    = get_post_meta( $vocab_id, 'dnd_vocab_example_sound', true );
	$short_vi         = get_post_meta( $vocab_id, 'dnd_vocab_short_vietnamese', true );
	$full_vi          = get_post_meta( $vocab_id, 'dnd_vocab_full_vietnamese', true );
	$suggestion       = get_post_meta( $vocab_id, 'dnd_vocab_suggestion', true );

	if ( function_exists( 'dnd_vocab_strip_cloze' ) ) {
		$definition = dnd_vocab_strip_cloze( $definition );
		$example    = dnd_vocab_strip_cloze( $example );
	}

	$back_to_deck = add_query_arg(
		array(
			'deck_id' => $deck_id,
		),
		$base_url
	);

	ob_start();
	?>
	<div class="dnd-vocab-library__vocab-detail">
		<p>
			<a href="<?php echo esc_url( $back_to_deck ); ?>">
				<?php esc_html_e( '← Quay lại deck', 'dnd-vocab' ); ?>
			</a>
			|
			<a href="<?php echo esc_url( $base_url ); ?>">
				<?php esc_html_e( 'Quay lại danh sách deck', 'dnd-vocab' ); ?>
			</a>
		</p>

		<h2 class="dnd-vocab-library__vocab-word">
			<?php echo esc_html( $word ? $word : get_the_title( $vocab ) ); ?>
			<?php if ( $ipa ) : ?>
				<span class="dnd-vocab-library__vocab-ipa">/<?php echo esc_html( $ipa ); ?>/</span>
			<?php endif; ?>
		</h2>

		<?php if ( $suggestion ) : ?>
			<p class="dnd-vocab-library__vocab-suggestion">
				<?php esc_html_e( 'Gợi ý:', 'dnd-vocab' ); ?>
				<?php echo ' ' . esc_html( $suggestion ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $short_vi || $full_vi ) : ?>
			<div class="dnd-vocab-library__vocab-vietnamese">
				<?php if ( $short_vi ) : ?>
					<p><strong><?php esc_html_e( 'Nghĩa ngắn:', 'dnd-vocab' ); ?></strong> <?php echo esc_html( $short_vi ); ?></p>
				<?php endif; ?>
				<?php if ( $full_vi ) : ?>
					<p><strong><?php esc_html_e( 'Nghĩa đầy đủ:', 'dnd-vocab' ); ?></strong> <?php echo esc_html( $full_vi ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $definition ) : ?>
			<div class="dnd-vocab-library__vocab-definition">
				<h3><?php esc_html_e( 'Định nghĩa', 'dnd-vocab' ); ?></h3>
				<p><?php echo wp_kses_post( nl2br( esc_html( $definition ) ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $example ) : ?>
			<div class="dnd-vocab-library__vocab-example">
				<h3><?php esc_html_e( 'Ví dụ', 'dnd-vocab' ); ?></h3>
				<p><?php echo wp_kses_post( nl2br( esc_html( $example ) ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $image_url ) : ?>
			<div class="dnd-vocab-library__vocab-image">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $word ); ?>">
			</div>
		<?php endif; ?>

		<?php if ( $word_sound || $definition_sound || $example_sound ) : ?>
			<div class="dnd-vocab-library__vocab-audio">
				<?php if ( $word_sound ) : ?>
					<p>
						<strong><?php esc_html_e( 'Phát âm từ:', 'dnd-vocab' ); ?></strong><br>
						<audio controls src="<?php echo esc_url( $word_sound ); ?>"></audio>
					</p>
				<?php endif; ?>

				<?php if ( $definition_sound ) : ?>
					<p>
						<strong><?php esc_html_e( 'Phát âm định nghĩa:', 'dnd-vocab' ); ?></strong><br>
						<audio controls src="<?php echo esc_url( $definition_sound ); ?>"></audio>
					</p>
				<?php endif; ?>

				<?php if ( $example_sound ) : ?>
					<p>
						<strong><?php esc_html_e( 'Phát âm ví dụ:', 'dnd-vocab' ); ?></strong><br>
						<audio controls src="<?php echo esc_url( $example_sound ); ?>"></audio>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Study shortcode with spaced repetition (Anki-like).
 *
 * Shortcode: [dnd_vocab_study]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function dnd_vocab_render_study_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(),
		$atts,
		'dnd_vocab_study'
	);

	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'Hãy đăng nhập để sử dụng chế độ học và ôn tập từ vựng.', 'dnd-vocab' ) . '</p>';
	}

	$user_id    = get_current_user_id();
	$user_decks = get_user_meta( $user_id, 'dnd_vocab_user_decks', true );

	if ( ! is_array( $user_decks ) ) {
		$user_decks = array();
	}

	$user_decks = array_map( 'absint', $user_decks );
	$user_decks = array_filter( $user_decks );

	if ( empty( $user_decks ) ) {
		return '<p>' . esc_html__( 'Bạn chưa thêm deck nào vào bộ từ vựng cá nhân. Hãy vào trang thư viện deck để thêm ít nhất một deck trước khi học.', 'dnd-vocab' ) . '</p>';
	}

	// Check for due items (words that need review today).
	$due_items        = array();
	$due_items_count  = 0;
	if ( function_exists( 'dnd_vocab_srs_get_due_items' ) ) {
		$due_items = dnd_vocab_srs_get_due_items( $user_id, $user_decks );
		$due_items_count = is_array( $due_items ) ? count( $due_items ) : 0;
	}

	$message          = '';
	$review_notice    = '';
	$current_vocab_id = 0;
	$current_deck_id  = 0;
	$view_side        = 'front'; // front|back.

	// Always show notification about review status.
	if ( $due_items_count > 0 ) {
		$review_notice = sprintf(
			/* translators: %d: number of words to review */
			esc_html__( 'Bạn có %d từ cần ôn tập hôm nay, ôn tập ngay.', 'dnd-vocab' ),
			$due_items_count
		);
	} else {
		$review_notice = esc_html__( 'Bạn không có từ nào cần ôn tập hôm nay. Bạn có thể học từ mới.', 'dnd-vocab' );
	}

	// Handle study actions (show back / answer).
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['dnd_vocab_study_action'] ) ) {
		$action = sanitize_text_field( wp_unslash( $_POST['dnd_vocab_study_action'] ) );

		if ( in_array( $action, array( 'show_back', 'answer' ), true )
			&& isset( $_POST['dnd_vocab_study_nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['dnd_vocab_study_nonce'] ) ),
				'dnd_vocab_study'
			)
		) {
			$vocab_id = isset( $_POST['dnd_vocab_study_vocab_id'] ) ? absint( $_POST['dnd_vocab_study_vocab_id'] ) : 0;
			$deck_id  = isset( $_POST['dnd_vocab_study_deck_id'] ) ? absint( $_POST['dnd_vocab_study_deck_id'] ) : 0;

			if ( $vocab_id && $deck_id && in_array( $deck_id, $user_decks, true ) ) {
				// Extra safety: ensure vocab belongs to deck.
				$linked_deck_id = (int) get_post_meta( $vocab_id, '_dnd_vocab_deck_id', true );

				if ( $linked_deck_id === $deck_id ) {
					if ( 'show_back' === $action ) {
						// Keep current card, only switch to back view.
						$current_vocab_id = $vocab_id;
						$current_deck_id  = $deck_id;
						$view_side        = 'back';
					} elseif ( 'answer' === $action ) {
						$rating = isset( $_POST['dnd_vocab_study_rating'] ) ? intval( $_POST['dnd_vocab_study_rating'] ) : 0;

						if ( function_exists( 'dnd_vocab_srs_apply_answer' ) ) {
							dnd_vocab_srs_apply_answer( $user_id, $vocab_id, $deck_id, $rating );
						}

						// After answering, we will pick next card below.
					}
				}
			}
		} else {
			$message = esc_html__( 'Không thể xử lý yêu cầu học từ. Vui lòng thử lại.', 'dnd-vocab' );
		}
	}

	// If not showing back side of an existing card, select next card (review or new).
	if ( ! $current_vocab_id || 'back' !== $view_side ) {
		$view_side        = 'front';
		$current_vocab_id = 0;
		$current_deck_id  = 0;

		// Refresh due items list (in case it changed after answering).
		if ( function_exists( 'dnd_vocab_srs_get_due_items' ) ) {
			$due_items = dnd_vocab_srs_get_due_items( $user_id, $user_decks );
			$due_items_count = is_array( $due_items ) ? count( $due_items ) : 0;
		} else {
			$due_items = array();
			$due_items_count = 0;
		}

		// Update review notice if count changed.
		if ( $due_items_count > 0 ) {
			$review_notice = sprintf(
				/* translators: %d: number of words to review */
				esc_html__( 'Bạn có %d từ cần ôn tập hôm nay, ôn tập ngay.', 'dnd-vocab' ),
				$due_items_count
			);
		} else {
			$review_notice = esc_html__( 'Bạn không có từ nào cần ôn tập hôm nay. Bạn có thể học từ mới.', 'dnd-vocab' );
		}

		if ( ! empty( $due_items ) ) {
			// Take the first due item.
			$current_vocab_id = (int) reset( $due_items );
			$current_deck_id  = (int) get_post_meta( $current_vocab_id, '_dnd_vocab_deck_id', true );
		}

		// Only allow picking new vocabulary item if there are no due items (x = 0).
		if ( ! $current_vocab_id && 0 === $due_items_count && function_exists( 'dnd_vocab_srs_pick_next_new_item' ) ) {
			$new_id = dnd_vocab_srs_pick_next_new_item( $user_id, $user_decks );

			if ( $new_id ) {
				$current_vocab_id = (int) $new_id;
				$current_deck_id  = (int) get_post_meta( $current_vocab_id, '_dnd_vocab_deck_id', true );
			}
		}
	}

	// No card to study.
	if ( ! $current_vocab_id || ! $current_deck_id ) {
		ob_start();
		?>
		<div class="dnd-vocab-study">
			<?php if ( $message ) : ?>
				<div class="dnd-vocab-study__notice">
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Hôm nay bạn đã học xong. Hiện không còn từ nào để ôn hoặc học mới.', 'dnd-vocab' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	// Load vocabulary fields for current card.
	$vocab_post = get_post( $current_vocab_id );

	if ( ! $vocab_post || 'dnd_vocab_item' !== $vocab_post->post_type || 'publish' !== $vocab_post->post_status ) {
		return '<p>' . esc_html__( 'Từ vựng không khả dụng để học.', 'dnd-vocab' ) . '</p>';
	}

	$word             = get_post_meta( $current_vocab_id, 'dnd_vocab_word', true );
	$ipa              = get_post_meta( $current_vocab_id, 'dnd_vocab_ipa', true );
	$definition       = get_post_meta( $current_vocab_id, 'dnd_vocab_definition', true );
	$example          = get_post_meta( $current_vocab_id, 'dnd_vocab_example', true );
	$image_url        = get_post_meta( $current_vocab_id, 'dnd_vocab_image', true );
	$word_sound       = get_post_meta( $current_vocab_id, 'dnd_vocab_word_sound', true );
	$definition_sound = get_post_meta( $current_vocab_id, 'dnd_vocab_definition_sound', true );
	$example_sound    = get_post_meta( $current_vocab_id, 'dnd_vocab_example_sound', true );
	$short_vi         = get_post_meta( $current_vocab_id, 'dnd_vocab_short_vietnamese', true );
	$full_vi          = get_post_meta( $current_vocab_id, 'dnd_vocab_full_vietnamese', true );
	$suggestion       = get_post_meta( $current_vocab_id, 'dnd_vocab_suggestion', true );

	if ( function_exists( 'dnd_vocab_strip_cloze' ) ) {
		$definition = dnd_vocab_strip_cloze( $definition );
		$example    = dnd_vocab_strip_cloze( $example );
	}

	$display_word = $word ? $word : get_the_title( $vocab_post );

	ob_start();
	?>
	<div class="dnd-vocab-study">
		<?php if ( $message ) : ?>
			<div class="dnd-vocab-study__notice">
				<?php echo esc_html( $message ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $review_notice ) : ?>
			<div class="dnd-vocab-study__review-notice">
				<p><?php echo esc_html( $review_notice ); ?></p>
				<?php if ( $due_items_count > 0 ) : ?>
					<p>
						<a href="<?php echo esc_url( remove_query_arg( array( 'dnd_vocab_study_action', 'dnd_vocab_study_vocab_id', 'dnd_vocab_study_deck_id' ) ) ); ?>" class="dnd-vocab-study__button dnd-vocab-study__button--review">
							<?php esc_html_e( 'Ôn tập ngay', 'dnd-vocab' ); ?>
						</a>
					</p>
				<?php elseif ( 0 === $due_items_count ) : ?>
					<p>
						<a href="<?php echo esc_url( remove_query_arg( array( 'dnd_vocab_study_action', 'dnd_vocab_study_vocab_id', 'dnd_vocab_study_deck_id' ) ) ); ?>" class="dnd-vocab-study__button dnd-vocab-study__button--new">
							<?php esc_html_e( 'Học từ mới', 'dnd-vocab' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dnd-vocab-study__card dnd-vocab-study__card--<?php echo 'back' === $view_side ? 'back' : 'front'; ?>">
			<div class="dnd-vocab-study__front">
				<h2 class="dnd-vocab-study__word">
					<?php echo esc_html( $display_word ); ?>
					<?php if ( $ipa ) : ?>
						<span class="dnd-vocab-study__ipa">/<?php echo esc_html( $ipa ); ?>/</span>
					<?php endif; ?>
				</h2>

				<?php if ( $suggestion ) : ?>
					<p class="dnd-vocab-study__suggestion">
						<strong><?php esc_html_e( 'Gợi ý:', 'dnd-vocab' ); ?></strong>
						<?php echo ' ' . esc_html( $suggestion ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( 'back' === $view_side ) : ?>
				<div class="dnd-vocab-study__back">
					<?php if ( $short_vi || $full_vi ) : ?>
						<div class="dnd-vocab-study__vietnamese">
							<?php if ( $short_vi ) : ?>
								<p><strong><?php esc_html_e( 'Nghĩa ngắn:', 'dnd-vocab' ); ?></strong> <?php echo esc_html( $short_vi ); ?></p>
							<?php endif; ?>
							<?php if ( $full_vi ) : ?>
								<p><strong><?php esc_html_e( 'Nghĩa đầy đủ:', 'dnd-vocab' ); ?></strong> <?php echo esc_html( $full_vi ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $definition ) : ?>
						<div class="dnd-vocab-study__definition">
							<h3><?php esc_html_e( 'Định nghĩa', 'dnd-vocab' ); ?></h3>
							<p><?php echo wp_kses_post( nl2br( esc_html( $definition ) ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $example ) : ?>
						<div class="dnd-vocab-study__example">
							<h3><?php esc_html_e( 'Ví dụ', 'dnd-vocab' ); ?></h3>
							<p><?php echo wp_kses_post( nl2br( esc_html( $example ) ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $image_url ) : ?>
						<div class="dnd-vocab-study__image">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $display_word ); ?>">
						</div>
					<?php endif; ?>

					<?php if ( $word_sound || $definition_sound || $example_sound ) : ?>
						<div class="dnd-vocab-study__audio">
							<?php if ( $word_sound ) : ?>
								<p>
									<strong><?php esc_html_e( 'Phát âm từ:', 'dnd-vocab' ); ?></strong><br>
									<audio controls src="<?php echo esc_url( $word_sound ); ?>"></audio>
								</p>
							<?php endif; ?>

							<?php if ( $definition_sound ) : ?>
								<p>
									<strong><?php esc_html_e( 'Phát âm định nghĩa:', 'dnd-vocab' ); ?></strong><br>
									<audio controls src="<?php echo esc_url( $definition_sound ); ?>"></audio>
								</p>
							<?php endif; ?>

							<?php if ( $example_sound ) : ?>
								<p>
									<strong><?php esc_html_e( 'Phát âm ví dụ:', 'dnd-vocab' ); ?></strong><br>
									<audio controls src="<?php echo esc_url( $example_sound ); ?>"></audio>
								</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="dnd-vocab-study__controls">
			<?php if ( 'front' === $view_side ) : ?>
				<form method="post" class="dnd-vocab-study__form dnd-vocab-study__form--show-back">
					<?php wp_nonce_field( 'dnd_vocab_study', 'dnd_vocab_study_nonce' ); ?>
					<input type="hidden" name="dnd_vocab_study_action" value="show_back">
					<input type="hidden" name="dnd_vocab_study_vocab_id" value="<?php echo esc_attr( $current_vocab_id ); ?>">
					<input type="hidden" name="dnd_vocab_study_deck_id" value="<?php echo esc_attr( $current_deck_id ); ?>">
					<button type="submit" class="dnd-vocab-study__button dnd-vocab-study__button--show-back">
						<?php esc_html_e( 'Hiện mặt sau', 'dnd-vocab' ); ?>
					</button>
				</form>
			<?php else : ?>
				<form method="post" class="dnd-vocab-study__form dnd-vocab-study__form--answer">
					<?php wp_nonce_field( 'dnd_vocab_study', 'dnd_vocab_study_nonce' ); ?>
					<input type="hidden" name="dnd_vocab_study_action" value="answer">
					<input type="hidden" name="dnd_vocab_study_vocab_id" value="<?php echo esc_attr( $current_vocab_id ); ?>">
					<input type="hidden" name="dnd_vocab_study_deck_id" value="<?php echo esc_attr( $current_deck_id ); ?>">

					<div class="dnd-vocab-study__answer-buttons">
						<button type="submit" name="dnd_vocab_study_rating" value="0" class="dnd-vocab-study__button dnd-vocab-study__button--again">
							<?php esc_html_e( 'Chưa thuộc', 'dnd-vocab' ); ?>
						</button>
						<button type="submit" name="dnd_vocab_study_rating" value="3" class="dnd-vocab-study__button dnd-vocab-study__button--hard">
							<?php esc_html_e( 'Chưa chắc chắn', 'dnd-vocab' ); ?>
						</button>
						<button type="submit" name="dnd_vocab_study_rating" value="5" class="dnd-vocab-study__button dnd-vocab-study__button--easy">
							<?php esc_html_e( 'Đã thuộc', 'dnd-vocab' ); ?>
						</button>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
add_shortcode( 'dnd_vocab_study', 'dnd_vocab_render_study_shortcode' );

/**
 * Review heatmap shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function dnd_vocab_render_review_heatmap_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(),
		$atts,
		'dnd_vocab_review_heatmap'
	);

	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'Hãy đăng nhập để xem lịch sử ôn tập của bạn.', 'dnd-vocab' ) . '</p>';
	}

	$user_id = get_current_user_id();

	// Enqueue assets.
	dnd_vocab_enqueue_heatmap_assets();

	// Get heatmap data.
	$heatmap_data = array();
	$streak       = 0;

	if ( function_exists( 'dnd_vocab_get_heatmap_data' ) ) {
		$heatmap_data = dnd_vocab_get_heatmap_data( $user_id );
	}

	if ( function_exists( 'dnd_vocab_calculate_streak' ) ) {
		$streak = dnd_vocab_calculate_streak( $user_id );
	}

	ob_start();
	?>
	<div class="dnd-vocab-heatmap" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<div class="dnd-vocab-heatmap__header">
			<h2 class="dnd-vocab-heatmap__title"><?php esc_html_e( 'Lịch sử ôn tập', 'dnd-vocab' ); ?></h2>
			<?php if ( $streak > 0 ) : ?>
				<div class="dnd-vocab-heatmap__streak">
					<span class="dnd-vocab-heatmap__streak-label"><?php esc_html_e( 'Chuỗi ngày:', 'dnd-vocab' ); ?></span>
					<span class="dnd-vocab-heatmap__streak-value"><?php echo esc_html( $streak ); ?> <?php esc_html_e( 'ngày', 'dnd-vocab' ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<div class="dnd-vocab-heatmap__legend">
			<span class="dnd-vocab-heatmap__legend-label"><?php esc_html_e( 'Ít hơn', 'dnd-vocab' ); ?></span>
			<div class="dnd-vocab-heatmap__legend-colors">
				<div class="dnd-vocab-heatmap__legend-item dnd-vocab-heatmap__legend-item--0"></div>
				<div class="dnd-vocab-heatmap__legend-item dnd-vocab-heatmap__legend-item--1"></div>
				<div class="dnd-vocab-heatmap__legend-item dnd-vocab-heatmap__legend-item--2"></div>
				<div class="dnd-vocab-heatmap__legend-item dnd-vocab-heatmap__legend-item--3"></div>
				<div class="dnd-vocab-heatmap__legend-item dnd-vocab-heatmap__legend-item--4"></div>
			</div>
			<span class="dnd-vocab-heatmap__legend-label"><?php esc_html_e( 'Nhiều hơn', 'dnd-vocab' ); ?></span>
		</div>

		<div class="dnd-vocab-heatmap__container">
			<div class="dnd-vocab-heatmap__grid">
				<?php
				$now   = current_time( 'timestamp' );
				$today = wp_date( 'Y-m-d', $now );
				$start_date = wp_date( 'Y-m-d', $now - ( 365 * DAY_IN_SECONDS ) );
				$end_date   = wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) );

				$current_date = $start_date;
				$week_start = true;
				$week_count = 0;

				while ( $current_date <= $end_date ) {
					if ( $week_start ) {
						// Show week label.
						$week_timestamp = strtotime( $current_date );
						$week_label = wp_date( 'M j', $week_timestamp );
						?>
						<div class="dnd-vocab-heatmap__week-label"><?php echo esc_html( $week_label ); ?></div>
						<?php
						$week_start = false;
					}

					$count = 0;
					$is_today = ( $current_date === $today );
					$is_past = ( $current_date < $today );
					$is_future = ( $current_date > $today );

					if ( isset( $heatmap_data[ $current_date ] ) ) {
						$count = (int) $heatmap_data[ $current_date ]['total'];
					}

					// Determine intensity level (0-4).
					$intensity = 0;
					if ( $count > 0 ) {
						if ( $count <= 2 ) {
							$intensity = 1;
						} elseif ( $count <= 4 ) {
							$intensity = 2;
						} elseif ( $count <= 6 ) {
							$intensity = 3;
						} else {
							$intensity = 4;
						}
					}

					$classes = array( 'dnd-vocab-heatmap__day' );
					$classes[] = 'dnd-vocab-heatmap__day--' . $intensity;

					if ( $is_today ) {
						$classes[] = 'dnd-vocab-heatmap__day--today';
					}
					if ( $is_future ) {
						$classes[] = 'dnd-vocab-heatmap__day--future';
					}

					$date_label = wp_date( 'M j, Y', strtotime( $current_date ) );
					?>
					<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
						 data-date="<?php echo esc_attr( $current_date ); ?>"
						 data-count="<?php echo esc_attr( $count ); ?>"
						 title="<?php echo esc_attr( $date_label . ': ' . $count . ' ' . __( 'cards', 'dnd-vocab' ) ); ?>">
					</div>
					<?php

					$week_count++;
					if ( $week_count >= 7 ) {
						$week_count = 0;
						$week_start = true;
					}

				// Increment date safely.
				$date_obj = date_create( $current_date );
				if ( $date_obj ) {
					$date_obj->modify( '+1 day' );
					$current_date = $date_obj->format( 'Y-m-d' );
				} else {
					// Fallback.
					$current_timestamp = strtotime( $current_date . ' +1 day' );
					$current_date = wp_date( 'Y-m-d', $current_timestamp );
				}
				}
				?>
			</div>
		</div>
	</div>

	<!-- Modal for showing cards by date -->
	<div class="dnd-vocab-heatmap__modal" id="dnd-vocab-heatmap-modal">
		<div class="dnd-vocab-heatmap__modal-overlay"></div>
		<div class="dnd-vocab-heatmap__modal-content">
			<div class="dnd-vocab-heatmap__modal-header">
				<h3 class="dnd-vocab-heatmap__modal-title"></h3>
				<button class="dnd-vocab-heatmap__modal-close" aria-label="<?php esc_attr_e( 'Đóng', 'dnd-vocab' ); ?>">&times;</button>
			</div>
			<div class="dnd-vocab-heatmap__modal-body">
				<div class="dnd-vocab-heatmap__modal-loading">
					<?php esc_html_e( 'Đang tải...', 'dnd-vocab' ); ?>
				</div>
				<div class="dnd-vocab-heatmap__modal-content-inner" style="display: none;">
					<div class="dnd-vocab-heatmap__modal-section">
						<h4 class="dnd-vocab-heatmap__modal-section-title"><?php esc_html_e( 'Đã ôn tập', 'dnd-vocab' ); ?></h4>
						<ul class="dnd-vocab-heatmap__modal-list dnd-vocab-heatmap__modal-list--reviewed"></ul>
					</div>
					<div class="dnd-vocab-heatmap__modal-section">
						<h4 class="dnd-vocab-heatmap__modal-section-title"><?php esc_html_e( 'Sắp đến hạn', 'dnd-vocab' ); ?></h4>
						<ul class="dnd-vocab-heatmap__modal-list dnd-vocab-heatmap__modal-list--due"></ul>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
add_shortcode( 'dnd_vocab_review_heatmap', 'dnd_vocab_render_review_heatmap_shortcode' );

/**
 * Enqueue heatmap assets (CSS and JS).
 */
function dnd_vocab_enqueue_heatmap_assets() {
	$plugin_url = DND_VOCAB_PLUGIN_URL;
	$version     = DND_VOCAB_VERSION;

	wp_enqueue_style(
		'dnd-vocab-heatmap',
		$plugin_url . 'assets/css/review-heatmap.css',
		array(),
		$version
	);

	wp_enqueue_script(
		'dnd-vocab-heatmap',
		$plugin_url . 'assets/js/review-heatmap.js',
		array( 'jquery' ),
		$version,
		true
	);

	wp_localize_script(
		'dnd-vocab-heatmap',
		'dndVocabHeatmap',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dnd_vocab_heatmap_ajax' ),
			'i18n'    => array(
				'noCards'     => __( 'Không có cards nào', 'dnd-vocab' ),
				'loading'     => __( 'Đang tải...', 'dnd-vocab' ),
				'error'       => __( 'Có lỗi xảy ra', 'dnd-vocab' ),
				'cards'       => __( 'cards', 'dnd-vocab' ),
			),
		)
	);
}

/**
 * AJAX handler for getting cards by date.
 */
function dnd_vocab_ajax_get_cards_by_date() {
	check_ajax_referer( 'dnd_vocab_heatmap_ajax', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Bạn cần đăng nhập', 'dnd-vocab' ) ) );
	}

	$user_id = get_current_user_id();
	$date    = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

	if ( empty( $date ) ) {
		wp_send_json_error( array( 'message' => __( 'Ngày không hợp lệ', 'dnd-vocab' ) ) );
	}

	if ( ! function_exists( 'dnd_vocab_get_cards_by_date' ) ) {
		wp_send_json_error( array( 'message' => __( 'Function không tồn tại', 'dnd-vocab' ) ) );
	}

	$cards = dnd_vocab_get_cards_by_date( $user_id, $date );

	wp_send_json_success( $cards );
}
add_action( 'wp_ajax_dnd_vocab_get_cards_by_date', 'dnd_vocab_ajax_get_cards_by_date' );

