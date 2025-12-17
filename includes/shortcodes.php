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


