<?php
/**
 * File system helpers – archive & extract WordPress files.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Files {

    /**
     * Create a ZIP archive of the given directory.
     *
     * @param  string $source      Directory to archive.
     * @param  string $destination ZIP file path.
     * @param  array  $excludes    Relative paths to exclude.
     * @return bool|WP_Error
     */
    public static function create_zip( $source, $destination, $excludes = array() ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 's360_zip', 'ZipArchive PHP extension is required.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new WP_Error( 's360_zip', 'Cannot create ZIP archive.' );
        }

        $source = rtrim( $source, '/' );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $relative = str_replace( $source . '/', '', $item->getPathname() );

            // Check exclusions.
            $skip = false;
            foreach ( $excludes as $exclude ) {
                if ( strpos( $relative, $exclude ) === 0 ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) {
                continue;
            }

            if ( $item->isDir() ) {
                $zip->addEmptyDir( $relative );
            } else {
                $zip->addFile( $item->getPathname(), $relative );
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Extract a ZIP archive to the given directory.
     *
     * @param  string $zip_path    Path to the ZIP file.
     * @param  string $destination Extraction directory.
     * @return bool|WP_Error
     */
    public static function extract_zip( $zip_path, $destination ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 's360_zip', 'ZipArchive PHP extension is required.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return new WP_Error( 's360_zip', 'Cannot open ZIP archive.' );
        }

        // Validate paths to prevent directory traversal.
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( strpos( $name, '..' ) !== false ) {
                $zip->close();
                return new WP_Error( 's360_zip', 'ZIP archive contains unsafe paths.' );
            }
        }

        $zip->extractTo( $destination );
        $zip->close();
        return true;
    }

    /**
     * Copy a directory recursively.
     *
     * @param string $source      Source directory.
     * @param string $destination Destination directory.
     */
    public static function copy_dir( $source, $destination ) {
        if ( ! is_dir( $destination ) ) {
            wp_mkdir_p( $destination );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $dest_path = $destination . '/' . $iterator->getSubPathname();
            if ( $item->isDir() ) {
                wp_mkdir_p( $dest_path );
            } else {
                copy( $item->getPathname(), $dest_path );
            }
        }
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir Directory path.
     */
    public static function rmdir_recursive( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $item ) {
            if ( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                wp_delete_file( $item->getPathname() );
            }
        }
        rmdir( $dir );
    }
}
