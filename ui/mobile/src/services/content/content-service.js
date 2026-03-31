import { request } from '../http/client.js';
import { normalizeChapterSummary, normalizeContentSummary } from '../../utils/normalize.js';

/**
 * Loads the home dashboard payload and normalizes the sections used by the mobile app.
 */
export async function fetchHome() {
  const response = await request('/home');
  const data = response.data || {};

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
  const response = await request(`/content/type/${type}?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data.map(normalizeContentSummary) : [],
    meta: response.meta,
  };
}

/**
 * Loads content detail for the requested type and slug.
 */
export async function fetchContentDetail(type, slug) {
  const response = await request(`/content/${type}/${slug}`);
  return normalizeContentSummary(response.data || {});
}

/**
 * Loads chapter lists in a normalized shape.
 */
export async function fetchChapters(type, slug, page = 1, perPage = 50) {
  const response = await request(`/content/${type}/${slug}/chapters?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data.map(normalizeChapterSummary) : [],
    meta: response.meta,
  };
}

/**
 * Follows the selected series for the authenticated user.
 */
export async function followSeries(type, slug) {
  return request(`/content/${type}/${slug}/follow`, { method: 'POST' });
}

/**
 * Unfollows the selected series for the authenticated user.
 */
export async function unfollowSeries(type, slug) {
  return request(`/content/${type}/${slug}/follow`, { method: 'DELETE' });
}

/**
 * Unlocks a whole series when the backend exposes a series-level access product.
 */
export async function unlockSeries(type, slug) {
  return request(`/content/${type}/${slug}/unlock`, { method: 'POST' });
}
