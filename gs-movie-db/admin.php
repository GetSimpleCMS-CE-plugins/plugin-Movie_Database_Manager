<?php
/**
 * GetSimple CMS CE - Movie Database Manager (Admin View)
 * Location: /plugins/gs-movie-db/admin.php
 */

if (!defined('IN_GS')) { die('Direct access not allowed'); }

// Helper function for quick translation output
function i18n_movie($key) {
    return i18n_r('gs-movie-db/' . $key);
}


$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle CSV Export Action before any HTML is sent
if ($action === 'export_csv') {
 if (class_exists('MovieDB') && method_exists('MovieDB', 'exportCsvCatalogue')) {
 MovieDB::exportCsvCatalogue();
 }
}

// 1. Process Form Submissions & Actions
$message = '';
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : '';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_movie'])) {
        MovieDB::saveMovie($_POST);
        $message = '<div class="updated"><p>' . i18n_movie('MSG_SAVED') . '</p></div>';
        $action = 'list';
    } elseif (isset($_POST['save_settings'])) {
        MovieDB::saveSettings($_POST);
        $message = '<div class="updated"><p>' . i18n_movie('MSG_SETTINGS') . '</p></div>';
    }
}

if ($action === 'delete' && !empty($_GET['id_movie'])) {
    MovieDB::deleteMovie($_GET['id_movie']);
    $message = '<div class="updated"><p>' . i18n_movie('MSG_DELETED') . '</p></div>';
    $action = 'list';
}

// Data Loading & Admin Pagination logic
$all_movies = array_reverse(MovieDB::getMovies());
$settings = MovieDB::getSettings();
$movie_to_edit = ($action === 'edit' && !empty($edit_id)) ? MovieDB::getMovieById($edit_id) : null;

// Handle Title Search Execution
$searchResults = array();
if (!empty($search_query)) {
    $searchResults = MovieDB::searchMoviesByTitle($search_query);
    
    if (!empty($searchResults)) {
        $found_ids = array_column($searchResults, 'id');
        $remaining_movies = array_filter($all_movies, function($m) use ($found_ids) {
            return !in_array($m['id'], $found_ids);
        });
        $movies = array_merge($searchResults, $remaining_movies);
    } else {
        $movies = $all_movies;
    }
} else {
    $movies = $all_movies;
}

$per_page_admin = isset($_GET['per_page_admin']) ? max(1, intval($_GET['per_page_admin'])) : 10;
$total_movies = count($movies);
$total_pages = max(1, ceil($total_movies / $per_page_admin));

$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
if ($current_page < 1) { $current_page = 1; }
if ($current_page > $total_pages) { $current_page = $total_pages; }

$offset = ($current_page - 1) * $per_page_admin;
$paginated_movies = array_slice($movies, $offset, $per_page_admin);

function get_admin_url($page, $per_page, $q = '') {
    $url = 'load.php?id=gs-movie-db&action=list&p=' . intval($page) . '&per_page_admin=' . intval($per_page);
    if (!empty($q)) {
        $url .= '&q=' . urlencode($q);
    }
    return $url;
}
?>

<div class="movie-db-admin-wrap">
    <h3><b><?php echo i18n_movie('PLUGIN_TITLE'); ?></b></h3>
	<p><?php echo i18n_movie('PLUGIN_DESC'); ?></p>

    <?php echo $message; ?>

    <!-- Sub-Navigation Toolbar with Search Box placed right of Settings -->
    <div style="margin: 15px 0 25px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <a href="load.php?id=gs-movie-db&action=list" class="button <?php echo ($action === 'list' && empty($search_query)) ? 'current' : ''; ?>"><?php echo i18n_movie('CATALOGUE'); ?> (<?php echo count($all_movies); ?>)</a>
            <a href="load.php?id=gs-movie-db&action=add" class="button <?php echo ($action === 'add') ? 'current' : ''; ?>">+ <?php echo i18n_movie('ADD_NEW_MOVIE'); ?></a>
			<a href="load.php?id=gs-movie-db&action=export_csv" class="button"><?php echo i18n_movie('EXPORT_CATALOGUE'); ?></a>
            <a href="load.php?id=gs-movie-db&action=settings" class="button <?php echo ($action === 'settings') ? 'current' : ''; ?>"><?php echo i18n_movie('SETTINGS'); ?></a>
			
			<!-- Search Form -->
            <form action="load.php" method="get" style="display: inline-flex; align-items: center; gap: 4px; margin-left: 10px;">
                <input type="hidden" name="id" value="gs-movie-db" />
                <input type="hidden" name="action" value="list" />
                <input type="text" name="q" class="text" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="<?php echo i18n_movie('SEARCH_FOR_T'); ?>" style="padding: 3px 8px; font-size: 12px; width: 180px; margin: 0;" />
                <input type="submit" class="submit" value="<?php echo i18n_movie('SEARCH'); ?>" style="padding: 3px 10px; font-size: 12px; margin: 0; cursor: pointer;" />
                <?php if (!empty($search_query)): ?>
                    <a href="load.php?id=gs-movie-db&action=list" class="cancel" style="font-size: 12px; margin-left: 4px;"><?php echo i18n_movie('CLEAR'); ?></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($search_query)): ?>
        <div class="updated" style="margin-bottom: 15px;">
            <p>
                <?php echo i18n_movie('SEARCH_RESULT'); ?>: "<strong><?php echo htmlspecialchars($search_query); ?></strong>" 
                (<?php echo i18n_movie('FOUND'); ?>: <strong><?php echo count($searchResults); ?></strong>)
                <?php if (empty($searchResults)): ?>
                    - <em><?php echo i18n_movie('NO_MOVIE_MATCH'); ?></em>
                <?php else: ?>
                    - <em><?php echo i18n_movie('MATCH_RESULT'); ?></em>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($action === 'settings'): ?>
        <!-- SETTINGS FORM -->
        <form action="load.php?id=gs-movie-db&action=settings" method="post" class="moviedb-form">
            <h3><?php echo i18n_movie('CONFIGURATION'); ?></h3>
            
            <p>
                <label for="slug_prefix"><?php echo i18n_movie('URL_SLUG'); ?></label>
                <input type="text" class="text" id="slug_prefix" name="slug_prefix" value="<?php echo htmlspecialchars($settings['slug_prefix']); ?>" style="width:100%; max-width:400px;">
                <br><small><?php echo i18n_movie('YOUR_MOVIE'); ?> <code>/<?php echo htmlspecialchars($settings['slug_prefix']); ?>/movie-slug</code></small>
            </p>

            <p>
                <label for="per_page"><?php echo i18n_movie('MOVIES_PER_PAGE'); ?></label>
                <input type="number" class="text" id="per_page" name="per_page" value="<?php echo intval($settings['per_page']); ?>" style="width:100px;">
            </p>

            <p>
                <label for="default_poster"><?php echo i18n_movie('FALLBACK_URL'); ?></label>
                <input type="text" class="text" id="default_poster" name="default_poster" value="<?php echo htmlspecialchars($settings['default_poster']); ?>" style="width:100%; max-width:600px;">
            </p>

            <p>
                <input type="submit" name="save_settings" class="submit" value="<?php echo i18n_movie('SAVE_SETTINGS'); ?>">
            </p>
        </form>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- ADD / EDIT MOVIE FORM -->
        <h3><?php echo ($action === 'edit') ? i18n_movie('EDIT_MOVIE') : i18n_movie('ADD_NEW_MOVIE'); ?></h3>
        
        <form action="load.php?id=gs-movie-db&action=<?php echo htmlspecialchars($action); ?>" method="post">
            <input type="hidden" name="id" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['id']) : uniqid('m_'); ?>">

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Main Details (Left Side) -->
                <div style="flex: 2; min-width: 300px;">
                    <p>
                        <label for="title"><?php echo i18n_movie('MOVIE_TITLE'); ?> *</label>
                        <input type="text" class="text" id="title" name="title" required value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['title']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="slug"><?php echo i18n_movie('URL_SLUG'); ?>:</label>
                        <input type="text" class="text" id="slug" name="slug" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['slug']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="synopsis"><?php echo i18n_movie('SYNOPSIS'); ?></label>
                        <textarea name="synopsis" id="synopsis" rows="6" style="width:100%; box-sizing:border-box;"><?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['synopsis']) : ''; ?></textarea>
                    </p>

                    <div style="display:flex; gap:10px;">
                        <p style="flex:1;">
                            <label for="year"><?php echo i18n_movie('RELEASE_YEAR'); ?></label>
                            <input type="number" class="text" id="year" name="year" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['year']) : date('Y'); ?>" style="width:100%;">
                        </p> 
                        <p style="flex:1;">
                            <label for="runtime"><?php echo i18n_movie('RUNTIME'); ?></label>
                            <input type="number" class="text" id="runtime" name="runtime" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['runtime']) : '120'; ?>" style="width:100%;">
                        </p>
                        <p style="flex:1;">
                            <label for="rating"><?php echo i18n_movie('RATING'); ?></label>
                            <input type="text" class="text" id="rating" name="rating" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['rating']) : '8.0'; ?>" style="width:100%;">
                        </p>
                    </div>

                    <p>
                        <label for="genres"><?php echo i18n_movie('GENRES'); ?></label>
                        <input type="text" class="text" id="genres" name="genres" placeholder="Action, Sci-Fi, Drama" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['genres']) : ''; ?>" style="width:100%;">
                    </p>
                </div>

                <!-- Media & Metadata (Right Side) -->
                <div style="flex: 1; min-width: 250px; background: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <p>
                        <label for="director"><?php echo i18n_movie('DIRECTOR'); ?></label>
                        <input type="text" class="text" id="director" name="director" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['director']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="cast"><?php echo i18n_movie('CAST'); ?></label>
                        <input type="text" class="text" id="cast" name="cast" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['cast']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="poster"><?php echo i18n_movie('POSTER_URL'); ?></label>
                        <input type="text" class="text" id="poster" name="poster" placeholder="https://..." value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['poster']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="backdrop"><?php echo i18n_movie('BACKDROP_URL'); ?></label>
                        <input type="text" class="text" id="backdrop" name="backdrop" placeholder="https://..." value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['backdrop']) : ''; ?>" style="width:100%;">
                    </p>

                    <p>
                        <label for="trailer"><?php echo i18n_movie('TRAILER_URL'); ?></label>
                        <input type="text" class="text" id="trailer" name="trailer" placeholder="e.g. YoHD9XEinc0" value="<?php echo $movie_to_edit ? htmlspecialchars($movie_to_edit['trailer']) : ''; ?>" style="width:100%;">
                    </p>
                    <p>
                        <label for="imdb"><?php echo i18n_movie('IMDB_URL'); ?>:</label>
                        <input type="text" class="text" id="imdb" name="imdb" placeholder="https://www.imdb.com/title/tt1234567/" value="<?php echo $movie_to_edit && isset($movie_to_edit['imdb']) ? htmlspecialchars($movie_to_edit['imdb']) : ''; ?>" style="width:100%;">
                    </p>
                </div>
            </div>

            <p style="margin-top:20px;">
                <input type="submit" name="save_movie" class="submit" value="<?php echo ($action === 'edit') ? i18n_movie('UPDATE_MOVIE') : i18n_movie('SAVE_MOVIE'); ?>">
                <a href="load.php?id=gs-movie-db" class="cancel" style="margin-left:10px;"><?php echo i18n_movie('CANCEL'); ?></a>
            </p>
        </form>

    <?php else: ?>
        <!-- CATALOGUE TABLE -->
        <table class="edittable highlight">
            <thead>
                <tr>
                    <th style="width:45px;"><?php echo i18n_movie('POSTER'); ?></th>
                    <th><?php echo i18n_movie('MOVIE_T_SLUG'); ?></th>
                    <th style="width:70px;"><?php echo i18n_movie('YEAR'); ?></th>
                    <th style="width:140px;"><?php echo i18n_movie('GENRES_'); ?></th>
                    <th style="width:60px;"><?php echo i18n_movie('RATING_'); ?></th>
                    <th style="width:130px; text-align:right;"><?php echo i18n_movie('ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paginated_movies)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 25px; color: #666;">
                            <?php echo i18n_movie('NO_MOVIE_ADDED'); ?> <a href="load.php?id=gs-movie-db&action=add"><?php echo i18n_movie('ADD_FIRST_MOVIE'); ?></a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginated_movies as $m): ?>
                        <?php 
                        $is_matched = !empty($searchResults) && in_array($m['id'], array_column($searchResults, 'id'));
                        ?>
                        <tr style="<?php echo $is_matched ? 'background-color: #fff8e1;' : ''; ?>">
                            <td style="text-align:center; padding: 4px;">
                                <?php if (!empty($m['poster'])): ?>
                                    <img src="<?php echo htmlspecialchars($m['poster']); ?>" alt="" style="width:36px; height:50px; object-fit:cover; border-radius:2px; display:block;">
                                <?php else: ?>
                                    <div style="width:36px; height:50px; background:#e0e0e0; display:flex; align-items:center; justify-content:center; font-size:9px; color:#888;">N/A</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><a href="load.php?id=gs-movie-db&action=edit&edit=<?php echo urlencode($m['id']); ?>"><?php echo htmlspecialchars($m['title']); ?></a></strong>
                                <?php if ($is_matched): ?>
                                    <span style="background: red; color: #fff; font-size: 10px; padding: 5px 5px; border-radius: 3px; margin-left: 5px;"><?php echo i18n_movie('MATCHED'); ?></span>
                                <?php endif; ?>
                                <br><small style="color:#888;">/<?php echo htmlspecialchars($settings['slug_prefix']); ?>/<?php echo htmlspecialchars($m['slug']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($m['year']); ?></td>
                            <td><small><?php echo htmlspecialchars($m['genres']); ?></small></td>
                            <td>&#9733; <?php echo htmlspecialchars($m['rating']); ?></td>
                            <td style="text-align:right;">
                                <a href="load.php?id=gs-movie-db&action=edit&edit=<?php echo urlencode($m['id']); ?>" class="edit"><?php echo i18n_movie('EDIT'); ?></a> | 
                                <a href="load.php?id=gs-movie-db&action=delete&id_movie=<?php echo urlencode($m['id']); ?>" class="cancel" onclick="return confirm('<?php echo i18n_movie('CONFIRM_DELETE'); ?>');"><?php echo i18n_movie('DELETE'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- ADMIN PAGINATION CONTROLS -->
        <?php if ($total_movies > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; background: #f9f9f9; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                <div style="font-size: 12px; color: #666; white-space: nowrap;">
                    <?php echo i18n_movie('SHOWING'); ?> <strong><?php echo $offset + 1; ?></strong>&#x2012;<strong><?php echo min($offset + $per_page_admin, $total_movies); ?></strong> <?php echo i18n_movie('OF'); ?> <strong><?php echo $total_movies; ?></strong> <?php echo i18n_movie('MOVIES'); ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; gap: 4px; align-items: center; justify-content: center; flex-wrap: wrap;">
                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo get_admin_url($current_page - 1, $per_page_admin, $search_query); ?>" class="button" style="padding: 2px 8px; font-size: 12px;">&#171; <?php echo i18n_movie('PREV'); ?></a>
                        <?php endif; ?>

                        <?php
                        $active_style = 'padding: 2px 8px; font-size: 12px; font-weight: bold; background: #ff2a4b !important; color: #ffffff !important; border-color: #ff2a4b !important; text-shadow: none !important;';

                        // Truncated Pagination logic for Admin
                        if ($total_pages <= 5) {
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if ($i == $current_page) {
                                    echo '<span class="button current-page-active" style="' . $active_style . '">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . get_admin_url($i, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">' . $i . '</a>';
                                }
                            }
                        } else {
                            if ($current_page <= 3) {
                                for ($i = 1; $i <= 3; $i++) {
                                    if ($i == $current_page) {
                                        echo '<span class="button current-page-active" style="' . $active_style . '">' . $i . '</span>';
                                    } else {
                                        echo '<a href="' . get_admin_url($i, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">' . $i . '</a>';
                                    }
                                }
                                echo '<span style="padding: 0 4px; font-size: 12px;">&hellip;</span>';
                                echo '<a href="' . get_admin_url($total_pages, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">' . $total_pages . '</a>';
                            } elseif ($current_page >= $total_pages - 2) {
                                echo '<a href="' . get_admin_url(1, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">1</a>';
                                echo '<span style="padding: 0 4px; font-size: 12px;">&hellip;</span>';
                                for ($i = $total_pages - 2; $i <= $total_pages; $i++) {
                                    if ($i == $current_page) {
                                        echo '<span class="button current-page-active" style="' . $active_style . '">' . $i . '</span>';
                                    } else {
                                        echo '<a href="' . get_admin_url($i, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">' . $i . '</a>';
                                    }
                                }
                            } else {
                                echo '<a href="' . get_admin_url(1, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">1</a>';
                                echo '<span style="padding: 0 4px; font-size: 12px;">&hellip;</span>';
                                echo '<span class="button current-page-active" style="' . $active_style . '">' . $current_page . '</span>';
                                echo '<span style="padding: 0 4px; font-size: 12px;">&hellip;</span>';
                                echo '<a href="' . get_admin_url($total_pages, $per_page_admin, $search_query) . '" class="button" style="padding: 2px 8px; font-size: 12px;">' . $total_pages . '</a>';
                            }
                        }
                        ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo get_admin_url($current_page + 1, $per_page_admin, $search_query); ?>" class="button" style="padding: 2px 8px; font-size: 12px;"><?php echo i18n_movie('NEXT'); ?> &#187;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="font-size: 12px; color: #666; white-space: nowrap;">
                    <?php echo i18n_movie('PER_PAGE'); ?> 
                    <select onchange="location = this.value;" style="padding: 2px 5px; font-size: 12px;">
                        <?php foreach (array(10, 25, 50, 100) as $count): ?>
                            <option value="<?php echo get_admin_url(1, $count, $search_query); ?>" <?php echo $per_page_admin == $count ? 'selected' : ''; ?>>
                                <?php echo $count; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>