<?php

declare(strict_types = 1);

use Pu239\Image;
use Pu239\Session;
use Pu239\Torrent;
use Pu239\Upcoming;
use Rakit\Validation\Validator;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_pager.php';
require_once INCL_DIR . 'function_html.php';
require_once INCL_DIR . 'function_torrent_hover.php';
require_once INCL_DIR . 'function_categories.php';
$user = check_user_status();
$lang = array_merge(load_language('global'), load_language('upcoming'), load_language('upload'));
global $container, $site_config;

$images_class = $container->get(Image::class);
$cooker_class = $container->get(Upcoming::class);
$torrent = $container->get(Torrent::class);
$session = $container->get(Session::class);
$has_access = has_access($user['class'], UC_USER, 'internal') || has_access($user['class'], UC_STAFF, '');
$actions = [
    'view_all',
    'add',
    'edit',
    'delete',
];
$session->set('post_data', $_POST);
$data = $_GET;
$view_all = $add = $edit = $delete = false;
if (isset($data['action'])) {
    switch ($data['action']) {
        case 'view_all':
            $view_all = true;
            break;
        case 'add':
            $add = true;
            $post_data = $session->get('post_data');
            break;
        case 'edit':
            $edit = true;
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            $post_data = $cooker_class->get($id);
            break;
        case 'delete':
            $delete = true;
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            break;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = $container->get(Validator::class);
    $validation = $validator->validate($_POST, [
        'type' => 'required|numeric',
        'name' => 'required|regex:/[A-Za-z0-9\:_\-\s]/',
        'poster' => 'required|url:http,https',
        'status' => 'required|in:sourcing,ftping,encoding,remuxing,uploaded',
        'url' => 'required|url:http,https',
        'expected' => 'required|date:Y-m-d\TH:i',
        'id' => 'numeric',
    ]);
    if ($validation->fails()) {
        $errors = $validation->errors();
        stderr('Error', $errors->firstOfAll()['name']);
        die();
    }
    $values = [
        'category' => (int) $_POST['type'],
        'name' => htmlsafechars($_POST['name']),
        'poster' => htmlsafechars($_POST['poster']),
        'status' => htmlsafechars($_POST['status']),
        'url' => htmlsafechars($_POST['url']),
        'expected' => date('Y-m-d H:i:s', strtotime($_POST['expected'])),
        'userid' => $user['id'],
        'show_index' => 1,
    ];
    if ($add) {
        if ($cooker_class->insert($values)) {
            $session->unset('post_data');
            $session->set('is-success', 'Recipe: ' . format_comment($_POST['name']) . ' Added');
            header('Location: ' . $_SERVER['PHP_SELF']);
            die();
        }
    } elseif ($edit) {
        if ($cooker_class->update($values, (int) $_POST['id'])) {
            $session->set('is-success', 'Recipe: ' . format_comment($_POST['name']) . ' Updated');
            header('Location: ' . $_SERVER['PHP_SELF']);
            die();
        }
    }
}
$HTMLOUT = $add_new = $update = '';
$today = date('Y-m-d\TH:i', TIME_NOW);
$date = strtotime('+7 day');
$future = empty($post_data['expected']) ? date('Y-m-d\TH:i', $date) : date('Y-m-d\TH:i', strtotime($post_data['expected']));
$form = "
                <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>Category</div>
                    <div class='column'>
                        " . category_dropdown($lang) . "
                    </div>
                </div>
                <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>Name</div>
                    <div class='column'>
                        <input type='text' class='w-100' name='name' autocomplete='on' value='" . (!empty($post_data['name']) ? htmlsafechars($post_data['name']) : '') . "' required>
                    </div>
                </div>
                <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>Poster</div>
                    <div class='column'>
                        <input type='text' class='w-100' name='poster' autocomplete='on' value='" . (!empty($post_data['poster']) ? htmlsafechars($post_data['poster']) : '') . "' required>
                    </div>
                </div>
                <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>Status</div>
                    <div class='column'>
                        <select name='status' class='w-100' required>
                            <option value='' disabled selected>Select Status</option>
                            <option value='sourcing' " . (!empty($post_data['status']) && $post_data['status'] === 'sourcing' ? 'selected' : '') . ">Sourcing</option>
                            <option value='ftping' " . (!empty($post_data['status']) && $post_data['status'] === 'ftping' ? 'selected' : '') . ">FTPing</option>
                            <option value='encoding' " . (!empty($post_data['status']) && $post_data['status'] === 'encoding' ? 'selected' : '') . ">Encoding</option>
                            <option value='remuxing' " . (!empty($post_data['status']) && $post_data['status'] === 'remuxing' ? 'selected' : '') . ">Remuxing</option>
                            <option value='uploaded' " . (!empty($post_data['status']) && $post_data['status'] === 'uploaded' ? 'selected' : '') . ">Uploaded</option>
                        </select>
                    </div>
                </div>
                <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>IMDb Link</div>
                    <div class='column'>
                        <input type='text' class='w-100' name='url' autocomplete='on' value='" . (!empty($post_data['url']) ? htmlsafechars($post_data['url']) : '') . "' required>
                    </div>
                </div>
               <div class='columns is-marginless is-paddingless'>
                    <div class='column is-one-quarter has-text-left'>Expected</div>
                    <div class='column'>
                        <input type='datetime-local' class='w-100' name='expected' value='$future' min='$today' required>
                    </div>
                </div>";
if ($has_access) {
    if ($add) {
        $add_new = "
            <h2 class='has-text-centered'>Add Recipe</h2>
            <form class='form-inline table-wrapper' method='post' action='{$_SERVER['PHP_SELF']}?action=add' enctype='multipart/form-data' accept-charset='utf-8'>$form
                <div class='has-text-centered'>
                    <input type='submit' value='Add' class='button is-small'>
                </div>
            </form>";
        $add_new = main_div($add_new, 'has-text-centered w-50 min-350', 'padding20');
    } elseif ($edit && is_valid_id($id)) {
        $update = "
            <h2 class='has-text-centered'>Edit Recipe</h2>
            <form class='form-inline table-wrapper' method='post' action='{$_SERVER['PHP_SELF']}?action=edit' enctype='multipart/form-data' accept-charset='utf-8'>$form
                <div class='has-text-centered'>
                    <input type='hidden' name='id' value='{$id}'>
                    <input type='submit' value='Update' class='button is-small'>
                </div>
            </form>";
        $update = main_div($update, 'has-text-centered w-50 min-350', 'padding20');
    } elseif ($delete && is_valid_id($id)) {
        if ($cooker_class->delete($id, $user['class'] >= UC_STAFF, $user['id']) === 1) {
            $session->set('is-success', 'Recipe Deleted');
        } else {
            $session->set('is-warning', 'Recipe Was NOT Deleted');
        }
    }
}
$count = $cooker_class->get_count(false);
$perpage = 2;
$pager = pager($perpage, $count, $_SERVER['PHP_SELF'] . '?');
$menu_top = $count > $perpage ? $pager['pagertop'] : '';
$menu_bottom = $count > $perpage ? $pager['pagerbottom'] : '';
$recipes = $cooker_class->get_all($pager['pdo']['limit'], $pager['pdo']['offset'], 'expected', true, $view_all, false);
$HTMLOUT .= "
    <ul class='level-center bg-06 padding10'>
        <li><a href='{$_SERVER['PHP_SELF']}?action=add'>Add Recipe</a></li>" . ($view_all ? "
        <li><a href='{$_SERVER['PHP_SELF']}'>View Recipes in the Oven</a></li>" : "
        <li><a href='{$_SERVER['PHP_SELF']}?action=view_all'>View All Recipes</a></li>") . "
    </ul>
    <h1 class='has-text-centered'>{$site_config['site']['name']}'s {$lang['upcoming_title']}</h1>";

if (!empty($add_new)) {
    $HTMLOUT .= $add_new;
} elseif (!empty($update)) {
    $HTMLOUT .= $update;
} else {
    $heading = "
                    <tr>
                        <th class='has-text-centered'>{$lang['upcoming_cat']}</th>
                        <th class='has-text-centered min-250'>{$lang['upcoming_name']}</th>
                        <th class='has-text-centered'>{$lang['upcoming_chef']}</th>
                        <th class='has-text-centered'>{$lang['upcoming_status']}</th>
                        <th class='has-text-centered'>{$lang['upcoming_expected']}</th>" . ($has_access ? "
                        <th class='has-text-centered'><i class='icon-tools icon' aria-hidden='true'></i></th>" : '') . '
                    </tr>';
    $body = '';
    if (!empty($recipes)) {
        foreach ($recipes as $recipe) {
            $has_full_access = $user['id'] === $recipe['userid'] || has_access($user['class'], UC_STAFF, '') && $has_access;
            $caticon = !empty($recipe['image']) ? "<img src='{$site_config['paths']['images_baseurl']}caticons/" . get_category_icons() . '/' . format_comment($recipe['image']) . "' class='tooltipper' alt='" . format_comment($recipe['cat']) . "' title='" . format_comment($recipe['cat']) . "' height='20px' width='auto'>" : format_comment($recipe['cat']);
            $poster = !empty($recipe['poster']) ? "<div class='has-text-centered'><img src='" . url_proxy($recipe['poster'], true, 250) . "' alt='image' class='img-polaroid'></div>" : '';
            $background = $imdb_id = '';
            preg_match('#(tt\d{7,8})#', $recipe['url'], $match);
            if (!empty($match[1])) {
                $imdb_id = $match[1];
                $background = $images_class->find_images($imdb_id, $type = 'background');
                $background = !empty($background) ? "style='background-image: url({$background});'" : '';
                $poster = !empty($recipe['poster']) ? $recipe['poster'] : $images_class->find_images($imdb_id, $type = 'poster');
                $poster = empty($poster) ? "<img src='{$site_config['paths']['images_baseurl']}noposter.png' alt='Poster for {$recipe['name']}' class='tooltip-poster'>" : "<img src='" . url_proxy($poster, true, 250) . "' alt='Poster for {$recipe['name']}' class='tooltip-poster'>";
            }
            $chef = "<span class='" . get_user_class_name($recipe['class'], true) . "'>" . $recipe['username'] . '</span>';
            $plot = $torrent->get_plot($imdb_id);
            if (!empty($plot)) {
                $stripped = strip_tags($plot);
                $plot = strlen($stripped) > 500 ? substr($plot, 0, 500) . '...' : $stripped;
                $plot = "
                                                        <div class='column padding5 is-4'>
                                                            <span class='size_4 has-text-primary has-text-weight-bold'>{$lang['upcoming_plot']}:</span>
                                                        </div>
                                                        <div class='column padding5 is-8'>
                                                            <span class='size_4'>{$plot}</span>
                                                        </div>";
            }
            $hover = upcoming_hover($recipe['url'], 'upcoming_' . $recipe['id'], $recipe['name'], $background, $poster, $recipe['added'], $recipe['expected'], $chef, $plot, $lang);
            $body .= "
                    <tr>
                        <td class='has-text-centered'>{$caticon}</td>
                        <td>$hover</td>
                        <td class='has-text-centered'>{$chef}</td>
                        <td class='has-text-centered'>" . ucfirst($recipe['status']) . "</td>
                        <td class='has-text-centered'><span class='tooltipper' title='" . calc_time_difference(strtotime($recipe['expected']) - TIME_NOW, true) . "'>" . calc_time_difference(strtotime($recipe['expected']) - TIME_NOW, false) . '</span></td>' . ($has_access ? "
                        <td class='has-text-centered'>" . ($has_full_access ? "
                            <a href='{$_SERVER['PHP_SELF']}?action=edit&amp;id={$recipe['id']}'><i class='icon-edit icon has-text-info' aria-hidden='true'></i></a>
                            <a href='{$_SERVER['PHP_SELF']}?action=delete&amp;id={$recipe['id']}'><i class='icon-trash-empty icon has-text-danger' aria-hidden='true'></i></a>" : '') . '
                        </td>' : '') . '
                    </tr>';
        }
    } else {
        $cols = $has_access ? 7 : 6;
        $body = "
                    <tr>
                        <td colspan='{$cols}' class='has-text-centered'>Nothing Cookin'</td>
                    </tr>";
    }
    $HTMLOUT .= $menu_top . main_table($body, $heading) . $menu_bottom;
}

echo stdhead($lang['upcoming_title']) . wrapper($HTMLOUT) . stdfoot();
