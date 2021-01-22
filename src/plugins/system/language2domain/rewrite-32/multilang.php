<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Language
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Factory;

defined('JPATH_PLATFORM') or die;

/**
 * Utitlity class for multilang
 *
 * @package     Joomla.Libraries
 * @subpackage  Language
 * @since       1.0.0
 */
class JLanguageMultilang
{
	/**
	 * Method to determine if the language filter plugin is enabled.
	 * This works for both site and administrator.
	 *
	 * @return  boolean  True if site is supporting multiple languages; false otherwise.
	 *
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public static function isEnabled()
	{
		if (!defined('FALANG_J30') || FALANG_J30 == false)
		{
			return true;
		}
		elseif (defined('FALANG_J30'))
		{
			$menu    = Factory::getApplication()->getMenu();
			$active  = $menu->getActive();
			$default = $menu->getDefault();

			if (!empty($active) && !empty($default) && $active->id == $default->id)
			{
				return false;
			}
		}

		// Flag to avoid doing multiple database queries.
		static $tested = false;

		// Status of language filter plugin.
		static $enabled = false;

		// Get application object.
		$app = Factory::getApplication();

		// If being called from the front-end, we can avoid the database query.
		if ($app->isSite())
		{
			$enabled = $app->getLanguageFilter();

			return $enabled;
		}

		// If already tested, don't test again.
		if (!$tested)
		{
			// Determine status of language filter plug-in.
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('enabled'))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
				->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
				->where($db->quoteName('element') . ' = ' . $db->quote('languagefilter'));
			$db->setQuery($query);

			$enabled = $db->loadResult();
			$tested  = true;
		}

		return $enabled;
	}

	/**
	 * Method to return a list of published site languages.
	 *
	 * @return  array of language extension objects.
	 *
	 * @since   1.0.0
	 */
	public static function getSiteLangs()
	{
		// To avoid doing duplicate database queries.
		static $multiLangSiteLanguages = null;

		if (!isset($multiLangSiteLanguages))
		{
			// Check for published Site Languages.
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('element'))
				->from($db->quoteName('#__extensions'))
				->where('type = ' . $db->quote('language'))
				->where($db->quoteName('client_id') . ' = 0')
				->where($db->quoteName('enabled') . ' = 1');
			$db->setQuery($query);

			$multiLangSiteLanguages = $db->loadObjectList('element');
		}

		return $multiLangSiteLanguages;
	}

	/**
	 * Method to return a list of language home page menu items.
	 *
	 * @return  array of menu objects.
	 *
	 * @since   1.0.0
	 */
	public static function getSiteHomePages()
	{
		// To avoid doing duplicate database queries.
		static $multiLangSiteHomePages = null;

		if (!isset($multiLangSiteHomePages))
		{
			// Check for Home pages languages.
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select(
					$db->quoteName(
						[
							'language',
							'id'
						]
					)
				)
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('home') . ' = 1')
				->where($db->quoteName('published') . ' = 1')
				->where($db->quoteName('client_id') . ' = 0');
			$db->setQuery($query);

			$multiLangSiteHomePages = $db->loadObjectList('language');
		}

		return $multiLangSiteHomePages;
	}
}
