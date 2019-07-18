<?php
/**
 * @package          Joomla.Plugin
 * @subpackage       Fields.Jtautofillfromcf
 *
 * @author           Guido De Gobbis <support@joomtools.de>
 * @copyright    (c) 2019 JoomTools.de - All rights reserved.
 * @license          GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);

JFormHelper::addFieldPath(__DIR__ . '/fields');

/**
 * Fields Text Plugin
 *
 * @since  1.0.0
 */
class PlgFieldsJtAutofillFromCf extends FieldsPlugin
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
		if ($field->type != 'jtautofillfromcf')
		{
			return null;
		}

		$cfField = $field->fieldparams->get('cfield');

		list($cfFieldId, $cfFieldContext) = explode(',', $cfField);


		$userId      = (int) Factory::getUser()->id;
		$articleId   = Factory::getApplication()->input->getInt('a_id');
		$fieldsModel = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));

		if ($articleId !== null)
		{
			$userId = (int) $this->getUserId($articleId);
		}

		// Get user custom fields
		$fields = FieldsHelper::getFields($cfFieldContext);

		// Set field id as array key
		$fields = ArrayHelper::pivot($fields, 'id');

		$newField     = $fields[$cfFieldId];
		$fieldValue   = $field->rawvalue;
		$cfFieldValue = $fieldsModel->getFieldValue($cfFieldId, $userId);

		if ($fieldValue === null)
		{
			$field->default_value = $cfFieldValue;
		}

		$fieldNode = parent::onCustomFieldsPrepareDom($field, $parent, $form);

		$fieldNode->setAttribute('type', $newField->type);

		// Check if it is allowed to edit the field
		if (!FieldsHelper::canEditFieldValue($field))
		{
			$fieldNode->setAttribute('disabled', 'true');

			if ($articleId !== null)
			{
				if ($fieldValue != $cfFieldValue)
				{
					$this->setFieldValue($field->id, $articleId, $cfFieldValue);
				}
			}
		}

		// Set the specific field parameters
		foreach ($newField->fieldparams->toArray() as $key => $param)
		{
			if (is_array($param))
			{
				// Multidimensional arrays (eg. list options) can't be transformed properly
				$param = count($param) == count($param, COUNT_RECURSIVE) ? implode(',', $param) : '';
			}

			if ($param === '' || (!is_string($param) && !is_numeric($param)))
			{
				continue;
			}

			$fieldNode->setAttribute($key, $param);
		}

		return $fieldNode;
	}

	/**
	 * Get user id for given article id.
	 *
	 * @param   int  articleId
	 *
	 * @return   int
	 * @since    1.0.0
	 */
	private function getUserId($articleId)
	{
		$db = Factory::getDbo();

		$query = $db->getQuery(true);
		$query->select($db->qn('created_by'))
			->from($db->qn('#__content'))
			->where($db->qn('id') . '=' . $db->q((int) $articleId));

		$userId = $db->setQuery($query)->loadResult();

		return (int) $userId;
	}

	/**
	 * Setting the value for the given field id, context and item id.
	 *
	 * @param   string  $fieldId  The field ID.
	 * @param   string  $itemId   The ID of the item.
	 * @param   string  $value    The value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function setFieldValue($fieldId, $itemId, $value)
	{
		$db          = Factory::getDbo();
		$fieldsModel = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));

		$needsDelete = false;
		$needsInsert = false;
		$needsUpdate = false;

		$oldValue = $fieldsModel->getFieldValue($fieldId, $itemId);
		$value    = (array) $value;

		if ($oldValue === null)
		{
			// No records available, doing normal insert
			$needsInsert = true;
		}
		elseif (count($value) == 1 && count((array) $oldValue) == 1)
		{
			// Only a single row value update can be done when not empty
			$needsUpdate = is_array($value[0]) ? count($value[0]) : strlen($value[0]);
			$needsDelete = !$needsUpdate;
		}
		else
		{
			// Multiple values, we need to purge the data and do a new
			// insert
			$needsDelete = true;
			$needsInsert = true;
		}

		if ($needsDelete)
		{
			// Deleting the existing record as it is a reset
			$query = $db->getQuery(true);

			$query->delete($query->qn('#__fields_values'))
				->where($query->qn('field_id') . ' = ' . (int) $fieldId)
				->where($query->qn('item_id') . ' = ' . $query->q($itemId));

			$db->setQuery($query)->execute();
		}

		if ($needsInsert)
		{
			$newObj = new stdClass;

			$newObj->field_id = (int) $fieldId;
			$newObj->item_id  = $itemId;

			foreach ($value as $v)
			{
				$newObj->value = $v;

				$db->insertObject('#__fields_values', $newObj);
			}
		}

		if ($needsUpdate)
		{
			$updateObj = new stdClass;

			$updateObj->field_id = (int) $fieldId;
			$updateObj->item_id  = $itemId;
			$updateObj->value    = reset($value);

			$db->updateObject('#__fields_values', $updateObj, array('field_id', 'item_id'));
		}

		FieldsHelper::clearFieldsCache();

		return true;
	}

}
