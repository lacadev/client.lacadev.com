<?php

namespace App\Bootstrap;

use App\Features\AuthorTrustProfile;
use App\Features\ContactForm\ContactFormAjaxHandler;
use App\Features\ContactForm\MultiStepFormHandler;
use App\Features\ContextAwareCta;
use App\Features\DynamicCPT\DynamicCptManager;
use App\Features\EditorialWorkflow;
use App\Features\ExitIntentPopup;
use App\Features\FrontendChatbot\FrontendChatbotHandler;
use App\Features\RecommendationEngine;
use App\Features\RelatedPosts;
use App\Features\RoleBasedAdminUx;
use App\Features\SmartSearch\SmartSearchEndpoint;
use App\Settings\AdminSettings;
use App\Settings\AutoDownloadImage;
use App\Settings\BlockSyncReceiver;
use App\Settings\EmailLog\EmailLogManager;
use App\Settings\LacaAdmin\LacaAdminMenuOrganizer;
use App\Settings\LacaDevTrackerClient;
use App\Settings\LacaTools\ManagementExperience;
use App\Settings\LacaTools\Optimize;
use App\Settings\MaintenanceModeManager;
use App\Settings\RequirePlugins;
use App\Settings\Security\CustomLoginManager;
use App\Settings\Security\SecurityManager;
use App\Settings\Security\TwoFactorAuth;
use App\Settings\ThemeControlCenter;
use App\Settings\ThemeSettings;
use App\Settings\ThemeUpdater;
use App\Widgets\BlockSyncWidget;

/**
 * Central registry for feature bootstrapping.
 *
 * This keeps WordPress hook timing in one place while making the active feature
 * list easy to inspect and test.
 */
final class FeatureRegistry
{
    private const IMMEDIATE_CONSTRUCTORS = [
        RequirePlugins::class,
        AdminSettings::class,
        AutoDownloadImage::class,
        ThemeSettings::class,
        Optimize::class,
    ];

    private const IMMEDIATE_LATE_CONSTRUCTORS = [
        ManagementExperience::class,
        SmartSearchEndpoint::class,
    ];

    private const IMMEDIATE_INITIALIZERS = [
        MultiStepFormHandler::class,
        ContextAwareCta::class,
        AuthorTrustProfile::class,
        RecommendationEngine::class,
        ThemeControlCenter::class,
        EditorialWorkflow::class,
        RoleBasedAdminUx::class,
    ];

    private const DYNAMIC_POST_TYPE_CONSTRUCTORS = [
        DynamicCptManager::class,
    ];

    private const ADMIN_INIT_CONSTRUCTORS = [
        ThemeUpdater::class,
        BlockSyncWidget::class,
    ];

    private const ADMIN_INIT_REGISTRARS = [
        LacaAdminMenuOrganizer::class => 'register',
    ];

    private const INIT_PRIORITY_FIVE_STATIC_REGISTRARS = [
        LacaDevTrackerClient::class => 'register',
    ];

    private const INIT_PRIORITY_FIVE_CONSTRUCTORS = [
        BlockSyncReceiver::class,
    ];

    private const INIT_PRIORITY_ONE_INITIALIZERS = [
        MaintenanceModeManager::class,
    ];

    private const INIT_PRIORITY_ONE_CONSTRUCTORS = [
        CustomLoginManager::class,
    ];

    private const INIT_DEFAULT_INITIALIZERS = [
        ContactFormAjaxHandler::class,
        EmailLogManager::class,
        RelatedPosts::class,
        ExitIntentPopup::class,
        FrontendChatbotHandler::class,
    ];

    private const INIT_DEFAULT_CONSTRUCTORS = [
        TwoFactorAuth::class,
    ];

    private const INIT_DEFAULT_LATE_INITIALIZERS = [
        SecurityManager::class,
    ];

    private const LACA_TOOLS_FALLBACK_FILES = [
        'Management/ContentAuditService.php',
        'Management/MediaService.php',
        'Management/DashboardWidgets.php',
        'Management/WebsiteOverviewWidgets.php',
        'Management/QuickNotesWidget.php',
        'Management/PerformanceBudgetWidget.php',
        'Management/LacaDashboardPage.php',
        'Management/ListTableEnhancements.php',
        'Management/AdminUxService.php',
        'Management/ClientOperationsPage.php',
        'AIChatHandler.php',
        'AITranslationParser.php',
        'AITranslationHandler.php',
        'AITranslationManager.php',
        'ProjectReportsManager.php',
        'ManagementExperience.php',
    ];

    public static function manifest(): array
    {
        return [
            'immediate_constructors' => self::IMMEDIATE_CONSTRUCTORS,
            'immediate_late_constructors' => self::IMMEDIATE_LATE_CONSTRUCTORS,
            'immediate_initializers' => self::IMMEDIATE_INITIALIZERS,
            'dynamic_post_type_constructors' => self::DYNAMIC_POST_TYPE_CONSTRUCTORS,
            'admin_init_constructors' => self::ADMIN_INIT_CONSTRUCTORS,
            'admin_init_registrars' => self::ADMIN_INIT_REGISTRARS,
            'init_priority_five_static_registrars' => self::INIT_PRIORITY_FIVE_STATIC_REGISTRARS,
            'init_priority_five_constructors' => self::INIT_PRIORITY_FIVE_CONSTRUCTORS,
            'init_priority_one_initializers' => self::INIT_PRIORITY_ONE_INITIALIZERS,
            'init_priority_one_constructors' => self::INIT_PRIORITY_ONE_CONSTRUCTORS,
            'init_default_initializers' => self::INIT_DEFAULT_INITIALIZERS,
            'init_default_constructors' => self::INIT_DEFAULT_CONSTRUCTORS,
            'init_default_late_initializers' => self::INIT_DEFAULT_LATE_INITIALIZERS,
        ];
    }

    public static function hookManifest(): array
    {
        return [
            ['hook' => 'init', 'callback' => 'bootAdminFeatures', 'priority' => 10, 'condition' => 'is_admin'],
            ['hook' => 'init', 'callback' => 'bootPriorityFiveInitFeatures', 'priority' => 5],
            ['hook' => 'init', 'callback' => 'bootPriorityOneInitFeatures', 'priority' => 1],
            ['hook' => 'init', 'callback' => 'bootDefaultInitFeatures', 'priority' => 10],
        ];
    }

    public static function bootImmediate(): void
    {
        self::constructMany(self::IMMEDIATE_CONSTRUCTORS);
        self::loadLacaToolsFallbacks();
        self::constructMany(self::IMMEDIATE_LATE_CONSTRUCTORS);
        self::initializeMany(self::IMMEDIATE_INITIALIZERS);
    }

    public static function bootDynamicPostTypes(): void
    {
        self::constructMany(self::DYNAMIC_POST_TYPE_CONSTRUCTORS);
    }

    public static function bootAdminFeatures(): void
    {
        self::constructMany(self::ADMIN_INIT_CONSTRUCTORS);
        self::callInstanceMethods(self::ADMIN_INIT_REGISTRARS);
    }

    public static function bootPriorityFiveInitFeatures(): void
    {
        self::callStaticMethods(self::INIT_PRIORITY_FIVE_STATIC_REGISTRARS);
        self::constructMany(self::INIT_PRIORITY_FIVE_CONSTRUCTORS);
    }

    public static function bootPriorityOneInitFeatures(): void
    {
        self::initializeMany(self::INIT_PRIORITY_ONE_INITIALIZERS);
        self::constructMany(self::INIT_PRIORITY_ONE_CONSTRUCTORS);
    }

    public static function bootDefaultInitFeatures(): void
    {
        self::initializeMany(self::INIT_DEFAULT_INITIALIZERS);
        self::constructMany(self::INIT_DEFAULT_CONSTRUCTORS);
        self::initializeMany(self::INIT_DEFAULT_LATE_INITIALIZERS);
    }

    private static function constructMany(array $classes): void
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                new $class();
            }
        }
    }

    private static function initializeMany(array $classes): void
    {
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $instance = new $class();
            if (method_exists($instance, 'init')) {
                $instance->init();
            }
        }
    }

    private static function callInstanceMethods(array $registrars): void
    {
        foreach ($registrars as $class => $method) {
            if (!class_exists($class)) {
                continue;
            }

            $instance = new $class();
            if (method_exists($instance, $method)) {
                $instance->{$method}();
            }
        }
    }

    private static function callStaticMethods(array $registrars): void
    {
        foreach ($registrars as $class => $method) {
            if (class_exists($class) && method_exists($class, $method)) {
                $class::{$method}();
            }
        }
    }

    private static function loadLacaToolsFallbacks(): void
    {
        if (class_exists(ManagementExperience::class) || !function_exists('get_template_directory')) {
            return;
        }

        $parentLacaTools = get_template_directory() . '/app/src/Settings/LacaTools';
        $blockSyncWidget = get_template_directory() . '/app/src/Widgets/BlockSyncWidget.php';

        if (file_exists($blockSyncWidget)) {
            require_once $blockSyncWidget;
        }

        foreach (self::LACA_TOOLS_FALLBACK_FILES as $relativeFile) {
            $classFile = $parentLacaTools . '/' . $relativeFile;
            if (file_exists($classFile)) {
                require_once $classFile;
            }
        }
    }
}
