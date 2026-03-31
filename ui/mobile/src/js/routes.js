import StartupPage from '../pages/startup.f7';
import HomePage from '../pages/home.f7';
import TypeListPage from '../pages/type-list.f7';
import ContentDetailPage from '../pages/content-detail.f7';
import ReaderPage from '../pages/reader.f7';
import LoginPage from '../pages/login.f7';
import RegisterPage from '../pages/register.f7';
import ProfilePage from '../pages/profile.f7';
import LibraryPage from '../pages/library.f7';
import HistoryPage from '../pages/history.f7';
import WalletPage from '../pages/wallet.f7';
import NotificationsPage from '../pages/notifications.f7';
import SettingsPage from '../pages/settings.f7';

/**
 * Creates the mobile route list in one place so app bootstrap remains simple.
 */
export function createRoutes() {
  return [
    { path: '/', redirect: '/startup/' },
    { path: '/startup/', component: StartupPage },
    { path: '/home/', component: HomePage },
    { path: '/types/:type/', component: TypeListPage },
    { path: '/content/:type/:slug/', component: ContentDetailPage },
    { path: '/reader/:type/:slug/:chapterNumber/', component: ReaderPage },
    { path: '/login/', component: LoginPage },
    { path: '/register/', component: RegisterPage },
    { path: '/profile/', component: ProfilePage },
    { path: '/library/', component: LibraryPage },
    { path: '/history/', component: HistoryPage },
    { path: '/wallet/', component: WalletPage },
    { path: '/notifications/', component: NotificationsPage },
    { path: '/settings/', component: SettingsPage },
  ];
}
