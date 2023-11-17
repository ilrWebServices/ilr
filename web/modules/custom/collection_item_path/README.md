# Collection Item Path

## Introduction

The Collection item path module is an add-on to the [Collection module](https://www.drupal.org/project/collection). This module extends collection items by adding a base field for Drupal's path module and the ability to configure them for display. An example usage would be to be able to "cross-post" content from one blog to another on a given site, while not getting penalized on SEO.

## Usage

Once the module is enabled, you can configure the field formatter for the collected item on relevant collection item types (e.g. `admin/structure/collection_item/default/display`) to display the rendered entity.

Since collection items are typically not used as display entities, the next step is to intercept the collected items during the render process so that they can be used for display. An example might be a paragraph type that renders all entities for a given collection. For example, here's a snippet that comes from a paragraph behavior plugin `preprocess` function that is rendering `Post` nodes from a selected collection:

```
$collection = $paragraph->field_collection->entity;
$collection_item_storage = $this->entityTypeManager->getStorage('collection_item');
$view_builder = $this->entityTypeManager->getViewBuilder('node');
$cache_tags = $collection->getCacheTags();
$cache_tags[] = 'node_list:post'; // 8.9 and above
$posts = [];

$query = $collection_item_storage->getQuery();
$query->accessCheck(TRUE)
$query->condition('collection', $collection->id());
$query->condition('type', 'blog');
$query->condition('item.entity:node.status', 1);
$query->condition('item.entity:node.type', 'post');
$result = $query->execute();

$post_count = 0;
foreach ($collection_item_storage->loadMultiple($result) as $collection_item) {
  $post_count++;
  $rendered_entity = $view_builder->view($collection_item->item->entity, $this->getViewModeForListStyle($paragraph, $list_style, $post_count));
  $rendered_entity['#collection_item'] = $collection_item;
  $rendered_entity['#cache']['contexts'][] = 'url';
  $posts[] = $rendered_entity;
}

// Render the posts instead of the collection.
$variables['content']['field_collection']['#printed'] = TRUE;
$variables['content']['post_listing'] = [
  'items' => $posts,
  '#cache' => [
    'tags' => $cache_tags,
  ],
];
```
The critical line in the example above is where we add the `'#collection_item'` key to the render array. The alter hook `collection_item_path_node_view_alter()` will then set the url to the collection item, which will modify the `url` sent to the template during node preprocessing.

## Additional Features

This module modifies the links in the CollectionItemListBuilder to point to the collection items, rather than the collected content. By default, it always redirects requests to canonical collection items to the collected entity. Non-canonical items can be viewed via the configuration described above. When viewing a non-canonical collection item, admins will see an "Edit item" tab (replacing the typical "Edit" tab), as well as an "Edit content" tab, which redirects to the collected item edit form.

Additionally, this module plays well with the [Metatag](https://www.drupal.org/project/metatag) and [Extra Field](https://www.drupal.org/project/extra_field) modules. With the Metatag module enabled, the canonical link will automatically be added to the html head.

If rendering collection items as extra fields, simply add the `['#collection_item']` key to the render array for each item being rendered, and the urls passed to the templates will updated accordingly.
