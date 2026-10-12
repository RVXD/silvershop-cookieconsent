<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Control;

use PageController;

/**
 * Controller for the optional {@link \SilverShop\CookieConsent\Model\CookiePolicyPage}.
 *
 * It carries no logic of its own — the consent actions (acceptAllCookies,
 * acceptNecessaryCookies, saveCookieConsent) come from
 * {@link \SilverShop\CookieConsent\Extension\ContentControllerExtension}, which
 * applies to every ContentController. Its only job is to be a distinct type so
 * the banner suppresses itself on this page (see PromptCookieConsent), and to
 * render the standalone declaration + management Layout template.
 *
 * @mixin \SilverShop\CookieConsent\Model\CookiePolicyPage
 */
class CookiePolicyPageController extends PageController
{
}
