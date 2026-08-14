import React, { useEffect, useState, useCallback } from 'react';
import { contentService } from '../services';
import { HomeData } from '../types/api';
import { ExploreSlider } from '../components/home/ExploreSlider';
import { RecentChaptersSection } from '../components/home/RecentChaptersSection';
import { RecentlyAddedSection } from '../components/home/RecentlyAddedSection';
import { PopularBlogsSection } from '../components/home/PopularBlogsSection';
import { LatestBlogsSection } from '../components/home/LatestBlogsSection';
import { Skeleton, ContentGridSkeleton } from '../components/feedback/Skeleton';
import { ErrorState } from '../components/feedback/ErrorState';
import { EmptyState } from '../components/feedback/EmptyState';
import { usePreferences } from '../contexts/PreferencesContext';

export const HomePage: React.FC = () => {
  const { t } = usePreferences();
  const [homeData, setHomeData] = useState<HomeData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const fetchHomeData = useCallback(async () => {
    setIsLoading(true);
    setErrorMsg(null);

    const res = await contentService.getHome();

    if (res.status === 'success' && res.data) {
      setHomeData(res.data);
    } else {
      setErrorMsg(res.error?.message || t('home.errorTitle'));
    }

    setIsLoading(false);
  }, [t]);

  useEffect(() => {
    fetchHomeData();
  }, [fetchHomeData]);

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex flex-col gap-10">
        {/* Hero Slider Skeleton */}
        <div className="w-full h-80 rounded-2xl animate-pulse bg-slate-200 dark:bg-slate-800" />

        {/* Section Skeleton */}
        <div className="flex flex-col gap-4">
          <Skeleton className="h-8 w-48" />
          <ContentGridSkeleton count={5} />
        </div>

        {/* Recent Chapters Skeleton */}
        <div className="flex flex-col gap-4">
          <Skeleton className="h-8 w-48" />
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="h-24 rounded-xl animate-pulse bg-slate-200 dark:bg-slate-800" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (errorMsg) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <ErrorState
          title={t('home.errorTitle')}
          message={errorMsg}
          onRetry={fetchHomeData}
        />
      </div>
    );
  }

  const isAllEmpty =
    !homeData ||
    ((!homeData.explore || homeData.explore.length === 0) &&
      (!homeData.recent_chapters || homeData.recent_chapters.length === 0) &&
      (!homeData.recently_added || homeData.recently_added.length === 0) &&
      (!homeData.popular_blogs || homeData.popular_blogs.length === 0) &&
      (!homeData.latest_blogs || homeData.latest_blogs.length === 0));

  if (isAllEmpty) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <EmptyState
          title={t('home.emptyTitle')}
          description={t('home.emptyDesc')}
          actionLabel={t('common.refresh')}
          onAction={fetchHomeData}
        />
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col gap-8 pb-12 transition-colors duration-300">
      {/* 1. EXPLORE (Carousel/Slider) */}
      {homeData.explore && homeData.explore.length > 0 && (
        <ExploreSlider items={homeData.explore} />
      )}

      {/* 2. RECENT CHAPTERS */}
      {homeData.recent_chapters && homeData.recent_chapters.length > 0 && (
        <RecentChaptersSection chapters={homeData.recent_chapters} />
      )}

      {/* 3. RECENTLY ADDED */}
      {homeData.recently_added && homeData.recently_added.length > 0 && (
        <RecentlyAddedSection items={homeData.recently_added} />
      )}

      {/* 4. POPULAR BLOGS */}
      {homeData.popular_blogs && homeData.popular_blogs.length > 0 && (
        <PopularBlogsSection blogs={homeData.popular_blogs} />
      )}

      {/* 5. LATEST BLOGS */}
      {homeData.latest_blogs && homeData.latest_blogs.length > 0 && (
        <LatestBlogsSection blogs={homeData.latest_blogs} />
      )}
    </div>
  );
};

