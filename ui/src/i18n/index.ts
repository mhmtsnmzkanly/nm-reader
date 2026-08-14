import { tr } from './tr';
import { en } from './en';
import { AppLanguage } from '../types/domain';

export const dictionaries = {
  tr,
  en,
};

export function getTranslation(
  lang: AppLanguage,
  keyPath: string,
  params?: Record<string, string | number>
): string {
  const dict = dictionaries[lang] || dictionaries.tr;
  const parts = keyPath.split('.');
  
  let current: any = dict;
  for (const part of parts) {
    if (current && typeof current === 'object' && part in current) {
      current = current[part];
    } else {
      // Fallback to Turkish dictionary if missing in English
      let fallback: any = dictionaries.tr;
      for (const fbPart of parts) {
        if (fallback && typeof fallback === 'object' && fbPart in fallback) {
          fallback = fallback[fbPart];
        } else {
          return keyPath;
        }
      }
      current = fallback;
      break;
    }
  }

  if (typeof current !== 'string') {
    return keyPath;
  }

  let text = current;
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
    });
  }

  return text;
}
