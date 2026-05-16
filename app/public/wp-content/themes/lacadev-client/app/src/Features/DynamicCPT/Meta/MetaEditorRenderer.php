<?php

namespace App\Features\DynamicCPT\Meta;

class MetaEditorRenderer
{
    public function render(
        string $slug,
        array $cpt,
        string $code,
        string $filePath,
        bool $exists,
        string $pageUrl,
        string $message,
        mixed $codeMirrorSettings,
        string $nonceAction,
        string $nonceField
    ): void {
        $relativePath = $exists
            ? str_replace(ABSPATH, '/', $filePath)
            : __('(chưa tạo — sẽ được tạo khi lưu lần đầu)', 'laca');
        ?>
        <div class="wrap laca-cpt-wrap">
            <?php $this->renderStyles(); ?>
            <div class="laca-cpt-header">
                <div>
                    <a href="<?php echo esc_url($pageUrl); ?>" class="laca-back-link">
                        ← <?php esc_html_e('Danh sách CPT', 'laca'); ?>
                    </a>
                    <h1>
                        <?php esc_html_e('Meta Fields', 'laca'); ?> —
                        <?php echo esc_html((string) ($cpt['singular'] ?? $slug)); ?>
                        <code class="laca-card-slug"><?php echo esc_html($slug); ?></code>
                    </h1>
                    <p class="laca-cpt-subtitle"><?php echo esc_html($relativePath); ?></p>
                </div>
            </div>

            <?php if ($message) : ?>
                <div class="laca-notice laca-notice--success"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <div class="laca-meta-layout">
                <div class="laca-meta-builder laca-panel">
                    <div class="laca-panel-head">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('Field Builder', 'laca'); ?>
                        <small><?php esc_html_e('Generate code nhanh → tinh chỉnh bên phải', 'laca'); ?></small>
                    </div>

                    <div class="laca-panel-body">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="laca_cpt_meta_generate">
                            <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($slug); ?>">
                            <?php wp_nonce_field($nonceAction, $nonceField); ?>

                            <div class="laca-field">
                                <label for="laca-container-title"><?php esc_html_e('Tiêu đề container', 'laca'); ?></label>
                                <input type="text" id="laca-container-title" name="container_title"
                                       value="<?php echo esc_attr('Thông tin ' . ((string) ($cpt['singular'] ?? $slug))); ?>"
                                       placeholder="vd: Thông tin dự án">
                            </div>

                            <div id="laca-fields-list"></div>

                            <div class="laca-builder-footer">
                                <button type="button" id="laca-add-field" class="laca-btn-add">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e('Thêm field', 'laca'); ?>
                                </button>
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Generate Code →', 'laca'); ?>
                                </button>
                            </div>
                            <p class="laca-warn-note">
                                ⚠ <?php esc_html_e('Generate sẽ ghi đè toàn bộ code editor.', 'laca'); ?>
                            </p>
                        </form>
                    </div>
                </div>

                <div class="laca-meta-code-col laca-panel">
                    <div class="laca-panel-head">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Code Editor', 'laca'); ?>
                        <small><?php esc_html_e('Full Carbon Fields API — lưu trực tiếp vào file PHP', 'laca'); ?></small>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="laca_cpt_meta_save">
                        <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($slug); ?>">
                        <?php wp_nonce_field($nonceAction, $nonceField); ?>

                        <textarea id="laca-meta-code" name="meta_code"><?php echo esc_textarea($code); ?></textarea>

                        <div class="laca-code-footer">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Lưu Code', 'laca'); ?>
                            </button>
                            <span class="laca-field-note">
                                <?php echo $exists ? esc_html__('Ghi đè file hiện có', 'laca') : esc_html__('Tạo file mới', 'laca'); ?>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            <?php if (!empty($codeMirrorSettings)) : ?>
            wp.codeEditor.initialize($('#laca-meta-code'), <?php echo wp_json_encode($codeMirrorSettings); ?>);
            <?php endif; ?>

            var TYPES = [
                { v: 'text', l: 'Text' },
                { v: 'textarea', l: 'Textarea' },
                { v: 'rich_text', l: 'Rich Text' },
                { v: 'image', l: 'Image' },
                { v: 'file', l: 'File' },
                { v: 'select', l: 'Select' },
                { v: 'checkbox', l: 'Checkbox' },
                { v: 'date', l: 'Date' },
                { v: 'color', l: 'Color' },
            ];
            var PLACEHOLDER_TYPES = ['text', 'textarea', 'date'];
            var HAS_ROWS = ['textarea'];
            var HAS_OPTIONS = ['select'];
            var HAS_OPTION_VALUE = ['checkbox'];
            var HAS_VALUE_TYPE = ['image', 'file'];
            var HAS_FILE_TYPE = ['file'];
            var HAS_COLOR_OPTS = ['color'];
            var HAS_STORAGE_FORMAT = ['date'];
            var typeOpts = TYPES.map(function (type) {
                return '<option value="' + type.v + '">' + type.l + '</option>';
            }).join('');
            var idx = 0;

            function n(i, key) { return 'meta_fields[' + i + '][' + key + ']'; }
            function inp(i, key, placeholder, cls) {
                cls = cls || '';
                return '<input type="text" name="' + n(i, key) + '" placeholder="' + placeholder + '" class="lbf-' + key + ' ' + cls + '">';
            }

            function addRow() {
                var i = idx++;
                var $block = $('<div class="lbf-block" data-idx="' + i + '">');
                var $main = $('<div class="lbf-main-row">').appendTo($block);

                $main.append(
                    '<input type="text"   name="' + n(i,'name')  + '" placeholder="field_name" class="lbf-name">',
                    '<input type="text"   name="' + n(i,'label') + '" placeholder="Label"      class="lbf-label">',
                    $('<select name="' + n(i,'type') + '" class="lbf-type">').append(typeOpts),
                    '<input type="number" name="' + n(i,'width') + '" value="100" min="1" max="100" class="lbf-width" title="Width%">',
                    '<button type="button" class="lbf-toggle-opts" title="Options">⚙</button>',
                    '<button type="button" class="lbf-remove" title="Xóa">✕</button>'
                );

                var $opts = $('<div class="lbf-opts-panel" style="display:none">').appendTo($block);
                var $row1 = $('<div class="lbf-opts-row">').appendTo($opts);
                $row1.append(
                    $('<label class="lbf-opt-group lbf-group-placeholder">').html('<span>Placeholder</span>' + inp(i, 'placeholder', 'Nhập placeholder...')),
                    $('<label class="lbf-opt-group">').html('<span>Default value</span>' + inp(i, 'default_value', 'Giá trị mặc định')),
                    $('<label class="lbf-opt-group">').html('<span>Help text</span>' + inp(i, 'help_text', 'Mô tả hiển thị dưới field')),
                    $('<label class="lbf-opt-group lbf-opt-inline">').html('<input type="checkbox" name="' + n(i,'required') + '" value="1"><span>Required</span>')
                );

                var $row2 = $('<div class="lbf-opts-row lbf-opts-row--specific">').appendTo($opts);
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-rows" style="display:none">').html('<span>Rows</span><input type="number" name="' + n(i,'rows') + '" value="5" min="1" max="50" class="lbf-rows">'),
                    $('<label class="lbf-opt-group lbf-group-options" style="display:none">').html('<span>Options <em>(value|Label, mỗi dòng 1 option)</em></span><textarea name="' + n(i,'options') + '" rows="4" placeholder="published|Đã xuất bản\ndraft|Nháp" class="lbf-options"></textarea>'),
                    $('<label class="lbf-opt-group lbf-group-option_value" style="display:none">').html('<span>Option value <em>(giá trị khi checked)</em></span>' + inp(i, 'option_value', 'vd: 1')),
                    $('<label class="lbf-opt-group lbf-group-value_type" style="display:none">').html('<span>Lưu dưới dạng</span><select name="' + n(i,'value_type') + '" class="lbf-value_type"><option value="id">ID (khuyến nghị)</option><option value="url">URL</option></select>'),
                    $('<label class="lbf-opt-group lbf-group-file_type" style="display:none">').html('<span>File type <em>(image/audio/video/pdf...)</em></span>' + inp(i, 'file_type', 'vd: image')),
                    $('<label class="lbf-opt-group lbf-opt-inline lbf-group-alpha_enabled" style="display:none">').html('<input type="checkbox" name="' + n(i,'alpha_enabled') + '" value="1"><span>Alpha (opacity)</span>'),
                    $('<label class="lbf-opt-group lbf-group-palette" style="display:none">').html('<span>Palette <em>(hex, cách nhau bằng dấu phẩy)</em></span>' + inp(i, 'palette', '#ffffff,#000000,#ff0000')),
                    $('<label class="lbf-opt-group lbf-group-storage_format" style="display:none">').html('<span>Storage format <em>(mặc định: Y-m-d)</em></span>' + inp(i, 'storage_format', 'Y-m-d'))
                );

                var $typeSelect = $main.find('.lbf-type');
                function syncTypeOpts(type) {
                    $opts.find('.lbf-group-placeholder').toggle(PLACEHOLDER_TYPES.includes(type));
                    $opts.find('.lbf-group-rows').toggle(HAS_ROWS.includes(type));
                    $opts.find('.lbf-group-options').toggle(HAS_OPTIONS.includes(type));
                    $opts.find('.lbf-group-option_value').toggle(HAS_OPTION_VALUE.includes(type));
                    $opts.find('.lbf-group-value_type').toggle(HAS_VALUE_TYPE.includes(type));
                    $opts.find('.lbf-group-file_type').toggle(HAS_FILE_TYPE.includes(type));
                    $opts.find('.lbf-group-alpha_enabled, .lbf-group-palette').toggle(HAS_COLOR_OPTS.includes(type));
                    $opts.find('.lbf-group-storage_format').toggle(HAS_STORAGE_FORMAT.includes(type));
                }

                $typeSelect.on('change', function () { syncTypeOpts(this.value); });
                syncTypeOpts($typeSelect.val());

                $main.find('.lbf-toggle-opts').on('click', function () {
                    $opts.toggle();
                    $(this).toggleClass('lbf-toggle-opts--active');
                });
                $main.find('.lbf-remove').on('click', function () {
                    $block.remove();
                });
                $('#laca-fields-list').append($block);
            }

            $('#laca-add-field').on('click', addRow);
            addRow();
        });
        </script>
        <?php
    }

    private function renderStyles(): void
    {
        ?>
        <style>
        .laca-notice { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
        .laca-notice--success { background: #edfaef; border-left: 3px solid #00a32a; color: #1d7a34; }
        .laca-field-note { font-size: 11px; color: #646970; }
        .laca-card-slug { font-size: 11px; font-family: Consolas, monospace; background: #f0f0f1; border: 1px solid #e2e4e7; border-radius: 3px; padding: 1px 5px; color: #50575e; font-weight: 400; }
        .laca-cpt-header { margin-bottom: 20px; }
        .laca-cpt-header h1 { margin: 4px 0; font-size: 20px; font-weight: 600; color: #1d2327; }
        .laca-cpt-subtitle { margin: 0; color: #646970; font-size: 12px; }
        .laca-back-link { display: inline-block; margin-bottom: 6px; font-size: 13px; color: #646970; text-decoration: none; }
        .laca-back-link:hover { color: #2271b1; }
        .laca-meta-layout { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
        .laca-meta-builder { flex: 0 0 480px; min-width: 0; }
        .laca-meta-code-col { flex: 1 1 500px; min-width: 0; }
        .laca-panel { background: #fff; border: 1px solid #e2e4e7; border-radius: 6px; overflow: hidden; }
        .laca-panel-head { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #e2e4e7; font-size: 13px; font-weight: 600; color: #1d2327; }
        .laca-panel-head .dashicons { color: #2271b1; font-size: 18px; width: 18px; height: 18px; }
        .laca-panel-head small { font-weight: 400; color: #8c8f94; font-size: 12px; margin-left: 4px; }
        .laca-panel-body { padding: 16px; }
        .laca-field { margin-bottom: 14px; }
        .laca-field label { display: block; font-size: 12px; font-weight: 600; color: #3c434a; margin-bottom: 5px; }
        .laca-field input[type=text] { width: 100%; box-sizing: border-box; height: 32px; padding: 0 8px; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 13px; }
        .lbf-block { border: 1px solid #e2e4e7; border-radius: 5px; margin-bottom: 8px; overflow: hidden; }
        .lbf-main-row { display: flex; align-items: center; gap: 5px; padding: 6px 8px; background: #f9fafb; }
        .lbf-main-row input[type=text], .lbf-main-row select { height: 28px; padding: 0 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; box-sizing: border-box; }
        .lbf-name { flex: 1.2; min-width: 0; }
        .lbf-label { flex: 1.4; min-width: 0; }
        .lbf-type { flex: 1; min-width: 0; }
        .lbf-width { width: 52px !important; flex: none; }
        .lbf-toggle-opts, .lbf-remove { background: none; border: 1px solid #e2e4e7; border-radius: 3px; cursor: pointer; padding: 3px 6px; font-size: 12px; line-height: 1; color: #646970; transition: all .1s; }
        .lbf-toggle-opts:hover { background: #f0f6fc; border-color: #2271b1; color: #2271b1; }
        .lbf-toggle-opts--active { background: #f0f6fc; border-color: #2271b1; color: #2271b1; }
        .lbf-remove:hover { background: #fdf3f3; border-color: #f5b8b8; color: #d63638; }
        .lbf-opts-panel { padding: 10px 12px; border-top: 1px dashed #e2e4e7; background: #fff; }
        .lbf-opts-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
        .lbf-opts-row:last-child { margin-bottom: 0; }
        .lbf-opt-group { display: flex; flex-direction: column; gap: 4px; flex: 1 1 160px; min-width: 0; }
        .lbf-opt-group span { font-size: 11px; font-weight: 600; color: #646970; white-space: nowrap; }
        .lbf-opt-group em { font-weight: 400; color: #8c8f94; }
        .lbf-opt-group input[type=text], .lbf-opt-group input[type=number], .lbf-opt-group select { height: 28px; padding: 0 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; width: 100%; box-sizing: border-box; }
        .lbf-opt-group textarea { padding: 5px 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; width: 100%; box-sizing: border-box; font-family: Consolas, monospace; resize: vertical; }
        .lbf-opt-inline { flex-direction: row; align-items: center; flex: 0 0 auto; gap: 6px; }
        .lbf-rows { width: 60px !important; }
        .laca-builder-footer { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
        .laca-btn-add { display: flex; align-items: center; gap: 4px; font-size: 12px; padding: 4px 10px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; color: #2271b1; cursor: pointer; line-height: 1.4; }
        .laca-btn-add:hover { background: #f0f6fc; border-color: #2271b1; }
        .laca-btn-add .dashicons { font-size: 14px; width: 14px; height: 14px; }
        .laca-warn-note { margin: 8px 0 0; font-size: 11px; color: #996800; }
        #laca-meta-code { display: none; }
        .CodeMirror { height: 520px; font-size: 13px; font-family: "Courier New", Consolas, monospace; }
        .laca-code-footer { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #e2e4e7; background: #f9fafb; }
        </style>
        <?php
    }
}
