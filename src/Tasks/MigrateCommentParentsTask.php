<?php

namespace SilverStripe\Comments\Tasks;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Migrates all 3.x comment's BaseClass fields to the new ParentClass fields
 *
 * @package comments
 */
class MigrateCommentParentsTask extends BuildTask
{
    /**
     * {@inheritDoc}
     */
    private static $segment = 'MigrateCommentParentsTask';

    /**
     * {@inheritDoc}
     */
    protected $title = 'Migrate Comment Parent classes from 3.x';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Migrates all 3.x Comment BaseClass fields to the new ParentClass fields in 4.0';

    /**
     * {@inheritDoc}
     */
    public function run($request)
    {
        // Set the class names to fully qualified class names first
        $remapping = Config::inst()->get('SilverStripe\\ORM\\DatabaseAdmin', 'classname_value_remapping');
        $updateQuery = "UPDATE \"Comment\" SET \"BaseClass\" = ? WHERE \"BaseClass\" = ?";
        foreach ($remapping as $old => $new) {
            DB::prepared_query($updateQuery, [$new, $old]);
        }

        // Move these values to ParentClass (the 4.x column name)
        DB::query('UPDATE "Comment" SET "ParentClass" = "BaseClass"');
        DB::alteration_message('Finished updating any applicable Comment class columns', 'notice');
    }
}
