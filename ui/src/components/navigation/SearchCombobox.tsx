import React, { useState, useEffect, useRef } from 'react';
import { Search, X, Loader2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { contentService } from '../../services';
import { SearchSuggestItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';

export const SearchCombobox: React.FC = () => {
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<SearchSuggestItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const navigate = useNavigate();
  const wrapperRef = useRef<HTMLDivElement>(null);
  const { t } = usePreferences();

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
      return;
    }

    const timer = setTimeout(async () => {
      setIsLoading(true);
      const res = await contentService.searchSuggest(query);
      if (res.status === 'success') {
        setSuggestions(res.data);
        setIsOpen(true);
      }
      setIsLoading(false);
    }, 200);

    return () => clearTimeout(timer);
  }, [query]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
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

  return (
    <div ref={wrapperRef} className="relative w-full max-w-[130px] xs:max-w-[170px] sm:max-w-xs md:max-w-sm">
      <div className="relative flex items-center">
        <Search className="absolute left-3 w-3.5 h-3.5 text-[var(--text-muted)] pointer-events-none" />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => query.trim().length >= 2 && setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder="Ara..."
          className="w-full pl-8 sm:pl-9 pr-7 sm:pr-8 py-2 bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl text-xs font-medium focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all sm:placeholder:content-['Seri,_yazar_veya_etiket_ara...']"
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

      {isOpen && suggestions.length > 0 && (
        <div className="absolute right-0 sm:left-0 sm:right-auto top-full mt-2 w-[calc(100vw-32px)] max-w-[340px] sm:w-full bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] shadow-2xl overflow-hidden z-50 max-h-80 overflow-y-auto transition-colors">
          <div className="p-2 text-[10px] uppercase tracking-[0.2em] font-semibold text-[var(--text-muted)] px-3 border-b border-[var(--border-color)]">
            {t('search.suggestion')}
          </div>
          {suggestions.map((item, idx) => (
            <div
              key={item.id}
              onClick={() => handleSelect(item)}
              className={`flex items-center gap-3 p-2.5 cursor-pointer transition-colors ${
                idx === selectedIndex
                  ? 'bg-[var(--accent-light)] text-[var(--accent-color)]'
                  : 'hover:bg-[var(--bg-tertiary)] text-[var(--text-primary)]'
              }`}
            >
              <div className="w-8 h-10 rounded overflow-hidden bg-[var(--bg-tertiary)] flex-shrink-0 border border-[var(--border-color)]">
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
              <div className="flex flex-col flex-grow min-w-0">
                <span className="text-xs font-medium text-[var(--text-primary)] truncate">
                  {item.title}
                </span>
                <div className="flex items-center gap-1 mt-0.5">
                  <Badge variant="primary" size="sm">
                    {item.type}
                  </Badge>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
