export function formatRelativeTime(dateString: string, lang: 'tr' | 'en' = 'tr'): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return dateString;

  const now = new Date();
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  // Less than 60 seconds
  if (diffInSeconds < 60) {
    return lang === 'en' ? 'Just now' : 'Az önce';
  }

  const diffInMinutes = Math.floor(diffInSeconds / 60);
  if (diffInMinutes < 60) {
    if (lang === 'en') {
      return diffInMinutes === 1 ? '1 min ago' : `${diffInMinutes} mins ago`;
    }
    return `${diffInMinutes} dk önce`;
  }

  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) {
    if (lang === 'en') {
      return diffInHours === 1 ? '1 hour ago' : `${diffInHours} hours ago`;
    }
    return `${diffInHours} saat önce`;
  }

  const diffInDays = Math.floor(diffInHours / 24);
  if (diffInDays < 7) {
    if (lang === 'en') {
      return diffInDays === 1 ? '1 day ago' : `${diffInDays} days ago`;
    }
    return `${diffInDays} gün önce`;
  }

  const diffInWeeks = Math.floor(diffInDays / 7);
  if (diffInWeeks < 4) {
    if (lang === 'en') {
      return diffInWeeks === 1 ? '1 week ago' : `${diffInWeeks} weeks ago`;
    }
    return `${diffInWeeks} hafta önce`;
  }

  const diffInMonths = Math.floor(diffInDays / 30);
  if (diffInMonths < 12) {
    if (lang === 'en') {
      return diffInMonths === 1 ? '1 month ago' : `${diffInMonths} months ago`;
    }
    return `${diffInMonths} ay önce`;
  }

  const diffInYears = Math.floor(diffInDays / 365);
  if (lang === 'en') {
    return diffInYears === 1 ? '1 year ago' : `${diffInYears} years ago`;
  }
  return `${diffInYears} yıl önce`;
}

export function formatDate(
  dateString: string,
  lang: 'tr' | 'en' = 'tr',
  options?: Intl.DateTimeFormatOptions
): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return dateString;

  const locale = lang === 'en' ? 'en-US' : 'tr-TR';
  const defaultOptions: Intl.DateTimeFormatOptions = options || {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  };

  return date.toLocaleDateString(locale, defaultOptions);
}

export function formatDateTime(
  dateString: string,
  lang: 'tr' | 'en' = 'tr'
): string {
  return formatDate(dateString, lang, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
