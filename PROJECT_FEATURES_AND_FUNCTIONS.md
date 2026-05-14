# Project Features and Functions

This file is an inventory of the main features, routes, endpoints, and project-defined functions in this custom Wallos build. It is based on the repository contents as of 2026-05-13.

Third-party library internals under `app/libs/PHPMailer`, `app/libs/OTPHP`, and similar vendor-style folders are not expanded here. Project wrappers and helpers are included.

## Feature Summary

- Subscription tracking dashboard with upcoming payments, cost summaries, disabled subscriptions, dashboard edit actions, and AI recommendation counts.
- Subscription management: add, edit, clone, delete, renew, mark as paid, search, sort, filter, logo upload, logo search, and calendar export.
- Calendar view and private iCal feed support, including feed token regeneration and optional settings.
- Stats view with bar and line charts for subscription spending.
- Household/member management for assigning subscriptions to people.
- Category management with add, edit, delete, and sorting.
- Currency management, exchange-rate refresh, Fixer API key support, original price display, and currency conversion.
- Payment method management with icon upload/search, enable/disable, rename, delete, and custom sorting.
- Notification settings and test/send support for email, webhook, Telegram, Discord, Ntfy, Gotify, Pushover, PushPlus, Mattermost, and ServerChan.
- User profile management: profile updates, avatar upload/delete, budget, API key regeneration, account deletion, JSON/CSV export, TOTP enable/disable, and backup code handling.
- Admin area: user management, open registrations, SMTP settings, security settings, OIDC settings, update notifications, database backup/restore, cron execution, and unused logo cleanup.
- Authentication flows: login, logout, registration, password reset, email verification, login throttling, auth cookie helpers, OIDC callback handling, and TOTP.
- Backup and restore helpers with safe zip extraction, SQLite validation, logo restore, scheduled backup cleanup, and protected backup storage.
- Cron jobs for database creation, migrations, exchange updates, next-payment updates, yearly cost storage, notifications, update checks, reset-token cleanup, verification email sends, recommendation generation, and timezone setup.
- Mobile/PWA improvements: service worker, mobile navigation positioning, cache clearing, mobile-first layout behavior, and modern swipe actions.
- Theming: dark/light/system theme, color themes, design themes, custom colors, custom CSS, and theme reset.
- Public API responses for settings, users, categories, currencies, household, payment methods, subscriptions, monthly cost, iCal feed, admin settings, OIDC settings, fixer settings, notification settings, and version status.
- Security helpers for CSRF, SSRF protection, request parsing, API key/bearer token lookup, admin enforcement, secure cookies, and endpoint validation.

## Public Pages

- `app/index.php` - dashboard.
- `app/subscriptions.php` - subscription list and management.
- `app/calendar.php` - calendar view.
- `app/stats.php` - spending statistics.
- `app/settings.php` - user and app settings.
- `app/profile.php` - user profile, avatar, API key, TOTP, exports, account deletion.
- `app/admin.php` - admin settings and maintenance.
- `app/logos.php` - logo search page.
- `app/login.php` - login.
- `app/logout.php` - logout with client cache cleanup.
- `app/registration.php` - first-run/user registration and restore flow.
- `app/passwordreset.php` - password reset.
- `app/totp.php` - TOTP prompt/verification.
- `app/verifyemail.php` - email verification.
- `app/about.php` - about page.
- `app/health.php` - health check.

## API Routes

- `app/api/admin/get_admin_settings.php` - read admin settings.
- `app/api/admin/get_oidc_settings.php` - read OIDC settings.
- `app/api/admin/set_disable_password_login.php` - set password-login availability.
- `app/api/categories/get_categories.php` - list categories.
- `app/api/currencies/get_currencies.php` - list currencies.
- `app/api/fixer/get_fixer.php` - read Fixer configuration.
- `app/api/household/get_household.php` - list household members.
- `app/api/notifications/get_notification_settings.php` - read notification settings.
- `app/api/payment_methods/get_payment_methods.php` - list payment methods.
- `app/api/settings/get_settings.php` - read user settings.
- `app/api/status/version.php` - return version status.
- `app/api/subscriptions/get_ical_feed.php` - return iCal feed data.
- `app/api/subscriptions/get_monthly_cost.php` - return monthly cost summary.
- `app/api/subscriptions/get_subscriptions.php` - return subscription data.
- `app/api/users/get_user.php` - return user data.

## Endpoint Groups

- Admin endpoints: `adduser`, `deleteuser`, `deleteunusedlogos`, `enableoidc`, `saveoidcsettings`, `saveopenregistrations`, `savesecuritysettings`, `savesmtpsettings`, `updatenotification`.
- AI endpoints: `save_settings`, `fetch_models`, `generate_recommendations`, `delete_recommendation`.
- Calendar endpoints: `ical`, `save_ical_settings`, `regenerate_ical_token`.
- Category endpoint: `category`.
- Cron endpoints: `backup`, `checkforupdates`, `cleanupresettokens`, `createdatabase`, `generaterecommendations`, `sendcancellationnotifications`, `sendcompletednotifications`, `sendnotifications`, `sendresetpasswordemails`, `sendverificationemails`, `settimezone`, `storetotalyearlycost`, `updateexchange`, `updatenextpayment`, `validate`.
- Currency endpoints: `currency`, `fixer_api_key`, `update_exchange`.
- Database endpoints: `backup`, `download_backup`, `import`, `migrate`, `restore`.
- Household endpoint: `household`.
- Logo endpoint: `search`.
- Notification endpoints: save and test integrations for email, webhook, Telegram, Discord, Ntfy, Gotify, Pushover, PushPlus, Mattermost, and ServerChan.
- Payment method endpoints: `add`, `delete`, `get`, `rename`, `search`, `sort`, `toggle`.
- Settings endpoints: `adjust_to_working_day`, `colortheme`, `convert_currency`, `customcss`, `customtheme`, `deleteaccount`, `design_theme`, `disabled_to_bottom`, `hide_disabled`, `mobile_navigation`, `monthly_price`, `remove_background`, `resettheme`, `show_original_price`, `subscription_progress`, `theme`.
- Single-subscription endpoints: `add`, `clone`, `delete`, `exportcalendar`, `get`, `getcalendar`, `markpaid`, `renew`.
- Subscription-list endpoints: `export`, `get`.
- User endpoints: `budget`, `delete_avatar`, `disable_totp`, `enable_totp`, `regenerateapikey`, `save_user`.

## PHP Helper Functions

### API and Response Helpers

- `apiSuccess($data = null, $msg = null)` - standard internal API success payload.
- `apiError($msg, $http = 400, $details = null)` - standard internal API error payload.
- `apiPublicSuccess(array $data = [], string $version = 'v1')` - public API success payload.
- `apiPublicError(string $title, int $http = 400, ?array $details = null, string $version = 'v1')` - public API error payload.
- `getPriceConverted($price, $currency, $database)` - local API conversion helper in subscription API files.

### Request, Auth, and Security Helpers

- `requirePostJsonOrForm()` - read POST data from JSON or form submissions.
- `currentUserId()` - return current session user ID.
- `requireAdmin()` - enforce admin access.
- `requestBearerToken(?array $server = null)` - extract bearer token.
- `requestApiKey(?array $request = null, ?array $server = null)` - extract API key.
- `parseIntegerListParam($value, string $name, ?string &$error = null)` - parse comma-separated integer filters.
- `parseBoolParam($value, bool $default = false)` - parse boolean request values.
- `bindBool(SQLite3Stmt $stmt, string $name, $value)` - bind booleans to SQLite statements.
- `bindNullableDate(SQLite3Stmt $stmt, string $name, ?string $value)` - bind nullable dates.
- `generate_csrf_token()` - generate CSRF token.
- `verify_csrf_token(?string $token)` - validate CSRF token.
- `validate($value)` - generic input validation used in several files.
- `wallosClientIp()` - resolve the client IP for throttling.
- `wallosLoginThrottleConfig()` - read login throttle configuration.
- `wallosLoginThrottleBlocked($db, string $ip)` - check whether login is throttled.
- `wallosLoginThrottleRecordFailure($db, string $ip, string $username = '')` - record failed login.
- `wallosLoginThrottleClear($db, string $ip)` - clear throttle records after success.
- `wallosLoginThrottleRetryAfterSeconds($db, string $ip)` - calculate remaining lockout time.
- `wallosIsSecureRequest()` - determine whether the request is secure.
- `wallosAuthCookieParams(int $expires)` - build auth cookie options.
- `is_cgnat_ip($ip)` - detect CGNAT/private-like IPs for SSRF protection.
- `wallos_ssrf_error($message, $http = 400)` - return SSRF validation errors.
- `wallos_validate_url_for_ssrf($url, $db)` - validate remote URLs before fetching.
- `validate_webhook_url_for_ssrf($url, $db, $i18n)` - validate webhook URLs.
- `is_url_safe_for_ssrf($url, $db)` - boolean SSRF safety check.

### Formatting, Dates, and Subscriptions

- `formatPrice($price, $currencyCode, $currencies)` - format a price with currency.
- `formatDate($date, $lang = 'en')` - format a date.
- `getPricePerMonth($cycle, $frequency, $price)` - normalize subscription cost to monthly cost.
- `getPriceConverted($price, $currency, $database, $userId = null)` - convert prices through stored exchange data.
- `wallosCycleSuffix($cycle, $frequency = 1)` - produce cycle suffix text.
- `wallosRelativeDayLabel($dateStr)` - produce relative date labels.
- `wallosIntlIsAvailable()` - check PHP Intl availability.
- `wallosNormalizeLocale($locale, $fallback = 'en')` - normalize locales.
- `wallosFallbackDatePattern($pattern)` - provide fallback date patterns.
- `wallosFormatDateValue($date, $locale = 'en', $dateType = null, $timeType = null, $pattern = null)` - format arbitrary dates.
- `wallosFormatSubscriptionDate($date, $locale = 'en')` - format subscription dates.
- `normalizeSubscriptionDate($date)` - normalize subscription date input.
- `getSubscriptionInterval($cycle, $frequency)` - get a DateInterval for a subscription cycle.
- `hasSeparateFirstPayment($subscription)` - detect separate first-payment dates.
- `getUpcomingSubscriptionPaymentDate($subscription, $currentDate = null)` - calculate the next payment date.
- `getSubscriptionOccurrencesInRange($subscription, $rangeStart, $rangeEnd)` - list payment occurrences in a range.
- `shiftToNextWorkingDay(DateTimeImmutable $date)` - move weekend dates to the next working day.
- `getAdjustedPaymentDate($subscription, $currentDate = null, $globalAdjust = false)` - get adjusted payment date.
- `getPreviousSubscriptionPaymentDate($subscription, $currentDate = null)` - get previous payment date.
- `getSubscriptionCycleProgress($subscription, $currentDate = null)` - calculate cycle progress percent.
- `getPassedAutoRenewalOccurrencesInRange($subscription, $rangeStart, $rangeEnd)` - list auto-renewal occurrences already passed.
- `getBillingCycle($cycle, $frequency, $i18n)` - render billing cycle text.
- `getSubscriptionProgress($cycle, $frequency, $next_payment)` - calculate display progress.
- `formatNotificationLeadTime($days, $i18n)` - render notification lead time.
- `getEffectiveNotificationRuleLabel($subscription, $globalNotificationDays, $i18n)` - describe active notification rule.
- `printSubscriptions(...)` - render subscription list markup.
- `normalizeOptionalDateInput($value)` - normalize optional form dates.
- `normalizeSubscriptionForm(array $input)` - normalize subscription form payloads.
- `validateSubscriptionForm(array $subscription, array $i18n)` - validate subscription form data.
- `bindSubscriptionForm(SQLite3Stmt $stmt, array $subscription, int $userId, string $logo)` - bind subscription form data to SQLite.

### Backup, Restore, Zip, and Migration Helpers

- `addFolderToBackupZip($sourceDir, $zipArchive, $zipDir = '')` - add a folder to a backup zip.
- `createWallosBackupZip($zipPath, $appRoot = null)` - create a Wallos backup archive.
- `cleanupOldWallosBackups($backupDir, $retentionDays)` - remove expired scheduled backups.
- `wallosSafeZipExtract(string $zipPath, string $destination, array $options = [])` - safely extract backup zip files.
- `wallosRemoveTree(string $path)` - recursively remove restore workspace files.
- `wallosCleanupRestoreWorkspace(string $tmpDir)` - clean temporary restore workspace.
- `wallosValidateSqliteDatabase(string $dbPath)` - validate restored SQLite database.
- `wallosCopyRestoredLogos(string $restoreDir, string $logosDir)` - restore uploaded logos.
- `wallosRestoreBackup(array $uploadedFile, string $appRoot)` - restore a backup upload.
- `errorHandler($severity, $message, $file, $line)` - migration error handler.

### iCal and Email Helpers

- `wallosGenerateIcalToken()` - generate an iCal token.
- `wallosEnsureIcalToken(SQLite3 $db, int $userId)` - ensure a user has an iCal token.
- `wallosGetIcalUrls(string $token)` - build iCal feed URLs.
- `wallosIcalFail(int $status, string $message)` - emit iCal error responses.
- `wallosIcalEscape($value)` - escape iCal text.
- `wallosIcalDate($date)` - format iCal dates.
- `wallosIcalRrule($cycle, $frequency)` - create recurrence rules.
- `wallosEmailEscape($value)` - escape email HTML.
- `wallosEmailBrandLogoPath()` - resolve brand logo path.
- `wallosEmailSubscriptionLogoPath($logo)` - resolve subscription logo path.
- `wallosEmailEmbedBrandLogo(PHPMailer $mail)` - embed brand logo in email.
- `wallosEmailEmbedSubscriptionLogos(PHPMailer $mail, array &$subscriptions)` - embed subscription logos.
- `wallosBuildPlainTextEmail($intro, array $subscriptions)` - build plain-text notification email.
- `wallosBuildHtmlEmail($title, $intro, array $subscriptions, $actionUrl = '', $actionLabel = 'Open Wallos', $brandLogoCid = null)` - build HTML notification email.

### Admin, Categories, Household, Currency, Logos

- `handleAddCategory($db, $userId, $i18n)` - add category.
- `handleEditCategory($db, $userId, $i18n)` - edit category.
- `handleDeleteCategory($db, $userId, $i18n)` - delete category.
- `handleSortCategories($db, $userId, $i18n)` - sort categories.
- `handleAddMember($db, $userId, $i18n)` - add household member.
- `handleEditMember($db, $userId, $i18n)` - edit household member.
- `handleDeleteMember($db, $userId, $i18n)` - delete household member.
- `handleAddCurrency($db, $userId, $i18n)` - add currency.
- `handleEditCurrency($db, $userId, $i18n)` - edit currency.
- `handleDeleteCurrency($db, $userId, $i18n)` - delete currency.
- `sanitizeFilename($filename)` - sanitize uploaded file names.
- `validateFileExtension($fileExtension)` - validate image extensions.
- `getLogoFromUrl(...)` - download a remote logo for subscriptions or payment methods.
- `saveLogo($imageData, $uploadFile, $name, $settings)` - save downloaded logo data.
- `resizeAndUploadLogo(...)` - resize and save uploaded logos.
- `resizeAndUploadAvatar($uploadedFile, $uploadDir, $name)` - resize and save profile avatars.
- `wallosResolveRedirectUrl(string $baseUrl, string $location)` - resolve logo fetch redirects.
- `wallosFetchLogoFromUrl(string $url, SQLite3 $db, array $options = [])` - fetch a logo with SSRF protection.
- `applyProxy($ch)` - apply configured proxy to cURL requests.
- `curlGet($url, $headers = [])` - fetch remote search results.
- `getVqdToken($query)` - retrieve DuckDuckGo image search token.
- `fetchDDGImages($query, $vqd)` - search DuckDuckGo images.
- `fetchBraveImages($query)` - search Brave images.
- `generate_username_from_email($email)` - derive OIDC username from email.
- `update_exchange_rate($db, $userId)` - refresh exchange rate for user currency.
- `sc_send($text, $desp = '', $key = '')` - send ServerChan test notification.
- `base32_encode($hex)` - encode TOTP secret.
- `trigger_deprecation($package, $version, $message, ...$args)` - compatibility shim for TOTP dependency.
- `translate($text, $translations)` - translate a server-side string.
- `hex2rgb($hex)` - convert hex color to RGB.

## JavaScript Function Index

### Shared UI and Fetch Helpers

- `updateMobileNavPosition()` - adjust mobile navigation position.
- `enableIosMobileNavFallback()` - enable iOS navigation fallback behavior.
- `toggleDropdown()` - toggle dropdown menus.
- `withSpinner(promise, host)` - show spinner around async work.
- `safeFetch(input, init)` - wrapped fetch with error handling.
- `translateOrDefault(key, fallback)` - translate with fallback text.
- `apiFetch(input, init = {}, options = {})` - app API fetch helper.
- `apiErrorMessage(error, fallback)` - normalize API error messages.
- `parseDeclarativeArgs(element)` - parse declarative action arguments.
- `runDeclarativeHandler(event, element, attrName)` - run declarative handlers.
- `closestDeclarativeTarget(event, selector)` - find declarative event target.
- `bindDeclarativeHandlers()` - bind declarative UI actions.
- `navigateTo(url)` - navigate programmatically.
- `showErrorMessage(message, options = {})` - show error toast/banner.
- `showSuccessMessage(message)` - show success toast/banner.
- `getCookie(name)` - read cookies.
- `translate(key)` - client-side i18n lookup.

### Dashboard and Modern/Mobile UI

- `openDashboardEditModal(id)` - open dashboard edit modal.
- `closeDashboardEditModal()` - close dashboard edit modal.
- `updateAiRecommendationNumbers()` - refresh AI recommendation counters.
- `isModernActive()` - detect modern design mode.
- `mobileFirstActive()` - detect mobile-first mode.
- `subscriptionActionClass(action)` - map subscription action to CSS class.
- `escapeAttr(value)` - escape attribute values.
- `sortSubscriptionActions(actions)` - order action buttons.
- `modernActionIcon(action)` - select modern action icon.
- `buildActionsPanel(container)` - build subscription action panel.
- `closeAllExcept(except)` - close other swipe panels.
- `attachSwipe(container)` - attach subscription swipe gestures.
- `wireActionClicks()` - bind modern action clicks.
- `toggleDesktopReveal(target, event)` - toggle desktop action reveal.
- `wireDesktopRevealClicks()` - bind desktop reveal clicks.
- `initSwipe()` - initialize subscription swipe behavior.
- `initFab()` - initialize floating action button behavior.
- `initSettingsTabs()` - initialize settings tabs.
- `extractDashboardId(card)` - read dashboard card ID.
- `buildDashboardActionsPanel(card, id)` - create dashboard action panel.
- `attachDashboardSwipe(wrapper)` - attach dashboard swipe gestures.
- `wireDashboardActionClicks()` - bind dashboard action clicks.
- `initDashboardSwipe()` - initialize dashboard swipe behavior.
- `initFabVisibility()` - control floating action button visibility.
- `init()` - initialize modern UI module.

### Subscriptions UI

- `toggleOpenSubscription(subId)` - expand/collapse subscription details.
- `toggleSortOptions()` - show/hide sort controls.
- `toggleNotificationDays()` - show/hide notification day settings.
- `resetForm()` - reset subscription form.
- `fillEditFormFields(subscription)` - populate edit form.
- `openEditSubscription(event, id)` - open edit modal.
- `addSubscription()` - open add modal.
- `closeAddSubscription()` - close add/edit modal.
- `getFocusableModalElements(modal)` - collect focusable modal elements.
- `focusSubscriptionModal()` - focus subscription modal.
- `trapSubscriptionModalFocus(event)` - trap keyboard focus.
- `handleFileSelect(event)` - preview selected logo/icon file.
- `deleteSubscription(event, id)` - delete subscription.
- `cloneSubscription(event, id)` - clone subscription.
- `renewSubscription(event, id)` - renew subscription.
- `markSubscriptionPaid(event, id)` - mark subscription paid.
- `setSearchButtonStatus()` - enable/disable logo search.
- `searchLogo()` - search for subscription logos.
- `displayImageResults(imageSources)` - render image search results.
- `selectWebLogo(url)` - select remote logo.
- `closeLogoSearch()` - close logo search modal.
- `fetchSubscriptions(id, event, initiator)` - fetch subscription list/details.
- `setSortOption(sortOption)` - save sort option.
- `convertSvgToPng(file, callback)` - convert uploaded SVG to PNG.
- `dataURLtoFile(dataurl, filename)` - convert data URL to File.
- `submitFormData(formData, submitButton, endpoint)` - submit subscription form.
- `runSubscriptionAction(action, id, event)` - route subscription action.
- `bindSubscriptionActionDelegates()` - bind delegated subscription actions.
- `searchSubscriptions()` - filter subscriptions by search query.
- `clearSearch()` - clear subscription search.
- `closeSubMenus()` - close action submenus.
- `setSwipeElements()` - initialize legacy swipe elements.
- `toggleSubMenu(subMenu)` - toggle action submenu.
- `toggleReplacementSub()` - toggle replacement subscription fields.
- `clearFilters()` - clear subscription filters.
- `expandActions(event, subscriptionId)` - expand action buttons.
- `swipeHintAnimation()` - show swipe hint animation.
- `autoFillNextPaymentDate(e)` - auto-fill next payment date.
- `toISOStringWithTimezone(date)` - format date with local timezone.

### Calendar and Stats UI

- `nextMonth(currentMonth, currentYear)` - move calendar forward.
- `prevMonth(currentMonth, currentYear)` - move calendar backward.
- `currentMoth()` - jump to current month.
- `closeSubscriptionModal()` - close calendar subscription modal.
- `runSubscriptionModalAction(endpoint, subscriptionId, successMessage)` - run modal subscription action.
- `translateWithFallback(key, fallback)` - calendar translation helper.
- `calendarDataArgs(args)` - parse calendar modal data.
- `openSubscriptionModal(subscriptionId)` - open calendar subscription modal.
- `loadGraph(container, dataPoints, currency, run)` - render stats bar chart.
- `loadLineGraph(container, dataPoints, currency, run)` - render stats line chart.
- `renderChartWithSpinner(container, render)` - render chart with loading spinner.

### Settings UI

- `saveBudget()` - save user budget.
- `addMemberButton(memberId)` - add household member from UI.
- `removeMember(memberId)` - remove household member.
- `editMember(memberId)` - edit household member.
- `addCategoryButton(categoryId)` - add category from UI.
- `removeCategory(categoryId)` - remove category.
- `editCategory(categoryId)` - edit category.
- `addCurrencyButton(currencyId)` - add currency from UI.
- `removeCurrency(currencyId)` - remove currency.
- `editCurrency(currencyId)` - edit currency.
- `togglePayment(paymentId)` - enable/disable payment method.
- `renamePayment(paymentId, newName)` - rename payment method.
- `searchPaymentIcon()` - search payment method icons.
- `selectWebIcon(url)` - select remote payment icon.
- `closeIconSearch()` - close icon search modal.
- `resetFormIcon()` - reset payment icon form.
- `reloadPaymentMethods()` - reload payment method list.
- `addPaymentMethod()` - add payment method.
- `deletePaymentMethod(paymentId)` - delete payment method.
- `savePaymentMethodsSorting()` - save payment method order.
- `addFixerKeyButton()` - save Fixer API key.
- `updateIcalFeedUrls(data)` - refresh displayed iCal URLs.
- `saveIcalSettings()` - save iCal settings.
- `copyIcalFeedUrl()` - copy iCal feed URL.
- `regenerateIcalToken()` - regenerate private iCal token.
- `storeSettingsOnDB(endpoint, value)` - save generic setting.
- `setShowMonthlyPrice()` - save monthly price display setting.
- `setConvertCurrency()` - save currency conversion setting.
- `setRemoveBackground()` - save logo background setting.
- `setAdjustToWorkingDay()` - save working-day adjustment setting.
- `setHideDisabled()` - save hide-disabled setting.
- `setDisabledToBottom()` - save disabled-to-bottom setting.
- `setShowOriginalPrice()` - save original-price setting.
- `setMobileNavigation()` - save mobile navigation setting.
- `setShowSubscriptionProgress()` - save progress display setting.
- `saveCategorySorting()` - save category order.
- `fetch_ai_models()` - fetch AI provider models.
- `toggleAiInputs()` - toggle AI settings inputs.
- `toggleAiApiKeyVisibility()` - show/hide AI API key.
- `saveAiSettingsButton()` - save AI settings.
- `runAiRecommendations()` - generate AI recommendations.
- `clearBrowserCacheAndReload()` - clear browser caches and reload.

### Notifications UI

- `openNotificationsSettings(type)` - open notification settings section.
- `makeFetchCall(url, data, button)` - submit notification setting/test request.
- `saveNotifications()` - save global notification settings.
- `saveNotificationsEmailButton()` - save email notification settings.
- `testNotificationEmailButton()` - test email notifications.
- `saveNotificationsWebhookButton()` - save webhook notification settings.
- `testNotificationsWebhookButton()` - test webhook notifications.
- `saveNotificationsTelegramButton()` - save Telegram settings.
- `testNotificationsTelegramButton()` - test Telegram notifications.
- `saveNotificationsPushPlusButton()` - save PushPlus settings.
- `testNotificationsPushPlusButton()` - test PushPlus notifications.
- `saveNotificationsMattermostButton()` - save Mattermost settings.
- `testNotificationsMattermostButton()` - test Mattermost notifications.
- `saveNotificationsGotifyButton()` - save Gotify settings.
- `testNotificationsGotifyButton()` - test Gotify notifications.
- `saveNotificationsPushoverButton()` - save Pushover settings.
- `testNotificationsPushoverButton()` - test Pushover notifications.
- `saveNotificationsDiscordButton()` - save Discord settings.
- `testNotificationsDiscordButton()` - test Discord notifications.
- `saveNotificationsNtfyButton()` - save Ntfy settings.
- `testNotificationsNtfyButton()` - test Ntfy notifications.
- `saveNotificationsServerchanButton()` - save ServerChan settings.
- `testNotificationsServerchanButton()` - test ServerChan notifications.

### Profile, Admin, Registration, Theme

- `toggleAvatarSelect()` - open/close avatar selector.
- `closeAvatarSelect()` - close avatar selector.
- `changeAvatar(src)` - change selected avatar.
- `successfulUpload(field, msg)` - show upload success.
- `deleteAvatar(path)` - delete profile avatar.
- `enableTotp()` - request TOTP enablement.
- `openTotpPopup()` - open TOTP popup.
- `closeTotpPopup()` - close TOTP popup.
- `submitTotp()` - submit TOTP setup code.
- `copyBackupCodes()` - copy TOTP backup codes.
- `downloadBackupCodes()` - download TOTP backup codes.
- `closeTotpDisablePopup()` - close disable-TOTP popup.
- `disableTotp()` - open disable-TOTP flow.
- `submitDisableTotp()` - submit disable-TOTP request.
- `regenerateApiKey()` - regenerate API key.
- `exportAsJson()` - export user data as JSON.
- `exportAsCsv()` - export user data as CSV.
- `deleteAccount(userId)` - delete account.
- `makeFetchCall(url, data, button)` - admin request helper.
- `testSmtpSettingsButton()` - test SMTP settings.
- `saveSmtpSettingsButton()` - save SMTP settings.
- `backupDB()` - create/download database backup.
- `openRestoreDBFileSelect()` - open restore file selector.
- `restoreDB()` - restore database backup.
- `saveAccountRegistrationsButton()` - save registration settings.
- `saveSecuritySettingsButton()` - save security settings.
- `removeUser(userId)` - delete a user.
- `addUserButton()` - add a user.
- `deleteUnusedLogos()` - clean unused logos.
- `toggleUpdateNotification()` - toggle update notifications.
- `executeCronJob(job)` - execute cron job from admin UI.
- `runCronJob(job)` - run cron job wrapper.
- `normalizeCronOutput(output)` - normalize cron output display.
- `runNotificationCheck()` - run notification cron check.
- `toggleOidcEnabled()` - toggle OIDC login.
- `saveOidcSettingsButton()` - save OIDC settings.
- `setCookie(name, value, days)` - set registration cookie.
- `storeFormFieldValue(fieldId)` - store registration field.
- `storeFormFields()` - store registration form.
- `restoreFormFieldValue(fieldId)` - restore registration field.
- `restoreFormFields()` - restore registration form.
- `removeFromStorage()` - clear registration storage.
- `changeLanguage(selectedLanguage)` - change registration language.
- `runDatabaseMigration()` - run setup migration.
- `checkThemeNeedsUpdate()` - check theme refresh requirement.
- `enableGoToLoginButton()` - enable login button after setup.
- `bindRegistrationControls()` - bind registration UI events.
- `switchTheme()` - toggle dark/light/system theme.
- `setDarkTheme(theme)` - set dark theme mode.
- `setTheme(themeColor)` - set color theme.
- `setDesignTheme(design)` - set design theme.
- `resetCustomColors()` - reset custom colors.
- `saveCustomColors()` - save custom colors.
- `saveCustomCss()` - save custom CSS.
- `clearAndRedirect()` - clear client caches on logout before redirecting.

## Tests and Tooling Functions

- `smokeAssert(bool $condition, string $message)` - smoke test assertion.
- `smokeTableExists(SQLite3 $db, string $table)` - check table existence.
- `smokeColumns(SQLite3 $db, string $table)` - list table columns.
- `smokeRemoveTree(string $path)` - remove temporary smoke-test files.
- `smokeRunPhpLint(string $appRoot)` - run PHP lint checks.
- `smokeRunFreshDatabaseBootstrap(string $appRoot)` - test fresh database bootstrap.
- `smokeRunSafeZipTest(string $appRoot)` - test safe zip extraction.
- `smokeCreateSqliteDb(string $dbPath, string $marker)` - create test SQLite DB.
- `smokeRunRestoreSafetyTest(string $appRoot)` - test restore safety behavior.
- `smokeRunArchitectureHelperTests(string $appRoot)` - test helper behavior.

