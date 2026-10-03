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

    // Render Grid View with Pagination
    public static function renderGrid() {
        if (!class_exists('MovieDB')) {
            require_once(GSPLUGINPATH . 'gs-movie-db/class.moviedb.php');
        }

        $all_movies = MovieDB::getMovies();
        $settings   = MovieDB::getSettings();

        if (empty($all_movies)) {
            return '<div class="moviedb-empty"><p>No movies currently listed in catalogue.</p></div>';
        }

        // --- 1. Pagination Logic ---
        $per_page     = !empty($settings['per_page']) ? max(1, intval($settings['per_page'])) : 12;
        $total_movies = count($all_movies);
        $total_pages  = ceil($total_movies / $per_page);

        // Get current page from query string (e.g. ?p=2)
        $current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
        if ($current_page < 1) { $current_page = 1; }
        if ($current_page > $total_pages) { $current_page = $total_pages; }

        // Offset & Slice array for current page
        $offset = ($current_page - 1) * $per_page;
        $movies = array_slice($all_movies, $offset, $per_page);

        // --- 2. Build Grid HTML ---
        $base_url    = rtrim(self::getBaseUrl(), '/') . '/';
        $slug_prefix = !empty($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';

        $html = '<div class="moviedb-grid-wrap" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';

        foreach ($movies as $m) {
            $poster    = !empty($m['poster']) ? htmlspecialchars($m['poster']) : htmlspecialchars($settings['default_poster']);
            $title     = htmlspecialchars($m['title']);
            $year      = htmlspecialchars($m['year']);
            $rating    = htmlspecialchars($m['rating']);
            $genres    = htmlspecialchars($m['genres']);
            $movie_url = $base_url . $slug_prefix . '/' . htmlspecialchars($m['slug']);

            $html .= '
            <div class="moviedb-card" style="border: 1px solid #333; border-radius: 8px; overflow: hidden; background: #1a1a1a; color: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
                <a href="' . $movie_url . '" style="text-decoration:none; color:inherit;">
                    <div style="position: relative; width: 100%; height: 100%; background: #000;">
                        <img src="' . $poster . '" alt="' . $title . '" style="width: 100%; height: 100%; object-fit: cover;">
                        <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.85); color: #ffca28; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">&#9733; ' . $rating . '</span>
                    </div>
                </a>
                <div style="padding: 12px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h4 style="margin: 0 0 5px 0; font-size: 16px;"><a href="' . $movie_url . '" style="text-decoration: none; color: #fff;">' . $title . '</a></h4>
                        <p style="margin: 0; font-size: 12px; color: #aaa;">' . $year . ' &#9679; ' . $genres . '</p>
                    </div>
                </div>
            </div>';
        }

        $html .= '</div>';

        // --- 3. Build Pagination Controls ---
        if ($total_pages > 1) {
            // Build current URL preserving non-pagination parameters
            $query_params = $_GET;
            
            $html .= '<div class="moviedb-pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 30px 0;">';

            // Previous Button
            if ($current_page > 1) {
                $query_params['p'] = $current_page - 1;
                $prev_link = '?' . http_build_query($query_params);
                $html .= '<a href="' . $prev_link . '" style="padding: 8px 14px; background: #333; color: #fff; border-radius: 4px; text-decoration: none;">&#171; '. i18n_movie('PREV') .'</a>';
            }

            // Numbered Page Buttons
            for ($i = 1; $i <= $total_pages; $i++) {
                $query_params['p'] = $i;
                $page_link = '?' . http_build_query($query_params);

                if ($i === $current_page) {
                    $html .= '<span style="padding: 8px 14px; background: #ffca28; color: #000; border-radius: 4px; font-weight: bold;">' . $i . '</span>';
                } else {
                    $html .= '<a href="' . $page_link . '" style="padding: 8px 14px; background: #333; color: #fff; border-radius: 4px; text-decoration: none;">' . $i . '</a>';
                }
            }

            // Next Button
            if ($current_page < $total_pages) {
                $query_params['p'] = $current_page + 1;
                $next_link = '?' . http_build_query($query_params);
                $html .= '<a href="' . $next_link . '" style="padding: 8px 14px; background: #333; color: #fff; border-radius: 4px; text-decoration: none;">'. i18n_movie('NEXT') .' &#187;</a>';
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
            return '<div class="moviedb-error"><h3>'. i18n_movie('MOVIE_NOT_FOUND') .'</h3><p>'. i18n_movie('MOVIE_NOT_FOUND_') .'</p></div>';
        }

        $base_url    = rtrim(self::getBaseUrl(), '/') . '/';
        $slug_prefix = !empty($settings['slug_prefix']) ? trim($settings['slug_prefix'], '/') : 'movies';
        $back_url    = $base_url . $slug_prefix;

        $title    = htmlspecialchars($movie['title']);
        $year     = htmlspecialchars($movie['year']);
        $runtime  = htmlspecialchars($movie['runtime']);
        $rating   = htmlspecialchars($movie['rating']);
        $genres   = htmlspecialchars($movie['genres']);
        $synopsis = nl2br(htmlspecialchars($movie['synopsis']));
        $director = htmlspecialchars($movie['director']);
        $cast     = htmlspecialchars($movie['cast']);
        $poster   = !empty($movie['poster']) ? htmlspecialchars($movie['poster']) : htmlspecialchars($settings['default_poster']);
        $backdrop = !empty($movie['backdrop']) ? htmlspecialchars($movie['backdrop']) : $poster;

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
        <div class="moviedb-detail-wrap">
            <p style="margin-bottom: 15px;"><a href="' . $back_url . '" style="text-decoration:none; color: #ffca28;">&#171; '. i18n_movie('BACK_TO_CATALOG') .'</a></p>

            <div style="position: relative; width: 100%; height: 320px; background: #000 url(\'' . $backdrop . '\') center/cover no-repeat; border-radius: 8px; overflow: hidden; margin-bottom: 25px;">
                <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.3) 100%);"></div>
                <div style="position: absolute; bottom: 20px; left: 20px; right: 20px; color: #fff;">
                    <h1 style="margin: 0 0 5px 0; font-size: 28px; color: #fff;">' . $title . ' <span style="font-weight: normal; font-size: 20px; opacity: 0.8;">(' . $year . ')</span></h1>
                    <p style="margin: 0; font-size: 14px; color:#ffe793; opacity: 0.9;"> ' . $runtime . ' '. i18n_movie('RUNTIME_') .' |  ' . $rating . '/10 | ' . $genres . '</p>
                </div>
            </div>

            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px; max-width: 280px;">
                    <img src="' . $poster . '" alt="' . $title . '" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                </div>

                <div style="flex: 2; min-width: 280px;">
                    <h3>'. i18n_movie('SYNOPSIS_') .'</h3>
                    <p style="line-height: 1.6;">' . $synopsis . '</p>

                    <hr style="border: 0; border-top: 1px solid #333; margin: 20px 0;">

                    <p><strong>'. i18n_movie('DIRECTOR') .':</strong> ' . ($director ?: 'N/A') . '</p>
                    <p><strong>'. i18n_movie('CAST_') .':</strong> ' . ($cast ?: 'N/A') . '</p>

                    ' . (!empty($trailer_id) ? '
                    <h3 style="margin-top: 25px;">Trailer</h3>
                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; background: #000;">
                        <iframe src="https://www.youtube.com/embed/' . $trailer_id . '" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                    ' : '') . '
                </div>
            </div>
        </div>';
    }
}
?>