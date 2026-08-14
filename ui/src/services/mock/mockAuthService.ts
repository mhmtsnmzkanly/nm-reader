import { IAuthService } from '../contracts';
import {
  ApiResponse,
  ApiSuccess,
  ApiError,
  AuthPayload,
  UserSession,
} from '../../types/api';
import { mockSessions } from '../../mocks/fixtures';
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
    remember: boolean
  ): Promise<ApiResponse<AuthPayload>> {
    await delay();
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
  ): Promise<ApiResponse<{ id: string; username: string; email: string }>> {
    await delay();
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
    });
  }

  async refresh(): Promise<ApiResponse<AuthPayload>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'session_expired') {
      return makeError(401, 'UNAUTHORIZED', 'Session expired');
    }

    const payload: AuthPayload = {
      id: 'u8k2m4qz',
      username: 'deniz',
      email: 'deniz@example.test',
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
    return makeSuccess(mockSessions);
  }

  async revokeSession(
    sessionId: string
  ): Promise<ApiResponse<{ revoked: boolean }>> {
    await delay();
    const idx = mockSessions.findIndex((s) => s.id === sessionId);
    if (idx !== -1) {
      const target = mockSessions[idx];
      mockSessions.splice(idx, 1);
      if (target.is_current) {
        scenarioManager.setScenario('normal_guest');
      }
    }
    return makeSuccess({ revoked: true });
  }
}
