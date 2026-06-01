import { useState, useEffect } from "react";
import { useParams, Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Button } from "@/components/ui/button";
import { Play, Bookmark, Share2, Star, Calendar, Clock, Globe, Film, ChevronRight } from "lucide-react";
import { CommentSection } from "@/components/comments";
import { fetchDetail, getPosterUrl, formatRating, ContentDetail, Episode } from "@/services/api";

const SkeletonDetail = () => (
  <div className="animate-pulse">
    <div className="h-[300px] bg-secondary" />
    <div className="container mx-auto px-4 -mt-32 relative z-10">
      <div className="flex flex-col md:flex-row gap-6">
        <div className="w-[180px] h-[270px] rounded-lg bg-secondary flex-shrink-0" />
        <div className="flex-1 space-y-3 pt-32">
          <div className="h-8 bg-secondary rounded w-2/3" />
          <div className="h-4 bg-secondary rounded w-1/3" />
          <div className="h-4 bg-secondary rounded w-full" />
          <div className="h-4 bg-secondary rounded w-4/5" />
        </div>
      </div>
    </div>
  </div>
);

const MovieDetailPage = () => {
  const { slug } = useParams<{ slug: string }>();
  const [content, setContent] = useState<ContentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeEpisode, setActiveEpisode] = useState<Episode | null>(null);
  const [bookmarked, setBookmarked] = useState(false);

  useEffect(() => {
    if (!slug) return;
    setLoading(true);
    setError(null);

    fetchDetail(slug)
      .then((data) => {
        setContent(data);
        // Auto-select first episode if TV show
        if (data.episodes && data.episodes.length > 0) {
          setActiveEpisode(data.episodes[data.episodes.length - 1]); // latest
        }
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, [slug]);

  if (loading) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <SkeletonDetail />
    </div>
  );

  if (error || !content) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <Film className="w-16 h-16 mb-4 opacity-30" />
        <p className="text-lg font-medium">Konten tidak ditemukan</p>
        <p className="text-sm mt-1">{error}</p>
        <Link to="/movies" className="mt-4 text-primary hover:underline text-sm">
          ← Kembali ke Movies
        </Link>
      </div>
      <Footer />
    </div>
  );

  const isTVShow = content.status === 'ongoing' || (content.episodes && content.episodes.length > 1);
  const episodes = content.episodes || [];

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      {/* Banner */}
      <div className="relative h-[320px] md:h-[420px]">
        <img referrerPolicy="no-referrer"
          src={content.banner_url || getPosterUrl(content.poster_url)}
          alt={content.title}
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-background/80 to-transparent" />
      </div>

      <main className="container mx-auto px-4 -mt-40 relative z-10 pb-16">
        <div className="flex flex-col md:flex-row gap-6">

          {/* Poster */}
          <div className="flex-shrink-0">
            <img referrerPolicy="no-referrer"
              src={getPosterUrl(content.poster_url)}
              alt={content.title}
              className="w-[160px] md:w-[210px] rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.8)]"
              onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }}
            />
          </div>

          {/* Info */}
          <div className="flex-1 pt-0 md:pt-28">
            {/* Breadcrumb */}
            <div className="flex items-center gap-1 text-xs text-muted-foreground mb-3">
              <Link to="/movies" className="hover:text-primary transition-colors">Movies</Link>
              <ChevronRight className="w-3 h-3" />
              <span className="text-foreground truncate">{content.title}</span>
            </div>

            <h1 className="text-2xl md:text-4xl font-black text-foreground mb-2 leading-tight">
              {content.title}
            </h1>
            {content.title_alt && (
              <p className="text-sm text-muted-foreground mb-3">{content.title_alt}</p>
            )}

            {/* Meta row */}
            <div className="flex flex-wrap items-center gap-3 mb-4">
              {content.rating > 0 && (
                <span className="flex items-center gap-1 text-amber-500 font-bold">
                  <Star className="w-4 h-4 fill-current" />
                  {formatRating(content.rating)}
                </span>
              )}
              {content.year && (
                <span className="flex items-center gap-1 text-muted-foreground text-sm">
                  <Calendar className="w-3.5 h-3.5" /> {content.year}
                </span>
              )}
              {content.duration && (
                <span className="flex items-center gap-1 text-muted-foreground text-sm">
                  <Clock className="w-3.5 h-3.5" /> {content.duration}
                </span>
              )}
              {content.quality && (
                <span className="px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 text-xs font-bold">
                  {content.quality}
                </span>
              )}
              <span className={`px-2 py-0.5 rounded text-xs font-semibold ${isTVShow ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400'}`}>
                {isTVShow ? 'TV Show' : 'Movie'}
              </span>
            </div>

            {/* Genres */}
            {content.genres?.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-4">
                {content.genres.map((g) => (
                  <span key={g} className="px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-sm">
                    {g}
                  </span>
                ))}
              </div>
            )}

            {/* Description */}
            {content.description && (
              <p className="text-muted-foreground text-sm leading-relaxed mb-6 max-w-2xl">
                {content.description}
              </p>
            )}

            {/* Actions */}
            <div className="flex items-center gap-3 flex-wrap">
              {activeEpisode?.source_url ? (
                <a href={activeEpisode.source_url} target="_blank" rel="noopener noreferrer">
                  <Button className="gap-2 bg-primary hover:bg-primary/90">
                    <Play className="w-4 h-4 fill-current" />
                    {isTVShow ? `Tonton Episode ${activeEpisode.episode_number}` : 'Tonton Sekarang'}
                  </Button>
                </a>
              ) : (
                <Button className="gap-2" disabled>
                  <Play className="w-4 h-4 fill-current" />
                  Tonton Sekarang
                </Button>
              )}
              <Button
                variant="outline"
                size="icon"
                onClick={() => setBookmarked(!bookmarked)}
                className={bookmarked ? 'text-primary border-primary/50' : ''}
                title="Tambah ke koleksi"
              >
                <Bookmark className={`w-4 h-4 ${bookmarked ? 'fill-current' : ''}`} />
              </Button>
              <Button
                variant="outline"
                size="icon"
                onClick={() => navigator.share?.({ title: content.title, url: window.location.href })}
                title="Bagikan"
              >
                <Share2 className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Episodes list (for TV shows) */}
        {isTVShow && episodes.length > 0 && (
          <div className="mt-12">
            <h2 className="text-xl font-bold text-foreground mb-4 flex items-center gap-2">
              <Film className="w-5 h-5 text-primary" />
              Episode <span className="text-muted-foreground font-normal text-base">({episodes.length} episode)</span>
            </h2>
            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
              {[...episodes].reverse().map((ep) => (
                <button
                  key={ep.id}
                  onClick={() => setActiveEpisode(ep)}
                  className={`relative py-3 px-2 rounded-lg text-sm font-medium transition-all text-center ${
                    activeEpisode?.id === ep.id
                      ? 'bg-primary text-primary-foreground shadow-[0_0_16px_hsl(0_72%_51%/0.4)]'
                      : 'bg-secondary hover:bg-secondary/80 text-foreground'
                  }`}
                >
                  <span className="block text-xs text-inherit opacity-70 mb-0.5">Eps</span>
                  {ep.episode_number}
                </button>
              ))}
            </div>

            {/* Active episode player area */}
            {activeEpisode?.source_url && (
              <div className="mt-6 p-4 rounded-xl bg-secondary/30 border border-border/50 flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">
                    Episode {activeEpisode.episode_number}
                    {activeEpisode.title ? `: ${activeEpisode.title}` : ''}
                  </p>
                  <p className="text-xs text-muted-foreground mt-0.5">Klik tombol untuk menonton</p>
                </div>
                <a href={activeEpisode.source_url} target="_blank" rel="noopener noreferrer">
                  <Button size="sm" className="gap-2">
                    <Play className="w-3.5 h-3.5 fill-current" />
                    Tonton
                  </Button>
                </a>
              </div>
            )}
          </div>
        )}

        {/* Comments */}
        <div className="mt-14">
          <CommentSection
            category="movie"
            contentId={content.id}
            episodeId={activeEpisode?.id}
          />
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default MovieDetailPage;