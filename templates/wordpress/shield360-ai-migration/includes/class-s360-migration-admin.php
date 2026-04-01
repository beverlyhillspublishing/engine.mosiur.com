<?php
/**
 * Admin UI – menu pages, scripts, and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class S360_Migration_Admin {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_menu() {
        add_menu_page(
            'Shield360 AI Migration',
            'Shield360 Migration',
            'manage_options',
            's360-migration',
            array( $this, 'render_page' ),
            'dashicons-migrate',
            80
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 's360-migration' ) === false ) {
            return;
        }

        wp_enqueue_style(
            's360-migration-admin',
            S360_MIGRATION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            S360_MIGRATION_VERSION
        );

        wp_enqueue_script(
            's360-migration-admin',
            S360_MIGRATION_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            S360_MIGRATION_VERSION,
            true
        );

        wp_localize_script( 's360-migration-admin', 's360Migration', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 's360_migration_nonce' ),
            'siteUrl'  => site_url(),
            'homeUrl'  => home_url(),
            'siteKey'  => get_option( 's360_migration_site_key', '' ),
        ) );
    }

    /**
     * Render the main admin page.
     */
    public function render_page() {
        $site_key = get_option( 's360_migration_site_key', '' );
        ?>
        <div class="wrap s360-wrap">
            <div class="s360-header">
                <h1><span class="dashicons dashicons-shield"></span> Shield360 AI Migration</h1>
                <p class="s360-subtitle">Migrate your entire WordPress site with one click</p>
            </div>

            <!-- Tab Navigation -->
            <nav class="s360-tabs">
                <a href="#" class="s360-tab active" data-tab="export">
                    <span class="dashicons dashicons-upload"></span> Export
                </a>
                <a href="#" class="s360-tab" data-tab="import">
                    <span class="dashicons dashicons-download"></span> Import
                </a>
                <a href="#" class="s360-tab" data-tab="push">
                    <span class="dashicons dashicons-cloud-upload"></span> Push to Server
                </a>
                <a href="#" class="s360-tab" data-tab="pull">
                    <span class="dashicons dashicons-cloud-saved"></span> Pull from Server
                </a>
                <a href="#" class="s360-tab" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span> Settings
                </a>
            </nav>

            <!-- Export Tab -->
            <div class="s360-panel active" id="s360-tab-export">
                <h2>Export Site</h2>
                <p>Create a migration package of your entire site. Download it and import on the destination server.</p>

                <table class="form-table">
                    <tr>
                        <th>Components to Export</th>
                        <td>
                            <label><input type="checkbox" id="export-db" checked> <strong>Database</strong> – All tables, posts, pages, users, settings</label><br>
                            <label><input type="checkbox" id="export-themes" checked> <strong>Themes</strong> – All installed themes</label><br>
                            <label><input type="checkbox" id="export-plugins" checked> <strong>Plugins</strong> – All installed plugins</label><br>
                            <label><input type="checkbox" id="export-uploads" checked> <strong>Media Uploads</strong> – All media files</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary button-hero" id="s360-btn-export">
                        <span class="dashicons dashicons-migrate"></span> Create Migration Package
                    </button>
                </p>

                <div id="s360-export-progress" class="s360-progress" style="display:none;">
                    <div class="s360-progress-bar"><div class="s360-progress-fill"></div></div>
                    <p class="s360-progress-text">Preparing export...</p>
                </div>

                <div id="s360-export-result" class="s360-result" style="display:none;"></div>
            </div>

            <!-- Import Tab -->
            <div class="s360-panel" id="s360-tab-import">
                <h2>Import Site</h2>
                <p>Upload a Shield360 migration package to restore or migrate a site here.</p>

                <div class="s360-upload-zone" id="s360-upload-zone">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <p>Drag & drop your migration package here<br>or click to browse</p>
                    <input type="file" id="s360-import-file" accept=".zip" style="display:none;">
                </div>

                <table class="form-table" style="margin-top:20px;">
                    <tr>
                        <th>New Site URL</th>
                        <td><input type="url" id="import-site-url" class="regular-text" value="<?php echo esc_attr( site_url() ); ?>" placeholder="https://newsite.com"></td>
                    </tr>
                    <tr>
                        <th>Components to Import</th>
                        <td>
                            <label><input type="checkbox" id="import-db" checked> Database</label><br>
                            <label><input type="checkbox" id="import-themes" checked> Themes</label><br>
                            <label><input type="checkbox" id="import-plugins" checked> Plugins</label><br>
                            <label><input type="checkbox" id="import-uploads" checked> Media Uploads</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary button-hero" id="s360-btn-import" disabled>
                        <span class="dashicons dashicons-database-import"></span> Start Import
                    </button>
                </p>

                <div id="s360-import-progress" class="s360-progress" style="display:none;">
                    <div class="s360-progress-bar"><div class="s360-progress-fill"></div></div>
                    <p class="s360-progress-text">Importing...</p>
                </div>

                <div id="s360-import-result" class="s360-result" style="display:none;"></div>
            </div>

            <!-- Push Tab -->
            <div class="s360-panel" id="s360-tab-push">
                <h2>Push to Remote Server</h2>
                <p>Automatically export and push this site to another server running Shield360 AI Migration.</p>

                <table class="form-table">
                    <tr>
                        <th>Destination Site URL</th>
                        <td><input type="url" id="push-remote-url" class="regular-text" placeholder="https://destination-site.com"></td>
                    </tr>
                    <tr>
                        <th>Destination API Key</th>
                        <td><input type="text" id="push-remote-key" class="regular-text" placeholder="Paste the remote site's API key"></td>
                    </tr>
                    <tr>
                        <th>Components</th>
                        <td>
                            <label><input type="checkbox" id="push-db" checked> Database</label><br>
                            <label><input type="checkbox" id="push-themes" checked> Themes</label><br>
                            <label><input type="checkbox" id="push-plugins" checked> Plugins</label><br>
                            <label><input type="checkbox" id="push-uploads" checked> Media Uploads</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary button-hero" id="s360-btn-push">
                        <span class="dashicons dashicons-cloud-upload"></span> Push Migration
                    </button>
                </p>

                <div id="s360-push-progress" class="s360-progress" style="display:none;">
                    <div class="s360-progress-bar"><div class="s360-progress-fill"></div></div>
                    <p class="s360-progress-text">Pushing to remote server...</p>
                </div>

                <div id="s360-push-result" class="s360-result" style="display:none;"></div>
            </div>

            <!-- Pull Tab -->
            <div class="s360-panel" id="s360-tab-pull">
                <h2>Pull from Remote Server</h2>
                <p>Pull a migration package from another site running Shield360 AI Migration.</p>

                <table class="form-table">
                    <tr>
                        <th>Source Site URL</th>
                        <td><input type="url" id="pull-remote-url" class="regular-text" placeholder="https://source-site.com"></td>
                    </tr>
                    <tr>
                        <th>Source API Key</th>
                        <td><input type="text" id="pull-remote-key" class="regular-text" placeholder="Paste the source site's API key"></td>
                    </tr>
                    <tr>
                        <th>Package ID</th>
                        <td><input type="text" id="pull-package-id" class="regular-text" placeholder="e.g. s360_abc123def456"></td>
                    </tr>
                    <tr>
                        <th>Components</th>
                        <td>
                            <label><input type="checkbox" id="pull-db" checked> Database</label><br>
                            <label><input type="checkbox" id="pull-themes" checked> Themes</label><br>
                            <label><input type="checkbox" id="pull-plugins" checked> Plugins</label><br>
                            <label><input type="checkbox" id="pull-uploads" checked> Media Uploads</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary button-hero" id="s360-btn-pull">
                        <span class="dashicons dashicons-cloud-saved"></span> Pull Migration
                    </button>
                </p>

                <div id="s360-pull-progress" class="s360-progress" style="display:none;">
                    <div class="s360-progress-bar"><div class="s360-progress-fill"></div></div>
                    <p class="s360-progress-text">Pulling from remote server...</p>
                </div>

                <div id="s360-pull-result" class="s360-result" style="display:none;"></div>
            </div>

            <!-- Settings Tab -->
            <div class="s360-panel" id="s360-tab-settings">
                <h2>Settings & System Info</h2>

                <table class="form-table">
                    <tr>
                        <th>Your Site API Key</th>
                        <td>
                            <input type="text" id="s360-site-key" class="regular-text" value="<?php echo esc_attr( $site_key ); ?>" readonly>
                            <button class="button" id="s360-copy-key">Copy</button>
                            <p class="description">Share this key with the remote site to allow push/pull migrations.</p>
                        </td>
                    </tr>
                </table>

                <h3>System Information</h3>
                <div id="s360-system-info" class="s360-info-grid">
                    <p>Loading system info...</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="s360-footer">
                <p>Shield360 AI Migration v<?php echo esc_html( S360_MIGRATION_VERSION ); ?> &mdash; Secure, fast, one-click WordPress migration.</p>
            </div>
        </div>
        <?php
    }
}
