import { contentService } from './index';
import { Chapter, ContentType } from '../types/api';
import { normalizeChapter } from '../utils/chapter';

export async function fetchChapter(
  seriesType: string,
  seriesSlug: string,
  chapterNumber: string
): Promise<Chapter> {
  const response = await contentService.getChapterReader(
    seriesType as ContentType,
    seriesSlug,
    chapterNumber
  );

  if (response.status !== 'success') {
    const errObj = response as any;
    throw new Error(errObj.error?.message || errObj.message || 'Unable to load chapter.');
  }

  const raw = (response.data as any)?.chapter ?? (response.data as any)?.data ?? response.data;
  const inferredTitle =
    raw?.series?.title ||
    raw?.series_title ||
    raw?.content_title ||
    seriesSlug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

  return normalizeChapter(response.data, {
    slug: seriesSlug,
    type: seriesType,
    title: inferredTitle,
  });
}
