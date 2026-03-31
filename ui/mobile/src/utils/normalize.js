import { resolveCoverUrl, resolveMediaUrl } from './media.js';

/**
 * Normalizes home and listing content items into a stable mobile shape.
 */
export function normalizeContentSummary(item = {}) {
  return {
    id: item.id || '',
    slug: item.slug || item.series_slug || '',
    type: item.type || item.series_type || '',
    title: item.title || item.series_title || 'Untitled',
    description: item.description || '',
    cover: resolveCoverUrl(
      item.cover_image ||
      item.cover ||
      item.thumbnail ||
      item.image ||
      item.series_cover_image ||
      item.series_cover
    ),
    genres: Array.isArray(item.genres) ? item.genres : [],
    tags: Array.isArray(item.tags) ? item.tags : [],
    author: item.author || item.author_name || '',
    status: item.status || '',
    rating: Number(item.rating_avg || item.rating || 0),
    followers: Number(item.follow_count || 0),
    chapterCount: Number(item.chapter_count || 0),
    access: item.access || {},
  };
}

/**
 * Normalizes chapter list items for chapter lists and reader navigation.
 */
export function normalizeChapterSummary(item = {}) {
  return {
    id: item.id || '',
    slug: item.slug || item.series_slug || '',
    type: item.type || item.series_type || '',
    title: item.title || item.name || '',
    number: item.chapter_number || item.chapterNumber || item.number || '',
    price: Number(item.price_coin || item.price_amount || item.access?.chapter_unlock_price || 0),
    isLocked: item.is_locked ?? !(item.access?.granted ?? item.is_unlocked ?? true),
    isPurchased: Boolean(item.access?.granted || item.is_chapter_unlocked || item.is_series_unlocked),
    createdAt: item.created_at || null,
    access: item.access || {},
  };
}

/**
 * Normalizes reader payloads and splits novel/image readers explicitly.
 */
export function normalizeReaderPayload(item = {}) {
  const chapterType = item.type === 'image' ? 'image' : 'text';
  const rawData = typeof item.data === 'string' ? item.data : '';

  return {
    id: item.id || '',
    title: item.title || '',
    number: item.chapter_number || item.number || '',
    readerType: chapterType,
    body: chapterType === 'text' ? rawData : '',
    images: chapterType === 'image'
      ? rawData.split('|').map((entry) => resolveMediaUrl(entry)).filter(Boolean)
      : [],
    access: item.access || {},
    previousChapter: item.adjacent_chapters?.previous || null,
    nextChapter: item.adjacent_chapters?.next || null,
    series: normalizeContentSummary(item.series || {
      slug: item.slug || item.series_slug,
      type: item.series_type || item.type,
      title: item.series_title || item.series?.title,
      cover_image: item.series_cover || item.series_cover_image,
    }),
  };
}

/**
 * Normalizes the authenticated or public-facing user profile.
 */
export function normalizeProfile(item = {}) {
  return {
    id: item.id || '',
    username: item.username || 'guest',
    email: item.email || '',
    bio: item.bio || '',
    profileImage: resolveMediaUrl(item.profile_image),
    coverImage: resolveMediaUrl(item.cover_image),
    createdAt: item.created_at || null,
    isGuest: Boolean(item.is_guest),
    stats: item.stats || {},
  };
}

/**
 * Normalizes wallet responses into a stable summary shape.
 */
export function normalizeWalletSummary(item = {}) {
  return {
    balance: Number(item.balance || item.coin_balance || 0),
    totalPurchased: Number(item.total_purchased || 0),
    totalSpent: Number(item.total_spent || 0),
  };
}
