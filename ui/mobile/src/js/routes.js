import HomePage from '../pages/home.f7';
import TypeListPage from '../pages/type-list.f7';
import ContentDetailPage from '../pages/content-detail.f7';
import ReaderPage from '../pages/reader.f7';
import WalletPage from '../pages/wallet.f7';
import WalletTransactionsPage from '../pages/wallet-transactions.f7';
import ShopPackagesPage from '../pages/shop-packages.f7';
import ShopFeaturesPage from '../pages/shop-features.f7';
import SearchPage from '../pages/search.f7';
import GenresPage from '../pages/genres.f7';
import GenreDetailPage from '../pages/genre-detail.f7';
import TagsPage from '../pages/tags.f7';
import TagDetailPage from '../pages/tag-detail.f7';
import ProfilePage from '../pages/profile.f7';
import LibraryPage from '../pages/library.f7';
import HistoryPage from '../pages/history.f7';
import BlogsPage from '../pages/blogs.f7';
import BlogDetailPage from '../pages/blog-detail.f7';
import NotificationsPage from '../pages/notifications.f7';
import PreferencesPage from '../pages/preferences.f7';
import SessionsPage from '../pages/sessions.f7';

var routes = [
  { path: '/', component: HomePage },
  { path: '/types/:type/', component: TypeListPage },
  { path: '/content/:type/:slug/', component: ContentDetailPage },
  { path: '/reader/:type/:slug/:chapterNumber/', component: ReaderPage },
  { path: '/wallet/', component: WalletPage },
  { path: '/wallet/transactions/', component: WalletTransactionsPage },
  { path: '/shop/packages/', component: ShopPackagesPage },
  { path: '/shop/features/', component: ShopFeaturesPage },
  { path: '/search/', component: SearchPage },
  { path: '/genres/', component: GenresPage },
  { path: '/genre/:slug/', component: GenreDetailPage },
  { path: '/tags/', component: TagsPage },
  { path: '/tag/:slug/', component: TagDetailPage },
  { path: '/profile/', component: ProfilePage },
  { path: '/library/', component: LibraryPage },
  { path: '/history/', component: HistoryPage },
  { path: '/blogs/', component: BlogsPage },
  { path: '/blogs/:slug/', component: BlogDetailPage },
  { path: '/notifications/', component: NotificationsPage },
  { path: '/preferences/', component: PreferencesPage },
  { path: '/sessions/', component: SessionsPage },
];

export default routes;
