import React from "react";
import { Link } from "react-router-dom";
import { BookOpen, Star } from "lucide-react";
import { Novel } from "../../types/novel";

interface NovelCardProps {
  novel: Novel;
  compact?: boolean;
  rank?: number;
}

const NovelCard: React.FC<NovelCardProps> = ({ novel, compact = false, rank }) => {
  return (
    <Link
      to={`/novel/${novel.slug}`}
      className={[
        "group relative flex flex-col rounded-xl overflow-hidden",
        "bg-card border border-border",
        "hover:border-primary/40 hover:-translate-y-1",
        "transition-all duration-300 ease-out",
        "hover:shadow-xl hover:shadow-primary/10",
        compact ? "min-w-[150px] w-[150px] flex-shrink-0" : "",
      ].join(" ")}
    >
      {/* Cover */}
      <div className="relative overflow-hidden aspect-[2/3] bg-secondary">
        {novel.poster_url ? (
          <img
            referrerPolicy="no-referrer"
            src={novel.poster_url}
            alt={novel.title}
            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            onError={(e) => { (e.target as HTMLImageElement).src = '/placeholder.svg'; }}
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-secondary">
            <BookOpen className="w-10 h-10 text-muted-foreground/30" />
          </div>
        )}

        {/* Gradient overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

        {/* Rank badge */}
        {rank && (
          <div className="absolute top-2 left-2 w-7 h-7 rounded-lg bg-primary text-primary-foreground flex items-center justify-center font-bold text-xs">
            #{rank}
          </div>
        )}

        {/* Status badge */}
        <span className={[
          "absolute top-2 right-2 px-2 py-0.5 rounded text-[10px] font-semibold",
          novel.status === "Tamat" || novel.status === "completed"
            ? "bg-green-500/80 text-white"
            : "bg-orange-500/80 text-white",
        ].join(" ")}>
          {novel.status === "Tamat" || novel.status === "completed" ? "Tamat" : "Ongoing"}
        </span>

        {/* Chapter count */}
        <div className="absolute bottom-2 left-2 flex items-center gap-1 px-2 py-0.5 rounded bg-black/70 text-white text-[10px]">
          <BookOpen className="w-3 h-3" />
          {novel.total_chapters} Ch
        </div>

        {/* Rating */}
        {novel.rating > 0 && (
          <div className="absolute bottom-2 right-2 flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-black/70 text-white text-[10px]">
            <Star className="w-3 h-3 text-yellow-400 fill-yellow-400" />
            {novel.rating.toFixed(1)}
          </div>
        )}
      </div>

      {/* Info */}
      <div className={compact ? "p-2" : "p-3"}>
        <h3 className={[
          "font-semibold text-foreground leading-snug line-clamp-2 group-hover:text-primary transition-colors",
          compact ? "text-[11px]" : "text-xs",
        ].join(" ")}>
          {novel.title}
        </h3>
        {!compact && novel.author && (
          <p className="text-[11px] text-muted-foreground mt-1 truncate">{novel.author}</p>
        )}
      </div>
    </Link>
  );
};

export default NovelCard;
