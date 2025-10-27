(function ($) {
    'use strict';

    var KSTB = {
        init: function () {
            this.bindEvents();
            this.initIconSelect();
        },

        bindEvents: function () {
            $('.kstb-add-new-button').on('click', this.showNewForm);
            $('.kstb-edit-button').on('click', this.editPostType);
            $('.kstb-delete-button').on('click', this.deletePostType);
            $('.kstb-cancel-button').on('click', this.hideForm);
            $('.kstb-close-button').on('click', this.hideForm);
            $('#kstb-post-type-form').on('submit', this.savePostType);

            // メインタブ（一覧と説明書）
            $('.kstb-main-tab-buttons a').on('click', this.switchMainTab);

            // フォームタブ
            $('.kstb-tab-buttons a').on('click', this.switchTab);

            $(document).on('input', '#kstb-label', this.updateLabelPreview);
            $(document).on('input', '#kstb-url-slug', this.autoGenerateSlug);

            // Post Mover events - use delegated events for dynamic content
            $(document).on('click', '#kstb-load-posts-btn', function() { KSTB.loadPosts(); });
            $(document).on('change', '#kstb-select-all-checkbox', function() { KSTB.toggleAllPosts(); });
            $(document).on('click', '#kstb-select-all-posts', function() { KSTB.selectAllPosts(true); });
            $(document).on('click', '#kstb-deselect-all-posts', function() { KSTB.selectAllPosts(false); });
            $(document).on('change', '#kstb-mover-source-type', function() { KSTB.loadTaxonomies(); });
            $(document).on('change', '#kstb-mover-source-category', function() { KSTB.validateMove(); });
            $(document).on('click', '#kstb-move-posts-btn', function() { KSTB.movePosts(); });

            // Table sort
            $('.sortable').on('click', this.sortTable);
        },

        initIconSelect: function () {
            $('#kstb-menu-icon').on('change', function () {
                var icon = $(this).val();
                var $preview = $('.kstb-icon-preview');

                if (icon) {
                    $preview.html('<span class="dashicons ' + icon + '"></span>');
                } else {
                    $preview.html('');
                }
            });
        },

        showNewForm: function () {
            $('.kstb-form-title').text('新規カスタム投稿タイプ');
            $('#kstb-post-type-form')[0].reset();
            $('#kstb-post-type-id').val('');
            $('.kstb-icon-preview').html('');

            // プレビューもリセット
            $('.kstb-preview-field').text('（ラベルを入力すると表示されます）');
            $('#preview-add-new').text('新規追加');

            // 親ディレクトリの選択をリセット
            $('select[name="parent_directory"]').val('');

            // 記事移動タブを新規追加モードに設定
            KSTB.setPostMoverMode('new');

            $('.kstb-form-area').slideDown();
            $('.kstb-tab-buttons a:first').click();
        },

        hideForm: function () {
            $('.kstb-form-area').slideUp();
        },

        editPostType: function () {
            var id = $(this).data('id');

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'GET',
                data: {
                    action: 'kstb_get_post_type',
                    id: id,
                    nonce: kstb_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        KSTB.populateForm(response.data);
                        $('.kstb-form-title').text('編集: ' + response.data.label);
                        $('.kstb-form-area').slideDown();
                        $('.kstb-tab-buttons a:first').click();
                    } else {
                        KSTB.showNotice(response.data, 'error');
                    }
                }
            });
        },

        populateForm: function (data) {
            $('#kstb-post-type-id').val(data.id);
            $('#kstb-url-slug').val(data.url_slug || data.slug);
            $('#kstb-slug').val(data.slug);
            $('#kstb-label').val(data.label);
            $('#kstb-menu-icon').val(data.menu_icon).trigger('change');
            $('#kstb-menu-position').val(data.menu_position);

            // プレビューを更新
            KSTB.updateLabelPreview.call($('#kstb-label')[0]);

            // 記事移動タブを編集モードに設定（移動先として現在の投稿タイプを設定）
            KSTB.setPostMoverMode('edit', data.slug, data.label);



            $('input[name="public"]').prop('checked', data.public == 1);
            $('input[name="publicly_queryable"]').prop('checked', data.publicly_queryable == 1);
            $('input[name="show_ui"]').prop('checked', data.show_ui == 1);
            $('input[name="show_in_menu"]').prop('checked', data.show_in_menu == 1);
            $('input[name="query_var"]').prop('checked', data.query_var == 1);
            // スラッグトップページの設定
            var slugTopDisplay = 'unspecified';
            if (data.archive_display_type === 'default') {
                slugTopDisplay = 'unspecified';
            } else if (data.archive_display_type === 'none') {
                slugTopDisplay = 'none';
            } else if (data.has_archive == 1) {
                if (data.archive_display_type === 'custom_page' && data.archive_page_id) {
                    slugTopDisplay = 'page';
                } else {
                    slugTopDisplay = 'archive';
                }
            } else {
                slugTopDisplay = 'none';
            }
            $('input[name="slug_top_display"][value="' + slugTopDisplay + '"]').prop('checked', true);

            // 固定ページIDの設定
            if (data.archive_page_id) {
                $('#archive_page_id').val(data.archive_page_id);
            } else {
                $('#archive_page_id').val('');
            }

            // スラッグトップページ表示設定のトリガー
            $('input[name="slug_top_display"]:checked').trigger('change');
            
            // 親ディレクトリの設定
            var parentDir = data.parent_directory || '';
            if (parentDir) {
                // 選択肢にあるかチェック
                var $option = $('select[name="parent_directory"] option[value="' + parentDir + '"]');
                if ($option.length > 0) {
                    $('select[name="parent_directory"]').val(parentDir);
                }
            } else {
                $('select[name="parent_directory"]').val('');
            }

            $('input[name="hierarchical"]').prop('checked', data.hierarchical == 1);

            $('input[name="supports[]"]').prop('checked', false);
            if (data.supports) {
                $.each(data.supports, function (i, support) {
                    $('input[name="supports[]"][value="' + support + '"]').prop('checked', true);
                });
            }

            $('input[name="taxonomies[]"]').prop('checked', false);
            if (data.taxonomies) {
                $.each(data.taxonomies, function (i, taxonomy) {
                    $('input[name="taxonomies[]"][value="' + taxonomy + '"]').prop('checked', true);
                });
            }
        },

        savePostType: function (e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=kstb_save_post_type&nonce=' + kstb_ajax.nonce,
                success: function (response) {
                    if (response.success) {
                        KSTB.showNotice(response.data.message, 'success');

                        // カスタム投稿タイプ作成/更新後はページをリロード
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        KSTB.showNotice(response.data, 'error');
                    }
                }
            });
        },

        deletePostType: function () {
            var id = $(this).data('id');

            if (!confirm(kstb_ajax.labels.confirm_delete)) {
                return;
            }

            // 削除ボタンを一時的に無効化
            var $button = $(this);
            var originalText = $button.text();
            $button.prop('disabled', true).text('削除中...');

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kstb_delete_post_type',
                    id: id,
                    nonce: kstb_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        KSTB.showNotice(response.data.message, 'success');

                        // 削除されたメニューを即座に非表示にする
                        if (response.data.deleted_slug) {
                            const menuClass = 'menu-icon-' + response.data.deleted_slug;
                            const menuItem = document.querySelector('#adminmenu li.' + menuClass);
                            if (menuItem) {
                                menuItem.style.display = 'none';
                            }
                        }

                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        KSTB.showNotice(response.data, 'error');
                        // ボタンを再有効化
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function (xhr, status, error) {
                    KSTB.showNotice('AJAX エラー: ' + error, 'error');
                    // ボタンを再有効化
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        switchMainTab: function (e) {
            e.preventDefault();

            var target = $(this).attr('href');

            $('.kstb-main-tab-buttons a').removeClass('active');
            $(this).addClass('active');

            $('.kstb-main-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        switchTab: function (e) {
            e.preventDefault();

            var target = $(this).attr('href');

            $('.kstb-tab-buttons a').removeClass('active');
            $(this).addClass('active');

            $('.kstb-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        updateLabelPreview: function () {
            var label = $(this).val().trim();

            if (!label) {
                $('.kstb-preview-field').text('（ラベルを入力すると表示されます）');
                $('#preview-add-new').text('新規追加');
                return;
            }

            $('#preview-name').text(label);
            $('#preview-singular-name').text(label);
            $('#preview-menu-name').text(label);
            $('#preview-add-new').text('新規追加');
            $('#preview-add-new-item').text('新規' + label + 'を追加');
            $('#preview-edit-item').text(label + 'を編集');
            $('#preview-view-item').text(label + 'を表示');
            $('#preview-all-items').text('すべての' + label);
            $('#preview-search-items').text(label + 'を検索');
            $('#preview-not-found').text(label + 'が見つかりません');
        },

        autoGenerateSlug: function () {
            var urlSlug = $(this).val().trim();
            var $slugField = $('#kstb-slug');
            var $warning = $('#kstb-slug-warning');

            if (!urlSlug) {
                $slugField.val('');
                $warning.hide();
                return;
            }

            // 20文字を超えたら警告を表示、以下なら非表示
            if (urlSlug.length > 20) {
                $warning.slideDown(200);
            } else {
                $warning.slideUp(200);
            }

            // URLスラッグから短縮名を自動生成（常に自動生成）
            var shortSlug = urlSlug;
            if (urlSlug.length > 20) {
                // 20文字まで切り詰め
                shortSlug = urlSlug.substring(0, 20);
                // 最後のハイフン以降を削除して綺麗にする
                var lastHyphen = shortSlug.lastIndexOf('-');
                if (lastHyphen > 10) {
                    shortSlug = shortSlug.substring(0, lastHyphen);
                }
            }

            $slugField.val(shortSlug);
        },


        showNotice: function (message, type) {
            var $notice = $('<div class="kstb-notice kstb-notice-' + type + '">' + message + '</div>');
            $('#kstb-notice-area').html($notice);

            $notice.hide().fadeIn();

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        },

        // Post Mover functions
        setPostMoverMode: function (mode, targetSlug, targetLabel) {
            if (mode === 'new') {
                // 新規追加モード：記事移動機能を無効化
                $('.kstb-post-mover-new-mode').show();
                $('.kstb-post-mover-edit-mode').hide();
                $('#kstb-posts-list-container').hide();
                $('#kstb-mover-target-type').val('');
            } else if (mode === 'edit') {
                // 編集モード：移動先を現在の投稿タイプに設定
                $('.kstb-post-mover-new-mode').hide();
                $('.kstb-post-mover-edit-mode').show();
                $('#kstb-current-post-type-label').text(targetLabel);
                $('#kstb-target-post-type-label').text(targetLabel);
                $('#kstb-mover-target-type').val(targetSlug);

                // 移動元のドロップダウンから現在の投稿タイプを除外
                $('#kstb-mover-source-type option').each(function () {
                    if ($(this).val() === targetSlug) {
                        $(this).prop('disabled', true).text($(this).text() + ' （現在編集中）');
                    } else {
                        $(this).prop('disabled', false);
                        var text = $(this).text().replace(' （現在編集中）', '');
                        $(this).text(text);
                    }
                });
            }
        },

        loadTaxonomies: function () {
            var sourceType = $('#kstb-mover-source-type').val();
            var $categorySelect = $('#kstb-mover-source-category');
            var $loadHint = $('#kstb-load-posts-hint');

            // 投稿タイプが未選択の場合
            if (!sourceType) {
                $categorySelect.prop('disabled', true).html('<option value="">カテゴリを選択してください</option>');
                $('#kstb-load-posts-btn').prop('disabled', true);
                $loadHint.text('');
                KSTB.validateMove();
                return;
            }

            // 「すべて」の場合はカテゴリ選択を必須にする
            if (sourceType === '__all__') {
                $loadHint.text('※ カテゴリを選択してください');
                $('#kstb-load-posts-btn').prop('disabled', true);
            } else {
                $loadHint.text('');
                $('#kstb-load-posts-btn').prop('disabled', false);
            }

            // タクソノミーを読み込む
            $categorySelect.prop('disabled', true).html('<option value="">読み込み中...</option>');
            $('#kstb-category-spinner').addClass('is-active');

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kstb_get_taxonomies_by_type',
                    post_type: sourceType,
                    nonce: kstb_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var options = '<option value="">カテゴリを選択してください</option>';

                        if (response.data.taxonomies.length === 0) {
                            options = '<option value="">カテゴリがありません</option>';
                            $categorySelect.html(options).prop('disabled', true);
                        } else {
                            // タクソノミーごとにグループ化
                            var grouped = {};
                            $.each(response.data.taxonomies, function (i, item) {
                                if (!grouped[item.taxonomy_label]) {
                                    grouped[item.taxonomy_label] = [];
                                }
                                grouped[item.taxonomy_label].push(item);
                            });

                            // optgroupで表示
                            $.each(grouped, function (taxLabel, items) {
                                options += '<optgroup label="' + taxLabel + '">';
                                $.each(items, function (i, item) {
                                    options += '<option value="' + item.taxonomy + '|' + item.term_id + '">' +
                                        item.term_name + ' (' + item.count + ')</option>';
                                });
                                options += '</optgroup>';
                            });

                            $categorySelect.html(options).prop('disabled', false);
                        }
                    } else {
                        $categorySelect.html('<option value="">読み込みに失敗しました</option>').prop('disabled', true);
                    }
                    $('#kstb-category-spinner').removeClass('is-active');
                },
                error: function () {
                    $categorySelect.html('<option value="">読み込みに失敗しました</option>').prop('disabled', true);
                    $('#kstb-category-spinner').removeClass('is-active');
                }
            });

            KSTB.validateMove();
        },

        loadPosts: function () {
            var sourceType = $('#kstb-mover-source-type').val();
            var categoryValue = $('#kstb-mover-source-category').val();

            if (!sourceType) {
                alert('移動元の投稿タイプを選択してください');
                return;
            }

            // 「すべて」の場合はカテゴリ必須
            if (sourceType === '__all__' && !categoryValue) {
                alert('カテゴリを選択してください');
                return;
            }

            $('#kstb-load-posts-btn').prop('disabled', true).text('読み込み中...');
            $('#kstb-posts-spinner').addClass('is-active');

            var ajaxData = {
                action: 'kstb_get_posts_by_type',
                post_type: sourceType,
                nonce: kstb_ajax.nonce
            };

            // カテゴリフィルタを追加
            if (categoryValue) {
                var parts = categoryValue.split('|');
                if (parts.length === 2) {
                    ajaxData.taxonomy = parts[0];
                    ajaxData.term_id = parts[1];
                }
            }

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function (response) {
                    if (response.success) {
                        KSTB.displayPosts(response.data.posts, response.data.count);
                        $('#kstb-posts-list-container').slideDown();
                        $('#kstb-move-status').text('');
                    } else {
                        alert(response.data || '記事の読み込みに失敗しました');
                    }
                    $('#kstb-load-posts-btn').prop('disabled', false).text('記事を読み込む');
                    $('#kstb-posts-spinner').removeClass('is-active');
                },
                error: function (xhr, status, error) {
                    alert('記事の読み込みに失敗しました');
                    $('#kstb-load-posts-btn').prop('disabled', false).text('記事を読み込む');
                    $('#kstb-posts-spinner').removeClass('is-active');
                }
            });
        },

        displayPosts: function (posts, count) {
            var $tbody = $('#kstb-posts-tbody');
            $tbody.empty();

            var sourceType = $('#kstb-mover-source-type').val();
            var showPostType = (sourceType === '__all__');

            // 投稿タイプ列の表示/非表示
            if (showPostType) {
                $('#kstb-post-type-header').show();
            } else {
                $('#kstb-post-type-header').hide();
            }

            var colspan = showPostType ? 6 : 5;

            if (posts.length === 0) {
                $tbody.append('<tr><td colspan="' + colspan + '" style="text-align: center;">記事が見つかりませんでした</td></tr>');
                $('#kstb-posts-count').text('');
                return;
            }

            $('#kstb-posts-count').text('（' + count + '件）');

            $.each(posts, function (i, post) {
                var statusLabels = {
                    'publish': '公開',
                    'draft': '下書き',
                    'pending': '承認待ち',
                    'future': '予約',
                    'private': '非公開'
                };
                var statusLabel = statusLabels[post.status] || post.status;

                var row = '<tr>' +
                    '<th scope="row" class="check-column">' +
                    '<input type="checkbox" class="kstb-post-checkbox" value="' + post.ID + '">' +
                    '</th>' +
                    '<td><strong>' + post.title + '</strong></td>';

                // 投稿タイプ列を追加（「すべて」の場合のみ）
                if (showPostType) {
                    row += '<td>' + (post.post_type || '') + '</td>';
                }

                row += '<td>' + statusLabel + '</td>' +
                    '<td>' + post.date + '</td>' +
                    '<td>' + post.author + '</td>' +
                    '</tr>';
                $tbody.append(row);
            });
        },

        toggleAllPosts: function () {
            var checked = $('#kstb-select-all-checkbox').prop('checked');
            $('.kstb-post-checkbox').prop('checked', checked);
        },

        selectAllPosts: function (select) {
            $('.kstb-post-checkbox').prop('checked', select);
            $('#kstb-select-all-checkbox').prop('checked', select);
        },

        validateMove: function () {
            var sourceType = $('#kstb-mover-source-type').val();
            var categoryValue = $('#kstb-mover-source-category').val();
            var targetType = $('#kstb-mover-target-type').val();

            // 「すべて」選択時はカテゴリが選択されるまで読み込みボタンを無効化
            if (sourceType === '__all__') {
                if (categoryValue) {
                    $('#kstb-load-posts-btn').prop('disabled', false);
                } else {
                    $('#kstb-load-posts-btn').prop('disabled', true);
                }
            } else if (sourceType) {
                $('#kstb-load-posts-btn').prop('disabled', false);
            } else {
                $('#kstb-load-posts-btn').prop('disabled', true);
            }

            if (!sourceType || !targetType) {
                $('#kstb-move-warnings').hide();
                return;
            }

            if (sourceType === targetType) {
                $('#kstb-move-warnings')
                    .html('<strong>⚠️ 警告:</strong> 移動元と移動先が同じです。別の投稿タイプを選択してください。')
                    .show();
                return;
            }

            $('#kstb-move-warnings')
                .html('<strong>⚠️ 注意:</strong> 記事を移動すると、URLが変更されます。SEOに影響する可能性があります。')
                .show();
        },

        movePosts: function () {
            var sourceType = $('#kstb-mover-source-type').val();
            var targetType = $('#kstb-mover-target-type').val();
            var targetLabel = $('#kstb-target-post-type-label').text();
            var selectedPosts = [];

            $('.kstb-post-checkbox:checked').each(function () {
                selectedPosts.push($(this).val());
            });

            if (!sourceType) {
                alert('移動元の投稿タイプを選択してください');
                return;
            }

            if (!targetType) {
                alert('エラー: 移動先の投稿タイプが設定されていません');
                return;
            }

            if (sourceType === targetType) {
                alert('移動元と移動先が同じです。別の投稿タイプを選択してください。');
                return;
            }

            if (selectedPosts.length === 0) {
                alert('移動する記事を選択してください');
                return;
            }

            var confirmMessage = selectedPosts.length + '件の記事を「' + targetLabel + '」へ移動します。\n' +
                '移動後はURLが変更されます。よろしいですか？';

            if (!confirm(confirmMessage)) {
                return;
            }

            $('#kstb-move-posts-btn').prop('disabled', true);
            $('#kstb-move-spinner').addClass('is-active');
            $('#kstb-move-status').text('移動中...').css('color', '#0073aa');

            $.ajax({
                url: kstb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kstb_move_posts',
                    post_ids: selectedPosts,
                    from_type: sourceType,
                    to_type: targetType,
                    nonce: kstb_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var message = response.data.message || response.data;
                        $('#kstb-move-status')
                            .text(message)
                            .css('color', 'green');

                        // 成功後、記事リストを再読み込み
                        setTimeout(function () {
                            KSTB.loadPosts();
                        }, 1500);
                    } else {
                        $('#kstb-move-status')
                            .text('エラー: ' + (response.data || '移動に失敗しました'))
                            .css('color', 'red');
                    }
                    $('#kstb-move-posts-btn').prop('disabled', false);
                    $('#kstb-move-spinner').removeClass('is-active');
                },
                error: function () {
                    $('#kstb-move-status')
                        .text('エラー: 移動に失敗しました')
                        .css('color', 'red');
                    $('#kstb-move-posts-btn').prop('disabled', false);
                    $('#kstb-move-spinner').removeClass('is-active');
                }
            });
        },

    };

    $(document).ready(function () {
        KSTB.init();

        // スラッグトップページ設定のイベントハンドラー（委譲方式）
        $(document).on('change', 'input[name="slug_top_display"]', function() {
            if ($(this).val() === 'page') {
                $('#custom_page_selector').show();
            } else {
                $('#custom_page_selector').hide();
            }
        });

        // Table sorting
        $('.sortable').on('click', function() {
            var $th = $(this);
            var sortKey = $th.data('sort');
            var $tbody = $th.closest('table').find('tbody');
            var rows = $tbody.find('tr').get();

            // Determine sort direction
            var isAsc = $th.hasClass('sorted-asc');
            var direction = isAsc ? -1 : 1;

            // Remove all sort indicators
            $th.closest('tr').find('th').removeClass('sorted-asc sorted-desc');
            $th.closest('tr').find('.sort-indicator').text('');

            // Add sort indicator
            if (isAsc) {
                $th.addClass('sorted-desc');
                $th.find('.sort-indicator').text('▼');
            } else {
                $th.addClass('sorted-asc');
                $th.find('.sort-indicator').text('▲');
            }

            // Sort rows
            rows.sort(function(a, b) {
                var aVal = $(a).data(sortKey);
                var bVal = $(b).data(sortKey);

                // Handle numeric values (post-count)
                if (sortKey === 'post-count') {
                    aVal = parseInt(aVal) || 0;
                    bVal = parseInt(bVal) || 0;
                    return (aVal - bVal) * direction;
                }

                // Handle string values
                aVal = String(aVal || '').toLowerCase();
                bVal = String(bVal || '').toLowerCase();

                if (aVal < bVal) return -1 * direction;
                if (aVal > bVal) return 1 * direction;
                return 0;
            });

            // Reorder DOM
            $.each(rows, function(index, row) {
                $tbody.append(row);
            });
        });

    });

})(jQuery);
