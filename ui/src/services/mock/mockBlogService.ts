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
    let blogs = mockBlogs.filter((b) => b.approved === 1);

    if (tag && tag.trim()) {
      const lowerTag = tag.toLowerCase().trim();
      blogs = blogs.filter((b) =>
        b.tags?.some((t) => t.slug === lowerTag || t.name.toLowerCase() === lowerTag || t.id === lowerTag)
      );
    }

    if (sort === 'popular') {
      blogs = [...blogs].sort((a, b) => (b.stats?.views ?? b.views ?? 0) - (a.stats?.views ?? a.views ?? 0));
    } else {
      blogs = [...blogs].sort((a, b) => new Date(b.published_at || b.created_at).getTime() - new Date(a.published_at || a.created_at).getTime());
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
    const blog = mockBlogs.find((b) => b.slug === slug);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog post not found');
    }
    if (blog.approved !== 1) {
      const sc = scenarioManager.getScenario();
      if (sc === 'normal_guest' || blog.user_id !== 'u8k2m4qz') {
        return makeError(404, 'NOT_FOUND', 'Blog post not found');
      }
    }
    return makeSuccess(blog);
  }

  async toggleLikeBlog(slug: string): Promise<ApiResponse<{ liked: boolean; likes: number }>> {
    await delay();
    const blog = mockBlogs.find((b) => b.slug === slug);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog post not found');
    }

    const currentLiked = blog.user_state?.liked ?? false;
    const newLiked = !currentLiked;

    const currentLikes = blog.stats?.likes ?? blog.likes ?? blog.upvote_count ?? 0;
    const newLikes = newLiked ? currentLikes + 1 : Math.max(0, currentLikes - 1);

    if (!blog.stats) {
      blog.stats = { views: blog.views ?? 1, likes: newLikes, comments: blog.comments_count ?? 0 };
    } else {
      blog.stats.likes = newLikes;
    }
    if (!blog.user_state) {
      blog.user_state = { liked: newLiked };
    } else {
      blog.user_state.liked = newLiked;
    }
    blog.likes = newLikes;
    blog.upvote_count = newLikes;
    blog.my_vote = newLiked ? 1 : 0;

    return makeSuccess({ liked: newLiked, likes: newLikes });
  }

  async getRelatedBlogs(slug: string, limit = 3): Promise<ApiResponse<BlogSummary[]>> {
    await delay();
    const current = mockBlogs.find((b) => b.slug === slug);
    const approved = mockBlogs.filter((b) => b.approved === 1 && b.slug !== slug);

    if (!current) {
      return makeSuccess(approved.slice(0, limit));
    }

    // Try finding by matching tags
    const currentTagIds = new Set(current.tags?.map((t) => t.id) || []);
    const scored = approved.map((b) => {
      let score = 0;
      b.tags?.forEach((t) => {
        if (currentTagIds.has(t.id)) score += 2;
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

  async getUserBlogs(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<BlogSummary[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const myBlogs = mockBlogs.filter((b) => b.user_id === 'u8k2m4qz');
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = myBlogs.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = myBlogs.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async createBlog(
    title: string,
    body: string,
    tags: string[] = ['anime', 'manga'],
    excerpt?: string
  ): Promise<ApiResponse<BlogSummary>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (!title || !body) {
      return makeError(400, 'BAD_REQUEST', 'Title and body are required');
    }

    const slug = title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)+/g, '');

    const newBlog: BlogSummary = {
      id: 'blog-' + Date.now().toString(36),
      user_id: 'u8k2m4qz',
      title,
      slug,
      excerpt: excerpt || body.slice(0, 140) + '...',
      cover_image: 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800&auto=format&fit=crop&q=80',
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
        views: 1,
        likes: 0,
        comments: 0,
      },
      user_state: {
        liked: false,
      },
      body,
      approved: 1,
      created_at: new Date().toISOString(),
      author_username: 'deniz',
      upvote_count: 0,
      downvote_count: 0,
      my_vote: 0,
      views: 1,
      likes: 0,
      comments_count: 0,
    };

    mockBlogs.unshift(newBlog);
    return makeSuccess(newBlog);
  }

  async voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ vote: number; upvote_count: number; downvote_count: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const blog = mockBlogs.find((b) => b.slug === slug);
    if (!blog) {
      return makeError(404, 'NOT_FOUND', 'Blog not found');
    }

    blog.my_vote = vote;
    if (vote === 1) blog.upvote_count = (blog.upvote_count || 0) + 1;
    if (vote === -1) blog.downvote_count = (blog.downvote_count || 0) + 1;

    return makeSuccess({
      vote,
      upvote_count: blog.upvote_count || 0,
      downvote_count: blog.downvote_count || 0,
    });
  }
}
