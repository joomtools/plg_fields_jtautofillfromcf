<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Text
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);

JFormHelper::addFieldPath(__DIR__ . '/fields');

/**
 * Fields Text Plugin
 *
 * @since  3.7.0
 */
class PlgFieldsJtAutofillFromUserCf extends FieldsPlugin
{
	/**
	 * @var   string
	 *
	 * @since   1.0.0
	 */
	private $context;

	/**
	 * Show field only in allowed sections
	 *
	 * @return   array|\string[][]
	 *
	 * @since   1.0.0
	 */
	public function onCustomFieldsGetTypes()
	{
		if (in_array($this->context, array(
			'com_fields.field.com_content.article',
			null,
		)))
		{
			return parent::onCustomFieldsGetTypes();
		}

		return array();
	}

	/**
	 * Set context for validation of allowed sections
	 *
	 * @param   \JForm     $form
	 * @param   \stdClass  $data
	 *
	 * @return   void
	 *
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(JForm $form, $data)
	{
		$this->context = $form->getName();

		return parent::onContentPrepareForm($form, $data);
	}

	/**
	 * Transforms the field into a DOM XML element and appends it as a child on the given parent.
	 *
	 * @param   stdClass    $field   The field.
	 * @param   DOMElement  $parent  The field node parent.
	 * @param   JForm       $form    The form.
	 *
	 * @return   DOMElement
	 *
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareDom($field, DOMElement $parent, JForm $form)
	{
		$fieldNode = parent::onCustomFieldsPrepareDom($field, $parent, $form);

		if (!$fieldNode)
		{
			return $fieldNode;
		}

		$userId = JFactory::getUser()->id;
		$articleId = JFactory::getApplication()->input->get('id');

		if ($articleId !== null)
		{
			$userId = $this->getUserId($articleId);
		}

		$fieldId = $field->fieldparams->get('usercfield');
		$value = $this->getUserCfValue($fieldId, $userId);

		$fieldNode->setAttribute('default', $value);

		return $fieldNode;
	}

	/**
	 *
	 */
	private function getUserId($articleId)
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select($db->qn('created_by'))
			->from($db->qn('#__content'))
			->where($db->qn('id') . '=' . $db->q((int) $articleId));

		$userId = $db->setQuery($query)->loadResult();

		return (int) $userId;
	}

	/**
	 *
	 */
	private function getUserCfValue($fieldId, $userId)
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select($db->qn('value'))
			->from($db->qn('#__fields_values'))
			->where(array(
				$db->qn('field_id') . '=' . $db->q((int) $fieldId),
				$db->qn('item_id') . '=' . $db->q((int) $userId),
			));

		$value = $db->setQuery($query)->loadResult();

		return (string) $value;
	}

}
