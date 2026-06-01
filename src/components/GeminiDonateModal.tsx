import { useState } from "react";
import { X, KeyRound, Check, ExternalLink, Sparkles } from "lucide-react";

export default function GeminiDonateModal({ onClose }: { onClose: () => void }) {
  const [key, setKey] = useState("");
  const [donor, setDonor] = useState("");
  const [status, setStatus] = useState<{ ok?: boolean; msg: string } | null>(null);
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!key.trim()) { setStatus({ ok: false, msg: "Masukkan API key dulu" }); return; }
    setLoading(true); setStatus({ msg: "Mengecek key..." });
    try {
      const res = await fetch("/api/gemini-key.php", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ key: key.trim(), donor: donor.trim() || "Anonim" }),
      });
      const d = await res.json();
      if (d.ok) { setStatus({ ok: true, msg: `${d.message} (Total pool: ${d.pool} key)` }); setKey(""); }
      else setStatus({ ok: false, msg: d.error || "Gagal" });
    } catch { setStatus({ ok: false, msg: "Gagal menghubungi server" }); }
    setLoading(false);
  };

  return (
    <div className="fixed inset-0 z-[110] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/80 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl">
        <div className="sticky top-0 bg-card flex items-center justify-between px-5 py-4 border-b border-border">
          <div className="flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-primary" />
            <h2 className="font-bold">AI Translate (Gemini)</h2>
          </div>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground"><X className="w-5 h-5" /></button>
        </div>

        <div className="p-5 space-y-4 text-sm">
          <div>
            <p className="text-muted-foreground leading-relaxed">
              Novel di sini bahasa Inggris. Fitur <b className="text-foreground">AI Translate</b> menerjemahkan chapter ke Bahasa Indonesia pakai Google Gemini (gratis).
            </p>
          </div>

          <div className="rounded-xl bg-secondary/50 border border-border p-4">
            <p className="font-semibold mb-2 flex items-center gap-2"><KeyRound className="w-4 h-4 text-primary" /> Donasikan API Key Kamu</p>
            <p className="text-muted-foreground text-xs leading-relaxed mb-3">
              Biar fitur translate jalan buat semua user, kamu bisa donasikan API key Gemini gratismu ke pool bersama. Sistem rotasi key otomatis biar gak kena limit.
            </p>
            <ol className="text-xs text-muted-foreground space-y-1.5 list-decimal list-inside mb-3">
              <li>Buka <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" className="text-primary inline-flex items-center gap-0.5">aistudio.google.com/apikey <ExternalLink className="w-3 h-3" /></a></li>
              <li>Login Google → klik <b>"Create API key"</b></li>
              <li>Copy key-nya, paste di bawah</li>
              <li>Klik <b>Cek &amp; Donasi</b> — kalau valid langsung masuk pool</li>
            </ol>

            <input value={donor} onChange={(e) => setDonor(e.target.value)} placeholder="Nama kamu (opsional)"
              className="w-full mb-2 px-3 py-2 rounded-lg bg-background border border-border text-sm outline-none focus:border-primary/50" />
            <input value={key} onChange={(e) => setKey(e.target.value)} placeholder="AIza... (API key Gemini)" type="password"
              className="w-full mb-3 px-3 py-2 rounded-lg bg-background border border-border text-sm outline-none focus:border-primary/50" />

            <button onClick={submit} disabled={loading}
              className="w-full py-2.5 rounded-lg bg-primary text-white font-semibold text-sm hover:bg-primary/90 disabled:opacity-50 flex items-center justify-center gap-2">
              {loading ? "Mengecek..." : <><Check className="w-4 h-4" /> Cek &amp; Donasi</>}
            </button>

            {status && (
              <p className={`mt-3 text-xs ${status.ok ? "text-green-400" : status.ok === false ? "text-red-400" : "text-muted-foreground"}`}>
                {status.msg}
              </p>
            )}
          </div>

          <p className="text-[11px] text-muted-foreground/70 text-center">
            Key kamu dicek validitasnya dulu sebelum disimpan. Dipakai bersama untuk fitur translate.
          </p>
        </div>
      </div>
    </div>
  );
}
