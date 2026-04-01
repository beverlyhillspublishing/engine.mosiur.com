/**
 * Shield360 AI Migration – Admin JavaScript
 */
(function ($) {
    'use strict';

    var config = window.s360Migration || {};

    // ── Tab Navigation ──────────────────────────────────────────────────
    $(document).on('click', '.s360-tab', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.s360-tab').removeClass('active');
        $(this).addClass('active');
        $('.s360-panel').removeClass('active');
        $('#s360-tab-' + tab).addClass('active');

        if (tab === 'settings') {
            loadSystemInfo();
        }
    });

    // ── Helpers ─────────────────────────────────────────────────────────
    function showProgress(section, text, percent) {
        var $p = $('#s360-' + section + '-progress');
        $p.show();
        $p.find('.s360-progress-text').text(text);
        if (typeof percent === 'number') {
            $p.find('.s360-progress-fill').css('width', percent + '%');
        }
    }

    function hideProgress(section) {
        $('#s360-' + section + '-progress').hide();
    }

    function showResult(section, success, html) {
        var $r = $('#s360-' + section + '-result');
        $r.removeClass('success error').addClass(success ? 'success' : 'error');
        $r.html(html).show();
    }

    // ── Export ───────────────────────────────────────────────────────────
    $('#s360-btn-export').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        showProgress('export', 'Creating migration package... This may take several minutes for large sites.', 30);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 's360_export_package',
                nonce: config.nonce,
                include_db: $('#export-db').is(':checked') ? 1 : 0,
                include_themes: $('#export-themes').is(':checked') ? 1 : 0,
                include_plugins: $('#export-plugins').is(':checked') ? 1 : 0,
                include_uploads: $('#export-uploads').is(':checked') ? 1 : 0
            },
            timeout: 900000,
            success: function (resp) {
                hideProgress('export');
                $btn.prop('disabled', false);

                if (resp.success) {
                    var d = resp.data;
                    var downloadUrl = config.ajaxUrl + '?action=s360_download_package&nonce=' + config.nonce + '&package_id=' + d.package_id;
                    showResult('export', true,
                        '<h3>Package Created Successfully!</h3>' +
                        '<p><strong>Package ID:</strong> ' + d.package_id + '</p>' +
                        '<p><strong>Size:</strong> ' + d.size + '</p>' +
                        '<p><a href="' + downloadUrl + '" class="button button-primary">Download Package</a></p>' +
                        '<p class="description">Use this package to import on the destination server, or use the Package ID for remote pull.</p>'
                    );
                } else {
                    showResult('export', false, '<h3>Export Failed</h3><p>' + resp.data + '</p>');
                }
            },
            error: function (xhr) {
                hideProgress('export');
                $btn.prop('disabled', false);
                showResult('export', false, '<h3>Export Failed</h3><p>Request error: ' + xhr.statusText + '</p>');
            }
        });
    });

    // ── Import (File Upload) ────────────────────────────────────────────
    var selectedFile = null;

    var $zone = $('#s360-upload-zone');
    $zone.on('click', function () { $('#s360-import-file').click(); });
    $zone.on('dragover', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
    $zone.on('dragleave drop', function () { $(this).removeClass('dragover'); });
    $zone.on('drop', function (e) {
        e.preventDefault();
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) { handleFileSelect(files[0]); }
    });

    $('#s360-import-file').on('change', function () {
        if (this.files.length) { handleFileSelect(this.files[0]); }
    });

    function handleFileSelect(file) {
        if (!file.name.endsWith('.zip')) {
            alert('Please select a .zip migration package.');
            return;
        }
        selectedFile = file;
        $zone.addClass('has-file').find('p').html('<strong>' + file.name + '</strong><br>(' + formatSize(file.size) + ')');
        $('#s360-btn-import').prop('disabled', false);
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }

    $('#s360-btn-import').on('click', function () {
        if (!selectedFile) return;

        var $btn = $(this).prop('disabled', true);
        showProgress('import', 'Uploading and importing... This may take several minutes.', 20);

        var formData = new FormData();
        formData.append('action', 's360_import_package');
        formData.append('nonce', config.nonce);
        formData.append('package', selectedFile);
        formData.append('new_site_url', $('#import-site-url').val());
        formData.append('new_home_url', $('#import-site-url').val());
        formData.append('import_db', $('#import-db').is(':checked') ? 1 : 0);
        formData.append('import_themes', $('#import-themes').is(':checked') ? 1 : 0);
        formData.append('import_plugins', $('#import-plugins').is(':checked') ? 1 : 0);
        formData.append('import_uploads', $('#import-uploads').is(':checked') ? 1 : 0);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 900000,
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 60);
                        showProgress('import', 'Uploading... ' + pct + '%', pct);
                    }
                });
                return xhr;
            },
            success: function (resp) {
                hideProgress('import');
                $btn.prop('disabled', false);

                if (resp.success) {
                    var log = resp.data.log || [];
                    var html = '<h3>Import Completed!</h3><ul>';
                    log.forEach(function (msg) { html += '<li>' + msg + '</li>'; });
                    html += '</ul><p class="description">You may need to re-save permalinks and clear any caches.</p>';
                    showResult('import', true, html);
                } else {
                    showResult('import', false, '<h3>Import Failed</h3><p>' + resp.data + '</p>');
                }
            },
            error: function (xhr) {
                hideProgress('import');
                $btn.prop('disabled', false);
                showResult('import', false, '<h3>Import Failed</h3><p>Request error: ' + xhr.statusText + '</p>');
            }
        });
    });

    // ── Push Migration ──────────────────────────────────────────────────
    $('#s360-btn-push').on('click', function () {
        var remoteUrl = $('#push-remote-url').val().replace(/\/+$/, '');
        var remoteKey = $('#push-remote-key').val();

        if (!remoteUrl || !remoteKey) {
            alert('Please enter the destination URL and API key.');
            return;
        }

        if (!confirm('This will overwrite the destination site. Continue?')) {
            return;
        }

        var $btn = $(this).prop('disabled', true);
        showProgress('push', 'Connecting to remote server and pushing migration...', 20);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 's360_push_migration',
                nonce: config.nonce,
                remote_url: remoteUrl,
                remote_key: remoteKey,
                include_db: $('#push-db').is(':checked') ? 1 : 0,
                include_themes: $('#push-themes').is(':checked') ? 1 : 0,
                include_plugins: $('#push-plugins').is(':checked') ? 1 : 0,
                include_uploads: $('#push-uploads').is(':checked') ? 1 : 0
            },
            timeout: 900000,
            success: function (resp) {
                hideProgress('push');
                $btn.prop('disabled', false);

                if (resp.success) {
                    showResult('push', true, '<h3>Migration Pushed Successfully!</h3><p>Your site has been migrated to <strong>' + remoteUrl + '</strong>.</p>');
                } else {
                    showResult('push', false, '<h3>Push Failed</h3><p>' + resp.data + '</p>');
                }
            },
            error: function (xhr) {
                hideProgress('push');
                $btn.prop('disabled', false);
                showResult('push', false, '<h3>Push Failed</h3><p>Request error: ' + xhr.statusText + '</p>');
            }
        });
    });

    // ── Pull Migration ──────────────────────────────────────────────────
    $('#s360-btn-pull').on('click', function () {
        var remoteUrl = $('#pull-remote-url').val().replace(/\/+$/, '');
        var remoteKey = $('#pull-remote-key').val();
        var packageId = $('#pull-package-id').val();

        if (!remoteUrl || !remoteKey || !packageId) {
            alert('Please fill in all fields.');
            return;
        }

        if (!confirm('This will overwrite your current site with data from the remote server. Continue?')) {
            return;
        }

        var $btn = $(this).prop('disabled', true);
        showProgress('pull', 'Downloading and importing from remote server...', 20);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 's360_pull_migration',
                nonce: config.nonce,
                remote_url: remoteUrl,
                remote_key: remoteKey,
                package_id: packageId,
                import_db: $('#pull-db').is(':checked') ? 1 : 0,
                import_themes: $('#pull-themes').is(':checked') ? 1 : 0,
                import_plugins: $('#pull-plugins').is(':checked') ? 1 : 0,
                import_uploads: $('#pull-uploads').is(':checked') ? 1 : 0
            },
            timeout: 900000,
            success: function (resp) {
                hideProgress('pull');
                $btn.prop('disabled', false);

                if (resp.success) {
                    var log = resp.data.log || [];
                    var html = '<h3>Pull Completed!</h3><ul>';
                    log.forEach(function (msg) { html += '<li>' + msg + '</li>'; });
                    html += '</ul>';
                    showResult('pull', true, html);
                } else {
                    showResult('pull', false, '<h3>Pull Failed</h3><p>' + resp.data + '</p>');
                }
            },
            error: function (xhr) {
                hideProgress('pull');
                $btn.prop('disabled', false);
                showResult('pull', false, '<h3>Pull Failed</h3><p>Request error: ' + xhr.statusText + '</p>');
            }
        });
    });

    // ── Copy API Key ────────────────────────────────────────────────────
    $('#s360-copy-key').on('click', function () {
        var $input = $('#s360-site-key');
        $input[0].select();
        document.execCommand('copy');
        $(this).text('Copied!');
        setTimeout(function () { $('#s360-copy-key').text('Copy'); }, 2000);
    });

    // ── System Info ─────────────────────────────────────────────────────
    function loadSystemInfo() {
        $.post(config.ajaxUrl, {
            action: 's360_system_info',
            nonce: config.nonce
        }, function (resp) {
            if (!resp.success) return;

            var d = resp.data;
            var html = '';

            var items = [
                { label: 'Site URL', value: d.site_url },
                { label: 'Home URL', value: d.home_url },
                { label: 'WordPress', value: d.wp_version },
                { label: 'PHP Version', value: d.php_version },
                { label: 'Max Upload', value: d.max_upload },
                { label: 'Memory Limit', value: d.memory_limit },
                { label: 'Max Execution', value: d.max_execution + 's' },
                { label: 'ZipArchive', value: d.zip_available ? 'Available' : 'Missing', cls: d.zip_available ? 'ok' : 'bad' },
                { label: 'Free Disk Space', value: d.disk_free },
                { label: 'Table Prefix', value: d.table_prefix },
                { label: 'Active Theme', value: d.active_theme },
                { label: 'Active Plugins', value: d.active_plugins.length + ' plugins' }
            ];

            items.forEach(function (item) {
                html += '<div class="s360-info-item">';
                html += '<div class="label">' + item.label + '</div>';
                html += '<div class="value' + (item.cls ? ' ' + item.cls : '') + '">' + item.value + '</div>';
                html += '</div>';
            });

            $('#s360-system-info').html(html);
        });
    }

})(jQuery);
