import { useState, useEffect, useRef } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Play, Sparkles, Tv, BookOpen, Video, FileText, Star, ChevronRight, Search, Menu, X, Eye, EyeOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/contexts/AuthContext";
import { cn } from "@/lib/utils";
import { fetchContent, getPosterUrl, formatRating, Content } from "@/services/api";

function AuthModal({ mode, onClose, onSwitch }: { mode: "login"|"register"; onClose: ()=>void; onSwitch: ()=>void }) {
  const { login, loginGoogle, register } = useAuth();
  const [email, setEmail] = useState(""); const [password, setPassword] = useState(""); const [username, setUsername] = useState("");
  const [showPw, setShowPw] = useState(false); const [error, setError] = useState(""); const [loading, setLoading] = useState(false);
  const googleBtnRef = useRef<HTMLDivElement>(null);
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setError(""); setLoading(true);
    const res = mode === "login" ? await login(email, password) : await register(username, email, password);
    setLoading(false);
    if (res.error) setError(res.error); else onClose();
  };
  useEffect(() => {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
    if (!clientId) return;
    const render = () => {
      const g = (window as any).google;
      if (!g?.accounts?.id || !googleBtnRef.current) return;
      g.accounts.id.initialize({
        client_id: clientId,
        callback: async (resp: any) => {
          const res = await loginGoogle(resp.credential);
          if (res.error) setError(res.error); else onClose();
        },
      });
      g.accounts.id.renderButton(googleBtnRef.current, { theme: "filled_black", size: "large", width: 320, text: "continue_with", shape: "pill" });
    };
    if ((window as any).google?.accounts?.id) { render(); return; }
    let s = document.getElementById("gsi-script") as HTMLScriptElement | null;
    if (!s) { s = document.createElement("script"); s.src = "https://accounts.google.com/gsi/client"; s.async = true; s.id = "gsi-script"; document.body.appendChild(s); }
    s.addEventListener("load", render);
    return () => s?.removeEventListener("load", render);
  }, []);
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl p-6 w-full max-w-sm shadow-2xl">
        <button onClick={onClose} className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"><X className="w-5 h-5" /></button>
        <div className="flex items-center gap-2 mb-6">
          <img src="/assets/logo.png" alt="PlayAll" className="w-8 h-8 rounded-lg object-cover" />
          <span className="font-display font-extrabold text-lg uppercase tracking-tight">play<span className="text-primary">all</span></span>
        </div>
        <h2 className="text-xl font-bold mb-1">{mode === "login" ? "Selamat datang kembali!" : "Buat akun baru"}</h2>
        <p className="text-muted-foreground text-sm mb-6">{mode === "login" ? "Masuk untuk melanjutkan" : "Bergabung dan mulai streaming"}</p>
        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          {mode === "register" && <Input placeholder="Username" value={username} onChange={(e) => setUsername(e.target.value)} className="bg-secondary border-border" required />}
          <Input type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} className="bg-secondary border-border" required />
          <div className="relative">
            <Input type={showPw ? "text" : "password"} placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} className="bg-secondary border-border pr-10" required />
            <button type="button" onClick={() => setShowPw(!showPw)} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
              {showPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
            </button>
          </div>
          {error && <p className="text-destructive text-sm">{error}</p>}
          <Button type="submit" className="bg-primary hover:bg-primary/90 w-full mt-1" disabled={loading}>{loading ? "Loading..." : mode === "login" ? "Masuk" : "Daftar Sekarang"}</Button>
        </form>
        <div className="flex items-center gap-3 my-4">
          <div className="flex-1 h-px bg-border" /><span className="text-xs text-muted-foreground">atau</span><div className="flex-1 h-px bg-border" />
        </div>
        <div ref={googleBtnRef} className="flex justify-center" />
        <p className="text-center text-sm text-muted-foreground mt-4">
          {mode === "login" ? "Belum punya akun?" : "Sudah punya akun?"}{" "}
          <button onClick={onSwitch} className="text-primary hover:underline font-medium">{mode === "login" ? "Daftar" : "Masuk"}</button>
        </p>
      </div>
    </div>
  );
}

function LandingNavbar() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [showLogin, setShowLogin] = useState(false); const [showRegister, setShowRegister] = useState(false);
  const [searchQuery, setSearchQuery] = useState(""); const navigate = useNavigate();
  const handleSearch = (e: React.FormEvent) => { e.preventDefault(); if (searchQuery.trim()) navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`); };
  return (
    <>
      <nav className="fixed top-0 left-0 right-0 z-50 bg-background/80 backdrop-blur-xl border-b border-border">
        <div className="container mx-auto px-4">
          <div className="flex items-center justify-between h-16">
            <Link to="/" className="flex items-center gap-2">
              <img src="/assets/logo.png" alt="PlayAll" className="w-9 h-9 rounded-lg object-cover" />
              <span className="font-display text-lg font-extrabold tracking-tight uppercase">play<span className="text-primary">all</span></span>
            </Link>
            <div className="hidden md:flex items-center gap-6">
              {[{l:"Anime",h:"/anime"},{l:"Donghua",h:"/donghua"},{l:"Manga",h:"/manga"}].map((x) => (
                <Link key={x.l} to={x.h} className="text-muted-foreground hover:text-foreground transition-colors font-medium text-sm">{x.l}</Link>
              ))}
            </div>
            <div className="flex items-center gap-2">
              <form onSubmit={handleSearch} className="hidden md:flex relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari..." className="w-48 pl-9 h-9 bg-secondary border-border text-sm" />
              </form>
              <Button variant="ghost" size="sm" className="hidden md:flex" onClick={() => setShowLogin(true)}>Masuk</Button>
              <Button size="sm" className="bg-primary hover:bg-primary/90" onClick={() => setShowRegister(true)}>Daftar</Button>
              <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}</Button>
            </div>
          </div>
          {menuOpen && (
            <div className="md:hidden pb-4 border-t border-border pt-4 flex flex-col gap-1">
              {[{l:"Anime",h:"/anime"},{l:"Donghua",h:"/donghua"},{l:"Manga",h:"/manga"}].map((x) => (
                <Link key={x.l} to={x.h} onClick={() => setMenuOpen(false)} className="py-2 px-3 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors text-sm">{x.l}</Link>
              ))}
              <div className="flex gap-2 mt-2 px-3">
                <Button variant="outline" size="sm" className="flex-1" onClick={() => { setShowLogin(true); setMenuOpen(false); }}>Masuk</Button>
                <Button size="sm" className="flex-1 bg-primary" onClick={() => { setShowRegister(true); setMenuOpen(false); }}>Daftar</Button>
              </div>
            </div>
          )}
        </div>
      </nav>
      {showLogin && <AuthModal mode="login" onClose={() => setShowLogin(false)} onSwitch={() => { setShowLogin(false); setShowRegister(true); }} />}
      {showRegister && <AuthModal mode="register" onClose={() => setShowRegister(false)} onSwitch={() => { setShowRegister(false); setShowLogin(true); }} />}
    </>
  );
}

const contentTypes = [{icon:Tv,label:"Anime"},{icon:Video,label:"Donghua"},{icon:BookOpen,label:"Manga"},{icon:FileText,label:"Novel"}];

export default function LandingPage() {
  const [showRegister, setShowRegister] = useState(false);
  const [showLogin, setShowLogin] = useState(false);
  const [activeCat, setActiveCat] = useState<"anime"|"donghua"|"manga">("anime");
  const [trending, setTrending] = useState<Content[]>([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    setLoading(true);
    fetchContent({ type: activeCat as any, limit: 12, order_by: "rating", order_dir: "DESC" })
      .then((d) => setTrending(d.content)).catch(() => setTrending([])).finally(() => setLoading(false));
  }, [activeCat]);
  const cats = [{key:"anime",label:"Anime",icon:Tv},{key:"donghua",label:"Donghua",icon:Video},{key:"manga",label:"Manga",icon:BookOpen}] as const;
  const catCards = [
    {icon:Tv,title:"Anime",desc:"Anime Jepang sub indo terlengkap kualitas HD",color:"from-rose-500/20 to-red-500/20",border:"hover:border-rose-500/50",iconColor:"text-rose-400",bg:"bg-rose-500/10",href:"/anime"},
    {icon:Video,title:"Donghua",desc:"Animasi China populer dengan cerita epic",color:"from-amber-500/20 to-orange-500/20",border:"hover:border-amber-500/50",iconColor:"text-amber-400",bg:"bg-amber-500/10",href:"/donghua"},
    {icon:BookOpen,title:"Manga",desc:"Baca manga terbaru terjemahan bahasa Indonesia",color:"from-cyan-500/20 to-teal-500/20",border:"hover:border-cyan-500/50",iconColor:"text-cyan-400",bg:"bg-cyan-500/10",href:"/manga"},
    {icon:FileText,title:"Novel",desc:"Light novel & web novel dari berbagai genre",color:"from-violet-500/20 to-purple-500/20",border:"hover:border-violet-500/50",iconColor:"text-violet-400",bg:"bg-violet-500/10",href:"/novel"},
  ];
  return (
    <div className="min-h-screen bg-background">
      <LandingNavbar />
      {/* HERO */}
      <section className="relative min-h-[92vh] flex items-center justify-center overflow-hidden pt-16">
        <div className="absolute inset-0">
          <img src="/assets/hero-bg.jpg" alt="" className="w-full h-full object-cover object-center" />
          <div className="absolute inset-0 bg-gradient-to-t from-background via-background/55 to-background/20" />
        </div>
        <div className="container mx-auto px-4 relative z-10">
          <div className="text-center max-w-4xl mx-auto">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-8">
              <Sparkles className="w-4 h-4 text-primary" />
              <span className="text-sm font-medium text-primary">Platform Streaming All-in-One Indonesia</span>
            </div>
            <h1 className="font-display text-5xl sm:text-6xl md:text-7xl font-extrabold mb-6 leading-none tracking-tight uppercase">
              play<span className="text-primary">all</span>
            </h1>
            <p className="text-lg sm:text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">Anime, Donghua, Manga & Novel — semua dalam satu platform</p>
            <div className="flex flex-wrap items-center justify-center gap-3 mb-10">
              {contentTypes.map((t) => (
                <div key={t.label} className="flex items-center gap-2 px-4 py-2 rounded-full bg-secondary/80 border border-border">
                  <t.icon className="w-4 h-4 text-primary" /><span className="text-sm font-medium">{t.label}</span>
                </div>
              ))}
            </div>
            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
              <Button size="lg" className="btn-textured text-white shadow-lg px-8 py-6 text-base gap-2" onClick={() => setShowRegister(true)}>
                <Play className="w-5 h-5 fill-current" />Mulai Streaming Gratis
              </Button>
              <Button size="lg" variant="outline" className="px-8 py-6 text-base" asChild><Link to="/anime">Jelajahi Konten</Link></Button>
            </div>
            <div className="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
              {[{v:"350+",l:"Anime"},{v:"520+",l:"Donghua"},{v:"440+",l:"Manga"},{v:"700+",l:"Novel"}].map((s) => (
                <div key={s.l} className="text-center">
                  <div className="text-2xl md:text-3xl font-black">{s.v}</div>
                  <div className="text-xs text-muted-foreground mt-1">{s.l}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>
      {/* TRENDING */}
      <section className="py-16 md:py-20">
        <div className="container mx-auto px-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div><h2 className="font-display text-2xl sm:text-3xl font-extrabold mb-1">Trending Sekarang</h2><p className="text-muted-foreground text-sm">Konten paling populer minggu ini</p></div>
            <Button variant="ghost" className="text-primary gap-1 self-start" asChild><Link to={`/${activeCat}`}>Lihat Semua <ChevronRight className="w-4 h-4" /></Link></Button>
          </div>
          <div className="flex gap-2 mb-8 overflow-x-auto pb-1">
            {cats.map((cat) => { const Icon = cat.icon; return (
              <Button key={cat.key} size="sm" variant={activeCat===cat.key?"default":"outline"} onClick={() => setActiveCat(cat.key)}
                className={cn("whitespace-nowrap gap-1.5", activeCat===cat.key?"bg-primary text-primary-foreground":"border-border hover:bg-secondary")}>
                <Icon className="w-3.5 h-3.5" />{cat.label}
              </Button>
            );})}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4">
            {loading
              ? Array.from({ length: 12 }).map((_, i) => (
                  <div key={i} className="rounded-xl overflow-hidden bg-card border border-border">
                    <div className="aspect-[3/4] bg-secondary animate-pulse" />
                    <div className="p-2.5"><div className="h-3 bg-secondary rounded animate-pulse" /></div>
                  </div>
                ))
              : trending.map((item, i) => (
                <Link key={item.id} to={`/${activeCat}/${item.slug}`} className="group relative rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all duration-300 hover:scale-[1.03] hover:shadow-xl hover:shadow-primary/10">
                  <div className="absolute top-2 left-2 z-10 w-7 h-7 rounded-lg bg-primary text-primary-foreground flex items-center justify-center font-bold text-xs">{i+1}</div>
                  <div className="aspect-[3/4] relative overflow-hidden">
                    <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title} loading="lazy" className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                      <div className="w-12 h-12 rounded-full bg-primary/90 flex items-center justify-center"><Play className="w-5 h-5 text-white fill-white ml-0.5" /></div>
                    </div>
                  </div>
                  <div className="p-2.5">
                    <h3 className="font-semibold text-xs line-clamp-2 mb-1.5 group-hover:text-primary transition-colors">{item.title}</h3>
                    <div className="flex items-center justify-between">
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0 capitalize">{item.status}</Badge>
                      {item.rating > 0 && <div className="flex items-center gap-0.5"><Star className="w-3 h-3 text-yellow-500 fill-yellow-500" /><span className="text-[10px] text-muted-foreground">{formatRating(item.rating)}</span></div>}
                    </div>
                  </div>
                </Link>
              ))}
          </div>
        </div>
      </section>
      {/* CATEGORY CARDS */}
      <section className="py-16 bg-secondary/20">
        <div className="container mx-auto px-4">
          <div className="text-center mb-12">
            <h2 className="font-display text-2xl sm:text-3xl font-extrabold mb-3">Semua Konten dalam Satu Platform</h2>
            <p className="text-muted-foreground text-sm max-w-2xl mx-auto">Nikmati berbagai konten favorit tanpa perlu berpindah aplikasi</p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {catCards.map((cat) => (
              <Link key={cat.title} to={cat.href} className={`group relative rounded-2xl bg-card border border-border p-5 transition-all duration-300 hover:scale-[1.03] hover:shadow-xl ${cat.border}`}>
                <div className={`absolute inset-0 rounded-2xl bg-gradient-to-br ${cat.color} opacity-0 group-hover:opacity-100 transition-opacity duration-300`} />
                <div className="relative z-10 flex flex-col items-center text-center">
                  <div className={`w-14 h-14 rounded-2xl ${cat.bg} flex items-center justify-center mb-4 ${cat.iconColor} group-hover:scale-110 transition-transform`}><cat.icon className="w-7 h-7" /></div>
                  <h3 className="text-base font-bold mb-1">{cat.title}</h3>
                  <p className="text-muted-foreground text-xs line-clamp-2">{cat.desc}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>
      {/* FOOTER */}
      <footer className="border-t border-border py-10">
        <div className="container mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <img src="/assets/logo.png" alt="PlayAll" className="w-8 h-8 rounded-lg object-cover" />
            <span className="font-display font-extrabold uppercase tracking-tight">play<span className="text-primary">all</span></span>
          </div>
          <p className="text-muted-foreground text-xs">© 2026 playall.me — Platform streaming Indonesia</p>
          <div className="flex gap-4 text-xs text-muted-foreground">
            <Link to="/anime" className="hover:text-foreground">Anime</Link>
            <Link to="/donghua" className="hover:text-foreground">Donghua</Link>
            <Link to="/manga" className="hover:text-foreground">Manga</Link>
          </div>
        </div>
      </footer>
      {showRegister && <AuthModal mode="register" onClose={() => setShowRegister(false)} onSwitch={() => { setShowRegister(false); setShowLogin(true); }} />}
      {showLogin && <AuthModal mode="login" onClose={() => setShowLogin(false)} onSwitch={() => { setShowLogin(false); setShowRegister(true); }} />}
    </div>
  );
}
