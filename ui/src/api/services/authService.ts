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
}

export const authService = new ApiAuthService();
