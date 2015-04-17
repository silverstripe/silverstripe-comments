<?php

class CommentsGridField extends GridField
{
	/**
	 * {@inheritdoc}
	 */
	protected function newRow($gridfield, $total, $index, $record, $attributes, $content) {
		$classes = array('ss-gridfield-item');

		if($index == 0) {
			$classes[] = 'first';
		}

		if($index == $total - 1) {
			$classes[] = 'last';
		}

		$classes[] = ($index % 2) ? 'even' : 'odd';

		if ($record->IsSpam) {
			$classes[] = 'spam';
		}

		$attributes = array(
			'class' => implode(' ', $classes),
			'data-id' => $record->ID,
			'data-class' => $record->ClassName,
		);

		return FormField::create_tag(
			'tr',
			$attributes,
			$content
		);
	}
}