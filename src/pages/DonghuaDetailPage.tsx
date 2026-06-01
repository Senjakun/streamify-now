import { useState, useEffect, useRef } from "react";
import { useParams, Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Play, Bookmark, Share2, ChevronRight, Video } from "lucide-react";
import { CommentSection } from "@/components/comments";
import { toggleBookmark, saveHistory } from "@/services/api";

interface Episode { id: number; number: number; title: string; slug: string; url: string; video_url: string; }
interface DonghuaDetail {
  slug: string; title: string; poster_url: string; description: string;
  status: string; genres: string[]; type: string; episodes: Episode[];
}

const DonghuaDetailPage = () => {
  const { slug } = useParams<{ slug: string }>();

  const [content, setContent]         = useState<DonghuaDetail | null>(null);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState<string | null>(null);
  const [activeEp, setActiveEp]       = useState<Episode | null>(null);
  const [embedUrl, setEmbedUrl]       = useState('');
  const [embedLoading, setEmbedLoading] = useState(false);
  const [servers, setServers]         = useState<{label:string;embed:string}[]>([]);
  const [downloads, setDownloads]     = useState<{label:string;url:string}[]>([]);
  const [bookmarked, setBookmarked]   = useState(false);
  const [contentId, setContentId]     = useState(0);
  const playerRef = useRef<HTMLDivElement>(null);

  // Fetch detail
  useEffect(() => {
    if (!slug) return;
    setLoading(true);
    fetch('/api/content.php?action=detail&slug=' + slug + '&type=donghua')
      .then(r => r.json())
      .then((res) => {
        if (!res || !res.id) throw new Error('Donghua tidak ditemukan');
        const mapped = {
          slug: res.slug,
          title: res.title,
          poster_url: res.poster_url || '',
          description: res.description ? (() => { const txt = document.createElement('textarea'); txt.innerHTML = res.description; return txt.value; })() : '',
          status: res.status || 'ongoing',
          genres: Array.isArray(res.genres) ? res.genres : (res.genres ? JSON.parse(res.genres) : []),
          type: 'donghua',
          episodes: (res.episodes || []).map((e: any) => ({
            number: e.episode_number,
            id: e.id || 0,
            title: e.title || 'Episode ' + e.episode_number,
            slug: e.source_url ? e.source_url.replace('https://anichin.cafe/', '').replace(/\/$/, '') : String(e.episode_number),
            url: e.source_url || '',
            video_url: e.video_url && e.video_url !== 'None' ? e.video_url : '',
          })),
        };
        setContent(mapped);
        setContentId(res.id);
        if (mapped.episodes?.length) setActiveEp(mapped.episodes[0]);
        setLoading(false);
      })
      .catch((err) => { setError(err.message); setLoading(false); });
  }, [slug]);

  // Save history saat contentId ready dan ada activeEp
  useEffect(() => {
    if (contentId && activeEp) {
      saveHistory({ content_id: contentId, episode_id: 0 });
    }
  }, [contentId]);

  // Fetch all servers from animexin (multi-sub: Indo/Eng/Mandarin × hosts)
  useEffect(() => {
    if (!activeEp) return;
    setEmbedLoading(true); setServers([]); setEmbedUrl(''); setDownloads([]);
    const title = content?.title || '';
    fetch(`/api/donghua-embed.php?url=${encodeURIComponent(activeEp.url || '')}&title=${encodeURIComponent(title)}&ep=${activeEp.number}`)
      .then(r => r.json())
      .then(d => {
        const list: {label:string;embed:string}[] = d.servers || [];
        setServers(list);
        setDownloads(d.downloads || []);
        const noAds = (s:{label:string}) => !/\[?\s*ads?\s*\]?/i.test(s.label);
        // prefer: Indonesia Dailymotion (no ads) > any non-ad Indonesia > any non-ad > first
        const pick = list.find(s => /indonesia.*dailymotion/i.test(s.label) && noAds(s))
          || list.find(s => /indonesia/i.test(s.label) && noAds(s))
          || list.find(noAds) || list[0];
        setEmbedUrl(pick?.embed || '');
        setEmbedLoading(false);
      })
      .catch(() => setEmbedLoading(false));
  }, [activeEp]);

  const handleEpClick = (ep: Episode) => {
    setActiveEp(ep);
    const token = localStorage.getItem('token');
    if (contentId && token) saveHistory({ content_id: contentId, episode_id: 0 });
    setTimeout(() => playerRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
  };

  if (loading) return (
    <div className="min-h-screen bg-background"><Navbar />
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
    <div className="min-h-screen bg-background"><Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <Video className="w-16 h-16 mb-4 opacity-30" />
        <p className="text-lg font-medium">Donghua tidak ditemukan</p>
        <Link to="/donghua" className="mt-4 text-primary hover:underline text-sm">← Kembali ke Donghua</Link>
      </div><Footer />
    </div>
  );

  const sortedEps = [...(content.episodes || [])].sort((a, b) => b.number - a.number);

  return (
    <div className="min-h-screen bg-background"><Navbar />

      <div className="relative h-[280px] md:h-[360px]">
        <img referrerPolicy="no-referrer" src={content.poster_url} alt={content.title}
          className="w-full h-full object-cover object-top filter blur-md scale-105"
          onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/70 to-background/30" />
      </div>

      <main className="container mx-auto px-4 -mt-36 relative z-10 pb-16">
        <div className="flex flex-col md:flex-row gap-6">
          <div className="flex-shrink-0">
            <img referrerPolicy="no-referrer" src={content.poster_url} alt={content.title}
              className="w-[150px] md:w-[200px] rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.8)]"
              onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
          </div>
          <div className="flex-1 md:pt-20">
            <div className="flex items-center gap-1 text-xs text-muted-foreground mb-3">
              <Link to="/donghua" className="hover:text-primary">Donghua</Link>
              <ChevronRight className="w-3 h-3" />
              <span className="text-foreground truncate">{content.title}</span>
            </div>
            <h1 className="text-2xl md:text-3xl font-black mb-3">{content.title}</h1>
            <div className="flex flex-wrap items-center gap-3 mb-3">
              <span className={"px-2 py-0.5 rounded text-xs font-semibold " + (content.status === 'ongoing' ? 'bg-orange-500/20 text-orange-400' : 'bg-green-500/20 text-green-400')}>
                {content.status === 'ongoing' ? 'Ongoing' : 'Tamat'}
              </span>
              <span className="text-sm text-muted-foreground">🎬 {sortedEps.length} Episode</span>
            </div>
            {content.genres?.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-4">
                {content.genres.map((g) => <span key={g} className="px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-sm">{g}</span>)}
              </div>
            )}
            {content.description && <p className="text-muted-foreground text-sm leading-relaxed mb-5 max-w-2xl">{content.description}</p>}
            <div className="flex items-center gap-3 flex-wrap">
              {activeEp && (
                <button onClick={() => playerRef.current?.scrollIntoView({ behavior: 'smooth' })}
                  className="flex items-center gap-2 px-5 py-2.5 rounded-full bg-amber-500 text-black font-bold text-sm hover:bg-amber-400 transition-colors">
                  <Play className="w-4 h-4 fill-current" /> Tonton EP {activeEp.number}
                </button>
              )}
              <button onClick={async () => {
                const token = localStorage.getItem('token');
                if (!token || !contentId) return;
                const res = await toggleBookmark(contentId, token);
                setBookmarked(res.bookmarked);
              }} className={"p-2.5 rounded-full border transition-all " + (bookmarked ? 'border-amber-500 text-amber-500' : 'border-border hover:border-amber-500/50')}>
                <Bookmark className={"w-4 h-4 " + (bookmarked ? 'fill-current' : '')} />
              </button>
              <button onClick={() => navigator.share?.({ title: content.title, url: window.location.href })}
                className="p-2.5 rounded-full border border-border hover:border-amber-500/50 transition-all">
                <Share2 className="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        {/* Player */}
        <div ref={playerRef} className="mt-10">
          {embedLoading && (
            <div className="rounded-2xl bg-black aspect-video flex items-center justify-center">
              <div className="text-white/50 text-sm animate-pulse">Memuat player...</div>
            </div>
          )}
          {!embedLoading && embedUrl && (
            <div className="rounded-2xl overflow-hidden bg-black aspect-video">
              <iframe src={embedUrl} className="w-full h-full" allowFullScreen allow="autoplay; fullscreen; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation" referrerPolicy="no-referrer" />
            </div>
          )}
          {!embedLoading && servers.length > 0 && (
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <span className="text-xs text-muted-foreground shrink-0">Server:</span>
              <select value={embedUrl} onChange={(e) => setEmbedUrl(e.target.value)}
                className="flex-1 min-w-[160px] px-3 py-2 rounded-lg bg-secondary border border-border text-sm font-medium text-foreground focus:border-amber-500/60 outline-none cursor-pointer">
                {servers.map((s) => (
                  <option key={s.label} value={s.embed}>{s.label}</option>
                ))}
              </select>
              {downloads.length > 0 && (
                <select defaultValue="" title="Download episode" onChange={(e) => { if (e.target.value) { window.open(e.target.value, '_blank'); e.target.value=''; } }}
                  className="w-12 px-2 py-2 rounded-lg bg-green-600/90 border border-green-500 text-sm font-semibold text-white outline-none cursor-pointer text-center">
                  <option value="" disabled>⬇</option>
                  {downloads.map((d) => (<option key={d.label} value={d.url} className="bg-secondary text-foreground">{d.label}</option>))}
                </select>
              )}
            </div>
          )}
          {!embedLoading && !embedUrl && activeEp && (
            <div className="rounded-2xl bg-black aspect-video flex items-center justify-center">
              <div className="text-center text-white/50">
                <Video className="w-12 h-12 mx-auto mb-2 opacity-30" />
                <p className="text-sm">Embed tidak tersedia</p>
              </div>
            </div>
          )}
        </div>

        {/* Episode list */}
        {sortedEps.length > 0 && (
          <div className="mt-10">
            <h2 className="text-xl font-bold mb-4 flex items-center gap-2">
              <Video className="w-5 h-5 text-amber-400" />
              Daftar Episode
              <span className="text-muted-foreground font-normal text-base">({sortedEps.length})</span>
            </h2>
            <div className="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 max-h-[320px] overflow-y-auto pr-2">
              {sortedEps.map((ep) => (
                <button key={ep.number} onClick={() => handleEpClick(ep)}
                  className={"px-3 py-2 rounded-lg border text-sm font-medium transition-all " + (activeEp?.number === ep.number ? 'bg-amber-500 border-amber-500 text-black' : 'bg-secondary border-border hover:border-amber-500/40 hover:text-amber-400')}>
                  {ep.number}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="mt-14">
          <CommentSection category="donghua" contentId={contentId} />
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default DonghuaDetailPage;
