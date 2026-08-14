// API Response Envelope and Contract Types for nm-reader

export type SearchFilters = {
  genres?: string[];
  tags?: string[];
  status?: string;
  sort?: string;
};

export interface PaginationMeta {
  page: number;
  per_page: number;
  total?: number;
  total_pages?: number;
  next_cursor?: string | null;
  q?: string;
  filters?: SearchFilters;
  [key: string]: unknown;
}

export type ApiSuccess<T> = {
  status: 'success';
  data: T;
  meta: PaginationMeta | Record<string, unknown>;
  error: null;
};

export type ApiErrorDetail = {
  code: number;
  key: string;
  message: string;
  params: Record<string, unknown>;
};

export type ApiError = {
  status: 'error';
  data: null;
  meta: Record<string, unknown>;
  error: ApiErrorDetail;
};

export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type ContentType =
  | 'manga'
  | 'manhua'
  | 'manhwa'
  | 'webtoon'
  | 'light-novel'
  | 'web-novel'
  | 'novel';

export type ContentStatus = 'ongoing' | 'completed' | 'hiatus' | 'dropped';

export type GenreConfig = {
  icon?: string;
  color?: string;
};

export type Genre = {
  id: number | string;
  name: string;
  slug: string;
  ui_config?: GenreConfig;
  content_count?: number;
};

export type Tag = {
  id: number | string;
  name: string;
  slug: string;
  ui_config?: Record<string, unknown>;
  content_count?: number;
};

export type AuthorObj = {
  id?: string;
  name: string;
};

export type ArtistObj = {
  id?: string;
  name: string;
};

export type ContentRating = {
  average: number;
  count: number;
};

export type ContentUserState = {
  is_following: boolean;
  is_in_library: boolean;
  last_read_chapter_id?: string | null;
  last_read_chapter_number?: string | number | null;
  last_read_progress?: number | null;
};

export type ContentDetailChapter = {
  id: string;
  number: number;
  title: string | null;
  published_at: string;
  is_locked: boolean;
  price_coin: number;
  chapter_number?: string;
  content_id?: string;
  created_at?: string;
  type?: 'image' | 'text';
  body?: string | null;
  pages?: ChapterPage[];
  access?: ChapterAccess;
  adjacent_chapters?: { prev: string | null; next: string | null };
};

export type ContentSummary = {
  id: string; // 6-char alphanumeric e.g. "a1b2c3"
  title: string;
  slug: string;
  type: ContentType;
  status: ContentStatus | string;
  rating_avg: number;
  rating_average?: number;
  rating_count: number;
  chapter_count: number;
  comment_count: number;
  cover_image: string | null;
  cover?: string | null;
  banner?: string | null;
  accent_color: string | null;
  is_followed: boolean;
  author: AuthorObj | string | null;
  artist?: ArtistObj | string | null;
  alternative_titles?: string[] | string | null;
  country?: string | null;
  release_year?: number | string | null;
  description?: string | null;
  summary?: string | null;
  views?: number;
  total_views?: number;
  created_at?: string | null;
  type_path?: string;
  url_path?: string;
};

export type ReadingProgress = {
  last_chapter_id: string;
  last_page: number;
  updated_at: string;
};

export type ContentDetail = ContentSummary & {
  rating?: ContentRating | number;
  genres?: Genre[];
  tags?: Tag[];
  series_genres?: Genre[];
  series_tags?: Tag[];
  chapters?: ContentDetailChapter[];
  user_state?: ContentUserState;
  reading_progress?: ReadingProgress | null;
  series_unlock_price?: number;
  is_series_unlocked?: boolean;
  has_any_premium?: boolean;
};

export type AdjacentChapters = {
  next: string | null; // chapter_number or null
  prev: string | null; // chapter_number or null
};

export type AccessInfo = {
  granted: boolean;
  reason:
    | 'granted'
    | 'series_unlocked'
    | 'chapter_unlocked'
    | 'free'
    | 'chapter_unlock_required'
    | 'series_unlock_required'
    | 'auth_required';
  series_unlock_price: number;
  chapter_unlock_price: number;
  is_series_unlocked: boolean;
  is_chapter_unlocked: boolean;
  is_free: boolean;
  requires_series_unlock: boolean;
  requires_chapter_unlock: boolean;
};

export type Series = {
  id: string;
  title: string;
  slug: string;
  type: string;
};

export type ChapterPage = {
  image_path: string;
  page_order?: number;
};

export type ChapterNavigation = {
  previous: string | null;
  next: string | null;
};

export type ChapterAccess = {
  granted: boolean;
  locked?: boolean;
  price_coin?: number;
  reason?: string;
  is_chapter_unlocked?: boolean;
  series_unlock_price?: number;
  chapter_unlock_price?: number;
  is_series_unlocked?: boolean;
  is_free?: boolean;
  requires_series_unlock?: boolean;
  requires_chapter_unlock?: boolean;
};

export type Chapter = {
  id: string;
  content_id: string;
  series: Series;
  chapter_number: string;
  title: string | null;
  type: 'text' | 'image';
  created_at: string;
  body: string | null;
  pages: ChapterPage[];
  navigation: ChapterNavigation;
  access: ChapterAccess;
};

export type ChapterSummary = {
  id: string;
  content_id: string;
  chapter_number: string;
  title: string | null;
  type: 'image' | 'text';
  created_at: string;
  body: string | null;
  pages: ChapterPage[];
  adjacent_chapters: { prev: string | null; next: string | null };
  access: ChapterAccess;
  price_coin: number;
  is_locked: boolean;
};

export type ChapterReader = Chapter;

export type ExploreItem = {
  id: string;
  type: ContentType;
  slug: string;
  title: string;
  summary: string;
  cover: string;
  background?: string;
  rating: number;
  status: string;
  cover_image?: string | null;
  description?: string | null;
  rating_avg?: number;
};

export type RecentChapterItem = {
  id: string;
  series_id: string;
  series_type: ContentType;
  series_slug: string;
  series_title: string;
  chapter_number: number | string;
  chapter_title: string | null;
  cover: string;
  published_at: string;
  cover_image?: string | null;
  created_at?: string;
};

export type BlogAuthor = {
  id?: string;
  username: string;
  display_name: string;
  avatar?: string | null;
};

export type BlogTag = {
  id: string;
  name: string;
  slug: string;
};

export type BlogStats = {
  views: number;
  likes: number;
  comments: number;
};

export type BlogUserState = {
  liked: boolean;
};

export type BlogItem = {
  id: string;
  slug: string;
  title: string;
  excerpt: string;
  cover_image: string;
  author: BlogAuthor;
  published_at: string;
  updated_at?: string;
  read_time: number;
  tags: BlogTag[];
  content: string;
  stats: BlogStats;
  user_state?: BlogUserState;
  // Compatibility fields
  user_id?: string;
  approved?: number;
  approver_user_id?: string | null;
  approved_at?: string | null;
  created_at?: string;
  body?: string;
  author_username?: string;
  approver_username?: string | null;
  views?: number;
  likes?: number;
  comments_count?: number;
  upvote_count?: number;
  downvote_count?: number;
  my_vote?: number;
};

export type BlogSummary = BlogItem;
export type HomeBlogItem = BlogItem;

export type HomeData = {
  explore: ExploreItem[];
  recent_chapters: RecentChapterItem[];
  recently_added: ContentSummary[];
  popular_blogs: HomeBlogItem[];
  latest_blogs: HomeBlogItem[];
};

export type SearchSuggestItem = {
  id: string;
  title: string;
  slug: string;
  type: ContentType;
  cover_image: string | null;
};

export type Comment = {
  id: number;
  body: string;
  parent_id: number | null;
  upvote_count: number;
  downvote_count: number;
  created_at: string;
  user_id: string;
  username: string;
  profile_image: string | null;
  my_vote?: number;
};

export type UserProfileStats = {
  chapters_read: number;
  series_following: number;
  library_count: number;
  comments: number;
  score?: number;
  followers_count?: number;
  following_count?: number;
  votes_count?: number;
  blogs_count?: number;
};

export type ReadingSummary = {
  chapters_read: number;
  completed_series: number;
  ongoing_series: number;
};

export type UserActivityItem = {
  id: string | number;
  type: 'comment' | 'favorite' | 'review' | 'blog';
  target: {
    type: 'content' | 'chapter' | 'blog';
    id: string;
    slug?: string;
    title: string;
    content_type?: string;
  };
  text?: string;
  created_at: string;
};

export type UserProfile = {
  id: string | null;
  username: string;
  display_name?: string;
  email: string | null;
  bio: string | null;
  avatar?: string | null;
  profile_image: string | null;
  cover_image: string | null;
  joined_at?: string | null;
  created_at: string | null;
  is_guest: boolean;
  stats?: UserProfileStats;
  reading?: ReadingSummary;
  user_state?: {
    is_following: boolean;
  };
};

export type AuthPayload = {
  id: string;
  username: string;
  email: string;
  csrf_token: string;
  refresh_token: string | null;
  api_token: string;
  roles: string[];
  permissions: string[];
};

export type AppearancePreferences = {
  theme: 'dark' | 'light' | 'system' | 'default' | 'royal' | 'bootstrap' | 'material' | 'apple' | 'glass';
  accent: 'default' | 'emerald' | 'amber' | 'rose' | 'cyan' | 'purple' | string;
};

export type LanguagePreferences = {
  locale: 'tr' | 'en';
};

export type ReaderPreferences = {
  layout: 'vertical' | 'single' | 'double';
  fit: 'width' | 'height' | 'original';
  direction: 'ltr' | 'rtl';
  auto_hide_ui: boolean;
  show_progress: boolean;
  fontSize?: string;
  fontFamily?: string;
  lineHeight?: string;
  fontWeight?: string;
  imageFit?: 'width' | 'height' | 'original';
  readingDirection?: 'ltr' | 'rtl';
};

export type NotificationPreferences = {
  new_chapter: boolean;
  comments: boolean;
  replies: boolean;
  mentions: boolean;
  system: boolean;
};

export type UserPreferences = {
  appearance: AppearancePreferences;
  language: LanguagePreferences;
  reader: ReaderPreferences;
  notifications: NotificationPreferences;
  account?: {
    is_logged_in: boolean;
    email: string | null;
    last_sync: string;
  };
  // Compatibility getters / legacy aliases
  lang?: 'tr' | 'en';
  theme?: 'default' | 'dark' | 'light' | 'system' | 'royal' | 'bootstrap' | 'material' | 'apple' | 'glass';
};

export type LibraryItem = {
  id: string;
  content: {
    id: string;
    type: ContentType;
    slug: string;
    title: string;
    cover: string;
    status?: string;
    rating?: number;
    summary?: string;
    total_chapters?: number;
  };
  added_at: string;
  user_state: {
    is_following: boolean;
    last_read_chapter_id?: string | null;
    last_read_chapter_number?: number | string | null;
    last_read_progress?: number | null;
  };
};

export type ReadingHistoryItem = {
  id: string;
  content: {
    id: string;
    type: ContentType;
    slug: string;
    title: string;
    cover: string;
    status?: string;
    rating?: number;
  };
  chapter: {
    id: string;
    number: number | string;
    title?: string | null;
  };
  progress: number;
  last_page?: number;
  total_pages?: number;
  read_at: string;

  // Backward compatibility fields
  chapter_id?: string;
  chapter_number?: string | number;
  chapter_title?: string | null;
  content_slug?: string;
  content_title?: string;
  content_type?: ContentType;
  content_cover_image?: string;
  series?: {
    id: string;
    title: string;
    slug: string;
    cover?: string;
    type?: ContentType;
  };
};

export type NotificationType =
  | 'chapter_release'
  | 'new_chapter'
  | 'comment_reply'
  | 'comment_vote'
  | 'comment'
  | 'reply'
  | 'mention'
  | 'user_follow'
  | 'follow'
  | 'coin_reward'
  | 'wallet_transaction'
  | 'system'
  | 'system_announcement';

export type NotificationPayloadData = {
  content_id?: string;
  content_slug?: string;
  content_title?: string;
  content_type?: string;
  chapter_id?: string;
  chapter_number?: string | number;
  chapter_title?: string;
  comment_id?: number;
  actor_avatar?: string;
  coins?: number;
  url?: string;
  [key: string]: unknown;
};

export type NotificationItem = {
  id: number;
  type: NotificationType | string;
  title: string;
  body: string;
  data: string | NotificationPayloadData; // JSON string or object
  is_read: number; // 0 or 1
  created_at: string;
  actor_user_id: string;
  actor_username: string;
  actor_avatar?: string | null;
  target_url?: string;
};

export type WalletData = {
  user_id: string;
  balance_coin: number;
  balance?: number;
  currency?: string;
  total_coin_purchased: number;
  total_coin_spent: number;
  updated_at: string;
};

export type WalletTransactionType =
  | 'package_credit'
  | 'manual_credit'
  | 'series_unlock'
  | 'chapter_unlock'
  | 'feature_unlock'
  | 'manual_debit'
  | 'refund'
  | 'PURCHASE'
  | 'CREDIT'
  | 'REFUND';

export type WalletTransaction = {
  id: number | string;
  type: WalletTransactionType;
  coin_delta: number;
  amount?: number;
  balance_after: number;
  reference_type?: string | null;
  reference_id?: string | null;
  reference?: {
    type: string;
    id: string;
  } | null;
  description: string;
  metadata?: string | Record<string, unknown> | null;
  created_by?: string | null;
  created_at: string;
};

export type TransactionFilterType = 'ALL' | 'CREDIT' | 'PURCHASE' | 'REFUND';
export type TransactionSortOption = 'newest' | 'oldest' | 'amount_desc' | 'amount_asc';

export type ShopPackage = {
  id: number;
  name: string;
  coin_amount: number;
  bonus_coin: number;
  display_price: string;
  currency: string;
  is_active: number;
  is_featured?: boolean;
  badge?: string;
  sort_order: number;
  created_at: string;
  updated_at: string;
};

export type FeatureProduct = {
  feature_key: string;
  name: string;
  coin_price: number;
  duration_days: number;
  is_active: number;
  updated_at: string;
};

export type FeatureEntitlement = {
  id: number;
  feature_key: string;
  source_type: string;
  source_id: string;
  transaction_id: number;
  starts_at: string;
  expires_at: string;
  created_at: string;
};

export type FeatureStatusMap = {
  [key: string]: {
    feature_key: string;
    active: boolean;
    starts_at: string | null;
    expires_at: string | null;
  };
};

export type SeriesUnlockRow = {
  id: number;
  content_id: string;
  content_title: string;
  content_slug: string;
  content_type: ContentType;
  price_coin: number;
  transaction_id: number;
  unlocked_at: string;
};

export type ChapterUnlockRow = {
  id: number;
  content_id: string;
  chapter_id: string;
  chapter_number: string;
  chapter_title: string | null;
  price_coin: number;
  transaction_id: number;
  unlocked_at: string;
};

export type PublicProfileData = {
  user: UserProfile;
  stats: {
    chapters_read?: number;
    series_following?: number;
    library_count?: number;
    comments?: number;
    score: number;
    followers_count: number;
    following_count: number;
    votes_count: number;
    comments_count: number;
    blogs_count: number;
  };
  reading?: ReadingSummary;
  user_state?: {
    is_following: boolean;
  };
  library?: ContentSummary[];
  following?: ContentSummary[];
  recent_history?: ReadingHistoryItem[];
  activities?: UserActivityItem[];
  blogs: BlogSummary[];
  recent_comments: Comment[];
};

export type UserSession = {
  id: string;
  ip_address: string;
  user_agent: string;
  last_activity: string;
  is_current: boolean;
  created_at: string;
};
