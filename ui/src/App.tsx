import React, { useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { PreferencesProvider } from './contexts/PreferencesContext';
import { NotificationsProvider } from './contexts/NotificationsContext';
import { Header } from './components/navigation/Header';
import { Footer } from './components/navigation/Footer';
import { MobileNav } from './components/navigation/MobileNav';
import { AuthModal } from './components/auth/AuthModal';
import { NotificationsModal } from './components/notifications/NotificationsModal';
import { EmailVerificationBanner } from './components/common/EmailVerificationBanner';
import { MaintenanceOverlay } from './components/common/MaintenanceOverlay';
import { triggerQueueTick } from './services';

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
import { EditBlogPage } from './pages/EditBlogPage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { VerifyEmailPage } from './pages/VerifyEmailPage';
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
  useEffect(() => {
    // Sayfa ilk yüklendiğinde arka plan kuyruk tetikleyicisini sessizce çalıştır
    triggerQueueTick();
  }, []);

  return (
    <PreferencesProvider>
      <AuthProvider>
        <NotificationsProvider>
          <Router>
            <div className="min-h-screen w-full max-w-full overflow-x-hidden bg-[var(--bg-primary)] text-[var(--text-primary)] flex flex-col font-sans transition-colors duration-300 selection:bg-[var(--accent-color)] selection:text-white">
              <Header />
              <EmailVerificationBanner />
              <main className="flex-1 pb-16 sm:pb-0">
                <Routes>
                  <Route path="/" element={<HomePage />} />
                  <Route path="/browse" element={<BrowsePage />} />
                  <Route path="/browse/:type" element={<BrowsePage />} />
                  <Route path="/genres" element={<GenreDirectoryPage />} />
                  <Route path="/genre/:slug" element={<GenreResultsPage />} />
                  <Route path="/tags" element={<TagDirectoryPage />} />
                  <Route path="/tag/:slug" element={<TagResultsPage />} />
                  <Route path="/search" element={<SearchPage />} />

                  {/* Content Details & Reader */}
                  <Route path="/:type/:slug" element={<ContentDetailPage />} />
                  <Route path="/:type/:slug/chapters" element={<ChapterListPage />} />
                  <Route path="/:type/:slug/chapter/:chapterNumber" element={<ReaderPage />} />

                  {/* Blogs */}
                  <Route path="/blogs" element={<BlogListPage />} />
                  <Route path="/blog/:slug" element={<BlogDetailPage />} />
                  <Route path="/blogs/:slug" element={<BlogDetailPage />} />
                  <Route path="/my-blogs" element={<MyBlogsPage />} />
                  <Route path="/blogs/new" element={<NewBlogPage />} />
                  <Route path="/blogs/:id/edit" element={<EditBlogPage />} />

                  {/* Account & Auth */}
                  <Route path="/login" element={<LoginPage />} />
                  <Route path="/register" element={<RegisterPage />} />
                  <Route path="/reset-password" element={<ResetPasswordPage />} />
                  <Route path="/verify-email" element={<VerifyEmailPage />} />
                  <Route path="/me" element={<ProfilePage />} />
                  <Route path="/u/:username" element={<PublicProfilePage />} />
                  <Route path="/library" element={<LibraryPage />} />
                  <Route path="/history" element={<HistoryPage />} />
                  <Route path="/notifications" element={<NotificationsPage />} />
                  <Route path="/preferences" element={<PreferencesPage />} />
                  <Route path="/wallet" element={<WalletPage />} />
                  <Route path="/shop" element={<ShopPage />} />
                  <Route path="*" element={<NotFoundPage />} />
                </Routes>
              </main>
              <Footer />
              <MobileNav />
              <AuthModal />
              <NotificationsModal />
              <MaintenanceOverlay />
            </div>
          </Router>
        </NotificationsProvider>
      </AuthProvider>
    </PreferencesProvider>
  );
}

export default App;
