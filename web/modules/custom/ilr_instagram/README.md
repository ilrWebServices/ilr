# ILR Instagram module

This simple module adds a screen scraping service that fetches Instagram posts for a given username without using an API.

This technique is used by another Drupal module called [Instagram without API][] and some other projects found on github:

- https://github.com/jsanahuja/InstagramFeed/blob/master/src/InstagramFeed.js
- https://github.com/sexybiggetje/brandtrapselfie.nl/blob/master/lib/instagram.php

The service is currently only used for a Paragraphs behavior, but it could also be used for a block or extra field.

## Theming

No CSS styling is included in this base module, but the rendered output can be easily modified using custom templates.

### Post listing

Create a template called `container--instagram-listing.html.twig` and output the `children` variable. Note that this is just a core `container.html.twig` render element with an additional suggestion. See the [Theme system overview][] for a bit more information.

### Post item

Individual posts can be themed in a template called `container--instagram-post.html.twig`. Avaliable properties of the `children` variable in this context include:

```
children.caption - The post caption.
children.image - An img element with the src and alt attributes filled in.
children.url - A URL to the post on instagram.
```

[Instagram without API]: https://git.drupalcode.org/project/instagram_without_api/-/blob/8.x-1.0/src/Plugin/Block/InstagramWithoutApi.php
[Theme system overview]: https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!theme.api.php/group/themeable/9.1.x#sec_preprocess_templates
