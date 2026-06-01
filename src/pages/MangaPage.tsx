import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { BookOpen, ChevronLeft, ChevronRight, Star, Info, Flame, Clock } from "lucide-react";
import { getPosterUrl, formatRating } from "@/services/api";

const GENRES = ["Action", "Adventure", "Comedy", "Drama", "Fantasy", "Horror", "Isekai", "Martial Arts", "Mystery", "Romance", "School", "Slice of Life", "Supernatural"];

interface Manga { id?: number; slug: string; title: string; poster_url: string; status: string; type?: string; rating: number; }

function fetchManga(params: Record<string,string|number>): Promise<{content: Manga[]; pages: number}> {
  const q = new URLSearchParams({ action: 'list', type: 'manga', limit: '24', order_dir: 'DESC', ...Object.fromEntries(Object.entries(params).map(([k,v])=>[k,String(v)])) });
  return fetch('/api/content.php?' + q).then(r => r.json()).then(d => ({
    content: (d.content || []).map((m: any) => ({ ...m, rating: parseFloat(m.rating) || 0 })),
    pages: d.pagination?.pages || 1,
  }));
}

function PosterCard({ item }: { item: Manga }) {
  return (
    <Link to={`/manga/${item.slug}`} className="group block">
      <div className="relative aspect-[2/3] rounded-lg overflow-hidden bg-secondary">
        <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} loading="lazy"
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }} />
        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
          <div className="w-11 h-11 rounded-full bg-primary/90 flex items-center justify-center"><BookOpen className="w-5 h-5 text-white" /></div>
        </div>
        {item.rating > 0 && (
          <div className="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-black/70 rounded px-1.5 py-0.5">
            <Star className="w-2.5 h-2.5 text-yellow-400 fill-yellow-400" /><span className="text-[10px] text-white font-medium">{formatRating(item.rating)}</span>
          </div>
        )}
        {item.type && <div className="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-primary text-white text-[9px] font-bold uppercase">{item.type}</div>}
      </div>
      <h3 className="mt-1.5 text-xs font-medium text-foreground/90 line-clamp-2 group-hover:text-primary transition-colors leading-tight">{item.title}</h3>
    </Link>
  );
}

function Row({ title, icon, params }: { title: string; icon: React.ReactNode; params: Record<string,string> }) {
  const [items, setItems] = useState<Manga[]>([]);
  useEffect(() => { fetchManga({ limit: 14, ...params }).then(d => setItems(d.content)).catch(() => {}); }, []);
  if (!items.length) return null;
  return (
    <section className="mb-7">
      <div className="flex items-center gap-2 mb-3"><span className="text-primary">{icon}</span><h2 className="font-display text-lg font-bold">{title}</h2></div>
      <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
        {items.map(item => <div key={item.slug} className="shrink-0 w-28 sm:w-32"><PosterCard item={item} /></div>)}
      </div>
    </section>
  );
}

const MangaPage = () => {
  const [featured, setFeatured] = useState<Manga[]>([]);
  const [heroIdx, setHeroIdx] = useState(0);
  const [items, setItems] = useState<Manga[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [genre, setGenre] = useState('');
  const [status, setStatus] = useState<'all'|'ongoing'|'completed'>('all');

  useEffect(() => { fetchManga({ limit: 6, order_by: 'updated_at' }).then(d => setFeatured(d.content.filter(c => c.poster_url))).catch(() => {}); }, []);
  useEffect(() => {
    if (featured.length < 2) return;
    const t = setInterval(() => setHeroIdx(i => (i + 1) % featured.length), 6000);
    return () => clearInterval(t);
  }, [featured]);

  useEffect(() => {
    let cancelled = false; setLoading(true);
    const p: Record<string,string|number> = { page, order_by: 'updated_at' };
    if (status !== 'all') p.status = status;
    if (genre) p.genre = genre;
    fetchManga(p).then(d => { if (!cancelled) { setItems(d.content); setTotalPages(d.pages); setLoading(false); } }).catch(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [page, genre, status]);

  const hero = featured[heroIdx];

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <section className="relative h-[58vh] min-h-[360px] flex items-end">
        <div className="absolute inset-0">
          {hero && <img referrerPolicy="no-referrer" key={hero.slug} src={getPosterUrl(hero.poster_url)} alt={hero.title} className="w-full h-full object-cover object-center animate-fade-in" onError={(e) => { (e.target as HTMLImageElement).style.opacity = '0'; }} />}
          <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-background/20" />
          <div className="absolute inset-0 bg-gradient-to-r from-background/80 to-transparent" />
        </div>
        <div className="container mx-auto px-4 relative z-10 pb-8">
          <div className="flex items-center gap-2 mb-2">
            <span className="px-2 py-0.5 rounded bg-primary text-white text-xs font-bold">📖 Top Komik</span>
            {hero?.rating > 0 && <span className="text-yellow-400 text-sm font-semibold flex items-center gap-1"><Star className="w-3.5 h-3.5 fill-yellow-400" />{formatRating(hero.rating)}</span>}
          </div>
          <h1 className="font-display text-3xl sm:text-5xl font-extrabold mb-3 max-w-xl leading-none">{hero?.title || 'Komik'}</h1>
          <div className="flex gap-3">
            <Link to={hero ? `/manga/${hero.slug}` : '#'} className="btn-textured inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-white font-semibold text-sm transition-all shadow-lg"><BookOpen className="w-4 h-4" /> Baca</Link>
            <Link to={hero ? `/manga/${hero.slug}` : '#'} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-secondary/80 hover:bg-secondary text-foreground font-medium text-sm transition-colors"><Info className="w-4 h-4" /> Detail</Link>
          </div>
          {featured.length > 1 && <div className="flex gap-1.5 mt-4">{featured.map((_, i) => <button key={i} onClick={() => setHeroIdx(i)} className={`h-1 rounded-full transition-all ${i === heroIdx ? 'w-6 bg-primary' : 'w-2 bg-white/30'}`} />)}</div>}
        </div>
      </section>

      <main className="container mx-auto px-4 -mt-2 relative z-10 pb-12">
        <Row title="Populer" icon={<Flame className="w-5 h-5" />} params={{ order_by: 'rating' }} />
        <Row title="Update Terbaru" icon={<Clock className="w-5 h-5" />} params={{ order_by: 'updated_at' }} />

        <section className="mb-5">
          <h2 className="font-display text-lg font-bold mb-3">Jelajahi Genre</h2>
          <div className="flex flex-wrap gap-2">
            <button onClick={() => { setGenre(''); setPage(1); }} className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all ${!genre ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground hover:bg-muted'}`}>Semua</button>
            {GENRES.map(g => <button key={g} onClick={() => { setGenre(g); setPage(1); }} className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all ${genre === g ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground hover:bg-muted'}`}>{g}</button>)}
          </div>
        </section>

        <section>
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-display text-lg font-bold">{genre || (status !== 'all' ? status : 'Semua Komik')}</h2>
            <div className="flex gap-1.5">
              {(['all','ongoing','completed'] as const).map(s => <button key={s} onClick={() => { setStatus(s); setPage(1); }} className={`px-2.5 py-1 rounded-full text-xs transition-all ${status === s ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground'}`}>{s === 'all' ? 'Semua' : s === 'ongoing' ? 'Ongoing' : 'Tamat'}</button>)}
            </div>
          </div>
          <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 md:gap-4">
            {loading ? Array(18).fill(0).map((_, i) => <div key={i}><div className="aspect-[2/3] rounded-lg bg-secondary animate-pulse" /><div className="mt-2 h-3 bg-secondary rounded animate-pulse w-4/5" /></div>)
              : items.length === 0 ? <div className="col-span-full text-center py-16 text-muted-foreground"><p className="text-4xl mb-3">📭</p><p>Tidak ada komik</p></div>
              : items.map(item => <PosterCard key={item.slug} item={item} />)}
          </div>
          {!loading && totalPages > 1 && (
            <div className="flex items-center justify-center gap-3 mt-8">
              <button onClick={() => setPage(p => Math.max(1, p-1))} disabled={page === 1} className="flex items-center gap-1 px-4 py-2 rounded-full text-sm bg-secondary disabled:opacity-40 hover:bg-muted transition-colors"><ChevronLeft className="w-4 h-4" /> Prev</button>
              <span className="text-sm text-muted-foreground">{page} / {totalPages}</span>
              <button onClick={() => setPage(p => Math.min(totalPages, p+1))} disabled={page === totalPages} className="flex items-center gap-1 px-4 py-2 rounded-full text-sm bg-secondary disabled:opacity-40 hover:bg-muted transition-colors">Next <ChevronRight className="w-4 h-4" /></button>
            </div>
          )}
        </section>
      </main>
      <Footer />
    </div>
  );
};

export default MangaPage;
