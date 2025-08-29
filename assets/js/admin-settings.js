/**
 * WordPress Plugin Directory Filters - Admin Settings JavaScript
 *
 * Handles admin settings page interactions, algorithm weight validation,
 * and cache management
 *
 * @package WP_Plugin_Directory_Filters
 */

(function($) {
    'use strict';

    /**
     * Admin Settings object
     */
    window.WPPluginFiltersAdminSettings = {
        
        /**
         * Initialize admin settings
         */
        init: function() {
            this.bindEvents();
            this.calculateWeightTotals();
            this.setupValidation();
            
            console.log('[WP Plugin Filters Admin] Settings initialized');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Weight input changes
            $('.usability-weight-input, .health-weight-input').on('input change', function() {
                self.calculateWeightTotals();
                self.validateWeights();
            });
            
            // Cache management buttons
            $('#clear-all-cache').on('click', function(e) {
                e.preventDefault();
                self.clearCache('all');
            });
            
            $('#clear-search-cache').on('click', function(e) {
                e.preventDefault();
                self.clearCache('search_results');
            });
            
            $('#clear-ratings-cache').on('click', function(e) {
                e.preventDefault();
                self.clearCache('calculated_ratings');
            });
            
            // Form submission validation
            $('form').on('submit', function(e) {
                if (!self.validateForm()) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Reset to defaults buttons
            $('.reset-defaults').on('click', function(e) {
                e.preventDefault();
                self.resetToDefaults($(this).data('section'));
            });
        },
        
        /**
         * Calculate and display weight totals
         */
        calculateWeightTotals: function() {
            // Calculate usability weights total
            var usabilityTotal = 0;
            $('.usability-weight-input').each(function() {
                usabilityTotal += parseInt($(this).val()) || 0;
            });
            
            var usabilityDisplay = $('#usability-weight-total');
            usabilityDisplay.html(this.getWeightDisplayHtml(usabilityTotal, 'Usability Rating'));
            
            // Calculate health weights total
            var healthTotal = 0;
            $('.health-weight-input').each(function() {
                healthTotal += parseInt($(this).val()) || 0;
            });
            
            var healthDisplay = $('#health-weight-total');
            healthDisplay.html(this.getWeightDisplayHtml(healthTotal, 'Health Score'));
        },
        
        /**
         * Get weight display HTML
         */
        getWeightDisplayHtml: function(total, algorithmName) {
            var statusClass = 'weight-status-';
            var statusText = '';
            
            if (total === 100) {
                statusClass += 'valid';
                statusText = wpPluginFiltersAdmin.strings.algorithmValid || 'Valid';
            } else if (total > 100) {
                statusClass += 'invalid';
                statusText = wpPluginFiltersAdmin.strings.algorithmOver || 'Over 100%';
            } else {
                statusClass += 'invalid';
                statusText = wpPluginFiltersAdmin.strings.algorithmUnder || 'Under 100%';
            }
            
            return `<div class="${statusClass}">
                <strong>${algorithmName} Total: ${total}%</strong> - 
                <span class="status-text">${statusText}</span>
            </div>`;
        },
        
        /**
         * Validate algorithm weights
         */
        validateWeights: function() {
            var usabilityTotal = 0;
            $('.usability-weight-input').each(function() {
                usabilityTotal += parseInt($(this).val()) || 0;
            });
            
            var healthTotal = 0;
            $('.health-weight-input').each(function() {
                healthTotal += parseInt($(this).val()) || 0;
            });
            
            // Show warnings if totals are not 100
            this.showWeightWarning('usability', usabilityTotal);
            this.showWeightWarning('health', healthTotal);
            
            return usabilityTotal === 100 && healthTotal === 100;
        },
        
        /**
         * Show weight validation warning
         */
        showWeightWarning: function(type, total) {
            var warningId = `${type}-weight-warning`;
            var existingWarning = $('#' + warningId);
            
            if (total !== 100) {
                if (existingWarning.length === 0) {
                    var warningHtml = `
                        <div id="${warningId}" class="notice notice-warning inline">
                            <p><strong>${wpPluginFiltersAdmin.strings.weightValidationError}</strong></p>
                        </div>
                    `;
                    
                    $(`#${type}-weight-total`).after(warningHtml);
                }
            } else {
                existingWarning.remove();
            }
        },
        
        /**
         * Validate entire form
         */
        validateForm: function() {
            var isValid = this.validateWeights();
            
            if (!isValid) {
                this.showNotice(
                    wpPluginFiltersAdmin.strings.weightValidationError,
                    'error'
                );
                
                // Scroll to first invalid section
                $('html, body').animate({
                    scrollTop: $('.weight-status-invalid').first().offset().top - 100
                }, 500);
            }
            
            return isValid;
        },
        
        /**
         * Clear cache via AJAX
         */
        clearCache: function(cacheType) {
            var button = $(`#clear-${cacheType === 'all' ? 'all' : cacheType.replace('_', '-')}-cache`);
            var originalText = button.text();
            
            button.prop('disabled', true).text(wpPluginFiltersAdmin.strings.clearing || 'Clearing...');
            
            $.ajax({
                url: wpPluginFiltersAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wppdfi_clear_cache',
                    nonce: wpPluginFiltersAdmin.nonces.clear_cache,
                    cache_type: cacheType
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotice(
                            response.data.message || wpPluginFiltersAdmin.strings.cacheCleared,
                            'success'
                        );
                    } else {
                        this.showNotice(
                            response.data.message || wpPluginFiltersAdmin.strings.cacheClearError,
                            'error'
                        );
                    }
                }.bind(this),
                error: function() {
                    this.showNotice(
                        wpPluginFiltersAdmin.strings.cacheClearError,
                        'error'
                    );
                }.bind(this),
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Reset algorithm weights to defaults
         */
        resetToDefaults: function(section) {
            var defaults = {
                usability: {
                    user_rating: 40,
                    rating_count: 20,
                    installation_count: 25,
                    support_responsiveness: 15
                },
                health: {
                    update_frequency: 30,
                    wp_compatibility: 25,
                    support_response: 20,
                    time_since_update: 15,
                    reported_issues: 10
                }
            };
            
            if (defaults[section]) {
                var sectionDefaults = defaults[section];
                
                Object.keys(sectionDefaults).forEach(function(key) {
                    $(`#${section}_weight_${key}`).val(sectionDefaults[key]);
                });
                
                this.calculateWeightTotals();
                this.validateWeights();
                
                this.showNotice(
                    `${section.charAt(0).toUpperCase() + section.slice(1)} weights reset to defaults`,
                    'success'
                );
            }
        },
        
        /**
         * Test algorithm with sample data
         */
        testAlgorithm: function(algorithmType) {
            var button = $(`#test-${algorithmType}-algorithm`);
            var originalText = button.text();
            
            button.prop('disabled', true).text(wpPluginFiltersAdmin.strings.testingAlgorithm);
            
            $.ajax({
                url: wpPluginFiltersAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wppdfi_test_algorithm',
                    nonce: wpPluginFiltersAdmin.nonces.test_algorithm,
                    algorithm_type: algorithmType,
                    weights: this.getCurrentWeights(algorithmType)
                },
                success: function(response) {
                    if (response.success) {
                        this.showAlgorithmTestResults(response.data, algorithmType);
                    } else {
                        this.showNotice(
                            response.data.message || 'Algorithm test failed',
                            'error'
                        );
                    }
                }.bind(this),
                error: function() {
                    this.showNotice(
                        'Error testing algorithm',
                        'error'
                    );
                }.bind(this),
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Get current weights for an algorithm
         */
        getCurrentWeights: function(algorithmType) {
            var weights = {};
            
            $(`.${algorithmType}-weight-input`).each(function() {
                var key = $(this).attr('name').match(/\[([^\]]+)\]$/)[1];
                weights[key] = parseInt($(this).val()) || 0;
            });
            
            return weights;
        },
        
        /**
         * Show algorithm test results
         */
        showAlgorithmTestResults: function(results, algorithmType) {
            var resultHtml = `
                <div class="algorithm-test-results notice notice-info">
                    <h4>${algorithmType.charAt(0).toUpperCase() + algorithmType.slice(1)} Algorithm Test Results</h4>
                    <p><strong>Sample Score:</strong> ${results.sample_score}</p>
                    <p><strong>Component Breakdown:</strong></p>
                    <ul>
            `;
            
            Object.keys(results.breakdown).forEach(function(component) {
                resultHtml += `<li><strong>${component}:</strong> ${results.breakdown[component]}</li>`;
            });
            
            resultHtml += `
                    </ul>
                    <p><em>Test completed successfully with current weights.</em></p>
                </div>
            `;
            
            $('.algorithm-test-results').remove();
            $(`#${algorithmType}-weight-total`).after(resultHtml);
            
            // Auto-remove after 10 seconds
            setTimeout(function() {
                $('.algorithm-test-results').fadeOut();
            }, 10000);
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var noticeHtml = `
                <div class="notice notice-${type} is-dismissible admin-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $('.admin-notice').remove();
            $('.wrap h1').after(noticeHtml);
            
            // Enable dismiss functionality
            $('.notice-dismiss').on('click', function() {
                $(this).closest('.notice').fadeOut();
            });
            
            // Auto-remove success notices after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('.notice-success').fadeOut();
                }, 5000);
            }
        },
        
        /**
         * Setup form validation
         */
        setupValidation: function() {
            // Cache duration validation
            $('input[name*="cache_durations"]').on('input', function() {
                var value = parseInt($(this).val());
                var min = parseInt($(this).attr('min'));
                var max = parseInt($(this).attr('max'));
                
                if (value < min || value > max) {
                    $(this).addClass('invalid');
                    this.showFieldError($(this), `Value must be between ${min} and ${max} seconds`);
                } else {
                    $(this).removeClass('invalid');
                    this.hideFieldError($(this));
                }
            }.bind(this));
            
            // Weight input validation
            $('.usability-weight-input, .health-weight-input').on('input', function() {
                var value = parseInt($(this).val());
                
                if (value < 0 || value > 100) {
                    $(this).addClass('invalid');
                    this.showFieldError($(this), 'Weight must be between 0 and 100');
                } else {
                    $(this).removeClass('invalid');
                    this.hideFieldError($(this));
                }
            }.bind(this));
        },
        
        /**
         * Show field-specific error
         */
        showFieldError: function($field, message) {
            var errorId = $field.attr('id') + '-error';
            
            if ($('#' + errorId).length === 0) {
                $field.after(`<div id="${errorId}" class="field-error" style="color: #d63638; font-size: 12px; margin-top: 2px;">${message}</div>`);
            }
        },
        
        /**
         * Hide field-specific error
         */
        hideFieldError: function($field) {
            var errorId = $field.attr('id') + '-error';
            $('#' + errorId).remove();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof wpPluginFiltersAdmin !== 'undefined') {
            WPPluginFiltersAdminSettings.init();
        }
    });

})(jQuery);