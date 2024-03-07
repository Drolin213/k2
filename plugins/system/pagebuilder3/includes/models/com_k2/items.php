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
defined('_JEXEC') or die('Restricted access');

// Extend K2ModelItems to support retrieving items from multiple categories.
if (is_file(JPATH_ADMINISTRATOR . '/components/com_k2/models/model.php'))
{
	JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');

	include_once JPATH_ADMINISTRATOR . '/components/com_k2/models/model.php';
	include_once JPATH_ADMINISTRATOR . '/components/com_k2/models/items.php';

	if (!defined('K2_JVERSION'))
	{
		// Determine Joomla version
		if (version_compare(JVERSION, '3.0', 'ge'))
		{
			define('K2_JVERSION', '30');
		}
		elseif (version_compare(JVERSION, '2.5', 'ge'))
		{
			define('K2_JVERSION', '25');
		}
		else
		{
			define('K2_JVERSION', '15');
		}
	}

	class PB3_K2ModelItems extends K2ModelItems
	{
		public function getData()
		{
			$application = JFactory::getApplication();
			$user = JFactory::getUser();
			$aid = $user->get('aid');
			$params = JComponentHelper::getParams('com_k2');
			$option = JRequest::getCmd('option');
			$view = JRequest::getCmd('view');
			$db = JFactory::getDbo();
			$jnow = JFactory::getDate();
			$now = K2_JVERSION == '15' ? $jnow->toMySQL() : $jnow->toSql();
			$nullDate = $db->getNullDate();
			$limit = $application->input->getInt('limit', $application->getCfg('list_limit'));
			$limitstart = $application->input->getInt('limitstart', 0);
			$filter_order = $application->input->getCmd('filter_order', 'i.id');
			$filter_order_Dir = $application->input->getWord('filter_order_Dir', 'DESC');
			$filter_featured = $application->input->getInt('filter_featured', -1);
			$filter_category = $application->input->getString('filter_category', '');
			$filter_author = $application->input->getString('filter_author', '');
			$filter_state = $application->input->getInt('filter_state', -1);
			$search = $application->input->getString('search', '');
			$search = JString::strtolower($search);
			$search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
			$tag = $application->input->getString('tag', '');
			$language = $application->input->getString('language', '');

			$query = "SELECT i.*, g.name AS groupname, c.name AS category, v.name AS author, w.name as moderator, u.name AS editor FROM #__k2_items as i";

			$query .= " LEFT JOIN #__k2_categories AS c ON c.id = i.catid"." LEFT JOIN #__groups AS g ON g.id = i.access"." LEFT JOIN #__users AS u ON u.id = i.checked_out"." LEFT JOIN #__users AS v ON v.id = i.created_by"." LEFT JOIN #__users AS w ON w.id = i.modified_by";

			if ($tag) {
				$query .= " LEFT JOIN #__k2_tags_xref AS tags_xref ON tags_xref.itemID = i.id";
			}

			$query .= " WHERE i.published = 1";
			$query .= " AND ( i.publish_up = " . $db->Quote($nullDate) . " OR i.publish_up <= " . $db->Quote($now) . " )";
			$query .= " AND ( i.publish_down = " . $db->Quote($nullDate) . " OR i.publish_down >= " . $db->Quote($now) . " )";

			if (K2_JVERSION != '15') {
				$query .= " AND i.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ") AND i.trash = 0 AND c.published = 1 AND c.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ") AND c.trash = 0";

				if ($languageFilter = $application->getLanguageFilter()) {
					$languageTag = JFactory::getLanguage()->getTag();
					$query .= " AND c.language IN (" . $db->quote($languageTag) . ", " . $db->quote('*') . ") AND i.language IN (" . $db->quote($languageTag) . ", " . $db->quote('*') . ")";
				}
			} else {
				$query .= " AND i.access <= {$aid} AND i.trash = 0 AND c.published = 1 AND c.access <= {$aid} AND c.trash = 0";
			}

			if ($search) {
				// Detect exact search phrase using double quotes in search string
				if (substr($search, 0, 1)=='"' && substr($search, -1)=='"') {
					$exact = true;
				} else {
					$exact = false;
				}

				// Now completely strip double quotes
				$search = trim(str_replace('"', '', $search));

				// Escape remaining string
				$escaped = K2_JVERSION == '15' ? $db->getEscaped($search, true) : $db->escape($search, true);

				// Full phrase or set of words
				if (strpos($escaped, ' ')!==false && !$exact) {
					$escaped=explode(' ', $escaped);
					$quoted = array();
					foreach ($escaped as $key=>$escapedWord) {
						$quoted[] = $db->Quote('%'.$escapedWord.'%', false);
					}
					if ($params->get('adminSearch') == 'full') {
						foreach ($quoted as $quotedWord) {
							$query .= " AND ( ".
								"LOWER(i.title) LIKE ".$quotedWord." ".
								"OR LOWER(i.introtext) LIKE ".$quotedWord." ".
								"OR LOWER(i.`fulltext`) LIKE ".$quotedWord." ".
								"OR LOWER(i.extra_fields_search) LIKE ".$quotedWord." ".
								"OR LOWER(i.image_caption) LIKE ".$quotedWord." ".
								"OR LOWER(i.image_credits) LIKE ".$quotedWord." ".
								"OR LOWER(i.video_caption) LIKE ".$quotedWord." ".
								"OR LOWER(i.video_credits) LIKE ".$quotedWord." ".
								"OR LOWER(i.metadesc) LIKE ".$quotedWord." ".
								"OR LOWER(i.metakey) LIKE ".$quotedWord." ".
								" )";
						}
					} else {
						foreach ($quoted as $quotedWord) {
							$query .= " AND LOWER(i.title) LIKE ".$quotedWord;
						}
					}
				}
				// Single word or exact phrase to search for (wrapped in double quotes in the search block)
				else {
					$quoted = $db->Quote('%'.$escaped.'%', false);

					if ($params->get('adminSearch') == 'full') {
						$query .= " AND ( ".
							"LOWER(i.title) LIKE ".$quoted." ".
							"OR LOWER(i.introtext) LIKE ".$quoted." ".
							"OR LOWER(i.`fulltext`) LIKE ".$quoted." ".
							"OR LOWER(i.extra_fields_search) LIKE ".$quoted." ".
							"OR LOWER(i.image_caption) LIKE ".$quoted." ".
							"OR LOWER(i.image_credits) LIKE ".$quoted." ".
							"OR LOWER(i.video_caption) LIKE ".$quoted." ".
							"OR LOWER(i.video_credits) LIKE ".$quoted." ".
							"OR LOWER(i.metadesc) LIKE ".$quoted." ".
							"OR LOWER(i.metakey) LIKE ".$quoted." ".
							" )";
					} else {
						$query .= " AND LOWER(i.title) LIKE ".$quoted;
					}
				}
			}

			if ($filter_state > -1) {
				$query .= " AND i.published={$filter_state}";
			}

			if ($filter_featured > -1) {
				$query .= " AND i.featured={$filter_featured}";
			}

			// Add support for retrieving items from multiple categories.
			$filter_by_categories = array();

			foreach (array_map('intval', explode(',', (string) $filter_category)) as $cat)
			{
				if ($cat > 0)
				{
					if ($params->get('showChildCatItems'))
					{
						K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
						$itemListModel = K2Model::getInstance('Itemlist', 'K2Model');
						$categories    = $itemListModel->getCategoryTree($cat);
						$sql           = @implode(',', $categories);
						$filter_by_categories[] = "i.catid IN ({$sql})";
					}
					else
					{
						$filter_by_categories[] = "i.catid={$cat}";
					}
				}
			}

			if (count($filter_by_categories))
			{
				$query .= ' AND (' . implode(' OR ', $filter_by_categories) . ')';
			}

			if ($filter_author > 0) {
				$query .= " AND i.created_by={$filter_author}";
			}

			if ($tag) {
				// Add support for retrieving items from multiple tags.
				$filter_by_tags = array();

				foreach (array_map('intval', explode(',', (string) $tag)) as $t)
				{
					if ($t > 0)
					{
						$filter_by_tags[] = "tags_xref.tagID = {$t}";
					}
				}

				if (count($filter_by_tags))
				{
					$query .= ' AND (' . implode(' OR ', $filter_by_tags) . ')';
				}
			}

			if ($language) {
				$query .= " AND (i.language = ".$db->Quote($language)." OR i.language = '*')";
			}

			// Group items by ID to avoid duplication.
			$query .= ' GROUP BY i.id';

			if ($filter_order == 'i.ordering') {
				$query .= " ORDER BY i.catid, i.ordering {$filter_order_Dir}";
			} else {
				$query .= " ORDER BY {$filter_order} {$filter_order_Dir} ";
			}

			if (K2_JVERSION != '15') {
				$query = JString::str_ireplace('#__groups', '#__viewlevels', $query);
				$query = JString::str_ireplace('g.name', 'g.title', $query);
			}

			// Plugin Events
			JPluginHelper::importPlugin('k2');
			$dispatcher = JDispatcher::getInstance();

			// Trigger K2 plugins
			$dispatcher->trigger('onK2BeforeSetQuery', array(&$query));

			$db->setQuery($query, $limitstart, $limit);
			$rows = $db->loadObjectList();
			return $rows;
		}
	}
}
