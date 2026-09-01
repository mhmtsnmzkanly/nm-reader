import {
  IContentService,
  IAuthService,
  IUserService,
  IWalletService,
  IBlogService,
  ICommentService,
  IReportService,
} from './contracts';

import { ApiContentService } from './api/apiContentService';
import { ApiAuthService } from './api/apiAuthService';
import { ApiUserService } from './api/apiUserService';
import { ApiWalletService } from './api/apiWalletService';
import { ApiBlogService } from './api/apiBlogService';
import { ApiCommentService } from './api/apiCommentService';
import { ApiReportService } from './api/apiReportService';

import { MockContentService } from './mock/mockContentService';
import { MockAuthService } from './mock/mockAuthService';
import { MockUserService } from './mock/mockUserService';
import { MockWalletService } from './mock/mockWalletService';
import { MockBlogService } from './mock/mockBlogService';
import { MockCommentService } from './mock/mockCommentService';
import { MockReportService } from './mock/mockReportService';

const useMock = typeof import.meta !== 'undefined' && import.meta.env?.VITE_USE_MOCK === 'true';

export const contentService: IContentService = useMock
  ? new MockContentService()
  : new ApiContentService();

export const authService: IAuthService = useMock
  ? new MockAuthService()
  : new ApiAuthService();

export const userService: IUserService = useMock
  ? new MockUserService()
  : new ApiUserService();

export const walletService: IWalletService = useMock
  ? new MockWalletService()
  : new ApiWalletService();

export const blogService: IBlogService = useMock
  ? new MockBlogService()
  : new ApiBlogService();

export const commentService: ICommentService = useMock
  ? new MockCommentService()
  : new ApiCommentService();

export const reportService: IReportService = useMock
  ? new MockReportService()
  : new ApiReportService();

export * from './queueService';
export * from './apiClient';
