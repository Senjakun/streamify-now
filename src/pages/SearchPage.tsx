import { useState, useEffect, useCallback } from "react";
import { useSearchParams, Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Search, Play, BookOpen, Film, ChevronLeft, ChevronRight } from "lucide-react";
import { searchContent, getPosterUrl, formatRating, getDetailRoute, Content, ContentType } from "@/services/api";

const SkeletonCard = () => (
  <div>
    <div className="aspect-[2/3] rounded-lg bg-secondary animate-pulse" />
    <div className="mt-2 h-3 bg-secondary rounded animate-pulse w-4/5" />
    <div className="mt-1 h-3 bg-secondary rounded animate-pulse w-2/5" />
  </div>
);

const TYPE_BADGE: Record<string, { label: string; color: string }> = {
  anime:   { label: 'Anime',   color: 'bg-red-500' },
  movie:   { label: 'Movie',   color: 'bg-amber-500' },
  manga:   { label: 'Manga',   color: 'bg-blue-500' },
  manhwa:  { label: 'Manhwa',  color: 'bg-purple-500' },
  manhua:  { label: 'Manhua',  color: 'bg-pink-500' },
  donghua: { label: 'Donghua', color: 'bg-cyan-500' },
  novel:   { label: 'Novel',   color: 'bg-green-500' },
  drama:   { label: 'Drama',   color: 'bg-violet-500' },
};

const SearchPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQuery = searchParams.get("q") || "";

  const [query, setQuery] = useState(initialQuery);
  const [submittedQuery, setSubmittedQuery] = useState(initialQuery);
  const [typeFilter, setTypeFilter] = useState<ContentType | ''>('');
  const [results, setResults] = useState<Content[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  const doSearch = useCallback(async (q: string, type: ContentType | '', p: number) => {
    if (q.length < 2) return;
    setLoading(true);
    setError(null);

    try {
      // DB search covers anime/donghua/manga. Add novel search (separate table).
      const dbPromise = searchContent(q, type || undefined, p);

      const fetchNovel = !type || type === 'novel';
      const novelPromise = fetchNovel
        ? fetch("/api/novel.php?action=list&search=" + encodeURIComponent(q) + "&limit=24")
            .then(r => r.json())
            .then(res => (res.data?.content || []).map((n: any) => ({
              id: 'novel_' + n.slug,
              slug: n.slug,
              title: n.title,
              poster_url: n.poster_url,
              type: 'novel',
              rating: parseFloat(n.rating) || 0,
              genres: Array.isArray(n.genres) ? n.genres : [],
              status: n.status || 'ongoing',
              created_at: '',
              updated_at: '',
            })))
            .catch(() => [])
        : Promise.resolve([]);

      const [dbData, novelItems] = await Promise.all([dbPromise, novelPromise]);

      // DB results — filter sesuai type jika dipilih
      const dbItems = dbData.content.map((item: any) => ({
        ...item,
        rating: parseFloat(item.rating) || 0,
        genres: Array.isArray(item.genres) ? item.genres : [],
      }));

      const existingSlugs = new Set(dbItems.map((i: any) => i.slug));
      const uniqueNovels = (novelItems as any[]).filter((n: any) => !existingSlugs.has(n.slug));

      const combined = type && type !== 'novel'
        ? dbItems
        : [...dbItems, ...uniqueNovels];

      setResults(combined);
      setTotalPages(dbData.pagination?.pages || 1);
      setTotal(combined.length > dbData.pagination?.total
        ? combined.length
        : dbData.pagination?.total || combined.length);
      setLoading(false);

    } catch (err: any) {
      setError(err.message);
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (submittedQuery.length >= 2) {
      doSearch(submittedQuery, typeFilter, page);
    } else {
      setResults([]);
      setTotal(0);
    }
  }, [submittedQuery, typeFilter, page, doSearch]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (query.trim().length < 2) return;
    setSubmittedQuery(query.trim());
    setPage(1);
    setSearchParams({ q: query.trim() });
  };

  const getIcon = (type: ContentType) => {
    if (type === 'anime') return <Play className="w-3 h-3" />;
    if (type === 'manga') return <BookOpen className="w-3 h-3" />;
    return <Film className="w-3 h-3" />;
  };

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <main className="pt-20 pb-12">
        <div className="container mx-auto px-4">

          <div className="mb-8">
            <h1 className="text-3xl font-bold text-foreground mb-4">Pencarian</h1>

            <form onSubmit={handleSubmit} className="flex gap-2 max-w-xl">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
                <Input
                  type="text"
                  placeholder="Cari anime, manga, movie..."
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  className="pl-10 h-12"
                  autoFocus
                />
              </div>
              <Button type="submit" className="h-12 px-6" disabled={query.trim().length < 2}>
                Cari
              </Button>
            </form>

            {submittedQuery && (
              <div className="flex items-center gap-2 mt-4 flex-wrap">
                <span className="text-xs text-muted-foreground">Filter:</span>
                {(['', 'anime', 'donghua', 'movie', 'manga'] as (ContentType | '')[]).map((t) => (
                  <button
                    key={t}
                    onClick={() => { setTypeFilter(t); setPage(1); }}
                    className={"flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all " + (
                      typeFilter === t
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-secondary text-muted-foreground hover:bg-secondary/80'
                    )}
                  >
                    {t ? getIcon(t as ContentType) : null}
                    {t === '' ? 'Semua' : t.charAt(0).toUpperCase() + t.slice(1)}
                  </button>
                ))}
              </div>
            )}
          </div>

          {submittedQuery && !loading && !error && (
            <p className="text-muted-foreground text-sm mb-6">
              {total > 0
                ? `Ditemukan ${total} hasil untuk "${submittedQuery}"`
                : `Tidak ada hasil untuk "${submittedQuery}"`}
            </p>
          )}

          {error && (
            <div className="text-center py-16 text-muted-foreground">
              <p className="text-4xl mb-4">⚠️</p>
              <p className="font-medium">Pencarian gagal</p>
              <p className="text-xs mt-1">{error}</p>
            </div>
          )}

          {!submittedQuery && !loading && (
            <div className="text-center py-16 text-muted-foreground">
              <Search className="w-12 h-12 mx-auto mb-4 opacity-30" />
              <p>Ketik minimal 2 karakter untuk mencari</p>
            </div>
          )}

          {!error && (submittedQuery || loading) && (
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
              {loading
                ? Array(12).fill(0).map((_, i) => <SkeletonCard key={i} />)
                : results.map((item) => {
                    const t = item.type || 'manga';
                    const badge = TYPE_BADGE[t] || { label: t, color: 'bg-zinc-500' };
                    return (
                      <Link key={String(item.id) + item.slug} to={getDetailRoute(item)} className="group">
                        <div className="relative aspect-[2/3] rounded-lg overflow-hidden bg-secondary">
                          <img referrerPolicy="no-referrer"
                            src={getPosterUrl(item.poster_url)}
                            alt={item.title}
                            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                            onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }}
                          />
                          <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <div className="w-12 h-12 rounded-full bg-primary flex items-center justify-center">
                              <Play className="w-6 h-6 text-primary-foreground fill-current" />
                            </div>
                          </div>
                          <div className={"absolute top-2 left-2 px-2 py-0.5 rounded text-white text-[10px] font-semibold " + badge.color}>
                            {badge.label}
                          </div>
                          {item.rating > 0 && (
                            <div className="absolute top-2 right-2 px-2 py-0.5 rounded bg-black/60 text-white text-xs font-semibold">
                              ★ {formatRating(item.rating)}
                            </div>
                          )}
                        </div>
                        <h3 className="mt-2 text-sm font-medium text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                          {item.title}
                        </h3>
                        {item.year && (
                          <p className="text-xs text-muted-foreground mt-0.5">{item.year}</p>
                        )}
                      </Link>
                    );
                  })
              }
            </div>
          )}

          {!loading && !error && totalPages > 1 && (
            <div className="flex items-center justify-center gap-3 mt-10">
              <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                className="flex items-center gap-1 px-4 py-2 rounded-full text-sm font-medium bg-secondary disabled:opacity-40 hover:bg-secondary/80 transition-colors">
                <ChevronLeft className="w-4 h-4" /> Sebelumnya
              </button>
              <span className="text-sm text-muted-foreground">Halaman {page} dari {totalPages}</span>
              <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages}
                className="flex items-center gap-1 px-4 py-2 rounded-full text-sm font-medium bg-secondary disabled:opacity-40 hover:bg-secondary/80 transition-colors">
                Selanjutnya <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          )}

        </div>
      </main>
      <Footer />
    </div>
  );
};

export default SearchPage;
