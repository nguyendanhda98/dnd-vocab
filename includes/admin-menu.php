<?php
/**
 * Admin Menu Configuration
 *
 * @package DND_Vocab
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register admin menu and submenus
 */
function dnd_vocab_admin_menu() {
    // Main menu page - DND Vocab
    add_menu_page(
        __( 'DND Vocab', 'dnd-vocab' ),           // Page title
        __( 'DND Vocab', 'dnd-vocab' ),           // Menu title
        'manage_options',                          // Capability
        'dnd-vocab',                               // Menu slug
        'dnd_vocab_redirect_to_deck',             // Callback function
        'dashicons-welcome-learn-more',           // Icon
        30                                         // Position
    );

    // Submenu: Deck (links to CPT list)
    add_submenu_page(
        'dnd-vocab',                              // Parent slug
        __( 'Decks', 'dnd-vocab' ),               // Page title
        __( 'Deck', 'dnd-vocab' ),                // Menu title
        'manage_options',                          // Capability
        'edit.php?post_type=dnd_deck'             // Menu slug (CPT list URL)
    );

    // Submenu: FSRS Test
    add_submenu_page(
        'dnd-vocab',                              // Parent slug
        __( 'FSRS Test', 'dnd-vocab' ),          // Page title
        __( 'FSRS Test', 'dnd-vocab' ),          // Menu title
        'manage_options',                          // Capability
        'dnd-vocab-fsrs-test',                     // Menu slug
        'dnd_vocab_fsrs_test_page'                // Callback function
    );

    // Submenu: Settings
    add_submenu_page(
        'dnd-vocab',                              // Parent slug
        __( 'DND Vocab Settings', 'dnd-vocab' ), // Page title
        __( 'Settings', 'dnd-vocab' ),           // Menu title
        'manage_options',                          // Capability
        'dnd-vocab-settings',                     // Menu slug
        'dnd_vocab_settings_page'                 // Callback function
    );

    // Submenu: Deck Vocabulary Items (management per deck) - page exists but is hidden from menu.
    add_submenu_page(
        'dnd-vocab',
        __( 'Deck Vocabulary', 'dnd-vocab' ),
        __( 'Deck Vocabulary', 'dnd-vocab' ),
        'manage_options',
        'dnd-vocab-deck-items',
        'dnd_vocab_deck_items_page'
    );

    // Submenu: Deck Import (hidden from visible menu, used when importing vocab for a deck).
    add_submenu_page(
        'dnd-vocab',
        __( 'Import Deck Vocabulary', 'dnd-vocab' ),
        __( 'Import Deck Vocabulary', 'dnd-vocab' ),
        'manage_options',
        'dnd-vocab-deck-import',
        'dnd_vocab_deck_import_page'
    );

    // Remove the duplicate first menu item
    global $submenu;
    if ( isset( $submenu['dnd-vocab'] ) ) {
        // Remove the auto-generated first submenu that duplicates the main menu.
        // Keeping all real submenu pages (Deck Vocabulary, Import, Settings) registered
        // ensures WordPress recognizes them and does not block access with a 403.
        unset( $submenu['dnd-vocab'][0] );
    }
}
add_action( 'admin_menu', 'dnd_vocab_admin_menu' );

/**
 * Render Deck Vocabulary management page.
 */
function dnd_vocab_deck_items_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dnd-vocab' ) );
    }

    $deck_id = isset( $_GET['deck_id'] ) ? absint( $_GET['deck_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( ! $deck_id ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Deck Vocabulary', 'dnd-vocab' ); ?></h1>
            <p><?php esc_html_e( 'No deck selected. Please go back to the deck list and choose a deck.', 'dnd-vocab' ); ?></p>
        </div>
        <?php
        return;
    }

    $deck = get_post( $deck_id );

    if ( ! $deck || 'dnd_deck' !== $deck->post_type ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Deck Vocabulary', 'dnd-vocab' ); ?></h1>
            <p><?php esc_html_e( 'Invalid deck.', 'dnd-vocab' ); ?></p>
        </div>
        <?php
        return;
    }

    // Handle notices via URL param.
    if ( isset( $_GET['dnd_vocab_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = sanitize_text_field( wp_unslash( $_GET['dnd_vocab_message'] ) );
        if ( 'added' === $message ) {
            dnd_vocab_admin_notice( __( 'Vocabulary item added.', 'dnd-vocab' ) );
        } elseif ( 'deleted' === $message ) {
            dnd_vocab_admin_notice( __( 'Vocabulary item deleted.', 'dnd-vocab' ) );
        }
    }

    // Load all vocab items for this deck.
    $query = new WP_Query(
        array(
            'post_type'      => 'dnd_vocab_item',
            'posts_per_page' => -1,
            'meta_key'       => '_dnd_vocab_deck_id',
            'meta_value'     => $deck_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    ?>
    <div class="wrap">
        <h1>
            <?php
            printf(
                /* translators: %s: deck title */
                esc_html__( 'Vocabulary for Deck: %s', 'dnd-vocab' ),
                esc_html( get_the_title( $deck_id ) )
            );
            ?>
        </h1>

        <p>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dnd_deck' ) ); ?>" class="button">
                <?php esc_html_e( 'Back to Decks', 'dnd-vocab' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=dnd_vocab_item&deck_id=' . $deck_id ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Add New Vocabulary Item', 'dnd-vocab' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-deck-import&deck_id=' . $deck_id ) ); ?>" class="button">
                <?php esc_html_e( 'Import from File', 'dnd-vocab' ); ?>
            </a>
        </p>

        <h2><?php esc_html_e( 'Vocabulary Items', 'dnd-vocab' ); ?></h2>

        <?php if ( $query->have_posts() ) : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Word', 'dnd-vocab' ); ?></th>
                        <th><?php esc_html_e( 'IPA', 'dnd-vocab' ); ?></th>
                        <th><?php esc_html_e( 'Short Vietnamese', 'dnd-vocab' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'dnd-vocab' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ( $query->have_posts() ) :
                        $query->the_post();
                        $item_id   = get_the_ID();
                        $ipa       = get_post_meta( $item_id, 'dnd_vocab_ipa', true );
                        $short_vi  = get_post_meta( $item_id, 'dnd_vocab_short_vietnamese', true );
                        $edit_link = get_edit_post_link( $item_id );
                        $delete_url = wp_nonce_url(
                            add_query_arg(
                                array(
                                    'action'       => 'dnd_vocab_delete_item',
                                    'item_id'      => $item_id,
                                    'deck_id'      => $deck_id,
                                ),
                                admin_url( 'admin.php?page=dnd-vocab-deck-items' )
                            ),
                            'dnd_vocab_delete_item_' . $item_id
                        );
                        ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td><?php echo esc_html( $ipa ); ?></td>
                            <td><?php echo esc_html( $short_vi ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'dnd-vocab' ); ?></a> |
                                <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this vocabulary item?', 'dnd-vocab' ) ); ?>');">
                                    <?php esc_html_e( 'Delete', 'dnd-vocab' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No vocabulary items yet. Click \"Add New Vocabulary Item\" to create one.', 'dnd-vocab' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render Deck Vocabulary Import page.
 *
 * Steps:
 * 1. Upload .txt file.
 * 2. Choose column mapping.
 * 3. Import to create vocabulary items for a deck.
 */
function dnd_vocab_deck_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dnd-vocab' ) );
    }

    $deck_id = isset( $_REQUEST['deck_id'] ) ? absint( $_REQUEST['deck_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( ! $deck_id ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Deck Vocabulary', 'dnd-vocab' ); ?></h1>
            <p><?php esc_html_e( 'No deck selected. Please go back to the deck list and choose a deck.', 'dnd-vocab' ); ?></p>
        </div>
        <?php
        return;
    }

    $deck = get_post( $deck_id );
    if ( ! $deck || 'dnd_deck' !== $deck->post_type ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Deck Vocabulary', 'dnd-vocab' ); ?></h1>
            <p><?php esc_html_e( 'Invalid deck.', 'dnd-vocab' ); ?></p>
        </div>
        <?php
        return;
    }

    $step     = isset( $_POST['dnd_vocab_import_step'] ) ? sanitize_text_field( wp_unslash( $_POST['dnd_vocab_import_step'] ) ) : 'upload'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $messages = array();

    // Step 2: Handle import with mapping.
    if ( 'map' === $step && isset( $_POST['dnd_vocab_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_import_nonce'] ) ), 'dnd_vocab_import' ) ) {
        $rows_json = isset( $_POST['dnd_vocab_rows'] ) ? wp_unslash( $_POST['dnd_vocab_rows'] ) : '';
        $rows      = json_decode( $rows_json, true );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            $messages[] = array(
                'type'    => 'error',
                'content' => __( 'Invalid or empty data for import.', 'dnd-vocab' ),
            );
        } else {
            $map = array(
                'word'            => isset( $_POST['dnd_vocab_col_word'] ) ? intval( $_POST['dnd_vocab_col_word'] ) : -1,
                'ipa'             => isset( $_POST['dnd_vocab_col_ipa'] ) ? intval( $_POST['dnd_vocab_col_ipa'] ) : -1,
                'definition'      => isset( $_POST['dnd_vocab_col_definition'] ) ? intval( $_POST['dnd_vocab_col_definition'] ) : -1,
                'example'         => isset( $_POST['dnd_vocab_col_example'] ) ? intval( $_POST['dnd_vocab_col_example'] ) : -1,
                'short_vietnamese'=> isset( $_POST['dnd_vocab_col_short_vietnamese'] ) ? intval( $_POST['dnd_vocab_col_short_vietnamese'] ) : -1,
                'full_vietnamese' => isset( $_POST['dnd_vocab_col_full_vietnamese'] ) ? intval( $_POST['dnd_vocab_col_full_vietnamese'] ) : -1,
            );

            $imported = 0;

            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) || empty( $row ) ) {
                    continue;
                }

                $word = ( $map['word'] >= 0 && isset( $row[ $map['word'] ] ) ) ? sanitize_text_field( $row[ $map['word'] ] ) : '';
                if ( '' === $word ) {
                    // Skip rows without a word.
                    continue;
                }

                $ipa        = ( $map['ipa'] >= 0 && isset( $row[ $map['ipa'] ] ) ) ? sanitize_text_field( $row[ $map['ipa'] ] ) : '';
                $definition = ( $map['definition'] >= 0 && isset( $row[ $map['definition'] ] ) ) ? sanitize_textarea_field( $row[ $map['definition'] ] ) : '';
                $example    = ( $map['example'] >= 0 && isset( $row[ $map['example'] ] ) ) ? sanitize_textarea_field( $row[ $map['example'] ] ) : '';
                $short_vi   = ( $map['short_vietnamese'] >= 0 && isset( $row[ $map['short_vietnamese'] ] ) ) ? sanitize_textarea_field( $row[ $map['short_vietnamese'] ] ) : '';
                $full_vi    = ( $map['full_vietnamese'] >= 0 && isset( $row[ $map['full_vietnamese'] ] ) ) ? sanitize_textarea_field( $row[ $map['full_vietnamese'] ] ) : '';

                // Strip cloze markers from definition & example.
                if ( function_exists( 'dnd_vocab_strip_cloze' ) ) {
                    $definition = dnd_vocab_strip_cloze( $definition );
                    $example    = dnd_vocab_strip_cloze( $example );
                }

                $post_id = wp_insert_post(
                    array(
                        'post_type'   => 'dnd_vocab_item',
                        'post_status' => 'publish',
                        'post_title'  => $word,
                    ),
                    true
                );

                if ( is_wp_error( $post_id ) ) {
                    continue;
                }

                update_post_meta( $post_id, 'dnd_vocab_word', $word );
                if ( '' !== $ipa ) {
                    update_post_meta( $post_id, 'dnd_vocab_ipa', $ipa );
                }
                if ( '' !== $definition ) {
                    update_post_meta( $post_id, 'dnd_vocab_definition', $definition );
                }
                if ( '' !== $example ) {
                    update_post_meta( $post_id, 'dnd_vocab_example', $example );
                }
                if ( '' !== $short_vi ) {
                    update_post_meta( $post_id, 'dnd_vocab_short_vietnamese', $short_vi );
                }
                if ( '' !== $full_vi ) {
                    update_post_meta( $post_id, 'dnd_vocab_full_vietnamese', $full_vi );
                }

                // Auto-generate suggestion from word.
                if ( function_exists( 'dnd_vocab_generate_suggestion' ) ) {
                    $suggestion = dnd_vocab_generate_suggestion( $word );
                    if ( ! empty( $suggestion ) ) {
                        update_post_meta( $post_id, 'dnd_vocab_suggestion', $suggestion );
                    }
                }

                // Link to deck.
                update_post_meta( $post_id, '_dnd_vocab_deck_id', $deck_id );

                $imported++;
            }

            if ( $imported > 0 ) {
                $redirect = add_query_arg(
                    array(
                        'page'              => 'dnd-vocab-deck-items',
                        'deck_id'           => $deck_id,
                        'dnd_vocab_message' => 'added',
                    ),
                    admin_url( 'admin.php' )
                );
                wp_safe_redirect( $redirect );
                exit;
            } else {
                $messages[] = array(
                    'type'    => 'error',
                    'content' => __( 'No vocabulary items were imported. Please check your mapping and file content.', 'dnd-vocab' ),
                );
            }
        }
    }

    // Step 1: Handle file upload & show mapping form.
    $sample_row = array();
    $rows       = array();

    if ( 'upload' === $step && ! empty( $_FILES['dnd_vocab_file']['tmp_name'] ) && isset( $_POST['dnd_vocab_import_upload_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dnd_vocab_import_upload_nonce'] ) ), 'dnd_vocab_import_upload' ) ) {
        $file_tmp = $_FILES['dnd_vocab_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content  = file_get_contents( $file_tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( false === $content ) {
            $messages[] = array(
                'type'    => 'error',
                'content' => __( 'Unable to read uploaded file.', 'dnd-vocab' ),
            );
        } else {
            // Normalize line endings.
            $content = str_replace( array( "\r\n", "\r" ), "\n", $content );
            $lines   = explode( "\n", $content );

            $separator      = "\t";
            $found_header   = false;
            $data_rows      = array();

            foreach ( $lines as $line ) {
                $trimmed = trim( $line );
                if ( '' === $trimmed ) {
                    continue;
                }

                // Header lines starting with #.
                if ( 0 === strpos( $trimmed, '#separator:' ) ) {
                    $found_header = true;
                    $sep_value    = strtolower( trim( substr( $trimmed, strlen( '#separator:' ) ) ) );
                    if ( 'tab' === $sep_value ) {
                        $separator = "\t";
                    } elseif ( 'comma' === $sep_value ) {
                        $separator = ',';
                    }
                    continue;
                }

                if ( 0 === strpos( $trimmed, '#html:' ) ) {
                    // We currently ignore html flag; treat as plain text.
                    continue;
                }

                if ( '#' === substr( $trimmed, 0, 1 ) ) {
                    // Other comment line; skip.
                    continue;
                }

                // Data row.
                $cols = explode( $separator, $line );
                $cols = array_map( 'trim', $cols );

                if ( ! empty( $cols ) ) {
                    $data_rows[] = $cols;
                }
            }

            if ( empty( $data_rows ) ) {
                $messages[] = array(
                    'type'    => 'error',
                    'content' => __( 'No data rows found in file.', 'dnd-vocab' ),
                );
            } else {
                $rows       = $data_rows;
                $sample_row = $data_rows[0];
                $step       = 'map';
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>
            <?php
            printf(
                /* translators: %s: deck title */
                esc_html__( 'Import Vocabulary for Deck: %s', 'dnd-vocab' ),
                esc_html( get_the_title( $deck_id ) )
            );
            ?>
        </h1>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=dnd-vocab-deck-items&deck_id=' . $deck_id ) ); ?>" class="button">
                <?php esc_html_e( 'Back to Deck Vocabulary', 'dnd-vocab' ); ?>
            </a>
        </p>

        <?php foreach ( $messages as $msg ) : ?>
            <div class="notice notice-<?php echo esc_attr( $msg['type'] ); ?> is-dismissible">
                <p><?php echo esc_html( $msg['content'] ); ?></p>
            </div>
        <?php endforeach; ?>

        <?php if ( 'upload' === $step ) : ?>
            <h2><?php esc_html_e( 'Step 1: Upload .txt File', 'dnd-vocab' ); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'dnd_vocab_import_upload', 'dnd_vocab_import_upload_nonce' ); ?>
                <input type="hidden" name="dnd_vocab_import_step" value="upload">
                <input type="hidden" name="deck_id" value="<?php echo esc_attr( $deck_id ); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dnd_vocab_file"><?php esc_html_e( 'TXT File', 'dnd-vocab' ); ?></label>
                        </th>
                        <td>
                            <input type="file" id="dnd_vocab_file" name="dnd_vocab_file" accept=".txt" required>
                            <p class="description">
                                <?php esc_html_e( 'Upload a .txt file exported from Anki. The file should include a #separator header (e.g. #separator:tab).', 'dnd-vocab' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Continue to Mapping', 'dnd-vocab' ) ); ?>
            </form>
        <?php else : ?>
            <h2><?php esc_html_e( 'Step 2: Map Columns to Fields', 'dnd-vocab' ); ?></h2>
            <p><?php esc_html_e( 'Using the first row as an example, choose which column corresponds to each field.', 'dnd-vocab' ); ?></p>

            <?php if ( ! empty( $sample_row ) ) : ?>
                <table class="widefat striped" style="max-width: 800px; margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <?php foreach ( $sample_row as $index => $value ) : ?>
                                <th><?php printf( esc_html__( 'Column %d', 'dnd-vocab' ), intval( $index + 1 ) ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ( $sample_row as $value ) : ?>
                                <td><?php echo esc_html( $value ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'dnd_vocab_import', 'dnd_vocab_import_nonce' ); ?>
                <input type="hidden" name="dnd_vocab_import_step" value="map">
                <input type="hidden" name="deck_id" value="<?php echo esc_attr( $deck_id ); ?>">
                <input type="hidden" name="dnd_vocab_rows" value="<?php echo esc_attr( wp_json_encode( $rows ) ); ?>">

                <?php
                $field_labels = array(
                    'word'             => __( 'Word', 'dnd-vocab' ),
                    'ipa'              => __( 'IPA', 'dnd-vocab' ),
                    'definition'       => __( 'Definition', 'dnd-vocab' ),
                    'example'          => __( 'Example', 'dnd-vocab' ),
                    'short_vietnamese' => __( 'Short Vietnamese', 'dnd-vocab' ),
                    'full_vietnamese'  => __( 'Full Vietnamese', 'dnd-vocab' ),
                );

                $field_names = array(
                    'word'             => 'dnd_vocab_col_word',
                    'ipa'              => 'dnd_vocab_col_ipa',
                    'definition'       => 'dnd_vocab_col_definition',
                    'example'          => 'dnd_vocab_col_example',
                    'short_vietnamese' => 'dnd_vocab_col_short_vietnamese',
                    'full_vietnamese'  => 'dnd_vocab_col_full_vietnamese',
                );
                ?>

                <table class="form-table">
                    <?php foreach ( $field_labels as $key => $label ) : ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr( $field_names[ $key ] ); ?>"><?php echo esc_html( $label ); ?></label>
                            </th>
                            <td>
                                <select id="<?php echo esc_attr( $field_names[ $key ] ); ?>" name="<?php echo esc_attr( $field_names[ $key ] ); ?>">
                                    <option value="-1"><?php esc_html_e( '-- Ignore --', 'dnd-vocab' ); ?></option>
                                    <?php foreach ( $sample_row as $index => $value ) : ?>
                                        <?php
                                        $option_label = sprintf(
                                            /* translators: 1: column number, 2: sample value */
                                            __( 'Column %1$d: %2$s', 'dnd-vocab' ),
                                            intval( $index + 1 ),
                                            mb_strimwidth( $value, 0, 60, '...' )
                                        );
                                        ?>
                                        <option value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $option_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( __( 'Import Vocabulary', 'dnd-vocab' ) ); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}


/**
 * Redirect main menu click to Deck list
 */
function dnd_vocab_redirect_to_deck() {
    wp_safe_redirect( admin_url( 'edit.php?post_type=dnd_deck' ) );
    exit;
}

/**
 * Highlight parent menu when on Deck CPT pages
 *
 * @param string $parent_file The parent file.
 * @return string
 */
function dnd_vocab_menu_highlight( $parent_file ) {
    global $current_screen;

    if ( isset( $current_screen->post_type ) && 'dnd_deck' === $current_screen->post_type ) {
        $parent_file = 'dnd-vocab';
    }

    return $parent_file;
}
add_filter( 'parent_file', 'dnd_vocab_menu_highlight' );

/**
 * Highlight submenu when on Deck CPT pages
 *
 * @param string $submenu_file The submenu file.
 * @return string
 */
function dnd_vocab_submenu_highlight( $submenu_file ) {
    global $current_screen;

    if ( isset( $current_screen->post_type ) && 'dnd_deck' === $current_screen->post_type ) {
        $submenu_file = 'edit.php?post_type=dnd_deck';
    }

    return $submenu_file;
}
add_filter( 'submenu_file', 'dnd_vocab_submenu_highlight' );

