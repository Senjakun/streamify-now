import { useState, useEffect, useRef } from "react";
import { useParams, Link, useNavigate } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Button } from "@/components/ui/button";
import { BookOpen, Bookmark, Share2, Star, ChevronRight, ChevronLeft, X, ZoomIn, ZoomOut, List } from "lucide-react";
import { CommentSection } from "@/components/comments";
import { fetchDetail, getPosterUrl, formatRating, ContentDetail, Chapter, toggleBookmark } from "@/services/api";
import { saveHistory } from "@/services/api";

// ── Manga Reader (inline) ────────────────────────────────────
interface ReaderProps {
  chapter: Chapter;
  allChapters: Chapter[];
  onClose: () => void;
  onNavigate: (chapter: Chapter) => void;
}

const getMangaPoster = (url?: string) => {
  if (!url) return '/placeholder.svg';
  if (url.includes('kiryuu.to') || url.includes('mangaserver')) {
    return `/api/manga-proxy.php?action=image&url=${encodeURIComponent(url)}`;
  }
  return url;
};

const MangaReader = ({ chapter, allChapters, onClose, onNavigate }: ReaderProps) => {
  const [zoom, setZoom] = useState(100);
  const [showControls, setShowControls] = useState(true);
  const currentIndex = allChapters.findIndex(c => c.id === chapter.id);
  const prevChapter = currentIndex < allChapters.length - 1 ? allChapters[currentIndex + 1] : null;
  const nextChapter = currentIndex > 0 ? allChapters[currentIndex - 1] : null;

  const toggleControls = () => setShowControls(prev => !prev);

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
    }
  };

  useEffect(() => {
    document.body.style.overflow = 'hidden';
    document.querySelectorAll('nav, header, footer').forEach(el => el.setAttribute('style', 'display:none'));
    window.scrollTo(0, 0);
    return () => {
      document.body.style.overflow = '';
      document.querySelectorAll('nav, header, footer').forEach(el => el.setAttribute('style', ''));
    };
  }, []);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[100] bg-black overflow-hidden">
      {/* Navbar Atas */}
      <div
        className={`absolute top-0 left-0 right-0 z-10 flex items-center justify-between px-4 py-3 bg-black/90 border-b border-white/10 transition-transform duration-300 ${
          showControls ? 'translate-y-0' : '-translate-y-full'
        }`}
      >
        <div className="flex items-center gap-3">
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-white/10 transition-colors">
            <X className="w-5 h-5 text-white" />
          </button>
          <div>
            <p className="text-white text-sm font-semibold">Chapter {chapter.chapter_number}</p>
            {chapter.title && <p className="text-white/50 text-xs">{chapter.title}</p>}
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setZoom(z => Math.max(50, z - 10))}
            className="p-1.5 rounded-lg hover:bg-white/10 transition-colors text-white"
          >
            <ZoomOut className="w-4 h-4" />
          </button>
          <span className="text-white text-xs w-10 text-center">{zoom}%</span>
          <button
            onClick={() => setZoom(z => Math.min(200, z + 10))}
            className="p-1.5 rounded-lg hover:bg-white/10 transition-colors text-white"
          >
            <ZoomIn className="w-4 h-4" />
          </button>

          <button
            onClick={toggleFullscreen}
            className="p-1.5 rounded-lg hover:bg-white/10 transition-colors text-white ml-2"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
          </button>
        </div>
      </div>

      {/* Area Gambar */}
      <div key={chapter.id} className="absolute inset-0 overflow-y-auto cursor-pointer" onClick={toggleControls}>
        {chapter.images.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-white/50 gap-4">
            <BookOpen className="w-16 h-16 opacity-30" />
            <p>Gambar chapter belum tersedia</p>
            <p className="text-xs">Jalankan scraper manga terlebih dahulu</p>
          </div>
        ) : (
          <div className="flex flex-col items-center mx-auto" style={{ maxWidth: zoom <= 100 ? '800px' : 'none', width: `${zoom}%` }}>
            {chapter.images.map((img, i) => (
              <img referrerPolicy="no-referrer"
                key={i}
                src={img}
                alt={`Halaman ${i + 1}`}
                className="block w-full align-top"
                style={{ margin: 0 }}
                loading="lazy"
                onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
              />
            ))}
          </div>
        )}
      </div>

      {/* Navbar Bawah */}
      <div
        className={`absolute bottom-0 left-0 right-0 z-10 flex items-center justify-center gap-4 px-4 py-3 bg-black/90 border-t border-white/10 transition-transform duration-300 ${
          showControls ? 'translate-y-0' : 'translate-y-full'
        }`}
      >
        <button
          onClick={() => prevChapter && onNavigate(prevChapter)}
          disabled={!prevChapter}
          className="flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-30 text-white text-sm transition-colors"
        >
          <ChevronLeft className="w-4 h-4" />
          Chapter {prevChapter?.chapter_number ?? '-'}
        </button>
        <span className="text-white/40 text-xs">Ch {chapter.chapter_number}</span>
        <button
          onClick={() => nextChapter && onNavigate(nextChapter)}
          disabled={!nextChapter}
          className="flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-30 text-white text-sm transition-colors"
        >
          Chapter {nextChapter?.chapter_number ?? '-'}
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
};

// ── Main Page ────────────────────────────────────────────────
const MangaDetailPage = () => {
  const { slug } = useParams<{ slug: string }>();
  const [content, setContent] = useState<ContentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeChapter, setActiveChapter] = useState<Chapter | null>(null);
  const [bookmarked, setBookmarked] = useState(false);
  const [chapterLoading, setChapterLoading] = useState(false);
  const [chapterImages, setChapterImages] = useState<string[]>([]);
  const contentIdRef = useRef<number>(0);

  const handleChClick = (ch: Chapter) => {
    const cid = contentIdRef.current || content?.id || 0;
    if (cid) saveHistory({ content_id: cid, chapter_id: ch.id });
    setActiveChapter(ch);
    // If images already loaded from DB, use them
    if (ch.images && ch.images.length > 0) { setChapterImages(ch.images); return; }
    // Else fetch on-demand from Komiku
    const chUrl = (ch as any).source_url || '';
    if (!chUrl) { setChapterImages([]); return; }
    setChapterLoading(true);
    setChapterImages([]);
    fetch(`/api/komiku-chapter.php?url=${encodeURIComponent(chUrl)}`)
      .then(r => r.json())
      .then(res => {
        const imgs = res.images || [];
        setChapterImages(imgs);
        setActiveChapter(prev => prev ? { ...prev, images: imgs } as Chapter : prev);
        setChapterLoading(false);
      })
      .catch(() => { setChapterLoading(false); });
  };

  useEffect(() => {
    if (!slug) return;
    setLoading(true);
    setError(null);

    fetch(`/api/content.php?action=detail&slug=${slug}&type=manga`)
      .then(r => r.json())
      .then((data) => {
        if (!data || !data.id) throw new Error('Komik tidak ditemukan');
        const chs = (data.chapters || []).map((ch: any) => ({
          id: ch.id,
          content_id: data.id,
          chapter_number: ch.chapter_number,
          title: ch.title,
          images: Array.isArray(ch.images) ? ch.images : (ch.images ? JSON.parse(ch.images) : []),
          source_url: ch.source_url,
          created_at: ch.created_at || '',
        }));
        const normalized = {
          ...data,
          type: 'manga',
          created_at: data.created_at || '',
          updated_at: data.updated_at || '',
          rating: parseFloat(String(data.rating)) || 0,
          genres: Array.isArray(data.genres) ? data.genres : [],
          chapters: chs,
        };
        contentIdRef.current = data.id;
        setContent(normalized as ContentDetail);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, [slug]);

  const chapters = content?.chapters || [];

  if (loading) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="pt-20 container mx-auto px-4">
        <div className="flex gap-6 mt-8">
          <div className="w-[160px] h-[240px] rounded-xl bg-secondary animate-pulse flex-shrink-0" />
          <div className="flex-1 space-y-3 pt-4">
            <div className="h-8 bg-secondary rounded w-2/3 animate-pulse" />
            <div className="h-4 bg-secondary rounded w-1/4 animate-pulse" />
            <div className="h-20 bg-secondary rounded animate-pulse" />
          </div>
        </div>
      </div>
    </div>
  );

  if (error || !content) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <BookOpen className="w-16 h-16 mb-4 opacity-30" />
        <p className="text-lg font-medium">Manga tidak ditemukan</p>
        <p className="text-sm mt-1">{error}</p>
        <Link to="/manga" className="mt-4 text-primary hover:underline text-sm">
          ← Kembali ke Manga
        </Link>
      </div>
      <Footer />
    </div>
  );

  return (
    <>
      {activeChapter && (
        <MangaReader
          chapter={activeChapter}
          allChapters={chapters}
          onClose={() => { setActiveChapter(null); setChapterImages([]); }}
          onNavigate={(ch) => { setChapterImages([]); handleChClick(ch); }}
        />
      )}

      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="relative h-[280px] md:h-[360px]">
          <img referrerPolicy="no-referrer"
            src={getMangaPoster(content.poster_url)}
            alt={content.title}
            className="w-full h-full object-cover object-top filter blur-md scale-105"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-background via-background/70 to-background/30" />
        </div>

        <main className="container mx-auto px-4 -mt-36 relative z-10 pb-16">
          <div className="flex flex-col md:flex-row gap-6">
            <div className="flex-shrink-0">
              <img referrerPolicy="no-referrer"
                src={getMangaPoster(content.poster_url)}
                alt={content.title}
                className="w-[150px] md:w-[200px] rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.8)]"
                onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }}
              />
            </div>

            <div className="flex-1 md:pt-20">
              <div className="flex items-center gap-1 text-xs text-muted-foreground mb-3">
                <Link to="/manga" className="hover:text-primary transition-colors">Manga</Link>
                <ChevronRight className="w-3 h-3" />
                <span className="text-foreground truncate">{content.title}</span>
              </div>

              <h1 className="text-2xl md:text-3xl font-black text-foreground mb-1 leading-tight">
                {content.title}
              </h1>
              {content.title_alt && (
                <p className="text-sm text-muted-foreground mb-3">{content.title_alt}</p>
              )}

              <div className="flex flex-wrap items-center gap-3 mb-3">
                {content.rating > 0 && (
                  <span className="flex items-center gap-1 text-amber-500 font-bold">
                    <Star className="w-4 h-4 fill-current" />
                    {formatRating(content.rating)}
                  </span>
                )}
                {content.author && (
                  <span className="text-sm text-muted-foreground">✍️ {content.author}</span>
                )}
                {content.year && (
                  <span className="text-sm text-muted-foreground">📅 {content.year}</span>
                )}
                <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                  content.status === 'ongoing' ? 'bg-orange-500/20 text-orange-400' : 'bg-green-500/20 text-green-400'
                }`}>
                  {content.status === 'ongoing' ? 'Ongoing' : 'Tamat'}
                </span>
                {chapters.length > 0 && (
                  <span className="text-sm text-muted-foreground">
                    📚 {chapters.length} Chapter
                  </span>
                )}
              </div>

              {content.genres?.length > 0 && (
                <div className="flex flex-wrap gap-2 mb-4">
                  {content.genres.map((g) => (
                    <span key={g} className="px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-sm">
                      {g}
                    </span>
                  ))}
                </div>
              )}

              {content.description && (
                <p className="text-muted-foreground text-sm leading-relaxed mb-5 max-w-2xl">
                  {content.description}
                </p>
              )}

              <div className="flex items-center gap-3 flex-wrap">
                {chapters.length > 0 && (
                  <Button
                    className="gap-2 bg-orange-500 hover:bg-orange-600 text-white"
                    onClick={() => setActiveChapter(chapters[0])}
                  >
                    <BookOpen className="w-4 h-4" />
                    Baca Chapter Terbaru
                  </Button>
                )}
                <Button
                  variant="outline"
                  size="icon"
                  onClick={async () => { const token = localStorage.getItem("token"); if (!token) return; const res = await toggleBookmark(content!.id, token); setBookmarked(res.bookmarked); }}
                  className={bookmarked ? 'text-primary border-primary/50' : ''}
                >
                  <Bookmark className={`w-4 h-4 ${bookmarked ? 'fill-current' : ''}`} />
                </Button>
                <Button variant="outline" size="icon">
                  <Share2 className="w-4 h-4" />
                </Button>
              </div>
            </div>
          </div>

          {chapters.length > 0 && (
            <div className="mt-12">
              <h2 className="text-xl font-bold text-foreground mb-4 flex items-center gap-2">
                <List className="w-5 h-5 text-orange-500" />
                Daftar Chapter
                <span className="text-muted-foreground font-normal text-base">({chapters.length})</span>
              </h2>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2 max-h-[400px] overflow-y-auto pr-2">
                {chapters.map((ch) => (
                  <button
                    key={ch.id}
                    onClick={() => handleChClick(ch)}
                    className="flex items-center justify-between px-3 py-2.5 rounded-lg bg-secondary hover:bg-secondary/70 hover:border-orange-500/40 border border-border/50 transition-all text-left group"
                  >
                    <span className="text-sm font-medium text-foreground group-hover:text-orange-400 transition-colors truncate">
                      Ch {ch.chapter_number}
                    </span>
                    <BookOpen className="w-3.5 h-3.5 text-muted-foreground group-hover:text-orange-400 flex-shrink-0 ml-1" />
                  </button>
                ))}
              </div>
            </div>
          )}

          {chapters.length === 0 && !loading && (
            <div className="mt-12 text-center py-12 rounded-xl bg-secondary/30 border border-border/50 text-muted-foreground">
              <BookOpen className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p>Belum ada chapter tersedia</p>
              <p className="text-xs mt-1">Jalankan scraper manga untuk mengambil chapter</p>
            </div>
          )}

          <div className="mt-14">
            <CommentSection
              category="manga"
              contentId={content.id || (slug ? slug.split('').reduce((a,c) => a + c.charCodeAt(0), 0) : 0)}
            />
          </div>
        </main>
        <Footer />
      </div>
    </>
  );
};

export default MangaDetailPage;
