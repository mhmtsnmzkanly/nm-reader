import HomePage from '../pages/home.f7';
import TypeListPage from '../pages/type-list.f7';
import ContentDetailPage from '../pages/content-detail.f7';
import ReaderPage from '../pages/reader.f7';
import WalletPage from '../pages/wallet.f7';
import WalletTransactionsPage from '../pages/wallet-transactions.f7';
import ShopPackagesPage from '../pages/shop-packages.f7';
import ShopFeaturesPage from '../pages/shop-features.f7';

var routes = [
  { path: '/', component: HomePage },
  { path: '/types/:type/', component: TypeListPage },
  { path: '/content/:type/:slug/', component: ContentDetailPage },
  { path: '/reader/:type/:slug/:chapterNumber/', component: ReaderPage },
  { path: '/wallet/', component: WalletPage },
  { path: '/wallet/transactions/', component: WalletTransactionsPage },
  { path: '/shop/packages/', component: ShopPackagesPage },
  { path: '/shop/features/', component: ShopFeaturesPage },
];

export default routes;
