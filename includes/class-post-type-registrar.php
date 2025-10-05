<?php


class KSTB_Post_Type_Registrar {
    private static $instance = null;
    private static $initialized = false;

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
            'all_items' => 'すべての' . $post_type->label,
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
        $rewrite = json_decode($post_type->rewrite, true);
        if (empty($rewrite)) {
            $rewrite = array(
                'slug' => $post_type->slug,
                'with_front' => false,
                'feeds' => true,
                'pages' => true
            );
        } else {
            // slug は常に投稿タイプのslugを使用
            $rewrite['slug'] = $post_type->slug;
            // feeds と pages が未設定なら追加
            if (!isset($rewrite['feeds'])) {
                $rewrite['feeds'] = true;
            }
            if (!isset($rewrite['pages'])) {
                $rewrite['pages'] = true;
            }
        }

        // 親ディレクトリの設定を適用
        if (!empty($post_type->parent_directory)) {
            $parent_dir = trim($post_type->parent_directory, '/');
            // 親ディレクトリを含めたスラッグに変更
            $rewrite['slug'] = $parent_dir . '/' . $post_type->slug;
            // with_frontを強制的にfalseにして、余計なプレフィックスを防ぐ
            $rewrite['with_front'] = false;
        }

        // has_archiveの設定
        // 「表示しない」の場合は完全にfalseにする
        $has_archive = (bool) $post_type->has_archive;

        // WordPress標準に従った引数設定
        $args = array(
            'label' => $post_type->label,
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,  // 個別投稿ページは表示可能にする
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => $rewrite,
            'capability_type' => 'post',
            'has_archive' => $has_archive,
            'hierarchical' => (bool) $post_type->hierarchical,
            'menu_position' => (int) $post_type->menu_position ?: 25,
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

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type->slug)) {
                continue;
            }

            $post_type_object = get_post_type_object($post_type->slug);
            if (!$post_type_object || !$post_type_object->show_in_menu) {
                continue;
            }

            global $menu, $submenu;
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
}
