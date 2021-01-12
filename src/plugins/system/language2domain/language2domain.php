<?php
/**
 * Joomla! System plugin - Language 2 Domain
 * Originally developed by Jisse Reitsma, https://yireo.com
 * Modified by Peter Martin, https://db8.nl
 *
 * @author     Yireo <info@yireo.com>
 * @author     Peter Martin <joomla@db8.nl>
 * @copyright  Copyright 2016 Yireo.com. All rights reserved
 * @license    GNU Public License
 * @link       https://db8.nl
 */

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

require_once JPATH_SITE . '/plugins/system/languagefilter/languagefilter.php';

/**
 * Class PlgSystemLanguage2Domain
 *
 * @package     Joomla!
 * @subpackage  System
 * @since       1.0.0
 */
class PlgSystemLanguage2Domain extends PlgSystemLanguageFilter
{
	/**
	 * @var PlgSystemLanguage2DomainHelper
	 * @since 1.0.0
	 */
	protected $helper;

	/**
	 * @var     CMSApplication
	 * @since   1.0.0
	 */
	protected $app;

	/**
	 * @var boolean
	 * @since 1.0.0
	 */
	protected $bindings = false;

	/**
	 * @var string
	 * @since 1.0.0
	 */
	protected $originalDefaultLanguage;

	/**
	 * @var string
	 * @since 1.0.0
	 */
	protected $currentLanguageTag;

	/**
	 * @var array
	 * @since 1.0.0
	 */
	protected $debugMessages;

	/**
	 * @var array
	 * @since 1.0.0
	 */
	protected $timers;

	/**
	 * Constructor
	 *
	 * @param   mixed  $subject  Instance of JEventDispatcher
	 * @param   mixed  $config   Configuration array
	 *
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public function __construct(&$subject, $config)
	{
		$this->includeHelper();
		$this->helper->overrideClasses();

		$rt = parent::__construct($subject, $config);

		$componentParams               = ComponentHelper::getParams('com_languages');
		$this->originalDefaultLanguage = $componentParams->get('site');

		$this->app = Factory::getApplication();

		// If this is the Site-application
		if ($this->app->isClient('site') == true)
		{
			// Detect the current language
			$currentLanguageTag = $this->detectLanguage();
			$this->setLanguage($currentLanguageTag);

			// Get the bindings
			$bindings = $this->getBindings();

			if (!empty($bindings))
			{
				// Check whether the currently defined language is in the list of domains
				if (!array_key_exists($currentLanguageTag, $bindings))
				{
					$this->setLanguage($currentLanguageTag);

					return $rt;
				}

				// Check if the current default language is correct
				foreach ($bindings as $bindingLanguageTag => $bindingDomains)
				{
					$bindingDomain = $bindingDomains['primary'];

					if (stristr(Uri::current(), $bindingDomain) == true)
					{
						// Change the current default language
						$newLanguageTag = $bindingLanguageTag;
						break;
					}
				}

				// Make sure the current language-tag is registered as current
				if (!empty($newLanguageTag) && $newLanguageTag != $currentLanguageTag)
				{
					$this->setLanguage($newLanguageTag);
				}
			}
		}

		return $rt;
	}

	/**
	 * Event onAfterInitialise
	 *
	 * @return  mixed
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public function onAfterInitialise()
	{
		// Store the previous language tag in the plugin
		$this->oldLanguageTag = Factory::getLanguage()->getTag();

		// Remove the cookie if it exists
		$this->cleanLanguageCookie();

		// Fix bug after update to joomla 3.7.3 (bug: prefix in home page of main language)
		Factory::getSession()->set('plg_system_languagefilter.language', substr($this->detectLanguage(), 0, 2));

		// Make sure not to redirect to a URL with language prefix
		$this->params->set('remove_default_prefix', 1);

		// Enable item-associations
		$this->app->item_associations = $this->params->get('item_associations', 1);
		$this->app->menu_associations = $this->params->get('item_associations', 1);

		// If this is the Administrator-application, or if debugging is set, do nothing
		if ($this->app->isClient('site') == false)
		{
			return;
		}

		// Disable browser-detection
		$this->params->set('detect_browser', 0);
		$this->app->setDetectBrowser(false);

		// Detect the language
		$languageTag = Factory::getLanguage()->getTag();

		// Detect the language again
		if (empty($languageTag))
		{
			$language    = Factory::getLanguage();
			$languageTag = $language->getTag();
		}

		// Get the bindings
		$bindings = $this->getBindings();

		// Preliminary checks
		if (empty($bindings) || (!empty($languageTag) && !array_key_exists($languageTag, $bindings)))
		{
			// Run the event of the parent-plugin
			if ($this->helper->isFalangDatabaseDriver() == false)
			{
				parent::onAfterInitialise();
			}

			// Re-enable item-associations
			$this->app->item_associations = $this->params->get('item_associations', 1);
			$this->app->menu_associations = $this->params->get('item_associations', 1);

			return;
		}

		// Check for an empty language
		if (empty($languageTag))
		{
			// Check if the current default language is correct
			foreach ($bindings as $bindingLanguageTag => $bindingDomains)
			{
				$bindingDomain = $bindingDomains['primary'];

				if (stristr(Uri::current(), $bindingDomain) == true)
				{
					// Change the current default language
					$newLanguageTag = $bindingLanguageTag;

					break;
				}
			}
		}

		// Override the default language if the domain was matched
		if (empty($languageTag) && !empty($newLanguageTag))
		{
			$languageTag = $newLanguageTag;
		}

		// Make sure the current language-tag is registered as current
		if (!empty($languageTag))
		{
			$this->setLanguage($languageTag);

			$component = ComponentHelper::getComponent('com_languages');
			$component->params->set('site', $languageTag);
		}

		// Run the event of the parent-plugin
		if ($this->helper->isFalangDatabaseDriver() == false)
		{
			parent::onAfterInitialise();
		}

		// Re-enable item-associations
		$this->app->item_associations = $this->params->get('item_associations', 1);
		$this->app->menu_associations = $this->params->get('item_associations', 1);

		$this->helper->resetDefaultLanguage();
	}

	/**
	 * Function to get the paths of all the language files loaded before the language switch
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function getPaths()
	{
		$loadOverride = function ($filename = null) {
			// Get the path of all the translation files already loaded
			return $this->paths;
		};

		// We use Closure here as we need to access private attributes of JLanguage
		$lang           = Factory::getLanguage();
		$loadOverrideCB = $loadOverride->bindTo($lang, 'JLanguage');
		$this->paths    = $loadOverrideCB();
	}

	/**
	 * Function to relaod all the language files in the new language after the language switch
	 *
	 * @param   string  $oldtag  Old Tag
	 * @param   string  $tag     Tag
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function reloadPaths($oldtag, $tag)
	{
		$loadOverride = function ($paths, $oldcode, $code) {
			foreach ($paths as $extension => $extensionPaths)
			{
				foreach ($extensionPaths as $fileName => $oldResult)
				{
					// Replace the language code in the translation file path
					$fileName = str_replace($oldcode, $code, $fileName);

					// Parse the language file
					$strings = $this->parse($fileName);
					$result  = false;

					// Add the translations to JLanguage
					if ($strings !== array())
					{
						$this->strings = array_replace($this->strings, $strings, $this->override);
						$result        = true;
					}

					// Record the result of loading the extension's file
					if (!isset($this->paths[$extension]))
					{
						$this->paths[$extension] = array();
					}

					$this->paths[$extension][$fileName] = $result;
				}
			}
		};

		// We use Closure here as we need to access private attributes of JLanguage
		$lang           = Factory::getLanguage();
		$loadOverrideCB = $loadOverride->bindTo($lang, 'JLanguage');
		$loadOverrideCB($this->paths, $oldtag, $tag);
	}

	/**
	 * Event onAfterRoute
	 *
	 * @return  void
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public function onAfterRoute()
	{
		$this->startTimer('onAfterRoute');

		// Run the event of the parent-plugin
		if (method_exists(get_parent_class(), 'onAfterRoute'))
		{
			parent::onAfterRoute();
		}

		// Remove the cookie if it exists
		$this->cleanLanguageCookie();

		// If this is the Administrator-application, or if debugging is set, do nothing
		if ($this->app->isClient('site') == false)
		{
			return;
		}

		// Detect the current language again, but now after routing
		$languageTag = $this->detectLanguage();

		// Don't continue for sh404SEF
		if ($this->isSh404Sef())
		{
			return;
		}

		// Get the paths of all the language files loaded before the language switch
		$this->getPaths();

		// If this language is not included in this plugins configuration, set it as current
		if (!$this->isLanguageBound($languageTag))
		{
			$this->setLanguage($languageTag, true);
		}
		// If this language is included in this plugins configuration, override the language again
		else
		{
			$this->setLanguage($this->currentLanguageTag, true);
		}

		$this->debug('Current language tag: ' . $languageTag);

		// Reload all the language files in the new language after the language switch
		$this->reloadPaths($this->oldLanguageTag, $languageTag);

		if (empty($languageTag))
		{
			$this->redirectLanguageToDomain($languageTag);
		}

		$this->redirectDomainToPrimaryDomain($languageTag);

		$this->resetPathForHome();

		$this->endTimer('onAfterRoute');
	}

	/**
	 * Event onAfterDispatch - left empty to catch event for parent plugin
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAfterDispatch()
	{
		$this->startTimer('onAfterDispatch');

		$languageTag = Factory::getLanguage()->getTag();
		$languageSef = $this->getLanguageSefByTag($languageTag);

		parent::onAfterDispatch();

		if (!empty($languageSef))
		{
			$uri  = Uri::getInstance();
			$path = $uri->getPath();
			$uri->setPath('/' . $languageSef . '/' . preg_replace('/^\//', '', $path));
		}

		$this->endTimer('onAfterDispatch');
	}

	/**
	 * Event onAfterRender
	 *
	 * @return  void
	 * @since 1.0.0
	 */
	public function onAfterRender()
	{
		// If this is the Administrator-application, or if debugging is set, do nothing
		if ($this->app->isClient('administrator') || JDEBUG)
		{
			return;
		}

		// Fetch the document buffer
		$buffer = $this->app->getBody();

		// Get the bindings
		$bindings = $this->getBindings();

		if (empty($bindings))
		{
			return;
		}

		// Loop through the languages and check for any URL
		$languages = LanguageHelper::getLanguages('sef');

		foreach ($languages as $languageSef => $language)
		{
			$languageCode = $language->lang_code;

			if (!array_key_exists($languageCode, $bindings))
			{
				continue;
			}

			if (empty($bindings[$languageCode]))
			{
				continue;
			}

			if (empty($languageSef))
			{
				continue;
			}

			$primaryDomain    = $bindings[$languageCode]['primary'];
			$primaryUrl       = $this->helper->getUrlFromDomain($primaryDomain);
			$secondaryDomains = $bindings[$languageCode]['domains'];

			$this->debug('Inspecting language: ' . $languageSef . ' / ' . $primaryUrl);

			// Replace shortened URLs
			$this->rewriteShortUrls($buffer, $languageSef, $primaryUrl, $secondaryDomains);

			// Replace shortened URLs that contain /index.php/
			$this->rewriteShortUrlsWithIndex($buffer, $languageSef, $primaryUrl, $secondaryDomains);

			// Replace full URLs
			$this->rewriteFullUrls($buffer, $languageSef, $primaryUrl, $primaryDomain, $secondaryDomains);

			// Replace full SEF URLs
			$this->rewriteFullSefUrls($buffer, $languageSef, $primaryUrl, $primaryDomain, $secondaryDomains);
		}

		if (!empty($this->debugMessages))
		{
			$debugMessages = implode('', $this->debugMessages);
			$buffer        = str_replace('</body>', '<script>' . $debugMessages . '</script></body>', $buffer);
		}

		$this->app->setBody($buffer);
	}

	/**
	 * Override of the build rule of the parent plugin
	 *
	 * @param   Router  $router  JRouter object.
	 * @param   Uri     $uri     Uri object.
	 *
	 * @return  void
	 * @since 1.0.0
	 */
	public function buildRule(&$router, &$uri)
	{
		if ((bool) $this->params->get('load_buildrule') || $this->helper->isFalangDatabaseDriver() == false)
		{
			// Make sure to append the language prefix to all URLs, so we can properly parse the HTML using onAfterRender()
			$this->params->set('remove_default_prefix', 0);

			parent::buildRule($router, $uri);

			$language    = Factory::getLanguage();
			$languageSef = $this->getLanguageSefByTag($language->getTag());
			$uri->setPath(str_replace('index.php/' . $languageSef . '/', 'index.php', $uri->getPath()));
		}
	}

	/**
	 * Include the helper class
	 *
	 * @return  void
	 * @since 1.0.0
	 */
	protected function includeHelper()
	{
		include_once 'helper.php';
		$this->helper = new PlgSystemLanguage2DomainHelper;
	}

	/**
	 * Replace all short URLs with a language X with a domain Y
	 *
	 * @param   string  $buffer            Buffer
	 * @param   string  $languageSef       Language SEF
	 * @param   string  $primaryUrl        Primary URL
	 * @param   array   $secondaryDomains  Secondary Domains
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function rewriteShortUrls(&$buffer, $languageSef, $primaryUrl, $secondaryDomains)
	{
		$this->startTimer('rewriteShortUrls');

		if (preg_match_all('/([\'\"]{1})\/(' . $languageSef . ')\/([^\'\"]*)([\'\"]{1})/', $buffer, $matches))
		{
			foreach ($matches[0] as $index => $match)
			{
				$match = preg_replace('/(\'|\")/', '', $match);

				$this->debug('Match shortened URL: ' . $match);

				if ($this->allowUrlChange($match) == false)
				{
					continue;
				}

				if ($this->doesSefMatchCurrentLanguage($languageSef))
				{
					$buffer = str_replace(
						$matches[0][$index], $matches[1][$index] . '/' . $matches[3][$index] . $matches[4][$index], $buffer
					);
				}
				else
				{
					$buffer = str_replace(
						$matches[0][$index], $matches[1][$index] . $primaryUrl . $matches[3][$index] . $matches[4][$index],
						$buffer
					);
				}
			}
		}
		else
		{
			$this->debug('No matches');
		}

		$this->endTimer('rewriteShortUrls');
	}

	/**
	 * Replace all short URLs containing /index.php/ with a language X with a domain Y
	 *
	 * @param   string  $buffer            Buffer
	 * @param   string  $languageSef       Language SEF
	 * @param   string  $primaryUrl        Primary URL
	 * @param   array   $secondaryDomains  Secondary Domains
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	protected function rewriteShortUrlsWithIndex(&$buffer, $languageSef, $primaryUrl, $secondaryDomains)
	{
		$this->startTimer('rewriteShortUrlsWithIndex');
		$config = Factory::getConfig();

		if ($config->get('sef_rewrite', 0) == 0)
		{
			if (preg_match_all('/([\'\"]{1})\/index.php\/(' . $languageSef . ')\/([^\'\"]*)([\'\"]{1})/', $buffer, $matches))
			{
				foreach ($matches[0] as $index => $match)
				{
					$match = preg_replace('/(\'|\")/', '', $match);

					$this->debug('Match shortened URL with /index.php/: ' . $match);

					if ($this->allowUrlChange($match) == true)
					{
						$buffer = str_replace(
							$matches[0][$index], $matches[1][$index] . $primaryUrl . $matches[3][$index] . $matches[4][$index], $buffer
						);
					}
				}
			}
			else
			{
				$this->debug('No matches');
			}
		}

		$this->endTimer('rewriteShortUrlsWithIndex');
	}

	/**
	 * Replace all full URLs with a language X with a domain Y
	 *
	 * @param   string  $buffer            Buffer
	 * @param   string  $languageSef       Language SEF
	 * @param   string  $primaryUrl        Primary URL
	 * @param   string  $primaryDomain     Primary Domain
	 * @param   array   $secondaryDomains  Secondary Domains
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	protected function rewriteFullUrls(&$buffer, $languageSef, $primaryUrl, $primaryDomain, $secondaryDomains)
	{
		$this->startTimer('rewriteFullUrls');

		$bindings   = $this->getBindings();
		$allDomains = $this->getAllDomains();

		if (empty($bindings))
		{
			return false;
		}

		// Scan for full URLs
		if (preg_match_all('/(http|https)\:\/\/([a-zA-Z0-9\-\/\.]{5,40})\/' . $languageSef . '\/([^\'\"]*)([\'\"]{1})/', $buffer, $matches))
		{
			foreach ($matches[0] as $index => $match)
			{
				$this->debug('Match full URL: ' . $match);

				if ($this->allowUrlChange($match) == true)
				{
					$match         = preg_replace('/(\'|\")/', '', $match);
					$workMatch     = str_replace('index.php/', '', $match);
					$matchedDomain = $this->helper->getDomainFromUrl($workMatch);

					// Skip domains that are not within this configuration
					if (!in_array($matchedDomain, $allDomains))
					{
						continue;
					}

					// Replace the domain name
					if (!in_array($matchedDomain, $secondaryDomains) && !in_array('www.' . $matchedDomain, $secondaryDomains))
					{
						$buffer = str_replace($matches[0][$index], $primaryUrl . $matches[3][$index] . $matches[4][$index], $buffer);
						continue;
					}

					// Replace the language suffix in secondary domains because it is not needed
					if (in_array($matchedDomain, $secondaryDomains) || in_array('www.' . $matchedDomain, $secondaryDomains))
					{
						$url = $primaryUrl;

						if ($this->params->get('enforce_domains', 0) == 0)
						{
							$url = str_replace($primaryDomain, $matchedDomain, $url);
						}

						$buffer = str_replace($matches[0][$index], $url . $matches[3][$index] . $matches[4][$index], $buffer);
						continue;
					}
				}
			}
		}
		else
		{
			$this->debug('No matches');
		}

		$this->endTimer('rewriteFullUrls');

		return true;
	}

	/**
	 * Replace all full SEF URLs with a language X with a domain Y
	 *
	 * @param   string  $buffer            Buffer
	 * @param   string  $languageSef       Language SEF
	 * @param   string  $primaryUrl        Primary URL
	 * @param   string  $primaryDomain     Primary Domain
	 * @param   array   $secondaryDomains  Secondary Domains
	 *
	 * @return  boolean
	 * @since   1.0.0
	 */
	protected function rewriteFullSefUrls(&$buffer, $languageSef, $primaryUrl, $primaryDomain, $secondaryDomains)
	{
		$bindings   = $this->getBindings();
		$allDomains = $this->getAllDomains();

		if (empty($bindings))
		{
			return false;
		}

		if (strstr($buffer, '?lang=' . $languageSef) == false && strstr($buffer, '&lang=' . $languageSef) == false)
		{
			return false;
		}

		$this->startTimer('rewriteFullSefUrls');

		// Scan for full URLs
		if (preg_match_all('/([\'\"]{1})([^\'\"]+)([\?\&])lang=' . $languageSef . '([\'\"]{1})/', $buffer, $matches))
		{
			foreach ($matches[2] as $index => $match)
			{
				$match = preg_replace('/\?$/', '', $match);
				$match = preg_replace('/^\//', '', $match);

				// Strip the URL from all domains that we know of
				$match = preg_replace('/^(http|https):\/\/(' . implode('|', $allDomains) . ')/', '', $match);

				// Skip URLs with an unknown domain
				if (preg_match('/^(http|https):\/\//', $match))
				{
					continue;
				}

				// Skip broken entries
				if (preg_match('/^([^a-zA-Z0-9\/]+)/', $match))
				{
					continue;
				}

				$this->debug('Match full URL: ' . $match . ' [' . $languageSef . ']');

				if ($this->allowUrlChange($match) == true)
				{
					$replacement = $matches[0][$index] . $primaryUrl . $match . $matches[4][$index];
					$buffer      = str_replace($matches[0][$index], $replacement, $buffer);
				}
			}
		}
		else
		{
			$this->debug('No matches');
		}

		$this->endTimer('rewriteFullSefUrls');

		return true;
	}

	/**
	 * Method to get all the domains configured in this plugin
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	protected function getAllDomains()
	{
		$bindings   = $this->getBindings();
		$allDomains = array();

		if (empty($bindings))
		{
			return $allDomains;
		}

		foreach ($bindings as $binding)
		{
			$allDomains[] = $binding['primary'];

			if (is_array($binding['domains']))
			{
				$allDomains = array_merge($allDomains, $binding['domains']);
			}
		}

		return $allDomains;
	}

	/**
	 * Method to get the bindings for languages
	 *
	 * @return  null
	 * @since   1.0.0
	 */
	protected function getBindings()
	{
		if (is_array($this->bindings))
		{
			return $this->bindings;
		}

		$bindings = trim($this->params->get('bindings'));

		if (empty($bindings))
		{
			$this->bindings = array();

			return $this->bindings;
		}

		$bindingsArray = explode("\n", $bindings);
		$bindings      = array();

		foreach ($bindingsArray as $index => $binding)
		{
			$binding = trim($binding);

			if (empty($binding))
			{
				continue;
			}

			$binding = explode('=', $binding);

			if (!isset($binding[0]) || !isset($binding[1]))
			{
				continue;
			}

			$languageCode = trim($binding[0]);
			$languageCode = str_replace('_', '-', $languageCode);

			if (preg_match('/([^a-zA-Z\-]+)/', $languageCode))
			{
				continue;
			}

			$domainString = trim($binding[1]);
			$domainParts  = explode('|', $domainString);
			$domain       = array_shift($domainParts);

			if (!is_array($domainParts))
			{
				$domainParts = array();
			}

			$bindings[$languageCode] = array(
				'primary' => $domain,
				'domains' => $domainParts
			);
		}

		$this->bindings = $bindings;

		return $this->bindings;
	}

	/**
	 * Helper-method to get the language bound to specific domain
	 *
	 * @param   string  $domain  Domain to determine language from
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	protected function getLanguageFromDomain($domain = null)
	{
		if (empty($domain))
		{
			$uri    = Uri::getInstance();
			$domain = $uri->toString(array('host'));
		}

		$bindings = $this->getBindings();

		foreach ($bindings as $languageTag => $binding)
		{
			if ($binding['primary'] == $domain || 'www.' . $binding['primary'] == $domain)
			{
				return $languageTag;
			}

			if (in_array($domain, $binding['domains']))
			{
				return $languageTag;
			}
		}

		return null;
	}

	/**
	 * Redirect to a certain domain based on a language tag
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return  boolean
	 * @since   1.0.0
	 */
	protected function redirectLanguageToDomain($languageTag)
	{
		// Check whether to allow redirects or to leave things as they are
		$allowRedirect = $this->allowRedirect();

		if ($allowRedirect == false)
		{
			return false;
		}

		// Get the language domain
		$domain = $this->getDomainByLanguageTag($languageTag);

		if (!empty($domain))
		{
			if (stristr(Uri::current(), $domain) == false)
			{
				// Add URL-elements to the domain
				$domain = $this->helper->getUrlFromDomain($domain);

				// Replace the current domain with the new domain
				$currentUrl = Uri::current();
				$newUrl     = str_replace(Uri::base(), $domain, $currentUrl);

				if ($this->params->get('debug', 0) == 1)
				{
					echo '<a href="' . $newUrl . '">' . $newUrl . '</a>';
					exit;
				}

				// Set the cookie
				$conf         = Factory::getConfig();
				$cookieDomain = $conf->get('config.cookie_domain', '');
				$cookiePath   = $conf->get('config.cookie_path', '/');
				setcookie(ApplicationHelper::getHash('language'), null, time() - 365 * 86400, $cookiePath, $cookieDomain);

				// Redirect
				$this->app->redirect($newUrl);
				$this->app->close();
			}
		}

		return true;
	}

	/**
	 * Redirect from a secondary domain to the primary domain
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return  boolean
	 * @since   1.0.0
	 */
	protected function redirectDomainToPrimaryDomain($languageTag)
	{
		// Check whether to allow redirects or to leave things as they are
		$allowRedirect = $this->allowRedirect();

		if ($allowRedirect == false)
		{
			return false;
		}

		if ($this->params->get('enforce_domains', 0) == 0)
		{
			return false;
		}

		$bindings      = $this->getBindings();
		$primaryDomain = $this->getDomainByLanguageTag($languageTag);
		$currentDomain = Uri::getInstance()
			->getHost();

		if (empty($bindings))
		{
			return false;
		}

		foreach ($bindings as $binding)
		{
			if (in_array($currentDomain, $binding['domains']))
			{
				$primaryDomain = $binding['primary'];
			}
		}

		if (stristr(Uri::current(), '/' . $primaryDomain) == false)
		{
			// Replace the current domain with the new domain
			$currentUrl = Uri::current();
			$newUrl     = str_replace($currentDomain, $primaryDomain, $currentUrl);

			if ($this->params->get('debug', 0) == 1)
			{
				echo '<a href="' . $newUrl . '">' . $newUrl . '</a>';
				exit;
			}

			// Redirect
			$this->app->redirect($newUrl);
			$this->app->close();
		}

		return true;
	}

	/**
	 * Return the domain by language tag
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	protected function getDomainByLanguageTag($languageTag)
	{
		$bindings = $this->getBindings();

		if (empty($bindings))
		{
			return false;
		}

		if (!array_key_exists($languageTag, $bindings))
		{
			return false;
		}

		return $bindings[$languageTag]['primary'];
	}

	/**
	 * Reset the URI path to include the language SEF part specifically for the home Menu-Item
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function resetPathForHome()
	{
		$menu        = $this->app->getMenu();
		$active      = $menu->getActive();
		$currentPath = Uri::getInstance()
			->toString(array('path', 'query'));

		if (!empty($active) && $active->home == 1 && in_array($currentPath, array('', '/')))
		{
			$uri = Uri::getInstance();
			$uri->setPath('/');
		}
	}

	/**
	 * Find the SEF part of a certain language tag
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return  mixed
	 * @since   1.0.0
	 */
	public function getLanguageSefByTag($languageTag)
	{
		$languages          = LanguageHelper::getLanguages('sef');
		$currentLanguageSef = null;

		foreach ($languages as $languageSef => $language)
		{
			if ($language->lang_code == $languageTag)
			{
				$currentLanguageSef = $languageSef;
				break;
			}
		}

		return $currentLanguageSef;
	}

	/**
	 * Return the domain by language tag
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	protected function getDomainsByLanguageTag($languageTag)
	{
		$bindings = $this->getBindings();

		if (empty($bindings))
		{
			return false;
		}

		if (!array_key_exists($languageTag, $bindings))
		{
			return false;
		}

		return $bindings[$languageTag]['domains'];
	}

	/**
	 * Wipe language cookie
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	protected function cleanLanguageCookie()
	{
		if (method_exists('ApplicationHelper', 'getHash'))
		{
			$languageHash = ApplicationHelper::getHash('language');
		}
		else
		{
			$languageHash = JApplication::getHash('language');
		}

		if (!isset($_COOKIE[$languageHash]))
		{
			return false;
		}

		$conf         = Factory::getConfig();
		$cookieDomain = $conf->get('config.cookie_domain', '');
		$cookiePath   = $conf->get('config.cookie_path', '/');

		setcookie($languageHash, '', time() - 3600, $cookiePath, $cookieDomain);
		$this->app->input->cookie->set($languageHash, '');

		return true;
	}

	/**
	 * Detect the current language
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function detectLanguage()
	{
		$currentLanguageTag = $this->app->input->get('language');

		if (empty($currentLanguageTag))
		{
			$currentLanguageTag = $this->app->input->get('lang');
		}

		if (empty($currentLanguageTag))
		{
			$currentLanguageTag = $this->getLanguageFromDomain();
		}

		if (empty($currentLanguageTag))
		{
			$currentLanguageTag = Factory::getLanguage()->getTag();
		}

		return $currentLanguageTag;
	}

	/**
	 * Change the current language
	 *
	 * @param   string  $languageTag  Tag of a language
	 * @param   bool    $fullInit     Fully initialize the language or not
	 *
	 * @return  null
	 * @since   1.0.0
	 * @throws  Exception
	 */
	protected function setLanguage($languageTag, $fullInit = false)
	{
		$this->currentLanguageTag = $languageTag;
		$this->current_lang       = $languageTag;
		$languageSef              = $this->getLanguageSefByTag($languageTag);

		$prop = new ReflectionProperty($this, 'default_lang');

		if ($prop->isStatic())
		{
			self::$default_lang = $languageSef;
		}
		else
		{
			$this->default_lang = $languageSef;
		}

		// Set the input variable
		$this->app->input->set('language', $languageTag);
		$this->app->input->set('lang', $languageSef);

		// Rerun the constructor ugly style
		Factory::getLanguage()->__construct($languageTag);

		// Reload languages
		$language = JLanguage::getInstance($languageTag, false);

		if ($fullInit == true)
		{
			$language->load('tpl_' . $this->app->getTemplate(), JPATH_SITE, $languageTag, true);
		}

		$language->load('joomla', JPATH_SITE, $languageTag, true);
		$language->load('lib_joomla', JPATH_SITE, $languageTag, true);

		// Reinject the language back into the application
		try
		{
			$this->app->set('language', $languageTag);
		}
		catch (Exception $e)
		{
			return;
		}

		if (method_exists($this->app, 'loadLanguage'))
		{
			$this->app->loadLanguage($language);
		}

		if (method_exists($this->app, 'setLanguageFilter'))
		{
			$this->app->setLanguageFilter(true);
		}

		// Falang override
		$registry = Factory::getConfig();
		$registry->set('config.defaultlang', $this->originalDefaultLanguage);

		// Falang override
		ComponentHelper::getParams('com_languages')
			->set('site', $this->originalDefaultLanguage);

		// Reset the Factory
		try
		{
			Factory::$language = $language;
		}
		catch (Exception $e)
		{
			return;
		}
	}

	/**
	 * Allow a redirect
	 *
	 * @return  boolean
	 * @since   1.0.0
	 */
	private function allowRedirect()
	{
		$input = $this->app->input;

		if ($input->getMethod() == 'POST' || count($input->post) > 0 || count($input->files) > 0)
		{
			return false;
		}

		if ($input->getCmd('tmpl') == 'component')
		{
			return false;
		}

		if (in_array($input->getCmd('format'), array('json', 'feed', 'api', 'opchtml')))
		{
			return false;
		}

		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			return false;
		}

		return true;
	}

	/**
	 * Allow a specific URL to be changed by this plugin
	 *
	 * @param   string  $url  URL
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	private function allowUrlChange($url)
	{
		// Exclude specific component-calls
		if (preg_match('/format=(raw|json|api)/', $url))
		{
			return false;
		}

		// Exclude specific JavaScript
		if (preg_match('/\.js$/', $url))
		{
			return false;
		}

		// Do not rewrite non-SEF URLs
		if (stristr($url, 'index.php?option='))
		{
			return false;
		}

		// Do not rewrite edit layouts
		if (stristr($url, 'layout=edit'))
		{
			return false;
		}

		// Exclude specific components
		$excludeComponents = $this->getArrayFromParam('exclude_components');

		if (!empty($excludeComponents))
		{
			foreach ($excludeComponents as $excludeComponent)
			{
				if (stristr($url, 'components/' . $excludeComponent))
				{
					return false;
				}

				if (stristr($url, 'option=' . $excludeComponent . '&'))
				{
					return false;
				}
			}
		}

		// Exclude specific URLs
		$excludeUrls   = $this->getArrayFromParam('exclude_urls');
		$excludeUrls[] = '/media/jui/js/';
		$excludeUrls[] = '/assets/js/';

		if (!empty($excludeUrls))
		{
			foreach ($excludeUrls as $excludeUrl)
			{
				if (stristr($url, $excludeUrl))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check whether a certain SEF string matches the current language
	 *
	 * @param   string  $sef  SEF
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	private function doesSefMatchCurrentLanguage($sef)
	{
		$languages = LanguageHelper::getLanguages('sef');

		if (!isset($languages[$sef]))
		{
			return false;
		}

		$language        = $languages[$sef];
		$currentLanguage = Factory::getLanguage();

		if ($currentLanguage->getTag() == $language->lang_code)
		{
			return true;
		}

		return false;
	}

	/**
	 * Get an array from a parameter
	 *
	 * @param   string  $param  Params
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function getArrayFromParam($param)
	{
		$data = $this->params->get($param);
		$data = trim($data);

		if (empty($data))
		{
			return array();
		}

		$data = explode(',', $data);

		$newData = array();

		foreach ($data as $value)
		{
			$value = trim($value);

			if (!empty($value))
			{
				$newData[] = $value;
			}
		}

		return $newData;
	}

	/**
	 * Debug a certain message
	 *
	 * @param   string  $message  Message
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	private function debug($message)
	{
		if ($this->allowRedirect() == false)
		{
			return false;
		}

		$debug = false;
		$input = $this->app->input;

		if ($input->getInt('debug') == 1)
		{
			$debug = true;
		}

		if ($this->params->get('debug') == 1)
		{
			$debug = true;
		}

		if ($debug)
		{
			$this->debugMessages[] = 'console.log("LANGUAGE DOMAINS: ' . addslashes($message) . '");';
		}

		return true;
	}

	/**
	 * Quick check to see if a specific language tag is included in the bindings
	 *
	 * @param   string  $languageTag  Language Tag
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	private function isLanguageBound($languageTag)
	{
		$bindings = $this->getBindings();

		if (isset($bindings[$languageTag]))
		{
			return true;
		}

		return false;
	}

	/**
	 * @param   string  $label  Label
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function startTimer($label)
	{
		$this->timers[$label] = microtime(true);
	}

	/**
	 * @param   string  $label  Label
	 *
	 * @return float|mixed|string
	 *
	 * @since 1.0.0
	 */
	private function getTimer($label)
	{
		return microtime(true) - $this->timers[$label];
	}

	/**
	 * @param   string  $label  Label
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function endTimer($label)
	{
		$timer = $this->getTimer($label);

		$this->debug($label . ' = ' . $timer . 's');
	}

	/**
	 * @return boolean
	 *
	 * @since 1.0.0
	 */
	private function isSh404Sef()
	{
		$plugin = PluginHelper::getPlugin('system', 'sh404sef');

		if (!empty($plugin))
		{
			return true;
		}

		return false;
	}
}
