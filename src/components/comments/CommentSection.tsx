import { Link, useNavigate } from "react-router-dom";
import { useState, useEffect, useCallback } from "react";
import { MessageCircle, LogIn, RefreshCw } from "lucide-react";
import CommentItem, { Comment } from "./CommentItem";
import CommentEditor from "./CommentEditor";
import { Button } from "@/components/ui/button";
import { fetchComments, postComment, likeComment } from "@/services/api";

interface CommentSectionProps {
  category: 'anime' | 'manga' | 'movie';
  contentId: number;
  episodeId?: number;
  chapterId?: number;
}

// ── Simple Auth helpers ─────────────────────────────────────
// Baca token dari localStorage (diset waktu login)
const getToken = (): string | null => localStorage.getItem("token");
const getUser = () => {
  const token = localStorage.getItem("token");
  if (!token) return null;
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    return { id: payload.user_id };
  } catch { return null; }
};


// Badge colors dari Artalk style
const BADGE_COLORS: Record<string, {bg: string, text: string, border: string, glow?: string}> = {
  red:     {bg:'#450a0a', text:'#fca5a5', border:'#ef4444'},
  sky:     {bg:'#0c4a6e', text:'#7dd3fc', border:'#0ea5e9'},
  blue:    {bg:'#1e3a5f', text:'#93c5fd', border:'#3b82f6'},
  orange:  {bg:'#7c2d12', text:'#fdba74', border:'#f97316'},
  green:   {bg:'#14532d', text:'#86efac', border:'#22c55e'},
  emerald: {bg:'#064e3b', text:'#6ee7b7', border:'#10b981'},
  zinc:    {bg:'#27272a', text:'#d4d4d8', border:'#71717a'},
  slate:   {bg:'#1e293b', text:'#cbd5e1', border:'#64748b'},
  purple:  {bg:'#3b0764', text:'#d8b4fe', border:'#a855f7'},
  violet:  {bg:'#2e1065', text:'#c4b5fd', border:'#8b5cf6'},
  fuchsia: {bg:'#4a044e', text:'#f0abfc', border:'#d946ef'},
  amber:   {bg:'#78350f', text:'#fcd34d', border:'#f59e0b'},
  yellow:  {bg:'#713f12', text:'#fde68a', border:'#eab308'},
  indigo:  {bg:'#1e1b4b', text:'#a5b4fc', border:'#6366f1'},
  lime:    {bg:'#1a2e05', text:'#bef264', border:'#84cc16'},
  gray:    {bg:'#1f2937', text:'#9ca3af', border:'#4b5563'},
  gold:    {bg:'linear-gradient(135deg,#78350f,#92400e)', text:'#fde68a', border:'#f59e0b', glow:'0 0 8px #f59e0b80'},
};

function BadgeDisplay({ badge, icon, color }: { badge: string; icon: string; color: string }) {
  const style = BADGE_COLORS[color] || BADGE_COLORS.gray;
  const isGold = color === 'gold';
  return (
    <span style={{
      background: style.bg,
      color: style.text,
      border: `1px solid ${style.border}`,
      boxShadow: isGold ? style.glow : undefined,
      display: 'inline-flex', alignItems: 'center', gap: '3px',
      padding: '1px 7px', borderRadius: '999px',
      fontSize: '11px', fontWeight: 700, lineHeight: '1.6',
    }}>
      {icon} {badge}
    </span>
  );
}

const CommentSection = ({ category, contentId, episodeId, chapterId }: CommentSectionProps) => {
  if (!contentId) return null;
  const [comments, setComments] = useState<Comment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [replyTo, setReplyTo] = useState<{ id: number; username: string } | null>(null);
  const [likedComments, setLikedComments] = useState<Set<number>>(new Set());
  const [totalComments, setTotalComments] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);

  const token = getToken();
  const user = getUser();
  const isLoggedIn = !!token && !!user;

  // ── Fetch comments ──────────────────────────────────────
  const loadComments = useCallback(async (p = 1, append = false) => {
    if (!contentId) return;
    setIsLoading(true);
    setError(null);
    try {
      const data = await fetchComments({
        content_id: contentId,
        episode_id: episodeId,
        chapter_id: chapterId,
        page: p,
      });

      // Map API response to Comment interface
      const mapped: Comment[] = data.comments.map((c: any) => ({
        id: c.id,
        user_id: c.user_id,
        username: c.username || 'Pengguna',
        avatar_url: c.avatar_url,
        rank_label: c.rank_label || 'Rakyat',
        badge: c.badge || 'Penduduk Desa',
        badge_icon: c.badge_icon || '👤',
        badge_color: c.badge_color || 'gray',
        comment_text: c.comment_text,
        is_spoiler: Boolean(c.is_spoiler),
        likes_count: Number(c.likes_count) || 0,
        created_at: c.created_at,
        replies: (c.replies || []).map((r: any) => ({
          id: r.id,
          user_id: r.user_id,
          username: r.username || 'Pengguna',
          avatar_url: r.avatar_url,
          rank_label: r.rank_label || 'Rakyat',
          badge: r.badge || 'Penduduk Desa',
          badge_icon: r.badge_icon || '👤',
          badge_color: r.badge_color || 'gray',
          comment_text: r.comment_text,
          is_spoiler: Boolean(r.is_spoiler),
          likes_count: Number(r.likes_count) || 0,
          created_at: r.created_at,
          replies: [],
          reply_count: 0,
        })),
        reply_count: c.reply_count || 0,
      }));

      setComments(prev => append ? [...prev, ...mapped] : mapped);
      setTotalComments(data.pagination.total);
      setHasMore(p < data.pagination.pages);
      setPage(p);
    } catch (err: any) {
      setError(err.message || 'Gagal memuat komentar');
    } finally {
      setIsLoading(false);
    }
  }, [contentId, episodeId, chapterId]);

  useEffect(() => {
    loadComments(1);
  }, [loadComments]);

  // ── Submit comment ──────────────────────────────────────
  const handleSubmit = async (text: string, isSpoiler: boolean) => {
    if (!isLoggedIn || !token) return;
    setIsSubmitting(true);
    try {
      const data = await postComment({
        content_id: contentId,
        comment_text: text,
        episode_id: episodeId,
        chapter_id: chapterId,
        parent_id: replyTo?.id,
        is_spoiler: isSpoiler,
        token,
      });

      const newComment: Comment = {
        ...data.comment,
        username: user?.username || 'Kamu',
        avatar_url: user?.avatar_url,
        rank_label: user?.rank_label || 'Rakyat',
        badge: (user as any)?.badge || 'Penduduk Desa',
        badge_icon: (user as any)?.badge_icon || '👤',
        badge_color: (user as any)?.badge_color || 'gray',
        replies: [],
        reply_count: 0,
      };

      if (replyTo) {
        setComments(prev => prev.map(c =>
          c.id === replyTo.id
            ? { ...c, replies: [...(c.replies || []), newComment], reply_count: (c.reply_count || 0) + 1 }
            : c
        ));
      } else {
        setComments(prev => [newComment, ...prev]);
        setTotalComments(t => t + 1);
      }
      setReplyTo(null);
    } catch (err: any) {
      alert('Gagal posting komentar: ' + err.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  // ── Like comment ────────────────────────────────────────
  const handleLike = async (commentId: number) => {
    if (!isLoggedIn || !token) return;
    try {
      const data = await likeComment(commentId, token);
      const wasLiked = likedComments.has(commentId);

      setLikedComments(prev => {
        const s = new Set(prev);
        wasLiked ? s.delete(commentId) : s.add(commentId);
        return s;
      });

      const updateLikes = (list: Comment[]): Comment[] =>
        list.map(c => {
          if (c.id === commentId) return { ...c, likes_count: data.likes_count };
          if (c.replies) return { ...c, replies: updateLikes(c.replies) };
          return c;
        });

      setComments(updateLikes);
    } catch {
      // silent fail
    }
  };

  // ── Reply ───────────────────────────────────────────────
  const handleReply = (commentId: number) => {
    const c = comments.find(x => x.id === commentId);
    if (c) setReplyTo({ id: commentId, username: c.username });
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <MessageCircle className="w-5 h-5 text-purple-500" />
          <h3 className="text-lg font-semibold text-foreground">
            Diskusi <span className="text-muted-foreground text-base font-normal">({totalComments})</span>
          </h3>
        </div>
        <button
          onClick={() => loadComments(1)}
          className="p-1.5 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground"
          title="Refresh komentar"
        >
          <RefreshCw className="w-4 h-4" />
        </button>
      </div>

      {/* Editor or login prompt */}
      {isLoggedIn ? (
        <CommentEditor
          onSubmit={handleSubmit}
          isLoading={isSubmitting}
          replyTo={replyTo}
          onCancelReply={() => setReplyTo(null)}
          placeholder={
            episodeId ? "Diskusi episode ini..." :
            chapterId ? "Diskusi chapter ini..." :
            "Tulis komentar..."
          }
        />
      ) : (
        <div className="flex flex-col sm:flex-row items-center justify-center gap-3 p-6 rounded-xl bg-secondary/30 border border-border/50 text-center">
          <LogIn className="w-5 h-5 text-muted-foreground" />
          <span className="text-muted-foreground text-sm">Login untuk ikut diskusi</span>
          
            <Button variant="outline" size="sm" className="border-purple-500/50 text-purple-400 hover:bg-purple-500/10" onClick={() => window.dispatchEvent(new CustomEvent("openLogin"))}>
              Login
            </Button>
          
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="text-center py-6 text-muted-foreground text-sm">
          <p>⚠️ {error}</p>
          <button
            onClick={() => loadComments(1)}
            className="mt-2 text-primary hover:underline text-xs"
          >
            Coba lagi
          </button>
        </div>
      )}

      {/* List */}
      <div className="space-y-4">
        {isLoading && comments.length === 0 ? (
          Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="animate-pulse p-4 rounded-xl bg-secondary/30">
              <div className="flex gap-3">
                <div className="w-10 h-10 rounded-full bg-muted" />
                <div className="flex-1 space-y-2">
                  <div className="h-4 bg-muted rounded w-1/4" />
                  <div className="h-3 bg-muted rounded w-3/4" />
                  <div className="h-3 bg-muted rounded w-1/2" />
                </div>
              </div>
            </div>
          ))
        ) : comments.length === 0 && !error ? (
          <div className="text-center py-12 text-muted-foreground">
            <MessageCircle className="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p className="font-medium">Belum ada diskusi</p>
            <p className="text-sm mt-1">Jadilah yang pertama berkomentar!</p>
          </div>
        ) : (
          comments.map((comment) => (
            <CommentItem
              key={comment.id}
              comment={comment}
              onReply={handleReply}
              onLike={handleLike}
              isLiked={likedComments.has(comment.id)}
            />
          ))
        )}
      </div>

      {/* Load more */}
      {hasMore && !isLoading && (
        <div className="text-center">
          <button
            onClick={() => loadComments(page + 1, true)}
            className="px-6 py-2 rounded-full bg-secondary hover:bg-secondary/80 text-sm text-muted-foreground transition-colors"
          >
            Muat lebih banyak komentar
          </button>
        </div>
      )}
    </div>
  );
};

export default CommentSection;