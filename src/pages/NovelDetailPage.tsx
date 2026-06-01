import React, { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { CommentSection } from "@/components/comments";
import { fetchChapterContent, fetchNovelDetail } from "../lib/novelApi";
import { Chapter, Novel } from "../types/novel";
import {
  BookOpen,
  ChevronLeft,
  ChevronRight,
  Search,
  BookText,
  Info,
  Layers3,
  X,
  List,
  Settings,
  Languages,
  Minus,
  Plus,
} from "lucide-react";

type Theme = "dark" | "light" | "sepia";

const THEME_STYLES: Record<
  Theme,
  { bg: string; text: string; nav: string; border: string; panel: string }
> = {
  dark: {
    bg: "bg-zinc-950",
    text: "text-gray-100",
    nav: "bg-zinc-900/95",
    border: "border-zinc-700/70",
    panel: "bg-zinc-900/75",
  },
  light: {
    bg: "bg-white",
    text: "text-gray-900",
    nav: "bg-gray-100/95",
    border: "border-gray-200",
    panel: "bg-white/90",
  },
  sepia: {
    bg: "bg-amber-50",
    text: "text-amber-900",
    nav: "bg-amber-100/95",
    border: "border-amber-200",
    panel: "bg-amber-50/95",
  },
};

const COUNTRY_BADGE: Record<string, string> = {
  JP: "bg-red-600 text-white",
  CN: "bg-amber-500 text-black",
  KR: "bg-blue-600 text-white",
};

const COUNTRY_LABEL: Record<string, string> = {
  JP: "JP Novel",
  CN: "CN Novel",
  KR: "KR Novel",
};

interface ChapterContentState {
  number: number;
  title: string;
  content: string;
  prev?: number | null;
  next?: number | null;
}

const NovelDetailPage: React.FC = () => {
  const { slug } = useParams<{ slug: string }>();

  const [novel, setNovel] = useState<Novel | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [chapterSearch, setChapterSearch] = useState("");
  const [readerOpen, setReaderOpen] = useState(false);
  const [chapterLoading, setChapterLoading] = useState(false);
  const [activeChapter, setActiveChapter] = useState<ChapterContentState | null>(null);
  const [showChapterList, setShowChapterList] = useState(false);
  const [showControls, setShowControls] = useState(true);
  const [showSettings, setShowSettings] = useState(false);
  const [fontSize, setFontSize] = useState(16);
  const [theme, setTheme] = useState<Theme>("dark");
  const [showFullSynopsis, setShowFullSynopsis] = useState(false);
  const [translated, setTranslated] = useState<string | null>(null);
  const [translating, setTranslating] = useState(false);

  // Reset translation when chapter changes
  useEffect(() => { setTranslated(null); }, [activeChapter?.number]);

  const translateChapter = async () => {
    if (!activeChapter) return;
    if (translated) { setTranslated(null); return; } // toggle off
    const plain = activeChapter.content.replace(/<[^>]+>/g, '\n').replace(/\n{2,}/g, '\n').trim();
    setTranslating(true);
    try {
      const res = await fetch('/api/gemini.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: plain, target: 'Indonesia' }),
      });
      const d = await res.json();
      if (d.error === 'no_key') {
        alert('Belum ada API key di pool. Donasikan API key Gemini kamu (gratis) lewat tombol 🔑 di halaman novel biar semua bisa translate.');
        setTranslating(false); return;
      }
      if (d.error) { alert('Gagal translate: ' + d.error); setTranslating(false); return; }
      const txt = d.text || '';
      setTranslated(txt ? txt.split('\n').filter(Boolean).map((p: string) => `<p>${p}</p>`).join('') : '<p>Terjemahan kosong.</p>');
    } catch (e) {
      alert('Gagal menghubungi server translate.');
    }
    setTranslating(false);
  };

  useEffect(() => {
    if (readerOpen) {
      document.body.style.overflow = 'hidden';
      document.querySelectorAll('nav, header, footer').forEach(el => el.setAttribute('style', 'display:none'));
    } else {
      document.body.style.overflow = '';
      document.querySelectorAll('nav, header, footer').forEach(el => el.setAttribute('style', ''));
    }
    return () => {
      document.body.style.overflow = '';
      document.querySelectorAll('nav, header, footer').forEach(el => el.setAttribute('style', ''));
    };
  }, [readerOpen]);

  useEffect(() => {
    if (!slug) return;

    let mounted = true;

    const run = async () => {
      try {
        setLoading(true);
        setError(null);
        const data = await fetchNovelDetail(slug);
        if (!mounted) return;
        setNovel(data);
      } catch (err: any) {
        if (!mounted) return;
        setError(err?.message || "Gagal memuat detail novel");
      } finally {
        if (mounted) setLoading(false);
      }
    };

    run();

    return () => {
      mounted = false;
    };
  }, [slug]);

  const filteredChapters = useMemo(() => {
    if (!novel?.chapters) return [];
    const q = chapterSearch.trim().toLowerCase();
    if (!q) return novel.chapters;

    return novel.chapters.filter((ch) => {
      return (
        String(ch.number).includes(q) ||
        (ch.title || "").toLowerCase().includes(q)
      );
    });
  }, [novel, chapterSearch]);

  const loadChapter = async (chapterNumber: number) => {
    if (!slug) return;

    try {
      setReaderOpen(true);
      setChapterLoading(true);
      setShowControls(true);
      setShowChapterList(false);

      const data = await fetchChapterContent(slug, chapterNumber);

      setActiveChapter({
        number: data.number,
        title: data.title,
        content: data.content,
        prev: data.prev,
        next: data.next,
      });

      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: "instant" as ScrollBehavior });
      });
    } catch (err) {
      console.error(err);
    } finally {
      setChapterLoading(false);
    }
  };

  const cycleTheme = () => {
    setTheme((t) => (t === "dark" ? "light" : t === "light" ? "sepia" : "dark"));
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <main className="pt-20 pb-12">
          <div className="container mx-auto px-4">
            <div className="rounded-3xl border border-white/10 bg-secondary/30 p-5 animate-pulse">
              <div className="h-8 w-40 bg-secondary rounded mb-5" />
              <div className="flex flex-col md:flex-row gap-6">
                <div className="w-[160px] h-[230px] rounded-2xl bg-secondary" />
                <div className="flex-1 space-y-3">
                  <div className="h-8 w-2/3 bg-secondary rounded" />
                  <div className="h-4 w-1/3 bg-secondary rounded" />
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div className="h-20 bg-secondary rounded-2xl" />
                    <div className="h-20 bg-secondary rounded-2xl" />
                    <div className="h-20 bg-secondary rounded-2xl" />
                    <div className="h-20 bg-secondary rounded-2xl" />
                  </div>
                  <div className="h-24 bg-secondary rounded-2xl" />
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    );
  }

  if (error || !novel) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <main className="pt-24 pb-16">
          <div className="container mx-auto px-4">
            <div className="flex flex-col items-center justify-center min-h-[55vh] text-muted-foreground">
              <BookOpen className="w-16 h-16 mb-4 opacity-30" />
              <p>Novel tidak ditemukan</p>
              <Link to="/novel" className="mt-4 text-primary hover:underline text-sm">
                ← Kembali ke Novel
              </Link>
            </div>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  const badgeClass = COUNTRY_BADGE[novel.country] || "bg-gray-600 text-white";
  const countryLabel = COUNTRY_LABEL[novel.country] || novel.country;
  const ts = THEME_STYLES[theme];

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="relative h-[280px] md:h-[340px] overflow-hidden">
        <img referrerPolicy="no-referrer"
          src={novel.poster_url || "/placeholder.svg"}
          alt={novel.title}
          className="w-full h-full object-cover object-top scale-105 blur-md"
          onError={(e) => {
            (e.target as HTMLImageElement).src = "/placeholder.svg";
          }}
        />
        <div className="absolute inset-0 bg-gradient-to-b from-black/50 via-background/70 to-background" />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,197,94,0.16),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(239,68,68,0.10),transparent_30%)]" />
      </div>

      <main className="container mx-auto px-4 -mt-24 md:-mt-28 relative z-10 pb-16">
        <section className="rounded-3xl border border-white/10 bg-gradient-to-br from-zinc-950 via-zinc-950 to-violet-950/30 p-4 md:p-6 shadow-[0_24px_80px_rgba(0,0,0,0.45)]">
          <div className="flex items-center gap-2 text-xs text-muted-foreground mb-4">
            <Link to="/novel" className="hover:text-primary">Novel</Link>
            <span>›</span>
            <span className="truncate">{novel.title}</span>
          </div>

          <div className="flex flex-col md:flex-row gap-5 md:gap-7">
            <div className="w-[150px] md:w-[190px] flex-shrink-0">
              <img referrerPolicy="no-referrer"
                src={novel.poster_url || "/placeholder.svg"}
                alt={novel.title}
                className="w-full rounded-2xl shadow-[0_18px_40px_rgba(0,0,0,0.55)]"
                onError={(e) => {
                  (e.target as HTMLImageElement).src = "/placeholder.svg";
                }}
              />
            </div>

            <div className="flex-1">
              <div className="flex flex-wrap items-center gap-2 mb-3">
                <span
                  className={
                    "px-2.5 py-1 rounded-lg text-xs font-semibold " +
                    (novel.status === "Tamat"
                      ? "bg-violet-600 text-white"
                      : "bg-orange-500 text-white")
                  }
                >
                  {novel.status === "Tamat" ? "Tamat" : "Ongoing"}
                </span>

                {novel.rating > 0 && (
                  <span className="px-2.5 py-1 rounded-lg bg-yellow-500 text-black text-xs font-bold">
                    ★ {novel.rating.toFixed(1)}
                  </span>
                )}
              </div>

              <h1 className="text-2xl md:text-4xl font-black tracking-tight text-foreground leading-tight">
                {novel.title}
              </h1>

              {novel.author && (
                <p className="text-sm text-muted-foreground mt-2">✍️ {novel.author}</p>
              )}

              {novel.genres?.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-4">
                  {novel.genres.map((genre) => (
                    <span
                      key={genre}
                      className="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-muted-foreground"
                    >
                      {genre}
                    </span>
                  ))}
                </div>
              )}

              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
                <div className="rounded-2xl border border-white/10 bg-white/5 p-3.5">
                  <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                    <Layers3 className="w-4 h-4" /> Total Chapter
                  </div>
                  <div className="text-xl font-black">{novel.total_chapters}</div>
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/5 p-3.5">
                  <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                    <BookOpen className="w-4 h-4" /> Chapter Terbaru
                  </div>
                  <div className="text-xl font-black">{novel.latest_chapter}</div>
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/5 p-3.5">
                  <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                    <Info className="w-4 h-4" /> Status
                  </div>
                  <div className="text-xl font-black">{novel.status === "Tamat" ? "Tamat" : "Ongoing"}</div>
                </div>
              </div>

              <div className="flex flex-wrap gap-3 mt-5">
                <button
                  onClick={() => loadChapter(1)}
                  className="px-5 py-3 rounded-full bg-violet-600 text-white font-bold text-sm hover:bg-violet-500"
                >
                  Baca Chapter 1
                </button>

                {novel.latest_chapter > 1 && (
                  <button
                    onClick={() => loadChapter(novel.latest_chapter)}
                    className="px-5 py-3 rounded-full bg-white/8 border border-white/10 text-foreground font-semibold text-sm hover:bg-white/12"
                  >
                    Ch. {novel.latest_chapter} Terbaru
                  </button>
                )}
              </div>
            </div>
          </div>
        </section>

        <div className="grid lg:grid-cols-[1.1fr_0.9fr] gap-6 mt-8">
          <section className="rounded-3xl border border-white/10 bg-black/25 p-4 md:p-5">
            <div className="flex items-center gap-2 mb-3">
              <BookText className="w-5 h-5 text-violet-400" />
              <h2 className="text-lg md:text-xl font-bold">Sinopsis</h2>
            </div>

            <div className="rounded-2xl bg-white/5 border border-white/10 p-4">
              <p
                className={
                  "text-sm leading-7 text-muted-foreground whitespace-pre-line " +
                  (showFullSynopsis ? "" : "line-clamp-5")
                }
              >
                {novel.description || "Belum ada deskripsi."}
              </p>

              {(novel.description || "").length > 260 && (
                <button
                  onClick={() => setShowFullSynopsis((v) => !v)}
                  className="mt-3 text-sm font-medium text-violet-400 hover:text-violet-300 transition-colors"
                >
                  {showFullSynopsis ? "Sembunyikan" : "Lihat selengkapnya"}
                </button>
              )}
            </div>
          </section>

          <section className="rounded-3xl border border-white/10 bg-black/25 p-4 md:p-5">
            <div className="flex items-center gap-2 mb-3">
              <Info className="w-5 h-5 text-violet-400" />
              <h2 className="text-lg md:text-xl font-bold">Info Novel</h2>
            </div>

            <div className="space-y-3">
              <div className="rounded-2xl bg-white/5 border border-white/10 p-4">
                <p className="text-xs text-muted-foreground mb-1">Judul</p>
                <p className="text-sm font-medium">{novel.title}</p>
              </div>

              <div className="rounded-2xl bg-white/5 border border-white/10 p-4">
                <p className="text-xs text-muted-foreground mb-1">Author</p>
                <p className="text-sm font-medium">{novel.author || "-"}</p>
              </div>

              <div className="rounded-2xl bg-white/5 border border-white/10 p-4">
                <p className="text-xs text-muted-foreground mb-1">Status</p>
                <p className="text-sm font-medium">{novel.status === "Tamat" ? "Tamat" : "Ongoing"}</p>
              </div>
            </div>
          </section>
        </div>

        <section className="rounded-3xl border border-white/10 bg-black/25 p-4 md:p-5 mt-8">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <List className="w-5 h-5 text-violet-400" />
                <h2 className="text-lg md:text-xl font-bold">Daftar Chapter</h2>
              </div>
              <p className="text-xs text-muted-foreground">
                {novel.chapters?.length || 0} chapter terindeks
              </p>
            </div>

            <div className="relative w-full md:w-72">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
              <input
                value={chapterSearch}
                onChange={(e) => setChapterSearch(e.target.value)}
                placeholder="Cari chapter..."
                className="w-full pl-9 pr-4 py-2.5 rounded-full text-sm bg-secondary border border-border/50 outline-none"
              />
            </div>
          </div>

          <div className="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 gap-2 max-h-[420px] overflow-y-auto pr-1">
            {filteredChapters.map((ch: Chapter) => (
              <button
                key={ch.id}
                onClick={() => loadChapter(ch.number)}
                className="px-2 py-2.5 rounded-xl border border-white/10 bg-white/5 text-sm font-medium hover:border-violet-500/40 hover:text-violet-400 transition-all"
              >
                {ch.number}
              </button>
            ))}
          </div>
        </section>

        <div className="mt-12">
          <CommentSection category="novel" contentId={String(novel.id)} />
        </div>
      </main>

      {readerOpen && (
        <div className={`fixed inset-0 z-[100] flex flex-col ${ts.bg}`}>
          <div
            className={
              "flex items-center justify-between px-4 py-3 border-b backdrop-blur transition-transform duration-300 " +
              ts.nav +
              " " +
              ts.border +
              (showControls ? "" : " -translate-y-full absolute top-0 left-0 right-0")
            }
          >
            <button onClick={() => setReaderOpen(false)} className={`p-2 rounded-lg ${ts.text}`}>
              <X className="w-5 h-5" />
            </button>

            <div className={`text-center flex-1 px-3 ${ts.text}`}>
              <p className="text-sm font-semibold truncate">{novel.title}</p>
              {activeChapter && (
                <p className="text-xs opacity-60">Chapter {activeChapter.number}</p>
              )}
            </div>

            <button onClick={() => setShowChapterList((v) => !v)} className={`p-2 rounded-lg ${ts.text}`}>
              <List className="w-5 h-5" />
            </button>
          </div>

          {showChapterList && (
            <div
              className={
                "absolute top-14 right-0 bottom-0 w-64 border-l z-10 overflow-y-auto p-3 backdrop-blur " +
                ts.nav +
                " " +
                ts.border
              }
            >
              <p className={`font-semibold mb-3 text-sm ${ts.text}`}>Daftar Chapter</p>

              <div className="grid grid-cols-3 gap-1.5">
                {(novel.chapters || []).map((ch) => (
                  <button
                    key={ch.id}
                    onClick={() => loadChapter(ch.number)}
                    className={
                      "px-2 py-2 rounded-lg text-xs font-medium border transition-all " +
                      (activeChapter?.number === ch.number
                        ? "bg-violet-600 text-white border-violet-600"
                        : `${ts.text} ${ts.border} ${ts.panel}`)
                    }
                  >
                    {ch.number}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div
            key={activeChapter?.number ?? 'load'}
            className="flex-1 overflow-y-auto"
            onClick={() => setShowControls((s) => !s)}
          >
            {chapterLoading ? (
              <div className={`flex items-center justify-center h-full ${ts.text}`}>
                <div className="animate-pulse text-sm">Memuat chapter...</div>
              </div>
            ) : activeChapter ? (
              <div className="max-w-3xl mx-auto px-5 py-8 md:py-10">
                <div className={`mb-8 text-center ${ts.text}`}>
                  <h2 className="text-lg md:text-xl font-bold mb-2">
                    Chapter {activeChapter.number}
                  </h2>
                  <p className="text-sm opacity-70">{activeChapter.title}</p>
                </div>

                {translating ? (
                  <div className={`text-center py-8 ${ts.text} opacity-70 animate-pulse`}>Menerjemahkan dengan Gemini AI...</div>
                ) : (
                  <div
                    className={ts.text + " reader-prose"}
                    style={{ fontSize: `${fontSize}px`, lineHeight: "1.9" }}
                    dangerouslySetInnerHTML={{ __html: translated || activeChapter.content }}
                  />
                )}
              </div>
            ) : null}
          </div>

          {activeChapter && (
            <div
              className={
                "flex items-center justify-center gap-4 px-4 py-3 border-t backdrop-blur transition-transform duration-300 " +
                ts.nav +
                " " +
                ts.border +
                (showControls ? "" : " translate-y-full absolute bottom-0 left-0 right-0")
              }
            >
              <button
                onClick={() => activeChapter.prev && loadChapter(activeChapter.prev)}
                disabled={!activeChapter.prev}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl border disabled:opacity-30 text-sm ${ts.text} ${ts.border}`}
              >
                <ChevronLeft className="w-4 h-4" /> Sebelumnya
              </button>

              <span className={`text-xs opacity-60 ${ts.text}`}>
                Ch {activeChapter.number}
              </span>

              <button
                onClick={() => activeChapter.next && loadChapter(activeChapter.next)}
                disabled={!activeChapter.next}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl border disabled:opacity-30 text-sm ${ts.text} ${ts.border}`}
              >
                Selanjutnya <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          )}

          {/* Floating settings button - hides with controls */}
          {showControls && (
            <button
              onClick={(e) => { e.stopPropagation(); setShowSettings(true); }}
              className="fixed bottom-20 right-4 z-20 w-12 h-12 rounded-full bg-zinc-700/90 text-white shadow-lg flex items-center justify-center hover:bg-zinc-600 transition-colors backdrop-blur"
              aria-label="Pengaturan baca"
            >
              <Settings className="w-5 h-5" />
            </button>
          )}

          {/* Settings bottom-sheet */}
          {showSettings && (
            <div className="fixed inset-0 z-30" onClick={() => setShowSettings(false)}>
              <div className="absolute inset-0 bg-black/50" />
              <div
                className={`absolute bottom-0 left-0 right-0 rounded-t-2xl p-5 ${ts.panel} ${ts.text} border-t ${ts.border}`}
                onClick={(e) => e.stopPropagation()}
              >
                <div className="w-10 h-1 rounded-full bg-current opacity-20 mx-auto mb-4" />
                <h3 className="font-bold text-base mb-4">Pengaturan Baca</h3>

                {/* Font size */}
                <div className="flex items-center justify-between mb-4">
                  <span className="text-sm opacity-70">Ukuran Font</span>
                  <div className="flex items-center gap-3">
                    <button onClick={() => setFontSize((f) => Math.max(12, f - 1))} className={`w-9 h-9 rounded-lg border flex items-center justify-center ${ts.border}`}><Minus className="w-4 h-4" /></button>
                    <span className="text-sm font-bold w-8 text-center">{fontSize}</span>
                    <button onClick={() => setFontSize((f) => Math.min(32, f + 1))} className={`w-9 h-9 rounded-lg border flex items-center justify-center ${ts.border}`}><Plus className="w-4 h-4" /></button>
                  </div>
                </div>

                {/* Theme */}
                <div className="flex items-center justify-between mb-4">
                  <span className="text-sm opacity-70">Tema</span>
                  <div className="flex items-center gap-2">
                    {([['dark','#0a0a0a','🌙'],['light','#ffffff','☀️'],['sepia','#f5ecd9','📜']] as const).map(([th, col, ic]) => (
                      <button key={th} onClick={() => setTheme(th as Theme)}
                        className={`w-10 h-10 rounded-lg border-2 flex items-center justify-center text-sm ${theme===th ? 'border-violet-500' : 'border-transparent'}`}
                        style={{ background: col, color: th==='dark'?'#fff':'#000' }}>{ic}</button>
                    ))}
                  </div>
                </div>

                {/* AI Translate */}
                <button
                  onClick={() => { setShowSettings(false); translateChapter(); }}
                  disabled={translating}
                  className={`w-full flex items-center justify-center gap-2 py-3 rounded-xl font-semibold mb-2 ${translated ? 'bg-violet-600 text-white' : 'border ' + ts.border}`}
                >
                  <Languages className="w-4 h-4" />
                  {translating ? 'Menerjemahkan...' : translated ? 'Tampilkan Teks Asli' : 'Terjemahkan AI (Gemini → Indonesia)'}
                </button>
                <p className="text-[11px] opacity-50 text-center">Pakai API key Gemini sendiri (gratis). Disimpan di browser kamu.</p>
              </div>
            </div>
          )}
        </div>
      )}

      <Footer />
    </div>
  );
};

export default NovelDetailPage;
