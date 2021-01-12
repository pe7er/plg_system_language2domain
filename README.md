# Language2Domain - System Plugin for Joomla

This plugin is for multilingual Joomla websites to make every language available under its own domain name. 

It was originally developed as "Language Domains" by Jisse Reitsma, author of Programming Joomla! Plugins.
Jisse no longer maintains his "Language Domains" plugin because of lack of time due to his other work: 
Jisse is expert in Magento 2, Shopware 6, React and Vue and can be hired via his company https://yireo.com.

Language2Domain is a modified version by Peter Martin (https://db8.nl) to work with Joomla 3.9, PHP 7.4 and Name Spacing, 
and it has been adapted to the Joomla 3.9 Code Style. 
It has been renamed to "Language2Domain" to avoid confusion with the original plugin.

## Background
Peter created a website about the best practises for websites: https://the-best-website.com. 
The website describes what you can do to improve it for visitors and its administrators. 
Furthermore, it discusses all kinds of tests to test its best practises.

He wanted to make his multilingual Joomla 3.9 website available with each of its languages under its own domain name.
Therefore, Peter modified the plugin. Now the website (one instance) is available under 5 different languages/domain names: 
- English: The best website: https://the-best-website.com/
- German: Die beste Website: https://die-beste-website.de/
- French: Le meilleur site web: https://le-meilleur-site-web.fr/
- Spanish: El mejor sitio web: https://el-mejor-sitio-web.es/
- Dutch: De beste website: https://de-beste-website.nl/

## Disclaimer
This plugin works as is. It works with the Joomla core but has NOT been tested with all available extensions
It's released under the GNU GPL license.

## Introduction
This Joomla 3 plugin allows you to assign a domain per language. You configure specific "bindings" in the parameters of this plugin. Each line contains a binding between a language-tag and a domain. The plugin checks whether the current domain matches the specified language, and redirects to the neccessary domain if needed. Also, it will try to fix all of the generated Joomla! SEF URLs.

## Installation
* Make sure Joomla! SEF is fully enabled
* Install this plugin in Joomla!
* Disable "System - Language Filter" core-plugin (!)
* Enable "System - Language Domains" plugin (this plugin)
* Configure this plugin for the proper bindings.

## Usage
For example, you could configure the following bindings:

    en-GB=example.co.uk
    fr-FR=example.fr
    de-DE=example.de
    nl-NL=example.nl

You can also specify additional domains like this:

    nl-NL=example.nl|www.example.nl

This will enforce the language Dutch to the domain `example.nl` while any request to `www.example.nl` will also redirect to `example.nl`. So, the first domain in the column-separated list is the primary domain for that language.

Note that this plugin treats domains and subdomains as the same thing.

## Credits & Contributions
This plugin has received great improvements from various contributors for which we are very thankful: Jisse Reitsma, Ruud van Zuidam, Sérgio Alves.

## Troubleshooting
With a regular environment, the Language Domains plugin should be loaded before the System - SEF plugin.

Falang is not supported. If you do want to play around with Falang combined with this plugin, make sure to try to re-order the plugins (especially the Falang plugins, the System - SEF plugin and the Language Domains plugin) to see if this works for you. We have had Falang environments where things worked and Falang environments where things did not work.

If you are using Falang and this is working for you, you might bump into the issue that language switching works on all pages except for the homepage. The problem is not within the Language Domains plugin, but in the Falang Language Switcher module. Create a template override of the file modules/mod_falang/tmpl/default.php, locate the foreach loop of the languages and add the following line right after the foreach start:

    if (empty($language->link) || in_array($language->link, array('/', 'index.php'))) $language->link = '/?lang='.$language->sef;

Make sure to remove all cookies when you are testing with this plugin. Alternatively restart your browser.

Make sure to disable the System - Language Filter plugin, when this plugin is enabled.

When using caching, beware of using the Sytem - Cache plugin. Under Joomla! 2.5, that plugin does not cache content on a domain-level, therefore all cache is the same for all domains. Simply put, plugins like ours are not compatible with the System - Cache plugin under Joomla! 2.5. Under Joomla! 3, using the cache-plugin is definitely possible. Test things first with the cache-plugin disabled. If you are enabling the plugin, make sure its ordering is higher than this System - Language Domains plugin - so that the cache-plugin is loaded after this plugin. Also make sure to wipe out the page-cache by using the Cache Manager.

When using the System - SEF plugin to translate links in your content, make sure it is loaded after the Language Domains plugin.

## FAQ: Is this plugin compatible with JoomFish or Falang?
We have not made any effort to make this plugin compatible with JoomFish and Falang, and actually we shouldn't. The Joomla! core contains full support for multilinguality, and it is the task of any extension developer to make their own extension compatible with it. We have done exactly that. However, currently Falang replaces major parts of the Joomla multilingual system.

We do not provide support for the combination of our plugin and Falang. You can still check the Troubleshooting section on the plugin page to see if you can make things work.

## FAQ: Does this plugin aid to SEO?
According to some SEO readings on the web, having a website that is matching the offered language with the chosen domain-suffix (TLD or top-level domain name) has a beneficial effect on your SEO. This means that if you have German website with a domainname ending with .de (German TLD), this website would be preferred over the same German website with a domainname ending with .fr (French TLD).

This is what others are saying. But we are not able to confirm this or unconfirm this. We are simply developing cool plugins. Tuning of SEO is where you come in. If you want know whether this actually has an effect on SEO, either experiment with it (as SEO experts do already) or reconfirm what we have read on the web.

## FAQ: Is my site indexed on all languages on all domains?
We don't know the structure of your site, so we can't answer this question either. If you want to know what is being indexed, scan your site for links. If a language-link is there, and it can be followed, without switching domains, it might be a bug in our plugin and we are happy to fix that.

## FAQ: Should all the domains point to the same Joomla! site?
Yes. You need one Joomla! site with multiple languages and multiple domains. Before installing this plugin, make sure that all domains actually point to the same Joomla! site. Also make sure that all languages are working and are switching properly. If everything is configured properly, then you are ready to install and configure this plugin.

## FAQ: Can I also use this for my subdomains?
Yes. For this plugin, a domain-name and a subdomain are the same thing.
