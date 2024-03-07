<?php
/**
 * @version    $Id$
 * @package    JSN_PageBuilder3
 * @author     JoomlaShine Team <support@joomlashine.com>
 * @copyright  Copyright (C) 2012 JoomlaShine.com. All Rights Reserved.
 * @license    GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Websites: http://www.joomlashine.com
 * Technical Support:  Feedback - http://www.joomlashine.com/contact-us/get-support.html
 */

// No direct access to this file
defined( '_JEXEC' ) OR die( 'Restricted access' );

jimport('joomla.filesystem.folder');

/**
 * Subinstall script for finalizing JSN PageBuilder 3 plugin.
 *
 * @package  JSN_PageBuilder3
 */
class PlgEditorsXtdPageBuilder3InstallerScript
{
	/**
	 * Enable JSN PageBuilder 3 plugin.
	 *
	 * @param   string  $route  Route type: install, update or uninstall.
	 * @param   object  $_this  The installer object.
	 *
	 * @return  boolean
	 */
	public function postflight( $route, $_this )
	{
		// Get a database connector object
		$db = JFactory::getDbo();

		try
		{
			// Enable plugin by default
			$q = $db->getQuery( true );

			$q
				->update( '#__extensions' )
				->set( array( 'enabled = 1' ) )
				->where( "element = 'pagebuilder3'" )
				->where( "type = 'plugin'", 'AND' )
				->where( "folder = 'editors-xtd'", 'AND' );

			$db->setQuery( $q )->execute();
		}
		catch ( Exception $e )
		{
			throw $e;
		}
	}
}
