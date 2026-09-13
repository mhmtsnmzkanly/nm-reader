import type {
  IAuthService,
  IBlogService,
  ICommentService,
  IContentService,
  IReportService,
  IUserService,
  IWalletService,
} from './contracts';
import { ApiAuthService } from './api/apiAuthService';
import { ApiBlogService } from './api/apiBlogService';
import { ApiCommentService } from './api/apiCommentService';
import { ApiContentService } from './api/apiContentService';
import { ApiReportService } from './api/apiReportService';
import { ApiUserService } from './api/apiUserService';
import { ApiWalletService } from './api/apiWalletService';

export const contentService: IContentService = new ApiContentService();
export const authService: IAuthService = new ApiAuthService();
export const userService: IUserService = new ApiUserService();
export const walletService: IWalletService = new ApiWalletService();
export const blogService: IBlogService = new ApiBlogService();
export const commentService: ICommentService = new ApiCommentService();
export const reportService: IReportService = new ApiReportService();
