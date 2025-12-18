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

	// Prepare next review texts for current card for each rating option, if available.
	$next_review_options = array();

	if ( function_exists( 'dnd_vocab_srs_predict_next_reviews' ) && function_exists( 'dnd_vocab_human_readable_next_review' ) ) {
		$predictions = dnd_vocab_srs_predict_next_reviews( $user_id, $current_vocab_id, $current_deck_id );

		if ( is_array( $predictions ) ) {
			foreach ( $predictions as $rating => $due_timestamp ) {
				$text = dnd_vocab_human_readable_next_review( (int) $due_timestamp );

				if ( $text ) {
					$next_review_options[ (int) $rating ] = $text;
				}
			}
		}
	}

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
				<form method="post" class="dnd-vocab-study__form dnd-vocab_study__form--answer">
					<?php wp_nonce_field( 'dnd_vocab_study', 'dnd_vocab_study_nonce' ); ?>
					<input type="hidden" name="dnd_vocab_study_action" value="answer">
					<input type="hidden" name="dnd_vocab_study_vocab_id" value="<?php echo esc_attr( $current_vocab_id ); ?>">
					<input type="hidden" name="dnd_vocab_study_deck_id" value="<?php echo esc_attr( $current_deck_id ); ?>">

					<div class="dnd-vocab-study__answer-buttons" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
						<span class="dnd-vocab-study__answer-option dnd-vocab-study__answer-option--again" style="display:flex;flex-direction:column;align-items:center;">
							<button type="submit" name="dnd_vocab_study_rating" value="0" class="dnd-vocab-study__button dnd-vocab-study__button--again">
								<?php esc_html_e( '❌ QUÊN', 'dnd-vocab' ); ?>
							</button>
							<?php if ( ! empty( $next_review_options[0] ) ) : ?>
								<div class="dnd-vocab-study__next-review dnd-vocab-study__next-review--again">
									<?php echo esc_html( $next_review_options[0] ); ?>
								</div>
							<?php endif; ?>
						</span>

						<span class="dnd-vocab-study__answer-option dnd-vocab-study__answer-option--hard" style="display:flex;flex-direction:column;align-items:center;">
							<button type="submit" name="dnd_vocab_study_rating" value="3" class="dnd-vocab-study__button dnd-vocab-study__button--hard">
								<?php esc_html_e( '🤔 MƠ HỒ', 'dnd-vocab' ); ?>
							</button>
							<?php if ( ! empty( $next_review_options[3] ) ) : ?>
								<div class="dnd-vocab-study__next-review dnd-vocab-study__next-review--hard">
									<?php echo esc_html( $next_review_options[3] ); ?>
								</div>
							<?php endif; ?>
						</span>

						<span class="dnd-vocab-study__answer-option dnd-vocab-study__answer-option--easy" style="display:flex;flex-direction:column;align-items:center;">
							<button type="submit" name="dnd_vocab_study_rating" value="5" class="dnd-vocab-study__button dnd-vocab-study__button--easy">
								<?php esc_html_e( '✅ NHỚ', 'dnd-vocab' ); ?>
							</button>
							<?php if ( ! empty( $next_review_options[5] ) ) : ?>
								<div class="dnd-vocab-study__next-review dnd-vocab-study__next-review--easy">
									<?php echo esc_html( $next_review_options[5] ); ?>
								</div>
							<?php endif; ?>
						</span>
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

	// Get selected year from URL parameter or default to current year.
	$now = current_time( 'timestamp' );
	$current_year = (int) wp_date( 'Y', $now );
	$selected_year = isset( $_GET['heatmap_year'] ) ? (int) $_GET['heatmap_year'] : $current_year;
	
	// Validate year range.
	if ( $selected_year < 2000 || $selected_year > 2100 ) {
		$selected_year = $current_year;
	}

	// Get today's statistics for title.
	$today_stats = array( 'cards' => 0, 'seconds' => 0 );
	if ( function_exists( 'dnd_vocab_get_today_stats' ) ) {
		$today_stats = dnd_vocab_get_today_stats( $user_id );
	}

	// Get year-specific heatmap data.
	$heatmap_data = array();
	if ( function_exists( 'dnd_vocab_get_heatmap_data_for_year' ) ) {
		$heatmap_data = dnd_vocab_get_heatmap_data_for_year( $user_id, $selected_year );
	}

	// Get year-specific statistics.
	$year_stats = array(
		'daily_average'        => 0,
		'days_learned_percent' => 0,
		'longest_streak'        => 0,
		'current_streak'        => 0,
	);
	if ( function_exists( 'dnd_vocab_get_year_stats' ) ) {
		$year_stats = dnd_vocab_get_year_stats( $user_id, $selected_year );
	}

	$today = wp_date( 'Y-m-d', $now );
	$today_year = (int) wp_date( 'Y', $now );

	// Calculate year boundaries for calendar.
	$year_start = $selected_year . '-01-01';
	$year_end = $selected_year . '-12-31';
	
	// Get first day of year and its day of week (0 = Sunday, 6 = Saturday).
	$first_day_timestamp = strtotime( $year_start );
	$first_day_of_week = (int) wp_date( 'w', $first_day_timestamp );
	
	// Start calendar from the Sunday of the week containing Jan 1.
	$calendar_start_timestamp = $first_day_timestamp - ( $first_day_of_week * DAY_IN_SECONDS );
	$calendar_start = wp_date( 'Y-m-d', $calendar_start_timestamp );
	
	// Calculate end date (53 weeks later).
	$calendar_end_timestamp = $calendar_start_timestamp + ( 53 * 7 * DAY_IN_SECONDS ) - DAY_IN_SECONDS;
	$calendar_end = wp_date( 'Y-m-d', $calendar_end_timestamp );

	ob_start();
	?>
	<div class="dnd-vocab-heatmap" data-user-id="<?php echo esc_attr( $user_id ); ?>" data-year="<?php echo esc_attr( $selected_year ); ?>">
		<div class="dnd-vocab-heatmap__header">
			<h2 class="dnd-vocab-heatmap__title">
				<?php
				$cards_text = sprintf(
					_n( '%d card', '%d cards', $today_stats['cards'], 'dnd-vocab' ),
					$today_stats['cards']
				);
				$seconds_text = sprintf(
					_n( '%d second', '%d seconds', $today_stats['seconds'], 'dnd-vocab' ),
					$today_stats['seconds']
				);
				printf(
					esc_html__( 'Studied %1$s in %2$s today.', 'dnd-vocab' ),
					esc_html( $cards_text ),
					esc_html( $seconds_text )
				);
				?>
			</h2>
			<div class="dnd-vocab-heatmap__navigation">
				<button class="dnd-vocab-heatmap__nav-btn dnd-vocab-heatmap__nav-btn--prev" data-year="<?php echo esc_attr( $selected_year - 1 ); ?>" aria-label="<?php esc_attr_e( 'Previous year', 'dnd-vocab' ); ?>">&lt;</button>
				<button class="dnd-vocab-heatmap__nav-btn dnd-vocab-heatmap__nav-btn--today" data-year="<?php echo esc_attr( $current_year ); ?>" aria-label="<?php esc_attr_e( 'Today', 'dnd-vocab' ); ?>">T</button>
				<button class="dnd-vocab-heatmap__nav-btn dnd-vocab-heatmap__nav-btn--next" data-year="<?php echo esc_attr( $selected_year + 1 ); ?>" aria-label="<?php esc_attr_e( 'Next year', 'dnd-vocab' ); ?>" <?php echo ( $selected_year >= $current_year ) ? 'disabled' : ''; ?>>&gt;</button>
			</div>
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
				$current_date = $calendar_start;
				$total_days   = 0;

				// Vẽ hình chữ nhật 7 (hàng) x 53 (tuần) các ô liền nhau.
				while ( $current_date <= $calendar_end && $total_days < ( 53 * 7 ) ) {
					$count      = 0;
					$is_today   = ( $current_date === $today );
					$is_in_year = ( $current_date >= $year_start && $current_date <= $year_end );
					$is_future  = ( $current_date > $today );

					if ( $is_in_year && isset( $heatmap_data[ $current_date ] ) ) {
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

					$classes   = array( 'dnd-vocab-heatmap__day' );
					$classes[] = 'dnd-vocab-heatmap__day--' . $intensity;

					if ( $is_today ) {
						$classes[] = 'dnd-vocab-heatmap__day--today';
					}
					if ( $is_future ) {
						$classes[] = 'dnd-vocab-heatmap__day--future';
					}

					// Format date for tooltip.
					$date_timestamp = strtotime( $current_date );
					$day_name       = wp_date( 'l', $date_timestamp );
					$month_name     = wp_date( 'F', $date_timestamp );
					$day_number     = wp_date( 'j', $date_timestamp );
					$date_year      = wp_date( 'Y', $date_timestamp );
					$tooltip_text   = sprintf(
						esc_html__( '%1$d reviews on %2$s %3$s %4$d, %5$s', 'dnd-vocab' ),
						$count,
						$day_name,
						$month_name,
						$day_number,
						$date_year
					);
					?>
					<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
						 data-date="<?php echo esc_attr( $current_date ); ?>"
						 data-count="<?php echo esc_attr( $count ); ?>"
						 title="<?php echo esc_attr( $tooltip_text ); ?>">
					</div>
					<?php

					$total_days++;

					// Increment date safely.
					$date_obj = date_create( $current_date );
					if ( $date_obj ) {
						$date_obj->modify( '+1 day' );
						$current_date = $date_obj->format( 'Y-m-d' );
					} else {
						// Fallback.
						$current_timestamp = strtotime( $current_date . ' +1 day' );
						$current_date      = wp_date( 'Y-m-d', $current_timestamp );
					}
				}
				?>
			</div>
		</div>

		<div class="dnd-vocab-heatmap__year-label"><?php echo esc_html( $selected_year ); ?></div>

		<div class="dnd-vocab-heatmap__stats">
			<div class="dnd-vocab-heatmap__stat">
				<span class="dnd-vocab-heatmap__stat-label"><?php esc_html_e( 'Daily average:', 'dnd-vocab' ); ?></span>
				<span class="dnd-vocab-heatmap__stat-value dnd-vocab-heatmap__stat-value--green"><?php echo esc_html( $year_stats['daily_average'] ); ?> <?php esc_html_e( 'reviews', 'dnd-vocab' ); ?></span>
			</div>
			<div class="dnd-vocab-heatmap__stat">
				<span class="dnd-vocab-heatmap__stat-label"><?php esc_html_e( 'Days learned:', 'dnd-vocab' ); ?></span>
				<span class="dnd-vocab-heatmap__stat-value dnd-vocab-heatmap__stat-value--yellow"><?php echo esc_html( $year_stats['days_learned_percent'] ); ?>%</span>
			</div>
			<div class="dnd-vocab-heatmap__stat">
				<span class="dnd-vocab-heatmap__stat-label"><?php esc_html_e( 'Longest streak:', 'dnd-vocab' ); ?></span>
				<span class="dnd-vocab-heatmap__stat-value dnd-vocab-heatmap__stat-value--green"><?php echo esc_html( $year_stats['longest_streak'] ); ?> <?php esc_html_e( 'days', 'dnd-vocab' ); ?></span>
			</div>
			<div class="dnd-vocab-heatmap__stat">
				<span class="dnd-vocab-heatmap__stat-label"><?php esc_html_e( 'Current streak:', 'dnd-vocab' ); ?></span>
				<span class="dnd-vocab-heatmap__stat-value dnd-vocab-heatmap__stat-value--green"><?php echo esc_html( $year_stats['current_streak'] ); ?> <?php esc_html_e( 'days', 'dnd-vocab' ); ?></span>
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

/**
 * AJAX handler for getting year heatmap data.
 */
function dnd_vocab_ajax_get_year_heatmap() {
	check_ajax_referer( 'dnd_vocab_heatmap_ajax', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Bạn cần đăng nhập', 'dnd-vocab' ) ) );
	}

	$user_id = get_current_user_id();
	$year    = isset( $_POST['year'] ) ? (int) $_POST['year'] : 0;

	if ( $year < 2000 || $year > 2100 ) {
		wp_send_json_error( array( 'message' => __( 'Năm không hợp lệ', 'dnd-vocab' ) ) );
	}

	// Get heatmap data for the year.
	$heatmap_data = array();
	if ( function_exists( 'dnd_vocab_get_heatmap_data_for_year' ) ) {
		$heatmap_data = dnd_vocab_get_heatmap_data_for_year( $user_id, $year );
	}

	// Get year statistics.
	$year_stats = array(
		'daily_average'        => 0,
		'days_learned_percent' => 0,
		'longest_streak'        => 0,
		'current_streak'        => 0,
	);
	if ( function_exists( 'dnd_vocab_get_year_stats' ) ) {
		$year_stats = dnd_vocab_get_year_stats( $user_id, $year );
	}

	wp_send_json_success( array(
		'heatmap_data' => $heatmap_data,
		'stats'        => $year_stats,
	) );
}
add_action( 'wp_ajax_dnd_vocab_get_year_heatmap', 'dnd_vocab_ajax_get_year_heatmap' );

