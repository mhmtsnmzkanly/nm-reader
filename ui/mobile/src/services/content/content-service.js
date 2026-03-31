import { request } from '../http/client.js';
import { normalizeChapterSummary, normalizeContentSummary } from '../../utils/normalize.js';
import { debugError, debugTrace } from '../../utils/debug.js';

/**
 * Loads the home dashboard payload and normalizes the sections used by the mobile app.
 */
export async function fetchHome() {
  debugTrace({
    scope: 'contentService',
    action: 'fetchHome:start',
    caller: 'contentService.fetchHome',
    callee: 'httpClient.request',
    next: 'normalize-home-response',
  });
  const response = await request('/home');
  const data = response.data || {};
  debugTrace({
    scope: 'contentService',
    action: 'fetchHome:success',
    caller: 'contentService.fetchHome',
    callee: 'normalizeContentSummary[]',
    next: 'return-home-payload',
    detail: {
      exploreCount: Array.isArray(data.explore) ? data.explore.length : 0,
      latestChapterCount: Array.isArray(data.recent_chapters) ? data.recent_chapters.length : 0,
    },
  });

  return {
    explore: Array.isArray(data.explore) ? data.explore.map(normalizeContentSummary) : [],
    latestChapters: Array.isArray(data.recent_chapters) ? data.recent_chapters.map(normalizeChapterSummary) : [],
    recentContent: Array.isArray(data.recently_added) ? data.recently_added.map(normalizeContentSummary) : [],
    blogs: Array.isArray(data.popular_blogs) ? data.popular_blogs : [],
  };
}

/**
 * Loads a paginated list of series by content type.
 */
export async function fetchTypeList(type, page = 1, perPage = 20) {
  debugTrace({
    scope: 'contentService',
    action: 'fetchTypeList:start',
    caller: 'contentService.fetchTypeList',
    callee: 'httpClient.request',
    next: 'normalize-type-list',
    detail: { type, page, perPage },
  });

  try {
    const response = await request(`/content/type/${type}?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data.map(normalizeContentSummary) : [];
    debugTrace({
      scope: 'contentService',
      action: 'fetchTypeList:success',
      caller: 'contentService.fetchTypeList',
      callee: 'normalizeContentSummary[]',
      next: 'return-type-list',
      detail: { type, count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'contentService',
      action: 'fetchTypeList:error',
      caller: 'contentService.fetchTypeList',
      callee: 'httpClient.request',
      next: 'surface-type-list-error',
      detail: { type, page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Loads content detail for the requested type and slug.
 */
export async function fetchContentDetail(type, slug) {
  debugTrace({
    scope: 'contentService',
    action: 'fetchContentDetail:start',
    caller: 'contentService.fetchContentDetail',
    callee: 'httpClient.request',
    next: 'normalize-content-detail',
    detail: { type, slug },
  });

  try {
    const response = await request(`/content/${type}/${slug}`);
    const normalized = normalizeContentSummary(response.data || {});
    debugTrace({
      scope: 'contentService',
      action: 'fetchContentDetail:success',
      caller: 'contentService.fetchContentDetail',
      callee: 'normalizeContentSummary',
      next: 'return-content-detail',
      detail: { type, slug, hasAccess: Boolean(normalized.access) },
    });
    return normalized;
  } catch (error) {
    debugError({
      scope: 'contentService',
      action: 'fetchContentDetail:error',
      caller: 'contentService.fetchContentDetail',
      callee: 'httpClient.request',
      next: 'surface-content-detail-error',
      detail: { type, slug, message: error.message },
    });
    throw error;
  }
}

/**
 * Loads chapter lists in a normalized shape.
 */
export async function fetchChapters(type, slug, page = 1, perPage = 50) {
  debugTrace({
    scope: 'contentService',
    action: 'fetchChapters:start',
    caller: 'contentService.fetchChapters',
    callee: 'httpClient.request',
    next: 'normalize-chapter-list',
    detail: { type, slug, page, perPage },
  });

  try {
    const response = await request(`/content/${type}/${slug}/chapters?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data.map(normalizeChapterSummary) : [];
    debugTrace({
      scope: 'contentService',
      action: 'fetchChapters:success',
      caller: 'contentService.fetchChapters',
      callee: 'normalizeChapterSummary[]',
      next: 'return-chapter-list',
      detail: { type, slug, count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'contentService',
      action: 'fetchChapters:error',
      caller: 'contentService.fetchChapters',
      callee: 'httpClient.request',
      next: 'surface-chapter-list-error',
      detail: { type, slug, page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Follows the selected series for the authenticated user.
 */
export async function followSeries(type, slug) {
  debugTrace({
    scope: 'contentService',
    action: 'followSeries:start',
    caller: 'contentService.followSeries',
    callee: 'httpClient.request',
    next: 'return-follow-state',
    detail: { type, slug },
  });
  return request(`/content/${type}/${slug}/follow`, { method: 'POST' });
}

/**
 * Unfollows the selected series for the authenticated user.
 */
export async function unfollowSeries(type, slug) {
  debugTrace({
    scope: 'contentService',
    action: 'unfollowSeries:start',
    caller: 'contentService.unfollowSeries',
    callee: 'httpClient.request',
    next: 'return-unfollow-state',
    detail: { type, slug },
  });
  return request(`/content/${type}/${slug}/follow`, { method: 'DELETE' });
}

/**
 * Unlocks a whole series when the backend exposes a series-level access product.
 */
export async function unlockSeries(type, slug) {
  debugTrace({
    scope: 'contentService',
    action: 'unlockSeries:start',
    caller: 'contentService.unlockSeries',
    callee: 'httpClient.request',
    next: 'refresh-wallet-and-detail',
    detail: { type, slug },
  });
  return request(`/content/${type}/${slug}/unlock`, { method: 'POST' });
}
