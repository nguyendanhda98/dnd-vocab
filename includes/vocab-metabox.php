<?php
/**
 * Meta box for Vocabulary Items
 *
 * @package DND_Vocab
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register meta box for vocabulary item fields.
 */
function dnd_vocab_register_vocab_metaboxes() {
    add_meta_box(
        'dnd_vocab_item_details',
        __( 'Vocabulary Details', 'dnd-vocab' ),
        'dnd_vocab_vocab_metabox_callback',
        'dnd_vocab_item',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'dnd_vocab_register_vocab_metaboxes' );

/**
 * Render the vocabulary item meta box.
 *
 * @param WP_Post $post Current post object.
 */
function dnd_vocab_vocab_metabox_callback( $post ) {
    // Add nonce for security.
    wp_nonce_field( 'dnd_vocab_save_vocab_item', 'dnd_vocab_vocab_nonce' );

    // Deck ID can be pre-filled from query string when creating from a deck page.
    $deck_id_query = isset( $_GET['deck_id'] ) ? absint( $_GET['deck_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $fields = array(
        'word'             => get_post_meta( $post->ID, 'dnd_vocab_word', true ),
        'ipa'              => get_post_meta( $post->ID, 'dnd_vocab_ipa', true ),
        'definition'       => get_post_meta( $post->ID, 'dnd_vocab_definition', true ),
        'example'          => get_post_meta( $post->ID, 'dnd_vocab_example', true ),
        'image'            => get_post_meta( $post->ID, 'dnd_vocab_image', true ),
        'wordSound'        => get_post_meta( $post->ID, 'dnd_vocab_word_sound', true ),
        'definitionSound'  => get_post_meta( $post->ID, 'dnd_vocab_definition_sound', true ),
        'exampleSound'     => get_post_meta( $post->ID, 'dnd_vocab_example_sound', true ),
        'shortVietnamese'  => get_post_meta( $post->ID, 'dnd_vocab_short_vietnamese', true ),
        'fullVietnamese'   => get_post_meta( $post->ID, 'dnd_vocab_full_vietnamese', true ),
        'suggestion'       => get_post_meta( $post->ID, 'dnd_vocab_suggestion', true ),
        '_deck_id'         => get_post_meta( $post->ID, '_dnd_vocab_deck_id', true ),
    );

    if ( empty( $fields['_deck_id'] ) && $deck_id_query ) {
        $fields['_deck_id'] = $deck_id_query;
    }

    // Resolve a valid deck to be used for the Back button.
    $deck_id  = 0;
    $deck     = null;
    $back_url = '';

    if ( ! empty( $fields['_deck_id'] ) ) {
        $deck_id = absint( $fields['_deck_id'] );

        if ( $deck_id > 0 ) {
            $deck = get_post( $deck_id );

            if ( $deck && 'dnd_deck' === $deck->post_type ) {
                $back_url = get_edit_post_link( $deck_id );

                if ( ! $back_url ) {
                    $back_url = add_query_arg(
                        array(
                            'post'   => $deck_id,
                            'action' => 'edit',
                        ),
                        admin_url( 'post.php' )
                    );
                }
            } else {
                $deck_id  = 0;
                $back_url = '';
            }
        }
    }

    ?>
    <?php if ( $deck_id && $back_url ) : ?>
        <p class="dnd-vocab-back-to-deck">
            <a href="<?php echo esc_url( $back_url ); ?>" class="button">
                <?php esc_html_e( 'Back to Deck', 'dnd-vocab' ); ?>
            </a>
            <span class="dnd-vocab-back-to-deck__label">
                <?php
                printf(
                    /* translators: %s: deck title */
                    esc_html__( 'Current deck: %s', 'dnd-vocab' ),
                    esc_html( get_the_title( $deck_id ) )
                );
                ?>
            </span>
        </p>
    <?php endif; ?>

    <table class="form-table dnd-vocab-item-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="dnd_vocab_word"><?php esc_html_e( 'Word', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="text" id="dnd_vocab_word" name="dnd_vocab_word" class="regular-text"
                        value="<?php echo esc_attr( $fields['word'] ); ?>" required>
                    <p class="description"><?php esc_html_e( 'English vocabulary word.', 'dnd-vocab' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_ipa"><?php esc_html_e( 'IPA', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="text" id="dnd_vocab_ipa" name="dnd_vocab_ipa" class="regular-text"
                        value="<?php echo esc_attr( $fields['ipa'] ); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_definition"><?php esc_html_e( 'Definition', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <textarea id="dnd_vocab_definition" name="dnd_vocab_definition" rows="3" class="large-text"><?php echo esc_textarea( $fields['definition'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_example"><?php esc_html_e( 'Example', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <textarea id="dnd_vocab_example" name="dnd_vocab_example" rows="3" class="large-text"><?php echo esc_textarea( $fields['example'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_image"><?php esc_html_e( 'Image URL', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="url" id="dnd_vocab_image" name="dnd_vocab_image" class="regular-text"
                        value="<?php echo esc_attr( $fields['image'] ); ?>">
                    <p class="description"><?php esc_html_e( 'URL to image illustrating the word.', 'dnd-vocab' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_word_sound"><?php esc_html_e( 'Word Sound URL', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="url" id="dnd_vocab_word_sound" name="dnd_vocab_word_sound" class="regular-text"
                        value="<?php echo esc_attr( $fields['wordSound'] ); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_definition_sound"><?php esc_html_e( 'Definition Sound URL', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="url" id="dnd_vocab_definition_sound" name="dnd_vocab_definition_sound" class="regular-text"
                        value="<?php echo esc_attr( $fields['definitionSound'] ); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_example_sound"><?php esc_html_e( 'Example Sound URL', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="url" id="dnd_vocab_example_sound" name="dnd_vocab_example_sound" class="regular-text"
                        value="<?php echo esc_attr( $fields['exampleSound'] ); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_short_vietnamese"><?php esc_html_e( 'Short Vietnamese', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <textarea id="dnd_vocab_short_vietnamese" name="dnd_vocab_short_vietnamese" rows="2" class="large-text"><?php echo esc_textarea( $fields['shortVietnamese'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_full_vietnamese"><?php esc_html_e( 'Full Vietnamese', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <textarea id="dnd_vocab_full_vietnamese" name="dnd_vocab_full_vietnamese" rows="3" class="large-text"><?php echo esc_textarea( $fields['fullVietnamese'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="dnd_vocab_suggestion"><?php esc_html_e( 'Suggestion', 'dnd-vocab' ); ?></label>
                </th>
                <td>
                    <input type="text" id="dnd_vocab_suggestion" name="dnd_vocab_suggestion" class="regular-text"
                        value="<?php echo esc_attr( $fields['suggestion'] ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'Hint representation of the word (can be auto-generated in future).', 'dnd-vocab' ); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php
    ?>
    <input type="hidden" name="dnd_vocab_deck_id" value="<?php echo esc_attr( $fields['_deck_id'] ); ?>">
    <?php
}

/**
 * Save vocabulary item meta.
 *
 * @param int $post_id Post ID.
 */
function dnd_vocab_save_vocab_item_meta( $post_id ) {
    // Check nonce.
    if ( ! isset( $_POST['dnd_vocab_vocab_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_vocab_nonce'] ) ), 'dnd_vocab_save_vocab_item' ) ) {
        return;
    }

    // Autosave?
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Correct post type?
    if ( 'dnd_vocab_item' !== get_post_type( $post_id ) ) {
        return;
    }

    // Check permission.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $map_text = array(
        'dnd_vocab_word',
        'dnd_vocab_ipa',
        'dnd_vocab_suggestion',
    );

    foreach ( $map_text as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta(
                $post_id,
                $key,
                sanitize_text_field( wp_unslash( $_POST[ $key ] ) )
            );
        }
    }

    $map_textarea = array(
        'dnd_vocab_definition',
        'dnd_vocab_example',
        'dnd_vocab_short_vietnamese',
        'dnd_vocab_full_vietnamese',
    );

    foreach ( $map_textarea as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta(
                $post_id,
                $key,
                sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) )
            );
        }
    }

    $map_url = array(
        'dnd_vocab_image',
        'dnd_vocab_word_sound',
        'dnd_vocab_definition_sound',
        'dnd_vocab_example_sound',
    );

    foreach ( $map_url as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta(
                $post_id,
                $key,
                esc_url_raw( wp_unslash( $_POST[ $key ] ) )
            );
        }
    }

    // Save deck relation.
    if ( isset( $_POST['dnd_vocab_deck_id'] ) ) {
        $deck_id = absint( $_POST['dnd_vocab_deck_id'] );
        if ( $deck_id > 0 ) {
            update_post_meta( $post_id, '_dnd_vocab_deck_id', $deck_id );
        }
    }
}
add_action( 'save_post', 'dnd_vocab_save_vocab_item_meta' );


