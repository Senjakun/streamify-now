import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { fetchHistory, getPosterUrl } from "@/services/api";
import { Clock, Play, BookOpen, Tv, Video, FileText } from "lucide-react";

const typeIcons: Record<string, any> = {
  anime: Tv, donghua: Video, manga: BookOpen, novel: FileText,
};
const typeColors: Record<string, string> = {
  anime: "bg-rose-500", donghua: "bg-amber-500", manga: "bg-cyan-500", novel: "bg-violet-500",
};

const HistoryPage = () => {
  const [history, setHistory] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchHistory(20).then((data) => { setHistory(data); setLoading(false); }).catch(() => setLoading(false));
  }, []);

  const getDetailPath = (item: any) => `/${item.type}/${item.slug}`;
  const getSubtitle = (item: any) => {
    if (item.episode_number) return `Episode ${item.episode_number}`;
    if (item.chapter_number) return `Chapter ${item.chapter_number}`;
    return "";
  };

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="pt-20 container mx-auto px-4 pb-16">
        <div className="flex items-center gap-3 mb-8 mt-6">
          <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
            <Clock className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h1 className="text-2xl font-black">Riwayat Tontonan & Bacaan</h1>
            <p className="text-sm text-muted-foreground">20 konten terakhir yang kamu akses</p>
          </div>
        </div>

        {loading ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {Array.from({ length: 10 }).map((_, i) => <div key={i} className="rounded-xl bg-secondary animate-pulse aspect-[3/4]" />)}
          </div>
        ) : history.length === 0 ? (
          <div className="flex flex-col items-center justify-center min-h-[40vh] text-muted-foreground">
            <Clock className="w-16 h-16 mb-4 opacity-30" />
            <p className="text-lg font-medium">Belum ada riwayat</p>
            <p className="text-sm mt-1">Mulai tonton atau baca konten untuk melihat riwayat</p>
            <Link to="/" className="mt-4 text-primary hover:underline text-sm">← Kembali ke Beranda</Link>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {history.map((item) => {
              const Icon = typeIcons[item.type] || Tv;
              const subtitle = getSubtitle(item);
              return (
                <Link key={item.id} to={getDetailPath(item)}
                  className="group relative rounded-xl overflow-hidden bg-card border border-border hover:border-primary/50 transition-all hover:scale-[1.03] hover:shadow-lg">
                  <div className="aspect-[3/4] relative overflow-hidden">
                    <img referrerPolicy="no-referrer" src={getPosterUrl(item.poster_url)} alt={item.title}
                      className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                      onError={(e) => { (e.target as HTMLImageElement).src = "/placeholder.svg"; }} />
                    <div className={`absolute top-2 left-2 w-7 h-7 rounded-full ${typeColors[item.type] || "bg-primary"} flex items-center justify-center`}>
                      <Icon className="w-3.5 h-3.5 text-white" />
                    </div>
                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                      <div className="w-10 h-10 rounded-full bg-primary/90 flex items-center justify-center">
                        {item.type === "manga" ? <BookOpen className="w-4 h-4 text-white" /> : <Play className="w-4 h-4 text-white fill-white ml-0.5" />}
                      </div>
                    </div>
                    {subtitle && (
                      <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2">
                        <p className="text-xs text-white font-medium">{subtitle}</p>
                      </div>
                    )}
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
      <Footer />
    </div>
  );
};

export default HistoryPage;
