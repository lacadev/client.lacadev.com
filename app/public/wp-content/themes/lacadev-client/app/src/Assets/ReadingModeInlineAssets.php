<?php

namespace App\Assets;

/**
 * Inline scripts for reading-mode compatibility with legacy browser state.
 */
final class ReadingModeInlineAssets
{
    public static function disabledScript(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    try {
        localStorage.removeItem('lacadev_reading_mode');
    } catch (e) {}

    document.body.classList.remove('reading-mode');

    var removeButton = function () {
        var btn = document.getElementById('reading-mode-btn');
        if (btn) {
            btn.remove();
        }
    };

    removeButton();

    var observer = new MutationObserver(function () {
        removeButton();
    });

    observer.observe(document.body, { childList: true, subtree: true });

    setTimeout(function () {
        observer.disconnect();
    }, 5000);
});
JS;
    }
}
