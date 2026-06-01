import { Heart, Twitter, Instagram, Github, Shield } from "lucide-react";
import { Link } from "react-router-dom";
import { useSite } from "@/contexts/SiteContext";

const Footer = () => {
  const currentYear = new Date().getFullYear();
  const { siteName, tagline } = useSite();

  const footerLinks = [
    {
      title: "Kategori",
      links: [
        { name: "Anime", href: "/anime" },
        { name: "Donghua", href: "/donghua" },
        { name: "Manga", href: "/manga" },
        { name: "Novel", href: "/novel" },
      ],
    },
    {
      title: "Navigasi",
      links: [
        { name: "Beranda", href: "/" },
        { name: "Bookmark", href: "/bookmarks" },
        { name: "Search", href: "/search" },
      ],
    },
    {
      title: "Informasi",
      links: [
        { name: "Tentang Kami", href: "/about" },
        { name: "Kontak", href: "/contact" },
        { name: "DMCA", href: "/dmca" },
        { name: "Disclaimer", href: "/disclaimer" },
      ],
    },
  ];

  return (
    <footer className="bg-card border-t border-border mt-12">
      <div className="container mx-auto px-4 py-10">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
          {/* Brand */}
          <div className="col-span-2 md:col-span-1 space-y-4">
            <Link to="/" className="flex items-center gap-2">
              <div className="w-8 h-8 rounded bg-primary flex items-center justify-center">
                <span className="text-primary-foreground font-bold">▶</span>
              </div>
              <span className="text-lg font-bold text-primary">{siteName}</span>
            </Link>
            <p className="text-sm text-muted-foreground leading-relaxed">
              {tagline}
            </p>
            <div className="flex items-center gap-2">
              <a
                href="#"
                className="w-8 h-8 rounded-lg bg-secondary flex items-center justify-center text-muted-foreground hover:text-primary hover:bg-primary/10 transition-colors"
              >
                <Twitter className="w-4 h-4" />
              </a>
              <a
                href="#"
                className="w-8 h-8 rounded-lg bg-secondary flex items-center justify-center text-muted-foreground hover:text-primary hover:bg-primary/10 transition-colors"
              >
                <Instagram className="w-4 h-4" />
              </a>
              <a
                href="#"
                className="w-8 h-8 rounded-lg bg-secondary flex items-center justify-center text-muted-foreground hover:text-primary hover:bg-primary/10 transition-colors"
              >
                <Github className="w-4 h-4" />
              </a>
            </div>
          </div>

          {/* Links */}
          {footerLinks.map((section) => (
            <div key={section.title}>
              <h3 className="font-semibold text-foreground mb-3 text-sm">{section.title}</h3>
              <ul className="space-y-2">
                {section.links.map((link) => (
                  <li key={link.name}>
                    <Link
                      to={link.href}
                      className="text-sm text-muted-foreground hover:text-primary transition-colors"
                    >
                      {link.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom */}
        <div className="mt-10 pt-6 border-t border-border">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
              © {currentYear} {siteName}. All rights reserved.
            </p>
            <div className="flex items-center gap-4">
              <Link
                to="/admin"
                className="text-xs text-muted-foreground/50 hover:text-muted-foreground flex items-center gap-1 transition-colors"
              >
                <Shield className="w-3 h-3" />
                Admin
              </Link>
              <p className="text-sm text-muted-foreground flex items-center gap-1">
                Made with <Heart className="w-4 h-4 text-primary fill-primary" /> in Indonesia
              </p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
