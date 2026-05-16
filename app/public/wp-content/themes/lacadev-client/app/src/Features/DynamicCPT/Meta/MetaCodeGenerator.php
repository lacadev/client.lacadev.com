<?php

namespace App\Features\DynamicCPT\Meta;

class MetaCodeGenerator
{
    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function generateStub(string $slug, string $containerTitle, array $fields): string
    {
        $lines = '';

        foreach ($fields as $field) {
            $name = sanitize_key((string) ($field['name'] ?? ''));
            if (!$name) {
                continue;
            }
            $lines .= $this->fieldLine($field);
        }

        if (!$lines) {
            $lines  = "            // Thêm fields tại đây\n";
            $lines .= "            // Field::make('text', 'ten_field', __('Label', 'laca')),\n";
        }

        $safeSlug  = addslashes($slug);
        $safeTitle = addslashes($containerTitle);

        return <<<PHP
<?php

/**
 * Meta fields cho CPT: {$slug}
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('{$safeTitle}', 'laca'))
        ->where('post_type', '=', '{$safeSlug}')
        ->add_fields([
{$lines}        ]);
});
PHP;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fieldLine(array $field): string
    {
        $name  = sanitize_key((string) ($field['name'] ?? ''));
        $label = sanitize_text_field((string) ($field['label'] ?? $name));
        $type  = sanitize_key((string) ($field['type'] ?? 'text'));
        $width = absint($field['width'] ?? 100);

        $chains = '';

        if ($width < 100) {
            $chains .= "\n                ->set_width({$width})";
        }

        $placeholder = sanitize_text_field((string) ($field['placeholder'] ?? ''));
        if ($placeholder !== '' && in_array($type, ['text', 'textarea', 'date'], true)) {
            $chains .= "\n                ->set_attribute('placeholder', '" . addslashes($placeholder) . "')";
        }

        $default = $field['default_value'] ?? '';
        if ($default !== '') {
            $chains .= "\n                ->set_default_value('" . addslashes(sanitize_text_field((string) $default)) . "')";
        }

        $help = sanitize_text_field((string) ($field['help_text'] ?? ''));
        if ($help !== '') {
            $chains .= "\n                ->set_help_text(__('". addslashes($help) . "', 'laca'))";
        }

        if (!empty($field['required'])) {
            $chains .= "\n                ->set_required(true)";
        }

        switch ($type) {
            case 'textarea':
                $rows = absint($field['rows'] ?? 5);
                if ($rows !== 5) {
                    $chains .= "\n                ->set_rows({$rows})";
                }
                break;
            case 'select':
                $chains .= $this->buildSelectOptions((string) ($field['options'] ?? ''));
                break;
            case 'checkbox':
                $optionValue = sanitize_text_field((string) ($field['option_value'] ?? ''));
                if ($optionValue !== '') {
                    $chains .= "\n                ->set_option_value('" . addslashes($optionValue) . "')";
                }
                break;
            case 'image':
            case 'file':
                $valueType = in_array($field['value_type'] ?? 'id', ['id', 'url'], true)
                    ? (string) ($field['value_type'] ?? 'id')
                    : 'id';
                $chains .= "\n                ->set_value_type('{$valueType}')";
                if ($type === 'file') {
                    $fileType = sanitize_text_field((string) ($field['file_type'] ?? ''));
                    if ($fileType !== '') {
                        $chains .= "\n                ->set_type('" . addslashes($fileType) . "')";
                    }
                }
                break;
            case 'color':
                if (!empty($field['alpha_enabled'])) {
                    $chains .= "\n                ->set_alpha_enabled(true)";
                }
                $palette = sanitize_text_field((string) ($field['palette'] ?? ''));
                if ($palette !== '') {
                    $colors = array_filter(array_map('trim', explode(',', $palette)));
                    $colorItems = array_map(static function (string $color): string {
                        return "                    '" . addslashes($color) . "'";
                    }, $colors);
                    if ($colorItems) {
                        $chains .= "\n                ->set_palette([\n" . implode(",\n", $colorItems) . ",\n                ])";
                    }
                }
                break;
            case 'date':
                $format = sanitize_text_field((string) ($field['storage_format'] ?? ''));
                if ($format !== '' && $format !== 'Y-m-d') {
                    $chains .= "\n                ->set_storage_format('" . addslashes($format) . "')";
                }
                break;
        }

        return "            \\Carbon_Fields\\Field\\Field::make('{$type}', '{$name}', __('{$label}', 'laca')){$chains},\n";
    }

    private function buildSelectOptions(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return "\n                ->add_options([/* 'value' => 'Label' */])";
        }

        $items = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '|') !== false) {
                [$value, $label] = explode('|', $line, 2);
            } else {
                $value = $label = $line;
            }

            $items[] = "                    '" . addslashes(trim($value)) . "' => '" . addslashes(trim($label)) . "'";
        }

        if (empty($items)) {
            return "\n                ->add_options([/* 'value' => 'Label' */])";
        }

        return "\n                ->add_options([\n" . implode(",\n", $items) . ",\n                ])";
    }
}
