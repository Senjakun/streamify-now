import { useState, useEffect, useRef } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Search, Menu, X, ChevronDown, Bookmark, Bell, User, History, LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useSite } from "@/contexts/SiteContext";
import { useAuth } from "@/contexts/AuthContext";

const Navbar = () => {
  const [scrolled, setScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const location = useLocation();
  const navigate = useNavigate();
  const { siteName } = useSite();
  const { isAuthenticated, user, logout } = useAuth();
  const [announcements, setAnnouncements] = useState<any[]>([]);
  const [showNotif, setShowNotif] = useState(false);
  const [readIds, setReadIds] = useState<number[]>(() => {
    try { return JSON.parse(localStorage.getItem("read_announcements") || "[]"); } catch { return []; }
  });
  const notifRef = useRef<HTMLDivElement>(null);

  const unreadCount = announcements.filter(a => !readIds.includes(a.id)).length;

  useEffect(() => {
    fetch("/api/announcements.php?action=list")
      .then(r => r.json())
      .then(d => setAnnouncements(Array.isArray(d) ? d : []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) setShowNotif(false);
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const handleOpenNotif = () => {
    setShowNotif(v => !v);
    const allIds = announcements.map(a => a.id);
    const merged = [...new Set([...readIds, ...allIds])];
    setReadIds(merged);
    localStorage.setItem("read_announcements", JSON.stringify(merged));
  };

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };

    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
      setSearchQuery("");
      setIsMobileMenuOpen(false);
    }
  };

  const categories = [
    { name: "Anime", href: "/anime" },
    { name: "Donghua", href: "/donghua" },
    { name: "Manga", href: "/manga" },
    { name: "Novel", href: "/novel" },
  ];

  const isActive = (path: string) => location.pathname === path;

  return (
    <>
      <nav
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
          scrolled
            ? "bg-background/95 backdrop-blur-md border-b border-border"
            : "bg-gradient-to-b from-background/80 to-transparent"
        }`}
      >
        <div className="container mx-auto px-4">
          <div className="flex items-center justify-between h-16">
            {/* Logo */}
            <Link to="/" className="flex items-center gap-2">
              <img src="/assets/logo.png" alt="PlayAll" className="w-9 h-9 rounded-lg object-cover shadow-glow" />
              <span className="font-display text-xl font-extrabold uppercase tracking-tight">play<span className="text-primary">all</span></span>
            </Link>

            {/* Desktop Navigation */}
            <div className="hidden md:flex items-center gap-1">
              <Link
                to="/"
                className={`px-4 py-2 text-sm font-medium rounded-full transition-colors ${
                  isActive("/")
                    ? "bg-primary/20 text-primary"
                    : "text-muted-foreground hover:text-foreground hover:bg-muted/50"
                }`}
              >
                Beranda
              </Link>

              {/* Kategori Dropdown */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button className="flex items-center gap-1 px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors">
                    Kategori
                    <ChevronDown className="w-4 h-4" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-44">
                  {categories.map((cat) => (
                    <DropdownMenuItem key={cat.href} asChild>
                      <Link to={cat.href} className="cursor-pointer">{cat.name}</Link>
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>

              <Link
                to="/bookmarks"
                className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-full transition-colors ${
                  isActive("/bookmarks")
                    ? "bg-primary/20 text-primary"
                    : "text-muted-foreground hover:text-foreground hover:bg-muted/50"
                }`}
              >
                <Bookmark className="w-4 h-4" />
                Koleksi
              </Link>
            </div>

            {/* Search & Actions */}
            <div className="flex items-center gap-3">
              {/* Desktop Search */}
              <form onSubmit={handleSearch} className="hidden md:flex items-center">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input
                    type="text"
                    placeholder="Cari konten..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-9 pr-4 py-2 h-10 w-48 bg-secondary/50 border-border/50 text-sm rounded-full focus:w-64 transition-all"
                  />
                </div>
              </form>

              {/* Bell Notif */}
              <div className="relative" ref={notifRef}>
<Link to="/announcements" className="relative p-2 rounded-full hover:bg-muted/50 transition-colors flex items-center">
                  <Bell className="w-5 h-5 text-muted-foreground" />
                  {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                  )}
                </Link>
                {showNotif && (
                  <div className="absolute right-0 top-12 w-80 bg-background border border-border rounded-2xl shadow-2xl z-50 overflow-hidden">
                    <div className="px-4 py-3 border-b border-border flex items-center justify-between">
                      <span className="font-semibold text-sm">Pengumuman</span>
                      <span className="text-xs text-muted-foreground">{announcements.length} total</span>
                    </div>
                    <div className="max-h-80 overflow-y-auto">
                      {announcements.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground text-sm">Belum ada pengumuman</div>
                      ) : announcements.map(a => (
                        <div key={a.id} className={`px-4 py-3 border-b border-border/50 last:border-0 ${
                          a.type === "danger" ? "bg-red-500/5" :
                          a.type === "warning" ? "bg-amber-500/5" :
                          a.type === "success" ? "bg-emerald-500/5" : "bg-blue-500/5"
                        }`}>
                          <div className="flex items-start gap-2">
                            <span className="text-base mt-0.5">{
                              a.type === "danger" ? "🚨" :
                              a.type === "warning" ? "⚠️" :
                              a.type === "success" ? "✅" : "📢"
                            }</span>
                            <div>
                              <p className="text-sm font-medium text-foreground">{a.title}</p>
                              <p className="text-xs text-muted-foreground mt-0.5 leading-relaxed">{a.content}</p>
                              <p className="text-xs text-muted-foreground/60 mt-1">{new Date(a.created_at).toLocaleDateString("id-ID", {day:"numeric",month:"short",year:"numeric"})}</p>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Auth / Profile */}
              {isAuthenticated ? (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button className="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-xs font-bold text-primary-foreground overflow-hidden">
                      {user?.avatar_url ? <img src={user.avatar_url} alt={user.username} className="w-full h-full object-cover" /> : (user?.username?.slice(0,2).toUpperCase() || "U")}
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-52">
                    <div className="px-3 py-2"><p className="text-sm font-semibold truncate">{user?.username}</p><p className="text-xs text-muted-foreground truncate">{user?.email}</p></div>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild><Link to="/profile" className="cursor-pointer"><User className="w-4 h-4 mr-2" />Profil Saya</Link></DropdownMenuItem>
                    <DropdownMenuItem asChild><Link to="/bookmarks" className="cursor-pointer"><Bookmark className="w-4 h-4 mr-2" />Bookmark</Link></DropdownMenuItem>
                    <DropdownMenuItem asChild><Link to="/history" className="cursor-pointer"><History className="w-4 h-4 mr-2" />Riwayat</Link></DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={logout} className="text-primary focus:text-primary cursor-pointer"><LogOut className="w-4 h-4 mr-2" />Keluar</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ) : (
                <Button size="sm" className="hidden md:flex bg-primary hover:bg-primary/90" asChild>
                  <Link to="/">Masuk</Link>
                </Button>
              )}

              {/* Mobile Menu Toggle */}
              <Button
                variant="ghost"
                size="icon"
                className="md:hidden"
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              >
                {isMobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
              </Button>
            </div>
          </div>
        </div>

        {/* Mobile Menu */}
        {isMobileMenuOpen && (
          <div className="md:hidden bg-background/98 backdrop-blur-md border-b border-border">
            <div className="container mx-auto px-4 py-4 space-y-4">
              {/* Mobile Search */}
              <form onSubmit={handleSearch}>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input
                    type="text"
                    placeholder="Cari konten..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-9 w-full"
                  />
                </div>
              </form>

              <Link
                to="/"
                onClick={() => setIsMobileMenuOpen(false)}
                className={`block px-4 py-2.5 text-sm font-medium rounded-lg ${
                  isActive("/") ? "bg-primary/20 text-primary" : "text-foreground hover:bg-muted"
                }`}
              >
                Beranda
              </Link>

              <div className="px-4 py-2">
                <p className="text-xs text-muted-foreground mb-3 uppercase tracking-wider">Kategori</p>
                <div className="grid grid-cols-2 gap-2">
                  {categories.map((cat) => (
                    <Link
                      key={cat.href}
                      to={cat.href}
                      onClick={() => setIsMobileMenuOpen(false)}
                      className={`text-sm py-2 px-3 rounded-lg ${
                        isActive(cat.href) ? "bg-primary/20 text-primary" : "text-foreground hover:bg-muted"
                      }`}
                    >
                      {cat.name}
                    </Link>
                  ))}
                </div>
              </div>

              <Link
                to="/bookmarks"
                onClick={() => setIsMobileMenuOpen(false)}
                className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-muted"
              >
                <Bookmark className="w-4 h-4" />
                Koleksi Saya
              </Link>
            </div>
          </div>
        )}
      </nav>
    </>
  );
};

export default Navbar;
