<?php
/**
 * Database export / import helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_DB {

    /**
     * Export the full database to a SQL file.
     *
     * @param  string $filepath Destination .sql file path.
     * @return bool|WP_Error
     */
    public static function export( $filepath ) {
        global $wpdb;

        $handle = fopen( $filepath, 'w' );
        if ( ! $handle ) {
            return new WP_Error( 's360_db_export', 'Cannot open file for writing.' );
        }

        fwrite( $handle, "-- Shield360 AI Migration Database Export\n" );
        fwrite( $handle, "-- Generated: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n" );
        fwrite( $handle, "-- WordPress: " . get_bloginfo( 'version' ) . "\n" );
        fwrite( $handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" );
        fwrite( $handle, "SET NAMES utf8mb4;\n\n" );

        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );

        foreach ( $tables as $table ) {
            // Table structure.
            $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
            fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n" );
            fwrite( $handle, $create[1] . ";\n\n" );

            // Table data in batches.
            $offset = 0;
            $batch  = 500;
            while ( true ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT {$offset}, {$batch}", ARRAY_N );
                if ( empty( $rows ) ) {
                    break;
                }

                foreach ( $rows as $row ) {
                    $values = array_map( function ( $v ) use ( $wpdb ) {
                        if ( is_null( $v ) ) {
                            return 'NULL';
                        }
                        return "'" . esc_sql( $v ) . "'";
                    }, $row );
                    fwrite( $handle, "INSERT INTO `{$table}` VALUES (" . implode( ',', $values ) . ");\n" );
                }

                $offset += $batch;
            }
            fwrite( $handle, "\n" );
        }

        fclose( $handle );
        return true;
    }

    /**
     * Import a SQL file into the database.
     *
     * @param  string $filepath Path to the .sql file.
     * @return bool|WP_Error
     */
    public static function import( $filepath ) {
        global $wpdb;

        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 's360_db_import', 'SQL file not found.' );
        }

        $handle = fopen( $filepath, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 's360_db_import', 'Cannot open SQL file.' );
        }

        $query = '';
        while ( ( $line = fgets( $handle ) ) !== false ) {
            $trimmed = trim( $line );

            // Skip comments and empty lines.
            if ( empty( $trimmed ) || strpos( $trimmed, '--' ) === 0 ) {
                continue;
            }

            $query .= $line;

            // Execute on statement end.
            if ( substr( $trimmed, -1 ) === ';' ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query( $query );
                $query = '';
            }
        }

        fclose( $handle );
        return true;
    }
}
