<?php
/**
 * Plugin Name: Shield360 AI Migration
 * Plugin URI:  https://engine.mosiur.com/shield360-ai-migration
 * Description: Automatically migrate your entire WordPress site (database, files, themes, plugins, media, and settings) to another server with one click.
 * Version:     1.0.0
 * Author:      Shield360
 * Author URI:  https://engine.mosiur.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shield360-ai-migration
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'S360_MIGRATION_VERSION', '1.0.0' );
define( 'S360_MIGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'S360_MIGRATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'S360_MIGRATION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-core.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-export.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-import.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-api.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-admin.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-db.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-files.php';
require_once S360_MIGRATION_PLUGIN_DIR . 'includes/class-s360-migration-search-replace.php';

/**
 * Initialize the plugin.
 */
function s360_migration_init() {
    $core = new S360_Migration_Core();
    $core->init();
}
add_action( 'plugins_loaded', 's360_migration_init' );

/**
 * Activation hook.
 */
function s360_migration_activate() {
    // Create temp directory for migration packages.
    $upload_dir = wp_upload_dir();
    $migration_dir = $upload_dir['basedir'] . '/shield360-migration';
    if ( ! file_exists( $migration_dir ) ) {
        wp_mkdir_p( $migration_dir );
        file_put_contents( $migration_dir . '/.htaccess', 'deny from all' );
        file_put_contents( $migration_dir . '/index.php', '<?php // Silence is golden.' );
    }

    // Generate a unique site key for secure transfers.
    if ( ! get_option( 's360_migration_site_key' ) ) {
        update_option( 's360_migration_site_key', wp_generate_password( 64, false ) );
    }
}
register_activation_hook( __FILE__, 's360_migration_activate' );

/**
 * Deactivation hook.
 */
function s360_migration_deactivate() {
    wp_clear_scheduled_hook( 's360_migration_cleanup' );
}
register_deactivation_hook( __FILE__, 's360_migration_deactivate' );
