import { Layers, Zap, Smartphone } from "lucide-react";
import { useSite } from "@/contexts/SiteContext";

const features = [
  {
    icon: Layers,
    title: "Multi-Kategori",
    description: "Anime, K-Drama, Movies, Short Drama, dan Novel dalam satu platform terintegrasi",
    color: "text-primary bg-primary/10",
  },
  {
    icon: Zap,
    title: "Update Cepat",
    description: "Episode baru langsung tersedia setelah rilis dengan kualitas terbaik",
    color: "text-primary bg-primary/10",
  },
  {
    icon: Smartphone,
    title: "Mobile Friendly",
    description: "Tonton dimana saja dari perangkat apapun dengan UI yang responsif",
    color: "text-primary bg-primary/10",
  },
];

const FeaturesSection = () => {
  const { siteName } = useSite();

  return (
    <section className="py-16 relative overflow-hidden">
      {/* Background decoration */}
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-primary/5 to-transparent pointer-events-none" />

      <div className="container mx-auto px-4 relative">
        {/* Header */}
        <div className="text-center mb-10">
          <h2 className="text-2xl md:text-3xl font-bold text-foreground mb-2">
            Kenapa {siteName}?
          </h2>
          <p className="text-muted-foreground text-sm md:text-base max-w-md mx-auto">
            Semua konten favorit dalam satu tempat dengan pengalaman premium
          </p>
        </div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 max-w-4xl mx-auto">
          {features.map((feature) => {
            const Icon = feature.icon;
            return (
              <div
                key={feature.title}
                className="group p-6 rounded-xl bg-card/50 border border-border/50 hover:border-primary/30 hover:bg-card/80 transition-all duration-300 text-center"
              >
                <div className={`w-12 h-12 rounded-xl ${feature.color} flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform`}>
                  <Icon className="w-6 h-6" />
                </div>
                <h3 className="text-base font-semibold text-foreground mb-2">
                  {feature.title}
                </h3>
                <p className="text-sm text-muted-foreground leading-relaxed">
                  {feature.description}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default FeaturesSection;
