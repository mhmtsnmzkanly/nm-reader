import {
  IContentService,
  IAuthService,
  IUserService,
  IWalletService,
  IBlogService,
  ICommentService,
} from './contracts';

import {
  authService as realAuthService,
  contentService as realContentService,
  userService as realUserService,
  walletService as realWalletService,
  blogService as realBlogService,
  commentService as realCommentService,
} from '../api';

import { MockContentService } from './mock/mockContentService';
import { MockAuthService } from './mock/mockAuthService';
import { MockUserService } from './mock/mockUserService';
import { MockWalletService } from './mock/mockWalletService';
import { MockBlogService } from './mock/mockBlogService';
import { MockCommentService } from './mock/mockCommentService';

const useMock = typeof import.meta !== 'undefined' && (import.meta as any).env?.VITE_USE_MOCK === 'true';

export const contentService: IContentService = useMock ? new MockContentService() : realContentService;
export const authService: IAuthService = useMock ? new MockAuthService() : realAuthService;
export const userService: IUserService = useMock ? new MockUserService() : realUserService;
export const walletService: IWalletService = useMock ? new MockWalletService() : realWalletService;
export const blogService: IBlogService = useMock ? new MockBlogService() : realBlogService;
export const commentService: ICommentService = useMock ? new MockCommentService() : realCommentService;

export * from './contracts';
