<?php
/**
 * Serialization-safe search & replace for database values.
 * Handles serialized strings, JSON, and plain text.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Search_Replace {

    /**
     * Run search & replace across all WordPress tables.
     *
     * @param string $search  The old URL / path.
     * @param string $replace The new URL / path.
     * @return int Number of changes made.
     */
    public static function run( $search, $replace ) {
        global $wpdb;

        if ( $search === $replace ) {
            return 0;
        }

        $tables  = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
        $changes = 0;

        foreach ( $tables as $table ) {
            $columns = $wpdb->get_results( "DESCRIBE `{$table}`" );
            $text_columns = array();

            foreach ( $columns as $col ) {
                if ( preg_match( '/(text|varchar|longtext|mediumtext|char)/i', $col->Type ) ) {
                    $text_columns[] = $col->Field;
                }
            }

            if ( empty( $text_columns ) ) {
                continue;
            }

            // Find primary key.
            $primary_key = null;
            foreach ( $columns as $col ) {
                if ( $col->Key === 'PRI' ) {
                    $primary_key = $col->Field;
                    break;
                }
            }

            if ( ! $primary_key ) {
                continue;
            }

            // Build WHERE clause to only fetch rows containing the search string.
            $where_parts = array();
            foreach ( $text_columns as $col_name ) {
                $where_parts[] = "`{$col_name}` LIKE '%" . esc_sql( $wpdb->esc_like( $search ) ) . "%'";
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                "SELECT * FROM `{$table}` WHERE " . implode( ' OR ', $where_parts ),
                ARRAY_A
            );

            foreach ( $rows as $row ) {
                $update = array();
                foreach ( $text_columns as $col_name ) {
                    if ( ! isset( $row[ $col_name ] ) || strpos( $row[ $col_name ], $search ) === false ) {
                        continue;
                    }
                    $new_value = self::replace_value( $row[ $col_name ], $search, $replace );
                    if ( $new_value !== $row[ $col_name ] ) {
                        $update[ $col_name ] = $new_value;
                    }
                }

                if ( ! empty( $update ) ) {
                    $wpdb->update( $table, $update, array( $primary_key => $row[ $primary_key ] ) );
                    $changes += count( $update );
                }
            }
        }

        return $changes;
    }

    /**
     * Replace a value, handling serialized data safely.
     *
     * @param  string $value   The original value.
     * @param  string $search  Search string.
     * @param  string $replace Replace string.
     * @return string
     */
    public static function replace_value( $value, $search, $replace ) {
        // Try to unserialize.
        $unserialized = @unserialize( $value );
        if ( $unserialized !== false || $value === 'b:0;' ) {
            $replaced = self::replace_recursive( $unserialized, $search, $replace );
            return serialize( $replaced );
        }

        // Try JSON.
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $replaced = self::replace_recursive( $decoded, $search, $replace );
            return wp_json_encode( $replaced );
        }

        // Plain string.
        return str_replace( $search, $replace, $value );
    }

    /**
     * Recursively replace within arrays / objects.
     *
     * @param  mixed  $data    Data structure.
     * @param  string $search  Search string.
     * @param  string $replace Replace string.
     * @return mixed
     */
    private static function replace_recursive( $data, $search, $replace ) {
        if ( is_string( $data ) ) {
            return str_replace( $search, $replace, $data );
        }

        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = self::replace_recursive( $value, $search, $replace );
            }
        }

        return $data;
    }
}
