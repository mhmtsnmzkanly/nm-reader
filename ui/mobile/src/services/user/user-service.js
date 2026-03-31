import { request } from '../http/client.js';
import { normalizeContentSummary, normalizeProfile } from '../../utils/normalize.js';
import { debugError, debugTrace } from '../../utils/debug.js';

/**
 * Loads the current authenticated profile.
 */
export async function fetchProfile() {
  debugTrace({
    scope: 'userService',
    action: 'fetchProfile:start',
    caller: 'userService.fetchProfile',
    callee: 'httpClient.request',
    next: 'normalize-profile',
  });
  try {
    const response = await request('/user/profile');
    const profile = normalizeProfile(response.data || {});
    debugTrace({
      scope: 'userService',
      action: 'fetchProfile:success',
      caller: 'userService.fetchProfile',
      callee: 'normalizeProfile',
      next: 'render-profile',
      detail: { username: profile.username || null },
    });
    return profile;
  } catch (error) {
    debugError({
      scope: 'userService',
      action: 'fetchProfile:error',
      caller: 'userService.fetchProfile',
      callee: 'httpClient.request',
      next: 'surface-profile-error',
      detail: { message: error.message },
    });
    throw error;
  }
}

/**
 * Loads the authenticated library/follows listing.
 */
export async function fetchLibrary(page = 1, perPage = 20) {
  debugTrace({
    scope: 'userService',
    action: 'fetchLibrary:start',
    caller: 'userService.fetchLibrary',
    callee: 'httpClient.request',
    next: 'normalize-library',
    detail: { page, perPage },
  });
  try {
    const response = await request(`/user/follows?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data.map(normalizeContentSummary) : [];
    debugTrace({
      scope: 'userService',
      action: 'fetchLibrary:success',
      caller: 'userService.fetchLibrary',
      callee: 'normalizeContentSummary[]',
      next: 'render-library',
      detail: { count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'userService',
      action: 'fetchLibrary:error',
      caller: 'userService.fetchLibrary',
      callee: 'httpClient.request',
      next: 'surface-library-error',
      detail: { page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Loads reading history entries for the authenticated user.
 */
export async function fetchHistory(page = 1, perPage = 20) {
  debugTrace({
    scope: 'userService',
    action: 'fetchHistory:start',
    caller: 'userService.fetchHistory',
    callee: 'httpClient.request',
    next: 'return-history',
    detail: { page, perPage },
  });
  try {
    const response = await request(`/user/history?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data : [];
    debugTrace({
      scope: 'userService',
      action: 'fetchHistory:success',
      caller: 'userService.fetchHistory',
      callee: 'httpClient.request',
      next: 'render-history',
      detail: { count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'userService',
      action: 'fetchHistory:error',
      caller: 'userService.fetchHistory',
      callee: 'httpClient.request',
      next: 'surface-history-error',
      detail: { page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Loads notification entries for the authenticated user.
 */
export async function fetchNotifications(page = 1, perPage = 20) {
  debugTrace({
    scope: 'userService',
    action: 'fetchNotifications:start',
    caller: 'userService.fetchNotifications',
    callee: 'httpClient.request',
    next: 'return-notifications',
    detail: { page, perPage },
  });
  try {
    const response = await request(`/user/notifications?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data : [];
    debugTrace({
      scope: 'userService',
      action: 'fetchNotifications:success',
      caller: 'userService.fetchNotifications',
      callee: 'httpClient.request',
      next: 'render-notifications',
      detail: { count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'userService',
      action: 'fetchNotifications:error',
      caller: 'userService.fetchNotifications',
      callee: 'httpClient.request',
      next: 'surface-notifications-error',
      detail: { page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Marks all notifications as read in one backend call.
 */
export async function markNotificationsRead() {
  debugTrace({
    scope: 'userService',
    action: 'markNotificationsRead:start',
    caller: 'userService.markNotificationsRead',
    callee: 'httpClient.request',
    next: 'reload-notifications',
  });
  return request('/user/notifications/read', { method: 'POST' });
}

/**
 * Loads the stored user preferences from the backend.
 */
export async function fetchPreferences() {
  debugTrace({
    scope: 'userService',
    action: 'fetchPreferences:start',
    caller: 'userService.fetchPreferences',
    callee: 'httpClient.request',
    next: 'return-preferences',
  });
  const response = await request('/user/preferences');
  return response.data || {};
}

/**
 * Persists app or reader preferences to the backend.
 */
export async function updatePreferences(payload) {
  debugTrace({
    scope: 'userService',
    action: 'updatePreferences:start',
    caller: 'userService.updatePreferences',
    callee: 'httpClient.request',
    next: 'sync-local-preferences',
    detail: payload,
  });
  try {
    const response = await request('/user/preferences', {
      method: 'PUT',
      body: payload,
    });
    debugTrace({
      scope: 'userService',
      action: 'updatePreferences:success',
      caller: 'userService.updatePreferences',
      callee: 'httpClient.request',
      next: 'return-updated-preferences',
    });
    return response.data || {};
  } catch (error) {
    debugError({
      scope: 'userService',
      action: 'updatePreferences:error',
      caller: 'userService.updatePreferences',
      callee: 'httpClient.request',
      next: 'surface-settings-error',
      detail: { message: error.message },
    });
    throw error;
  }
}
