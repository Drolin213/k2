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

// Extend EasyBlogModelBlogs to support retrieving items from multiple categories.
if (is_file(JPATH_ADMINISTRATOR . '/components/com_easyblog/includes/easyblog.php'))
{
	include_once JPATH_ADMINISTRATOR . '/components/com_easyblog/includes/easyblog.php';
	include_once JPATH_ADMINISTRATOR . '/components/com_easyblog/models/model.php';
	include_once JPATH_ADMINISTRATOR . '/components/com_easyblog/models/blogs.php';

	class PB3_EasyBlogModelBlogs extends EasyBlogModelBlogs
	{
		public function getData($userId = null)
		{
			// Get the WHERE and ORDER BY clauses for the query
			$where = $this->_buildDataQueryWhere();

			// Get the db
			$db = EB::db();

			// Get custom sorting
			$customSorting = $this->app->getUserStateFromRequest('com_easyblog.blogs.filter_sort_by', 'filter_sort_by', '', 'word');

			$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.*';

			if ($customSorting == 'most_rated') {
				$query .= ', count(r.`uid`) as total_rated';
			}

			$query .= ' FROM ' . $db->nameQuote('#__easyblog_post') . ' AS a ';

			// Get the current state
			$state = $this->app->getUserStateFromRequest('com_easyblog.blogs.filter_state', 'filter_state', '', 'word' );

			if ($state == 'F') {
				$query 	.= ' INNER JOIN #__easyblog_featured AS `featured`';
				$query	.= ' ON a.`id` = featured.`content_id` AND featured.`type` = "post"';
			}

			// Always join with the category table
			$query .= ' LEFT JOIN ' . $db->quoteName('#__easyblog_post_category') . ' AS cat';
			$query .= ' ON a.' . $db->quoteName('id') . ' = cat.' . $db->quoteName('post_id');

			// Add support for retrieving items from multiple tags.
			$filter_tag = $this->app->input->getString('filter_tag', '');
			$filter_by_tags = array();

			if ($filter_tag)
			{
				foreach (array_map('intval', explode(',', (string) $filter_tag)) as $tag)
				{
					$filter_by_tags[] = 'b.`tag_id` = ' . $db->Quote($tag);
				}
			}

			if (count($filter_by_tags))
			{
				$query .= ' INNER JOIN #__easyblog_post_tag AS b ';
				$query .= 'ON a.`id`=b.`post_id` AND (' . implode(' OR ', $filter_by_tags) . ')';
			}

			$query	.= ' LEFT JOIN #__easyblog_featured AS f ';
			$query	.= ' ON a.`id` = f.`content_id` AND f.`type`="post"';

			if ($customSorting == 'most_rated' || $customSorting == 'highest_rated') {
				$query .= ' LEFT JOIN #__easyblog_ratings AS r';
				$query .= ' ON a.`id` = r.`uid`';
			}

			$customQuery = '';

			if ($customSorting) {
				$direction = 'DESC';

				switch ($customSorting) {
					case 'latest':
						$ordering = 'a.`created`';
						break;
					case 'oldest':
						$ordering = 'a.`created`';
						$direction = 'ASC';
						break;
					case 'popular':
						$ordering = 'a.`hits`';
						break;
					case 'highest_rated':
						$ordering = 'r.`value`';
						break;
					case 'most_rated':
						$ordering = 'total_rated';
						$customQuery .= ' GROUP BY a.`id` ';
						break;
					default:
						$ordering = 'a.`id`';
						break;
				}

			} else {
				$ordering = $this->app->getUserStateFromRequest('com_easyblog.blogs.filter_order', 'filter_order', 'a.id', 'cmd');
				$direction = $this->app->getUserStateFromRequest('com_easyblog.blogs.filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
			}

			$query .= $where;
			$query .= $customQuery;
			$query .= ' ORDER BY '. $ordering .' ' . $direction .', ordering';

			$limitstart = $this->getState('limitstart');
			$limit = $this->getState('limit');

			$mainQuery = $query;

			if ($limit) {
				$mainQuery = $query . ' LIMIT ' . $limitstart . ',' . $limit;
			}

			$db->setQuery($mainQuery);
			$data = $db->loadObjectList();

			// Get Total
			$queryLimit = 'select FOUND_ROWS()';
			$db->setQuery($queryLimit);

			$this->total = (int) $db->loadResult();

			// Reset the limitstart (perhaps caused by other filters)
			if ($this->total <= $limitstart) {
				$limitstart = 0;
				$this->setState('limitstart', 0);
			}

			// Rerun the query with new limitstart
			if ($limit) {
				$mainQuery = $query . ' LIMIT ' . $limitstart . ',' . $limit;
			}

			$db->setQuery($mainQuery);
			$data = $db->loadObjectList();

			return $data;
		}

		public function _buildDataQueryWhere()
		{
			$db = EB::db();

			$filter_state = $this->app->input->getWord('filter_state');
			$filter_category = $this->app->input->getString('filter_category', '');
			$filter_blogger = $this->app->input->getInt('filter_blogger' , 0);
			$filter_language = $this->app->input->getString('filter_language' , '');
			$filter_posttype = $this->app->input->getWord('filter_posttype', '');

			$endDate = $this->app->input->getString('filter_end_date', '');
			$startDate = $this->app->input->getString('filter_start_date', '');

			// Filter by source
			$source = $this->input->get('filter_source', '-1', 'default');

			$where = array();

			switch($filter_state) {
				case 'U':
					// Unpublished posts
					$where[] = 'a.`published` = ' . $db->Quote(EASYBLOG_POST_UNPUBLISHED);
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_NORMAL);
					break;

				case 'S':
					// Scheduled posts
					$where[] = 'a.`published` = ' . $db->Quote(EASYBLOG_POST_SCHEDULED);
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_NORMAL);
					break;

				case 'T':
					// trashed posts
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_TRASHED);
					break;

				case 'A':
					// archived posts
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_ARCHIVED);
					break;

				case 'P':
					// Published posts only
					$where[] = 'a.`published` = ' . $db->Quote(EASYBLOG_POST_PUBLISHED);
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_NORMAL);
					break;

				case 'FP':
					// Frontpage Post
					$where[] = 'a.`frontpage` = ' . $db->Quote(EASYBLOG_POST_PUBLISHED);

				default:
					$where[] = 'a.' . $db->qn('published') . ' IN (' . $db->Quote(EASYBLOG_POST_PUBLISHED) . ',' . $db->Quote(EASYBLOG_POST_UNPUBLISHED) . ',' . $db->Quote(EASYBLOG_POST_SCHEDULED) . ')';
					$where[] = 'a.' . $db->qn('state') . '=' . $db->Quote(EASYBLOG_POST_NORMAL);
					break;
			}

			if ($source != '-1') {
				$where[]	= 'a.' . $db->nameQuote( 'source' ) . '=' . $db->Quote( $source );
			}

			// Add support for retrieving items from multiple categories.
			$filter_by_categories = array();

			if ($filter_category)
			{
				foreach (array_map('intval', explode(',', (string) $filter_category)) as $cat)
				{
					$filter_by_categories[] = 'cat.`category_id` = ' . $db->Quote($cat);
				}
			}

			if (count($filter_by_categories))
			{
				$where[] = ' (' . implode(' OR ', $filter_by_categories) . ')';
			}

			if ($filter_posttype) {
				if ($filter_posttype == 'text') {
					$where[] = 'a.' . $db->nameQuote('posttype') . '=' . $db->Quote('');
				} else {
					$where[] = 'a.' . $db->nameQuote('posttype') . '=' . $db->Quote($filter_posttype);
				}
			}

			if ($filter_blogger) {
				$where[] = ' a.`created_by` = ' . $db->Quote($filter_blogger);
			}

			if ($filter_language && $filter_language != '*') {
				$where[] = ' a.`language`= ' . $db->Quote($filter_language);
			}

			// Process search
			$search = $this->app->input->getString('search', '');

			if ($search) {
				// If there is a : in the search query
				$column = 'a.title';
				$value = $search;

				$customSearch = $this->getSearchableItems($search);

				if ($customSearch) {
					$column = 'a.' . strtolower($customSearch->column);
					$value = $customSearch->query;
				}

				$where[] = $db->qn($column) . ' LIKE ' . $db->Quote('%' . $value . '%');
			}

			if ($filter_state == 'date' && ($endDate || $startDate)) {
				if ($startDate) {
					$startDate = EB::date($startDate)->toSql();

					$where[] = ' a.`created` > ' . $db->Quote($startDate);
				}

				if ($endDate) {
					$endDate = EB::date($endDate . ' +23 hour +59 minutes')->toSql();
				} else {
					// By default filter up until today date
					$endDate = EB::date()->toSql();
				}

				$where[] = ' a.`created` < ' . $db->Quote($endDate);
			}

			$where = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

			return $where;
		}
	}
}
