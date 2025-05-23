# Webform Reference Category Select

Enhances the Webform Reference field widget to limit available webforms by category.

## Requirements

The following patch is required to the Webform module:

```diff
diff --git a/src/Plugin/Field/FieldWidget/WebformEntityReferenceSelectWidget.php b/src/Plugin/Field/FieldWidget/WebformEntityReferenceSelectWidget.php
index bac738263..89f24a6b2 100644
--- a/src/Plugin/Field/FieldWidget/WebformEntityReferenceSelectWidget.php
+++ b/src/Plugin/Field/FieldWidget/WebformEntityReferenceSelectWidget.php
@@ -155,6 +155,7 @@ class WebformEntityReferenceSelectWidget extends OptionsWidgetBase {
       $context = [
         'fieldDefinition' => $this->fieldDefinition,
         'entity' => $entity,
+        'fieldWidget' => $this,
       ];
       $module_handler->alter('options_list', $options, $context);
 
```
