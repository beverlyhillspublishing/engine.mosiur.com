<?php
/**
 * Handles full-site export – database + files → ZIP package.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Export {

    /**
     * Create a full migration package.
     *
     * @param  array $options Export options.
     * @return array|WP_Error Package info on success.
     */
    public function create_package( $options = array() ) {
        $defaults = array(
            'include_db'      => true,
            'include_themes'  => true,
            'include_plugins' => true,
            'include_uploads' => true,
            'include_core'    => false,
            'exclude_paths'   => array(),
        );
        $options = wp_parse_args( $options, $defaults );

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $package_id    = 's360_' . wp_generate_password( 12, false );
        $work_dir      = $migration_dir . '/' . $package_id;
        wp_mkdir_p( $work_dir );

        // ── 1. Export database ──────────────────────────────────────────
        if ( $options['include_db'] ) {
            $db_result = S360_Migration_DB::export( $work_dir . '/database.sql' );
            if ( is_wp_error( $db_result ) ) {
                S360_Migration_Files::rmdir_recursive( $work_dir );
                return $db_result;
            }
        }

        // ── 2. Export wp-content files ──────────────────────────────────
        $wp_content = WP_CONTENT_DIR;
        $excludes   = array_merge(
            array( 'cache', 'upgrade', 'shield360-migration' ),
            $options['exclude_paths']
        );

        $dirs_to_pack = array();
        if ( $options['include_themes'] ) {
            $dirs_to_pack['themes'] = $wp_content . '/themes';
        }
        if ( $options['include_plugins'] ) {
            $dirs_to_pack['plugins'] = $wp_content . '/plugins';
        }
        if ( $options['include_uploads'] ) {
            $dirs_to_pack['uploads'] = $wp_content . '/uploads';
        }

        foreach ( $dirs_to_pack as $label => $dir_path ) {
            if ( ! is_dir( $dir_path ) ) {
                continue;
            }
            $zip_path = $work_dir . '/' . $label . '.zip';
            $result   = S360_Migration_Files::create_zip( $dir_path, $zip_path, $excludes );
            if ( is_wp_error( $result ) ) {
                S360_Migration_Files::rmdir_recursive( $work_dir );
                return $result;
            }
        }

        // ── 3. Build manifest ───────────────────────────────────────────
        $manifest = array(
            'package_id'    => $package_id,
            'version'       => S360_MIGRATION_VERSION,
            'wp_version'    => get_bloginfo( 'version' ),
            'php_version'   => phpversion(),
            'site_url'      => site_url(),
            'home_url'      => home_url(),
            'table_prefix'  => $GLOBALS['table_prefix'],
            'created_at'    => gmdate( 'Y-m-d H:i:s' ),
            'contents'      => array_keys( $dirs_to_pack ),
            'has_database'  => $options['include_db'],
            'charset'       => get_bloginfo( 'charset' ),
            'active_theme'  => get_stylesheet(),
            'active_plugins'=> get_option( 'active_plugins', array() ),
        );

        file_put_contents( $work_dir . '/manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

        // ── 4. Pack everything into a single ZIP ────────────────────────
        $final_zip = $migration_dir . '/' . $package_id . '.zip';
        $result    = S360_Migration_Files::create_zip( $work_dir, $final_zip );
        S360_Migration_Files::rmdir_recursive( $work_dir );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'package_id' => $package_id,
            'file'       => $final_zip,
            'size'       => filesize( $final_zip ),
            'manifest'   => $manifest,
        );
    }

    /**
     * Delete a migration package.
     *
     * @param string $package_id Package ID.
     */
    public function delete_package( $package_id ) {
        $migration_dir = S360_Migration_Core::get_migration_dir();
        $zip_path      = $migration_dir . '/' . sanitize_file_name( $package_id ) . '.zip';
        if ( file_exists( $zip_path ) ) {
            wp_delete_file( $zip_path );
        }
    }
}
