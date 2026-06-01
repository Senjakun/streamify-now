export interface ApiChapter {
  id: string | number;
  chapter_number: number;
  title: string;
  source_url?: string;
  created_at?: string;
}

export interface ApiNovel {
  id: string | number;
  slug: string;
  title: string;
  source: string;
  poster_url: string;
  author: string;
  status: string;
  genres: string[];
  rating: number;
  total_chapters: number;
  latest_chapter: number;
  country?: string;
  description?: string;
  chapters?: ApiChapter[];
}

export interface FrontChapter {
  id: string | number;
  number: number;
  title: string;
  slug: string;
  created_at?: string;
}

export interface FrontNovel {
  id: string | number;
  slug: string;
  title: string;
  source: string;
  poster_url: string;
  author: string;
  status: string;
  genres: string[];
  rating: number;
  total_chapters: number;
  latest_chapter: number;
  country: string;
  description?: string;
  chapters?: FrontChapter[];
}

export interface FrontChapterContent {
  number: number;
  title: string;
  content: string;
  novel_title?: string;
  novel_slug?: string;
  prev?: number | null;
  next?: number | null;
}

export interface FrontNovelListResult {
  content: FrontNovel[];
  page: number;
  pages: number;
  total: number;
}

function normalizeCountry(country?: string): string {
  return String(country || "jp").toUpperCase();
}

function normalizeStatus(status?: string): string {
  const s = String(status || "").toLowerCase();
  if (s === "completed" || s === "tamat") return "Tamat";
  if (s === "ongoing") return "Ongoing";
  return status || "Ongoing";
}

export function normalizeNovel(novel: ApiNovel): FrontNovel {
  return {
    id: novel.id,
    slug: novel.slug,
    title: novel.title,
    source: novel.source,
    poster_url: novel.poster_url,
    author: novel.author || "",
    status: normalizeStatus(novel.status),
    genres: Array.isArray(novel.genres) ? novel.genres : [],
    rating: Number(novel.rating || 0),
    total_chapters: Number(novel.total_chapters || 0),
    latest_chapter: Number(novel.latest_chapter || 0),
    country: normalizeCountry(novel.country),
    description: novel.description || "",
    chapters: Array.isArray(novel.chapters)
      ? novel.chapters.map((ch) => ({
          id: ch.id,
          number: Number(ch.chapter_number || 0),
          title: ch.title || `Chapter ${ch.chapter_number}`,
          slug: novel.slug,
          created_at: ch.created_at,
        }))
      : undefined,
  };
}

export async function fetchAllNovels(): Promise<FrontNovel[]> {
  const first = await fetch("/api/novel.php?action=list&page=1&limit=50");
  if (!first.ok) throw new Error("Network error");
  const firstJson = await first.json();
  if (!firstJson.success) throw new Error(firstJson.error || "Gagal memuat novel");

  const pages = Number(firstJson?.data?.pagination?.pages || 1);
  let rows: ApiNovel[] = Array.isArray(firstJson?.data?.content) ? firstJson.data.content : [];

  for (let p = 2; p <= pages; p++) {
    const res = await fetch(`/api/novel.php?action=list&page=${p}&limit=50`);
    if (!res.ok) continue;
    const json = await res.json();
    if (json?.success && Array.isArray(json?.data?.content)) {
      rows = rows.concat(json.data.content);
    }
  }

  const uniq = new Map<string, FrontNovel>();
  for (const row of rows) {
    const novel = normalizeNovel(row);
    uniq.set(novel.slug, novel);
  }

  return Array.from(uniq.values());
}

export async function fetchNovelListPage(params: {
  page?: number;
  limit?: number;
  search?: string;
  country?: string;
  status?: string;
  genre?: string;
  orderby?: string;
}): Promise<FrontNovelListResult> {
  const qs = new URLSearchParams();
  qs.set("action", "list");
  qs.set("page", String(params.page ?? 1));
  qs.set("limit", String(params.limit ?? 24));
  if (params.search) qs.set("search", params.search);
  if (params.country) qs.set("country", params.country);
  if (params.status) qs.set("status", params.status);
  if (params.genre) qs.set("genre", params.genre);
  if (params.orderby) qs.set("orderby", params.orderby);

  const res = await fetch("/api/novel.php?" + qs.toString());
  if (!res.ok) throw new Error("Network error");

  const json = await res.json();
  if (!json.success) throw new Error(json.error || "Gagal memuat novel");

  return {
    content: Array.isArray(json?.data?.content)
      ? json.data.content.map((item: ApiNovel) => normalizeNovel(item))
      : [],
    page: Number(json?.data?.pagination?.page || 1),
    pages: Number(json?.data?.pagination?.pages || 1),
    total: Number(json?.data?.pagination?.total || 0),
  };
}

export async function fetchNovelDetail(slug: string): Promise<FrontNovel> {
  const res = await fetch(`/api/novel.php?action=detail&slug=${encodeURIComponent(slug)}`);
  if (!res.ok) throw new Error("Not found");
  const json = await res.json();
  if (!json.success) throw new Error(json.error || "Not found");
  return normalizeNovel(json.data as ApiNovel);
}

export async function fetchChapterContent(slug: string, chapter: number): Promise<FrontChapterContent> {
  const res = await fetch(`/api/novel-chapter.php?novel=${encodeURIComponent(slug)}&chapter=${chapter}`);
  if (!res.ok) throw new Error("Chapter not found");
  const json = await res.json();
  if (!json.success) throw new Error(json.error || "Chapter not found");

  const data = json.data || {};
  return {
    number: Number(data.chapter_number || chapter),
    title: data.title || `Chapter ${chapter}`,
    content: data.content || "",
    novel_slug: slug,
    prev: data.prev ?? null,
    next: data.next ?? null,
  };
}
