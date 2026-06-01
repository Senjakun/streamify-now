import { useState, useEffect, useRef } from "react";
import { useParams, Link } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Camera, Bookmark, MessageCircle, Edit, Check, X, Eye, EyeOff } from "lucide-react";

const API = import.meta.env.VITE_API_URL || '/api';

const BADGE_COLORS: Record<string, string> = {
  red:'bg-red-950 text-red-300 border-red-500', sky:'bg-sky-950 text-sky-300 border-sky-500',
  blue:'bg-blue-950 text-blue-300 border-blue-500', orange:'bg-orange-950 text-orange-300 border-orange-500',
  green:'bg-green-950 text-green-300 border-green-500', emerald:'bg-emerald-950 text-emerald-300 border-emerald-500',
  zinc:'bg-zinc-800 text-zinc-300 border-zinc-500', slate:'bg-slate-800 text-slate-300 border-slate-500',
  purple:'bg-purple-950 text-purple-300 border-purple-500', violet:'bg-violet-950 text-violet-300 border-violet-500',
  fuchsia:'bg-fuchsia-950 text-fuchsia-300 border-fuchsia-500', amber:'bg-amber-950 text-amber-300 border-amber-500',
  yellow:'bg-yellow-950 text-yellow-300 border-yellow-500', indigo:'bg-indigo-950 text-indigo-300 border-indigo-500',
  lime:'bg-lime-950 text-lime-300 border-lime-500', gray:'bg-gray-800 text-gray-300 border-gray-500',
};

function BadgeDisplay({ badge, icon, color }: { badge: string; icon: string; color: string }) {
  if (color === 'gold') return (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border border-yellow-400 text-yellow-300 shadow-[0_0_8px_#f59e0b80]"
      style={{background:'linear-gradient(135deg,#78350f,#92400e)'}}>
      {icon} {badge}
    </span>
  );
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border ${BADGE_COLORS[color] || BADGE_COLORS.gray}`}>
      {icon} {badge}
    </span>
  );
}

const ProfilePage = () => {
  const { userId } = useParams<{ userId: string }>();
  const { user: me, refreshUser } = useAuth() as any;
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [badges, setBadges] = useState<any[]>([]);
  const [editing, setEditing] = useState(false);
  const [showBadgePicker, setShowBadgePicker] = useState(false);
  const [editData, setEditData] = useState({ username:'', badge:'', old_password:'', new_password:'', confirm_password:'' });
  const [showPw, setShowPw] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<'comments'|'history'>('comments');
  const [userComments, setUserComments] = useState<any[]>([]);
  const [commentPage, setCommentPage] = useState(1);
  const [commentTotal, setCommentTotal] = useState(0);
  const [commentPages, setCommentPages] = useState(1);
  const [userHistory, setUserHistory] = useState<any[]>([]);
  const fileRef = useRef<HTMLInputElement>(null);

  const isOwnProfile = me && (!userId || parseInt(userId) === me.id);
  const targetId = userId ? parseInt(userId) : me?.id;

  useEffect(() => {
    if (!targetId) return;
    fetch(`${API}/profile.php?action=get&user_id=${targetId}`)
      .then(r => r.json())
      .then(d => { setProfile(d); setEditData(p => ({...p, username:d.username, badge:d.badge})); setLoading(false); })
      .catch(() => setLoading(false));
    fetch(`${API}/profile.php?action=badges`).then(r => r.json()).then(setBadges).catch(() => {});
    loadComments(1);
    loadHistory();
  }, [targetId]);

  const loadComments = async (p: number) => {
    if (!targetId) return;
    const r = await fetch(`${API}/profile.php?action=comments&user_id=${targetId}&page=${p}`);
    const d = await r.json();
    setUserComments(d.comments || []);
    setCommentTotal(d.total || 0);
    setCommentPages(d.pages || 1);
    setCommentPage(p);
  };

  const loadHistory = async () => {
    if (!targetId) return;
    const r = await fetch(`${API}/profile.php?action=history_public&user_id=${targetId}`);
    const d = await r.json();
    setUserHistory(Array.isArray(d) ? d : []);
  };

  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { setError('Maksimal 5MB'); return; }
    const token = localStorage.getItem('token');
    if (!token) return;
    const form = new FormData();
    form.append('avatar', file);
    const r = await fetch(`${API}/profile.php?action=avatar`, { method:'POST', headers:{Authorization:`Bearer ${token}`}, body:form });
    const d = await r.json();
    if (d.success) {
      setProfile((p: any) => ({ ...p, avatar_url: d.avatar_url }));
      setSuccess('Foto diperbarui!');
      try { if (refreshUser) refreshUser(); } catch {}
    } else {
      setError(d.error || 'Gagal upload avatar');
      console.error('Avatar upload gagal:', d);
    }
  };

  const handleSave = async () => {
    setError(''); setSaving(true);
    if (editData.new_password && editData.new_password !== editData.confirm_password) {
      setError('Konfirmasi password tidak cocok'); setSaving(false); return;
    }
    const token = localStorage.getItem('token');
    const body: any = {};
    if (editData.username !== profile.username) body.username = editData.username;
    if (editData.badge !== profile.badge) body.badge = editData.badge;
    if (editData.new_password) { body.old_password = editData.old_password; body.new_password = editData.new_password; }
    const r = await fetch(`${API}/profile.php?action=update`, {
      method:'POST', headers:{'Content-Type':'application/json', Authorization:`Bearer ${token}`}, body:JSON.stringify(body)
    });
    const d = await r.json();
    setSaving(false);
    if (d.success) {
      setProfile((p: any) => ({...p, ...d.user}));
      setEditing(false); setSuccess('Profil diperbarui!');
      setTimeout(() => setSuccess(''), 3000);
      if (refreshUser) refreshUser();
    } else setError(d.error);
  };

  if (loading) return (
    <div className="min-h-screen bg-background"><Navbar />
      <div className="pt-20 flex items-center justify-center min-h-[60vh]">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    </div>
  );

  if (!profile) return (
    <div className="min-h-screen bg-background"><Navbar />
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-muted-foreground">
        <p className="text-lg">User tidak ditemukan</p>
        <Link to="/" className="mt-4 text-primary hover:underline text-sm">← Kembali</Link>
      </div>
      <Footer />
    </div>
  );

  const avatarUrl = profile?.avatar_url
    ? (String(profile.avatar_url).startsWith('http')
        ? `${profile.avatar_url}${String(profile.avatar_url).includes('?') ? '&' : '?'}v=${Date.now()}`
        : (profile.avatar_url.startsWith('/') ? profile.avatar_url : `/${profile.avatar_url}`))
    : null;

  return (
    <div className="min-h-screen bg-background"><Navbar />
      <div className="relative h-[200px]">
        <div className="w-full h-full bg-gradient-to-r from-primary/30 via-primary/10 to-background" />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/50 to-transparent" />
      </div>
      <main className="container mx-auto px-4 -mt-24 relative z-10 pb-4">
        <div className="flex flex-col sm:flex-row gap-6">
          {/* Avatar */}
          <div className="relative flex-shrink-0">
            <div className="w-28 h-28 rounded-full border-4 border-background overflow-hidden bg-secondary shadow-xl">
              {avatarUrl ? <img src={avatarUrl} alt={profile.username} className="w-full h-full object-cover" />
                : <div className="w-full h-full flex items-center justify-center text-4xl font-black text-primary">{profile.username?.slice(0,2).toUpperCase()}</div>}
            </div>
            {isOwnProfile && (
              <>
                <button onClick={() => fileRef.current?.click()}
                  className="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary flex items-center justify-center shadow-lg hover:bg-primary/90">
                  <Camera className="w-4 h-4 text-white" />
                </button>
                <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleAvatarUpload} />
              </>
            )}
          </div>

          {/* Info */}
          <div className="flex-1 pt-6">
            {editing ? (
              <div className="space-y-3 max-w-sm">
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Username</label>
                  <Input value={editData.username} onChange={e => setEditData(p => ({...p, username:e.target.value}))} className="bg-secondary border-border h-9" />
                </div>
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Badge</label>
                  <button onClick={() => setShowBadgePicker(!showBadgePicker)}
                    className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-secondary border border-border hover:border-primary/50 text-sm">
                    <BadgeDisplay badge={editData.badge||'Penduduk Desa'} icon={badges.find(b=>b.name===editData.badge)?.icon||'👤'} color={badges.find(b=>b.name===editData.badge)?.color||'gray'} />
                    <span className="text-xs text-muted-foreground">Ganti</span>
                  </button>
                  {showBadgePicker && (
                    <div className="mt-2 p-3 rounded-xl bg-secondary border border-border max-h-60 overflow-y-auto">
                      {['fantasy','noble','modern','default'].map(cat => (
                        <div key={cat} className="mb-3">
                          <p className="text-xs text-muted-foreground mb-2 font-medium">
                            {cat==='fantasy'?'🧝 Fantasy Race':cat==='noble'?'👑 Bangsawan':cat==='modern'?'💼 Modern':'👤 Default'}
                          </p>
                          <div className="flex flex-wrap gap-1.5">
                            {badges.filter(b=>b.category===cat).map(b => (
                              <button key={b.name} onClick={() => { setEditData(p=>({...p,badge:b.name})); setShowBadgePicker(false); }}
                                className={`transition-all hover:scale-105 ${editData.badge===b.name?'ring-2 ring-primary ring-offset-1 ring-offset-background':''}`}>
                                <BadgeDisplay badge={b.name} icon={b.icon} color={b.color} />
                              </button>
                            ))}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
                <div className="border-t border-border pt-3">
                  <p className="text-xs text-muted-foreground mb-2">Ganti Password (opsional)</p>
                  <Input type="password" placeholder="Password lama" value={editData.old_password} onChange={e=>setEditData(p=>({...p,old_password:e.target.value}))} className="bg-secondary border-border h-9 mb-2" />
                  <div className="relative">
                    <Input type={showPw?'text':'password'} placeholder="Password baru" value={editData.new_password} onChange={e=>setEditData(p=>({...p,new_password:e.target.value}))} className="bg-secondary border-border h-9 pr-9 mb-2" />
                    <button onClick={()=>setShowPw(!showPw)} className="absolute right-2 top-2 text-muted-foreground">{showPw?<EyeOff className="w-4 h-4"/>:<Eye className="w-4 h-4"/>}</button>
                  </div>
                  <Input type="password" placeholder="Konfirmasi password" value={editData.confirm_password} onChange={e=>setEditData(p=>({...p,confirm_password:e.target.value}))} className="bg-secondary border-border h-9" />
                </div>
                {error && <p className="text-destructive text-sm">{error}</p>}
                <div className="flex gap-2">
                  <Button size="sm" className="bg-primary gap-1" onClick={handleSave} disabled={saving}>
                    <Check className="w-3.5 h-3.5" />{saving?'Menyimpan...':'Simpan'}
                  </Button>
                  <Button size="sm" variant="outline" onClick={()=>{setEditing(false);setError('');}}><X className="w-3.5 h-3.5"/></Button>
                </div>
              </div>
            ) : (
              <div>
                <div className="flex items-center gap-2 flex-wrap mb-1">
                  <h1 className="text-2xl font-black">{profile.username}</h1>
                  <BadgeDisplay badge={profile.badge||'Penduduk Desa'} icon={profile.badge_icon||'👤'} color={profile.badge_color||'gray'} />
                </div>
                <p className="text-sm text-muted-foreground mb-3">
                  Bergabung {new Date(profile.created_at).toLocaleDateString('id-ID',{day:'numeric',month:'long',year:'numeric'})}
                </p>
                <div className="flex gap-4 text-sm text-muted-foreground mb-4">
                  <span className="flex items-center gap-1"><MessageCircle className="w-4 h-4"/>{commentTotal} Komentar</span>
                  <span className="flex items-center gap-1"><Bookmark className="w-4 h-4"/>{profile.bookmark_count} Bookmark</span>
                </div>
                {success && <p className="text-green-400 text-sm mb-2">{success}</p>}
                {isOwnProfile && (
                  <Button size="sm" variant="outline" className="gap-1.5" onClick={()=>setEditing(true)}>
                    <Edit className="w-3.5 h-3.5"/>Edit Profil
                  </Button>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Tabs */}
        <div className="mt-8 flex gap-2 mb-4">
          <button onClick={()=>setActiveTab('comments')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${activeTab==='comments'?'bg-primary text-white':'bg-secondary hover:bg-secondary/80'}`}>
            💬 Komentar ({commentTotal})
          </button>
        </div>

        {activeTab==='comments' && (
          <div className="space-y-3">
            {userComments.length===0 ? (
              <p className="text-muted-foreground text-sm text-center py-8">Belum ada komentar</p>
            ) : userComments.map((c:any) => (
              <div key={c.id} className="p-3 rounded-xl bg-secondary/30 border border-border">
                <div className="flex items-center gap-2 mb-1 flex-wrap">
                  {c.content_title && <a href={`/${c.content_type}/${c.content_slug}`} className="text-xs text-primary hover:underline">{c.content_title}</a>}
                  <span className="text-xs text-muted-foreground">{new Date(c.created_at).toLocaleDateString('id-ID')}</span>
                </div>
                <p className="text-sm">{c.comment_text.split(/(\[img\][^\]]*\[\/img\])/g).map((part: string, i: number) => {
                  const m = part.match(/\[img\](.*?)\[\/img\]/);
                  if (m) return <img key={i} src={m[1]} alt="gambar" className="max-h-32 rounded-lg mt-1 border border-border" />;
                  return <span key={i}>{part}</span>;
                })}</p>
                <div className="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
                  {c.is_spoiler?<span className="text-destructive">⚠️ Spoiler</span>:null}
                  <span className="text-muted-foreground/50">❤️ {c.likes_count}</span>
                </div>
              </div>
            ))}
            {commentPages>1 && (
              <div className="flex items-center gap-2 justify-center pt-2">
                <button onClick={()=>loadComments(commentPage-1)} disabled={commentPage<=1} className="px-3 py-1.5 rounded-lg bg-secondary text-sm disabled:opacity-50">← Prev</button>
                <span className="text-sm text-muted-foreground">{commentPage}/{commentPages}</span>
                <button onClick={()=>loadComments(commentPage+1)} disabled={commentPage>=commentPages} className="px-3 py-1.5 rounded-lg bg-secondary text-sm disabled:opacity-50">Next →</button>
              </div>
            )}
          </div>
        )}
      </main>
      <Footer />
    </div>
  );
};

export default ProfilePage;
