# Collection Node Menu

This module does two things related to adding nodes to menus.

1. When adding or editing a page node in a Collection, include the `subsite` or
   `content section` menu as the only 'Parent link' option in 'Menu settings'.
2. Allow update access to `subsite` and `content section` menus to owners of
   Collections.

This module requires the `menu_ui_access.patch` to allow access to the menu item
field form element added in `menu_ui_form_node_form_alter()`.
