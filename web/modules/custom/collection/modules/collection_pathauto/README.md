# Collection Pathauto

This module integrates pathauto with the `canonical` collection item setting.

It will prepend the URL alias of the Collection to any existing pathauto pattern for an item placed in a collection.

For example, if:

1. A `post` content type has the pathauto pattern `post/[node:title]`
2. A `post` node is added to a Collection that has the URL alias `my-blog`
3. The collection item `canonical` bit is set to `TRUE`

Then the resulting pattern for the `post` node would be `my-blog/post/[node:title]`.

When modifying the pathauto pattern, this module fires a new alter hook to allow further modification of the already modified pattern. See collection_pathauto.api.php.
