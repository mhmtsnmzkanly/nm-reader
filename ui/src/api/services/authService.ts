import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type { AuthPayload, UserSession } from '../../types/api';
import type { IAuthService } from '../../services/contracts';

export class ApiAuthService implements IAuthService {
  public login(email: string, password: string, remember = false): Promise<ApiResponse<AuthPayload>> {
    return apiClient.post<AuthPayload>('/auth/login', {
      email,
      password,
      remember,
    }, { skipCsrf: true });
  }

  public register(username: string, email: string, password: string): Promise<ApiResponse<{ id: string; username: string; email: string }>> {
    return apiClient.post<{ id: string; username: string; email: string }>('/auth/register', {
      username,
      email,
      password,
    }, { skipCsrf: true });
  }

  public refresh(): Promise<ApiResponse<AuthPayload>> {
    return apiClient.post<AuthPayload>('/auth/refresh', {}, { skipCsrf: true });
  }

  public logout(): Promise<ApiResponse<{ logged_out: boolean }>> {
    return apiClient.post<{ logged_out: boolean }>('/auth/logout', {}, { skipCsrf: true });
  }

  public getSessions(): Promise<ApiResponse<UserSession[]>> {
    return apiClient.get<UserSession[]>('/auth/sessions');
  }

  public revokeSession(sessionId: string): Promise<ApiResponse<{ revoked: boolean }>> {
    return apiClient.delete<{ revoked: boolean }>(`/auth/sessions/${sessionId}`);
  }

  public revokeOtherSessions(): Promise<ApiResponse<{ revoked_count: number }>> {
    return apiClient.post<{ revoked_count: number }>('/auth/sessions/revoke-others');
  }

  public forgotPassword(email: string): Promise<ApiResponse<{ message: string }>> {
    return apiClient.post<{ message: string }>('/auth/forgot-password', { email }, { skipCsrf: true });
  }

  public resetPassword(token: string, password: string): Promise<ApiResponse<{ id: string; message: string }>> {
    return apiClient.post<{ id: string; message: string }>('/auth/reset-password', { token, password }, { skipCsrf: true });
  }

  public verifyEmail(token: string): Promise<ApiResponse<{ id: string; email_verified: boolean }>> {
    return apiClient.post<{ id: string; email_verified: boolean }>('/auth/verify-email', { token }, { skipCsrf: true });
  }

  public resendVerificationEmail(): Promise<ApiResponse<{ message: string }>> {
    return apiClient.post<{ message: string }>('/auth/verify-email/resend');
  }
}

export const authService = new ApiAuthService();
