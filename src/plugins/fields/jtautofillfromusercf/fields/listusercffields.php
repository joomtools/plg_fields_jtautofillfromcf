<?php

defined('_JEXEC') or die;

JFormHelper::loadFieldClass('list');

class JFormFieldListusercffields extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Listusercffields';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   3.7.0
	 */
	protected function getOptions()
	{
		$fields = FieldsHelper::getFields('com_users.user');
		$options   = array();

		foreach ($fields as $field)
		{
			$value = $field->id;
			$text  = trim($field->label);

			$tmp = array(
				'value'      => $value,
				'text'       => JText::_($text),
			);

			// Add the option object to the result set.
			$options[] = (object) $tmp;
		}

			$tmp        = new stdClass;
			$tmp->value = '';
			$tmp->text  = JText::_('JGLOBAL_SELECT_AN_OPTION');

			array_unshift($options, $tmp);

		reset($options);

		return $options;
	}

}