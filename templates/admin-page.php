<?php
if (!defined('ABSPATH')) {
    exit;
}

$post_types = KSTB_Database::get_all_post_types();

// パスでソート（デフォルト）
usort($post_types, function($a, $b) use ($post_types) {
    $path_a = kstb_build_full_path($a->slug, $post_types);
    $path_b = kstb_build_full_path($b->slug, $post_types);
    return strcmp($path_a, $path_b);
});

// 各投稿タイプの完全パスを計算
function kstb_build_full_path($post_type_slug, $all_post_types, $visited = array()) {
    static $cache = array();

    if (isset($cache[$post_type_slug])) {
        return $cache[$post_type_slug];
    }

    // 循環参照を検出
    if (in_array($post_type_slug, $visited)) {
        error_log('KSTB Warning: Circular reference detected in post type hierarchy for: ' . $post_type_slug);
        $cache[$post_type_slug] = '';
        return '';
    }

    // 訪問済みリストに追加
    $visited[] = $post_type_slug;

    // 該当する投稿タイプを検索
    $current = null;
    foreach ($all_post_types as $pt) {
        if ($pt->slug === $post_type_slug) {
            $current = $pt;
            break;
        }
    }

    if (!$current) {
        return '';
    }

    // 親ディレクトリがない場合
    if (empty($current->parent_directory)) {
        $cache[$post_type_slug] = '';
        return '';
    }

    $parent_dir = trim($current->parent_directory, '/');

    // 親ディレクトリが投稿タイプかどうかチェック
    $parent_is_post_type = false;
    foreach ($all_post_types as $pt) {
        if ($pt->slug === $parent_dir) {
            $parent_is_post_type = true;
            break;
        }
    }

    if ($parent_is_post_type) {
        // 親の完全パスを再帰的に取得（訪問済みリストを渡す）
        $parent_full_path = kstb_build_full_path($parent_dir, $all_post_types, $visited);
        $full_path = $parent_full_path ? $parent_full_path . '/' . $parent_dir : $parent_dir;
    } else {
        // 親は固定ページやその他
        $full_path = $parent_dir;
    }

    $cache[$post_type_slug] = $full_path;
    return $full_path;
}

$dashicons = array(
    // Admin Menu
    'dashicons-menu' => 'dashicons-menu',
    'dashicons-menu-alt' => 'dashicons-menu-alt',
    'dashicons-menu-alt2' => 'dashicons-menu-alt2',
    'dashicons-menu-alt3' => 'dashicons-menu-alt3',
    'dashicons-admin-site' => 'dashicons-admin-site',
    'dashicons-admin-site-alt' => 'dashicons-admin-site-alt',
    'dashicons-admin-site-alt2' => 'dashicons-admin-site-alt2',
    'dashicons-admin-site-alt3' => 'dashicons-admin-site-alt3',
    'dashicons-dashboard' => 'dashicons-dashboard',
    'dashicons-admin-post' => 'dashicons-admin-post',
    'dashicons-admin-media' => 'dashicons-admin-media',
    'dashicons-admin-links' => 'dashicons-admin-links',
    'dashicons-admin-page' => 'dashicons-admin-page',
    'dashicons-admin-comments' => 'dashicons-admin-comments',
    'dashicons-admin-appearance' => 'dashicons-admin-appearance',
    'dashicons-admin-plugins' => 'dashicons-admin-plugins',
    'dashicons-plugins-checked' => 'dashicons-plugins-checked',
    'dashicons-admin-users' => 'dashicons-admin-users',
    'dashicons-admin-tools' => 'dashicons-admin-tools',
    'dashicons-admin-settings' => 'dashicons-admin-settings',
    'dashicons-admin-network' => 'dashicons-admin-network',
    'dashicons-admin-home' => 'dashicons-admin-home',
    'dashicons-admin-generic' => 'dashicons-admin-generic',
    'dashicons-admin-collapse' => 'dashicons-admin-collapse',
    'dashicons-filter' => 'dashicons-filter',
    'dashicons-admin-customizer' => 'dashicons-admin-customizer',
    'dashicons-admin-multisite' => 'dashicons-admin-multisite',

    // Welcome Screen
    'dashicons-welcome-write-blog' => 'dashicons-welcome-write-blog',
    'dashicons-welcome-add-page' => 'dashicons-welcome-add-page',
    'dashicons-welcome-view-site' => 'dashicons-welcome-view-site',
    'dashicons-welcome-widgets-menus' => 'dashicons-welcome-widgets-menus',
    'dashicons-welcome-comments' => 'dashicons-welcome-comments',
    'dashicons-welcome-learn-more' => 'dashicons-welcome-learn-more',

    // Post Formats
    'dashicons-format-aside' => 'dashicons-format-aside',
    'dashicons-format-image' => 'dashicons-format-image',
    'dashicons-format-gallery' => 'dashicons-format-gallery',
    'dashicons-format-video' => 'dashicons-format-video',
    'dashicons-format-status' => 'dashicons-format-status',
    'dashicons-format-quote' => 'dashicons-format-quote',
    'dashicons-format-chat' => 'dashicons-format-chat',
    'dashicons-format-audio' => 'dashicons-format-audio',
    'dashicons-camera' => 'dashicons-camera',
    'dashicons-camera-alt' => 'dashicons-camera-alt',
    'dashicons-images-alt' => 'dashicons-images-alt',
    'dashicons-images-alt2' => 'dashicons-images-alt2',
    'dashicons-video-alt' => 'dashicons-video-alt',
    'dashicons-video-alt2' => 'dashicons-video-alt2',
    'dashicons-video-alt3' => 'dashicons-video-alt3',

    // Media
    'dashicons-media-archive' => 'dashicons-media-archive',
    'dashicons-media-audio' => 'dashicons-media-audio',
    'dashicons-media-code' => 'dashicons-media-code',
    'dashicons-media-default' => 'dashicons-media-default',
    'dashicons-media-document' => 'dashicons-media-document',
    'dashicons-media-interactive' => 'dashicons-media-interactive',
    'dashicons-media-spreadsheet' => 'dashicons-media-spreadsheet',
    'dashicons-media-text' => 'dashicons-media-text',
    'dashicons-media-video' => 'dashicons-media-video',
    'dashicons-playlist-audio' => 'dashicons-playlist-audio',
    'dashicons-playlist-video' => 'dashicons-playlist-video',
    'dashicons-controls-play' => 'dashicons-controls-play',
    'dashicons-controls-pause' => 'dashicons-controls-pause',
    'dashicons-controls-forward' => 'dashicons-controls-forward',
    'dashicons-controls-skipforward' => 'dashicons-controls-skipforward',
    'dashicons-controls-back' => 'dashicons-controls-back',
    'dashicons-controls-skipback' => 'dashicons-controls-skipback',
    'dashicons-controls-repeat' => 'dashicons-controls-repeat',
    'dashicons-controls-volumeon' => 'dashicons-controls-volumeon',
    'dashicons-controls-volumeoff' => 'dashicons-controls-volumeoff',

    // Image Editing
    'dashicons-image-crop' => 'dashicons-image-crop',
    'dashicons-image-rotate' => 'dashicons-image-rotate',
    'dashicons-image-rotate-left' => 'dashicons-image-rotate-left',
    'dashicons-image-rotate-right' => 'dashicons-image-rotate-right',
    'dashicons-image-flip-vertical' => 'dashicons-image-flip-vertical',
    'dashicons-image-flip-horizontal' => 'dashicons-image-flip-horizontal',
    'dashicons-image-filter' => 'dashicons-image-filter',
    'dashicons-undo' => 'dashicons-undo',
    'dashicons-redo' => 'dashicons-redo',

    // TinyMCE
    'dashicons-editor-bold' => 'dashicons-editor-bold',
    'dashicons-editor-italic' => 'dashicons-editor-italic',
    'dashicons-editor-ul' => 'dashicons-editor-ul',
    'dashicons-editor-ol' => 'dashicons-editor-ol',
    'dashicons-editor-ol-rtl' => 'dashicons-editor-ol-rtl',
    'dashicons-editor-quote' => 'dashicons-editor-quote',
    'dashicons-editor-alignleft' => 'dashicons-editor-alignleft',
    'dashicons-editor-aligncenter' => 'dashicons-editor-aligncenter',
    'dashicons-editor-alignright' => 'dashicons-editor-alignright',
    'dashicons-editor-insertmore' => 'dashicons-editor-insertmore',
    'dashicons-editor-spellcheck' => 'dashicons-editor-spellcheck',
    'dashicons-editor-expand' => 'dashicons-editor-expand',
    'dashicons-editor-contract' => 'dashicons-editor-contract',
    'dashicons-editor-kitchensink' => 'dashicons-editor-kitchensink',
    'dashicons-editor-underline' => 'dashicons-editor-underline',
    'dashicons-editor-justify' => 'dashicons-editor-justify',
    'dashicons-editor-textcolor' => 'dashicons-editor-textcolor',
    'dashicons-editor-paste-word' => 'dashicons-editor-paste-word',
    'dashicons-editor-paste-text' => 'dashicons-editor-paste-text',
    'dashicons-editor-removeformatting' => 'dashicons-editor-removeformatting',
    'dashicons-editor-video' => 'dashicons-editor-video',
    'dashicons-editor-customchar' => 'dashicons-editor-customchar',
    'dashicons-editor-outdent' => 'dashicons-editor-outdent',
    'dashicons-editor-indent' => 'dashicons-editor-indent',
    'dashicons-editor-help' => 'dashicons-editor-help',
    'dashicons-editor-strikethrough' => 'dashicons-editor-strikethrough',
    'dashicons-editor-unlink' => 'dashicons-editor-unlink',
    'dashicons-editor-rtl' => 'dashicons-editor-rtl',
    'dashicons-editor-ltr' => 'dashicons-editor-ltr',
    'dashicons-editor-break' => 'dashicons-editor-break',
    'dashicons-editor-code' => 'dashicons-editor-code',
    'dashicons-editor-paragraph' => 'dashicons-editor-paragraph',
    'dashicons-editor-table' => 'dashicons-editor-table',

    // Posts
    'dashicons-align-left' => 'dashicons-align-left',
    'dashicons-align-right' => 'dashicons-align-right',
    'dashicons-align-center' => 'dashicons-align-center',
    'dashicons-align-none' => 'dashicons-align-none',
    'dashicons-lock' => 'dashicons-lock',
    'dashicons-unlock' => 'dashicons-unlock',
    'dashicons-calendar' => 'dashicons-calendar',
    'dashicons-calendar-alt' => 'dashicons-calendar-alt',
    'dashicons-visibility' => 'dashicons-visibility',
    'dashicons-hidden' => 'dashicons-hidden',
    'dashicons-post-status' => 'dashicons-post-status',
    'dashicons-edit' => 'dashicons-edit',
    'dashicons-trash' => 'dashicons-trash',
    'dashicons-sticky' => 'dashicons-sticky',

    // Sorting
    'dashicons-external' => 'dashicons-external',
    'dashicons-arrow-up' => 'dashicons-arrow-up',
    'dashicons-arrow-down' => 'dashicons-arrow-down',
    'dashicons-arrow-right' => 'dashicons-arrow-right',
    'dashicons-arrow-left' => 'dashicons-arrow-left',
    'dashicons-arrow-up-alt' => 'dashicons-arrow-up-alt',
    'dashicons-arrow-down-alt' => 'dashicons-arrow-down-alt',
    'dashicons-arrow-right-alt' => 'dashicons-arrow-right-alt',
    'dashicons-arrow-left-alt' => 'dashicons-arrow-left-alt',
    'dashicons-arrow-up-alt2' => 'dashicons-arrow-up-alt2',
    'dashicons-arrow-down-alt2' => 'dashicons-arrow-down-alt2',
    'dashicons-arrow-right-alt2' => 'dashicons-arrow-right-alt2',
    'dashicons-arrow-left-alt2' => 'dashicons-arrow-left-alt2',
    'dashicons-sort' => 'dashicons-sort',
    'dashicons-leftright' => 'dashicons-leftright',
    'dashicons-randomize' => 'dashicons-randomize',
    'dashicons-list-view' => 'dashicons-list-view',
    'dashicons-excerpt-view' => 'dashicons-excerpt-view',
    'dashicons-grid-view' => 'dashicons-grid-view',
    'dashicons-move' => 'dashicons-move',

    // Social
    'dashicons-share' => 'dashicons-share',
    'dashicons-share-alt' => 'dashicons-share-alt',
    'dashicons-share-alt2' => 'dashicons-share-alt2',
    'dashicons-rss' => 'dashicons-rss',
    'dashicons-email' => 'dashicons-email',
    'dashicons-email-alt' => 'dashicons-email-alt',
    'dashicons-email-alt2' => 'dashicons-email-alt2',
    'dashicons-networking' => 'dashicons-networking',
    'dashicons-amazon' => 'dashicons-amazon',
    'dashicons-facebook' => 'dashicons-facebook',
    'dashicons-facebook-alt' => 'dashicons-facebook-alt',
    'dashicons-google' => 'dashicons-google',
    'dashicons-instagram' => 'dashicons-instagram',
    'dashicons-linkedin' => 'dashicons-linkedin',
    'dashicons-pinterest' => 'dashicons-pinterest',
    'dashicons-podio' => 'dashicons-podio',
    'dashicons-reddit' => 'dashicons-reddit',
    'dashicons-spotify' => 'dashicons-spotify',
    'dashicons-twitch' => 'dashicons-twitch',
    'dashicons-twitter' => 'dashicons-twitter',
    'dashicons-twitter-alt' => 'dashicons-twitter-alt',
    'dashicons-whatsapp' => 'dashicons-whatsapp',
    'dashicons-xing' => 'dashicons-xing',
    'dashicons-youtube' => 'dashicons-youtube',

    // Jobs/WordPress.org
    'dashicons-hammer' => 'dashicons-hammer',
    'dashicons-art' => 'dashicons-art',
    'dashicons-migrate' => 'dashicons-migrate',
    'dashicons-performance' => 'dashicons-performance',
    'dashicons-universal-access' => 'dashicons-universal-access',
    'dashicons-universal-access-alt' => 'dashicons-universal-access-alt',
    'dashicons-tickets' => 'dashicons-tickets',
    'dashicons-nametag' => 'dashicons-nametag',
    'dashicons-clipboard' => 'dashicons-clipboard',
    'dashicons-heart' => 'dashicons-heart',
    'dashicons-megaphone' => 'dashicons-megaphone',
    'dashicons-schedule' => 'dashicons-schedule',
    'dashicons-tide' => 'dashicons-tide',
    'dashicons-rest-api' => 'dashicons-rest-api',
    'dashicons-code-standards' => 'dashicons-code-standards',

    // Internal/Products
    'dashicons-wordpress' => 'dashicons-wordpress',
    'dashicons-wordpress-alt' => 'dashicons-wordpress-alt',
    'dashicons-pressthis' => 'dashicons-pressthis',
    'dashicons-update' => 'dashicons-update',
    'dashicons-update-alt' => 'dashicons-update-alt',
    'dashicons-screenoptions' => 'dashicons-screenoptions',
    'dashicons-info' => 'dashicons-info',
    'dashicons-cart' => 'dashicons-cart',
    'dashicons-feedback' => 'dashicons-feedback',
    'dashicons-cloud' => 'dashicons-cloud',
    'dashicons-translation' => 'dashicons-translation',

    // Taxonomies
    'dashicons-tag' => 'dashicons-tag',
    'dashicons-category' => 'dashicons-category',

    // Widgets
    'dashicons-archive' => 'dashicons-archive',
    'dashicons-tagcloud' => 'dashicons-tagcloud',
    'dashicons-text' => 'dashicons-text',

    // Notifications
    'dashicons-bell' => 'dashicons-bell',
    'dashicons-yes' => 'dashicons-yes',
    'dashicons-yes-alt' => 'dashicons-yes-alt',
    'dashicons-no' => 'dashicons-no',
    'dashicons-no-alt' => 'dashicons-no-alt',
    'dashicons-plus' => 'dashicons-plus',
    'dashicons-plus-alt' => 'dashicons-plus-alt',
    'dashicons-plus-alt2' => 'dashicons-plus-alt2',
    'dashicons-minus' => 'dashicons-minus',
    'dashicons-dismiss' => 'dashicons-dismiss',
    'dashicons-marker' => 'dashicons-marker',
    'dashicons-star-filled' => 'dashicons-star-filled',
    'dashicons-star-half' => 'dashicons-star-half',
    'dashicons-star-empty' => 'dashicons-star-empty',
    'dashicons-flag' => 'dashicons-flag',
    'dashicons-warning' => 'dashicons-warning',

    // Misc/Post
    'dashicons-location' => 'dashicons-location',
    'dashicons-location-alt' => 'dashicons-location-alt',
    'dashicons-vault' => 'dashicons-vault',
    'dashicons-shield' => 'dashicons-shield',
    'dashicons-shield-alt' => 'dashicons-shield-alt',
    'dashicons-sos' => 'dashicons-sos',
    'dashicons-search' => 'dashicons-search',
    'dashicons-slides' => 'dashicons-slides',
    'dashicons-text-page' => 'dashicons-text-page',
    'dashicons-analytics' => 'dashicons-analytics',
    'dashicons-chart-pie' => 'dashicons-chart-pie',
    'dashicons-chart-bar' => 'dashicons-chart-bar',
    'dashicons-chart-line' => 'dashicons-chart-line',
    'dashicons-chart-area' => 'dashicons-chart-area',
    'dashicons-groups' => 'dashicons-groups',
    'dashicons-businessman' => 'dashicons-businessman',
    'dashicons-businesswoman' => 'dashicons-businesswoman',
    'dashicons-businessperson' => 'dashicons-businessperson',
    'dashicons-id' => 'dashicons-id',
    'dashicons-id-alt' => 'dashicons-id-alt',
    'dashicons-products' => 'dashicons-products',
    'dashicons-awards' => 'dashicons-awards',
    'dashicons-forms' => 'dashicons-forms',
    'dashicons-testimonial' => 'dashicons-testimonial',
    'dashicons-portfolio' => 'dashicons-portfolio',
    'dashicons-book' => 'dashicons-book',
    'dashicons-book-alt' => 'dashicons-book-alt',
    'dashicons-download' => 'dashicons-download',
    'dashicons-upload' => 'dashicons-upload',
    'dashicons-backup' => 'dashicons-backup',
    'dashicons-clock' => 'dashicons-clock',
    'dashicons-lightbulb' => 'dashicons-lightbulb',
    'dashicons-microphone' => 'dashicons-microphone',
    'dashicons-desktop' => 'dashicons-desktop',
    'dashicons-laptop' => 'dashicons-laptop',
    'dashicons-tablet' => 'dashicons-tablet',
    'dashicons-smartphone' => 'dashicons-smartphone',
    'dashicons-phone' => 'dashicons-phone',
    'dashicons-index-card' => 'dashicons-index-card',
    'dashicons-carrot' => 'dashicons-carrot',
    'dashicons-building' => 'dashicons-building',
    'dashicons-store' => 'dashicons-store',
    'dashicons-album' => 'dashicons-album',
    'dashicons-palmtree' => 'dashicons-palmtree',
    'dashicons-tickets-alt' => 'dashicons-tickets-alt',
    'dashicons-money' => 'dashicons-money',
    'dashicons-money-alt' => 'dashicons-money-alt',
    'dashicons-smiley' => 'dashicons-smiley',
    'dashicons-thumbs-up' => 'dashicons-thumbs-up',
    'dashicons-thumbs-down' => 'dashicons-thumbs-down',
    'dashicons-layout' => 'dashicons-layout',
    'dashicons-paperclip' => 'dashicons-paperclip',
    'dashicons-color-picker' => 'dashicons-color-picker',
    'dashicons-edit-large' => 'dashicons-edit-large',
    'dashicons-edit-page' => 'dashicons-edit-page',
    'dashicons-airplane' => 'dashicons-airplane',
    'dashicons-bank' => 'dashicons-bank',
    'dashicons-beer' => 'dashicons-beer',
    'dashicons-calculator' => 'dashicons-calculator',
    'dashicons-car' => 'dashicons-car',
    'dashicons-coffee' => 'dashicons-coffee',
    'dashicons-drumstick' => 'dashicons-drumstick',
    'dashicons-food' => 'dashicons-food',
    'dashicons-fullscreen-alt' => 'dashicons-fullscreen-alt',
    'dashicons-fullscreen-exit-alt' => 'dashicons-fullscreen-exit-alt',
    'dashicons-games' => 'dashicons-games',
    'dashicons-hourglass' => 'dashicons-hourglass',
    'dashicons-open-folder' => 'dashicons-open-folder',
    'dashicons-pdf' => 'dashicons-pdf',
    'dashicons-pets' => 'dashicons-pets',
    'dashicons-printer' => 'dashicons-printer',
    'dashicons-privacy' => 'dashicons-privacy',
    'dashicons-superhero' => 'dashicons-superhero',
    'dashicons-superhero-alt' => 'dashicons-superhero-alt',

    // Block Editor
    'dashicons-align-full-width' => 'dashicons-align-full-width',
    'dashicons-align-pull-left' => 'dashicons-align-pull-left',
    'dashicons-align-pull-right' => 'dashicons-align-pull-right',
    'dashicons-align-wide' => 'dashicons-align-wide',
    'dashicons-block-default' => 'dashicons-block-default',
    'dashicons-button' => 'dashicons-button',
    'dashicons-cloud-saved' => 'dashicons-cloud-saved',
    'dashicons-cloud-upload' => 'dashicons-cloud-upload',
    'dashicons-columns' => 'dashicons-columns',
    'dashicons-cover-image' => 'dashicons-cover-image',
    'dashicons-ellipsis' => 'dashicons-ellipsis',
    'dashicons-embed-audio' => 'dashicons-embed-audio',
    'dashicons-embed-generic' => 'dashicons-embed-generic',
    'dashicons-embed-photo' => 'dashicons-embed-photo',
    'dashicons-embed-post' => 'dashicons-embed-post',
    'dashicons-embed-video' => 'dashicons-embed-video',
    'dashicons-exit' => 'dashicons-exit',
    'dashicons-heading' => 'dashicons-heading',
    'dashicons-html' => 'dashicons-html',
    'dashicons-info-outline' => 'dashicons-info-outline',
    'dashicons-insert' => 'dashicons-insert',
    'dashicons-insert-after' => 'dashicons-insert-after',
    'dashicons-insert-before' => 'dashicons-insert-before',
    'dashicons-remove' => 'dashicons-remove',
    'dashicons-saved' => 'dashicons-saved',
    'dashicons-shortcode' => 'dashicons-shortcode',
    'dashicons-table-col-after' => 'dashicons-table-col-after',
    'dashicons-table-col-before' => 'dashicons-table-col-before',
    'dashicons-table-col-delete' => 'dashicons-table-col-delete',
    'dashicons-table-row-after' => 'dashicons-table-row-after',
    'dashicons-table-row-before' => 'dashicons-table-row-before',
    'dashicons-table-row-delete' => 'dashicons-table-row-delete',

    // Buddicons/Community
    'dashicons-buddicons-activity' => 'dashicons-buddicons-activity',
    'dashicons-buddicons-bbpress-logo' => 'dashicons-buddicons-bbpress-logo',
    'dashicons-buddicons-buddypress-logo' => 'dashicons-buddicons-buddypress-logo',
    'dashicons-buddicons-community' => 'dashicons-buddicons-community',
    'dashicons-buddicons-forums' => 'dashicons-buddicons-forums',
    'dashicons-buddicons-friends' => 'dashicons-buddicons-friends',
    'dashicons-buddicons-groups' => 'dashicons-buddicons-groups',
    'dashicons-buddicons-pm' => 'dashicons-buddicons-pm',
    'dashicons-buddicons-replies' => 'dashicons-buddicons-replies',
    'dashicons-buddicons-topics' => 'dashicons-buddicons-topics',
    'dashicons-buddicons-tracking' => 'dashicons-buddicons-tracking',

    // Database
    'dashicons-database' => 'dashicons-database',
    'dashicons-database-add' => 'dashicons-database-add',
    'dashicons-database-export' => 'dashicons-database-export',
    'dashicons-database-import' => 'dashicons-database-import',
    'dashicons-database-remove' => 'dashicons-database-remove',
    'dashicons-database-view' => 'dashicons-database-view'
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
            <!-- タブ切り替え -->
            <div class="kstb-main-tabs">
                <ul class="kstb-main-tab-buttons">
                    <li><a href="#kstb-main-tab-list" class="active">カスタム投稿タイプ一覧</a></li>
                    <li><a href="#kstb-main-tab-menu">メニュー管理</a></li>
                    <li><a href="#kstb-main-tab-guide">説明書</a></li>
                </ul>

                <div id="kstb-main-tab-list" class="kstb-main-tab-content active">
            <?php if (empty($post_types)) : ?>
                <p>カスタム投稿タイプがまだ作成されていません。</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-label sortable" data-sort="label">
                                ラベル <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="column-post-count sortable" data-sort="post-count">
                                投稿数 <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="column-path sortable sorted-asc" data-sort="path">
                                パス <span class="sort-indicator">▲</span>
                            </th>
                            <th scope="col" class="column-slug sortable" data-sort="slug">
                                スラッグ <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="column-public">公開</th>
                            <th scope="col" class="column-rest">REST API</th>
                            <th scope="col" class="column-actions">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($post_types as $post_type) :
                            // 投稿数を事前に取得
                            $published_count = 0;
                            if (post_type_exists($post_type->slug)) {
                                $post_count = wp_count_posts($post_type->slug);
                                $published_count = isset($post_count->publish) ? $post_count->publish : 0;
                            }
                            // パスを事前に取得
                            $full_path = kstb_build_full_path($post_type->slug, $post_types);
                        ?>
                            <tr data-id="<?php echo esc_attr($post_type->id); ?>"
                                data-label="<?php echo esc_attr($post_type->label); ?>"
                                data-post-count="<?php echo esc_attr($published_count); ?>"
                                data-path="<?php echo esc_attr($full_path); ?>"
                                data-slug="<?php echo esc_attr($post_type->slug); ?>">
                                <td class="column-label">
                                    <?php if ($post_type->menu_icon) : ?>
                                        <span class="dashicons <?php echo esc_attr($post_type->menu_icon); ?>"></span>
                                    <?php endif; ?>
                                    <span style="font-weight: 600;"><?php echo esc_html($post_type->label); ?></span>
                                </td>
                                <td class="column-post-count">
                                    <?php
                                    if (post_type_exists($post_type->slug)) {
                                        if ($published_count > 0) {
                                            echo '<a href="' . esc_url(admin_url('edit.php?post_type=' . $post_type->slug)) . '" style="text-decoration: none;">';
                                            echo esc_html($published_count);
                                            echo '</a>';
                                        } else {
                                            echo '<span style="color: #999;">0</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-path">
                                    <?php
                                    if ($full_path) {
                                        echo '<span style="color: #666;">/' . esc_html($full_path) . '/</span>';
                                    } else {
                                        echo '<span style="color: #999;">/</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-slug">
                                    <?php
                                    $archive_url = '';
                                    if (post_type_exists($post_type->slug)) {
                                        // スラッグまでのパス（アーカイブURL）を生成
                                        $parent_dir = !empty($post_type->parent_directory) ? trim($post_type->parent_directory, '/') . '/' : '';
                                        $url_slug = !empty($post_type->url_slug) ? $post_type->url_slug : $post_type->slug;
                                        $archive_url = home_url('/' . $parent_dir . $url_slug . '/');
                                    }

                                    if ($archive_url) : ?>
                                        <a href="<?php echo esc_url($archive_url); ?>" target="_blank" title="スラッグのパスを表示">
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
                                    <button type="button" class="button kstb-edit-button" data-id="<?php echo esc_attr($post_type->id); ?>">編集</button>
                                    <button type="button" class="button kstb-delete-button" data-id="<?php echo esc_attr($post_type->id); ?>">削除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
                </div>

                <!-- メニュー管理タブ -->
                <div id="kstb-main-tab-menu" class="kstb-main-tab-content">
                    <h3 style="margin-top: 10px;">📁 メニュー管理</h3>
                    <p>カスタム投稿タイプを管理画面のサイドメニューでフォルダ（カテゴリー）にまとめることができます。</p>

                    <div class="kstb-menu-manager">
                        <div class="kstb-menu-categories">
                            <h4>カテゴリー一覧</h4>
                            <table class="wp-list-table widefat fixed striped" id="kstb-categories-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">アイコン</th>
                                        <th style="width: 30%;">カテゴリー名</th>
                                        <th style="width: 40%;">含まれる投稿タイプ</th>
                                        <th style="width: 20%;">操作</th>
                                    </tr>
                                </thead>
                                <tbody id="kstb-categories-list">
                                    <!-- JavaScriptで動的に生成 -->
                                </tbody>
                            </table>
                            <p>
                                <button type="button" class="button" id="kstb-add-category-btn">+ 新しいカテゴリーを追加</button>
                            </p>
                        </div>

                        <!-- アイコンセレクターモーダル（非表示） -->
                        <div id="kstb-icon-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                                <h3 style="margin-top: 0;">アイコンを選択</h3>
                                <div id="kstb-icon-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px; margin: 20px 0;">
                                    <!-- フォルダ系アイコン -->
                                    <?php
                                    $folder_icons = array(
                                        'dashicons-category' => 'カテゴリー',
                                        'dashicons-portfolio' => 'ポートフォリオ',
                                        'dashicons-media-archive' => 'アーカイブ',
                                        'dashicons-admin-page' => 'ページ',
                                        'dashicons-admin-post' => '投稿',
                                        'dashicons-admin-media' => 'メディア',
                                        'dashicons-admin-site' => 'サイト',
                                        'dashicons-admin-generic' => '一般',
                                        'dashicons-admin-tools' => 'ツール',
                                        'dashicons-admin-settings' => '設定',
                                        'dashicons-database' => 'データベース',
                                        'dashicons-archive' => 'アーカイブ',
                                        'dashicons-book' => '本',
                                        'dashicons-book-alt' => '本2',
                                        'dashicons-businessman' => 'ビジネス',
                                        'dashicons-groups' => 'グループ',
                                        'dashicons-id' => 'ID',
                                        'dashicons-id-alt' => 'ID2',
                                        'dashicons-products' => '商品',
                                        'dashicons-cart' => 'カート',
                                        'dashicons-store' => 'ストア'
                                    );
                                    foreach ($folder_icons as $icon => $label) {
                                        echo '<div class="kstb-icon-option" data-icon="' . esc_attr($icon) . '" style="text-align: center; cursor: pointer; padding: 10px; border: 2px solid #ddd; border-radius: 4px; transition: all 0.2s;" title="' . esc_attr($label) . '" onmouseover="this.style.borderColor=\'#2271b1\'; this.style.backgroundColor=\'#f0f6fc\';" onmouseout="this.style.borderColor=\'#ddd\'; this.style.backgroundColor=\'transparent\';">';
                                        echo '<span class="dashicons ' . esc_attr($icon) . '" style="font-size: 32px; width: 32px; height: 32px;"></span>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <div style="text-align: right; margin-top: 20px;">
                                    <button type="button" class="button" id="kstb-close-icon-modal">キャンセル</button>
                                </div>
                            </div>
                        </div>

                        <div class="kstb-menu-assignments" style="margin-top: 30px;">
                            <h4>投稿タイプのメニュー設定</h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">投稿タイプ</th>
                                        <th style="width: 70%;">メニュー設定</th>
                                    </tr>
                                </thead>
                                <tbody id="kstb-menu-assignments-list">
                                    <?php foreach ($post_types as $post_type) : ?>
                                        <tr data-post-type-id="<?php echo esc_attr($post_type->id); ?>">
                                            <td>
                                                <strong><?php echo esc_html($post_type->label); ?></strong><br>
                                                <code><?php echo esc_html($post_type->slug); ?></code>
                                            </td>
                                            <td>
                                                <?php
                                                $current_mode = !empty($post_type->menu_display_mode) ? $post_type->menu_display_mode : 'category';
                                                $current_category = !empty($post_type->menu_parent_category) ? $post_type->menu_parent_category : '';
                                                ?>
                                                <select class="kstb-menu-mode-select" data-post-type-id="<?php echo esc_attr($post_type->id); ?>" style="width: 100%; max-width: 400px;">
                                                    <option value="toplevel" <?php echo ($current_mode === 'toplevel') ? 'selected' : ''; ?>>
                                                        トップレベルメニュー（個別表示）
                                                    </option>
                                                    <optgroup label="カテゴリー">
                                                        <?php
                                                        $all_categories = KSTB_Parent_Menu_Manager::get_all_categories();
                                                        foreach ($all_categories as $category) {
                                                            $selected = ($current_mode === 'category' && $current_category === $category) ? 'selected' : '';
                                                            echo '<option value="category:' . esc_attr($category) . '" ' . $selected . '>' . esc_html($category) . '</option>';
                                                        }
                                                        ?>
                                                    </optgroup>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="text-align: right; margin-top: 15px;">
                                <button type="button" class="button button-primary button-large" id="kstb-save-all-menu-btn">保存</button>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 説明書タブ -->
                <div id="kstb-main-tab-guide" class="kstb-main-tab-content">
                    <h3 style="margin-top: 10px;">💡 カスタム投稿タイプの作成ガイド</h3>

                    <h4>📝 必須項目</h4>
                    <ul style="margin-left: 20px;">
                        <li><strong>スラッグ</strong> <span class="required">* 必須</span> - URL に使用される一意の識別子（半角英数字とハイフン）</li>
                        <li><strong>ラベル</strong> <span class="required">* 必須</span> - 管理画面で表示される名前</li>
                    </ul>
                    <p style="margin-left: 20px;"><em>その他の項目はすべて任意です（デフォルト値が設定されています）</em></p>

                    <h4>🔧 主な設定項目</h4>
                    <ul style="margin-left: 20px;">
                        <li><strong>親ディレクトリ</strong> - 階層URL構造を作成（例：<code>/親ディレクトリ/スラッグ/</code>）</li>
                        <li><strong>アーカイブページ</strong> - 投稿一覧ページの表示を制御</li>
                        <li><strong>スラッグトップページ</strong> - アーカイブ無効時の表示内容を選択</li>
                        <li><strong>階層化</strong> - 投稿に親子関係を持たせる（固定ページと同様）</li>
                    </ul>

                    <h4>📋 ラベルの自動生成</h4>
                    <p style="margin-left: 20px;">
                        「ラベル」タブの各項目は、基本設定の「ラベル」から自動的に生成されます。<br>
                        通常は編集する必要はありませんが、より細かくカスタマイズしたい場合に使用できます。
                    </p>

                    <h4>🔄 記事移動機能について</h4>
                    <p style="margin-left: 20px;">
                        「記事移動」タブは、既存の投稿タイプを編集する際に表示されます。<br>
                        他の投稿タイプから記事を一括移動できますが、<strong>URLが変更される</strong>ため慎重に実行してください。
                    </p>

                    <h4>⚙️ 高度な設定</h4>
                    <ul style="margin-left: 20px;">
                        <li><strong>サポート機能</strong> - タイトル、エディター、アイキャッチ画像などの機能を選択</li>
                        <li><strong>タクソノミー</strong> - カテゴリーやタグとの関連付け</li>
                        <li><strong>メニュー位置</strong> - 管理画面メニューの表示順序（5〜100、デフォルト：25）</li>
                    </ul>

                    <p style="margin: 15px 20px 5px 20px; font-size: 13px; color: #666;">
                        詳しい使い方は <a href="https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types" target="_blank">GitHub リポジトリ</a> をご覧ください。
                    </p>
                </div>
            </div>
        </div>

        <div class="kstb-form-area" style="display: none;">
            <h2>
                <span class="kstb-form-title">新規カスタム投稿タイプ</span>
                <button type="button" class="kstb-close-button" title="閉じる">×</button>
            </h2>


            <form id="kstb-post-type-form">
                <input type="hidden" id="kstb-post-type-id" name="id" value="">

                <div class="kstb-tabs">
                    <ul class="kstb-tab-buttons">
                        <li><a href="#kstb-tab-basic" class="active">基本設定</a></li>
                        <li><a href="#kstb-tab-labels">ラベル（自動生成）</a></li>
                        <li><a href="#kstb-tab-settings">詳細設定</a></li>
                        <li><a href="#kstb-tab-supports">サポート機能</a></li>
                        <li><a href="#kstb-tab-taxonomies">タクソノミー</a></li>
                        <li><a href="#kstb-tab-post-mover">記事移動</a></li>
                    </ul>

                    <div id="kstb-tab-basic" class="kstb-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="kstb-url-slug">スラッグ <span class="required">* 必須</span></label></th>
                                <td>
                                    <input type="text" id="kstb-url-slug" name="url_slug" class="regular-text" required maxlength="64" title="半角英数字、ハイフン、アンダースコアのみ使用可能">
                                    <!-- 内部名は自動生成されるため非表示 -->
                                    <input type="hidden" id="kstb-slug" name="slug">
                                    <!-- 20文字超過警告 -->
                                    <div id="kstb-slug-warning" style="display: none; margin-top: 8px; padding: 8px 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">
                                        ⚠️ 20文字を超えるスラッグは、一部のプラグインやテーマで互換性の問題が発生する可能性があります。
                                    </div>
                                    <p class="description">
                                        <strong>URLで使用される識別子</strong><br>
                                        半角英数字、ハイフン、アンダースコアのみ（最大64文字）<br>
                                        例: company-introduction, news-archive, product-catalog など<br>
                                        <span style="color: #0073aa;"><strong>※ 実際のURL: https://example.com/このスラッグ/</strong></span>
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
                        </table>
                    </div>

                    <div id="kstb-tab-labels" class="kstb-tab-content">

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
                                            <input type="radio" name="slug_top_display" value="unspecified" checked>
                                            指定なし
                                        </label>
                                        <p class="description" style="margin-left: 24px;">WordPressのデフォルト動作に任せます。固定ページや通常投稿で一致するスラッグがあればそれを表示し、なければ404になります。</p>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label>
                                            <input type="radio" name="slug_top_display" value="none">
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
                                        <optgroup label="カスタム投稿タイプ">
                                            <?php
                                            // 他のカスタム投稿タイプを親として選択可能
                                            $other_post_types = KSTB_Database::get_all_post_types();
                                            foreach ($other_post_types as $other_type) {
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

                    <div id="kstb-tab-post-mover" class="kstb-tab-content">
                        <div class="kstb-post-mover-new-mode" style="display: none;">
                            <div class="notice notice-info">
                                <p>
                                    <strong>ℹ️ 記事移動機能について</strong><br>
                                    記事移動機能は、既存の投稿タイプを編集する際に使用できます。先にこの投稿タイプを保存してから、編集画面の「記事移動」タブをご利用ください。
                                </p>
                            </div>
                        </div>

                        <div class="kstb-post-mover-edit-mode" style="display: none;">
                            <div class="notice notice-warning" style="margin-bottom: 15px;">
                                <p>
                                    <strong>⚠️ 記事移動時の注意事項</strong><br>
                                    記事を移動すると<strong>URLが変更</strong>されます。SEOに影響する可能性があるため、慎重に実行してください。
                                </p>
                            </div>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="kstb-mover-source-type">移動元の投稿タイプ</label></th>
                                    <td>
                                        <select id="kstb-mover-source-type" class="regular-text">
                                            <option value="">選択してください</option>
                                            <option value="__all__">すべて</option>
                                            <?php
                                            $movable_types = KSTB_Post_Mover::get_instance()->get_movable_post_types();
                                            foreach ($movable_types as $slug => $label) {
                                                echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description">どの投稿タイプから記事を移動するか選択してください</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="kstb-mover-source-category">移動元のカテゴリ</label></th>
                                    <td>
                                        <select id="kstb-mover-source-category" class="regular-text" disabled>
                                            <option value="">カテゴリを選択してください</option>
                                        </select>
                                        <span class="spinner kstb-loading-spinner" id="kstb-category-spinner"></span>
                                        <p class="description">投稿タイプを選択すると、そのタイプに関連するカテゴリが表示されます</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">&nbsp;</th>
                                    <td>
                                        <button type="button" id="kstb-load-posts-btn" class="button">記事を読み込む</button>
                                        <span class="spinner kstb-loading-spinner" id="kstb-posts-spinner"></span>
                                        <p class="description" id="kstb-load-posts-hint" style="color: #999; margin-top: 5px;"></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div id="kstb-posts-list-container" style="display: none; margin-top: 20px;">
                            <h3>記事一覧 <span id="kstb-posts-count" style="font-size: 14px; font-weight: normal; color: #666;"></span></h3>

                            <div style="margin-bottom: 10px;">
                                <button type="button" id="kstb-select-all-posts" class="button">すべて選択</button>
                                <button type="button" id="kstb-deselect-all-posts" class="button">すべて解除</button>
                            </div>

                            <table class="wp-list-table widefat fixed striped" id="kstb-posts-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="check-column">
                                            <input type="checkbox" id="kstb-select-all-checkbox">
                                        </th>
                                        <th scope="col">タイトル</th>
                                        <th scope="col" id="kstb-post-type-header" style="display: none;">投稿タイプ</th>
                                        <th scope="col">ステータス</th>
                                        <th scope="col">日付</th>
                                        <th scope="col">作成者</th>
                                    </tr>
                                </thead>
                                <tbody id="kstb-posts-tbody">
                                </tbody>
                            </table>

                            <input type="hidden" id="kstb-mover-target-type" value="">

                            <div id="kstb-move-warnings" style="display: none; margin: 15px 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                            </div>

                            <p class="submit">
                                <button type="button" id="kstb-move-posts-btn" class="button button-primary">
                                    「<span id="kstb-target-post-type-label"></span>」へ選択した記事を移動
                                </button>
                                <span class="spinner kstb-loading-spinner" id="kstb-move-spinner"></span>
                                <span id="kstb-move-status" style="margin-left: 15px; font-weight: bold;"></span>
                            </p>
                        </div>
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
