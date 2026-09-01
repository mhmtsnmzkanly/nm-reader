import { IBlogService } from '../contracts';
import { ApiResponse, ApiSuccess, ApiError, BlogSummary } from '../../types/api';
import { mockBlogs } from '../../mocks/fixtures';
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

export class MockBlogService implements IBlogService {
  async getBlogs(
    page = 1,
    per_page = 9,
    sort: 'latest' | 'popular' = 'latest',
    tag?: string
  ): Promise<ApiResponse<BlogSummary[]>> {
    await delay();
    let blogs = mockBlogs.filter((b) => b.approved === 1 || b.status === 'published');

    if (tag && tag.trim()) {
      const lowerTag = tag.toLowerCase().trim();
      blogs = blogs.filter((b) =>
        b.tags?.some((t: any) => {
          const val = typeof t === 'string' ? t : t?.slug || t?.name || t?.id || '';
          return String(val).toLowerCase() === lowerTag;
        })
      );
    }

    if (sort === 'popular') {
      blogs = [...blogs].sort((a, b) => (b.stats?.views ?? b.views ?? 0) - (a.stats?.views ?? a.views ?? 0));
    } else {
      blogs = [...blogs].sort(
        (a, b) => new Date(b.published_at || b.created_at || '').getTime() - new Date(a.published_at || a.created_at || '').getTime()
      );
    }

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = blogs.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = blogs.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getBlogBySlug(slug: string): Promise<ApiResponse<BlogSummary>> {
    await delay();
    const blog = mockBlogs.find((b) => b.slug === slug || b.id === slug);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog post not found');
    }
    if (blog.approved !== 1 && blog.status !== 'published') {
      const sc = scenarioManager.getScenario();
      if (sc === 'normal_guest' || blog.user_id !== 'u8k2m4qz') {
        return makeError(404, 'NOT_FOUND', 'Blog post not found');
      }
    }
    return makeSuccess(blog);
  }

  async getRelatedBlogs(slug: string, limit = 3): Promise<ApiResponse<BlogSummary[]>> {
    await delay();
    const current = mockBlogs.find((b) => b.slug === slug || b.id === slug);
    const approved = mockBlogs.filter((b) => (b.approved === 1 || b.status === 'published') && b.slug !== slug && b.id !== slug);

    if (!current) {
      return makeSuccess(approved.slice(0, limit));
    }

    // Try finding by matching tags
    const currentTags = (current.tags || []).map((t) =>
      (typeof t === 'string' ? t : (t.slug || t.id || t.name)).toLowerCase()
    );
    const currentTagSet = new Set(currentTags);

    const scored = approved.map((b) => {
      let score = 0;
      b.tags?.forEach((t) => {
        const val = (typeof t === 'string' ? t : (t.slug || t.id || t.name)).toLowerCase();
        if (currentTagSet.has(val)) score += 2;
      });
      if (b.author?.id && current.author?.id && b.author.id === current.author.id) {
        score += 1;
      }
      return { blog: b, score };
    });

    scored.sort((a, b) => b.score - a.score);
    const result = scored.slice(0, limit).map((s) => s.blog);
    return makeSuccess(result);
  }

  async voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ vote: number; upvote_count: number; downvote_count: number; likes: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const blog = mockBlogs.find((b) => b.slug === slug || b.id === slug);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog not found');
    }

    const previousVote = blog.my_vote ?? 0;
    blog.my_vote = vote;

    if (previousVote === 1) {
      blog.upvote_count = Math.max(0, (blog.upvote_count || 1) - 1);
    } else if (previousVote === -1) {
      blog.downvote_count = Math.max(0, (blog.downvote_count || 1) - 1);
    }

    if (vote === 1) {
      blog.upvote_count = (blog.upvote_count || 0) + 1;
    } else if (vote === -1) {
      blog.downvote_count = (blog.downvote_count || 0) + 1;
    }

    const currentLikes = blog.upvote_count || 0;
    blog.likes = currentLikes;
    if (blog.stats) {
      blog.stats.likes = currentLikes;
    }
    if (blog.user_state) {
      blog.user_state.liked = vote === 1;
    }

    return makeSuccess({
      vote,
      upvote_count: blog.upvote_count || 0,
      downvote_count: blog.downvote_count || 0,
      likes: currentLikes,
    });
  }

  async uploadBlogImage(formData: FormData): Promise<ApiResponse<{ path: string; url: string }>> {
    await delay(300);
    const file = formData.get('image') || formData.get('file');
    const filename = file instanceof File ? file.name : `upload-${Date.now()}.jpg`;
    const mockUrl = 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800&auto=format&fit=crop&q=80';
    return makeSuccess({
      path: `/uploads/blogs/${filename}`,
      url: mockUrl,
    });
  }

  async getUserBlogs(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<BlogSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const myBlogs = mockBlogs.filter((b) => b.user_id === 'u8k2m4qz' || b.author?.id === 'u8k2m4qz');
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = myBlogs.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = myBlogs.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getMyBlog(id: string): Promise<ApiResponse<BlogSummary>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const blog = mockBlogs.find((b) => b.id === id || b.slug === id);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog post not found');
    }
    return makeSuccess(blog);
  }

  async createBlog(
    title: string,
    body: string,
    tags: string[] = ['anime', 'manga'],
    excerpt?: string,
    cover_image?: string,
    status: 'draft' | 'pending' = 'pending'
  ): Promise<ApiResponse<BlogSummary>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (!title || !body) {
      return makeError(400, 'BAD_REQUEST', 'Title and body are required');
    }

    const slugBase = title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)+/g, '');
    const slug = `${slugBase || 'blog'}-${Date.now().toString(36).slice(-4)}`;

    const newBlog: BlogSummary = {
      id: 'blog-' + Date.now().toString(36),
      user_id: 'u8k2m4qz',
      title,
      slug,
      excerpt: excerpt || body.slice(0, 140) + '...',
      cover_image: cover_image || 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800&auto=format&fit=crop&q=80',
      author: {
        id: 'u8k2m4qz',
        username: 'deniz',
        display_name: 'Deniz Yazar',
        avatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
      },
      published_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      read_time: Math.max(1, Math.ceil(body.split(/\s+/).length / 150)),
      tags: tags.map((t) => ({ id: t.toLowerCase(), name: t, slug: t.toLowerCase() })),
      content: body,
      stats: {
        views: 0,
        likes: 0,
        comments: 0,
      },
      user_state: {
        liked: false,
      },
      status: status || 'pending',
      approved: status === 'draft' ? 0 : 0,
      body,
      created_at: new Date().toISOString(),
      author_username: 'deniz',
      upvote_count: 0,
      downvote_count: 0,
      my_vote: 0,
      views: 0,
      likes: 0,
      comments_count: 0,
    };

    mockBlogs.unshift(newBlog);
    return makeSuccess(newBlog);
  }

  async updateBlog(
    id: string,
    payload: {
      title?: string;
      body?: string;
      tags?: string[];
      excerpt?: string;
      cover_image?: string;
      status?: 'draft' | 'pending';
    }
  ): Promise<ApiResponse<BlogSummary>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const blog = mockBlogs.find((b) => b.id === id || b.slug === id);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog not found');
    }

    if (payload.title !== undefined) {
      blog.title = payload.title;
      const slugBase = payload.title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');
      blog.slug = `${slugBase || 'blog'}-${blog.id.replace('blog-', '')}`;
    }
    if (payload.body !== undefined) {
      blog.body = payload.body;
      blog.content = payload.body;
      blog.read_time = Math.max(1, Math.ceil(payload.body.split(/\s+/).length / 150));
    }
    if (payload.excerpt !== undefined) {
      blog.excerpt = payload.excerpt;
    }
    if (payload.cover_image !== undefined) {
      blog.cover_image = payload.cover_image;
    }
    if (payload.tags !== undefined) {
      blog.tags = payload.tags.map((t) => ({ id: t.toLowerCase(), name: t, slug: t.toLowerCase() }));
    }
    if (payload.status !== undefined) {
      blog.status = payload.status;
      blog.approved = payload.status === 'draft' ? 0 : 0;
    }
    blog.updated_at = new Date().toISOString();

    return makeSuccess(blog);
  }

  async deleteBlog(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const index = mockBlogs.findIndex((b) => b.id === id || b.slug === id);
    if (index === -1) {
      return makeError(404, 'NOT_FOUND', 'Blog not found');
    }

    mockBlogs.splice(index, 1);
    return makeSuccess({ deleted: true });
  }
}

