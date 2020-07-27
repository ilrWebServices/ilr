# Menu Graft

This module allows the items in one menu to be grafted as children to a menu link in another menu.

One use case is to allow content section menus to be managed separately while still allowing their links to be part of the main menu. This could allow more granular menu admin permissions (by using Menu Admin per Menu, for example).

## Known issues

- Unsetting the menu graft does not remove the grafted mirror items, and clearing caches doesn't seem to do so either.
- Permissions for menu link items may not propagate to the mirrored graft items. More testing needs to be done here.
