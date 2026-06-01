import { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Play, Search, Menu, X, Bell, LogOut, User, Bookmark, History, Settings, ChevronRight, Star, Flame, Sparkles, Tv, Video, BookOpen, FileText, Clapperboard, Crown, Plus, Info, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { useAuth } from "@/contexts/AuthContext";
import { fetchContent, getPosterUrl, formatRating, Content } from "@/services/api";
import { fetchNovelListPage, FrontNovel } from "@/lib/novelApi";

function DashboardNavbar() {
  const { user, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const navigate = useNavigate();
  const handleSearch = (e: React.FormEvent) => { e.preventDefault(); if (searchQuery.trim()) navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`); };
  const initials = user?.username?.slice(0,2).toUpperCase() || "U";
  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-background/90 backdrop-blur-xl border-b border-border">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <Link to="/" className="flex items-center gap-2">
            <img src="/assets/logo.png" alt="PlayAll" className="w-9 h-9 rounded-lg object-cover" />
            <span className="font-display text-xl font-extrabold uppercase tracking-tight hidden sm:block">play<span className="text-primary">all</span></span>
          </Link>
          <div className="hidden md:flex items-center gap-6">
            {[{l:"Beranda",h:"/"},{l:"Anime",h:"/anime"},{l:"Donghua",h:"/donghua"},{l:"Manga",h:"/manga"}].map((x) => (
              <Link key={x.l} to={x.h} className="text-muted-foreground hover:text-foreground transition-colors font-medium text-sm">{x.l}</Link>
            ))}
          </div>
          <div className="flex items-center gap-2">
            <form onSubmit={handleSearch} className="hidden md:flex relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
              <Input value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari..." className="w-48 pl-9 h-9 bg-secondary border-border text-sm" />
            </form>
            <Link to="/announcements" className="relative p-2 rounded-full hover:bg-muted/50 transition-colors flex items-center justify-center"><Bell className="w-4 h-4" /><span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-primary rounded-full" /></Link>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="flex items-center gap-2 px-2 h-9">
                  <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-xs font-bold text-white overflow-hidden">{user?.avatar_url ? <img referrerPolicy="no-referrer" src={user.avatar_url} alt={user.username} className="w-full h-full object-cover" /> : initials}</div>
                  <span className="hidden sm:block text-sm font-medium max-w-20 truncate">{user?.username}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-52">
                <div className="px-3 py-2"><p className="text-sm font-semibold">{user?.username}</p><p className="text-xs text-muted-foreground">{user?.email}</p></div>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => window.location.href="/profile"}><User className="w-4 h-4 mr-2" />Profil Saya</DropdownMenuItem>
                <DropdownMenuItem asChild><Link to="/bookmarks"><Bookmark className="w-4 h-4 mr-2" />Bookmark</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link to="/history"><History className="w-4 h-4 mr-2" />Riwayat Tontonan</Link></DropdownMenuItem>
                <DropdownMenuItem><Settings className="w-4 h-4 mr-2" />Pengaturan</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={logout} className="text-primary focus:text-primary"><LogOut className="w-4 h-4 mr-2" />Keluar</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}</Button>
          </div>
        </div>
        {menuOpen && (
          <div className="md:hidden pb-4 border-t border-border pt-4 flex flex-col gap-1">
            {[{l:"Beranda",h:"/"},{l:"Anime",h:"/anime"},{l:"Donghua",h:"/donghua"},{l:"Manga",h:"/manga"}].map((x) => (
              <Link key={x.l} to={x.h} onClick={() => setMenuOpen(false)} className="py-2 px-3 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground text-sm">{x.l}</Link>
            ))}
          </div>
        )}
      </div>
    </nav>
  );
}

function FeaturedBanner() {
  const [featured, setFeatured] = useState<Content | null>(null);
  useEffect(() => {
    fetchContent({ type: "anime", limit: 1, order_by: "rating", order_dir: "DESC" })
      .then((d) => setFeatured(d.content[0])).catch(() => {});
  }, []);
  const item = featured;
  return (
    <section className="relative min-h-[70vh] flex items-end overflow-hidden pt-16">
      <div className="absolute inset-0">
        <img referrerPolicy="no-referrer" src={item ? getPosterUrl(item.poster_url) : "https://images.unsplash.com/photo-1578632767115-351597cf2477?w=1920&h=1080&fit=crop"}
          alt="Featured" className="w-full h-full object-cover object-top"
          onError={(e) => { (e.target as HTMLImageElement).src = "https://images.unsplash.com/photo-1578632767115-351597cf2477?w=1920&h=1080&fit=crop"; }} />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-background via-transparent to-transparent" />
      </div>
      <div className="container mx-auto px-4 relative z-10 pb-12 md:pb-20">
        <div className="max-w-2xl">
          <div className="flex items-center gap-3 mb-4">
            <Badge className="bg-primary text-primary-foreground">Trending #1</Badge>
            <Badge variant="outline" className="border-foreground/30 text-foreground">Anime</Badge>
          </div>
          <h1 className="text-3xl sm:text-4xl md:text-5xl font-black text-foreground mb-4">{item?.title || "Sousou no Frieren"}</h1>
          <div className="flex flex-wrap items-center gap-4 mb-4 text-sm text-muted-foreground">
            {item?.rating && item.rating > 0 && (
              <div className="flex items-center gap-1"><Star className="w-4 h-4 text-yellow-500 fill-yellow-500" /><span className="text-foreground font-medium">{formatRating(item.rating)}</span></div>
            )}
            {item?.year && <span>{item.year}</span>}
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${item?.status === "ongoing" ? "bg-orange-500/20 text-orange-400" : "bg-green-500/20 text-green-400"}`}>
              {item?.status === "ongoing" ? "Ongoing" : "Completed"}
            </span>
          </div>
          {item?.description && <p className="text-muted-foreground mb-6 line-clamp-3 max-w-xl text-sm">{item.description}</p>}
          <div className="flex flex-wrap gap-3">
            <Button size="lg" className="btn-textured text-white shadow-lg gap-2" asChild>
              <Link to={item ? `/anime/${item.slug}` : "/anime"}><Play className="w-5 h-5 fill-current" />Tonton Sekarang</Link>
            </Button>
            <Button size="lg" variant="outline" className="gap-2 border-foreground/30 hover:bg-foreground/10">
              <Plus className="w-5 h-5" />Watchlist
            </Button>
            <Button size="lg" variant="ghost" className="gap-2" asChild>
              <Link to={item ? `/anime/${item.slug}` : "/anime"}><Info className="w-5 h-5" />Detail</Link>
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
}

function QuickCategories() {
  const links = [
    {icon:Flame,label:"Trending",color:"from-red-500 to-orange-500",href:"#trending"},
    {icon:Sparkles,label:"Terbaru",color:"from-amber-500 to-yellow-500",href:"#terbaru"},
    {icon:Tv,label:"Anime",color:"from-rose-500 to-red-500",href:"/anime"},
    {icon:Video,label:"Donghua",color:"from-orange-500 to-amber-500",href:"/donghua"},
    {icon:BookOpen,label:"Manga",color:"from-cyan-500 to-teal-500",href:"/manga"},
    {icon:FileText,label:"Novel",color:"from-violet-500 to-purple-500",href:"/novel"},
  ];
  return (
    <section className="py-5 border-b border-border">
      <div className="container mx-auto px-4">
        <div className="flex gap-3 overflow-x-auto pb-1 -mx-4 px-4 scrollbar-hide">
          {links.map((item) => {
            const cls = "group flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full bg-secondary hover:bg-secondary/80 border border-border hover:border-primary/50 transition-all";
            const inner = (<><div className={`w-7 h-7 rounded-full bg-gradient-to-br ${item.color} flex items-center justify-center`}><item.icon className="w-3.5 h-3.5 text-white" /></div><span className="text-sm font-medium whitespace-nowrap">{item.label}</span></>);
            return item.href.startsWith("#")
              ? <a key={item.label} href={item.href} onClick={(e) => { e.preventDefault(); document.querySelector(item.href)?.scrollIntoView({ behavior: "smooth" }); }} className={cls}>{inner}</a>
              : <Link key={item.label} to={item.href} className={cls}>{inner}</Link>;
          })}
        </div>
      </div>
    </section>
  );
}

function ContentRow({ title, type, href, emoji }: { title: string; type: string; href: string; emoji: string }) {
  const [items, setItems] = useState<Content[]>([]);
  useEffect(() => {
    fetchContent({ type: type as any, limit: 12, order_by: "updated_at", order_dir: "DESC" })
      .then((d) => setItems(d.content)).catch(() => {});
  }, [type]);
  if (!items.length) return null;
  return (
    <section className="py-6">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-black">{emoji} {title}</h2>
          <Button variant="ghost" size="sm" className="text-primary hover:text-primary/80 gap-1" asChild>
            <Link to={href}>Lihat Semua <ChevronRight className="w-3.5 h-3.5" /></Link>
          </Button>
        </div>
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
          {items.map((item) => (
            <Link key={item.id} to={`/${type}/${item.slug}`} className="group relative rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.04] hover:shadow-lg">
              <div className="aspect-[3/4] relative overflow-hidden">
                <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                  <div className="w-10 h-10 rounded-full bg-primary/90 flex items-center justify-center"><Play className="w-4 h-4 text-white fill-white ml-0.5" /></div>
                </div>
                {item.rating > 0 && (
                  <div className="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-black/70 rounded px-1 py-0.5">
                    <Star className="w-2.5 h-2.5 text-yellow-500 fill-yellow-500" /><span className="text-[10px] text-white font-medium">{formatRating(item.rating)}</span>
                  </div>
                )}
              </div>
              <div className="p-2"><h3 className="font-semibold text-[11px] line-clamp-2 group-hover:text-primary transition-colors leading-tight">{item.title}</h3></div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}

const typeColors: Record<string, string> = {
  anime: "bg-rose-500", donghua: "bg-amber-500", manga: "bg-cyan-500", novel: "bg-violet-500",
};
const typeIcons: Record<string, any> = {
  anime: Tv, donghua: Video, manga: BookOpen, novel: FileText,
};

function Recommendations() {
  const [items, setItems] = useState<Content[]>([]);
  useEffect(() => {
    fetchContent({ limit: 12, order_by: "rating", order_dir: "DESC" })
      .then((d) => setItems(d.content)).catch(() => {});
  }, []);
  if (!items.length) return null;
  return (
    <section className="py-6">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2"><Sparkles className="w-5 h-5 text-primary" /><h2 className="text-lg font-black">Rekomendasi Untukmu</h2></div>
          <Button variant="ghost" size="sm" className="text-primary gap-1" asChild><Link to="/anime">Lihat Semua <ChevronRight className="w-3.5 h-3.5" /></Link></Button>
        </div>
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
          {items.map((item) => {
            const TypeIcon = typeIcons[item.type] || Tv;
            const href = `/${item.type}/${item.slug}`;
            return (
              <Link key={item.id} to={href} className="group rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.04] hover:shadow-lg">
                <div className="aspect-[3/4] relative overflow-hidden">
                  <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                  <div className={`absolute top-1.5 left-1.5 w-6 h-6 rounded-full ${typeColors[item.type] || "bg-primary"} flex items-center justify-center`}>
                    <TypeIcon className="w-3 h-3 text-white" />
                  </div>
                  <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                    <div className="w-10 h-10 rounded-full bg-primary/90 flex items-center justify-center"><Play className="w-4 h-4 text-white fill-white ml-0.5" /></div>
                  </div>
                  {item.rating > 0 && (
                    <div className="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-black/70 rounded px-1 py-0.5">
                      <Star className="w-2.5 h-2.5 text-yellow-500 fill-yellow-500" /><span className="text-[10px] text-white">{formatRating(item.rating)}</span>
                    </div>
                  )}
                </div>
                <div className="p-2"><h3 className="font-semibold text-[11px] line-clamp-2 group-hover:text-primary transition-colors leading-tight">{item.title}</h3></div>
              </Link>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function MixedRow({ title, emoji, orderBy, id }: { title: string; emoji: string; orderBy: string; id?: string }) {
  const [items, setItems] = useState<Content[]>([]);
  useEffect(() => {
    const types = ["anime", "donghua", "manga"];
    Promise.all(types.map((t) =>
      fetchContent({ type: t as any, limit: 6, order_by: orderBy, order_dir: "DESC" }).then((d) => d.content).catch(() => [])
    )).then((lists) => {
      const mixed: Content[] = [];
      for (let i = 0; i < 6; i++) for (const l of lists) if (l[i]) mixed.push(l[i]);
      setItems(mixed.slice(0, 12));
    });
  }, [orderBy]);
  if (!items.length) return null;
  return (
    <section id={id} className="py-6 scroll-mt-20">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between mb-4"><h2 className="text-lg font-black">{emoji} {title}</h2></div>
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
          {items.map((item) => {
            const TypeIcon = typeIcons[item.type] || Tv;
            return (
              <Link key={`${item.type}-${item.id}`} to={`/${item.type}/${item.slug}`} className="group rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.04] hover:shadow-lg">
                <div className="aspect-[3/4] relative overflow-hidden">
                  <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                  <div className={`absolute top-1.5 left-1.5 w-6 h-6 rounded-full ${typeColors[item.type] || "bg-primary"} flex items-center justify-center`}><TypeIcon className="w-3 h-3 text-white" /></div>
                  {item.rating > 0 && (
                    <div className="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-black/70 rounded px-1 py-0.5">
                      <Star className="w-2.5 h-2.5 text-yellow-500 fill-yellow-500" /><span className="text-[10px] text-white font-medium">{formatRating(item.rating)}</span>
                    </div>
                  )}
                </div>
                <div className="p-2"><h3 className="font-semibold text-[11px] line-clamp-2 group-hover:text-primary transition-colors leading-tight">{item.title}</h3></div>
              </Link>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function NovelRow() {
  const [items, setItems] = useState<FrontNovel[]>([]);
  useEffect(() => {
    fetchNovelListPage({ limit: 10, orderby: "terbaru" })
      .then((d) => setItems(d.content)).catch(() => {});
  }, []);
  if (!items.length) return null;
  return (
    <section className="py-6">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-black">📚 Update Novel</h2>
          <Button variant="ghost" size="sm" className="text-primary hover:text-primary/80 gap-1" asChild>
            <Link to="/novel">Lihat Semua <ChevronRight className="w-3.5 h-3.5" /></Link>
          </Button>
        </div>
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
          {items.map((item) => (
            <Link key={item.slug} to={`/novel/${item.slug}`} className="group rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.04] hover:shadow-lg">
              <div className="aspect-[3/4] relative overflow-hidden">
                <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} loading="lazy" className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                <div className="absolute top-1.5 left-1.5 w-6 h-6 rounded-full bg-violet-500 flex items-center justify-center"><FileText className="w-3 h-3 text-white" /></div>
                <div className="absolute bottom-1.5 right-1.5 bg-black/70 rounded px-1 py-0.5 text-[10px] text-white">{item.total_chapters} ch</div>
              </div>
              <div className="p-2"><h3 className="font-semibold text-[11px] line-clamp-2 group-hover:text-primary transition-colors leading-tight">{item.title}</h3></div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}

export default function DashboardPage() {
  return (
    <div className="min-h-screen bg-background">
      <DashboardNavbar />
      <FeaturedBanner />
      <QuickCategories />
      <MixedRow emoji="🔥" title="Trending" orderBy="rating" id="trending" />
      <MixedRow emoji="🆕" title="Terbaru" orderBy="updated_at" id="terbaru" />
      <ContentRow emoji="📺" title="Update Anime" type="anime" href="/anime" />
      <ContentRow emoji="🎬" title="Update Donghua" type="donghua" href="/donghua" />
      <ContentRow emoji="📖" title="Update Komik" type="manga" href="/manga" />
      <NovelRow />
    </div>
  );
}

// ── PLACEHOLDER EXPORT (sudah ada di atas) ──
// File ini sudah lengkap
