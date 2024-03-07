<?php
/**
 * @version     $Id
 * @package     JSNPagebuilder
 * @subpackage  Plugin
 * @author      JoomlaShine Team <support@joomlashine.com>
 * @copyright   Copyright (C) @JOOMLASHINECOPYRIGHTYEAR@ JoomlaShine.com. All Rights Reserved.
 * @license     GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Websites: http://www.joomlashine.com
 * Technical Support:  Feedback - http://www.joomlashine.com/contact-us/get-support.html
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Content plugin for PageBuilder 3.
 *
 * @package  JSN_PageBuilder3
 * @since    1.0.0
 */
class plgContentPagebuilder3 extends JPlugin
{
	/**
	 * Handle onContentPrepare event.
	 *
	 * @param   string  $context
	 * @param   object  $article
	 * @param   object  $params
	 * @param   int     $page
	 *
	 * @return  bool
	 */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
	    $app = JFactory::getApplication();

	    if ($app->isSite())
	    {
		    // Don't run this plugin when the content is being indexed.
		    if ($context === 'com_finder.indexer')
		    {
			    return true;
		    }

		    // Verify parameters.
		    if (is_null($params) || is_string($params))
		    {
			    return true;
		    }

		    // Define filters to strip out some special characters.
		    $filters = array('/\t/', '/\r/', '/\n/');

		    // Define supported context.
		    $supported = array(
			    'com_content.article',
			    'com_k2.item',
			    'mod_k2_content'
		    );

		    if (in_array($context, $supported))
		    {
			    if ($context == 'com_content.article')
			    {
				    if (!(bool) $params->get('show_intro'))
				    {
					    if (!empty($article->introtext) && !empty($article->fulltext))
					    {
						    if (false !== strpos($article->introtext, PlgSystemPageBuilder3::$start_html) && false !== strpos($article->fulltext, PlgSystemPageBuilder3::$end_html))
						    {
							    $introText = preg_replace($filters, '', $article->introtext);

							    if (preg_match_all('#<style[^>]+type="text/css"[^>]*>(.*)</style>#', $introText, $matches, PREG_SET_ORDER)
							    	&& count($matches))
							    {
								    $style = @$matches[0][0];
								    $article->text = PlgSystemPageBuilder3::$start_html . $style . $article->text;
							    }
						    }
					    }
				    }
			    }
			    elseif ($context == 'com_k2.item')
			    {
				    if (!empty($article->introtext) && !empty($article->fulltext))
				    {
					    if (false !== strpos($article->introtext, PlgSystemPageBuilder3::$start_html) && false !== strpos($article->fulltext, PlgSystemPageBuilder3::$end_html))
					    {
						    if (false === strpos($article->text, PlgSystemPageBuilder3::$start_html))
						    {
							    $introText = preg_replace($filters, '', $article->introtext);

							    if (preg_match_all('#<style[^>]+type="text/css"[^>]*>(.*)</style>#', $introText, $matches, PREG_SET_ORDER)
							        && count($matches))
							    {
								    $style = @$matches[0][0];
								    $article->text = str_replace('{K2Splitter}', '', $article->text);
								    $article->text = '{K2Splitter}' . PlgSystemPageBuilder3::$start_html . $article->text . $style;
							    }
						    }
					    }
				    }
			    }
				elseif ($context == 'mod_k2_content')
				{
					if (!empty($article->introtext) && !empty($article->fulltext))
				    {
					    if (false !== strpos($article->introtext, PlgSystemPageBuilder3::$start_html) && false !== strpos($article->fulltext, PlgSystemPageBuilder3::$end_html))
					    {
						   if (false === strpos($article->text, PlgSystemPageBuilder3::$start_html))
						   {
 								$introText = preg_replace($filters, '', $article->introtext);
 								
							    if (preg_match_all('#<style[^>]+type="text/css"[^>]*>(.*)</style>#', $introText, $matches, PREG_SET_ORDER)
							        && count($matches))
							    {
							    	$style = @$matches[0][0];
							    	preg_match_all('#<style[^>]+type="text/css"[^>]*>(.*)</style>#s', $article->introtext, $submatches, PREG_SET_ORDER);
							    	$substyle = @$submatches[0][1];
							    	$tmpIntro = str_replace('<style type="text/css">' . $substyle . '</style>', '', $article->introtext);
								    $article->text =  strip_tags($tmpIntro);
							    }
						   }
					    }
				    }
				}			    
		    }
	    }
    }

	/**
	 * Handle onContentPrepareData event.
	 *
	 * @param   string  $context
	 * @param   array   $data
	 *
	 * @return  void
	 */
    public function onContentPrepareData($context, $data)
    {
		$user = JFactory::getUser();

		if (!(int) $user->get('id'))
		{
			return;
		}

		// Predefine checked data.
        $checkedData = '';

		// Check if the current screen is for editing an article.
        if ($context === 'com_content.article')
        {
			$checkedData = $data->articletext;
		}
		elseif ($context === 'com_modules.module' && (string) $data->module === 'mod_custom')
		{
			$checkedData = $data->content;
		}
		else
		{
			return;
		}

		if (trim($checkedData) == '')
		{
			return;
		}

		// Check if the editing article is created using JSN PageBuilder 3.
		if (strpos($checkedData, PlgSystemPageBuilder3::$start_html) !== false
			&& strpos($checkedData, PlgSystemPageBuilder3::$end_html) !== false)
		{
			$found = true;
		}
		elseif (strpos($checkedData, PlgSystemPageBuilder3::$start_data) !== false
			&& strpos($checkedData, PlgSystemPageBuilder3::$end_data) !== false)
		{
			$found = true;
		}
		elseif (strpos($checkedData, PlgSystemPageBuilder3::$start_hash) !== false
			&& strpos($checkedData, PlgSystemPageBuilder3::$end_hash) !== false)
		{
			$found = true;
		}

		// Load JSN PageBuilder 3 editor if the editing article was created with JSN PageBuilder 3.
		if (!empty($found) && JFactory::getApplication()->input->getCmd('editor') != 'default')
		{
			// Override default editor in global config.
			JFactory::$config->set('editor', 'pagebuilder3');

			// Override default editor in user config.
			JFactory::getUser()->setParam('editor', 'pagebuilder3');
		}

		return;
    }
}
