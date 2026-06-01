import { Link } from "react-router-dom";

const categories = [
  {
    id: "anime",
    title: "Anime",
    description: "Streaming anime sub indo terlengkap",
    image: "https://images.unsplash.com/photo-1578632767115-351597cf2477?w=600&h=800&fit=crop",
    color: "bg-red-500",
  },
  {
    id: "movies",
    title: "Movies",
    description: "Film box office & series populer",
    image: "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=600&h=800&fit=crop",
    color: "bg-amber-500",
  },
  {
    id: "manga",
    title: "Manga",
    description: "Baca manga terbaru dari Kiryuu",
    image: "https://images.unsplash.com/photo-1612178537253-bccd437b730e?w=600&h=800&fit=crop",
    color: "bg-orange-500",
  },
];

const CategorySection = () => {
  return (
    <section className="py-8">
      <div className="container mx-auto px-4">
        {/* Category Grid - 3 columns on first row, 2 on second */}
        <div className="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
          {categories.map((category, index) => (
            <Link
              key={category.id}
              to={`/${category.id}`}
              className={`group relative rounded-xl overflow-hidden aspect-[3/4] ${
                index >= 3 ? "md:col-span-1" : ""
              }`}
            >
              {/* Background Image */}
              <img
                src={category.image}
                alt={category.title}
                className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              />

              {/* Gradient Overlay */}
              <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/20" />

              {/* Badge */}
              <div className="absolute top-3 left-3">
                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-semibold text-white ${category.color}`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-white" />
                  {category.title.toUpperCase()}
                </span>
              </div>

              {/* Content */}
              <div className="absolute bottom-0 left-0 right-0 p-4">
                <h3 className="text-lg md:text-xl font-bold text-white mb-1">
                  {category.title}
                </h3>
                <p className="text-xs md:text-sm text-white/70 line-clamp-2">
                  {category.description}
                </p>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
};

export default CategorySection;
