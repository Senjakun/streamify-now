import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Play, ChevronLeft, ChevronRight, Filter, Tv } from "lucide-react";
import { fetchContent, getPosterUrl, formatRating, Content } from "@/services/api";

const GENRES = ["Action", "Adventure", "Animation", "Comedy", "Crime", "Documentary", "Drama", "Fantasy", "Horror", "Mystery", "Romance", "Sci-Fi", "Thriller", "War"];

const SkeletonCard = () => (
  <div>
    <div className="aspect-[2/3] rounded-lg bg-secondary animate-pulse" />
    <div className="mt-2 h-3 bg-secondary rounded animate-pulse w-4/5" />
    <div className="mt-1 h-3 bg-secondary rounded animate-pulse w-2/5" />
  </div>
);

const MoviesPage = () => {
  const [items, setItems] = useState<Content[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [contentKind, setContentKind] = useState<'movie' | 'tvshow'>('movie');
  const [genre, setGenre] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchContent({
      type: 'movie',
      genre: genre || undefined,
      page,
      limit: 24,
      order_by: 'updated_at',
    })
      .then((data) => {
        if (!cancelled) {
          // Filter by status for tvshow vs movie distinction (tvshow = ongoing)
          const normalized = data.content.map((item: any) => ({ ...item, rating: parseFloat(item.rating) || 0, genres: Array.isArray(item.genres) ? item.genres : [] }));
          const filtered = contentKind === 'tvshow'
            ? normalized.filter((c: any) => c.status === 'ongoing')
            : normalized.filter((c: any) => c.status === 'completed');
          setItems(filtered);
          setTotalPages(data.pagination.pages);
          setLoading(false);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err.message);
          setLoading(false);
        }
      });

    return () => { cancelled = true; };
  }, [page, genre, contentKind]);

  const handleKindChange = (val: 'movie' | 'tvshow') => {
    setContentKind(val);
    setPage(1);
  };

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <main className="pt-20 pb-12">
        <div className="container mx-auto px-4">

          {/* Header */}
          <div className="mb-6">
            <h1 className="text-3xl font-bold text-foreground mb-1">Movies & Series</h1>
            <p className="text-muted-foreground text-sm">Film box office & series populer dari Filmapik</p>
          </div>

          {/* Filters */}
          <div className="flex flex-wrap items-center gap-3 mb-6 p-4 rounded-xl bg-secondary/30 border border-border/50">
            <Filter className="w-4 h-4 text-muted-foreground flex-shrink-0" />

            {/* Movie / TV Show toggle */}
            <div className="flex gap-2">
              <button
                onClick={() => handleKindChange('movie')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                  contentKind === 'movie' ? 'bg-amber-500 text-black' : 'bg-secondary text-muted-foreground hover:bg-secondary/80'
                }`}
              >
                <Play className="w-3 h-3" /> Movie
              </button>
              <button
                onClick={() => handleKindChange('tvshow')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                  contentKind === 'tvshow' ? 'bg-amber-500 text-black' : 'bg-secondary text-muted-foreground hover:bg-secondary/80'
                }`}
              >
                <Tv className="w-3 h-3" /> TV Show
              </button>
            </div>

            <div className="w-px h-5 bg-border hidden sm:block" />

            {/* Genre */}
            <select
              value={genre}
              onChange={(e) => { setGenre(e.target.value); setPage(1); }}
              className="px-3 py-1.5 rounded-full text-xs font-medium bg-secondary text-foreground border border-border/50 outline-none cursor-pointer"
            >
              <option value="">Semua Genre</option>
              {GENRES.map((g) => <option key={g} value={g}>{g}</option>)}
            </select>
          </div>

          {/* Error */}
          {error && (
            <div className="text-center py-16 text-muted-foreground">
              <p className="text-4xl mb-4">⚠️</p>
              <p className="font-medium">Gagal memuat data movies</p>
              <p className="text-xs mt-1">{error}</p>
            </div>
          )}

          {/* Grid */}
          {!error && (
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
              {loading
                ? Array(24).fill(0).map((_, i) => <SkeletonCard key={i} />)
                : items.length === 0
                  ? (
                    <div className="col-span-full text-center py-16 text-muted-foreground">
                      <p className="text-4xl mb-4">📭</p>
                      <p>Belum ada {contentKind === 'movie' ? 'movie' : 'TV show'} tersedia</p>
                      <p className="text-xs mt-1">Jalankan scraper terlebih dahulu</p>
                    </div>
                  )
                  : items.map((movie) => (
                    <Link key={movie.id} to={`/movies/${movie.slug}`} className="group">
                      <div className="relative aspect-[2/3] rounded-lg overflow-hidden bg-secondary">
                        <img referrerPolicy="no-referrer"
                          src={getPosterUrl(movie.poster_url)}
                          alt={movie.title}
                          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                          loading="lazy"
                          onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }}
                        />
                        {/* Hover overlay */}
                        <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                          <div className="w-12 h-12 rounded-full bg-primary flex items-center justify-center">
                            <Play className="w-6 h-6 text-primary-foreground fill-current" />
                          </div>
                        </div>
                        {/* Rating */}
                        {movie.rating > 0 && (
                          <div className="absolute top-2 right-2 px-2 py-0.5 rounded bg-amber-500 text-black text-xs font-bold">
                            ★ {formatRating(movie.rating)}
                          </div>
                        )}
                        {/* Quality badge */}
                        {movie.quality && (
                          <div className="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-black/70 text-white text-[10px] font-semibold">
                            {movie.quality}
                          </div>
                        )}
                        {/* Year */}
                        {movie.year && (
                          <div className="absolute bottom-2 right-2 px-2 py-0.5 rounded bg-black/60 text-white text-[10px]">
                            {movie.year}
                          </div>
                        )}
                      </div>
                      <h3 className="mt-2 text-sm font-medium text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                        {movie.title}
                      </h3>
                      {movie.genres?.length > 0 && (
                        <p className="text-xs text-muted-foreground mt-0.5 truncate">{movie.genres[0]}</p>
                      )}
                    </Link>
                  ))
              }
            </div>
          )}

          {/* Pagination */}
          {!loading && !error && totalPages > 1 && (
            <div className="flex items-center justify-center gap-3 mt-10">
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="flex items-center gap-1 px-4 py-2 rounded-full text-sm font-medium bg-secondary disabled:opacity-40 hover:bg-secondary/80 transition-colors"
              >
                <ChevronLeft className="w-4 h-4" /> Sebelumnya
              </button>
              <span className="text-sm text-muted-foreground">Halaman {page} dari {totalPages}</span>
              <button
                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
                className="flex items-center gap-1 px-4 py-2 rounded-full text-sm font-medium bg-secondary disabled:opacity-40 hover:bg-secondary/80 transition-colors"
              >
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

export default MoviesPage;