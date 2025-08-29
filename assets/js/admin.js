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
            // Final check before initialization
            if (this.isModalContext()) {
                console.log('[WP Plugin Filters] Modal context detected during init, aborting');
                return;
            }

            this.cacheElements();
            this.injectFilterControls();
            this.bindEvents();
            this.saveOriginalPlugins();
            this.enhanceNativePluginCards();
            this.monitorForModals();
            // loadStateFromURL disabled to avoid auto-applying filters on load
            
            // DO NOT add any body classes on init - only when filters are applied
            // Plugin initialized successfully
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
                searchInput: $('#plugin-search-input, .wp-filter-search input[type="search"], input[name="s"]').first(),
                resultsContainer: $('.plugin-browser .plugin-list-table-body, .plugin-browser .wp-list-table tbody, #the-list'),
                pluginCards: $('.plugin-browser .plugin-card'),
                paginationLinks: $('.tablenav-pages')
            };
        },

        /**
         * Refresh cached DOM elements - critical for maintaining functionality after DOM changes
         */
        refreshElementCache: function() {
            console.log('[WP Plugin Filters] Refreshing element cache...');
            
            // Re-cache basic elements that might have changed
            this.$elements.searchInput = $('#plugin-search-input, .wp-filter-search input[type="search"], input[name="s"]').first();
            this.$elements.resultsContainer = $('.plugin-browser .plugin-list-table-body, .plugin-browser .wp-list-table tbody, #the-list');
            this.$elements.pluginCards = $('.plugin-browser .plugin-card');
            this.$elements.paginationLinks = $('.tablenav-pages');
            
            // Re-cache filter control elements (these should still exist)
            this.$elements.filterControls = $('.wp-plugin-filters-controls');
            this.$elements.installationRange = $('#wp-plugin-filter-installations');
            this.$elements.updateTimeframe = $('#wp-plugin-filter-updates');
            this.$elements.usabilityRating = $('#wp-plugin-filter-usability');
            this.$elements.healthScore = $('#wp-plugin-filter-health');
            this.$elements.rating = $('#wp-plugin-filter-rating');
            this.$elements.sortBy = $('#wp-plugin-filter-sort');
            this.$elements.sortDirection = $('#wp-plugin-filter-direction');
            this.$elements.clearFilters = $('#wp-plugin-clear-filters');
            
            console.log('[WP Plugin Filters] Element cache refreshed:', {
                filterControls: this.$elements.filterControls.length,
                installationRange: this.$elements.installationRange.length,
                searchInput: this.$elements.searchInput.length,
                resultsContainer: this.$elements.resultsContainer.length
            });
        },

        /**
         * Simple detection of modal/popup contexts - KEEP IT SIMPLE
         */
        isModalContext: function() {
            // Only check for the most specific modal contexts
            var url = window.location.href;
            
            // Check URL parameters for plugin information modal
            if (url.indexOf('tab=plugin-information') !== -1 && 
                url.indexOf('TB_iframe=true') !== -1) {
                return true;
            }

            // Check if we're in a thickbox iframe showing plugin details
            if (window !== window.top && 
                (url.indexOf('TB_iframe=true') !== -1 || url.indexOf('plugin-information') !== -1)) {
                return true;
            }

            return false;
        },

        /**
         * Monitor for dynamically loaded modals and remove filters if detected
         */
        monitorForModals: function() {
            var self = this;
            
            // Use MutationObserver to watch for modal elements being added
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            // Check if any modal elements were added
                            var modalSelectors = [
                                '#plugin-information-modal',
                                '.thickbox',
                                '#TB_window',
                                '#TB_overlay',
                                '[role="dialog"]',
                                '.ui-dialog',
                                '#plugin-information-content',
                                '.plugin-information',
                                '.plugin-details-modal'
                            ];
                            
                            for (var i = 0; i < modalSelectors.length; i++) {
                                if ($(modalSelectors[i]).length > 0) {
                                    console.log('[WP Plugin Filters] Modal detected after initialization, removing filter controls');
                                    self.removeFilterControls();
                                    return;
                                }
                            }
                        }
                    });
                });
                
                // Start observing
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }

            // Also listen for WordPress events that might indicate modal opening
            $(document).on('tb:iframe:loaded', function() {
                console.log('[WP Plugin Filters] ThickBox iframe loaded, removing filter controls');
                self.removeFilterControls();
            });

            $(document).on('wp-plugin-install-modal-open', function() {
                console.log('[WP Plugin Filters] Plugin install modal opened, removing filter controls');
                self.removeFilterControls();
            });
        },

        /**
         * Remove filter controls from the page
         */
        removeFilterControls: function() {
            $('.wp-plugin-filters-controls').remove();
            $('.wp-plugin-filters-summary').remove();
            $('.wp-plugin-filters-error').remove();
            console.log('[WP Plugin Filters] Filter controls removed');
        },

        /**
         * Save original plugins from page load
         */
        saveOriginalPlugins: function() {
            // Try to capture original plugin data from the page
            var originalCards = $('.plugin-card, .plugin-list-item, #the-list > tr');
            
            // For now, we'll save the HTML content - in a future version we could 
            // extract actual plugin data, but this preserves the original layout
            if (originalCards.length > 0) {
                this.state.originalContent = originalCards.parent().html();
            }
        },

        /**
         * Inject filter controls into the WordPress admin interface
         */
        injectFilterControls: function() {
            console.log('[WP Plugin Filters] Starting filter controls injection...');
            
            // Enhanced modal detection - Don't inject in any modal/popup contexts
            if (this.isModalContext()) {
                console.log('[WP Plugin Filters] Modal context detected, skipping injection');
                return;
            }
            
            // Prevent double injection
            if ($('.wp-plugin-filters-controls').length > 0) {
                console.log('[WP Plugin Filters] Filter controls already exist, updating cache and skipping injection');
                // Update cache to ensure we have fresh references
                this.refreshElementCache();
                return;
            }
            
            var filterControlsHTML = this.buildFilterControlsHTML();
            console.log('[WP Plugin Filters] Built filter controls HTML');
            
            // Debug: Check what elements are available
            console.log('[WP Plugin Filters] Available elements:', {
                'wp-filter': $('.wp-filter').length,
                'search-box': $('.search-box').length,
                'plugin-search-input': $('#plugin-search-input').length,
                'plugin-filter': $('#plugin-filter').length,
                'plugin-browser': $('.plugin-browser').length,
                'wp-list-table': $('.wp-list-table').length
            });
            
            // Insert ABOVE the native search box - try multiple selectors in order
            var inserted = false;
            
            // Try to find the search container and insert before it
            if ($('.wp-filter').length) {
                console.log('[WP Plugin Filters] Inserting before .wp-filter');
                $('.wp-filter').before(filterControlsHTML);
                inserted = true;
            } else if ($('.search-box').length) {
                console.log('[WP Plugin Filters] Inserting before .search-box');
                $('.search-box').before(filterControlsHTML);
                inserted = true;
            } else if ($('#plugin-search-input').length) {
                console.log('[WP Plugin Filters] Inserting before plugin-search-input container');
                $('#plugin-search-input').closest('form, div, p').before(filterControlsHTML);
                inserted = true;
            } else if ($('#plugin-filter').length) {
                console.log('[WP Plugin Filters] Prepending to #plugin-filter');
                $('#plugin-filter').prepend(filterControlsHTML);
                inserted = true;
            } else if ($('.plugin-browser').length) {
                console.log('[WP Plugin Filters] Prepending to .plugin-browser');
                $('.plugin-browser').prepend(filterControlsHTML);
                inserted = true;
            } else if ($('.wrap').length) {
                console.log('[WP Plugin Filters] Inserting after .wrap h1');
                $('.wrap h1').first().after(filterControlsHTML);
                inserted = true;
            } else if ($('#wpbody-content').length) {
                console.log('[WP Plugin Filters] Prepending to #wpbody-content');
                $('#wpbody-content').prepend(filterControlsHTML);
                inserted = true;
            }
            
            if (!inserted) {
                console.warn('[WP Plugin Filters] Could not find suitable insertion point for filters');
                console.log('[WP Plugin Filters] Available elements for debugging:', {
                    'body': $('body').length,
                    'wrap': $('.wrap').length,
                    'wpbody-content': $('#wpbody-content').length,
                    'wpbody': $('#wpbody').length
                });
                console.log('[WP Plugin Filters] DOM structure sample:', $('body').html().substring(0, 1000));
                
                // Last resort - insert at the beginning of body
                $('body').prepend('<div style="background: #f9f9f9; padding: 10px; margin: 10px; border: 1px solid #ddd;">' + filterControlsHTML + '</div>');
                inserted = true;
                console.log('[WP Plugin Filters] Inserted as last resort at beginning of body');
            }
            
            console.log('[WP Plugin Filters] Filter controls injected successfully');
            
            // Cache the new elements
            this.$elements.filterControls = $('.wp-plugin-filters-controls');
            this.$elements.installationRange = $('#wp-plugin-filter-installations');
            this.$elements.updateTimeframe = $('#wp-plugin-filter-updates');
            this.$elements.usabilityRating = $('#wp-plugin-filter-usability');
            this.$elements.healthScore = $('#wp-plugin-filter-health');
            this.$elements.rating = $('#wp-plugin-filter-rating');
            this.$elements.sortBy = $('#wp-plugin-filter-sort');
            this.$elements.sortDirection = $('#wp-plugin-filter-direction');
            this.$elements.clearFilters = $('#wp-plugin-clear-filters');
            
            console.log('[WP Plugin Filters] Cached filter elements:', {
                filterControls: this.$elements.filterControls.length,
                installationRange: this.$elements.installationRange.length,
                sortBy: this.$elements.sortBy.length
            });
        },

        /**
         * Build HTML for filter controls
         */
        buildFilterControlsHTML: function() {
            return `
                <div class="wp-plugin-filters-controls" style="position: relative; z-index: 1000;">
                    <form onsubmit="return false;" style="margin: 0; padding: 0;">
                        <div class="wp-plugin-filters-inline">
                            <div class="wp-plugin-filters-controls-left">
                                <select id="wp-plugin-filter-installations">
                                    <option value="all">All Installs</option>
                                    <option value="1m-plus">1M+</option>
                                    <option value="100k-1m">100K+</option>
                                    <option value="10k-100k">10K+</option>
                                    <option value="1k-10k">1K+</option>
                                </select>
                                
                                <select id="wp-plugin-filter-updates">
                                    <option value="all">Any Update</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_3months">Last 3 Months</option>
                                    <option value="last_year">Last Year</option>
                                </select>
                                
                                <select id="wp-plugin-filter-usability">
                                    <option value="0">Any Usability</option>
                                    <option value="4">4+ stars</option>
                                    <option value="3">3+ stars</option>
                                    <option value="2">2+ stars</option>
                                </select>
                                
                                <select id="wp-plugin-filter-health">
                                    <option value="0">Any Health</option>
                                    <option value="85">Excellent (85+)</option>
                                    <option value="70">Good (70+)</option>
                                    <option value="40">Fair (40+)</option>
                                </select>
                                
                                <select id="wp-plugin-filter-rating">
                                    <option value="0">Any Rating</option>
                                    <option value="4">4+ Stars</option>
                                    <option value="3">3+ Stars</option>
                                    <option value="2">2+ Stars</option>
                                    <option value="1">1+ Stars</option>
                                </select>
                                
                                <select id="wp-plugin-filter-sort">
                                    <option value="">Sort Order</option>
                                    <option value="relevance">Relevance</option>
                                    <option value="installations">Installs</option>
                                    <option value="rating">Rating</option>
                                    <option value="updated">Updated</option>
                                    <option value="usability_rating">Usability</option>
                                    <option value="health_score">Health</option>
                                </select>
                                
                                <button type="button" id="wp-plugin-apply-filters" class="button button-primary">Apply Filters</button>
                                <button type="button" id="wp-plugin-clear-filters" class="button">Clear</button>
                            </div>
                            <div class="wppd-filters-branding">
                                <a href="https://wppd-filters.terryarthur.com/index.html" target="_blank" rel="noopener" class="plugin-link">WPPD Filters</a>
                                <span class="separator">•</span>
                                <span class="made-by-text">by</span>
                                <a href="https://terryarthur.com" target="_blank" rel="noopener" class="author-link">Terry Arthur</a>
                            </div>
                        </div>
                    </form>
                </div>
            `;
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Apply filters button - only apply when clicked
            $(document).on('click', '#wp-plugin-apply-filters', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[WP Plugin Filters] Apply filters button clicked');
                self.applyFilters();
                return false;
            });
            
            // Clear filters button
            $(document).on('click', '#wp-plugin-clear-filters', function(e) {
                e.preventDefault();
                self.clearAllFilters();
            });
            
            // Native WordPress search form submission - handle with clean layout
            $(document).on('submit', '.wp-filter-search form, .search-form', function(e) {
                e.preventDefault();
                console.log('[WP Plugin Filters] Native search form submitted');
                
                // Get search term from native search input
                var searchTerm = $(this).find('input[type="search"], input[name="s"]').val() || '';
                console.log('[WP Plugin Filters] Native search term:', searchTerm);
                
                // Clear all custom filters first to ensure clean search
                self.clearFilterFormValues();
                
                // Perform clean search with just the search term
                self.performCleanSearch(searchTerm);
                
                return false;
            });
            
            // Watch for search input changes (including clear events)
            $(document).on('input keyup', '.wp-filter-search input[type="search"], input[name="s"], #plugin-search-input', function(e) {
                var searchTerm = $(this).val() || '';
                console.log('[WP Plugin Filters] Search input changed:', searchTerm);
                
                // If search box is cleared (empty), reset to default layout
                if (searchTerm.length === 0) {
                    console.log('[WP Plugin Filters] Search cleared, resetting to default layout');
                    self.resetToDefaultLayout();
                }
            });
            
            // Handle search clear button (X) specifically
            $(document).on('search', '.wp-filter-search input[type="search"], input[name="s"], #plugin-search-input', function(e) {
                var searchTerm = $(this).val() || '';
                console.log('[WP Plugin Filters] Search event triggered:', searchTerm);
                
                // If search box is cleared via X button, reset to default layout
                if (searchTerm.length === 0) {
                    console.log('[WP Plugin Filters] Search cleared via X button, resetting to default layout');
                    self.resetToDefaultLayout();
                }
            });
            
            // Pagination clicks
            $(document).on('click', '.tablenav-pages a', function(e) {
                e.preventDefault();
                self.handlePaginationClick($(this));
            });
            
            // Plugin card interactions - disabled to avoid modifying native cards
            // Cards will only be enhanced when filters are applied
        },

        /**
         * Handle filter changes - no longer auto-apply
         */
        handleFilterChange: function() {
            // Filters are now only applied when Apply Filters button is clicked
            // This prevents breaking the native search functionality
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
         * Apply current filters - Direct API call like working Chrome extension
         */
        applyFilters: function() {
            if (this.state.isLoading) {
                return;
            }
            
            // Ensure elements are fresh before applying filters
            this.refreshElementCache();
            
            // Defensive check - if filter controls are missing, re-inject them
            if (this.$elements.filterControls.length === 0) {
                console.warn('[WP Plugin Filters] Filter controls missing, re-injecting...');
                this.injectFilterControls();
            }
            
            var filterData = this.getCurrentFilterData();
            
            // Check if there's a search term before applying filters
            if (!filterData.search_term || filterData.search_term.trim() === '') {
                this.showSearchRequiredMessage();
                return;
            }
            
            this.state.currentFilters = filterData;
            
            // Check if this is a pure search (no filters applied) or filtered search
            var hasActiveFilters = this.hasActiveFilters(filterData);
            
            this.showLoadingState();
            
            // Use working extension approach - direct API call
            this.fetchPluginDataFromAPI(filterData.search_term)
                .then(function(response) {
                    if (hasActiveFilters) {
                        // Apply custom filtered layout
                        this.handleDirectAPISuccess(response);
                    } else {
                        // Apply clean native layout for pure search
                        this.handleDirectAPISuccessClean(response);
                    }
                }.bind(this))
                .catch(this.handleDirectAPIError.bind(this));
        },

        /**
         * Fetch plugin data from WordPress.org API directly (like Chrome extension)
         */
        fetchPluginDataFromAPI: function(searchTerm) {
            var self = this;
            searchTerm = searchTerm || '';
            
            console.log('[WP Plugin Filters] fetchPluginDataFromAPI called with search term:', searchTerm);
            
            var apiUrl = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins' +
                '&request[search]=' + encodeURIComponent(searchTerm) +
                '&request[per_page]=100' +
                '&request[page]=1' +
                '&request[fields][short_description]=true' +
                '&request[fields][rating]=true' +
                '&request[fields][ratings]=true' +
                '&request[fields][active_installs]=true' +
                '&request[fields][last_updated]=true' +
                '&request[fields][icons]=true' +
                '&request[fields][num_ratings]=true';
            
            console.log('[WP Plugin Filters] Calling WordPress.org API directly:', apiUrl);
            
            return fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('[WP Plugin Filters] Direct API response:', data);
                if (data && data.plugins && Array.isArray(data.plugins)) {
                    return {
                        plugins: data.plugins,
                        pagination: {
                            page: data.info?.page || 1,
                            pages: data.info?.pages || 1,
                            total_results: data.info?.results || data.plugins.length
                        }
                    };
                } else {
                    throw new Error('Invalid API response structure');
                }
            });
        },

        /**
         * Check if any filters are actively applied (not just search)
         */
        hasActiveFilters: function(filterData) {
            // Check if any non-search filters are applied
            return (filterData.installation_range && filterData.installation_range !== 'all') ||
                   (filterData.update_timeframe && filterData.update_timeframe !== 'all') ||
                   (filterData.usability_rating && parseFloat(filterData.usability_rating) > 0) ||
                   (filterData.health_score && parseInt(filterData.health_score) > 0) ||
                   (filterData.rating && parseFloat(filterData.rating) > 0) ||
                   (filterData.sort_by && filterData.sort_by !== '');
        },

        /**
         * Handle successful direct API response with clean native layout
         */
        handleDirectAPISuccessClean: function(response) {
            console.log('[WP Plugin Filters] Direct API Success (Clean Layout):', response);
            this.hideLoadingState();
            this.state.retryCount = 0;
            
            if (response && response.plugins) {
                // For clean layout, don't apply client-side filters (only search was used)
                var processedResponse = {
                    plugins: response.plugins,
                    pagination: response.pagination
                };
                
                console.log('[WP Plugin Filters] Updating grid with clean native layout:', processedResponse);
                // Remove filter classes to ensure native WordPress layout
                $('body').removeClass('wp-filter-active wp-filter-results-active');
                this.updatePluginGridClean(processedResponse);
                this.updatePagination(processedResponse.pagination);
            } else {
                console.error('[WP Plugin Filters] Invalid clean API response structure:', response);
                this.showError('Invalid API response structure');
            }
        },

        /**
         * Handle successful direct API response with filtered layout
         */
        handleDirectAPISuccess: function(response) {
            console.log('[WP Plugin Filters] Direct API Success (Filtered Layout):', response);
            this.hideLoadingState();
            this.state.retryCount = 0;
            
            if (response && response.plugins) {
                // Apply client-side filters
                var filteredPlugins = this.applyClientSideFilters(response.plugins, this.state.currentFilters);
                
                var processedResponse = {
                    plugins: filteredPlugins,
                    pagination: response.pagination
                };
                
                console.log('[WP Plugin Filters] Updating grid with filtered layout:', processedResponse);
                this.updatePluginGrid(processedResponse);
                this.updatePagination(processedResponse.pagination);
            } else {
                console.error('[WP Plugin Filters] Invalid filtered API response structure:', response);
                this.showError('Invalid API response structure');
            }
        },

        /**
         * Handle direct API errors
         */
        handleDirectAPIError: function(error) {
            console.error('[WP Plugin Filters] Direct API Error:', error);
            this.hideLoadingState();
            this.showError('Failed to fetch plugins: ' + error.message);
        },

        /**
         * Apply filters client-side (like Chrome extension)
         */
        applyClientSideFilters: function(plugins, filterData) {
            var self = this;
            
            var filtered = plugins.filter(function(plugin) {
                // Installation range filter
                if (filterData.installation_range && filterData.installation_range !== 'all') {
                    var installs = plugin.active_installs || 0;
                    var rangeMap = {
                        '1m-plus': 1000000,
                        '100k-1m': 100000,
                        '10k-100k': 10000,
                        '1k-10k': 1000
                    };
                    var minInstalls = rangeMap[filterData.installation_range];
                    if (minInstalls && installs < minInstalls) {
                        return false;
                    }
                }
                
                // Update timeframe filter
                if (filterData.update_timeframe && filterData.update_timeframe !== 'all') {
                    var lastUpdated = self.parseWordPressDate(plugin.last_updated);
                    var daysSinceUpdate = Math.floor((new Date() - lastUpdated) / (1000 * 60 * 60 * 24));
                    var maxDaysMap = {
                        'last_month': 30,
                        'last_3months': 90,
                        'last_year': 365
                    };
                    var maxDays = maxDaysMap[filterData.update_timeframe];
                    if (maxDays && daysSinceUpdate > maxDays) {
                        return false;
                    }
                }
                
                // Usability rating filter
                if (filterData.usability_rating && parseFloat(filterData.usability_rating) > 0) {
                    var usability = self.calculateUsability(plugin.ratings || {}, plugin.num_ratings || 0);
                    if (usability.score < parseFloat(filterData.usability_rating)) {
                        return false;
                    }
                }
                
                // Health score filter  
                if (filterData.health_score && parseInt(filterData.health_score) > 0) {
                    var healthScore = self.calculateHealthProxy(plugin);
                    if (healthScore < parseInt(filterData.health_score)) {
                        return false;
                    }
                }
                
                // Rating filter
                if (filterData.rating && parseFloat(filterData.rating) > 0) {
                    var pluginRating = plugin.rating || 0;
                    if (pluginRating < parseFloat(filterData.rating)) {
                        return false;
                    }
                }
                
                return true;
            });

            // Only sort if a specific sort order is selected
            if (filterData.sort_by && filterData.sort_by !== '') {
                filtered.sort(function(a, b) {
                    // Sort by selected criteria
                    var sortBy = filterData.sort_by;
                    switch (sortBy) {
                        case 'relevance':
                            return 0; // Keep original order for relevance
                        case 'installations':
                            return (b.active_installs || 0) - (a.active_installs || 0);
                        case 'rating':
                            return (b.rating || 0) - (a.rating || 0);
                        case 'updated':
                            var dateA = self.parseWordPressDate(a.last_updated);
                            var dateB = self.parseWordPressDate(b.last_updated);
                            return dateB - dateA;
                        case 'usability_rating':
                            var usabilityA = self.calculateUsability(a.ratings || {}, a.num_ratings || 0);
                            var usabilityB = self.calculateUsability(b.ratings || {}, b.num_ratings || 0);
                            return usabilityB.score - usabilityA.score;
                        case 'health_score':
                            return self.calculateHealthProxy(b) - self.calculateHealthProxy(a);
                        default:
                            return 0;
                    }
                });
            }

            return filtered;
        },

        /**
         * Get icon URL from plugin data (handles WordPress.org API format)
         */
        getIconUrl: function(plugin) {
            if (!plugin || !plugin.icons || typeof plugin.icons !== 'object') {
                return ''; // Return empty string for broken images to hide via onerror
            }
            
            var icons = plugin.icons;
            console.log('Getting icon URL for', plugin.slug, '- available icons:', JSON.stringify(icons));
            
            // Priority order: svg, 2x, 1x, default
            if (icons.svg) return icons.svg;
            if (icons['2x']) return icons['2x'];  
            if (icons['1x']) return icons['1x'];
            if (icons.default) return icons.default;
            
            // No valid icons found
            return '';
        },

        /**
         * Get current filter data from form
         */
        getCurrentFilterData: function() {
            // Defensive checks - ensure elements exist before accessing their values
            var searchTerm = '';
            if (this.$elements.searchInput && this.$elements.searchInput.length > 0) {
                searchTerm = this.$elements.searchInput.val() || '';
            }
            
            console.log('[WP Plugin Filters] Search term captured:', searchTerm);
            console.log('[WP Plugin Filters] Search input element:', this.$elements.searchInput ? this.$elements.searchInput.length : 0);
            
            return {
                search_term: searchTerm,
                installation_range: (this.$elements.installationRange && this.$elements.installationRange.length) ? this.$elements.installationRange.val() || 'all' : 'all',
                update_timeframe: (this.$elements.updateTimeframe && this.$elements.updateTimeframe.length) ? this.$elements.updateTimeframe.val() || 'all' : 'all',
                usability_rating: (this.$elements.usabilityRating && this.$elements.usabilityRating.length) ? parseFloat(this.$elements.usabilityRating.val()) || 0 : 0,
                health_score: (this.$elements.healthScore && this.$elements.healthScore.length) ? parseInt(this.$elements.healthScore.val()) || 0 : 0,
                rating: (this.$elements.rating && this.$elements.rating.length) ? parseFloat(this.$elements.rating.val()) || 0 : 0,
                sort_by: (this.$elements.sortBy && this.$elements.sortBy.length) ? this.$elements.sortBy.val() || '' : '',
                sort_direction: (this.$elements.sortDirection && this.$elements.sortDirection.length) ? this.$elements.sortDirection.val() || 'desc' : 'desc',
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
                'wppdfi_filter': 'filter_plugins',
                'wppdfi_sort': 'sort_plugins', 
                'wppdfi_rating': 'calculate_rating',
                'wppdfi_clear_cache': 'clear_cache'
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
         * Handle successful filter response (UNUSED - using direct API now)
         */
        handleFilterSuccess: function(response) {
            // This function is no longer used - we call WordPress.org API directly
            console.log('[WP Plugin Filters] handleFilterSuccess called but should not be - using direct API');
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
         * Update plugin grid with filtered results - Match Chrome extension approach
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
            
            // Debug: Log first plugin data to see what we're getting from direct API
            if (data.plugins.length > 0) {
                console.log('[WP Plugin Filters] Sample plugin data from DIRECT API:', {
                    name: data.plugins[0].name,
                    slug: data.plugins[0].slug,
                    icons: data.plugins[0].icons,
                    hasIconsProperty: data.plugins[0].hasOwnProperty('icons'),
                    iconType: typeof data.plugins[0].icons,
                    iconKeys: data.plugins[0].icons ? Object.keys(data.plugins[0].icons) : 'no icons',
                    icon1x: data.plugins[0].icons?.['1x'],
                    icon2x: data.plugins[0].icons?.['2x'],
                    iconSvg: data.plugins[0].icons?.svg,
                    iconDefault: data.plugins[0].icons?.default,
                    fullPlugin: data.plugins[0]
                });
            }
            
            // WordPress admin plugin installer containers
            var containerSelectors = [
                '#the-list',
                '.plugin-browser .plugin-list',
                '.wp-list-table tbody',
                '.plugin-browser'
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
                    blockPostTemplate: $('.wp-block-post-template').length,
                    pluginCards: $('.plugin-cards').length,
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
            
            // Add classes to apply filtered styling ONLY when filters are actually applied
            $('body').addClass('wp-filter-active');
            $('body').addClass('wp-filter-results-active');
            
            console.log('[WP Plugin Filters] Applied filter classes - custom layout now active');
            
            // Only apply grid layout via CSS classes - don't force inline styles
            // This allows CSS to control the layout properly
            
            // Replace the content
            $container.html(pluginCards);
            
            // Icons will use browser's native error handling - no custom error handling needed
            
            // Update results count if available
            if (data.pagination && data.pagination.total_results) {
                $('.displaying-num').text(data.pagination.total_results + ' items');
            }
            
            // Cards are already enhanced during build, no need to enhance again
            
            // Trigger WordPress events for compatibility
            $(document).trigger('wp-plugin-install-success');
            
            console.log('[WP Plugin Filters] Grid update completed');
        },

        /**
         * Update plugin grid with clean results (no filter styling)
         */
        updatePluginGridClean: function(data) {
            var self = this;
            
            console.log('[WP Plugin Filters] updatePluginGridClean called with:', data);
            
            if (!data.plugins || !Array.isArray(data.plugins)) {
                console.error('[WP Plugin Filters] No plugins array in clean data:', data);
                this.showNoResults();
                return;
            }
            
            console.log('[WP Plugin Filters] Found', data.plugins.length, 'plugins to display cleanly');
            
            var containerSelectors = [
                '#the-list',
                '.plugin-browser .plugin-list',
                '.wp-list-table tbody',
                '.plugin-browser'
            ];
            
            var $container = null;
            for (var i = 0; i < containerSelectors.length; i++) {
                $container = $(containerSelectors[i]).first();
                if ($container.length) {
                    break;
                }
            }
            
            if (!$container || !$container.length) {
                console.error('[WP Plugin Filters] Could not find results container for clean update');
                return;
            }
            
            // Build clean native WordPress plugin cards (no enhancements)
            var pluginCards = data.plugins.map(function(plugin) {
                return self.buildNativePluginCard(plugin);
            }).join('');
            
            // DO NOT add filter classes for clean results
            // This maintains the native WordPress layout
            $('body').removeClass('wp-filter-active wp-filter-results-active');
            
            // Replace the content
            $container.html(pluginCards);
            
            // Update results count if available
            if (data.pagination && data.pagination.total_results) {
                $('.displaying-num').text(data.pagination.total_results + ' items');
            }
            
            console.log('[WP Plugin Filters] Clean grid update completed - native layout preserved');
        },

        /**
         * Build HTML for a native WordPress plugin card (no enhancements)
         */
        buildNativePluginCard: function(plugin) {
            var rating = plugin.rating ? (plugin.rating / 20) : 0; // Convert 0-100 to 0-5
            var installs = plugin.active_installs || 0;
            
            return `
                <li class="wp-block-post post-${plugin.slug} plugin type-plugin status-publish hentry">
                    <div class="plugin-card" data-slug="${plugin.slug}">
                        <div class="plugin-card-top">
                            <div class="name column-name">
                                <h3>
                                    <a href="${this.getPluginDetailsUrl(plugin.slug)}" class="thickbox open-plugin-details-modal" aria-label="${this.escapeHtml(plugin.name)} plugin information">
                                        ${this.escapeHtml(plugin.name)}
                                        <img class="plugin-icon" 
                                             src="${this.getIconUrl(plugin)}" 
                                             alt="${this.escapeHtml(plugin.name)} icon"
                                             onerror="this.style.display='none'">
                                    </a>
                                </h3>
                            </div>
                            <div class="action-links">
                                <ul>
                                    <li><a class="install-now button" data-slug="${plugin.slug}" href="${this.getInstallUrl(plugin.slug)}" aria-label="Install ${this.escapeHtml(plugin.name)} now">Install Now</a></li>
                                    <li><a href="${this.getPluginDetailsUrl(plugin.slug)}" class="thickbox open-plugin-details-modal" aria-label="More information about ${this.escapeHtml(plugin.name)}">More Details</a></li>
                                </ul>
                            </div>
                            <div class="desc column-description">
                                <p>${this.escapeHtml(plugin.short_description || '')}</p>
                                <p class="authors"><cite>By ${plugin.author || 'Unknown'}</cite></p>
                            </div>
                        </div>
                        <div class="plugin-card-bottom">
                            <div class="vers column-rating">
                                <div class="star-rating" aria-label="${rating} out of 5 stars" data-rating="${rating}">
                                    ${this.createStarRating(rating)}
                                </div>
                                <span class="num-ratings">(${plugin.num_ratings || 0})</span>
                            </div>
                            <div class="column-updated">
                                <strong>Last Updated:</strong>
                                <span title="${plugin.last_updated || 'Unknown'}">${this.formatRelativeTime(plugin.last_updated)}</span>
                            </div>
                            <div class="column-downloaded">
                                ${this.formatInstallCount(installs)} Active Installations
                            </div>
                            <div class="column-compatibility">
                                <span class="compatibility-compatible">
                                    <strong>Tested up to:</strong> ${plugin.tested || 'Unknown'}
                                </span>
                            </div>
                        </div>
                    </div>
                </li>
            `;
        },

        /**
         * Build HTML for a plugin card matching WordPress ADMIN structure
         */
        buildPluginCard: function(plugin) {
            var rating = plugin.rating ? (plugin.rating / 20) : 0; // Convert 0-100 to 0-5
            var installs = plugin.active_installs || 0;
            var healthScore = plugin.health_score || this.calculateHealthProxy(plugin);
            
            // Calculate usability score from ratings breakdown
            var usability = this.calculateUsability(plugin.ratings || {}, plugin.num_ratings || 0);
            var usabilityColor = this.getUsabilityColor(usability.score);
            
            // Get update status with color-coded indicator
            var updateStatus = this.getUpdateStatus(plugin.last_updated);
            
            // Get WordPress compatibility status
            var wpCompatStatus = this.getWPCompatibilityStatus(plugin.tested);
            
            return `
                <li class="wp-block-post post-${plugin.slug} plugin type-plugin status-publish hentry">
                    <div class="plugin-card wp-block-wporg-link-wrapper is-style-no-underline wp-plugin-enhanced" data-slug="${plugin.slug}">
                        <div class="entry">
                            <header class="entry-header" style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; position: relative;">
                                <div class="entry-thumbnail" style="flex-shrink: 0;">
                                    <img class="plugin-icon" 
                                         src="${this.getIconUrl(plugin)}" 
                                         alt="${this.escapeHtml(plugin.name)} icon" 
                                         style="width: 64px; height: 64px; border-radius: 4px; object-fit: cover;"
                                         onerror="this.style.display='none'">
                                </div>
                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <h3 class="entry-title" style="margin: 0 0 8px 0; padding-right: 120px;"><a href="${this.getPluginDetailsUrl(plugin.slug)}" class="thickbox open-plugin-details-modal" aria-label="${this.escapeHtml(plugin.name)} plugin information">${this.escapeHtml(plugin.name)}</a></h3>
                                </div>
                                <div class="plugin-card-details-action" style="position: absolute; top: 0; right: 0;">
                                    <a href="${this.getPluginDetailsUrl(plugin.slug)}" class="thickbox open-plugin-details-modal button" aria-label="More information about ${this.escapeHtml(plugin.name)}">More Details</a>
                                </div>
                            </header>

                            <div class="plugin-rating">
                                <div class="wporg-ratings" aria-label="${rating} out of 5 stars" data-title-template="%s out of 5 stars" data-rating="${rating}" style="color:#ffb900">
                                    ${this.createStarRating(rating)}
                                </div>
                                <span class="rating-count">(${plugin.num_ratings || 0}<span class="screen-reader-text"> total ratings</span>)</span>
                            </div>
                            <div class="entry-excerpt">
                                <p>${this.escapeHtml(plugin.short_description || '')}</p>
                            </div>
                        </div>

                        <footer style="position: relative;">
                            <div class="plugin-footer-info">
                                <span class="plugin-author">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" fill-rule="evenodd"></path></svg>
                                    ${plugin.author || 'Unknown'}
                                </span>
                                <span class="active-installs">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M11.25 5h1.5v15h-1.5V5zM6 10h1.5v10H6V10zm12 4h-1.5v6H18v-6z" clip-rule="evenodd"></path></svg>
                                    <span>${this.formatInstallCount(installs)} active installations</span>
                                </span>
                                <span class="tested-with ${wpCompatStatus.cssClass}">
                                    <span style="display: inline-flex; align-items: center; margin-right: 6px;">${wpCompatStatus.icon}</span>
                                    <span>${wpCompatStatus.text}${wpCompatStatus.label ? ' (' + wpCompatStatus.label + ')' : ''}</span>
                                </span>
                                <span class="last-updated ${updateStatus.cssClass}">
                                    <span style="display: inline-flex; align-items: center; margin-right: 6px;">${updateStatus.icon}</span>
                                    <span>${updateStatus.text}${updateStatus.label ? ' (' + updateStatus.label + ')' : ''}</span>
                                </span>
                                <span class="usability-score usability-${usabilityColor}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
                                    <span>Usability Rating: ${usability.score}/100</span>
                                </span>
                                <span class="health-score health-${this.getHealthColor(healthScore)}" data-slug="${plugin.slug}">
                                    <span class="health-meter">${this.getHealthPowerMeter(healthScore)}</span>
                                    <span>Health Score: ${healthScore}/100</span>
                                </span>
                            </div>
                            <div class="plugin-card-install-action" style="position: absolute; bottom: 0; right: 0;">
                                <a class="install-now button button-primary" data-slug="${plugin.slug}" href="${this.getInstallUrl(plugin.slug)}" aria-label="Install ${this.escapeHtml(plugin.name)} now">Install Now</a>
                            </div>
                        </footer>
                    </div>
                </li>
            `;
        },
        
        /**
         * Get current WordPress version for compatibility
         */
        getWPVersion: function() {
            return (typeof window.wp !== 'undefined' && window.wp.version) ? window.wp.version : '6.7.1';
        },

        /**
         * Get WordPress compatibility status based on tested version
         */
        getWPCompatibilityStatus: function(testedVersion) {
            // Current WordPress versions and security info (as of January 2025)
            var currentVersion = '6.7.1';
            var lastCriticalSecurityVersion = '6.4.4'; // Last version before critical security fixes
            
            if (!testedVersion) {
                return {
                    color: 'red',
                    icon: '<svg width="16" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-top: -1px;"><circle cx="12" cy="12" r="10" fill="#d63638"/><path d="m8.5 8.5 7 7m0-7-7 7" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
                    cssClass: 'wp-compat-unknown',
                    text: 'WordPress compatibility unknown',
                    label: 'Security Risk'
                };
            }
            
            // Clean version string (remove any extra text)
            var cleanTested = testedVersion.replace(/[^\d.]/g, '');
            
            // Compare versions
            if (this.compareVersions(cleanTested, currentVersion) >= 0) {
                // Tested with current or newer version
                return {
                    color: 'green',
                    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="#00a32a"/><path d="m9 12 2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    cssClass: 'wp-compat-current',
                    text: 'Tested with WordPress ' + testedVersion,
                    label: 'Up To Date'
                };
            } else if (this.compareVersions(cleanTested, lastCriticalSecurityVersion) >= 0) {
                // Tested with recent version but not current - show yellow for any non-current version
                return {
                    color: 'yellow',
                    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L2 22h20L12 2z" fill="#f56e28"/><path d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    cssClass: 'wp-compat-recent',
                    text: 'Tested with WordPress ' + testedVersion,
                    label: 'Outdated'
                };
            } else {
                // Tested with version before critical security updates
                return {
                    color: 'red',
                    icon: '<svg width="16" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-top: -1px;"><circle cx="12" cy="12" r="10" fill="#d63638"/><path d="m8.5 8.5 7 7m0-7-7 7" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
                    cssClass: 'wp-compat-outdated',
                    text: 'Tested with WordPress ' + testedVersion,
                    label: 'Security Risk'
                };
            }
        },

        /**
         * Compare semantic version strings (returns: -1, 0, or 1)
         */
        compareVersions: function(version1, version2) {
            var v1parts = version1.split('.').map(Number);
            var v2parts = version2.split('.').map(Number);
            var maxLength = Math.max(v1parts.length, v2parts.length);
            
            // Pad arrays to same length
            while (v1parts.length < maxLength) v1parts.push(0);
            while (v2parts.length < maxLength) v2parts.push(0);
            
            for (var i = 0; i < maxLength; i++) {
                if (v1parts[i] > v2parts[i]) return 1;
                if (v1parts[i] < v2parts[i]) return -1;
            }
            return 0;
        },

        /**
         * Calculate plugin usability score and return detailed breakdown
         * @param {Object} ratings - WP.org ratings breakdown {1: x, 2: x, 3: x, 4: x, 5: x}
         * @param {number} numRatings - total number of ratings
         * @param {number} globalMean - average rating across all plugins (default ~3.8)
         * @param {number} C - confidence constant (higher = more pull toward global mean for small samples)
         * @returns {Object} breakdown { avgStars, adjustedAvg, score, total, distribution }
         */
        calculateUsability: function(ratings, numRatings, globalMean, C) {
            globalMean = globalMean || 3.8;
            C = C || 100;
            
            if (!ratings || numRatings === 0) {
                return {
                    avgStars: 0,
                    adjustedAvg: 0,
                    score: 0,
                    total: 0,
                    distribution: {1:0,2:0,3:0,4:0,5:0}
                };
            }

            // Weighted average from distribution
            var weightedSum = 0;
            for (var i = 1; i <= 5; i++) {
                weightedSum += (i * (ratings[i] || 0));
            }
            var avgStars = weightedSum / numRatings;

            // Bayesian adjustment
            var adjustedAvg = ((C * globalMean) + (numRatings * avgStars)) / (C + numRatings);

            // Normalize to 0–100
            var score = (adjustedAvg / 5) * 100;

            return {
                avgStars: Math.round(avgStars * 10) / 10,       // plain average
                adjustedAvg: Math.round(adjustedAvg * 10) / 10, // smoothed average
                score: Math.round(score * 10) / 10,             // usability score
                total: numRatings,
                distribution: ratings
            };
        },

        /**
         * Get traffic light color based on usability score
         */
        getUsabilityColor: function(score) {
            if (score >= 70) {
                return 'green'; // Good usability
            } else if (score >= 40) {
                return 'yellow'; // Medium usability
            } else {
                return 'red'; // Poor usability
            }
        },

        /**
         * Parse WordPress date string
         */
        parseWordPressDate: function(dateString) {
            if (!dateString) return new Date(0);
            
            try {
                // Parse WordPress API date format: "2025-08-13 9:37am GMT"
                var dateStr = dateString.replace(/(\d+):(\d+)(am|pm) GMT/, ' $1:$2:00 $3 GMT');
                var parsedDate = new Date(dateStr);
                return !isNaN(parsedDate.getTime()) ? parsedDate : new Date(0);
            } catch (e) {
                console.warn('Date parsing failed:', dateString);
                return new Date(0);
            }
        },

        /**
         * Get time ago string
         */
        getTimeAgo: function(date) {
            var now = new Date();
            var diffTime = Math.abs(now - date);
            var diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            var diffHours = Math.floor(diffTime / (1000 * 60 * 60));
            
            // Today (within last 24 hours)
            if (diffHours < 24) {
                if (diffHours < 1) {
                    return 'Just now';
                } else if (diffHours === 1) {
                    return '1 hour ago';
                } else {
                    return diffHours + ' hours ago';
                }
            }
            
            // Yesterday
            if (diffDays === 1) {
                return 'Yesterday';
            }
            
            // This week (2-6 days ago)
            if (diffDays >= 2 && diffDays <= 6) {
                var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return 'Last ' + dayNames[date.getDay()];
            }
            
            // Last week (7-13 days ago)
            if (diffDays >= 7 && diffDays <= 13) {
                return 'Last week';
            }
            
            // This month (2-4 weeks ago)
            if (diffDays >= 14 && diffDays <= 30) {
                var weeks = Math.floor(diffDays / 7);
                return weeks + ' ' + (weeks === 1 ? 'week' : 'weeks') + ' ago';
            }
            
            // Last month to 11 months
            if (diffDays >= 31 && diffDays <= 365) {
                var months = Math.floor(diffDays / 30);
                if (months === 1) {
                    return 'Last month';
                } else {
                    return months + ' months ago';
                }
            }
            
            // Years
            var years = Math.floor(diffDays / 365);
            if (years === 1) {
                return 'Last year';
            } else {
                return years + ' years ago';
            }
        },

        /**
         * Create star rating HTML
         */
        createStarRating: function(rating) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                if (rating >= i) {
                    stars += '<span class="dashicons dashicons-star-filled"></span>';
                } else if (rating >= i - 0.5) {
                    stars += '<span class="dashicons dashicons-star-half"></span>';
                } else {
                    stars += '<span class="dashicons dashicons-star-empty"></span>';
                }
            }
            return stars;
        },

        /**
         * Calculate a health proxy score based on available plugin data
         */
        calculateHealthProxy: function(plugin) {
            var healthScore = 0;
            
            // Factor 1: How recently updated (40% weight)
            var lastUpdated = this.parseWordPressDate(plugin.last_updated);
            var daysSinceUpdate = Math.floor((new Date() - lastUpdated) / (1000 * 60 * 60 * 24));
            var updateScore = 0;
            if (daysSinceUpdate <= 30) updateScore = 40;        // Recently updated
            else if (daysSinceUpdate <= 90) updateScore = 30;   // Updated within 3 months
            else if (daysSinceUpdate <= 180) updateScore = 20;  // Updated within 6 months
            else if (daysSinceUpdate <= 365) updateScore = 10;  // Updated within 1 year
            else updateScore = 0;                               // Over 1 year old
            
            // Factor 2: Rating quality (30% weight)
            var rating = plugin.rating ? (plugin.rating / 20) : 0; // Convert 0-100 to 0-5
            var numRatings = plugin.num_ratings || 0;
            var ratingScore = 0;
            if (rating >= 4.5 && numRatings >= 50) ratingScore = 30;      // Excellent with good sample
            else if (rating >= 4.0 && numRatings >= 20) ratingScore = 25; // Very good
            else if (rating >= 3.5 && numRatings >= 10) ratingScore = 20; // Good
            else if (rating >= 3.0 && numRatings >= 5) ratingScore = 15;  // Decent
            else if (rating >= 2.0) ratingScore = 10;                    // Poor
            else ratingScore = 0;                                         // Very poor or no ratings
            
            // Factor 3: Active installations (20% weight)
            var installs = plugin.active_installs || 0;
            var installScore = 0;
            if (installs >= 1000000) installScore = 20;      // 1M+ installs
            else if (installs >= 100000) installScore = 17;  // 100K+ installs
            else if (installs >= 10000) installScore = 14;   // 10K+ installs
            else if (installs >= 1000) installScore = 11;    // 1K+ installs
            else if (installs >= 100) installScore = 8;      // 100+ installs
            else installScore = 5;                           // Less than 100 installs
            
            // Factor 4: WordPress version compatibility (10% weight)
            var tested = plugin.tested || '';
            var compatScore = 0;
            if (tested >= '6.0') compatScore = 10;       // Recent WordPress version
            else if (tested >= '5.8') compatScore = 8;   // Fairly recent
            else if (tested >= '5.5') compatScore = 6;   // Somewhat recent
            else if (tested >= '5.0') compatScore = 4;   // Older but still supported
            else compatScore = 2;                        // Very old or unknown
            
            healthScore = updateScore + ratingScore + installScore + compatScore;
            
            // Ensure score is between 0-100
            return Math.min(100, Math.max(0, healthScore));
        },

        /**
         * Get health color based on score
         */
        getHealthColor: function(score) {
            if (score >= 80) return 'green';
            if (score >= 50) return 'yellow';
            return 'red';
        },

        /**
         * Get update status info (color, icon, text) based on last updated date
         */
        getUpdateStatus: function(lastUpdated) {
            var parsedDate = this.parseWordPressDate(lastUpdated);
            var daysSinceUpdate = Math.floor((new Date() - parsedDate) / (1000 * 60 * 60 * 24));
            var monthsSinceUpdate = Math.floor(daysSinceUpdate / 30);
            
            var status = {
                color: 'red',
                icon: '<svg width="16" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-top: -1px;"><circle cx="12" cy="12" r="10" fill="#d63638"/><path d="m8.5 8.5 7 7m0-7-7 7" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
                cssClass: 'update-status-old',
                text: 'Updated ' + this.getTimeAgo(parsedDate),
                label: 'Security Risk'
            };
            
            if (daysSinceUpdate <= 90) { // 3 months
                status.color = 'green';
                status.icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="#00a32a"/><path d="m9 12 2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                status.cssClass = 'update-status-recent';
                status.label = 'Up To Date';
            } else if (daysSinceUpdate <= 270) { // 9 months
                status.color = 'yellow';
                status.icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L2 22h20L12 2z" fill="#f56e28"/><path d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                status.cssClass = 'update-status-moderate';
                status.label = 'Outdated';
            }
            
            return status;
        },

        /**
         * Get health power meter emoji based on score
         */
        getHealthPowerMeter: function(score) {
            if (score >= 80) {
                return '🔋'; // Full battery for high health (80-100)
            } else if (score >= 60) {
                return '🔋'; // High battery for good health (60-79)
            } else if (score >= 40) {
                return '🪫'; // Medium battery for fair health (40-59)
            } else if (score >= 20) {
                return '🪫'; // Low battery for poor health (20-39)
            } else {
                return '🪫'; // Critical battery for very poor health (0-19)
            }
        },

        /**
         * Build star rating HTML
         */
        buildStarRating: function(rating, numRatings) {
            rating = parseFloat(rating) || 0;
            numRatings = parseInt(numRatings) || 0;
            
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
            
            var ratingText = rating > 0 ? `${rating.toFixed(1)} out of 5 stars` : 'No rating yet';
            var ratingsCount = numRatings > 0 ? `(${numRatings} rating${numRatings !== 1 ? 's' : ''})` : '';
            
            return `
                <div class="star-rating" data-rating="${rating}">
                    <span class="screen-reader-text">${ratingText}</span>
                    ${starsHtml}
                    <span class="num-ratings" aria-hidden="true">${ratingsCount}</span>
                </div>
            `;
        },

        /**
         * Build health score badge HTML
         */
        buildHealthBadge: function(score, color) {
            score = parseInt(score) || 0;
            
            // Auto-determine color based on score if not provided
            if (!color || color === 'gray') {
                if (score >= 85) color = 'excellent';
                else if (score >= 70) color = 'good';
                else if (score >= 40) color = 'fair';
                else color = 'poor';
            }
            
            var badgeClass = 'wp-health-badge wp-health-' + color;
            var badgeText = score;
            var badgeLabel = this.getHealthLabel(score);
            
            return `<span class="${badgeClass}" data-score="${score}" title="Health Score: ${score}/100 (${badgeLabel})">${badgeText}</span>`;
        },

        /**
         * Get health label based on score
         */
        getHealthLabel: function(score) {
            if (score >= 85) return 'Excellent';
            if (score >= 70) return 'Good';
            if (score >= 40) return 'Fair';
            return 'Poor';
        },

        /**
         * Format installation count for display - Match Chrome extension
         */
        formatInstallCount: function(count) {
            if (count >= 1000000) {
                return Math.floor(count / 1000000) + '+ million';
            } else if (count >= 1000) {
                return Math.floor(count / 1000) + '+ thousand';
            } else if (count > 0) {
                return count + '+';
            }
            return 'Less than 10';
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
         * Show message when user tries to filter without search term
         */
        showSearchRequiredMessage: function() {
            // Remove any existing message
            $('.wp-plugin-search-required').remove();
            
            // Create message
            var message = $(`
                <div class="wp-plugin-search-required notice notice-warning" style="margin: 10px 0; padding: 12px;">
                    <p><strong>Please enter a search keyword first.</strong> Filtering and sorting require a search term for better performance.</p>
                </div>
            `);
            
            // Insert after filter controls
            var filterControls = $('.wp-plugin-filters-controls');
            if (filterControls.length) {
                filterControls.after(message);
            }
            
            // Focus search input
            if (this.$elements.searchInput && this.$elements.searchInput.length) {
                this.$elements.searchInput.focus();
            }
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                message.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        },

        /**
         * Clear filter form values only (not search)
         */
        clearFilterFormValues: function() {
            // Reset all filter form values with defensive checks
            if (this.$elements.installationRange && this.$elements.installationRange.length) {
                this.$elements.installationRange.val('all');
            }
            if (this.$elements.updateTimeframe && this.$elements.updateTimeframe.length) {
                this.$elements.updateTimeframe.val('all');
            }
            if (this.$elements.usabilityRating && this.$elements.usabilityRating.length) {
                this.$elements.usabilityRating.val('0');
            }
            if (this.$elements.healthScore && this.$elements.healthScore.length) {
                this.$elements.healthScore.val('0');
            }
            if (this.$elements.rating && this.$elements.rating.length) {
                this.$elements.rating.val('0');
            }
            if (this.$elements.sortBy && this.$elements.sortBy.length) {
                this.$elements.sortBy.val('');
            }
            if (this.$elements.sortDirection && this.$elements.sortDirection.length) {
                this.$elements.sortDirection.val('desc');
            }
            
            console.log('[WP Plugin Filters] Filter form values cleared');
        },

        /**
         * Perform clean search with native WordPress layout
         */
        performCleanSearch: function(searchTerm) {
            console.log('[WP Plugin Filters] Performing clean search for:', searchTerm);
            
            this.showLoadingState();
            
            // Fetch plugins with just the search term
            this.fetchPluginDataFromAPI(searchTerm)
                .then(function(response) {
                    // Always use clean layout for native searches
                    this.handleDirectAPISuccessClean(response);
                }.bind(this))
                .catch(this.handleDirectAPIError.bind(this));
        },

        /**
         * Reset to default WordPress layout (when search is cleared)
         */
        resetToDefaultLayout: function() {
            console.log('[WP Plugin Filters] Resetting to default layout');
            
            // Clear all custom filters
            this.clearFilterFormValues();
            
            // Remove all filter classes to restore native WordPress layout
            $('body').removeClass('wp-filter-active wp-filter-results-active wp-plugin-filters-loading');
            
            // Remove any enhanced classes from plugin cards
            $('.plugin-card').removeClass('wp-plugin-enhanced');
            
            // Reset state
            this.state.currentFilters = {};
            this.state.isLoading = false;
            this.state.retryCount = 0;
            
            // Restore original WordPress plugin content if available
            if (this.state.originalContent) {
                console.log('[WP Plugin Filters] Restoring saved original HTML content');
                var $container = this.$elements.resultsContainer;
                if (!$container.length) {
                    $container = $('#the-list, .wp-list-table tbody').first();
                }
                if ($container.length) {
                    $container.parent().html(this.state.originalContent);
                    console.log('[WP Plugin Filters] Original content restored successfully');
                } else {
                    console.warn('[WP Plugin Filters] Could not find container to restore original content');
                    // Fallback: perform a clean search with empty term to get default plugins
                    this.fallbackToBrowseAll();
                }
            } else {
                console.log('[WP Plugin Filters] No saved original content - using fallback');
                // Fallback: perform a clean search with empty term to get default plugins
                this.fallbackToBrowseAll();
            }
            
            // CRITICAL: Re-cache elements after DOM changes
            this.refreshElementCache();
        },

        /**
         * Clear all filters and revert to native WordPress layout
         */
        clearAllFilters: function() {
            // Reset all filter form values with defensive checks
            if (this.$elements.installationRange && this.$elements.installationRange.length) {
                this.$elements.installationRange.val('all');
            }
            if (this.$elements.updateTimeframe && this.$elements.updateTimeframe.length) {
                this.$elements.updateTimeframe.val('all');
            }
            if (this.$elements.usabilityRating && this.$elements.usabilityRating.length) {
                this.$elements.usabilityRating.val('0');
            }
            if (this.$elements.healthScore && this.$elements.healthScore.length) {
                this.$elements.healthScore.val('0');
            }
            if (this.$elements.rating && this.$elements.rating.length) {
                this.$elements.rating.val('0');
            }
            if (this.$elements.sortBy && this.$elements.sortBy.length) {
                this.$elements.sortBy.val('');
            }
            if (this.$elements.sortDirection && this.$elements.sortDirection.length) {
                this.$elements.sortDirection.val('desc');
            }
            
            // Clear search input to fully reset
            if (this.$elements.searchInput && this.$elements.searchInput.length) {
                this.$elements.searchInput.val('');
            }
            
            // Remove ALL filter-related classes to restore native WordPress layout
            $('body').removeClass('wp-filter-active wp-filter-results-active wp-plugin-filters-loading');
            
            // Remove any enhanced classes from plugin cards
            $('.plugin-card').removeClass('wp-plugin-enhanced');
            
            console.log('[WP Plugin Filters] Cleared all filters - restoring native layout');
            
            // Reset state
            this.state.currentFilters = {};
            this.state.isLoading = false;
            this.state.retryCount = 0;
            
            // Restore original WordPress plugin content if we have it saved
            if (this.state.originalContent) {
                console.log('[WP Plugin Filters] Restoring saved original HTML content');
                var $container = this.$elements.resultsContainer;
                if (!$container.length) {
                    $container = $('#the-list, .wp-list-table tbody').first();
                }
                if ($container.length) {
                    $container.parent().html(this.state.originalContent);
                    console.log('[WP Plugin Filters] Original content restored successfully');
                } else {
                    console.warn('[WP Plugin Filters] Could not find container to restore original content');
                    this.fallbackToBrowseAll();
                }
            } else {
                console.log('[WP Plugin Filters] No saved original content - using fallback');
                this.fallbackToBrowseAll();
            }
            
            // CRITICAL: Re-cache elements after DOM changes
            // The filter controls should still be in the DOM, but other elements may have changed
            this.refreshElementCache();
        },

        /**
         * Fallback method to browse all plugins when original content unavailable
         */
        fallbackToBrowseAll: function() {
            console.log('[WP Plugin Filters] Using fallback to browse all plugins');
            
            // Make clean API call with no search term to get popular plugins
            this.fetchPluginDataFromAPI('')
                .then((response) => {
                    // Don't apply filter classes for clean results
                    this.updatePluginGridClean(response);
                })
                .catch((error) => {
                    console.error('[WP Plugin Filters] Fallback failed:', error);
                    // As a last resort, try to trigger WordPress native search
                    var $searchForm = $('.wp-filter-search form, form.search-form');
                    if ($searchForm.length) {
                        console.log('[WP Plugin Filters] Triggering native search form');
                        $searchForm.trigger('submit');
                    }
                });
        },

        /**
         * Load state from URL parameters - DISABLED to prevent access errors
         */
        loadStateFromURL: function() {
            // Disabled URL state loading to prevent access errors
            // Filter state is now maintained only in the UI during the session
            console.log('[WP Plugin Filters] URL state loading disabled to prevent access errors');
            return;
            
            /*
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
            */
        },

        /**
         * Update URL with current filter state - DISABLED to prevent access errors on refresh
         */
        updateURL: function() {
            // Disabled URL updating to prevent "not allowed to access page" errors on refresh
            // WordPress admin doesn't handle custom filter parameters well
            console.log('[WP Plugin Filters] URL update disabled to prevent refresh access errors');
            return;
            
            /*
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
            */
        },

        /**
         * Enhance existing plugin cards with ratings
         */
        enhanceExistingPlugins: function() {
            // Skip automatic enhancement since our generated cards already include all data
            console.log('[WP Plugin Filters] Skipping automatic enhancement - cards are pre-enhanced');
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
                    action: 'wppdfi_rating',
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
         * Get plugin icon URL with comprehensive fallbacks
         */
        getPluginIcon: function(plugin) {
            if (!plugin || !plugin.slug) {
                console.log('No plugin or slug provided, using empty string');
                return '';
            }
            
            console.log('Getting icon for plugin:', plugin.name, 'slug:', plugin.slug, 'icons:', plugin.icons);
            console.log('Icon object details:', {
                hasIcons: !!plugin.icons,
                iconsType: typeof plugin.icons,
                isObject: typeof plugin.icons === 'object' && plugin.icons !== null,
                iconKeys: plugin.icons ? Object.keys(plugin.icons) : 'no icons'
            });
            
            // Check if we have icons from the API like the Chrome extension does
            if (plugin.icons && typeof plugin.icons === 'object' && Object.keys(plugin.icons).length > 0) {
                // Return the best available icon (Chrome extension uses 2x or default)
                var iconUrl = plugin.icons['2x'] || plugin.icons.default || plugin.icons['1x'] || plugin.icons.svg;
                if (iconUrl) {
                    console.log('Using API icon:', iconUrl);
                    return iconUrl;
                }
            }
            
            // No API icon data available - return empty string so template can handle it
            console.log('No API icon data available for plugin:', plugin.slug);
            return '';
        },

        /**
         * Get default plugin icon (WordPress standard)
         */
        getDefaultIcon: function() {
            // Use local default plugin icon
            return wpPluginFilters.pluginUrl + 'assets/images/plugin-icon-default.svg';
        },

        /**
         * Handle plugin icon loading errors with better fallback system
         */
        handleIconError: function(img, plugin) {
            var $img = $(img);
            var slug = plugin && plugin.slug ? plugin.slug : ($img.data('plugin-slug') || 'unknown');
            
            // Get current fallback attempt count
            var attempts = $img.data('fallback-attempts') || 0;
            attempts++;
            $img.data('fallback-attempts', attempts);
            
            console.log('Icon error for ' + slug + ', attempt ' + attempts + ', current src:', img.src);
            
            // Prevent infinite loops - max 5 attempts
            if (attempts > 5 || !slug || slug === 'unknown') {
                console.log('All icon fallbacks failed for ' + slug + ', using default');
                this.setDefaultIcon($img);
                return;
            }
            
            // Use local default icon as fallback (no remote calls)
            var fallbacks = [
                wpPluginFilters.pluginUrl + 'assets/images/plugin-icon-default.svg'
            ];
            
            var nextFallback = fallbacks[attempts - 1];
            
            if (nextFallback) {
                console.log('Trying fallback ' + attempts + ' for ' + slug + ':', nextFallback);
                // Set up error handler for next attempt
                $img.off('error.wpPluginFilters').on('error.wpPluginFilters', function() {
                    WPPluginFilters.handleIconError(this, {slug: slug});
                });
                img.src = nextFallback;
            } else {
                this.setDefaultIcon($img);
            }
        },
        
        /**
         * Set default icon styling when all fallbacks fail
         */
        setDefaultIcon: function($img) {
            // Better default plugin icon as base64 SVG
            var defaultIconSvg = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iNCIgZmlsbD0iI2Y2ZjdmNyIvPgo8cGF0aCBkPSJNNDggMjR2OGgtNFYyNGgtMTBWMTZoMTBjMi4yIDAgNCAMS44IDQgNHpNMjAgMjR2OGgtNFYyNGMwLTIuMiAxLjgtNCA0LTRoMTB2NGgtMTB6TTU2IDMyVjI0YzAtNC40LTMuNi04LTgtOEgyMGMtNC40IDAtOCAzLjYtOCA4djhjLTIuMiAwLTQgMS44LTQgNHMxLjggNCA0IDR2OGMwIDQuNCAzLjYgOCA4IDhoMjRjNC40IDAgOC0zLjYgOC04di04YzIuMiAwIDQtMS44IDQtNHMtMS44LTQtNC00eiIgZmlsbD0iIzY0Njk3MCIvPgo8L3N2Zz4K';
            
            console.log('Setting default icon for failed image');
            $img.attr('src', defaultIconSvg);
            $img.css({
                'background': '#f6f7f7',
                'border': '1px solid #dcdcde',
                'opacity': '0.9',
                'object-fit': 'contain',
                'padding': '4px'
            });
        },

        /**
         * Get plugin details URL
         */
        getPluginDetailsUrl: function(slug) {
            var adminUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl.replace('admin-ajax.php', '') : '/wp-admin/';
            return adminUrl + 'plugin-install.php?tab=plugin-information&plugin=' + encodeURIComponent(slug) + '&TB_iframe=true&width=772&height=550';
        },

        /**
         * Get plugin install URL
         */
        getInstallUrl: function(slug) {
            var adminUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl.replace('admin-ajax.php', '') : '/wp-admin/';
            return adminUrl + 'update.php?action=install-plugin&plugin=' + encodeURIComponent(slug);
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
         * Decode HTML entities and escape for security
         */
        escapeHtml: function(text) {
            if (!text) return '';
            
            // First decode common HTML entities
            var decoded = text
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&quot;/g, '"')
                .replace(/&#39;/g, "'")
                .replace(/&#8211;/g, '\u2013')  // en dash
                .replace(/&#8212;/g, '\u2014')  // em dash
                .replace(/&#8216;/g, '\u2018')  // left single quote
                .replace(/&#8217;/g, '\u2019')  // right single quote
                .replace(/&#8220;/g, '"')  // left double quote
                .replace(/&#8221;/g, '"')  // right double quote
                .replace(/&#8230;/g, '\u2026'); // ellipsis
            
            // Then escape dangerous characters for HTML output
            return decoded.replace(/[<>"']/g, function(match) {
                var escapeMap = {
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

    // Initialize when DOM is ready with retry mechanism
    $(document).ready(function() {
        console.log('[WP Plugin Filters] DOM ready, checking for plugin installer page...');
        console.log('[WP Plugin Filters] Body classes:', $('body').attr('class'));
        console.log('[WP Plugin Filters] Plugin filter element:', $('#plugin-filter').length);
        
        // Don't initialize in modal contexts (plugin detail popups)
        if (WPPluginFilters.isModalContext()) {
            console.log('[WP Plugin Filters] Modal context detected during initialization, skipping');
            return;
        }
        
        // Only initialize on plugin installer pages
        if ($('body').hasClass('plugin-install-php') || $('#plugin-filter').length) {
            console.log('[WP Plugin Filters] Plugin installer page detected, initializing...');
            WPPluginFilters.init();
        } else {
            console.log('[WP Plugin Filters] Not a plugin installer page, trying again in 1 second...');
            // Try again after a short delay in case elements load slowly
            setTimeout(function() {
                if ($('body').hasClass('plugin-install-php') || $('#plugin-filter').length || $('.plugin-browser').length) {
                    console.log('[WP Plugin Filters] Plugin installer elements found on retry, initializing...');
                    WPPluginFilters.init();
                } else {
                    console.log('[WP Plugin Filters] Still no plugin installer elements found, skipping initialization');
                }
            }, 1000);
        }
    });

})(jQuery);