<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カスタム投稿タイプのパーマリンク検証クラス
 *
 * 正しいパーマリンク以外でアクセスされた場合に404を返す
 */
class KSTB_Permalink_Validator {
    private static $instance = null;
    private $post_types_cache = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * 投稿タイプを取得（キャッシュ付き）
     */
    private function get_post_types() {
        if ($this->post_types_cache === null) {
            $this->post_types_cache = KSTB_Database::get_all_post_types();
        }
        return $this->post_types_cache;
    }

    public function init() {
        // template_redirectフックで検証（優先度を高めに設定）
        add_action('template_redirect', array($this, 'validate_permalink'), 1);

        // カスタム投稿タイプのCanonical Redirectを無効化
        add_filter('redirect_canonical', array($this, 'disable_canonical_redirect_for_custom_post_types'), 10, 2);
    }

    /**
     * カスタム投稿タイプのCanonical Redirectを無効化
     *
     * @param string $redirect_url リダイレクト先URL
     * @param string $requested_url リクエストされたURL
     * @return string|false リダイレクト先URL、または無効化する場合はfalse
     */
    public function disable_canonical_redirect_for_custom_post_types($redirect_url, $requested_url) {
        // 投稿が存在しない場合はスキップ
        if (!is_singular()) {
            return $redirect_url;
        }

        global $post;
        if (!$post) {
            return $redirect_url;
        }

        // カスタム投稿タイプかチェックし、設定を取得
        $post_types = $this->get_post_types();
        $current_post_type = null;

        foreach ($post_types as $post_type) {
            if ($post->post_type === $post_type->slug) {
                $current_post_type = $post_type;
                break;
            }
        }

        // カスタム投稿タイプでない場合は通常処理
        if (!$current_post_type) {
            return $redirect_url;
        }

        // クエリ文字列URL（?p=ID や ?post_type=xxx&p=ID）でアクセスされた場合
        $is_query_string_access = (
            strpos($requested_url, '?') !== false &&
            (isset($_GET['p']) || isset($_GET['page_id']) || isset($_GET['post_type']))
        );

        if ($is_query_string_access) {
            // allow_shortlink が有効な場合はリダイレクトしない（そのまま表示）
            if (!empty($current_post_type->allow_shortlink)) {
                return false;
            }
            // allow_shortlink が無効な場合はリダイレクトを許可（正規URLへ転送）
            return $redirect_url;
        }

        // クエリ文字列以外のアクセス（階層的パーマリンク等）の場合は
        // 従来通りリダイレクトを無効化
        return false;
    }

    /**
     * パーマリンクを検証する
     */
    public function validate_permalink() {
        // 管理画面は検証しない
        if (is_admin()) {
            return;
        }

        global $wp_query;

        // 現在のリクエストパスを取得
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = rtrim($current_path, '/') . '/';

        // URLセグメントを解析
        $segments = explode('/', trim($current_path, '/'));
        if (empty($segments) || empty($segments[0])) {
            return;
        }

        // カスタム投稿タイプのパターンに部分的にマッチするかチェック
        $post_types = $this->get_post_types();
        $should_block = false;

        foreach ($post_types as $post_type) {
            // 各カスタム投稿タイプの正しいフルパスを構築
            $full_path = $this->build_full_path_for_post_type($post_type);
            $full_path_segments = explode('/', $full_path);

            // 現在のURLが部分的にマッチする場合（正しくない階層）
            // 例: /seo/xxx/ が /blog/seo/xxx/ であるべき場合
            if ($this->is_incorrect_hierarchy($segments, $full_path_segments, $post_type->slug)) {
                $should_block = true;
                break;
            }
        }

        // 投稿が存在する場合は、正しいパーマリンクかチェック
        if (is_singular() && !is_404()) {
            // query_varsのpを使用（これは正しい値が設定されている）
            $query_post_id = $wp_query->get('p');
            $query_post_type = $wp_query->get('post_type');

            // query_varsにpost IDがある場合はそれを使用
            $check_post = null;
            if ($query_post_id) {
                $check_post = get_post($query_post_id);
            }

            // フォールバック: グローバル$postを使用
            if (!$check_post) {
                global $post;
                $check_post = $post;
            }

            if ($check_post) {
                // カスタム投稿タイプかチェック
                $is_custom_post_type = false;

                foreach ($post_types as $post_type) {
                    if ($check_post->post_type === $post_type->slug) {
                        $is_custom_post_type = true;
                        break;
                    }
                }

                // カスタム投稿タイプの場合、正しいパーマリンクかチェック
                if ($is_custom_post_type) {
                    $correct_permalink = get_permalink($check_post->ID);
                    $correct_path = parse_url($correct_permalink, PHP_URL_PATH);
                    $correct_path = rtrim($correct_path, '/') . '/';

                    // パスが一致しない場合は404
                    if ($correct_path !== $current_path) {
                        $should_block = true;
                    }
                }
            }
        }

        // ブロックすべき場合は404を表示
        if ($should_block) {
            global $wp_query;
            $wp_query->set_404();
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
            exit;
        }
    }

    /**
     * カスタム投稿タイプのフルパスを構築
     */
    private function build_full_path_for_post_type($post_type, $visited = array()) {
        // 循環参照を検出
        if (in_array($post_type->slug, $visited)) {
            error_log('KSTB Warning: Circular reference detected in post type hierarchy for: ' . $post_type->slug);
            return (!empty($post_type->url_slug)) ? $post_type->url_slug : $post_type->slug;
        }

        // 訪問済みリストに追加
        $visited[] = $post_type->slug;

        $effective_slug = (!empty($post_type->url_slug)) ? $post_type->url_slug : $post_type->slug;

        if (empty($post_type->parent_directory)) {
            return $effective_slug;
        }

        $parent_dir = trim($post_type->parent_directory, '/');

        // 親ディレクトリが別のカスタム投稿タイプかチェック
        $parent_post_type = KSTB_Database::get_post_type_by_slug($parent_dir);
        if ($parent_post_type) {
            // 親のフルパスを再帰的に取得
            $parent_path = $this->build_full_path_for_post_type($parent_post_type, $visited);
            return $parent_path . '/' . $effective_slug;
        }

        // 通常のディレクトリ
        return $parent_dir . '/' . $effective_slug;
    }

    /**
     * 不正な階層かチェック
     */
    private function is_incorrect_hierarchy($url_segments, $correct_path_segments, $post_type_slug) {
        // URLの最後から2番目のセグメントがカスタム投稿タイプのスラッグかチェック
        if (count($url_segments) >= 2) {
            $second_last = $url_segments[count($url_segments) - 2];

            // カスタム投稿タイプのスラッグと一致する場合
            if ($second_last === $post_type_slug) {
                // 正しい階層構造と比較
                if (count($correct_path_segments) > 1) {
                    // 正しい階層が存在するのに、URLがそれより短い場合
                    if (count($url_segments) < count($correct_path_segments) + 1) {
                        return true;
                    }

                    // URLの階層が正しい階層と一致しないかチェック
                    for ($i = 0; $i < count($correct_path_segments); $i++) {
                        if (!isset($url_segments[$i]) || $url_segments[$i] !== $correct_path_segments[$i]) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
