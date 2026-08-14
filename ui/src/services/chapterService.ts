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

  return normalizeChapter(response.data, {
    slug: seriesSlug,
    type: seriesType,
    title: seriesSlug.replace(/-/g, ' ').toUpperCase(),
  });
}
