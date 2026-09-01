import {
  ApiResponse,
  AuthPayload,
  BlogSummary,
  ChapterDetail,
  ChapterReader,
  ChapterSummary,
  ChapterUnlockRow,
  Comment,
  ContentDetail,
  ContentSummary,
  ContentType,
  FeatureEntitlement,
  FeatureProduct,
  FollowingUserItem,
  Genre,
  HomeData,
  LibraryItem,
  NotificationItem,
  PublicProfileData,
  ReadingHistoryItem,
  SearchFilters,
  SearchSuggestItem,
  SeriesUnlockRow,
  ShopPackage,
  Tag,
  User,
  UserProfile,
  UserPreferences,
  UserSession,
  WalletData,
  WalletTransaction,
} from '../types/api';

export interface IContentService {
  getHome(page?: number, per_page?: number): Promise<ApiResponse<HomeData>>;
  getContentByType(
    type: ContentType,
    page?: number,
    per_page?: number
  ): Promise<ApiResponse<ContentSummary[]>>;
  getContentDetail(type: ContentType, slug: string): Promise<ApiResponse<ContentDetail>>;
  getChapters(
    type: ContentType,
    slug: string,
    page?: number,
    per_page?: number
  ): Promise<ApiResponse<ChapterSummary[]>>;
  getChapterReader(
    type: ContentType,
    slug: string,
    chapterNumber: string
  ): Promise<ApiResponse<ChapterReader>>;
  getGenres(page?: number, per_page?: number): Promise<ApiResponse<Genre[]>>;
  getGenreContents(
    slug: string,
    page?: number,
    per_page?: number
  ): Promise<ApiResponse<ContentSummary[]>>;
  getTags(page?: number, per_page?: number): Promise<ApiResponse<Tag[]>>;
  getTagContents(
    slug: string,
    page?: number,
    per_page?: number
  ): Promise<ApiResponse<ContentSummary[]>>;
  search(
    q: string,
    page?: number,
    per_page?: number,
    filters?: SearchFilters
  ): Promise<ApiResponse<ContentSummary[]>>;
  searchSuggest(q: string): Promise<ApiResponse<SearchSuggestItem[]>>;
  toggleFollow(type: ContentType, slug: string): Promise<ApiResponse<{ followed: boolean }>>;
  rateContent(
    type: ContentType,
    slug: string,
    rating: number
  ): Promise<ApiResponse<{ rated: boolean }>>;
  unlockSeries(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>>;
  unlockChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>>;
}

export interface IBlogService {
  // Kamuya açık blog listeleme (Sıralama ve etiket filtreli)
  getBlogs(
    page?: number,
    per_page?: number,
    sort?: 'latest' | 'popular',
    tag?: string
  ): Promise<ApiResponse<BlogSummary[]>>;

  // Tekil blog detayı
  getBlogBySlug(slug: string): Promise<ApiResponse<BlogSummary>>;

  // İlgili blog yazıları
  getRelatedBlogs(slug: string, limit?: number): Promise<ApiResponse<BlogSummary[]>>;

  // Blog oylama (+1, -1, 0)
  voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ vote: number; upvote_count: number; downvote_count: number; likes: number }>>;

  // Görsel yükleme (Backend { path, url } döner)
  uploadBlogImage(formData: FormData): Promise<ApiResponse<{ path: string; url: string }>>;

  // YAZAR YÖNETİMİ (Kullanıcının kendi yazıları)
  getUserBlogs(page?: number, per_page?: number): Promise<ApiResponse<BlogSummary[]>>;
  getMyBlog(id: string): Promise<ApiResponse<BlogSummary>>;
  createBlog(
    title: string,
    body: string,
    tags?: string[],
    excerpt?: string,
    cover_image?: string,
    status?: 'draft' | 'pending'
  ): Promise<ApiResponse<BlogSummary>>;
  updateBlog(
    id: string,
    payload: {
      title?: string;
      body?: string;
      tags?: string[];
      excerpt?: string;
      cover_image?: string;
      status?: 'draft' | 'pending';
    }
  ): Promise<ApiResponse<BlogSummary>>;
  deleteBlog(id: string): Promise<ApiResponse<{ deleted: boolean }>>;
}

export interface ICommentService {
  getComments(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    page?: number,
    cursor?: string
  ): Promise<ApiResponse<Comment[]>>;
  postComment(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    body: string,
    parent_id?: number | null
  ): Promise<ApiResponse<{ comment_id: number }>>;
  voteComment(
    commentId: number,
    vote: -1 | 0 | 1
  ): Promise<
    ApiResponse<{
      comment_id: number;
      upvote_count: number;
      downvote_count: number;
      my_vote: number;
    }>
  >;
}

export interface IAuthService {
  login(
    identity: string,
    password: string,
    remember?: boolean
  ): Promise<ApiResponse<AuthPayload>>;
  register(
    username: string,
    email: string,
    password: string
  ): Promise<ApiResponse<{ id: string; username: string; email: string; email_verified?: boolean }>>;
  refresh(): Promise<ApiResponse<AuthPayload>>;
  logout(): Promise<ApiResponse<{ logged_out: boolean }>>;
  getSessions(): Promise<ApiResponse<UserSession[]>>;
  revokeSession(sessionId: string): Promise<ApiResponse<{ revoked: boolean }>>;
  revokeOtherSessions(): Promise<ApiResponse<{ revoked_count: number }>>;

  // Yeni E-posta & Şifre İşlemleri
  forgotPassword(email: string): Promise<ApiResponse<{ message: string }>>;
  resetPassword(token: string, password: string): Promise<ApiResponse<{ id: string; message: string }>>;
  verifyEmail(token: string): Promise<ApiResponse<{ id: string; email_verified: boolean }>>;
  resendVerificationEmail(): Promise<ApiResponse<{ message: string }>>;
}

export interface IUserService {
  getProfile(): Promise<ApiResponse<UserProfile>>;
  updateProfile(data: Partial<UserProfile>): Promise<ApiResponse<UserProfile>>;
  getPublicProfile(person: string): Promise<ApiResponse<PublicProfileData>>;
  getPreferences(): Promise<ApiResponse<UserPreferences>>;
  updatePreferences(prefs: Partial<UserPreferences>): Promise<ApiResponse<UserPreferences>>;
  getLibrary(
    page?: number,
    per_page?: number,
    filter?: string,
    sort?: string
  ): Promise<ApiResponse<LibraryItem[]>>;
  addToLibrary(
    contentIdOrSlug: string,
    type?: ContentType
  ): Promise<ApiResponse<{ added: boolean; item: LibraryItem }>>;
  removeFromLibrary(
    idOrSlug: string
  ): Promise<ApiResponse<{ removed: boolean }>>;
  toggleLibrary(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ in_library: boolean }>>;
  getFollows(page?: number, per_page?: number): Promise<ApiResponse<ContentSummary[]>>;
  getFollowingUsers(page?: number, per_page?: number): Promise<ApiResponse<FollowingUserItem[]>>;
  getHistory(page?: number, per_page?: number): Promise<ApiResponse<ReadingHistoryItem[]>>;
  recordHistory(data: {
    contentSlug: string;
    contentType?: ContentType;
    chapterId?: string;
    chapterNumber: number | string;
    chapterTitle?: string | null;
    page?: number;
    totalPages?: number;
    progress: number;
  }): Promise<ApiResponse<{ recorded: boolean }>>;
  removeFromHistory(id: string): Promise<ApiResponse<{ deleted: boolean }>>;
  clearHistory(): Promise<ApiResponse<{ cleared: boolean }>>;
  getNotifications(
    page?: number,
    cursor?: string
  ): Promise<ApiResponse<NotificationItem[]>>;
  markNotificationsRead(id?: number | 'all'): Promise<ApiResponse<{ marked: boolean }>>;
  deleteNotification(id: number): Promise<ApiResponse<{ deleted: boolean }>>;
  toggleFollowUser(username: string): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>>;
}

export interface IWalletService {
  getWallet(): Promise<ApiResponse<WalletData>>;
  getTransactions(
    page?: number,
    per_page?: number,
    filter?: string,
    sort?: string,
    search?: string
  ): Promise<ApiResponse<WalletTransaction[]>>;
  getSeriesUnlocks(page?: number, per_page?: number): Promise<ApiResponse<SeriesUnlockRow[]>>;
  getChapterUnlocks(
    page?: number,
    per_page?: number
  ): Promise<ApiResponse<ChapterUnlockRow[]>>;
  getFeatureEntitlements(): Promise<ApiResponse<FeatureEntitlement[]>>;
  getShopPackages(): Promise<ApiResponse<ShopPackage[]>>;
  getShopFeatures(): Promise<ApiResponse<FeatureProduct[]>>;
  purchasePackage(packageId: number): Promise<ApiResponse<{ purchased: boolean; balance: number; package: ShopPackage }>>;
  purchaseChapter(chapterId: string): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number; balance: number }>>;
  purchaseAdFree(): Promise<ApiResponse<{ purchased: boolean; expires_at: string }>>;
}

export type ReportTargetType = 'series' | 'chapter' | 'blog' | 'comment';

export interface IReportService {
  createReport(payload: {
    target_type: ReportTargetType;
    target_id: string;
    reason: string;
    description?: string;
  }): Promise<ApiResponse<{ id: number; status: string }>>;
}

export type {
  User,
  ContentSummary,
  ContentDetail,
  ChapterSummary,
  ChapterDetail,
  ChapterReader,
};
