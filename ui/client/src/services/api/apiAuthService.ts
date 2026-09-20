import { api } from '../apiClient';
import { ApiResponse, AuthPayload, UserSession } from '../../types/api';
import { IAuthService } from '../contracts';

export class ApiAuthService implements IAuthService {
  async login(
    identity: string,
    password: string,
    remember = false,
    turnstileToken = ''
  ): Promise<ApiResponse<AuthPayload>> {
    return api.post<AuthPayload>('/auth/login', {
      identity,
      email: identity,
      password,
      remember,
      turnstile_token: turnstileToken,
    });
  }

  async register(
    username: string,
    email: string,
    password: string,
    turnstileToken = ''
  ): Promise<ApiResponse<{ id: string; username: string; email: string; email_verified?: boolean }>> {
    return api.post<{ id: string; username: string; email: string; email_verified?: boolean }>(
      '/auth/register',
      { username, email, password, turnstile_token: turnstileToken }
    );
  }

  async refresh(): Promise<ApiResponse<AuthPayload>> {
    return api.post<AuthPayload>('/auth/refresh');
  }

  async logout(): Promise<ApiResponse<{ logged_out: boolean }>> {
    return api.post<{ logged_out: boolean }>('/auth/logout');
  }

  async getSessions(): Promise<ApiResponse<UserSession[]>> {
    return api.get<UserSession[]>('/auth/sessions');
  }

  async revokeSession(sessionId: string): Promise<ApiResponse<{ revoked: boolean }>> {
    return api.delete<{ revoked: boolean }>(`/auth/sessions/${sessionId}`);
  }

  async revokeOtherSessions(): Promise<ApiResponse<{ revoked_count: number }>> {
    return api.post<{ revoked_count: number }>('/auth/sessions/revoke-others');
  }

  async forgotPassword(email: string, turnstileToken = ''): Promise<ApiResponse<{ message: string }>> {
    return api.post<{ message: string }>('/auth/forgot-password', { email, turnstile_token: turnstileToken });
  }

  async resetPassword(
    token: string,
    password: string
  ): Promise<ApiResponse<{ id: string; message: string }>> {
    return api.post<{ id: string; message: string }>('/auth/reset-password', { token, password });
  }

  async verifyEmail(token: string): Promise<ApiResponse<{ id: string; email_verified: boolean }>> {
    return api.post<{ id: string; email_verified: boolean }>('/auth/verify-email', { token });
  }

  async resendVerificationEmail(): Promise<ApiResponse<{ message: string }>> {
    return api.post<{ message: string }>('/auth/verify-email/resend');
  }
}
