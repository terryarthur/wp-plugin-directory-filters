/**
 * Tests for WordPress Plugin Directory Filters admin JavaScript
 */

// Import the admin script (simulate loading it)
require('../../assets/js/admin.js');

describe('WPPluginFilters', () => {
  let wpPluginFilters;

  beforeEach(() => {
    // Get the global WPPluginFilters object
    wpPluginFilters = window.WPPluginFilters;
    
    // Reset state
    wpPluginFilters.state = {
      currentFilters: {},
      isLoading: false,
      retryCount: 0,
      originalPlugins: [],
      filteredPlugins: []
    };
    
    // Clear debounce timers
    wpPluginFilters.debounceTimers = {};
  });

  describe('Initialization', () => {
    test('WPPluginFilters object exists', () => {
      expect(wpPluginFilters).toBeDefined();
      expect(typeof wpPluginFilters).toBe('object');
    });

    test('has required methods', () => {
      const requiredMethods = [
        'init',
        'cacheElements',
        'injectFilterControls',
        'bindEvents',
        'handleFilterChange',
        'applyFilters',
        'clearAllFilters'
      ];

      requiredMethods.forEach(method => {
        expect(typeof wpPluginFilters[method]).toBe('function');
      });
    });

    test('has correct configuration', () => {
      expect(wpPluginFilters.config).toBeDefined();
      expect(wpPluginFilters.config.debounceDelay).toBe(300);
      expect(wpPluginFilters.config.maxRetries).toBe(3);
      expect(wpPluginFilters.config.retryDelay).toBe(1000);
    });

    test('initializes state correctly', () => {
      expect(wpPluginFilters.state).toBeDefined();
      expect(wpPluginFilters.state.isLoading).toBe(false);
      expect(wpPluginFilters.state.retryCount).toBe(0);
    });
  });

  describe('Element Caching', () => {
    test('cacheElements method caches jQuery elements', () => {
      wpPluginFilters.cacheElements();

      expect(wpPluginFilters.$elements).toBeDefined();
      expect(wpPluginFilters.$elements.body).toBeDefined();
      expect(wpPluginFilters.$elements.searchInput).toBeDefined();
      expect(wpPluginFilters.$elements.pluginGrid).toBeDefined();
    });
  });

  describe('Filter Controls Injection', () => {
    test('buildFilterControlsHTML returns valid HTML', () => {
      const html = wpPluginFilters.buildFilterControlsHTML();
      
      expect(html).toContain('wp-plugin-filters-controls');
      expect(html).toContain('wp-plugin-filter-installations');
      expect(html).toContain('wp-plugin-filter-updates');
      expect(html).toContain('wp-plugin-filter-usability');
      expect(html).toContain('wp-plugin-filter-health');
      expect(html).toContain('wp-plugin-filter-sort');
    });

    test('filter controls contain all required options', () => {
      const html = wpPluginFilters.buildFilterControlsHTML();
      
      // Installation ranges
      expect(html).toContain('value="all"');
      expect(html).toContain('value="0-1k"');
      expect(html).toContain('value="1k-10k"');
      expect(html).toContain('value="10k-100k"');
      expect(html).toContain('value="100k-1m"');
      expect(html).toContain('value="1m-plus"');
      
      // Update timeframes
      expect(html).toContain('value="last_week"');
      expect(html).toContain('value="last_month"');
      expect(html).toContain('value="last_3months"');
      
      // Sort options
      expect(html).toContain('value="relevance"');
      expect(html).toContain('value="installations"');
      expect(html).toContain('value="rating"');
      expect(html).toContain('value="usability_rating"');
    });
  });

  describe('Filter Data Handling', () => {
    beforeEach(() => {
      // Mock DOM elements
      wpPluginFilters.$elements = {
        searchInput: { val: jest.fn(() => 'test search') },
        installationRange: { val: jest.fn(() => '10k-100k') },
        updateTimeframe: { val: jest.fn(() => 'last_month') },
        usabilityRating: { val: jest.fn(() => '4') },
        healthScore: { val: jest.fn(() => '70') },
        sortBy: { val: jest.fn(() => 'rating') },
        sortDirection: { val: jest.fn(() => 'desc') }
      };
    });

    test('getCurrentFilterData returns correct data structure', () => {
      const filterData = wpPluginFilters.getCurrentFilterData();

      expect(filterData).toEqual({
        search_term: 'test search',
        installation_range: '10k-100k',
        update_timeframe: 'last_month',
        usability_rating: 4,
        health_score: 70,
        sort_by: 'rating',
        sort_direction: 'desc',
        page: 1,
        per_page: 24
      });
    });

    test('handles empty/undefined element values', () => {
      wpPluginFilters.$elements = {
        searchInput: { val: jest.fn(() => '') },
        installationRange: { val: jest.fn(() => undefined) },
        updateTimeframe: { val: jest.fn(() => null) },
        usabilityRating: { val: jest.fn(() => 'invalid') },
        healthScore: { val: jest.fn(() => 'invalid') },
        sortBy: { val: jest.fn(() => '') },
        sortDirection: { val: jest.fn(() => '') }
      };

      const filterData = wpPluginFilters.getCurrentFilterData();

      expect(filterData.search_term).toBe('');
      expect(filterData.installation_range).toBe('all');
      expect(filterData.update_timeframe).toBe('all');
      expect(filterData.usability_rating).toBe(0);
      expect(filterData.health_score).toBe(0);
      expect(filterData.sort_by).toBe('relevance');
      expect(filterData.sort_direction).toBe('desc');
    });
  });

  describe('AJAX Request Handling', () => {
    beforeEach(() => {
      // Mock jQuery AJAX
      global.$.ajax = jest.fn();
    });

    test('executeAjaxRequest creates correct request data', () => {
      const testData = {
        search_term: 'test',
        page: 1
      };

      const mockDeferred = {
        done: jest.fn(() => mockDeferred),
        fail: jest.fn(() => mockDeferred)
      };

      global.$.ajax.mockReturnValue(mockDeferred);

      wpPluginFilters.executeAjaxRequest('wp_plugin_filter', testData);

      expect(global.$.ajax).toHaveBeenCalledWith({
        url: wpPluginFilters.ajaxUrl,
        method: 'POST',
        data: {
          action: 'wp_plugin_filter',
          nonce: wpPluginFilters.nonces.filter_plugins,
          search_term: 'test',
          page: 1
        },
        dataType: 'json',
        timeout: 30000
      });
    });

    test('handles AJAX success response', () => {
      const mockResponse = createMockApiResponse([createMockPlugin()]);
      
      // Mock DOM elements
      wpPluginFilters.$elements = {
        resultsContainer: {
          html: jest.fn()
        }
      };

      // Mock other methods
      wpPluginFilters.hideLoadingState = jest.fn();
      wpPluginFilters.updatePluginGrid = jest.fn();
      wpPluginFilters.updateURL = jest.fn();
      wpPluginFilters.updatePagination = jest.fn();
      wpPluginFilters.showFilterSummary = jest.fn();

      wpPluginFilters.handleFilterSuccess(mockResponse);

      expect(wpPluginFilters.hideLoadingState).toHaveBeenCalled();
      expect(wpPluginFilters.updatePluginGrid).toHaveBeenCalledWith(mockResponse.data);
      expect(wpPluginFilters.state.retryCount).toBe(0);
    });

    test('handles AJAX error response', () => {
      const mockXHR = {
        status: 500,
        responseJSON: {
          data: {
            message: 'Server error'
          }
        }
      };

      wpPluginFilters.hideLoadingState = jest.fn();
      wpPluginFilters.showError = jest.fn();

      wpPluginFilters.handleFilterError(mockXHR, 'error', 'Internal Server Error');

      expect(wpPluginFilters.hideLoadingState).toHaveBeenCalled();
      expect(wpPluginFilters.showError).toHaveBeenCalledWith('Server error');
    });

    test('handles rate limit error specifically', () => {
      const mockXHR = {
        status: 429,
        responseJSON: {
          data: {
            message: 'Rate limited'
          }
        }
      };

      wpPluginFilters.hideLoadingState = jest.fn();
      wpPluginFilters.showError = jest.fn();

      wpPluginFilters.handleFilterError(mockXHR, 'error', 'Too Many Requests');

      expect(wpPluginFilters.showError).toHaveBeenCalledWith(wpPluginFilters.strings.rateLimit);
    });
  });

  describe('Plugin Card Building', () => {
    test('buildPluginCard returns valid HTML', () => {
      const plugin = createMockPlugin();
      const html = wpPluginFilters.buildPluginCard(plugin);

      expect(html).toContain(`plugin-card-${plugin.slug}`);
      expect(html).toContain(plugin.name);
      expect(html).toContain(plugin.short_description);
      expect(html).toContain(plugin.author);
      expect(html).toContain('Install Now');
      expect(html).toContain('More Details');
    });

    test('handles missing plugin data gracefully', () => {
      const incompletePlugin = {
        slug: 'test-plugin',
        name: 'Test Plugin'
        // Missing other fields
      };

      const html = wpPluginFilters.buildPluginCard(incompletePlugin);

      expect(html).toContain('test-plugin');
      expect(html).toContain('Test Plugin');
      expect(html).not.toContain('undefined');
      expect(html).not.toContain('null');
    });

    test('escapes HTML in plugin data', () => {
      const maliciousPlugin = createMockPlugin({
        name: '<script>alert("xss")</script>Test Plugin',
        short_description: '<img src=x onerror=alert("xss")>Description',
        author: '<iframe src="evil.com">Author</iframe>'
      });

      const html = wpPluginFilters.buildPluginCard(maliciousPlugin);

      expect(html).not.toContain('<script>');
      expect(html).not.toContain('<img src=x onerror=');
      expect(html).not.toContain('<iframe');
      expect(html).toContain('&lt;script&gt;');
    });
  });

  describe('Star Rating Building', () => {
    test('buildStarRating creates correct number of stars', () => {
      const testCases = [
        { rating: 5.0, expectedFull: 5, expectedHalf: 0, expectedEmpty: 0 },
        { rating: 4.5, expectedFull: 4, expectedHalf: 1, expectedEmpty: 0 },
        { rating: 3.0, expectedFull: 3, expectedHalf: 0, expectedEmpty: 2 },
        { rating: 0.0, expectedFull: 0, expectedHalf: 0, expectedEmpty: 5 },
        { rating: 2.7, expectedFull: 2, expectedHalf: 1, expectedEmpty: 2 }
      ];

      testCases.forEach(({ rating, expectedFull, expectedHalf, expectedEmpty }) => {
        const html = wpPluginFilters.buildStarRating(rating);
        
        const fullStars = (html.match(/star-full/g) || []).length;
        const halfStars = (html.match(/star-half/g) || []).length;
        const emptyStars = (html.match(/star-empty/g) || []).length;

        expect(fullStars).toBe(expectedFull);
        expect(halfStars).toBe(expectedHalf);
        expect(emptyStars).toBe(expectedEmpty);
        expect(html).toContain(`data-rating="${rating}"`);
        expect(html).toContain(`(${rating.toFixed(1)})`);
      });
    });
  });

  describe('Health Badge Building', () => {
    test('buildHealthBadge creates correct badge', () => {
      const testCases = [
        { score: 95, color: 'green', expected: 'health-badge-green' },
        { score: 75, color: 'yellow', expected: 'health-badge-yellow' },
        { score: 45, color: 'red', expected: 'health-badge-red' },
        { score: 0, color: 'gray', expected: 'health-badge-gray' }
      ];

      testCases.forEach(({ score, color, expected }) => {
        const html = wpPluginFilters.buildHealthBadge(score, color);
        
        expect(html).toContain(expected);
        expect(html).toContain(`data-score="${score}"`);
        expect(html).toContain(`${score}/100`);
      });
    });
  });

  describe('Installation Count Formatting', () => {
    test('formatInstallCount formats numbers correctly', () => {
      const testCases = [
        { count: 50, expected: '50+' },
        { count: 1500, expected: '1.5K+' },
        { count: 15000, expected: '15K+' },
        { count: 150000, expected: '150K+' },
        { count: 1500000, expected: '1.5M+' },
        { count: 15000000, expected: '15M+' }
      ];

      testCases.forEach(({ count, expected }) => {
        const result = wpPluginFilters.formatInstallCount(count);
        expect(result).toBe(expected);
      });
    });
  });

  describe('Relative Time Formatting', () => {
    test('formatRelativeTime formats dates correctly', () => {
      const now = new Date();
      
      const testCases = [
        { 
          date: now.toISOString().split('T')[0], 
          expected: 'Today' 
        },
        { 
          date: new Date(now.getTime() - 24 * 60 * 60 * 1000).toISOString().split('T')[0], 
          expected: 'Yesterday' 
        },
        { 
          date: new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], 
          expected: '7 days ago' 
        }
      ];

      testCases.forEach(({ date, expected }) => {
        const result = wpPluginFilters.formatRelativeTime(date);
        expect(result).toContain(expected.split(' ')[0]); // Check the number part
      });
    });

    test('handles empty date string', () => {
      const result = wpPluginFilters.formatRelativeTime('');
      expect(result).toBe('');
    });
  });

  describe('Loading State Management', () => {
    beforeEach(() => {
      wpPluginFilters.$elements = {
        spinner: {
          addClass: jest.fn(),
          removeClass: jest.fn()
        },
        body: {
          addClass: jest.fn(),
          removeClass: jest.fn()
        }
      };
    });

    test('showLoadingState sets loading state correctly', () => {
      wpPluginFilters.showLoadingState();

      expect(wpPluginFilters.state.isLoading).toBe(true);
      expect(wpPluginFilters.$elements.spinner.addClass).toHaveBeenCalledWith('is-active');
      expect(wpPluginFilters.$elements.body.addClass).toHaveBeenCalledWith('wp-plugin-filters-loading');
    });

    test('hideLoadingState clears loading state correctly', () => {
      wpPluginFilters.state.isLoading = true;
      wpPluginFilters.hideLoadingState();

      expect(wpPluginFilters.state.isLoading).toBe(false);
      expect(wpPluginFilters.$elements.spinner.removeClass).toHaveBeenCalledWith('is-active');
      expect(wpPluginFilters.$elements.body.removeClass).toHaveBeenCalledWith('wp-plugin-filters-loading');
    });
  });

  describe('Error Handling', () => {
    beforeEach(() => {
      wpPluginFilters.$elements = {
        filterControls: {
          after: jest.fn()
        }
      };

      // Mock jQuery remove function
      global.$ = jest.fn(() => ({
        remove: jest.fn(),
        fadeOut: jest.fn()
      }));
    });

    test('showError displays error message', () => {
      const errorMessage = 'Test error message';
      wpPluginFilters.showError(errorMessage);

      expect(wpPluginFilters.$elements.filterControls.after).toHaveBeenCalled();
      const errorHtml = wpPluginFilters.$elements.filterControls.after.mock.calls[0][0];
      expect(errorHtml).toContain('notice-error');
      expect(errorHtml).toContain(errorMessage);
    });

    test('escapes HTML in error messages', () => {
      const maliciousError = '<script>alert("xss")</script>Error';
      wpPluginFilters.showError(maliciousError);

      const errorHtml = wpPluginFilters.$elements.filterControls.after.mock.calls[0][0];
      expect(errorHtml).not.toContain('<script>');
      expect(errorHtml).toContain('&lt;script&gt;');
    });
  });

  describe('Filter Clearing', () => {
    beforeEach(() => {
      wpPluginFilters.$elements = {
        installationRange: { val: jest.fn() },
        updateTimeframe: { val: jest.fn() },
        usabilityRating: { val: jest.fn() },
        healthScore: { val: jest.fn() },
        sortBy: { val: jest.fn() },
        sortDirection: { val: jest.fn() }
      };

      wpPluginFilters.applyFilters = jest.fn();
    });

    test('clearAllFilters resets all form values', () => {
      wpPluginFilters.clearAllFilters();

      expect(wpPluginFilters.$elements.installationRange.val).toHaveBeenCalledWith('all');
      expect(wpPluginFilters.$elements.updateTimeframe.val).toHaveBeenCalledWith('all');
      expect(wpPluginFilters.$elements.usabilityRating.val).toHaveBeenCalledWith('0');
      expect(wpPluginFilters.$elements.healthScore.val).toHaveBeenCalledWith('0');
      expect(wpPluginFilters.$elements.sortBy.val).toHaveBeenCalledWith('relevance');
      expect(wpPluginFilters.$elements.sortDirection.val).toHaveBeenCalledWith('desc');
      expect(wpPluginFilters.applyFilters).toHaveBeenCalled();
    });
  });

  describe('URL State Management', () => {
    test('updateURL modifies browser history', () => {
      const filterData = {
        search_term: 'test',
        installation_range: '10k-100k',
        sort_by: 'rating'
      };

      wpPluginFilters.updateURL(filterData);

      expect(window.history.replaceState).toHaveBeenCalled();
    });

    test('loadStateFromURL reads URL parameters', () => {
      // Mock URL parameters
      window.location.search = '?installation_range=10k-100k&sort_by=rating';

      wpPluginFilters.$elements = {
        installationRange: { val: jest.fn() },
        updateTimeframe: { val: jest.fn() },
        usabilityRating: { val: jest.fn() },
        healthScore: { val: jest.fn() },
        sortBy: { val: jest.fn() },
        sortDirection: { val: jest.fn() }
      };

      wpPluginFilters.loadStateFromURL();

      expect(wpPluginFilters.$elements.installationRange.val).toHaveBeenCalledWith('10k-100k');
      expect(wpPluginFilters.$elements.sortBy.val).toHaveBeenCalledWith('rating');
    });
  });

  describe('Security and Sanitization', () => {
    test('escapeHtml properly escapes dangerous characters', () => {
      const testCases = [
        { input: '<script>alert("xss")</script>', expected: '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;' },
        { input: '<img src=x onerror=alert(1)>', expected: '&lt;img src=x onerror=alert(1)&gt;' },
        { input: 'Safe text', expected: 'Safe text' },
        { input: '"quotes" & \'apostrophes\'', expected: '&quot;quotes&quot; &amp; &#39;apostrophes&#39;' },
        { input: '', expected: '' },
        { input: null, expected: '' },
        { input: undefined, expected: '' }
      ];

      testCases.forEach(({ input, expected }) => {
        const result = wpPluginFilters.escapeHtml(input);
        expect(result).toBe(expected);
      });
    });
  });

  describe('Debounce Functionality', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      wpPluginFilters.applyFilters = jest.fn();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('handleFilterChange debounces filter application', () => {
      // Call multiple times rapidly
      wpPluginFilters.handleFilterChange();
      wpPluginFilters.handleFilterChange();
      wpPluginFilters.handleFilterChange();

      // Should not have called applyFilters yet
      expect(wpPluginFilters.applyFilters).not.toHaveBeenCalled();

      // Fast-forward past debounce delay
      jest.advanceTimersByTime(300);

      // Should have called applyFilters once
      expect(wpPluginFilters.applyFilters).toHaveBeenCalledTimes(1);
    });

    test('handleSearchChange debounces search application', () => {
      wpPluginFilters.handleSearchChange();
      wpPluginFilters.handleSearchChange();

      expect(wpPluginFilters.applyFilters).not.toHaveBeenCalled();

      jest.advanceTimersByTime(300);

      expect(wpPluginFilters.applyFilters).toHaveBeenCalledTimes(1);
    });
  });

  describe('Edge Cases and Error Conditions', () => {
    test('handles missing jQuery gracefully', () => {
      const originalJQuery = global.$;
      global.$ = undefined;

      expect(() => {
        // This should not throw an error
        wpPluginFilters.escapeHtml('<script>test</script>');
      }).not.toThrow();

      global.$ = originalJQuery;
    });

    test('handles empty plugin data in updatePluginGrid', () => {
      wpPluginFilters.$elements = {
        resultsContainer: {
          html: jest.fn()
        }
      };

      wpPluginFilters.showNoResults = jest.fn();

      // Test with empty plugins array
      wpPluginFilters.updatePluginGrid({ plugins: [] });
      expect(wpPluginFilters.showNoResults).toHaveBeenCalled();

      // Test with null plugins
      wpPluginFilters.updatePluginGrid({ plugins: null });
      expect(wpPluginFilters.showNoResults).toHaveBeenCalledTimes(2);

      // Test with missing plugins property
      wpPluginFilters.updatePluginGrid({});
      expect(wpPluginFilters.showNoResults).toHaveBeenCalledTimes(3);
    });

    test('prevents multiple simultaneous filter requests', () => {
      wpPluginFilters.state.isLoading = true;
      wpPluginFilters.executeAjaxRequest = jest.fn();

      wpPluginFilters.applyFilters();

      expect(wpPluginFilters.executeAjaxRequest).not.toHaveBeenCalled();
    });
  });
});