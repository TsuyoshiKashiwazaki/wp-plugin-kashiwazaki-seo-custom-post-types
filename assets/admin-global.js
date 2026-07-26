/**
 * Kashiwazaki SEO Custom Post Types - Global Admin Script
 * 全管理画面で動作するスクリプト
 */
(function ($) {
    'use strict';

    // カスタム投稿タイプメニューの強制表示
    function forceShowCustomPostTypeMenus() {
        const adminMenu = document.getElementById('adminmenu');
        if (!adminMenu || !kstb_global || !kstb_global.custom_post_types) return;

        // admin_urlを安全に取得
        var adminUrl = kstb_global.admin_url || '/wp-admin/';

        // 既存のKSTB作成メニューをクリア
        const existingKstbMenus = adminMenu.querySelectorAll('li.kstb-custom-post-type');
        existingKstbMenus.forEach(function (menu) {
            menu.remove();
        });

        // KSTB作成のカスタム投稿タイプのメニューのみを安全に管理
        const currentSlugs = kstb_global.custom_post_types.map(pt => pt.slug);


        // 各カスタム投稿タイプのメニューを処理
        kstb_global.custom_post_types.forEach(function (postType) {
            // メニュー表示モードを確認（トップレベルの場合のみ処理）
            const menuDisplayMode = postType.menu_display_mode || 'category';

            // カテゴリーまたはカスタム親メニューの場合はスキップ
            if (menuDisplayMode === 'category' || menuDisplayMode === 'custom_parent') {
                return;
            }

            const menuClass = 'menu-icon-' + postType.slug;
            let menuItem = adminMenu.querySelector('li.' + menuClass + ':not(.kstb-custom-post-type)');

            // menu-iconクラスで見つからない場合は、URLで検索
            if (!menuItem) {
                const allMenuLinks = adminMenu.querySelectorAll('li.menu-top > a');
                for (const link of allMenuLinks) {
                    if (link.getAttribute('href') && link.getAttribute('href').includes('post_type=' + postType.slug)) {
                        menuItem = link.closest('li');
                        break;
                    }
                }
            }

            if (!menuItem) {
                // メニューが存在しない場合は作成
                const newMenuItem = document.createElement('li');
                newMenuItem.className = 'wp-has-submenu wp-not-current-submenu menu-top ' + menuClass + ' kstb-custom-post-type';

                const menuIcon = postType.menu_icon || 'dashicons-admin-post';
                // 前方一致では `dashicons-x" onmouseover="...` を通してしまうため、
                // dashicon として許される文字種に完全一致するものだけを採用する
                const dashiconClass = /^dashicons-[a-z0-9-]+$/.test(menuIcon) ? menuIcon : 'dashicons-admin-post';

                const menuName = postType.menu_name || postType.label;

                // innerHTML への文字列展開はやめ、DOM API で構築する
                // (属性値・テキストとも値の設定を DOM 側に任せ、注入経路を断つ)
                const listUrl = adminUrl + 'edit.php?post_type=' + encodeURIComponent(postType.slug);
                const newUrl = adminUrl + 'post-new.php?post_type=' + encodeURIComponent(postType.slug);

                const el = (tag, className) => {
                    const n = document.createElement(tag);
                    if (className) { n.className = className; }
                    return n;
                };

                const topLink = el('a', 'wp-has-submenu wp-not-current-submenu menu-top ' + menuClass);
                topLink.setAttribute('href', listUrl);
                topLink.setAttribute('aria-haspopup', 'true');

                const arrow = el('div', 'wp-menu-arrow');
                arrow.appendChild(el('div'));
                topLink.appendChild(arrow);

                const image = el('div', 'wp-menu-image dashicons-before ' + dashiconClass);
                image.setAttribute('aria-hidden', 'true');
                image.appendChild(el('br'));
                topLink.appendChild(image);

                const nameBox = el('div', 'wp-menu-name');
                nameBox.textContent = menuName;
                topLink.appendChild(nameBox);

                const submenu = el('ul', 'wp-submenu wp-submenu-wrap');

                const head = el('li', 'wp-submenu-head');
                head.setAttribute('aria-hidden', 'true');
                head.textContent = menuName;
                submenu.appendChild(head);

                const firstItem = el('li', 'wp-first-item');
                const firstLink = el('a', 'wp-first-item');
                firstLink.setAttribute('href', listUrl);
                firstLink.textContent = 'すべての' + postType.label;
                firstItem.appendChild(firstLink);
                submenu.appendChild(firstItem);

                const addItem = el('li');
                const addLink = el('a');
                addLink.setAttribute('href', newUrl);
                addLink.textContent = '新規追加';
                addItem.appendChild(addLink);
                submenu.appendChild(addItem);

                newMenuItem.appendChild(topLink);
                newMenuItem.appendChild(submenu);

                // メニュー位置に基づいて挿入場所を決定
                const menuPosition = parseInt(postType.menu_position) || 25;
                let insertBefore = null;

                // 適切な挿入位置を探す
                const menuItems = adminMenu.querySelectorAll('li.menu-top');
                for (let i = 0; i < menuItems.length; i++) {
                    const item = menuItems[i];
                    // メニューのdata-menu-positionやIDから位置を推測
                    if (item.id === 'menu-comments' && menuPosition < 30) {
                        insertBefore = item.nextSibling;
                        break;
                    } else if (item.id === 'menu-appearance' && menuPosition < 60) {
                        insertBefore = item;
                        break;
                    } else if (item.id === 'menu-plugins' && menuPosition < 65) {
                        insertBefore = item;
                        break;
                    } else if (item.id === 'menu-users' && menuPosition < 70) {
                        insertBefore = item;
                        break;
                    } else if (item.id === 'menu-tools' && menuPosition < 75) {
                        insertBefore = item;
                        break;
                    }
                }

                if (insertBefore) {
                    adminMenu.insertBefore(newMenuItem, insertBefore);
                } else {
                    // 適切な位置が見つからない場合は最後に追加
                    adminMenu.appendChild(newMenuItem);
                }


            } else {
                // メニューが存在する場合は表示を確実にする
                menuItem.style.display = 'block';
                menuItem.style.visibility = 'visible';
                menuItem.classList.remove('wp-hidden');
                menuItem.classList.remove('hidden');
                menuItem.classList.add('kstb-custom-post-type');
            }
        });

        // 現在のページがカスタム投稿タイプの場合、メニューをアクティブにする
        const currentUrl = window.location.href;
        const urlParams = new URLSearchParams(window.location.search);
        const postType = urlParams.get('post_type');

        if (postType) {
            const customPostType = kstb_global.custom_post_types.find(pt => pt.slug === postType);
            if (customPostType) {
                const menuClass = 'menu-icon-' + postType;
                const menuItem = adminMenu.querySelector('li.' + menuClass);

                if (menuItem) {
                    // 他のメニューからcurrentクラスを削除
                    adminMenu.querySelectorAll('.current').forEach(el => {
                        el.classList.remove('current');
                    });
                    adminMenu.querySelectorAll('.wp-has-current-submenu').forEach(el => {
                        el.classList.remove('wp-has-current-submenu');
                        el.classList.add('wp-not-current-submenu');
                    });

                    // 該当メニューにcurrentクラスを追加
                    menuItem.classList.add('current', 'wp-has-current-submenu');
                    menuItem.classList.remove('wp-not-current-submenu');

                    // サブメニューも開く
                    menuItem.classList.add('opensub');


                }
            }
        }
    }

    // DOMContentLoadedで実行
    document.addEventListener('DOMContentLoaded', forceShowCustomPostTypeMenus);

    // 念のため、少し遅れてもう一度実行
    setTimeout(forceShowCustomPostTypeMenus, 100);
    setTimeout(forceShowCustomPostTypeMenus, 500);

    // メニューが隠れないように定期的にチェック
    setInterval(function () {
        if (!kstb_global || !kstb_global.custom_post_types) return;

        kstb_global.custom_post_types.forEach(function (postType) {
            const menuClass = 'menu-icon-' + postType.slug;
            const $menu = $('#adminmenu li.' + menuClass);

            if ($menu.length && $menu.is(':hidden')) {
                $menu.show();

            }
        });
    }, 1000);

})(jQuery);
