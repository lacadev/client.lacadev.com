<?php

declare(strict_types=1);

test('ThemeControlCenter delegates tab responsibilities to feature tab classes', function (): void {
    $themeRoot = dirname(__DIR__, 2);
    $source = file_get_contents($themeRoot . '/app/src/Settings/ThemeControlCenter.php');

    assert_true(is_string($source) && $source !== '', 'Unable to read ThemeControlCenter.php');

    foreach ([
        'ThemeControlTabs\\GeneralTab',
        'ThemeControlTabs\\CtaTab',
        'ThemeControlTabs\\AuthorTab',
        'ThemeControlTabs\\PerformanceTab',
        'ThemeControlTabs\\SearchTab',
        'ThemeControlTabs\\SystemTab',
        'ThemeControlTabs\\Assets',
    ] as $delegateClass) {
        assert_true(
            str_contains($source, $delegateClass),
            "ThemeControlCenter should reference {$delegateClass}"
        );
    }
});

test('SecurityManager delegates page rendering and inline assets to admin helpers', function (): void {
    $themeRoot = dirname(__DIR__, 2);
    $source = file_get_contents($themeRoot . '/app/src/Settings/Security/SecurityManager.php');

    assert_true(is_string($source) && $source !== '', 'Unable to read SecurityManager.php');

    foreach ([
        'Security\\Admin\\PageRenderer',
        'Security\\Admin\\FimResultRenderer',
        'Security\\Admin\\InlineAssets',
    ] as $delegateClass) {
        assert_true(
            str_contains($source, $delegateClass),
            "SecurityManager should reference {$delegateClass}"
        );
    }
});

test('Dynamic CPT admin and meta editors delegate heavy UI/code generation work', function (): void {
    $themeRoot = dirname(__DIR__, 2);
    $adminSource = file_get_contents($themeRoot . '/app/src/Features/DynamicCPT/DynamicCptAdminPage.php');
    $metaSource = file_get_contents($themeRoot . '/app/src/Features/DynamicCPT/DynamicCptMetaEditor.php');

    assert_true(is_string($adminSource) && $adminSource !== '', 'Unable to read DynamicCptAdminPage.php');
    assert_true(is_string($metaSource) && $metaSource !== '', 'Unable to read DynamicCptMetaEditor.php');

    assert_true(
        str_contains($adminSource, 'DynamicCPT\\Admin\\AdminPageRenderer'),
        'DynamicCptAdminPage should delegate page rendering to AdminPageRenderer'
    );
    assert_true(
        str_contains($metaSource, 'DynamicCPT\\Meta\\MetaCodeGenerator'),
        'DynamicCptMetaEditor should delegate code generation to MetaCodeGenerator'
    );
    assert_true(
        str_contains($metaSource, 'DynamicCPT\\Meta\\MetaEditorRenderer'),
        'DynamicCptMetaEditor should delegate editor rendering to MetaEditorRenderer'
    );
});

test('LacaDevTrackerClient delegates tracker responsibilities to dedicated client services', function (): void {
    $themeRoot = dirname(__DIR__, 2);
    $source = file_get_contents($themeRoot . '/app/src/Settings/LacaDevTrackerClient.php');

    assert_true(is_string($source) && $source !== '', 'Unable to read LacaDevTrackerClient.php');

    foreach ([
        'Tracker\\Client\\ScheduleManager',
        'Tracker\\Client\\EventMonitor',
        'Tracker\\Client\\DigestRunner',
        'Tracker\\Client\\DeliveryManager',
        'Tracker\\Client\\RemoteUpdateController',
    ] as $delegateClass) {
        assert_true(
            str_contains($source, $delegateClass),
            "LacaDevTrackerClient should reference {$delegateClass}"
        );
    }

    foreach ([
        'scheduleManager->addCronSchedules',
        'eventMonitor->onUpgraderComplete',
        'digestRunner->runDailyDigest',
        'deliveryManager->sendMaintenanceSummary',
        'remoteUpdateController->handleRemoteUpdate',
    ] as $delegateCall) {
        assert_true(
            str_contains($source, $delegateCall),
            "LacaDevTrackerClient should delegate via {$delegateCall}"
        );
    }
});
