import { useState, useEffect, useCallback, useRef } from "react"
import { Link } from "react-router-dom"
import { Search, Flame, Sparkles, ChevronLeft, ChevronRight, Loader2 } from "lucide-react"
import Navbar from "@/components/Navbar"
import Footer from "@/components/Footer"

interface Drama {
  id: string; title: string; cover: string; synopsis: string;
  episodes: number; genres: string[]; is_hot: boolean; is_new: boolean;
  duration?: string; source: string;
}

type TabType = "beranda" | "hot" | "new"
type SourceType = "melolo" | "velolo" | "dramabox" | "netshort" | "stardusttv" | "dramabite" | "reelife"

const SOURCES: { id: SourceType; label: string; color: string }[] = [
  { id: "melolo",     label: "Melolo",     color: "bg-rose-500 hover:bg-rose-600" },
  { id: "velolo",     label: "Velolo",     color: "bg-purple-500 hover:bg-purple-600" },
  { id: "dramabox",   label: "DramaBox",   color: "bg-blue-500 hover:bg-blue-600" },
  { id: "netshort",   label: "NetShort",   color: "bg-green-500 hover:bg-green-600" },
  { id: "stardusttv", label: "StardustTV", color: "bg-indigo-500 hover:bg-indigo-600" },
  { id: "dramabite",  label: "DramaBite",  color: "bg-orange-500 hover:bg-orange-600" },
  { id: "reelife",    label: "Reelife",    color: "bg-teal-500 hover:bg-teal-600" },
]

const GENRES = ["Semua","Romantis","Drama","Komedi","Aksi","Thriller","Misteri","Fantasi","Horror"]

const SkeletonCard = () => (
  <div className="space-y-2">
    <div className="aspect-[3/5] rounded-xl bg-secondary animate-pulse" />
    <div className="h-4 w-3/4 bg-secondary rounded animate-pulse" />
    <div className="h-3 w-1/2 bg-secondary rounded animate-pulse" />
  </div>
)

export default function ShortDramaPage() {
  const [dramas, setDramas] = useState<Drama[]>([])
  const [featuredDramas, setFeaturedDramas] = useState<Drama[]>([])
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(true)
  const [page, setPage] = useState(1)
  const [nextOffset, setNextOffset] = useState(0)
  const [activeTab, setActiveTab] = useState<TabType>("beranda")
  const [activeSource, setActiveSource] = useState<SourceType>("velolo")
  const [activeGenre, setActiveGenre] = useState("Semua")
  const [searchQuery, setSearchQuery] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [bannerIndex, setBannerIndex] = useState(0)
  const bannerIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const fetchDramas = useCallback(async (
    action: string, source: SourceType, pageNum: number,
    offset: number, genre?: string, query?: string, append = false
  ) => {
    if (append) setLoadingMore(true)
    else setLoading(true)

    try {
      const params = new URLSearchParams({ action, source, page: pageNum.toString() })
      if (source === 'melolo') params.set('offset', offset.toString())
      if (genre && genre !== "Semua") params.append("genre", genre)
      if (query) params.append("q", query)

      const response = await fetch(`/api/shortdrama-proxy.php?${params}`)
      const json = await response.json()
      const data = json.data || {}
      const content = data.content || []

      if (append) {
        setDramas(prev => [...prev, ...content])
      } else {
        setDramas(content)
        const featured = content.filter((d: Drama) => d.is_hot || d.is_new).slice(0, 5)
        setFeaturedDramas(featured.length > 0 ? featured : content.slice(0, 5))
      }

      setHasMore(data.has_more || false)
      setNextOffset(data.next_offset || offset + 20)
    } catch (error) {
      console.error("Failed:", error)
    } finally {
      setLoading(false)
      setLoadingMore(false)
    }
  }, [])

  useEffect(() => {
    const action = activeTab === "hot" ? "hot" : activeTab === "new" ? "new" : "list"
    setPage(1)
    setNextOffset(0)
    setDramas([])
    fetchDramas(action, activeSource, 1, 0, activeGenre)
  }, [activeTab, activeSource, activeGenre, fetchDramas])

  useEffect(() => {
    if (searchQuery.length >= 2) {
      setLoading(true)
      setDramas([])
      fetchDramas("search", activeSource, 1, 0, undefined, searchQuery)
    } else if (searchQuery.length === 0 && searchInput.length === 0) {
      const action = activeTab === "hot" ? "hot" : activeTab === "new" ? "new" : "list"
      fetchDramas(action, activeSource, 1, 0, activeGenre)
    }
  }, [searchQuery])

  useEffect(() => {
    if (featuredDramas.length > 1) {
      bannerIntervalRef.current = setInterval(() => {
        setBannerIndex(prev => (prev + 1) % featuredDramas.length)
      }, 5000)
    }
    return () => { if (bannerIntervalRef.current) clearInterval(bannerIntervalRef.current) }
  }, [featuredDramas.length])

  const handleLoadMore = () => {
    const nextPage = page + 1
    setPage(nextPage)
    const action = searchQuery ? "search" : activeTab === "hot" ? "hot" : activeTab === "new" ? "new" : "list"
    fetchDramas(action, activeSource, nextPage, nextOffset, activeGenre, searchQuery || undefined, true)
  }

  const handleSearch = () => setSearchQuery(searchInput)
  const handleTabChange = (tab: TabType) => { setActiveTab(tab); setSearchQuery(""); setSearchInput("") }
  const getSourceColor = (source: string) => SOURCES.find(s => s.id === source)?.color.split(" ")[0] || "bg-gray-500"

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <main className="pt-20 pb-12">
        <div className="container mx-auto px-4">

          {/* Search */}
          <div className="flex gap-2 mb-5 mt-4">
            <div className="relative flex-1">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input type="text" placeholder="Cari drama..."
                value={searchInput} onChange={e => setSearchInput(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') handleSearch() }}
                className="w-full pl-12 pr-4 py-3 rounded-xl bg-secondary text-foreground placeholder:text-muted-foreground border-0 focus:outline-none focus:ring-2 focus:ring-rose-500" />
            </div>
            <button onClick={handleSearch} className="px-5 py-3 rounded-xl bg-rose-500 text-white font-medium hover:bg-rose-600">Cari</button>
            {searchQuery && <button onClick={() => { setSearchQuery(""); setSearchInput("") }} className="px-4 py-3 rounded-xl bg-secondary hover:bg-accent text-sm">Reset</button>}
          </div>

          {/* Source Buttons */}
          <div className="flex flex-wrap gap-2 mb-6">
            {SOURCES.map(source => (
              <button key={source.id} onClick={() => setActiveSource(source.id)}
                className={"px-4 py-2 rounded-full text-sm font-medium text-white transition-all " + (activeSource === source.id ? source.color : "bg-secondary text-muted-foreground hover:bg-accent")}>
                {source.label}
              </button>
            ))}
          </div>

          {/* Featured Banner */}
          {featuredDramas.length > 0 && !loading && (
            <div className="relative mb-8 rounded-2xl overflow-hidden" style={{paddingTop: '58%'}}>
              <Link to={`/shortdrama/${featuredDramas[bannerIndex]?.id}?source=${featuredDramas[bannerIndex]?.source}`}
                className="absolute inset-0">
                <img src={featuredDramas[bannerIndex]?.cover} alt={featuredDramas[bannerIndex]?.title}
                  className="w-full h-full object-cover object-top"
                  onError={e => { (e.target as HTMLImageElement).src = '/placeholder.svg' }} />
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-4">
                  <span className={"inline-block px-2 py-0.5 rounded text-xs font-medium text-white mb-1 " + getSourceColor(featuredDramas[bannerIndex]?.source || '')}>
                    {featuredDramas[bannerIndex]?.source}
                  </span>
                  <h2 className="text-lg font-bold text-white line-clamp-1">{featuredDramas[bannerIndex]?.title}</h2>
                  <p className="text-xs text-white/70 line-clamp-2 mt-0.5">{featuredDramas[bannerIndex]?.synopsis}</p>
                </div>
              </Link>
              {featuredDramas.length > 1 && (
                <>
                  <button onClick={() => setBannerIndex(prev => (prev - 1 + featuredDramas.length) % featuredDramas.length)}
                    className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 flex items-center justify-center text-white hover:bg-black/70">
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <button onClick={() => setBannerIndex(prev => (prev + 1) % featuredDramas.length)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 flex items-center justify-center text-white hover:bg-black/70">
                    <ChevronRight className="w-5 h-5" />
                  </button>
                  <div className="absolute bottom-3 right-4 flex gap-1.5">
                    {featuredDramas.map((_, idx) => (
                      <button key={idx} onClick={() => setBannerIndex(idx)}
                        className={"h-1.5 rounded-full transition-all " + (idx === bannerIndex ? "bg-rose-500 w-5" : "bg-white/50 w-1.5")} />
                    ))}
                  </div>
                </>
              )}
            </div>
          )}

          {/* Tabs */}
          <div className="flex items-center gap-1 mb-5 border-b border-border">
            {[{id:'beranda',label:'Beranda',icon:null},{id:'hot',label:'Hot',icon:<Flame className="w-4 h-4"/>},{id:'new',label:'Terbaru',icon:<Sparkles className="w-4 h-4"/>}].map(tab => (
              <button key={tab.id} onClick={() => handleTabChange(tab.id as TabType)}
                className={"px-5 py-3 font-medium transition-colors relative flex items-center gap-1.5 " + (activeTab === tab.id ? "text-rose-500" : "text-muted-foreground hover:text-foreground")}>
                {tab.icon}{tab.label}
                {activeTab === tab.id && <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-rose-500" />}
              </button>
            ))}
          </div>

          {/* Genre Filter */}
          <div className="flex gap-2 overflow-x-auto pb-3 mb-5" style={{scrollbarWidth:'none'}}>
            {GENRES.map(genre => (
              <button key={genre} onClick={() => setActiveGenre(genre)}
                className={"px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all flex-shrink-0 " + (activeGenre === genre ? "bg-rose-500 text-white" : "bg-secondary text-muted-foreground hover:bg-accent")}>
                {genre}
              </button>
            ))}
          </div>

          {/* Grid */}
          {loading ? (
            <div className="flex flex-col gap-3">
              {Array.from({length: 8}).map((_, i) => (
                <div key={i} className="flex gap-3">
                  <div className="w-28 aspect-[2/3] rounded-xl bg-secondary animate-pulse flex-shrink-0" />
                  <div className="flex-1 py-1 space-y-2">
                    <div className="h-3 bg-secondary rounded animate-pulse w-1/3" />
                    <div className="h-4 bg-secondary rounded animate-pulse w-4/5" />
                    <div className="h-3 bg-secondary rounded animate-pulse w-1/2" />
                    <div className="h-3 bg-secondary rounded animate-pulse w-full" />
                  </div>
                </div>
              ))}
            </div>
          ) : dramas.length === 0 ? (
            <div className="text-center py-20 text-muted-foreground">
              <p className="text-5xl mb-4">📭</p>
              <p>Tidak ada drama ditemukan</p>
            </div>
          ) : (
            <div className="flex flex-col gap-3">
              {dramas.map(drama => (
                <Link key={`${drama.source}-${drama.id}`} to={`/shortdrama/${drama.id}?source=${drama.source}`} className="group flex gap-3 items-start">
                  <div className="relative flex-shrink-0 w-28 aspect-[2/3] rounded-xl overflow-hidden bg-secondary">
                    <img src={drama.cover} alt={drama.title}
                      className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                      loading="lazy" onError={e => { (e.target as HTMLImageElement).src = '/placeholder.svg' }} />
                    <div className="absolute bottom-1 right-1 px-1.5 py-0.5 rounded bg-black/70 text-[9px] font-medium text-white">
                      {drama.duration || `${drama.episodes} Ep`}
                    </div>
                  </div>
                  <div className="flex-1 min-w-0 py-1">
                    <div className="flex items-center gap-1.5 mb-1">
                      <span className={"px-2 py-0.5 rounded text-[10px] font-bold text-white " + getSourceColor(drama.source)}>{drama.source}</span>
                      {drama.is_hot && <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500 text-white">🔥</span>}
                      {drama.is_new && <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500 text-white">NEW</span>}
                    </div>
                    <h3 className="text-sm font-semibold text-foreground line-clamp-2 group-hover:text-rose-500 transition-colors leading-snug">{drama.title}</h3>
                    {drama.genres?.length > 0 && (
                      <p className="text-xs text-muted-foreground mt-1 truncate">{drama.genres.slice(0,3).join(' • ')}</p>
                    )}
                    {drama.synopsis && (
                      <p className="text-xs text-muted-foreground mt-1 line-clamp-2 leading-relaxed">{drama.synopsis}</p>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          )}

          {hasMore && dramas.length > 0 && !loading && (
            <div className="flex justify-center mt-8">
              <button onClick={handleLoadMore} disabled={loadingMore}
                className="px-8 py-3 rounded-xl bg-rose-500 text-white font-medium hover:bg-rose-600 transition-colors disabled:opacity-50 flex items-center gap-2">
                {loadingMore ? <><Loader2 className="w-5 h-5 animate-spin" />Memuat...</> : "Muat Lebih"}
              </button>
            </div>
          )}
        </div>
      </main>
      <Footer />
    </div>
  )
}
