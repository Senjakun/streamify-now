import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { TrendingUp, ChevronRight, Play, BookOpen, Film } from "lucide-react";
import { fetchTrending, getPosterUrl, formatRating, getDetailRoute, Content, ContentType } from "@/services/api";

// ── Skeleton loader ──────────────────────────────────────────
const SkeletonCard = () => (
  <div className="flex-shrink-0 w-[140px] md:w-[160px]">
    <div className="aspect-[2/3] rounded-lg bg-secondary animate-pulse" />
    <div className="mt-2 h-3 bg-secondary rounded animate-pulse w-4/5" />
    <div className="mt-1 h-3 bg-secondary rounded animate-pulse w-3/5" />
  </div>
);

type TabType = ContentType;

const TrendingSection = () => {
  const [activeTab, setActiveTab] = useState<TabType>('anime');
  const [items, setItems] = useState<Content[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchTrending(activeTab, 12)
      .then((data) => {
        if (!cancelled) {
          setItems(data.map((item: any) => ({ ...item, rating: parseFloat(item.rating) || 0, genres: Array.isArray(item.genres) ? item.genres : [] })));
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
  }, [activeTab]);

  const getTabIcon = (tab: TabType) => {
    switch (tab) {
      case 'anime': return <Play className="w-4 h-4" />;
      case 'manga': return <BookOpen className="w-4 h-4" />;
      case 'movie': return <Film className="w-4 h-4" />;
    }
  };

  const getTabColor = (tab: TabType) => {
    switch (tab) {
      case 'anime': return 'bg-red-500';
      case 'manga': return 'bg-orange-500';
      case 'movie': return 'bg-amber-500';
    }
  };

  const getSubLabel = (item: Content) => {
    if (item.type === 'anime') return item.status === 'ongoing' ? 'Ongoing' : 'Completed';
    if (item.type === 'manga') return item.status === 'ongoing' ? 'Ongoing' : 'Tamat';
    return item.year ? String(item.year) : '';
  };

  return (
    <section className="py-8">
      <div className="container mx-auto px-4">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
          <div className="flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-primary" />
            <div>
              <h2 className="text-xl font-bold text-foreground">Trending Now</h2>
              <p className="text-xs text-muted-foreground">Konten paling populer minggu ini</p>
            </div>
          </div>

          {/* Tabs */}
          <div className="flex items-center gap-2">
            {(['anime', 'manga', 'movie'] as TabType[]).map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-full transition-all ${
                  activeTab === tab
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-secondary/50 text-muted-foreground hover:bg-secondary'
                }`}
              >
                {getTabIcon(tab)}
                <span className="capitalize">{tab}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Error state */}
        {error && (
          <div className="text-center py-8 text-muted-foreground text-sm">
            <p>⚠️ Gagal memuat data: {error}</p>
            <p className="text-xs mt-1">Pastikan backend VPS sudah berjalan</p>
          </div>
        )}

        {/* Cards */}
        {!error && (
          <div className="relative -mx-4 px-4">
            <div className="flex gap-3 overflow-x-auto pb-4 scrollbar-hide">
              {loading
                ? Array(8).fill(0).map((_, i) => <SkeletonCard key={i} />)
                : items.length === 0
                  ? (
                    <div className="text-muted-foreground text-sm py-8">
                      Belum ada konten {activeTab} tersedia
                    </div>
                  )
                  : items.map((item, index) => (
                    <Link
                      key={item.id}
                      to={getDetailRoute(item)}
                      className="flex-shrink-0 w-[140px] md:w-[160px] group"
                    >
                      <div className="relative aspect-[2/3] rounded-lg overflow-hidden bg-secondary">
                        <img referrerPolicy="no-referrer"
                          src={getPosterUrl(item.poster_url)}
                          alt={item.title}
                          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                          loading="lazy"
                          onError={(e) => {
                            (e.target as HTMLImageElement).src = '/placeholder.svg';
                          }}
                        />

                        {/* Rank Number */}
                        <div className="absolute bottom-2 left-2">
                          <span
                            className="text-4xl md:text-5xl font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                            style={{ WebkitTextStroke: '1px rgba(0,0,0,0.3)' }}
                          >
                            {index + 1}
                          </span>
                        </div>

                        {/* Type badge */}
                        <div className="absolute top-2 right-2">
                          <span className={`px-2 py-0.5 text-[10px] font-semibold text-white rounded capitalize ${getTabColor(item.type)}`}>
                            {item.type}
                          </span>
                        </div>

                        {/* Status badge */}
                        <div className="absolute bottom-2 right-2">
                          <span className="px-1.5 py-0.5 text-[10px] font-medium bg-black/60 text-white rounded">
                            {getSubLabel(item)}
                          </span>
                        </div>

                        {/* Rating */}
                        <div className="absolute top-2 left-2">
                          <span className="px-1.5 py-0.5 text-[10px] font-bold bg-yellow-500 text-black rounded flex items-center gap-0.5">
                            ★ {formatRating(item.rating)}
                          </span>
                        </div>

                        {/* Hover overlay */}
                        <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                          <Play className="w-8 h-8 text-white fill-white" />
                        </div>
                      </div>

                      <h3 className="mt-2 text-sm font-medium text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                        {item.title}
                      </h3>
                    </Link>
                  ))
              }
            </div>
          </div>
        )}

        {/* View All */}
        {!loading && !error && items.length > 0 && (
          <div className="mt-4 text-center">
            <Link
              to={`/${activeTab}`}
              className="inline-flex items-center gap-2 px-6 py-2 text-sm font-medium bg-secondary/50 hover:bg-secondary text-foreground rounded-full transition-colors"
            >
              Lihat Semua {activeTab === 'anime' ? 'Anime' : activeTab === 'manga' ? 'Manga' : 'Movies'}
              <ChevronRight className="w-4 h-4" />
            </Link>
          </div>
        )}
      </div>
    </section>
  );
};

export default TrendingSection;