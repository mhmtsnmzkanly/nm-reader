import { request } from '../http/client.js';
import { normalizeReaderPayload } from '../../utils/normalize.js';
import { debugError, debugTrace } from '../../utils/debug.js';

/**
 * Loads a chapter detail payload and converts it into a reader-friendly structure.
 */
export async function fetchChapter(type, slug, chapterNumber) {
  debugTrace({
    scope: 'readerService',
    action: 'fetchChapter:start',
    caller: 'readerService.fetchChapter',
    callee: 'httpClient.request',
    next: 'normalize-reader-payload',
    detail: { type, slug, chapterNumber },
  });

  try {
    const response = await request(`/content/${type}/${slug}/chapter/${chapterNumber}`);
    const normalized = normalizeReaderPayload(response.data || {});
    debugTrace({
      scope: 'readerService',
      action: 'fetchChapter:success',
      caller: 'readerService.fetchChapter',
      callee: 'normalizeReaderPayload',
      next: 'render-reader',
      detail: {
        type,
        slug,
        chapterNumber,
        readerMode: normalized.readerMode,
      },
    });
    return normalized;
  } catch (error) {
    debugError({
      scope: 'readerService',
      action: 'fetchChapter:error',
      caller: 'readerService.fetchChapter',
      callee: 'httpClient.request',
      next: 'surface-reader-error',
      detail: { type, slug, chapterNumber, message: error.message },
    });
    throw error;
  }
}
