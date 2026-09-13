import type { ApiErrorDetail } from './types';

/**
 * Standardized API Client Error for NM-Reader.
 * Preserves the exact backend error code, key, message, and field validation parameters.
 */
export class ApiClientError extends Error {
  public readonly status: number;
  public readonly code: number;
  public readonly key: string;
  public readonly fields?: Record<string, string[] | string>;
  public readonly rawError?: ApiErrorDetail;

  constructor(status: number, errorDetail: Partial<ApiErrorDetail> = {}) {
    const message = errorDetail.message || `API Request Failed with status ${status}`;
    super(message);
    this.name = 'ApiClientError';
    this.status = status;
    this.code = errorDetail.code ?? status;
    this.key = errorDetail.key ?? 'UNKNOWN_ERROR';
    this.fields = errorDetail.fields;
    this.rawError = errorDetail as ApiErrorDetail;

    // Maintain prototype chain
    Object.setPrototypeOf(this, ApiClientError.prototype);
  }

  get isUnauthorized(): boolean {
    return this.status === 401 || this.key === 'UNAUTHORIZED';
  }

  get isForbidden(): boolean {
    return this.status === 403 || this.key === 'FORBIDDEN';
  }

  get isCsrfError(): boolean {
    return this.status === 419 || this.key === 'CSRF_ERROR' || this.key === 'INVALID_CSRF_TOKEN';
  }

  get isValidationError(): boolean {
    return this.status === 422 || this.key === 'VALIDATION_FAILED' || this.key === 'BAD_REQUEST';
  }

  get isRateLimited(): boolean {
    return this.status === 429 || this.key === 'RATE_LIMITED';
  }

  get isNotFound(): boolean {
    return this.status === 404 || this.key === 'NOT_FOUND';
  }

  get isServerError(): boolean {
    return this.status >= 500;
  }
}

export class NetworkError extends Error {
  constructor(message = 'Network connection failed. Please check your internet.') {
    super(message);
    this.name = 'NetworkError';
    Object.setPrototypeOf(this, NetworkError.prototype);
  }
}

export class TimeoutError extends Error {
  constructor(message = 'Request timed out.') {
    super(message);
    this.name = 'TimeoutError';
    Object.setPrototypeOf(this, TimeoutError.prototype);
  }
}
