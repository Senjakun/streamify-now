import React, { useState, useEffect } from "react";
import { useParams, Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Button } from "@/components/ui/button";
import { Bookmark, Share2, Star, ChevronRight } from "lucide-react";
import { toggleBookmark } from "@/services/api";
import { CommentSection } from "@/components/comments";
import { fetchDetail, getPosterUrl, formatRating, ContentDetail, Episode } from "@/services/api";
import { saveHistory } from "@/services/api";


const DescriptionBox = ({ text }: { text: string }) => {
  const [expanded, setExpanded] = useState(false);
  const isLong = text.length > 300;
  return (
    <div className="mb-6">
      <p className={`text-muted-foreground text-sm leading-relaxed ${!expanded && isLong ? "line-clamp-4" : ""}`}>
        {text}
      </p>
      {isLong && (
        <button onClick={() => setExpanded(!expanded)} className="text-primary text-xs mt-1 hover:underline">
          {expanded ? "Sembunyikan" : "Selengkapnya..."}
        </button>
      )}
    </div>
  );
};

const AnimeDetailPage = () => {
  const { slug } = useParams<{ slug: string }>();
  const [content, setContent] = useState<ContentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeEpisode, setActiveEpisode] = useState<Episode | null>(null);
  const [bookmarked, setBookmarked] = useState(false);
  const [embedUrl, setEmbedUrl] = useState<string | null>(null);
  const [loadingEmbed, setLoadingEmbed] = useState(true);
  const [servers, setServers] = useState<{label: string, token?: string, embed?: string}[]>([]);
  const [downloads, setDownloads] = useState<{label: string, url: string}[]>([]);
  const [resolver, setResolver] = useState<{rurl: string, rkey: string}>({rurl: '', rkey: ''});
  const [activeIdx, setActiveIdx] = useState(0);

  const pickServer = (idx: number, list = servers, r = resolver) => {
    const s = list[idx]; if (!s) return;
    setActiveIdx(idx);
    if (s.embed) { setEmbedUrl(s.embed); setLoadingEmbed(false); return; } // direct (reliable main server)
    setEmbedUrl(null); setLoadingEmbed(true);
    fetch(`/api/anime-embed.php?token=${encodeURIComponent(s.token || '')}&rurl=${encodeURIComponent(r.rurl)}&rkey=${encodeURIComponent(r.rkey)}`)
      .then(r => r.json())
      .then(d => { setEmbedUrl(d.embed || null); setLoadingEmbed(false); })
      .catch(() => setLoadingEmbed(false));
  };

  useEffect(() => {
    if (!activeEpisode?.source_url) return;
    setEmbedUrl(null); setLoadingEmbed(true); setServers([]); setDownloads([]);
    fetch(`/api/anime-embed.php?url=${encodeURIComponent(activeEpisode.source_url)}`)
      .then(r => r.json())
      .then(d => {
        const srv = d.servers || [];
        setServers(srv); setDownloads(d.downloads || []);
        const r = {rurl: d.rurl || '', rkey: d.rkey || ''};
        setResolver(r);
        // default: Server Utama (direct) > 720p > first
        let idx = srv.findIndex((s: any) => s.embed);
        if (idx < 0) idx = srv.findIndex((s: any) => /720p/i.test(s.label));
        if (idx < 0) idx = 0;
        if (srv.length) pickServer(idx, srv, r);
        else setLoadingEmbed(false);
      })
      .catch(() => setLoadingEmbed(false));
  }, [activeEpisode?.source_url]);

  const _legacyEmbed = () => {
    fetch(`/api/get-embed.php?url=${encodeURIComponent(activeEpisode!.source_url)}`)
      .then(r => r.json())
      .then(d => { setEmbedUrl(d.embed); setLoadingEmbed(false); })
      .catch(() => setLoadingEmbed(false));
  };

  useEffect(() => {
    if (!slug) return;
    setLoading(true);
    fetchDetail(slug)
      .then((data) => {
        const normalized = { ...data, rating: parseFloat(String(data.rating)) || 0, genres: Array.isArray(data.genres) ? data.genres : [] };
        setContent(normalized as ContentDetail);
        if (data.episodes && data.episodes.length > 0) {
          setActiveEpisode(data.episodes[data.episodes.length - 1]);
        }
        setLoading(false);
      })
      .catch((err) => { setError(err.message); setLoading(false); });
  }, [slug]);

  if (loading) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="pt-20 container mx-auto px-4">
        <div className="flex gap-6 mt-8 animate-pulse">
          <div className="w-[180px] h-[270px] rounded-lg bg-secondary flex-shrink-0" />
          <div className="flex-1 space-y-3 pt-4">
            <div className="h-8 bg-secondary rounded w-2/3" />
            <div className="h-4 bg-secondary rounded w-1/4" />
            <div className="h-20 bg-secondary rounded" />
          </div>
        </div>
      </div>
    </div>
  );

  if (error || !content) return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <p className="text-lg font-medium">Anime tidak ditemukan</p>
        <Link to="/anime" className="mt-4 text-primary hover:underline text-sm">← Kembali ke Anime</Link>
      </div>
      <Footer />
    </div>
  );

  const episodes = content?.episodes || [];
  const videoUrl = activeEpisode?.video_url && activeEpisode.video_url !== 'None' ? activeEpisode.video_url : null;
  const hasEmbed = embedUrl || videoUrl;

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="relative h-[300px] md:h-[400px]">
        <img referrerPolicy="no-referrer" src={getPosterUrl(content.banner_url || content.poster_url)} alt={content.title} className="w-full h-full object-cover" onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/50 to-transparent" />
      </div>

      <main className="container mx-auto px-4 -mt-32 relative z-10 pb-12">
        <div className="flex flex-col md:flex-row gap-6">
          <div className="flex-shrink-0">
            <img referrerPolicy="no-referrer" src={getPosterUrl(content.poster_url)} alt={content.title} className="w-[180px] md:w-[220px] rounded-lg shadow-lg" onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-1 text-xs text-muted-foreground mb-3">
              <Link to="/anime" className="hover:text-primary">Anime</Link>
              <ChevronRight className="w-3 h-3" />
              <span className="text-foreground truncate">{content.title}</span>
            </div>
            <h1 className="text-2xl md:text-4xl font-bold text-foreground mb-2">{content.title}</h1>
            {content.title_alt && <p className="text-sm text-muted-foreground mb-2">{content.title_alt}</p>}
            <div className="flex items-center gap-3 mb-4 flex-wrap">
              <span className="flex items-center gap-1 text-primary font-semibold"><Star className="w-4 h-4 fill-current" />{formatRating(content.rating)}</span>
              {content.year && <span className="text-muted-foreground text-sm">{content.year}</span>}
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${content.status === 'ongoing' ? 'bg-orange-500/20 text-orange-400' : 'bg-green-500/20 text-green-400'}`}>{content.status === 'ongoing' ? 'Ongoing' : 'Completed'}</span>
            </div>
            {content.genres?.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-4">
                {content.genres.map((g) => <span key={g} className="px-3 py-1 rounded-full bg-secondary text-secondary-foreground text-xs">{g}</span>)}
              </div>
            )}
            {content.description && <DescriptionBox text={content.description} />}
            <div className="flex items-center gap-3">
              <Button variant="outline" size="icon" onClick={async () => { const token = localStorage.getItem("token"); if (!token) return; const res = await toggleBookmark(content!.id, token); setBookmarked(res.bookmarked); }} className={bookmarked ? 'text-primary border-primary/50' : ''}>
                <Bookmark className={`w-4 h-4 ${bookmarked ? 'fill-current' : ''}`} />
              </Button>
              <Button variant="outline" size="icon">
                <Share2 className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Video Player */}
        {activeEpisode && (
          <div className="mt-8">
            <h2 className="text-xl font-bold text-foreground mb-4">
              Episode {activeEpisode.episode_number}
              {activeEpisode.title && activeEpisode.title !== `Episode ${activeEpisode.episode_number}` ? ` - ${activeEpisode.title}` : ''}
            </h2>
            {loadingEmbed ? (
              <div className="relative w-full rounded-xl overflow-hidden bg-black flex items-center justify-center" style={{paddingTop: '56.25%'}}>
                <div className="absolute inset-0 flex items-center justify-center"><p className="text-muted-foreground animate-pulse">Memuat video...</p></div>
              </div>
            ) : hasEmbed ? (
              <div className="relative w-full rounded-xl overflow-hidden bg-black" style={{paddingTop: '56.25%'}}>
                <iframe
                  src={embedUrl || videoUrl || ""}
                  className="absolute inset-0 w-full h-full"
                  allowFullScreen
                  allow="fullscreen; picture-in-picture; autoplay; encrypted-media"
                  sandbox="allow-scripts allow-same-origin allow-presentation"
                  referrerPolicy="no-referrer"
                  title={`Episode ${activeEpisode.episode_number}`}

                />
              </div>
            ) : null}
            {(servers.length > 0 || downloads.length > 0) && (
              <div className="mt-3 flex flex-wrap items-center gap-2">
                {servers.length > 0 && (
                  <>
                    <span className="text-xs text-muted-foreground shrink-0">Server / Resolusi:</span>
                    <select value={activeIdx} onChange={(e) => pickServer(Number(e.target.value))}
                      className="flex-1 min-w-[160px] px-3 py-2 rounded-lg bg-secondary border border-border text-sm font-medium text-foreground focus:border-primary/60 outline-none cursor-pointer">
                      {servers.map((s, i) => (<option key={i} value={i}>{s.label}</option>))}
                    </select>
                  </>
                )}
                {downloads.length > 0 && (
                  <select defaultValue="" title="Download episode" onChange={(e) => { if (e.target.value) { window.open(e.target.value, '_blank'); e.target.value=''; } }}
                    className="w-12 px-2 py-2 rounded-lg bg-green-600/90 border border-green-500 text-sm font-semibold text-white outline-none cursor-pointer text-center">
                    <option value="" disabled>⬇</option>
                    {downloads.map((d, i) => (<option key={i} value={d.url} className="bg-secondary text-foreground">{d.label}</option>))}
                  </select>
                )}
              </div>
            )}
          </div>
        )}

        {/* Episode List */}
        {episodes.length > 0 && (
          <div className="mt-8">
            <h2 className="text-xl font-bold text-foreground mb-4">Daftar Episode ({episodes.length})</h2>
            <div className="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-12 gap-2 max-h-[320px] overflow-y-auto pr-1">
              {[...episodes].sort((a,b) => a.episode_number - b.episode_number).map((ep) => (
                <button key={ep.id} onClick={() => { setActiveEpisode(ep); if (content) saveHistory({ content_id: content.id, episode_id: ep.id }); }}
                  className={`py-3 px-2 rounded-lg text-sm font-medium transition-all text-center ${activeEpisode?.id === ep.id ? 'bg-primary text-primary-foreground' : 'bg-secondary hover:bg-secondary/80 text-foreground'}`}>
                  {ep.episode_number}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="mt-12">
          <CommentSection category="anime" contentId={content.id} episodeId={activeEpisode?.id} />
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default AnimeDetailPage;
