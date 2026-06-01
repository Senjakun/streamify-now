import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Bookmark, Star, Play, BookOpen, Tv, Video, FileText } from "lucide-react";
import { fetchBookmarks, getPosterUrl, formatRating } from "@/services/api";

const typeIcons: Record<string, any> = { anime: Tv, donghua: Video, manga: BookOpen, novel: FileText };
const typeColors: Record<string, string> = { anime: "bg-rose-500", donghua: "bg-amber-500", manga: "bg-cyan-500", novel: "bg-violet-500" };

const BookmarksPage = () => {
  const [bookmarks, setBookmarks] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState("Semua");

  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) { setLoading(false); return; }
    fetchBookmarks(token)
      .then((d) => { setBookmarks(d.bookmarks || []); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  const filtered = bookmarks.filter((item) => {
    if (filter === "Tonton") return ["anime","donghua"].includes(item.type);
    if (filter === "Baca") return ["manga","novel"].includes(item.type);
    return true;
  });

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <main className="pt-20 pb-12">
        <div className="container mx-auto px-4">
          <div className="flex items-center gap-3 mb-8 mt-6">
            <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
              <Bookmark className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-black">Koleksi Saya</h1>
              <p className="text-sm text-muted-foreground">Konten yang kamu simpan</p>
            </div>
          </div>
          <div className="flex gap-2 mb-6">
            {["Semua","Tonton","Baca"].map((tab) => (
              <button key={tab} onClick={() => setFilter(tab)}
                className={`px-4 py-1.5 rounded-full text-sm font-medium transition-colors ${filter === tab ? "bg-primary text-white" : "bg-secondary text-muted-foreground hover:text-foreground"}`}>
                {tab}
              </button>
            ))}
          </div>
          {loading ? (
            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
              {Array.from({length:12}).map((_,i) => <div key={i} className="rounded-xl bg-secondary animate-pulse aspect-[3/4]" />)}
            </div>
          ) : filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
              <div className="w-20 h-20 rounded-full bg-secondary flex items-center justify-center mb-4">
                <Bookmark className="w-10 h-10 opacity-30" />
              </div>
              <h2 className="text-xl font-semibold mb-2">Belum ada koleksi</h2>
              <p className="text-sm max-w-md">Klik icon bookmark di halaman anime/manga untuk menyimpan</p>
              <Link to="/" className="mt-4 text-primary hover:underline text-sm">Jelajahi Konten</Link>
            </div>
          ) : (
            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
              {filtered.map((item) => {
                const Icon = typeIcons[item.type] || Tv;
                return (
                  <Link key={item.id} to={`/${item.type}/${item.slug}`}
                    className="group relative rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.03]">
                    <div className="aspect-[3/4] relative overflow-hidden">
                      <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title}
                        className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                        onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                      <div className={`absolute top-1.5 left-1.5 w-6 h-6 rounded-full ${typeColors[item.type]||"bg-primary"} flex items-center justify-center`}>
                        <Icon className="w-3 h-3 text-white" />
                      </div>
                      {item.rating > 0 && (
                        <div className="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-black/70 rounded px-1 py-0.5">
                          <Star className="w-2.5 h-2.5 text-yellow-500 fill-yellow-500" />
                          <span className="text-[10px] text-white">{formatRating(item.rating)}</span>
                        </div>
                      )}
                      <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                        <div className="w-10 h-10 rounded-full bg-primary/90 flex items-center justify-center">
                          {["manga","novel"].includes(item.type) ? <BookOpen className="w-4 h-4 text-white" /> : <Play className="w-4 h-4 text-white fill-white ml-0.5" />}
                        </div>
                      </div>
                    </div>
                    <div className="p-2">
                      <h3 className="font-semibold text-xs line-clamp-2 group-hover:text-primary transition-colors">{item.title}</h3>
                      <p className="text-[10px] text-muted-foreground mt-0.5 capitalize">{item.type}</p>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default BookmarksPage;
