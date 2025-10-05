<?php
if (!defined('ABSPATH')) {
    exit;
}

$post_types = KSTB_Database::get_all_post_types();

$dashicons = array(
    'dashicons-admin-post' => 'dashicons-admin-post',
    'dashicons-admin-media' => 'dashicons-admin-media',
    'dashicons-admin-page' => 'dashicons-admin-page',
    'dashicons-admin-comments' => 'dashicons-admin-comments',
    'dashicons-admin-users' => 'dashicons-admin-users',
    'dashicons-admin-tools' => 'dashicons-admin-tools',
    'dashicons-admin-settings' => 'dashicons-admin-settings',
    'dashicons-controls-volumeon' => 'dashicons-controls-volumeon',
    'dashicons-format-image' => 'dashicons-format-image',
    'dashicons-format-gallery' => 'dashicons-format-gallery',
    'dashicons-format-video' => 'dashicons-format-video',
    'dashicons-camera' => 'dashicons-camera',
    'dashicons-images-alt' => 'dashicons-images-alt',
    'dashicons-video-alt' => 'dashicons-video-alt',
    'dashicons-media-archive' => 'dashicons-media-archive',
    'dashicons-media-audio' => 'dashicons-media-audio',
    'dashicons-media-document' => 'dashicons-media-document',
    'dashicons-media-video' => 'dashicons-media-video',
    'dashicons-database' => 'dashicons-database',
    'dashicons-search' => 'dashicons-search',
    'dashicons-analytics' => 'dashicons-analytics',
    'dashicons-chart-pie' => 'dashicons-chart-pie',
    'dashicons-chart-bar' => 'dashicons-chart-bar',
    'dashicons-groups' => 'dashicons-groups',
    'dashicons-businessman' => 'dashicons-businessman',
    'dashicons-products' => 'dashicons-products',
    'dashicons-awards' => 'dashicons-awards',
    'dashicons-forms' => 'dashicons-forms',
    'dashicons-portfolio' => 'dashicons-portfolio',
    'dashicons-book' => 'dashicons-book',
    'dashicons-lightbulb' => 'dashicons-lightbulb',
    'dashicons-desktop' => 'dashicons-desktop',
    'dashicons-smartphone' => 'dashicons-smartphone',
    'dashicons-building' => 'dashicons-building',
    'dashicons-store' => 'dashicons-store',
    'dashicons-money' => 'dashicons-money',
    'dashicons-smiley' => 'dashicons-smiley',
    'dashicons-thumbs-up' => 'dashicons-thumbs-up',
    'dashicons-layout' => 'dashicons-layout',
    'dashicons-edit-page' => 'dashicons-edit-page',
    'dashicons-airplane' => 'dashicons-airplane',
    'dashicons-coffee' => 'dashicons-coffee',
    'dashicons-food' => 'dashicons-food',
    'dashicons-games' => 'dashicons-games',
    'dashicons-pets' => 'dashicons-pets',
    'dashicons-car' => 'dashicons-car'
);

$supports = array(
    'title' => __('タイトル', 'kashiwazaki-seo-type-builder'),
    'editor' => __('エディター', 'kashiwazaki-seo-type-builder'),
    'author' => __('作成者', 'kashiwazaki-seo-type-builder'),
    'thumbnail' => __('アイキャッチ画像', 'kashiwazaki-seo-type-builder'),
    'excerpt' => __('抜粋', 'kashiwazaki-seo-type-builder'),
    'trackbacks' => __('トラックバック', 'kashiwazaki-seo-type-builder'),
    'custom-fields' => __('カスタムフィールド', 'kashiwazaki-seo-type-builder'),
    'comments' => __('コメント', 'kashiwazaki-seo-type-builder'),
    'revisions' => __('リビジョン', 'kashiwazaki-seo-type-builder'),
    'page-attributes' => __('ページ属性', 'kashiwazaki-seo-type-builder'),
    'post-formats' => __('投稿フォーマット', 'kashiwazaki-seo-type-builder')
);

$taxonomies = get_taxonomies(array('public' => true), 'objects');
?>

<div class="wrap kstb-admin-wrap">
        <h1 class="wp-heading-inline">Kashiwazaki SEO Custom Post Types</h1>
    <button type="button" class="page-title-action kstb-add-new-button">新規追加</button>

    <?php
    // データベースとWordPressの登録状況を確認
    $db_post_types = KSTB_Database::get_all_post_types();
    $wp_registered = get_post_types(array('_builtin' => false), 'names');
    $missing_in_wp = array();

    foreach ($db_post_types as $pt) {
        if (!in_array($pt->slug, $wp_registered)) {
            $missing_in_wp[] = $pt->slug;
        }
    }

    if (!empty($missing_in_wp)) :
    ?>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>⚠️ メニューに表示されていないカスタム投稿タイプ:</strong> <?php echo implode(', ', $missing_in_wp); ?><br>
            <small>「すべて強制登録」ボタンをクリックして修正してください。</small>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

        <div id="kstb-notice-area"></div>


    <div class="kstb-container">
        <div class="kstb-list-area">
            <h2>カスタム投稿タイプ一覧</h2>
            <?php if (empty($post_types)) : ?>
                <p>カスタム投稿タイプがまだ作成されていません。</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-label">ラベル</th>
                            <th scope="col" class="column-slug">スラッグ</th>
                            <th scope="col" class="column-public">公開</th>
                            <th scope="col" class="column-rest">REST API</th>
                            <th scope="col" class="column-actions">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($post_types as $post_type) : ?>
                            <tr data-id="<?php echo esc_attr($post_type->id); ?>">
                                <td class="column-label">
                                    <?php if ($post_type->menu_icon) : ?>
                                        <span class="dashicons <?php echo esc_attr($post_type->menu_icon); ?>"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($post_type->label); ?>
                                </td>
                                <td class="column-slug">
                                    <?php 
                                    $archive_url = '';
                                    if (post_type_exists($post_type->slug)) {
                                        if ($post_type->has_archive) {
                                            // アーカイブページが有効な場合
                                            $parent_dir = !empty($post_type->parent_directory) ? trim($post_type->parent_directory, '/') . '/' : '';
                                            $archive_url = home_url('/' . $parent_dir . $post_type->slug . '/');
                                        } else {
                                            // 個別投稿のサンプルURLを表示（投稿が存在する場合）
                                            $sample_post = get_posts(array(
                                                'post_type' => $post_type->slug,
                                                'posts_per_page' => 1,
                                                'post_status' => 'publish'
                                            ));
                                            if (!empty($sample_post)) {
                                                $archive_url = get_permalink($sample_post[0]);
                                            }
                                        }
                                    }
                                    
                                    if ($archive_url) : ?>
                                        <a href="<?php echo esc_url($archive_url); ?>" target="_blank" title="<?php echo $post_type->has_archive ? 'アーカイブページを表示' : 'サンプル投稿を表示'; ?>">
                                            <?php echo esc_html($post_type->slug); ?> 
                                            <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: text-top;"></span>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($post_type->slug); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="column-public">
                                    <?php echo $post_type->public ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>'; ?>
                                </td>
                                <td class="column-rest">
                                    <?php echo $post_type->show_in_rest ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>'; ?>
                                </td>
                                <td class="column-actions">
                                    <?php if (post_type_exists($post_type->slug)) : ?>
                                        <span style="color: green; font-weight: bold;">✓ 登録済み</span><br>
                                    <?php else : ?>
                                        <span style="color: red; font-weight: bold;">✗ 未登録</span><br>
                                    <?php endif; ?>
                                    <?php if (post_type_exists($post_type->slug)) : ?>
                                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type->slug)); ?>" class="button button-primary">投稿を管理</a>
                                    <?php endif; ?>
                                    <button type="button" class="button kstb-edit-button" data-id="<?php echo esc_attr($post_type->id); ?>">編集</button>
                                    <button type="button" class="button kstb-delete-button" data-id="<?php echo esc_attr($post_type->id); ?>">削除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="kstb-form-area" style="display: none;">
            <h2><span class="kstb-form-title">新規カスタム投稿タイプ</span></h2>

            <div class="notice notice-info" style="margin: 10px 0;">
                <p>
                    <strong>必須項目:</strong> <span class="required">* 必須</span>マークの「スラッグ」と「ラベル」の2つだけです。他はすべて任意（デフォルト値あり）
                </p>
            </div>

            <form id="kstb-post-type-form">
                <input type="hidden" id="kstb-post-type-id" name="id" value="">

                <div class="kstb-tabs">
                    <ul class="kstb-tab-buttons">
                        <li><a href="#kstb-tab-basic" class="active">基本設定</a></li>
                        <li><a href="#kstb-tab-labels">ラベル（自動生成）</a></li>
                        <li><a href="#kstb-tab-settings">詳細設定</a></li>
                        <li><a href="#kstb-tab-supports">サポート機能</a></li>
                        <li><a href="#kstb-tab-taxonomies">タクソノミー</a></li>
                    </ul>

                    <div id="kstb-tab-basic" class="kstb-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="kstb-slug">スラッグ <span class="required">* 必須</span></label></th>
                                <td>
                                    <input type="text" id="kstb-slug" name="slug" class="regular-text" required maxlength="20" title="半角英数字、ハイフン、アンダースコアのみ使用可能">
                                    <p class="description">
                                        <strong>URLで使用される識別子</strong><br>
                                        半角英数字、ハイフン、アンダースコアのみ（最大20文字）<br>
                                        例: {投稿タイプ名}, {サービス名}, {コンテンツ名} など
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kstb-label">ラベル <span class="required">* 必須</span></label></th>
                                <td>
                                    <input type="text" id="kstb-label" name="label" class="regular-text" required>
                                    <p class="description">
                                        <strong>管理画面のメニューに表示される名前</strong><br>
                                        この名前が左メニューと投稿管理画面に表示されます<br>
                                        日本語でOK。「ニュース」「商品」「スタッフ」など<br>
                                        <span style="color: #0073aa;"><strong>※ この値から全てのボタンテキストが自動生成されます</strong></span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kstb-menu-icon">メニューアイコン</label></th>
                                <td>
                                    <select id="kstb-menu-icon" name="menu_icon" class="kstb-icon-select">
                                        <option value="">なし</option>
                                        <?php foreach ($dashicons as $key => $value) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" data-icon="<?php echo esc_attr($value); ?>">
                                                <?php echo esc_html($key); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="kstb-icon-preview"></span>
                                    <p class="description">
                                        <strong>管理画面メニューに表示されるアイコン</strong><br>
                                        選択すると右側にプレビューが表示されます
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kstb-menu-position">メニュー位置</label></th>
                                <td>
                                    <input type="number" id="kstb-menu-position" name="menu_position" class="small-text" min="5" max="100" placeholder="25">
                                    <p class="description">
                                        <strong>管理画面メニューの表示順序</strong><br>
                                        5〜100の数値。小さいほど上に表示されます<br>
                                        参考: 投稿(5), メディア(10), 固定ページ(20), コメント(25)<br>
                                        ※ 空欄の場合は25（デフォルト）
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="kstb-tab-labels" class="kstb-tab-content">
                        <div class="notice notice-info" style="margin-bottom: 15px;">
                            <p>
                                <strong>📋 自動生成されるラベル一覧</strong><br>
                                これらは「ラベル」フィールドから自動的に生成されます。編集する必要はありません。
                            </p>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th scope="row">複数形の名前</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-name">（ラベルを入力すると表示されます）</div>
                                    <p class="description">投稿一覧ページのタイトルなどで使用</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">単数形の名前</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-singular-name">（ラベルを入力すると表示されます）</div>
                                    <p class="description">個別の投稿を指す名前</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">メニュー名</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-menu-name">（ラベルを入力すると表示されます）</div>
                                    <p class="description">左メニューに表示される名前</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">新規追加</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-add-new">新規追加</div>
                                    <p class="description">新規追加ボタンのテキスト</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">新規追加ページ</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-add-new-item">（ラベルを入力すると表示されます）</div>
                                    <p class="description">新規追加ページのタイトル</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">編集</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-edit-item">（ラベルを入力すると表示されます）</div>
                                    <p class="description">編集ページのタイトル</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">表示</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-view-item">（ラベルを入力すると表示されます）</div>
                                    <p class="description">投稿表示ボタンのテキスト</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">すべて表示</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-all-items">（ラベルを入力すると表示されます）</div>
                                    <p class="description">一覧ページのメニュー名</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">検索</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-search-items">（ラベルを入力すると表示されます）</div>
                                    <p class="description">検索ボタンのテキスト</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">見つかりません</th>
                                <td>
                                    <div class="kstb-preview-field" id="preview-not-found">（ラベルを入力すると表示されます）</div>
                                    <p class="description">検索結果なしの表示</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="kstb-tab-settings" class="kstb-tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">公開設定</th>
                                <td>
                                    <label><input type="checkbox" name="public" value="1" checked> 公開</label>
                                    <p class="description">
                                        <strong>フロントエンドで表示可能にする</strong><br>
                                        チェックを外すと管理画面のみで使用される非公開の投稿タイプになります
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">表示設定</th>
                                <td>
                                    <label><input type="checkbox" name="show_ui" value="1" checked> 管理画面に表示</label>
                                    <p class="description" style="margin-left: 24px;">投稿の追加・編集画面を表示する</p>

                                    <label><input type="checkbox" name="show_in_menu" value="1" checked> メニューに表示</label>
                                    <p class="description" style="margin-left: 24px;">管理画面の左メニューに表示する</p>

                                    <label><input type="checkbox" name="publicly_queryable" value="1" checked> パブリッククエリ可能</label>
                                    <p class="description" style="margin-left: 24px;">フロントエンドでURLアクセス可能にする</p>

                                    <label><input type="checkbox" name="query_var" value="1" checked> クエリ変数を使用</label>
                                    <p class="description" style="margin-left: 24px;">URLパラメータで投稿を取得可能にする</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">階層</th>
                                <td>
                                    <label><input type="checkbox" name="hierarchical" value="1"> 階層化（親子関係を有効化）</label>
                                    <p class="description">
                                        <strong>親子関係を持たせる場合にチェック</strong><br>
                                        チェックすると、編集画面で親ページを選択できるようになります。<br>
                                        固定ページ、投稿ページ、カスタム投稿ページから親を選択可能です。
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">スラッグトップページ</th>
                                <td>
                                    <div style="margin-bottom: 10px;">
                                        <label>
                                            <input type="radio" name="slug_top_display" value="none" checked>
                                            表示しない
                                        </label>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label>
                                            <input type="radio" name="slug_top_display" value="archive">
                                            アーカイブ一覧を表示
                                        </label>
                                        <p class="description" style="margin-left: 24px;">/スラッグ名/ で投稿一覧を表示します</p>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label>
                                            <input type="radio" name="slug_top_display" value="page">
                                            固定ページを表示
                                        </label>
                                        <div id="custom_page_selector" style="display: none; margin-left: 24px; margin-top: 5px;">
                                            <select name="archive_page_id" id="archive_page_id">
                                                <option value="">選択してください</option>
                                                <?php
                                                $pages = get_pages();
                                                foreach ($pages as $page) {
                                                    echo '<option value="' . $page->ID . '">' . esc_html($page->post_title) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">親ディレクトリ</th>
                                <td>
                                    <select name="parent_directory" class="regular-text">
                                        <option value="">— 親ディレクトリなし —</option>
                                        <optgroup label="固定ページ">
                                            <?php
                                            $pages = get_pages(array('sort_column' => 'post_title'));
                                            foreach ($pages as $page) {
                                                $page_path = '/' . trim(get_page_uri($page), '/') . '/';
                                                echo '<option value="' . esc_attr($page_path) . '">' . 
                                                     str_repeat('&nbsp;&nbsp;&nbsp;', count(explode('/', trim($page_path, '/'))) - 1) . 
                                                     esc_html($page->post_title) . ' (' . esc_html($page_path) . ')</option>';
                                            }
                                            ?>
                                        </optgroup>
                                        <optgroup label="カスタム投稿タイプ（アーカイブ）">
                                            <?php
                                            // 他のカスタム投稿タイプを親として選択可能
                                            $other_post_types = KSTB_Database::get_all_post_types();
                                            foreach ($other_post_types as $other_type) {
                                                if ($other_type->has_archive) {
                                                    // スラッグのみを値として使用
                                                    $value_path = '/' . $other_type->slug . '/';
                                                    // 表示用のフルパス
                                                    $display_path = '/' . $other_type->slug . '/';
                                                    if (!empty($other_type->parent_directory)) {
                                                        $parent_dir = trim($other_type->parent_directory, '/');
                                                        $display_path = '/' . $parent_dir . '/' . $other_type->slug . '/';
                                                    }
                                                    echo '<option value="' . esc_attr($value_path) . '">' . 
                                                         esc_html($other_type->label) . ' (' . esc_html($display_path) . ')</option>';
                                                }
                                            }
                                            ?>
                                        </optgroup>
                                    </select>
                                    <p class="description">
                                        <strong>カスタム投稿タイプの親となるディレクトリを選択</strong><br>
                                        選択した親の下にこのカスタム投稿タイプのURLが配置されます<br>
                                        例：「会社情報 (/company/)」を選択 → 「/company/blog/」「/company/blog/post-slug/」
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="kstb-tab-supports" class="kstb-tab-content">
                        <p class="description" style="margin-bottom: 20px;">
                            <strong>ヒント:</strong> この投稿タイプで利用したい機能にチェックを入れてください。<br>
                            「タイトル」と「エディター」は基本的に必須です。
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row">サポートする機能</th>
                                <td>
                                    <?php foreach ($supports as $key => $label) : ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="supports[]" value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, array('title', 'editor')) ? 'checked' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="kstb-tab-taxonomies" class="kstb-tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">使用するタクソノミー</th>
                                <td>
                                    <p class="description" style="margin-bottom: 10px;">
                                        この投稿タイプで使用したい分類を選択してください。カテゴリーは階層構造、タグは非階層構造です。
                                    </p>
                                    <?php foreach ($taxonomies as $taxonomy) : ?>
                                        <?php if (!in_array($taxonomy->name, array('post_format', 'nav_menu', 'link_category'))) : ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($taxonomy->name); ?>">
                                                <?php echo esc_html($taxonomy->label); ?> (<?php echo esc_html($taxonomy->name); ?>)
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary kstb-save-button">保存</button>
                    <button type="button" class="button kstb-cancel-button">キャンセル</button>
                </p>
            </form>
        </div>
    </div>
</div>
