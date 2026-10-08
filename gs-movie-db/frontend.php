<?php
/**
 * Movie Database Frontend Renderer
 * Location: /plugins/gs-movie-db/frontend.php
 */

if (!defined('IN_GS')) { die('Direct access not allowed'); }

// Helper function for quick translation output
function i18n_movie($key) {
    return i18n_r('gs-movie-db/' . $key);
}

class MovieDBFrontend {

    private static function getBaseUrl() {
        global $SITEURL;
        if (function_exists('get_site_url')) {
            return get_site_url(false);
        } elseif (!empty($SITEURL)) {
            return $SITEURL;
        }
        return '/';
    }

    /**
     * Get clean catalogue root URL (e.g. https://site.com/movies/)
     */
    private static function getCatalogUrl() {
        $settings = MovieDB::getSettings();
        $prefix = isset($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';
        return rtrim(self::getBaseUrl(), '/') . '/' . $prefix;
    }

    /**
     * Render the search form bar and output matching results
     */
    public static function renderSearch() {
        $q = isset($_GET['q_movie']) ? trim($_GET['q_movie']) : '';
        $settings = MovieDB::getSettings();
        $prefix = isset($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';
        $baseUrl = self::getBaseUrl();
        $catalogUrl = self::getCatalogUrl() . '/';
        $minSearchLen = 2;

        ob_start();
        ?>
        <div class="moviedb-search-wrapper" style="margin-bottom: 2rem;">
            <!-- Form points directly to clean catalog URL without carrying 'id=movies' -->
            <form action="<?php echo htmlspecialchars($catalogUrl); ?>" method="get" class="moviedb-search-form" style="display: flex; gap: 8px; max-width: 600px;">
                <input 
                    type="text" 
                    name="q_movie" 
                    value="<?php echo htmlspecialchars($q); ?>" 
                    placeholder="<?php echo i18n_movie('SEARCH_FOR'); ?>" 
                    style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                    minlength="<?php echo $minSearchLen; ?>"
                    required
                />
                <button type="submit" style="width: auto; padding: 10px 18px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                    <?php echo i18n_movie('SEARCH'); ?>
                </button>
                <?php if (!empty($q)): ?>
                    <a href="<?php echo htmlspecialchars($catalogUrl); ?>" style="padding: 10px 14px; background: #888; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.9em; display: inline-block;"><?php echo i18n_movie('CLEAR'); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <?php
        if (!empty($q)) {
            if (mb_strlen($q, 'UTF-8') < $minSearchLen) {
                echo '<div class="moviedb-search-results">';
                echo '<p style="color: #c00;">' . i18n_movie('PLEASE_ENTER') . $minSearchLen . i18n_movie('MIN_CHAR_') . '</p>';
                echo '</div>';
            } else {
                $results = MovieDB::searchMovies($q, $minSearchLen);
                echo '<div class="moviedb-search-results">';
                echo '<h3>' . i18n_movie('SEARCH_RESULT') . ': "<em>' . htmlspecialchars($q) . '</em>" (' . count($results) . ')</h3>';

                if (!empty($results)) {
                    echo '<div class="moviedb-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">';
                    foreach ($results as $movie) {
                        $detailUrl = $baseUrl . $prefix . '/' . urlencode($movie['slug']);
                        $castStr = isset($movie['cast']) ? (is_array($movie['cast']) ? implode(', ', $movie['cast']) : $movie['cast']) : '';
                        $rawGenre = isset($movie['genres']) ? $movie['genres'] : '';
                        $genreStr = is_array($rawGenre) ? implode(', ', $rawGenre) : $rawGenre;
                        ?>
                        <div class="moviedb-card" style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; background: #fff;">
                            <?php if (!empty($movie['poster'])): ?>
                                <a href="<?php echo $detailUrl; ?>">
                                    <img src="<?php echo htmlspecialchars($movie['poster']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" style="width: 100%; height: auto; border-radius: 4px; object-fit: cover; aspect-ratio: 2/3;" />
                                </a>
                            <?php endif; ?>
                            <h4 style="margin: 10px 0 5px 0;">
                                <a href="<?php echo $detailUrl; ?>" style="text-decoration: none; color: #333;">
                                    <?php echo htmlspecialchars($movie['title']); ?> (<?php echo htmlspecialchars($movie['year']); ?>)
                                </a>
                            </h4>
                            <?php if (!empty($genreStr)): ?>
                                <p style="font-size: 0.85em; color: #666; margin: 3px 0;"><strong><?php echo i18n_movie('GENRES_'); ?>:</strong> <?php echo htmlspecialchars($genreStr); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($movie['director'])): ?>
                                <p style="font-size: 0.85em; color: #666; margin: 3px 0;"><strong><?php echo i18n_movie('DIRECTOR'); ?>:</strong> <?php echo htmlspecialchars($movie['director']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($castStr)): ?>
                                <p style="font-size: 0.85em; color: #666; margin: 3px 0;"><strong><?php echo i18n_movie('CAST_'); ?>:</strong> <?php echo htmlspecialchars($castStr); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    echo '</div>';
                } else {
                    echo '<p>' . i18n_movie('NO_MOVIE_MATCH') . '</p>';
                }
                echo '<hr style="border: 0; border-top: 1px solid #333; margin: 20px 0;"></div>';
            }
        }

        return ob_get_clean();
    }

    // Render Grid View with Clean Pagination
    public static function renderGrid() {
        if (!class_exists('MovieDB')) {
            require_once(GSPLUGINPATH . 'gs-movie-db/class.moviedb.php');
        }

        $all_movies = array_reverse(MovieDB::getMovies());
        $settings = MovieDB::getSettings();

        if (empty($all_movies)) {
            return '<div class="moviedb-empty"><p>' . i18n_movie('NO_MOVIE_LISTED') . '</p></div>';
        }

        // --- 1. Pagination Logic ---
        $per_page = !empty($settings['per_page']) ? max(1, intval($settings['per_page'])) : 12;
        $total_movies = count($all_movies);
        $total_pages = (int)ceil($total_movies / $per_page);

        $current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
        if ($current_page < 1) { $current_page = 1; }
        if ($current_page > $total_pages) { $current_page = $total_pages; }

        $offset = ($current_page - 1) * $per_page;
        $movies = array_slice($all_movies, $offset, $per_page);

        // --- 2. Build Grid HTML ---
        $base_url = rtrim(self::getBaseUrl(), '/') . '/';
        $slug_prefix = !empty($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';
        $catalog_url = self::getCatalogUrl();

        $html = '<div class="moviedb-grid-wrap" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';

        foreach ($movies as $m) {
            $poster = !empty($m['poster']) ? htmlspecialchars($m['poster']) : htmlspecialchars($settings['default_poster']);
            $title = htmlspecialchars($m['title']);
            $year = htmlspecialchars($m['year']);
            $rating = htmlspecialchars($m['rating']);
            $genres = htmlspecialchars($m['genres']);
            $movie_url = $base_url . $slug_prefix . '/' . htmlspecialchars($m['slug']);

            $html .= '
            <style>.moviedb-card img {transition: filter 0.3s ease;}.moviedb-card img:hover {filter: brightness(200%) grayscale(1);}</style>
            <div class="moviedb-card" style="border: 1px solid #333; border-radius: 8px; overflow: hidden; background: #0073AA; color: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
                <a href="' . $movie_url . '" style="text-decoration:none; color:inherit;">
                    <div style="position: relative; width: 100%; height: 100%; background: #000;">
                        <img src="' . $poster . '" alt="' . $title . '" style="width: 100%; height: 100%; object-fit: cover;">
                        <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.85); color: #ffca28; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">&#9733; ' . $rating . '</span>
                    </div>
                </a>
                <div style="padding: 12px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h4 style="margin: 0 0 5px 0; font-size: 16px;"><a href="' . $movie_url . '" style="text-decoration: none; color: #fff;">' . $title . '</a></h4>
                        <p style="margin: 0; font-size: 12px; color: #dbdbdb;">' . $year . ' &#9679; ' . $genres . '</p>
                    </div>
                </div>
            </div>';
        }

        $html .= '</div>';

        // --- 3. Build Clean Pagination Controls ---
        if ($total_pages > 1) {
            $html .= '<style>
            .moviedb-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 6px;
                margin: 30px 0;
                flex-wrap: wrap;
            }
            .moviedb-pagination a.page-btn, .moviedb-pagination .page-active {
                display: inline-block;
                padding: 8px 14px;
                background: #333;
                color: #fff;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: background-color 0.2s ease, color 0.2s ease;
            }
            .moviedb-pagination .page-active {
                background: #0073AA !important;
                color: #fff !important;
            }
            .moviedb-pagination a.page-btn:hover {
                background: #0073AA !important;
                color: #fff !important;
            }
            .moviedb-pagination .page-dots {
                color: #fff;
                padding: 0 4px;
                font-weight: bold;
            }
            </style>';

            $html .= '<div class="moviedb-pagination">';

            // Standard GetSimple URL Builder (?p=2)
            $buildPageUrl = function($pageNum) use ($catalog_url) {
                return htmlspecialchars($catalog_url . '?p=' . $pageNum);
            };

            // Prev Button
            if ($current_page > 1) {
                $html .= '<a href="' . $buildPageUrl($current_page - 1) . '" class="page-btn">&#171; ' . i18n_movie('PREV') . '</a>';
            }

            // Truncated Numbers Logic (1 2 3 ... 34)
            if ($total_pages <= 5) {
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i === $current_page) {
                        $html .= '<span class="page-active">' . $i . '</span>';
                    } else {
                        $html .= '<a href="' . $buildPageUrl($i) . '" class="page-btn">' . $i . '</a>';
                    }
                }
            } else {
                if ($current_page <= 3) {
                    for ($i = 1; $i <= 3; $i++) {
                        if ($i === $current_page) {
                            $html .= '<span class="page-active">' . $i . '</span>';
                        } else {
                            $html .= '<a href="' . $buildPageUrl($i) . '" class="page-btn">' . $i . '</a>';
                        }
                    }
                    $html .= '<span class="page-dots">&hellip;</span>';
                    $html .= '<a href="' . $buildPageUrl($total_pages) . '" class="page-btn">' . $total_pages . '</a>';
                } elseif ($current_page >= $total_pages - 2) {
                    $html .= '<a href="' . $buildPageUrl(1) . '" class="page-btn">1</a>';
                    $html .= '<span class="page-dots">&hellip;</span>';
                    for ($i = $total_pages - 2; $i <= $total_pages; $i++) {
                        if ($i === $current_page) {
                            $html .= '<span class="page-active">' . $i . '</span>';
                        } else {
                            $html .= '<a href="' . $buildPageUrl($i) . '" class="page-btn">' . $i . '</a>';
                        }
                    }
                } else {
                    $html .= '<a href="' . $buildPageUrl(1) . '" class="page-btn">1</a>';
                    $html .= '<span class="page-dots">&hellip;</span>';
                    $html .= '<span class="page-active">' . $current_page . '</span>';
                    $html .= '<span class="page-dots">&hellip;</span>';
                    $html .= '<a href="' . $buildPageUrl($total_pages) . '" class="page-btn">' . $total_pages . '</a>';
                }
            }

            // Next Button
            if ($current_page < $total_pages) {
                $html .= '<a href="' . $buildPageUrl($current_page + 1) . '" class="page-btn">' . i18n_movie('NEXT') . ' &#187;</a>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    // Render Individual Detail Page
    public static function renderDetail($slug) {
        if (!class_exists('MovieDB')) {
            require_once(GSPLUGINPATH . 'gs-movie-db/class.moviedb.php');
        }

        $movie = MovieDB::getMovieBySlug($slug);
        $settings = MovieDB::getSettings();

        if (!$movie) {
            return '<div class="moviedb-error"><h3>' . i18n_movie('MOVIE_NOT_FOUND') . '</h3><p>' . i18n_movie('MOVIE_NOT_FOUND_') . '</p></div>';
        }

        $base_url = rtrim(self::getBaseUrl(), '/') . '/';
        $slug_prefix = !empty($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';
        $back_url = $base_url . $slug_prefix;

        $title = htmlspecialchars($movie['title']);
        $year = htmlspecialchars($movie['year']);
        $runtime = htmlspecialchars($movie['runtime']);
        $rating = htmlspecialchars($movie['rating']);
        $genres = htmlspecialchars($movie['genres']);
        $synopsis = nl2br(htmlspecialchars($movie['synopsis']));
        $director = htmlspecialchars($movie['director']);
        $cast = htmlspecialchars($movie['cast']);
        $poster = !empty($movie['poster']) ? htmlspecialchars($movie['poster']) : htmlspecialchars($settings['default_poster']);
        $backdrop = !empty($movie['backdrop']) ? htmlspecialchars($movie['backdrop']) : $poster;
        $imdb = !empty($movie['imdb']) ? htmlspecialchars($movie['imdb']) : '';

        // Parse YouTube Trailer ID
        $trailer_id = '';
        if (!empty($movie['trailer'])) {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/??]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $movie['trailer'], $match)) {
                $trailer_id = $match[1];
            } else {
                $trailer_id = htmlspecialchars($movie['trailer']);
            }
        }

        return '
        <style>
        @media print {
            .moviedb-backdrop-banner {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        .moviedb-back-link {
            text-decoration: none;
            color: #0073AA;
            font-weight: bold;
            transition: color 0.2s ease;
        }
        .moviedb-back-link:hover {
            color: #02A7E1 !important;
        }
        </style>
        <div class="moviedb-detail-wrap">
            <p style="margin: 15px 0 15px;"><a href="' . $back_url . '" class="moviedb-back-link">&#171; ' . i18n_movie('BACK_TO_CATALOG') . '</a></p>
            <div class="moviedb-backdrop-banner" style="position: relative; width: 100%; height: 320px; background: #000 url(\'' . $backdrop . '\') center/cover no-repeat; border-radius: 8px; overflow: hidden; margin-bottom: 25px;">
                <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.1) 100%);"></div>
                <div style="position: absolute; bottom: 20px; left: 20px; right: 20px; color: #fff;">
                    <h1 style="margin: 0 0 5px 0; font-size: 28px; color: #fff;">' . $title . ' <span style="font-weight: normal; font-size: 20px; opacity: 0.8;">(' . $year . ')</span></h1>
                    <p style="margin: 0; font-size: 14px; color:#ffe793; opacity: 0.8;">' . $genres . '</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px; max-width: 280px;">
                    <img src="' . $poster . '" alt="' . $title . '" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                    
                    ' . (!empty($imdb) ? '
                    <a href="' . $imdb . '" target="_blank" rel="noopener noreferrer" style="display: block; width: 100%; text-align: center; margin-top: 12px; padding: 10px 0; background: #f5c518; color: #000; font-weight: bold; text-decoration: none; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        ' . i18n_movie('IMDB_VIEW') . ' &#127916;
                    </a>
                    ' : '') . '<p><b>' . i18n_movie('RATING_') . ':</b> <span style="color:#F5C518">&#9733; </span>' . $rating . '/10 <b>'. i18n_movie('RUNTIME_F') . ':</b> ' . $runtime . ' ' . i18n_movie('RUNTIME_') . '</p>
                </div>

                <div style="flex: 2; min-width: 280px;">
                    <h3>' . i18n_movie('SYNOPSIS_') . '</h3>
                    <p style="line-height: 1.6;">' . $synopsis . '</p>

                    <hr style="border: 0; border-top: 1px solid #333; margin: 20px 0;">

                    <p><strong>' . i18n_movie('DIRECTOR') . ':</strong> ' . ($director ?: 'N/A') . '</p>
                    <p><strong>' . i18n_movie('CAST_') . ':</strong> ' . ($cast ?: 'N/A') . '</p>

                    ' . (!empty($trailer_id) ? '
                    <h3 style="margin: 25px 0 10px;">' . i18n_movie('TRAILER') . '</h3>
                    <div style="position: relative; margin-bottom:25px; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; background: #000;">
                        <iframe src="https://www.youtube.com/embed/' . $trailer_id . '" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                    ' : '') . '
                </div>
            </div>
        </div>';
    }
}