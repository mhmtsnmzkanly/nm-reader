import { api } from '../apiClient';
import { ApiResponse } from '../../types/api';
import { IReportService, ReportTargetType } from '../contracts';

export class ApiReportService implements IReportService {
  async createReport(payload: {
    target_type: ReportTargetType;
    target_id: string;
    reason: string;
    description?: string;
  }): Promise<ApiResponse<{ id: number; status: string }>> {
    return api.post<{ id: number; status: string }>('/reports', payload);
  }
}
