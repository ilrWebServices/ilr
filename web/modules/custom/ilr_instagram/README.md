# ILR Instagram module

This simple module adds an instagram feed parsing service that fetches Instagram posts for a given feed url without using an API.

Our `cornellilr` instagram account has a feed configured in Zapier at https://zapier.com/engine/rss/2606624/cornell-ilr-school-instagram.

The service is currently only used for a Paragraphs behavior, but it could also be used for a block or extra field.

## Theming

No CSS styling is included in this base module, but the rendered output can be easily modified using custom templates.

### Post listing

Create a template called `container--instagram-listing.html.twig` and output the `children` variable. Note that this is just a core `container.html.twig` render element with an additional suggestion. See the [Theme system overview][] for a bit more information.

### Post item

Individual posts can be themed in a template called `container--instagram-post.html.twig`. Avaliable properties of the `children` variable in this context include:

```
children.caption - The post caption.
children.image - A render array with the instagram media file url and an image style.
children.url - A URL to the post on instagram.
```

See `union_marketing_preprocess_imagecache_external__instagram_post()` to see how we alter the image style used in our instagram listing.

[Theme system overview]: https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!theme.api.php/group/themeable/9.1.x#sec_preprocess_templates
