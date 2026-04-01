<?php
/**
 * REST API endpoints for remote push / pull migration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_API {

    private $export;
    private $import;
    private $namespace = 'shield360/v1';

    public function __construct( S360_Migration_Export $export, S360_Migration_Import $import ) {
        $this->export = $export;
        $this->import = $import;
    }

    public function register_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'wp_ajax_s360_export_package', array( $this, 'ajax_export_package' ) );
        add_action( 'wp_ajax_s360_import_package', array( $this, 'ajax_import_package' ) );
        add_action( 'wp_ajax_s360_push_migration', array( $this, 'ajax_push_migration' ) );
        add_action( 'wp_ajax_s360_pull_migration', array( $this, 'ajax_pull_migration' ) );
        add_action( 'wp_ajax_s360_system_info', array( $this, 'ajax_system_info' ) );
        add_action( 'wp_ajax_s360_download_package', array( $this, 'ajax_download_package' ) );
    }

    /**
     * Register REST API routes for remote site communication.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/handshake', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'rest_handshake' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        register_rest_route( $this->namespace, '/receive', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'rest_receive_package' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        register_rest_route( $this->namespace, '/package', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_serve_package' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        register_rest_route( $this->namespace, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_status' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );
    }

    /**
     * Verify the API key from the request.
     */
    public function verify_api_key( $request ) {
        $key = $request->get_header( 'X-S360-Key' );
        if ( ! $key ) {
            $key = $request->get_param( 'api_key' );
        }
        $site_key = get_option( 's360_migration_site_key', '' );
        return $key && hash_equals( $site_key, $key );
    }

    // ── REST Endpoints ──────────────────────────────────────────────────

    /**
     * Handshake – verify connection between sites.
     */
    public function rest_handshake( $request ) {
        return rest_ensure_response( array(
            'success'    => true,
            'site_url'   => site_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_ver' => S360_MIGRATION_VERSION,
            'php'        => phpversion(),
            'max_upload' => wp_max_upload_size(),
        ) );
    }

    /**
     * Receive a migration package from a remote site (push).
     */
    public function rest_receive_package( $request ) {
        $files = $request->get_file_params();
        if ( empty( $files['package'] ) ) {
            return new WP_Error( 's360_receive', 'No package file received.', array( 'status' => 400 ) );
        }

        $file = $files['package'];
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 's360_receive', 'Upload error: ' . $file['error'], array( 'status' => 400 ) );
        }

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $dest          = $migration_dir . '/' . sanitize_file_name( $file['name'] );
        move_uploaded_file( $file['tmp_name'], $dest );

        $options = json_decode( $request->get_param( 'options' ), true ) ?: array();

        $result = $this->import->import_package( $dest, $options );
        wp_delete_file( $dest );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * Serve a package for download (pull).
     */
    public function rest_serve_package( $request ) {
        $package_id = sanitize_text_field( $request->get_param( 'package_id' ) );
        if ( ! $package_id ) {
            return new WP_Error( 's360_package', 'Missing package_id.', array( 'status' => 400 ) );
        }

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $zip_path      = $migration_dir . '/' . sanitize_file_name( $package_id ) . '.zip';

        if ( ! file_exists( $zip_path ) ) {
            return new WP_Error( 's360_package', 'Package not found.', array( 'status' => 404 ) );
        }

        // Stream the file.
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        readfile( $zip_path );
        exit;
    }

    /**
     * Status check.
     */
    public function rest_status( $request ) {
        return rest_ensure_response( array(
            'status'  => 'ready',
            'version' => S360_MIGRATION_VERSION,
        ) );
    }

    // ── AJAX Handlers ───────────────────────────────────────────────────

    /**
     * AJAX: Create export package.
     */
    public function ajax_export_package() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // Increase limits for large sites.
        @set_time_limit( 900 );
        wp_raise_memory_limit( 'admin' );

        $options = array(
            'include_db'      => ! empty( $_POST['include_db'] ),
            'include_themes'  => ! empty( $_POST['include_themes'] ),
            'include_plugins' => ! empty( $_POST['include_plugins'] ),
            'include_uploads' => ! empty( $_POST['include_uploads'] ),
        );

        $result = $this->export->create_package( $options );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'package_id' => $result['package_id'],
            'size'       => size_format( $result['size'] ),
            'size_bytes' => $result['size'],
            'manifest'   => $result['manifest'],
        ) );
    }

    /**
     * AJAX: Import from uploaded package.
     */
    public function ajax_import_package() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        if ( empty( $_FILES['package'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        @set_time_limit( 900 );
        wp_raise_memory_limit( 'admin' );

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $file          = $_FILES['package'];
        $dest          = $migration_dir . '/' . sanitize_file_name( $file['name'] );
        move_uploaded_file( $file['tmp_name'], $dest );

        $options = array(
            'new_site_url'   => sanitize_url( wp_unslash( $_POST['new_site_url'] ?? '' ) ),
            'new_home_url'   => sanitize_url( wp_unslash( $_POST['new_home_url'] ?? '' ) ),
            'import_db'      => ! empty( $_POST['import_db'] ),
            'import_themes'  => ! empty( $_POST['import_themes'] ),
            'import_plugins' => ! empty( $_POST['import_plugins'] ),
            'import_uploads' => ! empty( $_POST['import_uploads'] ),
        );

        $result = $this->import->import_package( $dest, $options );
        wp_delete_file( $dest );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Push migration to remote site.
     */
    public function ajax_push_migration() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $remote_url = esc_url_raw( wp_unslash( $_POST['remote_url'] ?? '' ) );
        $remote_key = sanitize_text_field( wp_unslash( $_POST['remote_key'] ?? '' ) );

        if ( ! $remote_url || ! $remote_key ) {
            wp_send_json_error( 'Remote URL and API key are required.' );
        }

        @set_time_limit( 900 );
        wp_raise_memory_limit( 'admin' );

        // Step 1: Handshake with remote.
        $handshake = wp_remote_post( trailingslashit( $remote_url ) . 'wp-json/shield360/v1/handshake', array(
            'headers' => array( 'X-S360-Key' => $remote_key ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $handshake ) ) {
            wp_send_json_error( 'Cannot connect to remote: ' . $handshake->get_error_message() );
        }

        $hs_body = json_decode( wp_remote_retrieve_body( $handshake ), true );
        if ( empty( $hs_body['success'] ) ) {
            wp_send_json_error( 'Remote handshake failed. Check the API key.' );
        }

        // Step 2: Create local package.
        $options = array(
            'include_db'      => ! empty( $_POST['include_db'] ),
            'include_themes'  => ! empty( $_POST['include_themes'] ),
            'include_plugins' => ! empty( $_POST['include_plugins'] ),
            'include_uploads' => ! empty( $_POST['include_uploads'] ),
        );

        $package = $this->export->create_package( $options );
        if ( is_wp_error( $package ) ) {
            wp_send_json_error( 'Export failed: ' . $package->get_error_message() );
        }

        // Step 3: Push to remote.
        $import_options = wp_json_encode( array(
            'new_site_url' => $remote_url,
            'new_home_url' => $remote_url,
            'import_db'    => true,
            'import_themes'  => $options['include_themes'],
            'import_plugins' => $options['include_plugins'],
            'import_uploads' => $options['include_uploads'],
        ) );

        $boundary = wp_generate_password( 24, false );
        $body     = self::build_multipart_body( $boundary, $package['file'], $import_options );

        $push = wp_remote_post( trailingslashit( $remote_url ) . 'wp-json/shield360/v1/receive', array(
            'headers' => array(
                'X-S360-Key'   => $remote_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body'    => $body,
            'timeout' => 600,
        ) );

        // Cleanup local package.
        $this->export->delete_package( $package['package_id'] );

        if ( is_wp_error( $push ) ) {
            wp_send_json_error( 'Push failed: ' . $push->get_error_message() );
        }

        $push_body = json_decode( wp_remote_retrieve_body( $push ), true );
        wp_send_json_success( array(
            'message' => 'Migration pushed successfully!',
            'remote'  => $push_body,
        ) );
    }

    /**
     * AJAX: Pull migration from remote site.
     */
    public function ajax_pull_migration() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $remote_url = esc_url_raw( wp_unslash( $_POST['remote_url'] ?? '' ) );
        $remote_key = sanitize_text_field( wp_unslash( $_POST['remote_key'] ?? '' ) );
        $package_id = sanitize_text_field( wp_unslash( $_POST['package_id'] ?? '' ) );

        if ( ! $remote_url || ! $remote_key || ! $package_id ) {
            wp_send_json_error( 'Remote URL, API key, and package ID are required.' );
        }

        @set_time_limit( 900 );
        wp_raise_memory_limit( 'admin' );

        // Download package from remote.
        $download_url = add_query_arg(
            array( 'package_id' => $package_id, 'api_key' => $remote_key ),
            trailingslashit( $remote_url ) . 'wp-json/shield360/v1/package'
        );

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $dest          = $migration_dir . '/' . sanitize_file_name( $package_id ) . '.zip';

        $download = wp_remote_get( $download_url, array(
            'timeout'  => 600,
            'stream'   => true,
            'filename' => $dest,
            'headers'  => array( 'X-S360-Key' => $remote_key ),
        ) );

        if ( is_wp_error( $download ) ) {
            wp_send_json_error( 'Download failed: ' . $download->get_error_message() );
        }

        $options = array(
            'new_site_url'   => site_url(),
            'new_home_url'   => home_url(),
            'import_db'      => ! empty( $_POST['import_db'] ),
            'import_themes'  => ! empty( $_POST['import_themes'] ),
            'import_plugins' => ! empty( $_POST['import_plugins'] ),
            'import_uploads' => ! empty( $_POST['import_uploads'] ),
        );

        $result = $this->import->import_package( $dest, $options );
        wp_delete_file( $dest );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Download package locally.
     */
    public function ajax_download_package() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $package_id = sanitize_text_field( wp_unslash( $_GET['package_id'] ?? '' ) );
        if ( ! $package_id ) {
            wp_die( 'Missing package ID' );
        }

        $migration_dir = S360_Migration_Core::get_migration_dir();
        $zip_path      = $migration_dir . '/' . sanitize_file_name( $package_id ) . '.zip';

        if ( ! file_exists( $zip_path ) ) {
            wp_die( 'Package not found' );
        }

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        readfile( $zip_path );
        exit;
    }

    /**
     * AJAX: System info.
     */
    public function ajax_system_info() {
        check_ajax_referer( 's360_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        wp_send_json_success( array(
            'site_url'       => site_url(),
            'home_url'       => home_url(),
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => phpversion(),
            'max_upload'     => size_format( wp_max_upload_size() ),
            'memory_limit'   => ini_get( 'memory_limit' ),
            'max_execution'  => ini_get( 'max_execution_time' ),
            'zip_available'  => class_exists( 'ZipArchive' ),
            'disk_free'      => function_exists( 'disk_free_space' ) ? size_format( @disk_free_space( ABSPATH ) ) : 'N/A',
            'table_prefix'   => $GLOBALS['table_prefix'],
            'site_key'       => get_option( 's360_migration_site_key', '' ),
            'active_theme'   => get_stylesheet(),
            'active_plugins' => get_option( 'active_plugins', array() ),
        ) );
    }

    /**
     * Build multipart/form-data body for file upload via wp_remote_post.
     */
    private static function build_multipart_body( $boundary, $file_path, $options_json ) {
        $body  = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"options\"\r\n\r\n";
        $body .= $options_json . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="package"; filename="' . basename( $file_path ) . "\"\r\n";
        $body .= "Content-Type: application/zip\r\n\r\n";
        $body .= file_get_contents( $file_path ) . "\r\n";
        $body .= "--{$boundary}--\r\n";
        return $body;
    }
}
