#!/bin/bash
# /root/streamify-now-main/cron-scrape.sh
# Run every 6 hours to update content
LOG="/var/log/playall-scrape.log"
echo "=== $(date) ===" >> $LOG

# Anime: otakudesu.blog full catalog (1812) + episodes (multi-res streaming + download)
docker exec streamify-php php /var/www/api/scraper/otakudesu-blog.php >> $LOG 2>&1

# Donghua: animexin (multi-sub, clean embeds) - full catalog + episodes
docker exec streamify-php php /var/www/api/scraper/donghua-animexin.php >> $LOG 2>&1

# Manga: scrape latest hot from komiku (images server-rendered)
docker exec streamify-php php /var/www/api/scraper/komiku-scraper.php hot 3 >> $LOG 2>&1

# Novel PRIMARY: readnovelfull (latest + popular)
docker exec streamify-php php /var/www/api/scraper/novel-readnovelfull.php latest 3 1 >> $LOG 2>&1
docker exec streamify-php php /var/www/api/scraper/novel-readnovelfull.php popular 2 1 >> $LOG 2>&1

# Novel: readnovelfull fanfiction genre
docker exec streamify-php php /var/www/api/scraper/novel-readnovelfull.php genre:fanfiction 2 1 >> $LOG 2>&1

echo "=== DONE $(date) ===" >> $LOG
