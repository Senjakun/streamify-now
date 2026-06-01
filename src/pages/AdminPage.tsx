import { useState, useEffect, useCallback, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import {
  BarChart3, MessageSquare, Users, Settings, Shield, Trash2, Megaphone,
  Pin, PinOff, ChevronLeft, ChevronRight, RefreshCw,
  Crown, UserX, UserCheck, Save, TrendingUp, Film, BookOpen,
  Tv, Layers, AlertCircle, X, Check
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

const API = import.meta.env.VITE_API_URL || '/api';

function getToken() { return localStorage.getItem("token") || ""; }
function authHeader() { return { Authorization: `Bearer ${getToken()}`, "Content-Type": "application/json" }; }

async function apiFetch(action: string, opts?: RequestInit) {
  const r = await fetch(`${API}/admin.php?${action}`, {
    ...opts,
    headers: { ...authHeader(), ...(opts?.headers || {}) },
  });
  return r.json();
}



type NovelCronCount = {
  source: string;
  country: string;
  total_novels: number;
  total_chapters: number;
};

type NovelCronStatus = {
  counts: NovelCronCount[];
  cursors: Record<string, number>;
  running: string[];
};

const BADGE_COLORS: Record<string, string> = {
  red: "bg-red-950 text-red-300 border-red-500",
  sky: "bg-sky-950 text-sky-300 border-sky-500",
  blue: "bg-blue-950 text-blue-300 border-blue-500",
  orange: "bg-orange-950 text-orange-300 border-orange-500",
  green: "bg-green-950 text-green-300 border-green-500",
  emerald: "bg-emerald-950 text-emerald-300 border-emerald-500",
  zinc: "bg-zinc-800 text-zinc-300 border-zinc-500",
  purple: "bg-purple-950 text-purple-300 border-purple-500",
  amber: "bg-amber-950 text-amber-300 border-amber-500",
  gold: "border border-yellow-400 text-yellow-300",
};

function BadgeChip({ badge, icon, color }: { badge?: string; icon?: string; color?: string }) {
  if (!badge) return null;
  const isGold = color === "gold";
  if (isGold) return (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border border-yellow-400 text-yellow-300 shadow-[0_0_8px_#f59e0b60]"
      style={{ background: "linear-gradient(135deg,#78350f,#92400e)" }}>
      {icon} {badge}
    </span>
  );
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border ${BADGE_COLORS[color || ""] || BADGE_COLORS.zinc}`}>
      {icon} {badge}
    </span>
  );
}

function Toast({ msg, type, onClose }: { msg: string; type: "success" | "error"; onClose: () => void }) {
  useEffect(() => { const t = setTimeout(onClose, 3000); return () => clearTimeout(t); }, [onClose]);
  return (
    <div className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl border text-sm font-medium
      ${type === "success" ? "bg-emerald-950 border-emerald-500 text-emerald-200" : "bg-red-950 border-red-500 text-red-200"}`}>
      {type === "success" ? <Check size={16} /> : <AlertCircle size={16} />}
      {msg}
      <button onClick={onClose} className="ml-2 opacity-60 hover:opacity-100"><X size={14} /></button>
    </div>
  );
}

function ConfirmDialog({ msg, onConfirm, onCancel }: { msg: string; onConfirm: () => void; onCancel: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div className="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl">
        <div className="flex items-start gap-3 mb-5">
          <AlertCircle className="text-amber-400 mt-0.5 shrink-0" size={20} />
          <p className="text-zinc-200 text-sm leading-relaxed">{msg}</p>
        </div>
        <div className="flex gap-3 justify-end">
          <button onClick={onCancel} className="px-4 py-2 text-sm rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors">Batal</button>
          <button onClick={onConfirm} className="px-4 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-500 text-white font-medium transition-colors">Hapus</button>
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon, label, value, color }: { icon: React.ReactNode; label: string; value: number | string; color: string }) {
  return (
    <div className={`relative overflow-hidden rounded-2xl border bg-zinc-900/80 p-5 ${color}`}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">{label}</p>
          <p className="text-3xl font-bold text-white tabular-nums">{Number(value).toLocaleString()}</p>
        </div>
        <div className="p-2 rounded-xl bg-zinc-800/60">{icon}</div>
      </div>
    </div>
  );
}

function Pagination({ page, pages, onPage }: { page: number; pages: number; onPage: (p: number) => void }) {
  if (pages <= 1) return null;
  return (
    <div className="flex items-center gap-2 justify-center mt-4">
      <button disabled={page <= 1} onClick={() => onPage(page - 1)}
        className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-30 transition-colors">
        <ChevronLeft size={16} />
      </button>
      <span className="text-sm text-zinc-400">Hal {page} / {pages}</span>
      <button disabled={page >= pages} onClick={() => onPage(page + 1)}
        className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-30 transition-colors">
        <ChevronRight size={16} />
      </button>
    </div>
  );
}

function StatsTab() {
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => { apiFetch("action=stats").then(d => { setStats(d); setLoading(false); }); }, []);
  if (loading) return <div className="flex items-center justify-center py-20"><RefreshCw className="animate-spin text-zinc-500" size={24} /></div>;
  return (
    <div>
      <h2 className="text-xl font-bold text-white mb-6 flex items-center gap-2"><TrendingUp size={20} className="text-violet-400" /> Statistik Situs</h2>
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        <StatCard icon={<Users size={20} className="text-sky-400" />} label="Total User" value={stats?.users ?? 0} color="border-sky-500/20" />
        <StatCard icon={<MessageSquare size={20} className="text-emerald-400" />} label="Komentar" value={stats?.comments ?? 0} color="border-emerald-500/20" />
        <StatCard icon={<Film size={20} className="text-violet-400" />} label="Anime" value={stats?.anime ?? 0} color="border-violet-500/20" />
        <StatCard icon={<Tv size={20} className="text-orange-400" />} label="Donghua" value={stats?.donghua ?? 0} color="border-orange-500/20" />
        <StatCard icon={<BookOpen size={20} className="text-pink-400" />} label="Manga" value={stats?.manga ?? 0} color="border-pink-500/20" />
        <StatCard icon={<Layers size={20} className="text-amber-400" />} label="Episode" value={stats?.episodes ?? 0} color="border-amber-500/20" />
      </div>
      <div className="mt-6 p-5 rounded-2xl bg-zinc-900 border border-zinc-800">
        <h3 className="text-sm font-semibold text-zinc-400 mb-3 uppercase tracking-wider">Ringkasan</h3>
        <div className="space-y-2 text-sm text-zinc-300">
          <div className="flex justify-between"><span>Total Konten</span><span className="font-mono font-bold text-white">{(+(stats?.anime||0) + +(stats?.donghua||0) + +(stats?.manga||0)).toLocaleString()}</span></div>
          <div className="flex justify-between"><span>Rata-rata Episode/Anime</span><span className="font-mono font-bold text-white">{stats?.anime > 0 ? Math.round(stats.episodes / stats.anime) : 0}</span></div>
          <div className="flex justify-between"><span>Komentar/User</span><span className="font-mono font-bold text-white">{stats?.users > 0 ? (stats.comments / stats.users).toFixed(1) : 0}</span></div>
        </div>
      </div>
    </div>
  );
}

function CommentsTab() {
  const [comments, setComments] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [confirm, setConfirm] = useState<number | null>(null);
  const [toast, setToast] = useState<{ msg: string; type: "success" | "error" } | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    const q = search ? `&search=${encodeURIComponent(search)}` : "";
    apiFetch(`action=comments&page=${page}${q}`).then(d => {
      setComments(d.comments || []); setPages(d.pages || 1); setTotal(d.total || 0); setLoading(false);
    });
  }, [page, search]);

  useEffect(() => { load(); }, [load]);

  const handleDelete = async (id: number) => {
    const d = await apiFetch("action=delete_comment", { method: "POST", body: JSON.stringify({ id }) });
    if (d.success) { setToast({ msg: "Komentar dihapus", type: "success" }); load(); }
    else setToast({ msg: d.error || "Gagal hapus", type: "error" });
    setConfirm(null);
  };

  const handlePin = async (id: number) => {
    const d = await apiFetch("action=pin_comment", { method: "POST", body: JSON.stringify({ id }) });
    if (d.success) { setToast({ msg: d.is_pinned ? "Dipin" : "Pin dilepas", type: "success" }); load(); }
  };

  return (
    <div>
      {toast && <Toast msg={toast.msg} type={toast.type} onClose={() => setToast(null)} />}
      {confirm !== null && <ConfirmDialog msg="Hapus komentar ini?" onConfirm={() => handleDelete(confirm)} onCancel={() => setConfirm(null)} />}
      <div className="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <h2 className="text-xl font-bold text-white flex items-center gap-2"><MessageSquare size={20} className="text-emerald-400" /> Komentar <span className="text-sm font-normal text-zinc-500">({total})</span></h2>
        <div className="flex gap-2">
          <input value={searchInput} onChange={e => setSearchInput(e.target.value)}
            onKeyDown={e => { if (e.key === "Enter") { setSearch(searchInput); setPage(1); } }}
            placeholder="Cari..." className="px-3 py-2 text-sm rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-200 placeholder-zinc-500 focus:outline-none w-36" />
          <button onClick={() => { setSearch(searchInput); setPage(1); }} className="px-3 py-2 text-sm rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition-colors">Cari</button>
          <button onClick={load} className="p-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition-colors"><RefreshCw size={14} className={loading ? "animate-spin" : ""} /></button>
        </div>
      </div>
      {loading ? <div className="flex items-center justify-center py-16"><RefreshCw className="animate-spin text-zinc-500" size={24} /></div>
        : comments.length === 0 ? <div className="text-center py-16 text-zinc-500">Tidak ada komentar</div>
        : <div className="space-y-3">
          {comments.map(c => (
            <div key={c.id} className={`p-4 rounded-xl border ${c.is_pinned ? "bg-amber-950/20 border-amber-500/30" : "bg-zinc-900 border-zinc-800"}`}>
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span className="text-sm font-semibold text-white">{c.username}</span>
                    <BadgeChip badge={c.badge} icon={c.badge_icon} color={c.badge_color} />
                    {c.is_pinned === 1 && <span className="text-xs text-amber-400 flex items-center gap-1"><Pin size={10} /> Pin</span>}
                    <span className="text-xs text-zinc-500">{new Date(c.created_at).toLocaleString("id-ID")}</span>
                  </div>
                  <p className="text-sm text-zinc-300 leading-relaxed line-clamp-3">{c.comment_text}</p>
                </div>
                <div className="flex gap-1.5 shrink-0">
                  <button onClick={() => handlePin(c.id)} className="p-1.5 rounded-lg bg-zinc-800 hover:bg-amber-900/40 hover:text-amber-400 text-zinc-400 transition-colors">
                    {c.is_pinned ? <PinOff size={14} /> : <Pin size={14} />}
                  </button>
                  <button onClick={() => setConfirm(c.id)} className="p-1.5 rounded-lg bg-zinc-800 hover:bg-red-900/40 hover:text-red-400 text-zinc-400 transition-colors">
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>}
      <Pagination page={page} pages={pages} onPage={setPage} />
    </div>
  );
}

function UsersTab() {
  const { user: me } = useAuth();
  const [users, setUsers] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState<{ msg: string; type: "success" | "error" } | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    apiFetch(`action=users&page=${page}`).then(d => { setUsers(d.users || []); setPages(d.pages || 1); setTotal(d.total || 0); setLoading(false); });
  }, [page]);

  useEffect(() => { load(); }, [load]);

  const handleToggleAdmin = async (id: number, name: string, isAdmin: number) => {
    const d = await apiFetch("action=toggle_admin", { method: "POST", body: JSON.stringify({ id }) });
    if (d.success) { setToast({ msg: `${name} ${isAdmin ? "dicabut admin" : "dijadikan admin"}`, type: "success" }); load(); }
    else setToast({ msg: d.error || "Gagal", type: "error" });
  };

  return (
    <div>
      {toast && <Toast msg={toast.msg} type={toast.type} onClose={() => setToast(null)} />}
      <div className="flex items-center justify-between mb-5 flex-wrap gap-3">
        <h2 className="text-xl font-bold text-white flex items-center gap-2"><Users size={20} className="text-sky-400" /> Users <span className="text-sm font-normal text-zinc-500">({total})</span></h2>
        <button onClick={load} className="p-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition-colors"><RefreshCw size={14} className={loading ? "animate-spin" : ""} /></button>
      </div>
      {loading ? <div className="flex items-center justify-center py-16"><RefreshCw className="animate-spin text-zinc-500" size={24} /></div>
        : <div className="overflow-x-auto rounded-xl border border-zinc-800">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-zinc-800 bg-zinc-900/60">
                <th className="text-left px-4 py-3 text-zinc-500 font-medium">User</th>
                <th className="text-left px-4 py-3 text-zinc-500 font-medium hidden sm:table-cell">Email</th>
                <th className="text-left px-4 py-3 text-zinc-500 font-medium hidden md:table-cell">Komentar</th>
                <th className="text-left px-4 py-3 text-zinc-500 font-medium">Role</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-800/60">
              {users.map(u => (
                <tr key={u.id} className="bg-zinc-900 hover:bg-zinc-800/50 transition-colors">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <div className="w-8 h-8 rounded-full bg-zinc-700 flex items-center justify-center text-sm font-bold text-zinc-300">{u.username[0].toUpperCase()}</div>
                      <div><div className="font-medium text-white">{u.username}</div><div className="text-xs text-zinc-500">#{u.id}</div></div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-zinc-400 hidden sm:table-cell">{u.email}</td>
                  <td className="px-4 py-3 text-zinc-400 text-center hidden md:table-cell">{u.comment_count}</td>
                  <td className="px-4 py-3">
                    {u.is_admin ? <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-950 text-yellow-300 border border-yellow-500/40"><Crown size={10} /> Admin</span>
                      : <span className="text-xs text-zinc-600">User</span>}
                  </td>
                  <td className="px-4 py-3">
                    {u.id !== me?.id && (
                      <button onClick={() => handleToggleAdmin(u.id, u.username, u.is_admin)}
                        className={`p-1.5 rounded-lg transition-colors ${u.is_admin ? "bg-zinc-800 hover:bg-red-900/40 hover:text-red-400 text-zinc-400" : "bg-zinc-800 hover:bg-yellow-900/40 hover:text-yellow-400 text-zinc-400"}`}>
                        {u.is_admin ? <UserX size={14} /> : <UserCheck size={14} />}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>}
      <Pagination page={page} pages={pages} onPage={setPage} />
    </div>
  );
}

function SettingsTab() {
  const [settings, setSettings] = useState({ site_name: "", site_description: "", site_logo: "" });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadingImg, setUploadingImg] = useState(false);
  const imgRef = useRef<HTMLInputElement>(null);
  const [toast, setToast] = useState<{ msg: string; type: "success" | "error" } | null>(null);

  useEffect(() => { apiFetch("action=get_settings").then(d => { setSettings(s => ({ ...s, ...d })); setLoading(false); }); }, []);

  const save = async () => {
    setSaving(true);
    const d = await apiFetch("action=save_settings", { method: "POST", body: JSON.stringify(settings) });
    setSaving(false);
    setToast(d.success ? { msg: "Tersimpan!", type: "success" } : { msg: d.error || "Gagal", type: "error" });
  };

  if (loading) return <div className="flex items-center justify-center py-16"><RefreshCw className="animate-spin text-zinc-500" size={24} /></div>;

  return (
    <div>
      {toast && <Toast msg={toast.msg} type={toast.type} onClose={() => setToast(null)} />}
      <h2 className="text-xl font-bold text-white mb-6 flex items-center gap-2"><Settings size={20} className="text-zinc-400" /> Pengaturan Situs</h2>
      <div className="space-y-5 max-w-lg">
        <div>
          <label className="block text-sm font-medium text-zinc-400 mb-1.5">Nama Situs</label>
          <Input value={settings.site_name} onChange={e => setSettings(s => ({ ...s, site_name: e.target.value }))} placeholder="playall.me" className="bg-zinc-900 border-zinc-700 text-white" />
        </div>
        <div>
          <label className="block text-sm font-medium text-zinc-400 mb-1.5">Deskripsi</label>
          <textarea value={settings.site_description} onChange={e => setSettings(s => ({ ...s, site_description: e.target.value }))}
            placeholder="Deskripsi singkat..." rows={3}
            className="w-full px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-700 text-white text-sm placeholder-zinc-500 focus:outline-none focus:border-zinc-500 resize-none" />
        </div>
        <div>
          <label className="block text-sm font-medium text-zinc-400 mb-1.5">URL Logo</label>
          <Input value={settings.site_logo} onChange={e => setSettings(s => ({ ...s, site_logo: e.target.value }))} placeholder="https://..." className="bg-zinc-900 border-zinc-700 text-white" />
          {settings.site_logo && <img src={settings.site_logo} alt="Preview" className="mt-3 h-12 object-contain rounded-lg border border-zinc-700 p-1 bg-zinc-900" />}
        </div>
        <Button onClick={save} disabled={saving} className="flex items-center gap-2 bg-violet-600 hover:bg-violet-500">
          {saving ? <RefreshCw size={14} className="animate-spin" /> : <Save size={14} />}
          {saving ? "Menyimpan..." : "Simpan"}
        </Button>
      </div>
    </div>
  );
}


function AnnouncementsTab() {
  const [list, setList] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ title: "", content: "", type: "info", image_url: "", blast: false });
  const [saving, setSaving] = useState(false);
  const [uploadingImg, setUploadingImg] = useState(false);
  const imgRef = useRef<HTMLInputElement>(null);
  const [toast, setToast] = useState<{ msg: string; type: "success" | "error" } | null>(null);

  const load = () => {
    setLoading(true);
    fetch("/api/announcements.php?action=list").then(r=>r.json()).then(d=>{setList(Array.isArray(d)?d:[]); setLoading(false);});
  };

  useEffect(() => { load(); }, []);

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadingImg(true);
    const token = localStorage.getItem("token") || "";
    const fd = new FormData();
    fd.append("image", file);
    try {
      const r = await fetch("/api/upload.php", { method: "POST", headers: { Authorization: `Bearer ${token}` }, body: fd });
      const d = await r.json();
      if (d.success) setForm(s => ({...s, image_url: d.url}));
      else setToast({msg: d.error || "Gagal upload", type: "error"});
    } catch { setToast({msg: "Gagal upload", type: "error"}); }
    setUploadingImg(false);
  };

  const handleCreate = async () => {
    if (!form.title || !form.content) { setToast({msg:"Title dan content wajib", type:"error"}); return; }
    setSaving(true);
    const token = localStorage.getItem("token") || "";
    const d = await fetch("/api/announcements.php?action=create", {
      method:"POST",
      headers:{"Content-Type":"application/json", Authorization:`Bearer ${token}`},
      body: JSON.stringify(form)
    }).then(r=>r.json());
    setSaving(false);
    if (d.success) { setToast({msg:"Pengumuman dibuat!", type:"success"}); setForm({title:"",content:"",type:"info",image_url:"",content_html:"",blast:false}); load(); }
    else setToast({msg: d.error||"Gagal", type:"error"});
  };

  const handleDelete = async (id: number) => {
    const token = localStorage.getItem("token") || "";
    await fetch("/api/announcements.php?action=delete", {
      method:"POST",
      headers:{"Content-Type":"application/json", Authorization:`Bearer ${token}`},
      body: JSON.stringify({id})
    });
    setToast({msg:"Dihapus", type:"success"});
    load();
  };

  const TYPE_CONFIG: Record<string, {label:string; emoji:string; color:string}> = {
    info:    {label:"Info",    emoji:"📢", color:"text-blue-400"},
    success: {label:"Update",  emoji:"✅", color:"text-emerald-400"},
    warning: {label:"Warning", emoji:"⚠️", color:"text-amber-400"},
    danger:  {label:"Urgent",  emoji:"🚨", color:"text-red-400"},
  };

  return (
    <div>
      {toast && <Toast msg={toast.msg} type={toast.type} onClose={() => setToast(null)} />}
      <h2 className="text-xl font-bold text-white mb-6 flex items-center gap-2">
        <Megaphone size={20} className="text-amber-400" /> Pengumuman
      </h2>
      <div className="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 mb-6">
        <h3 className="text-sm font-semibold text-zinc-400 mb-4 uppercase tracking-wider">Buat Pengumuman Baru</h3>
        <div className="space-y-3">
          <Input value={form.title} onChange={e=>setForm(s=>({...s,title:e.target.value}))}
            placeholder="Judul pengumuman..." className="bg-zinc-800 border-zinc-700 text-white" />
          <input value={form.content} onChange={e=>setForm(s=>({...s,content:e.target.value}))}
            placeholder="Deskripsi singkat (preview di list)..."
            className="w-full px-3 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-white text-sm placeholder-zinc-500 focus:outline-none" />
          <div className="flex gap-2">
            <input value={form.image_url} onChange={e=>setForm(s=>({...s,image_url:e.target.value}))}
              placeholder="URL gambar banner (opsional)..."
              className="flex-1 px-3 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-white text-sm placeholder-zinc-500 focus:outline-none" />
            <button onClick={()=>imgRef.current?.click()} disabled={uploadingImg}
              className="px-3 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-sm transition-colors shrink-0">
              {uploadingImg ? "..." : "📁 Upload"}
            </button>
            <input ref={imgRef} type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />
          </div>
          {form.image_url && <img src={form.image_url} alt="preview" className="h-24 rounded-lg object-cover border border-zinc-700" />}
          <div className="flex gap-3 items-center">
          <textarea value={form.content_html} onChange={e=>setForm(s=>({...s,content_html:e.target.value}))} placeholder="Isi konten (support HTML)" rows={5} className="w-full px-3 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-white text-sm placeholder-zinc-500 focus:outline-none resize-none" />
            <select value={form.type} onChange={e=>setForm(s=>({...s,type:e.target.value}))}
              className="px-3 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-white text-sm focus:outline-none">
              <option value="info">📢 Info</option>
              <option value="success">✅ Update</option>
              <option value="warning">⚠️ Warning</option>
              <option value="danger">🚨 Urgent</option>
            </select>
            <label className="flex items-center gap-2 cursor-pointer select-none">
              <div onClick={()=>setForm(s=>({...s,blast:!s.blast}))}
                className={`w-10 h-5 rounded-full transition-colors relative ${form.blast ? "bg-amber-500" : "bg-zinc-600"}`}>
                <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all ${form.blast ? "left-5" : "left-0.5"}`} />
              </div>
              <span className="text-sm text-zinc-400">Blast email ke semua user</span>
            </label>
            <Button onClick={handleCreate} disabled={saving} className="bg-amber-600 hover:bg-amber-500 flex items-center gap-2">
              {saving ? <RefreshCw size={14} className="animate-spin" /> : <Megaphone size={14} />}
              {saving ? "Mengirim..." : "Kirim"}
            </Button>
          </div>
        </div>
      </div>
      <div className="space-y-3">
        {loading ? <div className="flex justify-center py-8"><RefreshCw className="animate-spin text-zinc-500" size={20} /></div>
        : list.length === 0 ? <div className="text-center py-8 text-zinc-500 text-sm">Belum ada pengumuman aktif</div>
        : list.map(a => (
          <div key={a.id} className="flex items-start justify-between gap-3 p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <div className="flex items-start gap-3">
              <span className="text-xl">{TYPE_CONFIG[a.type]?.emoji}</span>
              <div>
                <p className={`font-semibold text-sm ${TYPE_CONFIG[a.type]?.color}`}>{a.title}</p>
                <p className="text-xs text-zinc-400 mt-0.5 leading-relaxed">{a.content}</p>
                <p className="text-xs text-zinc-600 mt-1">{new Date(a.created_at).toLocaleString("id-ID")}</p>
              </div>
            </div>
            <button onClick={() => handleDelete(a.id)}
              className="p-1.5 rounded-lg bg-zinc-800 hover:bg-red-900/40 hover:text-red-400 text-zinc-400 transition-colors shrink-0">
              <Trash2 size={14} />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}



function ScraperTab() {
  const [loading, setLoading] = useState<string | null>(null);
  const [logs, setLogs] = useState("");
  const [novelStatus, setNovelStatus] = useState<any>(null);
  const [novelBusy, setNovelBusy] = useState<string | null>(null);
  const [novelLogs, setNovelLogs] = useState("");

  const loadNovelStatus = useCallback(async () => {
    const d = await apiFetch("action=novel_status");
    if (!d.error) setNovelStatus(d);
  }, []);

  useEffect(() => {
    loadNovelStatus();
  }, [loadNovelStatus]);

  const runScript = async (script: string) => {
    setLoading(script);
    try {
      const token = localStorage.getItem("token");
      const res = await fetch("/api/admin.php?action=run_scraper&script=" + script, {
        headers: { Authorization: "Bearer " + token }
      });
      const d = await res.json();
      if (d.output) setLogs(d.output);
    } finally {
      setLoading(null);
    }
  };

  const viewLog = async (logfile: string) => {
    const token = localStorage.getItem("token");
    const res = await fetch("/api/admin.php?action=view_log&file=" + logfile, {
      headers: { Authorization: "Bearer " + token }
    });
    const d = await res.json();
    setLogs(d.content || "Log kosong");
  };

  const actNovel = async (action: string, payload: any, okMsg: string) => {
    setNovelBusy(action + ":" + (payload.source || ""));
    try {
      const d = await apiFetch("action=" + action, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      if (!d.error) {
        alert(okMsg);
        loadNovelStatus();
      } else {
        alert(d.error || "Gagal");
      }
    } finally {
      setNovelBusy(null);
    }
  };

  const viewNovelLog = async (file: string) => {
    const d = await apiFetch("action=novel_view_log&file=" + encodeURIComponent(file));
    setNovelLogs(d.content || "Log kosong");
  };

  const sumFor = (source: string, key: "total_novels" | "total_chapters") =>
    (novelStatus?.counts || [])
      .filter((r: any) => r.source === source)
      .reduce((a: number, b: any) => a + Number(b[key] || 0), 0);

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold text-white">Scraper & Cronjob</h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-zinc-800 rounded-xl p-4">
          <h3 className="text-white font-medium mb-3">Anime</h3>
          <div className="space-y-2">
            <button onClick={() => runScript("update-episodes")} disabled={!!loading}
              className="w-full bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
              {loading === "update-episodes" ? "Running..." : "▶ Update Episode Baru"}
            </button>
            <button onClick={() => runScript("fix-poster")} disabled={!!loading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
              {loading === "fix-poster" ? "Running..." : "🖼 Fix Poster Kosong"}
            </button>
            <button onClick={() => viewLog("update-episodes.log")}
              className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm">
              📋 Lihat Log Update
            </button>
          </div>
        </div>

        <div className="bg-zinc-800 rounded-xl p-4">
          <h3 className="text-white font-medium mb-3">Manga</h3>
          <div className="space-y-2">
            <button onClick={() => runScript("manga-bulk")} disabled={!!loading}
              className="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
              {loading === "manga-bulk" ? "Running..." : "📚 Bulk Scrape (8000+ manga)"}
            </button>
            <button onClick={() => runScript("manga-update")} disabled={!!loading}
              className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
              {loading === "manga-update" ? "Running..." : "🔄 Update Manga Baru"}
            </button>
            <button onClick={() => viewLog("manga-bulk.log")}
              className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm">
              📋 Lihat Log Bulk
            </button>
            <button onClick={() => viewLog("manga-update.log")}
              className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm">
              📋 Lihat Log Update
            </button>
          </div>
        </div>

        <div className="bg-zinc-800 rounded-xl p-4">
          <h3 className="text-white font-medium mb-3">Donghua</h3>
          <div className="space-y-2">
            <button onClick={() => runScript("donghua-update")} disabled={!!loading}
              className="w-full bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
              {loading === "donghua-update" ? "Running..." : "🎬 Update Donghua Baru"}
            </button>
            <button onClick={() => viewLog("donghua-update.log")}
              className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm">
              📋 Lihat Log Update
            </button>
          </div>
        </div>

        <div className="bg-zinc-800 rounded-xl p-4">
          <h3 className="text-white font-medium mb-3">Cronjob Schedule</h3>
          <div className="text-sm text-zinc-400 space-y-2">
            <p>🕕 Update episode anime: <span className="text-white">Setiap hari 06:00</span></p>
            <p>🕒 Fix poster: <span className="text-white">Setiap Minggu 03:00</span></p>
            <p>🕒 Update manga baru: <span className="text-white">Setiap hari 03:00</span></p>
            <p>🕒 Update donghua baru: <span className="text-white">Setiap hari 04:00</span></p>
            <p>🕒 Novel CN batch: <span className="text-white">Setiap 10 menit</span></p>
            <p>🕒 Novel JP batch: <span className="text-white">Setiap 15 menit</span></p>
            <p>🕒 Novel KR batch: <span className="text-white">Setiap 20 menit</span></p>
          </div>
        </div>
      </div>

      <div className="bg-zinc-900 rounded-2xl border border-zinc-800 p-4">
        <h3 className="text-white text-lg font-semibold mb-4">Novel Cronjob</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[
            { source: "mtlreader", title: "Novel JP (MTLReader)", batch: 40, collectable: false, log: "mtlreader_cron.log" },
            { source: "freewebnovel", title: "Novel CN (FreeWebNovel)", batch: 60, collectable: true, log: "freewebnovel_seed_live.log" },
            { source: "woopread", title: "Novel KR (WoopRead)", batch: 20, collectable: true, log: "woopread_seed_live.log" },
          ].map((item) => (
            <div key={item.source} className="bg-zinc-800 rounded-xl p-4">
              <h4 className="text-white font-medium mb-2">{item.title}</h4>
              <div className="text-xs text-zinc-400 mb-3 space-y-1">
                <div>{sumFor(item.source, "total_novels")} novel</div>
                <div>{sumFor(item.source, "total_chapters")} total chapter indexed</div>
                <div>cursor: {novelStatus?.cursors?.[item.source] ?? 0}</div>
              </div>

              <div className="space-y-2">
                <button
                  onClick={() => actNovel("novel_run_batch", { source: item.source, batch: item.batch }, "Batch sync dijalankan")}
                  disabled={!!novelBusy}
                  className="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50"
                >
                  {novelBusy === "novel_run_batch:" + item.source ? "Running..." : "▶ Sync Batch"}
                </button>

                {item.collectable && (
                  <button
                    onClick={() => actNovel("novel_collect_seed", { source: item.source }, "Collect + Seed dijalankan")}
                    disabled={!!novelBusy}
                    className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50"
                  >
                    {novelBusy === "novel_collect_seed:" + item.source ? "Running..." : "📚 Collect + Seed"}
                  </button>
                )}

                <button
                  onClick={() => actNovel("novel_stop", { source: item.source }, "Stop signal dikirim")}
                  disabled={!!novelBusy}
                  className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50"
                >
                  Stop
                </button>

                <button
                  onClick={() => viewNovelLog(item.log)}
                  className="w-full bg-zinc-700 hover:bg-zinc-600 text-white px-4 py-2 rounded-lg text-sm"
                >
                  📋 Lihat Log
                </button>
              </div>
            </div>
          ))}

          <div className="bg-zinc-800 rounded-xl p-4 md:col-span-2">
            <h4 className="text-white font-medium mb-3">Novel Process Status</h4>
            {!novelStatus?.running?.length ? (
              <p className="text-sm text-zinc-500">Tidak ada proses novel aktif.</p>
            ) : (
              <div className="space-y-1">
                {novelStatus.running.map((line: string, i: number) => (
                  <div key={i} className="text-xs text-zinc-400 break-all">{line}</div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {logs && (
        <div className="bg-zinc-950 rounded-xl p-4 border border-zinc-700">
          <pre className="text-xs text-green-400 whitespace-pre-wrap max-h-64 overflow-y-auto">{logs}</pre>
        </div>
      )}

      {novelLogs && (
        <div className="bg-zinc-950 rounded-xl p-4 border border-zinc-700">
          <pre className="text-xs text-green-400 whitespace-pre-wrap max-h-64 overflow-y-auto">{novelLogs}</pre>
        </div>
      )}
    </div>
  );
}


const TABS = [
  { id: "stats", label: "Statistik", icon: <BarChart3 size={16} /> },
  { id: "comments", label: "Komentar", icon: <MessageSquare size={16} /> },
  { id: "users", label: "Users", icon: <Users size={16} /> },
  { id: "settings", label: "Pengaturan", icon: <Settings size={16} /> },
  { id: "announcements", label: "Pengumuman", icon: <Megaphone size={16} /> },
  { id: "scraper", label: "Scraper", icon: <RefreshCw size={16} /> },
];

export default function AdminPage() {
  const { user, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [tab, setTab] = useState("stats");
  const [authChecked, setAuthChecked] = useState(false);


  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) { navigate("/"); return; }
  }, []);

  useEffect(() => {
    if (user === null) return; // still loading
    setAuthChecked(true);
    if (!user?.is_admin) navigate("/");
  }, [user, navigate]);

  if (!isAuthenticated || !user?.is_admin) return (
    <div className="min-h-screen bg-zinc-950 flex items-center justify-center">
      <div className="text-center"><Shield size={48} className="mx-auto mb-4 text-zinc-600" /><p className="text-zinc-400">Akses ditolak.</p></div>
    </div>
  );

  return (
    <div className="min-h-screen bg-zinc-950">
      <Navbar />
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="flex items-center gap-3 mb-8">
          <div className="p-2.5 rounded-xl bg-violet-500/10 border border-violet-500/20"><Shield size={24} className="text-violet-400" /></div>
          <div><h1 className="text-2xl font-bold text-white">Admin Panel</h1><p className="text-sm text-zinc-500 flex items-center gap-2">Halo, <span className="text-zinc-300">{user.username}</span> <BadgeChip badge={user.badge} icon={user.badge_icon} color={user.badge_color} /></p></div>
        </div>
        <div className="flex gap-1 p-1 bg-zinc-900 rounded-xl border border-zinc-800 mb-6 overflow-x-auto">
          {TABS.map(t => (
            <button key={t.id} onClick={() => setTab(t.id)}
              className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex-1 justify-center
                ${tab === t.id ? "bg-zinc-800 text-white shadow-sm" : "text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50"}`}>
              {t.icon} {t.label}
            </button>
          ))}
        </div>
        <div className="bg-zinc-900/50 rounded-2xl border border-zinc-800 p-6">
          {tab === "stats" && <StatsTab />}
          {tab === "comments" && <CommentsTab />}
          {tab === "users" && <UsersTab />}
          {tab === "settings" && <SettingsTab />}
          {tab === "announcements" && <AnnouncementsTab />}
          {tab === "scraper" && <ScraperTab />}
        </div>
      </div>
      <Footer />
    </div>
  );
}
