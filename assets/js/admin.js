/**
 * WordPress Plugin Directory Filters - Admin JavaScript
 *
 * Handles frontend filtering, sorting, and AJAX interactions
 * Integrates seamlessly with WordPress admin plugin installer
 *
 * @package WP_Plugin_Directory_Filters
 */

(function($) {
    'use strict';

    /**
     * Plugin Filters main object
     */
    window.WPPluginFilters = {
        
        // Configuration
        config: {
            debounceDelay: 300,
            maxRetries: 3,
            retryDelay: 1000
        },
        
        // State management
        state: {
            currentFilters: {},
            isLoading: false,
            retryCount: 0,
            originalPlugins: [],
            filteredPlugins: []
        },
        
        // DOM elements cache
        $elements: {},
        
        // Debounce timers
        debounceTimers: {},

        /**
         * Initialize the plugin filters
         */
        init: function() {
            this.cacheElements();
            this.injectFilterControls();
            this.bindEvents();
            this.loadStateFromURL();
            this.enhanceExistingPlugins();
            
            console.log('[WP Plugin Filters] Initialized successfully');
        },

        /**
         * Cache frequently used DOM elements
         */
        cacheElements: function() {
            this.$elements = {
                body: $('body'),
                filterSearch: $('.wp-filter-search'),
                pluginBrowser: $('#plugin-filter'),
                pluginGrid: $('.wp-list-table.plugins'),
                spinner: $('.wp-filter-search .spinner'),
                searchInput: $('#plugin-search-input'),
                resultsContainer: $('.plugin-browser .plugin-list-table-body, .plugin-browser .wp-list-table tbody, #the-list'),
                pluginCards: $('.plugin-browser .plugin-card'),
                paginationLinks: $('.tablenav-pages')
            };
        },

        /**
         * Inject filter controls into the WordPress admin interface
         */
        injectFilterControls: function() {
            // Prevent double injection
            if ($('.wp-plugin-filters-controls').length > 0) {
                console.log('[WP Plugin Filters] Filter controls already exist, skipping injection');
                return;
            }
            
            var filterControlsHTML = this.buildFilterControlsHTML();
            
            // Insert after the search box
            if (this.$elements.filterSearch.length) {
                this.$elements.filterSearch.after(filterControlsHTML);
            } else {
                // Fallback insertion point
                $('.wp-filter').prepend(filterControlsHTML);
            }
            
            // Cache the new elements
            this.$elements.filterControls = $('.wp-plugin-filters-controls');
            this.$elements.installationRange = $('#wp-plugin-filter-installations');
            this.$elements.updateTimeframe = $('#wp-plugin-filter-updates');
            this.$elements.usabilityRating = $('#wp-plugin-filter-usability');
            this.$elements.healthScore = $('#wp-plugin-filter-health');
            this.$elements.sortBy = $('#wp-plugin-filter-sort');
            this.$elements.sortDirection = $('#wp-plugin-filter-direction');
            this.$elements.clearFilters = $('#wp-plugin-clear-filters');
        },

        /**
         * Build HTML for filter controls
         */
        buildFilterControlsHTML: function() {
            return `
                <div class="wp-plugin-filters-controls">
                    <div class="wp-plugin-filters-row">
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-installations">${wpPluginFilters.strings.installations || 'Active Installations'}</label>
                            <select id="wp-plugin-filter-installations" class="wp-plugin-filter-select">
                                <option value="all">${wpPluginFilters.strings.all || 'All'}</option>
                                <option value="0-1k">&lt; 1K</option>
                                <option value="1k-10k">1K - 10K</option>
                                <option value="10k-100k">10K - 100K</option>
                                <option value="100k-1m">100K - 1M</option>
                                <option value="1m-plus">&gt; 1M</option>
                            </select>
                        </div>
                        
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-updates">${wpPluginFilters.strings.lastUpdated || 'Last Updated'}</label>
                            <select id="wp-plugin-filter-updates" class="wp-plugin-filter-select">
                                <option value="all">${wpPluginFilters.strings.all || 'All'}</option>
                                <option value="last_week">${wpPluginFilters.strings.lastWeek || 'Last week'}</option>
                                <option value="last_month">${wpPluginFilters.strings.lastMonth || 'Last month'}</option>
                                <option value="last_3months">${wpPluginFilters.strings.last3Months || 'Last 3 months'}</option>
                                <option value="last_6months">${wpPluginFilters.strings.last6Months || 'Last 6 months'}</option>
                                <option value="last_year">${wpPluginFilters.strings.lastYear || 'Last year'}</option>
                                <option value="older">${wpPluginFilters.strings.older || 'Older'}</option>
                            </select>
                        </div>
                        
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-usability">${wpPluginFilters.strings.usabilityRating || 'Usability Rating'}</label>
                            <select id="wp-plugin-filter-usability" class="wp-plugin-filter-select">
                                <option value="0">${wpPluginFilters.strings.all || 'All'}</option>
                                <option value="1">1+ ${wpPluginFilters.strings.stars || 'stars'}</option>
                                <option value="2">2+ ${wpPluginFilters.strings.stars || 'stars'}</option>
                                <option value="3">3+ ${wpPluginFilters.strings.stars || 'stars'}</option>
                                <option value="4">4+ ${wpPluginFilters.strings.stars || 'stars'}</option>
                                <option value="5">5 ${wpPluginFilters.strings.stars || 'stars'}</option>
                            </select>
                        </div>
                        
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-health">${wpPluginFilters.strings.healthScore || 'Health Score'}</label>
                            <select id="wp-plugin-filter-health" class="wp-plugin-filter-select">
                                <option value="0">${wpPluginFilters.strings.all || 'All'}</option>
                                <option value="40">${wpPluginFilters.strings.fair || 'Fair'} (40+)</option>
                                <option value="70">${wpPluginFilters.strings.good || 'Good'} (70+)</option>
                                <option value="85">${wpPluginFilters.strings.excellent || 'Excellent'} (85+)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wp-plugin-filters-row">
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-sort">${wpPluginFilters.strings.sortBy || 'Sort By'}</label>
                            <select id="wp-plugin-filter-sort" class="wp-plugin-filter-select">
                                <option value="relevance">${wpPluginFilters.strings.relevance || 'Relevance'}</option>
                                <option value="installations">${wpPluginFilters.strings.installations || 'Active Installations'}</option>
                                <option value="rating">${wpPluginFilters.strings.rating || 'Rating'}</option>
                                <option value="updated">${wpPluginFilters.strings.lastUpdated || 'Last Updated'}</option>
                                <option value="usability_rating">${wpPluginFilters.strings.usabilityRating || 'Usability Rating'}</option>
                                <option value="health_score">${wpPluginFilters.strings.healthScore || 'Health Score'}</option>
                            </select>
                        </div>
                        
                        <div class="wp-plugin-filters-field">
                            <label for="wp-plugin-filter-direction">${wpPluginFilters.strings.sortDirection || 'Sort Direction'}</label>
                            <select id="wp-plugin-filter-direction" class="wp-plugin-filter-select">
                                <option value="desc">${wpPluginFilters.strings.descending || 'Descending'}</option>
                                <option value="asc">${wpPluginFilters.strings.ascending || 'Ascending'}</option>
                            </select>
                        </div>
                        
                        <div class="wp-plugin-filters-field">
                            <button type="button" id="wp-plugin-clear-filters" class="button">
                                ${wpPluginFilters.strings.clearFilters || 'Clear Filters'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Filter change events
            $(document).on('change', '.wp-plugin-filter-select', function() {
                self.handleFilterChange();
            });
            
            // Clear filters button
            $(document).on('click', '#wp-plugin-clear-filters', function(e) {
                e.preventDefault();
                self.clearAllFilters();
            });
            
            // Search input changes
            $(document).on('input', '#plugin-search-input', function() {
                self.handleSearchChange();
            });
            
            // Pagination clicks
            $(document).on('click', '.tablenav-pages a', function(e) {
                e.preventDefault();
                self.handlePaginationClick($(this));
            });
            
            // Plugin card interactions
            $(document).on('click', '.plugin-card-top', function() {
                var $card = $(this).closest('.plugin-card');
                self.loadPluginRatings($card);
            });
        },

        /**
         * Handle filter changes with debouncing
         */
        handleFilterChange: function() {
            var self = this;
            
            clearTimeout(this.debounceTimers.filter);
            this.debounceTimers.filter = setTimeout(function() {
                self.applyFilters();
            }, this.config.debounceDelay);
        },

        /**
         * Handle search input changes
         */
        handleSearchChange: function() {
            var self = this;
            
            clearTimeout(this.debounceTimers.search);
            this.debounceTimers.search = setTimeout(function() {
                self.applyFilters();
            }, this.config.debounceDelay);
        },

        /**
         * Apply current filters via AJAX
         */
        applyFilters: function() {
            if (this.state.isLoading) {
                return;
            }
            
            var filterData = this.getCurrentFilterData();
            this.state.currentFilters = filterData;
            
            this.showLoadingState();
            this.executeAjaxRequest('wp_plugin_filter', filterData)
                .done(this.handleFilterSuccess.bind(this))
                .fail(this.handleFilterError.bind(this));
        },

        /**
         * Get current filter data from form
         */
        getCurrentFilterData: function() {
            return {
                search_term: this.$elements.searchInput.val() || '',
                installation_range: this.$elements.installationRange.val() || 'all',
                update_timeframe: this.$elements.updateTimeframe.val() || 'all',
                usability_rating: parseFloat(this.$elements.usabilityRating.val()) || 0,
                health_score: parseInt(this.$elements.healthScore.val()) || 0,
                sort_by: this.$elements.sortBy.val() || 'relevance',
                sort_direction: this.$elements.sortDirection.val() || 'desc',
                page: 1, // Reset to first page when filters change
                per_page: 24 // WordPress default
            };
        },

        /**
         * Execute AJAX request with retry logic
         */
        executeAjaxRequest: function(action, data) {
            var self = this;
            
            // Map action to correct nonce
            var nonceMap = {
                'wp_plugin_filter': 'filter_plugins',
                'wp_plugin_sort': 'sort_plugins', 
                'wp_plugin_rating': 'calculate_rating',
                'wp_plugin_clear_cache': 'clear_cache'
            };
            
            var requestData = $.extend({}, data, {
                action: action,
                nonce: wpPluginFilters.nonces[nonceMap[action]] || wpPluginFilters.nonces.filter_plugins
            });
            
            return $.ajax({
                url: wpPluginFilters.ajaxUrl,
                method: 'POST',
                data: requestData,
                dataType: 'json',
                timeout: 30000
            }).fail(function(xhr, status, error) {
                console.error('[WP Plugin Filters] AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    action: action
                });
                
                if (xhr.status === 405) {
                    self.showError('Method not allowed. Please check plugin configuration.');
                    return;
                }
                
                if (self.state.retryCount < self.config.maxRetries) {
                    self.state.retryCount++;
                    setTimeout(function() {
                        self.executeAjaxRequest(action, data);
                    }, self.config.retryDelay);
                } else {
                    self.showError(wpPluginFilters.strings.error + ' (Status: ' + xhr.status + ')');
                }
            });
        },

        /**
         * Handle successful filter response
         */
        handleFilterSuccess: function(response) {
            console.log('[WP Plugin Filters] AJAX Success:', response);
            this.hideLoadingState();
            this.state.retryCount = 0;
            
            if (response.success && response.data) {
                console.log('[WP Plugin Filters] Updating grid with data:', response.data);
                this.updatePluginGrid(response.data);
                this.updateURL(this.state.currentFilters);
                this.updatePagination(response.data.pagination);
                this.showFilterSummary(response.data.filters_applied);
            } else {
                console.error('[WP Plugin Filters] Invalid response structure:', response);
                this.showError(response.data && response.data.message ? response.data.message : wpPluginFilters.strings.error);
            }
        },

        /**
         * Handle filter request errors
         */
        handleFilterError: function(xhr, textStatus, errorThrown) {
            this.hideLoadingState();
            
            var errorMessage = wpPluginFilters.strings.error;
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            }
            
            if (xhr.status === 429) {
                errorMessage = wpPluginFilters.strings.rateLimit;
            }
            
            this.showError(errorMessage);
        },

        /**
         * Update plugin grid with filtered results
         */
        updatePluginGrid: function(data) {
            var self = this;
            
            console.log('[WP Plugin Filters] updatePluginGrid called with:', data);
            
            if (!data.plugins || !Array.isArray(data.plugins)) {
                console.error('[WP Plugin Filters] No plugins array in data:', data);
                this.showNoResults();
                return;
            }
            
            console.log('[WP Plugin Filters] Found', data.plugins.length, 'plugins to display');
            
            // Try multiple selectors to find the results container
            var containerSelectors = [
                '.plugin-browser .plugin-list',
                '.wp-list-table tbody',
                '#the-list',
                '.plugin-browser',
                '#plugin-filter',
                '.plugin-install-tab-featured .plugin-list',
                '.plugin-install-tab-popular .plugin-list'
            ];
            
            var $container = null;
            for (var i = 0; i < containerSelectors.length; i++) {
                $container = $(containerSelectors[i]).first();
                if ($container.length) {
                    console.log('[WP Plugin Filters] Found container with selector:', containerSelectors[i]);
                    break;
                }
            }
            
            if (!$container || !$container.length) {
                console.error('[WP Plugin Filters] Could not find results container. Available elements:', {
                    pluginBrowser: $('.plugin-browser').length,
                    pluginList: $('.plugin-list').length,
                    wpListTable: $('.wp-list-table').length,
                    theList: $('#the-list').length
                });
                return;
            }
            
            var pluginCards = data.plugins.map(function(plugin) {
                return self.buildPluginCard(plugin);
            }).join('');
            
            console.log('[WP Plugin Filters] Generated', pluginCards.length, 'characters of HTML');
            
            // Replace the content
            $container.html(pluginCards);
            
            // Update results count if available
            if (data.pagination && data.pagination.total_results) {
                $('.displaying-num').text(data.pagination.total_results + ' items');
            }
            
            // Enhance new cards
            this.enhancePluginCards($container.find('.plugin-card'));
            
            // Trigger WordPress events for compatibility
            $(document).trigger('wp-plugin-install-success');
            
            console.log('[WP Plugin Filters] Grid update completed');
        },

        /**
         * Build HTML for a plugin card with enhanced information
         */
        buildPluginCard: function(plugin) {
            var usabilityStars = this.buildStarRating(plugin.usability_rating || plugin.rating || 0);
            var healthBadge = this.buildHealthBadge(plugin.health_score || 0, plugin.health_color || 'gray');
            var lastUpdatedHuman = plugin.last_updated_human || this.formatRelativeTime(plugin.last_updated);
            
            return `
                <div class="plugin-card plugin-card-${plugin.slug}">
                    <div class="plugin-card-top">
                        <div class="name column-name">
                            <h3>
                                <a href="#" class="thickbox open-plugin-details-modal" 
                                   data-slug="${plugin.slug}">
                                    ${this.escapeHtml(plugin.name)}
                                    <img src="${plugin.icons && plugin.icons['1x'] ? plugin.icons['1x'] : ''}" 
                                         class="plugin-icon" alt="">
                                </a>
                            </h3>
                        </div>
                        
                        <div class="action-links">
                            <ul class="plugin-action-buttons">
                                <li>
                                    <button class="button activate-now" data-slug="${plugin.slug}">
                                        ${wpPluginFilters.strings.installNow || 'Install Now'}
                                    </button>
                                </li>
                                <li>
                                    <a href="#" class="thickbox open-plugin-details-modal" 
                                       data-slug="${plugin.slug}">
                                        ${wpPluginFilters.strings.moreDetails || 'More Details'}
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="desc column-description">
                            <p>${this.escapeHtml(plugin.short_description || '')}</p>
                            <p class="authors">
                                <cite>${wpPluginFilters.strings.by || 'By'} ${this.escapeHtml(plugin.author || '')}</cite>
                            </p>
                        </div>
                    </div>
                    
                    <div class="plugin-card-bottom">
                        <div class="vers column-rating">
                            <div class="plugin-enhanced-rating">
                                <div class="star-rating-usability">
                                    ${usabilityStars}
                                    <span class="rating-label">${wpPluginFilters.strings.usability || 'Usability'}</span>
                                </div>
                                <div class="health-score-badge">
                                    ${healthBadge}
                                </div>
                            </div>
                        </div>
                        
                        <div class="column-updated">
                            <strong>${lastUpdatedHuman}</strong>
                        </div>
                        
                        <div class="column-downloaded">
                            ${this.formatInstallCount(plugin.active_installs || 0)} ${wpPluginFilters.strings.activeInstalls || 'active installations'}
                        </div>
                        
                        <div class="column-compatibility">
                            <span class="compatibility-compatible">
                                ${wpPluginFilters.strings.compatibleWith || 'Compatible with'} ${plugin.tested || ''}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Build star rating HTML
         */
        buildStarRating: function(rating) {
            var fullStars = Math.floor(rating);
            var hasHalfStar = (rating % 1) >= 0.5;
            var emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
            
            var starsHtml = '';
            
            // Full stars
            for (var i = 0; i < fullStars; i++) {
                starsHtml += '<span class="star star-full dashicons dashicons-star-filled"></span>';
            }
            
            // Half star
            if (hasHalfStar) {
                starsHtml += '<span class="star star-half dashicons dashicons-star-half"></span>';
            }
            
            // Empty stars
            for (var i = 0; i < emptyStars; i++) {
                starsHtml += '<span class="star star-empty dashicons dashicons-star-empty"></span>';
            }
            
            return `<div class="star-rating" data-rating="${rating}">${starsHtml} <span class="rating-number">(${rating.toFixed(1)})</span></div>`;
        },

        /**
         * Build health score badge HTML
         */
        buildHealthBadge: function(score, color) {
            var badgeClass = 'health-badge health-badge-' + color;
            var badgeText = score + '/100';
            
            return `<span class="${badgeClass}" data-score="${score}" title="${wpPluginFilters.strings.healthScore || 'Health Score'}: ${score}/100">${badgeText}</span>`;
        },

        /**
         * Format installation count for display
         */
        formatInstallCount: function(count) {
            if (count >= 1000000) {
                return Math.floor(count / 100000) / 10 + 'M+';
            } else if (count >= 1000) {
                return Math.floor(count / 100) / 10 + 'K+';
            } else {
                return count + '+';
            }
        },

        /**
         * Format relative time
         */
        formatRelativeTime: function(dateString) {
            if (!dateString) return '';
            
            var date = new Date(dateString);
            var now = new Date();
            var diff = now - date;
            var days = Math.floor(diff / (24 * 60 * 60 * 1000));
            
            if (days === 0) return wpPluginFilters.strings.today || 'Today';
            if (days === 1) return wpPluginFilters.strings.yesterday || 'Yesterday';
            if (days < 30) return days + ' ' + (wpPluginFilters.strings.daysAgo || 'days ago');
            if (days < 365) return Math.floor(days / 30) + ' ' + (wpPluginFilters.strings.monthsAgo || 'months ago');
            return Math.floor(days / 365) + ' ' + (wpPluginFilters.strings.yearsAgo || 'years ago');
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            this.state.isLoading = true;
            if (this.$elements.spinner.length) {
                this.$elements.spinner.addClass('is-active');
            }
            this.$elements.body.addClass('wp-plugin-filters-loading');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            this.state.isLoading = false;
            if (this.$elements.spinner.length) {
                this.$elements.spinner.removeClass('is-active');
            }
            this.$elements.body.removeClass('wp-plugin-filters-loading');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var errorHtml = `
                <div class="notice notice-error wp-plugin-filters-error">
                    <p><strong>${wpPluginFilters.strings.error || 'Error'}:</strong> ${this.escapeHtml(message)}</p>
                </div>
            `;
            
            $('.wp-plugin-filters-error').remove();
            this.$elements.filterControls.after(errorHtml);
            
            // Auto-remove error after 5 seconds
            setTimeout(function() {
                $('.wp-plugin-filters-error').fadeOut();
            }, 5000);
        },

        /**
         * Show no results message
         */
        showNoResults: function() {
            var noResultsHtml = `
                <div class="wp-plugin-filters-no-results">
                    <p>${wpPluginFilters.strings.noResults || 'No plugins found matching your criteria.'}</p>
                </div>
            `;
            
            if (this.$elements.resultsContainer.length) {
                this.$elements.resultsContainer.html(noResultsHtml);
            }
        },

        /**
         * Clear all filters
         */
        clearAllFilters: function() {
            this.$elements.installationRange.val('all');
            this.$elements.updateTimeframe.val('all');
            this.$elements.usabilityRating.val('0');
            this.$elements.healthScore.val('0');
            this.$elements.sortBy.val('relevance');
            this.$elements.sortDirection.val('desc');
            
            this.applyFilters();
        },

        /**
         * Load state from URL parameters
         */
        loadStateFromURL: function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('installation_range')) {
                this.$elements.installationRange.val(urlParams.get('installation_range'));
            }
            if (urlParams.get('update_timeframe')) {
                this.$elements.updateTimeframe.val(urlParams.get('update_timeframe'));
            }
            if (urlParams.get('usability_rating')) {
                this.$elements.usabilityRating.val(urlParams.get('usability_rating'));
            }
            if (urlParams.get('health_score')) {
                this.$elements.healthScore.val(urlParams.get('health_score'));
            }
            if (urlParams.get('sort_by')) {
                this.$elements.sortBy.val(urlParams.get('sort_by'));
            }
            if (urlParams.get('sort_direction')) {
                this.$elements.sortDirection.val(urlParams.get('sort_direction'));
            }
        },

        /**
         * Update URL with current filter state
         */
        updateURL: function(filterData) {
            var url = new URL(window.location);
            
            // Update URL parameters
            Object.keys(filterData).forEach(function(key) {
                if (filterData[key] && filterData[key] !== 'all' && filterData[key] !== '0' && filterData[key] !== 'relevance') {
                    url.searchParams.set(key, filterData[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });
            
            // Update browser history
            window.history.replaceState({}, '', url);
        },

        /**
         * Enhance existing plugin cards with ratings
         */
        enhanceExistingPlugins: function() {
            var self = this;
            var $pluginCards = $('.plugin-card');
            
            if ($pluginCards.length) {
                $pluginCards.each(function() {
                    self.loadPluginRatings($(this));
                });
            }
        },

        /**
         * Load plugin ratings for a specific card
         */
        loadPluginRatings: function($card) {
            var slug = $card.attr('class').match(/plugin-card-([^\s]+)/);
            if (!slug || !slug[1]) return;
            
            slug = slug[1];
            
            // Check if already enhanced
            if ($card.hasClass('wp-plugin-enhanced')) {
                return;
            }
            
            var self = this;
            $.ajax({
                url: wpPluginFilters.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_plugin_rating',
                    plugin_slug: slug,
                    nonce: wpPluginFilters.nonces.calculate_rating
                },
                dataType: 'json'
            }).done(function(response) {
                if (response.success && response.data) {
                    self.enhancePluginCard($card, response.data);
                }
            });
        },

        /**
         * Enhance a plugin card with calculated ratings
         */
        enhancePluginCard: function($card, ratingData) {
            $card.addClass('wp-plugin-enhanced');
            
            // Add usability rating
            var usabilityStars = this.buildStarRating(ratingData.usability_rating || 0);
            var $ratingDiv = $card.find('.vers.column-rating');
            if ($ratingDiv.length) {
                $ratingDiv.prepend(`
                    <div class="plugin-enhanced-rating">
                        <div class="star-rating-usability">
                            ${usabilityStars}
                            <span class="rating-label">${wpPluginFilters.strings.usability || 'Usability'}</span>
                        </div>
                    </div>
                `);
            }
            
            // Add health score badge
            var healthBadge = this.buildHealthBadge(
                ratingData.health_score || 0, 
                ratingData.health_color || 'gray'
            );
            $card.find('.plugin-enhanced-rating').append(`
                <div class="health-score-badge">${healthBadge}</div>
            `);
        },

        /**
         * Enhance multiple plugin cards with ratings and additional info
         */
        enhancePluginCards: function($cards) {
            var self = this;
            
            if (!$cards || $cards.length === 0) {
                return;
            }
            
            $cards.each(function() {
                var $card = $(this);
                var pluginSlug = self.extractPluginSlug($card);
                
                if (pluginSlug) {
                    // Load ratings for this plugin card
                    self.loadPluginRatings($card);
                    
                    // Add enhanced styling
                    $card.addClass('wp-plugin-enhanced');
                    
                    // Add click handler for detailed ratings
                    $card.find('.plugin-card-top').off('click.wpPluginFilters').on('click.wpPluginFilters', function() {
                        self.loadPluginRatings($card);
                    });
                }
            });
        },

        /**
         * Extract plugin slug from card element
         */
        extractPluginSlug: function($card) {
            // Try multiple methods to get plugin slug
            var slug = $card.data('slug') || 
                      $card.attr('data-slug') ||
                      $card.find('[data-slug]').data('slug');
            
            if (!slug) {
                // Try to extract from class name
                var classList = $card.attr('class') || '';
                var match = classList.match(/plugin-card-([^\s]+)/);
                if (match) {
                    slug = match[1];
                }
            }
            
            if (!slug) {
                // Try to extract from plugin install links
                var $installLink = $card.find('.install-now');
                if ($installLink.length) {
                    var href = $installLink.attr('href') || '';
                    var urlMatch = href.match(/plugin=([^&]+)/);
                    if (urlMatch) {
                        slug = urlMatch[1];
                    }
                }
            }
            
            return slug;
        },

        /**
         * Escape HTML for security
         */
        escapeHtml: function(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(match) {
                var escapeMap = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                };
                return escapeMap[match];
            });
        },

        /**
         * Update pagination controls
         */
        updatePagination: function(paginationData) {
            // WordPress pagination update logic would go here
            // This is a simplified version
            if (paginationData && this.$elements.paginationLinks.length) {
                // Update pagination display
                console.log('Updating pagination:', paginationData);
            }
        },

        /**
         * Show filter summary
         */
        showFilterSummary: function(appliedFilters) {
            $('.wp-plugin-filters-summary').remove();
            
            if (Object.keys(appliedFilters).length > 0) {
                var summaryHtml = '<div class="wp-plugin-filters-summary notice notice-info inline">';
                summaryHtml += '<p><strong>' + (wpPluginFilters.strings.filtersApplied || 'Filters applied') + ':</strong> ';
                
                var filterDescriptions = [];
                Object.keys(appliedFilters).forEach(function(key) {
                    if (appliedFilters[key] && appliedFilters[key] !== 'all' && appliedFilters[key] !== '0') {
                        filterDescriptions.push(key.replace('_', ' ') + ': ' + appliedFilters[key]);
                    }
                });
                
                summaryHtml += filterDescriptions.join(', ');
                summaryHtml += '</p></div>';
                
                this.$elements.filterControls.after(summaryHtml);
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize on plugin installer pages
        if ($('body').hasClass('plugin-install-php') || $('#plugin-filter').length) {
            WPPluginFilters.init();
        }
    });

})(jQuery);