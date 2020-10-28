# Blizz Dynamic Entitylist

INTRODUCTION
------------
This module provides a way to define dynamic lists of entities using a 
simple interface. The list definition is stored in a custom field, which
can be attached to any fieldable content entity.

The field widget allows editors to enrich their list definitions with
filters based upon taxonomy terms and provides a live preview within the
edit form on the list result. This result is not static, so every time 
content matching the list criteria gets entered in the system the output
of the list adapts subsequently.

Also, editors are able to choose the view mode in which the entities 
get rendered.

REQUIREMENTS
------------
This module has no dependencies.

INSTALLATION
------------
 * Installation as usual: Place the directory of this module within /modules
(or maybe /modules/contrib). When using composer use `composer require
drupal/blizz_dynamic_entitylist`.

 * Enable the module by navigating to Administer > Extend and checking the
"Enabled" checkbox on "Blizz Dynamic Entitylist" (or simply use drush:
`drush en blizz_dynamic_entitylist`).

CONFIGURATION
-------------
 * Configure user permissions in Administration » People » Permissions
 * Configure fields using the standard Drupal coniguration interfaces

KNOWN ISSUES
------------
 * No known issues.

MAINTAINERS
-----------
Current maintainers:
 * Christian Lamine (CHiLi.HH) - https://www.drupal.org/u/chilihh

This project has been sponsored by:
 * comm-press GmbH
   As northern Germany's best-known Drupal service provider we have 
   several years' experience creating websites for large customers, 
   including some of Germany's largest publishers. We use Drupal for 
   all of our projects and are becoming increasingly well-versed in 
   the Symfony PHP Framework as it is an important part of Drupal 8.
