import { useState, useRef } from "react";
import { Send, AlertTriangle, X, ImagePlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

interface CommentEditorProps {
  onSubmit: (text: string, isSpoiler: boolean) => void;
  isLoading?: boolean;
  replyTo?: { id: number; username: string } | null;
  onCancelReply?: () => void;
  placeholder?: string;
}

const CommentEditor = ({
  onSubmit,
  isLoading = false,
  replyTo,
  onCancelReply,
  placeholder = "Tulis komentar..."
}: CommentEditorProps) => {
  const [text, setText] = useState("");
  const [isSpoiler, setIsSpoiler] = useState(false);
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { alert("Maksimal 5MB"); return; }
    const token = localStorage.getItem("token");
    if (!token) { alert("Login dulu!"); return; }
    setUploading(true);
    const form = new FormData();
    form.append("image", file);
    try {
      const r = await fetch("/api/upload.php", {
        method: "POST",
        headers: { Authorization: "Bearer " + token },
        body: form
      });
      const d = await r.json();
      if (d.success) setImageUrl(d.url);
      else alert(d.error || "Gagal upload");
    } catch { alert("Gagal upload"); }
    finally { setUploading(false); if (e.target) e.target.value = ""; }
  };

  const handleSubmit = () => {
    if ((!text.trim() && !imageUrl) || isLoading) return;
    let finalText = text.trim();
    if (imageUrl) finalText += (finalText ? "\n" : "") + "[img]" + imageUrl + "[/img]";
    onSubmit(finalText, isSpoiler);
    setText("");
    setIsSpoiler(false);
    setImageUrl(null);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      handleSubmit();
    }
  };

  return (
    <div className="rounded-xl bg-secondary/50 border border-border/50 p-4">
      {replyTo && (
        <div className="flex items-center justify-between mb-3 px-3 py-2 rounded-lg bg-purple/10 border border-purple/30">
          <span className="text-sm text-purple">
            Membalas <span className="font-semibold">@{replyTo.username}</span>
          </span>
          <button onClick={onCancelReply} className="text-purple hover:text-purple-light transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      <Textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder={placeholder}
        className="min-h-[100px] bg-background/50 border-border/50 focus:border-purple/50 resize-none"
        maxLength={1000}
      />

      {imageUrl && (
        <div className="relative mt-2 inline-block">
          <img src={imageUrl} alt="preview" className="max-h-32 rounded-lg border border-border" />
          <button onClick={() => setImageUrl(null)}
            className="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-destructive flex items-center justify-center">
            <X className="w-3 h-3 text-white" />
          </button>
        </div>
      )}

      <div className="flex items-center justify-between mt-3">
        <div className="flex items-center gap-2">
          <button
            onClick={() => fileRef.current?.click()}
            disabled={uploading}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-muted text-muted-foreground hover:bg-muted/80 transition-all disabled:opacity-50"
          >
            <ImagePlus className="w-4 h-4" />
            <span>{uploading ? "..." : "Gambar"}</span>
          </button>
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />

          <button
            onClick={() => setIsSpoiler(!isSpoiler)}
            className={cn(
              "flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all",
              isSpoiler
                ? "bg-destructive/20 text-destructive border border-destructive/50"
                : "bg-muted text-muted-foreground hover:bg-muted/80"
            )}
          >
            <AlertTriangle className="w-4 h-4" />
            <span>Bocoran</span>
          </button>

          <span className="text-xs text-muted-foreground">{text.length}/1000</span>
        </div>

        <Button
          onClick={handleSubmit}
          disabled={(!text.trim() && !imageUrl) || isLoading}
          className="bg-purple hover:bg-purple-light text-foreground gap-2"
        >
          <Send className="w-4 h-4" />
          <span className="hidden sm:inline">Kirim</span>
        </Button>
      </div>

      <p className="text-xs text-muted-foreground mt-2">
        Tekan <kbd className="px-1 py-0.5 rounded bg-muted text-xs">Ctrl + Enter</kbd> untuk kirim
      </p>
    </div>
  );
};

export default CommentEditor;
