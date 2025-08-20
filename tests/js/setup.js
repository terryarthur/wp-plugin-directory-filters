/**
 * Jest test setup file
 * Configures the testing environment for WordPress Plugin Directory Filters
 */

import '@testing-library/jest-dom';

// Mock jQuery
const jQueryMock = jest.fn(() => ({
  ready: jest.fn(),
  on: jest.fn(),
  off: jest.fn(),
  click: jest.fn(),
  change: jest.fn(),
  val: jest.fn(),
  attr: jest.fn(),
  data: jest.fn(),
  addClass: jest.fn(),
  removeClass: jest.fn(),
  hasClass: jest.fn(),
  find: jest.fn(() => jQueryMock),
  closest: jest.fn(() => jQueryMock),
  append: jest.fn(),
  prepend: jest.fn(),
  after: jest.fn(),
  before: jest.fn(),
  remove: jest.fn(),
  empty: jest.fn(),
  html: jest.fn(),
  text: jest.fn(),
  show: jest.fn(),
  hide: jest.fn(),
  fadeIn: jest.fn(),
  fadeOut: jest.fn(),
  each: jest.fn(),
  map: jest.fn(),
  filter: jest.fn(),
  length: 0,
  ajax: jest.fn(),
  extend: jest.fn(),
  isArray: jest.fn(),
  parseJSON: jest.fn()
}));

// Mock jQuery global functions
jQueryMock.fn = jest.fn;
jQueryMock.extend = jest.fn((target, ...sources) => Object.assign(target, ...sources));
jQueryMock.isArray = jest.fn(Array.isArray);
jQueryMock.parseJSON = jest.fn(JSON.parse);

// jQuery constructor should return the mock
Object.setPrototypeOf(jQueryMock, Function.prototype);
global.jQuery = jQueryMock;
global.$ = jQueryMock;

// Mock WordPress globals
global.wp = {
  util: {
    template: jest.fn()
  },
  ajax: {
    post: jest.fn(),
    send: jest.fn()
  }
};

global.ajaxurl = 'http://localhost/wp-admin/admin-ajax.php';

// Mock console methods for testing
global.console = {
  ...console,
  log: jest.fn(),
  error: jest.fn(),
  warn: jest.fn(),
  info: jest.fn(),
  debug: jest.fn()
};

// Mock window.location
delete window.location;
window.location = {
  href: 'http://localhost/wp-admin/plugin-install.php',
  origin: 'http://localhost',
  protocol: 'http:',
  host: 'localhost',
  hostname: 'localhost',
  port: '',
  pathname: '/wp-admin/plugin-install.php',
  search: '',
  hash: '',
  assign: jest.fn(),
  replace: jest.fn(),
  reload: jest.fn()
};

// Mock URLSearchParams
global.URLSearchParams = class URLSearchParams {
  constructor(search = '') {
    this.params = new Map();
    if (search) {
      search.replace(/^\?/, '').split('&').forEach(param => {
        const [key, value] = param.split('=');
        if (key) {
          this.params.set(decodeURIComponent(key), decodeURIComponent(value || ''));
        }
      });
    }
  }

  get(key) {
    return this.params.get(key);
  }

  set(key, value) {
    this.params.set(key, value);
  }

  delete(key) {
    this.params.delete(key);
  }

  has(key) {
    return this.params.has(key);
  }

  toString() {
    const pairs = [];
    this.params.forEach((value, key) => {
      pairs.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
    });
    return pairs.join('&');
  }
};

// Mock URL constructor
global.URL = class URL {
  constructor(url, base) {
    if (base) {
      url = new global.URL(base).origin + '/' + url.replace(/^\//, '');
    }
    
    const parser = document.createElement('a');
    parser.href = url;
    
    this.href = parser.href;
    this.origin = `${parser.protocol}//${parser.host}`;
    this.protocol = parser.protocol;
    this.host = parser.host;
    this.hostname = parser.hostname;
    this.port = parser.port;
    this.pathname = parser.pathname;
    this.search = parser.search;
    this.hash = parser.hash;
    this.searchParams = new URLSearchParams(this.search);
  }
};

// Mock window.history
window.history = {
  ...window.history,
  pushState: jest.fn(),
  replaceState: jest.fn(),
  back: jest.fn(),
  forward: jest.fn(),
  go: jest.fn()
};

// Mock performance API
window.performance = {
  ...window.performance,
  now: jest.fn(() => Date.now()),
  mark: jest.fn(),
  measure: jest.fn(),
  getEntriesByType: jest.fn(() => []),
  getEntriesByName: jest.fn(() => [])
};

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
  constructor(callback, options) {
    this.callback = callback;
    this.options = options;
  }

  observe() {}
  unobserve() {}
  disconnect() {}
};

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
  constructor(callback) {
    this.callback = callback;
  }

  observe() {}
  unobserve() {}
  disconnect() {}
};

// Mock fetch API
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    status: 200,
    json: () => Promise.resolve({}),
    text: () => Promise.resolve(''),
    blob: () => Promise.resolve(new Blob()),
    headers: new Map()
  })
);

// Setup DOM environment
document.body.innerHTML = `
  <div id="wpbody">
    <div class="wp-filter">
      <div class="wp-filter-search">
        <input type="search" id="plugin-search-input" placeholder="Search plugins...">
        <span class="spinner"></span>
      </div>
    </div>
    <div id="plugin-filter" class="plugin-browser">
      <div class="wp-list-table plugins">
        <tbody class="plugins"></tbody>
      </div>
      <div class="tablenav">
        <div class="tablenav-pages"></div>
      </div>
    </div>
  </div>
`;

// Utility functions for tests
global.createMockPlugin = (overrides = {}) => ({
  slug: 'test-plugin',
  name: 'Test Plugin',
  version: '1.0.0',
  author: 'Test Author',
  rating: 4.5,
  num_ratings: 100,
  active_installs: 10000,
  last_updated: '2023-11-01',
  tested: '6.4',
  requires: '5.8',
  short_description: 'A test plugin for unit testing',
  homepage: 'https://example.com',
  download_link: 'https://downloads.wordpress.org/plugin/test-plugin.zip',
  tags: ['test'],
  support_threads: 10,
  support_threads_resolved: 8,
  downloaded: 50000,
  usability_rating: 4.2,
  health_score: 85,
  health_color: 'green',
  ...overrides
});

global.createMockApiResponse = (plugins = [], info = {}) => ({
  success: true,
  data: {
    plugins,
    pagination: {
      current_page: 1,
      total_pages: 1,
      total_results: plugins.length,
      per_page: 24,
      ...info
    },
    filters_applied: {},
    cache_info: {
      cached: false,
      cache_key: 'test_cache_key',
      expires_in: 3600
    }
  }
});

global.createMockErrorResponse = (message = 'Test error', code = 'test_error') => ({
  success: false,
  data: {
    message,
    code
  }
});

// Helper to wait for DOM updates
global.waitForDOM = () => new Promise(resolve => setTimeout(resolve, 0));

// Helper to trigger events
global.triggerEvent = (element, eventType, eventData = {}) => {
  const event = new Event(eventType, { bubbles: true, cancelable: true });
  Object.keys(eventData).forEach(key => {
    event[key] = eventData[key];
  });
  element.dispatchEvent(event);
};

// Clear all mocks between tests
beforeEach(() => {
  jest.clearAllMocks();
  
  // Reset jQuery mock functions
  jQueryMock.mockClear();
  Object.keys(jQueryMock).forEach(key => {
    if (typeof jQueryMock[key] === 'function' && jQueryMock[key].mockClear) {
      jQueryMock[key].mockClear();
    }
  });
  
  // Reset console mocks
  console.log.mockClear();
  console.error.mockClear();
  console.warn.mockClear();
  
  // Reset fetch mock
  fetch.mockClear();
  
  // Reset DOM
  document.body.className = '';
  document.querySelectorAll('.wp-plugin-filters-controls').forEach(el => el.remove());
  document.querySelectorAll('.notice').forEach(el => el.remove());
  
  // Reset location
  window.location.search = '';
  window.location.hash = '';
});

// Cleanup after all tests
afterAll(() => {
  jest.restoreAllMocks();
});