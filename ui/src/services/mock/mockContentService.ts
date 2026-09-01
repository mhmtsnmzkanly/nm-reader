import { IContentService } from '../contracts';
import { normalizeChapter } from '../../utils/chapter';
import {
  ApiResponse,
  ApiSuccess,
  ApiError,
  HomeData,
  ContentSummary,
  ContentDetail,
  ChapterSummary,
  ChapterReader,
  Genre,
  Tag,
  SearchFilters,
  SearchSuggestItem,
  ContentType,
} from '../../types/api';
import {
  mockHomeData,
  mockContentSummaries,
  mockContentDetails,
  mockChapters,
  mockGenres,
  mockTags,
  mockWalletData,
  mockTransactions,
  mockChapterUnlocks,
  mockSeriesUnlocks,
} from '../../mocks/fixtures';
import { scenarioManager } from '../../mocks/scenarios';

function makeSuccess<T>(data: T, meta: Record<string, unknown> = {}): ApiSuccess<T> {
  return { status: 'success', data, meta, error: null };
}

function makeError(code: number, key: string, message: string): ApiError {
  return {
    status: 'error',
    data: null,
    meta: {},
    error: { code, key, message, params: {} },
  };
}

const delay = (ms = 150) => new Promise((res) => setTimeout(res, ms));

export class MockContentService implements IContentService {
  async getHome(): Promise<ApiResponse<HomeData>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') {
      return makeError(500, 'INTERNAL_ERROR', 'Network communication error');
    }
    if (sc === 'empty_data') {
      return makeSuccess({
        explore: [],
        recent_chapters: [],
        recently_added: [],
        popular_blogs: [],
        latest_blogs: [],
      });
    }
    return makeSuccess(mockHomeData, { page: 1, per_page: 20 });
  }

  async getContentByType(
    type: ContentType,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') return makeError(500, 'INTERNAL_ERROR', 'Network error');
    if (sc === 'empty_data') return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });

    const filtered = mockContentSummaries.filter((c) => c.type === type);
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = filtered.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = filtered.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getContentDetail(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<ContentDetail>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') return makeError(500, 'INTERNAL_ERROR', 'Network error');

    const found = mockContentDetails[slug] || mockContentSummaries.find((c) => c.slug === slug);
    if (!found) {
      return makeError(404, 'NOT_FOUND', 'Content not found');
    }

    const chapterList = mockChapters[slug] || [];
    const convertedChapters = chapterList.map((ch) => ({
      ...ch,
      is_adult: ch.is_adult ?? found.is_adult ?? false,
      is_members_only: ch.is_members_only ?? found.is_members_only ?? false,
      number: ch.number ?? Number(ch.chapter_number || 1),
      published_at: ch.published_at ?? ch.created_at ?? '2026-08-12T10:00:00Z',
    }));

    const detail: ContentDetail = {
      ...found,
      genres: (found as ContentDetail).genres || (found as ContentDetail).series_genres || [mockGenres[0], mockGenres[1]],
      tags: (found as ContentDetail).tags || (found as ContentDetail).series_tags || [mockTags[0]],
      series_genres: (found as ContentDetail).series_genres || [mockGenres[0], mockGenres[1]],
      series_tags: (found as ContentDetail).series_tags || [mockTags[0]],
      series_unlock_price: (found as ContentDetail).series_unlock_price || 0,
      is_series_unlocked: (found as ContentDetail).is_series_unlocked || false,
      has_any_premium: (found as ContentDetail).has_any_premium ?? true,
      chapters: convertedChapters,
      user_state: (found as ContentDetail).user_state || {
        is_following: found.is_followed ?? false,
        is_in_library: false,
        last_read_chapter_id: null,
        last_read_progress: null,
      },
    };

    return makeSuccess(detail);
  }

  async getChapters(
    type: ContentType,
    slug: string,
    page = 1,
    per_page = 50
  ): Promise<ApiResponse<ChapterSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') return makeError(500, 'INTERNAL_ERROR', 'Network error');
    if (sc === 'empty_data') return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });

    const list = mockChapters[slug] || [];
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = list.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = list.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getChapterReader(
    type: ContentType,
    slug: string,
    chapterNumber: string
  ): Promise<ApiResponse<ChapterReader>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') return makeError(500, 'INTERNAL_ERROR', 'Network error');

    const list = mockChapters[slug] || [];
    let ch = list.find((c) => c.chapter_number === chapterNumber);

    const detail = mockContentDetails[slug];
    const seriesTitle = detail?.title || slug.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');

    if (!ch) {
      const num = parseInt(chapterNumber, 10) || 1;
      const isText = type.includes('novel');

      ch = {
        id: `ch_${slug}_${chapterNumber}`,
        content_id: detail?.id || `content_${slug}`,
        chapter_number: chapterNumber,
        title: `Bölüm ${chapterNumber}`,
        type: isText ? 'text' : 'image',
        created_at: new Date().toISOString(),
        body: isText
          ? `# Bölüm ${chapterNumber}\n\nGüneş dağların ardında gözden kaybolmuştu bile.\n\nYavaşça **eski ahşap kapıyı** açtı.\n\n> "İçeride onu bekleyen bir şeyler vardı."\n\nRüzgar, yaprakları *sessizce* hışırdatırken karanlık derinleşti.`
          : null,
        pages: isText
          ? []
          : [
              { image_path: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1000&auto=format&fit=crop&q=80', page_order: 1 },
              { image_path: 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=1000&auto=format&fit=crop&q=80', page_order: 2 },
              { image_path: 'https://images.unsplash.com/photo-1541701494587-cb58502866ab?w=1000&auto=format&fit=crop&q=80', page_order: 3 },
            ],
        adjacent_chapters: {
          prev: num > 1 ? String(num - 1) : null,
          next: String(num + 1),
        },
        access: {
          granted: true,
          locked: false,
          price_coin: 0,
        },
        price_coin: 0,
        is_locked: false,
      };
    }

    const reader = normalizeChapter(
      {
        ...ch,
        is_adult: ch.is_adult ?? detail?.is_adult ?? false,
        is_members_only: ch.is_members_only ?? detail?.is_members_only ?? false,
      },
      {
        id: ch.content_id,
        title: seriesTitle,
        slug: slug,
        type: type,
      }
    );
    return makeSuccess(reader);
  }

  async getGenres(page = 1, per_page = 20): Promise<ApiResponse<Genre[]>> {
    await delay();
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = mockGenres.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = mockGenres.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getGenreContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'empty_data') return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });

    const lowerSlug = slug.toLowerCase();
    const filtered = mockContentSummaries.filter((c) => {
      const detail = mockContentDetails[c.slug];
      const genres = detail?.series_genres?.map((g) => g.slug.toLowerCase()) || [];
      return genres.length === 0 || genres.includes(lowerSlug);
    });

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = filtered.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = filtered.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getTags(page = 1, per_page = 20): Promise<ApiResponse<Tag[]>> {
    await delay();
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = mockTags.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = mockTags.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getTagContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'empty_data') return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });

    const lowerSlug = slug.toLowerCase();
    const filtered = mockContentSummaries.filter((c) => {
      const detail = mockContentDetails[c.slug];
      const tags = detail?.series_tags?.map((t) => t.slug.toLowerCase()) || [];
      return tags.length === 0 || tags.includes(lowerSlug);
    });

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = filtered.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = filtered.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async search(
    q: string,
    page = 1,
    per_page = 20,
    filters: SearchFilters = {}
  ): Promise<ApiResponse<ContentSummary[]>> {
    await delay();
    const trimmedQ = (q || '').trim();
    const query = trimmedQ.toLowerCase();

    const parseSlugList = (val?: string[] | string): string[] => {
      if (Array.isArray(val)) return val.map((s) => s.trim().toLowerCase()).filter(Boolean);
      if (typeof val === 'string' && val.trim()) {
        return val.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
      }
      return [];
    };

    const genreSlugs = parseSlugList(filters.genres);
    const tagSlugs = parseSlugList(filters.tags);
    const statusFilter = (filters.status || '').trim().toLowerCase();
    const sortFilter = (filters.sort || '').trim();

    let results = [...mockContentSummaries];

    if (query.length > 0) {
      results = results.filter((c) => {
        const authorStr =
          typeof c.author === 'object' && c.author !== null
            ? c.author.name
            : typeof c.author === 'string'
            ? c.author
            : '';
        return (
          c.title.toLowerCase().includes(query) ||
          authorStr.toLowerCase().includes(query) ||
          c.type.toLowerCase().includes(query) ||
          (c.description && c.description.toLowerCase().includes(query))
        );
      });
    }

    if (genreSlugs.length > 0) {
      results = results.filter((c) => {
        const detail = mockContentDetails[c.slug];
        const genres = detail?.series_genres?.map((g) => g.slug.toLowerCase()) || [];
        return genreSlugs.some((gs) => genres.includes(gs));
      });
    }

    if (tagSlugs.length > 0) {
      results = results.filter((c) => {
        const detail = mockContentDetails[c.slug];
        const tags = detail?.series_tags?.map((t) => t.slug.toLowerCase()) || [];
        return tagSlugs.some((ts) => tags.includes(ts));
      });
    }

    if (statusFilter) {
      results = results.filter(
        (c) => c.status.toLowerCase() === statusFilter
      );
    }

    if (sortFilter === 'EN YENİLER' || sortFilter === 'newest') {
      results.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));
    } else if (sortFilter === 'EN ÇOK OKUNAN' || sortFilter === 'popular') {
      results.sort((a, b) => (b.chapter_count || 0) - (a.chapter_count || 0));
    } else if (sortFilter === 'EN YÜKSEK PUAN' || sortFilter === 'rating') {
      results.sort((a, b) => (b.rating_avg || 0) - (a.rating_avg || 0));
    }

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = results.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = results.slice(start, start + validPerPage);

    return makeSuccess(paginated, {
      q: trimmedQ,
      page: validPage,
      per_page: validPerPage,
      total,
      total_pages,
      filters: {
        genres: genreSlugs,
        tags: tagSlugs,
        status: filters.status || '',
        sort: filters.sort || '',
      },
    });
  }

  async searchSuggest(q: string): Promise<ApiResponse<SearchSuggestItem[]>> {
    await delay(50);
    if (!q || q.trim().length < 2) {
      return makeSuccess([]);
    }
    const query = q.toLowerCase();
    const results = mockContentSummaries
      .filter((c) => {
        const authorName = typeof c.author === 'string' ? c.author : c.author?.name || '';
        return c.title.toLowerCase().includes(query) || authorName.toLowerCase().includes(query);
      })
      .slice(0, 8)
      .map((c) => {
        const authorName = typeof c.author === 'string' ? c.author : c.author?.name || null;
        return {
          id: c.id,
          title: c.title,
          slug: c.slug,
          type: c.type,
          cover_image: c.cover_image || c.cover || null,
          rating_avg: c.rating_avg,
          chapter_count: c.chapter_count,
          status: c.status,
          author: authorName,
        };
      });
    return makeSuccess(results);
  }

  async toggleFollow(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ followed: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const target = mockContentSummaries.find((c) => c.slug === slug);
    if (target) {
      target.is_followed = !target.is_followed;
      return makeSuccess({ followed: target.is_followed });
    }
    return makeSuccess({ followed: true });
  }

  async rateContent(
    type: ContentType,
    slug: string,
    rating: number
  ): Promise<ApiResponse<{ rated: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (rating < 1 || rating > 5) {
      return makeError(400, 'BAD_REQUEST', 'Rating must be between 1 and 5');
    }
    return makeSuccess({ rated: true });
  }

  async unlockSeries(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const detail = mockContentDetails[slug];
    const price = detail ? detail.series_unlock_price : 75;

    if (sc === 'insufficient_coins' || mockWalletData.balance_coin < price) {
      return makeError(402, 'PAYMENT_REQUIRED', `Yetersiz bakiye. Bu seri için ${price} Coin gereklidir.`);
    }

    mockWalletData.balance_coin -= price;
    mockWalletData.balance = mockWalletData.balance_coin;
    mockWalletData.total_coin_spent += price;
    mockWalletData.updated_at = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const txId = Date.now();
    mockTransactions.unshift({
      id: txId,
      type: 'series_unlock',
      coin_delta: -price,
      amount: -price,
      balance_after: mockWalletData.balance_coin,
      reference_type: 'series',
      reference_id: slug,
      reference: {
        type: 'series',
        id: slug,
      },
      description: `${detail?.title || slug} — Tüm Seri Kilidi Açıldı`,
      metadata: JSON.stringify({ content_slug: slug, content_type: type }),
      created_by: null,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    if (detail) {
      detail.is_series_unlocked = true;
    }

    mockSeriesUnlocks.unshift({
      id: Date.now(),
      content_id: detail?.id || 'a1b2c3',
      content_title: detail?.title || 'Series',
      content_slug: slug,
      content_type: type,
      price_coin: price,
      transaction_id: txId,
      unlocked_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    return makeSuccess({ unlocked: true, transaction_id: txId });
  }

  async unlockChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    // Check duplicate
    const existing = mockChapterUnlocks.find((u) => u.chapter_id === chapterId);
    if (existing) {
      return makeSuccess({ unlocked: true, transaction_id: existing.transaction_id });
    }

    let targetChapter: ChapterSummary | null = null;
    let targetSlug = '';

    for (const [s, list] of Object.entries(mockChapters)) {
      const found = list.find((c) => c.id === chapterId);
      if (found) {
        targetChapter = found;
        targetSlug = s;
        break;
      }
    }

    const price = targetChapter ? targetChapter.price_coin : 12;

    if (sc === 'insufficient_coins' || mockWalletData.balance_coin < price) {
      return makeError(402, 'PAYMENT_REQUIRED', `Yetersiz bakiye. Bu bölüm için ${price} Coin gereklidir.`);
    }

    mockWalletData.balance_coin -= price;
    mockWalletData.balance = mockWalletData.balance_coin;
    mockWalletData.total_coin_spent += price;
    mockWalletData.updated_at = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const txId = Date.now();
    mockTransactions.unshift({
      id: txId,
      type: 'chapter_unlock',
      coin_delta: -price,
      amount: -price,
      balance_after: mockWalletData.balance_coin,
      reference_type: 'chapter',
      reference_id: chapterId,
      reference: {
        type: 'chapter',
        id: chapterId,
      },
      description: targetChapter
        ? `${targetSlug.replace(/-/g, ' ')} — Bölüm #${targetChapter.chapter_number} Kilidi Açıldı`
        : `Bölüm Kilidi Açıldı (${chapterId})`,
      metadata: JSON.stringify({ content_slug: targetSlug, chapter_id: chapterId }),
      created_by: null,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    if (targetChapter) {
      targetChapter.is_locked = false;
      targetChapter.access.granted = true;
      targetChapter.access.reason = 'chapter_unlocked';
      targetChapter.access.is_chapter_unlocked = true;
      if (targetChapter.type === 'image' && targetChapter.pages.length === 0) {
        targetChapter.pages = [
          { image_path: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1000&auto=format&fit=crop&q=80', page_order: 1 },
          { image_path: 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=1000&auto=format&fit=crop&q=80', page_order: 2 },
        ];
      }
    }

    const parentContent = mockContentSummaries.find((c) => c.id === targetChapter?.content_id);
    mockChapterUnlocks.unshift({
      id: Date.now(),
      content_id: targetChapter?.content_id || 'a1b2c3',
      content_title: parentContent?.title || 'Solo Leveling',
      chapter_id: chapterId,
      chapter_number: targetChapter?.chapter_number || '1',
      chapter_title: targetChapter?.title || null,
      price_coin: price,
      transaction_id: txId,
      unlocked_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    return makeSuccess({ unlocked: true, transaction_id: txId });
  }
}
