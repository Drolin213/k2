<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.pagebreak
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Render buttons to toggle between default editor and PageBuilder3.
 */
class PlgButtonPageBuilder3 extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var  boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * Display buttons to toggle between default editor and PageBuilder3.
	 *
	 * @param   string  $name  The name of the button to add
	 *
	 * @return  JObject  The button options as JObject
	 */
	public function onDisplay($name)
	{
		// Get Joomla application object.
		$app = JFactory::getApplication();

		// Get Joomla input object.
		$input = $app->input;

		// Get Joomla database object.
		$dbo = JFactory::getDbo();

		// Define container signatures.
		if ($input->getCmd('option') == 'com_content' && $input->getCmd('layout') == 'edit'
			&& in_array($input->getCmd('view'), array('article', 'form')))
		{
			if ($input->getCmd('view') == 'article')
			{
				$pos = 'before';
				$elm = '#general .span9 .adminform';
			}
			else
			{
				$pos = 'after';
				$elm = '#editor .control-group';
			}
		}
		elseif (in_array($input->getCmd('option'), array('com_modules', 'com_advancedmodules'))
			&& $input->getCmd('view') == 'module' && $input->getCmd('layout') == 'edit')
		{
			// Get module data.
			if ($input->getInt('id'))
			{
				$module = $dbo->setQuery(
					$dbo->getQuery(true)
						->select('module')
						->from('#__modules')
						->where('id = ' . $input->getInt('id'))
				)->loadResult();
			}
			elseif ($input->getInt('eid') || $app->getUserState('com_modules.add.module.extension_id')
				|| $app->getUserState('com_advancedmodules.add.module.extension_id'))
			{
				if ($input->getInt('eid'))
				{
					$module = $input->getInt('eid');
				}
				elseif ($app->getUserState('com_modules.add.module.extension_id'))
				{
					$module = (int) $app->getUserState('com_modules.add.module.extension_id');
				}
				else
				{
					$module = (int) $app->getUserState('com_advancedmodules.add.module.extension_id');
				}

				if ($module)
				{
					$module = $dbo->setQuery(
						$dbo->getQuery(true)
							->select('element')
							->from('#__extensions')
							->where('extension_id = ' . $module)
					)->loadResult();
				}
			}

			if (isset($module) && $module == 'mod_custom')
			{
				$pos = 'after';
				$elm = '#general .span9 .info-labels + div';
			}
		}
		elseif ($input->getCmd('option') == 'com_config'
			&& $input->getCmd('controller') == 'config.display.modules' && $input->getInt('id'))
		{
			// Get module data.
			$module = $dbo->setQuery(
				$dbo->getQuery(true)
					->select('module')
					->from('#__modules')
					->where('id = ' . $input->getInt('id'))
			)->loadResult();

			if ($module == 'mod_custom')
			{
				$pos = 'before';
				$elm = '#custom > *:first-child';
			}
		}
		elseif ($input->getCmd('option') == 'com_advancedmodules' && $input->getCmd('view') == 'edit'
			&& $input->getCmd('task') == 'edit' && $input->getInt('id'))
		{
			// Get module data.
			$module = $dbo->setQuery(
				$dbo->getQuery(true)
					->select('module')
					->from('#__modules')
					->where('id = ' . $input->getInt('id'))
			)->loadResult();

			if ($module == 'mod_custom')
			{
				$pos = 'before';
				$elm = '#jform_content';
			}
		}
		elseif ($input->getCmd('option') == 'com_k2' && $input->getCmd('view') == 'item')
		{
			$pos = 'before';
			$elm = '#k2Tabs .k2TabsContent .k2ItemFormEditor';
		}
		elseif ($input->getCmd('option') == 'com_virtuemart' && $input->getCmd('view') == 'product')
		{
			$pos = 'after';
			$elm = '#admin-ui-tabs .tabs:nth-of-type(2) fieldset:nth-of-type(2) legend';
		}
		elseif ($input->getCmd('option') == 'com_djcatalog2' && $input->getCmd('view') == 'item'
			&& $input->getCmd('layout') == 'edit')
		{
			$pos = 'before';
			$elm = '#jform_description-lbl';
		}
		elseif ($input->getCmd('option') == 'com_hikashop' && $input->getCmd('ctrl') == 'product')
		{
			$pos = 'after';
			$elm = '.hikashop_product_edit_description .hikashop_product_edit_description_title';
		}
		elseif ($input->getCmd('option') == 'com_digicom' && $input->getCmd('view') == 'product'
			&& $input->getCmd('layout') == 'dgform')
		{
			$pos = 'after';
			$elm = '#jform_fulltext-lbl';
		}
		elseif ($input->getCmd('option') == 'com_falang' && $input->getCmd('task') == 'translate.edit'
			&& in_array($input->getCmd('catid'), array('content', 'modules')))
		{
			if ($input->getCmd('catid') == 'content')
			{
				$pos = 'after';
				$elm = 'input[name="id_introtext"]';
			}
			else
			{
				$pos = 'after';
				$elm = 'input[name="id_content"]';
			}
		}
		elseif ($input->getCmd('option') == 'com_cck' && $input->getCmd('view') == 'form'
			&& in_array($input->getCmd('type'), array('article', 'category')))
		{
			$pos = $input->getCmd('type') == 'article' ? 'after' : 'before';
			$elm = $input->getCmd('type') == 'article' ? '#cck1r_label_art_fulltext' : '#cck1r_form_cat_description';
		}

		if (isset($elm))
		{
			// Get requested editor.
			$editor = JFactory::getUser()->getParam('editor');

			JsnExtFwAssets::loadInlineStyle('
				#pb3-editor-switcher {
					margin-bottom: 18px !important;
					padding-left: 0;
				}
				#k2Tabs .k2TabsContent #pb3-editor-switcher,
				.hikashop_product_edit_description #pb3-editor-switcher {
					margin-top: 18px !important;
				}
				.virtuemart-admin-area #pb3-editor-switcher label {
					margin-right: 0;
				}
				.com_djcatalog2 #details #pb3-editor-switcher label {
					width: initial;
					min-width: initial;
                    padding: 4px 12px;
				}
			');

			JsnExtFwAssets::loadInlineScript(
				';document.addEventListener("DOMContentLoaded", function() {
					(function createEditorSwitcher() {
						// Find the reference element.
						var sign = document.querySelector(\'' . $elm . '\');

						if (sign) {
							' . ($input->getCmd('option') == 'com_djcatalog2' && $input->getCmd('view') == 'item' ? '
							sign = sign.parentNode.nextElementSibling.firstElementChild;
							' : '') . '
							// Create button group for switching editor.
							var toggler = document.createElement("div");

							toggler.className = "form-horizontal";
							toggler.lastEditor = "' . $editor . '";
							toggler.style.clear = "both";

							toggler.innerHTML = "<div id=\"pb3-editor-switcher\" class=\"control-group btn-group btn-group-yesno radio\">"
								+ "<input type=\"radio\" id=\"pb3-editor-switcher-default\" name=\"pb3-editor-switcher\" value=\"default\"' . ($editor != 'pagebuilder3' ? ' checked' : '') . '>"
								+ "<label for=\"pb3-editor-switcher-default\" class=\"btn' . ($editor != 'pagebuilder3' ? ' active btn-success' : '') . '\">Joomla Editor</label>"
								+ "<input type=\"radio\" id=\"pb3-editor-switcher-pb3\" name=\"pb3-editor-switcher\" value=\"pagebuilder3\"' . ($editor == 'pagebuilder3' ? ' checked' : '') . '>"
								+ "<label for=\"pb3-editor-switcher-pb3\" class=\"btn' . ($editor == 'pagebuilder3' ? ' active btn-success' : '') . '\">JSN PageBuilder 3</label>"
								+ "</div>";

							toggler.onclick = function(event) {
								if (
									event.target.nodeName == "LABEL" 
									&& 
									event.target.previousElementSibling.value != toggler.lastEditor
								) {
									if (event.target.previousElementSibling.value == "pagebuilder3") {
										window.location.href = window.location.href.replace(/([\?&])editor=default/, "").replace(/(#.*)?$/, "")
											+ (window.location.href.indexOf("?") < 0 ? "?" : "&") + "editor=pagebuilder3"
											' . ($input->getCmd('option') == 'com_virtuemart' && $input->getCmd('view') == 'product' && $input->getCmd('task') == 'add' ? '
											+ (window.location.href.indexOf("&task=add") > -1 ? "" : "&task=add")
											' : '') . '
											' . ($input->getCmd('option') == 'com_virtuemart' && $input->getCmd('view') == 'product' && $input->getCmd('task') == 'edit' ? '
											+ (window.location.href.indexOf("&task=edit") > -1 ? "" : "&task=edit&virtuemart_product_id=' . current($input->getInt('virtuemart_product_id')) . '")
											' : '') . '
											' . ($input->getCmd('option') == 'com_hikashop' && $input->getCmd('ctrl') == 'product' && $input->getCmd('task') == 'add' ? '
											+ (window.location.href.indexOf("&task=add") > -1 ? "" : "&task=add")
											' : '') . '
											' . ($input->getCmd('option') == 'com_hikashop' && $input->getCmd('ctrl') == 'product' && in_array($input->getCmd('task'), array('edit', 'apply')) ? '
											+ (window.location.href.indexOf("&task=edit") > -1 ? "" : "&task=edit&cid[]=" + document.querySelector(\'input[name="product_id"]\').value)
											' : '') . '
											' . ($input->getCmd('option') == 'com_falang' && $input->getCmd('task') == 'translate.edit' ? '
											+ "&catid=' . $input->getCmd('catid') . '" + (window.location.href.indexOf("&task=translate.edit") > -1 ? "" : "&task=translate.edit&cid[]=' . current($input->getString('cid')) . '")
											' : '') . ';
									} else {
										window.location.href = window.location.href.replace(/([\?&])editor=pagebuilder3/, "").replace(/(#.*)?$/, "")
											+ (window.location.href.indexOf("?") < 0 ? "?" : "&") + "editor=default"
											' . ($input->getCmd('option') == 'com_virtuemart' && $input->getCmd('view') == 'product' && $input->getCmd('task') == 'add' ? '
											+ (window.location.href.indexOf("&task=add") > -1 ? "" : "&task=add")
											' : '') . '
											' . ($input->getCmd('option') == 'com_virtuemart' && $input->getCmd('view') == 'product' && $input->getCmd('task') == 'edit' ? '
											+ (window.location.href.indexOf("&task=edit") > -1 ? "" : "&task=edit&virtuemart_product_id=' . current($input->getInt('virtuemart_product_id')) . '")
											' : '') . '
											' . ($input->getCmd('option') == 'com_hikashop' && $input->getCmd('ctrl') == 'product' && $input->getCmd('task') == 'add' ? '
											+ (window.location.href.indexOf("&task=add") > -1 ? "" : "&task=add")
											' : '') . '
											' . ($input->getCmd('option') == 'com_hikashop' && $input->getCmd('ctrl') == 'product' && in_array($input->getCmd('task'), array('edit', 'apply')) ? '
											+ (window.location.href.indexOf("&task=edit") > -1 ? "" : "&task=edit&cid[]=" + document.querySelector(\'input[name="product_id"]\').value)
											' : '') . '
											' . ($input->getCmd('option') == 'com_falang' && $input->getCmd('task') == 'translate.edit' ? '
											+ "&catid=' . $input->getCmd('catid') . '" + (window.location.href.indexOf("&task=translate.edit") > -1 ? "" : "&task=translate.edit&cid[]=' . current($input->getString('cid')) . '")
											' : '') . ';
									}

									toggler.lastEditor = event.target.previousElementSibling.value;
								}
							};

							sign.parentNode.insertBefore(toggler, sign' . ($pos == 'after' ? '.nextSibling' : '') . ');
							' . ($input->getCmd('option') == 'com_modules' && $editor == 'pagebuilder3' ? '
							// Enable Prepare Content parameter when JSN PageBuilder 3 is used on a custom module.
							var enablePrepareContent = document.querySelector("#jform_params_prepare_content0 + label");

							if (enablePrepareContent) {
								enablePrepareContent.click();
							}' : '') . '
						}
						else if (!createEditorSwitcher.retry || createEditorSwitcher.retry <= 100) {
							createEditorSwitcher.retry = createEditorSwitcher.retry ? createEditorSwitcher.retry + 1 : 1;
							setTimeout(createEditorSwitcher, 100);
						}
					})();
				});'
			);
		}
	}
}
