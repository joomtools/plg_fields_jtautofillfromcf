<?php
/**
 * @package      Joomla.Plugin
 * @subpackage   Fields.Jtautofillfromcf
 *
 * @author       Guido De Gobbis <support@joomtools.de>
 * @copyright    (c) 2019 JoomTools.de - All rights reserved.
 * @license      GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

JFormHelper::loadFieldClass('groupedlist');

class JFormFieldListcffields extends JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Listcffields';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   1.0.0
	 */
	protected function getGroups()
	{
		$userGroup    = JText::_('PLG_FIELDS_JTAUTOFILLFROMCF_COM_USER');
		$contentGroup = JText::_('PLG_FIELDS_JTAUTOFILLFROMCF_COM_CONTENT');
		$options      = array();
		$fields       = array(
			$userGroup    => FieldsHelper::getFields('com_users.user'),
			$contentGroup => FieldsHelper::getFields('com_content.article'),
		);

		foreach ($fields as $key => $values)
		{
			foreach ($values as $field)
			{
				$value = $field->id . ',' . $field->context;
				$text  = trim($field->label);

				$tmp = array(
					'value' => $value,
					'text'  => JText::_($text),
				);

				// Add the option object to the result set.
				$options[$key][] = (object) $tmp;
			}
		}

			$tmp        = new stdClass;
			$tmp->value = '';
			$tmp->text  = JText::_('JGLOBAL_SELECT_AN_OPTION');

			array_unshift($options, $tmp);

		reset($options);

		return $options;
	}

}