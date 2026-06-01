import { useState, useEffect, useRef, useMemo } from "react";
import { useState, useEffect, useRef } from "react";
import { useParams, Link, useSearchParams } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Tv, Play } from "lucide-react";

interface VideoItem { episode: number; vid: string; duration: number; url?: string; subtitle?: string; }
interface Drama {
  id: string; title: string; cover: string; synopsis: string;
  episodes: number; videos: VideoItem[];
}

const ShortDramaDetailPage = () => {

  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const source = searchParams.get("source") || "melolo";
  const [drama, setDrama] = useState<Drama | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeVid, setActiveVid] = useState<string | null>(null);
  const [activeEp, setActiveEp] = useState(1);
  const [videoUrl, setVideoUrl] = useState<string | null>(null);
  const [servers, setServers] = useState<{label: string, url: string}[]>([]);
  const [subtitleUrl, setSubtitleUrl] = useState<string | null>(null);
  const [loadingVideo, setLoadingVideo] = useState(false);
  const playerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!id) return;
    fetch(`/api/shortdrama-proxy.php?action=detail&id=${id}&source=${source}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) throw new Error(res.error);
        setDrama(res.data);
        setLoading(false);
        // Auto load episode 1
        if (res.data.videos?.length > 0) {
          loadVideo(res.data.videos[0].vid, 1, res.data.videos[0].url, res.data.videos[0].subtitle);
        }
      })
      .catch(err => { setError(err.message); setLoading(false); });
  }, [id]);

  const loadVideo = (vid: string, ep: number, directUrl?: string, directSubtitle?: string) => {
    setActiveVid(vid);
    setActiveEp(ep);
    setLoadingVideo(true);
    setVideoUrl(null);

    // Velolo sudah punya URL langsung
    if (directUrl) {
      setVideoUrl(directUrl);
      setServers([{ label: '480p', url: directUrl }]);
      if (directSubtitle) setSubtitleUrl('/api/subtitle-proxy.php?url=' + encodeURIComponent(directSubtitle));
      setLoadingVideo(false);
      setTimeout(() => playerRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
      return;
    }

    fetch(`/api/shortdrama-proxy.php?action=video&vid=${vid}&source=${source}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) throw new Error(res.error);
        setVideoUrl(res.data.url);
        setServers(res.data.servers || []);
        setLoadingVideo(false);
        setTimeout(() => playerRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
      })
      .catch(() => setLoadingVideo(false));
  };

  const formatDuration = (s: number) => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;

  if (loading) return (
    <div className="min-h-screen bg-background"><Navbar />
      <div className="pt-20 container mx-auto px-4">
        <div className="flex gap-6 mt-8">
          <div className="w-[140px] h-[210px] rounded-xl bg-secondary animate-pulse flex-shrink-0" />
          <div className="flex-1 space-y-3 pt-4">
            <div className="h-8 bg-secondary rounded w-2/3 animate-pulse" />
            <div className="h-4 bg-secondary rounded w-1/3 animate-pulse" />
            <div className="h-20 bg-secondary rounded animate-pulse" />
          </div>
        </div>
      </div>
    </div>
  );

  if (error || !drama) return (
    <div className="min-h-screen bg-background"><Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <Tv className="w-16 h-16 mb-4 opacity-30" />
        <p>Drama tidak ditemukan</p>
        <Link to="/shortdrama" className="mt-4 text-rose-400 hover:underline text-sm">← Kembali</Link>
      </div><Footer />
    </div>
  );

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="relative h-[220px]">
        <img src={drama.cover} alt={drama.title}
          className="w-full h-full object-cover object-top filter blur-md scale-105"
          onError={e => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/70 to-background/30" />
      </div>

      <main className="container mx-auto px-4 -mt-24 relative z-10 pb-16">
        <div className="flex flex-col md:flex-row gap-5">
          <div className="flex-shrink-0">
            <img src={drama.cover} alt={drama.title}
              className="w-[120px] md:w-[160px] rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.8)]"
              onError={e => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
          </div>
          <div className="flex-1 md:pt-12">
            <div className="flex items-center gap-1 text-xs text-muted-foreground mb-2">
              <Link to="/shortdrama" className="hover:text-rose-400">Short Drama</Link>
              <span>›</span><span className="truncate">{drama.title}</span>
            </div>
            <h1 className="text-xl font-black mb-2">{drama.title}</h1>
            <div className="flex gap-2 mb-3">
              <span className="px-2 py-0.5 rounded bg-secondary text-xs">📺 {drama.episodes} Episode</span>
            </div>
            {drama.synopsis && (
              <p className="text-muted-foreground text-sm leading-relaxed line-clamp-3">{drama.synopsis}</p>
            )}
          </div>
        </div>

        {/* Player */}
        <div ref={playerRef} className="mt-6">
          <p className="text-sm font-semibold mb-2 text-muted-foreground">
            Episode {activeEp}
          </p>
          <div className="relative w-full rounded-xl overflow-hidden bg-black" style={{paddingTop: '56.25%'}}>
            {loadingVideo ? (
              <div className="absolute inset-0 flex items-center justify-center bg-black">
                <div className="text-white/60 animate-pulse">Memuat video...</div>
              </div>
            ) : videoUrl ? (
              <video key={videoUrl} controls autoPlay playsInline
                className="absolute inset-0 w-full h-full"
                src={videoUrl}>
                {subtitleUrl && <track kind="subtitles" src={subtitleUrl} srcLang="id" label="Indonesia" default />}
                Browser tidak mendukung video.
              </video>
            ) : (
              <div className="absolute inset-0 flex items-center justify-center bg-black">
                <Tv className="w-12 h-12 text-white/20" />
              </div>
            )}
          </div>

          {/* Server selector */}
          {servers.length > 1 && (
            <div className="mt-3">
              <p className="text-xs text-muted-foreground mb-2">Pilih Kualitas:</p>
              <div className="flex flex-wrap gap-2">
                {servers.map(s => (
                  <button key={s.label} onClick={() => setVideoUrl(s.url)}
                    className={"px-3 py-1.5 rounded-lg text-xs font-medium border transition-all " + (videoUrl === s.url ? 'bg-rose-600 text-white border-rose-600' : 'bg-secondary border-border hover:border-rose-500/40')}>
                    {s.label}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Episode List */}
        {drama.videos?.length > 0 && (
          <div className="mt-8">
            <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
              <Tv className="w-5 h-5 text-rose-400" />
              Daftar Episode
              <span className="text-muted-foreground font-normal text-base">({drama.videos.length})</span>
            </h2>
            <div className="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-10 gap-2">
              {drama.videos.map(v => (
                <button key={v.vid} onClick={() => loadVideo(v.vid, v.episode, v.url, v.subtitle)}
                  className={"px-2 py-2.5 rounded-lg text-xs font-medium transition-all border " + (activeEp === v.episode ? 'bg-rose-600 text-white border-rose-600' : 'bg-secondary border-border hover:border-rose-500/40 hover:text-rose-400')}>
                  <div>{v.episode}</div>
                  <div className="text-[9px] opacity-60 mt-0.5">{formatDuration(v.duration)}</div>
                </button>
              ))}
            </div>
          </div>
        )}
      </main>
      <Footer />
    </div>
  );
};

export default ShortDramaDetailPage;
