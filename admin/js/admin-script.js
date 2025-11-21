/**
 * ChkLinkOut Admin Script - Version 2.0
 */

(function($) {
    'use strict';

    let currentScanId = null;
    let currentPage = 1;
    let currentFilters = {
        search: '',
        post_type: '',
        is_broken: null,
        orderby: 'id',
        order: 'DESC'
    };

    $(document).ready(function() {
        // Button click handlers
        $('#chklinkout-scan-btn').on('click', startNewScan);
        $('#chklinkout-load-cache-btn').on('click', loadCachedScan);
        $('#chklinkout-check-broken-btn').on('click', checkBrokenLinks);
        $('#chklinkout-export-csv').on('click', exportCSV);
        $('#chklinkout-export-json').on('click', exportJSON);

        // Filter handlers
        $('#chklinkout-filter-type').on('change', function() {
            if (!currentScanId) return;
            currentFilters.post_type = $(this).val();
            currentPage = 1;
            loadResults();
        });

        $('#chklinkout-filter-broken').on('change', function() {
            if (!currentScanId) return;
            const val = $(this).val();
            currentFilters.is_broken = val === '' ? null : val;
            currentPage = 1;
            loadResults();
        });

        // Search with debounce
        let searchTimeout;
        $('#chklinkout-search').on('input', function() {
            if (!currentScanId) return;
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                currentFilters.search = $('#chklinkout-search').val();
                currentPage = 1;
                loadResults();
            }, 500);
        });

        // Try loading cached scan on page load
        loadCachedScan();
    });

    /**
     * Start new scan
     */
    function startNewScan() {
        if (currentScanId && !confirm(chklinkoutAjax.i18n.confirm_new_scan)) {
            return;
        }

        $('#chklinkout-scan-btn').prop('disabled', true);
        $('#chklinkout-results').hide();
        $('#chklinkout-error').hide();
        showProgress(chklinkoutAjax.i18n.scanning, 0);

        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_start_scan',
                nonce: chklinkoutAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentScanId = response.data.scan_id;
                    processBatches(response.data);
                } else {
                    showError(response.data.message);
                    $('#chklinkout-scan-btn').prop('disabled', false);
                    hideProgress();
                }
            },
            error: function(xhr, status, error) {
                showError(chklinkoutAjax.i18n.error + ': ' + error);
                $('#chklinkout-scan-btn').prop('disabled', false);
                hideProgress();
            }
        });
    }

    /**
     * Process all batches
     */
    function processBatches(scanInfo) {
        const totalPosts = scanInfo.total_posts;
        const batchSize = scanInfo.batch_size;
        let currentOffset = 0;
        let processedPosts = 0;

        function processBatch() {
            $.ajax({
                url: chklinkoutAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'chklinkout_scan_batch',
                    nonce: chklinkoutAjax.nonce,
                    scan_id: currentScanId,
                    offset: currentOffset
                },
                success: function(response) {
                    if (response.success) {
                        processedPosts += response.data.processed;
                        currentOffset = response.data.offset;

                        const progress = Math.min(100, Math.round((processedPosts / totalPosts) * 100));
                        updateProgress(chklinkoutAjax.i18n.scanning + ' ' + processedPosts + '/' + totalPosts, progress);

                        if (currentOffset < totalPosts) {
                            // Continue with next batch
                            processBatch();
                        } else {
                            // All batches completed
                            completeScan();
                        }
                    } else {
                        showError(response.data.message);
                        $('#chklinkout-scan-btn').prop('disabled', false);
                        hideProgress();
                    }
                },
                error: function() {
                    showError(chklinkoutAjax.i18n.error);
                    $('#chklinkout-scan-btn').prop('disabled', false);
                    hideProgress();
                }
            });
        }

        processBatch();
    }

    /**
     * Complete scan
     */
    function completeScan() {
        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_complete_scan',
                nonce: chklinkoutAjax.nonce,
                scan_id: currentScanId
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(chklinkoutAjax.i18n.completed, 100);
                    setTimeout(function() {
                        hideProgress();
                        loadResults();
                        $('#chklinkout-check-broken-btn').show();
                    }, 1000);
                } else {
                    showError(response.data.message);
                }
                $('#chklinkout-scan-btn').prop('disabled', false);
            },
            error: function() {
                showError(chklinkoutAjax.i18n.error);
                $('#chklinkout-scan-btn').prop('disabled', false);
                hideProgress();
            }
        });
    }

    /**
     * Check broken links
     */
    function checkBrokenLinks() {
        if (!currentScanId) return;

        $('#chklinkout-check-broken-btn').prop('disabled', true);
        showProgress(chklinkoutAjax.i18n.checking, 0);

        let batch = 0;

        function checkBatch() {
            $.ajax({
                url: chklinkoutAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'chklinkout_check_broken_links',
                    nonce: chklinkoutAjax.nonce,
                    scan_id: currentScanId,
                    batch: batch
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.completed) {
                            updateProgress(chklinkoutAjax.i18n.completed, 100);
                            setTimeout(function() {
                                hideProgress();
                                loadResults();
                                $('#chklinkout-check-broken-btn').prop('disabled', false);
                            }, 1000);
                        } else {
                            updateProgress(chklinkoutAjax.i18n.checking + ' (' + data.checked + ' links)', 50);
                            batch++;
                            checkBatch();
                        }
                    } else {
                        showError(response.data.message);
                        $('#chklinkout-check-broken-btn').prop('disabled', false);
                        hideProgress();
                    }
                },
                error: function() {
                    showError(chklinkoutAjax.i18n.error);
                    $('#chklinkout-check-broken-btn').prop('disabled', false);
                    hideProgress();
                }
            });
        }

        checkBatch();
    }

    /**
     * Load cached scan
     */
    function loadCachedScan() {
        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_get_cached_scan',
                nonce: chklinkoutAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentScanId = response.data.scan_id;
                    loadResults();
                    $('#chklinkout-check-broken-btn').show();
                } else {
                    // No cached scan available - show welcome message
                    displayWelcomeMessage();
                }
            },
            error: function() {
                // Silent fail on page load
                displayWelcomeMessage();
            }
        });
    }

    /**
     * Load results from database
     */
    function loadResults() {
        if (!currentScanId) {
            console.log('ChkLinkOut: No scan ID available');
            return;
        }

        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_get_results',
                nonce: chklinkoutAjax.nonce,
                scan_id: currentScanId,
                page: currentPage,
                per_page: 50,
                search: currentFilters.search,
                post_type: currentFilters.post_type,
                is_broken: currentFilters.is_broken,
                orderby: currentFilters.orderby,
                order: currentFilters.order
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    showError(response.data.message || 'Có lỗi xảy ra khi tải dữ liệu');
                }
            },
            error: function(xhr, status, error) {
                console.error('ChkLinkOut Error:', error);
                showError('Không thể tải kết quả. Vui lòng thử lại.');
            }
        });
    }

    /**
     * Display results
     */
    function displayResults(data) {
        if (data.total === 0) {
            displayEmptyState();
            return;
        }

        displayStatistics(data.stats);
        displayTable(data.links);
        displayPagination(data);
        $('#chklinkout-results').show();
    }

    /**
     * Display statistics
     */
    function displayStatistics(stats) {
        let statsHTML = `
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.total_posts || 0}</div>
                <div class="stat-label">Tổng số mục</div>
            </div>
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.total_links || 0}</div>
                <div class="stat-label">Tổng External Links</div>
            </div>
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.total_domains || 0}</div>
                <div class="stat-label">Unique Domains</div>
            </div>
            <div class="chklinkout-stat-box">
                <div class="stat-number" style="color: ${stats.total_broken > 0 ? '#dc3232' : '#46b450'}">${stats.total_broken || 0}</div>
                <div class="stat-label">Broken Links</div>
            </div>
        `;

        // Add top domains
        if (stats.top_domains && stats.top_domains.length > 0) {
            let domainsHTML = '<div class="chklinkout-stat-details"><h3>Top Domains</h3><ul>';
            stats.top_domains.forEach(function(item) {
                domainsHTML += `<li><span>${escapeHtml(item.domain)}</span><span class="domain-count">${item.count}</span></li>`;
            });
            domainsHTML += '</ul></div>';
            statsHTML = statsHTML + domainsHTML;
        }

        $('#chklinkout-stats').html(statsHTML);
    }

    /**
     * Display table
     */
    function displayTable(links) {
        let tableHTML = `
            <table class="chklinkout-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Tiêu đề / Loại</th>
                        <th style="width: 45%;">External Link</th>
                        <th style="width: 10%;">HTTP Status</th>
                        <th style="width: 10%;">Vị trí</th>
                        <th style="width: 10%;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
        `;

        // Group links by post
        const groupedLinks = {};
        links.forEach(function(link) {
            const key = link.post_id + '_' + link.post_type;
            if (!groupedLinks[key]) {
                groupedLinks[key] = {
                    post_id: link.post_id,
                    post_title: link.post_title,
                    post_type: link.post_type,
                    post_url: link.post_url,
                    edit_url: link.edit_url,
                    links: []
                };
            }
            groupedLinks[key].links.push(link);
        });

        Object.values(groupedLinks).forEach(function(group) {
            const postTypeLabel = getPostTypeLabel(group.post_type);
            const linkCount = group.links.length;

            tableHTML += `<tr><td colspan="5" class="group-header">
                <strong>${escapeHtml(group.post_title)}</strong>
                <span class="chklinkout-post-type">${postTypeLabel}</span>
                <span class="link-count">(${linkCount} link${linkCount > 1 ? 's' : ''})</span>
            </td></tr>`;

            group.links.forEach(function(link) {
                const statusClass = getStatusClass(link.http_status, link.is_broken);
                const statusText = link.http_status !== null ? link.http_status : 'N/A';

                tableHTML += `
                    <tr>
                        <td></td>
                        <td>
                            <a href="${escapeHtml(link.external_url)}" target="_blank" class="chklinkout-link-url" rel="noopener noreferrer">
                                ${escapeHtml(link.external_url)}
                            </a>
                        </td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td><small>${escapeHtml(link.location)}</small></td>
                        <td class="chklinkout-actions">
                            <a href="${link.edit_url}" class="button button-small" target="_blank">Sửa</a>
                            ${group.post_type !== 'widget' ? `<a href="${link.post_url}" class="button button-small" target="_blank">Xem</a>` : ''}
                        </td>
                    </tr>
                `;
            });
        });

        tableHTML += '</tbody></table>';
        $('#chklinkout-table-container').html(tableHTML);
    }

    /**
     * Display pagination
     */
    function displayPagination(data) {
        if (data.total_pages <= 1) {
            $('#chklinkout-pagination').empty();
            return;
        }

        let paginationHTML = '<div class="pagination-info">Trang ' + data.page + ' / ' + data.total_pages + ' (Tổng: ' + data.total + ' links)</div>';
        paginationHTML += '<div class="pagination-buttons">';

        // Previous button
        if (data.page > 1) {
            paginationHTML += '<button class="button pagination-btn" data-page="1">« Đầu</button>';
            paginationHTML += '<button class="button pagination-btn" data-page="' + (data.page - 1) + '">‹ Trước</button>';
        }

        // Page numbers
        const startPage = Math.max(1, data.page - 2);
        const endPage = Math.min(data.total_pages, data.page + 2);

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === data.page ? ' button-primary' : '';
            paginationHTML += '<button class="button pagination-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        // Next button
        if (data.page < data.total_pages) {
            paginationHTML += '<button class="button pagination-btn" data-page="' + (data.page + 1) + '">Sau ›</button>';
            paginationHTML += '<button class="button pagination-btn" data-page="' + data.total_pages + '">Cuối »</button>';
        }

        paginationHTML += '</div>';
        $('#chklinkout-pagination').html(paginationHTML);

        // Bind pagination click events
        $('.pagination-btn').on('click', function() {
            currentPage = parseInt($(this).data('page'));
            loadResults();
            $('html, body').animate({ scrollTop: $('#chklinkout-results').offset().top - 50 }, 300);
        });
    }

    /**
     * Export CSV
     */
    function exportCSV() {
        if (!currentScanId) return;

        const url = chklinkoutAjax.ajax_url + '?action=chklinkout_export_csv&scan_id=' + currentScanId + '&nonce=' + chklinkoutAjax.nonce;
        window.location.href = url;
    }

    /**
     * Export JSON
     */
    function exportJSON() {
        if (!currentScanId) return;

        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_export_json',
                nonce: chklinkoutAjax.nonce,
                scan_id: currentScanId
            },
            success: function(response) {
                if (response.success) {
                    const dataStr = JSON.stringify(response.data, null, 2);
                    const blob = new Blob([dataStr], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'chklinkout-export-' + Date.now() + '.json';
                    link.click();
                    URL.revokeObjectURL(url);
                }
            }
        });
    }

    /**
     * Progress bar functions
     */
    function showProgress(text, percent) {
        $('#chklinkout-progress').show();
        updateProgress(text, percent);
    }

    function updateProgress(text, percent) {
        $('#chklinkout-progress-text').text(text);
        $('#chklinkout-progress-percent').text(percent + '%');
        $('#chklinkout-progress-bar').css('width', percent + '%');
    }

    function hideProgress() {
        $('#chklinkout-progress').hide();
    }

    /**
     * Display empty state
     */
    function displayEmptyState() {
        $('#chklinkout-results').html(`
            <div class="chklinkout-empty">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3>Không tìm thấy External Links</h3>
                <p>Website của bạn không chứa external links nào hoặc không có kết quả phù hợp với bộ lọc.</p>
            </div>
        `).show();
    }

    /**
     * Display welcome message
     */
    function displayWelcomeMessage() {
        $('#chklinkout-results').html(`
            <div class="chklinkout-empty">
                <span class="dashicons dashicons-admin-links" style="color: #2271b1;"></span>
                <h3>Chào mừng đến với ChkLinkOut!</h3>
                <p>Click nút <strong>"Bắt đầu quét mới"</strong> để scan external links trong website của bạn.</p>
            </div>
        `).show();
    }

    /**
     * Show error
     */
    function showError(message) {
        $('#chklinkout-error p').text(message);
        $('#chklinkout-error').show();
    }

    /**
     * Utility functions
     */
    function getPostTypeLabel(postType) {
        const labels = {
            'post': 'Bài viết',
            'page': 'Trang',
            'widget': 'Widget'
        };
        return labels[postType] || postType;
    }

    function getStatusClass(status, isBroken) {
        if (status === null) return 'status-unknown';
        if (isBroken || status >= 400 || status === 0) return 'status-error';
        if (status >= 300) return 'status-redirect';
        if (status >= 200 && status < 300) return 'status-success';
        return 'status-unknown';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
