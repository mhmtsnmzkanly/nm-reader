import { IAuthService } from '../contracts';
import {
  ApiResponse,
  ApiSuccess,
  ApiError,
  AuthPayload,
  UserSession,
} from '../../types/api';
import { mockSessions, mockUserProfile } from '../../mocks/fixtures';
import { scenarioManager } from '../../mocks/scenarios';

function makeSuccess<T>(data: T, meta: Record<string, unknown> = {}): ApiSuccess<T> {
  return { status: 'success', data, meta, error: null };
}

function makeError(code: number, key: string, message: string): ApiError {
  return {
    status: 'error',
    data: null,
    meta: {},
    error: { code, key, message, params: {} },
  };
}

const delay = (ms = 150) => new Promise((res) => setTimeout(res, ms));

export class MockAuthService implements IAuthService {
  async login(
    email: string,
    password: string,
    remember = false
  ): Promise<ApiResponse<AuthPayload>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    if (!email || !password) {
      return makeError(400, 'BAD_REQUEST', 'Email and password are required');
    }
    if (password.length < 8) {
      return makeError(400, 'BAD_REQUEST', 'Password must be at least 8 characters');
    }

    const payload: AuthPayload = {
      id: 'u8k2m4qz',
      username: 'deniz',
      email: email,
      email_verified: mockUserProfile.email_verified,
      csrf_token: '0123456789abcdef0123456789abcdef0123456789abcdef',
      refresh_token: remember ? 'opaque-refresh-token-998877' : null,
      api_token: 'opaque-api-token-112233',
      roles: ['user'],
      permissions: [],
    };

    scenarioManager.setScenario('normal_authenticated');
    return makeSuccess(payload);
  }

  async register(
    username: string,
    email: string,
    password: string
  ): Promise<ApiResponse<{ id: string; username: string; email: string; email_verified?: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    if (!username || !email || !password) {
      return makeError(400, 'BAD_REQUEST', 'All fields are required');
    }
    if (email === 'taken@example.test') {
      return makeError(409, 'CONFLICT', 'Email or username already in use');
    }

    return makeSuccess({
      id: 'u' + Math.random().toString(36).substring(2, 9),
      username,
      email,
      email_verified: false,
    });
  }

  async refresh(): Promise<ApiResponse<AuthPayload>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }
    if (sc === 'session_expired') {
      return makeError(401, 'UNAUTHORIZED', 'Session expired');
    }

    const payload: AuthPayload = {
      id: 'u8k2m4qz',
      username: 'deniz',
      email: 'deniz@example.test',
      email_verified: mockUserProfile.email_verified,
      csrf_token: 'refreshed-csrf-token-' + Date.now(),
      refresh_token: 'new-refresh-token-' + Date.now(),
      api_token: 'new-api-token-' + Date.now(),
      roles: ['user'],
      permissions: [],
    };
    return makeSuccess(payload);
  }

  async logout(): Promise<ApiResponse<{ logged_out: boolean }>> {
    await delay();
    scenarioManager.setScenario('normal_guest');
    return makeSuccess({ logged_out: true });
  }

  async getSessions(): Promise<ApiResponse<UserSession[]>> {
    await delay();
    return makeSuccess([...mockSessions]);
  }

  async revokeSession(
    sessionId: string
  ): Promise<ApiResponse<{ revoked: boolean }>> {
    await delay();
    const idx = mockSessions.findIndex((s) => s.id === sessionId);
    if (idx !== -1) {
      const target = mockSessions[idx];
      mockSessions.splice(idx, 1);
      if (target.is_current || target.current) {
        scenarioManager.setScenario('normal_guest');
      }
    }
    return makeSuccess({ revoked: true });
  }

  async revokeOtherSessions(): Promise<ApiResponse<{ revoked_count: number }>> {
    await delay();
    const currentSession = mockSessions.find((s) => s.is_current || s.current);
    const beforeCount = mockSessions.length;
    if (currentSession) {
      mockSessions.length = 0;
      mockSessions.push(currentSession);
    }
    return makeSuccess({ revoked_count: Math.max(0, beforeCount - mockSessions.length) });
  }

  // Yeni E-posta & Şifre İşlemleri
  async forgotPassword(email: string): Promise<ApiResponse<{ message: string }>> {
    await delay(300);
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    if (!email || !email.includes('@')) {
      return makeError(400, 'BAD_REQUEST', 'Geçerli bir e-posta adresi giriniz.');
    }

    return makeSuccess({
      message: 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.',
    });
  }

  async resetPassword(token: string, password: string): Promise<ApiResponse<{ id: string; message: string }>> {
    await delay(300);
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    if (!token || token === 'invalid' || token === 'expired') {
      return makeError(400, 'INVALID_TOKEN', 'Geçersiz veya süresi dolmuş sıfırlama bağlantısı.');
    }

    const hasMinLength = password.length >= 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);

    if (!hasMinLength || !hasUpperCase || !hasLowerCase || !hasNumber) {
      return makeError(
        400,
        'WEAK_PASSWORD',
        'Şifreniz en az 8 karakter uzunluğunda olmalı ve büyük harf, küçük harf ile rakam içermelidir.'
      );
    }

    return makeSuccess({
      id: 'u8k2m4qz',
      message: 'Şifreniz başarıyla güncellendi.',
    });
  }

  async verifyEmail(token: string): Promise<ApiResponse<{ id: string; email_verified: boolean }>> {
    await delay(400);
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    if (!token || token === 'invalid' || token === 'expired') {
      return makeError(400, 'INVALID_TOKEN', 'Doğrulama bağlantısı geçersiz veya süresi dolmuş.');
    }

    mockUserProfile.email_verified = true;

    return makeSuccess({
      id: 'u8k2m4qz',
      email_verified: true,
    });
  }

  async resendVerificationEmail(): Promise<ApiResponse<{ message: string }>> {
    await delay(300);
    const sc = scenarioManager.getScenario();
    if (sc === 'maintenance') {
      return makeError(503, 'SERVICE_UNAVAILABLE', 'Sistem bakım modundadır.');
    }

    return makeSuccess({
      message: 'Doğrulama e-postası başarıyla gönderildi.',
    });
  }
}
