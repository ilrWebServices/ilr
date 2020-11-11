# ILR Auth Access

This simple module allows editors to restrict the content in content entities to authenticated users only.

Note that, unlike other access modules, protected pages do not return an HTTP 403 status code. This is mainly so that the entities will still appear in menus and listings. 

To restrict a content entity (e.g. node, media, collection, etc.), add a field with the machine name `auth_protected` to the entity. The machine name should NOT be prefixed with `field_`.

If a content entity has the `auth_protected` field and its value is set to 1 (or anything truthy), the page will display a message and hide the content.
