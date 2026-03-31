import { request } from '../http/client.js';
import { normalizeContentSummary, normalizeProfile } from '../../utils/normalize.js';

/**
 * Loads the current authenticated profile.
 */
export async function fetchProfile() {
  const response = await request('/user/profile');
  return normalizeProfile(response.data || {});
}

/**
 * Loads the authenticated library/follows listing.
 */
export async function fetchLibrary(page = 1, perPage = 20) {
  const response = await request(`/user/follows?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data.map(normalizeContentSummary) : [],
    meta: response.meta,
  };
}

/**
 * Loads reading history entries for the authenticated user.
 */
export async function fetchHistory(page = 1, perPage = 20) {
  const response = await request(`/user/history?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data : [],
    meta: response.meta,
  };
}

/**
 * Loads notification entries for the authenticated user.
 */
export async function fetchNotifications(page = 1, perPage = 20) {
  const response = await request(`/user/notifications?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data : [],
    meta: response.meta,
  };
}

/**
 * Marks all notifications as read in one backend call.
 */
export async function markNotificationsRead() {
  return request('/user/notifications/read', { method: 'POST' });
}

/**
 * Loads the stored user preferences from the backend.
 */
export async function fetchPreferences() {
  const response = await request('/user/preferences');
  return response.data || {};
}

/**
 * Persists app or reader preferences to the backend.
 */
export async function updatePreferences(payload) {
  const response = await request('/user/preferences', {
    method: 'PUT',
    body: payload,
  });
  return response.data || {};
}
