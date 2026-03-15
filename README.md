# skipper-php

[![Tests](https://github.com/get-skipper/skipper-php/actions/workflows/tests.yml/badge.svg)](https://github.com/get-skipper/skipper-php/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/get-skipper/skipper-php.svg)](https://packagist.org/packages/get-skipper/skipper-php)
[![PHP](https://img.shields.io/packagist/php-v/get-skipper/skipper-php.svg)](https://packagist.org/packages/get-skipper/skipper-php)
[![License](https://img.shields.io/packagist/l/get-skipper/skipper-php.svg)](LICENSE)

Test-gating for PHP via Google Sheets or Excel on Office 365. Enable or disable tests without changing code — just update a date in a spreadsheet.

A PHP port of [get-skipper/skipper](https://github.com/get-skipper/skipper), supporting PHPUnit, Pest, Behat, Codeception, PHPSpec, and Kahlan.

---

## How it works

A spreadsheet (Google Sheets or Excel on Office 365) stores test IDs with optional `disabledUntil` dates:

| testId | disabledUntil | notes |
|--------|---------------|-------|
| `tests/Feature/AuthTest.php > AuthTest > testItCanLogin` | | |
| `tests/Feature/PaymentTest.php > PaymentTest > testCheckout` | `2099-12-31` | Flaky on CI |
| `features/auth.feature > Auth > User can log in` | `2026-06-01` | Under investigation |

- **Empty `disabledUntil`** → test runs normally
- **Past date** → test runs normally
- **Future date** → test is skipped automatically

Tests not listed in the spreadsheet **always run** (opt-out model).

---

## Installation

```bash
composer require get-skipper/skipper-php
```

Install your test framework if not already present:

```bash
# PHPUnit / Pest
composer require --dev phpunit/phpunit

# Behat
composer require --dev behat/behat

# Codeception
composer require --dev codeception/codeception

# PHPSpec
composer require --dev phpspec/phpspec

# Kahlan
composer require --dev kahlan/kahlan
```

---

## Google Sheets setup

1. Create a Google Spreadsheet with the following columns in row 1:
   - `testId`
   - `disabledUntil`
   - `notes` (optional)

2. Create a Google Cloud service account and download the JSON key file.

3. Share the spreadsheet with the service account's email address (`client_email` in the JSON).

4. Note the spreadsheet ID from the URL:
   `https://docs.google.com/spreadsheets/d/YOUR_SPREADSHEET_ID/edit`

---

## Excel / Office 365 setup

1. Create an Excel workbook (`.xlsx`) in OneDrive or SharePoint with columns `testId`, `disabledUntil`, `notes` in row 1.

2. Register an Azure AD application:
   - [Azure Portal](https://portal.azure.com) → **Azure Active Directory → App registrations → New registration**
   - Note the **Application (client) ID** and **Directory (tenant) ID**
   - **Certificates & secrets → New client secret** — copy the Value immediately

3. Grant Microsoft Graph API permissions:
   - **API permissions → Add a permission → Microsoft Graph → Application permissions**
   - Add `Files.ReadWrite.All` (OneDrive) or `Sites.ReadWrite.All` (SharePoint)
   - Click **Grant admin consent for {tenant}**

4. Share the workbook folder/drive with the app's service principal (**Edit** role for sync mode, **Read** for read-only).

5. Find the `workbookId` via [Graph Explorer](https://developer.microsoft.com/graph/graph-explorer):
   ```
   GET https://graph.microsoft.com/v1.0/drives/{driveId}/root/children
   ```
   The `workbookId` is `"drives/{driveId}/items/{itemId}"` (copy from the response).

---

## Credentials

### Google Sheets credentials

Three formats are accepted:

| Format | Class | Use case |
|--------|-------|----------|
| File path | `FileCredentials('./service-account.json')` | Local development |
| Base64 string | `Base64Credentials('eyJ0eX...')` | CI/CD inline secret |
| Environment variable | `Base64Credentials((string) getenv('GOOGLE_CREDS_B64'))` | CI/CD env var (base64) |

To encode your credentials file for CI:
```bash
base64 -i service-account.json | tr -d '\n'
```

### Excel / Office 365 credentials

Three formats are accepted (JSON contains `tenantId`, `clientId`, `clientSecret`):

| Format | Class | Use case |
|--------|-------|----------|
| Inline | `AzureClientSecretCredentials($tenantId, $clientId, $clientSecret)` | Simple / hardcoded |
| File path | `AzureFileCredentials('./azure-creds.json')` | Local development |
| Base64 string | `AzureBase64Credentials((string) getenv('AZURE_CREDS_B64'))` | CI/CD env var (base64) |

To encode your Azure credentials file for CI:
```bash
# azure-creds.json: {"tenantId":"...","clientId":"...","clientSecret":"..."}
base64 -i azure-creds.json | tr -d '\n'
```

---

## Framework integrations

### PHPUnit (10 / 11 / 12)

**Google Sheets** — add to `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php">

  <testsuites>
    <testsuite name="Tests">
      <directory>tests</directory>
    </testsuite>
  </testsuites>

  <extensions>
    <bootstrap class="GetSkipper\PHPUnit\SkipperExtension">
      <parameter name="spreadsheetId" value="YOUR_SPREADSHEET_ID"/>

      <!-- Choose one credentials option: -->
      <parameter name="credentialsFile" value="./service-account.json"/>
      <!-- <parameter name="credentialsBase64" value="eyJ0eXBlIjoic2VydmljZV9hY2NvdW50Ii4uLn0="/> -->
      <!-- <parameter name="credentialsEnvVar" value="GOOGLE_CREDS_B64"/> -->

      <parameter name="sheetName" value="MySheet"/>  <!-- optional -->
    </bootstrap>
  </extensions>

</phpunit>
```

**Excel / Office 365** — use `ExcelConfig` instead:

```xml
  <extensions>
    <bootstrap class="GetSkipper\PHPUnit\SkipperExtension">
      <parameter name="source" value="excel"/>
      <parameter name="workbookId" value="drives/YOUR_DRIVE_ID/items/YOUR_ITEM_ID"/>

      <!-- Choose one credentials option: -->
      <parameter name="credentialsEnvVar" value="AZURE_CREDS_B64"/>
      <!-- <parameter name="credentialsFile" value="./azure-creds.json"/> -->

      <parameter name="sheetName" value="MySheet"/>  <!-- optional -->
    </bootstrap>
  </extensions>
```

**Test ID format:**
```
tests/Unit/AuthTest.php > AuthTest > testItCanLogin
tests/Unit/AuthTest.php > AuthTest > testWithDataProvider with data set "valid"
```

---

### Pest (v2 / v3)

Pest runs on top of PHPUnit — use the same `phpunit.xml` configuration above. The extension auto-detects Pest-generated classes (`P\` namespace prefix) and applies the correct ID format automatically.

**Test ID format:**
```
tests/Feature/auth.php > can login
tests/Feature/auth.php > Auth > can login      ← with describe() block
```

**Alternative: hook-based setup via `tests/Pest.php`**

```php
<?php
// tests/Pest.php

use GetSkipper\Core\Config\ExcelConfig;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\AzureBase64Credentials;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Pest\Plugin;

// Google Sheets
Plugin::skipperSetup(new SkipperConfig(
    spreadsheetId: 'YOUR_SPREADSHEET_ID',
    credentials: new FileCredentials('./service-account.json'),
    // credentials: new Base64Credentials((string) getenv('GOOGLE_CREDS_B64')),
    sheetName: 'MySheet', // optional
));

// OR — Excel / Office 365
Plugin::skipperSetup(new ExcelConfig(
    workbookId: 'drives/YOUR_DRIVE_ID/items/YOUR_ITEM_ID',
    credentials: new AzureBase64Credentials((string) getenv('AZURE_CREDS_B64')),
    sheetName: 'MySheet', // optional
));
```

---

### Behat (3.x)

Add to `behat.yml`:

```yaml
default:
  extensions:
    GetSkipper\Behat\SkipperExtension:
      spreadsheetId: 'YOUR_SPREADSHEET_ID'

      # Choose one credentials option:
      credentialsFile: './service-account.json'
      # credentialsBase64: 'eyJ0eXBlIjoic2VydmljZV9hY2NvdW50Ii4uLn0='
      # credentialsEnvVar: 'GOOGLE_CREDS_B64'

      sheetName: 'MySheet'  # optional

  suites:
    default:
      contexts:
        - GetSkipper\Behat\SkipperContext
        # Add your other contexts here
        - App\Context\FeatureContext
```

Disabled scenarios are marked as **Pending** (yellow).

**Test ID format:**
```
features/auth/login.feature > User authentication > User can log in
```

---

### Codeception (5.x)

Add to `codeception.yml`:

```yaml
extensions:
  enabled:
    - GetSkipper\Codeception\SkipperExtension

  config:
    GetSkipper\Codeception\SkipperExtension:
      spreadsheetId: 'YOUR_SPREADSHEET_ID'

      # Choose one credentials option:
      credentialsFile: './service-account.json'
      # credentialsBase64: 'eyJ0eXBlIjoic2VydmljZV9hY2NvdW50Ii4uLn0='
      # credentialsEnvVar: 'GOOGLE_CREDS_B64'

      sheetName: 'MySheet'  # optional
```

**Test ID format:**
```
tests/Acceptance/AuthCest.php > AuthCest > tryToLogin
tests/Unit/AuthTest.php > AuthTest > testItCanLogin
```

---

### PHPSpec (7 / 8)

Add to `phpspec.yml`:

```yaml
extensions:
  GetSkipper\PHPSpec\SkipperExtension:
    spreadsheetId: 'YOUR_SPREADSHEET_ID'

    # Choose one credentials option:
    credentialsFile: './service-account.json'
    # credentialsBase64: 'eyJ0eXBlIjoic2VydmljZV9hY2NvdW50Ii4uLn0='
    # credentialsEnvVar: 'GOOGLE_CREDS_B64'

    sheetName: 'MySheet'  # optional
```

Disabled specs are marked as **Skipped**.

**Test ID format:**
```
spec/Auth/LoginSpec.php > LoginSpec > it login with valid credentials
spec/Auth/LoginSpec.php > LoginSpec > it reject invalid passwords
```

---

### Kahlan (5.x / 6.x)

In `kahlan-config.php`:

```php
<?php
// kahlan-config.php

use GetSkipper\Core\Config\ExcelConfig;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\AzureBase64Credentials;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Kahlan\SkipperPlugin;

$config->beforeAll(function () {
    // Google Sheets
    SkipperPlugin::setup(new SkipperConfig(
        spreadsheetId: 'YOUR_SPREADSHEET_ID',
        credentials: new FileCredentials('./service-account.json'),
        // credentials: new Base64Credentials((string) getenv('GOOGLE_CREDS_B64')),
        sheetName: 'MySheet', // optional
    ));

    // OR — Excel / Office 365
    // SkipperPlugin::setup(new ExcelConfig(
    //     workbookId: 'drives/YOUR_DRIVE_ID/items/YOUR_ITEM_ID',
    //     credentials: new AzureBase64Credentials((string) getenv('AZURE_CREDS_B64')),
    //     sheetName: 'MySheet',
    // ));
});

// Check tests globally (all specs in all directories):
$config->scope()->beforeEach(function () {
    SkipperPlugin::checkTest($this);
});
```

**Test ID format:**
```
spec/Auth/LoginSpec.php > Auth > Login > can login with valid credentials
```

---

## Sync mode

In sync mode, the spreadsheet is automatically reconciled with your test suite:
- **New tests** are added as rows (with empty `disabledUntil`)
- **Removed tests** are deleted from the spreadsheet

Enable with the `SKIPPER_MODE` environment variable:

```bash
# PHPUnit / Pest
SKIPPER_MODE=sync vendor/bin/phpunit

# Behat
SKIPPER_MODE=sync vendor/bin/behat

# Codeception
SKIPPER_MODE=sync vendor/bin/codecept run

# PHPSpec
SKIPPER_MODE=sync vendor/bin/phpspec run

# Kahlan
SKIPPER_MODE=sync vendor/bin/kahlan
```

> **Note:** Sync only writes to the primary sheet. Reference sheets are never modified.

### Sync via GitHub Actions

The bundled workflow (`.github/workflows/tests.yml`) includes a `sync` job that runs automatically after every push to `main`, once all tests have passed:

```yaml
sync:
  name: Sync spreadsheet
  needs: tests
  if: github.ref == 'refs/heads/main' && github.event_name == 'push'
  steps:
    - run: composer test
      env:
        SKIPPER_MODE: sync
        GOOGLE_CREDS_B64: ${{ secrets.GOOGLE_CREDS_B64 }}
        # OR for Excel: AZURE_CREDS_B64: ${{ secrets.AZURE_CREDS_B64 }}
```

This ensures the spreadsheet is always up to date with the current test suite on `main`. The sync job is skipped on pull requests and on branches other than `main`.

---

## Reference sheets

You can merge test entries from multiple sheets. When the same test ID appears in multiple sheets, the most restrictive (latest) `disabledUntil` wins.

Configure additional sheets with `referenceSheets`:

```xml
<!-- phpunit.xml -->
<parameter name="sheetName" value="Main"/>
<parameter name="referenceSheets" value='["SharedDisabled", "QA"]'/>
```

```yaml
# behat.yml / phpspec.yml / codeception.yml
sheetName: 'Main'
referenceSheets: ['SharedDisabled', 'QA']
```

---

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SKIPPER_MODE` | `read-only` | Set to `sync` to enable spreadsheet reconciliation |
| `SKIPPER_CACHE_FILE` | _(auto)_ | Path to the resolver cache file (set by the main process) |
| `SKIPPER_DISCOVERED_DIR` | _(auto)_ | Directory for collecting discovered test IDs across workers |
| `SKIPPER_DEBUG` | _(unset)_ | Set to any non-empty value to enable verbose logging |

---

## Test ID format reference

| Framework | Format example |
|-----------|----------------|
| PHPUnit | `tests/Unit/AuthTest.php > AuthTest > testItCanLogin` |
| Pest | `tests/Feature/auth.php > Auth > can login` |
| Behat | `features/auth.feature > User authentication > User can log in` |
| Codeception | `tests/Acceptance/AuthCest.php > AuthCest > tryToLogin` |
| PHPSpec | `spec/Auth/LoginSpec.php > LoginSpec > it login with valid credentials` |
| Kahlan | `spec/Auth/LoginSpec.php > Auth > Login > can login` |

All test IDs are **case-insensitive** and **whitespace-collapsed** for comparison.

---

## CI — GitHub Actions

The repository ships with a workflow at `.github/workflows/tests.yml` that runs the test suite across PHP 8.2–8.4 and a linting job.

### Required secret

Add your credentials as a GitHub Actions secret:

**Google Sheets:**
1. Go to your repository → **Settings** → **Secrets and variables** → **Actions**
2. Name: `GOOGLE_CREDS_B64` — Value: output of `base64 -i service-account.json`

**Excel / Office 365:**
1. Create `azure-creds.json`: `{"tenantId":"...","clientId":"...","clientSecret":"..."}`
2. Name: `AZURE_CREDS_B64` — Value: output of `base64 -i azure-creds.json`

The workflow passes the secret as an environment variable:

```yaml
- name: Run tests
  run: composer test
  env:
    GOOGLE_CREDS_B64: ${{ secrets.GOOGLE_CREDS_B64 }}
    # OR for Excel: AZURE_CREDS_B64: ${{ secrets.AZURE_CREDS_B64 }}
```

### PHP version matrix

The workflow tests against all supported PHP versions in parallel:

```yaml
strategy:
  fail-fast: false
  matrix:
    php: ['8.2', '8.3', '8.4']
```

---

## Publishing to Packagist

### 1. Prepare the repository

Ensure `composer.json` has the correct `name`, `description`, `license`, and `require` fields. The package name must match the intended Packagist slug:

```json
{
    "name": "get-skipper/skipper-php",
    "description": "Test-gating via Google Sheets or Excel on Office 365 for PHP test frameworks",
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "google/apiclient": "^2.15"
    }
}
```

### 2. Tag a release

Packagist uses Git tags as versions. Follow [semantic versioning](https://semver.org/):

```bash
git tag v1.0.0
git push origin v1.0.0
```

### 3. Submit to Packagist

1. Sign in at [packagist.org](https://packagist.org)
2. Click **Submit** in the top-right menu
3. Enter your repository URL: `https://github.com/get-skipper/skipper-php`
4. Click **Check** → **Submit**

### 4. Enable auto-updates via GitHub

Packagist can be notified of new tags automatically using the GitHub integration:

1. On Packagist, go to your package page and copy the **API token** from your [profile](https://packagist.org/profile/)
2. In your GitHub repository, go to **Settings** → **Webhooks** → **Add webhook**
3. Set **Payload URL** to `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
4. Set **Content type** to `application/json`
5. Set **Secret** to your Packagist API token
6. Select **Just the push event** and save

From this point on, every `git push` (including new tags) will trigger a Packagist update within seconds.
