import React, { lazy, Suspense } from 'react';
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

// Route-level chunks keep the initial app shell small.
const HomePage = lazy(() => import('./pages/HomePage').then((module) => ({ default: module.HomePage })));
const BrowsePage = lazy(() => import('./pages/BrowsePage').then((module) => ({ default: module.BrowsePage })));
const GenreDirectoryPage = lazy(() => import('./pages/GenreDirectoryPage').then((module) => ({ default: module.GenreDirectoryPage })));
const GenreResultsPage = lazy(() => import('./pages/GenreResultsPage').then((module) => ({ default: module.GenreResultsPage })));
const TagDirectoryPage = lazy(() => import('./pages/TagDirectoryPage').then((module) => ({ default: module.TagDirectoryPage })));
const TagResultsPage = lazy(() => import('./pages/TagResultsPage').then((module) => ({ default: module.TagResultsPage })));
const SearchPage = lazy(() => import('./pages/SearchPage').then((module) => ({ default: module.SearchPage })));
const ContentDetailPage = lazy(() => import('./pages/ContentDetailPage').then((module) => ({ default: module.ContentDetailPage })));
const ChapterListPage = lazy(() => import('./pages/ChapterListPage').then((module) => ({ default: module.ChapterListPage })));
const ReaderPage = lazy(() => import('./pages/ReaderPage').then((module) => ({ default: module.ReaderPage })));
const BlogListPage = lazy(() => import('./pages/BlogListPage').then((module) => ({ default: module.BlogListPage })));
const BlogDetailPage = lazy(() => import('./pages/BlogDetailPage').then((module) => ({ default: module.BlogDetailPage })));
const MyBlogsPage = lazy(() => import('./pages/MyBlogsPage').then((module) => ({ default: module.MyBlogsPage })));
const NewBlogPage = lazy(() => import('./pages/NewBlogPage').then((module) => ({ default: module.NewBlogPage })));
const EditBlogPage = lazy(() => import('./pages/EditBlogPage').then((module) => ({ default: module.EditBlogPage })));
const LoginPage = lazy(() => import('./pages/LoginPage').then((module) => ({ default: module.LoginPage })));
const RegisterPage = lazy(() => import('./pages/RegisterPage').then((module) => ({ default: module.RegisterPage })));
const ResetPasswordPage = lazy(() => import('./pages/ResetPasswordPage').then((module) => ({ default: module.ResetPasswordPage })));
const VerifyEmailPage = lazy(() => import('./pages/VerifyEmailPage').then((module) => ({ default: module.VerifyEmailPage })));
const ProfilePage = lazy(() => import('./pages/ProfilePage').then((module) => ({ default: module.ProfilePage })));
const PublicProfilePage = lazy(() => import('./pages/PublicProfilePage').then((module) => ({ default: module.PublicProfilePage })));
const LibraryPage = lazy(() => import('./pages/LibraryPage').then((module) => ({ default: module.LibraryPage })));
const HistoryPage = lazy(() => import('./pages/HistoryPage').then((module) => ({ default: module.HistoryPage })));
const NotificationsPage = lazy(() => import('./pages/NotificationsPage').then((module) => ({ default: module.NotificationsPage })));
const PreferencesPage = lazy(() => import('./pages/PreferencesPage').then((module) => ({ default: module.PreferencesPage })));
const WalletPage = lazy(() => import('./pages/WalletPage').then((module) => ({ default: module.WalletPage })));
const ShopPage = lazy(() => import('./pages/ShopPage').then((module) => ({ default: module.ShopPage })));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage').then((module) => ({ default: module.NotFoundPage })));

export function App() {
  return (
    <PreferencesProvider>
      <AuthProvider>
        <NotificationsProvider>
          <Router>
            <div className="min-h-screen w-full max-w-full overflow-x-hidden bg-[var(--bg-primary)] text-[var(--text-primary)] flex flex-col font-sans transition-colors duration-300 selection:bg-[var(--accent-color)] selection:text-white">
              <Header />
              <EmailVerificationBanner />
              <main className="flex-1 pb-16 sm:pb-0">
                <Suspense fallback={<div className="min-h-[40vh]" aria-busy="true" aria-label="Loading" />}>
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
                </Suspense>
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
