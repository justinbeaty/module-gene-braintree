# module-gene-braintree
OpenMage compatible fork of Gene_Braintree module

This module was originally available through the Magento Marketplace under the name "Braintree Payments With Hosted Fields".

This module has been updated to use the 6.x.x version of the [Braintree PHP library](https://github.com/braintree/braintree_php). Originally, the Gene module used the 3.x.x	version of the Braintree library which will be deprecated in March 2022 and unsupported in March 2023. Since the OpenMage project aims to continue supporting Magento 1.9 for many years, merchants may need an updated version of this module.

It is important to note that this fork removed the inlined copy of the Braintree library, and instead uses Composer to manage the dependency of the Braintree library. Therefore, you must install this module via Composer. For example:

```
{
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": [
    { "type": "vcs", "no-api": true, "url": "git@github.com:justinbeaty/module-gene-braintree.git" },
  ],
  "require": {
    "aydin-hassan/magento-core-composer-installer": "*",
    "openmage/magento-lts": "*",
    "gene/braintree": "dev-master",
  }
}
```

This module has been tested with at least one production OpenMage 20.x website, but you should make sure to test it on a local or staging website pushing it live.
