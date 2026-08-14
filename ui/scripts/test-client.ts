/**
 * Standalone Automated Test Suite for NM-Reader API Client.
 * Validates envelopes, error normalization, CSRF retry, 401 handling, credentials, and media URL resolution.
 */

import { HttpClient } from '../src/api/client';
import { ApiClientError, NetworkError, TimeoutError } from '../src/api/errors';
import { getCsrfToken, setCsrfToken, clearCsrfToken } from '../src/api/auth';
import { resolveMediaUrl, isProtectedMedia } from '../src/api/media';
import { configureApi, resetApiConfig } from '../src/api/config';

let passedTests = 0;
let failedTests = 0;

function assert(condition: boolean, testName: string, detail?: string) {
  if (condition) {
    passedTests++;
    console.log(`  [PASS] ${testName}`);
  } else {
    failedTests++;
    console.error(`  [FAIL] ${testName}${detail ? ` - ${detail}` : ''}`);
  }
}

async function runTests() {
  console.log('==============================================================');
  console.log('         NM-READER — API CLIENT AUTOMATED TESTS               ');
  console.log('==============================================================\n');

  // Save original fetch
  const originalFetch = globalThis.fetch;

  try {
    // -------------------------------------------------------------
    // Test 1: Media URL Resolution
    // -------------------------------------------------------------
    console.log('1. Testing Media URL Resolution Contract...');
    assert(
      resolveMediaUrl('cover.8k2ma7qx4.webp') === '/media/public/cover.8k2ma7qx4.webp',
      'Public cover resolution'
    );
    assert(
      resolveMediaUrl('user.profile.9x7a1b.webp') === '/media/public/user.profile.9x7a1b.webp',
      'Public user avatar resolution'
    );
    assert(
      resolveMediaUrl('t_eyJjaWQiOiJjaDEyMyIsImV4cCI6MTc3MTIzNDU2N30.sig') ===
        '/media/chapter/t_eyJjaWQiOiJjaDEyMyIsImV4cCI6MTc3MTIzNDU2N30.sig',
      'Protected chapter token auto-detection'
    );
    assert(
      resolveMediaUrl('custom.webp', 'chapter') === '/media/chapter/custom.webp',
      'Explicit protected chapter resolution'
    );
    assert(
      resolveMediaUrl('https://example.com/img.jpg') === 'https://example.com/img.jpg',
      'External absolute URL preserved'
    );
    assert(
      resolveMediaUrl('') === '/assets/img/covers/placeholder.svg',
      'Empty identifier returns placeholder'
    );
    assert(
      isProtectedMedia('t_abc123') === true && isProtectedMedia('cover.webp') === false,
      'isProtectedMedia helper check'
    );

    // -------------------------------------------------------------
    // Test 2: Success Response Envelope
    // -------------------------------------------------------------
    console.log('\n2. Testing Standard Success Envelope...');
    configureApi({ baseUrl: 'http://test-api.local/api/v1' });

    let lastRequestInfo: { url: string; method: string; headers: Headers; body: any; credentials: any } | null = null;

    globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = typeof input === 'string' ? input : input.toString();
      lastRequestInfo = {
        url,
        method: init?.method || 'GET',
        headers: new Headers(init?.headers),
        body: init?.body,
        credentials: init?.credentials,
      };

      return new Response(
        JSON.stringify({
          status: 'success',
          data: { id: 'c123', title: 'Solo Leveling' },
          meta: { pagination: { page: 1, per_page: 20 } },
          error: null,
        }),
        {
          status: 200,
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': 'csrf_token_from_header' },
        }
      );
    };

    const client = new HttpClient();
    const res = await client.get<{ id: string; title: string }>('/content/manga/solo-leveling');
    assert(res.status === 'success' && res.data.title === 'Solo Leveling', 'Valid success envelope parsed');
    assert((res.meta as any).pagination?.page === 1, 'Pagination meta extracted');
    assert(getCsrfToken() === 'csrf_token_from_header', 'CSRF token extracted from response header');
    assert(lastRequestInfo?.credentials === 'include', 'credentials: "include" configured');

    // -------------------------------------------------------------
    // Test 3: CSRF Injection on Mutations
    // -------------------------------------------------------------
    console.log('\n3. Testing CSRF Header Injection on Mutations...');
    setCsrfToken('active_csrf_token_123');

    await client.post('/content/manga/solo-leveling/rate', { rating: 5 });
    assert(
      lastRequestInfo?.headers.get('X-CSRF-Token') === 'active_csrf_token_123',
      'X-CSRF-Token automatically injected for POST'
    );

    await client.get('/content/manga/solo-leveling');
    assert(
      lastRequestInfo?.headers.get('X-CSRF-Token') === null,
      'GET request does not include X-CSRF-Token'
    );

    // -------------------------------------------------------------
    // Test 4: Error Handling (400, 401, 403, 422, 429, 500)
    // -------------------------------------------------------------
    console.log('\n4. Testing HTTP Error Code Handling...');

    const errorCases = [
      { status: 400, key: 'BAD_REQUEST', msg: 'Bad parameters' },
      { status: 401, key: 'UNAUTHORIZED', msg: 'Authentication required' },
      { status: 403, key: 'FORBIDDEN', msg: 'Access denied' },
      { status: 404, key: 'NOT_FOUND', msg: 'Resource not found' },
      { status: 422, key: 'VALIDATION_FAILED', msg: 'Validation failed', fields: { email: ['Invalid email'] } },
      { status: 429, key: 'RATE_LIMITED', msg: 'Too many requests' },
      { status: 500, key: 'SERVER_ERROR', msg: 'Internal server error' },
    ];

    for (const ec of errorCases) {
      globalThis.fetch = async () =>
        new Response(
          JSON.stringify({
            status: 'error',
            data: null,
            meta: {},
            error: { code: ec.status, key: ec.key, message: ec.msg, fields: (ec as any).fields },
          }),
          { status: ec.status, headers: { 'Content-Type': 'application/json' } }
        );

      let thrownErr: ApiClientError | null = null;
      try {
        await client.get('/test-error', { skipAuthRetry: true });
      } catch (err: any) {
        thrownErr = err;
      }

      assert(
        thrownErr instanceof ApiClientError && thrownErr.status === ec.status && thrownErr.key === ec.key,
        `Status ${ec.status} throws ApiClientError with key ${ec.key}`
      );
      if (ec.status === 422) {
        assert(thrownErr?.fields?.email !== undefined, '422 preserves validation fields');
      }
    }

    // -------------------------------------------------------------
    // Test 5: 419 CSRF Automatic Single Retry
    // -------------------------------------------------------------
    console.log('\n5. Testing 419 CSRF Automatic Single Retry...');
    let callCount = 0;
    globalThis.fetch = async (input: RequestInfo | URL) => {
      const url = input.toString();
      callCount++;

      // If it is the refresh call
      if (url.includes('/auth/refresh')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: { csrf_token: 'new_fresh_csrf_999' },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      // Initial call fails with 419
      if (callCount === 1) {
        return new Response(
          JSON.stringify({
            status: 'error',
            data: null,
            meta: {},
            error: { code: 419, key: 'CSRF_ERROR', message: 'Token expired' },
          }),
          { status: 419, headers: { 'Content-Type': 'application/json' } }
        );
      }

      // Replay call succeeds
      return new Response(
        JSON.stringify({
          status: 'success',
          data: { comment_id: 101 },
          meta: {},
          error: null,
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } }
      );
    };

    setCsrfToken('stale_csrf_token');
    const commentRes = await client.post<{ comment_id: number }>('/content/manga/solo-leveling/comment', {
      body: 'Hello',
    });
    assert(
      commentRes.status === 'success' && commentRes.data.comment_id === 101,
      '419 triggered refresh and successfully replayed request'
    );
    assert(getCsrfToken() === 'new_fresh_csrf_999', 'New CSRF token stored in auth state');

    // -------------------------------------------------------------
    // Test 6: Network Error and Invalid JSON Response
    // -------------------------------------------------------------
    console.log('\n6. Testing Network & Parse Error Handling...');
    globalThis.fetch = async () => {
      throw new TypeError('Failed to fetch');
    };

    let netErr: NetworkError | null = null;
    try {
      await client.get('/network-fail');
    } catch (err: any) {
      netErr = err;
    }
    assert(netErr instanceof NetworkError, 'TypeError translates to NetworkError');

    globalThis.fetch = async () =>
      new Response('<html>502 Bad Gateway</html>', {
        status: 502,
        headers: { 'Content-Type': 'text/html' },
      });

    let htmlErr: ApiClientError | null = null;
    try {
      await client.get('/html-error');
    } catch (err: any) {
      htmlErr = err;
    }
    assert(
      htmlErr instanceof ApiClientError && htmlErr.key === 'INVALID_JSON_RESPONSE',
      'HTML/Non-JSON response wrapped in ApiClientError'
    );

    resetApiConfig();
    clearCsrfToken();
  } finally {
    globalThis.fetch = originalFetch;
  }

  console.log('\n==============================================================');
  console.log(`TOTAL CLIENT TESTS: ${passedTests + failedTests} | PASSED: ${passedTests} | FAILED: ${failedTests}`);
  console.log('==============================================================\n');

  if (failedTests > 0) {
    process.exit(1);
  }
}

runTests();
