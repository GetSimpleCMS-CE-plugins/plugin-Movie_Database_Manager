<?php
/**
 * Movie Database Class Handler
 * Location: /plugins/gs-movie-db/class.moviedb.php
 */

if (!defined('IN_GS')) { die('Direct access not allowed'); }

class MovieDB {
    
    // Get path to storage folder using standard GetSimple CMS constants
    private static function getDataDir() {
        if (defined('GSDATAOTHERPATH')) {
            $dir = GSDATAOTHERPATH;
        } elseif (defined('GSUSERDATAPATH')) {
            $dir = GSUSERDATAPATH . 'other/';
        } else {
            $dir = GSDATAPATH . 'other/';
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private static function getXmlPath() {
        return self::getDataDir() . 'movies.xml';
    }

    private static function getSettingsPath() {
        return self::getDataDir() . 'moviedb_settings.xml';
    }

    // Load all movies from XML
    public static function getMovies() {
        $path = self::getXmlPath();
        $movies = array();

        if (!file_exists($path)) {
            self::initDefaultXml();
        }

        $xml = @simplexml_load_file($path);
        if ($xml) {
            foreach ($xml->movie as $m) {
                $movies[] = array(
                    'id' => (string)$m->id,
                    'title' => (string)$m->title,
                    'slug' => (string)$m->slug,
                    'synopsis' => (string)$m->synopsis,
                    'year' => (string)$m->year,
                    'runtime' => (string)$m->runtime,
                    'rating' => (string)$m->rating,
                    'genres' => (string)$m->genres,
                    'director' => (string)$m->director,
                    'cast' => (string)$m->cast,
                    'poster' => (string)$m->poster,
                    'backdrop' => (string)$m->backdrop,
                    'trailer' => (string)$m->trailer,
                    'imdb' => (string)$m->imdb 
                );
            }
        }

        return $movies;
    }

    // Load a single movie by ID
    public static function getMovieById($id) {
        $movies = self::getMovies();
        foreach ($movies as $movie) {
            if ($movie['id'] === $id) {
                return $movie;
            }
        }
        return null;
    }

    // Load a single movie by URL slug
    public static function getMovieBySlug($slug) {
        $movies = self::getMovies();
        foreach ($movies as $movie) {
            if ($movie['slug'] === $slug) {
                return $movie;
            }
        }
        return null;
    }

    /**
     * Admin search movies by Title, Director, or Cast.
     *
     * @param string $query Search query string
     * @param int $minLength Minimum characters required
     * @return array List of matching movie records
     */
    public static function searchMoviesByTitle($query, $minLength = 2) {
        $query = trim(mb_strtolower($query, 'UTF-8'));

        if (empty($query) || mb_strlen($query, 'UTF-8') < $minLength) {
            return array();
        }

        $allMovies = self::getMovies();
        $results = array();

        $quotedQuery = preg_quote($query, '/');
        $pattern = '/\b' . $quotedQuery . '\b/iu';

        foreach ($allMovies as $movie) {
            $searchableFields = array(
                isset($movie['title']) ? $movie['title'] : '',
                isset($movie['director']) ? $movie['director'] : '',
                isset($movie['cast']) ? (is_array($movie['cast']) ? implode(' ', $movie['cast']) : $movie['cast']) : '',
                isset($movie['genres']) ? (is_array($movie['genres']) ? implode(' ', $movie['genres']) : $movie['genres']) : ''
            );

            $searchableText = implode(' ', $searchableFields);

            if (preg_match($pattern, $searchableText)) {
                $results[] = $movie;
            }
        }

        return $results;
    }

    /**
     * Search movies by keyword across title, director, cast, genres, synopsis, and year
     * Enforces minimum query length and word-boundary matching.
     *
     * @param string $query Search query string
     * @param int $minLength Minimum characters required to execute search (default: 2)
     * @return array List of matching movie records
     */
    public static function searchMovies($query, $minLength = 2) {
        $query = trim(mb_strtolower($query, 'UTF-8'));

        if (empty($query) || mb_strlen($query, 'UTF-8') < $minLength) {
            return array();
        }

        $allMovies = self::getMovies();
        $results = array();

        $quotedQuery = preg_quote($query, '/');
        $pattern = '/\b' . $quotedQuery . '\b/iu';

        foreach ($allMovies as $movie) {
            $searchableFields = array(
                isset($movie['title']) ? $movie['title'] : '',
                isset($movie['director']) ? $movie['director'] : '',
                isset($movie['genres']) ? (is_array($movie['genres']) ? implode(' ', $movie['genres']) : $movie['genres']) : '',
                isset($movie['cast']) ? (is_array($movie['cast']) ? implode(' ', $movie['cast']) : $movie['cast']) : '',
                isset($movie['synopsis']) ? $movie['synopsis'] : '',
                isset($movie['year']) ? (string)$movie['year'] : ''
            );

            $searchableText = implode(' ', $searchableFields);

            if (preg_match($pattern, $searchableText)) {
                $results[] = $movie;
            }
        }

        return $results;
    }

    // Save (Insert or Update) a movie
    public static function saveMovie($data) {
        $path = self::getXmlPath();
        if (!file_exists($path)) {
            self::initDefaultXml();
        }

        $xml = @simplexml_load_file($path);
        if (!$xml) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><movies></movies>');
        }

        $id = !empty($data['id']) ? $data['id'] : uniqid('m_');
        $slug = !empty($data['slug']) ? self::slugify($data['slug']) : self::slugify($data['title']);

        $existingNode = null;
        foreach ($xml->movie as $node) {
            if ((string)$node->id === $id) {
                $existingNode = $node;
                break;
            }
        }

        if ($existingNode !== null) {
            $movie = $existingNode;
        } else {
            $movie = $xml->addChild('movie');
            $movie->addChild('id', $id);
        }

        $movie->title = isset($data['title']) ? $data['title'] : '';
        $movie->slug = $slug;
        $movie->synopsis = isset($data['synopsis']) ? $data['synopsis'] : '';
        $movie->year = isset($data['year']) ? $data['year'] : '';
        $movie->runtime = isset($data['runtime']) ? $data['runtime'] : '';
        $movie->rating = isset($data['rating']) ? $data['rating'] : '';
        $movie->genres = isset($data['genres']) ? $data['genres'] : '';
        $movie->director = isset($data['director']) ? $data['director'] : '';
        $movie->cast = isset($data['cast']) ? $data['cast'] : '';
        $movie->poster = isset($data['poster']) ? $data['poster'] : '';
        $movie->backdrop = isset($data['backdrop']) ? $data['backdrop'] : '';
        $movie->trailer = isset($data['trailer']) ? $data['trailer'] : '';
        $movie->imdb = isset($data['imdb']) ? $data['imdb'] : '';

        $xml->asXML($path);
    }

    // Delete a movie by ID
    public static function deleteMovie($id) {
        $path = self::getXmlPath();
        if (!file_exists($path)) return;

        $xml = @simplexml_load_file($path);
        if (!$xml) return;

        foreach ($xml->movie as $node) {
            if ((string)$node->id === $id) {
                $domNode = dom_import_simplexml($node);
                $domNode->parentNode->removeChild($domNode);
                break;
            }
        }

        $xml->asXML($path);
    }

    // Load plugin settings safely
    public static function getSettings() {
        global $SITEURL;
        $path = self::getSettingsPath();
        $defaults = array(
            'slug_prefix' => 'movies',
            'per_page' => 12,
            'default_poster' => $SITEURL .'plugins/gs-movie-db/img/placeholder.jpg'
        );

        if (!file_exists($path)) {
            return $defaults;
        }

        $xml = @simplexml_load_file($path);
        if ($xml) {
            return array(
                'slug_prefix' => !empty($xml->slug_prefix) ? (string)$xml->slug_prefix : $defaults['slug_prefix'],
                'per_page' => !empty($xml->per_page) ? (int)$xml->per_page : $defaults['per_page'],
                'default_poster' => !empty($xml->default_poster) ? (string)$xml->default_poster : $defaults['default_poster']
            );
        }

        return $defaults;
    }

    // Save plugin settings safely
    public static function saveSettings($data) {
        $path = self::getSettingsPath();
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><settings></settings>');
        
        $slug_prefix = isset($data['slug_prefix']) ? $data['slug_prefix'] : 'movies';
        $per_page = isset($data['per_page']) ? intval($data['per_page']) : 12;
        $default_poster = isset($data['default_poster']) ? $data['default_poster'] : '';

        $xml->addChild('slug_prefix', self::slugify($slug_prefix));
        $xml->addChild('per_page', $per_page);
        $xml->addChild('default_poster', htmlspecialchars($default_poster));

        $xml->asXML($path);
    }

    
	/**
		* Export movie catalogue to CSV download
	*/
	public static function exportCsvCatalogue() {
		$movies = self::getMovies();
		$filename = 'movie_catalogue_' . date('Y-m-d') . '.csv';

		if (ob_get_level()) {
			ob_end_clean();
	}

			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Pragma: no-cache');
			header('Expires: 0');

			$output = fopen('php://output', 'w');

	// Write UTF-8 BOM for Microsoft Excel compatibility
	fprintf($output, "\xEF\xBB\xBF");

	// Required Header Columns
	fputcsv($output, array(
			'Movie Title',
			'Director',
			'Cast',
			'Synopsis',
			'Release Year',
			'Runtime Min.',
			'Genres',
			'Imdb url'
	), ',', '"', '\\');

	// Export Data Rows
	foreach ($movies as $m) {
	fputcsv($output, array(
	isset($m['title']) ? $m['title'] : '',
	isset($m['director']) ? $m['director'] : '',
	isset($m['cast']) ? $m['cast'] : '',
	isset($m['synopsis']) ? $m['synopsis'] : '',
	isset($m['year']) ? $m['year'] : '',
	isset($m['runtime']) ? $m['runtime'] : '',
	isset($m['genres']) ? $m['genres'] : '',
	isset($m['imdb']) ? $m['imdb'] : ''
		), ',', '"', '\\');
	}

	fclose($output);
	exit;
	}
		
	// Helper: Initialize empty movies.xml
    private static function initDefaultXml() {
        $path = self::getXmlPath();
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><movies></movies>');
        $xml->asXML($path);
    }

    // Helper: Slugify string
    private static function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }
}