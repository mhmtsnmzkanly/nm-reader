import { Chapter } from '../types/api';

export function normalizeChapter(
  response: any,
  fallbackSeries?: { id?: string; title?: string; slug?: string; type?: string }
): Chapter {
  const raw = response?.chapter ?? response?.data ?? response ?? {};

  const series = raw.series ?? {
    id: raw.content_id || fallbackSeries?.id || '',
    title: fallbackSeries?.title || '',
    slug: fallbackSeries?.slug || '',
    type: fallbackSeries?.type || 'manga',
  };

  const nav = raw.navigation ?? {
    previous: raw.adjacent_chapters?.prev ?? raw.adjacent_chapters?.previous ?? null,
    next: raw.adjacent_chapters?.next ?? null,
  };

  const rawAccess = raw.access || {};
  const access = {
    granted: rawAccess.granted ?? (raw.is_locked === false ? true : !raw.is_locked),
    locked: rawAccess.locked ?? raw.is_locked ?? false,
    price_coin: rawAccess.price_coin ?? raw.price_coin ?? rawAccess.chapter_unlock_price ?? 0,
  };

  return {
    id: raw.id || '',
    content_id: raw.content_id || '',
    series: {
      id: series.id || '',
      title: series.title || '',
      slug: series.slug || '',
      type: series.type || 'manga',
    },
    chapter_number: String(raw.chapter_number || ''),
    title: raw.title ?? null,
    type: raw.type === 'text' ? 'text' : 'image',
    created_at: raw.created_at || new Date().toISOString(),
    body: raw.body ?? null,
    pages: Array.isArray(raw.pages)
      ? raw.pages.map((p: any) => ({
          image_path: typeof p === 'string' ? p : p.image_path || '',
          page_order: p.page_order,
        }))
      : [],
    navigation: {
      previous: nav.previous ? String(nav.previous) : null,
      next: nav.next ? String(nav.next) : null,
    },
    access,
  };
}

export function chapterUrl(series: { type: string; slug: string }, chapterNumber: string) {
  return `/${encodeURIComponent(series.type)}/${encodeURIComponent(series.slug)}/chapter/${encodeURIComponent(chapterNumber)}`;
}

export function getReadingProgress(): number {
  const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
  if (scrollableHeight <= 0) {
    return 100;
  }
  return Math.min(100, Math.max(0, (window.scrollY / scrollableHeight) * 100));
}
