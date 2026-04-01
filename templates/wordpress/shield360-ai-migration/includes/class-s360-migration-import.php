<?php
/**
 * Handles full-site import from a migration package.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Import {

    /**
     * Import a migration package.
     *
     * @param  string $zip_path Path to the migration ZIP package.
     * @param  array  $options  Import options.
     * @return array|WP_Error   Result info on success.
     */
    public function import_package( $zip_path, $options = array() ) {
        $defaults = array(
            'new_site_url'    => '',
            'new_home_url'    => '',
            'import_db'       => true,
            'import_themes'   => true,
            'import_plugins'  => true,
            'import_uploads'  => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $work_dir      = $migration_dir . '/import_' . wp_generate_password( 8, false );
        wp_mkdir_p( $work_dir );

        // ── 1. Extract outer package ────────────────────────────────────
        $result = S360_Migration_Files::extract_zip( $zip_path, $work_dir );
        if ( is_wp_error( $result ) ) {
            S360_Migration_Files::rmdir_recursive( $work_dir );
            return $result;
        }

        // ── 2. Read manifest ────────────────────────────────────────────
        $manifest_path = $work_dir . '/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            S360_Migration_Files::rmdir_recursive( $work_dir );
            return new WP_Error( 's360_import', 'Invalid package: manifest.json not found.' );
        }

        $manifest = json_decode( file_get_contents( $manifest_path ), true );
        if ( ! $manifest ) {
            S360_Migration_Files::rmdir_recursive( $work_dir );
            return new WP_Error( 's360_import', 'Invalid manifest file.' );
        }

        $log = array();

        // ── 3. Import database ──────────────────────────────────────────
        if ( $options['import_db'] && $manifest['has_database'] ) {
            $sql_file = $work_dir . '/database.sql';
            if ( file_exists( $sql_file ) ) {
                $db_result = S360_Migration_DB::import( $sql_file );
                if ( is_wp_error( $db_result ) ) {
                    $log[] = 'Database import failed: ' . $db_result->get_error_message();
                } else {
                    $log[] = 'Database imported successfully.';
                }
            }
        }

        // ── 4. Import files ────────────────────────────────────────────
        $wp_content = WP_CONTENT_DIR;
        $file_maps  = array(
            'themes'  => array( 'zip' => 'themes.zip',  'dest' => $wp_content . '/themes',  'flag' => 'import_themes' ),
            'plugins' => array( 'zip' => 'plugins.zip', 'dest' => $wp_content . '/plugins', 'flag' => 'import_plugins' ),
            'uploads' => array( 'zip' => 'uploads.zip', 'dest' => $wp_content . '/uploads', 'flag' => 'import_uploads' ),
        );

        foreach ( $file_maps as $label => $map ) {
            if ( ! $options[ $map['flag'] ] ) {
                continue;
            }

            $zip_file = $work_dir . '/' . $map['zip'];
            if ( ! file_exists( $zip_file ) ) {
                continue;
            }

            $extract_result = S360_Migration_Files::extract_zip( $zip_file, $map['dest'] );
            if ( is_wp_error( $extract_result ) ) {
                $log[] = ucfirst( $label ) . ' import failed: ' . $extract_result->get_error_message();
            } else {
                $log[] = ucfirst( $label ) . ' imported successfully.';
            }
        }

        // ── 5. Search & replace URLs ────────────────────────────────────
        $changes = 0;
        $old_site_url = $manifest['site_url'];
        $old_home_url = $manifest['home_url'];
        $new_site_url = ! empty( $options['new_site_url'] ) ? $options['new_site_url'] : site_url();
        $new_home_url = ! empty( $options['new_home_url'] ) ? $options['new_home_url'] : home_url();

        if ( $old_site_url !== $new_site_url ) {
            $changes += S360_Migration_Search_Replace::run( $old_site_url, $new_site_url );
        }
        if ( $old_home_url !== $new_home_url && $old_home_url !== $old_site_url ) {
            $changes += S360_Migration_Search_Replace::run( $old_home_url, $new_home_url );
        }

        // Also replace http/https variants.
        $old_no_scheme = preg_replace( '#^https?://#', '', $old_site_url );
        $new_no_scheme = preg_replace( '#^https?://#', '', $new_site_url );
        if ( $old_no_scheme !== $new_no_scheme ) {
            $changes += S360_Migration_Search_Replace::run( $old_no_scheme, $new_no_scheme );
        }

        // Replace file paths.
        $old_path = wp_parse_url( $old_site_url, PHP_URL_PATH );
        $new_path = wp_parse_url( $new_site_url, PHP_URL_PATH );
        if ( $old_path && $new_path && $old_path !== $new_path ) {
            $changes += S360_Migration_Search_Replace::run( $old_path, $new_path );
        }

        $log[] = "Search & replace completed: {$changes} changes.";

        // ── 6. Cleanup ──────────────────────────────────────────────────
        S360_Migration_Files::rmdir_recursive( $work_dir );

        // Flush rewrite rules and caches.
        flush_rewrite_rules();
        wp_cache_flush();

        return array(
            'success'  => true,
            'manifest' => $manifest,
            'log'      => $log,
            'changes'  => $changes,
        );
    }
}
