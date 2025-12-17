<?php
/**
 * Plugin Name: DND Vocab
 * Plugin URI: https://example.com/dnd-vocab
 * Description: Quản lý từ vựng với Deck và Tag trong WordPress admin.
 * Version: 1.0.0
 * Author: DND
 * Author URI: https://example.com
 * Text Domain: dnd-vocab
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'DND_VOCAB_VERSION', '1.0.0' );
define( 'DND_VOCAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DND_VOCAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DND_VOCAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin
 */
function dnd_vocab_init() {
    // Load includes
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/helpers.php';
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/post-types.php';
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/admin-menu.php';
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/settings-page.php';
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/deck-metabox.php';
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/vocab-metabox.php';
}
add_action( 'plugins_loaded', 'dnd_vocab_init' );

/**
 * Activation hook
 */
function dnd_vocab_activate() {
    // Include post types to register them
    require_once DND_VOCAB_PLUGIN_DIR . 'includes/post-types.php';
    dnd_vocab_register_post_types();
    dnd_vocab_register_taxonomies();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dnd_vocab_activate' );

/**
 * Deactivation hook
 */
function dnd_vocab_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dnd_vocab_deactivate' );

