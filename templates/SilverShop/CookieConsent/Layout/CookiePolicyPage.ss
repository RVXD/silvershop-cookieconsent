<div class="cookie-policy">
    <div class="cookie-policy__content typography">$Content</div>

    <form class="cookie-manage" method="post" action="$SaveCookieConsentLink">
        <h2 class="cookie-manage__title"><%t SilverShop\CookieConsent\ConsentState.PreferencesTitle 'Cookie preferences' %></h2>
        <% loop $CookieConsentCategories %>
            <fieldset class="cookie-manage__cat">
                <label class="switch cookie-manage__toggle">
                    <input class="switch__input" type="checkbox" name="categories[]" value="$Code"<% if $isRequired %> checked disabled<% else_if $isGranted %> checked<% end_if %> />
                    <strong>$Title</strong><% if $isRequired %> <span class="cookie-manage__required">(<%t SilverShop\CookieConsent\ConsentState.AlwaysOn 'always on' %>)</span><% end_if %>
                </label>
                <div class="cookie-manage__desc">$Content</div>
                <% if $ShowCookies %><% include SilverShop\CookieConsent\CookieDeclarationTable %><% end_if %>
            </fieldset>
        <% end_loop %>
        <button type="submit" class="consent__btn consent__btn--primary"><%t SilverShop\CookieConsent\ConsentState.SavePreferences 'Save preferences' %></button>
    </form>
</div>
