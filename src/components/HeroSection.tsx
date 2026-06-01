import { Sparkles } from "lucide-react";
import { useSite } from "@/contexts/SiteContext";

const HeroSection = () => {
  const { siteName } = useSite();

  return (
    <section className="relative pt-24 pb-12 overflow-hidden">
      {/* Background Gradient */}
      <div className="absolute inset-0 bg-gradient-to-b from-primary/10 via-primary/5 to-transparent pointer-events-none" />

      {/* Decorative orbs */}
      <div className="absolute top-20 left-1/4 w-64 h-64 bg-primary/20 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute top-40 right-1/4 w-48 h-48 bg-primary/10 rounded-full blur-3xl pointer-events-none" />

      <div className="container mx-auto px-4 relative z-10">
        <div className="flex flex-col items-center text-center space-y-4">
          {/* Badge */}
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/20">
            <Sparkles className="w-4 h-4 text-primary" />
            <span className="text-sm font-medium text-primary">Platform Streaming Premium</span>
          </div>

          {/* Title */}
          <h1 className="text-4xl md:text-5xl lg:text-6xl font-black">
            <span className="text-primary">{siteName.replace('.me', '')}</span>
            <span className="text-foreground">.me</span>
          </h1>

          {/* Subtitle */}
          <p className="text-muted-foreground text-base md:text-lg max-w-lg">
            Nonton Anime, Movies & Baca Manga dalam satu platform
          </p>
        </div>
      </div>
    </section>
  );
};

export default HeroSection;
