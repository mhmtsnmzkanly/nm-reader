import { IReportService, ReportTargetType } from '../contracts';
import { ApiResponse } from '../../types/api';
import { scenarioManager } from '../../mocks/scenarios';

const delay = (ms = 200) => new Promise((res) => setTimeout(res, ms));

export class MockReportService implements IReportService {
  async createReport(payload: {
    target_type: ReportTargetType;
    target_id: string;
    reason: string;
    description?: string;
  }): Promise<ApiResponse<{ id: number; status: string }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return {
        status: 'error',
        data: null,
        meta: {},
        error: { code: 401, key: 'UNAUTHORIZED', message: 'Rapor göndermek için giriş yapmalısınız.', params: {} },
      };
    }

    return {
      status: 'success',
      data: {
        id: Math.floor(Math.random() * 10000) + 1,
        status: 'pending',
      },
      meta: {},
      error: null,
    };
  }
}
