<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カスタム投稿タイプの親メニュー管理クラス
 *
 * カテゴリーごとに親メニューを作成し、カスタム投稿タイプをサブメニューとして整理します。
 */
class KSTB_Parent_Menu_Manager {

    /**
     * 登録された親メニューのスラッグを保存
     * @var array
     */
    private static $registered_parent_menus = array();

    /**
     * 初期化
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_parent_menus'), 5);
    }

    /**
     * 親メニューを登録
     *
     * カスタム投稿タイプのカテゴリーを収集し、各カテゴリーごとに親メニューを作成します。
     */
    public static function register_parent_menus() {
        $post_types = KSTB_Database::get_all_post_types();
        $categories = array();

        // カテゴリーモードの投稿タイプからカテゴリーを収集
        foreach ($post_types as $post_type) {
            $menu_display_mode = !empty($post_type->menu_display_mode) ? $post_type->menu_display_mode : 'category';

            if ($menu_display_mode === 'category' && !empty($post_type->menu_parent_category)) {
                $category = $post_type->menu_parent_category;

                if (!isset($categories[$category])) {
                    $categories[$category] = array(
                        'name' => $category,
                        'post_types' => array(),
                        'icon' => 'dashicons-category'
                    );
                }

                $categories[$category]['post_types'][] = $post_type;
            }
        }

        // カテゴリーごとに親メニューを登録
        foreach ($categories as $category => $data) {
            $menu_slug = self::generate_menu_slug($category);

            // データベースからカテゴリー情報を取得（アイコン含む）
            $category_data = KSTB_Database::get_category($category);
            $icon = $category_data && !empty($category_data->icon) ? $category_data->icon : 'dashicons-category';

            // 親メニューページを追加
            add_menu_page(
                $category,                              // page_title
                $category,                              // menu_title
                'edit_posts',                          // capability
                $menu_slug,                            // menu_slug - 独自のスラッグ
                array(__CLASS__, 'render_parent_menu_page'), // callback
                $icon,                                 // icon
                25                                     // position
            );

            // 登録された親メニューを記録（サブメニュー追加時に使用するスラッグ）
            self::$registered_parent_menus[$category] = $menu_slug;
        }

        // カスタム親メニュー（menu_parent_slug指定）の処理
        foreach ($post_types as $post_type) {
            $menu_display_mode = !empty($post_type->menu_display_mode) ? $post_type->menu_display_mode : 'category';

            if ($menu_display_mode === 'custom_parent' && !empty($post_type->menu_parent_slug)) {
                $parent_slug = $post_type->menu_parent_slug;

                // 親メニューがまだ作成されていない場合は作成
                if (!in_array($parent_slug, self::$registered_parent_menus)) {
                    // このスラッグが既にWordPressに存在するかチェック
                    global $menu;
                    $parent_exists = false;

                    if (!empty($menu)) {
                        foreach ($menu as $menu_item) {
                            if (isset($menu_item[2]) && $menu_item[2] === $parent_slug) {
                                $parent_exists = true;
                                break;
                            }
                        }
                    }

                    // 存在しない場合は新しい親メニューを作成
                    if (!$parent_exists) {
                        add_menu_page(
                            $parent_slug,                          // page_title
                            $parent_slug,                          // menu_title
                            'edit_posts',                         // capability
                            $parent_slug,                         // menu_slug
                            array(__CLASS__, 'render_parent_menu_page'), // callback
                            'dashicons-admin-generic',            // icon
                            25                                    // position
                        );
                    }

                    self::$registered_parent_menus[] = $parent_slug;
                }
            }
        }
    }

    /**
     * カテゴリー名からメニュースラッグを生成
     *
     * @param string $category カテゴリー名
     * @return string メニュースラッグ
     */
    public static function generate_menu_slug($category) {
        // カテゴリー名からスラッグを生成
        $slug = sanitize_title($category);

        // 日本語の場合は英数字のハッシュを使用
        if (empty($slug) || preg_match('/[^\x20-\x7E]/', $category)) {
            $slug = 'kstb-category-' . substr(md5($category), 0, 8);
        } else {
            $slug = 'kstb-' . $slug;
        }

        return $slug;
    }

    /**
     * 親メニューのスラッグを取得
     *
     * @param string $category カテゴリー名
     * @return string|null メニュースラッグ、見つからない場合はnull
     */
    public static function get_parent_menu_slug($category) {
        if (isset(self::$registered_parent_menus[$category])) {
            return self::$registered_parent_menus[$category];
        }
        return null;
    }

    /**
     * 親メニューページをレンダリング
     *
     * カテゴリーに属する投稿タイプの一覧を表示します。
     */
    public static function render_parent_menu_page() {
        // 現在の親メニューのスラッグを取得
        $current_menu = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if (empty($current_menu)) {
            return;
        }

        // カテゴリーに属する投稿タイプの一覧を表示
        $category_name = '';

        // 現在のメニュースラッグからカテゴリー名を逆引き
        foreach (self::$registered_parent_menus as $cat => $slug) {
            if ($slug === $current_menu) {
                $category_name = $cat;
                break;
            }
        }

        // カテゴリー名が見つからない場合は全投稿タイプから探す
        if (empty($category_name)) {
            $all_post_types = KSTB_Database::get_all_post_types();
            foreach ($all_post_types as $pt) {
                if ($pt->menu_display_mode === 'category' && !empty($pt->menu_parent_category)) {
                    $slug = self::generate_menu_slug($pt->menu_parent_category);
                    if ($slug === $current_menu) {
                        $category_name = $pt->menu_parent_category;
                        break;
                    }
                }
            }
        }

        if (empty($category_name)) {
            $category_name = 'カテゴリー';
        }

        // このカテゴリーに属する投稿タイプを取得
        $category_post_types = array();
        $all_post_types = KSTB_Database::get_all_post_types();

        foreach ($all_post_types as $pt) {
            if ($pt->menu_display_mode === 'category' && $pt->menu_parent_category === $category_name) {
                $category_post_types[] = $pt;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($category_name); ?></h1>

            <?php if (empty($category_post_types)) : ?>
                <p>このカテゴリーにはカスタム投稿タイプがまだ登録されていません。</p>
            <?php else : ?>
                <p>このカテゴリーに属するカスタム投稿タイプ一覧</p>

                <div class="kstb-category-overview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach ($category_post_types as $pt) : ?>
                        <?php
                        $post_count = wp_count_posts($pt->slug);
                        $published = isset($post_count->publish) ? $post_count->publish : 0;
                        $draft = isset($post_count->draft) ? $post_count->draft : 0;
                        ?>
                        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 4px; background: #fff;">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                                <span class="dashicons <?php echo esc_attr($pt->menu_icon ?: 'dashicons-admin-post'); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                <?php echo esc_html($pt->label); ?>
                            </h3>
                            <p style="margin: 10px 0; color: #666;">
                                公開: <strong><?php echo esc_html($published); ?></strong>件 /
                                下書き: <strong><?php echo esc_html($draft); ?></strong>件
                            </p>
                            <p style="margin-top: 15px;">
                                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $pt->slug)); ?>" class="button button-primary">
                                    一覧を見る
                                </a>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $pt->slug)); ?>" class="button">
                                    新規追加
                                </a>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 投稿タイプの親メニュースラッグを取得
     *
     * @param object $post_type 投稿タイプオブジェクト
     * @return string|bool 親メニューのスラッグ、またはfalse（トップレベルの場合）
     */
    public static function get_post_type_parent_slug($post_type) {
        $menu_display_mode = !empty($post_type->menu_display_mode) ? $post_type->menu_display_mode : 'category';

        if ($menu_display_mode === 'toplevel') {
            // トップレベルメニューとして表示
            return false;
        } elseif ($menu_display_mode === 'custom_parent' && !empty($post_type->menu_parent_slug)) {
            // カスタム親メニュー
            return $post_type->menu_parent_slug;
        } elseif ($menu_display_mode === 'category' && !empty($post_type->menu_parent_category)) {
            // カテゴリーモード
            return self::generate_menu_slug($post_type->menu_parent_category);
        }

        // デフォルト: カテゴリーモード
        if (!empty($post_type->menu_parent_category)) {
            return self::generate_menu_slug($post_type->menu_parent_category);
        }

        return false;
    }

    /**
     * すべてのカテゴリーを取得
     *
     * @return array カテゴリー名の配列
     */
    public static function get_all_categories() {
        // データベースのカテゴリーテーブルから取得
        $category_objects = KSTB_Database::get_all_categories();
        $categories = array();

        foreach ($category_objects as $cat) {
            $categories[] = $cat->name;
        }

        return $categories;
    }
}
