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

jimport('joomla.filesystem.folder');

/**
 * JSN PageBuilder3 Ajax handler.
 *
 * @package  JSN_PageBuilder3
 * @since    1.0.0
 */
class JSNPageBuilder3Ajax
{
    /**
     * Variable to hold the active Joomla application.
     *
     * @var  JApplication
     * @since 1.0.0
     */
    protected $app;

    /**
     * Variable to hold the active Joomla database connector.
     *
     * @var  JDatabaseDriver
     * @since 1.0.0
     */
    protected $dbo;
//    protected $limitFolder = '/images';

    protected $content;

    public function __construct()
    {
        // Get Joomla application.
        $this->app = JFactory::getApplication();

        // Get Joomla database connector.
        $this->dbo = JFactory::getDbo();

        if (!class_exists('JSNPageBuilder3ContentHelper')) {
            require_once JPATH_ROOT . '/administrator/components/com_pagebuilder3/helpers/content.php';
        }
        $this->content = new JSNPageBuilder3ContentHelper();

        // Only disable error reporting and display when accessing pb3ajax
        ini_set('error_reporting', E_STRICT);
        ini_set('display_errors', 'Off');
    }

    public function handleRequest()
    {
        // Get requested task.
        $task = $this->app->input->getCmd('task');
        // If requested task is valid, execute it.
        $data = array();

        if (method_exists($this, $task)) {
            $data = call_user_func(array($this, $task));
        }
        return $data;
    }

    protected function getDefaultPage()
    {
        return '';
    }

    protected function getPageBuilder3Banner()
    {
        $this->isEditable();
        $qry = $this->dbo->getQuery(true)
            ->select('params')
            ->from('#__extensions')
            ->where('type = "component" ')
            ->where('element = "com_pagebuilder3"');

        $this->dbo->setQuery($qry);
        if (!($params = json_decode($this->dbo->loadResult(), true))) {
            $params = array();
        }
        if (isset($params['token'])) {
            $token = $params['token'];
            $result = JSNPageBuilder3ContentHelper::fetchHttp('https://www.joomlashine.com/index.php?option=com_lightcart&view=adsbanners&task=adsbanners.getBanners&tmpl=component&type=json&category_alias=jsn-pagebuilder-3-inspector-panel&token=' . $token);
            die($result);
        }
        return null;
    }

    protected function savePageData()
    {
        header('Content-Type: application/json');
        $this->isEditable();

        $data = isset($_POST['data']) ? $_POST['data'] : '';
        $page_hash = $this->app->input->getString('page_hash', '');

        if ($page_hash !== '') {
            $existed = $this->content->select('data', '#__jsn_pagebuilder3_pages', "`page_hash` =  '$page_hash'", true);
            if (!empty($existed)) {
                $fields = array($this->dbo->quoteName('data') . ' = ' . $this->dbo->quote($data));
                $conditions = array(
                    $this->dbo->quoteName('page_hash') . ' = ' . $this->dbo->quote($page_hash),
                );
                if ($this->content->update($fields, $conditions, '#__jsn_pagebuilder3_pages') == 'true') {
                    return array('success' => true);
                } else {
                    return array('success' => false, 'message' => 'Failed to update data, please try again!');
                }
            }


            $columns = array('page_hash', 'data');
            $values = array($this->dbo->quote($page_hash), $this->dbo->quote($data));
            $result = $this->content->create($columns, $values, '#__jsn_pagebuilder3_pages');
            return array('success' => $result[0], 'id' => $result[1]);
        } else {
            return array('success' => false, 'message' => 'Missing parameters!');
        }
    }

    protected function getPageData()
    {
        header('Content-Type: application/json');
        $page_hash = $this->app->input->getString('page_hash', '');
        $data = $this->content->select('data', '#__jsn_pagebuilder3_pages', "`page_hash` =  '$page_hash'", true);

        return !empty($data->data) ? array('success' => true, 'data' => json_decode($data->data)) : array('success' => false);
    }

    protected function deletePageData()
    {
        header('Content-Type: application/json');
        $this->authorizeRequest();
        $page_hash = $this->app->input->getString('page_hash', '');
        if ($page_hash !== '') {
            return array('success' => $this->content->delete($page_hash, 'page_hash', '#__jsn_pagebuilder3_pages'));
        }

        return array('success' => false, 'message' => 'Missing parameter!');
    }

    /**
     * @return array
     * @since 1.1.1
     */
    public function getRevisions()
    {
        header('Content-Type: application/json');
        $page_hash = $this->app->input->getString('page_hash', null);
        if (empty($page_hash)) {
            return array('success' => false, 'data' => 'Missing parameter!');
        }
        $result = $this->getRevisionsByPageHash($page_hash);
        if (!empty($result)) {
            return array('success' => true, 'data' => $result);
        } else {
            return array('success' => false, 'data' => array());
        }
    }

    public function getElements()
    {
		$allElements = $this->getAllElement();
		if (count((array) $allElements ))
		{
			foreach($allElements as $i => $allElement)
			{
				$tmpHTML = json_decode($allElement->html);
				$tmpHTML->url = JUri::root() . ltrim($tmpHTML->dir, "/");
				$allElements[$i]->html = json_encode($tmpHTML);
			}
		}
        return array('success' => true, 'data' => $allElements);
    }

    public function saveElement()
    {
        if (empty($_POST['data'])) {
            return array('success' => false, 'data' => 'Missing parameter!');
        }
        $data = $this->dbo->quote($_POST['data']);
        $element_id = !empty($_POST['element_id']) ? $_POST['element_id'] : null;
        $html = $this->dbo->quote(!empty($_POST['html']) ? $_POST['html'] : '');
        $element_type = $this->dbo->quote(!empty($_POST['element_type']) ? $_POST['element_type'] : 'normal');
        $status = $this->dbo->quote(!empty($_POST['status']) ? $_POST['status'] : 'normal');
        if ($element_id === null) {
            $columns = array('data', 'html', 'status', 'type');
            $values = array($data, $html, $status, $element_type);
            $result = $this->content->create($columns, $values, '#__jsn_pagebuilder3_elements');

        } else {
            $fields = array(
                $this->dbo->quoteName('data') . ' = ' . $data,
                $this->dbo->quoteName('type') . ' = ' . $element_type,
                $this->dbo->quoteName('status') . ' = ' . $status,
                $this->dbo->quoteName('html') . ' = ' . $html
            );
            $conditions = array(
                $this->dbo->quoteName('id') . ' = ' . $element_id,
            );
            $result = $this->content->update($fields, $conditions, '#__jsn_pagebuilder3_elements');
        }
        return array('success' => $result, 'data' => $this->getAllElement());
    }

    public function deleteElement()
    {
        $element_id = !empty($_POST['element_id']) ? $_POST['element_id'] : null;
        if ($element_id === null) {
            return array('success' => false, 'data' => 'Missing parameter element ID!');
        }

        return array('success' => $this->content->delete($element_id, 'id', '#__jsn_pagebuilder3_elements'));
    }

    private function getAllElement()
    {
        return $this->content->select('id, type, data, html, status, updated', '#__jsn_pagebuilder3_elements', "", false, "updated DESC");
    }

    private function getRevisionsByPageHash($page_hash)
    {
        $result = $this->content->select('id, revision_data, status, updated', '#__jsn_pagebuilder3_revisions', "`page_hash` =  '$page_hash'", false, "updated DESC");
        if (!empty($result) && is_array($result)) {
            foreach ($result as $k => $r) {
                $result[$k]->revision_html_url = JUri::root() . "index.php?option=com_ajax&format=html&plugin=pagebuilder3&task=getRevisionHTML&id={$r->id}";
            }
        }
        return $result;
    }

    public function getRevisionHTML()
    {
        header('Content-Type: text/html; charset=utf-8');
        $id = $this->app->input->getString('id', null);
        if (empty($id)) {
            die('Not Found!');
        }
        $html = $this->content->select('revision_html', '#__jsn_pagebuilder3_revisions', "`id` =  '$id'", true);
        die($html->revision_html);
    }

    /**
     * @return array
     * @since 1.1.1
     */
    public function saveRevision()
    {
        $page_hash = $this->app->input->getString('page_hash', '');
        $revision_data = !empty($_POST['revision_data']) ? $_POST['revision_data'] : '';
        $revision_html = !empty($_POST['revision_html']) ? $_POST['revision_html'] : '';
        $status = !empty($_POST['status']) ? $_POST['status'] : '';

        if (empty($page_hash) || empty($revision_data)) {
            return array('success' => false, 'data' => 'Missing parameter!');
        }
        if ($status === 'draft') {
            $query = $this->dbo->getQuery(true);
            // delete all page draft.
            $conditions = array(
                $this->dbo->quoteName('page_hash') . ' = ' . $this->dbo->quote($page_hash),
                $this->dbo->quoteName('status') . ' = ' . $this->dbo->quote('draft')
            );
            $query->delete($this->dbo->quoteName('#__jsn_pagebuilder3_revisions'));
            $query->where($conditions);
            $this->dbo->setQuery($query);
            $this->dbo->execute();
        }

        $limit = 30;
        $data = $this->getRevisionsByPageHash($page_hash);
        if (class_exists('JsnExtFwHelper')) {
            $config = JsnExtFwHelper::getSettings('com_pagebuilder3');
            $revision_limit = $config['revision_limit'];
            $limit = !empty($revision_limit) ? $revision_limit : 30;
        }
        if ($limit < count($data)) {
            $this->dbo->setQuery("DELETE FROM `#__jsn_pagebuilder3_revisions` WHERE `page_hash` = " . $this->dbo->quote($page_hash) . " ORDER BY updated ASC LIMIT 1");
            $this->dbo->execute();
        }
        $columns = array('page_hash', 'revision_data', 'revision_html', 'status');
        $values = array($this->dbo->quote($page_hash), $this->dbo->quote($revision_data), $this->dbo->quote($revision_html), $this->dbo->quote($status));
        $result = $this->content->create($columns, $values, '#__jsn_pagebuilder3_revisions');
        $data = $this->getRevisionsByPageHash($page_hash);
		if ($status === 'draft')
		{
			 return array('success' => isset($result[0]) ? $result[0] : false, 'data' => $data);
		}
		else
		{
			return array('success' => isset($result[0]) ? $result[0] : false);
		}
    }

    /**
     * @return array
     * @since 1.1.1
     */
    public function deleteRevisionById()
    {
        $revision_id = $this->app->input->getString('revision_id', '');

        return array('success' => $this->content->delete($revision_id, 'id', '#__jsn_pagebuilder3_revisions'));
    }

    /**
     * @return array
     * @since 1.1.1
     */
    public function deleteRevisionByPageHash()
    {
        $page_hash = $this->app->input->getString('page_hash', '');
        return array('success' => $this->content->delete($page_hash, 'page_hash', '#__jsn_pagebuilder3_revisions'));
    }


    protected function savePreset()
    {

    }

    protected function getPreset()
    {

    }

    protected function deletePreset()
    {

    }

    /**
     *
     * @return mixed
     *
     * @since version
     */
    protected function listPageBuilderArticles()
    {
        header('Content-Type: application/json');
        $type = $this->app->input->getString('type', '');
        $limit = $this->app->input->getString('limit', '0,1000');
        $search = $this->app->input->getString('search', '');
        $search = $search !== '' ? " AND `a`.`title` LIKE '%{$search}%' " : '';
        jimport('joomla.database.table');
        switch ($type) {
            default:
            case 'articles':
                {
                    $content =& JTable::getInstance("content");
                    $query = "SELECT `a`.`id` ,  `a`.`title` ,  `a`.`publish_up`, `c`.`title` as `cTitle`   FROM  `#__content` as `a`  JOIN `#__categories` as `c` ON `a`.`catid` = `c`.`id`  WHERE  `introtext` LIKE  '<!-- Start%' OR  `fulltext` LIKE  '<!-- Start%' {$search} LIMIT {$limit} ";
                    $this->dbo->setQuery($query);
                    $result = $this->dbo->loadObjectList();
                    foreach ($result as $key => $article) {
                        $content->load($article->id);
                        $result[$key]->state = $content->get('state');
                    }
                    break;
                }
            case 'modules':
                {
                    $query = "SELECT `id` ,  `title` ,  `position`, `published` as `state` FROM  `#__modules`  WHERE  `content` LIKE  '<!-- Start%' {$search} LIMIT {$limit} ";
                    $this->dbo->setQuery($query);
                    $result = $this->dbo->loadObjectList();
                    break;
                }
        }

        return $result;

    }


    protected function uploadFile()
    {

        // Verify token. Only access in administrator
        $this->authorizeMediaRequest();

        $d = $this->app->input->getString('dir', '');
        $root = JPATH_ROOT . '/' . $d;
        if ($_POST['data_uri']) {
            $uri = $root . $_POST['filename'];
            if (is_file($uri)) {
                return array(
                    "message" => 'Filename already exists!',
                    'uri' => $_POST['filename'],
                    'list' => $this->listFiles($d)
                );
            }
            $data = $_POST['data_uri'];
            list($type, $data) = explode(';', $data);
            if (preg_match('/image|svg|pdf|video/', $type, $matches)) {
                list(, $data) = explode(',', $data);
                $data = base64_decode($data);
                file_put_contents($uri, $data);
                return array("message" => "done", 'uri' => $_POST['filename'], 'list' => $this->listFiles($d));
            } else {
                return array("message" => "Invalid File type!");

            }
        }

    }

    protected function uploadFileNew()
    {
        // Verify token. Only access in administrator
        $this->authorizeRequest();
        $d = $this->app->input->getString('dir', '');
        $target_dir = JPATH_ROOT . '/' . $d;
        $target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $message = array();
        // Check if image file is a actual image or fake image
        if (isset($_POST["submit"])) {
            $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
            if ($check !== false) {
                $message [] = "File is an image - " . $check["mime"] . ".";
                $uploadOk = 1;
            } else {
                $message [] = "File is not an image.";
                $uploadOk = 0;
            }
        }
        // Check if file already exists
        if (file_exists($target_file)) {
            $message [] = "Sorry, file already exists.";
            $uploadOk = 0;
        }
        // Check file size
        if ($_FILES["fileToUpload"]["size"] > 500000) {
            $message [] = "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif") {
            $message [] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            $message [] = "Sorry, your file was not uploaded.";
            // if everything is ok, try to upload file
        } else {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                $message [] = "The file " . basename($_FILES["fileToUpload"]["name"]) . " has been uploaded.";
            } else {
                $message [] = "Sorry, there was an error uploading your file.";
            }
        }
        return array('message' => $message);
    }


    protected function getListFiles()
    {

        // Verify token.
        $this->authorizeMediaRequest();

        $d = $this->app->input->getString('dir', '');
        $type = $this->app->input->getString('type', '');

        return $this->listFiles($d, $type);

    }

    protected function createFolder()
    {
        $this->authorizeMediaRequest();

        $d = $this->app->input->getString('dir', '');
        $name = $this->app->input->getString('name', '');
        try {
            if (file_exists(JPATH_ROOT . $d . $name)) {
                return array('success' => false, 'message' => 'The folder name is already exists!', 'path' => $d . $name);

            }
            $created = JFolder::create(JPATH_ROOT . $d . $name);
            if ($created) {
                return array('success' => true, 'message' => 'New folder successfully created!', 'path' => $d . $name);
            } else {
                return array('success' => false, 'message' => 'Failed to create new folder, please try again!', 'path' => $d . $name);
            }
        } catch (Exception $e) {
            return $e;
        }
    }

    protected function deleteFolder()
    {
        $this->authorizeMediaRequest(true);

        $d = $this->app->input->getString('dir', '');
        try {
            if (file_exists(JPATH_ROOT . $d)) {
                $deleted = JFolder::delete(JPATH_ROOT . $d);

                if ($deleted === true) {
                    return array('success' => true, 'message' => 'The folder ' . $d . ' has been deleted!', 'path' => $d);
                } else {
                    return array('success' => false, 'message' => 'Failed to delete folder, please try again!', 'path' => $d);
                }
            }
        } catch (Exception $e) {
            return $e;
        }
    }

    protected function renameFolder()
    {
        $this->authorizeMediaRequest();

        $d = $this->app->input->getString('dir', '');
        $newPath = $this->app->input->getString('newPath', '');
        try {
            if (file_exists(JPATH_ROOT . $newPath)) {
                return array('success' => false, 'message' => 'A folder with this name is already exists!', 'path' => $d, 'newPath' => $newPath);
            } else {
                $moved = JFolder::move(JPATH_ROOT . $d, JPATH_ROOT . $newPath);
                if ($moved === true) {
                    return array('success' => $moved, 'message' => 'Successfully moved/renamed folder!', 'path' => $d, 'newPath' => $newPath);
                } else {
                    return array('success' => $moved, 'message' => 'An error occurred, please try again', 'path' => $d, 'newPath' => $newPath);
                }
            }
        } catch (Exception $e) {
            return $e;
        }
    }

    protected function renameFile()
    {
        $this->authorizeMediaRequest();

        $d = $this->app->input->getString('dir', '');
        $newPath = $this->app->input->getString('newPath', '');
        try {
            if (file_exists(JPATH_ROOT . $newPath)) {
                return array('success' => false, 'message' => 'A file with this name is already exists!', 'path' => $d, 'newPath' => $newPath);
            } else {
                $moved = JFile::move(JPATH_ROOT . $d, JPATH_ROOT . $newPath);
                if ($moved === true) {
                    return array('success' => $moved, 'message' => 'Successfully moved/renamed file!', 'path' => $d, 'newPath' => $newPath);
                } else {
                    return array('success' => $moved, 'message' => 'An error occurred, please try again', 'path' => $d, 'newPath' => $newPath);
                }
            }
        } catch (Exception $e) {
            return $e;
        }
    }

    protected function deleteFile()
    {
        $this->authorizeMediaRequest(true);

        $d = $this->app->input->getString('dir', '');
        try {
            if (file_exists(JPATH_ROOT . $d)) {
                $deleted = JFile::delete(JPATH_ROOT . $d);

                if ($deleted === true) {
                    return array('success' => true, 'message' => 'The folder ' . $d . ' has been deleted!', 'path' => $d);
                } else {
                    return array('success' => false, 'message' => 'Failed to delete folder, please try again!', 'path' => $d);
                }
            }
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Switch content editor.
     *
     * @param   string $new_editor Editor to switch to.
     *
     * @return  string
     */
    public function switchEditor($new_editor = null)
    {
        // Get new editor to switch to.
        $new_editor = $this->app->input->get('editor', $new_editor);

        if (!is_null($new_editor)) {
            // Get current user.
            $user = JFactory::getUser();

            // Get current user parameters.
            $params = json_decode($user->get('params'));

            if (!$params) {
                $params = new stdClass();
            }

            // Store default editor of the current user to session storage if necessary.
            if ($new_editor == 'pagebuilder3') {
                // Get Joomla session handler.
                $session = JFactory::getApplication()->getSession();

                if (!$session->has('pb3_user_editor')) {
                    if ($params->editor == 'pagebuilder3') {
                        $params->editor = 'global';
                    }

                    $session->set('pb3_user_editor', empty($params->editor) ? 'global' : $params->editor);
                }
            } elseif ($new_editor == 'global') {
                $new_editor = '';
            }

            // Set new editor into the user parameters.
            $user->setParam('editor', $new_editor);
            $config_editor = new JConfig();
            $this->app->set('editor', $new_editor == '' ? $config_editor->editor : $new_editor);

            $params->editor = $new_editor;

            // Save new user parameters.
            $table = $user->getTable();

            $table->load($user->get('id'));

            $table->params = json_encode($params);

            $table->store();

            return $new_editor->name;
        }
    }

    /**
     * Get list of PageBuilder template.
     *
     * @return  void
     */
    protected function getPackages()
    {
        // Import PageBuilder plugins.
        JPluginHelper::importPlugin('pagebuilder3');

        // Get list of PageBuilder element.
        $results = JEventDispatcher::getInstance()->trigger('getElementList');
        $packages = array();

        foreach ($results as $result) {
            $package = array(
                'name' => isset($result['name']) ? $result['name'] : '',
                'title' => isset($result['title']) ? $result['title'] : 'Untitled',
                'scripts' => isset($result['scripts']) ? $result['scripts'] : array(),
                'elements' => isset($result['elements']) ? $result['elements'] : array()
            );

            foreach ($package['scripts'] as &$path) {
                $path = JURI::root() . "plugins/pagebuilder3/{$result['name']}/{$path}";
            }

            // Get element templates.
            $package['templates'] = array();

            $this->getTemplates("{$result['name']}/templates", '', $package['templates']);

            $packages[] = $package;
        }

        return $packages;
    }

    protected function getModules()
    {
        // Verify token.
        JSession::checkToken('get') or jexit('Invalid Token');

        // Prepare request data.
        $module_type = $this->app->input->getCmd('type');
        $filter_text = $this->app->input->getString('filter');

        // Get database object.
        $this->dboo = JFactory::getDbo();

        // Build query.
        $qry = $this->dboo->getQuery(true);

        $qry->select('*')->from('#__modules')->where('client_id = 0')->where('published = 1');

        if (!empty($module_type)) {
            $qry->where("module LIKE '%{$module_type}%'");
        }

        if (!empty($filter_text)) {
            $qry->where("title LIKE '%{$filter_text}%'");
        }

        // Query for results.
        $modules = array();

        if ($results = $this->dboo->setQuery($qry)->loadObjectList()) {
            // Prepare data to return.
            foreach ($results as $result) {
                $modules[] = array(
                    'id' => $result->id,
                    'type' => $result->module,
                    'title' => $result->title
                );
            }
        }

        return $modules;
    }

    protected function getModuleStyles()
    {
        $moduleStyles = array();

        // Define system template.
        $templates = array(
            ( object )array(
                'element' => 'system',
                'name' => 'system',
                'enabled' => 1
            )
        );

        // Get active template.
        $dbo = JFactory::getDbo();
        $qry = $dbo->getQuery(true);

        $qry
            ->select('e.element, e.name, e.enabled')
            ->from('#__extensions as e')
            ->join('inner', '#__template_styles as t ON t.template = e.element')
            ->where('e.type = ' . $dbo->quote('template'))
            ->where('t.client_id = 0')
            ->where('t.home = 1');

        $dbo->setQuery($qry);

        $templates[] = $dbo->loadObject();

        // Get all available module chromes.
        foreach ($templates as $template) {
            $modulesFilePath = JPATH_SITE . "/templates/{$template->element}/html/modules.php";

            // Is there modules.php for that template?
            if (is_file($modulesFilePath)) {
                $modulesFileData = file_get_contents($modulesFilePath);
                $pattern = '/function[\s\t]*modChrome\_([a-z0-9\-\_]*)[\s\t]*\(/i';

                if (preg_match_all($pattern, $modulesFileData, $styles)) {
                    $moduleStyles[$template->element] = $styles[1];
                }
            }
        }

        return $moduleStyles;
    }

    protected function getModuleHTML()
    {
        // Get module ID.
        $id = $this->app->input->getCmd('moduleID');

        // Emulate an article to let our load module plugin render the module.
        $article = ( object )array('text' => '{pb3loadmodule ' . $id . '}');
        $params = array();

        // Import content plugin.
        JPluginHelper::importPlugin('content');

        // Trigger onContentPrepare event.
        JEventDispatcher::getInstance()->trigger('onContentPrepare', array('', &$article, &$params, 0));

        echo new JResponseJson(array('html' => $article->text, 'id' => $id));

        exit;
    }

    protected function getArticles()
    {
    	// Get model.
	    JLoader::register(
	    	'PB3_ContentModelArticles',
		    dirname(__FILE__) . '/models/com_content/articles.php'
	    );

	    $model = new PB3_ContentModelArticles();

	    // Get any property from state to populate the model state first.
	    $model->getState('list.limit');

	    // Set filter parameters.
	    $o = $this->app->input->getString('filter_category');
	    $model->setState('filter.category_id', $o ? explode(',', $o) : '');

	    $o = $this->app->input->getString('filter_author');
	    $model->setState('filter.author_id', $o ? explode(',', $o) : '');

	    $o = $this->app->input->getString('filter_tag');
	    $model->setState('filter.tag', $o ? explode(',', $o) : '');

		if (strpos((string) @ $_SERVER["HTTP_REFERER"], 'administrator/index.php?option=com_ajax&plugin=pagebuilder3') !== false)
		{
			$model->setState('filter.language', false);
		} 		
	    // Set ordering parameters.
	    if ($o = $this->app->input->getString('sort'))
	    {
		    if ($o === 'category')
		    {
			    $o = 'category_title';
		    }
		    elseif ($o === 'moderator')
		    {
			    $o = 'modified_by_name';
		    }

		    $model->setState('list.ordering', $o);
	    }

	    if ($o = $this->app->input->getString('order'))
	    {
		    $model->setState('list.direction', $o);
	    }

	    // Set list limit and start index.
	    $model->set('list.start', 0);
	    $model->set('list.limit', $this->app->input->getInt('limit'));

	    // Get articles based on the specified attributes.
	    $results = $model->getItems();
        $articles = array();

        if (!empty($results))
        {
            // Load content helper route.
            if (!class_exists('ContentHelperRoute'))
            {
                require_once JPATH_ROOT . '/components/com_content/helpers/route.php';
            }

	        $text_limit = $this->app->input->getInt('text_limit', 0);
	        $word_limit = $this->app->input->getInt('word_limit', 20);
	        $date_format = $this->app->input->getString('date_format', 'd F Y');

	        if ($this->app->input->getInt('hasPrimarySecondary', 0))
	        {
	        	$primary_word_limit = $this->app->input->getInt('primary_word_limit', 20);
	        }

	        $idx = 0;

            foreach ($results as $result)
            {
                $result->direct_url = $this->replaceBaseUrl(JRoute::_(ContentHelperRoute::getArticleRoute($result->id, $result->catid), false));
                $result->category_direct_url = $this->replaceBaseUrl(JRoute::_(ContentHelperRoute::getCategoryRoute($result->catid), false));

                foreach ($result as $key => $value)
                {
                    switch ($key)
                    {
                        case 'images':
                        case 'urls':
                        case 'attribs':
                        case 'metadata':
                            $result->{$key} = json_decode($value);
                        break;

                        case 'text':
                        case 'introtext':
                        case 'fulltext':
		                    if ($word_limit || (isset($primary_word_limit) && $idx === 0))
		                    {
			                    $result->{$key} = $this->truncateText(
			                    	$value,
				                    (isset($primary_word_limit) && $idx === 0) ? $primary_word_limit : $word_limit,
				                    true
			                    );
		                    }
		                    elseif ($text_limit)
		                    {
			                    $result->{$key} = $this->truncateText($value, $text_limit);
		                    }
                        break;

                        case 'created':
                        case 'modified':
                        case 'publish_up':
		                    if ($date_format)
		                    {
			                    $result->{$key} = date($date_format, strtotime($value));
		                    }
                        break;
						case 'title':
							$tmpAltImageText = 'pb_alt_image_content';
							$result->{$tmpAltImageText} = htmlspecialchars($value);
						break;
                    }
                }

                if (empty($result->images))
                {
                    $result->images = (object) array();
                }

                $articles[] = $result;

	            $idx++;
            }
        }

        return $articles;
    }

    protected function getArticlesFilter()
    {
        // Query data.
        $results = new stdClass();

        $results->categories = $this->getCategories('joomla');
        $results->author = $this->getActiveAuthors('joomla');

	    // Load TagsModelTags class.
	    JLoader::register(
		    'TagsModelTags',
		    JPATH_ROOT . '/administrator/components/com_tags/models/tags.php'
	    );

	    // Instantiate TagsModelTags object.
	    if (class_exists('TagsModelTags'))
	    {
		    $model = new TagsModelTags();

		    // Set limit and start to model state.
		    $model->setState('list.limit', 9999);
		    $model->setState('list.start', 0);

		    // Get Joomla tags.
		    $results->tags = $model->getItems();
	    }

        return $results;
    }

    protected function clearTmp() {
        JFolder::delete(JPATH_ROOT . '/tmp');
    }

    protected function getK2Items()
    {
	    // Get model.
	    JLoader::register(
		    'PB3_K2ModelItems',
		    dirname(__FILE__) . '/models/com_k2/items.php'
	    );

	    if (!class_exists('PB3_K2ModelItems'))
        {
            return array();
        }

        // Load model to get K2 items.
        $model = new PB3_K2ModelItems();

        // Set request variables.
	    $this->app->input->set('limitstart', 0);

	    if ($o = $this->app->input->getString('sort'))
	    {
		    $this->app->input->set('filter_order', str_replace('a.', 'i.', $o));
	    }

	    if ($o = $this->app->input->getString('order'))
	    {
		    $this->app->input->set('filter_order_Dir', $o);
	    }

        if ($o = $this->app->input->getString('filter_tag'))
        {
	        $this->app->input->set('tag', $o);
        }

        // Get K2 items based on the specified attributes.
        $results = $model->getData();
        $articles = array();

        if (!empty($results))
        {
            // Load content helper route.
            if (!class_exists('K2HelperRoute'))
            {
                require_once JPATH_ROOT . '/components/com_k2/helpers/route.php';
            }

            $text_limit = $this->app->input->getInt('text_limit', 0);
            $word_limit = $this->app->input->getInt('word_limit', 20);
            $date_format = $this->app->input->getString('date_format', 'd F Y');

	        if ($this->app->input->getInt('hasPrimarySecondary', 0))
	        {
		        $primary_word_limit = $this->app->input->getInt('primary_word_limit', 20);
	        }

	        $idx = 0;

            foreach ($results as $result)
            {
                $result->direct_url = $this->replaceBaseUrl(JRoute::_(K2HelperRoute::getItemRoute($result->id, $result->catid), false));
                $result->category_direct_url = $this->replaceBaseUrl(JRoute::_(K2HelperRoute::getCategoryRoute($result->catid), false));

                //Get K2 intro image.
                if (JFile::exists(JPATH_SITE . '/media/k2/items/cache/' . md5("Image{$result->id}") . '_XL.jpg'))
                {
                    $result->images->image_intro = 'media/k2/items/cache/' . md5("Image{$result->id}") . '_XL.jpg';
                }

                foreach ($result as $key => $value)
                {
                    switch ($key) {
                        case 'params':
                        case 'metadata':
                            $result->{$key} = json_decode($value);
                        break;

                        case 'text':
                        case 'introtext':
                        case 'fulltext':
		                    if ($word_limit || (isset($primary_word_limit) && $idx === 0))
		                    {
			                    $result->{$key} = $this->truncateText(
				                    $value,
				                    (isset($primary_word_limit) && $idx === 0) ? $primary_word_limit : $word_limit,
				                    true
			                    );
		                    }
                            elseif ($text_limit)
                            {
                                $result->{$key} = $this->truncateText($value, $text_limit);
                            }
                        break;

                        case 'created':
                        case 'modified':
                        case 'publish_up':
                            if ($date_format)
                            {
                                $result->{$key} = date($date_format, strtotime($value));
                            }
                        break;
						case 'title':
							$tmpAltImageText = 'pb_alt_image_content';
							$result->{$tmpAltImageText} = htmlspecialchars($value);
						break;
                    }
                }

                $articles[] = $result;

	            $idx++;
            }
        }

        return $articles;
    }

    protected function getK2ItemsFilter()
    {
        // Query data.
        $results = new stdClass();

        $results->categories = $this->getCategories('k2');
        $results->author = $this->getActiveAuthors('k2');
        $results->tags = $this->getK2Tags();

        return $results;
    }

    protected function getEasyBlogPosts()
    {
	    // Get model.
	    JLoader::register(
		    'PB3_EasyBlogModelBlogs',
		    dirname(__FILE__) . '/models/com_easyblog/blogs.php'
	    );

	    if (!class_exists('PB3_EasyBlogModelBlogs'))
        {
            return array();
        }

        // Load model to get EasyBlog posts.
        $model = new PB3_EasyBlogModelBlogs();

	    // Set request variables.
	    $this->app->input->set('limitstart', 0);

	    if ($o = $this->app->input->getString('sort'))
	    {
		    $this->app->input->set('filter_sort_by', $o);
	    }

	    if ($o = $this->app->input->getInt('filter_author'))
	    {
		    $this->app->input->set('filter_blogger', $o);
	    }

	    // Get EasyBlog posts based on the specified attributes.
        $results = $model->getData();
        $articles = array();
        $category = $this->getCategories('easyblog');

        if (!empty($results))
        {
	        $text_limit = $this->app->input->getInt('text_limit', 0);
	        $word_limit = $this->app->input->getInt('word_limit', 20);
	        $date_format = $this->app->input->getString('date_format', 'd F Y');

	        if ($this->app->input->getInt('hasPrimarySecondary', 0))
	        {
		        $primary_word_limit = $this->app->input->getInt('primary_word_limit', 20);
	        }

	        $idx = 0;

	        foreach ($results as $result)
            {
                if (!empty($result->image))
                {
                    try
                    {
                        if (!class_exists('EBMM') && file_exists(JPATH_ADMINISTRATOR . '/components/com_easyblog/includes/mediamanager/mediamanager.php'))
                        {
                            require_once JPATH_ADMINISTRATOR . '/components/com_easyblog/includes/mediamanager/mediamanager.php';
                        }

                        $result->image = EBMM::getUrl($result->image, true);
                    }
                    catch (Exception $e)
                    {
                    	// Do nothing.
                    }
                }
                else
                {
                    $result->image = JUri::root() . 'plugins/system/pagebuilder3/assets/img/place-holder.svg';
                }

                $result->created_by_who = $this->getEasyBlogAuthor($result->created_by);
                $result->direct_url = 'index.php?option=com_easyblog&view=entry&id=' . $result->id;
                $result->category_direct_url = 'index.php?option=com_easyblog&view=categories&layout=listings&id=' . $result->category_id;

                foreach ($category as $k => $v)
                {
                    if ($v->id == $result->category_id)
                    {
                        $result->category_title = $v->title;
                    }
                }

                $result->category_title = !empty($result->category_title) ? $result->category_title : 'Uncategorized';

                foreach ($result as $key => $value)
                {
                    switch ($key)
                    {
                        case 'document':
                            $result->{$key} = json_decode($value);
                        break;

                        case 'content':
                        case 'intro':
                        case 'excerpt':
		                    if ($word_limit || (isset($primary_word_limit) && $idx === 0))
		                    {
			                    $result->{$key} = $this->truncateText(
				                    $value,
				                    (isset($primary_word_limit) && $idx === 0) ? $primary_word_limit : $word_limit,
				                    true
			                    );
		                    }
		                    elseif ($text_limit)
		                    {
			                    $result->{$key} = $this->truncateText($value, $text_limit);
		                    }
                        break;

                        case 'created':
                        case 'modified':
                        case 'publish_up':
		                    if ($date_format)
		                    {
			                    $result->{$key} = date($date_format, strtotime($value));
		                    }
                        break;
						case 'title':
							$tmpAltImageText = 'pb_alt_image_content';
							$result->{$tmpAltImageText} = htmlspecialchars($value);
						break;
                    }
                }

                $articles[] = $result;

	            $idx++;
            }
        }

        return $articles;
    }

    protected function getEasyBlogPostsFilter()
    {
        // Query data.
        $results = new stdClass();

        $results->categories = $this->getCategories('easyblog');
        $results->author = $this->getActiveAuthors('easyblog');

        // Load EasyBlogModelTags class.
	    JLoader::register(
		    'EB',
		    JPATH_ROOT . '/administrator/components/com_easyblog/includes/easyblog.php'
	    );
	    JLoader::register(
		    'EasyBlogAdminModel',
		    JPATH_ROOT . '/administrator/components/com_easyblog/models/model.php'
	    );
	    JLoader::register(
		    'EasyBlogModelTags',
		    JPATH_ROOT . '/administrator/components/com_easyblog/models/tags.php'
	    );

	    // Instantiate EasyBlogModelTags object.
	    if (class_exists('EasyBlogModelTags'))
	    {
		    $model = new EasyBlogModelTags();

		    // Set limit and start to model state.
		    $model->setState('limit', 9999);
		    $model->setState('limitstart', 0);

		    // Get EasyBlog tags.
		    $results->tags = $model->getData();
	    }

        return $results;
    }

    protected function getTemplates($root, $path, &$array)
    {
        $fullPath = JPATH_PLUGINS . "/pagebuilder3/{$root}{$path}";

        if (!file_exists($fullPath)) {
            return;
        }

        if ($handle = opendir($fullPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != '.' && $entry != '..' && is_dir("{$fullPath}/{$entry}")) {
                    $array[] = array(
                        'type' => 'dir',
                        'path' => $path,
                        'name' => $entry,
                    );

                    $this->getTemplates($root, "{$path}/{$entry}", $array);
                } else {
                    $file_parts = pathinfo("{$fullPath}/{$entry}");

                    switch ($file_parts['extension']) {
                        case 'json':
                            $data = file_get_contents("{$fullPath}/{$entry}");

                            $array[] = array(
                                'type' => 'file',
                                'path' => $path,
                                'name' => str_replace('.json', '', $entry),
                                'data' => json_decode($data)
                            );
                            break;
                    }
                }
            }

            closedir($handle);
        }
    }

    protected function getCategories($source = 'joomla')
    {
        try {
            $qry = $this->dbo->getQuery(true);

            switch ($source) {
                case 'k2' :
                    $qry->select('id, name AS title')->from('#__k2_categories');
                    break;

                case 'easyblog' :
                    $qry->select('id, title')->from('#__easyblog_category');
                    break;

                case 'joomla' :
                default :
                    $qry->select('id, title')->from('#__categories')->where('extension = "com_content"');
                    break;
            }

            $qry->where('published = 1')->order('title');

            $this->dbo->setQuery($qry);

            $results = $this->dbo->loadObjectList();
        } catch (Exception $e) {
            $results = array();
        }

        return $results;
    }

    protected function getActiveAuthors($source = 'joomla')
    {
        try {
            $qry = $this->dbo->getQuery(true);

            $qry->select('id, name, username')->from('#__users');

            switch ($source) {
                case 'k2' :
                    $table = '#__k2_items';
                    break;

                case 'easyblog' :
                    $table = '#__easyblog_post';
                    break;

                case 'joomla' :
                default :
                    $table = '#__content';
                    break;
            }

            $sub = $this->dbo->getQuery(true)->select('distinct(created_by)')->from($table);

            $qry->where('id IN (' . $sub . ')')->order('name');

            $this->dbo->setQuery($qry);

            $results = $this->dbo->loadAssocList('id');
        } catch (Exception $e) {
            $results = array();
        }

        return $results;
    }

    protected function getK2Tags()
    {
        try {
            $this->dbo->setQuery(
	            $this->dbo->getQuery(true)
		            ->select('id, `name` AS tag')
		            ->from('#__k2_tags')
		            ->where('published = 1')
		            ->order('`name`')
            );

            $results = $this->dbo->loadObjectList();
        } catch (Exception $e) {
            $results = array();
        }

        return $results;
    }

    private function replaceBaseUrl($url)
    {
        return preg_replace('/^(.*)(index.php.*)$/i', '$2', $url);
    }

    private function listFiles($d, $type = '')
    {
        $root = JPATH_ROOT . '/' . $d;

        $files = array();
        $dirs = array();
        $directories = array();
        $last_letter = $root[strlen($root) - 1];
        $root = ($last_letter == '\\' || $last_letter == '/') ? $root : $root . DIRECTORY_SEPARATOR;

        $directories[] = $root;

        while (sizeof($directories)) {
            $dir = array_pop($directories);
            if ($handle = opendir($dir)) {
                $count = 0;
                $ignore = array('cgi-bin', '.', '..', '._', '_installation', 'cache', 'bin', 'cli', 'logs', 'tmp');
                while (false !== ($file = readdir($handle))) {
                    if (in_array($file, $ignore) || substr($file, 0, 1) == '.') {
                        continue;
                    }

                    $path = $dir . $file;
                    $file_size = filesize($path);
                    $obj = new stdClass();
                    $obj->name = $file;
                    $obj->key = $count++;
                    $obj->file_size = round($file_size / 1024, 2);

                    if (preg_match('/\.(bmp|gif|svg|ico|jpe?g|png|svg|tiff?|webp)$/', $file))
                    {
	                    list($width, $height) = getimagesize($path);
	                    $obj->image_width = $width;
	                    $obj->image_height = $height;
                    }

                    if (is_dir($dir . $file)) {
                        $obj->type = 'dir';
                        $dirs[] = $obj;
                    } else {
                        $obj->type = 'file';
                        $files[] = $obj;
                    }
                }
                closedir($handle);
            }
        }
        if ($type === 'file') {
            return $files;
        } elseif ($type === 'dir') {
            return $dirs;
        }

        return array_merge($dirs, $files);
    }


    private function getEasyBlogAuthor($id)
    {
        $q = $this->dbo->getQuery(true);
        $q->select($this->dbo->quoteName('nickname'))
            ->from($this->dbo->quoteName('#__easyblog_users'))
            ->where($this->dbo->quoteName('id') . ' = ' . $id);
        $this->dbo->setQuery($q);

        return $this->dbo->loadResult();
    }

    private function truncateText($text, $limit, $limit_by_word = false)
    {
        // Clear all shortcode tags.
        $text = preg_replace('/(\{\{[^\}]*\}\}|\{[^\}]*\})/', ' ', $text);

        // Use method from JSN Ext. Framework 2 if available.
        if (method_exists('JsnExtFwText', 'truncate'))
        {
            return JsnExtFwText::truncate($text, $limit . ($limit_by_word ? 'w' : 'c'), true);
        }

        // Clear all <!-- ... --> comment tags.
        $parts = explode('<!--', $text);
        $text = $parts[0];

        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            $tempo = explode('-->', $parts[$i]);
            $text .= $tempo[1];
        }

        // Clear all <style> tag.
        $parts = explode('<style', $text);
        $text = $parts[0];

        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            $tempo = explode('</style>', $parts[$i]);
            $text .= $tempo[1];
        }

        // Clear all <script> tag.
        $parts = explode('<script', $text);
        $text = $parts[0];

        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            $tempo = explode('</script>', $parts[$i]);
            $text .= $tempo[1];
        }

        // Insert a space between sibling close and open HTML tag.
        $text = preg_replace('#(</[^>]+>)(<[^\>]+>)#', '\\1 \\2', trim($text));

        // Truncate text.
        if ($limit_by_word) {
            $text = $this->limit_text(strip_tags($text), $limit);
        } else {
            $text = \Joomla\String\StringHelper::substr(strip_tags($text), 0, $limit);
        }
        return html_entity_decode($text);
    }

    private function limit_text($text, $limit)
    {
        // Use method from JSN Ext. Framework 2 if available.
        if (method_exists('JsnExtFwText', 'getWords'))
        {
            return JsnExtFwText::getWords($text, $limit);
        }

        $arr_words = explode(" ", trim($text), $limit + 1);
        array_pop($arr_words);
        return implode(" ", $arr_words);
    }

    public function json_fix($data)
    {
        # Process arrays
        if (is_array($data))
        {
            $new = array();
            foreach ($data as $k => $v)
            {
                $new[$k] = $this->json_fix($v);
            }
            $data = $new;
        } # Process objects
        else if (is_object($data))
        {
            $datas = get_object_vars($data);
            foreach ($datas as $m => $v)
            {
                $data->$m = $this->json_fix($v);
            }
        } # Process strings
        else if (is_string($data) && !json_encode($data)) {
            $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
            $data = preg_replace($regex, '$1', $data);
        }

        return $data;
    }

    private function authorizeRequest()
    {
        if (!JFactory::getUser()->authorise('core.manage') || !JSession::checkToken('get')) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array('success' => false, 'message' => 'Unauthorized request!')
            );
            die;
        }
    }

    public function sendEmail()
    {
        header('Content-type: application/json');
        $input      = JFactory::getApplication()->input;
        $post       = $input->post->getArray();

        $jconfig    = JFactory::getConfig();
        $secret     = $jconfig->get('secret');

        if (!isset($post[md5($secret)]))
        {
            echo json_encode(array('success' => false, 'message' => 'Invalid Token'));
            exit();
        }

        if ($input->getInt('recaptcha') || $input->getInt('invisible_recaptcha'))
        {
        	// Verify recaptcha.
	        try
	        {
		        // Get Joomla event dispatcher.
		        $dispatcher = JEventDispatcher::getInstance();

		        if ($input->getInt('recaptcha')
			        && JPluginHelper::importPlugin('captcha', 'recaptcha', false))
		        {
			        // Load the plugin from the database.
			        $plugin = JPluginHelper::getPlugin('captcha', 'recaptcha');

			        // Instantiate the plugin.
			        $plugin = new PlgCaptchaRecaptcha($dispatcher, (array) $plugin);
		        }
		        elseif ($input->getInt('invisible_recaptcha')
			        && JPluginHelper::importPlugin('captcha', 'recaptcha_invisible', false))
		        {
			        // Load the plugin from the database.
			        $plugin = JPluginHelper::getPlugin('captcha', 'recaptcha_invisible');

			        // Instantiate the plugin.
			        $plugin = new PlgCaptchaRecaptcha_Invisible($dispatcher, (array) $plugin);
		        }

		        if (!isset($plugin))
		        {
		        	throw new Exception('Not found any plugin to verify captcha.');
		        }

		        if (!$plugin->onCheckAnswer())
		        {
			        throw new Exception('Captcha verification failed.');
		        }
	        }
	        catch (Exception $e)
	        {
		        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
		        exit();
	        }

	        unset($post['recaptcha']);
	        unset($post['invisible_recaptcha']);
	        unset($post['g-recaptcha-response']);
	        unset($post['recaptcha_challenge_field']);
	        unset($post['recaptcha_response_field']);
        }

        jimport('joomla.mail.helper');

        $exclusive = array('recipient', 'bcc', 'subject', 'from_name', 'from_email', 'custom_message', md5($secret));

        if (count((array) $post))
        {
            $mailer             = JFactory::getMailer();
            $recipient          = array();
            $fromName           = '';
            $fromEmail          = '';
            $customMessage      = '';
            $subject            = '';
            $body               = '';

            // Check Recipient
            if (isset($post['recipient']))
            {
                if (trim($post['recipient']) != '')
				{
					$recipient = array_map('trim', array_map('strip_tags', explode(
						',', $post['recipient']
					)));
				}
            }

            if (!count($recipient))
            {
                $recipient = array($jconfig->get('mailfrom'));
            }

            foreach ($recipient as $k => $email)
            {
	            if (!JMailHelper::isEmailAddress($email))
	            {
	            	unset($recipient[$k]);
	            }
            }

	        if (!count($recipient))
	        {
		        echo json_encode(array('success' => false, 'message' => 'Recipient email address is invalid!'));
		        exit();
	        }
            // Check Recipient

            // Check Subject
            if (isset($post['subject']))
            {
                $subject = trim($post['subject']);
            }

            if ($subject == '')
            {
                $subject = $jconfig->get('sitename');
            }
            // Check Subject

            // Check From Name
            if (isset($post['from_name']))
            {
                $fromName = trim(strip_tags($post['from_name']));
            }

            if ($fromName == '')
            {
                $fromName = $jconfig->get('fromname');
            }
            // Check From Name

            // Check From Email
            if (isset($post['from_email']))
            {
                $fromEmail = trim(strip_tags($post['from_email']));
            }

            if ($fromEmail == '')
            {
                $fromEmail = $jconfig->get('mailfrom');
            }
            // Check From Email

            // Check Customer Message
            if (isset($post['custom_message']))
            {
                $customMessage = trim(strip_tags($post['custom_message']));
            }

            if ($customMessage == '')
            {
                $customMessage = 'Email was sent successfully!';
            }
            // Check From Email

            foreach ($post as $key => $item)
            {
                if (!in_array($key, $exclusive))
                {
                    if (is_array($item))
                    {
                        if (count((array) $item))
                        {
                            $tmpItem    = $item;
                            $tmpString  = '';

                            foreach ($tmpItem as $subKey => $subItem)
                            {
                                if (!is_numeric($subKey))
                                {
                                    if ($subItem != '')
                                    {
                                        $tmpString .= '<li><strong>' . ucwords(str_replace('_', ' ', (string) $subKey)) . '</strong>: ' . (string) $subItem . '</li>';
                                    }
                                }
                                else
                                {
                                    $tmpString .= '<li>' . (string) $subItem . '</li>';
                                }
                            }


                            if ($tmpString == '')
                            {
                                $item = '';
                            }
                            else
                            {
                                $item = '<ul>' . $tmpString . '</ul>';
                            }

                        }
                        else
                        {
                            $item = '';
                        }
                    }
                    else
                    {
                        $item = strip_tags((string) $item);

                        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])T(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/", $item))
                        {
                            $item = date('Y-m-d H:i:s', strtotime($item));
                        }
                    }

                    if ((string) $item != '')
                    {
                        $body .= '<p><strong>' . ucwords(str_replace('_', ' ', (string) $key)) . '</strong>: ' . (string) $item . '</p>';
                    }
                }
            }

            if ($body == '')
            {
                echo json_encode(array('success' => false, 'message' => 'The data is invalid!'));
                exit();
            }

            $mailer->setSender(array($fromEmail, $fromName));
            $mailer->setSubject(stripslashes($subject));
            $mailer->IsHtml(true);

            $mailer->setBody($body);

	        // Send the Mail
            if (isset($post['bcc']) && $post['bcc'] === 'true')
            {
            	foreach ((array) $recipient as $to)
	            {
		            $mailer->addRecipient($to);
		            $rs = $mailer->Send();

		            if ($rs instanceof Exception || empty($rs))
		            {
		            	break;
		            }

		            $mailer->clearAllRecipients();
	            }
            }
            else
            {
	            $mailer->addRecipient($recipient);
	            $rs = $mailer->Send();
            }

            // Check for an error
            if ($rs instanceof Exception)
            {
                echo json_encode(array('success' => false, 'message' => $rs->getMessage()));
                exit();
            }
            elseif (empty($rs))
            {
                echo json_encode(array('success' => false, 'message' => 'The mail could not be sent!'));
                exit();
            }
            else
            {
                echo json_encode(array('success' => true, 'message' => $customMessage));
                exit();
            }
        }

        echo json_encode(array('success' => false, 'message' => 'The data is invalid!'));
        exit();
    }

    protected function fetchPageBuilder3TemplateData()
    {
        //header('Content-Type: application/json');
        $this->isEditable();

        $input  = JFactory::getApplication()->input;
        $url    = trim($input->getString('url', ''));

        if ($url == '')
        {
            echo json_encode(array('success' => false, 'message' => 'The data is invalid!'));
            exit();
        }

        $result = JSNPageBuilder3ContentHelper::fetchHttp($url);

        echo $result;
        exit();

    }

    private function authorizeMediaRequest($delete = false)
    {

        if (!JSession::checkToken('get') || ! (int) JFactory::getUser()->get('id'))
        {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array('success' => false, 'message' => 'Unauthorized request!'));
            die();
        }

        if ($delete)
        {
            if (!JFactory::getUser()->authorise('core.create', 'com_media') || !JFactory::getUser()->authorise('core.delete', 'com_media'))
            {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(array('success' => false, 'message' => 'Unauthorized request!'));
                die();
            }
        }
        else
        {
            if (!JFactory::getUser()->authorise('core.create', 'com_media'))
            {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(array('success' => false, 'message' => 'Unauthorized request!'));
                die();
            }
        }
    }

    private function isLogin()
    {
        if (!JSession::checkToken('get') || ! (int) JFactory::getUser()->get('id'))
        {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array('success' => false, 'message' => 'Unauthorized request!'));
            die();
        }
    }

    private function isEditable()
    {
        if ((!JFactory::getUser()->authorise('core.edit') && !JFactory::getUser()->authorise('core.create')) || !JSession::checkToken('get') || ! (int) JFactory::getUser()->get('id')) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array('success' => false, 'message' => 'Unauthorized request!')
            );
            die;
        }
    }
}

// Fallback support for old pb3ajax=1.
if ($this->app->input->getInt('pb3ajax') === 1) {
    $ajax = new JSNPageBuilder3Ajax();
    $data = $ajax->handleRequest();
    if ($result = json_encode($data)) {
        echo $result;
    } else {
        echo json_encode($ajax->json_fix($data));
    }
    exit;
}
