export interface Chapter {
  id: string | number;
  number: number;
  title: string;
  slug: string;
  created_at?: string;
}

export interface Novel {
  id: string | number;
  slug: string;
  title: string;
  source: string;
  poster_url: string;
  author: string;
  status: "Ongoing" | "Tamat" | string;
  genres: string[];
  rating: number;
  total_chapters: number;
  latest_chapter: number;
  country: "JP" | "CN" | "KR" | string;
  description?: string;
  chapters?: Chapter[];
}

export interface ChapterContent {
  number: number;
  title: string;
  content: string;
  novel_title?: string;
  novel_slug?: string;
}

export type CountryFilter = "ALL" | "JP" | "CN" | "KR";
export type StatusFilter = "ALL" | "Ongoing" | "Tamat";
export type SortOption = "terbaru" | "rating" | "chapter" | "az";
export type ReaderTheme = "dark" | "light" | "sepia";
export type FontSize = "sm" | "md" | "lg" | "xl";
