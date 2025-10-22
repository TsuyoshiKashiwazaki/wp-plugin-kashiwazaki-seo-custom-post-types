<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カスタム投稿タイプのアーカイブページ表示制御
 */
class KSTB_Archive_Controller {
    private static $instance = null;
    private $processed = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        // リライトルールをフィルタリング（最も早いタイミング）
        add_filter('rewrite_rules_array', array($this, 'filter_rewrite_rules'), 999);

        // requestフィルターでリクエストを事前処理
        add_filter('request', array($this, 'filter_request'), 5);

        // parseリクエスト時に制御
        add_action('parse_request', array($this, 'handle_request'), 1);

        // parse_queryで queried_object を修正（最優先）
        add_action('parse_query', array($this, 'fix_queried_object'), 1);

        // wpアクションでも修正（テンプレート読み込み直前）
        add_action('wp', array($this, 'fix_queried_object_on_wp'), 1);

        // template_includeフィルタでテンプレート直前にも修正
        add_filter('template_include', array($this, 'fix_queried_object_before_template'), 1);

        // get_headerアクションでheader.php読み込み直前にも修正
        add_action('get_header', array($this, 'fix_queried_object_on_wp'), 0);

        // get_queried_objectフィルターで強制修正
        add_filter('get_queried_object', array($this, 'filter_queried_object'), 1);

        // WP_Post_Type関連のエラーを抑制
        add_action('init', array($this, 'suppress_post_type_errors'), 1);

        // pre_get_postsでクエリを制御（最後に実行）
        add_action('pre_get_posts', array($this, 'control_query'), 999);

        // テンプレートリダイレクトで最終制御（最優先）
        add_action('template_redirect', array($this, 'template_control'), -999);
    }

    /**
     * カスタム投稿タイプのフルパスを再帰的に構築
     */
    private function build_full_path_for_post_type($post_type) {
        if (empty($post_type->parent_directory)) {
            return $post_type->slug;
        }

        $parent_dir = trim($post_type->parent_directory, '/');

        // 親ディレクトリが別のカスタム投稿タイプかチェック
        $all_post_types = KSTB_Database::get_all_post_types();
        foreach ($all_post_types as $other_type) {
            if ($other_type->slug === $parent_dir) {
                // 親のフルパスを再帰的に取得
                $parent_path = $this->build_full_path_for_post_type($other_type);
                return $parent_path . '/' . $post_type->slug;
            }
        }

        // 通常のディレクトリ
        return $parent_dir . '/' . $post_type->slug;
    }

    /**
     * リライトルールをフィルタリング
     */
    public function filter_rewrite_rules($rules) {
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type) {
            // フルパスを再帰的に構築
            $pattern = $this->build_full_path_for_post_type($post_type);

            if (!$post_type->has_archive) {
                // アーカイブが無効の場合、アーカイブ関連のリライトルールを削除
                $pattern = preg_quote($pattern, '/');
                foreach ($rules as $rule => $query) {
                    if (preg_match('/^' . $pattern . '\/?\$/', $rule)) {
                        unset($rules[$rule]);
                    }
                    if (preg_match('/^' . $pattern . '\/feed/', $rule)) {
                        unset($rules[$rule]);
                    }
                    if (preg_match('/^' . $pattern . '\/page/', $rule)) {
                        unset($rules[$rule]);
                    }
                }
            } else {
                // アーカイブが有効な場合、ページネーション用のルールを追加/保持
                $pattern_escaped = preg_quote($pattern, '/');

                // ページネーション用のルールが存在しない場合は追加
                $page_rule = $pattern . '/page/?([0-9]{1,})/?$';
                if (!isset($rules[$page_rule])) {
                    $rules[$page_rule] = 'index.php?post_type=' . $post_type->slug . '&paged=$matches[1]';
                }
            }
        }

        return $rules;
    }

    /**
     * リクエストをフィルタリング
     */
    public function filter_request($query_vars) {
        if (is_admin()) {
            return $query_vars;
        }

        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH) ?? '';
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return $query_vars;
        }

        $segments = explode('/', $uri);
        $post_types = KSTB_Database::get_all_post_types();

        // 最も長いパスにマッチする投稿タイプを見つける
        $best_match = null;
        $best_match_length = 0;
        $best_match_is_subdir = false;

        foreach ($post_types as $post_type) {
            $matches = false;
            $is_subdir = false;

            // 階層URLの場合
            if (!empty($post_type->parent_directory)) {
                // フルパスを再帰的に構築
                $full_path = $this->build_full_path_for_post_type($post_type);
                $full_segments = explode('/', $full_path);

                // 現在のURLがフルパスと一致するかチェック
                if (count($segments) >= count($full_segments)) {
                    $path_matches = true;
                    for ($i = 0; $i < count($full_segments); $i++) {
                        if ($segments[$i] !== $full_segments[$i]) {
                            $path_matches = false;
                            break;
                        }
                    }

                    if ($path_matches) {
                        $match_length = count($full_segments);
                        if (count($segments) === count($full_segments)) {
                            // スラッグトップページ
                            if ($match_length > $best_match_length) {
                                $best_match = $post_type;
                                $best_match_length = $match_length;
                                $best_match_is_subdir = false;
                            }
                        } elseif (count($segments) > count($full_segments)) {
                            // スラッグ以下のサブディレクトリ
                            if ($match_length > $best_match_length) {
                                $best_match = $post_type;
                                $best_match_length = $match_length;
                                $best_match_is_subdir = true;
                            }
                        }
                    }
                }
            }
            // 単純URLの場合
            elseif ($segments[0] === $post_type->slug) {
                $match_length = 1;
                if (count($segments) === 1) {
                    // スラッグトップページ
                    if ($match_length > $best_match_length) {
                        $best_match = $post_type;
                        $best_match_length = $match_length;
                        $best_match_is_subdir = false;
                    }
                } elseif (count($segments) > 1) {
                    // スラッグ以下のサブディレクトリ
                    if ($match_length > $best_match_length) {
                        $best_match = $post_type;
                        $best_match_length = $match_length;
                        $best_match_is_subdir = true;
                    }
                }
            }
        }

        // 最適なマッチが見つかった場合の処理
        if ($best_match) {
            $post_type = $best_match;
            $is_subdir = $best_match_is_subdir;
            $matches = !$is_subdir;

            // スラッグトップページでアーカイブ無効の場合
            if ($matches && !$post_type->has_archive) {
                // 「表示しない」の場合は何もしない（404にする）
                if (isset($post_type->archive_display_type) && $post_type->archive_display_type === 'none') {
                    // 何も返さない（404になる）
                    return $query_vars;
                }

                // 「指定なし」の場合のみ固定ページを探す
                if (!isset($post_type->archive_display_type) || $post_type->archive_display_type === 'default') {
                    // 同じパスに固定ページが存在するかチェック
                    $page = get_page_by_path($uri);

                    if ($page && $page->post_status === 'publish') {
                        // 固定ページとして処理するクエリ変数を返す
                        return array(
                            'pagename' => $uri,
                            'page' => '',
                            'post_type' => 'page'
                        );
                    }
                }
            }

            // スラッグ以下のサブディレクトリの場合
            if ($is_subdir) {
                // カスタム投稿タイプの個別記事として処理されそうな場合、
                // 固定ページが存在するかチェック
                $page = get_page_by_path($uri);

                if ($page && $page->post_status === 'publish') {
                    // 固定ページとして処理するクエリ変数を返す
                    return array(
                        'pagename' => $uri,
                        'page' => '',
                        'post_type' => 'page'
                    );
                }
            }
        }

        return $query_vars;
    }

    /**
     * 早期URLチェック
     */
    public function early_url_check() {
        if (is_admin() || $this->processed) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH) ?? '';
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return;
        }

        // 階層URLの処理
        $segments = explode('/', $uri);

        // 2階層以上のURLをチェック (company/member のような)
        if (count($segments) >= 2) {
            $parent = $segments[0];
            $slug = $segments[1];

            // 階層URLのカスタム投稿タイプをチェック
            $post_types = KSTB_Database::get_all_post_types();
            foreach ($post_types as $post_type) {
                if ($post_type->slug === $slug &&
                    !empty($post_type->parent_directory) &&
                    trim($post_type->parent_directory, '/') === $parent) {

                    // 個別記事ページでない場合のみ処理
                    if (count($segments) === 2 && !$post_type->has_archive) {
                        $this->processed = true;
                        // フルパスで検索
                        $full_path = implode('/', $segments);
                        $this->handle_no_archive($full_path);
                    }
                }
            }
        }

        // 1階層のURLをチェック
        if (count($segments) === 1) {
            $slug = $segments[0];
            $post_types = KSTB_Database::get_all_post_types();

            foreach ($post_types as $post_type) {
                if ($post_type->slug === $slug && empty($post_type->parent_directory)) {
                    if (!$post_type->has_archive) {
                        $this->processed = true;
                        $this->handle_no_archive($slug);
                    } elseif ($post_type->archive_display_type === 'custom_page' && $post_type->archive_page_id) {
                        $this->processed = true;
                        $this->handle_custom_page($post_type->archive_page_id);
                    }
                }
            }
        }
    }

    /**
     * parseリクエスト時の処理
     */
    public function handle_request($wp) {
        if ($this->processed || is_admin()) {
            return;
        }

        if (!isset($wp->request)) {
            return;
        }

        $request = trim($wp->request, '/');
        if (empty($request)) {
            return;
        }

        $segments = explode('/', $request);

        // カスタム投稿タイプかチェック
        $post_types = KSTB_Database::get_all_post_types();

        // 最も長いパスにマッチする投稿タイプを見つける
        $best_match = null;
        $best_match_length = 0;
        $best_match_is_subdir = false;

        foreach ($post_types as $post_type) {
            $matches = false;
            $is_subdir = false;

            // 階層URLの場合
            if (!empty($post_type->parent_directory)) {
                // フルパスを再帰的に構築
                $full_path = $this->build_full_path_for_post_type($post_type);
                $full_segments = explode('/', $full_path);

                // 現在のURLがフルパスと一致するかチェック
                if (count($segments) >= count($full_segments)) {
                    $path_matches = true;
                    for ($i = 0; $i < count($full_segments); $i++) {
                        if ($segments[$i] !== $full_segments[$i]) {
                            $path_matches = false;
                            break;
                        }
                    }

                    if ($path_matches) {
                        $match_length = count($full_segments);
                        if (count($segments) === count($full_segments)) {
                            // スラッグトップページ
                            if ($match_length > $best_match_length) {
                                $best_match = $post_type;
                                $best_match_length = $match_length;
                                $best_match_is_subdir = false;
                            }
                        } elseif (count($segments) > count($full_segments)) {
                            // スラッグ以下のサブディレクトリ
                            if ($match_length > $best_match_length) {
                                $best_match = $post_type;
                                $best_match_length = $match_length;
                                $best_match_is_subdir = true;
                            }
                        }
                    }
                }
            }
            // 単純URLの場合
            elseif ($segments[0] === $post_type->slug) {
                $match_length = 1;
                if (count($segments) === 1) {
                    // スラッグトップページ
                    if ($match_length > $best_match_length) {
                        $best_match = $post_type;
                        $best_match_length = $match_length;
                        $best_match_is_subdir = false;
                    }
                } elseif (count($segments) > 1) {
                    // スラッグ以下のサブディレクトリ
                    if ($match_length > $best_match_length) {
                        $best_match = $post_type;
                        $best_match_length = $match_length;
                        $best_match_is_subdir = true;
                    }
                }
            }
        }

        // 最適なマッチが見つかった場合の処理
        if ($best_match) {
            $post_type = $best_match;
            $is_subdir = $best_match_is_subdir;
            $matches = !$is_subdir;

            // スラッグトップページの処理
            if ($matches) {
                if (!$post_type->has_archive) {
                    // 「表示しない」の場合は常に404
                    if (isset($post_type->archive_display_type) && $post_type->archive_display_type === 'none') {
                        $wp->query_vars = array('error' => '404');
                        $this->processed = true;
                        return;
                    }

                    // 「指定なし」の場合のみ固定ページを探す
                    if (!isset($post_type->archive_display_type) || $post_type->archive_display_type === 'default') {
                        // 同じパスに固定ページが存在するかチェック
                        $page = get_page_by_path($request);

                        if ($page && $page->post_status === 'publish') {
                            // 固定ページが存在する場合は明示的に固定ページを表示
                            $wp->query_vars = array(
                                'pagename' => $request,
                                'page' => '',
                                'name' => '',
                                'post_type' => 'page'
                            );
                            // カスタム投稿タイプ関連のクエリ変数を削除
                            unset($wp->query_vars[$post_type->slug]);
                            unset($wp->query_vars['post_type']);
                            $this->processed = true;
                            return;
                        }

                        // 固定ページが存在しない場合のみ404にする
                        $wp->query_vars = array('error' => '404');
                        $this->processed = true;
                        return;
                    }
                } elseif ($post_type->archive_display_type === 'custom_page' && $post_type->archive_page_id) {
                    // カスタムページを表示
                    $wp->query_vars = array(
                        'page_id' => $post_type->archive_page_id,
                        'post_type' => 'page'
                    );
                    $this->processed = true;
                    return;
                }
            }

            // スラッグ以下のサブディレクトリの処理
            if ($is_subdir) {
                // まずカスタム投稿タイプの個別記事として存在するかチェック
                $post_slug = $segments[count($segments) - 1];
                $args = array(
                    'name' => $post_slug,
                    'post_type' => $post_type->slug,
                    'post_status' => 'publish',
                    'posts_per_page' => 1
                );
                $posts = get_posts($args);

                if (empty($posts)) {
                    // カスタム投稿タイプの記事が存在しない場合、固定ページをチェック
                    $page = get_page_by_path($request);

                    if ($page && $page->post_status === 'publish') {
                        // 固定ページとして処理
                        $wp->query_vars = array(
                            'pagename' => $request,
                            'page' => '',
                            'name' => '',
                            'post_type' => 'page'
                        );
                        // カスタム投稿タイプ関連のクエリ変数を削除
                        unset($wp->query_vars[$post_type->slug]);
                        unset($wp->query_vars['post_type']);
                        $this->processed = true;
                        return;
                    }
                }
            }
        }
    }

    /**
     * queried_objectがWP_Post_Typeの場合に修正
     */
    public function fix_queried_object($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // queried_objectがWP_Post_Typeの場合はクリア
        if (isset($query->queried_object) && $query->queried_object instanceof WP_Post_Type) {
            $query->queried_object = null;
            $query->queried_object_id = null;
        }
    }

    /**
     * wpアクションでqueried_objectを修正
     */
    public function fix_queried_object_on_wp() {
        global $wp_query;

        if (is_admin()) {
            return;
        }

        // queried_objectがWP_Post_Typeの場合はクリア
        if (isset($wp_query->queried_object) && $wp_query->queried_object instanceof WP_Post_Type) {
            $wp_query->queried_object = null;
            $wp_query->queried_object_id = null;
        }
    }

    /**
     * WP_Post_Type関連のエラーを抑制
     */
    public function suppress_post_type_errors() {
        if (!is_admin()) {
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                // WP_Post_Typeの未定義プロパティエラーを抑制
                if (strpos($errstr, 'WP_Post_Type') !== false &&
                    (strpos($errstr, '$ID') !== false ||
                     strpos($errstr, '$post_title') !== false ||
                     strpos($errstr, '$post_name') !== false)) {
                    return true; // エラーを抑制
                }
                return false; // 他のエラーは通常通り処理
            }, E_WARNING | E_NOTICE);
        }
    }

    /**
     * get_queried_objectフィルターでWP_Post_Typeを除外
     */
    public function filter_queried_object($queried_object) {
        // WP_Post_Typeの場合はnullを返す
        if ($queried_object instanceof WP_Post_Type) {
            return null;
        }
        return $queried_object;
    }

    /**
     * テンプレート読み込み直前にqueried_objectを修正
     */
    public function fix_queried_object_before_template($template) {
        global $wp_query;

        // queried_objectがWP_Post_Typeの場合はクリア
        if (isset($wp_query->queried_object) && $wp_query->queried_object instanceof WP_Post_Type) {
            $wp_query->queried_object = null;
            $wp_query->queried_object_id = null;
        }

        return $template;
    }

    /**
     * pre_get_postsでクエリを制御
     */
    public function control_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // 現在のURLを取得
        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH) ?? '';
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return;
        }

        $segments = explode('/', $uri);
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type_data) {
            $matches = false;
            $is_subdir = false;

            // 階層URLの場合
            if (!empty($post_type_data->parent_directory)) {
                // フルパスを再帰的に構築
                $full_path = $this->build_full_path_for_post_type($post_type_data);
                $full_segments = explode('/', $full_path);

                // 現在のURLがフルパスと一致するかチェック
                if (count($segments) >= count($full_segments)) {
                    $path_matches = true;
                    for ($i = 0; $i < count($full_segments); $i++) {
                        if ($segments[$i] !== $full_segments[$i]) {
                            $path_matches = false;
                            break;
                        }
                    }

                    if ($path_matches) {
                        if (count($segments) === count($full_segments)) {
                            // スラッグトップページ
                            $matches = true;
                        } elseif (count($segments) > count($full_segments)) {
                            // スラッグ以下のサブディレクトリ
                            $is_subdir = true;
                        }
                    }
                }
            }
            // 単純URLの場合
            elseif ($segments[0] === $post_type_data->slug) {
                if (count($segments) === 1) {
                    // スラッグトップページ
                    $matches = true;
                } elseif (count($segments) > 1) {
                    // スラッグ以下のサブディレクトリ
                    $is_subdir = true;
                }
            }

            // スラッグトップページでアーカイブ無効の場合
            if ($matches && !$post_type_data->has_archive) {
                // 「表示しない」の場合は常に404
                if (isset($post_type_data->archive_display_type) && $post_type_data->archive_display_type === 'none') {
                    $query->set_404();
                    // queried_objectをクリア
                    $query->queried_object = null;
                    $query->queried_object_id = null;
                    $query->set('post__in', array(0));
                    return;
                }

                // 「指定なし」の場合のみ固定ページを探す
                if (!isset($post_type_data->archive_display_type) || $post_type_data->archive_display_type === 'default') {
                    // 同じパスに固定ページが存在するかチェック
                    $page = get_page_by_path($uri);

                    if ($page && $page->post_status === 'publish') {

                        // すべてのクエリ変数をクリア
                        $query->query_vars = array();
                        $query->query = array();

                        // 固定ページとしてクエリを設定
                        $query->set('page_id', $page->ID);
                        $query->set('post_type', 'page');
                        $query->set('posts_per_page', 1);
                        $query->set('paged', 1);

                        // 条件タグをリセット
                        $query->is_page = true;
                        $query->is_singular = true;
                        $query->is_single = false;
                        $query->is_404 = false;
                        $query->is_home = false;
                        $query->is_archive = false;
                        $query->is_post_type_archive = false;
                        $query->is_tax = false;
                        $query->is_category = false;
                        $query->is_tag = false;
                        $query->is_author = false;
                        $query->is_date = false;
                        $query->is_year = false;
                        $query->is_month = false;
                        $query->is_day = false;
                        $query->is_time = false;
                        $query->is_search = false;
                        $query->is_feed = false;
                        $query->is_comment_feed = false;
                        $query->is_trackback = false;
                        $query->is_embed = false;
                        $query->is_paged = false;
                        $query->is_admin = false;
                        $query->is_attachment = false;
                        $query->is_posts_page = false;

                        // queried_objectを明示的に設定
                        $query->queried_object = $page;
                        $query->queried_object_id = $page->ID;

                        return;
                    }

                    // 固定ページが存在しない場合は404
                    $query->set_404();
                    // queried_objectをクリア
                    $query->queried_object = null;
                    $query->queried_object_id = null;
                    $query->set('post__in', array(0));
                    return;
                }
            }

            // スラッグ以下のサブディレクトリで、404になりそうな場合
            if ($is_subdir && $query->is_404()) {
                // 固定ページが存在するかチェック
                $page = get_page_by_path($uri);

                if ($page && $page->post_status === 'publish') {
                    // すべてのクエリ変数をクリア
                    $query->query_vars = array();
                    $query->query = array();

                    // 固定ページとしてクエリを設定
                    $query->set('page_id', $page->ID);
                    $query->set('post_type', 'page');
                    $query->set('posts_per_page', 1);
                    $query->set('paged', 1);

                    // 条件タグをリセット
                    $query->is_page = true;
                    $query->is_singular = true;
                    $query->is_single = false;
                    $query->is_404 = false;
                    $query->is_home = false;
                    $query->is_archive = false;
                    $query->is_post_type_archive = false;
                    $query->is_tax = false;
                    $query->is_category = false;
                    $query->is_tag = false;
                    $query->is_author = false;
                    $query->is_date = false;
                    $query->is_year = false;
                    $query->is_month = false;
                    $query->is_day = false;
                    $query->is_time = false;
                    $query->is_search = false;
                    $query->is_feed = false;
                    $query->is_comment_feed = false;
                    $query->is_trackback = false;
                    $query->is_embed = false;
                    $query->is_paged = false;
                    $query->is_admin = false;
                    $query->is_attachment = false;
                    $query->is_posts_page = false;

                    // queried_objectを明示的に設定
                    $query->queried_object = $page;
                    $query->queried_object_id = $page->ID;

                    return;
                }
            }
        }

    }

    /**
     * テンプレートリダイレクトで最終制御
     */
    public function template_control() {
        if (is_admin()) {
            return;
        }

        // 現在のURLを取得
        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH) ?? '';
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return;
        }

        $segments = explode('/', $uri);
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type_data) {
            $matches = false;
            $is_subdir = false;

            // 階層URLの場合
            if (!empty($post_type_data->parent_directory)) {
                // フルパスを再帰的に構築
                $full_path = $this->build_full_path_for_post_type($post_type_data);
                $full_segments = explode('/', $full_path);

                // 現在のURLがフルパスと一致するかチェック
                if (count($segments) >= count($full_segments)) {
                    $path_matches = true;
                    for ($i = 0; $i < count($full_segments); $i++) {
                        if ($segments[$i] !== $full_segments[$i]) {
                            $path_matches = false;
                            break;
                        }
                    }

                    if ($path_matches) {
                        if (count($segments) === count($full_segments)) {
                            // スラッグトップページ
                            $matches = true;
                        } elseif (count($segments) > count($full_segments)) {
                            // スラッグ以下のサブディレクトリ
                            $is_subdir = true;
                        }
                    }
                }
            }
            // 単純URLの場合
            elseif ($segments[0] === $post_type_data->slug) {
                if (count($segments) === 1) {
                    // スラッグトップページ
                    $matches = true;
                } elseif (count($segments) > 1) {
                    // スラッグ以下のサブディレクトリ
                    $is_subdir = true;
                }
            }

            // スラッグトップページでアーカイブ無効の場合
            if ($matches && !$post_type_data->has_archive) {
                // 「表示しない」の場合は常に404
                if (isset($post_type_data->archive_display_type) && $post_type_data->archive_display_type === 'none') {
                    $this->display_404();
                    exit;
                }

                // 「指定なし」の場合のみ固定ページを探す
                if (!isset($post_type_data->archive_display_type) || $post_type_data->archive_display_type === 'default') {
                    // 同じパスに固定ページが存在するかチェック
                    $page = get_page_by_path($uri);

                    if ($page && $page->post_status === 'publish') {
                        $this->display_page($page);
                        exit;
                    }

                    // 固定ページが存在しない場合は404
                    $this->display_404();
                    exit;
                }
            }

            // スラッグ以下のサブディレクトリで404になりそうな場合
            if ($is_subdir && is_404()) {
                // まずカスタム投稿タイプの記事が存在するか確認
                $post_slug = $segments[count($segments) - 1];
                $args = array(
                    'name' => $post_slug,
                    'post_type' => $post_type_data->slug,
                    'post_status' => 'publish',
                    'posts_per_page' => 1
                );
                $posts = get_posts($args);

                if (empty($posts)) {
                    // カスタム投稿タイプの記事が存在しない場合、固定ページをチェック
                    $page = get_page_by_path($uri);

                    if ($page && $page->post_status === 'publish') {
                        $this->display_page($page);
                        exit;
                    }
                }
            }
        }

        // 既存の処理は削除（上記で処理されるため）
        return;

        global $wp_query;

        // カスタム投稿タイプのアーカイブかチェック
        if (!empty($wp_query->query_vars['post_type'])) {
            $post_type = $wp_query->query_vars['post_type'];

            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }

            $post_type_data = KSTB_Database::get_post_type_by_slug($post_type);

            if ($post_type_data && !$post_type_data->has_archive) {
                // 「表示しない」の場合は常に404
                if (isset($post_type_data->archive_display_type) && $post_type_data->archive_display_type === 'none') {
                    $this->display_404();
                    exit;
                }

                // 「指定なし」以外の場合のみ固定ページを探す
                if (!isset($post_type_data->archive_display_type) || $post_type_data->archive_display_type !== 'default') {
                    // 現在のURLパスを取得
                    $uri = $_SERVER['REQUEST_URI'];
                    $uri = parse_url($uri, PHP_URL_PATH);
                    $uri = trim($uri, '/');

                    // フルパスで固定ページを探す
                    $page = get_page_by_path($uri);
                    if ($page && $page->post_status === 'publish') {
                        $this->display_page($page);
                        exit;
                    }

                    // スラッグだけでも検索
                    $page = get_page_by_path($post_type);
                    if ($page && $page->post_status === 'publish') {
                        $this->display_page($page);
                        exit;
                    }

                    // 404を表示
                    $this->display_404();
                    exit;
                }
            }
        }
    }

    /**
     * アーカイブ無効時の処理
     */
    private function handle_no_archive($slug) {
        // まず現在のURLパスを取得
        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH) ?? '';
        $uri = trim($uri, '/');

        // 階層URLの場合はフルパスで検索
        $page = get_page_by_path($uri);
        if ($page && $page->post_status === 'publish') {
            $this->display_page($page);
            exit;
        }

        // スラッグだけでも検索
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') {
            $this->display_page($page);
            exit;
        }

        // 同じスラッグの投稿を探す
        $args = array(
            'name' => $slug,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1
        );
        $posts = get_posts($args);

        if (!empty($posts)) {
            $this->display_post($posts[0]);
            exit;
        }

        // 404を表示
        $this->display_404();
        exit;
    }

    /**
     * カスタムページを表示
     */
    private function handle_custom_page($page_id) {
        $page = get_post($page_id);
        if (!$page || $page->post_status !== 'publish') {
            $this->display_404();
            exit;
        }

        $this->display_page($page);
        exit;
    }

    /**
     * 固定ページを表示
     */
    private function display_page($page) {
        global $wp_query, $post, $wp;

        // クエリをリセット
        $wp_query = new WP_Query(array(
            'page_id' => $page->ID,
            'post_type' => 'page',
            'posts_per_page' => 1
        ));

        $post = $page;
        setup_postdata($post);

        // queried_objectを明示的に設定
        $wp_query->queried_object = $page;
        $wp_query->queried_object_id = $page->ID;

        // WordPressのクエリ変数を正しく設定
        $wp->query_vars = array(
            'page_id' => $page->ID,
            'post_type' => 'page'
        );
        $wp->query_string = '';
        $wp->matched_query = '';

        // is_page()などの条件タグが正しく動作するように設定
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_single = false;
        $wp_query->is_404 = false;
        $wp_query->is_archive = false;
        $wp_query->is_post_type_archive = false;

        // ステータスコード
        status_header(200);

        // テンプレートローダーを使用（これによりwp_head()などのフックが正しく実行される）
        remove_all_actions('template_redirect');

        // テンプレートを決定
        $template = '';

        if ($page_template = get_page_template_slug($page->ID)) {
            $template = locate_template($page_template);
        }

        if (!$template) {
            $template = get_page_template();
        }

        if (!$template) {
            $template = get_singular_template();
        }

        if (!$template) {
            $template = get_index_template();
        }

        // テンプレートを読み込む
        if ($template) {
            include($template);
            exit;
        }
    }

    /**
     * 投稿を表示
     */
    private function display_post($post_obj) {
        global $wp_query, $post;

        // クエリをリセット
        $wp_query = new WP_Query(array(
            'p' => $post_obj->ID,
            'post_type' => 'post',
            'posts_per_page' => 1
        ));

        $post = $post_obj;
        setup_postdata($post);

        // queried_objectを明示的に設定
        $wp_query->queried_object = $post_obj;
        $wp_query->queried_object_id = $post_obj->ID;

        // ステータスコード
        status_header(200);

        // テンプレートを読み込む
        $template = locate_template('single.php');
        if ($template) {
            include($template);
            exit;
        }

        $template = locate_template('singular.php');
        if ($template) {
            include($template);
            exit;
        }

        $template = locate_template('index.php');
        if ($template) {
            include($template);
            exit;
        }
    }

    /**
     * 404を表示
     */
    private function display_404() {
        global $wp_query;

        $wp_query->set_404();
        // queried_objectをクリア
        $wp_query->queried_object = null;
        $wp_query->queried_object_id = null;

        status_header(404);
        nocache_headers();

        $template = get_404_template();
        if ($template) {
            include($template);
        } else {
            $template = locate_template('index.php');
            if ($template) {
                include($template);
            }
        }
    }
}