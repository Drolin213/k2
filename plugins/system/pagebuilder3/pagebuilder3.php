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

/**
 * JSN PageBuilder3 system plugin.
 *
 * @package  JSN_PageBuilder3
 * @since    1.0.0
 */
class PlgSystemPageBuilder3 extends JPlugin
{
    /**
     * Define PageBuilder signatures.
     *
     * @var  string
     * @since 1.0.0
     */
    public static $start_html = '<!-- Start PageFly HTML -->';
    public static $end_html = '<!-- End PageFly HTML -->';
    public static $start_data = '<!-- Start PageFly Data|'; // Old (for support old article)
    public static $end_data = '|End PageFly Data -->';
    public static $start_hash = '<!-- Start PageFly Hash|';  // New for PageBuilder 3 save in #__jsn_pagebuilder3_pages
    public static $end_hash = '|End PageFly Hash -->';
    public static $unSupport = array('com_quick2cart', 'com_joomsport');
    public static $instance;
	public static $onRenderPageBuilderContent = false;

    /**
     * The PageBuilder 3 component.
     *
     * @var  string
     * @since 1.0.0
     */
    protected static $ext = 'com_pagebuilder3';
    /**
     * Joomla application object.
     *
     * @var  object
     * @since 1.0.0
     */
    protected $app;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @return  void
	 */
	public function __construct($subject, $option = array())
	{
		parent::__construct($subject, $option);

		self::$instance = &$this;
	}

    /**
     * Register onAfterInitialise event handler.
     *
     * @return  void
     * @since 1.0.0
     */
    public function onAfterInitialise()
    {
        $this->app = JFactory::getApplication();
        $option = $this->app->input->getString('option');

	    if (!in_array($option, self::$unSupport)) {
		    defined('PAGEFLY_VERSION') || define('PAGEFLY_VERSION', '1.3.16');
		    define('JSNPB3_EDITOR_SWITCHER_AVAILABLE', true);
	    }

        // Get active language.
        $lang = JFactory::getLanguage();

        // Check if language file exists for active language.
        if (!file_exists(JPATH_ROOT . '/administrator/language/' . $lang->get('tag') . '/' . $lang->get('tag') . '.plg_system_pagebuilder3.ini')) {
            // Load language file from plugin directory.
            $lang->load('plg_system_pagebuilder3', dirname(__FILE__), null, true);
        } else {
            $lang->load('plg_system_pagebuilder3', JPATH_ADMINISTRATOR, null, true);
        }

        // Initialize Ajax handler for PageBuilder app.
        require_once dirname(__FILE__) . '/includes/ajax.php';

	    // Load edition manager.
        if ($this->app->isAdmin() && $option === 'com_pagebuilder3' && class_exists('JsnExtFwAssets')) {
            JsnExtFwAssets::loadEditionManager();
        }

	    // Reset the default editor because JSN PageBuilder 3 no longer work as a native editor.
	    $isDefault = in_array('pagebuilder3', array(
		    JFactory::getConfig()->get('editor'),
		    JFactory::getUser()->getParam('editor')
	    ));

	    if ($isDefault && (int) JFactory::getUser()->get('id'))
	    {
		    $alterEditor = '';

		    if (JPluginHelper::isEnabled('editors', 'tinymce'))
		    {
			    $alterEditor = 'tinymce';
		    }
		    elseif (JPluginHelper::isEnabled('editors', 'codemirror')) {
			    $alterEditor = 'codemirror';
		    }
		    elseif (JPluginHelper::isEnabled('editors', 'none')) {
			    $alterEditor = 'none';
		    }

		    if (!empty($alterEditor))
		    {
			    // Override global config.
			    JFactory::getConfig()->set('editor', $alterEditor);

			    // Override user config.
			    JFactory::getUser()->setParam('editor', $alterEditor);
		    }
	    }
    }

    /**
     * Implement onAfterRoute event handler to switch default editor if necessary.
     *
     * @return  void
     */
    public function onAfterRoute()
    {
    	// Check if editing content is created using JSN PageBuilder 3.
	    $dbo = JFactory::getDbo();
	    $content = null;
	    $found = null;

	    // If editing a module at frontend, get the module content.
	    if ($this->app->input->getCmd('option') == 'com_config' && $this->app->input->getCmd('controller') == 'config.display.modules'
		    && $this->app->input->getInt('id'))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__modules')
				    ->where('id = ' . $this->app->input->getInt('id'))
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->content;
		    }
	    }

	    // If editing a K2 item, get the item content.
	    elseif ($this->app->input->getCmd('option') == 'com_k2' && $this->app->input->getCmd('view') == 'item'
		    && $this->app->input->getInt('cid'))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__k2_items')
				    ->where('id = ' . $this->app->input->getInt('cid'))
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->introtext . $res->fulltext;
		    }
	    }

	    // If editing a VirtueMart product, get the product description.
	    elseif ($this->app->input->getCmd('option') == 'com_virtuemart' && $this->app->input->getCmd('view') == 'product'
		    && $this->app->input->getInt('virtuemart_product_id'))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__virtuemart_products_' . strtolower(str_replace('-', '_', JFactory::getLanguage()->getTag())))
				    ->where('virtuemart_product_id IN (' . implode(',', (array) $this->app->input->getInt('virtuemart_product_id')) . ')')
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->product_desc;
		    }
	    }

	    // If editing a DJ Catalog product, get the product description.
	    elseif ($this->app->input->getCmd('option') == 'com_djcatalog2' && $this->app->input->getCmd('view') == 'item'
		    && $this->app->input->getCmd('layout') == 'edit' && $this->app->input->getInt('id'))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__djc2_items')
				    ->where('id = ' . $this->app->input->getInt('id'))
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->description;
		    }
	    }

	    // If editing a HikaShop product, get the product description.
	    elseif ($this->app->input->getCmd('option') == 'com_hikashop' && $this->app->input->getCmd('ctrl') == 'product'
		    && ($this->app->input->getCmd('task') == 'apply' || ($this->app->input->getCmd('task') == 'edit' && $this->app->input->getInt('cid'))))
	    {
	    	if ($this->app->input->getCmd('task') == 'apply' && $this->app->input->getCmd('pb3-editor-switcher') == 'pagebuilder3')
		    {
		    	$found = true;
		    }
	    	else
		    {
			    $res = $dbo->setQuery(
				    $dbo->getQuery(true)
					    ->select('*')
					    ->from('#__hikashop_product')
					    ->where('product_id = ' . current($this->app->input->getInt('cid')))
			    )->loadObject();

			    if ($res)
			    {
				    $content = $res->product_description;
			    }
		    }
	    }

	    // If editing a DigiCom product, get the product description.
	    elseif ($this->app->input->getCmd('option') == 'com_digicom' && $this->app->input->getCmd('view') == 'product'
		    && $this->app->input->getCmd('layout') == 'edit' && $this->app->input->getInt('id'))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__digicom_products')
				    ->where('id = ' . $this->app->input->getInt('id'))
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->fulltext;
		    }
	    }

	    // If editing a FaLang translation, get the translation text.
	    elseif ($this->app->input->getCmd('option') == 'com_falang' && $this->app->input->getCmd('task') == 'translate.edit'
		    && in_array($this->app->input->getCmd('catid'), array('content', 'modules')) && $this->app->input->getString('cid'))
	    {
		    list($translation_id, $content_id, $language_id) = explode('|', current($this->app->input->getString('cid')));

		    if ($content_id && $language_id)
		    {
			    $res = $dbo->setQuery(
				    $dbo->getQuery(true)
					    ->select('*')
					    ->from('#__falang_content')
					    ->where('reference_id = ' . $content_id)
					    ->where('language_id = ' . $language_id)
					    ->where('reference_table = ' . $dbo->quote($this->app->input->getCmd('catid')))
					    ->where('reference_field IN ("content", "introtext", "fulltext")')
			    )->loadObjectList();

			    if ($res)
			    {
				    $content = $res[0]->value . (isset($res[1]) ? $res[1]->value : '');
			    }
		    }
	    }

	    // If editing a module instance with Advanced Module Manager, get the module content.
	    elseif ($this->app->input->getCmd('option') == 'com_advancedmodules' && $this->app->input->getInt('id')
		    && (
			    ($this->app->input->getCmd('view') == 'module' && $this->app->input->getCmd('layout') == 'edit')
		        ||
			    ($this->app->input->getCmd('view') == 'edit' && $this->app->input->getCmd('task') == 'edit')
		    ))
	    {
		    $res = $dbo->setQuery(
			    $dbo->getQuery(true)
				    ->select('*')
				    ->from('#__modules')
				    ->where('id = ' . $this->app->input->getInt('id'))
		    )->loadObject();

		    if ($res)
		    {
			    $content = $res->content;
		    }
	    }

	    // If editing an article or a category with SEBLOD, get the article content or category description.
	    elseif ($this->app->input->getCmd('option') == 'com_cck' && $this->app->input->getCmd('view') == 'form'
	        && in_array($this->app->input->getCmd('type'), array('article', 'category')) && $this->app->input->getInt('id'))
	    {
	    	if ($this->app->input->getCmd('type') == 'article')
		    {
			    $res = $dbo->setQuery(
				    $dbo->getQuery(true)
					    ->select('*')
					    ->from('#__content')
					    ->where('id = ' . $this->app->input->getInt('id'))
			    )->loadObject();
		    }
	    	else
		    {
			    $res = $dbo->setQuery(
				    $dbo->getQuery(true)
					    ->select('*')
					    ->from('#__categories')
					    ->where('id = ' . $this->app->input->getInt('id'))
			    )->loadObject();
		    }

		    if ($res)
		    {
			    $content = $this->app->input->getCmd('type') == 'article' ? $res->introtext : $res->description;
		    }
	    }

	    // Check if editing content is created using JSN PageBuilder 3.
	    if (!empty($content))
	    {
		    if (strpos($content, self::$start_html) !== false && strpos($content, self::$end_html) !== false)
		    {
			    $found = true;
		    }
		    elseif (strpos($content, self::$start_data) !== false && strpos($content, self::$end_data) !== false)
		    {
			    $found = true;
		    }
		    elseif (strpos($content, self::$start_hash) !== false && strpos($content, self::$end_hash) !== false)
		    {
			    $found = true;
		    }
	    }

	    // If JSN PageBuilder 3 editor is requested, set plain text as default editor.
	    if ((!empty($found) && $this->app->input->getCmd('editor') != 'default')
		    || $this->app->input->getCmd('editor') == 'pagebuilder3')
	    {
		    // Override global config.
		    JFactory::$config->set('editor', 'pagebuilder3');

		    // Override user config.
		    JFactory::getUser()->setParam('editor', 'pagebuilder3');
	    }
    }

    /**
     * Method to set PageBuilder as default editor if editing an item previously edited by PageBuilder.
     *
     * @return  void
     */
    protected function switchEditor()
    {
        // Switch default editor for the current user if necessary.
        $option = $this->app->input->getCmd('option');
        $view = $this->app->input->getCmd('view');
        $task = $this->app->input->getCmd('task', $this->app->input->getCmd('layout'));
        // Continue only if in admin interface.
        if (!$this->app->isAdmin() && $task !== 'edit') {
            return;
        }
        if (strpos($task, '.') !== false) {
            list($view, $task) = explode('.', $task);
        }

        if ($option == 'com_hikashop') {
            $view = $this->app->input->getCmd('ctrl');
        } elseif ($option == 'com_k2' && $view == 'item' && $this->app->input->getInt('cid')) {
            $task = 'edit';
        }

        $supported = array(
            'com_content.article',
            'com_modules.module',
            'com_virtuemart.product',
            'com_djcatalog2.item',
            'com_hikashop.product',
            'com_digicom.product',
            'com_falang.translate',
            'com_k2.item'
        );

        if (in_array("{$option}.{$view}", $supported)) {
            $this->ajaxHandler = new JSNPageBuilder3Ajax();
            $session = $this->app->getSession();

            if ($task == 'edit') {
                if (!$this->app->input->getCmd('switchFrom')) {
                    switch ($option) {
                        case 'com_content':
                            // Check if the editing article was edited by PageBuilder?
                            JLoader::register('ContentModelArticle', JPATH_ADMINISTRATOR . '/components/com_content/models/article.php');

                            if ($item = JModelAdmin::getInstance('Article', 'ContentModel')) {
                                if ($item = $item->getItem($this->app->input->getInt('id'))) {
                                    $editedByPageBuilder = (strpos($item->introtext, self::$start_html) !== false &&
                                        strpos($item->introtext, self::$end_html) !== false);

                                    if (!$editedByPageBuilder) {
                                        $editedByPageBuilder = (strpos($item->fulltext, self::$start_html) !== false &&
                                            strpos($item->fulltext, self::$end_html) !== false);
                                    }

                                    if (!$editedByPageBuilder) {
                                        $editedByPageBuilder = (strpos($item->articletext, self::$start_html) !== false &&
                                            strpos($item->articletext, self::$end_html) !== false);
                                    }

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_modules':
                            // Check if the editing module was edited by PageBuilder?
                            JLoader::register('ModulesModelModule', JPATH_ADMINISTRATOR . '/components/com_modules/models/module.php');

                            if ($item = JModelAdmin::getInstance('Module', 'ModulesModel')) {
                                if ($item = $item->getItem($this->app->input->getInt('id'))) {
                                    $editedByPageBuilder = (strpos($item->content, self::$start_html) !== false &&
                                        strpos($item->content, self::$end_html) !== false);

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_virtuemart':
                            // Check if the editing product was edited by PageBuilder?
                            require_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';

                            JLoader::register('vObject', JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vobject.php');
                            JLoader::register('vRequest', JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vrequest.php');
                            JLoader::register('VmTable', JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vmtable.php');
                            JLoader::register('VmModel', JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vmmodel.php');

                            JLoader::load('vObject');
                            JLoader::load('vRequest');
                            JLoader::load('VmTable');

                            if ($item = VmModel::getModel('product')) {
                                if ($item = $item->getProductSingle($this->app->input->getInt('virtuemart_product_id'))) {
                                    $editedByPageBuilder = (strpos($item->product_desc, self::$start_html) !== false &&
                                        strpos($item->product_desc, self::$end_html) !== false);

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_djcatalog2':
                            // Check if the editing product was edited by PageBuilder?
                            define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_djcatalog2');

                            JLoader::register('Djcatalog2ModelItem', JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');

                            if ($item = new Djcatalog2ModelItem()) {
                                if ($item = $item->getItem($this->app->input->getInt('id'))) {
                                    $editedByPageBuilder = (strpos($item->description, self::$start_html) !== false &&
                                        strpos($item->description, self::$end_html) !== false);

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_hikashop':
                            // Check if the editing product was edited by PageBuilder?
                            require_once JPATH_ADMINISTRATOR . '/components/com_hikashop/helpers/helper.php';

                            if ($item = hikashop_get('class.product')) {
                                if ($item = $item->get(hikashop_getCID('product_id'), true)) {
                                    $editedByPageBuilder = (strpos($item->product_description, self::$start_html) !== false &&
                                        strpos($item->product_description, self::$end_html) !== false);

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_digicom':
                            // Check if the editing product was edited by PageBuilder?
                            JLoader::register('TableFiles', JPATH_ADMINISTRATOR . '/components/com_digicom/tables/files.php');
                            JLoader::register('TableBundle', JPATH_ADMINISTRATOR . '/components/com_digicom/tables/bundle.php');
                            JLoader::register('TableProduct', JPATH_ADMINISTRATOR . '/components/com_digicom/tables/product.php');
                            JLoader::register('DigiComModelProduct', JPATH_ADMINISTRATOR . '/components/com_digicom/models/product.php');

                            if ($item = JModelAdmin::getInstance('Product', 'DigiComModel')) {
                                if ($item = $item->getItem($this->app->input->getInt('id'))) {
                                    $editedByPageBuilder = (strpos($item->fulltext, self::$start_html) !== false &&
                                        strpos($item->fulltext, self::$end_html) !== false);

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;

                        case 'com_falang':
                            if (!class_exists('FalangManager')) {
                                return;
                                break;
                            }
                            // Check if the editing translation was edited by PageBuilder?
                            $cid = $this->app->input->getVar('cid', array(
                                0
                            ));
                            $translation_id = 0;

                            if (strpos($cid[0], '|') >= 0) {
                                list($translation_id, $contentid, $language_id) = explode('|', $cid[0]);

                                $select_language_id = $this->app->getUserStateFromRequest('selected_lang', 'select_language_id', '-1');
                                $_language_id = $this->app->input->getVar('language_id', $select_language_id);
                                $select_language_id = ($select_language_id == -1 && $_language_id != -1) ? $_language_id : $select_language_id;
                                $select_language_id = ($select_language_id == -1 && $language_id != -1) ? $language_id : $select_language_id;
                            } else {
                                $select_language_id = -1;
                            }

                            $catid = $this->app->getUserStateFromRequest('selected_catid', 'catid', '');

                            if (isset($catid) && $catid != '') {
                                $contentElement = FalangManager::getInstance()->getContentElement($catid);

                                JLoader::import('models.ContentObject', JPATH_ADMINISTRATOR . '/components/com_falang');

                                $actContentObject = new ContentObject($language_id, $contentElement);

                                $actContentObject->loadFromContentID($contentid);

                                $elementTable = $actContentObject->getTable();

                                foreach ($elementTable->Fields as $field) {
                                    $field->preHandle($elementTable);

                                    if ($field->Translate) {
                                        $editedByPageBuilder = (strpos($field->originalValue, self::$start_html) !== false &&
                                            strpos($field->originalValue, self::$end_html) !== false);

                                        if (!$editedByPageBuilder && !empty($field->translationContent->value)) {
                                            $editedByPageBuilder = (strpos($field->translationContent->value, self::$start_html) !== false &&
                                                strpos($field->translationContent->value, self::$end_html) !== false);
                                        }

                                        if ($editedByPageBuilder) {
                                            // Change active editor to PageBuilder 3.
                                            return $this->ajaxHandler->switchEditor('pagebuilder3');
                                        }
                                    }
                                }
                            }
                            break;

                        case 'com_k2':
                            // Check if the editing K2 item was edited by PageBuilder?
                            K2Model::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/models');

                            if ($item = K2Model::getInstance('Item', 'K2Model',
                                array(
                                    'table_path' => JPATH_ADMINISTRATOR . '/components/com_k2/tables'
                                ))) {
                                if ($item = $item->getData()) {
                                    $editedByPageBuilder = (strpos($item->introtext, self::$start_html) !== false &&
                                        strpos($item->introtext, self::$end_html) !== false);

                                    if (!$editedByPageBuilder) {
                                        $editedByPageBuilder = (strpos($item->fulltext, self::$start_html) !== false &&
                                            strpos($item->fulltext, self::$end_html) !== false);
                                    }

                                    if ($editedByPageBuilder) {
                                        // Change active editor to PageBuilder 3.
                                        return $this->ajaxHandler->switchEditor('pagebuilder3');
                                    }
                                }
                            }
                            break;
                    }

                    // Restore default editor for the current user.
                    if ($session->has('pb3_user_editor') && $option !== 'com_falang' && $option !== 'com_k2') {
                        $this->ajaxHandler->switchEditor($session->get('pb3_user_editor', 'global'));
                    }
                }
            } else {
                // Restore default editor for the current user.

                // Temporary turn off auto switch editor due to buggy

                //                if ($session->has('pb3_user_editor') && $option !== 'com_falang' && $option !== 'com_k2') {
                //                    $this->ajaxHandler->switchEditor($session->get('pb3_user_editor', 'global'));
                //                }
            }
        }
    }

    /**
     * Implement onUserAfterSave event handler to reset default editor of the current user in session storage.
     *
     * @param   array   $props  Properties.
     * @param   boolean $isNew  Whether a new item is saved.
     * @param   boolean $result Whether save action completed successfully.
     * @param   string  $error  Error message if has.
     *
     * @return  void
     */
//    public function onUserAfterSave($props, $isNew, $result, $error)
//    {
//        if ($result && !$isNew && $props['id'] == JFactory::getUser()->get('id')) {
//            // Decode user params.
//            if ($params = json_decode($props['params'])) {
//                JFactory::getApplication()->getSession()->clear('pb3_user_editor');
//            }
//        }
//    }

    public function onAjaxPageBuilder3($return = false)
    {
        require_once dirname(__FILE__) . '/includes/ajax.php';
        $format = $this->app->input->getString('format', 'json');
        $task = $this->app->input->getString('task', '');
        if ($format === 'html' & $task === 'getDefaultPage') {
            die('<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta http-equiv="X-UA-Compatible" content="IE=edge"><base href="' . JUri::root() . '"/></head><body></body></html>');
        }
        $ajax = new JSNPageBuilder3Ajax();
        $data = $ajax->handleRequest();
        if (!$return) {
            if ($result = json_encode($data)) {
                echo $result;
            } else {
                echo json_encode($ajax->json_fix($data));
            }
            exit;
        }
        return ($result = json_encode($data)) ? $result : json_encode($ajax->json_fix($data));
    }

    /**
     * Register onBeforeRender event handler.
     *
     * @return  void
     * @since 1.0.0
     */
    public function onBeforeRender()
    {
        $doc = JFactory::getDocument();

        if ($this->app->isAdmin())
        {
            $option = $this->app->input->getString('option', '');
            if ($option == 'com_rstbox')
            {
                return;
            }
        }

	    // Remove JSN PageBuilder 3 from list of editor.
	    $doc->addScriptDeclaration(';
			jQuery(function($) {
				$(document).ready(function() {
					var options = document.querySelectorAll("option[value=\"pagebuilder3\"]");
					for (var i = 0; i < options.length; i++) {
						var selector = options[i].parentNode;
						options[i].parentNode.removeChild(options[i]);
						if ($(selector).data("chosen")) {
							var params = $(selector).data("chosen").options;
							$(selector).chosen("destroy");
							$(selector).chosen(params);
						}
					}
				});
			});
		');

	    // Assets for frontend
        if ($this->app->isSite()) {
            $doc->addScript(JUri::root(true) . '/plugins/editors/pagebuilder3/assets/app/assets/' . PAGEFLY_VERSION . '/helper.js');
            $jversion = new JVersion();
            $ver = $jversion->getShortVersion();
            $isJoomla37OrHigher = version_compare($ver, '3.7', '>=');
            if ($isJoomla37OrHigher) {
                $doc->addStyleSheet(JUri::root(true) . '/plugins/editors/pagebuilder3/assets/app/assets/' . PAGEFLY_VERSION . '/main.css', array(), array('data-pagefly-main' => 'true'));
            } else {
                $doc->addStyleSheet(JUri::root(true) . '/plugins/editors/pagebuilder3/assets/app/assets/' . PAGEFLY_VERSION . '/main.css', null, null, array('data-pagefly-main' => 'true'));
            }
            //JHTML::_('behavior.modal');
        }
        $doc->addScriptDeclaration('
		window.pb_baseUrl = "' . JUri::root() . '";
		');
    }

    /**
     * Register onAfterRender event handler.
     *
     * @return  void
     * @since 1.0.0
     */
    public function onAfterRender()
    {
        // Render PageBuilder content if has.
        try {
            if ($this->app->isSite() && $this->app->input->getString('format', '') !== "raw") {
 				$config 	= JFactory::getConfig();
                $secret 	= $config->get('secret');
                // Get the current response body.
                $body = $this->app->getBody();
				$url    = rtrim(JURI::root(), '/');
                $body   = str_replace('JSN_PAGEBUILDER3_ROOT_URL', $url, $body);
                $body   = str_replace('JSN_PAGEBUILDER3_JOOMLA_TOKEN', md5($secret), $body);
                self::renderPageBuilderContent($body);
				//$body = self::handleLink($body);
				if (self::$onRenderPageBuilderContent)
				{
					$body = self::handleLink($body);
				}
                // Trigger onContentPrepare event to allow content plugins, such as Email Cloaking,
	            // to manipulate the final content.
				$option = $this->app->input->getString('option', '');
	            if (JPluginHelper::importPlugin('content') && $option != 'com_k2')
	            {
	            	$object = (object) array('text' => $body);
	            	$params = new JRegistry();

	            	$this->app->triggerEvent(
	            		'onContentPrepare',
			            array('com_pagebuilder3.content', &$object, &$params, 0)
		            );

		            $body = $object->text;
	            }

                $this->app->setBody($body);

            }
        } catch (Exception $e) {
            //            throw new $e;
        }
    }

    /**
     * Method to render PageBuilder content.
     *
     * @param   string &$content Content to render.
     *
     * @return  void
     * @since 1.0.0
     */
    protected static function renderPageBuilderContent(&$content)
    {
        // Parse PageBuilder content.
        if (false !== strpos($content, self::$start_html) && false !== strpos($content, self::$end_html)) {
			
			if (!self::$onRenderPageBuilderContent)
			{
				self::$onRenderPageBuilderContent = true;
			}
			
            // Look for PageBuilder content (at deepest level first) based on defined signatures.
            $temp = explode(self::$end_html, $content, 2);
            $from = strpos($temp[0], self::$start_html);
            $html = substr($temp[0], $from + strlen(self::$start_html));

            $data = self::getPageBuilderData($temp, $html);
            $fullData = $data['data'];
            if ($fullData !== null) {
                $page = $data['page'];
                $temp = $html;

                // Render Google reCAPTCHA if has embed tags.
	            $temp = self::renderCaptcha($temp);

                // Render all {pb3loadmodule} tags that are not rendered.
                if (strpos( $temp, 'loadmodule') !== false) {
                    $temp = self::renderModule($temp);
                }
                if ($page) {
                    //$version = isset($page->created_version) ? $page->created_version : isset($page->pb_version) ? $page->pb_version : 100;
                    
					if (isset($page->created_version))
					{
						$version = $page->created_version;
					}
					else
					{
						if (isset($page->pb_version))
						{
							$version = $page->pb_version;
						}
						else
						{
							$version = 100;
						}
					}
					$temp = '<div data-pb-version="' . $version . '" style="display: none;"></div>' . $temp;
                    $temp = self::renderDynamicContent($page, $temp);
                }

                //$temp = self::handleLink($temp);
                $oldContent = $content;
                $content = str_replace(
                    $fullData,
                    $temp,
                    $content
                );
                // Continue render remaining PageBuilder content.
                if ($oldContent !== $content) {
                    self::renderPageBuilderContent($content);
                }
            }
        }
    }

    public static function getPageBuilderData($temp, $html)
    {
        $page = false;
        $fullData = null;
        if (!class_exists('JSNPageBuilder3ContentHelper')) {
            require_once JPATH_ROOT . '/administrator/components/' . self::$ext . '/helpers/content.php';
        }
        if (false !== strpos($temp[1], self::$end_data) && substr($temp[1], 0, 24) === self::$start_data) {
            $fromData = strpos($temp[1], self::$start_data) + strlen(self::$start_data);
            $data = substr($temp[1], $fromData, strpos($temp[1], self::$end_data) - $fromData);
            $page = json_decode(base64_decode($data));
            $fullData = self::$start_html . $html . self::$end_html . self::$start_data . $data . self::$end_data;
        } elseif (false !== strpos($temp[1], self::$end_hash) && substr($temp[1], 0, 24) === self::$start_hash) {
            $fromHash = strpos($temp[1], self::$start_hash) + strlen(self::$start_hash);
            $hash = substr($temp[1], $fromHash, strpos($temp[1], self::$end_hash) - $fromHash);
            $helper = new JSNPageBuilder3ContentHelper();
            $data = $helper->select('data', '#__jsn_pagebuilder3_pages', "`page_hash` =  '$hash'", true);
            if (is_object($data) && is_string($data->data)) {
	            $page = json_decode($data->data);
            }
            $fullData = self::$start_html . $html . self::$end_html . self::$start_hash . $hash . self::$end_hash;
        }
        return array('page' => $page, 'data' => $fullData);
    }

    private static function renderCaptcha($temp)
    {
		if (strpos($temp, '{{Recaptcha #') !== false
			|| strpos($temp, '{{Invisible Recaptcha #') !== false)
		{
			// Get Joomla event dispatcher.
			$dispatcher = JEventDispatcher::getInstance();

			// Get Joomla document object.
			$document = JFactory::getDocument();

			if (strpos($temp, '{{Recaptcha #') !== false)
			{
				if (class_exists('PlgCaptchaRecaptcha')
					|| JPluginHelper::importPlugin('captcha', 'recaptcha', false))
				{
					// Load the plugin from the database.
					$plugin = JPluginHelper::getPlugin('captcha', 'recaptcha');

					// Instantiate the plugin.
					$plugin = new PlgCaptchaRecaptcha($dispatcher, (array) $plugin);

					// Render recaptcha.
					$regexp = '/\{\{Recaptcha #(\d+)\}\}/';

					if (preg_match_all($regexp, $temp, $matches, PREG_SET_ORDER))
					{
						foreach ($matches as $match)
						{
							// Backup and clear loaded scripts.
							$loaded_script = $document->_script;
							$loaded_scripts = $document->_scripts;
							$document->_scripts = $document->_script = array();

							// Initialize recaptcha.
							$plugin->onInit("pb3_recaptcha_{$match[1]}");

							// Get assets that need to be loaded.
							$html = self::generateScriptTags($document->_scripts, $document->_script);
							$html = str_replace('onload=JoomlaInitReCaptcha2&', '', $html);

							// Get HTML tags to render recaptcha.
							$html .= $plugin->onDisplay(null, "pb3_recaptcha_{$match[1]}");
							$html .= '<input type="hidden" name="recaptcha" value="1" /><br />';

							// Restore loaded scripts.
							$document->_script = $loaded_script;
							$document->_scripts = $loaded_scripts;

							// Replace embed tag with real content.
							$temp = str_replace($match[0], $html, $temp);
						}
					}
				}
			}

			if (strpos($temp, '{{Invisible Recaptcha #') !== false)
			{
				if (class_exists('PlgCaptchaRecaptcha_Invisible')
					|| JPluginHelper::importPlugin('captcha', 'recaptcha_invisible', false))
				{
					// Load the plugin from the database.
					$plugin = JPluginHelper::getPlugin('captcha', 'recaptcha_invisible');

					// Instantiate the plugin.
					$plugin = new PlgCaptchaRecaptcha_Invisible($dispatcher, (array) $plugin);

					// Render invisible recaptcha.
					$regexp = '/\{\{Invisible Recaptcha #(\d+)\}\}/';

					if (preg_match_all($regexp, $temp, $matches, PREG_SET_ORDER))
					{
						foreach ($matches as $match)
						{
							// Backup and clear loaded scripts.
							$loaded_script = $document->_script;
							$loaded_scripts = $document->_scripts;
							$document->_scripts = $document->_script = array();

							// Initialize recaptcha.
							$plugin->onInit("pb3_invisible_recaptcha_{$match[1]}");

							// Get assets that need to be loaded.
							$html = self::generateScriptTags($document->_scripts, $document->_script);
							$html = str_replace('onload=JoomlaInitReCaptchaInvisible&', '', $html);

							// Get HTML tags to render recaptcha.
							$html .= $plugin->onDisplay(null, "pb3_invisible_recaptcha_{$match[1]}");
							$html .= '<input type="hidden" name="invisible_recaptcha" value="1" />';

							// Restore loaded scripts.
							$document->_script = $loaded_script;
							$document->_scripts = $loaded_scripts;

							// Replace embed tag with real content.
							$temp = str_replace($match[0], $html, $temp);
						}
					}
				}
			}
		}

		return $temp;
    }

    private static function generateScriptTags($scripts, $inline_scripts = array())
    {
    	$tags = array();

	    foreach ($scripts as $script => $attrs)
	    {
		    $tags[] = '<script src="' .
			    ((isset($attrs['relative']) && $attrs['relative']) ? JUri::root() . '/media/' : '') .
			    $script . '"' . ($attrs['type'] ? " type=\"{$attrs['type']}\"" : 'text/javascript') .
			    ((isset($attrs['async']) && $attrs['async']) ? ' async="async"' : '') .
			    ((isset($attrs['defer']) && $attrs['defer']) ? ' defer="defer"' : '') . '></script>';
	    }

	    foreach ($inline_scripts as $type => $content)
	    {
		    $tags[] = '<script type="' . ($type ?: 'text/javascript') . '">' . $content . '</script>';
	    }

	    return implode($tags);
    }

    private static function renderModule($temp)
    {
		// Get Joomla event dispatcher.
		$dispatcher = JEventDispatcher::getInstance();

		// Emulate an article.
		$article = (object) array('text' => $temp);
		$params = array();

		// Render all {loadmodule} tags if exists.
		if (strpos($temp, '{loadmodule ') !== false)
		{
			if (class_exists('PlgContentLoadmodule') || JPluginHelper::importPlugin('content', 'loadmodule', false))
			{
				// Load the plugin from the database.
				$plugin = JPluginHelper::getPlugin('content', 'loadmodule');

				// Instantiate the plugin.
				$plugin = new PlgContentLoadmodule($dispatcher, (array) $plugin);

				// Render all {loadmodule} tags.
				call_user_func_array(array($plugin, 'onContentPrepare'), array('', &$article, &$params, 0));
			}
		}

		// Render all {pb3loadmodule} tags if exists.
		if (strpos($temp, '{pb3loadmodule ') !== false)
		{
			if (class_exists('PlgContentPB3LoadModule') || JPluginHelper::importPlugin('content', 'pb3loadmodule'))
			{
				if (!isset(PlgContentPB3LoadModule::$instance))
				{
					// Load the plugin from the database.
					$plugin = JPluginHelper::getPlugin('content', 'pb3loadmodule');

					// Instantiate the plugin.
					new PlgContentPB3LoadModule($dispatcher, (array) $plugin);
				}

				// Render all {pb3loadmodule} tags.
				call_user_func_array(array(PlgContentPB3LoadModule::$instance, 'onContentPrepare'), array('', &$article, &$params, 0));
			}
		}

		return $article->text;
    }

    /**
     * @param $page
     * @param $temp
     *
     * @return mixed
     *
     * @since 1.4.0
     */
    public static function renderDynamicContent($page, $temp)
    {

        $attr = array();

        foreach ($page->items as $i => $element) {

            if (isset($element->url)) {
                $id = isset($element->_id) ? $element->_id : $i;
                $url = $element->url;
                $url = preg_match('/^http/is', $url) ? $url : JUri::base() . $url;
                try {
                    $res = JSNPageBuilder3ContentHelper::fetchHttp($url);
                    if ($res === false) {
                        $url = str_replace(JUri::root(), $_SERVER['HTTP_X_FORWARDED_BY'] . JUri::base(true) . '/', $url);
                        $res = JSNPageBuilder3ContentHelper::fetchHttp($url);
                    }
                    if ($res !== false) {
                        $attr[$id] = json_decode($res);
                    }

                } catch (Exception $e) {
                	error_log($e->getMessage());
                }
            }
        }

        if (count($attr) > 0) {
            // Loop thru dynamic content to render.
	        foreach ($attr as $key => $items) {
	        	// Find dynamic content templates to replace with real content.
		        $begin = '{{#' . $key . '}}';
		        $end = '{{/' . $key . '}}';

		        if (strpos($temp, $begin) !== false && strpos($temp, $end) !== false)
		        {
		        	// Get dynamic content templates.
			        $parts = explode($begin, $temp);
			        $matches = array();

			        for ($i = 1, $n = count($parts); $i < $n; $i++)
			        {
			        	$matches[] = array(
			        		'in' => current(explode($end, $parts[$i])),
				            'out' => ''
				        );
			        }

		        	// Replace template tags with real data.
			        $numTemplates = count($matches);

		        	foreach ($items as $idx => $item)
			        {
			        	// Get dynamic content template to replace with real content.
			        	if (isset($matches[$idx]))
				        {
				        	$t =& $matches[$idx];
				        }
			        	else
				        {
					        $t =& $matches[$numTemplates - 1];
				        }

				        // Find template tags to replace with real content.
				        if (preg_match_all('/\{\{([^\}]+)\}\}/', $t['in'], $tags, PREG_SET_ORDER))
				        {
					        $out = $t['in'];
							$out = str_replace('<picture><img src="{{images.image_intro}}"></picture>', '<picture><img alt="{{pb_alt_image_content}}" title="{{pb_alt_image_content}}" src="{{images.image_intro}}"></picture>', $out);
							//$out = str_replace('<picture><img src="{{image}}"></picture>', '<picture><img alt="{{pb_alt_image_content}}" title="{{pb_alt_image_content}}" src="{{images}}"></picture>', $out);
							$out = str_replace('<picture><img src="{{image}}" /></picture>', '<picture><img alt="{{pb_alt_image_content}}" title="{{pb_alt_image_content}}" src="{{images}}"></picture>', $out);

							if (count((array) $tags))
							{
								$tags [] = array('{{pb_alt_image_content}}', 'pb_alt_image_content');
							}

							foreach ($tags as $tag)
					        {
					        	// Parse key path to get data.
						        $path = explode('.', $tag[1]);

						        // Get data to replace.
						        $data = $item;

						        foreach ($path as $key)
						        {
						        	$data = $data->{$key};
						        }

						        // Replace template tag with real data.
						        $out = str_replace($tag[0], $data, $out);
					        }

				        	$t['out'] .= $out;
				        }
			        }

		        	// Replace dynamic content templates with real content.
			        foreach ($matches as $match)
			        {
			        	$temp = preg_replace(
			        		'/' . preg_quote("{$begin}{$match['in']}{$end}", '/') . '/',
					        $match['out'],
					        $temp,
					        1
				        );
			        }
		        }
	        }
        }

        // Remove all remaining dynamic content templates.
	    if (preg_match('/\{\{#\d+\}\}/', $temp))
	    {
		    $parts = preg_split('/\{\{#\d+\}\}/', $temp);
		    $temp  = $parts[0];

		    for ($i = 1, $n = count($parts); $i < $n; $i++)
		    {
			    $parts[$i] = preg_split('/\{\{\/\d+\}\}/', $parts[$i]);
			    $temp      .= array_pop($parts[$i]);
		    }
	    }

        return $temp;
    }

    public static function handleLink($content)
    {

        $base = JUri::base();
        $path_base = preg_replace("(^https?:)", "", JUri::base());
        // Replace all relative links with absolute URLs in HTML tags.
        if (preg_match_all('/(href|src)="([^"]+)"/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Fix some wrong image url in k2
                if (strpos($match[0], 'src="' . $path_base . 'http') !== false) {
                    preg_match('/(https?:\/\/[^\s]+)/', $match[0], $text);
                    $content = str_replace($match[0], 'src="' . $text[0] . '"', $content);
                }

                //Fix broken image link.
                if (strpos($match[0], $base) > 0 && strpos($match[0], 'src="') !== false) {
                    $content = str_replace($match[0], 'src="' . substr($match[0], strpos($match[0], $base)), $content);
                }
                // If link is joomla link, convert it to seo link.
                if (preg_match('/&pb-slug=1/', $match[2])) {
                    $slugLink = JRoute::_(str_replace('&pb-slug=1', '', $match[2]));
                    $content = str_replace($match[2], $slugLink, $content);
                }
            }
        }

        unset($matches);

        /**
         * Replace all relative links with absolute URLs in CSS rules.
         */
        if (preg_match_all('/url\(\s*[\'"]*([^\)]+)[\'"]*\s*\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // If link is relative, convert it to absolute.
                $match[1] = trim($match[1], '\'"');
                if (!preg_match('/^([a-zA-Z0-9]+:|\/|#)/', $match[1])) {
                    $newUrl = "{$base}{$match[1]}";
                    $newUrl = 'url(' . $newUrl . ')';
                    $content = str_replace($match[0], $newUrl, $content);
                }
            }
        }

        return $content;
    }
}
