import React, { createContext, useContext, useState, ReactNode } from 'react';

interface SiteContextType {
  siteName: string;
  setSiteName: (name: string) => void;
  siteLogo: string | null;
  setSiteLogo: (logo: string | null) => void;
  tagline: string;
  setTagline: (tagline: string) => void;
}

const SiteContext = createContext<SiteContextType | undefined>(undefined);

// ✅ FIX MASALAH 3: Nama situs diseragamkan jadi "Streamify.me"
// Sebelumnya database.php pakai "Playall.me" tapi frontend pakai "Streamify.me"
// Pilih salah satu — kalau mau ganti nama, ubah di sini SAJA
const SITE_NAME = import.meta.env.VITE_SITE_NAME || 'Streamify.me';
const SITE_TAGLINE = 'Nonton Anime, Movies & Baca Manga dalam satu platform';

export const SiteProvider = ({ children }: { children: ReactNode }) => {
  const [siteName, setSiteName] = useState(SITE_NAME);
  const [siteLogo, setSiteLogo] = useState<string | null>(null);
  const [tagline, setTagline] = useState(SITE_TAGLINE);

  return (
    <SiteContext.Provider value={{
      siteName,
      setSiteName,
      siteLogo,
      setSiteLogo,
      tagline,
      setTagline
    }}>
      {children}
    </SiteContext.Provider>
  );
};

export const useSite = () => {
  const context = useContext(SiteContext);
  if (context === undefined) {
    throw new Error('useSite must be used within a SiteProvider');
  }
  return context;
};