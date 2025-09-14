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
            $('#kstb-post-type-form').on('submit', this.savePostType);


            $('.kstb-tab-buttons a').on('click', this.switchTab);

            $('#kstb-rewrite-enabled').on('change', this.toggleRewriteOptions);
            $('input[name="show_in_rest"]').on('change', this.toggleRestOptions);

            $(document).on('input', '#kstb-label', this.updateLabelPreview);
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
            $('#kstb-slug').val(data.slug);
            $('#kstb-label').val(data.label);
            $('#kstb-menu-icon').val(data.menu_icon).trigger('change');
            $('#kstb-menu-position').val(data.menu_position);

            // プレビューを更新
            KSTB.updateLabelPreview.call($('#kstb-label')[0]);



            $('input[name="public"]').prop('checked', data.public == 1);
            $('input[name="publicly_queryable"]').prop('checked', data.publicly_queryable == 1);
            $('input[name="show_ui"]').prop('checked', data.show_ui == 1);
            $('input[name="show_in_menu"]').prop('checked', data.show_in_menu == 1);
            $('input[name="query_var"]').prop('checked', data.query_var == 1);
            // スラッグトップページの設定
            var slugTopDisplay = 'none';
            if (data.has_archive == 1) {
                if (data.archive_display_type === 'custom_page' && data.archive_page_id) {
                    slugTopDisplay = 'page';
                } else {
                    slugTopDisplay = 'archive';
                }
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
            $('input[name="show_in_rest"]').prop('checked', data.show_in_rest == 1);
            $('input[name="capability_type"]').val(data.capability_type);
            $('input[name="rest_base"]').val(data.rest_base);

            $('input[name="rewrite[enabled]"]').prop('checked', data.rewrite !== false);
            if (data.rewrite) {
                $('input[name="rewrite[slug]"]').val(data.rewrite.slug);
                $('input[name="rewrite[with_front]"]').prop('checked', data.rewrite.with_front);
            }
            $('#kstb-rewrite-enabled').trigger('change');
            $('input[name="show_in_rest"]').trigger('change');

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

        switchTab: function (e) {
            e.preventDefault();

            var target = $(this).attr('href');

            $('.kstb-tab-buttons a').removeClass('active');
            $(this).addClass('active');

            $('.kstb-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        toggleRewriteOptions: function () {
            if ($(this).is(':checked')) {
                $('.kstb-rewrite-options').slideDown();
            } else {
                $('.kstb-rewrite-options').slideUp();
            }
        },

        toggleRestOptions: function () {
            if ($(this).is(':checked')) {
                $('.kstb-rest-options').slideDown();
            } else {
                $('.kstb-rest-options').slideUp();
            }
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


    });

})(jQuery);
