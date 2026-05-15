# Cấu Trúc Chức Năng Theme `lacadev-client`

Cập nhật lần cuối: 2026-05-15

Tài liệu này mô tả cấu trúc chức năng hiện tại của theme sau các đợt refactor lớn để dễ bảo trì hơn.
Mỗi mục bên dưới ghi rõ:

- Chức năng đang nằm ở đâu
- File/class chính bắt đầu từ line nào
- File test nào đang bảo vệ hành vi hiện có

## 1. Tracker Client

- Mục đích:
  Gửi log vận hành từ site khách về hệ thống LacaDev, nhận lệnh cập nhật từ xa, lưu hàng đợi cục bộ, hiển thị timeline bảo trì công khai.
- Các file chính:
  - `app/src/Settings/LacaDevTrackerClient.php:49`
  - `app/src/Settings/Tracker/TrackerClientConfig.php:8`
  - `app/src/Settings/Tracker/TrackerHttpTransport.php:8`
  - `app/src/Settings/Tracker/TrackerQueuePolicy.php:8`
  - `app/src/Settings/Tracker/TrackerHealthSummary.php:8`
  - `app/src/Settings/Tracker/TrackerTimelinePresenter.php:8`
  - `app/src/Settings/Tracker/TrackerShortcodeRenderer.php:8`
  - `app/src/Settings/Tracker/TrackerClientRequestHandler.php:10`
  - `app/src/Settings/Tracker/ClientSupportRequestBuilder.php:8`
  - `app/src/Settings/Tracker/SupportAttachmentFiles.php:8`
  - `app/src/Settings/Tracker/SupportAttachmentUploader.php:8`
  - `app/src/Settings/Tracker/RemoteUpdateRequestValidator.php:8`
  - `app/src/Settings/Tracker/RemoteUpdatePreflight.php:8`
  - `app/src/Settings/Tracker/RemoteUpdateExecutor.php:8`
  - `app/src/Settings/Tracker/RemoteUpdateMeta.php:8`
  - `app/src/Settings/Tracker/RemoteUpdateHistory.php:8`
  - `app/src/Settings/Tracker/RemoteUpdateHistoryStore.php:8`
  - `app/src/Settings/Tracker/RemoteUpdatePolicy.php:8`
  - `app/src/Settings/Tracker/MaintenanceSnapshot.php:10`
  - `app/src/Settings/Tracker/TrackerMaintenanceTimelineBuilder.php:10`
  - `app/src/Settings/Tracker/SuspiciousFileScanner.php:8`
  - `app/src/Settings/Tracker/TrackerFileIntegrity.php:8`
- Test liên quan:
  - `tests/Unit/TrackerClientConfigTest.php:14`
  - `tests/Unit/TrackerHttpTransportTest.php:14`
  - `tests/Unit/TrackerQueuePolicyTest.php:11`
  - `tests/Unit/TrackerHealthSummaryTest.php:14`
  - `tests/Unit/TrackerTimelinePresenterTest.php:30`
  - `tests/Unit/TrackerShortcodeRendererTest.php:56`
  - `tests/Unit/ClientSupportRequestBuilderTest.php:21`
  - `tests/Unit/SupportAttachmentFilesTest.php:7`
  - `tests/Unit/SupportAttachmentUploaderTest.php:39`
  - `tests/Unit/RemoteUpdateRequestValidatorTest.php:21`
  - `tests/Unit/RemoteUpdatePreflightTest.php:7`
  - `tests/Unit/RemoteUpdateMetaTest.php:7`
  - `tests/Unit/RemoteUpdateHistoryTest.php:28`
  - `tests/Unit/RemoteUpdateHistoryStoreTest.php:28`
  - `tests/Unit/MaintenanceSnapshotTest.php:50`
  - `tests/Unit/TrackerMaintenanceTimelineBuilderTest.php:28`
  - `tests/Unit/SupportAttachmentFilesTest.php:7`
  - `tests/Unit/SuspiciousFileScannerTest.php` chưa có file riêng trong suite hiện tại
  - `tests/Unit/TrackerFileIntegrityTest.php:7`

## 2. Contact Form

- Mục đích:
  Quản lý form liên hệ tùy chỉnh, builder admin, validate dữ liệu gửi lên, gửi email, lưu submissions, xuất CSV và render shortcode frontend.
- Các file chính:
  - `app/src/Features/ContactForm/ContactFormManager.php:23`
  - `app/src/Features/ContactForm/ContactFormAjaxHandler.php:19`
  - `app/src/Features/ContactForm/ContactFormEmailService.php:15`
  - `app/src/Features/ContactForm/MultiStepFormHandler.php:21`
  - `app/src/Features/ContactForm/ContactFormSchema.php:12`
  - `app/src/Features/ContactForm/ContactFormDefaults.php:8`
  - `app/src/Features/ContactForm/ContactFormAdminSanitizer.php:8`
  - `app/src/Features/ContactForm/ContactFormSubmissionValidator.php:8`
  - `app/src/Features/ContactForm/ContactFormCsvExporter.php:8`
  - `app/src/Features/ContactForm/ContactFormFieldRenderer.php:8`
  - `app/src/Features/ContactForm/ContactFormFrontendAssets.php:8`
  - `app/src/Features/ContactForm/ContactFormFrontendScripts.php:8`
  - `app/src/Features/ContactForm/ContactFormListPageRenderer.php:5`
  - `app/src/Features/ContactForm/ContactFormEditPageRenderer.php:5`
  - `app/src/Features/ContactForm/ContactFormSubmissionsPageRenderer.php:5`
- Test liên quan:
  - `tests/Unit/ContactFormDefaultsTest.php:14`
  - `tests/Unit/ContactFormSchemaTest.php:66`
  - `tests/Unit/ContactFormAdminSanitizerTest.php:36`
  - `tests/Unit/ContactFormSubmissionValidatorTest.php:7`
  - `tests/Unit/ContactFormCsvExporterTest.php:7`
  - `tests/Unit/ContactFormFieldRendererTest.php:21`
  - `tests/Unit/ContactFormFrontendAssetsTest.php:7`
  - `tests/Unit/ContactFormFrontendScriptsTest.php:14`
  - `tests/Unit/ContactFormAdminPageRenderersTest.php:76`

## 3. Admin Settings Và Admin Utilities

- Mục đích:
  Điều phối trải nghiệm admin của khách hàng, branding khu vực quản trị, phân quyền truy cập, media helper và đăng ký toàn bộ trang option Carbon Fields.
- Các file chính:
  - `app/src/Settings/AdminSettings.php:13`
  - `app/src/Settings/Admin/AdminOptionsRegistrar.php:8`
  - `app/src/Settings/Admin/AdminAccessPolicy.php:8`
  - `app/src/Settings/Admin/AdminAccessDeniedPage.php:8`
  - `app/src/Settings/Admin/AdminDashboardIntroWidget.php:8`
  - `app/src/Settings/Admin/AdminMediaSupport.php:8`
  - `app/src/Settings/Admin/AdminOptionHtml.php:8`
- Test liên quan:
  - `tests/Unit/AdminSettingsStructureTest.php:5`
  - `tests/Unit/AdminAccessPolicyTest.php:7`
  - `tests/Unit/AdminAccessDeniedPageTest.php:21`
  - `tests/Unit/AdminMediaSupportTest.php:7`
  - `tests/Unit/AdminOptionHtmlTest.php:14`

## 4. Theme Control Center

- Mục đích:
  Gom các cài đặt theme phân tán thành một màn hình tabbed duy nhất trong admin.
- Các file chính:
  - `app/src/Settings/ThemeControlCenter.php:31`
- Trạng thái:
  Đây vẫn là một module lớn nhưng đã có cấu trúc tab nội bộ rõ ràng: `general`, `cta`, `author`, `performance`, `search`, `system`.

## 5. Security Center

- Mục đích:
  Cung cấp dashboard bảo mật cho admin: audit bảo mật, file integrity monitor, malware scan, hidden user scan, custom login và 2FA.
- Các file chính:
  - `app/src/Settings/Security/SecurityManager.php:23`
  - `app/src/Settings/Security/SecurityAudit.php:15`
  - `app/src/Settings/Security/FileIntegrityMonitor.php:19`
  - `app/src/Settings/Security/MalwareScanner.php:17`
  - `app/src/Settings/Security/HiddenUserScanner.php:15`
  - `app/src/Settings/Security/CustomLoginManager.php:19`
  - `app/src/Settings/Security/TwoFactorAuth.php:19`
- Ghi chú:
  `SecurityManager` đang làm vai trò controller/admin hub cho các service bảo mật ở trên.

## 6. Dynamic CPT

- Mục đích:
  Cho phép tạo Custom Post Type động, sinh template archive/single, và tạo file meta fields cho từng CPT.
- Các file chính:
  - `app/src/Features/DynamicCPT/DynamicCptManager.php:12`
  - `app/src/Features/DynamicCPT/DynamicCptAdminPage.php:12`
  - `app/src/Features/DynamicCPT/DynamicCptMetaEditor.php:25`
  - `app/src/Features/DynamicCPT/DynamicCptTemplateGenerator.php:12`
- Ghi chú:
  Hai file admin lớn nhất hiện tại của mảng này là `DynamicCptAdminPage` và `DynamicCptMetaEditor`.

## 7. Assets Và Shared Support

- Mục đích:
  Tách dữ liệu/luật tải asset khỏi file setup để frontend/admin/login/editor dễ bảo trì hơn.
- Các file chính:
  - `app/src/Assets/AssetLoadingRules.php:10`
  - `app/src/Assets/AssetPreloader.php:8`
  - `app/src/Assets/AdminScriptData.php:8`
  - `app/src/Assets/AdminStyleOverrides.php:8`
  - `app/src/Assets/EditorStyleData.php:8`
  - `app/src/Assets/LoginAssetData.php:8`
  - `app/src/Assets/LoginInlineAssets.php:8`
  - `app/src/Assets/ReadingModeInlineAssets.php:8`
  - `app/src/Assets/ProjectChartData.php:8`
  - `app/src/Support/ClientIpResolver.php:8`
- Test liên quan:
  - `tests/Unit/AssetLoadingRulesTest.php:8`
  - `tests/Unit/AssetPreloaderTest.php:14`
  - `tests/Unit/AdminScriptDataTest.php:7`
  - `tests/Unit/InlineAssetHelpersTest.php:17`
  - `tests/Unit/LoginAssetDataTest.php:21`
  - `tests/Unit/ProjectChartDataTest.php:7`
  - `tests/Unit/ClientIpResolverTest.php:7`

## 8. Tóm Tắt Trạng Thái Hiện Tại

- Đã tách mạnh khỏi file lớn:
  - Tracker transport / queue / preflight / snapshot / support request / timeline builder / remote-update helpers
  - Contact form admin renderer / sanitize / validate / CSV / frontend helper
  - Admin settings option registrar / access policy / media helper / option HTML
- Các file vẫn còn lớn nhưng hiện đã có ranh giới trách nhiệm rõ hơn:
  - `app/src/Settings/LacaDevTrackerClient.php:49`
  - `app/src/Settings/ThemeControlCenter.php:31`
  - `app/src/Settings/Security/SecurityManager.php:23`
  - `app/src/Features/DynamicCPT/DynamicCptAdminPage.php:12`
  - `app/src/Features/DynamicCPT/DynamicCptMetaEditor.php:25`

## 9. Lệnh Test Hiện Tại

```bash
php tests/run.php
composer test
```

Tại thời điểm cập nhật tài liệu này, suite đang pass: `104 tests, 0 failures`.
