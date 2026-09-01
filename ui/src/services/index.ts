import {
  IContentService,
  IAuthService,
  IUserService,
  IWalletService,
  IBlogService,
  ICommentService,
  IReportService,
} from './contracts';
import { MockContentService } from './mock/mockContentService';
import { MockAuthService } from './mock/mockAuthService';
import { MockUserService } from './mock/mockUserService';
import { MockWalletService } from './mock/mockWalletService';
import { MockBlogService } from './mock/mockBlogService';
import { MockCommentService } from './mock/mockCommentService';
import { MockReportService } from './mock/mockReportService';

export const contentService: IContentService = new MockContentService();
export const authService: IAuthService = new MockAuthService();
export const userService: IUserService = new MockUserService();
export const walletService: IWalletService = new MockWalletService();
export const blogService: IBlogService = new MockBlogService();
export const commentService: ICommentService = new MockCommentService();
export const reportService: IReportService = new MockReportService();

export * from './queueService';
