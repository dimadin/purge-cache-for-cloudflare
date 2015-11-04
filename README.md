Purge Cache for CloudFlare
===========================

[Plugin homepage](http://blog.milandinic.com/wordpress/plugins/purge-cache-for-cloudflare/) | [Plugin author](http://blog.milandinic.com/) | **[Premium Version](https://shop.milandinic.com/downloads/purge-cache-for-cloudflare-plus)** | [Donate](http://blog.milandinic.com/donate/)

Purge Cache for CloudFlare is a simple plugin that uses CloudFlare® API to purge cache of full HTML pages when a new post is made.

This free version only for basic usage. If you want to use it in full capacity, consider buying [premium version](https://shop.milandinic.com/downloads/purge-cache-for-cloudflare-plus).

It works by purging front page, post's page, and main RSS feed. This should work for most sites. However, there are plenty of filters, actions, and methods that provide full customizability and extensibility.

Note that this plugin also sets cache to 30 minutes for all frontend pages. This means that if you use default option in CloudFlare, it tells them to revalidate page cache after that time, so it means that cache for any page expires on CloudFlare servers after that time.

You can change this limits by using filters from you code. If you want user interface in your admin, use [premium version](https://shop.milandinic.com/downloads/purge-cache-for-cloudflare-plus).

You should create new CloudFlare page rules to set proper caching. It is your responsibility to set this properly.

First page rule should exclude certain paths from caching. Recommended value for this is `wp-`. This excludes admin pages and default `.php` pages. Example of URL pattern: `*example.com/*wp-*` This value can also set via filter or via admin in [premium version](https://shop.milandinic.com/downloads/purge-cache-for-cloudflare-plus).

Second page rule should sets caching. You need to set "Custom caching" to "Cache everything". Recommended value for "Edge cache expire TTL" is default, "Respect all existing headers" which means that CloudFlare revalidates after 30 minutes, while for "Browser cache expire TTL" is also 30 minutes. Example of URL pattern: `*example.com/*`

Purge Cache for CloudFlare is in no way affiliated with CloudFlare. It is only using CloudFlare API to purge page cache of certain URLs.
CloudFlare is registered trademark of CloudFlare, Inc.