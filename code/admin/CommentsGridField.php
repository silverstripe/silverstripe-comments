<?php

class CommentsGridField extends GridField {
	/**
	 * {@inheritdoc}
	 */
	protected function newRow($total, $index, $record, $attributes, $content) {
		if(!isset($attributes['class'])) {
			$attributes['class'] = '';
		}

		if($record->IsSpam) {
			$attributes['class'] .= ' spam';
		}

		return FormField::create_tag(
			'tr',
			$attributes,
			$content
		);
	}
}