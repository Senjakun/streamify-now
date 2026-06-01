import { createContext, useContext, useState, useEffect, ReactNode } from "react";
const API = "/api/auth.php";
interface User { id: number; username: string; email: string; roles: string[]; avatar_url?: string; badge?: string; badge_icon?: string; badge_color?: string; is_admin?: number; }
interface AuthContextType { user: User | null; isAuthenticated: boolean; login: (email: string, password: string) => Promise<{ error?: string }>; loginGoogle: (credential: string) => Promise<{ error?: string }>; register: (username: string, email: string, password: string) => Promise<{ error?: string }>; logout: () => void; refreshUser: () => Promise<void>; }
const AuthContext = createContext<AuthContextType | null>(null);
function normalizeUser(u: any): User {
  const roles = Array.isArray(u.roles) ? u.roles : [];
  return { ...u, roles, is_admin: roles.includes("admin") ? 1 : 0 };
}
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) return;
    fetch(`${API}?action=me`, { headers: { Authorization: `Bearer ${token}` } })
      .then((r) => r.json()).then((d) => { if (d.user) setUser(normalizeUser(d.user)); })
      .catch(() => localStorage.removeItem("token"));
  }, []);
  const login = async (email: string, password: string) => {
    const r = await fetch(`${API}?action=login`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ email, password }) });
    const d = await r.json();
    if (d.error) return { error: d.error };
    localStorage.setItem("token", d.token); setUser(normalizeUser(d.user)); return {};
  };
  const loginGoogle = async (credential: string) => {
    const r = await fetch(`${API}?action=google`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ credential }) });
    const d = await r.json();
    if (d.error) return { error: d.error };
    localStorage.setItem("token", d.token); setUser(normalizeUser(d.user)); return {};
  };
  const register = async (username: string, email: string, password: string) => {
    const r = await fetch(`${API}?action=register`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username, email, password }) });
    const d = await r.json();
    if (d.error) return { error: d.error };
    localStorage.setItem("token", d.token); setUser(normalizeUser(d.user)); return {};
  };
  const logout = () => { localStorage.removeItem("token"); setUser(null); };
  const refreshUser = async () => {
    const token = localStorage.getItem("token");
    if (!token) return;
    try {
      const r = await fetch(`${API}?action=me`, { headers: { Authorization: `Bearer ${token}` } });
      const d = await r.json();
      if (d.user) setUser(normalizeUser(d.user));
    } catch {}
  };
  return <AuthContext.Provider value={{ user, isAuthenticated: !!user, login, loginGoogle, register, logout, refreshUser }}>{children}</AuthContext.Provider>;
}
export const useAuth = () => { const ctx = useContext(AuthContext); if (!ctx) throw new Error("useAuth must be used within AuthProvider"); return ctx; };
