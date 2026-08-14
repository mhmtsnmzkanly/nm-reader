import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { PreferencesProvider } from './contexts/PreferencesContext';
import { NotificationsProvider } from './contexts/NotificationsContext';
import { Header } from './components/navigation/Header';
import { Footer } from './components/navigation/Footer';
import { MobileNav } from './components/navigation/MobileNav';
import { AuthModal } from './components/auth/AuthModal';
import { NotificationsModal } from './components/notifications/NotificationsModal';

// Pages
import { HomePage } from './pages/HomePage';
import { BrowsePage } from './pages/BrowsePage';
import { GenreDirectoryPage } from './pages/GenreDirectoryPage';
import { GenreResultsPage } from './pages/GenreResultsPage';
import { TagDirectoryPage } from './pages/TagDirectoryPage';
import { TagResultsPage } from './pages/TagResultsPage';
import { SearchPage } from './pages/SearchPage';
import { ContentDetailPage } from './pages/ContentDetailPage';
import { ChapterListPage } from './pages/ChapterListPage';
import { ReaderPage } from './pages/ReaderPage';
import { BlogListPage } from './pages/BlogListPage';
import { BlogDetailPage } from './pages/BlogDetailPage';
import { MyBlogsPage } from './pages/MyBlogsPage';
import { NewBlogPage } from './pages/NewBlogPage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { ProfilePage } from './pages/ProfilePage';
import { PublicProfilePage } from './pages/PublicProfilePage';
import { LibraryPage } from './pages/LibraryPage';
import { HistoryPage } from './pages/HistoryPage';
import { NotificationsPage } from './pages/NotificationsPage';
import { PreferencesPage } from './pages/PreferencesPage';
import { WalletPage } from './pages/WalletPage';
import { ShopPage } from './pages/ShopPage';
import { NotFoundPage } from './pages/NotFoundPage';

export function App() {
  return (
    <PreferencesProvider>
      <AuthProvider>
        <NotificationsProvider>
          <Router>
            <div className="min-h-screen w-full max-w-full overflow-x-hidden bg-[var(--bg-primary)] text-[var(--text-primary)] flex flex-col font-sans transition-colors duration-300 selection:bg-[var(--accent-color)] selection:text-white">
              <Header />
              <main className="flex-1 pb-16 sm:pb-0">
                <Routes>
                  {/* Home */}
                  <Route path="/" element={<HomePage />} />
                  <Route path="/:lang(tr|en)" element={<HomePage />} />

                  {/* Browse & Discovery */}
                  <Route path="/browse" element={<BrowsePage />} />
                  <Route path="/:lang(tr|en)/browse" element={<BrowsePage />} />
                  <Route path="/browse/:type" element={<BrowsePage />} />
                  <Route path="/:lang(tr|en)/browse/:type" element={<BrowsePage />} />

                  {/* Taxonomy */}
                  <Route path="/genres" element={<GenreDirectoryPage />} />
                  <Route path="/:lang(tr|en)/genres" element={<GenreDirectoryPage />} />
                  <Route path="/genre/:slug" element={<GenreResultsPage />} />
                  <Route path="/:lang(tr|en)/genre/:slug" element={<GenreResultsPage />} />
                  <Route path="/tags" element={<TagDirectoryPage />} />
                  <Route path="/:lang(tr|en)/tags" element={<TagDirectoryPage />} />
                  <Route path="/tag/:slug" element={<TagResultsPage />} />
                  <Route path="/:lang(tr|en)/tag/:slug" element={<TagResultsPage />} />
                  <Route path="/search" element={<SearchPage />} />
                  <Route path="/:lang(tr|en)/search" element={<SearchPage />} />

                  {/* Blogs */}
                  <Route path="/blogs" element={<BlogListPage />} />
                  <Route path="/:lang(tr|en)/blogs" element={<BlogListPage />} />
                  <Route path="/blogs/new" element={<NewBlogPage />} />
                  <Route path="/:lang(tr|en)/blogs/new" element={<NewBlogPage />} />
                  <Route path="/my-blogs" element={<MyBlogsPage />} />
                  <Route path="/:lang(tr|en)/my-blogs" element={<MyBlogsPage />} />
                  <Route path="/blog/:slug" element={<BlogDetailPage />} />
                  <Route path="/:lang(tr|en)/blog/:slug" element={<BlogDetailPage />} />
                  <Route path="/blogs/:slug" element={<BlogDetailPage />} />
                  <Route path="/:lang(tr|en)/blogs/:slug" element={<BlogDetailPage />} />

                  {/* Account & User */}
                  <Route path="/login" element={<LoginPage />} />
                  <Route path="/:lang(tr|en)/login" element={<LoginPage />} />
                  <Route path="/register" element={<RegisterPage />} />
                  <Route path="/:lang(tr|en)/register" element={<RegisterPage />} />
                  <Route path="/me" element={<ProfilePage />} />
                  <Route path="/:lang(tr|en)/me" element={<ProfilePage />} />
                  <Route path="/profile" element={<ProfilePage />} />
                  <Route path="/:lang(tr|en)/profile" element={<ProfilePage />} />
                  <Route path="/u/:username" element={<PublicProfilePage />} />
                  <Route path="/:lang(tr|en)/u/:username" element={<PublicProfilePage />} />
                  <Route path="/profile/:username" element={<PublicProfilePage />} />
                  <Route path="/:lang(tr|en)/profile/:username" element={<PublicProfilePage />} />
                  <Route path="/library" element={<LibraryPage />} />
                  <Route path="/:lang(tr|en)/library" element={<LibraryPage />} />
                  <Route path="/history" element={<HistoryPage />} />
                  <Route path="/:lang(tr|en)/history" element={<HistoryPage />} />
                  <Route path="/notifications" element={<NotificationsPage />} />
                  <Route path="/:lang(tr|en)/notifications" element={<NotificationsPage />} />
                  <Route path="/preferences" element={<PreferencesPage />} />
                  <Route path="/:lang(tr|en)/preferences" element={<PreferencesPage />} />
                  <Route path="/wallet" element={<WalletPage />} />
                  <Route path="/:lang(tr|en)/wallet" element={<WalletPage />} />
                  <Route path="/shop" element={<ShopPage />} />
                  <Route path="/:lang(tr|en)/shop" element={<ShopPage />} />

                  {/* Content Details & Reader (Specific content types and patterns) */}
                  <Route path="/:type/:slug/chapters" element={<ChapterListPage />} />
                  <Route path="/:lang(tr|en)/:type/:slug/chapters" element={<ChapterListPage />} />
                  <Route path="/:type/:slug/chapter/:chapterNumber" element={<ReaderPage />} />
                  <Route path="/:lang(tr|en)/:type/:slug/chapter/:chapterNumber" element={<ReaderPage />} />
                  <Route path="/:type/:slug" element={<ContentDetailPage />} />
                  <Route path="/:lang(tr|en)/:type/:slug" element={<ContentDetailPage />} />

                  {/* Fallback */}
                  <Route path="*" element={<NotFoundPage />} />
                </Routes>
              </main>
              <Footer />
              <MobileNav />
              <AuthModal />
              <NotificationsModal />
            </div>
          </Router>
        </NotificationsProvider>
      </AuthProvider>
    </PreferencesProvider>
  );
}

export default App;
