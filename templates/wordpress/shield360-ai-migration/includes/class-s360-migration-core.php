<?php
/**
 * Core plugin class – wires everything together.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Core {

    /** @var S360_Migration_Admin */
    private $admin;

    /** @var S360_Migration_API */
    private $api;

    /** @var S360_Migration_Export */
    private $export;

    /** @var S360_Migration_Import */
    private $import;

    public function init() {
        $this->export = new S360_Migration_Export();
        $this->import = new S360_Migration_Import();
        $this->api    = new S360_Migration_API( $this->export, $this->import );
        $this->admin  = new S360_Migration_Admin();

        $this->api->register_hooks();

        if ( is_admin() ) {
            $this->admin->register_hooks();
        }

        // Scheduled cleanup of old migration packages (daily).
        if ( ! wp_next_scheduled( 's360_migration_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 's360_migration_cleanup' );
        }
        add_action( 's360_migration_cleanup', array( $this, 'cleanup_old_packages' ) );
    }

    /**
     * Remove migration packages older than 24 hours.
     */
    public function cleanup_old_packages() {
        $upload_dir    = wp_upload_dir();
        $migration_dir = $upload_dir['basedir'] . '/shield360-migration';

        if ( ! is_dir( $migration_dir ) ) {
            return;
        }

        $files = glob( $migration_dir . '/*.zip' );
        if ( ! $files ) {
            return;
        }

        $threshold = time() - DAY_IN_SECONDS;
        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $threshold ) {
                wp_delete_file( $file );
            }
        }
    }

    /**
     * Get the migration working directory path.
     */
    public static function get_migration_dir() {
        $upload_dir = wp_upload_dir();
        $dir        = $upload_dir['basedir'] . '/shield360-migration';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        return $dir;
    }
}
