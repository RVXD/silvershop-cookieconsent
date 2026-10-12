<%-- One category's cookie declaration table. Rendered in the context of a
     CookieCategory (by the [cookie_declaration] shortcode and the policy page). --%>
<div class="cookie-declaration">
    <h3 class="cookie-declaration__title">$Title</h3>
    <% if $Cookies %>
        <div class="cookie-declaration__scroll">
            <table class="cookie-declaration__table">
                <thead>
                    <tr>
                        <th><%t SilverShop\CookieConsent\Declaration.Name 'Name' %></th>
                        <th><%t SilverShop\CookieConsent\Declaration.Provider 'Placed by' %></th>
                        <th><%t SilverShop\CookieConsent\Declaration.Purpose 'Purpose' %></th>
                        <th><%t SilverShop\CookieConsent\Declaration.Expiry 'Expiry' %></th>
                    </tr>
                </thead>
                <tbody>
                    <% loop $Cookies %>
                        <tr>
                            <td>$Name</td>
                            <td>$Provider</td>
                            <td>$Purpose</td>
                            <td>$Expiry</td>
                        </tr>
                    <% end_loop %>
                </tbody>
            </table>
        </div>
    <% end_if %>
</div>
