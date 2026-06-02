<?php
/**
 * 百科全书后台管理页面
 * 
 * @since 9.0.0
 */

if (!defined('ABSPATH'))
    exit;

$repo = new \HLCC\Data\Repositories\WikiRepository();

// 当前标签页
$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'entries';

// 获取数据
$entries = $repo->get_all_entries(100, 0);
$requests = $repo->get_all_requests('pending', 50, 0);
?>

<div class="wrap">
    <h1>📚 自检百科全书</h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=hlcc-wiki&tab=entries" class="nav-tab <?php echo $tab === 'entries' ? 'nav-tab-active' : ''; ?>">
            词条管理 <span class="count">(
                <?php echo count($entries); ?>)
            </span>
        </a>
        <a href="?page=hlcc-wiki&tab=requests"
            class="nav-tab <?php echo $tab === 'requests' ? 'nav-tab-active' : ''; ?>">
            待处理请求 <span class="count">(
                <?php echo count($requests); ?>)
            </span>
        </a>
    </nav>

    <?php if ($tab === 'entries'): ?>
        <!-- 词条管理 -->
        <div class="hlcc-wiki-admin-section">
            <div class="hlcc-wiki-toolbar">
                <button type="button" class="button button-primary" id="hlcc-wiki-add-entry">+ 新增词条</button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30%">标题</th>
                        <th style="width:12%">分类</th>
                        <th style="width:40%">内容预览</th>
                        <th style="width:18%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;color:#666;">暂无词条，点击上方按钮新增</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <tr data-id="<?php echo esc_attr($entry['id']); ?>">
                                <td><strong>
                                        <?php echo esc_html($entry['title']); ?>
                                    </strong></td>
                                <td>
                                    <?php echo esc_html($repo->get_category_label($entry['category'])); ?>
                                </td>
                                <td>
                                    <?php echo esc_html(mb_substr(strip_tags($entry['content']), 0, 60) . '...'); ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small hlcc-wiki-edit-entry"
                                        data-id="<?php echo esc_attr($entry['id']); ?>">编辑</button>
                                    <button type="button" class="button button-small hlcc-wiki-delete-entry"
                                        data-id="<?php echo esc_attr($entry['id']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'requests'): ?>
        <!-- 客户请求 -->
        <div class="hlcc-wiki-admin-section">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50%">客户问题</th>
                        <th style="width:20%">提交时间</th>
                        <th style="width:30%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="3" style="text-align:center;color:#666;">暂无待处理请求</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr data-id="<?php echo esc_attr($req['id']); ?>">
                                <td>
                                    <?php echo esc_html($req['question']); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($req['created_at']); ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-primary button-small hlcc-wiki-create-from-request"
                                        data-id="<?php echo esc_attr($req['id']); ?>"
                                        data-question="<?php echo esc_attr($req['question']); ?>">创建词条</button>
                                    <button type="button" class="button button-small hlcc-wiki-dismiss-request"
                                        data-id="<?php echo esc_attr($req['id']); ?>">忽略</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="hlcc-wiki-entry-modal" style="display:none;">
    <div class="hlcc-wiki-modal-overlay"></div>
    <div class="hlcc-wiki-modal-content">
        <h2 id="hlcc-wiki-modal-title">新增词条</h2>
        <form id="hlcc-wiki-entry-form">
            <input type="hidden" name="entry_id" id="hlcc-wiki-entry-id" value="">
            <?php wp_nonce_field('hlcc_wiki_admin', 'hlcc_wiki_nonce'); ?>

            <p>
                <label for="hlcc-wiki-title">标题</label>
                <input type="text" id="hlcc-wiki-title" name="title" class="widefat" required>
            </p>

            <p>
                <label for="hlcc-wiki-keywords">关键词 (用逗号分隔)</label>
                <input type="text" id="hlcc-wiki-keywords" name="keywords" class="widefat" placeholder="例：皮秒,原理,爆破">
            </p>

            <p>
                <label for="hlcc-wiki-category">分类</label>
                <select id="hlcc-wiki-category" name="category" class="widefat">
                    <option value="general">基础知识</option>
                    <option value="symptom">症状判断</option>
                    <option value="care">护理方法</option>
                    <option value="faq">常见问题</option>
                    <option value="warning">注意事项</option>
                </select>
            </p>

            <p>
                <label for="hlcc-wiki-content">内容</label>
                <?php
                $content_value = '';
                wp_editor($content_value, 'hlcc-wiki-content-editor', [
                    'textarea_name' => 'content',
                    'media_buttons' => true,
                    'textarea_rows' => 12,
                    'teeny' => false,
                    'quicktags' => false,
                    'tinymce' => [
                        'toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | hr | removeformat',
                        'toolbar2' => '',
                        'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px',
                        'content_style' => 'body { font-size: 14px; line-height: 1.6; }',
                        // 确保换行生成段落
                        'forced_root_block' => 'p',
                        'force_p_newlines' => true,
                        'force_br_newlines' => false,
                        'remove_linebreaks' => false,
                        'convert_newlines_to_brs' => false,
                        // 段落间距
                        'wpautop' => true,
                    ],
                ]);
                ?>
            </p>

            <p>
                <label>配图（可选）</label>
                <input type="hidden" id="hlcc-wiki-image" name="image_url" value="">
            <div class="hlcc-wiki-image-field">
                <button type="button" class="button" id="hlcc-wiki-upload-btn">选择图片</button>
                <button type="button" class="button hlcc-wiki-remove-image" id="hlcc-wiki-remove-btn"
                    style="display:none;">移除图片</button>
            </div>
            <div id="hlcc-wiki-image-preview" class="hlcc-wiki-image-preview"></div>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary">保存</button>
                <button type="button" class="button hlcc-wiki-modal-close">取消</button>
            </p>
        </form>
    </div>
</div>

<style>
    .hlcc-wiki-admin-section {
        margin-top: 20px;
    }

    .hlcc-wiki-toolbar {
        margin-bottom: 15px;
    }

    .hlcc-wiki-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 100000;
    }

    .hlcc-wiki-modal-content {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        padding: 20px 25px;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        z-index: 100001;
        box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    }

    .hlcc-wiki-modal-content h2 {
        margin-top: 0;
    }

    .hlcc-wiki-modal-content label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .hlcc-wiki-modal-content p {
        margin-bottom: 15px;
    }

    .hlcc-wiki-image-field {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
    }

    .hlcc-wiki-image-preview {
        margin-top: 8px;
    }

    .hlcc-wiki-image-preview img {
        max-width: 200px;
        max-height: 150px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
</style>

<script>
    (function ($) {
        var $modal = $('#hlcc-wiki-entry-modal');
        var $form = $('#hlcc-wiki-entry-form');

        // 打开新增弹窗
        $('#hlcc-wiki-add-entry').on('click', function () {
            $('#hlcc-wiki-modal-title').text('新增词条');
            $('#hlcc-wiki-entry-id').val('');
            $form[0].reset();
            // 清空 TinyMCE 编辑器
            if (typeof tinymce !== 'undefined' && tinymce.get('hlcc-wiki-content-editor')) {
                tinymce.get('hlcc-wiki-content-editor').setContent('');
            }
            $modal.show();
        });

        // 关闭弹窗
        $('.hlcc-wiki-modal-close, .hlcc-wiki-modal-overlay').on('click', function () {
            $modal.hide();
        });

        // 编辑词条
        $(document).on('click', '.hlcc-wiki-edit-entry', function () {
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'hlcc_wiki_admin_get_entry',
                entry_id: id,
                nonce: $('#hlcc_wiki_nonce').val()
            }, function (res) {
                if (res.success) {
                    var e = res.data.entry;
                    $('#hlcc-wiki-modal-title').text('编辑词条');
                    $('#hlcc-wiki-entry-id').val(e.id);
                    $('#hlcc-wiki-title').val(e.title);
                    $('#hlcc-wiki-category').val(e.category);
                    $('#hlcc-wiki-keywords').val(e.keywords || '');
                    // 使用 TinyMCE API 设置内容
                    if (typeof tinymce !== 'undefined' && tinymce.get('hlcc-wiki-content-editor')) {
                        tinymce.get('hlcc-wiki-content-editor').setContent(e.content);
                    }
                    // 回填图片
                    if (e.image_url) {
                        $('#hlcc-wiki-image').val(e.image_url);
                        $('#hlcc-wiki-image-preview').html('<img src="' + e.image_url + '">');
                        $('#hlcc-wiki-remove-btn').show();
                    } else {
                        $('#hlcc-wiki-image').val('');
                        $('#hlcc-wiki-image-preview').html('');
                        $('#hlcc-wiki-remove-btn').hide();
                    }
                    $modal.show();
                }
            });
        });

        // 保存词条
        $form.on('submit', function (e) {
            e.preventDefault();

            // 手动同步 TinyMCE 内容到 textarea
            if (typeof tinymce !== 'undefined' && tinymce.get('hlcc-wiki-content-editor')) {
                tinymce.get('hlcc-wiki-content-editor').save();
            }

            var data = $(this).serialize();

            // 如果是从请求转来的，需要附加请求 ID
            var fromReq = $form.data('from-request');
            if (fromReq) {
                data += '&from_request_id=' + fromReq;
            }

            data += '&action=hlcc_wiki_admin_save_entry';

            var $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('保存中...');

            $.post(ajaxurl, data, function (res) {
                if (res.success) {
                    location.reload();
                } else {
                    $submitBtn.prop('disabled', false).text('保存');
                    alert(res.data.message || '保存失败');
                    console.error('Wiki save error:', res);
                }
            }).fail(function (xhr) {
                $submitBtn.prop('disabled', false).text('保存');
                alert('系统错误，请检查网络或控制台日志');
                console.error('AJAX error:', xhr);
            });
        });

        // 删除词条
        $(document).on('click', '.hlcc-wiki-delete-entry', function () {
            if (!confirm('确定删除此词条？')) return;
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'hlcc_wiki_admin_delete_entry',
                entry_id: id,
                nonce: $('#hlcc_wiki_nonce').val()
            }, function (res) {
                if (res.success) location.reload();
            });
        });

        // 忽略请求
        $(document).on('click', '.hlcc-wiki-dismiss-request', function () {
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'hlcc_wiki_admin_dismiss_request',
                request_id: id,
                nonce: $('#hlcc_wiki_nonce').val()
            }, function (res) {
                if (res.success) location.reload();
            });
        });

        // 从请求创建词条
        $(document).on('click', '.hlcc-wiki-create-from-request', function () {
            var question = $(this).data('question');
            $('#hlcc-wiki-modal-title').text('根据客户问题创建词条');
            $('#hlcc-wiki-entry-id').val('');
            $form[0].reset();
            $('#hlcc-wiki-title').val(question);
            $('#hlcc-wiki-category').val('faq');
            // 清空 TinyMCE 编辑器
            if (typeof tinymce !== 'undefined' && tinymce.get('hlcc-wiki-content-editor')) {
                tinymce.get('hlcc-wiki-content-editor').setContent('');
            }
            // 清空图片
            $('#hlcc-wiki-image').val('');
            $('#hlcc-wiki-image-preview').html('');
            $('#hlcc-wiki-remove-btn').hide();
            $modal.show();
            // 同时记录请求 ID，保存时自动标记为已处理
            $form.data('from-request', $(this).data('id'));
        });

        // WordPress Media Uploader
        var mediaFrame;
        $('#hlcc-wiki-upload-btn').on('click', function (e) {
            e.preventDefault();
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }
            mediaFrame = wp.media({
                title: '选择词条配图',
                button: { text: '使用这张图片' },
                multiple: false
            });
            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#hlcc-wiki-image').val(attachment.url);
                $('#hlcc-wiki-image-preview').html('<img src="' + attachment.url + '">');
                $('#hlcc-wiki-remove-btn').show();
            });
            mediaFrame.open();
        });

        // 移除图片
        $('#hlcc-wiki-remove-btn').on('click', function () {
            $('#hlcc-wiki-image').val('');
            $('#hlcc-wiki-image-preview').html('');
            $(this).hide();
        });

        // 新增时清空图片状态
        $('#hlcc-wiki-add-entry').on('click', function () {
            $('#hlcc-wiki-image').val('');
            $('#hlcc-wiki-image-preview').html('');
            $('#hlcc-wiki-remove-btn').hide();
        });

    })(jQuery);
</script>