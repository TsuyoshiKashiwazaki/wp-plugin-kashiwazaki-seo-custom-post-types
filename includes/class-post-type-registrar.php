<?php


class KSTB_Post_Type_Registrar {
    private static $instance = null;
    private static $initialized = false;
    private static $hierarchical_post_id = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // initフックの優先度を5に設定（早めに実行）
        add_action('init', array($this, 'register_post_types'), 5);
        add_action('admin_menu', array($this, 'force_add_menus'), 999);

        // 投稿公開後のメッセージを改善
        add_filter('post_updated_messages', array($this, 'custom_post_updated_messages'));
        add_filter('bulk_post_updated_messages', array($this, 'custom_bulk_post_updated_messages'), 10, 2);

        // 階層的カスタム投稿タイプのパーマリンク生成フィルター
        add_filter('post_type_link', array($this, 'hierarchical_post_type_link'), 10, 4);

        // requestフィルターで階層的クエリを解決（KSTB_Archive_Controllerより前に実行）
        add_filter('request', array($this, 'resolve_hierarchical_request'), 1, 1);

        // pre_get_postsで階層的投稿タイプのクエリを修正
        add_action('pre_get_posts', array($this, 'fix_hierarchical_query'), 1, 1);

        // posts_pre_queryフィルターで直接投稿を返す
        add_filter('posts_pre_query', array($this, 'override_hierarchical_posts'), 10, 2);

        // the_postsフィルターで投稿を上書き（バックアップ）
        add_filter('the_posts', array($this, 'override_the_posts'), 10, 2);

        // wpアクションでグローバル変数を修正（最後に実行）
        add_action('wp', array($this, 'fix_global_post'), 9999);

        // template_redirectでも修正（validate_permalinkの前に実行）
        add_action('template_redirect', array($this, 'fix_global_post'), 0);

        // template_includeでもチェック
        add_filter('template_include', array($this, 'check_template_post'), 1);

        // wp_headの前に投稿を修正（タイトルタグの生成前）
        add_action('wp_head', array($this, 'fix_post_before_head'), -9999);

        // document_title_partsフィルターで正しいタイトルを設定
        add_filter('document_title_parts', array($this, 'fix_document_title'), 100);
    }

    /**
     * ドキュメントタイトルを修正
     */
    public function fix_document_title($title_parts) {
        global $wp_query;

        $p = $wp_query->get('p');
        $post_type = $wp_query->get('post_type');

        if (!$p || !$post_type) {
            return $title_parts;
        }

        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return $title_parts;
        }

        // 正しい投稿を取得
        $correct_post = get_post($p);
        if (!$correct_post) {
            return $title_parts;
        }


        $title_parts['title'] = $correct_post->post_title;

        return $title_parts;
    }

    /**
     * wp_headの前に投稿を修正
     */
    public function fix_post_before_head() {
        global $wp_query;

        $p = $wp_query->get('p');
        $post_type = $wp_query->get('post_type');

        if (!$p || !$post_type) {
            return;
        }

        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return;
        }

        // 正しい投稿を取得
        $correct_post = get_post($p);
        if (!$correct_post) {
            return;
        }

        // 現在の投稿が間違っている場合は修正
        if (!isset($wp_query->post) || $wp_query->post->ID != $p) {

            $wp_query->posts = array($correct_post);
            $wp_query->post = $correct_post;
            $wp_query->post_count = 1;
            $wp_query->found_posts = 1;

            $GLOBALS['post'] = $correct_post;
            setup_postdata($correct_post);
        }
    }

    /**
     * テンプレート読み込み直前の投稿を修正
     */
    public function check_template_post($template) {
        global $wp_query;

        $p = $wp_query->get('p');
        $post_type = $wp_query->get('post_type');


        if (!$p || !$post_type) {
            return $template;
        }

        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return $template;
        }

        // 正しい投稿を取得
        $correct_post = get_post($p);
        if (!$correct_post) {
            return $template;
        }


        // WP_Queryの投稿を修正
        $wp_query->posts = array($correct_post);
        $wp_query->post = $correct_post;
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;

        // グローバル$postも更新
        $GLOBALS['post'] = $correct_post;
        setup_postdata($correct_post);


        return $template;
    }

    /**
     * グローバル$postと$wp_queryを修正
     */
    public function fix_global_post() {
        if (is_admin()) {
            return;
        }

        global $wp_query;
        $p = $wp_query->get('p');
        $post_type = $wp_query->get('post_type');


        if (!$p || !$post_type) {
            return;
        }

        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return;
        }

        // 正しい投稿を取得
        $correct_post = get_post($p);
        if (!$correct_post) {
            return;
        }


        // 常に修正を適用（何かが上書きしている可能性があるため）

        $wp_query->posts = array($correct_post);
        $wp_query->post = $correct_post;
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;

        // グローバル$postも更新
        $GLOBALS['post'] = $correct_post;
        // setup_postdata($correct_post); // 一時的にコメントアウト

    }

    /**
     * 階層的投稿タイプのクエリを修正
     *
     * WP_Queryがquery_varsを処理した後、実際のクエリを実行する前に
     * 正しい投稿を取得するようクエリを修正する
     *
     * @param WP_Query $query クエリオブジェクト
     */
    public function fix_hierarchical_query($query) {
        // メインクエリのみ処理
        if (!$query->is_main_query() || is_admin()) {
            return;
        }

        // pが設定されていて、post_typeがカスタム投稿タイプの場合
        $p = $query->get('p');
        $post_type = $query->get('post_type');

        if (!$p || !$post_type) {
            return;
        }

        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return;
        }

        // 内部変数を直接クリア（query_varsだけでなく、内部処理用の変数も）
        $query->set('name', '');
        $query->set($post_type, '');
        $query->query_vars['name'] = '';
        $query->query_vars[$post_type] = '';

        // is_singleフラグを強制的に設定
        $query->is_single = true;
        $query->is_singular = true;
        $query->is_page = false;

        // 静的変数に保存してposts_pre_queryで使用
        self::$hierarchical_post_id = (int) $p;

        // デバッグ
    }

    /**
     * 階層的投稿タイプの投稿を直接返す
     *
     * @param array|null $posts 投稿配列またはnull
     * @param WP_Query $query クエリオブジェクト
     * @return array|null 投稿配列またはnull
     */
    public function override_hierarchical_posts($posts, $query) {
        // メインクエリのみ処理
        if (!$query->is_main_query() || is_admin()) {
            return $posts;
        }

        // 保存された投稿IDがある場合
        if (self::$hierarchical_post_id) {
            $post = get_post(self::$hierarchical_post_id);
            if ($post) {
                // クリアしない - the_postsでも使う
                return array($post);
            }
        }

        return $posts;
    }

    /**
     * the_postsフィルターで投稿を上書き
     *
     * @param array $posts 投稿配列
     * @param WP_Query $query クエリオブジェクト
     * @return array 投稿配列
     */
    public function override_the_posts($posts, $query) {
        // すべての呼び出しをログ

        // メインクエリのみ処理
        if (!$query->is_main_query() || is_admin()) {
            return $posts;
        }

        // 保存された投稿IDがある場合
        if (self::$hierarchical_post_id) {
            $post = get_post(self::$hierarchical_post_id);
            if ($post) {

                // WP_Queryの内部状態も更新
                $query->posts = array($post);
                $query->post_count = 1;
                $query->found_posts = 1;
                $query->post = $post;

                // グローバル$wp_queryも更新
                global $wp_query, $wp_the_query;
                $wp_query->posts = array($post);
                $wp_query->post_count = 1;
                $wp_query->found_posts = 1;
                $wp_query->post = $post;

                if ($wp_the_query !== $wp_query) {
                    $wp_the_query->posts = array($post);
                    $wp_the_query->post_count = 1;
                    $wp_the_query->found_posts = 1;
                    $wp_the_query->post = $post;
                }

                // グローバル$postも更新
                $GLOBALS['post'] = $post;


                // 一度使ったらクリア
                self::$hierarchical_post_id = null;
                return array($post);
            }
        }

        return $posts;
    }

    /**
     * カスタム投稿タイプのフルパスを再帰的に構築
     */
    private function build_full_path($slug, $visited = array()) {
        // 循環参照を検出
        if (in_array($slug, $visited)) {
            error_log('KSTB Warning: Circular reference detected in post type hierarchy for: ' . $slug);
            return $slug;
        }

        // 訪問済みリストに追加
        $visited[] = $slug;

        $post_type = KSTB_Database::get_post_type_by_slug($slug);
        if (!$post_type) {
            return $slug;
        }

        // url_slugが設定されている場合はそれを使用、なければslugを使用
        $effective_slug = (!empty($post_type->url_slug)) ? $post_type->url_slug : $slug;

        if (empty($post_type->parent_directory)) {
            return $effective_slug;
        }

        $parent = trim($post_type->parent_directory, '/');

        // 親がカスタム投稿タイプかチェック
        $parent_post_type = KSTB_Database::get_post_type_by_slug($parent);
        if ($parent_post_type) {
            // 親のフルパスを再帰的に取得（訪問済みリストを渡す）
            $parent_path = $this->build_full_path($parent, $visited);
            return $parent_path . '/' . $effective_slug;
        }

        // 親がカスタム投稿タイプでない場合（通常のディレクトリ）
        return $parent . '/' . $effective_slug;
    }

    /**
     * フルパスを構築（静的メソッド版）
     */
    public static function build_full_path_static($slug, $visited = array()) {
        // 循環参照を検出
        if (in_array($slug, $visited)) {
            error_log('KSTB Warning: Circular reference detected in post type hierarchy for: ' . $slug);
            return $slug;
        }

        // 訪問済みリストに追加
        $visited[] = $slug;

        $post_type = KSTB_Database::get_post_type_by_slug($slug);
        if (!$post_type) {
            return $slug;
        }

        // url_slugが設定されている場合はそれを使用、なければslugを使用
        $effective_slug = (!empty($post_type->url_slug)) ? $post_type->url_slug : $slug;

        if (empty($post_type->parent_directory)) {
            return $effective_slug;
        }

        $parent = trim($post_type->parent_directory, '/');

        // 親がカスタム投稿タイプかチェック
        $parent_post_type = KSTB_Database::get_post_type_by_slug($parent);
        if ($parent_post_type) {
            // 親のフルパスを再帰的に取得（訪問済みリストを渡す）
            $parent_path = self::build_full_path_static($parent, $visited);
            return $parent_path . '/' . $effective_slug;
        }

        // 親がカスタム投稿タイプでない場合（通常のディレクトリ）
        return $parent . '/' . $effective_slug;
    }

            public function register_post_types() {
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            $this->register_single_post_type($post_type);
        }
    }

    public function register_single_post_type($post_type_or_slug, $force = false) {
        // スラッグが渡された場合はデータベースから取得
        if (is_string($post_type_or_slug)) {
            $post_type = KSTB_Database::get_post_type_by_slug($post_type_or_slug);
            if (!$post_type) {
                return;
            }
        } else {
            $post_type = $post_type_or_slug;
        }
        
        // 通常の登録時は既存チェック、強制登録時はスキップ
        if (!$force && post_type_exists($post_type->slug)) {
            return;
        }

        $labels = json_decode($post_type->labels, true);
        $supports = json_decode($post_type->supports, true);

        if (empty($labels) || !is_array($labels)) {
            $labels = array();
        }

        // メニュー表示モードに応じたラベルを設定
        $menu_display_mode = !empty($post_type->menu_display_mode) ? $post_type->menu_display_mode : 'category';
        $parent_slug = KSTB_Parent_Menu_Manager::get_post_type_parent_slug($post_type);

        // 親メニューがある場合は階層構造風のラベルにする
        $all_items_label = 'すべての' . $post_type->label;
        if ($parent_slug !== false) {
            $all_items_label = '└ ' . $post_type->label;
        }

        // ラベルのデフォルト値設定（データベースクラスで既に処理済み）
        $default_labels = array(
            'name' => $post_type->label,
            'singular_name' => $post_type->label,
            'menu_name' => $post_type->label,
            'name_admin_bar' => $post_type->label,
            'add_new' => '新規追加',
            'add_new_item' => '新規' . $post_type->label . 'を追加',
            'new_item' => '新規' . $post_type->label,
            'edit_item' => $post_type->label . 'を編集',
            'view_item' => $post_type->label . 'を表示',
            'view_items' => $post_type->label . 'を表示',
            'all_items' => $all_items_label,
            'search_items' => $post_type->label . 'を検索',
            'not_found' => $post_type->label . 'が見つかりません',
            'not_found_in_trash' => 'ゴミ箱に' . $post_type->label . 'が見つかりません',
            'featured_image' => 'アイキャッチ画像',
            'set_featured_image' => 'アイキャッチ画像を設定',
            'remove_featured_image' => 'アイキャッチ画像を削除',
            'use_featured_image' => 'アイキャッチ画像として使用',
            'archives' => $post_type->label . 'アーカイブ',
            'insert_into_item' => $post_type->label . 'に挿入',
            'uploaded_to_this_item' => 'この' . $post_type->label . 'にアップロード',
            'filter_items_list' => $post_type->label . 'リストをフィルター',
            'items_list_navigation' => $post_type->label . 'リストナビゲーション',
            'items_list' => $post_type->label . 'リスト',
            // 投稿公開後のボタン用ラベル
            'item_published' => $post_type->label . 'を公開しました。',
            'item_published_privately' => $post_type->label . 'を非公開で公開しました。',
            'item_reverted_to_draft' => $post_type->label . 'を下書きに戻しました。',
            'item_scheduled' => $post_type->label . 'を予約投稿しました。',
            'item_updated' => $post_type->label . 'を更新しました。',
            'item_link' => $post_type->label . 'リンク',
            'item_link_description' => $post_type->label . 'へのリンク。'
        );

        // ユーザー設定を優先
        $labels = array_merge($default_labels, $labels);

        if (empty($supports) || !is_array($supports)) {
            $supports = array('title', 'editor', 'thumbnail');
        }

        // hierarchical が有効な場合は page-attributes を自動追加
        if ((bool) $post_type->hierarchical && !in_array('page-attributes', $supports)) {
            $supports[] = 'page-attributes';
        }

        // rewrite設定の準備
        // WordPressに登録するrewriteは短縮名を使用
        $rewrite = array(
            'slug' => $post_type->slug,
            'with_front' => false,
            'feeds' => true,
            'pages' => true
        );

        // 親ディレクトリの設定を適用
        if (!empty($post_type->parent_directory)) {
            // フルパスを再帰的に構築
            $full_path = $this->build_full_path($post_type->slug);
            $rewrite['slug'] = $full_path;
            // with_frontを強制的にfalseにして、余計なプレフィックスを防ぐ
            $rewrite['with_front'] = false;
        }

        // url_slugが存在し、slugと異なる場合はカスタムrewriteルールを追加
        if (!empty($post_type->url_slug) && $post_type->url_slug !== $post_type->slug) {
            $url_slug = $post_type->url_slug;
            $internal_slug = $post_type->slug;

            // 親ディレクトリがある場合はそれも含める
            if (!empty($post_type->parent_directory)) {
                $full_path = $this->build_full_path($post_type->slug);
                $prefix = !empty($full_path) ? $full_path . '/' : '';

                // 長いURLスラッグを短い内部名にマップ（個別投稿）
                add_rewrite_rule(
                    '^' . $prefix . $url_slug . '/([^/]+)/?$',
                    'index.php?' . $internal_slug . '=$matches[1]',
                    'top'
                );
                // アーカイブページ
                add_rewrite_rule(
                    '^' . $prefix . $url_slug . '/?$',
                    'index.php?post_type=' . $internal_slug,
                    'top'
                );
                // ページネーション
                add_rewrite_rule(
                    '^' . $prefix . $url_slug . '/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $internal_slug . '&paged=$matches[1]',
                    'top'
                );
            } else {
                // 長いURLスラッグを短い内部名にマップ（個別投稿）
                add_rewrite_rule(
                    '^' . $url_slug . '/([^/]+)/?$',
                    'index.php?' . $internal_slug . '=$matches[1]',
                    'top'
                );
                // アーカイブページ
                add_rewrite_rule(
                    '^' . $url_slug . '/?$',
                    'index.php?post_type=' . $internal_slug,
                    'top'
                );
                // ページネーション
                add_rewrite_rule(
                    '^' . $url_slug . '/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $internal_slug . '&paged=$matches[1]',
                    'top'
                );
            }
        }

        // has_archiveの設定
        // 「表示しない」の場合は完全にfalseにする
        $has_archive = (bool) $post_type->has_archive;

        // メニュー表示設定の決定
        $parent_slug = KSTB_Parent_Menu_Manager::get_post_type_parent_slug($post_type);
        $show_in_menu_value = false;

        if ($parent_slug !== false) {
            // サブメニューとして表示
            $show_in_menu_value = $parent_slug;
        } else {
            // トップレベルメニューとして表示
            $show_in_menu_value = (int) $post_type->menu_position ?: 25;
        }

        // WordPress標準に従った引数設定
        $args = array(
            'label' => $post_type->label,
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,  // 個別投稿ページは表示可能にする
            'show_ui' => true,
            'show_in_menu' => $show_in_menu_value,  // 親メニューまたはメニュー位置
            'query_var' => true,
            'rewrite' => $rewrite,
            'capability_type' => 'post',
            'has_archive' => $has_archive,
            'hierarchical' => (bool) $post_type->hierarchical,
            'menu_position' => null,  // show_in_menuで位置を制御するためnullにする
            'menu_icon' => $post_type->menu_icon ?: 'dashicons-admin-post',
            'supports' => $supports,
            'show_in_rest' => true,
            'rest_base' => $post_type->slug,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'rest_namespace' => 'wp/v2',
            'can_export' => true,
            'delete_with_user' => false,
            'exclude_from_search' => false,
            'map_meta_cap' => true
        );

        register_post_type($post_type->slug, $args);

        // タクソノミーの登録
        $taxonomies = !empty($post_type->taxonomies) ? json_decode($post_type->taxonomies, true) : array();
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    register_taxonomy_for_object_type($taxonomy, $post_type->slug);
                }
            }
        }
    }

    public function force_add_menus() {
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        global $menu, $submenu;

        // カテゴリーごとの投稿タイプ数をカウント
        $category_counts = array();
        foreach ($post_types as $post_type) {
            $parent_slug = KSTB_Parent_Menu_Manager::get_post_type_parent_slug($post_type);
            if ($parent_slug !== false) {
                if (!isset($category_counts[$parent_slug])) {
                    $category_counts[$parent_slug] = 0;
                }
                $category_counts[$parent_slug]++;
            }
        }

        $category_indexes = array();

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type->slug)) {
                continue;
            }

            $post_type_object = get_post_type_object($post_type->slug);
            // show_in_menuがfalseの場合のみスキップ（文字列の場合は親メニューを持つので続行）
            if (!$post_type_object || $post_type_object->show_in_menu === false) {
                continue;
            }

            // 親メニュースラッグを取得
            $parent_slug = KSTB_Parent_Menu_Manager::get_post_type_parent_slug($post_type);

            if ($parent_slug !== false) {
                // サブメニューとして追加
                $menu_page = 'edit.php?post_type=' . $post_type->slug;

                // サブメニューが既に存在するかチェック
                $submenu_exists = false;
                if (isset($submenu[$parent_slug]) && is_array($submenu[$parent_slug])) {
                    foreach ($submenu[$parent_slug] as $submenu_item) {
                        if (isset($submenu_item[2]) && $submenu_item[2] === $menu_page) {
                            $submenu_exists = true;
                            break;
                        }
                    }
                }

                // カテゴリー内での順番を追跡
                if (!isset($category_indexes[$parent_slug])) {
                    $category_indexes[$parent_slug] = 0;
                }
                $category_indexes[$parent_slug]++;

                // 最後の項目かどうか判定
                $is_last = ($category_indexes[$parent_slug] === $category_counts[$parent_slug]);
                $prefix = $is_last ? '└ ' : '├ ';

                // ラベルが長い場合は省略
                $display_label = $post_type->label;
                if (mb_strlen($display_label) > 15) {
                    $display_label = mb_substr($display_label, 0, 15) . '…';
                }

                if (!$submenu_exists) {
                    // 階層構造のラベル
                    $submenu_label = $prefix . $display_label;

                    add_submenu_page(
                        $parent_slug,
                        $post_type->label,
                        $submenu_label,  // メニュー表示時のラベル
                        'edit_posts',
                        $menu_page,
                        '',
                        0
                    );
                } else {
                    // 既存のサブメニューのラベルを更新
                    global $submenu;
                    if (isset($submenu[$parent_slug])) {
                        foreach ($submenu[$parent_slug] as $key => $item) {
                            if ($item[2] === $menu_page) {
                                $submenu[$parent_slug][$key][0] = $prefix . $display_label;
                                break;
                            }
                        }
                    }
                }
            } else {
                // トップレベルメニューとして追加
                $menu_exists = false;

                if (is_array($menu)) {
                    foreach ($menu as $menu_item) {
                        if (isset($menu_item[2]) && $menu_item[2] === 'edit.php?post_type=' . $post_type->slug) {
                            $menu_exists = true;
                            break;
                        }
                    }
                }

                if (!$menu_exists) {
                    add_menu_page(
                        $post_type->label,
                        $post_type->label,
                        'edit_posts',
                        'edit.php?post_type=' . $post_type->slug,
                        '',
                        $post_type->menu_icon ?: 'dashicons-admin-post',
                        (int) $post_type->menu_position ?: 25
                    );
                }
            }
        }
    }

    /**
     * カスタム投稿タイプの投稿公開後メッセージをカスタマイズ
     */
    public function custom_post_updated_messages($messages) {
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type->slug)) {
                continue;
            }

                                    $post_type_obj = get_post_type_object($post_type->slug);
            $permalink = get_permalink();
            $view_link = '';

            if ($permalink && $post_type_obj->public) {
                $view_link = sprintf(
                    ' <a href="%s">%sを表示</a>',
                    esc_url($permalink),
                    esc_html($post_type->label)
                );
            }

            $messages[$post_type->slug] = array(
                0  => '', // 未使用
                1  => $post_type->label . 'を更新しました。' . $view_link,
                2  => 'カスタムフィールドを更新しました。',
                3  => 'カスタムフィールドを削除しました。',
                4  => $post_type->label . 'を更新しました。',
                5  => isset($_GET['revision']) ? sprintf('%sを%sに復元しました。', $post_type->label, wp_post_revision_title((int) $_GET['revision'], false)) : false,
                6  => $post_type->label . 'を公開しました。' . $view_link,
                7  => $post_type->label . 'を保存しました。',
                8  => sprintf('%sを送信しました。%s', $post_type->label, $view_link),
                9  => sprintf(
                    '%sを予約投稿しました。予定日時: <strong>%s</strong>。%s',
                    $post_type->label,
                    date_i18n('Y年n月j日 G:i', strtotime(get_post()->post_date)),
                    $view_link
                ),
                10 => $post_type->label . 'の下書きを更新しました。' . $view_link
            );
        }

        return $messages;
    }

    /**
     * カスタム投稿タイプの一括操作メッセージをカスタマイズ
     */
    public function custom_bulk_post_updated_messages($bulk_messages, $bulk_counts) {
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type->slug)) {
                continue;
            }

            $bulk_messages[$post_type->slug] = array(
                'updated'   => _n('%s件の' . $post_type->label . 'を更新しました。', '%s件の' . $post_type->label . 'を更新しました。', $bulk_counts['updated']),
                'locked'    => _n('%s件の' . $post_type->label . 'を更新できませんでした。他のユーザーが編集中です。', '%s件の' . $post_type->label . 'を更新できませんでした。他のユーザーが編集中です。', $bulk_counts['locked']),
                'deleted'   => _n('%s件の' . $post_type->label . 'を完全に削除しました。', '%s件の' . $post_type->label . 'を完全に削除しました。', $bulk_counts['deleted']),
                'trashed'   => _n('%s件の' . $post_type->label . 'をゴミ箱に移動しました。', '%s件の' . $post_type->label . 'をゴミ箱に移動しました。', $bulk_counts['trashed']),
                'untrashed' => _n('%s件の' . $post_type->label . 'をゴミ箱から復元しました。', '%s件の' . $post_type->label . 'をゴミ箱から復元しました。', $bulk_counts['untrashed'])
            );
        }

        return $bulk_messages;
    }

    /**
     * 階層的カスタム投稿タイプのパーマリンクを生成
     *
     * @param string $post_link 生成されたパーマリンク
     * @param WP_Post $post 投稿オブジェクト
     * @param bool $leavename 投稿名をそのままにするか
     * @param bool $sample サンプルパーマリンクか
     * @return string 修正されたパーマリンク
     */
    public function hierarchical_post_type_link($post_link, $post, $leavename, $sample) {
        // カスタム投稿タイプかチェック
        $post_type_obj = KSTB_Database::get_post_type_by_slug($post->post_type);
        if (!$post_type_obj || !(bool) $post_type_obj->hierarchical) {
            return $post_link;
        }

        // 親投稿がある場合、階層的なパスを構築
        if ($post->post_parent) {
            $parent_path = $this->get_post_ancestors_path($post);
            if ($parent_path) {
                // URLを再構築
                $base_path = $this->build_full_path($post->post_type);
                $post_name = $leavename ? '%' . $post->post_type . '%' : $post->post_name;
                $post_link = home_url($base_path . '/' . $parent_path . '/' . $post_name . '/');
            }
        }

        return $post_link;
    }

    /**
     * 投稿の先祖パスを取得
     *
     * @param WP_Post $post 投稿オブジェクト
     * @return string 先祖のスラッグを/で連結したパス
     */
    private function get_post_ancestors_path($post) {
        $ancestors = get_post_ancestors($post);
        if (empty($ancestors)) {
            return '';
        }

        $path_parts = array();
        foreach (array_reverse($ancestors) as $ancestor_id) {
            $ancestor = get_post($ancestor_id);
            if ($ancestor) {
                $path_parts[] = $ancestor->post_name;
            }
        }

        return implode('/', $path_parts);
    }

    /**
     * requestフィルターで階層的クエリを解決
     *
     * @param array $query_vars クエリ変数
     * @return array 修正されたクエリ変数
     */
    public function resolve_hierarchical_request($query_vars) {
        // 管理画面はスキップ
        if (is_admin()) {
            return $query_vars;
        }

        // 階層的カスタム投稿タイプを取得
        $post_types = KSTB_Database::get_all_post_types();

        foreach ($post_types as $post_type) {
            if (!(bool) $post_type->hierarchical) {
                continue;
            }

            // クエリ変数にこの投稿タイプのスラッグがあるかチェック
            if (!empty($query_vars[$post_type->slug])) {
                $path = $query_vars[$post_type->slug];

                // パスに / が含まれる場合は階層的なURL
                if (strpos($path, '/') !== false) {
                    $segments = explode('/', trim($path, '/'));
                    $post_name = array_pop($segments); // 最後のセグメントが投稿スラッグ

                    // 投稿を検索
                    $found_post = $this->find_hierarchical_post($post_type->slug, $post_name, $segments);

                    if ($found_post) {
                        // クエリ変数を書き換え
                        $query_vars['p'] = $found_post->ID;
                        $query_vars['post_type'] = $post_type->slug;
                        unset($query_vars[$post_type->slug]); // 元のクエリ変数を削除
                        unset($query_vars['name']); // nameも削除

                        // デバッグ: 書き換え後のquery_vars

                        return $query_vars;
                    }
                }
            }
        }

        // クエリ変数に投稿タイプスラッグがない場合、URIから直接解析
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return $query_vars;
        }

        $uri_segments = explode('/', $uri);

        foreach ($post_types as $post_type) {
            if (!(bool) $post_type->hierarchical) {
                continue;
            }

            // 投稿タイプのベースパスを取得
            $base_path = $this->build_full_path($post_type->slug);
            $base_segments = explode('/', $base_path);

            // URIがベースパスで始まるかチェック
            if (count($uri_segments) > count($base_segments)) {
                $matches = true;
                for ($i = 0; $i < count($base_segments); $i++) {
                    if ($uri_segments[$i] !== $base_segments[$i]) {
                        $matches = false;
                        break;
                    }
                }

                if ($matches) {
                    // ベースパス以降のセグメントを取得
                    $remaining_segments = array_slice($uri_segments, count($base_segments));

                    // 2つ以上のセグメントがある場合（親/子の構造）
                    if (count($remaining_segments) >= 2) {
                        $post_name = array_pop($remaining_segments);
                        $parent_slugs = $remaining_segments;

                        // 投稿を検索
                        $found_post = $this->find_hierarchical_post($post_type->slug, $post_name, $parent_slugs);

                        if ($found_post) {
                            // クエリ変数を書き換え
                            $query_vars['p'] = $found_post->ID;
                            $query_vars['post_type'] = $post_type->slug;
                            // 既存のクエリ変数をクリア
                            if (isset($query_vars[$post_type->slug])) {
                                unset($query_vars[$post_type->slug]);
                            }
                            if (isset($query_vars['name'])) {
                                unset($query_vars['name']);
                            }
                            if (isset($query_vars['pagename'])) {
                                unset($query_vars['pagename']);
                            }
                            return $query_vars;
                        }
                    }
                }
            }
        }

        return $query_vars;
    }

    /**
     * 階層的投稿を検索
     *
     * @param string $post_type 投稿タイプスラッグ
     * @param string $post_name 投稿スラッグ
     * @param array $parent_slugs 親投稿のスラッグ配列（先祖から順）
     * @return WP_Post|null 見つかった投稿、またはnull
     */
    private function find_hierarchical_post($post_type, $post_name, $parent_slugs) {
        global $wpdb;

        // 投稿を名前で検索
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->posts}
             WHERE post_name = %s
             AND post_type = %s
             AND post_status = 'publish'",
            $post_name,
            $post_type
        ));

        if (!$post) {
            return null;
        }

        // 親階層を検証
        if (!empty($parent_slugs)) {
            $current_post = $post;
            $parent_slugs_reversed = array_reverse($parent_slugs);

            foreach ($parent_slugs_reversed as $expected_slug) {
                if (!$current_post->post_parent) {
                    return null; // 期待される親がいない
                }

                $parent = get_post($current_post->post_parent);
                if (!$parent || $parent->post_name !== $expected_slug) {
                    return null; // 親のスラッグが一致しない
                }

                $current_post = $parent;
            }
        }

        return $post;
    }
}
