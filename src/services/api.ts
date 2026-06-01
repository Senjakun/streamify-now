/**
 * API Service Layer
 * Menghubungkan React frontend ke PHP backend di VPS
 * 
 * Set VITE_API_URL di file .env:
 * VITE_API_URL=https://yourdomain.com/backend-vps/api
 */

const API_BASE = import.meta.env.VITE_API_URL || '/backend-vps/api';

// ============================================================
// TYPES
// ============================================================

export type ContentType = 'anime' | 'movie' | 'manga';
export type ContentStatus = 'ongoing' | 'completed' | 'upcoming';

export interface Content {
  id: number;
  slug: string;
  title: string;
  title_alt?: string;
  description?: string;
  type: ContentType;
  status: ContentStatus;
  poster_url?: string;
  banner_url?: string;
  rating: number;
  year?: number;
  genres: string[];
  source_url?: string;
  studio?: string;
  author?: string;
  duration?: string;
  quality?: string;
  created_at: string;
  updated_at: string;
}

export interface Episode {
  id: number;
  content_id: number;
  episode_number: number;
  season_number?: number;
  title?: string;
  thumbnail_url?: string;
  video_url?: string;
  source_url?: string;
  duration?: number;
}

export interface Chapter {
  id: number;
  content_id: number;
  chapter_number: number;
  title?: string;
  images: string[];
  source_url?: string;
  created_at: string;
}

export interface ContentDetail extends Content {
  episodes?: Episode[];
  chapters?: Chapter[];
}

export interface Pagination {
  page: number;
  limit: number;
  total: number;
  pages: number;
}

export interface ContentListResponse {
  content: Content[];
  pagination: Pagination;
}

export interface TrendingResponse {
  trending: Content[];
}

export interface SearchResponse {
  content: Content[];
  query: string;
  pagination: Pagination;
}

// ============================================================
// CORE FETCHER
// ============================================================

async function apiFetch<T>(endpoint: string, params: Record<string, string | number | undefined> = {}): Promise<T> {
  // Build query string, filter out undefined values
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.append(key, String(value));
    }
  });

  const url = `${API_BASE}/${endpoint}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

  const response = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(error.error || `HTTP ${response.status}`);
  }

  return response.json();
}

// ============================================================
// CONTENT API
// ============================================================

/**
 * Ambil daftar konten (anime / movie / manga)
 */
export async function fetchContent(params: {
  type?: ContentType;
  status?: ContentStatus;
  genre?: string;
  year?: number;
  page?: number;
  limit?: number;
  order_by?: 'updated_at' | 'rating' | 'title' | 'year';
  order_dir?: 'ASC' | 'DESC';
} = {}): Promise<ContentListResponse> {
  return apiFetch<ContentListResponse>('content.php', {
    action: 'list',
    ...params,
  });
}

/**
 * Ambil detail konten berdasarkan slug
 */
export async function fetchDetail(slug: string): Promise<ContentDetail> {
  return apiFetch<ContentDetail>('content.php', {
    action: 'detail',
    slug,
  });
}

/**
 * Ambil detail konten berdasarkan ID
 */
export async function fetchDetailById(id: number): Promise<ContentDetail> {
  return apiFetch<ContentDetail>('content.php', {
    action: 'detail',
    id,
  });
}

/**
 * Ambil konten trending
 */
export async function fetchTrending(type?: ContentType, limit = 10): Promise<Content[]> {
  const data = await apiFetch<TrendingResponse>('content.php', {
    action: 'trending',
    type,
    limit,
  });
  return data.trending;
}

/**
 * Cari konten
 */
export async function searchContent(query: string, type?: ContentType, page = 1): Promise<SearchResponse> {
  return apiFetch<SearchResponse>('content.php', {
    action: 'search',
    q: query,
    type,
    page,
  });
}

// ============================================================
// COMMENTS API
// ============================================================

export interface Comment {
  id: number;
  user_id: number;
  content_id: number;
  episode_id?: number;
  chapter_id?: number;
  parent_id?: number;
  comment_text: string;
  likes_count: number;
  is_spoiler: boolean;
  created_at: string;
  username?: string;
  avatar_url?: string;
  rank_label?: string;
  replies?: Comment[];
}

export async function fetchComments(params: {
  content_id: number;
  episode_id?: number;
  chapter_id?: number;
  page?: number;
}): Promise<{ comments: Comment[]; pagination: Pagination }> {
  return apiFetch('comments.php', { action: 'list', ...params });
}

export async function postComment(data: {
  content_id: number;
  comment_text: string;
  episode_id?: number;
  chapter_id?: number;
  parent_id?: number;
  is_spoiler?: boolean;
  token: string;
}): Promise<{ comment: Comment }> {
  const response = await fetch(`${API_BASE}/comments.php?action=create`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${data.token}`,
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Gagal posting komentar' }));
    throw new Error(error.error || `HTTP ${response.status}`);
  }

  return response.json();
}

export async function likeComment(commentId: number, token: string): Promise<{ likes_count: number }> {
  const response = await fetch(`${API_BASE}/comments.php?action=like`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ comment_id: commentId }),
  });

  if (!response.ok) throw new Error('Gagal like komentar');
  return response.json();
}

// ============================================================
// AUTH API
// ============================================================

export interface AuthUser {
  id: number;
  username: string;
  email: string;
  avatar_url?: string;
  rank_label: string;
  role: string;
}

export async function login(email: string, password: string): Promise<{ token: string; user: AuthUser }> {
  const response = await fetch(`${API_BASE}/auth.php?action=login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Login gagal' }));
    throw new Error(error.error || 'Login gagal');
  }

  return response.json();
}

export async function register(data: {
  username: string;
  email: string;
  password: string;
}): Promise<{ token: string; user: AuthUser }> {
  const response = await fetch(`${API_BASE}/auth.php?action=register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Registrasi gagal' }));
    throw new Error(error.error || 'Registrasi gagal');
  }

  return response.json();
}

// ============================================================
// BOOKMARKS API
// ============================================================

export async function fetchBookmarks(token: string): Promise<{ bookmarks: Content[] }> {
  const response = await fetch(`${API_BASE}/bookmarks.php?action=list`, {
    headers: { 'Authorization': `Bearer ${token}` },
  });
  if (!response.ok) throw new Error('Gagal mengambil bookmark');
  return response.json();
}

export async function toggleBookmark(contentId: number, token: string): Promise<{ bookmarked: boolean }> {
  const response = await fetch(`${API_BASE}/bookmarks.php?action=toggle`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ content_id: contentId }),
  });
  if (!response.ok) throw new Error('Gagal toggle bookmark');
  return response.json();
}

// ============================================================
// HELPERS
// ============================================================

/**
 * Format poster URL - direct load, no proxy
 */
export function getPosterUrl(url?: string): string {
  if (!url) return '/placeholder.svg';
  return url;
}

/**
 * Format rating display
 */
export function formatRating(rating: any): string {
  let num = parseFloat(rating);
  if (isNaN(num) || num <= 0) return "N/A";
  if (num > 10) num = num / 10; // some sources use 0-100 scale
  if (num > 10) num = 10;
  return num.toFixed(1);
}

/**
 * Get content detail route
 */
export function getDetailRoute(content: Content): string {
  switch (content.type) {
    case 'anime': return `/anime/${content.slug}`;
    case 'donghua': return `/donghua/${content.slug}`;
    case 'manga': return `/manga/${content.slug}`;
    case 'novel': return `/novel/${content.slug}`;
    default: return '/';
  }
}
// ── PATCH: normalize data from API ──
export function normalizeContent(item: any): Content {
  return {
    ...item,
    rating: parseFloat(item.rating) || 0,
    genres: Array.isArray(item.genres) ? item.genres : [],
  };
}

export async function fetchList(type: string, params: { limit?: number; sort?: string; page?: number } = {}): Promise<Content[]> {
  const data = await fetchContent({ type: type as ContentType, limit: params.limit || 12, page: params.page || 1, order_by: (params.sort as any) || 'updated_at', order_dir: 'DESC' });
  return data.content;
}

// ── History API ──────────────────────────────────────────────
export async function saveHistory(data: {
  content_id: number;
  episode_id?: number;
  chapter_id?: number;
}): Promise<void> {
  const token = localStorage.getItem('token');
  if (!token) return;
  fetch(`${API_BASE}/history.php?action=add`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify(data),
  }).catch(() => {});
}

export async function fetchHistory(limit = 12): Promise<any[]> {
  const token = localStorage.getItem('token');
  if (!token) return [];
  const r = await fetch(`${API_BASE}/history.php?action=list&limit=${limit}`, {
    headers: { 'Authorization': `Bearer ${token}` },
  });
  const d = await r.json();
  return d.history || [];
}
