import HomePage from '../pages/home.f7';
import TypeListPage from '../pages/type-list.f7';
import ContentDetailPage from '../pages/content-detail.f7';
import ReaderPage from '../pages/reader.f7';
import WalletPage from '../pages/wallet.f7';

var routes = [
  { path: '/', component: HomePage },
  { path: '/types/:type/', component: TypeListPage },
  { path: '/content/:type/:slug/', component: ContentDetailPage },
  { path: '/reader/:type/:slug/:chapterNumber/', component: ReaderPage },
  { path: '/wallet/', component: WalletPage },
];

export default routes;
