/**
 * Wprosh Admin JavaScript
 *
 * @package Wprosh
 * @since 1.1.0
 */

(function($) {
    'use strict';

    // Variables
    var selectedFile = null;
    var isProcessing = false;

    /**
     * Initialize
     */
    function init() {
        bindEvents();
        initDragDrop();
    }

    /**
     * Bind events
     */
    function bindEvents() {
        // Export button
        $('#wprosh-export-btn').on('click', handleExport);

        // Import button
        $('#wprosh-import-btn').on('click', handleImport);

        // File input change
        $('#wprosh-file-input').on('change', handleFileSelect);

        // Remove file button
        $('#wprosh-remove-file').on('click', handleRemoveFile);
    }

    /**
     * Initialize drag and drop
     */
    function initDragDrop() {
        var uploadArea = $('#wprosh-upload-area');

        uploadArea.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        uploadArea.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileDropped(files[0]);
            }
        });
    }

    /**
     * Handle file dropped
     */
    function handleFileDropped(file) {
        if (!validateFile(file)) {
            return;
        }

        selectedFile = file;
        showFileInfo(file);
        enableImportButton();
    }

    /**
     * Handle file select
     */
    function handleFileSelect(e) {
        var file = e.target.files[0];
        
        if (!file) {
            return;
        }

        if (!validateFile(file)) {
            $(this).val('');
            return;
        }

        selectedFile = file;
        showFileInfo(file);
        enableImportButton();
    }

    /**
     * Validate file
     */
    function validateFile(file) {
        var extension = file.name.split('.').pop().toLowerCase();
        
        if (extension !== 'csv') {
            showToast(wproshData.strings.invalidFile, 'error');
            return false;
        }

        return true;
    }

    /**
     * Show file info
     */
    function showFileInfo(file) {
        $('#wprosh-file-name').text(file.name);
        $('#wprosh-file-info').show();
        $('.wprosh-upload-label').hide();
    }

    /**
     * Hide file info
     */
    function hideFileInfo() {
        $('#wprosh-file-info').hide();
        $('.wprosh-upload-label').show();
        $('#wprosh-file-input').val('');
    }

    /**
     * Handle remove file
     */
    function handleRemoveFile(e) {
        e.preventDefault();
        e.stopPropagation();
        
        selectedFile = null;
        hideFileInfo();
        disableImportButton();
    }

    /**
     * Enable import button
     */
    function enableImportButton() {
        $('#wprosh-import-btn').prop('disabled', false);
    }

    /**
     * Disable import button
     */
    function disableImportButton() {
        $('#wprosh-import-btn').prop('disabled', true);
    }

    /**
     * Show progress bar
     */
    function showProgress() {
        $('#wprosh-import-btn').hide();
        $('#wprosh-progress-container').show();
        updateProgress(0, 'در حال آپلود فایل...');
    }

    /**
     * Hide progress bar
     */
    function hideProgress() {
        $('#wprosh-progress-container').hide();
        $('#wprosh-import-btn').show();
    }

    /**
     * Update progress bar
     */
    function updateProgress(percent, status) {
        $('#wprosh-progress-bar').css('width', percent + '%');
        $('#wprosh-progress-percent').text(percent + '%');
        if (status) {
            $('#wprosh-progress-status').text(status);
        }
    }

    /**
     * Handle export
     */
    function handleExport(e) {
        e.preventDefault();

        if (isProcessing) {
            return;
        }

        var $btn = $(this);
        setButtonLoading($btn, true);
        isProcessing = true;

        $.ajax({
            url: wproshData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wprosh_export',
                nonce: wproshData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast(wproshData.strings.exporting, 'success');
                    
                    // Update stats if available
                    if (response.data.stats) {
                        updateStats(response.data.stats);
                    }
                    
                    // Use redirect for download (more reliable)
                    if (response.data.use_redirect) {
                        window.location.href = response.data.download_url;
                    } else {
                        downloadFile(response.data.download_url, response.data.file_name);
                    }
                } else {
                    showToast(response.data.message || wproshData.strings.exportError, 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast(wproshData.strings.exportError + ': ' + error, 'error');
            },
            complete: function() {
                setButtonLoading($btn, false);
                isProcessing = false;
            }
        });
    }

    /**
     * Handle import with progress
     */
    function handleImport(e) {
        e.preventDefault();

        if (isProcessing || !selectedFile) {
            if (!selectedFile) {
                showToast(wproshData.strings.selectFile, 'error');
            }
            return;
        }

        isProcessing = true;
        showProgress();

        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'wprosh_import');
        formData.append('nonce', wproshData.nonce);
        formData.append('file', selectedFile);

        $.ajax({
            url: wproshData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                
                // Upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 30); // Upload is 30%
                        updateProgress(percent, 'در حال آپلود فایل...');
                    }
                }, false);
                
                return xhr;
            },
            success: function(response) {
                // Simulate processing progress
                updateProgress(50, 'در حال بررسی تغییرات...');
                
                setTimeout(function() {
                    updateProgress(80, 'در حال آپدیت محصولات...');
                    
                    setTimeout(function() {
                        updateProgress(100, 'تکمیل شد!');
                        
                        setTimeout(function() {
                            hideProgress();
                            
                            if (response.success) {
                                showResults(response.data);
                                
                                // Reset file selection
                                selectedFile = null;
                                hideFileInfo();
                                disableImportButton();
                                
                                // Refresh stats
                                refreshStats();
                            } else {
                                showToast(response.data.message || wproshData.strings.importError, 'error');
                            }
                            
                            isProcessing = false;
                        }, 500);
                    }, 300);
                }, 300);
            },
            error: function(xhr, status, error) {
                hideProgress();
                showToast(wproshData.strings.importError + ': ' + error, 'error');
                isProcessing = false;
            }
        });
    }

    /**
     * Set button loading state
     */
    function setButtonLoading($btn, loading) {
        if (loading) {
            $btn.addClass('loading').prop('disabled', true);
        } else {
            $btn.removeClass('loading').prop('disabled', false);
        }
    }

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        // Remove existing toasts
        $('.wprosh-success-toast, .wprosh-error-toast').remove();

        var className = type === 'success' ? 'wprosh-success-toast' : 'wprosh-error-toast';
        var $toast = $('<div class="' + className + '">' + escapeHtml(message) + '</div>');
        
        $('body').append($toast);

        // Auto remove after 4 seconds
        setTimeout(function() {
            $toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    /**
     * Download file from data URL
     */
    function downloadFile(url, filename) {
        var link = document.createElement('a');
        link.href = url;
        link.download = filename || 'download';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Download from base64 data
     */
    function downloadBase64File(base64Data, filename) {
        var link = document.createElement('a');
        link.href = 'data:text/csv;charset=utf-8;base64,' + base64Data;
        link.download = filename || 'wprosh-errors.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Show results
     */
    function showResults(data) {
        var $results = $('#wprosh-results');
        
        // Update numbers with animation
        animateNumber($('#result-total'), 0, data.results.total);
        animateNumber($('#result-updated'), 0, data.results.updated);
        animateNumber($('#result-skipped'), 0, data.results.skipped || 0);
        animateNumber($('#result-failed'), 0, data.results.failed);

        // Update message
        var $message = $('#wprosh-results-message');
        var hasErrors = data.errors_count > 0;
        
        if (hasErrors) {
            $message.html('<strong>' + data.results.updated + '</strong> محصول آپدیت شد. <strong>' + data.errors_count + '</strong> خطا در فیلدها وجود دارد.');
            $message.addClass('has-errors').removeClass('no-errors');
        } else if (data.results.updated > 0) {
            $message.html('<strong>' + data.results.updated + '</strong> محصول با موفقیت آپدیت شد.');
            $message.removeClass('has-errors').addClass('no-errors');
        } else {
            $message.text('هیچ تغییری در محصولات یافت نشد.');
            $message.removeClass('has-errors').addClass('no-errors');
        }

        // Show error report download if available
        var $actions = $('#wprosh-results-actions');
        var $downloadBtn = $('#wprosh-download-report');
        
        if (data.error_report_data && hasErrors) {
            // Bind click event to download base64 file
            $downloadBtn.off('click').on('click', function(e) {
                e.preventDefault();
                downloadBase64File(data.error_report_data, data.error_report_name || 'wprosh-errors.csv');
            });
            $downloadBtn.attr('href', '#');
            $actions.show();
        } else {
            $actions.hide();
        }

        // Show results section
        $results.slideDown(300);

        // Scroll to results
        $('html, body').animate({
            scrollTop: $results.offset().top - 100
        }, 500);

        // Show appropriate toast
        if (data.results.updated > 0) {
            showToast(data.results.updated + ' محصول آپدیت شد', 'success');
        } else {
            showToast('هیچ تغییری اعمال نشد', 'success');
        }
    }

    /**
     * Update stats on page
     */
    function updateStats(stats) {
        $('.wprosh-stat-card').each(function(index) {
            var $number = $(this).find('.wprosh-stat-number');
            var keys = ['total', 'simple', 'variable', 'variation'];
            if (keys[index] && stats[keys[index]] !== undefined) {
                animateNumber($number, parseInt($number.text()) || 0, stats[keys[index]]);
            }
        });
    }

    /**
     * Refresh stats via AJAX
     */
    function refreshStats() {
        $.ajax({
            url: wproshData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wprosh_get_stats',
                nonce: wproshData.nonce
            },
            success: function(response) {
                if (response.success && response.data.stats) {
                    updateStats(response.data.stats);
                }
            }
        });
    }

    /**
     * Animate number change
     */
    function animateNumber($element, from, to) {
        $({value: from}).animate({value: to}, {
            duration: 500,
            easing: 'swing',
            step: function() {
                $element.text(Math.round(this.value));
            },
            complete: function() {
                $element.text(to);
            }
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
