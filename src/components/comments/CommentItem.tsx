import { useState, useEffect } from "react";
import { Heart, MessageCircle, AlertTriangle, ChevronDown, ChevronUp } from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";

export interface Comment {
  id: number;
  user_id: number;
  username: string;
  avatar_url?: string;
  rank_label?: string;
  badge?: string;
  badge_icon?: string;
  badge_color?: string;
  comment_text: string;
  is_spoiler: boolean;
  likes_count: number;
  created_at: string;
  replies?: Comment[];
  reply_count?: number;
}

interface CommentItemProps {
  comment: Comment;
  onReply: (commentId: number) => void;
  onLike: (commentId: number) => void;
  isLiked?: boolean;
  depth?: number;
}

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
  return (
    <span style={{
      background: style.bg,
      color: style.text,
      border: `1px solid ${style.border}`,
      boxShadow: style.glow,
      display: 'inline-flex', alignItems: 'center', gap: '3px',
      padding: '1px 7px', borderRadius: '999px',
      fontSize: '11px', fontWeight: 700, lineHeight: '1.6',
    }}>
      {icon} {badge}
    </span>
  );
}

const CommentItem = ({ comment, onReply, onLike, isLiked = false, depth = 0 }: CommentItemProps) => {
  const [showSpoiler, setShowSpoiler] = useState(false);

  const renderText = (text: string) => {
    const parts = text.split(/(\[img\][^\]]*\[\/img\])/g);
    return parts.map((part, i) => {
      const m = part.match(/\[img\](.*?)\[\/img\]/);
      if (m) return <img key={i} src={m[1]} alt="gambar" className="max-w-full max-h-64 rounded-lg mt-2 border border-border block" />;
      return <span key={i} style={{whiteSpace:'pre-wrap'}}>{part}</span>;
    });
  };
  const [showReplies, setShowReplies] = useState(false);

  const formatDate = (dateString: string) => {
    const date = new Date(dateString.endsWith("Z") ? dateString : dateString + "Z");
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffInSeconds < 60) return 'Baru saja';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} menit lalu`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} jam lalu`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  };

  const [now, setNow] = useState(new Date());
  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  const badge = comment.badge || 'Penduduk Desa';
  const badgeIcon = comment.badge_icon || '👤';
  const badgeColor = comment.badge_color || 'gray';

  return (
    <div className={cn("group", depth > 0 && "ml-8 md:ml-12")}>
      <div className="flex gap-3 p-4 rounded-xl bg-secondary/30 border border-border/50 hover:border-purple/30 transition-all">
        {/* Avatar */}
        <Avatar className="w-10 h-10 ring-2 ring-purple/20">
          <AvatarImage src={comment.avatar_url} alt={comment.username} />
          <AvatarFallback className="bg-purple/20 text-purple-light">
            {comment.username?.charAt(0).toUpperCase()}
          </AvatarFallback>
        </Avatar>

        {/* Content */}
        <div className="flex-1 min-w-0">
          {/* Header */}
          <div className="flex flex-wrap items-center gap-2 mb-2">
            <a href={`/profile/${comment.user_id}`} className="font-semibold text-foreground hover:text-primary transition-colors cursor-pointer">{comment.username}</a>
            
            {/* Badge */}
            <BadgeDisplay badge={badge} icon={badgeIcon} color={badgeColor} />
            
            <span className="text-xs text-muted-foreground">
              {formatDate(comment.created_at)}
            </span>
          </div>

          {/* Comment Text with Spoiler Support */}
          {comment.is_spoiler && !showSpoiler ? (
            <button
              onClick={() => setShowSpoiler(true)}
              className="flex items-center gap-2 px-3 py-2 rounded-lg bg-destructive/10 border border-destructive/30 text-destructive text-sm hover:bg-destructive/20 transition-colors"
            >
              <AlertTriangle className="w-4 h-4" />
              <span>Klik untuk melihat bocoran</span>
            </button>
          ) : (
            <p className={cn(
              "text-sm text-foreground/90 whitespace-pre-wrap break-words",
              comment.is_spoiler && "bg-destructive/5 p-2 rounded-lg border border-destructive/20"
            )}>
              {renderText(comment.comment_text)}
            </p>
          )}

          {/* Actions */}
          <div className="flex items-center gap-4 mt-3">
            <button
              onClick={() => onLike(comment.id)}
              className={cn(
                "flex items-center gap-1.5 text-xs transition-colors",
                isLiked ? "text-purple" : "text-muted-foreground hover:text-purple"
              )}
            >
              <Heart className={cn("w-4 h-4", isLiked && "fill-current")} />
              <span>{comment.likes_count || 0}</span>
            </button>
            
            {depth === 0 && (
              <button
                onClick={() => onReply(comment.id)}
                className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-purple transition-colors"
              >
                <MessageCircle className="w-4 h-4" />
                <span>Balas</span>
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Replies Toggle */}
      {comment.replies && comment.replies.length > 0 && (
        <div className="mt-2">
          <button
            onClick={() => setShowReplies(!showReplies)}
            className="flex items-center gap-1 text-sm text-purple hover:text-purple-light transition-colors ml-4"
          >
            {showReplies ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
            <span>{showReplies ? 'Sembunyikan' : `Lihat ${comment.replies.length} balasan`}</span>
          </button>
          
          {showReplies && (
            <div className="mt-2 space-y-2">
              {comment.replies.map((reply) => (
                <CommentItem
                  key={reply.id}
                  comment={reply}
                  onReply={onReply}
                  onLike={onLike}
                  depth={depth + 1}
                />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default CommentItem;
