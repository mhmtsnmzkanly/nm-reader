/**
 * Canonical API Response Envelope & Protocol Types for NM-Reader.
 * Corresponds to docs/api/API_CONTRACT.md and docs/api/openapi.json
 */

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

export interface OffsetPaginationMeta {
  type: 'offset';
  page: number;
  per_page: number;
  total?: number;
  total_pages?: number;
  has_more?: boolean;
}

export interface CursorPaginationMeta {
  type: 'cursor';
  next_cursor: string | null;
  prev_cursor?: string | null;
  limit: number;
  has_more?: boolean;
}

export type PaginationMeta = {
  type?: 'offset' | 'cursor';
  page: number;
  per_page: number;
  total?: number;
  total_pages?: number;
  next_cursor?: string | null;
  prev_cursor?: string | null;
  limit?: number;
  has_more?: boolean;
  q?: string;
  filters?: Record<string, unknown>;
  [key: string]: unknown;
};

export interface ApiMeta {
  pagination?: PaginationMeta;
  timestamp?: number | string;
  server_time?: string;
  version?: string;
  page?: number;
  per_page?: number;
  total?: number;
  total_pages?: number;
  next_cursor?: string | null;
  [key: string]: unknown;
}

export interface ApiErrorDetail {
  code: number;
  key: string;
  message: string;
  params: Record<string, unknown>;
  fields?: Record<string, string[] | string>;
}

export interface ApiSuccess<T> {
  status: 'success';
  data: T;
  meta: ApiMeta | PaginationMeta | Record<string, unknown>;
  error: null;
}

export interface ApiError {
  status: 'error';
  data: null;
  meta: ApiMeta | Record<string, unknown>;
  error: ApiErrorDetail;
}

export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  params?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  timeout?: number;
  skipCsrf?: boolean;
  skipAuthRetry?: boolean;
}
