import { IUserService } from '../contracts';
import {
  ApiResponse,
  ApiSuccess,
  ApiError,
  UserProfile,
  UserPreferences,
  PublicProfileData,
  ContentSummary,
  ContentType,
  LibraryItem,
  ReadingHistoryItem,
  NotificationItem,
  FollowingUserItem,
} from '../../types/api';
import {
  mockUserProfile,
  mockPublicUsers,
  mockLibraryItems,
  mockReadingHistory,
  mockContentDetails,
  mockUserActivities,
  mockContentSummaries,
  mockNotifications,
  mockBlogs,
  mockComments,
  mockFollowingUsers,
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

const initialPreferences: UserPreferences = {
  appearance: {
    theme: 'dark',
    accent: 'default',
  },
  language: {
    locale: 'tr',
  },
  reader: {
    layout: 'vertical',
    fit: 'width',
    direction: 'ltr',
    auto_hide_ui: true,
    show_progress: true,
    fontSize: '18',
    fontFamily: 'var(--font-sans)',
    lineHeight: '1.8',
    fontWeight: '400',
    imageFit: 'width',
    readingDirection: 'ltr',
  },
  notifications: {
    new_chapter: true,
    comments: true,
    replies: true,
    mentions: true,
    system: true,
  },
  account: {
    is_logged_in: true,
    email: 'deniz@example.test',
    last_sync: '2026-08-14T02:00:00Z',
  },
  lang: 'tr',
  theme: 'dark',
};

export class MockUserService implements IUserService {
  private userPrefs: UserPreferences = { ...initialPreferences };

  async getProfile(): Promise<ApiResponse<UserProfile>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeSuccess({
        id: null,
        username: 'guest',
        display_name: 'Misafir Kullanıcı',
        email: null,
        bio: null,
        avatar: null,
        profile_image: null,
        cover_image: null,
        joined_at: null,
        created_at: null,
        is_guest: true,
        stats: {
          chapters_read: 0,
          series_following: 0,
          library_count: 0,
          comments: 0,
          score: 0,
          followers_count: 0,
          following_count: 0,
          votes_count: 0,
          blogs_count: 0,
        },
        reading: {
          chapters_read: 0,
          completed_series: 0,
          ongoing_series: 0,
        },
      });
    }
    return makeSuccess(mockUserProfile);
  }

  async updateProfile(
    data: Partial<UserProfile>
  ): Promise<ApiResponse<UserProfile>> {
    await delay();
    Object.assign(mockUserProfile, data);
    if (data.avatar) {
      mockUserProfile.profile_image = data.avatar;
    } else if (data.profile_image) {
      mockUserProfile.avatar = data.profile_image;
    }
    // Synchronize to public users lookup
    if (mockPublicUsers.deniz) {
      mockPublicUsers.deniz.user = { ...mockUserProfile };
    }
    return makeSuccess(mockUserProfile);
  }

  async getPublicProfile(
    person: string
  ): Promise<ApiResponse<PublicProfileData>> {
    await delay();
    const normalized = person.toLowerCase().trim();

    if (normalized === 'notfound' || normalized === '404' || normalized === 'error') {
      return makeError(404, 'NOT_FOUND', 'Aradığınız kullanıcı profili bulunamadı.');
    }

    const entry = mockPublicUsers[normalized];
    if (entry) {
      const data: PublicProfileData = {
        user: entry.user,
        stats: entry.stats,
        reading: entry.reading,
        user_state: entry.user_state,
        library: entry.library,
        following: entry.following,
        recent_history: entry.recent_history,
        activities: entry.activities,
        blogs: entry.blogs,
        recent_comments: entry.recent_comments,
      };
      return makeSuccess(data);
    }

    // Default dynamic user if not in specific dictionary
    const dynamicUser: PublicProfileData = {
      user: {
        id: `user-${normalized}`,
        username: person,
        display_name: person.charAt(0).toUpperCase() + person.slice(1),
        email: null,
        bio: 'Hikaye dünyasında gezinen bir okuyucu.',
        avatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&auto=format&fit=crop&q=80',
        profile_image: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&auto=format&fit=crop&q=80',
        cover_image: 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=1200&auto=format&fit=crop&q=80',
        joined_at: '2025-05-10T12:00:00Z',
        created_at: '2025-05-10 12:00:00',
        is_guest: false,
        stats: {
          chapters_read: 320,
          series_following: 12,
          library_count: 15,
          comments: 24,
          score: 450,
          followers_count: 18,
          following_count: 9,
          votes_count: 45,
          blogs_count: 0,
        },
        reading: {
          chapters_read: 320,
          completed_series: 4,
          ongoing_series: 8,
        },
        user_state: {
          is_following: false,
        },
      },
      stats: {
        chapters_read: 320,
        series_following: 12,
        library_count: 15,
        comments: 24,
        score: 450,
        followers_count: 18,
        following_count: 9,
        votes_count: 45,
        comments_count: 24,
        blogs_count: 0,
      },
      reading: {
        chapters_read: 320,
        completed_series: 4,
        ongoing_series: 8,
      },
      user_state: {
        is_following: false,
      },
      library: mockContentSummaries.slice(0, 2),
      following: mockContentSummaries.slice(0, 2),
      recent_history: mockReadingHistory.slice(0, 2),
      activities: mockUserActivities.slice(0, 2),
      blogs: [],
      recent_comments: mockComments.slice(0, 1),
    };

    return makeSuccess(dynamicUser);
  }

  async toggleFollowUser(
    username: string
  ): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>> {
    await delay();
    const normalized = username.toLowerCase().trim();
    let entry = mockPublicUsers[normalized];

    if (!entry) {
      // Create on the fly
      const publicData = (await this.getPublicProfile(username)).data;
      if (publicData) {
        mockPublicUsers[normalized] = {
          user: publicData.user,
          stats: {
            ...publicData.stats,
            chapters_read: publicData.stats.chapters_read || 0,
            series_following: publicData.stats.series_following || 0,
            library_count: publicData.stats.library_count || 0,
            comments: publicData.stats.comments || 0,
          },
          reading: publicData.reading || { chapters_read: 0, completed_series: 0, ongoing_series: 0 },
          user_state: publicData.user_state || { is_following: false },
          library: publicData.library || [],
          following: publicData.following || [],
          recent_history: publicData.recent_history || [],
          activities: publicData.activities || [],
          blogs: publicData.blogs || [],
          recent_comments: publicData.recent_comments || [],
        };
        entry = mockPublicUsers[normalized];
      }
    }

    if (entry) {
      const nextState = !entry.user_state.is_following;
      entry.user_state.is_following = nextState;
      if (entry.user.user_state) {
        entry.user.user_state.is_following = nextState;
      }
      if (nextState) {
        entry.stats.followers_count += 1;
        if (entry.user.stats) entry.user.stats.followers_count = (entry.user.stats.followers_count || 0) + 1;
      } else {
        entry.stats.followers_count = Math.max(0, entry.stats.followers_count - 1);
        if (entry.user.stats) entry.user.stats.followers_count = Math.max(0, (entry.user.stats.followers_count || 1) - 1);
      }

      const listTarget = mockFollowingUsers.find(
        (u) => u.username.toLowerCase() === normalized
      );
      if (listTarget) {
        listTarget.is_following = nextState;
        listTarget.followers_count = entry.stats.followers_count;
      }

      return makeSuccess({
        is_following: nextState,
        followers_count: entry.stats.followers_count,
      });
    }

    return makeSuccess({ is_following: true, followers_count: 1 });
  }

  async getPreferences(): Promise<ApiResponse<UserPreferences>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') {
      return makeError(500, 'NETWORK_ERROR', 'Ağ bağlantısı hatası oluştu');
    }
    return makeSuccess(this.userPrefs);
  }

  async updatePreferences(
    prefs: Partial<UserPreferences>
  ): Promise<ApiResponse<UserPreferences>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'network_error') {
      return makeError(500, 'NETWORK_ERROR', 'Ağ bağlantısı hatası oluştu');
    }

    // Appearance deep merge
    if (prefs.appearance) {
      this.userPrefs.appearance = {
        ...this.userPrefs.appearance,
        ...prefs.appearance,
      };
      if (prefs.appearance.theme) {
        this.userPrefs.theme = prefs.appearance.theme;
      }
    }
    if (prefs.theme) {
      this.userPrefs.theme = prefs.theme;
      if (this.userPrefs.appearance) {
        this.userPrefs.appearance.theme = prefs.theme;
      }
    }

    // Language deep merge
    if (prefs.language) {
      this.userPrefs.language = {
        ...this.userPrefs.language,
        ...prefs.language,
      };
      if (prefs.language.locale) {
        this.userPrefs.lang = prefs.language.locale;
      }
    }
    if (prefs.lang) {
      this.userPrefs.lang = prefs.lang;
      if (this.userPrefs.language) {
        this.userPrefs.language.locale = prefs.lang;
      }
    }

    // Reader deep merge
    if (prefs.reader) {
      this.userPrefs.reader = {
        ...this.userPrefs.reader,
        ...prefs.reader,
        // sync aliases
        fit: prefs.reader.fit || prefs.reader.imageFit || this.userPrefs.reader.fit || 'width',
        imageFit: prefs.reader.fit || prefs.reader.imageFit || this.userPrefs.reader.imageFit || 'width',
        direction: prefs.reader.direction || prefs.reader.readingDirection || this.userPrefs.reader.direction || 'ltr',
        readingDirection: prefs.reader.direction || prefs.reader.readingDirection || this.userPrefs.reader.readingDirection || 'ltr',
      };
    }

    // Notifications deep merge
    if (prefs.notifications) {
      this.userPrefs.notifications = {
        ...this.userPrefs.notifications,
        ...prefs.notifications,
      };
    }

    if (this.userPrefs.account) {
      this.userPrefs.account.last_sync = new Date().toISOString();
    }

    return makeSuccess(this.userPrefs);
  }

  async getLibrary(
    page = 1,
    per_page = 20,
    filter = 'all',
    sort = 'recently_added'
  ): Promise<ApiResponse<LibraryItem[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'network_error') {
      return makeError(500, 'SERVER_ERROR', 'Kütüphane yüklenirken bir sunucu hatası oluştu.');
    }
    if (sc === 'empty_data') {
      return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });
    }

    // 1. Filter by content type if not 'all'
    let items = [...mockLibraryItems];
    if (filter && filter !== 'all') {
      if (filter === 'novel') {
        items = items.filter((item) => ['novel', 'light-novel', 'web-novel'].includes(item.content.type));
      } else if (filter === 'manhwa' || filter === 'webtoon') {
        items = items.filter((item) => ['manhwa', 'webtoon'].includes(item.content.type));
      } else {
        items = items.filter((item) => item.content.type === filter);
      }
    }

    // 2. Sort items
    items.sort((a, b) => {
      if (sort === 'recently_added') {
        return new Date(b.added_at).getTime() - new Date(a.added_at).getTime();
      }
      if (sort === 'recently_read') {
        const progA = a.user_state.last_read_progress ?? -1;
        const progB = b.user_state.last_read_progress ?? -1;
        return progB - progA;
      }
      if (sort === 'title') {
        return a.content.title.localeCompare(b.content.title);
      }
      if (sort === 'rating') {
        return (b.content.rating ?? 0) - (a.content.rating ?? 0);
      }
      return 0;
    });

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = items.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = items.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async addToLibrary(
    contentIdOrSlug: string,
    type: ContentType = 'manga'
  ): Promise<ApiResponse<{ added: boolean; item: LibraryItem }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const existing = mockLibraryItems.find(
      (item) => item.content.slug === contentIdOrSlug || item.content.id === contentIdOrSlug || item.id === contentIdOrSlug
    );
    if (existing) {
      return makeSuccess({ added: true, item: existing });
    }

    // Find summary or details to construct a proper LibraryItem
    const summary = mockContentSummaries.find((c) => c.slug === contentIdOrSlug || c.id === contentIdOrSlug);
    const detail = mockContentDetails[contentIdOrSlug];

    const newItem: LibraryItem = {
      id: `lib-${Date.now()}`,
      content: {
        id: summary?.id || detail?.id || `series-${Date.now()}`,
        type: (summary?.type || detail?.type || type) as ContentType,
        slug: summary?.slug || detail?.slug || contentIdOrSlug,
        title: summary?.title || detail?.title || contentIdOrSlug,
        cover: summary?.cover_image || summary?.cover || detail?.cover || 'https://images.unsplash.com/photo-1563089145-599997674d42?w=600&auto=format&fit=crop&q=80',
        status: (summary?.status || detail?.status || 'ONGOING').toUpperCase(),
        rating: summary?.rating_avg || (typeof detail?.rating === 'object' ? detail?.rating?.average : 9.0) || 9.0,
        summary: summary?.description || detail?.description || '',
        total_chapters: summary?.chapter_count || detail?.chapters?.length || 1,
      },
      added_at: new Date().toISOString(),
      user_state: {
        is_following: summary?.is_followed ?? false,
        last_read_chapter_id: null,
        last_read_chapter_number: null,
        last_read_progress: null,
      },
    };

    mockLibraryItems.unshift(newItem);
    return makeSuccess({ added: true, item: newItem });
  }

  async removeFromLibrary(
    idOrSlug: string
  ): Promise<ApiResponse<{ removed: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const index = mockLibraryItems.findIndex(
      (item) => item.id === idOrSlug || item.content.slug === idOrSlug || item.content.id === idOrSlug
    );
    if (index !== -1) {
      mockLibraryItems.splice(index, 1);
      return makeSuccess({ removed: true });
    }
    return makeSuccess({ removed: false });
  }

  async toggleLibrary(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ in_library: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const index = mockLibraryItems.findIndex((item) => item.content.slug === slug);
    if (index !== -1) {
      mockLibraryItems.splice(index, 1);
      return makeSuccess({ in_library: false });
    } else {
      await this.addToLibrary(slug, type);
      return makeSuccess({ in_library: true });
    }
  }

  async getFollows(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'empty_data') {
      return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });
    }
    const followed = mockContentSummaries.filter((c) => c.is_followed);
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = followed.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = followed.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getHistory(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ReadingHistoryItem[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'network_error') {
      return makeError(500, 'SERVER_ERROR', 'Geçmiş verileri yüklenirken bir sunucu hatası oluştu.');
    }
    if (sc === 'empty_data') {
      return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });
    }

    // Sort by read_at desc
    const items = [...mockReadingHistory].sort(
      (a, b) => new Date(b.read_at).getTime() - new Date(a.read_at).getTime()
    );
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = items.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = items.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async recordHistory(data: {
    contentSlug: string;
    contentType?: ContentType;
    chapterId?: string;
    chapterNumber: number | string;
    chapterTitle?: string | null;
    page?: number;
    totalPages?: number;
    progress: number;
  }): Promise<ApiResponse<{ recorded: boolean }>> {
    await delay(30);
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const slug = data.contentSlug;
    const summary = mockContentSummaries.find((c) => c.slug === slug);
    const detail = mockContentDetails[slug];
    const type = data.contentType || summary?.type || detail?.type || 'manga';
    const title = summary?.title || detail?.title || slug;
    const cover = summary?.cover_image || summary?.cover || detail?.cover || 'https://images.unsplash.com/photo-1563089145-599997674d42?w=600&auto=format&fit=crop&q=80';
    const chapNum = data.chapterNumber;
    const chapId = data.chapterId || `chapter-${chapNum}`;
    const chapTitle = data.chapterTitle || `Bölüm ${chapNum}`;
    const progress = Math.min(100, Math.max(0, Math.round(data.progress)));
    const nowIso = new Date().toISOString();

    // Check if an entry for this chapter or series already exists in history
    const existingIndex = mockReadingHistory.findIndex(
      (h) => (h.content_slug === slug || h.content?.slug === slug) && (h.chapter?.number == chapNum || h.chapter_number == chapNum)
    );

    const historyItem: ReadingHistoryItem = {
      id: existingIndex !== -1 ? mockReadingHistory[existingIndex].id : `history-${Date.now()}`,
      content: {
        id: summary?.id || detail?.id || `series-${slug}`,
        type,
        slug,
        title,
        cover,
        status: summary?.status || detail?.status || 'ONGOING',
        rating: summary?.rating_avg || 9.0,
      },
      chapter: {
        id: chapId,
        number: chapNum,
        title: chapTitle,
      },
      progress,
      last_page: data.page || Math.round((progress / 100) * (data.totalPages || 20)),
      total_pages: data.totalPages || 20,
      read_at: nowIso,
      chapter_id: chapId,
      chapter_number: chapNum,
      chapter_title: chapTitle,
      content_slug: slug,
      content_title: title,
      content_type: type,
      content_cover_image: cover,
      series: {
        id: summary?.id || detail?.id || `series-${slug}`,
        title,
        slug,
        cover,
        type,
      },
    };

    if (existingIndex !== -1) {
      mockReadingHistory.splice(existingIndex, 1);
    }
    mockReadingHistory.unshift(historyItem);

    // Also update library item's last_read state if present
    const libItem = mockLibraryItems.find((l) => l.content.slug === slug);
    if (libItem) {
      libItem.user_state.last_read_chapter_id = chapId;
      libItem.user_state.last_read_chapter_number = chapNum;
      libItem.user_state.last_read_progress = progress;
    }

    return makeSuccess({ recorded: true });
  }

  async removeFromHistory(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const index = mockReadingHistory.findIndex(
      (h) => h.id === id || h.chapter_id === id || h.chapter?.id === id
    );
    if (index !== -1) {
      mockReadingHistory.splice(index, 1);
      return makeSuccess({ deleted: true });
    }
    return makeSuccess({ deleted: false });
  }

  async clearHistory(): Promise<ApiResponse<{ cleared: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    mockReadingHistory.length = 0;
    return makeSuccess({ cleared: true });
  }

  async getNotifications(
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<NotificationItem[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'network_error') {
      return makeError(500, 'SERVER_ERROR', 'Bildirimler alınırken bir sunucu hatası oluştu.');
    }
    if (sc === 'empty_data') {
      return makeSuccess([], { page, per_page: 20, total: 0, total_pages: 0 });
    }
    // Return a cloned copy sorted by created_at desc
    const sorted = [...mockNotifications].sort(
      (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    );
    return makeSuccess(sorted, { page, per_page: 20, next_cursor: null, total: sorted.length, total_pages: 1 });
  }

  async markNotificationsRead(id?: number | 'all'): Promise<ApiResponse<{ marked: boolean }>> {
    await delay();
    if (!id || id === 'all') {
      mockNotifications.forEach((n) => (n.is_read = 1));
    } else {
      const found = mockNotifications.find((n) => n.id === id);
      if (found) {
        found.is_read = 1;
      }
    }
    return makeSuccess({ marked: true });
  }

  async deleteNotification(id: number): Promise<ApiResponse<{ deleted: boolean }>> {
    await delay();
    const index = mockNotifications.findIndex((n) => n.id === id);
    if (index !== -1) {
      mockNotifications.splice(index, 1);
      return makeSuccess({ deleted: true });
    }
    return makeError(404, 'NOT_FOUND', 'Bildirim bulunamadı');
  }

  async getFollowingUsers(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<FollowingUserItem[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'empty_data') {
      return makeSuccess([], { page, per_page, total: 0, total_pages: 0 });
    }

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = mockFollowingUsers.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = mockFollowingUsers.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }
}
