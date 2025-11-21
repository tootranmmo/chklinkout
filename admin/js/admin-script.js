/**
 * ChkLinkOut Admin Script
 */

(function($) {
    'use strict';

    let scanResults = [];
    let filteredResults = [];

    $(document).ready(function() {
        // Scan button click
        $('#chklinkout-scan-btn').on('click', function() {
            startScan();
        });

        // Filter by type
        $('#chklinkout-filter-type').on('change', function() {
            filterResults();
        });

        // Search
        $('#chklinkout-search').on('input', function() {
            filterResults();
        });

        // Export CSV
        $('#chklinkout-export-csv').on('click', function() {
            exportToCSV();
        });
    });

    /**
     * Start scanning
     */
    function startScan() {
        $('#chklinkout-scan-btn').prop('disabled', true);
        $('#chklinkout-loading').show();
        $('#chklinkout-results').hide();
        $('#chklinkout-error').hide();

        $.ajax({
            url: chklinkoutAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'chklinkout_scan',
                nonce: chklinkoutAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    scanResults = response.data.results;
                    filteredResults = scanResults;
                    displayResults();
                } else {
                    showError(response.data.message || 'Có lỗi xảy ra khi quét.');
                }
            },
            error: function(xhr, status, error) {
                showError('Có lỗi xảy ra: ' + error);
            },
            complete: function() {
                $('#chklinkout-scan-btn').prop('disabled', false);
                $('#chklinkout-loading').hide();
            }
        });
    }

    /**
     * Display scan results
     */
    function displayResults() {
        if (scanResults.length === 0) {
            displayEmptyState();
            return;
        }

        displayStatistics();
        displayTable();
        $('#chklinkout-results').show();
    }

    /**
     * Display statistics
     */
    function displayStatistics() {
        const stats = calculateStatistics(scanResults);

        let statsHTML = `
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.totalPosts}</div>
                <div class="stat-label">Tổng số mục</div>
            </div>
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.totalLinks}</div>
                <div class="stat-label">Tổng External Links</div>
            </div>
            <div class="chklinkout-stat-box">
                <div class="stat-number">${stats.totalDomains}</div>
                <div class="stat-label">Unique Domains</div>
            </div>
        `;

        // Add top domains if available
        if (stats.topDomains.length > 0) {
            let domainsHTML = '<div class="chklinkout-stat-details"><h3>Top Domains</h3><ul>';
            stats.topDomains.forEach(function(domain) {
                domainsHTML += `<li><span>${domain.name}</span><span class="domain-count">${domain.count}</span></li>`;
            });
            domainsHTML += '</ul></div>';
            statsHTML = statsHTML + domainsHTML;
        }

        $('#chklinkout-stats').html(statsHTML);
    }

    /**
     * Calculate statistics
     */
    function calculateStatistics(results) {
        let totalLinks = 0;
        const domains = {};

        results.forEach(function(result) {
            totalLinks += result.external_links.length;

            result.external_links.forEach(function(link) {
                try {
                    const url = new URL(link.url);
                    const domain = url.hostname;
                    domains[domain] = (domains[domain] || 0) + 1;
                } catch (e) {
                    // Invalid URL
                }
            });
        });

        // Sort domains by count
        const sortedDomains = Object.entries(domains)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10)
            .map(([name, count]) => ({ name, count }));

        return {
            totalPosts: results.length,
            totalLinks: totalLinks,
            totalDomains: Object.keys(domains).length,
            topDomains: sortedDomains
        };
    }

    /**
     * Display results table
     */
    function displayTable() {
        if (filteredResults.length === 0) {
            $('#chklinkout-table-container').html('<div class="chklinkout-empty"><span class="dashicons dashicons-search"></span><h3>Không tìm thấy kết quả</h3><p>Thử thay đổi bộ lọc hoặc tìm kiếm.</p></div>');
            return;
        }

        let tableHTML = `
            <table class="chklinkout-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Tiêu đề / Loại</th>
                        <th style="width: 50%;">External Links</th>
                        <th style="width: 15%;">Số lượng</th>
                        <th style="width: 10%;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
        `;

        filteredResults.forEach(function(result) {
            const postTypeLabel = getPostTypeLabel(result.post_type);

            let linksHTML = '';
            result.external_links.forEach(function(link) {
                linksHTML += `
                    <div class="chklinkout-link-item">
                        <a href="${escapeHtml(link.url)}" target="_blank" class="chklinkout-link-url">${escapeHtml(link.url)}</a>
                        <div class="chklinkout-link-location">
                            <span class="dashicons dashicons-location"></span>
                            ${escapeHtml(link.location)}
                        </div>
                    </div>
                `;
            });

            tableHTML += `
                <tr>
                    <td>
                        <a href="${result.edit_url}" class="chklinkout-post-title" target="_blank">${escapeHtml(result.post_title)}</a>
                        <span class="chklinkout-post-type">${postTypeLabel}</span>
                    </td>
                    <td>${linksHTML}</td>
                    <td><strong>${result.external_links.length}</strong></td>
                    <td class="chklinkout-actions">
                        <a href="${result.edit_url}" class="button button-small" target="_blank">Sửa</a>
                        ${result.post_type !== 'widget' ? `<a href="${result.post_url}" class="button button-small" target="_blank">Xem</a>` : ''}
                    </td>
                </tr>
            `;
        });

        tableHTML += '</tbody></table>';
        $('#chklinkout-table-container').html(tableHTML);
    }

    /**
     * Filter results
     */
    function filterResults() {
        const typeFilter = $('#chklinkout-filter-type').val();
        const searchTerm = $('#chklinkout-search').val().toLowerCase();

        filteredResults = scanResults.filter(function(result) {
            // Filter by type
            if (typeFilter && result.post_type !== typeFilter) {
                return false;
            }

            // Filter by search term
            if (searchTerm) {
                const titleMatch = result.post_title.toLowerCase().includes(searchTerm);
                const urlMatch = result.external_links.some(function(link) {
                    return link.url.toLowerCase().includes(searchTerm);
                });

                return titleMatch || urlMatch;
            }

            return true;
        });

        displayTable();
    }

    /**
     * Export to CSV
     */
    function exportToCSV() {
        if (filteredResults.length === 0) {
            alert('Không có dữ liệu để xuất.');
            return;
        }

        let csv = 'Tiêu đề,Loại,External Link,Vị trí,URL Bài viết\n';

        filteredResults.forEach(function(result) {
            result.external_links.forEach(function(link) {
                csv += `"${escapeCSV(result.post_title)}",`;
                csv += `"${escapeCSV(result.post_type)}",`;
                csv += `"${escapeCSV(link.url)}",`;
                csv += `"${escapeCSV(link.location)}",`;
                csv += `"${escapeCSV(result.post_url)}"\n`;
            });
        });

        // Create download link
        const blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'chklinkout-export-' + Date.now() + '.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Display empty state
     */
    function displayEmptyState() {
        $('#chklinkout-results').html(`
            <div class="chklinkout-empty">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3>Không tìm thấy External Links</h3>
                <p>Website của bạn không chứa external links nào trong posts, pages hoặc widgets.</p>
            </div>
        `).show();
    }

    /**
     * Show error message
     */
    function showError(message) {
        $('#chklinkout-error p').text(message);
        $('#chklinkout-error').show();
    }

    /**
     * Get post type label
     */
    function getPostTypeLabel(postType) {
        const labels = {
            'post': 'Bài viết',
            'page': 'Trang',
            'widget': 'Widget'
        };
        return labels[postType] || postType;
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Escape CSV
     */
    function escapeCSV(text) {
        return text.replace(/"/g, '""');
    }

})(jQuery);
