import { request } from '../http/client.js';
import { normalizeReaderPayload } from '../../utils/normalize.js';

/**
 * Loads a chapter detail payload and converts it into a reader-friendly structure.
 */
export async function fetchChapter(type, slug, chapterNumber) {
  const response = await request(`/content/${type}/${slug}/chapter/${chapterNumber}`);
  return normalizeReaderPayload(response.data || {});
}
