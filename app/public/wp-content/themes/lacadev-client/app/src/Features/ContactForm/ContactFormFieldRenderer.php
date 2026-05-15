<?php

namespace App\Features\ContactForm;

/**
 * Renders frontend fields for contact forms.
 */
final class ContactFormFieldRenderer
{
    public static function renderSingle(array $field): string
    {
        ob_start();
        (new self())->render($field);
        return ob_get_clean();
    }

    public function render(array $field): void
    {
        if (($field['type'] ?? '') === 'step_break') {
            return;
        }

        $name = esc_attr($field['name']);
        $label = esc_html($field['label']);
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required = !empty($field['required']);
        $type = $field['type'];
        $rawCol = $field['col_width'] ?? '12';
        $colWidth = in_array($rawCol, ['12', '6', '4', '3'], true) ? $rawCol : '12';
        $reqAttr = $required ? 'required data-required="true"' : 'data-required="false"';
        $reqMark = $required ? ' <span class="laca-cf-required" aria-hidden="true">*</span>' : '';
        $fieldId = 'laca-cf-field-' . esc_attr($name) . '-' . uniqid('', true);
        $conditionAttrs = ContactFormSchema::buildConditionAttributes($field);
        ?>
        <div class="laca-cf-form-row laca-cf-type-<?php echo esc_attr($type); ?> laca-cf-col-<?php echo esc_attr($colWidth); ?>"<?php echo $conditionAttrs; ?>>
            <?php if ($type !== 'hidden'): ?>
                <label for="<?php echo esc_attr($fieldId); ?>" class="laca-cf-label">
                    <?php echo $label . $reqMark; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($type) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-textarea" placeholder="' . $placeholder . '" rows="4" ' . $reqAttr . '></textarea>';
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-select" ' . $reqAttr . '>';
                    echo '<option value="">— Chọn ' . $label . ' —</option>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'multiselect':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '[]" class="laca-cf-select laca-cf-multiselect" multiple size="4" ' . $reqAttr . '>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="laca-cf-hint">Giữ Ctrl / Cmd để chọn nhiều.</p>';
                    break;

                case 'radio':
                    $options = $field['options'] ?? [];
                    echo '<div class="laca-cf-radio-group" id="' . esc_attr($fieldId) . '" ' . $reqAttr . '>';
                    foreach ($options as $idx => $opt) {
                        $optId = esc_attr($fieldId . '-' . $idx);
                        echo '<label class="laca-cf-radio-label"><input type="radio" id="' . $optId . '" name="' . $name . '" value="' . esc_attr($opt) . '"> ' . esc_html($opt) . '</label>';
                    }
                    echo '</div>';
                    break;

                case 'checkbox':
                    $this->renderCheckbox($field, $fieldId, $name, $required, $reqAttr);
                    break;

                case 'date':
                    echo '<input type="date" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'datetime':
                    echo '<input type="datetime-local" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'hidden':
                    echo '<input type="hidden" name="' . $name . '" value="' . $placeholder . '">';
                    break;

                default:
                    $this->renderTextInput($type, $fieldId, $name, $placeholder, $reqAttr);
            }
            ?>
            <span class="laca-cf-field-error" hidden aria-live="polite"></span>
        </div>
        <?php
    }

    private function renderCheckbox(array $field, string $fieldId, string $name, bool $required, string $reqAttr): void
    {
        $options = $field['options'] ?? [];
        if (count($options) <= 1) {
            $singleOpt = $options[0] ?? 'yes';
            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . esc_attr($fieldId) . '" name="' . $name . '" value="' . esc_attr($singleOpt) . '" ' . $reqAttr . '> ' . esc_html($singleOpt) . '</label>';
            return;
        }

        echo '<div class="laca-cf-checkbox-group" id="' . esc_attr($fieldId) . '">';
        foreach ($options as $idx => $opt) {
            $optId = esc_attr($fieldId . '-' . $idx);
            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . $optId . '" name="' . $name . '[]" value="' . esc_attr($opt) . '" data-required="' . ($required ? 'true' : 'false') . '"> ' . esc_html($opt) . '</label>';
        }
        echo '</div>';
    }

    private function renderTextInput(string $type, string $fieldId, string $name, string $placeholder, string $reqAttr): void
    {
        $inputType = match ($type) {
            'email'  => 'email',
            'phone'  => 'tel',
            'number' => 'number',
            'url'    => 'url',
            default  => 'text',
        };
        $autocomplete = match ($type) {
            'email' => 'email',
            'phone' => 'tel',
            'text'  => 'on',
            default => 'off',
        };
        $extraAttrs = match ($type) {
            'phone' => ' inputmode="tel" pattern="\\+?[0-9\\s().-]{8,24}" minlength="8" maxlength="24"',
            'number' => ' inputmode="decimal"',
            default => '',
        };

        echo '<input type="' . esc_attr($inputType) . '" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" placeholder="' . $placeholder . '" autocomplete="' . esc_attr($autocomplete) . '" ' . $reqAttr . $extraAttrs . '>';
    }
}
