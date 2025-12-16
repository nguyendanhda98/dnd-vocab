<?php
/**
 * Custom Metabox for Deck Tags
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the custom metabox for tags on Deck edit screen
 */
function dnd_vocab_register_deck_metaboxes() {
    add_meta_box(
        'dnd_vocab_tags_metabox',
        __( 'Tags', 'dnd-vocab' ),
        'dnd_vocab_tags_metabox_callback',
        'dnd_deck',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'dnd_vocab_register_deck_metaboxes' );

/**
 * Remove the default taxonomy metabox and use our custom one
 */
function dnd_vocab_remove_default_tag_metabox() {
    remove_meta_box( 'tagsdiv-dnd_tag', 'dnd_deck', 'side' );
}
add_action( 'add_meta_boxes', 'dnd_vocab_remove_default_tag_metabox', 11 );

/**
 * Render the custom tags metabox
 *
 * @param WP_Post $post The current post object.
 */
function dnd_vocab_tags_metabox_callback( $post ) {
    // Add nonce for security
    wp_nonce_field( 'dnd_vocab_save_tags', 'dnd_vocab_tags_nonce' );

    // Get all available tags
    $all_tags = dnd_vocab_get_tags();

    // Get tags assigned to this deck
    $deck_tags = wp_get_object_terms( $post->ID, 'dnd_tag', array( 'fields' => 'ids' ) );
    if ( is_wp_error( $deck_tags ) ) {
        $deck_tags = array();
    }

    ?>
    <div class="dnd-vocab-tags-wrapper">
        <?php if ( ! empty( $all_tags ) && ! is_wp_error( $all_tags ) ) : ?>
            <div class="dnd-vocab-tags-checklist">
                <?php foreach ( $all_tags as $tag ) : ?>
                    <label class="dnd-vocab-tag-item">
                        <input type="checkbox" 
                               name="dnd_vocab_tags[]" 
                               value="<?php echo esc_attr( $tag->term_id ); ?>"
                               <?php checked( in_array( $tag->term_id, $deck_tags, true ) ); ?>>
                        <span><?php echo esc_html( $tag->name ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="dnd-vocab-no-tags">
                <?php esc_html_e( 'No tags available.', 'dnd-vocab' ); ?>
            </p>
        <?php endif; ?>

        <p class="dnd-vocab-tags-help">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag' ) ); ?>" target="_blank">
                <?php esc_html_e( 'Manage Tags', 'dnd-vocab' ); ?>
            </a>
        </p>
    </div>

    <style>
        .dnd-vocab-tags-wrapper {
            max-height: 200px;
            overflow-y: auto;
            padding: 5px 0;
        }
        .dnd-vocab-tags-checklist {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .dnd-vocab-tag-item {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            padding: 4px 0;
        }
        .dnd-vocab-tag-item:hover {
            background-color: #f0f0f0;
        }
        .dnd-vocab-tag-item input[type="checkbox"] {
            margin: 0;
        }
        .dnd-vocab-no-tags {
            color: #666;
            font-style: italic;
            margin: 0;
        }
        .dnd-vocab-tags-help {
            margin: 10px 0 0;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .dnd-vocab-tags-help a {
            text-decoration: none;
        }
    </style>
    <?php
}

/**
 * Save the deck tags when post is saved
 *
 * @param int $post_id The post ID.
 */
function dnd_vocab_save_deck_tags( $post_id ) {
    // Check if nonce is set
    if ( ! isset( $_POST['dnd_vocab_tags_nonce'] ) ) {
        return;
    }

    // Verify nonce
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_tags_nonce'] ) ), 'dnd_vocab_save_tags' ) ) {
        return;
    }

    // Check if this is an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check post type
    if ( 'dnd_deck' !== get_post_type( $post_id ) ) {
        return;
    }

    // Check user permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Get the submitted tags
    $tags = array();
    if ( isset( $_POST['dnd_vocab_tags'] ) && is_array( $_POST['dnd_vocab_tags'] ) ) {
        $tags = array_map( 'absint', $_POST['dnd_vocab_tags'] );
    }

    // Save the tags
    wp_set_object_terms( $post_id, $tags, 'dnd_tag' );
}
add_action( 'save_post', 'dnd_vocab_save_deck_tags' );

/**
 * Add custom column to Deck list table
 *
 * @param array $columns The existing columns.
 * @return array
 */
function dnd_vocab_deck_columns( $columns ) {
    $new_columns = array();
    
    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;
        
        // Add Tags column after title
        if ( 'title' === $key ) {
            $new_columns['dnd_tags'] = __( 'Tags', 'dnd-vocab' );
        }
    }
    
    return $new_columns;
}
add_filter( 'manage_dnd_deck_posts_columns', 'dnd_vocab_deck_columns' );

/**
 * Render custom column content
 *
 * @param string $column  The column name.
 * @param int    $post_id The post ID.
 */
function dnd_vocab_deck_column_content( $column, $post_id ) {
    if ( 'dnd_tags' === $column ) {
        $tags = wp_get_object_terms( $post_id, 'dnd_tag', array( 'fields' => 'names' ) );
        
        if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
            echo esc_html( implode( ', ', $tags ) );
        } else {
            echo '<span aria-hidden="true">—</span>';
        }
    }
}
add_action( 'manage_dnd_deck_posts_custom_column', 'dnd_vocab_deck_column_content', 10, 2 );

/**
 * Make Tags column sortable
 *
 * @param array $columns The sortable columns.
 * @return array
 */
function dnd_vocab_deck_sortable_columns( $columns ) {
    $columns['dnd_tags'] = 'dnd_tags';
    return $columns;
}
add_filter( 'manage_edit-dnd_deck_sortable_columns', 'dnd_vocab_deck_sortable_columns' );

