<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 階層的カスタム投稿タイプの旧スラッグ保存クラス
 *
 * WordPress コアの wp_check_for_changed_slugs() は hierarchical な投稿タイプを
 * 明示的に除外するため、type-builder が作成した hierarchical CPT でスラッグを
 * 変更しても _wp_old_slug が保存されない。この問題を補完する。
 */
class KSTB_Old_Slug_Tracker {
    private static $instance = null;
    private $managed_slugs_cache = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        // コアの wp_check_for_changed_slugs は priority 12 で登録されている
        add_action('post_updated', array($this, 'save_old_slug_for_hierarchical_cpt'), 15, 3);
    }

    /**
     * 階層的CPTのスラッグ変更時に旧スラッグを保存する
     *
     * @param int     $post_id     投稿ID
     * @param WP_Post $post        更新後の投稿オブジェクト
     * @param WP_Post $post_before 更新前の投稿オブジェクト
     */
    public function save_old_slug_for_hierarchical_cpt($post_id, $post, $post_before) {
        // スラッグ未変更 → return
        if ($post->post_name === $post_before->post_name) {
            return;
        }

        // publish でない → return
        if ($post->post_status !== 'publish') {
            return;
        }

        // page はコア側で別途処理されるため除外
        if ($post->post_type === 'page') {
            return;
        }

        // non-hierarchical はコアが処理するため除外
        if (!is_post_type_hierarchical($post->post_type)) {
            return;
        }

        // プラグイン管理のCPTでない → return
        $managed_slugs = $this->get_managed_slugs();
        if (!in_array($post->post_type, $managed_slugs, true)) {
            return;
        }

        // 旧スラッグ保存ロジック（コアの wp_check_for_changed_slugs と同一）
        $old_slugs = (array) get_post_meta($post_id, '_wp_old_slug');

        // 新スラッグが旧スラッグリストにある場合は削除（スラッグを戻した場合）
        if (in_array($post->post_name, $old_slugs, true)) {
            delete_post_meta($post_id, '_wp_old_slug', $post->post_name);
        }

        // 旧スラッグが未保存なら追加
        if (!in_array($post_before->post_name, $old_slugs, true)) {
            add_post_meta($post_id, '_wp_old_slug', $post_before->post_name);
        }
    }

    /**
     * プラグイン管理のCPTスラッグ一覧を取得（キャッシュ付き）
     *
     * @return array スラッグの配列
     */
    private function get_managed_slugs() {
        if ($this->managed_slugs_cache === null) {
            $post_types = KSTB_Database::get_all_post_types();
            $this->managed_slugs_cache = array();
            foreach ($post_types as $post_type) {
                $this->managed_slugs_cache[] = $post_type->slug;
            }
        }
        return $this->managed_slugs_cache;
    }
}
