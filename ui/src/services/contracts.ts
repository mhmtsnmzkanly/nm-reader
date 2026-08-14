import {
  ApiResponse,
  AuthPayload,
  BlogSummary,
  ChapterReader,
  ChapterSummary,
  ChapterUnlockRow,
  Comment,
  ContentDetail,
  ContentSummary,
  ContentType,
  FeatureEntitlement,
  FeatureProduct,
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
  getBlogs(
    page?: number,
    per_page?: number,
    sort?: 'latest' | 'popular',
    tag?: string
  ): Promise<ApiResponse<BlogSummary[]>>;
  getBlogBySlug(slug: string): Promise<ApiResponse<BlogSummary>>;
  getUserBlogs(page?: number, per_page?: number): Promise<ApiResponse<BlogSummary[]>>;
  createBlog(title: string, body: string, tags?: string[], excerpt?: string): Promise<ApiResponse<BlogSummary>>;
  toggleLikeBlog(slug: string): Promise<ApiResponse<{ liked: boolean; likes: number }>>;
  getRelatedBlogs(slug: string, limit?: number): Promise<ApiResponse<BlogSummary[]>>;
  voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ vote: number; upvote_count: number; downvote_count: number }>>;
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
    email: string,
    password: string,
    remember: boolean
  ): Promise<ApiResponse<AuthPayload>>;
  register(
    username: string,
    email: string,
    password: string
  ): Promise<ApiResponse<{ id: string; username: string; email: string }>>;
  refresh(): Promise<ApiResponse<AuthPayload>>;
  logout(): Promise<ApiResponse<{ logged_out: boolean }>>;
  getSessions(): Promise<ApiResponse<UserSession[]>>;
  revokeSession(sessionId: string): Promise<ApiResponse<{ revoked: boolean }>>;
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
