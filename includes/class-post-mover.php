<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カスタム投稿タイプ間の記事移動機能
 */
class KSTB_Post_Mover {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * 記事を別の投稿タイプに移動
     *
     * @param array $post_ids 移動する記事IDの配列
     * @param string $from_type 移動元の投稿タイプ
     * @param string $to_type 移動先の投稿タイプ
     * @return array 結果情報（success, moved_count, failed_count, errors）
     */
    public function move_posts($post_ids, $from_type, $to_type) {
        $result = array(
            'success' => false,
            'moved_count' => 0,
            'failed_count' => 0,
            'errors' => array()
        );

        // バリデーション
        if (empty($post_ids) || !is_array($post_ids)) {
            $result['errors'][] = '移動する記事が選択されていません。';
            return $result;
        }

        if (empty($from_type) || empty($to_type)) {
            $result['errors'][] = '移動元または移動先の投稿タイプが指定されていません。';
            return $result;
        }

        if ($from_type === $to_type) {
            $result['errors'][] = '移動元と移動先が同じ投稿タイプです。';
            return $result;
        }

        // 投稿タイプの存在確認
        if (!post_type_exists($from_type) || !post_type_exists($to_type)) {
            $result['errors'][] = '指定された投稿タイプが存在しません。';
            return $result;
        }

        // 移動先はKSTBカスタム投稿タイプのみ
        $to_post_type = KSTB_Database::get_post_type_by_slug($to_type);
        if (!$to_post_type) {
            $result['errors'][] = '移動先はこのプラグインで作成されたカスタム投稿タイプのみ指定できます。';
            return $result;
        }

        // 移動元の情報を取得（標準投稿タイプも許可）
        $from_post_type = KSTB_Database::get_post_type_by_slug($from_type);
        // 標準投稿タイプの場合はnullだが、それでOK

        // 移動先のタクソノミー情報を取得
        $to_taxonomies = !empty($to_post_type->taxonomies) ? json_decode($to_post_type->taxonomies, true) : array();

        // 各記事を移動
        foreach ($post_ids as $post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0) {
                continue;
            }

            $post = get_post($post_id);

            // 記事の存在と投稿タイプ確認
            if (!$post || $post->post_type !== $from_type) {
                $result['failed_count']++;
                $result['errors'][] = sprintf('記事ID %d: 移動元の投稿タイプと一致しません。', $post_id);
                continue;
            }

            // 投稿タイプを更新
            $updated = wp_update_post(array(
                'ID' => $post_id,
                'post_type' => $to_type
            ), true);

            if (is_wp_error($updated)) {
                $result['failed_count']++;
                $result['errors'][] = sprintf('記事ID %d: %s', $post_id, $updated->get_error_message());
                continue;
            }

            // タクソノミーの処理（移動先でサポートされていないタクソノミーを削除）
            $post_taxonomies = get_object_taxonomies($from_type);
            foreach ($post_taxonomies as $taxonomy) {
                if (!in_array($taxonomy, $to_taxonomies)) {
                    // 移動先でサポートされていないタクソノミーの関連を削除
                    wp_set_object_terms($post_id, array(), $taxonomy);
                }
            }

            // パーマリンクをフラッシュ（URLが変わるため）
            delete_post_meta($post_id, '_wp_old_slug');

            $result['moved_count']++;
        }

        // 全体の成功判定
        $result['success'] = $result['moved_count'] > 0;

        // リライトルールをフラッシュ
        if ($result['success']) {
            flush_rewrite_rules();
        }

        return $result;
    }

    /**
     * 指定された投稿タイプの記事一覧を取得
     *
     * @param string $post_type 投稿タイプ
     * @param array $args 追加のクエリ引数
     * @return array 記事情報の配列
     */
    public function get_posts_by_type($post_type, $args = array()) {
        if (empty($post_type) || !post_type_exists($post_type)) {
            return array();
        }

        // 標準投稿タイプ（post, page）も許可
        // KSTBカスタム投稿タイプでなくてもOK

        $default_args = array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'all'
        );

        $query_args = wp_parse_args($args, $default_args);
        $posts = get_posts($query_args);

        $result = array();
        foreach ($posts as $post) {
            $result[] = array(
                'ID' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'permalink' => get_permalink($post->ID)
            );
        }

        return $result;
    }

    /**
     * 移動可能な投稿タイプの一覧を取得（移動元として選択可能なもの）
     *
     * @param bool $include_builtin 標準投稿タイプ（post, page）を含めるか
     * @return array 投稿タイプの配列（slug => label）
     */
    public function get_movable_post_types($include_builtin = true) {
        $result = array();

        // 標準投稿タイプを含める場合
        if ($include_builtin) {
            $result['post'] = '投稿（標準）';
            $result['page'] = '固定ページ（標準）';
        }

        // カスタム投稿タイプを追加
        $post_types = KSTB_Database::get_all_post_types();
        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type->slug)) {
                $result[$post_type->slug] = $post_type->label;
            }
        }

        return $result;
    }

    /**
     * 移動先として選択可能な投稿タイプの一覧を取得（KSTBカスタム投稿タイプのみ）
     *
     * @return array 投稿タイプの配列（slug => label）
     */
    public function get_target_post_types() {
        $post_types = KSTB_Database::get_all_post_types();
        $result = array();

        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type->slug)) {
                $result[$post_type->slug] = $post_type->label;
            }
        }

        return $result;
    }

    /**
     * 移動の妥当性をチェック
     *
     * @param string $from_type 移動元
     * @param string $to_type 移動先
     * @return array チェック結果（valid, warnings）
     */
    public function validate_move($from_type, $to_type) {
        $result = array(
            'valid' => true,
            'warnings' => array()
        );

        if ($from_type === $to_type) {
            $result['valid'] = false;
            $result['warnings'][] = '移動元と移動先が同じです。';
            return $result;
        }

        // 移動先はKSTBカスタム投稿タイプのみ
        $to_post_type = KSTB_Database::get_post_type_by_slug($to_type);
        if (!$to_post_type) {
            $result['valid'] = false;
            $result['warnings'][] = '移動先の投稿タイプが見つかりません。';
            return $result;
        }

        // 移動元（標準投稿タイプも許可）
        $from_post_type = KSTB_Database::get_post_type_by_slug($from_type);
        $is_builtin_source = in_array($from_type, array('post', 'page'));

        // サポート機能の違いを警告（移動元がカスタム投稿タイプの場合のみ）
        if ($from_post_type) {
            $from_supports = !empty($from_post_type->supports) ? json_decode($from_post_type->supports, true) : array();
            $to_supports = !empty($to_post_type->supports) ? json_decode($to_post_type->supports, true) : array();

            $missing_supports = array_diff($from_supports, $to_supports);
            if (!empty($missing_supports)) {
                $result['warnings'][] = '移動先でサポートされていない機能: ' . implode(', ', $missing_supports);
            }
        }

        // タクソノミーの違いを警告
        if ($from_post_type) {
            $from_taxonomies = !empty($from_post_type->taxonomies) ? json_decode($from_post_type->taxonomies, true) : array();
        } else if ($is_builtin_source) {
            // 標準投稿タイプのデフォルトタクソノミー
            $from_taxonomies = array('category', 'post_tag');
        } else {
            $from_taxonomies = array();
        }

        $to_taxonomies = !empty($to_post_type->taxonomies) ? json_decode($to_post_type->taxonomies, true) : array();

        $missing_taxonomies = array_diff($from_taxonomies, $to_taxonomies);
        if (!empty($missing_taxonomies)) {
            $result['warnings'][] = '移動先でサポートされていないタクソノミー: ' . implode(', ', $missing_taxonomies) . ' の関連付けは削除されます。';
        }

        // URLの変更を警告
        $result['warnings'][] = 'パーマリンク（URL）が変更されます。SEOに影響する可能性があります。';

        return $result;
    }
}
