import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { SiteProvider } from "./contexts/SiteContext";
import { AuthProvider } from "./contexts/AuthContext";
import Index from "./pages/Index";
import AnimePage from "./pages/AnimePage";
import AnimeDetailPage from "./pages/AnimeDetailPage";
import DonghuaPage from "./pages/DonghuaPage";
import NovelPage from "./pages/NovelPage";
import NovelDetailPage from "./pages/NovelDetailPage";
import DonghuaDetailPage from "./pages/DonghuaDetailPage";
import MangaPage from "./pages/MangaPage";
import MangaDetailPage from "./pages/MangaDetailPage";
import SearchPage from "./pages/SearchPage";
import BookmarksPage from "./pages/BookmarksPage";
import HistoryPage from "./pages/HistoryPage";
import NotFound from "./pages/NotFound";
import ProfilePage from "./pages/ProfilePage";
import AdminPage from "./pages/AdminPage";
import AnnouncementsPage from "./pages/AnnouncementsPage";

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 5 * 60 * 1000 } },
});

const App = () => (
  <QueryClientProvider client={queryClient}>
    <AuthProvider>
      <SiteProvider>
        <TooltipProvider>
          <Toaster />
          <Sonner />
          <BrowserRouter>
            <Routes>
              <Route path="/" element={<Index />} />
              <Route path="/anime" element={<AnimePage />} />
              <Route path="/anime/:slug" element={<AnimeDetailPage />} />
              <Route path="/donghua" element={<DonghuaPage />} />
              <Route path="/donghua/:slug" element={<DonghuaDetailPage />} />
              <Route path="/novel" element={<NovelPage />} />
              <Route path="/novel/:slug" element={<NovelDetailPage />} />
              <Route path="/manga" element={<MangaPage />} />
              <Route path="/manga/:slug" element={<MangaDetailPage />} />
              <Route path="/search" element={<SearchPage />} />
              <Route path="/bookmarks" element={<BookmarksPage />} />
              <Route path="/history" element={<HistoryPage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/admin" element={<AdminPage />} />
              <Route path="/announcements" element={<AnnouncementsPage />} />
              <Route path="/profile/:userId" element={<ProfilePage />} />
              <Route path="*" element={<NotFound />} />
            </Routes>
          </BrowserRouter>
        </TooltipProvider>
      </SiteProvider>
    </AuthProvider>
  </QueryClientProvider>
);

export default App;
