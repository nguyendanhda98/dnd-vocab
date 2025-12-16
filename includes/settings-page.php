<?php
/**
 * Settings Page for DND Vocab
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the settings page
 */
function dnd_vocab_settings_page() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dnd-vocab' ) );
    }

    // Get current tab
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'tag';

    // Process form submissions
    dnd_vocab_process_tag_actions();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag' ) ); ?>" 
               class="nav-tab <?php echo 'tag' === $current_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Tag', 'dnd-vocab' ); ?>
            </a>
            <!-- Add more tabs here in the future -->
        </nav>

        <div class="tab-content" style="margin-top: 20px;">
            <?php
            switch ( $current_tab ) {
                case 'tag':
                default:
                    dnd_vocab_render_tag_tab();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Process tag form actions (add, edit, delete)
 */
function dnd_vocab_process_tag_actions() {
    // Handle Add Tag
    if ( isset( $_POST['dnd_vocab_add_tag'] ) ) {
        // Verify nonce
        if ( ! isset( $_POST['dnd_vocab_tag_nonce'] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_tag_nonce'] ) ), 'dnd_vocab_tag_action' ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_nonce_error', __( 'Security check failed.', 'dnd-vocab' ), 'error' );
            return;
        }

        $tag_name = isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
        $tag_slug = isset( $_POST['tag_slug'] ) ? sanitize_title( wp_unslash( $_POST['tag_slug'] ) ) : '';
        $tag_description = isset( $_POST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tag_description'] ) ) : '';

        if ( empty( $tag_name ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_empty_name', __( 'Tag name is required.', 'dnd-vocab' ), 'error' );
            return;
        }

        $result = dnd_vocab_create_tag( $tag_name, $tag_slug, $tag_description );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_add_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_add_success', __( 'Tag added successfully.', 'dnd-vocab' ), 'success' );
        }
    }

    // Handle Edit Tag
    if ( isset( $_POST['dnd_vocab_edit_tag'] ) ) {
        // Verify nonce
        if ( ! isset( $_POST['dnd_vocab_tag_nonce'] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_tag_nonce'] ) ), 'dnd_vocab_tag_action' ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_nonce_error', __( 'Security check failed.', 'dnd-vocab' ), 'error' );
            return;
        }

        $tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
        $tag_name = isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
        $tag_slug = isset( $_POST['tag_slug'] ) ? sanitize_title( wp_unslash( $_POST['tag_slug'] ) ) : '';
        $tag_description = isset( $_POST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tag_description'] ) ) : '';

        if ( empty( $tag_id ) || empty( $tag_name ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_edit_error', __( 'Tag ID and name are required.', 'dnd-vocab' ), 'error' );
            return;
        }

        $result = dnd_vocab_update_tag( $tag_id, $tag_name, $tag_slug, $tag_description );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_edit_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_edit_success', __( 'Tag updated successfully. All decks using this tag have been updated.', 'dnd-vocab' ), 'success' );
        }
    }

    // Handle Delete Tag
    if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['tag_id'] ) ) {
        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dnd_vocab_delete_tag_' . absint( $_GET['tag_id'] ) ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_nonce_error', __( 'Security check failed.', 'dnd-vocab' ), 'error' );
            return;
        }

        $tag_id = absint( $_GET['tag_id'] );
        $result = dnd_vocab_delete_tag( $tag_id );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_delete_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'dnd_vocab_messages', 'dnd_vocab_delete_success', __( 'Tag deleted successfully.', 'dnd-vocab' ), 'success' );
        }
    }
}

/**
 * Render the Tag tab content
 */
function dnd_vocab_render_tag_tab() {
    // Check if we're editing a tag
    $editing_tag = null;
    if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['tag_id'] ) ) {
        $editing_tag = dnd_vocab_get_tag( absint( $_GET['tag_id'] ) );
    }

    // Display messages
    settings_errors( 'dnd_vocab_messages' );

    ?>
    <div class="dnd-vocab-tag-management">
        <div id="col-container" class="wp-clearfix">
            
            <!-- Left Column: Add/Edit Form -->
            <div id="col-left">
                <div class="col-wrap">
                    <?php if ( $editing_tag && ! is_wp_error( $editing_tag ) ) : ?>
                        <!-- Edit Tag Form -->
                        <div class="form-wrap">
                            <h2><?php esc_html_e( 'Edit Tag', 'dnd-vocab' ); ?></h2>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag' ) ); ?>">
                                <?php wp_nonce_field( 'dnd_vocab_tag_action', 'dnd_vocab_tag_nonce' ); ?>
                                <input type="hidden" name="tag_id" value="<?php echo esc_attr( $editing_tag->term_id ); ?>">
                                
                                <div class="form-field form-required term-name-wrap">
                                    <label for="tag_name"><?php esc_html_e( 'Name', 'dnd-vocab' ); ?></label>
                                    <input name="tag_name" id="tag_name" type="text" value="<?php echo esc_attr( $editing_tag->name ); ?>" size="40" required>
                                    <p><?php esc_html_e( 'The name is how it appears on your site.', 'dnd-vocab' ); ?></p>
                                </div>

                                <div class="form-field term-slug-wrap">
                                    <label for="tag_slug"><?php esc_html_e( 'Slug', 'dnd-vocab' ); ?></label>
                                    <input name="tag_slug" id="tag_slug" type="text" value="<?php echo esc_attr( $editing_tag->slug ); ?>" size="40">
                                    <p><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'dnd-vocab' ); ?></p>
                                </div>

                                <div class="form-field term-description-wrap">
                                    <label for="tag_description"><?php esc_html_e( 'Description', 'dnd-vocab' ); ?></label>
                                    <textarea name="tag_description" id="tag_description" rows="5" cols="40"><?php echo esc_textarea( $editing_tag->description ); ?></textarea>
                                    <p><?php esc_html_e( 'The description is not prominent by default.', 'dnd-vocab' ); ?></p>
                                </div>

                                <p class="submit">
                                    <input type="submit" name="dnd_vocab_edit_tag" class="button button-primary" value="<?php esc_attr_e( 'Update Tag', 'dnd-vocab' ); ?>">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'dnd-vocab' ); ?></a>
                                </p>
                            </form>
                        </div>
                    <?php else : ?>
                        <!-- Add New Tag Form -->
                        <div class="form-wrap">
                            <h2><?php esc_html_e( 'Add New Tag', 'dnd-vocab' ); ?></h2>
                            <form method="post" action="">
                                <?php wp_nonce_field( 'dnd_vocab_tag_action', 'dnd_vocab_tag_nonce' ); ?>
                                
                                <div class="form-field form-required term-name-wrap">
                                    <label for="tag_name"><?php esc_html_e( 'Name', 'dnd-vocab' ); ?></label>
                                    <input name="tag_name" id="tag_name" type="text" value="" size="40" required>
                                    <p><?php esc_html_e( 'The name is how it appears on your site.', 'dnd-vocab' ); ?></p>
                                </div>

                                <div class="form-field term-slug-wrap">
                                    <label for="tag_slug"><?php esc_html_e( 'Slug', 'dnd-vocab' ); ?></label>
                                    <input name="tag_slug" id="tag_slug" type="text" value="" size="40">
                                    <p><?php esc_html_e( 'The "slug" is the URL-friendly version of the name. Leave empty to auto-generate.', 'dnd-vocab' ); ?></p>
                                </div>

                                <div class="form-field term-description-wrap">
                                    <label for="tag_description"><?php esc_html_e( 'Description', 'dnd-vocab' ); ?></label>
                                    <textarea name="tag_description" id="tag_description" rows="5" cols="40"></textarea>
                                    <p><?php esc_html_e( 'The description is not prominent by default.', 'dnd-vocab' ); ?></p>
                                </div>

                                <p class="submit">
                                    <input type="submit" name="dnd_vocab_add_tag" class="button button-primary" value="<?php esc_attr_e( 'Add New Tag', 'dnd-vocab' ); ?>">
                                </p>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Tag List -->
            <div id="col-right">
                <div class="col-wrap">
                    <h2><?php esc_html_e( 'Tags', 'dnd-vocab' ); ?></h2>
                    <?php dnd_vocab_render_tag_table(); ?>
                </div>
            </div>

        </div>
    </div>

    <style>
        #col-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        #col-left {
            width: 35%;
            padding-right: 20px;
            box-sizing: border-box;
        }
        #col-right {
            width: 65%;
            box-sizing: border-box;
        }
        @media screen and (max-width: 782px) {
            #col-left,
            #col-right {
                width: 100%;
                padding-right: 0;
            }
        }
        .form-wrap .form-field {
            margin-bottom: 15px;
        }
        .form-wrap label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-wrap input[type="text"],
        .form-wrap textarea {
            width: 100%;
        }
        .form-wrap p {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        .dnd-vocab-tag-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dnd-vocab-tag-table th,
        .dnd-vocab-tag-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e1e1e1;
        }
        .dnd-vocab-tag-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .dnd-vocab-tag-table tr:hover {
            background: #f5f5f5;
        }
        .row-actions {
            visibility: hidden;
            padding: 2px 0 0;
        }
        .dnd-vocab-tag-table tr:hover .row-actions {
            visibility: visible;
        }
        .row-actions span {
            padding-right: 10px;
        }
        .row-actions .delete a {
            color: #b32d2e;
        }
        .row-actions .delete a:hover {
            color: #a00;
        }
    </style>
    <?php
}

/**
 * Render the tag table
 */
function dnd_vocab_render_tag_table() {
    $tags = dnd_vocab_get_tags();

    if ( is_wp_error( $tags ) || empty( $tags ) ) {
        echo '<p>' . esc_html__( 'No tags found. Add your first tag using the form.', 'dnd-vocab' ) . '</p>';
        return;
    }

    ?>
    <table class="dnd-vocab-tag-table widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'dnd-vocab' ); ?></th>
                <th><?php esc_html_e( 'Slug', 'dnd-vocab' ); ?></th>
                <th><?php esc_html_e( 'Description', 'dnd-vocab' ); ?></th>
                <th><?php esc_html_e( 'Count', 'dnd-vocab' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $tags as $tag ) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $tag->name ); ?></strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag&action=edit&tag_id=' . $tag->term_id ) ); ?>">
                                    <?php esc_html_e( 'Edit', 'dnd-vocab' ); ?>
                                </a>
                            </span>
                            <span class="delete">
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dnd-vocab-settings&tab=tag&action=delete&tag_id=' . $tag->term_id ), 'dnd_vocab_delete_tag_' . $tag->term_id ) ); ?>" 
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this tag? This action cannot be undone.', 'dnd-vocab' ) ); ?>');">
                                    <?php esc_html_e( 'Delete', 'dnd-vocab' ); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    <td><?php echo esc_html( $tag->slug ); ?></td>
                    <td><?php echo esc_html( $tag->description ); ?></td>
                    <td><?php echo esc_html( $tag->count ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

