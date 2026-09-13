/**
 * A14.2 End-to-End Runtime Integration Verification Suite.
 * Validates real API client flows against the NM-Reader v1 contract:
 * - Session restore & CSRF injection
 * - Search & filter options
 * - Content detail & chapter list
 * - Reader premium lock / unlock
 * - Reading history & activity tracking
 * - Comments & voting
 * - Library & follows
 * - Wallet, shop & transactions
 * - Blogs & likes
 */

import {
  authService,
  contentService,
  blogService,
  commentService,
  userService,
  walletService,
} from '../src/services/provider';
import { getCsrfToken } from '../src/services/apiClient';
import { resolveMediaUrl } from '../src/api/media';

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;
const failures: string[] = [];

function assert(condition: boolean, testName: string, detail?: string) {
  totalTests++;
  if (condition) {
    passedTests++;
    console.log(`  [PASS] ${testName}`);
  } else {
    failedTests++;
    const msg = `${testName}${detail ? ` -> ${detail}` : ''}`;
    failures.push(msg);
    console.error(`  [FAIL] ${msg}`);
  }
}

async function runE2EVerification() {
  console.log('==============================================================');
  console.log('    NM-READER A14.2 — E2E RUNTIME & ROUTE INTEGRATION TEST    ');
  console.log('==============================================================\n');

  const originalFetch = globalThis.fetch;
  const originalWindow = globalThis.window;
  globalThis.window = {
    __NMR_CONTEXT: { auth: {} },
  } as unknown as Window & typeof globalThis;

  try {
    // -----------------------------------------------------------------
    // Setup isolated API test double simulating the PHP /api/v1 router.
    // This exists only in the verification process and is never bundled into UI.
    // -----------------------------------------------------------------
    let serverSession: { userId: string | null; role: string; csrfToken: string; balance: number } = {
      userId: null,
      role: 'guest',
      csrfToken: 'csrf_init_tok_123',
      balance: 500,
    };

    let serverHistory: Array<{ slug: string; progress: number; chapter: string }> = [];
    let serverLibrary: string[] = [];
    let serverComments: Array<{ id: number; body: string; user: string; vote: number }> = [
      { id: 1, body: 'Awesome chapter!', user: 'user1', vote: 5 },
    ];
    let serverBlogs: Array<{ slug: string; title: string; body: string; votes: number }> = [
      { slug: 'welcome-post', title: 'Welcome to NM-Reader', body: 'Hello world', votes: 12 },
    ];

    globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
      const urlStr = input.toString();
      const method = (init?.method || 'GET').toUpperCase();
      const headers = new Headers(init?.headers);
      const reqCsrf = headers.get('X-CSRF-Token');
      let body: any = null;
      if (init?.body && typeof init.body === 'string') {
        try {
          body = JSON.parse(init.body);
        } catch {
          body = {};
        }
      }

      // Check CSRF on mutating methods if session active (exempting auth endpoints)
      const isMutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
      const isAuthExempt = urlStr.includes('/auth/login') || urlStr.includes('/auth/register') || urlStr.includes('/auth/refresh') || urlStr.includes('/auth/logout');
      
      if (isMutating && !isAuthExempt) {
        if (!reqCsrf || reqCsrf !== serverSession.csrfToken) {
          return new Response(
            JSON.stringify({
              status: 'error',
              data: null,
              meta: {},
              error: { code: 419, key: 'CSRF_ERROR', message: 'CSRF Token Mismatch' },
            }),
            { status: 419, headers: { 'Content-Type': 'application/json' } }
          );
        }
      }

      // Router Simulation matching NM-Reader Backend Contracts
      if (urlStr.includes('/auth/login')) {
        serverSession.userId = 'usr_test_001';
        serverSession.role = 'user';
        serverSession.csrfToken = 'csrf_auth_session_999';
        return new Response(
          JSON.stringify({
            status: 'success',
            data: {
              id: 'usr_test_001',
              username: 'sungjinwoo',
              email: body.email,
              csrf_token: serverSession.csrfToken,
              roles: ['user'],
              permissions: ['content.read', 'comment.create'],
            },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': serverSession.csrfToken } }
        );
      }

      if (urlStr.includes('/auth/refresh')) {
        serverSession.csrfToken = 'csrf_refreshed_tok_777';
        return new Response(
          JSON.stringify({
            status: 'success',
            data: {
              id: serverSession.userId || 'usr_guest',
              csrf_token: serverSession.csrfToken,
            },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': serverSession.csrfToken } }
        );
      }

      if (urlStr.includes('/auth/logout')) {
        serverSession.userId = null;
        serverSession.role = 'guest';
        serverSession.csrfToken = 'csrf_guest_tok_000';
        return new Response(
          JSON.stringify({ status: 'success', data: { logged_out: true }, meta: {}, error: null }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/user/profile')) {
        if (!serverSession.userId) {
          return new Response(
            JSON.stringify({
              status: 'error',
              data: null,
              meta: {},
              error: { code: 401, key: 'UNAUTHORIZED', message: 'Unauthorized' },
            }),
            { status: 401, headers: { 'Content-Type': 'application/json' } }
          );
        }
        return new Response(
          JSON.stringify({
            status: 'success',
            data: {
              id: serverSession.userId,
              username: 'sungjinwoo',
              email: 'shadow@monarch.com',
              avatar: '/media/public/user.profile.a1b2c3.webp',
              profile_image: '/media/public/user.profile.a1b2c3.webp',
              is_guest: false,
              stats: { chapters_read: 142, series_following: 18, library_count: 12, comments: 25 },
            },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/home')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: {
              explore: [{ id: 'c1', title: 'Solo Leveling', slug: 'solo-leveling', type: 'manga', rating: 4.9 }],
              recent_chapters: [{ id: 'ch1', series_title: 'Solo Leveling', chapter_number: '179' }],
              recently_added: [{ id: 'c2', title: 'Omniscient Reader', slug: 'orv', type: 'manhwa' }],
              popular_blogs: [],
              latest_blogs: [],
            },
            meta: { pagination: { type: 'offset', page: 1, per_page: 20 } },
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/search')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: [
              { id: 'c1', title: 'Solo Leveling', slug: 'solo-leveling', type: 'manga', rating_avg: 4.9 },
            ],
            meta: { pagination: { type: 'offset', page: 1, per_page: 20, q: 'solo' } },
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/content/manga/solo-leveling/chapters')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: [
              { id: 'ch1', chapter_number: '1', title: 'Prologue', price_coin: 0, is_locked: false },
              { id: 'ch2', chapter_number: '2', title: 'The D-Rank Dungeon', price_coin: 10, is_locked: true },
            ],
            meta: { pagination: { type: 'offset', page: 1, per_page: 50, total: 200 } },
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/content/manga/solo-leveling/chapter/1')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: {
              id: 'ch1',
              chapter_number: '1',
              title: 'Prologue',
              type: 'image',
              pages: [
                { image_path: 'cover.8k2m.webp', page_order: 1 },
                { image_path: 't_eyJjaWQiOiJjaDEiLCJwIjoxfQ.sig', page_order: 2 },
              ],
              access: { granted: true, is_free: true },
            },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/chapter/ch2/unlock')) {
        serverSession.balance -= 10;
        return new Response(
          JSON.stringify({
            status: 'success',
            data: { unlocked: true, transaction_id: 8812, balance: serverSession.balance },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/user/history') && method === 'POST') {
        serverHistory.push({ slug: body.contentSlug, progress: body.progress, chapter: body.chapterNumber });
        return new Response(
          JSON.stringify({ status: 'success', data: { tracked: true }, meta: {}, error: null }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/content/manga/solo-leveling/follow')) {
        if (serverLibrary.includes('solo-leveling')) {
          serverLibrary = serverLibrary.filter((s) => s !== 'solo-leveling');
          return new Response(
            JSON.stringify({ status: 'success', data: { in_library: false, followed: false }, meta: {}, error: null }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
          );
        } else {
          serverLibrary.push('solo-leveling');
          return new Response(
            JSON.stringify({ status: 'success', data: { in_library: true, followed: true }, meta: {}, error: null }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
          );
        }
      }

      if (urlStr.includes('/content/manga/solo-leveling/comments') && method === 'GET') {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: serverComments,
            meta: { pagination: { type: 'offset', page: 1, per_page: 20 } },
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/content/manga/solo-leveling/comment') && method === 'POST') {
        serverComments.push({ id: 2, body: body.body, user: 'sungjinwoo', vote: 0 });
        return new Response(
          JSON.stringify({ status: 'success', data: { comment_id: 2 }, meta: {}, error: null }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/user/wallet/transactions')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: [
              { id: 1, type: 'chapter_unlock', coin_delta: -10, balance_after: serverSession.balance, description: 'Unlocked Ch.2' },
            ],
            meta: { pagination: { type: 'offset', page: 1, per_page: 20 } },
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/user/wallet')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: { user_id: 'usr_test_001', balance_coin: serverSession.balance, total_coin_purchased: 500, total_coin_spent: 10 },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/blogs') && method === 'POST') {
        serverBlogs.push({ slug: 'new-post', title: body?.title || '', body: body?.body || '', votes: 0 });
        return new Response(
          JSON.stringify({
            status: 'success',
            data: { id: 'b2', slug: 'new-post', title: body?.title, excerpt: (body?.body || '').slice(0, 50) },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      if (urlStr.includes('/blogs/welcome-post/vote')) {
        return new Response(
          JSON.stringify({
            status: 'success',
            data: { vote: 1, upvote_count: 13, downvote_count: 0 },
            meta: {},
            error: null,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      return new Response(
        JSON.stringify({
          status: 'error',
          data: null,
          meta: {},
          error: { code: 404, key: 'NOT_FOUND', message: `Route not found: ${method} ${urlStr}` },
        }),
        { status: 404, headers: { 'Content-Type': 'application/json' } }
      );
    };

    // -----------------------------------------------------------------
    // 1. Discovery & Content Routes
    // -----------------------------------------------------------------
    console.log('\n1. Testing Public Discovery & Content Routes...');
    const homeRes = await contentService.getHome(1, 20);
    assert(homeRes.status === 'success', 'GET /home returns status: success');
    assert(homeRes.data.explore.length > 0, 'Explore carousel contains content items');

    const searchRes = await contentService.search('solo', 1, 20, { status: 'ongoing', sort: 'popular' });
    assert(searchRes.status === 'success', 'GET /search with filter parameters returns success');
    assert(searchRes.data[0].slug === 'solo-leveling', 'Search finds expected content');

    const chaptersRes = await contentService.getChapters('manga', 'solo-leveling', 1, 50);
    assert(chaptersRes.status === 'success', 'GET /content/{type}/{slug}/chapters returns success');
    assert(chaptersRes.data.length === 2, 'Chapter list contains free and premium locked chapters');

    // -----------------------------------------------------------------
    // 3. Auth, Login & Session Restore
    // -----------------------------------------------------------------
    console.log('\n2. Testing Auth, Login, Session Restore & CSRF Token Injection...');
    const loginRes = await authService.login('shadow@monarch.com', 'Password123', true);
    assert(loginRes.status === 'success', 'POST /auth/login returns status: success');
    assert(loginRes.data.username === 'sungjinwoo', 'User profile authenticated');
    assert(getCsrfToken() === 'csrf_auth_session_999', 'Active CSRF token registered in memory');

    const profileRes = await userService.getProfile();
    assert(profileRes.status === 'success', 'GET /user/profile restores active session');
    assert(profileRes.data.is_guest === false, 'Session confirms authenticated user');

    // -----------------------------------------------------------------
    // 4. Reader, Media Resolution & Premium Chapter Unlock
    // -----------------------------------------------------------------
    console.log('\n3. Testing Reader Stream, Media Token Resolution & Premium Unlock...');
    const readerRes = await contentService.getChapterReader('manga', 'solo-leveling', '1');
    assert(readerRes.status === 'success', 'GET Chapter Reader loads chapter pages');

    const coverUrl = resolveMediaUrl(readerRes.data.pages[0].image_path);
    assert(coverUrl === '/media/public/cover.8k2m.webp', 'Public page resolves to /media/public/*');

    const tokenUrl = resolveMediaUrl(readerRes.data.pages[1].image_path);
    assert(tokenUrl.startsWith('/media/chapter/t_'), 'Protected page resolves to /media/chapter/t_*');

    const unlockRes = await walletService.purchaseChapter('ch2');
    assert(unlockRes.status === 'success', 'POST /chapter/ch2/unlock executes coin deduction');
    assert(unlockRes.data.balance === 490, 'User balance properly updated');

    // -----------------------------------------------------------------
    // 5. Reading History & Activity Tracking
    // -----------------------------------------------------------------
    console.log('\n4. Testing Reading Progress & Activity Tracking...');
    const activityRes = await userService.recordHistory({
      contentSlug: 'solo-leveling',
      chapterId: 'ch0001',
      chapterNumber: 1,
      progress: 10,
    });
    assert(activityRes.status === 'success', 'POST /user/history records reading progress');
    assert(serverHistory.length === 1, 'Server recorded reading progress');

    // -----------------------------------------------------------------
    // 6. User Library & Follows Flow
    // -----------------------------------------------------------------
    console.log('\n5. Testing Library Follow / Unfollow Flow...');
    const followRes1 = await userService.toggleLibrary('manga', 'solo-leveling');
    assert(followRes1.status === 'success' && followRes1.data.in_library === true, 'Added series to library');

    const followRes2 = await userService.toggleLibrary('manga', 'solo-leveling', true);
    assert(followRes2.status === 'success' && followRes2.data.in_library === false, 'Removed series from library');

    // -----------------------------------------------------------------
    // 7. Comments & Voting Flow
    // -----------------------------------------------------------------
    console.log('\n6. Testing Comment Thread & Interaction Flow...');
    const commentListRes = await commentService.getComments('content', 'solo-leveling');
    assert(
      commentListRes.status === 'success',
      'GET content comments thread',
      JSON.stringify(commentListRes)
    );

    const newCommentRes = await commentService.postComment('content', 'solo-leveling', 'Great series!');
    assert(newCommentRes.status === 'success', 'POST new comment with CSRF validation');
    assert(serverComments.length === 2, 'Comment added to thread');

    // -----------------------------------------------------------------
    // 8. Wallet, Transactions & Shop
    // -----------------------------------------------------------------
    console.log('\n7. Testing Wallet & Transaction Ledger...');
    const walletRes = await walletService.getWallet();
    assert(walletRes.status === 'success', 'GET user wallet balance');

    const txRes = await walletService.getTransactions(1, 20);
    assert(txRes.status === 'success', 'GET user wallet transaction history');

    // -----------------------------------------------------------------
    // 9. Blog Creation & Voting Flow
    // -----------------------------------------------------------------
    console.log('\n8. Testing Blog Publishing & Upvote Flow...');
    const createBlogRes = await blogService.createBlog('Solo Leveling Review', 'An epic journey from E to S rank');
    assert(createBlogRes.status === 'success', 'POST /blogs creates new blog post');

    const voteBlogRes = await blogService.voteBlog('welcome-post', 1);
    assert(voteBlogRes.status === 'success', 'POST /blogs/{slug}/vote submits upvote');

    // -----------------------------------------------------------------
    // 9. Logout & Session Invalidation
    // -----------------------------------------------------------------
    console.log('\n9. Testing Logout & Cleanup...');
    const logoutRes = await authService.logout();
    assert(logoutRes.status === 'success', 'POST /auth/logout terminates server session');

  } finally {
    globalThis.fetch = originalFetch;
    globalThis.window = originalWindow;
  }

  console.log('\n==============================================================');
  console.log(`TOTAL E2E TESTS: ${totalTests} | PASSED: ${passedTests} | FAILED: ${failedTests}`);
  console.log('==============================================================\n');

  if (failedTests > 0) {
    console.error('FAILED ASSERTIONS:');
    failures.forEach((f) => console.error(`  - ${f}`));
    process.exit(1);
  }
}

runE2EVerification();
