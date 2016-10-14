<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cache
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @copyright   Copyright (C) 2016 Best Project, Inc. All rights reserved.
 * @author      Artur Stępień
 * @email       artur.stepien@bestproject.pl
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Joomla! Page Cache Plugin modified to use automatic cache clearing.
 *
 * @since  1.5
 */
class PlgSystemBPCache extends JPlugin
{
	var $_cache = null;

	var $_cache_key = null;

	var $_group = 'bpcache';

	var $_tasks = array(
		'save', 'apply','copy','move','batch','deleteAll','delete',
		'publish', 'unpublish','trash','checkin','archive','unfeatured','featured'
	);

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		$app  = JFactory::getApplication();
		
		// Set the language in the class.
		$options = array(
			'defaultgroup' => $this->_group,
			'browsercache' => $this->params->get('browsercache', false),
			'caching'      => false,
			'cachebase'    => $app->get('cache_path', JPATH_SITE . '/cache')
		);

		$this->_cache     = JCache::getInstance('page', $options);
		$this->_cache_key = JUri::getInstance()->toString();
	}

	/**
	 * Converting the site URL to fit to the HTTP request.
	 *
	 * @return  void
	 *
	 * @since   1.5
	 */
	public function onAfterInitialise()
	{
		global $_PROFILER;

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		if ($app->isAdmin())
		{
			// Find task name
			$task = $app->input->get('task','');
			if( stripos($task, '.') ) {
				$task = explode('.',$task);
				$task = end($task);
			}
			
			// Clear cache when user is performing a usual tasks
			if (!$user->guest AND in_array($task, $this->_tasks))
			{
				$this->_cache->clean($this->_group);
				$this->_cache->clean('page');
			}

			return;
		}

		if (count($app->getMessageQueue()))
		{
			return;
		}

		if ($user->get('guest') && $app->input->getMethod() == 'GET')
		{
			$this->_cache->setCaching(true);
		}

		$data = $this->_cache->get($this->_cache_key, $this->_group);

		if ($data !== false)
		{
			// Set cached body.
			$app->setBody($data);

			echo $app->toString();

			if (JDEBUG)
			{
				$_PROFILER->mark('afterCache');
			}

			$app->close();
		}
	}

	/**
	 * After render.
	 *
	 * @return   void
	 *
	 * @since   1.5
	 */
	public function onAfterRespond()
	{
		$app = JFactory::getApplication();

		if ($app->isAdmin())
		{
			return;
		}

		if (count($app->getMessageQueue()))
		{
			return;
		}

		$user = JFactory::getUser();

		if ($user->get('guest') && !$this->isExcluded())
		{
			// We need to check again here, because auto-login plugins have not been fired before the first aid check.
			$this->_cache->store(null, $this->_cache_key, $this->_group);
		}
	}

	/**
	 * Check if the page is excluded from the cache or not.
	 *
	 * @return   boolean  True if the page is excluded else false
	 *
	 * @since    3.5
	 */
	protected function isExcluded()
	{
		// Check if menu items have been excluded
		if ($exclusions = $this->params->get('exclude_menu_items', array()))
		{
			// Get the current menu item
			$active = JFactory::getApplication()->getMenu()->getActive();

			if ($active && $active->id && in_array($active->id, (array) $exclusions))
			{
				return true;
			}
		}

		// Check if regular expressions are being used
		if ($exclusions = $this->params->get('exclude', ''))
		{
			// Normalize line endings
			$exclusions = str_replace(array("\r\n", "\r"), "\n", $exclusions);

			// Split them
			$exclusions = explode("\n", $exclusions);

			// Get current path to match against
			$path = JUri::getInstance()->toString(array('path', 'query', 'fragment'));

			// Loop through each pattern
			if ($exclusions)
			{
				foreach ($exclusions as $exclusion)
				{
					// Make sure the exclusion has some content
					if (strlen($exclusion))
					{
						if (preg_match('/' . $exclusion . '/is', $path, $match))
						{
							return true;
						}
					}
				}
			}
		}

		return false;
	}
}
