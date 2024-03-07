<?php
/**
 * @version    $Id$
 * @package    JSN_PageBuilder
 * @author     JoomlaShine Team <support@joomlashine.com>
 * @copyright  Copyright (C) 2012 JoomlaShine.com. All Rights Reserved.
 * @license    GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Websites: http://www.joomlashine.com
 * Technical Support:  Feedback - http://www.joomlashine.com/contact-us/get-support.html
 */

defined('_JEXEC') or die('Restricted access');

if (is_file(JPATH_ROOT . '/plugins/search/content/content.php'))
{
	require_once JPATH_ROOT . '/plugins/search/content/content.php';

	class PlgSearchPageBuilder3 extends PlgSearchContent
	{
		public static $start_html = '<!-- Start PageFly HTML -->';
    	public static $end_html = '<!-- End PageFly HTML -->';

		/**
		 * Search content (articles).
		 *
		 * The SQL must return the following fields that are used in a common display
		 * routine: href, title, section, created, text, browsernav.
		 *
		 * @param string $text     Target search string.
		 * @param string $phrase   Matching option (possible values: exact|any|all).
		 *                         Default is "any".
		 * @param string $ordering Ordering option (possible values: newest|oldest|popular|alpha|category).
		 *                         Default is "newest".
		 * @param mixed $areas     An array if the search it to be restricted to areas or null to search all areas.
		 *
		 * @return  array  Search results.
		 */
		public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
		{
			$results = parent::onContentSearch($text, $phrase, $ordering, $areas);

			foreach ($results as $_result)
			{
				if (strpos($_result->text, self::$start_html) !== false
					&& strpos($_result->text, self::$end_html) !== false)
				{
					if (isset($_result->metadesc) && !empty($_result->metadesc))
					{
						$_result->text = $_result->metadesc;
					}
					else
					{
						list($tmp, $_result->text) = explode(self::$start_html, $_result->text);
						list($_result->text, $tmp) = explode(self::$end_html, $_result->text);
					}
				}
			}

			return $results;
		}
	}
}
else
{
    class PlgSearchPageBuilder3 extends JPlugin
    {
        public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
        {
            return array();
        }
    }
}
