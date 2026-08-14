import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '../ui/Button';

export type PaginationProps = {
  currentPage: number;
  totalPages?: number;
  total?: number;
  perPage?: number;
  hasNext?: boolean;
  hasPrev?: boolean;
  disabled?: boolean;
  onPageChange: (page: number) => void;
};

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  total,
  perPage = 20,
  hasNext = true,
  hasPrev = currentPage > 1,
  disabled = false,
  onPageChange,
}) => {
  const isPrevDisabled = disabled || !hasPrev || currentPage <= 1;
  const isNextDisabled =
    disabled || (typeof totalPages === 'number' && totalPages > 0 ? currentPage >= totalPages : !hasNext);

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 my-6 p-4 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)]">
      <div className="text-xs text-[var(--text-muted)] font-mono">
        Sayfa <span className="font-bold text-[var(--text-primary)]">{currentPage}</span>
        {typeof totalPages === 'number' && totalPages > 0 && (
          <> / <span className="font-bold text-[var(--text-primary)]">{totalPages}</span></>
        )}
        {typeof total === 'number' && (
          <span className="ml-2 text-[var(--text-muted)]">(Toplam {total} sonuç)</span>
        )}
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={isPrevDisabled}
          onClick={() => onPageChange(currentPage - 1)}
          className="cursor-pointer"
        >
          <ChevronLeft className="w-4 h-4 mr-1" />
          Önceki
        </Button>

        <Button
          variant="outline"
          size="sm"
          disabled={isNextDisabled}
          onClick={() => onPageChange(currentPage + 1)}
          className="cursor-pointer"
        >
          Sonraki
          <ChevronRight className="w-4 h-4 ml-1" />
        </Button>
      </div>
    </div>
  );
};
