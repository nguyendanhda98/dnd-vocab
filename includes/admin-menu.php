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

    // Submenu: Settings
    add_submenu_page(
        'dnd-vocab',                              // Parent slug
        __( 'DND Vocab Settings', 'dnd-vocab' ), // Page title
        __( 'Settings', 'dnd-vocab' ),           // Menu title
        'manage_options',                          // Capability
        'dnd-vocab-settings',                     // Menu slug
        'dnd_vocab_settings_page'                 // Callback function
    );

    // Remove the duplicate first menu item
    global $submenu;
    if ( isset( $submenu['dnd-vocab'] ) ) {
        // Remove the auto-generated first submenu that duplicates the main menu
        unset( $submenu['dnd-vocab'][0] );
    }
}
add_action( 'admin_menu', 'dnd_vocab_admin_menu' );

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

