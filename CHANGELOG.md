# Changelog

All notable changes to `silvershop/cookieconsent` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project adheres to Semantic Versioning.

## [Unreleased]

### Added — initial module (slices 1–3 of the plan)

- **Consent-state core.** `ConsentState` service as the single source of truth: one server- and JS-readable JSON cookie
  (`{v,t,m,i,c}`), the standard `necessary / preferences / statistics / marketing` taxonomy (native, no legacy group
  aliases), `has()` / `granted()` / `needsPrompt()` / `version()` / `consentId()`, pure/testable `evaluateNeedsPrompt()`
  and `deriveMethod()`, and `save()` / `grantAll()` / `rejectAll()`.
- **Template + JS API.** `$HasConsent('statistics')` / `$CookieConsentNeedsPrompt` / `$CookieConsentVersion` template
  globals; `window.SilverShopConsent` (`has`/`grantAll`/`rejectAll`/`save`/`open`/`close`) + a `consentchange` event.
- **Accessible banner + in-banner preferences centre.** Framework-neutral, token-driven, overridable template reconciled
  with the `silvershop/storefront` markup (`#consent` / `.consent__*` / `data-consent`); Esc, focus management, no
  pre-ticked boxes, reject kept as prominent as accept, `prefers-reduced-motion`. Zero-config JS injection when no theme
  banner is present.
- **Script / tag blocking.** `type="text/plain" data-consent-category` activation on consent + on `consentchange`; a
  `ConsentScript::inline()/src()` PHP helper; a `[consent_embed]` click-to-load placeholder for third-party iframes.
- **CMS cookie declaration.** `ConsentAdmin` ModelAdmin over `CookieCategory` + `Cookie` (seeded from config) and the
  consent log; a `[cookie_declaration]` shortcode; an optional, off-by-default Cookie Policy page for no-JS management.
- **Google Consent Mode v2 / GTM.** Config-driven category → signal map; `default` denied emitted before tags (env-gated)
  + `update` and dataLayer events on consent.
- **Immutable consent log.** `ConsentRecord` (pseudonymous, no PII, `canEdit/Delete/Create = false`) +
  a `PruneConsentRecords` retention BuildTask.
- **Re-consent / versioning.** `consent_version`, `consent_lifetime_days` (default ~6 months) and a post-reject
  suppression window.
- **Analytics bridge.** `ConsentState.session_keys` mirrors `statistics` consent onto the `analytics_consent` session
  flag that `silvershop/analytics` already gates on — zero coupling.
- i18n in en, nl, de, fr, es, it. CI (PHP 8.3 + 8.4), phpcs (PSR-12), phpstan (silverstan L5), phpunit.

### Deferred (roadmap)

- Geo-gating (EU/EEA-only banner), CCPA/CPRA “Do Not Sell/Share” opt-out and Global Privacy Control (`Sec-GPC`).
