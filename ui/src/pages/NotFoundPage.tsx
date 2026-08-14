import React from 'react';
import { Link } from 'react-router-dom';
import { Compass, Home, Search, BookOpen } from 'lucide-react';
import { Button } from '../components/ui/Button';

export const NotFoundPage: React.FC = () => {
  return (
    <div className="max-w-3xl mx-auto px-4 py-16 sm:py-24 flex flex-col items-center justify-center text-center">
      <div className="w-20 h-20 rounded-2xl bg-[var(--accent-color)]/10 text-[var(--accent-color)] flex items-center justify-center mb-6 border border-[var(--accent-color)]/20 shadow-inner">
        <Compass className="w-10 h-10 animate-pulse" />
      </div>

      <span className="text-xs font-mono font-bold uppercase tracking-widest text-[var(--accent-color)] mb-2">
        Hata 404
      </span>

      <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-[var(--text-primary)] mb-4">
        Sayfa <span className="italic text-[var(--accent-color)]">Bulunamadı</span>
      </h1>

      <p className="text-sm sm:text-base text-[var(--text-secondary)] font-light max-w-md mb-8 leading-relaxed">
        Aradığınız sayfa silinmiş, adı değiştirilmiş veya geçici olarak kullanım dışı kalmış olabilir.
      </p>

      <div className="flex flex-wrap items-center justify-center gap-3">
        <Link to="/">
          <Button variant="gold" size="md" className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer">
            <Home className="w-4 h-4" />
            <span>Ana Sayfaya Dön</span>
          </Button>
        </Link>

        <Link to="/browse">
          <Button variant="outline" size="md" className="gap-2 cursor-pointer">
            <BookOpen className="w-4 h-4" />
            <span>Kataloğu İncele</span>
          </Button>
        </Link>

        <Link to="/search">
          <Button variant="ghost" size="md" className="gap-2 cursor-pointer">
            <Search className="w-4 h-4" />
            <span>Arama Yap</span>
          </Button>
        </Link>
      </div>
    </div>
  );
};
