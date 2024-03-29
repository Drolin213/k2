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

class PlgSearchPagebuilder3InstallerScript
{

    /**
     * Enable plugin after installation completed.
     *
     * @param   string $route Route type: install, update or uninstall.
     * @param   object $installer The installer object.
     *
     * @return bool
     * @throws \Exception
     */
    public function postflight($route, $installer)
    {
        $db = JFactory::getDbo();
        try 
        {
            $query = $db->getQuery(true);
            $query->update('#__extensions');
            $query->set(array('enabled = 1', 'protected = 1', 'ordering= -9999'));
            $query->where("element = 'pagebuilder3'");
            $query->where("type = 'plugin'", 'AND');
            $query->where("folder = 'search'", 'AND');
            $db->setQuery($query);
            $db->execute();
        } 
        catch (Exception $e) 
        {
            throw $e;
        }
    }
}