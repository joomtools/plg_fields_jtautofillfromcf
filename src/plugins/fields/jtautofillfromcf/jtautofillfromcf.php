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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);

JFormHelper::addFieldPath(__DIR__ . '/fields');

/**
 * Fields Autofill Plugin
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
	 * @return   array|string[][]
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
	 * @param   Form      $form
	 * @param   stdClass  $data
	 *
	 * @return   void
	 *
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Form $form, $data)
	{
		$this->context = $form->getName();

		return parent::onContentPrepareForm($form, $data);
	}

	/**
	 * Prepares the field
	 *
	 * @param   string    $context  The context.
	 * @param   stdclass  $item     The item.
	 * @param   stdclass  $field    The field.
	 *
	 * @return   string
	 *
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareField($context, $item, $field)
	{
		// Check if the field should be processed
		if (!$this->isTypeSupported($field->type))
		{
			return null;
		}

		$app       = Factory::getApplication();
		$cfField   = $field->fieldparams->get('cfield');

		// Get field id and field context from param
		list($cfFieldId, $cfFieldContext) = explode(',', $cfField);

		$cfFieldId  = (int) $cfFieldId;
		$valueId    = (int) Factory::getUser()->id;
		$articleId  = $app->input->getInt('id');
		$fieldValue = !empty($field->rawvalue);

		if ($app->isClient('site'))
		{
			$articleId = $app->input->getInt('a_id');
		}

		$fieldsModel = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));

		if (!empty($articleId))
		{
			$valueId = $articleId;

			if ($cfFieldContext == 'com_users.user')
			{
				$valueId = (int) $this->getArticleUserId($articleId);
			}
		}

		if (!empty($valueId))
		{
			$defaultValue = $fieldsModel->getFieldValue($cfFieldId, $valueId);

			if ($fieldValue === false)
			{
				$field->default_value = $defaultValue;
				$field->value         = Text::_($defaultValue);
				$field->rawvalue      = $defaultValue;
			}
		}

		// The field's rawvalue should be an array
		if (!is_array($field->rawvalue))
		{
			$field->rawvalue = (array) $field->rawvalue;
		}

		return parent::onCustomFieldsPrepareField($context, $item, $field);
	}

	/**
	 * Get custom field from param.
	 *
	 * @param   string  $param
	 *
	 * @return   stdclass
	 *
	 * @since   1.0.0
	 */
	private function getCustomField($param)
	{
		// Get field id and field context from param
		list($fieldId, $fieldContext) = explode(',', $param);

		// Get user custom fields
		$fields = FieldsHelper::getFields($fieldContext);

		// Set field id as array key
		$fields = ArrayHelper::pivot($fields, 'id');

		return $fields[$fieldId];
	}

	/**
	 * Transforms the field into a DOM XML element and appends it as a child on the given parent.
	 *
	 * @param   stdClass    $field   The field.
	 * @param   DOMElement  $parent  The field node parent.
	 * @param   Form        $form    The form.
	 *
	 * @return   DOMElement
	 *
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareDom($field, DOMElement $parent, Form $form)
	{
		if ($field->type != 'jtautofillfromcf')
		{
			return null;
		}

		$app       = Factory::getApplication();
		$cfField   = $field->fieldparams->get('cfield');

		// Get field id and field context from param
		list($cfFieldId, $cfFieldContext) = explode(',', $cfField);


		$cfFieldId    = (int) $cfFieldId;
		$valueId      = (int) Factory::getUser()->id;
		$articleId    = $app->input->getInt('id');
		$newField     = $this->getCustomField($cfField);
		$fieldValue   = !empty($field->rawvalue);
		$defaultValue = '';

		if ($app->isClient('site'))
		{
			$articleId = $app->input->getInt('a_id');
		}

		$fieldsModel = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));

		if (!empty($articleId))
		{
			$valueId = $articleId;

			if ($cfFieldContext == 'com_users.user')
			{
				$valueId = (int) $this->getArticleUserId($articleId);
			}
		}

		if (!empty($valueId))
		{
			$defaultValue = $fieldsModel->getFieldValue($cfFieldId, $valueId);

			if ($fieldValue === false)
			{
				$field->default_value = $defaultValue;
//				$field->value         = Text::_($defaultValue);
				$field->rawvalue      = $defaultValue;
			}
			else
			{
				$this->setFieldValue($field->id, $valueId, $field->rawvalue);
			}
		}

		$oldFieldparams = $field->fieldparams->toArray();
		$newFieldparams = $newField->fieldparams->toArray();
		$fieldparams = array_merge($oldFieldparams, $newFieldparams);
		$field->fieldparams = new Joomla\Registry\Registry($fieldparams);

		$fieldNode = parent::onCustomFieldsPrepareDom($field, $parent, $form);

		if (!empty($fieldNode))
		{
			$fieldNode->setAttribute('type', $newField->type);

			// Set the specific field parameters
			$fieldNode = $this->setParamsCustomField($newField, $fieldNode);
		}

		// Check if it is allowed to edit the field
		if (!FieldsHelper::canEditFieldValue($field))
		{
			$fieldNode->setAttribute('disabled', 'true');

			if ($articleId !== null)
			{
				if ($fieldValue === false)
				{
					$this->setFieldValue($field->id, $articleId, $defaultValue);
				}
			}
		}

		return $fieldNode;
	}

	/**
	 * Returns an array of key values to put in a list from the given field.
	 *
	 * @param   stdClass  $field  The field.
	 *
	 * @return   array
	 *
	 * @since   1.0.0
	 */
	private function getOptionsFromField($field)
	{
		$data = array();

		// Fetch the options from the plugin
		$params = clone $this->params;
		$params->merge($field->fieldparams);

		foreach ($params->get('options', array()) as $option)
		{
			$op = (object) $option;
			$data[$op->value] = $op->name;
		}

		return $data;
	}

	/**
	 * Get user id for given article id.
	 *
	 * @param   int  articleId
	 *
	 * @return   int
	 *
	 * @since    1.0.0
	 */
	private function getArticleUserId($articleId)
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
	 * @return   boolean
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

	/**
	 * Set the specific field parameters
	 *
	 * @param   stdClass     $newField
	 * @param   DOMElement  $fieldNode
	 *
	 * @return   DOMElement
	 *
	 * @since   1.0.0
	 */
	private function setParamsCustomField($newField, DOMElement $fieldNode)
	{
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

		if (in_array($newField->type, array('list', 'radio', 'checkboxes', 'combo'))
			&& !empty($newField->fieldparams->get('options', null)))
		{
			if ($newField->type != 'combo')
			{
				$fieldNode->setAttribute('validate', 'options');
			}

			foreach ($this->getOptionsFromField($newField) as $value => $name)
			{
				$option              = new DOMElement('option', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
				$option->textContent = htmlspecialchars(JText::_($name), ENT_COMPAT, 'UTF-8');

				$element = $fieldNode->appendChild($option);
				$element->setAttribute('value', $value);
			}
		}

		return $fieldNode;
	}

}
