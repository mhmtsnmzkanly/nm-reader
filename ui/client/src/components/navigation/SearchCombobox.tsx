import React, { useState, useEffect, useRef } from 'react';
import { Search, X, Loader2, Star, BookOpen, ArrowRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { contentService } from '../../services';
import { SearchSuggestItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';

export const SearchCombobox: React.FC = () => {
  const { t } = usePreferences();
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<SearchSuggestItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const navigate = useNavigate();
  const wrapperRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  useEffect(() => {
    if (query.trim().length < 2) {
      setSuggestions([]);
      setIsOpen(false);
      setSelectedIndex(-1);
      return;
    }

    const timer = setTimeout(async () => {
      setIsLoading(true);
      const res = await contentService.searchSuggest(query.trim());
      if (res.status === 'success') {
        setSuggestions(res.data);
        setIsOpen(true);
        setSelectedIndex(-1);
      }
      setIsLoading(false);
    }, 180);

    return () => clearTimeout(timer);
  }, [query]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen) {
      if (e.key === 'ArrowDown' && suggestions.length > 0) {
        setIsOpen(true);
      }
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev < suggestions.length - 1 ? prev + 1 : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev > 0 ? prev - 1 : suggestions.length - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIndex >= 0 && suggestions[selectedIndex]) {
        const sel = suggestions[selectedIndex];
        navigate(`/${sel.type}/${sel.slug}`);
        setIsOpen(false);
        setQuery('');
      } else if (query.trim().length >= 2) {
        navigate(`/search?q=${encodeURIComponent(query.trim())}`);
        setIsOpen(false);
      }
    } else if (e.key === 'Escape') {
      setIsOpen(false);
    }
  };

  const handleSelect = (item: SearchSuggestItem) => {
    navigate(`/${item.type}/${item.slug}`);
    setQuery('');
    setIsOpen(false);
  };

  const handleViewAll = () => {
    if (query.trim()) {
      navigate(`/search?q=${encodeURIComponent(query.trim())}`);
      setIsOpen(false);
    }
  };

  return (
    <div ref={wrapperRef} className="relative w-full max-w-[110px] xs:max-w-[140px] sm:max-w-xs md:max-w-sm">
      <div className="relative flex items-center">
        <Search className="absolute left-2.5 sm:left-3 w-3.5 h-3.5 text-[var(--text-muted)] pointer-events-none" />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => query.trim().length >= 2 && suggestions.length > 0 && setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={t('common.search')}
          className="w-full pl-7 sm:pl-9 pr-6 sm:pr-8 py-1.5 sm:py-2 bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl text-xs font-medium focus:outline-hidden focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
        />
        {isLoading ? (
          <Loader2 className="absolute right-2.5 w-3.5 h-3.5 text-[var(--text-muted)] animate-spin" />
        ) : query ? (
          <button
            type="button"
            onClick={() => {
              setQuery('');
              setSuggestions([]);
              setIsOpen(false);
            }}
            className="absolute right-1.5 p-1.5 text-[var(--text-muted)] hover:text-[var(--text-primary)] min-w-[28px] min-h-[28px] flex items-center justify-center cursor-pointer"
          >
            <X className="w-3.5 h-3.5" />
          </button>
        ) : null}
      </div>

      {isOpen && (
        <div className="absolute right-0 sm:left-0 sm:right-auto top-full mt-2 w-[calc(100vw-32px)] max-w-[360px] sm:w-full bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] shadow-2xl overflow-hidden z-50 transition-colors">
          {/* Section Header */}
          <div className="p-2.5 text-[10px] uppercase tracking-[0.2em] font-semibold text-[var(--text-muted)] px-3 border-b border-[var(--border-color)] flex items-center justify-between">
            <span>{t('searchAutocomplete.quickSuggestions')}</span>
            <span className="font-mono text-[9px] text-[var(--text-muted)]">
              {suggestions.length} {t('common.info')}
            </span>
          </div>

          {/* Results List */}
          {suggestions.length === 0 ? (
            <div className="p-6 text-center text-xs text-[var(--text-muted)] font-mono">
              {t('searchAutocomplete.noSuggestions')}
            </div>
          ) : (
            <div className="max-h-80 overflow-y-auto divide-y divide-[var(--border-color)]/50">
              {suggestions.map((item, idx) => (
                <div
                  key={item.id}
                  onClick={() => handleSelect(item)}
                  onMouseEnter={() => setSelectedIndex(idx)}
                  className={`flex items-center gap-3 p-3 cursor-pointer transition-colors ${
                    idx === selectedIndex
                      ? 'bg-[var(--accent-light)] text-[var(--accent-color)]'
                      : 'hover:bg-[var(--bg-tertiary)] text-[var(--text-primary)]'
                  }`}
                >
                  <div className="w-10 h-14 rounded-lg overflow-hidden bg-[var(--bg-tertiary)] flex-shrink-0 border border-[var(--border-color)]">
                    {item.cover_image ? (
                      <img
                        src={item.cover_image}
                        alt={item.title}
                        referrerPolicy="no-referrer"
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <div className="w-full h-full bg-[var(--accent-color)] text-white font-bold flex items-center justify-center text-xs">
                        {item.title.substring(0, 1)}
                      </div>
                    )}
                  </div>

                  <div className="flex flex-col flex-grow min-w-0 gap-1">
                    <span className="text-xs font-semibold text-[var(--text-primary)] truncate font-serif">
                      {item.title}
                    </span>

                    <div className="flex items-center gap-2 text-[11px] font-mono text-[var(--text-muted)]">
                      <Badge variant="primary" size="sm">
                        {item.type}
                      </Badge>

                      {item.rating_avg !== undefined && (
                        <span className="flex items-center gap-0.5 text-amber-500 font-bold">
                          <Star className="w-3 h-3 fill-current" />
                          {item.rating_avg}
                        </span>
                      )}

                      {item.chapter_count !== undefined && (
                        <span className="flex items-center gap-0.5">
                          <BookOpen className="w-3 h-3" />
                          {item.chapter_count}
                        </span>
                      )}
                    </div>

                    {item.author && (
                      <span className="text-[10px] text-[var(--text-muted)] truncate">
                        {item.author}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Footer View All */}
          {query.trim().length >= 2 && (
            <button
              onClick={handleViewAll}
              className="w-full p-2.5 bg-[var(--bg-tertiary)] hover:bg-[var(--accent-color)] text-[var(--text-primary)] hover:text-white text-xs font-medium border-t border-[var(--border-color)] transition-colors flex items-center justify-center gap-1.5 cursor-pointer font-mono"
            >
              <span>{t('searchAutocomplete.viewAllResults', { query: query.trim(), count: suggestions.length })}</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          )}
        </div>
      )}
    </div>
  );
};
