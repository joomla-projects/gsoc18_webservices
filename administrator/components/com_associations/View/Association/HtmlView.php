<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_associations
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Associations\Administrator\View\Association;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Associations\Administrator\Helper\AssociationsHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * View class for a list of articles.
 *
 * @since  3.7.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * An array of items
	 *
	 * @var    array
	 *
	 * @since  3.7.0
	 */
	protected $items;

	/**
	 * The pagination object
	 *
	 * @var    \Joomla\CMS\Pagination\Pagination
	 *
	 * @since  3.7.0
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var    object
	 *
	 * @since  3.7.0
	 */
	protected $state;

	/**
	 * Selected item type properties.
	 *
	 * @var    \Joomla\Registry\Registry
	 *
	 * @since  3.7.0
	 */
	public $itemType = null;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 * @throws  \Exception
	 */
	public function display($tpl = null)
	{
		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors), 500);
		}

		$this->app  = Factory::getApplication();
		$this->form = $this->get('Form');
		$input      = $this->app->input;
		$this->referenceId = $input->get('id', 0, 'int');

		list($extensionName, $typeName) = explode('.', $input->get('itemtype', '', 'string'), 2);

		$extension = AssociationsHelper::getSupportedExtension($extensionName);
		$types     = $extension->get('types');

		if (array_key_exists($typeName, $types))
		{
			$this->type          = $types[$typeName];
			$this->typeSupports  = array();
			$details             = $this->type->get('details');
			$this->save2copy     = false;

			if (array_key_exists('support', $details))
			{
				$support = $details['support'];
				$this->typeSupports = $support;
			}

			if (!empty($this->typeSupports['save2copy']))
			{
				$this->save2copy = true;
			}
		}

		$this->extensionName = $extensionName;
		$this->typeName      = $typeName;
		$this->itemtype      = $extensionName . '.' . $typeName;

		$languageField = AssociationsHelper::getTypeFieldName($extensionName, $typeName, 'language');
		$referenceId   = $input->get('id', 0, 'int');
		$reference     = ArrayHelper::fromObject(AssociationsHelper::getItem($extensionName, $typeName, $referenceId));

		$this->referenceLanguage   = $reference[$languageField];
		$this->referenceTitle      = AssociationsHelper::getTypeFieldName($extensionName, $typeName, 'title');
		$this->referenceTitleValue = $reference[$this->referenceTitle];

		// Check for special case category
		$typeNameExploded = explode('.', $typeName);

		if (array_pop($typeNameExploded) === 'category')
		{
			$this->typeName = 'category';

			if ($typeNameExploded)
			{
				$extensionName .= '.' . implode('.', $typeNameExploded);
			}

			$options = array(
				'option'    => 'com_categories',
				'view'      => 'category',
				'extension' => $extensionName,
				'tmpl'      => 'component',
			);
		}
		else
		{
			$options = array(
				'option'    => $extensionName,
				'view'      => $typeName,
				'extension' => $extensionName,
				'tmpl'      => 'component',
			);
		}

		// Reference and target edit links.
		$this->editUri = 'index.php?' . http_build_query($options);

		// Get target language.
		$this->targetId         = '0';
		$this->targetLanguage   = '';
		$this->defaultTargetSrc = '';
		$this->targetAction     = '';
		$this->targetTitle      = '';

		if ($target = $input->get('target', '', 'string'))
		{
			$matches = preg_split("#[\:]+#", $target);
			$this->targetAction     = $matches[2];
			$this->targetId         = $matches[1];
			$this->targetLanguage   = $matches[0];
			$this->targetTitle      = AssociationsHelper::getTypeFieldName($extensionName, $typeName, 'title');
			$task                   = $typeName . '.' . $this->targetAction;

			/*
			 * Let's put the target src into a variable to use in the javascript code
			 *  to avoid race conditions when the reference iframe loads.
			 */
			$document = Factory::getDocument();
			$document->addScriptOptions('targetSrc', Route::_($this->editUri . '&task=' . $task . '&id=' . (int) $this->targetId));
			$this->form->setValue('itemlanguage', '', $this->targetLanguage . ':' . $this->targetId . ':' . $this->targetAction);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since  3.7.0
	 */
	protected function addToolbar()
	{
		// Hide main menu.
		Factory::getApplication()->input->set('hidemainmenu', 1);

		$helper = AssociationsHelper::getExtensionHelper($this->extensionName);
		$title  = $helper->getTypeTitle($this->typeName);

		$languageKey = strtoupper($this->extensionName . '_' . $title . 'S');

		if ($this->typeName === 'category')
		{
			$languageKey = strtoupper($this->extensionName) . '_CATEGORIES';
		}

		ToolbarHelper::title(Text::sprintf('COM_ASSOCIATIONS_TITLE_EDIT', Text::_($this->extensionName), Text::_($languageKey)), 'language assoc');

		$bar = Toolbar::getInstance('toolbar');

		$bar->appendButton(
			'Custom', '<button onclick="Joomla.submitbutton(\'reference\')" '
			. 'class="btn btn-sm btn-success"><span class="icon-apply" aria-hidden="true"></span>'
			. Text::_('COM_ASSOCIATIONS_SAVE_REFERENCE') . '</button>', 'reference'
		);

		$bar->appendButton(
			'Custom', '<button onclick="Joomla.submitbutton(\'target\')" '
			. 'class="btn btn-sm btn-success"><span class="icon-apply" aria-hidden="true"></span>'
			. Text::_('COM_ASSOCIATIONS_SAVE_TARGET') . '</button>', 'target'
		);

		if ($this->typeName === 'category' || $this->extensionName === 'com_menus' || $this->save2copy === true)
		{
			ToolbarHelper::custom('copy', 'copy.png', '', 'COM_ASSOCIATIONS_COPY_REFERENCE', false);
		}

		ToolbarHelper::cancel('association.cancel', 'JTOOLBAR_CLOSE');
		ToolbarHelper::help('JHELP_COMPONENTS_ASSOCIATIONS_EDIT');
	}
}
