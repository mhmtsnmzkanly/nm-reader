import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { contentService } from '../services';
import { ChapterSummary, ContentType } from '../types/api';
import { ChapterRow } from '../components/content/ChapterRow';
import { usePreferences } from '../contexts/PreferencesContext';

function getBootstrapChapterList(type: string, slug: string): { title: string; chapters: ChapterSummary[] } | null {
  if (typeof window === 'undefined') return null;
  const page = window.__NMR_CONTEXT?.current_page;
  const pageData = page?.data;
  if (page?.route !== 'content' || pageData?.type !== type || pageData?.slug !== slug) return null;
  if (!pageData.content || !Array.isArray(pageData.chapters)) return null;
  return {
    title: pageData.content.title,
    chapters: pageData.chapters as ChapterSummary[],
  };
}

export const ChapterListPage: React.FC = () => {
  const { t } = usePreferences();
  const { type = 'manga', slug = '' } = useParams<{ type: string; slug: string }>();
  const [bootstrapData] = useState(() => getBootstrapChapterList(type, slug));
  const [contentTitle, setContentTitle] = useState<string>(bootstrapData?.title || '');
  const [chapters, setChapters] = useState<ChapterSummary[]>(bootstrapData?.chapters || []);
  const [isLoading, setIsLoading] = useState(bootstrapData === null);

  useEffect(() => {
    if (!slug) return;

    const fetchChapters = async () => {
      if (bootstrapData !== null) return;
      setIsLoading(true);
      try {
        const overviewRes = await contentService.getContentOverview(type as ContentType, slug);
        if (overviewRes.status === 'success') {
          setChapters(overviewRes.data.chapters as ChapterSummary[]);
          setContentTitle(overviewRes.data.content.title);
        }
      } catch {
        // ignore
      } finally {
        setIsLoading(false);
      }
    };

    fetchChapters();
  }, [bootstrapData, type, slug]);

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6 transition-colors duration-300">
      <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-4">
        <div>
          <Link
            to={`/${type}/${slug}`}
            className="text-xs font-mono uppercase text-[var(--accent-color)] hover:underline"
          >
            &larr; {t('chapter.backToSeries')}
          </Link>
          <h1 className="font-serif text-3xl text-[var(--text-primary)] font-bold capitalize mt-1">
            {contentTitle || slug.replace(/-/g, ' ')} <span className="italic text-[var(--accent-color)]">{t('content.chapters')}</span>
          </h1>
        </div>
      </div>

      {isLoading ? (
        <div className="flex flex-col gap-2">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-16 bg-[var(--bg-tertiary)] rounded-xl animate-pulse" />
          ))}
        </div>
      ) : chapters.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          {t('content.noChapters')}
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          {chapters.map((chap) => (
            <ChapterRow
              key={chap.id}
              chapter={chap}
              contentType={type as ContentType}
              contentSlug={slug}
            />
          ))}
        </div>
      )}
    </div>
  );
};
