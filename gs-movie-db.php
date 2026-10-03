<?php
/**
 * Plugin Name: Movie Database Manager
 * Description: Movie DB plugin for GetSimple CMS CE
 * Version: 1.0.0
 * Author: Zeth
 */

if (!defined('IN_GS')) { die('Direct access not allowed'); }

// 1. Register Plugin
$thisfile = basename(__FILE__);
register_plugin(
    'gs-movie-db',
    'Movie Database Manager',
    '1.0',
    'Zeth',
    'https://getsimple-ce.ovh/',
    'Manage catalogue, ratings, trailers, and movie pages.',
    'plugins',
    'moviedb_admin_main'
);

// Load Language File
i18n_merge('gs-movie-db') || i18n_merge('gs-movie-db', 'en_US');

// 2. Load Required Classes
require_once(GSPLUGINPATH . 'gs-movie-db/class.moviedb.php');

// 3. Register Admin Hooks
add_action('plugins-sidebar', 'createSideMenu', array('gs-movie-db', 'Movie Database'));

function moviedb_admin_main() {
    include(GSPLUGINPATH . 'gs-movie-db/admin.php');
}

// 4. Register Frontend Shortcodes & URL Router
add_filter('content', 'moviedb_shortcode_filter');
add_action('index-pretemplate', 'moviedb_url_router');

function moviedb_shortcode_filter($content) {
    require_once(GSPLUGINPATH . 'gs-movie-db/frontend.php');

    // Handle detail page injection
    if (isset($GLOBALS['MOVIEDB_CURRENT_SLUG'])) {
        return MovieDBFrontend::renderDetail($GLOBALS['MOVIEDB_CURRENT_SLUG']);
    }

    // Handle shortcode grid
    if (strpos($content, '(% movie_db_grid %)') !== false) {
        $content = str_replace('(% movie_db_grid %)', MovieDBFrontend::renderGrid(), $content);
    }

    return $content;
}

/**
 * URL Interceptor: Detects /movies/slug requests
 */
function moviedb_url_router() {
    global $url, $title;

    $settings = MovieDB::getSettings();
    $prefix   = trim($settings['slug_prefix'], '/');

    // Handle standard query params or fancy rewrites
    $movie_slug = '';
    
    if (isset($_GET['movie'])) {
        $movie_slug = $_GET['movie'];
    } else {
        // Parse current URI against prefix
        $uri = trim($_SERVER['REQUEST_URI'], '/');
        $parts = explode('/', $uri);
        
        // Find position of prefix in path
        $key = array_search($prefix, $parts);
        if ($key !== false && isset($parts[$key + 1])) {
            // Strip query strings if present
            $slug_part = explode('?', $parts[$key + 1]);
            $movie_slug = $slug_part[0];
        }
    }

    if (!empty($movie_slug)) {
        $movie = MovieDB::getMovieBySlug($movie_slug);
        if ($movie) {
            // Store target slug globally for the content filter
            $GLOBALS['MOVIEDB_CURRENT_SLUG'] = $movie_slug;
            
            // Override page title in GetSimple template
            $title = htmlspecialchars($movie['title']) . ' (' . htmlspecialchars($movie['year']) . ')';
        }
    }
}

// ============================================================================
// Global Template Functions (For use in Theme Files and Sidebar Components)
// ============================================================================

/**
 * Display main movie grid or detail page automatically
 */
function get_movie_db_display() {
    require_once(GSPLUGINPATH . 'gs-movie-db/frontend.php');

    // If viewing a single movie detail page
    if (isset($GLOBALS['MOVIEDB_CURRENT_SLUG'])) {
        echo MovieDBFrontend::renderDetail($GLOBALS['MOVIEDB_CURRENT_SLUG']);
    } else {
        // Fallback to full grid
        echo MovieDBFrontend::renderGrid();
    }
}

/**
 * Direct shortcut for rendering the movie grid
 */
function get_movie_db_grid() {
    require_once(GSPLUGINPATH . 'gs-movie-db/frontend.php');
    echo MovieDBFrontend::renderGrid();
}



?>