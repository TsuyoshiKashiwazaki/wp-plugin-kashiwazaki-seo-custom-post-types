<?php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * カスタム投稿タイプのメニュー表示問題を修正するクラス
 */
class KSTB_Post_Type_Menu_Fix {

    public static function init() {
        // 非常に遅いタイミングでメニューを確認・修正
        add_action('adminmenu', array(__CLASS__, 'ensure_menus_visible'));
        add_action('admin_head', array(__CLASS__, 'add_menu_css_fix'));

        // メニューの表示をフィルタ
        add_filter('parent_file', array(__CLASS__, 'fix_parent_file'));
    }

    /**
     * メニューが確実に表示されるようにする
     */
    public static function ensure_menus_visible() {
        global $menu, $submenu;

        // カスタム投稿タイプを取得
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type->slug) || !$post_type->show_in_menu) {
                continue;
            }

            $post_type_obj = get_post_type_object($post_type->slug);
            if (!$post_type_obj) {
                continue;
            }

            // メニューが既に存在するか確認
            $menu_exists = false;
            foreach ($menu as $key => $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === 'edit.php?post_type=' . $post_type->slug) {
                    $menu_exists = true;
                    // メニューが隠されている場合は表示する
                    if (isset($menu_item[4]) && strpos($menu_item[4], 'wp-menu-separator') === false) {
                        $menu[$key][4] = str_replace('wp-hidden', '', $menu_item[4]);
                    }
                    break;
                }
            }

            // メニューが存在しない場合は追加
            if (!$menu_exists) {
                $position = $post_type->menu_position ? (int) $post_type->menu_position : 25;

                // 位置が既に使用されている場合は空いている位置を探す
                while (isset($menu[$position])) {
                    $position++;
                }

                $menu[$position] = array(
                    $post_type_obj->labels->menu_name,
                    $post_type_obj->cap->edit_posts,
                    'edit.php?post_type=' . $post_type->slug,
                    '',
                    'menu-top menu-icon-' . $post_type->slug,
                    'menu-' . $post_type->slug,
                    $post_type->menu_icon ? $post_type->menu_icon : 'dashicons-admin-post'
                );

                // サブメニューを追加
                $submenu['edit.php?post_type=' . $post_type->slug] = array(
                    array($post_type_obj->labels->all_items, $post_type_obj->cap->edit_posts, 'edit.php?post_type=' . $post_type->slug),
                    array($post_type_obj->labels->add_new, $post_type_obj->cap->edit_posts, 'post-new.php?post_type=' . $post_type->slug)
                );


            }
        }

        // メニューを再ソート
        ksort($menu);
    }

    /**
     * CSSでメニューの表示を強制
     */
    public static function add_menu_css_fix() {
        ?>
        <style>
            /* カスタム投稿タイプのメニューを強制表示 */
            <?php
            $post_types = KSTB_Database::get_all_post_types();
            foreach ($post_types as $post_type) :
                if (post_type_exists($post_type->slug)) :
            ?>
            #adminmenu li.menu-icon-<?php echo esc_attr($post_type->slug); ?> {
                display: block !important;
                visibility: visible !important;
            }
            <?php
                endif;
            endforeach;
            ?>
        </style>
        <?php
    }

    /**
     * parent_fileフィルタを修正
     */
    public static function fix_parent_file($parent_file) {
        global $post_type;



        return $parent_file;
    }
}
