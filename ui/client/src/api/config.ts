export interface ApiClientConfig {
  baseUrl: string;
  timeoutMs: number;
  defaultHeaders: Record<string, string>;
  withCredentials: boolean;
}

const DEFAULT_CONFIG: ApiClientConfig = {
  baseUrl: (typeof import.meta !== 'undefined' && (import.meta as any).env?.VITE_API_BASE_URL) || '/api/v1',
  timeoutMs: 15000,
  defaultHeaders: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true,
};

let currentConfig: ApiClientConfig = { ...DEFAULT_CONFIG };

export function getApiConfig(): ApiClientConfig {
  return currentConfig;
}

export function configureApi(options: Partial<ApiClientConfig>): void {
  currentConfig = {
    ...currentConfig,
    ...options,
    defaultHeaders: {
      ...currentConfig.defaultHeaders,
      ...options.defaultHeaders,
    },
  };
}

export function resetApiConfig(): void {
  currentConfig = { ...DEFAULT_CONFIG };
}
