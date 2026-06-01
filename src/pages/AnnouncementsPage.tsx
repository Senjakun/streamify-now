import { useState, useEffect } from "react";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Megaphone, ArrowLeft, Calendar } from "lucide-react";

const TYPE_CONFIG: Record<string, { label: string; emoji: string; bg: string; border: string; badge: string }> = {
  info:    { label: "Info",    emoji: "📢", bg: "bg-blue-500/5",    border: "border-blue-500/20",    badge: "bg-blue-500/20 text-blue-300" },
  success: { label: "Update",  emoji: "✅", bg: "bg-emerald-500/5", border: "border-emerald-500/20", badge: "bg-emerald-500/20 text-emerald-300" },
  warning: { label: "Warning", emoji: "⚠️", bg: "bg-amber-500/5",   border: "border-amber-500/20",   badge: "bg-amber-500/20 text-amber-300" },
  danger:  { label: "Urgent",  emoji: "🚨", bg: "bg-red-500/5",     border: "border-red-500/20",     badge: "bg-red-500/20 text-red-300" },
};

export default function AnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<any | null>(null);

  useEffect(() => {
    fetch("/api/announcements.php?action=list")
      .then(r => r.json())
      .then(d => { setAnnouncements(Array.isArray(d) ? d : []); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  return (
    <div className="min-h-screen bg-zinc-950">
      <Navbar />
      <div className="max-w-3xl mx-auto px-4 pt-24 pb-8">
        <div className="flex items-center gap-3 mb-8">
          <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20">
            <Megaphone size={24} className="text-amber-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">Pengumuman</h1>
            <p className="text-sm text-zinc-500">Info & update terbaru dari admin</p>
          </div>
        </div>

        {selected ? (
          <div>
            <button onClick={() => setSelected(null)} className="flex items-center gap-2 text-sm text-zinc-400 hover:text-white mb-6 transition-colors">
              <ArrowLeft size={16} /> Kembali
            </button>
            <div className={`rounded-2xl border overflow-hidden ${TYPE_CONFIG[selected.type]?.border || "border-zinc-800"}`}>
              {selected.image_url && <img src={selected.image_url} alt={selected.title} className="w-full max-h-64 object-cover" />}
              <div className={`p-6 ${TYPE_CONFIG[selected.type]?.bg || ""}`}>
                <div className="flex items-center gap-2 mb-3">
                  <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${TYPE_CONFIG[selected.type]?.badge || "bg-zinc-700 text-zinc-300"}`}>
                    {TYPE_CONFIG[selected.type]?.emoji} {TYPE_CONFIG[selected.type]?.label}
                  </span>
                  <span className="text-xs text-zinc-500 flex items-center gap-1">
                    <Calendar size={11} /> {new Date(selected.created_at).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" })}
                  </span>
                </div>
                <h2 className="text-2xl font-bold text-white mb-4">{selected.title}</h2>
                {selected.content_html ? (
                  <div className="prose prose-invert prose-sm max-w-none text-zinc-300 leading-relaxed
                    [&_h1]:text-xl [&_h1]:font-bold [&_h1]:text-white [&_h1]:mb-3
                    [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-white [&_h2]:mb-2
                    [&_p]:mb-3 [&_p]:leading-relaxed [&_strong]:text-white
                    [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-3
                    [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-3
                    [&_a]:text-amber-400 [&_a]:underline
                    [&_img]:rounded-xl [&_img]:max-w-full [&_img]:my-3"
                    dangerouslySetInnerHTML={{ __html: selected.content_html }} />
                ) : (
                  <p className="text-zinc-300 leading-relaxed">{selected.content}</p>
                )}
              </div>
            </div>
          </div>
        ) : (
          <div>
            {loading ? (
              <div className="flex justify-center py-20">
                <div className="w-6 h-6 border-2 border-amber-400 border-t-transparent rounded-full animate-spin" />
              </div>
            ) : announcements.length === 0 ? (
              <div className="text-center py-20 text-zinc-500">
                <Megaphone size={40} className="mx-auto mb-3 opacity-30" />
                <p>Belum ada pengumuman</p>
              </div>
            ) : (
              <div className="space-y-4">
                {announcements.map(a => {
                  const cfg = TYPE_CONFIG[a.type] || TYPE_CONFIG.info;
                  return (
                    <button key={a.id} onClick={() => setSelected(a)} className={`w-full text-left rounded-2xl border overflow-hidden transition-all hover:scale-[1.01] ${cfg.border} ${cfg.bg}`}>
                      {a.image_url && <img src={a.image_url} alt={a.title} className="w-full h-40 object-cover" />}
                      <div className="p-5">
                        <div className="flex items-center gap-2 mb-2">
                          <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${cfg.badge}`}>{cfg.emoji} {cfg.label}</span>
                          <span className="text-xs text-zinc-500">{new Date(a.created_at).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}</span>
                        </div>
                        <h3 className="font-bold text-white text-lg mb-1">{a.title}</h3>
                        <p className="text-sm text-zinc-400 line-clamp-2">{a.content}</p>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        )}
      </div>
      <Footer />
    </div>
  );
}
