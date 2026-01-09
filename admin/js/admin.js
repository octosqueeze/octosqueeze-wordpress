/**
 * OctoSqueeze Admin JavaScript
 */

(function($) {
    'use strict';

    // Load stats on settings page
    function loadStats() {
        var $container = $('#octosqueeze-stats');
        if (!$container.length) return;

        $.ajax({
            url: octosqueeze.ajax_url,
            type: 'POST',
            data: {
                action: 'octosqueeze_stats',
                nonce: octosqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<div class="octosqueeze-stats-grid">';
                    html += '<div class="octosqueeze-stat">';
                    html += '<div class="octosqueeze-stat-value">' + data.total_images + '</div>';
                    html += '<div class="octosqueeze-stat-label">Images Compressed</div>';
                    html += '</div>';
                    html += '<div class="octosqueeze-stat">';
                    html += '<div class="octosqueeze-stat-value">' + formatBytes(data.total_saved) + '</div>';
                    html += '<div class="octosqueeze-stat-label">Total Saved</div>';
                    html += '</div>';
                    html += '<div class="octosqueeze-stat">';
                    html += '<div class="octosqueeze-stat-value">' + data.pending + '</div>';
                    html += '<div class="octosqueeze-stat-label">Pending</div>';
                    html += '</div>';
                    html += '<div class="octosqueeze-stat">';
                    var percent = data.total_original > 0
                        ? Math.round((data.total_saved / data.total_original) * 100)
                        : 0;
                    html += '<div class="octosqueeze-stat-value">' + percent + '%</div>';
                    html += '<div class="octosqueeze-stat-label">Average Savings</div>';
                    html += '</div>';
                    html += '</div>';
                    $container.html(html);
                }
            }
        });
    }

    // Format bytes to human readable
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Manual compress button in media library
    $(document).on('click', '.octosqueeze-compress', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $button.closest('.octosqueeze-status');
        var attachmentId = $button.data('id');

        $button.addClass('loading').text('Compressing');

        $.ajax({
            url: octosqueeze.ajax_url,
            type: 'POST',
            data: {
                action: 'octosqueeze_compress',
                nonce: octosqueeze.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                if (response.success) {
                    var savings = response.data.savings_percent || 0;
                    $status.removeClass('pending')
                           .addClass('compressed')
                           .html('-' + savings + '%');
                } else {
                    $status.removeClass('pending')
                           .addClass('error')
                           .attr('title', response.data.message)
                           .html('Error');
                }
            },
            error: function() {
                $button.removeClass('loading').text('Compress');
                alert('Compression failed. Please try again.');
            }
        });
    });

    // Initialize
    $(document).ready(function() {
        loadStats();
    });

})(jQuery);
