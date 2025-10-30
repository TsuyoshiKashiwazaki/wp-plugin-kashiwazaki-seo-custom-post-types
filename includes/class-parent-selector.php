<?php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * カスタム投稿タイプの親ページ選択機能
 */
class KSTB_Parent_Selector {
    private static $instance = null;
    private $rewrite_rules_added = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        // 最優先：WordPressが起動する前にリダイレクトを完全阻止
        try {
            $this->emergency_redirect_prevention();
        } catch (Exception $e) {
            throw $e;
        }

        // カスタム投稿タイプの編集画面にメタボックスを追加
        add_action('add_meta_boxes', array($this, 'add_parent_selector_metabox'));
        // 投稿保存時に親ページ情報を保存
        add_action('save_post', array($this, 'save_parent_page_data'));
        // 管理画面のスタイルとスクリプトを追加
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // パーマリンクフィルターを再有効化（階層URLを表示）
        add_filter('post_link', array($this, 'custom_post_link'), 10, 2);
        add_filter('post_type_link', array($this, 'custom_post_link'), 10, 2);

        // リライトルールを最優先で登録
        add_action('init', array($this, 'add_enhanced_rewrite_rules'), 1);
        
        // 親ディレクトリが設定されている場合は古いURLを404にする
        add_action('template_redirect', array($this, 'block_old_urls'), 1);

                        // 最も早い段階でWordPressを完全制御
        add_action('muplugins_loaded', array($this, 'early_redirect_prevention'), 1);
        add_filter('do_parse_request', array($this, 'intercept_wordpress_parsing'), 1, 3);
        add_action('parse_request', array($this, 'handle_custom_hierarchy_parsing'), 1);

                // より強力なリダイレクト阻止
        add_filter('redirect_canonical', array($this, 'absolute_canonical_blocker'), 1, 2);
        add_filter('wp_redirect', array($this, 'absolute_redirect_blocker'), 1, 2);
        add_action('wp_head', array($this, 'set_hierarchy_canonical'), 1);

        // さらに早い段階でのフック
        add_action('plugins_loaded', array($this, 'super_early_hooks'), 1);
        add_action('setup_theme', array($this, 'theme_level_hooks'), 1);

        // 即座に実行する強制フック
        $this->immediate_redirect_block();

        // メインクエリ修正の最終確認（一時的に無効化）
        // add_action('wp', array($this, 'force_hierarchy_query_final'), 1);

        // 最終的な防御ライン：テンプレートリダイレクトを無効化
        add_action('template_redirect', array($this, 'final_redirect_defense'), 1);

        // リダイレクト関数そのものをオーバーライド
        add_action('init', array($this, 'override_redirect_functions'), 1);

        // デバッグ情報の出力を無効化
        // add_action('wp_footer', array($this, 'output_debug_comments'), 999);
        // add_action('wp_head', array($this, 'output_debug_comments'), 999);

        // 強制的にパーマリンクをフラッシュ（開発用）
        add_action('admin_init', array($this, 'force_flush_rules_if_needed'));

        // 階層URL修正後のリライトルールフラッシュ
        add_action('init', array($this, 'maybe_flush_rewrite_rules_for_hierarchy'), 999);

        // パーマリンク設定保存時の自動フラッシュ
        add_action('load-options-permalink.php', array($this, 'hook_permalink_save'));

        // デバッグ情報の表示（管理者のみ、GETパラメータで有効化）
        if (isset($_GET['kstb_debug']) && current_user_can('manage_options')) {
            add_action('wp_footer', array($this, 'show_debug_info'));
        }
        // 管理画面の投稿一覧で階層を表示
        add_action('admin_init', array($this, 'add_hierarchy_columns'));
    }

    /**
     * 親ページ選択用のメタボックスを追加
     */
    public function add_parent_selector_metabox() {
        $custom_post_types = KSTB_Database::get_all_post_types();

        foreach ($custom_post_types as $post_type) {
            if (post_type_exists($post_type->slug)) {
                add_meta_box(
                    'kstb_parent_selector',
                    '親ページ選択 & スラッグ編集',
                    array($this, 'render_parent_selector_metabox'),
                    $post_type->slug,
                    'side',
                    'high',
                    array(
                        '__block_editor_compatible_meta_box' => true,
                        '__back_compat_meta_box' => false,
                    )
                );
            }
        }
    }

    /**
     * 親ページ選択メタボックスのHTMLを出力
     */
    public function render_parent_selector_metabox($post) {
        // デバッグ用（開発時のみ表示、後で削除可能）
        error_log('KSTB: render_parent_selector_metabox called for post ID ' . $post->ID);

        // ナンス追加
        wp_nonce_field('kstb_parent_selector_nonce', 'kstb_parent_selector_nonce');

        // 現在の親ページIDを取得（標準階層システムを優先）
        $current_parent_id = 0;
        if ($post->post_parent > 0) {
            $current_parent_id = $post->post_parent;
        } else {
            $current_parent_id = get_post_meta($post->ID, '_kstb_parent_page', true);
        }

        // 選択可能な親ページを取得
        $available_parents = $this->get_available_parent_pages($post->ID);

        // 現在のスラッグを取得
        $current_slug = $post->post_name;

        // 新規投稿の場合は空
        if (empty($current_slug) && $post->post_status === 'auto-draft') {
            $current_slug = '';
        }

        ?>
        <!-- KSTB DEBUG: Metabox is rendering -->
        <div class="kstb-parent-selector">
            <!-- スラッグ編集フィールド -->
            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd;">
                <p style="margin-top: 0;">
                    <label for="kstb_post_slug"><strong>スラッグ（URL）:</strong></label>
                </p>
                <input type="text" name="kstb_post_slug" id="kstb_post_slug" value="<?php echo esc_attr($current_slug); ?>" style="width: 100%; padding: 6px 8px;" placeholder="スラッグを入力" data-original-slug="<?php echo esc_attr($current_slug); ?>" oninput="kstbUpdateSlug(this.value)" />
                <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                    URLに使用されるスラッグです。半角英数字とハイフンのみ使用できます。
                </p>
                <script>
                function kstbUpdateSlug(newSlug) {
                    // 既存パネルのスラッグテキストを更新
                    document.querySelectorAll('.editor-post-link__link-post-name').forEach(function(el) {
                        el.textContent = newSlug;
                    });

                    // 既存パネルのリンクURLを更新
                    document.querySelectorAll('.editor-post-link__link').forEach(function(link) {
                        if (link.href) {
                            var parts = link.href.split('/');
                            for (var i = parts.length - 1; i >= 0; i--) {
                                if (parts[i] && parts[i] !== '') {
                                    parts[i] = newSlug;
                                    break;
                                }
                            }
                            link.href = parts.join('/');
                        }
                    });

                    // ブロックエディタのストアも更新
                    if (typeof wp !== 'undefined' && wp.data) {
                        wp.data.dispatch('core/editor').editPost({ slug: newSlug });
                    }
                }
                </script>
            </div>

            <!-- 親ページ選択 -->
            <p>
                <label for="kstb_parent_page_select">親ページを選択:</label>
            </p>
            <select name="kstb_parent_page" id="kstb_parent_page_select" style="width: 100%;">
                <option value="">— 親ページなし —</option>
                <?php foreach ($available_parents as $group_label => $pages): ?>
                    <optgroup label="<?php echo esc_attr($group_label); ?>">
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page['ID']); ?>" <?php selected($current_parent_id, $page['ID']); ?>>
                                <?php echo esc_html($page['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>

            <?php if ($current_parent_id): ?>
                <?php $parent_post = get_post($current_parent_id); ?>
                <?php if ($parent_post): ?>
                    <div class="kstb-current-parent" style="margin-top: 10px; padding: 8px; background: #f0f0f1; border-radius: 4px;">
                        <strong>現在の親ページ:</strong><br>
                        <a href="<?php echo get_edit_post_link($parent_post->ID); ?>" target="_blank">
                            <?php echo esc_html($parent_post->post_title); ?>
                        </a>
                        <br>
                        <small>タイプ: <?php echo get_post_type_object($parent_post->post_type)->label; ?></small>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <p class="description" style="margin-top: 10px;">
                この投稿の親ページを固定ページ、投稿ページ、またはカスタム投稿ページから選択できます。
            </p>
        </div>
        <?php
    }

        /**
     * 選択可能な親ページを取得（軽量化版）
     */
    private function get_available_parent_pages($current_post_id = 0) {
        // キャッシュキーを生成
        $cache_key = "kstb_available_parents_{$current_post_id}";
        $cached = wp_cache_get($cache_key, 'kstb');

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $available_parents = array();

        // 固定ページを軽量クエリで取得
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'page'
             AND post_status = 'publish'
             AND ID != %d
             ORDER BY post_title ASC
             LIMIT 100",
            $current_post_id
        ));

        if (!empty($pages)) {
            $available_parents['固定ページ'] = array();
            foreach ($pages as $page) {
                $available_parents['固定ページ'][] = array(
                    'ID' => $page->ID,
                    'title' => $page->post_title
                );
            }
        }

        // 投稿ページを軽量クエリで取得（制限数を減らす）
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'post'
             AND post_status = 'publish'
             AND ID != %d
             ORDER BY post_date DESC
             LIMIT 20",
            $current_post_id
        ));

        if (!empty($posts)) {
            $available_parents['投稿ページ'] = array();
            foreach ($posts as $post) {
                $available_parents['投稿ページ'][] = array(
                    'ID' => $post->ID,
                    'title' => $post->post_title
                );
            }
        }

        // カスタム投稿タイプを軽量クエリで取得
        $custom_post_types = KSTB_Database::get_all_post_types();
        foreach ($custom_post_types as $cpt) {
            if (!post_type_exists($cpt->slug)) {
                continue;
            }

            $custom_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts}
                 WHERE post_type = %s
                 AND post_status = 'publish'
                 AND ID != %d
                 ORDER BY post_title ASC
                 LIMIT 20",
                $cpt->slug,
                $current_post_id
            ));

            if (!empty($custom_posts)) {
                $available_parents[$cpt->label] = array();
                foreach ($custom_posts as $custom_post) {
                    $available_parents[$cpt->label][] = array(
                        'ID' => $custom_post->ID,
                        'title' => $custom_post->post_title
                    );
                }
            }
        }

        // 5分間キャッシュ
        wp_cache_set($cache_key, $available_parents, 'kstb', 300);

        return $available_parents;
    }

    /**
     * 親ページ情報を保存（軽量化版）
     */
    public function save_parent_page_data($post_id) {
        // 無限ループ防止
        static $processing = array();
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;

        // ナンス確認
        if (!isset($_POST['kstb_parent_selector_nonce']) ||
            !wp_verify_nonce($_POST['kstb_parent_selector_nonce'], 'kstb_parent_selector_nonce')) {
            unset($processing[$post_id]);
            return;
        }

        // 自動保存の場合はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            unset($processing[$post_id]);
            return;
        }

        // 権限確認
        if (!current_user_can('edit_post', $post_id)) {
            unset($processing[$post_id]);
            return;
        }

        // カスタム投稿タイプかどうか確認（キャッシュ使用）
        $post_type = get_post_type($post_id);
        if (!$this->is_our_custom_post_type($post_type)) {
            unset($processing[$post_id]);
            return;
        }

        // スラッグを保存
        if (isset($_POST['kstb_post_slug']) && !empty($_POST['kstb_post_slug'])) {
            $new_slug = sanitize_title($_POST['kstb_post_slug']);
            if (!empty($new_slug)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->posts,
                    array('post_name' => $new_slug),
                    array('ID' => $post_id),
                    array('%s'),
                    array('%d')
                );
                clean_post_cache($post_id);
            }
        }

        // 親ページIDを保存
        $parent_page_id = isset($_POST['kstb_parent_page']) ? intval($_POST['kstb_parent_page']) : 0;

        if (empty($parent_page_id)) {
            // 親ページをクリア
            delete_post_meta($post_id, '_kstb_parent_page');
            $this->update_post_parent_directly($post_id, 0);
        } else {
            // 親ページの存在確認（軽量）
            if ($this->is_valid_parent($parent_page_id)) {
                // カスタムメタフィールドに保存
                update_post_meta($post_id, '_kstb_parent_page', $parent_page_id);

                // 同じ投稿タイプの場合のみ post_parent も設定
                $parent_post_type = get_post_type($parent_page_id);
                if ($parent_post_type === $post_type) {
                    $this->update_post_parent_directly($post_id, $parent_page_id);
                } else {
                    $this->update_post_parent_directly($post_id, 0);
                }
            }
        }

        // キャッシュをクリア（更新内容を反映）
        $this->clear_parent_cache();

        unset($processing[$post_id]);
    }

    /**
     * 親ページ選択のキャッシュをクリア
     */
    private function clear_parent_cache() {
        wp_cache_flush_group('kstb');
    }

    /**
     * デバッグ情報を表示（管理者のみ）
     */
    public function show_debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('/', trim($request_uri, '/'));

        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            echo '<!-- KSTB Debug Info -->';
            echo '<div style="position: fixed; top: 50px; right: 20px; background: #fff; border: 2px solid #f00; padding: 10px; z-index: 9999; font-size: 12px; max-width: 300px;">';
            echo '<h4>KSTB デバッグ情報</h4>';
            echo '<strong>URL解析:</strong><br>';
            echo "親スラッグ: {$parent_slug}<br>";
            echo "投稿タイプ: {$post_type_slug}<br>";
            echo "投稿スラッグ: {$post_slug}<br><br>";

            // 投稿タイプの確認
            $is_custom = $this->is_custom_post_type_slug($post_type_slug);
            echo '<strong>投稿タイプ確認:</strong><br>';
            echo $is_custom ? '✅ カスタム投稿タイプ' : '❌ 非対応';
            echo '<br><br>';

            if ($is_custom) {
                // 投稿の存在確認
                $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                echo '<strong>投稿確認:</strong><br>';
                if ($found_post) {
                    echo "✅ 投稿が見つかりました (ID: {$found_post->ID})<br>";

                    // 親ページの確認
                    $parent = self::get_parent_page($found_post->ID);
                    echo '<strong>親ページ確認:</strong><br>';
                    if ($parent) {
                        echo "✅ 親ページ: {$parent->post_title} (slug: {$parent->post_name})<br>";
                        if ($parent->post_name === $parent_slug) {
                            echo "✅ 親スラッグが一致<br>";
                        } else {
                            echo "❌ 親スラッグが不一致<br>";
                        }
                    } else {
                        echo "❌ 親ページが設定されていません<br>";
                    }
                } else {
                    echo "❌ 投稿が見つかりません<br>";
                }
            }

            // クエリ情報
            global $wp_query;
            echo '<strong>WordPressクエリ:</strong><br>';
            echo is_404() ? '❌ 404エラー' : '✅ 正常';
            echo '<br>';
            echo is_single() ? '✅ 単一投稿' : '❌ 非単一投稿';
            echo '<br>';

            echo '</div>';
        }
    }

    /**
     * カスタム投稿タイプかどうか判定（キャッシュ使用）
     */
    private function is_our_custom_post_type($post_type) {
        static $cache = null;

        if ($cache === null) {
            $cache = array();
            $custom_post_types = KSTB_Database::get_all_post_types();
            foreach ($custom_post_types as $cpt) {
                $cache[$cpt->slug] = true;
            }
        }

        return isset($cache[$post_type]);
    }

    /**
     * 親ページの有効性を確認（軽量）
     */
    private function is_valid_parent($parent_id) {
        global $wpdb;

        // 存在確認のみ（軽量クエリ）
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish' LIMIT 1",
            $parent_id
        ));

        return !empty($exists);
    }

    /**
     * post_parentを直接更新（wp_update_postを使わない軽量版）
     */
    private function update_post_parent_directly($post_id, $parent_id) {
        global $wpdb;

        // 直接データベースを更新（高速）
        $wpdb->update(
            $wpdb->posts,
            array('post_parent' => $parent_id),
            array('ID' => $post_id),
            array('%d'),
            array('%d')
        );

        // キャッシュをクリア
        clean_post_cache($post_id);
    }

    /**
     * 管理画面用のスタイルとスクリプトを読み込み
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        // 投稿編集画面以外では読み込まない
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // カスタム投稿タイプかどうか確認
        $custom_post_types = KSTB_Database::get_all_post_types();
        $is_custom_post_type = false;

        foreach ($custom_post_types as $cpt) {
            if ($cpt->slug === $post_type) {
                $is_custom_post_type = true;
                break;
            }
        }

        if (!$is_custom_post_type) {
            return;
        }

        // スタイルとスクリプトを直接出力
        add_action('admin_head', function() {
            ?>
            <style>
            .kstb-parent-selector select {
                max-width: 100%;
            }
            .kstb-current-parent {
                font-size: 12px;
                line-height: 1.4;
            }
            .kstb-current-parent a {
                text-decoration: none;
                font-weight: bold;
            }
            </style>
            <?php
        });

        // JavaScriptを安全に追加
        add_action('admin_footer', function() {
            ?>
            <script>
            (function() {
                'use strict';

                // クラシックエディタ用：スラッグ編集ボタンを強制的に表示
                function forceShowSlugEditButton() {
                    var editSlugButtons = document.getElementById('edit-slug-buttons');
                    if (editSlugButtons) {
                        editSlugButtons.style.display = 'inline';
                        editSlugButtons.style.visibility = 'visible';
                    }
                }

                // DOM読み込み完了後に実行（クラシックエディタ用）
                document.addEventListener('DOMContentLoaded', function() {
                    forceShowSlugEditButton();

                    var parentSelector = document.getElementById('parent_id');
                    if (parentSelector) {
                        parentSelector.addEventListener('change', function() {
                            setTimeout(forceShowSlugEditButton, 10);
                        });
                    }

                    var editSlugButtons = document.getElementById('edit-slug-buttons');
                    if (editSlugButtons) {
                        var observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.type === 'attributes' &&
                                    (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                                    forceShowSlugEditButton();
                                }
                            });
                        });

                        observer.observe(editSlugButtons, {
                            attributes: true,
                            attributeFilter: ['style', 'class']
                        });

                        setInterval(forceShowSlugEditButton, 500);
                    }
                });
            })();
            </script>
            <?php
        });

        // ブロックエディタ用スクリプトを登録
        add_action('enqueue_block_editor_assets', function() {
            // より強力なCSSで強制表示
            wp_add_inline_style('wp-edit-post', '
                /* スラッグパネル全体を強制表示 */
                .components-panel__body.is-opened .editor-post-link,
                .editor-post-link,
                .editor-post-link__link,
                .editor-post-link__link-post-name,
                .edit-post-post-link__link-post-name,
                .components-external-link,
                .editor-post-link .components-button,
                button.components-button[aria-label*="変更"],
                button.components-button[aria-label*="編集"] {
                    display: inline-flex !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                }

                /* スラッグ編集フォーム */
                .editor-post-slug,
                .editor-post-slug__input,
                .editor-post-slug input[type="text"] {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }

                /* パネル内のスラッグ行全体 */
                .components-panel__row:has(.editor-post-link),
                .edit-post-post-link {
                    display: flex !important;
                    visibility: visible !important;
                }
            ');

            // より強力なJavaScript制御
            $script = "
(function(wp) {
    if (!wp || !wp.data) return;

    var editorStore = 'core/editor';
    var editPostStore = 'core/edit-post';

    // スラッグフィールドを強制表示する関数（より広範囲）
    function forceShowSlugFields() {
        // すべての可能性のあるセレクタをチェック
        var selectors = [
            '.editor-post-link',
            '.editor-post-link__link',
            '.editor-post-link__link-post-name',
            '.edit-post-post-link__link-post-name',
            'button[aria-label*=\"変更\"]',
            'button[aria-label*=\"編集\"]',
            '.editor-post-slug',
            '.editor-post-slug__input'
        ];

        selectors.forEach(function(selector) {
            var elements = document.querySelectorAll(selector);
            elements.forEach(function(el) {
                if (el) {
                    el.style.display = el.tagName === 'BUTTON' || el.tagName === 'A' ? 'inline-flex' : 'block';
                    el.style.visibility = 'visible';
                    el.style.opacity = '1';

                    // disabled属性を削除
                    if (el.hasAttribute('disabled')) {
                        el.removeAttribute('disabled');
                    }
                    if (el.hasAttribute('readonly')) {
                        el.removeAttribute('readonly');
                    }
                }
            });
        });

        // スラッグパネル全体を表示
        var slugPanels = document.querySelectorAll('.components-panel__row');
        slugPanels.forEach(function(panel) {
            if (panel.textContent.includes('スラッグ') || panel.querySelector('.editor-post-link')) {
                panel.style.display = 'flex';
                panel.style.visibility = 'visible';
            }
        });
    }

    // DOMの準備完了を待つ
    wp.domReady(function() {
        // 即座に実行
        setTimeout(forceShowSlugFields, 100);

        // 頻繁にチェック（最初の10秒間）
        var intensiveChecks = 0;
        var intensiveInterval = setInterval(function() {
            forceShowSlugFields();
            intensiveChecks++;
            if (intensiveChecks > 20) {
                clearInterval(intensiveInterval);
            }
        }, 500);

        // その後は定期的にチェック
        setInterval(forceShowSlugFields, 2000);

        // MutationObserverでDOM変更を監視（より広範囲）
        var observer = new MutationObserver(function(mutations) {
            var shouldCheck = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    shouldCheck = true;
                }
            });
            if (shouldCheck) {
                forceShowSlugFields();
            }
        });

        // body全体を監視（より確実に）
        var targetNode = document.querySelector('.edit-post-layout') || document.body;
        observer.observe(targetNode, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class', 'hidden', 'disabled']
        });

        // サイドバーの開閉を監視
        if (wp.data.subscribe) {
            var previousIsOpen = null;
            wp.data.subscribe(function() {
                var isOpen = wp.data.select(editPostStore) &&
                             wp.data.select(editPostStore).isEditorSidebarOpened();

                if (isOpen !== previousIsOpen) {
                    previousIsOpen = isOpen;
                    setTimeout(forceShowSlugFields, 100);
                }
            });
        }

        // カスタムスラッグフィールドをリアルタイムでブロックエディタに同期
        var kstbInitSlugSync = setInterval(function() {
            if (typeof wp === 'undefined' || !wp.data) return;

            var slugField = document.getElementById('kstb_post_slug');
            if (!slugField) return;

            clearInterval(kstbInitSlugSync);

            // 入力時にストアを更新
            slugField.addEventListener('input', function() {
                var newSlug = slugField.value;
                if (newSlug) {
                    // ブロックエディタのストアを即座に更新
                    wp.data.dispatch('core/editor').editPost({ slug: newSlug });
                }
            });

            // changeイベントでも更新
            slugField.addEventListener('change', function() {
                var newSlug = slugField.value;
                if (newSlug) {
                    wp.data.dispatch('core/editor').editPost({ slug: newSlug });
                }
            });

            // 初回ロード時にも同期
            var currentSlug = slugField.value;
            if (currentSlug) {
                wp.data.dispatch('core/editor').editPost({ slug: currentSlug });
            }
        }, 200);
    });
})(window.wp);
            ";

            wp_add_inline_script('wp-edit-post', $script);
        });
    }

    /**
     * 指定した投稿の親ページを取得
     */
    public static function get_parent_page($post_id) {
        $current_post = get_post($post_id);
        if (!$current_post) {
            return null;
        }

        // まずWordPressの標準階層システム（post_parent）をチェック
        if ($current_post->post_parent > 0) {
            return get_post($current_post->post_parent);
        }

        // 標準階層システムに親がない場合、カスタムメタフィールドをチェック
        $parent_id = get_post_meta($post_id, '_kstb_parent_page', true);
        return $parent_id ? get_post($parent_id) : null;
    }

    /**
     * 指定した投稿の子ページを取得
     */
    public static function get_child_pages($post_id) {
        $children = array();

        // 標準的な階層システムから子ページを取得
        $wp_children = get_posts(array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'post_parent' => $post_id,
            'numberposts' => -1
        ));

        // カスタムメタフィールドから子ページを取得
        $custom_children = get_posts(array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_kstb_parent_page',
                    'value' => $post_id,
                    'compare' => '='
                )
            ),
            'numberposts' => -1
        ));

        // 重複を除きながら結合
        $all_children = array_merge($wp_children, $custom_children);
        $seen_ids = array();

        foreach ($all_children as $child) {
            if (!in_array($child->ID, $seen_ids)) {
                $children[] = $child;
                $seen_ids[] = $child->ID;
            }
        }

        return $children;
    }

    /**
     * パンくずリストを生成
     */
    public static function get_breadcrumb_trail($post_id) {
        $breadcrumbs = array();
        $current_post = get_post($post_id);

        if (!$current_post) {
            return $breadcrumbs;
        }

        // 現在の投稿を最初に追加
        $breadcrumbs[] = array(
            'title' => $current_post->post_title,
            'url' => get_permalink($current_post->ID),
            'id' => $current_post->ID
        );

        // 親をたどっていく
        $current_id = $post_id;
        $max_depth = 10; // 無限ループを防ぐ
        $depth = 0;

        while ($depth < $max_depth) {
            $parent = self::get_parent_page($current_id);

            if (!$parent) {
                break;
            }

            array_unshift($breadcrumbs, array(
                'title' => $parent->post_title,
                'url' => get_permalink($parent->ID),
                'id' => $parent->ID
            ));

            $current_id = $parent->ID;
            $depth++;
        }

        return $breadcrumbs;
    }

    /**
     * パンくずリストのHTMLを出力
     */
    public static function display_breadcrumb($post_id, $separator = ' > ', $wrapper_class = 'kstb-breadcrumb') {
        $breadcrumbs = self::get_breadcrumb_trail($post_id);

        if (empty($breadcrumbs)) {
            return '';
        }

        $html = '<nav class="' . esc_attr($wrapper_class) . '">';
        $trail = array();

        foreach ($breadcrumbs as $index => $crumb) {
            if ($index === count($breadcrumbs) - 1) {
                // 最後の要素（現在のページ）はリンクなし
                $trail[] = '<span class="current">' . esc_html($crumb['title']) . '</span>';
            } else {
                $trail[] = '<a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['title']) . '</a>';
            }
        }

        $html .= implode('<span class="separator">' . esc_html($separator) . '</span>', $trail);
        $html .= '</nav>';

        return $html;
    }

                        /**
     * 統合されたリダイレクト防止システム
     */
    public function prevent_all_redirects($redirect_url, $requested_url) {
        if (!$redirect_url) {
            return $redirect_url;
        }

        // 階層URLの場合、すべてのリダイレクトを防ぐ
        if ($this->is_hierarchy_url($requested_url)) {
            // error_log("KSTB: Prevented canonical redirect for hierarchy URL: {$requested_url}");
            return false;
        }

        return $redirect_url;
    }

        /**
     * wp_redirect フィルター用の統合リダイレクト防止
     */
    public function prevent_all_wp_redirects($location, $status) {
        // リダイレクトが301/302の場合のみ処理
        if (!in_array($status, [301, 302])) {
            return $location;
        }

        // 現在のリクエストが階層URLの場合、すべてのリダイレクトを防ぐ
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($this->is_hierarchy_url($request_uri)) {
            // error_log("KSTB: Prevented wp_redirect for hierarchy URL: {$request_uri} -> {$location}");
            return false;
        }

        return $location;
    }

            /**
     * 強化されたリライトルールを追加
     */
    public function add_enhanced_rewrite_rules() {
        // クエリ変数を追加
        add_filter('query_vars', array($this, 'add_query_vars'));

        $custom_post_types = KSTB_Database::get_all_post_types();

        foreach ($custom_post_types as $post_type) {
            if (post_type_exists($post_type->slug) && (bool) $post_type->hierarchical) {
                $post_type_obj = get_post_type_object($post_type->slug);

                if ($post_type_obj && $post_type_obj->rewrite) {
                    $slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post_type->slug;
                    $parent_dir = !empty($post_type->parent_directory) ? trim($post_type->parent_directory, '/') : '';

                    // アーカイブページが有効な場合はアーカイブルールを追加
                    if ((bool) $post_type->has_archive) {
                        if ($parent_dir) {
                            // パターン0: /{parent_dir}/{post_type_slug}/ (アーカイブページ with parent)
                            add_rewrite_rule(
                                '^' . preg_quote($parent_dir, '/') . '/' . preg_quote($slug, '/') . '/?$',
                                'index.php?post_type=' . $post_type->slug . '&kstb_archive=1',
                                'top'
                            );
                        } else {
                            // パターン0: /{post_type_slug}/ (アーカイブページ)
                            add_rewrite_rule(
                                '^' . preg_quote($slug, '/') . '/?$',
                                'index.php?post_type=' . $post_type->slug . '&kstb_archive=1',
                                'top'
                            );
                        }
                        
            // error_log("KSTB Enhanced: Added archive rule for {$post_type->slug} with slug '{$slug}'" . ($parent_dir ? " under parent '{$parent_dir}'" : ''));
                    }

                    // 複数パターンのリライトルールを追加
                    // パターン1: /{parent}/{post_type_slug}/{post_slug}/
                    add_rewrite_rule(
                        '^([^/]+)/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                        'index.php?post_type=' . $post_type->slug . '&name=$matches[2]&kstb_parent_slug=$matches[1]',
                        'top'
                    );

                    // パターン2: /{parent}/{post_type_slug}/{post_slug}/（トレイリングスラッシュなし）
                    add_rewrite_rule(
                        '^([^/]+)/' . preg_quote($slug, '/') . '/([^/]+)$',
                        'index.php?post_type=' . $post_type->slug . '&name=$matches[2]&kstb_parent_slug=$matches[1]',
                        'top'
                    );

            // error_log("KSTB Enhanced: Added rewrite rules for {$post_type->slug} with slug '{$slug}'");
                }
            }
        }
    }

    /**
     * 必要に応じてリライトルールを強制フラッシュ
     */
    public function force_flush_rules_if_needed() {
        // 開発中は毎回フラッシュ（本番では削除推奨）
        $last_flush = get_option('kstb_last_flush_time', 0);
        $current_time = time();

        // 1時間ごとにフラッシュ（開発用）
        if ($current_time - $last_flush > 3600) {
            flush_rewrite_rules(true);
            update_option('kstb_last_flush_time', $current_time);
            // error_log("KSTB: Force flushed rewrite rules at " . date('Y-m-d H:i:s'));
        }
    }

    /**
     * カスタムクエリ変数を追加
     */
    public function add_query_vars($vars) {
        $vars[] = 'kstb_parent_slug';
        $vars[] = 'kstb_archive';
        $vars[] = 'kstb_is_archive';
        return $vars;
    }









    /**
     * 全てのリダイレクトをインターセプト（旧版・バックアップ）
     */
    public function intercept_all_redirects($location, $status) {
        // カスタム階層URLの場合は全てのリダイレクトを無効化
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('/', trim($request_uri, '/'));

        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // 投稿と親ページの関係を確認
                $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                if ($found_post) {
                    $parent = self::get_parent_page($found_post->ID);
                    if ($parent && $parent->post_name === $parent_slug) {
                        // 正しい階層関係が確認できた場合、全てのリダイレクトを無効化
                        return false;
                    }
                }
            }
        }

        return $location;
    }

    /**
     * リクエスト処理を完全に制御
     */
    public function override_request_processing($query_vars) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('/', trim($request_uri, '/'));

        // /{parent_slug}/{post_type_slug}/{post_slug}/ のパターンを検出
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // 投稿を直接検索
                $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);

                if ($found_post) {
                    $parent = self::get_parent_page($found_post->ID);
                    if ($parent && $parent->post_name === $parent_slug) {
                        // 正しい階層関係が確認できた場合、直接的なクエリに変換
                        return array(
                            'post_type' => $post_type_slug,
                            'name' => $post_slug,
                            'kstb_hierarchy_processed' => true
                        );
                    }
                }
            }
        }

        return $query_vars;
    }

    /**
     * リクエストを強制的に正しいクエリに変換
     */
    public function force_correct_query($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('/', trim($request_uri, '/'));

        // /{parent_slug}/{post_type_slug}/{post_slug}/ のパターンを検出
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // WordPressのクエリ変数を直接設定
                $wp->query_vars = array(
                    'post_type' => $post_type_slug,
                    'name' => $post_slug
                );

                // マッチしたルールを設定
                $wp->matched_rule = '(.+)';
                $wp->matched_query = "post_type={$post_type_slug}&name={$post_slug}";
                $wp->request = "{$parent_slug}/{$post_type_slug}/{$post_slug}";
            }
        }
    }

    /**
     * テンプレートリダイレクトを制御
     */
    public function override_template_redirect() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('/', trim($request_uri, '/'));

        // カスタム階層URLの場合、404を強制的に解除
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);

                if ($found_post) {
                    $parent = self::get_parent_page($found_post->ID);
                    if ($parent && $parent->post_name === $parent_slug) {
                        // グローバルクエリを直接設定
                        global $wp_query, $post;

                        $wp_query->init();
                        $wp_query->is_404 = false;
                        $wp_query->is_single = true;
                        $wp_query->is_singular = true;
                        $wp_query->queried_object = $found_post;
                        $wp_query->queried_object_id = $found_post->ID;
                        $wp_query->posts = array($found_post);
                        $wp_query->post_count = 1;
                        $wp_query->found_posts = 1;
                        $wp_query->max_num_pages = 1;

                        // グローバル$postも設定
                        $post = $found_post;
                        setup_postdata($post);

                        status_header(200);
                    }
                }
            }
        }
    }

        /**
     * カスタムリライトルールを追加
     */
    public function add_custom_rewrite_rules() {
        // 階層化されたカスタム投稿タイプ用のリライトルールを追加
        $custom_post_types = KSTB_Database::get_all_post_types();

        foreach ($custom_post_types as $post_type) {
            if (post_type_exists($post_type->slug) && (bool) $post_type->hierarchical) {
                $post_type_obj = get_post_type_object($post_type->slug);

                if ($post_type_obj && $post_type_obj->rewrite) {
                    $slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post_type->slug;

                    // より汎用的なパターンで親ページがある場合のURL構造をサポート
                    // 例: /{parent_slug}/{post_type_slug}/{post_slug}/
                    add_rewrite_rule(
                        '([^/]+)/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                        'index.php?post_type=' . $post_type->slug . '&name=$matches[2]&kstb_parent_slug=$matches[1]',
                        'top'
                    );

                    // さらに深い階層にも対応
                    // 例: /{category_slug}/{parent_slug}/{post_type_slug}/{post_slug}/
                    add_rewrite_rule(
                        '([^/]+)/([^/]+)/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                        'index.php?post_type=' . $post_type->slug . '&name=$matches[3]&kstb_parent_path=$matches[1]/$matches[2]',
                        'top'
                    );
                }
            }
        }

        // リライトルールが追加されたことを記録
        $this->rewrite_rules_added = true;

        // パーマリンクを自動フラッシュ（設定変更時のみ）
        if (!get_option('kstb_rewrite_rules_flushed_v2')) {
            flush_rewrite_rules();
            update_option('kstb_rewrite_rules_flushed_v2', true);
        }
    }

        /**
     * WPリダイレクトを制御
     */
    public function prevent_wp_redirect($location, $status) {
        // 301リダイレクトの場合のみ制御
        if ($status === 301) {
            // 現在のリクエストURLをチェック
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $current_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
            $current_path = trim($current_path, '/');
            $path_parts = explode('/', $current_path);

            // /{parent_slug}/{post_type_slug}/{post_slug}/ のようなカスタム階層URLの場合
            if (count($path_parts) >= 3) {
                $parent_slug = $path_parts[0];
                $post_type_slug = $path_parts[1];
                $post_slug = $path_parts[2];

                if ($this->is_custom_post_type_slug($post_type_slug)) {
                    // 投稿と親ページの関係を確認
                    $post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                    if ($post) {
                        $parent = self::get_parent_page($post->ID);
                        if ($parent && $parent->post_name === $parent_slug) {
                            // 正しい階層関係が確認できた場合、リダイレクトを防ぐ
                            return false;
                        }
                    }
                }
            }

            global $wp_query;

            // カスタム投稿タイプの単一ページで親ページが設定されている場合
            if (is_single() && isset($wp_query->post)) {
                $post = $wp_query->post;
                if ($this->is_custom_post_type($post->post_type)) {
                    $parent = self::get_parent_page($post->ID);
                    if ($parent) {
                        // 親ページのスラッグがURLに含まれている場合、リダイレクトを防ぐ
                        if (strpos($request_uri, '/' . $parent->post_name . '/') !== false) {
                            return false;
                        }
                    }
                }
            }
        }

        return $location;
    }

    /**
     * カスタム階層URLの処理
     */
    public function handle_custom_hierarchy_urls() {
        // 404エラーの場合のみ処理
        if (!is_404()) {
            return;
        }

        global $wp;
        $request_path = trim($wp->request, '/');

        if (empty($request_path)) {
            return;
        }

        $path_parts = explode('/', $request_path);

        // 最低3つの部分が必要：親スラッグ/投稿タイプスラッグ/投稿スラッグ
        if (count($path_parts) < 3) {
            return;
        }

        $parent_slug = $path_parts[0];
        $post_type_slug = $path_parts[1];
        $post_slug = $path_parts[2];

        // カスタム投稿タイプが存在するかチェック
        if (!$this->is_custom_post_type_slug($post_type_slug)) {
            return;
        }

        // 投稿を検索
        $post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
        if (!$post) {
            return;
        }

        // 親ページをチェック
        $parent = self::get_parent_page($post->ID);
        if (!$parent || $parent->post_name !== $parent_slug) {
            return;
        }

        // 正しい投稿が見つかった場合、404を解除してコンテンツを表示
        global $wp_query;
        $wp_query->is_404 = false;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;
        $wp_query->posts = array($post);
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;

        status_header(200);
    }

    /**
     * リクエストを早い段階でインターセプト
     */
    public function intercept_custom_hierarchy_request($wp) {
        $request_path = trim($wp->request, '/');

        if (empty($request_path)) {
            return;
        }

        $path_parts = explode('/', $request_path);

        // /{parent_slug}/{post_type_slug}/{post_slug}/ のようなパターンをチェック
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            // カスタム投稿タイプが存在するかチェック
            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // この時点でWordPressに「これは有効なURLです」と伝える
                $wp->matched_rule = '([^/]+)/(' . $post_type_slug . ')/([^/]+)/?$';
                $wp->matched_query = 'parent_slug=' . $parent_slug . '&post_type=' . $post_type_slug . '&name=' . $post_slug;

                // クエリ変数を設定
                $wp->query_vars['parent_slug'] = $parent_slug;
                $wp->query_vars['post_type'] = $post_type_slug;
                $wp->query_vars['name'] = $post_slug;

                // リダイレクトを防ぐフラグを設定
                $wp->query_vars['kstb_custom_hierarchy'] = true;
            }
        }
    }

    /**
     * リクエストを階層URLに対応するよう修正
     */
    public function modify_request_for_hierarchy($query_vars) {
        // カスタムアーカイブページの処理
        if (isset($query_vars['kstb_archive']) && $query_vars['kstb_archive'] && isset($query_vars['post_type'])) {
            $post_type = $query_vars['post_type'];
            // error_log("KSTB: Processing archive for post type: {$post_type}");
            
            // アーカイブページとしてマーク
            $query_vars['kstb_is_archive'] = true;
            
            return $query_vars;
        }

        // カスタム階層URLの場合
        if (isset($query_vars['kstb_custom_hierarchy']) && $query_vars['kstb_custom_hierarchy']) {
            // 通常のクエリに変換
            if (isset($query_vars['parent_slug'], $query_vars['post_type'], $query_vars['name'])) {
                $parent_slug = $query_vars['parent_slug'];
                $post_type = $query_vars['post_type'];
                $post_name = $query_vars['name'];

                // 投稿を検索
                $post = get_page_by_path($post_name, OBJECT, $post_type);

                if ($post) {
                    // 親ページをチェック
                    $parent = self::get_parent_page($post->ID);

                    if ($parent && $parent->post_name === $parent_slug) {
                        // 正しい階層関係が確認できた場合、通常のクエリに変換
                        $query_vars = array(
                            'post_type' => $post_type,
                            'name' => $post_name,
                            'kstb_hierarchy_verified' => true
                        );
                    }
                }
            }
        }

        return $query_vars;
    }

    /**
     * カスタム投稿タイプのアーカイブ設定を取得
     */
    private function get_archive_settings($post_type_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kstb_post_types';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT archive_display_type, archive_page_id FROM {$table_name} WHERE slug = %s",
            $post_type_slug
        ));
        
        if ($result) {
            return array(
                'display_type' => $result->archive_display_type ?: 'post_list',
                'page_id' => $result->archive_page_id
            );
        }
        
        return array(
            'display_type' => 'post_list',
            'page_id' => null
        );
    }

    /**
     * 投稿の完全な階層パスを再帰的に構築
     */
    private function get_hierarchical_path($post_id) {
        $path_parts = array();
        $current_post_id = $post_id;
        
        // 無限ループ防止のため、最大10階層まで
        $max_depth = 10;
        $depth = 0;
        
        while ($current_post_id && $depth < $max_depth) {
            $current_post = get_post($current_post_id);
            if (!$current_post) {
                break;
            }
            
            // 現在の投稿のスラッグを配列の先頭に追加
            array_unshift($path_parts, $current_post->post_name);
            
            // 親を取得
            $parent = self::get_parent_page($current_post_id);
            if (!$parent) {
                break;
            }
            
            $current_post_id = $parent->ID;
            $depth++;
        }
        
        return implode('/', $path_parts);
    }

    /**
     * カスタム投稿タイプのパーマリンクで階層を反映（改善版）
     */
    public function custom_post_link($permalink, $post) {
        // カスタム投稿タイプ以外は処理しない
        if (!$this->is_custom_post_type($post->post_type)) {
            return $permalink;
        }

        // 投稿タイプの設定を取得
        $post_type_obj = get_post_type_object($post->post_type);
        if (!$post_type_obj || !$post_type_obj->rewrite) {
            return $permalink;
        }

        // データベースから投稿タイプ設定を取得してurl_slugを使用
        $post_type_data = KSTB_Database::get_post_type_by_slug($post->post_type);
        $url_slug = (!empty($post_type_data->url_slug)) ? $post_type_data->url_slug : $post->post_type;

        // 階層化が有効で親ページがある場合
        if ($this->is_hierarchical_post_type($post->post_type)) {
            $parent = self::get_parent_page($post->ID);
            if ($parent) {
                // リライトスラッグを取得（親ディレクトリが含まれている）
                $post_type_slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post->post_type;

                // 親ディレクトリを除いた投稿タイプのベーススラッグを取得
                $slug_parts = explode('/', $post_type_slug);
                $base_slug = end($slug_parts);

                // 親ディレクトリ部分を取得（あれば）
                $parent_dir = '';
                if (count($slug_parts) > 1) {
                    array_pop($slug_parts);
                    $parent_dir = implode('/', $slug_parts) . '/';
                }

                // 完全な階層パスを取得
                $hierarchical_path = $this->get_hierarchical_path($post->ID);

                // 階層URLを生成: /親ディレクトリ/url_slug/完全な階層パス/
                $hierarchical_url = home_url("/{$parent_dir}{$url_slug}/{$hierarchical_path}/");

                return $hierarchical_url;
            }
        }

        // 親ディレクトリが設定されている場合、url_slugを使ってパーマリンクを再構築
        if ($post_type_data && !empty($post_type_data->url_slug) && $post_type_data->url_slug !== $post->post_type) {
            $post_type_slug = isset($post_type_obj->rewrite['slug']) ? $post_type_obj->rewrite['slug'] : $post->post_type;

            // 親ディレクトリ部分を取得（あれば）
            $slug_parts = explode('/', $post_type_slug);
            $base_slug = end($slug_parts);

            $parent_dir = '';
            if (count($slug_parts) > 1) {
                array_pop($slug_parts);
                $parent_dir = implode('/', $slug_parts) . '/';
            }

            // url_slugを使ったURLを生成
            return home_url("/{$parent_dir}{$url_slug}/{$post->post_name}/");
        }

        // デフォルトのパーマリンクを返す
        return $permalink;
    }



    /**
     * 正規URLのメタタグを追加（レガシー互換性のため保持）
     */
    public function add_canonical_meta() {
        // この関数は override_canonical_url に置き換えられました
        // レガシー互換性のためにのみ保持
        return;
    }

    /**
     * HTTPレベルでのリダイレクト防止
     */
    public function prevent_http_redirects() {
        // すでにリダイレクトヘッダーが送信される予定の場合
        $headers = headers_list();
        foreach ($headers as $header) {
            if (strpos(strtolower($header), 'location:') === 0) {
                // 現在のリクエストがカスタム階層URLの場合、リダイレクトをキャンセル
                $request_uri = $_SERVER['REQUEST_URI'] ?? '';
                $path_parts = explode('/', trim($request_uri, '/'));

                if (count($path_parts) >= 3) {
                    $post_type_slug = $path_parts[1];
                    if ($this->is_custom_post_type_slug($post_type_slug)) {
                        // ヘッダーをクリアしてリダイレクトを防ぐ
                        header_remove('Location');
                        http_response_code(200);
                        break;
                    }
                }
            }
        }
    }

    /**
     * 親ディレクトリが設定されている投稿タイプの古いURLをブロック
     * また、親ディレクトリが削除された投稿タイプの古い親ディレクトリ付きURLもブロック
     */
    public function block_old_urls() {
        if (is_admin()) {
            return;
        }

        global $wp_query;

        // 現在のURLパスを取得
        $current_url = $_SERVER['REQUEST_URI'];
        $current_path = parse_url($current_url, PHP_URL_PATH) ?? '';
        $current_path = trim($current_path, '/');
        $path_parts = explode('/', $current_path);

        // カスタム投稿タイプのシングルまたはアーカイブページの場合
        if (is_singular() || is_post_type_archive()) {
            $post_type = get_post_type();
            if (!$post_type) {
                $post_type = get_query_var('post_type');
            }

            if ($post_type) {
                // データベースから投稿タイプの設定を取得
                $post_type_data = KSTB_Database::get_post_type_by_slug($post_type);

                if ($post_type_data) {
                    // url_slugが設定されていて、短縮名と異なる場合
                    if (!empty($post_type_data->url_slug) && $post_type_data->url_slug !== $post_type) {
                        // 短縮名でのアクセスをブロック（長いurl_slugのみ許可）
                        if ($path_parts[0] === $post_type) {
                            // 短縮名が使われている場合は404
                            $wp_query->set_404();
                            status_header(404);
                            nocache_headers();
                            include(get_404_template());
                            exit;
                        }
                    }

                    // 親ディレクトリが設定されている場合
                    if (!empty($post_type_data->parent_directory)) {
                        // フルパスを構築（再帰的に親を辿る）
                        $full_path = KSTB_Post_Type_Registrar::build_full_path_static($post_type);

                        // フルパスで始まっているかチェック
                        if (strpos($current_path, $full_path) !== 0) {
                            // 404を返す
                            $wp_query->set_404();
                            status_header(404);
                            nocache_headers();
                            include(get_404_template());
                            exit;
                        }
                    } else {
                        // 親ディレクトリが設定されていない場合
                        // URLに余計なディレクトリが含まれていないかチェック
                        // url_slugも考慮する
                        $url_slug = !empty($post_type_data->url_slug) ? $post_type_data->url_slug : $post_type;

                        if (count($path_parts) > 1 && $path_parts[0] !== $post_type && $path_parts[0] !== $url_slug) {
                            // 投稿タイプ名（短縮名またはurl_slug）の前に余計なパスがある場合は404
                            $wp_query->set_404();
                            status_header(404);
                            nocache_headers();
                            include(get_404_template());
                            exit;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 投稿タイプスラッグがカスタム投稿タイプかどうか判定
     */
    private function is_custom_post_type_slug($slug) {
        $custom_post_types = KSTB_Database::get_all_post_types();
        foreach ($custom_post_types as $cpt) {
            $post_type_obj = get_post_type_object($cpt->slug);
            if ($post_type_obj && isset($post_type_obj->rewrite['slug']) && $post_type_obj->rewrite['slug'] === $slug) {
                return true;
            }
            if ($cpt->slug === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * 管理画面の投稿一覧に階層情報を追加
     */
    public function add_hierarchy_columns() {
        $custom_post_types = KSTB_Database::get_all_post_types();

        foreach ($custom_post_types as $post_type) {
            if (post_type_exists($post_type->slug) && (bool) $post_type->hierarchical) {
                add_filter("manage_{$post_type->slug}_posts_columns", array($this, 'add_parent_column'));
                add_action("manage_{$post_type->slug}_posts_custom_column", array($this, 'show_parent_column'), 10, 2);
            }
        }
    }

    /**
     * 親ページ列を追加
     */
    public function add_parent_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['parent_page'] = '親ページ';
            }
        }
        return $new_columns;
    }

    /**
     * 親ページ列の内容を表示
     */
    public function show_parent_column($column, $post_id) {
        if ($column === 'parent_page') {
            $parent = self::get_parent_page($post_id);
            if ($parent) {
                $edit_link = get_edit_post_link($parent->ID);
                $post_type_obj = get_post_type_object($parent->post_type);
                echo '<a href="' . esc_url($edit_link) . '">' . esc_html($parent->post_title) . '</a>';
                echo '<br><small>(' . esc_html($post_type_obj->label) . ')</small>';
            } else {
                echo '—';
            }
        }
    }

    /**
     * 指定した投稿タイプがカスタム投稿タイプかどうか判定
     */
    private function is_custom_post_type($post_type) {
        $custom_post_types = KSTB_Database::get_all_post_types();
        foreach ($custom_post_types as $cpt) {
            if ($cpt->slug === $post_type) {
                return true;
            }
        }
        return false;
    }

    /**
     * 指定した投稿タイプが階層化されているかどうか判定
     */
    private function is_hierarchical_post_type($post_type) {
        $custom_post_types = KSTB_Database::get_all_post_types();
        foreach ($custom_post_types as $cpt) {
            if ($cpt->slug === $post_type) {
                return (bool) $cpt->hierarchical;
            }
        }
                return false;
    }

    /**
     * 階層URL修正後のリライトルールフラッシュ
     */
    public function maybe_flush_rewrite_rules_for_hierarchy() {
        // プラグインのバージョンが更新された場合、または階層URL修正がアクティベートされた場合
        $flush_flag = get_option('kstb_hierarchy_rules_flushed_v4', false);

        if (!$flush_flag) {
            flush_rewrite_rules();
            update_option('kstb_hierarchy_rules_flushed_v4', true);
            // error_log('KSTB: Flushed rewrite rules for unified hierarchy URL support');
        }
    }

    /**
     * 統合された階層リクエスト処理（最優先）
     */
    public function handle_hierarchy_request($query_vars) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
        $path_parts = explode('/', trim($request_path, '/'));

        // 階層URL構造をチェック: /{parent_slug}/{post_type_slug}/{post_slug}/
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            // カスタム投稿タイプかどうか確認
            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // 投稿が存在するか確認
                $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                if ($found_post) {
                    // 親ページ関係を確認
                    $parent = self::get_parent_page($found_post->ID);
                    if ($parent && $parent->post_name === $parent_slug) {
                        // WordPressに正しいクエリを教える
                        $query_vars = array(
                            'post_type' => $post_type_slug,
                            'name' => $post_slug,
                            'kstb_hierarchy_valid' => true,
                            'kstb_parent_slug' => $parent_slug
                        );

            // error_log("KSTB: Handled hierarchy request: {$parent_slug}/{$post_type_slug}/{$post_slug}");
                    }
                }
            }
        }

        return $query_vars;
    }

    /**
     * 階層URLかどうかを判定
     */
    /**
     * カスタム投稿タイプのフルパスを構築（KSTB_Post_Type_Registrarと同じロジック）
     */
    private function build_full_path($slug) {
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
            // 親のフルパスを再帰的に取得
            $parent_path = $this->build_full_path($parent);
            return $parent_path . '/' . $effective_slug;
        }

        // 親がカスタム投稿タイプでない場合（通常のディレクトリ）
        return $parent . '/' . $effective_slug;
    }

    /**
     * URLパスから該当するカスタム投稿タイプを特定する
     *
     * @param array $path_parts URLパスの配列
     * @return array|false カスタム投稿タイプ情報 ['post_type' => object, 'index' => int, 'parent_path' => string] または false
     */
    private function identify_custom_post_type_from_path($path_parts) {
        if (empty($path_parts)) {
            return false;
        }

        $custom_post_types = KSTB_Database::get_all_post_types();
        if (empty($custom_post_types)) {
            return false;
        }

        $path_string = implode('/', $path_parts);
        $best_match = false;
        $best_match_length = 0;

        // 各カスタム投稿タイプのフルパスを構築してマッチング（最も長いマッチを優先）
        foreach ($custom_post_types as $post_type) {
            $full_path = $this->build_full_path($post_type->slug);
            $full_path_parts = explode('/', $full_path);

            // URLパスの先頭部分と比較
            $match = true;
            for ($i = 0; $i < count($full_path_parts); $i++) {
                if (!isset($path_parts[$i]) || $path_parts[$i] !== $full_path_parts[$i]) {
                    $match = false;
                    break;
                }
            }

            if ($match && count($full_path_parts) > $best_match_length) {
                $best_match_length = count($full_path_parts);
                $best_match = [
                    'post_type' => $post_type,
                    'index' => count($full_path_parts) - 1,  // カスタム投稿タイプのスラッグ位置
                    'parent_path' => implode('/', array_slice($full_path_parts, 0, -1))
                ];
            }
        }

        return $best_match;
    }

    private function is_hierarchy_url($url) {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = trim($path, '/');
        $path_parts = explode('/', $path);

        // 階層URLを汎用的に処理
        if (count($path_parts) >= 1) {
            // URLパスから該当するカスタム投稿タイプを特定
            $result = $this->identify_custom_post_type_from_path($path_parts);

            if ($result) {
                $post_type = $result['post_type'];
                $post_type_index = $result['index'];

                // カスタム投稿タイプの後に続く部分を確認
                if (isset($path_parts[$post_type_index + 1])) {
                    // 個別投稿の可能性
                    $post_slug = $path_parts[$post_type_index + 1];
                    $found_post = get_page_by_path($post_slug, OBJECT, $post_type->slug);
                    if ($found_post) {
                        return true;
                    }
                } else {
                    // アーカイブページ
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 階層URL用のcanonical URLを設定（デバッグ情報付き）
     */
    public function set_hierarchy_canonical() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if ($this->is_hierarchy_url($request_uri)) {
            // WordPressのデフォルトcanonical処理を無効化
            remove_action('wp_head', 'rel_canonical');

            // 現在の階層URLをcanonical URLとして設定
            $canonical_url = home_url($request_uri);
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";

            // error_log("KSTB: Set canonical URL for hierarchy: {$canonical_url}");
        }

        // デバッグ情報をHTMLコメントとして出力（常に実行）
        $this->output_debug_comments();
    }

    /**
     * デバッグ情報をHTMLコメントとして出力
     */
    public function output_debug_comments() {
        // デバッグ情報の出力を無効化
        return;

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        echo "\n<!-- KSTB デバッグ情報 START -->\n";
        echo "<!-- リクエストURI: " . esc_html($request_uri) . " -->\n";
        echo "<!-- リクエストメソッド: " . esc_html($request_method) . " -->\n";
        echo "<!-- ユーザーエージェント: " . esc_html($user_agent) . " -->\n";
        echo "<!-- タイムスタンプ: " . date('Y-m-d H:i:s') . " -->\n";

        // 階層URL判定結果
        $is_hierarchy = $this->is_hierarchy_url($request_uri);
        echo "<!-- 階層URL判定: " . ($is_hierarchy ? 'YES' : 'NO') . " -->\n";

        if ($is_hierarchy) {
            $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
            $path_parts = explode('/', trim($request_path, '/'));
            if (count($path_parts) >= 3) {
                $parent_slug = $path_parts[0];
                $post_type_slug = $path_parts[1];
                $post_slug = $path_parts[2];

                echo "<!-- 親スラッグ: " . esc_html($parent_slug) . " -->\n";
                echo "<!-- 投稿タイプスラッグ: " . esc_html($post_type_slug) . " -->\n";
                echo "<!-- 投稿スラッグ: " . esc_html($post_slug) . " -->\n";

                // 投稿タイプ確認
                $is_custom_type = $this->is_custom_post_type_slug($post_type_slug);
                echo "<!-- カスタム投稿タイプ: " . ($is_custom_type ? 'YES' : 'NO') . " -->\n";

                if ($is_custom_type) {
                    // 投稿存在確認
                    $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                    echo "<!-- 投稿発見: " . ($found_post ? 'YES (ID: ' . $found_post->ID . ')' : 'NO') . " -->\n";

                    if ($found_post) {
                        echo "<!-- 投稿タイトル: " . esc_html($found_post->post_title) . " -->\n";

                        // 親ページ確認
                        $parent = self::get_parent_page($found_post->ID);
                        echo "<!-- 親ページ: " . ($parent ? 'YES (' . esc_html($parent->post_title) . ')' : 'NO') . " -->\n";

                        if ($parent) {
                            $parent_match = ($parent->post_name === $parent_slug);
                            echo "<!-- 親スラッグ一致: " . ($parent_match ? 'YES' : 'NO') . " -->\n";
                        }
                    }
                }
            }
        }

        // WordPressクエリ状態
        global $wp_query;
        echo "<!-- WordPress is_404: " . (is_404() ? 'YES' : 'NO') . " -->\n";
        echo "<!-- WordPress is_single: " . (is_single() ? 'YES' : 'NO') . " -->\n";
        echo "<!-- WordPress is_singular: " . (is_singular() ? 'YES' : 'NO') . " -->\n";

        // 階層モード確認（複数の方法でチェック）
        $query_var_mode = get_query_var('kstb_hierarchy_mode');
        $global_mode = isset($GLOBALS['kstb_hierarchy_mode']) ? $GLOBALS['kstb_hierarchy_mode'] : false;
        $hierarchy_mode = $query_var_mode || $global_mode;
        echo "<!-- KSTB階層モード: " . ($hierarchy_mode ? 'YES' : 'NO') . " -->\n";

        // 詳細なモード確認
        echo "<!-- クエリ変数モード: " . ($query_var_mode ? 'YES' : 'NO') . " -->\n";
        echo "<!-- グローバル変数モード: " . ($global_mode ? 'YES' : 'NO') . " -->\n";

        // カスタム投稿タイプ一覧
        $custom_post_types = KSTB_Database::get_all_post_types();
                echo "<!-- 登録済みカスタム投稿タイプ数: " . count($custom_post_types) . " -->\n";

        foreach ($custom_post_types as $cpt) {
            $post_type_obj = get_post_type_object($cpt->slug);
            if ($post_type_obj && isset($post_type_obj->rewrite['slug'])) {
                echo "<!-- CPT: " . esc_html($cpt->slug) . " -> " . esc_html($post_type_obj->rewrite['slug']) . " -->\n";
            } else {
                echo "<!-- CPT: " . esc_html($cpt->slug) . " (リライトなし) -->\n";
            }
        }

        // グローバル$postの状態をデバッグ
        global $post;
        echo "<!-- グローバル\$post存在: " . (isset($post) && $post ? 'YES (ID: ' . $post->ID . ')' : 'NO') . " -->\n";
        if (isset($post) && $post) {
            echo "<!-- グローバル\$post タイトル: " . esc_html($post->post_title) . " -->\n";
            echo "<!-- グローバル\$post タイプ: " . esc_html($post->post_type) . " -->\n";
            echo "<!-- グローバル\$post ステータス: " . esc_html($post->post_status) . " -->\n";
            echo "<!-- グローバル\$post コンテンツ長: " . strlen($post->post_content) . " 文字 -->\n";
        }

        // WP_Queryの詳細状態
        global $wp_query;
        echo "<!-- WP_Query->posts数: " . count($wp_query->posts) . " -->\n";
        echo "<!-- WP_Query->post_count: " . $wp_query->post_count . " -->\n";
        echo "<!-- WP_Query->found_posts: " . $wp_query->found_posts . " -->\n";
        echo "<!-- WP_Query->current_post: " . $wp_query->current_post . " -->\n";

        if (!empty($wp_query->posts)) {
            $query_post = $wp_query->posts[0];
            echo "<!-- WP_Query->posts[0] ID: " . $query_post->ID . " -->\n";
            echo "<!-- WP_Query->posts[0] タイトル: " . esc_html($query_post->post_title) . " -->\n";
        }

        // リダイレクト試行情報
        if (isset($GLOBALS['kstb_redirect_attempts'])) {
            $redirect_attempts = $GLOBALS['kstb_redirect_attempts'];
            echo "<!-- リダイレクト試行回数: " . count($redirect_attempts) . " -->\n";

            foreach ($redirect_attempts as $index => $attempt) {
                echo "<!-- リダイレクト " . ($index + 1) . ": " .
                     esc_html($attempt['from']) . " -> " . esc_html($attempt['to']) .
                     " (Status: " . esc_html($attempt['status']) . ", " .
                     "Blocked: " . ($attempt['blocked'] ? 'YES' : 'NO');

                if ($attempt['blocked'] && isset($attempt['reason'])) {
                    echo ", Reason: " . esc_html($attempt['reason']);
                }

                echo ", Time: " . esc_html($attempt['timestamp']) . ") -->\n";
            }
        } else {
            echo "<!-- リダイレクト試行回数: 0 -->\n";
        }

        echo "<!-- KSTB デバッグ情報 END -->\n\n";
    }

            /**
     * WordPressのリクエスト解析を完全にインターセプト（強化版）
     */
    public function intercept_wordpress_parsing($do_parse, $wp, $extra_query_vars) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
        $request_path = trim($request_path, '/');

        // デバッグログは無効化
        // error_log("KSTB ALWAYS: intercept_wordpress_parsing called - URI: {$request_uri}");

        // HTMLコメント出力は無効化
        // if (!isset($GLOBALS['kstb_debug_called'])) {
        //     $GLOBALS['kstb_debug_called'] = true;
        //     add_action('wp_head', function() use ($request_uri) {
        //         echo "<!-- KSTB FUNCTION CALLED: intercept_wordpress_parsing with URI: {$request_uri} -->\n";
        //     }, 1);
        // }

        $path_parts = explode('/', $request_path);

        // HTMLコメント詳細デバッグは無効化
        // if (!isset($GLOBALS['kstb_detailed_debug'])) {
        //     $GLOBALS['kstb_detailed_debug'] = true;
        //     add_action('wp_head', function() use ($request_path, $path_parts) {
        //         echo "<!-- KSTB DETAILED: request_path = '{$request_path}' -->\n";
        //         echo "<!-- KSTB DETAILED: path_parts count = " . count($path_parts) . " -->\n";
        //         echo "<!-- KSTB DETAILED: path_parts = [" . implode(', ', $path_parts) . "] -->\n";
        //     }, 2);
        // }

        // 階層URL構造をチェック: /{parent_slug}/{post_type_slug}/{post_slug}/ または /{parent_slug}/{post_type_slug}/
        if (count($path_parts) >= 2) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = isset($path_parts[2]) ? $path_parts[2] : null; // アーカイブページの場合はnull

            // デバッグログは無効化
            // if ($post_slug) {
            //     error_log("KSTB DEBUG: Checking individual post - parent:{$parent_slug}, type:{$post_type_slug}, post:{$post_slug}");
            // } else {
            //     error_log("KSTB DEBUG: Checking archive page - parent:{$parent_slug}, type:{$post_type_slug}");
            // }

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                // error_log("KSTB DEBUG: Found custom post type slug: {$post_type_slug}");

                if ($post_slug) {
                    // 個別投稿の処理
                    $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                if ($found_post) {
                    // error_log("KSTB DEBUG: Found post: {$found_post->post_title} (ID: {$found_post->ID})");

                    $parent = self::get_parent_page($found_post->ID);
                    if ($parent && $parent->post_name === $parent_slug) {
                        // error_log("KSTB DEBUG: Parent validation successful - {$parent->post_title}");

                        // WordPressのクエリを階層URL用に書き換え
                        $wp->query_vars = array(
                            'post_type' => $post_type_slug,
                            'p' => $found_post->ID,
                            'kstb_hierarchy_mode' => true,
                            'kstb_parent_slug' => $parent_slug
                        );

                        // さらに強力なリダイレクト防止
                        add_filter('redirect_canonical', '__return_false', 999);
                        add_filter('wp_redirect', '__return_false', 999);

                        // HTMLコメント出力は無効化
                        // add_action('wp_head', function() use ($found_post) {
                        //     echo "<!-- KSTB SUCCESS: Query modified for post {$found_post->ID} - {$found_post->post_title} -->\n";
                        // }, 3);

                        // WordPressに処理を継続させる（クエリを置き換えて）
                        // error_log("KSTB SUCCESS: Modified WordPress query for hierarchy URL - CONTINUING WITH WP");
                        return $do_parse; // WordPressに処理を継続させる
                    } else {
                        // error_log("KSTB DEBUG: Parent validation failed");
                        // add_action('wp_head', function() use ($parent, $parent_slug) {
                        //     $parent_name = $parent ? $parent->post_name : 'NULL';
                        //     echo "<!-- KSTB DEBUG: Parent validation failed - parent name: '{$parent_name}' vs expected: '{$parent_slug}' -->\n";
                        // }, 3);
                    }
                } else {
                    // error_log("KSTB DEBUG: Post not found for slug: {$post_slug}");
                    // add_action('wp_head', function() use ($post_slug, $post_type_slug) {
                    //     echo "<!-- KSTB DEBUG: Post not found for slug: '{$post_slug}' in post type: '{$post_type_slug}' -->\n";
                    // }, 3);
                }
                } else {
                    // アーカイブページの処理
                    // error_log("KSTB DEBUG: Processing archive page for post type: {$post_type_slug}");

                    // 親ページが存在するかチェック
                    $parent_page = get_page_by_path($parent_slug);
                    if ($parent_page) {
                        // error_log("KSTB DEBUG: Found parent page: {$parent_page->post_title}");

                        // WordPressのクエリをアーカイブページ用に書き換え
                        $wp->query_vars = array(
                            'post_type' => $post_type_slug,
                            'kstb_hierarchy_mode' => true,
                            'kstb_parent_slug' => $parent_slug,
                            'kstb_archive_mode' => true
                        );

                        // リダイレクト防止
                        add_filter('redirect_canonical', '__return_false', 999);
                        add_filter('wp_redirect', '__return_false', 999);

                        // シンプルなアーカイブモード設定
                        $GLOBALS['kstb_archive_post_type_slug'] = $post_type_slug;

                        // HTMLコメント出力は無効化
                        // add_action('wp_head', function() use ($post_type_slug, $parent_slug) {
                        //     echo "<!-- KSTB SUCCESS: Archive page set for {$post_type_slug} under {$parent_slug} -->\n";
                        // }, 3);

                        // error_log("KSTB SUCCESS: Modified WordPress query for archive page - CONTINUING WITH WP");
                        return $do_parse; // WordPressに処理を継続させる
                    } else {
                        // error_log("KSTB DEBUG: Parent page not found for slug: {$parent_slug}");
                        // add_action('wp_head', function() use ($parent_slug) {
                        //     echo "<!-- KSTB DEBUG: Parent page not found for slug: '{$parent_slug}' -->\n";
                        // }, 3);
                    }
                }
            } else {
                // error_log("KSTB DEBUG: Not a custom post type slug: {$post_type_slug}");
                // add_action('wp_head', function() use ($post_type_slug) {
                //     echo "<!-- KSTB DEBUG: Not a custom post type slug: '{$post_type_slug}' -->\n";
                // }, 3);
            }
        } else {
            // add_action('wp_head', function() use ($path_parts) {
            //     echo "<!-- KSTB DEBUG: Not enough path parts: " . count($path_parts) . " -->\n";
            // }, 3);
            // error_log("KSTB DEBUG: Not enough path parts (" . count($path_parts) . ")");
        }

        // error_log("KSTB DEBUG: Allowing WordPress default parsing");
        return $do_parse; // 通常のリクエストはWordPressに処理させる
    }

    /**
     * 階層URL用のカスタムクエリを強制設定
     */
    private function setup_hierarchy_query($wp, $post, $parent_slug) {
        global $wp_query;

        // WordPressに「これは有効な投稿です」と強制的に教える
        $wp->query_vars = array(
            'post_type' => $post->post_type,
            'p' => $post->ID,
            'kstb_hierarchy_mode' => true,
            'kstb_parent_slug' => $parent_slug
        );

        $wp->matched_rule = 'kstb_hierarchy_custom';
        $wp->matched_query = 'p=' . $post->ID;

        // グローバルクエリを即座に設定
        $wp_query->is_404 = false;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;
        $wp_query->posts = array($post);
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;

        // 階層モードフラグをクエリ変数とグローバル変数の両方に設定
        $wp_query->query_vars['kstb_hierarchy_mode'] = true;
        $wp_query->query_vars['kstb_parent_slug'] = $parent_slug;
        $GLOBALS['kstb_hierarchy_mode'] = true;
        $GLOBALS['kstb_parent_slug'] = $parent_slug;

        // グローバル$postも設定
        global $post_global;
        $post_global = $post;

            // error_log("KSTB: Setup hierarchy query for post ID {$post->ID}");
    }

    /**
     * カスタム階層解析処理（強化版）
     */
    public function handle_custom_hierarchy_parsing($wp) {
        if (isset($wp->query_vars['kstb_hierarchy_mode'])) {
            // 階層モードの場合、メインクエリを完全に設定
            global $wp_query, $post;

            $post_id = $wp->query_vars['p'];
            $post = get_post($post_id);

            if ($post) {
                // WP_Queryを完全に再設定
                $wp_query->init();
                $wp_query->is_404 = false;
                $wp_query->is_single = true;
                $wp_query->is_singular = true;
                $wp_query->queried_object = $post;
                $wp_query->queried_object_id = $post->ID;
                $wp_query->posts = array($post);
                $wp_query->post_count = 1;
                $wp_query->found_posts = 1;
                $wp_query->max_num_pages = 1;
                $wp_query->current_post = 0;

                // グローバル変数も設定
                $GLOBALS['kstb_hierarchy_mode'] = true;
                $GLOBALS['kstb_parent_slug'] = $wp->query_vars['kstb_parent_slug'];

                setup_postdata($post);
            // error_log("KSTB: Enhanced hierarchy parsing completed for: " . $post->post_title);
            }
        }
    }

            /**
     * すべてのリダイレクトを完全にブロック（超強化版）
     */
    public function block_all_hierarchy_redirects($location, $status) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // リダイレクト情報をHTMLコメント用に保存
        if (!isset($GLOBALS['kstb_redirect_attempts'])) {
            $GLOBALS['kstb_redirect_attempts'] = array();
        }

        $redirect_info = array(
            'from' => $request_uri,
            'to' => $location,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'blocked' => false
        );

        // デバッグログは無効化
        // error_log("KSTB DEBUG: block_all_hierarchy_redirects called");
        // error_log("KSTB DEBUG: Redirect attempt - From: {$request_uri} To: {$location} Status: {$status}");

        if (get_query_var('kstb_hierarchy_mode')) {
            $redirect_info['blocked'] = true;
            $redirect_info['reason'] = 'hierarchy_mode';
            $GLOBALS['kstb_redirect_attempts'][] = $redirect_info;
            // error_log("KSTB BLOCK: BLOCKED redirect in hierarchy mode: {$location} (status: {$status})");
            return false; // リダイレクトを完全に阻止
        }

        // 階層URLパターンの場合もブロック
        if ($this->is_hierarchy_url($request_uri)) {
            $redirect_info['blocked'] = true;
            $redirect_info['reason'] = 'hierarchy_url_pattern';
            $GLOBALS['kstb_redirect_attempts'][] = $redirect_info;
            // error_log("KSTB BLOCK: BLOCKED redirect for hierarchy URL: {$request_uri} -> {$location}");
            return false;
        }

        // /{POST_TYPE_SLUG}/{POST_SLUG}/ から /{PREFIX}/{POST_TYPE_SLUG}/{POST_SLUG}/ へのリダイレクトもブロック
        $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
        $path_parts = explode('/', trim($request_path, '/'));
        if (count($path_parts) >= 3) {
            $parent_slug = $path_parts[0];
            $post_type_slug = $path_parts[1];
            $post_slug = $path_parts[2];

            if ($this->is_custom_post_type_slug($post_type_slug)) {
                $redirect_info['blocked'] = true;
                $redirect_info['reason'] = 'custom_post_type_pattern';
                $GLOBALS['kstb_redirect_attempts'][] = $redirect_info;
                // error_log("KSTB BLOCK: BLOCKED redirect for potential hierarchy URL pattern");
                return false;
            }
        }

        $GLOBALS['kstb_redirect_attempts'][] = $redirect_info;
        // error_log("KSTB DEBUG: Allowing redirect: {$request_uri} -> {$location}");
        return $location;
    }

    /**
     * 最終的なリダイレクト防御ライン
     */
    public function final_redirect_defense() {
        global $wp_query;
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // アーカイブページの処理を優先
        if (get_query_var('kstb_is_archive') && get_query_var('post_type')) {
            $post_type_slug = get_query_var('post_type');
            // error_log("KSTB FINAL DEFENSE: Processing archive page for {$post_type_slug}");
            
            // アーカイブ設定を取得
            $archive_settings = $this->get_archive_settings($post_type_slug);
            
            // メインクエリをアーカイブページ用に修正
            $wp_query->is_404 = false;
            $wp_query->is_single = false;
            $wp_query->is_singular = false;
            $wp_query->is_home = false;

            if ($archive_settings['display_type'] === 'custom_page' && $archive_settings['page_id']) {
                // 固定ページを表示する場合
                $custom_page = get_post($archive_settings['page_id']);
                if ($custom_page && $custom_page->post_status === 'publish') {
                    $wp_query->is_archive = false;
                    $wp_query->is_page = true;
                    $wp_query->is_singular = true;
                    
                    $wp_query->queried_object = $custom_page;
                    $wp_query->queried_object_id = $custom_page->ID;
                    
                    $wp_query->posts = array($custom_page);
                    $wp_query->post = $custom_page;
                    $wp_query->post_count = 1;
                    $wp_query->found_posts = 1;
                    
            // error_log("KSTB FINAL DEFENSE: Displaying custom page (ID: {$custom_page->ID}) for archive {$post_type_slug}");
                } else {
                    // 固定ページが見つからない場合は通常の投稿一覧にフォールバック
                    $archive_settings['display_type'] = 'post_list';
                }
            }
            
            if ($archive_settings['display_type'] === 'post_list') {
                // 投稿一覧を表示する場合
                $wp_query->is_archive = true;
                $wp_query->is_post_type_archive = true;

                // カスタム投稿タイプを設定
                $wp_query->set('post_type', $post_type_slug);

                // カスタム投稿タイプオブジェクトを設定
                $post_type_obj = get_post_type_object($post_type_slug);
                if ($post_type_obj) {
                    $wp_query->queried_object = $post_type_obj;
                    $wp_query->queried_object_id = 0;
                }

                // 現在のページ番号を取得
                $paged = get_query_var('paged') ? get_query_var('paged') : 1;
                $posts_per_page = get_option('posts_per_page', 10);

                // WP_Queryを使用してページネーション対応のクエリを実行
                $archive_query = new WP_Query(array(
                    'post_type' => $post_type_slug,
                    'posts_per_page' => $posts_per_page,
                    'paged' => $paged,
                    'post_status' => 'publish'
                ));

                // WP_Queryの結果を$wp_queryに反映
                $wp_query->posts = $archive_query->posts;
                $wp_query->post_count = $archive_query->post_count;
                $wp_query->found_posts = $archive_query->found_posts;
                $wp_query->max_num_pages = $archive_query->max_num_pages;
                $wp_query->current_post = -1;
                $wp_query->set('paged', $paged);
                
            // error_log("KSTB FINAL DEFENSE: Displaying post list for archive {$post_type_slug}");
            }

            // WordPressのループ関数が正しく動作するよう設定
            $wp_query->rewind_posts();
            status_header(200);
            
            return;
        }

        if ($this->is_hierarchy_url($request_uri)) {
            // error_log("KSTB FINAL DEFENSE: Processing hierarchy URL: {$request_uri}");

            // 階層URLの場合は常にクエリを修正
            global $wp_query, $post;
            $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
            $path_parts = explode('/', trim($request_path, '/'));

            // URLパスから該当するカスタム投稿タイプを特定
            $result = $this->identify_custom_post_type_from_path($path_parts);

            if ($result) {
                $post_type_data = $result['post_type'];
                $post_type_slug = $post_type_data->slug;
                $post_type_index = $result['index'];
                $parent_path = $result['parent_path'];

                // parent_pathから最後のスラッグを取得（複数階層の場合に対応）
                $parent_slug = !empty($parent_path) ? basename($parent_path) : '';

                // カスタム投稿タイプの後に続く部分を確認
                $post_slug = isset($path_parts[$post_type_index + 1]) ? $path_parts[$post_type_index + 1] : null;
                    if ($post_slug) {
                        // 個別投稿の処理
                        $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                        if ($found_post) {
                            // URLパスとカスタム投稿タイプの設定が一致していれば有効
                            // （すでにidentify_custom_post_type_from_pathで検証済み）
                            // メインクエリを強制修正
                            $wp_query->is_404 = false;
                            $wp_query->is_single = true;
                            $wp_query->is_singular = true;
                            $wp_query->is_home = false;
                            $wp_query->is_archive = false;
                            $wp_query->queried_object = $found_post;
                            $wp_query->queried_object_id = $found_post->ID;
                            $wp_query->posts = array($found_post);
                            $wp_query->post_count = 1;
                            $wp_query->found_posts = 1;
                            $wp_query->max_num_pages = 1;
                            $wp_query->current_post = -1;

                            // WordPressのループ状態をリセット
                            $wp_query->in_the_loop = false;

                            // グローバル $post を確実に設定
                            $post = $found_post;
                            $GLOBALS['post'] = $found_post;
                            $GLOBALS['kstb_hierarchy_mode'] = true;

                            // 投稿データをセットアップ
                            setup_postdata($post);

                            // WordPressのループ関数が正しく動作するよう設定
                            $wp_query->rewind_posts();

                            status_header(200);
            // error_log("KSTB FINAL DEFENSE: Successfully fixed main query for hierarchy URL");

                            // HTMLコメントで成功を通知
                            add_action('wp_head', function() use ($found_post) {
                                echo "<!-- KSTB TEMPLATE SUCCESS: Main query fixed for post {$found_post->ID} -->\n";
                            }, 10);
                        }
                    } else {
                        // アーカイブページの処理
            // error_log("KSTB FINAL DEFENSE: Processing archive page for {$post_type_slug}");

                        // アーカイブ設定を取得
                        $archive_settings = $this->get_archive_settings($post_type_slug);

                        // メインクエリをアーカイブページ用に修正
                        $wp_query->is_404 = false;
                        $wp_query->is_single = false;
                        $wp_query->is_singular = false;
                        $wp_query->is_home = false;

                        if ($archive_settings['display_type'] === 'custom_page' && $archive_settings['page_id']) {
                            // 固定ページを表示する場合
                            $custom_page = get_post($archive_settings['page_id']);
                            if ($custom_page && $custom_page->post_status === 'publish') {
                                $wp_query->is_archive = false;
                                $wp_query->is_page = true;
                                $wp_query->is_singular = true;
                                
                                $wp_query->queried_object = $custom_page;
                                $wp_query->queried_object_id = $custom_page->ID;
                                
                                $wp_query->posts = array($custom_page);
                                $wp_query->post = $custom_page;
                                $wp_query->post_count = 1;
                                $wp_query->found_posts = 1;
                                
            // error_log("KSTB FINAL DEFENSE: Displaying custom page (ID: {$custom_page->ID}) for archive {$post_type_slug}");
                            } else {
                                // 固定ページが見つからない場合は通常の投稿一覧にフォールバック
                                $archive_settings['display_type'] = 'post_list';
                            }
                        }
                        
                        if ($archive_settings['display_type'] === 'post_list') {
                            // 投稿一覧を表示する場合
                            $wp_query->is_archive = true;
                            $wp_query->is_post_type_archive = true;

                            // カスタム投稿タイプを設定
                            $wp_query->set('post_type', $post_type_slug);

                            // カスタム投稿タイプオブジェクトを設定
                            $post_type_obj = get_post_type_object($post_type_slug);
                            if ($post_type_obj) {
                                $wp_query->queried_object = $post_type_obj;
                                $wp_query->queried_object_id = 0;
                            }

                            // 現在のページ番号を取得
                            $paged = get_query_var('paged') ? get_query_var('paged') : 1;
                            $posts_per_page = get_option('posts_per_page', 10);

                            // WP_Queryを使用してページネーション対応のクエリを実行
                            $archive_query = new WP_Query(array(
                                'post_type' => $post_type_slug,
                                'posts_per_page' => $posts_per_page,
                                'paged' => $paged,
                                'post_status' => 'publish'
                            ));

                            // WP_Queryの結果を$wp_queryに反映
                            $wp_query->posts = $archive_query->posts;
                            $wp_query->post_count = $archive_query->post_count;
                            $wp_query->found_posts = $archive_query->found_posts;
                            $wp_query->max_num_pages = $archive_query->max_num_pages;
                            $wp_query->current_post = -1;
                            $wp_query->set('paged', $paged);
                            
            // error_log("KSTB FINAL DEFENSE: Displaying post list for archive {$post_type_slug}");
                        }

                        // 共通のグローバル変数設定
                        $GLOBALS['kstb_hierarchy_mode'] = true;
                        $GLOBALS['kstb_archive_mode'] = true;
                        $GLOBALS['kstb_parent_slug'] = $parent_slug;

                        // WordPressのループ関数が正しく動作するよう設定
                        $wp_query->rewind_posts();

                        status_header(200);
            // error_log("KSTB FINAL DEFENSE: Successfully processed archive for {$post_type_slug}");

                        // HTMLコメントで成功を通知 + テンプレート情報
                        add_action('wp_head', function() use ($post_type_slug, $parent_slug) {
                            echo "<!-- KSTB TEMPLATE SUCCESS: Archive page fixed for {$post_type_slug} under {$parent_slug} -->\n";
                        }, 10);

                        // 使用テンプレートをデバッグ出力
                        add_action('template_include', function($template) use ($post_type_slug) {
                            $template_name = basename($template);
            // error_log("KSTB TEMPLATE DEBUG: Using template '{$template_name}' for archive {$post_type_slug}");
                            add_action('wp_head', function() use ($template_name, $post_type_slug) {
                                echo "<!-- KSTB TEMPLATE USED: {$template_name} for {$post_type_slug} archive -->\n";
                            }, 5);
                            return $template;
                        }, 999);
                    }
                }
            }
        }

    /**
     * 最も早い段階でのリダイレクト防止
     */
    public function early_redirect_prevention() {
        // 全てのリダイレクト機能を無効化
        add_filter('redirect_canonical', '__return_false', 1);
        add_filter('wp_redirect', '__return_false', 1);

            // error_log("KSTB EARLY: Early redirect prevention activated");
    }

    /**
     * プラグイン読み込み後の超早期フック
     */
    public function super_early_hooks() {
        // 最も強力なリダイレクト防止
        add_filter('wp_redirect', array($this, 'absolute_redirect_blocker'), 1, 2);
        add_filter('redirect_canonical', array($this, 'absolute_canonical_blocker'), 1, 2);

            // error_log("KSTB SUPER EARLY: Super early hooks activated");
    }

    /**
     * 絶対的なリダイレクトブロッカー
     */
    public function absolute_redirect_blocker($location, $status) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // 階層URLの場合は絶対にリダイレクトしない
        if ($this->is_hierarchy_url($request_uri)) {
            // error_log("KSTB ABSOLUTE BLOCK: Blocked redirect from {$request_uri} to {$location}");
            return false;
        }

        return $location;
    }

    /**
     * 絶対的なcanonicalブロッカー
     */
    public function absolute_canonical_blocker($redirect_url, $requested_url) {
        if ($this->is_hierarchy_url($requested_url)) {
            // error_log("KSTB ABSOLUTE BLOCK: Blocked canonical redirect for {$requested_url}");
            return false;
        }

        return $redirect_url;
    }

    /**
     * テーマレベルでのフック
     */
    public function theme_level_hooks() {
        // テーマの関数より早く実行
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if ($this->is_hierarchy_url($request_uri)) {
            // ヘッダーレベルでリダイレクトを防ぐ
            add_action('send_headers', array($this, 'prevent_header_redirects'), 1);
            // error_log("KSTB THEME: Theme level hooks activated for hierarchy URL");
        }
    }

    /**
     * HTTPヘッダーレベルでリダイレクトを防ぐ
     */
    public function prevent_header_redirects() {
        // Location ヘッダーを削除
        if (function_exists('header_remove')) {
            header_remove('Location');
        }

        // ステータスを200に強制
        http_response_code(200);

            // error_log("KSTB HEADERS: Prevented header-level redirects");
    }

    /**
     * 即座に実行するリダイレクトブロック
     */
    private function immediate_redirect_block() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // 階層URLの場合、即座にリダイレクトをブロック
        if ($this->is_hierarchy_url($request_uri)) {
            // グローバル変数でリダイレクト無効化フラグを設定
            $GLOBALS['kstb_no_redirect'] = true;

            // 可能な限り早い段階でLocation ヘッダーを阻止
            if (!headers_sent()) {
                // 既存のLocation ヘッダーをクリア
                header_remove('Location');

                // リダイレクトステータスコードを200に上書き
                http_response_code(200);
            }

            // wp_redirect関数をグローバルに無効化
            if (!defined('KSTB_WP_REDIRECT_DISABLED')) {
                define('KSTB_WP_REDIRECT_DISABLED', true);

                // フィルターを即座に追加
                add_filter('wp_redirect', function($location, $status) {
                    if ($GLOBALS['kstb_no_redirect'] ?? false) {
            // error_log("KSTB IMMEDIATE: Blocked redirect to: {$location}");
                        return false;
                    }
                    return $location;
                }, 1, 2);

                add_filter('redirect_canonical', function($redirect_url, $requested_url) {
                    if ($GLOBALS['kstb_no_redirect'] ?? false) {
            // error_log("KSTB IMMEDIATE: Blocked canonical redirect for: {$requested_url}");
                        return false;
                    }
                    return $redirect_url;
                }, 1, 2);
            }

            // error_log("KSTB IMMEDIATE: Immediate redirect block activated for: {$request_uri}");
        }
    }

    /**
     * 緊急リダイレクト防止（最優先実行）
     */
    private function emergency_redirect_prevention() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // 階層URLパターンをチェック
        if ($this->is_hierarchy_url($request_uri)) {
            // 即座にすべてのリダイレクト関数を無効化
            add_filter('redirect_canonical', '__return_false', 1);
            add_filter('wp_redirect', '__return_false', 1);

            // WordPressのコア関数をオーバーライド
            if (!function_exists('wp_redirect_emergency_override')) {
                function wp_redirect_emergency_override($location, $status = 302, $x_redirect_by = 'WordPress') {
            // error_log("KSTB EMERGENCY: Blocked redirect attempt: {$location}");
                    return false;
                }

                // グローバル関数を置き換え
                $GLOBALS['wp_redirect_original'] = 'wp_redirect';

                // リダイレクト無効化フラグを設定
                define('KSTB_EMERGENCY_NO_REDIRECT', true);
            }

            // HTTPヘッダー送信を制御
            add_action('wp_loaded', array($this, 'force_remove_redirect_headers'), 1);

            // error_log("KSTB EMERGENCY: Emergency redirect prevention activated for: {$request_uri}");
        }
    }

    /**
     * リダイレクトヘッダーを強制削除
     */
    public function force_remove_redirect_headers() {
        if (!headers_sent()) {
            // Location ヘッダーがあれば削除
            $headers = headers_list();
            foreach ($headers as $header) {
                if (stripos($header, 'location:') === 0) {
                    header_remove('Location');
            // error_log("KSTB EMERGENCY: Removed Location header: {$header}");
                }
            }

            // ステータスコードを200に強制
            http_response_code(200);
        }
    }

    /**
     * メインクエリの最終修正（wpアクション時）
     */
    public function force_hierarchy_query_final() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if ($this->is_hierarchy_url($request_uri)) {
            $request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';
            $request_path = trim($request_path, '/');
            $path_parts = explode('/', $request_path);

            if (count($path_parts) >= 3) {
                $parent_slug = $path_parts[0];
                $post_type_slug = $path_parts[1];
                $post_slug = $path_parts[2];

                if ($this->is_custom_post_type_slug($post_type_slug)) {
                    $found_post = get_page_by_path($post_slug, OBJECT, $post_type_slug);
                    if ($found_post) {
                        $parent = self::get_parent_page($found_post->ID);
                        if ($parent && $parent->post_name === $parent_slug) {
                            // 既存のメインクエリを安全に修正
                            global $wp_query, $post;

                            // 既存のクエリオブジェクトのプロパティを修正
                            $wp_query->is_404 = false;
                            $wp_query->is_single = true;
                            $wp_query->is_singular = true;
                            $wp_query->is_home = false;
                            $wp_query->is_archive = false;
                            $wp_query->queried_object = $found_post;
                            $wp_query->queried_object_id = $found_post->ID;
                            $wp_query->posts = array($found_post);
                            $wp_query->post_count = 1;
                            $wp_query->found_posts = 1;
                            $wp_query->max_num_pages = 1;
                            $wp_query->current_post = 0;

                            // グローバル変数を設定
                            $post = $found_post;
                            $GLOBALS['post'] = $found_post;
                            $GLOBALS['kstb_hierarchy_mode'] = true;
                            $GLOBALS['kstb_parent_slug'] = $parent_slug;

                            // 投稿データをセットアップ
                            setup_postdata($post);

            // error_log("KSTB FINAL: Main query rebuilt for post {$found_post->ID} - {$found_post->post_title}");

                            // HTMLコメントで成功を通知
                            add_action('wp_head', function() use ($found_post) {
                                echo "<!-- KSTB FINAL SUCCESS: Main query rebuilt for post {$found_post->ID} -->\n";
                            }, 5);
                        }
                    }
                }
            }
        }
    }

    /**
     * WordPressのリダイレクト関数を完全にオーバーライド
     */
    public function override_redirect_functions() {
        // このメソッドは特に何もしない（フック登録のみに留める）
        // 実際のリダイレクト防止は他のフィルターで行う
            // error_log("KSTB: Override redirect functions initialized");
    }

    /**
     * パーマリンク設定ページでの保存フックを設定
     */
    public function hook_permalink_save() {
        // パーマリンク設定が保存される前にフックを追加
        add_action('admin_init', array($this, 'flush_on_permalink_save'), 20);
    }

    /**
     * パーマリンク設定保存時にリライトルールをフラッシュ
     */
    public function flush_on_permalink_save() {
        // パーマリンク設定ページでPOSTリクエストがあった場合のみ
        if (isset($_POST['permalink_structure']) || isset($_POST['category_base']) || isset($_POST['tag_base'])) {
            // リライトルールをフラッシュ
            flush_rewrite_rules(false);
            
            // カスタムリライトルールを再追加
            $this->add_enhanced_rewrite_rules();
            
            // 再度フラッシュ
            flush_rewrite_rules(false);
            
            // error_log("KSTB: Automatically flushed rewrite rules after permalink settings save");
        }
    }
}
