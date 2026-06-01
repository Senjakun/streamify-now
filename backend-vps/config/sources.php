<?php
/**
 * Sumber Data Configuration
 * Konfigurasi URL sumber untuk scraping
 */

return [
    // =============================================
    // ANIME SOURCE - Otakudesu.best
    // =============================================
    'anime' => [
        'name' => 'Otakudesu',
        'base_url' => 'https://otakudesu.best',
        'endpoints' => [
            'ongoing' => '/ongoing-anime/',
            'complete' => '/complete-anime/',
            'search' => '/?s={query}&post_type=anime',
            'detail' => '/anime/{slug}/',
        ],
        'selectors' => [
            'list_item' => '.detpost',
            'title' => '.jdlflm',
            'poster' => 'img',
            'episode' => '.epz',
            'link' => 'a',
        ]
    ],
    
    // =============================================
    // MANGA SOURCE - Kiryuu03.com
    // =============================================
    'manga' => [
        'name' => 'Kiryuu',
        'base_url' => 'https://kiryuu03.com',
        'endpoints' => [
            'latest' => '/',
            'popular' => '/manga/?orderby=popular',
            'search' => '/?s={query}',
            'detail' => '/manga/{slug}/',
            'chapter' => '/manga/{slug}/{chapter}/',
        ],
        'selectors' => [
            'list_item' => '.bs',
            'title' => '.tt',
            'poster' => 'img',
            'chapter' => '.epxs',
            'rating' => '.numscore',
            'link' => 'a',
        ],
        'replace' => [
            'source_name' => 'Kiryuu ID',
        ]
    ],
    
    // =============================================
    // MOVIE SOURCE - Filmapik (Dynamic Domain)
    // =============================================
    'movie' => [
        'name' => 'Filmapik',
        'base_url' => 'https://filmapik.fitness', // Auto-detected, may change
        'check_url' => 'https://filmapik.info/',
        'endpoints' => [
            'latest' => '/',
            'movies' => '/nonton-film/',
            'tvshows' => '/tvshow/',
            'category' => '/category/{category}/',
            'search' => '/?s={query}',
            'detail' => '/nonton-film/{slug}/',
        ],
        'selectors' => [
            'list_item' => 'article.item',
            'title' => '.title a, h4 a',
            'poster' => 'img',
            'year' => '.year',
            'quality' => '.quality',
            'rating' => '.imdb',
            'link' => 'a',
        ],
        'replace' => [
            'source_name' => 'Filmapik',
            'source_name_variants' => ['FILMAPIK', 'filmapik', 'Film apik'],
        ]
    ],
];
